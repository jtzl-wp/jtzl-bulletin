<?php
/**
 * Editing a reply, over the API.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * `bbp_edit_reply_handler()`'s lifecycle, with a return value where its redirect was.
 *
 * The sibling of Rest\TopicEditService, and separate from Rest\ReplyMutationService for
 * the reason given there: bbPress keeps creating and editing apart because they are two
 * policies rather than one with a flag.
 *
 * ## What an author may change, and what they may not
 *
 * The body, and nothing else. The thread, the forum, the author, the stored status and
 * the reply being answered are all read back from the post and written out again
 * unchanged, so a PATCH cannot move a reply between threads or re-point it at a
 * different parent. bbPress lets a **moderator** re-point one by posting `bbp_reply_to`;
 * that is moderation, and this route is author-only.
 *
 * ⚠ **The title is filtered and stored as an empty string, which is bbPress's own
 * behaviour and not an oversight.** Its handler reads `$_POST['bbp_reply_title']`, and
 * `form-reply.php` has no such field — so a browser edit writes an empty title over
 * whatever was there. Preserving it here instead would make the same edit differ by
 * transport: a reply imported with a title would keep it through the app and lose it
 * through the website. The filter still runs, because an extension hooked on it is
 * entitled to, and the length check behind it is bbPress's.
 *
 * @since 0.6.0
 */
class ReplyEditService {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * REST-side WordPress/bbPress seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.0
	 */
	private RestContextInterface $rest;

	/**
	 * Title, content and moderation.
	 *
	 * @var ContentGuard
	 * @since 0.6.0
	 */
	private ContentGuard $guard;

	/**
	 * The window a form-shaped hook runs inside.
	 *
	 * @var BbpHookScope
	 * @since 0.6.0
	 */
	private BbpHookScope $scope;

