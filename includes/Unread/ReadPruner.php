<?php
/**
 * Removing read state about things that no longer exist.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\Unread;

use JTZL\Bulletin\WordPress\ContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Deletes read rows whose topic or whose member has been deleted.
 *
 * **This is the whole of pruning, and deliberately so.** The obvious alternatives —
 * expire rows after N months, keep only a member's newest N — both work by declaring
 * topics read that the member never opened, which is exactly the behaviour the
 * feature was specified against (a topic is unread until it has been opened). A
 * bounded table is not worth a wrong dot, so the table is bounded by reality instead:
 * it can only hold rows about topics that exist and members who exist.
 *
 * That leaves growth proportional to threads actually read, which is the same order
 * as bbPress's own favourites and subscriptions. If it ever stops being acceptable at
 * .org scale, that is a decision with a number attached, not a policy to slip in
 * here.
 *
 * @since 0.5.0
 */
class ReadPruner {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.5.0
	 */
	private ContextInterface $wp;

	/**
	 * Read state.
	 *
	 * @var ReadState
	 * @since 0.5.0
	 */
	private ReadState $reads;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param ContextInterface $wp    WordPress/bbPress seam.
	 * @param ReadState        $reads Read state.
	 */
	public function __construct( ContextInterface $wp, ReadState $reads ) {
		$this->wp    = $wp;
		$this->reads = $reads;
	}

	/**
	 * Forget a deleted topic.
	 *
	 * Hooked on `deleted_post`, which fires for every post type once the row is
	 * actually gone. bbPress's own `bbp_delete_topic` fires *before* deletion and can
	 * be short-circuited, which would leave us deleting read state for a topic that
	 * then survived.
	 *
	 * ⚠ **The type is read off the post object the hook passes, not looked up.** By
	 * the time `deleted_post` runs, the row is gone and `clean_post_cache()` has not
	 * fired yet, so `get_post_type()` is answering from a cache that is about to be
	 * invalidated. It happens to be right today; it is right by luck, and the day an
	 * object cache or a reordering makes it return false, pruning stops happening and
	 * nothing says so. WordPress hands over the post it just deleted — using it costs
	 * one extra accepted arg and depends on no timing at all.
	 *
	 * Trashing is not deletion and is not handled: a trashed topic can be restored,
	 * and throwing away what everyone had read of it would be destroying data to
	 * tidy a table.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $post_id Deleted post ID.
	 * @param mixed $post    The post object WordPress has just deleted.
	 */
	public function forget_deleted_topic( $post_id, $post = null ): void {
		$post_id = (int) $post_id;
		$type    = is_object( $post ) ? (string) ( $post->post_type ?? '' ) : '';

		if ( $post_id <= 0 || $type !== $this->wp->get_topic_post_type() ) {
			return;
		}

		$this->reads->forget_topic( $post_id );
	}

	/**
	 * Forget a deleted member.
	 *
	 * Hooked on `deleted_user`. WordPress reassigns a deleted member's *posts* on
	 * request, but read state is not authorship — it says what one person had seen,
	 * and it means nothing once that person is gone.
	 *
	 * @since 0.5.0
	 *
	 * @param mixed $user_id Deleted user ID.
	 */
	public function forget_deleted_user( $user_id ): void {
		$this->reads->forget_user( (int) $user_id );
	}
}
