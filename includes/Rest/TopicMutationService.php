<?php
/**
 * Starting a thread, over the API.
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
 * `bbp_new_topic_handler()`'s lifecycle, with a return value where its redirect was.
 *
 * ## Why the sequence is copied rather than delegated
 *
 * bbPress's handler cannot be called: it verifies a nonce, reads a dozen `$_POST` keys,
 * writes into a process-global error bag, and finishes by redirecting the browser. What
 * it *does* between those, though, is the forum's posting policy, and every hook it
 * fires is one an installed extension is entitled to see. So the order below is its
 * order, function for function, audited against bbPress 2.6.14 — and the tests exercise
 * the hooks rather than the outcome, because an outcome can be right while a count, an
 * engagement or a subscriber's mail silently is not.
 *
 * ⚠ **Never `bbp_insert_topic()` instead.** That is bbPress's *other* create path, for
 * importers, and it fires its own bookkeeping. Running it and then `bbp_new_topic` would
 * double every count and engagement the action updates.
 *
 * ## Three places this deliberately differs
 *
 * 1. **The first refusal wins.** The handler collects every complaint and redraws the
 *    form with all of them; a REST envelope carries one `code`.
 * 2. **No `unfiltered_html` escape.** The browser path lets a capable member post raw
 *    markup by sending a matching nonce, which the API has no equivalent of and does not
 *    invent. kses always applies here — the safe direction of that difference.
 * 3. **No `bbp_topic_status` override.** A moderator cannot name the status a new thread
 *    is created under; the API's create route has no such input, so the branch that
 *    reads it is absent along with the error it raises when it is not permitted.
 *
 * ## Slashed, from the first line
 *
 * `Rest\ContentGuard`, the content filters, `bbp_check_for_duplicate()` and
 * `wp_insert_post()` all expect what PHP hands a form: slashed. REST hands over a plain
 * string, so the title and content are slashed on the way in and the whole lifecycle
 * matches the browser's byte for byte.
 *
 * @since 0.6.0
 */
class TopicMutationService {

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
	 * Who may have this forum.
	 *
	 * @var AccessPolicy
	 * @since 0.6.0
	 */
	private AccessPolicy $access;

	/**
	 * What the site has switched on.
	 *
	 * @var FeatureGate
	 * @since 0.6.0
	 */
	private FeatureGate $features;