	/**
	 * The pre-insert chain, run without letting Akismet end the request.
	 *
	 * @var AkismetPreInsertAdapter
	 * @since 0.6.0
	 */
	private AkismetPreInsertAdapter $akismet;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface        $wp      WordPress/bbPress seam.
	 * @param RestContextInterface    $rest    REST-side WordPress/bbPress seam.
	 * @param ContentGuard            $guard   Title, content and moderation.
	 * @param BbpHookScope            $scope   Pre-save hook compatibility window.
	 * @param AkismetPreInsertAdapter $akismet Pre-insert filter chain.
	 */
	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		ContentGuard $guard,
		BbpHookScope $scope,
		AkismetPreInsertAdapter $akismet
	) {
		$this->wp      = $wp;
		$this->rest    = $rest;
		$this->guard   = $guard;
		$this->scope   = $scope;
		$this->akismet = $akismet;
	}

	/**
	 * Edit a reply, as its author.
	 *
	 * @since 0.6.0
	 *
	 * @param int                 $reply_id  Reply being edited.
	 * @param int                 $author_id Editor, already known to be its author.
	 * @param array<string,mixed> $changes   Fields the request actually carried.
	 * @return MutationResult|\WP_Error
	 */
	public function update( int $reply_id, int $author_id, array $changes ) {
		// ⚠ **No merge, and no stored-body fallback.** Rest\TopicEditService reads what a
		// PATCH left out because a thread has two editable fields and either may be
		// omitted; a reply has one, so "omitted" and "empty request" are the same request
		// and there is nothing to merge with. A fallback here would be a branch no route
		// can reach.
		if ( ! array_key_exists( 'content', $changes ) ) {
			return $this->refuse( 'invalid_request', __( 'An edit has to change something.', 'jtzl-bulletin' ), 400 );
		}

		$body = $changes['content'];

		// Untitled and filtered anyway; see the class docblock.
		$title   = (string) $this->wp->apply_filters( 'bbp_edit_reply_pre_title', '', $reply_id );
		$content = (string) $this->wp->apply_filters(
			'bbp_edit_reply_pre_content',
			$this->rest->slash( is_scalar( $body ) ? (string) $body : '' ),
			$reply_id
		);
		$status  = $this->settle_status( $reply_id, $author_id, $title, $content );

		if ( ! is_string( $status ) ) {
			return $status;
		}

		$topic_id = $this->wp->get_reply_topic_id( $reply_id );
		$refused  = $this->scope->run(
			'bbp_edit_reply_pre_extras',
			array( $reply_id ),
			array(
				'bbp_reply_id'      => (string) $reply_id,
				'bbp_topic_id'      => (string) $topic_id,
				'bbp_reply_content' => $content,
			)
		);

		if ( true !== $refused ) {
			return $refused;
		}

		return $this->save( $reply_id, $topic_id, $title, $content, $status );
	}

	/**
	 * The title, content and moderation questions, in the handler's order.
	 *
	 * ⚠ The title is checked with `$required` false — a reply's may be empty, and here
	 * always is. Only the length rule behind it applies.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $reply_id  Reply being edited.
	 * @param int    $author_id Author ID.
	 * @param string $title     Filtered title.
	 * @param string $content   Merged, filtered content.
	 * @return string|\WP_Error The status to store under.
	 */
	private function settle_status( int $reply_id, int $author_id, string $title, string $content ) {
		$checked = $this->guard->title( $title, false );

		if ( true !== $checked ) {
			return $checked;
		}

		$checked = $this->guard->content( $content );

		if ( true !== $checked ) {
			return $checked;
		}

		return $this->guard->edited_status( $author_id, $title, $content, $this->wp->get_post_status( $reply_id ) );
	}

	/**
	 * The pre-insert filter, the update, and everything bbPress does after it.
	 *
	 * ⚠ **The thread's tags are re-set on every reply edit, unchanged.** That looks
	 * redundant and is not: `bbp_edit_reply_pre_set_terms` is the hook Akismet uses to
	 * put back the terms it stashed when a reply was spammed, so a lifecycle that skipped
	 * the write because nothing had changed would drop them. bbPress writes them
	 * unconditionally; so does this.
	 *
	 * ⚠ **A tag write that fails is not a failed edit.** The reply is already stored, and
	 * refusing the response now would tell the member their edit was rejected when it is
	 * sitting in the thread. bbPress notes it and carries on, and so does this.
	 *
	 * ⚠ **The stored pointer is always passed on, and this one deliberately differs from
	 * the browser.** bbPress's handler reads `bbp_get_reply_to()` only inside
	 * `elseif ( bbp_thread_replies() )`, so with threading switched off it passes the 0 it
	 * initialised the variable to — and that value is not inert. `bbp_update_reply()` is
	 * hooked to `bbp_edit_reply` and hands it to `bbp_update_reply_to()`, which
	 * **deletes `_bbp_reply_to`** when it is empty. Measured: on a site with threading
	 * off, every browser edit silently flattens the reply it touches, permanently, and
	 * turning threading back on cannot recover the tree.
	 *
	 * This route has no field that could ask for that. An edit here carries a body and
	 * nothing else, so passing 0 would destroy a relationship the request never mentioned
	 * — a worse failure than disagreeing with the website about a value that, with
	 * threading off, nothing reads. The pointer is read back and passed on whatever the
	 * setting says.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $reply_id Reply being edited.
	 * @param int    $topic_id Thread it answers.
	 * @param string $title    Filtered title.
	 * @param string $content  Merged, filtered content.
	 * @param string $status   Status settled by the moderation checks.
	 * @return MutationResult|\WP_Error
	 */
	private function save( int $reply_id, int $topic_id, string $title, string $content, string $status ) {
		$data = $this->akismet->apply(
			'bbp_edit_reply_pre_insert',
			array(
				'ID'           => $reply_id,
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => $status,
				'post_parent'  => $topic_id,
				'post_author'  => $this->wp->get_post_author( $reply_id ),
				'post_type'    => $this->wp->get_reply_post_type(),
			)
		);

		// Discarded. Nothing was written, no lifecycle hook ran, and the caller is told
		// only that the request was accepted.
		if ( null === $data ) {
			return MutationResult::accepted();
		}

		$updated = $this->rest->update_post( $data );

		if ( ! is_int( $updated ) || $updated <= 0 ) {
			return $this->refuse( 'write_failed', __( 'The reply could not be saved.', 'jtzl-bulletin' ), 500 );
		}

		$terms = $this->rest->get_topic_tag_names( $topic_id );
		$this->rest->set_topic_tags( $topic_id, $this->rest->filter_edit_reply_terms( $terms, $topic_id, $reply_id ) );

		$this->rest->fire_edit_reply(
			$reply_id,
			$topic_id,
			$this->wp->get_topic_forum_id( $topic_id ),
			(int) ( $data['post_author'] ?? 0 ),
			$this->wp->get_reply_to( $reply_id )
		);
		$this->rest->record_edit( $reply_id );
		$this->rest->fire_post_extras( 'bbp_edit_reply_post_extras', $reply_id );

		$stored = (string) ( $data['post_status'] ?? '' );

		return $this->rest->get_trash_status_id() === $stored || $this->rest->get_spam_status_id() === $stored
			? MutationResult::accepted()
			: MutationResult::entity( $reply_id );
	}

	/**
	 * One refusal, built the one way.
	 *
	 * @since 0.6.0
	 *
	 * @param string $code    Stable REST error code.
	 * @param string $message Message, carrying no markup.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	private function refuse( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
