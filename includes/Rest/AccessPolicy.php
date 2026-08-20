<?php
/**
 * Who may read what, over the API.
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
 * The one place a singular route asks whether this reader may have this thing.
 *
 * ## Missing and forbidden are the same answer
 *
 * A resource this reader may not know about returns 404, identical to one that does
 * not exist — same code, same message, same status. A 403 would confirm the ID names
 * something real, and enumerating a private forum's topics one ID at a time is exactly
 * what that confirmation buys.
 *
 * The one deliberate exception is a stored password, which returns `password_required`
 * with 403. That is not a leak: a protected forum is *advertised* by the website, its
 * existence is public, and the app has to be able to tell "there is nothing here" from
 * "there is something here you can unlock elsewhere".
 *
 * ## Fail-closed order
 *
 * Every method asks in the same order, and the order is the design: does the row exist
 * and is it the kind we were asked for; may this reader see its container; is its own
 * status one they may read; and only then, is it locked. Each step can only ever
 * narrow — none of them can hand back something an earlier step refused.
 *
 * ## Pending is author-only, and asked explicitly
 *
 * A held post is readable by whoever wrote it and by nobody else — not by another
 * member, and not by a moderator either. That is deliberately narrower than bbPress's
 * own moderation view: this class answers for the *app*, which has no moderation
 * surface in v1, and a status list that happened to include `pending` for a capable
 * reader would quietly widen it. So the author branch is written out rather than
 * folded into a status list.
 *
 * ## Editing is authorship, not moderation
 *
 * `can_edit_*` is the same three-gate policy the website's byline control uses
 * (View\AuthorEdit): not offered while the reader is a moderator of the thread, only
 * on a publicly visible post, and only when bbPress itself will issue an edit link —
 * which is where the capability, the edit window and content-editing being enabled are
 * all decided. This class adds one gate the website gets for free from that link: the
 * reader must actually be the author. A moderator editing somebody else's post is a
 * moderation act, and v1 has no moderation.
 *
 * @since 0.6.0
 */
class AccessPolicy {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * REST seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.0
	 */
	private RestContextInterface $rest;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface     $wp   WordPress/bbPress seam.
	 * @param RestContextInterface $rest REST seam.
	 */
	public function __construct( ContextInterface $wp, RestContextInterface $rest ) {
		$this->wp   = $wp;
		$this->rest = $rest;
	}

	/**
	 * May this reader have this forum?
	 *
	 * @since 0.6.0
	 *
	 * @param int $id Forum ID.
	 * @return true|\WP_Error
	 */
	public function forum( int $id ) {
		$post = $this->post_of_type( $id, $this->wp->get_forum_post_type() );

		if ( null === $post || ! in_array( $post->post_status, $this->rest->forum_post_statuses(), true ) ) {
			return $this->not_found();
		}

		if ( ! $this->wp->user_can_view_forum( $id ) ) {
			return $this->not_found();
		}

		return $this->rest->has_password_in_forum_chain( $id ) ? $this->password_required() : true;
	}

	/**
	 * May this reader have this topic?
	 *
	 * @since 0.6.0
	 *
	 * @param int $id Topic ID.
	 * @return true|\WP_Error
	 */
	public function topic( int $id ) {
		$post = $this->post_of_type( $id, $this->wp->get_topic_post_type() );

		if ( null === $post ) {
			return $this->not_found();
		}

		$forum_id = $this->wp->get_topic_forum_id( $id );

		if ( ! $this->wp->user_can_view_forum( $forum_id ) || ! $this->readable( $post, $this->wp->get_readable_topic_statuses() ) ) {
			return $this->not_found();
		}

		if ( $this->rest->has_stored_password( $id ) || $this->rest->has_password_in_forum_chain( $forum_id ) ) {
			return $this->password_required();
		}

		return true;
	}

	/**
	 * May this reader have this reply?
	 *
	 * The thread is asked first and its answer is returned unchanged, so a reply
	 * inherits both its topic's 404 and its 403. ⚠ A reply whose topic no longer
	 * exists — an import artefact — resolves to a topic ID of 0 and is refused. The
	 * website is more forgiving about orphans in a list; a singular API route is not
	 * the place to be, because there is nothing to inherit visibility from.
	 *
	 * @since 0.6.0
	 *
	 * @param int $id Reply ID.
	 * @return true|\WP_Error
	 */
	public function reply( int $id ) {
		$post = $this->post_of_type( $id, $this->wp->get_reply_post_type() );

		if ( null === $post ) {
			return $this->not_found();
		}

		$topic = $this->topic( $this->wp->get_reply_topic_id( $id ) );

		if ( true !== $topic ) {
			return $topic;
		}

		return $this->readable( $post, $this->wp->get_public_reply_statuses() ) ? true : $this->not_found();
	}

