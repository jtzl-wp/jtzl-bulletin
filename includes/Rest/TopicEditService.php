<?php
/**
 * Editing a thread over the REST API.
 *
 * @package JTZL\Bulletin
 * @since 0.6.0
 */

namespace JTZL\Bulletin\Rest;

use JTZL\Bulletin\WordPress\ContextInterface;
use JTZL\Bulletin\WordPress\RestContextInterface;

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Applies REST-only PATCH and tag policy, then delegates the write to bbPress.
 *
 * @since 0.6.0
 */
class TopicEditService {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * REST-side WordPress/bbPress seam.
	 *
	 * @var RestContextInterface
	 */
	private RestContextInterface $rest;

	/**
	 * What the site has switched on.
	 *
	 * @var FeatureGate
	 */
	private FeatureGate $features;

	/**
	 * REST tag validation.
	 *
	 * @var ContentGuard
	 */
	private ContentGuard $guard;

	/**
	 * Scoped native form-handler bridge.
	 *
	 * @var BbpFormHandlerBridge
	 */
	private BbpFormHandlerBridge $bridge;

	/**
	 * Constructor.
	 *
	 * @param ContextInterface     $wp       WordPress/bbPress seam.
	 * @param RestContextInterface $rest     REST-side WordPress/bbPress seam.
	 * @param FeatureGate          $features What the site has switched on.
	 * @param ContentGuard         $guard    REST tag validation.
	 * @param BbpFormHandlerBridge $bridge   Native handler bridge.
	 */
	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		FeatureGate $features,
		ContentGuard $guard,
		BbpFormHandlerBridge $bridge
	) {
		$this->wp       = $wp;
		$this->rest     = $rest;
		$this->features = $features;
		$this->guard    = $guard;
		$this->bridge   = $bridge;
	}

	/**
	 * Edit a thread as its author.
	 *
	 * The author ID remains part of the stable service contract. The native handler
	 * obtains the authoritative author from the stored topic and current-user context.
	 *
	 * @param int                 $topic_id  Thread being edited.
	 * @param int                 $author_id Authenticated author ID.
	 * @param array<string,mixed> $changes   Fields the request actually carried.
	 * @return MutationResult|\WP_Error
	 */
	public function update( int $topic_id, int $author_id, array $changes ) {
		$allowed = $this->may_edit( $topic_id, $changes );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$terms = $this->terms( $topic_id, $changes );

		if ( ! is_array( $terms ) ) {
			return $terms;
		}

		$form_values = array(
			'bbp_topic_id'      => (string) $topic_id,
			'bbp_forum_id'      => (string) $this->wp->get_topic_forum_id( $topic_id ),
			'bbp_topic_title'   => $this->merged( $changes, 'title', $this->rest->get_post_title_raw( $topic_id ) ),
			'bbp_topic_content' => $this->merged( $changes, 'content', $this->rest->get_post_content_raw( $topic_id ) ),
		);

		if ( array_key_exists( 'tags', $changes ) ) {
			$form_values['bbp_topic_tags'] = implode( ',', $terms );
		}

		$taxonomy           = $this->rest->topic_tag_taxonomy();
		$preserve_tag_names = static function ( array $data ) use ( $taxonomy, $terms ): array {
			$data['tax_input'][ $taxonomy ] = $terms;

			return $data;
		};

		// bbPress's edit form serializes tags through a comma-delimited field, including
		// when it preserves existing terms. Restore the exact REST/stored names before
		// extensions inspect the insertion data and WordPress assigns the taxonomy.
		$this->wp->add_filter( 'bbp_edit_topic_pre_insert', $preserve_tag_names, PHP_INT_MIN );

		try {
			return $this->bridge->run( 'bbp-edit-topic', $topic_id, $form_values );
		} finally {
			$this->wp->remove_filter_callback( 'bbp_edit_topic_pre_insert', $preserve_tag_names, PHP_INT_MIN );
		}
	}

	/**
	 * Enforce the two REST-only edit gates.
	 *
	 * The tag capability is asked on field presence, including for an empty list.
	 * Native bbPress can clear tags without asking that capability; REST deliberately
	 * treats clearing as assigning the empty set and refuses it consistently.
	 *
	 * @param int                 $topic_id Thread being edited.
	 * @param array<string,mixed> $changes  Fields the request actually carried.
	 * @return true|\WP_Error
	 */
	private function may_edit( int $topic_id, array $changes ) {
		if ( array() === $changes ) {
			return $this->refuse( 'invalid_request', __( 'An edit has to change something.', 'jtzl-bulletin' ), 400 );
		}

		if ( ! array_key_exists( 'tags', $changes ) ) {
			return true;
		}

		$enabled = $this->features->topic_tags();

		if ( true !== $enabled ) {
			return $enabled;
		}

		return $this->rest->current_user_can_for( 'assign_topic_tags', $topic_id )
			? true
			: $this->refuse( 'forbidden', __( 'You cannot change the tags on this thread.', 'jtzl-bulletin' ), 403 );
	}

	/**
	 * Exact slashed tag names the edit must leave on the topic.
	 *
	 * @param int                 $topic_id Thread being edited.
	 * @param array<string,mixed> $changes  Fields the request actually carried.
	 * @return string[]|\WP_Error
	 */
	private function terms( int $topic_id, array $changes ) {
		if ( ! array_key_exists( 'tags', $changes ) ) {
			return array_map(
				fn( \WP_Term $term ): string => $this->rest->slash( $term->name ),
				$this->rest->get_topic_tags( $topic_id )
			);
		}

		$names = $this->guard->tags( is_array( $changes['tags'] ) ? $changes['tags'] : array() );

		return is_array( $names ) ? array_map( array( $this->rest, 'slash' ), $names ) : $names;
	}

	/**
	 * A named PATCH field or its stored value, slashed once for the native handler.
	 *
	 * @param array<string,mixed> $changes Fields the request carried.
	 * @param string              $key     Field name.
	 * @param string              $stored  Current raw stored value.
	 * @return string
	 */
	private function merged( array $changes, string $key, string $stored ): string {
		$value = array_key_exists( $key, $changes ) ? $changes[ $key ] : $stored;

		return $this->rest->slash( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Build one stable REST refusal.
	 *
	 * @param string $code    Stable REST error code.
	 * @param string $message Plain-text message.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	private function refuse( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
