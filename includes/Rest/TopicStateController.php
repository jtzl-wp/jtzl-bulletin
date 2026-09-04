<?php
/**
 * A member's own state on a thread.
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
 * Read position, favourite and subscription, for the thread in the path.
 */
class TopicStateController implements ControllerInterface {

	private const SETTABLE = 'PUT, DELETE';

	private const MARKABLE = 'PUT';

	private TopicSerializer $topics;

	private RequestBounds $bounds;

	private ResponseFactory $responses;

	private StateService $state;

	public function __construct(
		TopicSerializer $topics,
		RequestBounds $bounds,
		ResponseFactory $responses,
		StateService $state
	) {
		$this->topics    = $topics;
		$this->bounds    = $bounds;
		$this->responses = $responses;
		$this->state     = $state;
	}

	/**
	 * Data contract.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function routes(): array {
		$id = array( 'id' => $this->bounds->integer_arg( array( 'required' => true ) ) );

		return array(
			'/topics/(?P<id>[\d]+)/read'         => $this->bounds->authenticated_route(
				array( $this, 'mark_read' ),
				array( $this->responses, 'authenticated' ),
				$id + $this->bounds->avatar_args() + array(

					'read_cursor' => $this->bounds->string_arg(
						array(
							'required'          => true,
							'sanitize_callback' => static fn( $value ): string => is_scalar( $value ) ? (string) $value : '',
						)
					),
				),
				self::MARKABLE
			),
			'/topics/(?P<id>[\d]+)/favorite'     => $this->bounds->authenticated_route(
				array( $this, 'favorite' ),
				array( $this->responses, 'authenticated' ),
				$id + $this->bounds->avatar_args(),
				self::SETTABLE
			),
			'/topics/(?P<id>[\d]+)/subscription' => $this->bounds->authenticated_route(
				array( $this, 'subscription' ),
				array( $this->responses, 'authenticated' ),
				$id + $this->bounds->avatar_args(),
				self::SETTABLE
			),
		);
	}

	/**
	 * Data contract.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function mark_read( \WP_REST_Request $request ) {
		$topic_id = $this->bounds->id( $request );
		$written  = $this->state->mark_read(
			$topic_id,
			$this->bounds->caller(),
			(string) $request->get_param( 'read_cursor' )
		);

		return true === $written ? $this->current( $topic_id, $request ) : $written;
	}

	/**
	 * Data contract.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function favorite( \WP_REST_Request $request ) {
		$topic_id = $this->bounds->id( $request );
		$written  = $this->state->set_favorite( $topic_id, $this->bounds->caller(), $this->wanted( $request ) );

		return true === $written ? $this->current( $topic_id, $request ) : $written;
	}

	/**
	 * Data contract.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function subscription( \WP_REST_Request $request ) {
		$topic_id = $this->bounds->id( $request );
		$written  = $this->state->set_topic_subscription( $topic_id, $this->bounds->caller(), $this->wanted( $request ) );

		return true === $written ? $this->current( $topic_id, $request ) : $written;
	}

	private function wanted( \WP_REST_Request $request ): bool {
		return 'DELETE' !== strtoupper( $request->get_method() );
	}

	private function current( int $topic_id, \WP_REST_Request $request ): \WP_REST_Response {
		return $this->responses->item(
			$this->topics->topic( $topic_id, $this->bounds->avatar_size( $request ) ),
			200,
			true
		);
	}
}