	/**
	 * Flood, duplicate and moderation.
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
	 * @param ContextInterface        $wp       WordPress/bbPress seam.
	 * @param RestContextInterface    $rest     REST-side WordPress/bbPress seam.
	 * @param AccessPolicy            $access   Who may have this forum.
	 * @param FeatureGate             $features What the site has switched on.
	 * @param ContentGuard            $guard    Flood, duplicate and moderation.
	 * @param BbpHookScope            $scope    Pre-save hook compatibility window.
	 * @param AkismetPreInsertAdapter $akismet  Pre-insert filter chain.
	 */
	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		AccessPolicy $access,
		FeatureGate $features,
		ContentGuard $guard,
		BbpHookScope $scope,
		AkismetPreInsertAdapter $akismet
	) {
		$this->wp       = $wp;
		$this->rest     = $rest;
		$this->access   = $access;
		$this->features = $features;
		$this->guard    = $guard;
		$this->scope    = $scope;
		$this->akismet  = $akismet;
	}

	/**
	 * Start a thread.
	 *
	 * @since 0.6.0
	 *
	 * @param int      $forum_id  Forum to start it in.
	 * @param int      $author_id Author, already known to be signed in.
	 * @param string   $title     Title, sanitized but not yet filtered.
	 * @param string   $content   Body, as the app sent it.
	 * @param string[] $tag_names Tag names, as the request carried them.
	 * @return MutationResult|\WP_Error
	 */
	public function create( int $forum_id, int $author_id, string $title, string $content, array $tag_names ) {
		$allowed = $this->may_start( $forum_id, $tag_names );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$tag_names = $this->guard->tags( $tag_names );

		if ( ! is_array( $tag_names ) ) {
			return $tag_names;
		}

		$title   = (string) $this->wp->apply_filters( 'bbp_new_topic_pre_title', $this->rest->slash( $title ) );
		$content = (string) $this->wp->apply_filters( 'bbp_new_topic_pre_content', $this->rest->slash( $content ) );
		$status  = $this->settle_status( $forum_id, $author_id, $title, $content );

		if ( ! is_string( $status ) ) {
			return $status;
		}

		$terms   = array_map( array( $this->rest, 'slash' ), $tag_names );
		$refused = $this->scope->run(
			'bbp_new_topic_pre_extras',
			array( $forum_id ),
			array(
				'bbp_topic_title'   => $title,
				'bbp_topic_content' => $content,
				'bbp_forum_id'      => (string) $forum_id,
				'bbp_topic_tags'    => implode( ',', $terms ),
			)
		);

		if ( true !== $refused ) {
			return $refused;
		}

		return $this->insert( $forum_id, $author_id, $title, $content, $status, $terms );
	}

	/**
	 * The gates bbPress's handler puts before anything is read off the form.
	 *
	 * ⚠ **The private and hidden tests are not repeated here**, though the browser
	 * handler writes them out. `Rest\AccessPolicy::forum()` has already asked
	 * `bbp_user_can_view_forum()` with `check_ancestors`, and that function answers the
	 * handler's two questions and nothing else: public-with-a-public-chain, or
	 * private-or-hidden-with `read_forum`. Measured, not assumed — removing the second
	 * copy changed no integration outcome, including for a public forum beneath a
	 * private parent, which is the case the ancestor walk exists for. A second
	 * implementation of a rule bbPress already owns is a second implementation to keep
	 * in step.
	 *
	 * @since 0.6.0
	 *
	 * @param int      $forum_id  Forum to start a thread in.
	 * @param string[] $tag_names Tags the request carried.
	 * @return true|\WP_Error
	 */
	private function may_start( int $forum_id, array $tag_names ) {
		if ( ! $this->wp->current_user_can( 'publish_topics' ) ) {
			return $this->refuse( 'forbidden', __( 'You cannot start threads.', 'jtzl-bulletin' ), 403 );
		}

		$allowed = $this->access->forum( $forum_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		if ( $this->wp->is_forum_category( $forum_id ) ) {
			return $this->refuse( 'forbidden', __( 'This forum is a category; threads cannot be started in it.', 'jtzl-bulletin' ), 403 );
		}

		if ( $this->wp->is_forum_closed( $forum_id ) && ! $this->rest->current_user_can_for( 'edit_forum', $forum_id ) ) {
			return $this->refuse( 'forbidden', __( 'This forum is closed to new threads.', 'jtzl-bulletin' ), 403 );
		}

		return array() === $tag_names ? true : $this->features->topic_tags();
	}

	/**
	 * The title, content and moderation questions, in the handler's order.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $forum_id  Forum the thread starts in.
	 * @param int    $author_id Author ID.
	 * @param string $title     Filtered title.
	 * @param string $content   Filtered content.
	 * @return string|\WP_Error The status to create under.
	 */
	private function settle_status( int $forum_id, int $author_id, string $title, string $content ) {
		$checked = $this->guard->title( $title, true );

		if ( true !== $checked ) {
			return $checked;
		}

		$checked = $this->guard->content( $content );

		if ( true !== $checked ) {
			return $checked;
		}

		return $this->guard->status( $author_id, $forum_id, $this->wp->get_topic_post_type(), $title, $content );
	}

	/**
	 * The pre-insert filter, the insert, and everything bbPress does after it.
	 *
	 * @since 0.6.0
	 *
	 * @param int      $forum_id  Forum the thread starts in.
	 * @param int      $author_id Author ID.
	 * @param string   $title     Filtered title.
	 * @param string   $content   Filtered content.
	 * @param string   $status    Status chosen by the moderation checks.
	 * @param string[] $terms     Slashed tag names.
	 * @return MutationResult|\WP_Error
	 */
	private function insert( int $forum_id, int $author_id, string $title, string $content, string $status, array $terms ) {
		$data = $this->akismet->apply(
			'bbp_new_topic_pre_insert',
			array(
				'post_author'    => $author_id,
				'post_title'     => $title,
				'post_content'   => $content,
				'post_status'    => $status,
				'post_parent'    => $forum_id,
				'post_type'      => $this->wp->get_topic_post_type(),
				'tax_input'      => array( $this->rest->topic_tag_taxonomy() => $terms ),
				'comment_status' => 'closed',
			)
		);

		// Discarded. Nothing was stored, no lifecycle hook ran, and the caller is told
		// only that the request was accepted.
		if ( null === $data ) {
			return MutationResult::accepted();
		}

		$topic_id = $this->rest->insert_post( $data );

		if ( ! is_int( $topic_id ) || $topic_id <= 0 ) {
			return $this->refuse( 'write_failed', __( 'The thread could not be saved.', 'jtzl-bulletin' ), 500 );
		}

		return $this->settle( $topic_id, $forum_id, $data );
	}

	/**
	 * Close, trash and spam bookkeeping, then the two actions that do everything else.
	 *
	 * ⚠ Both status tests are the handler's: the *stored* status or the *filtered* one
	 * for closing, and the **forum's** status or the filtered one for trashing. A
	 * pre-insert filter can change either, and reading only the row would miss it.
	 *
	 * @since 0.6.0
	 *
	 * @param int                 $topic_id Topic that was created.
	 * @param int                 $forum_id Forum it was created in.
	 * @param array<string,mixed> $data     Filtered post data.
	 * @return MutationResult
	 */
	private function settle( int $topic_id, int $forum_id, array $data ): MutationResult {
		$status = (string) ( $data['post_status'] ?? '' );
		$hidden = false;

		if ( $this->wp->get_closed_status_id() === $this->wp->get_post_status( $topic_id ) || $this->wp->get_closed_status_id() === $status ) {
			$this->rest->close_topic( $topic_id );
		}

		if ( $this->rest->get_trash_status_id() === $this->wp->get_post_status( $forum_id ) || $this->rest->get_trash_status_id() === $status ) {
			$this->rest->trash_post( $topic_id );
			$hidden = true;
		}

		if ( $this->rest->get_spam_status_id() === $status ) {
			$this->rest->mark_spam_meta_status( $topic_id );
			$hidden = true;
		}

		$this->rest->fire_new_topic( $topic_id, $forum_id, (int) ( $data['post_author'] ?? 0 ) );
		$this->rest->fire_post_extras( 'bbp_new_topic_post_extras', $topic_id );

		return $hidden ? MutationResult::accepted() : MutationResult::entity( $topic_id );
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
