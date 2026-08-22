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
 *
 * ## Both halves of one resource
 *
 * The second route arrived here from Rest\TopicController, which could not keep it once
 * the thread edit landed. The move is not only relief: `tag` is required, so that route
 * never answers "every thread on the site" — it answers "the threads under this name",
 * which is this resource seen from the other side. It also asks the same vocabulary gate
 * `/tags` asks, and needed no collaborator this class did not already hold but one.
 *
 * ⚠ **`tag` being required is a contract decision, not an implementation one.** The brief
 * has no screen for a global feed, the design deliberately declined a cross-forum
 * "Latest", and a route that answered without a filter would be that feature arriving
 * through the back door.
 *
 * ⚠ **An unknown tag answers 200 with an empty array**, exactly as a tag used only on
 * topics in a forum this reader cannot open does. Distinguishing them would turn the
 * route into a way to enumerate the vocabulary of a private forum one ID at a time — the
 * same reason `Rest\AccessPolicy` answers 404 for everything a reader may not know about.
 *
 * ## Why the vocabulary route exists at all
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
	 * @var FeatureGate
	 * @since 0.6.0
	 */
	private FeatureGate $features;

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
	 * Topic rows, for the threads a tag carries.
	 *
	 * @var TopicSerializer
	 * @since 0.6.0
	 */
	private TopicSerializer $topics;

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
	 * @param FeatureGate          $features    Whether there is a vocabulary at all.
	 * @param CollectionRepository $collections Collection arguments.
	 * @param TagSerializer        $tags        Tag rows.
	 * @param TopicSerializer      $topics      Topic rows.
	 * @param RequestBounds        $bounds      What a request may ask for.
	 * @param ResponseFactory      $responses   What comes back.
	 */
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
	 * The routes this controller answers.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function routes(): array {
		return array(
			'/tags'   => $this->bounds->readable_route(
				array( $this, 'get_collection' ),
				$this->bounds->collection_args()
			),
			// A tag's threads. The path is `/topics` because that is what comes back,
			// but the resource is this one: `tag` is required, so the route never
			// answers "every thread on the site", and the vocabulary gate in front of
			// it is the same one `/tags` asks.
			'/topics' => $this->bounds->readable_route(
				array( $this, 'get_tag_collection' ),
				$this->bounds->collection_args() + array(
					// Required, so WordPress refuses a bare `/topics` with
					// `rest_missing_callback_param` before the callback runs.
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
	 * One page of the vocabulary.
	 *
	 * @since 0.6.0
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
	 * One page of the threads carrying a tag.
	 *
	 * @since 0.6.0
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
