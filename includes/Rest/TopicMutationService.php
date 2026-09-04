<?php
/**
 * Starting a thread, over the API.
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
 * Applies REST-only policy, then delegates the shared write lifecycle to bbPress.
 */
class TopicMutationService {

	private ContextInterface $wp;

	private RestContextInterface $rest;

	private AccessPolicy $access;

	private FeatureGate $features;

	private TopicTagValidator $tags;

	private BbpFormHandlerBridge $bridge;

	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		AccessPolicy $access,
		FeatureGate $features,
		TopicTagValidator $tags,
		BbpFormHandlerBridge $bridge
	) {
		$this->wp       = $wp;
		$this->rest     = $rest;
		$this->access   = $access;
		$this->features = $features;
		$this->tags     = $tags;
		$this->bridge   = $bridge;
	}

	/**
	 * Data contract.
	 *
	 * @param int      $forum_id  Forum to start it in.
	 * @param int      $author_id Author, already known to be signed in.
	 * @param string   $title     Title, sanitized but not yet filtered.
	 * @param string   $content   Body, as the app sent it.
	 * @param string[] $tag_names Tag names, as the request carried them.
	 * @return MutationResult|\WP_Error
	 */
	public function create( int $forum_id, int $author_id, string $title, string $content, array $tag_names ) {
		$allowed = $this->may_start( $forum_id, $tag_names );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$tag_names = $this->tags->normalize( $tag_names );

		if ( ! is_array( $tag_names ) ) {
			return $tag_names;
		}

		$form_values = array(
			'bbp_forum_id'      => (string) $forum_id,
			'bbp_topic_title'   => $this->rest->slash( $title ),
			'bbp_topic_content' => $this->rest->slash( $content ),
		);

		if ( array() === $tag_names ) {
			return $this->bridge->run( 'bbp-new-topic', 0, $form_values );
		}

		$terms                         = array_map( array( $this->rest, 'slash' ), $tag_names );
		$form_values['bbp_topic_tags'] = implode( ',', $terms );
		$taxonomy                      = $this->rest->topic_tag_taxonomy();

		$preserve_tag_names = static function ( array $data ) use ( $taxonomy, $terms ): array {
			$data['tax_input'][ $taxonomy ] = $terms;

			return $data;
		};

		$this->wp->add_filter( 'bbp_new_topic_pre_insert', $preserve_tag_names, PHP_INT_MIN );

		try {
			return $this->bridge->run( 'bbp-new-topic', 0, $form_values );
		} finally {
			$this->wp->remove_filter_callback( 'bbp_new_topic_pre_insert', $preserve_tag_names, PHP_INT_MIN );
		}
	}

	/**
	 * Data contract.
	 *
	 * @param int      $forum_id  Forum to start a thread in.
	 * @param string[] $tag_names Tags the request carried.
	 * @return true|\WP_Error
	 */
	private function may_start( int $forum_id, array $tag_names ) {
		if ( ! $this->wp->current_user_can( 'publish_topics' ) ) {
			return $this->refuse( 'forbidden', __( 'You cannot start threads.', 'jtzl-bulletin' ), 403 );
		}

		$allowed = $this->access->forum( $forum_id );

		if ( true !== $allowed ) {
			return $allowed;
		}

		if ( $this->wp->is_forum_category( $forum_id ) ) {
			return $this->refuse( 'forbidden', __( 'This forum is a category; threads cannot be started in it.', 'jtzl-bulletin' ), 403 );
		}

		if ( $this->wp->is_forum_closed( $forum_id ) && ! $this->rest->current_user_can_for( 'edit_forum', $forum_id ) ) {
			return $this->refuse( 'forbidden', __( 'This forum is closed to new threads.', 'jtzl-bulletin' ), 403 );
		}

		return array() === $tag_names ? true : $this->features->topic_tags();
	}

	private function refuse( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
