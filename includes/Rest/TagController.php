<?php
/**
 * Tag reads, and the threads under a tag.
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
 * `GET /tags` and `GET /topics?tag={id}` — the vocabulary, and what carries a name.
 */
class TagController implements ControllerInterface {

	private FeatureGate $features;

	private CollectionRepository $collections;

	private TagSerializer $tags;

	private TopicSerializer $topics;

	private RequestBounds $bounds;

	private ResponseFactory $responses;

	public function __construct(
		FeatureGate $features,
		CollectionRepository $collections,
		TagSerializer $tags,
		TopicSerializer $topics,
		RequestBounds $bounds,
		ResponseFactory $responses
	) {
		$this->features    = $features;
		$this->collections = $collections;
		$this->tags        = $tags;
		$this->topics      = $topics;
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
			'/tags'   => $this->bounds->readable_route(
				array( $this, 'get_collection' ),
				$this->bounds->collection_args()
			),

			'/topics' => $this->bounds->readable_route(
				array( $this, 'get_tag_collection' ),
				$this->bounds->collection_args() + array(

					'tag' => $this->bounds->integer_arg(
						array(
							'required' => true,
							'minimum'  => 1,
						)
					),
				),
			),
		);
	}

	/**
	 * Data contract.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_collection( \WP_REST_Request $request ) {
		$enabled = $this->features->topic_tags();

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

	/**
	 * Data contract.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_tag_collection( \WP_REST_Request $request ) {
		$enabled = $this->features->topic_tags();

		if ( true !== $enabled ) {
			return $enabled;
		}

		$page = $this->collections->tag_topics(
			(int) $request->get_param( 'tag' ),
			$this->bounds->page( $request ),
			$this->bounds->per_page( $request )
		);

		return $this->responses->collection(
			$this->topics->topics( $page['ids'], $this->bounds->avatar_size( $request ) ),
			$page['total'],
			$page['total_pages']
		);
	}
}
