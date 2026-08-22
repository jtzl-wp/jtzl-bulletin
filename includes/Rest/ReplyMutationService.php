<?php
/**
 * Answering a thread, over the API.
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
 * `bbp_new_reply_handler()`'s lifecycle, with a return value where its redirect was.
 *
 * The sibling of Rest\TopicMutationService, and everything said there about copying the
 * handler's order rather than calling it applies here unchanged. What differs is what a
 * reply *is*, and the differences are all after the insert.
 *
 * ## The post-insert stretch is the whole reason this is not shared code
 *
 * A new topic is created and then the site is told about it. A new reply, in bbPress, is
 * created and then five more things happen — and each of them is load-bearing:
 *
 * - **The topic's tags are re-set through `bbp_new_reply_pre_set_terms`.** The API takes
 *   no tags on a reply, so the value passed is the topic's *existing* tag names and the
 *   write is a no-op. ⚠ Skipping it anyway would break Akismet, which hooks that filter
 *   when it marks a reply as spam so the reply's terms can be restored if it is later
 *   hammed.
 * - **A reply whose status a filter turned to trash or spam is hidden accordingly**, and
 *   answered with the acknowledgement rather than the row.
 * - **`bbp_new_reply` fires with seven arguments**, including the reply-to, which is what
 *   carries threading, counts, voices, engagements and subscriber notification.
 * - **`bbp_new_reply_post_extras`** gives extensions the same last word they get on the
 *   website.
 *
 * ## The order of the gates is bbPress's order
 *
 * In particular, `reply_to` and the closed-topic test are asked *after* the flood,
 * duplicate and moderation checks — so a member answering a closed thread with content
 * that trips the disallowed list is refused for the content, exactly as they would be on
 * the website. Reordering them for tidiness would change which of two true things the
 * API says.
 *
 * ⚠ **The closed-topic gate is `moderate`, not authorship.** bbPress lets a moderator
 * answer a closed thread and nobody else, its own author included — which is the defect
 * P4's last PR closed on the website, where a composer was being offered to somebody
 * bbPress would then refuse.
 *
 * @since 0.6.0
 */
