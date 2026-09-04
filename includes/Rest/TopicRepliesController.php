<?php
/**
 * A thread's replies: reading them, and adding one.
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
 * `GET|POST /topics/{id}/replies` — the posts under a thread, and answering it.
 */
class TopicRepliesController implements ControllerInterface {

	private AccessPolicy $access;

	private CollectionRepository $collections;

	private ReplyPresenter $replies;

	private RequestBounds $bounds;

	private ResponseFactory $responses;

	private WriteFields $fields;

	private ReplyMutationService $mutations;

	public function __construct(
		AccessPolicy $access,
		CollectionRepository $collections,
		ReplyPresenter $replies,
		RequestBounds $bounds,
		ResponseFactory $responses,
		WriteFields $fields,
		ReplyMutationService $mutations
	) {
		$this->access      = $access;
		$this->collections = $collections;
		$this->replies     = $replies;
		$this->bounds      = $bounds;
		$this->responses   = $responses;
		$this->fields      = $fields;
		$this->mutations   = $mutations;
	}

	/**
	 * Data contract.
	 *
	 * @return array<string,array<mixed>>
	 */
	public function routes(): array {
		return array(

			'/topics/(?P<id>[\d]+)/replies' => array(
				$this->bounds->readable_route(
					array( $this, 'get_collection' ),
					$this->bounds->collection_args() + array(
						'id'     => $this->bounds->integer_arg( array( 'required' => true ) ),
						'around' => $this->bounds->integer_arg(),
					),
				),
				$this->bounds->authenticated_route(
					array( $this, 'create_item' ),
					array( $this->responses, 'authenticated' ),
					array(
						'id'       => $this->bounds->integer_arg( array( 'required' => true ) ),
						'content'  => $this->fields->content_arg(),
						'reply_to' => $this->fields->reply_to_arg(),
					) + $this->bounds->avatar_args(),
					'POST'
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
	public function create_item( \WP_REST_Request $request ) {
		$size = $this->bounds->avatar_size( $request );

		return $this->responses->written(
			$this->mutations->create(
				$this->bounds->id( $request ),
				$this->bounds->caller(),
				$this->fields->content( $request ),
				$this->fields->reply_to( $request )
			),
			fn( int $reply_id ): array => $this->replies->reply( $reply_id, $size )
		);
	}

	/**
	 * Data contract.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_collection( \WP_REST_Request $request ) {
		$topic_id = $this->bounds->id( $request );
		$allowed  = $this->access->topic( $topic_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$per_page = $this->bounds->per_page( $request );
		$number   = $this->replies->page_number(
			$topic_id,
			max( 0, (int) $request->get_param( 'around' ) ),
			$this->bounds->page( $request ),
			$per_page
		);

		if ( ! is_int( $number ) ) {
			return $number;
		}

		$page = $this->collections->topic_replies( $topic_id, $number, $per_page );

		return $this->responses->collection(
			$this->replies->page( $page['ids'], $this->bounds->avatar_size( $request ), ( $number - 1 ) * $per_page ),
			$page['total'],
			$page['total_pages']
		);
	}
}
