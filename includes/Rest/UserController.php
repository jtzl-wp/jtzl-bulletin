<?php
/**
 * Public profile reads.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * `GET /users/{id}` and the two collections it counts — a member, and what they wrote.
 *
 * ## The counts are the collections, asked
 *
 * A profile's `topic_count` and `reply_count` are the `X-WP-Total` of the two routes
 * beneath it, computed by running those collections for a single row. bbPress keeps
 * per-user counters that would answer instantly, and they are the wrong number: they
 * count the whole site, so a member with three threads in a forum this reader cannot
 * open would have a profile saying "3" above a list showing none. That difference is
 * small and it is a disclosure — it tells a visitor precisely how much is being kept
 * from them, per person.
 *
 * ## Three routes, one existence question
 *
 * ⚠ All three ask `Rest\AccessPolicy::user()` first, the collections included. A
 * collection that answered 200 with an empty array for an ID nobody holds would
 * disagree with `/users/{id}` about the same number — and the pair of answers would
 * distinguish "nobody" from "somebody who has written nothing" that neither route
 * says on its own.
 *
 * There is no visibility question beyond that one. A bbPress profile is public, and
 * what a *stranger* may see of what that member wrote is settled inside the queries,
 * before the totals are taken.
 *
 * @since 0.6.0
 */
class UserController implements ControllerInterface {

	/**
	 * Whether this ID names somebody.
	 *
	 * @var AccessPolicy
	 * @since 0.6.0
	 */
	private AccessPolicy $access;

	/**
	 * Collection arguments.
	 *
	 * @var UnanchoredRepository
	 * @since 0.6.0
	 */
	private UnanchoredRepository $collections;

	/**
	 * Profiles.
	 *
	 * @var UserSerializer
	 * @since 0.6.0
	 */
	private UserSerializer $users;

	/**
	 * Topic rows.
	 *
	 * @var TopicSerializer
	 * @since 0.6.0
	 */
	private TopicSerializer $topics;

	/**
	 * Reply rows.
	 *
	 * @var ReplySerializer
	 * @since 0.6.0
	 */
	private ReplySerializer $replies;

	/**
	 * What a request may ask for.
	 *
	 * @var RequestBounds
	 * @since 0.6.0
	 */
	private RequestBounds $bounds;

	/**
	 * What comes back.
	 *
	 * @var ResponseFactory
	 * @since 0.6.0
	 */
	private ResponseFactory $responses;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param AccessPolicy         $access      Whether this ID names somebody.
	 * @param UnanchoredRepository $collections Collection arguments.
	 * @param UserSerializer       $users       Profiles.
	 * @param TopicSerializer      $topics      Topic rows.
	 * @param ReplySerializer      $replies     Reply rows.
	 * @param RequestBounds        $bounds      What a request may ask for.
	 * @param ResponseFactory      $responses   What comes back.
	 */
	public function __construct(
		AccessPolicy $access,
		UnanchoredRepository $collections,
		UserSerializer $users,
		TopicSerializer $topics,
		ReplySerializer $replies,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->access      = $access;
		$this->collections = $collections;
		$this->users       = $users;
		$this->topics      = $topics;
		$this->replies     = $replies;
		$this->bounds      = $bounds;
		$this->responses   = $responses;
	}

	/**
	 * The routes this controller answers.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function routes(): array {
		return array(
			'/users/(?P<id>[\d]+)'         => $this->bounds->readable_route(
				array( $this, 'get_item' ),
				array(
					'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
				) + $this->bounds->avatar_args()
			),
			'/users/(?P<id>[\d]+)/topics'  => $this->bounds->readable_route(
				array( $this, 'get_topics' ),
				$this->bounds->collection_args() + array(
					'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
				),
			),
			'/users/(?P<id>[\d]+)/replies' => $this->bounds->readable_route(
				array( $this, 'get_replies' ),
				$this->bounds->collection_args() + array(
					'id' => $this->bounds->integer_arg( array( 'required' => true ) ),
				),
			),
		);
	}

	/**
	 * One profile.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( \WP_REST_Request $request ) {
		$user_id = (int) $request->get_param( 'id' );
		$allowed = $this->access->user( $user_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		// One row each, for the totals rather than the rows: what a page of this
		// member's work looks like is the two collection routes' job.
		$profile = $this->users->user(
			$user_id,
			$this->bounds->avatar_size( $request ),
			$this->collections->user_topics( $user_id, 1, 1 )['total'],
			$this->collections->user_replies( $user_id, 1, 1 )['total']
		);

		// The policy has just established that this ID names somebody, so the
		// serializer's null is unreachable here — and it is answered by asking the
		// policy again rather than by a second 404 written out, so there stays one
		// place that decides what a missing thing looks like.
		return null === $profile ? $this->access->user( 0 ) : $this->responses->item( $profile );
	}

	/**
	 * One page of the threads a member started.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_topics( \WP_REST_Request $request ) {
		$user_id = (int) $request->get_param( 'id' );
		$allowed = $this->access->user( $user_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$page = $this->collections->user_topics(
			$user_id,
			$this->bounds->page( $request ),
			$this->bounds->per_page( $request )
		);

		return $this->responses->collection(
			$this->topics->topics( $page['ids'], $this->bounds->avatar_size( $request ) ),
			$page['total'],
			$page['total_pages']
		);
	}

	/**
	 * One page of the replies a member wrote.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_replies( \WP_REST_Request $request ) {
		$user_id = (int) $request->get_param( 'id' );
		$allowed = $this->access->user( $user_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$page = $this->collections->user_replies(
			$user_id,
			$this->bounds->page( $request ),
			$this->bounds->per_page( $request )
		);

		return $this->responses->collection(
			$this->replies->replies( $page['ids'], $this->bounds->avatar_size( $request ) ),
			$page['total'],
			$page['total_pages']
		);
	}
}
