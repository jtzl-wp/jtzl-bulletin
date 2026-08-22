<?php
/**
 * A member's own lists.
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
 * `GET /me/favorites` and `GET /me/subscriptions/{topics,forums}` — the three lists
 * that exist only for the member reading them.
 *
 * ## Why they are routes and not fields on `/me`
 *
 * Because there is no number they are bounded by. A member of six years can subscribe
 * to every forum on the site and favourite a thousand threads, and embedding those
 * arrays in a profile response would make it unbounded and unpageable — the app would
 * have no way to ask for the rest of one. So each is a collection, paged by the same
 * two arguments as every other collection here.
 *
 * ## ⚠ Not registered yet
 *
 * Rest\Routes does not list this controller, and that is deliberate rather than an
 * oversight: these three paths are the subject of confirmation 3 in
 * `docs/rest-api-plan.md`, which the app team has not answered. Everything below is
 * built, wired and tested; what is withheld is the registration, because a path
 * published under a versioned namespace is a promise and the shape of these three is
 * still a question. Adding `$this->personal` to `Rest\Routes::controllers()` is the
 * whole of what the answer costs.
 *
 * ## No `Rest\AccessPolicy::user()` here
 *
 * The other profile collections ask whether the ID names somebody, because a stranger
 * supplies it. These routes have no ID: the member is whoever is signed in, and the
 * permission callback has already established that there is one. What each route does
 * ask is whether the *feature* exists at all — a forum with favourites switched off
 * must say so rather than answer 200 with an empty list, which would read as "you
 * have none".
 *
 * @since 0.6.0
 */
class PersonalController implements ControllerInterface {

	/**
	 * Which features are on.
	 *
	 * @var FeatureGate
	 * @since 0.6.0
	 */
	private FeatureGate $features;

	/**
	 * Collection arguments.
	 *
	 * @var PersonalRepository
	 * @since 0.6.0
	 */
	private PersonalRepository $collections;

	/**
	 * Topic rows.
	 *
	 * @var TopicSerializer
	 * @since 0.6.0
	 */
	private TopicSerializer $topics;

	/**
	 * Forum rows.
	 *
	 * @var ForumSerializer
	 * @since 0.6.0
	 */
	private ForumSerializer $forums;

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
	 * @param FeatureGate        $features    Which features are on.
	 * @param PersonalRepository $collections Collection arguments.
	 * @param TopicSerializer    $topics      Topic rows.
	 * @param ForumSerializer    $forums      Forum rows.
	 * @param RequestBounds      $bounds      What a request may ask for.
	 * @param ResponseFactory    $responses   What comes back.
	 */
	public function __construct(
		FeatureGate $features,
		PersonalRepository $collections,
		TopicSerializer $topics,
		ForumSerializer $forums,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->features    = $features;
		$this->collections = $collections;
		$this->topics      = $topics;
		$this->forums      = $forums;
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
			'/me/favorites'            => $this->authenticated( array( $this, 'get_favorites' ) ),
			'/me/subscriptions/topics' => $this->authenticated( array( $this, 'get_subscribed_topics' ) ),
			'/me/subscriptions/forums' => $this->authenticated( array( $this, 'get_subscribed_forums' ) ),
		);
	}

	/**
	 * One page of the threads this member has favourited.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_favorites( \WP_REST_Request $request ) {
		$enabled = $this->features->favorites();

		if ( true !== $enabled ) {
			return $enabled;
		}

		return $this->topic_page(
			$this->collections->favorite_topics(
				$this->bounds->caller(),
				$this->bounds->page( $request ),
				$this->bounds->per_page( $request )
			),
			$request
		);
	}

	/**
	 * One page of the threads this member subscribes to.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_subscribed_topics( \WP_REST_Request $request ) {
		$enabled = $this->features->subscriptions();

		if ( true !== $enabled ) {
			return $enabled;
		}

		return $this->topic_page(
			$this->collections->subscribed_topics(
				$this->bounds->caller(),
				$this->bounds->page( $request ),
				$this->bounds->per_page( $request )
			),
			$request
		);
	}

	/**
	 * One page of the forums this member subscribes to.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_subscribed_forums( \WP_REST_Request $request ) {
		$enabled = $this->features->subscriptions();

		if ( true !== $enabled ) {
			return $enabled;
		}

		$page = $this->collections->subscribed_forums(
			$this->bounds->caller(),
			$this->bounds->page( $request ),
			$this->bounds->per_page( $request )
		);

		return $this->responses->collection(
			$this->forums->forums( $page['ids'] ),
			$page['total'],
			$page['total_pages']
		);
	}

	/**
	 * A page of topics, serialized — the half both topic lists share.
	 *
	 * @since 0.6.0
	 *
	 * @param array{ids:int[],total:int,total_pages:int} $page    Queried page.
	 * @param \WP_REST_Request                           $request Request.
	 * @return \WP_REST_Response
	 */
	private function topic_page( array $page, \WP_REST_Request $request ): \WP_REST_Response {
		return $this->responses->collection(
			$this->topics->topics( $page['ids'], $this->bounds->avatar_size( $request ) ),
			$page['total'],
			$page['total_pages']
		);
	}

	/**
	 * One personal collection route, declared the one way all three are.
	 *
	 * @since 0.6.0
	 *
	 * @param array{0:object,1:string} $callback Controller method to answer with.
	 * @return array<string,mixed>
	 */
	private function authenticated( array $callback ): array {
		return $this->bounds->authenticated_route(
			$callback,
			array( $this->responses, 'authenticated' ),
			$this->bounds->collection_args()
		);
	}
}
