<?php
/**
 * Tag reads.
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
 * `GET /tags` — the vocabulary behind the tag filter.
 *
 * ## Why the route exists at all
 *
 * Without it the app can only learn a tag's ID by fetching a topic that happens to
 * carry it, so a tag picker would be built out of whatever the reader had already
 * browsed. This route is the vocabulary in one place, paged, in name order.
 *
 * ⚠ **Which makes it the one route in this API that is deliberately an enumeration**,
 * and therefore the one where "absent" and "present with a count of nought" are
 * different answers to a stranger. A term is listed only when at least one topic
 * carrying it is readable — see `WordPress\RestContext::visible_tags()`, where the
 * visibility filter selects the rows rather than merely counting them. A zero-count
 * term would name a tag that exists somewhere the reader cannot go, and a list of
 * those names describes the forum they came from.
 *
 * There is no singular `/tags/{id}`. A tag is not a resource the app opens; it is a
 * filter it applies, and `GET /topics?tag={id}` is what applying it looks like.
 *
 * @since 0.6.0
 */
class TagController implements ControllerInterface {

	/**
	 * Whether there is a vocabulary at all.
	 *
	 * @var AccessPolicy
	 * @since 0.6.0
	 */
	private AccessPolicy $access;

	/**
	 * Collection arguments.
	 *
	 * @var CollectionRepository
	 * @since 0.6.0
	 */
	private CollectionRepository $collections;

	/**
	 * Tag rows.
	 *
	 * @var TagSerializer
	 * @since 0.6.0
	 */
	private TagSerializer $tags;

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
	 * @param AccessPolicy         $access      Whether there is a vocabulary at all.
	 * @param CollectionRepository $collections Collection arguments.
	 * @param TagSerializer        $tags        Tag rows.
	 * @param RequestBounds        $bounds      What a request may ask for.
	 * @param ResponseFactory      $responses   What comes back.
	 */
	public function __construct(
		AccessPolicy $access,
		CollectionRepository $collections,
		TagSerializer $tags,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->access      = $access;
		$this->collections = $collections;
		$this->tags        = $tags;
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
			'/tags' => $this->bounds->readable_route(
				array( $this, 'get_collection' ),
				$this->bounds->collection_args()
			),
		);
	}

	/**
	 * One page of the vocabulary.
	 *
	 * @since 0.6.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_collection( \WP_REST_Request $request ) {
		$enabled = $this->access->topic_tags();

		if ( true !== $enabled ) {
			return $enabled;
		}

		$page = $this->collections->tags( $this->bounds->page( $request ), $this->bounds->per_page( $request ) );

		return $this->responses->collection(
			$this->tags->tags( $page['terms'], $page['counts'] ),
			$page['total'],
			$page['total_pages']
		);
	}
}