class ReplyMutationService {

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
	 * Who may have this thread, and which post it may answer.
	 *
	 * @var AccessPolicy
	 * @since 0.6.0
	 */
	private AccessPolicy $access;

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
	 * @param ContextInterface        $wp      WordPress/bbPress seam.
	 * @param RestContextInterface    $rest    REST-side WordPress/bbPress seam.
	 * @param AccessPolicy            $access  Who may have this thread.
	 * @param ContentGuard            $guard   Flood, duplicate and moderation.
	 * @param BbpHookScope            $scope   Pre-save hook compatibility window.
	 * @param AkismetPreInsertAdapter $akismet Pre-insert filter chain.
	 */
	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		AccessPolicy $access,
		ContentGuard $guard,
		BbpHookScope $scope,
		AkismetPreInsertAdapter $akismet
	) {
		$this->wp      = $wp;
		$this->rest    = $rest;
		$this->access  = $access;
		$this->guard   = $guard;
		$this->scope   = $scope;
		$this->akismet = $akismet;
	}

	/**
	 * Answer a thread.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $topic_id  Thread being answered.
	 * @param int    $author_id Author, already known to be signed in.
	 * @param string $content   Body, as the app sent it.
	 * @param int    $reply_to  Reply being answered, or 0 for the thread itself.
	 * @return MutationResult|\WP_Error
	 */
	public function create( int $topic_id, int $author_id, string $content, int $reply_to ) {
		$allowed = $this->may_reply( $topic_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		// Untitled, and filtered anyway: the browser form has a title field the mobile
		// composer never renders, so the value is always empty — but an extension hooked
		// on the filter is entitled to run, and the length check behind it is bbPress's.
		$title   = (string) $this->wp->apply_filters( 'bbp_new_reply_pre_title', '' );
		$content = (string) $this->wp->apply_filters( 'bbp_new_reply_pre_content', $this->rest->slash( $content ) );
		$status  = $this->settle_status( $topic_id, $author_id, $title, $content );

		if ( ! is_string( $status ) ) {
			return $status;
		}

		$allowed = $this->may_answer( $topic_id, $reply_to );

		if ( true !== $allowed ) {
			return $allowed;
		}

		return $this->persist( $topic_id, $author_id, $reply_to, $status, $content, $title );
	}

	/**
	 * The gates bbPress's handler puts before anything is read off the form.
	 *
	 * ⚠ **The private and hidden tests are not repeated here**, for the reason given in
	 * Rest\TopicMutationService::may_start(): `Rest\AccessPolicy::topic()` has already
	 * asked `bbp_user_can_view_forum()` with `check_ancestors`, which answers the
	 * handler's two questions and nothing else.
	 *
	 * @since 0.6.0
	 *
	 * @param int $topic_id Thread being answered.
	 * @return true|\WP_Error
	 */
	private function may_reply( int $topic_id ) {
		if ( ! $this->wp->current_user_can( 'publish_replies' ) ) {
			return $this->refuse( 'forbidden', __( 'You cannot post replies.', 'jtzl-bulletin' ), 403 );
		}

		$allowed = $this->access->topic( $topic_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$forum_id = $this->wp->get_topic_forum_id( $topic_id );

		if ( $this->wp->is_forum_category( $forum_id ) ) {
			return $this->refuse( 'forbidden', __( 'This forum is a category; replies cannot be posted in it.', 'jtzl-bulletin' ), 403 );
		}

		if ( $this->wp->is_forum_closed( $forum_id ) && ! $this->rest->current_user_can_for( 'edit_forum', $forum_id ) ) {
			return $this->refuse( 'forbidden', __( 'This forum is closed to new replies.', 'jtzl-bulletin' ), 403 );
		}

		return true;
	}

	/**
	 * The two gates bbPress asks after the content has been judged.
	 *
	 * @since 0.6.0
	 *
	 * @param int $topic_id Thread being answered.
	 * @param int $reply_to Reply being answered, or 0.
	 * @return true|\WP_Error
	 */
	private function may_answer( int $topic_id, int $reply_to ) {
		if ( $reply_to > 0 ) {
			$allowed = $this->access->reply_to( $reply_to, $topic_id );

			if ( true !== $allowed ) {
				return $allowed;
			}
		}

		return $this->wp->is_topic_closed( $topic_id ) && ! $this->wp->current_user_can_moderate( $topic_id )
			? $this->refuse( 'forbidden', __( 'This thread is closed.', 'jtzl-bulletin' ), 403 )
			: true;
	}

	/**
	 * The title, content and moderation questions, in the handler's order.
	 *
	 * ⚠ A reply into a *held* thread is held itself, whatever the moderation keys say.
	 * The author of a pending thread can read it back and answer it, and an answer that
	 * went public under a thread nobody else can see would be a reply with no readable
	 * parent.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $topic_id  Thread being answered.
	 * @param int    $author_id Author ID.
	 * @param string $title     Filtered title.
	 * @param string $content   Filtered content.
	 * @return string|\WP_Error The status to create under.
	 */
	private function settle_status( int $topic_id, int $author_id, string $title, string $content ) {
		$checked = $this->guard->title( $title, false );

		if ( true !== $checked ) {
			return $checked;
		}

		$checked = $this->guard->content( $content );

		if ( true !== $checked ) {
			return $checked;
		}

		$status = $this->guard->status( $author_id, $topic_id, $this->wp->get_reply_post_type(), $title, $content );

		if ( ! is_string( $status ) ) {
			return $status;
		}

		return $this->wp->get_pending_status_id() === $this->wp->get_post_status( $topic_id )
			? $this->wp->get_pending_status_id()
			: $status;
	}

	/**
	 * The pre-insert filter, the insert, and everything bbPress does after it.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $topic_id  Thread being answered.
	 * @param int    $author_id Author ID.
	 * @param int    $reply_to  Reply being answered, or 0.
	 * @param string $status    Status chosen by the moderation checks.
	 * @param string $content   Filtered content.
	 * @param string $title     Filtered title.
	 * @return MutationResult|\WP_Error
	 */
	private function persist( int $topic_id, int $author_id, int $reply_to, string $status, string $content, string $title ) {
		$forum_id = $this->wp->get_topic_forum_id( $topic_id );

		// Read before the pre-save hooks run, exactly where bbPress reads it.
		$terms   = $this->rest->get_topic_tag_names( $topic_id );
		$refused = $this->scope->run(
			'bbp_new_reply_pre_extras',
			array( $topic_id, $forum_id ),
			array(
				'bbp_topic_id'      => (string) $topic_id,
				'bbp_forum_id'      => (string) $forum_id,
				'bbp_reply_content' => $content,
				'bbp_reply_to'      => (string) $reply_to,
			)
		);

		if ( true !== $refused ) {
			return $refused;
		}

		$data = $this->akismet->apply(
			'bbp_new_reply_pre_insert',
			array(
				'post_author'    => $author_id,
				'post_title'     => $title,
				'post_content'   => $content,
				'post_status'    => $status,
				'post_parent'    => $topic_id,
				'post_type'      => $this->wp->get_reply_post_type(),
				'comment_status' => 'closed',
				'menu_order'     => $this->wp->get_topic_reply_count( $topic_id ) + 1,
			)
		);

		// Discarded. Nothing was stored, no lifecycle hook ran, and the caller is told
		// only that the request was accepted.
		if ( null === $data ) {
			return MutationResult::accepted();
		}

		$reply_id = $this->rest->insert_post( $data );

		if ( ! is_int( $reply_id ) || $reply_id <= 0 ) {
			return $this->refuse( 'write_failed', __( 'The reply could not be saved.', 'jtzl-bulletin' ), 500 );
		}

		return $this->settle( $reply_id, $topic_id, $forum_id, $reply_to, $terms, $data );
	}

	/**
	 * Every post-insert step the browser handler runs, in its order.
	 *
	 * ⚠ A tag write that fails is not a failed reply. bbPress notes it and carries on,
	 * because the reply already exists and refusing the response now would tell the
	 * member their post was rejected when it is sitting in the thread. The same choice is
	 * made here for the same reason.
	 *
	 * ⚠ **What bbPress does here and this does not:** append the new reply to the topic's
	 * `_bbp_pre_trashed_replies` / `_bbp_pre_spammed_replies`. Those lists exist so that
	 * restoring a topic does not restore replies that were never public — and they are
	 * only ever written when the *topic* is already trash or spam, which over this API
	 * cannot happen: `Rest\AccessPolicy::topic()` refuses a thread whose status is not
	 * public or closed, for everybody, moderators included. Carrying the branch would be
	 * carrying code no request can reach. If that gate ever widens, this is the
	 * bookkeeping that has to come back with it.
	 *
	 * @since 0.6.0
	 *
	 * @param int                 $reply_id Reply that was created.
	 * @param int                 $topic_id Thread it answers.
	 * @param int                 $forum_id Forum that thread sits in.
	 * @param int                 $reply_to Reply it answers, or 0.
	 * @param string              $terms    The topic's tags as they were before the write.
	 * @param array<string,mixed> $data     Filtered post data.
	 * @return MutationResult
	 */
	private function settle( int $reply_id, int $topic_id, int $forum_id, int $reply_to, string $terms, array $data ): MutationResult {
		$this->rest->set_topic_tags( $topic_id, $this->rest->filter_new_reply_terms( $terms, $topic_id, $reply_id ) );

		$topic_status = $this->wp->get_post_status( $topic_id );
		$status       = (string) ( $data['post_status'] ?? '' );
		$trash        = $this->rest->get_trash_status_id();
		$spam         = $this->rest->get_spam_status_id();
		$hidden       = true;

		if ( $trash === $topic_status || $trash === $status ) {
			$this->rest->trash_post( $reply_id );
		} elseif ( $spam === $topic_status || $spam === $status ) {
			$this->rest->mark_spam_meta_status( $reply_id );
		} else {
			$hidden = false;
		}

		$this->rest->fire_new_reply( $reply_id, $topic_id, $forum_id, (int) ( $data['post_author'] ?? 0 ), $reply_to );
		$this->rest->fire_post_extras( 'bbp_new_reply_post_extras', $reply_id );

		return $hidden ? MutationResult::accepted() : MutationResult::entity( $reply_id );
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
