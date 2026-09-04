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
 */
class PersonalController implements ControllerInterface {

	private FeatureGate $features;

	private PersonalRepository $collections;

	private TopicSerializer $topics;

	private ForumSerializer $forums;

	private RequestBounds $bounds;

	private ResponseFactory $responses;

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
	 * Data contract.
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
	 * Data contract.
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
	 * Data contract.
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
	 * Data contract.
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
	 * Data contract.
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
	 * Data contract.
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
