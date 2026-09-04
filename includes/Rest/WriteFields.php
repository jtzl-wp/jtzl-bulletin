<?php
/**
 * The fields a write carries.
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
 * How a create or an edit declares its body, and how it reads it back.
 */
class WriteFields {

	/**
	 * Data contract.
	 *
	 * @return array<string,mixed>
	 */
	public function content_arg(): array {
		return array(
			'type'              => 'string',
			'required'          => true,
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'rest_sanitize_request_arg',
		);
	}

	/**
	 * Data contract.
	 *
	 * @return array<string,mixed>
	 */
	public function tags_arg(): array {
		return array(
			'type'              => 'array',
			'default'           => array(),
			'items'             => array( 'type' => 'string' ),
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'rest_sanitize_request_arg',
		);
	}

	/**
	 * Data contract.
	 *
	 * @return array<string,mixed>
	 */
	public function reply_to_arg(): array {
		return array(
			'type'              => array( 'integer', 'null' ),
			'default'           => null,
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'rest_sanitize_request_arg',
		);
	}

	/**
	 * Data contract.
	 *
	 * @return array<string,mixed>
	 */
	public function patch_content_arg(): array {
		$arg = $this->content_arg();

		unset( $arg['required'] );

		return $arg;
	}

	/**
	 * Data contract.
	 *
	 * @return array<string,mixed>
	 */
	public function patch_tags_arg(): array {
		$arg = $this->tags_arg();

		unset( $arg['default'] );

		return $arg;
	}

	/**
	 * Data contract.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string[]         $names   Field names this route accepts.
	 * @return array<string,mixed> Present fields, keyed by name.
	 */
	public function changes( \WP_REST_Request $request, array $names ): array {
		$changes = array();

		foreach ( $names as $name ) {
			if ( ! $request->has_param( $name ) ) {
				continue;
			}

			$changes[ $name ] = match ( $name ) {
				'tags'  => $this->tags( $request ),
				'title' => $this->title( $request ),
				default => $this->content( $request ),
			};
		}

		return $changes;
	}

	/**
	 * Data contract.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string[]
	 */
	public function tags( \WP_REST_Request $request ): array {
		$raw = $request->get_param( 'tags' );

		return is_array( $raw ) ? array_values( array_map( 'strval', array_filter( $raw, 'is_scalar' ) ) ) : array();
	}

	public function reply_to( \WP_REST_Request $request ): int {
		$raw = $request->get_param( 'reply_to' );

		return is_numeric( $raw ) ? max( 0, (int) $raw ) : 0;
	}

	public function content( \WP_REST_Request $request ): string {
		$raw = $request->get_param( 'content' );

		return is_scalar( $raw ) ? (string) $raw : '';
	}

	public function title( \WP_REST_Request $request ): string {
		$raw = $request->get_param( 'title' );

		return is_scalar( $raw ) ? trim( (string) $raw ) : '';
	}
}