	/**
	 * May a new or edited reply point at this one?
	 *
	 * One error for every way it can be wrong — the target does not exist, belongs to
	 * another thread, is not readable, or is the post itself. Telling those apart
	 * would answer "does reply 4211 exist?" for anyone willing to write a reply.
	 *
	 * @since 0.6.0
	 *
	 * @param int $target_id Reply being pointed at.
	 * @param int $topic_id  Thread the pointing reply belongs to.
	 * @param int $self_id   The pointing reply, when it already exists.
	 * @return true|\WP_Error
	 */
	public function reply_to( int $target_id, int $topic_id, int $self_id = 0 ) {
		$same_topic = $target_id > 0 && $this->wp->get_reply_topic_id( $target_id ) === $topic_id;

		if ( ! $same_topic || $target_id === $self_id || true !== $this->reply( $target_id ) ) {
			return new \WP_Error(
				'invalid_reply_to',
				__( 'That reply cannot be answered.', 'jtzl-bulletin' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * May this reader edit this topic, as its author?
	 *
	 * @since 0.6.0
	 *
	 * @param int $id Topic ID.
	 * @return bool
	 */
	public function can_edit_topic( int $id ): bool {
		return $this->author_may_edit( $id, $id, $this->wp->get_public_topic_statuses() )
			&& '' !== $this->wp->get_topic_edit_link( $id );
	}

	/**
	 * May this reader edit this reply, as its author?
	 *
	 * The moderation gate is asked of the *thread*, exactly as View\AuthorEdit asks
	 * it: moderation is a property of the thread being read, and a per-post answer
	 * would offer the control under some of a moderator's own posts and not others.
	 *
	 * @since 0.6.0
	 *
	 * @param int $id Reply ID.
	 * @return bool
	 */
	public function can_edit_reply( int $id ): bool {
		return $this->author_may_edit( $this->wp->get_reply_topic_id( $id ), $id, $this->wp->get_public_reply_statuses() )
			&& '' !== $this->wp->get_reply_edit_link( $id );
	}

	/**
	 * The gates that are ours, asked before bbPress is asked for a link.
	 *
	 * @since 0.6.0
	 *
	 * @param int      $topic_id Thread, for the moderation test.
	 * @param int      $post_id  Post whose authorship and status are tested.
	 * @param string[] $statuses Public statuses for that kind of post.
	 * @return bool
	 */
	private function author_may_edit( int $topic_id, int $post_id, array $statuses ): bool {
		$user_id = $this->wp->get_current_user_id();

		return $user_id > 0
			&& $user_id === $this->wp->get_post_author( $post_id )
			&& ! $this->wp->current_user_can_moderate( $topic_id )
			&& in_array( $this->wp->get_post_status( $post_id ), $statuses, true );
	}

	/**
	 * Whether a post's own status is one this reader may read.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_Post $post     Post to test.
	 * @param string[] $statuses Statuses readable without being the author.
	 * @return bool
	 */
	private function readable( \WP_Post $post, array $statuses ): bool {
		if ( $this->wp->get_pending_status_id() === $post->post_status ) {
			$user_id = $this->wp->get_current_user_id();

			return $user_id > 0 && $user_id === (int) $post->post_author;
		}

		return in_array( $post->post_status, $statuses, true );
	}

	/**
	 * A post of the expected type, or null.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $id   Post ID.
	 * @param string $type Expected post type.
	 * @return \WP_Post|null
	 */
	private function post_of_type( int $id, string $type ): ?\WP_Post {
		$post = $this->rest->get_post( $id );

		return null !== $post && $type === $post->post_type ? $post : null;
	}

	/**
	 * The answer for everything a reader may not know about.
	 *
	 * @since 0.6.0
	 *
	 * @return \WP_Error
	 */
	private function not_found(): \WP_Error {
		return new \WP_Error(
			'not_found',
			__( 'No such resource.', 'jtzl-bulletin' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * The answer for content behind a password.
	 *
	 * @since 0.6.0
	 *
	 * @return \WP_Error
	 */
	private function password_required(): \WP_Error {
		return new \WP_Error(
			'password_required',
			__( 'A password is required to read this content.', 'jtzl-bulletin' ),
			array( 'status' => 403 )
		);
	}
}
