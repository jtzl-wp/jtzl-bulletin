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
 */
class AccessPolicy {

	private ContextInterface $wp;

	private RestContextInterface $rest;

	public function __construct( ContextInterface $wp, RestContextInterface $rest ) {
		$this->wp   = $wp;
		$this->rest = $rest;
	}

	/**
	 * Data contract.
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
	 * Data contract.
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
	 * Data contract.
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
	 * Missing and inaccessible resources share the same refusal where disclosure would reveal their existence.
	 *
	 * @param int $id User ID.
	 * @return true|\WP_Error
	 */
	public function user( int $id ) {
		return $id > 0 && null !== $this->rest->get_user( $id ) ? true : $this->not_found();
	}

	/**
	 * Data contract.
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
	 * Authorship, then bbPress's own answer.
	 *
	 * @since 0.6.3 A moderator's own post is editable; the role no longer refuses it.
	 *
	 * @param int $id Topic ID.
	 * @return bool
	 */
	public function can_edit_topic( int $id ): bool {
		return $this->author_may_edit( $id, $this->wp->get_public_topic_statuses() )
			&& '' !== $this->wp->get_topic_edit_link( $id );
	}

	/**
	 * Authorship, then bbPress's own answer.
	 *
	 * @since 0.6.3 A moderator's own post is editable; the role no longer refuses it.
	 *
	 * @param int $id Reply ID.
	 * @return bool
	 */
	public function can_edit_reply( int $id ): bool {
		return $this->author_may_edit( $id, $this->wp->get_public_reply_statuses() )
			&& '' !== $this->wp->get_reply_edit_link( $id );
	}

	/**
	 * The author check is load-bearing for a moderator: bbPress's edit-link
	 * function skips its own guards for anyone holding `edit_others_*`, so this
	 * is what keeps a keymaster off other people's posts and off IDs nobody holds.
	 *
	 * @param int      $post_id  Post whose authorship and status are tested.
	 * @param string[] $statuses Public statuses for that kind of post.
	 * @return bool
	 */
	private function author_may_edit( int $post_id, array $statuses ): bool {
		$user_id = $this->wp->get_current_user_id();

		return $user_id > 0
			&& $user_id === $this->wp->get_post_author( $post_id )
			&& in_array( $this->wp->get_post_status( $post_id ), $statuses, true );
	}

	/**
	 * Data contract.
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
	 * Data contract.
	 *
	 * @param int    $id   Post ID.
	 * @param string $type Expected post type.
	 * @return \WP_Post|null
	 */
	private function post_of_type( int $id, string $type ): ?\WP_Post {
		$post = $this->rest->get_post( $id );

		return null !== $post && $type === $post->post_type ? $post : null;
	}

	private function not_found(): \WP_Error {
		return new \WP_Error(
			'not_found',
			__( 'No such resource.', 'jtzl-bulletin' ),
			array( 'status' => 404 )
		);
	}

	private function password_required(): \WP_Error {
		return new \WP_Error(
			'password_required',
			__( 'A password is required to read this content.', 'jtzl-bulletin' ),
			array( 'status' => 403 )
		);
	}
}
