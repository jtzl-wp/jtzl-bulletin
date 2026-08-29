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
 * ## `/me` is no longer this route with the identity filled in
 *
 * It was, for one release: `Rest\RequestBounds::member()` took the ID out of the path
 * when there was one and answered with the caller when there was not, so both were
 * served by one method and could not come to disagree about what a profile contains.
 *
 * ⚠ **That arrangement ended when `/me` grew a write.** A controller holding the read
 * collaborators *and* the write ones crossed PHPMD's coupling gate, and the gate was
 * right — the two routes had stopped being the same shape. `/me` is now
 * `Rest\ProfileController`'s, and the property that mattered survives the move in a
 * better form: both controllers answer through `Rest\ProfilePresenter`, so the decision
 * is named and shared rather than inherited by calling somebody else's method.
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
	 * What a profile response is.
	 *
	 * @var ProfilePresenter
	 * @since 0.6.1
	 */
	private ProfilePresenter $profiles;

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
	 * @param ProfilePresenter     $profiles    What a profile response is.
	 * @param TopicSerializer      $topics      Topic rows.
	 * @param ReplySerializer      $replies     Reply rows.
	 * @param RequestBounds        $bounds      What a request may ask for.
	 * @param ResponseFactory      $responses   What comes back.
	 */
	public function __construct(
		AccessPolicy $access,
		UnanchoredRepository $collections,
		ProfilePresenter $profiles,
		TopicSerializer $topics,
		ReplySerializer $replies,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->access      = $access;
		$this->collections = $collections;
		$this->profiles    = $profiles;
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
	 * @return array<string,array<mixed>>
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
		return $this->profiles->present(
			$this->bounds->id( $request ),
			$this->bounds->avatar_size( $request )
		);
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
		$user_id = $this->bounds->member( $request );
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
		$user_id = $this->bounds->member( $request );
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
