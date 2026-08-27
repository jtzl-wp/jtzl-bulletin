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
 *
 * @since 0.6.0
 */
class TopicMutationService {

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 * @since 0.6.0
	 */
	private ContextInterface $wp;

	/**
	 * REST-side WordPress/bbPress seam.
	 *
	 * @var RestContextInterface
	 * @since 0.6.0
	 */
	private RestContextInterface $rest;

	/**
	 * Who may have this forum.
	 *
	 * @var AccessPolicy
	 * @since 0.6.0
	 */
	private AccessPolicy $access;

	/**
	 * What the site has switched on.
	 *
	 * @var FeatureGate
	 * @since 0.6.0
	 */
	private FeatureGate $features;

	/**
	 * REST tag validation.
	 *
	 * @var TopicTagValidator
	 * @since 0.6.0
	 */
	private TopicTagValidator $tags;

	/**
	 * Scoped native form-handler bridge.
	 *
	 * @var BbpFormHandlerBridge
	 */
	private BbpFormHandlerBridge $bridge;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface     $wp       WordPress/bbPress seam.
	 * @param RestContextInterface $rest     REST-side WordPress/bbPress seam.
	 * @param AccessPolicy         $access   Who may have this forum.
	 * @param FeatureGate          $features What the site has switched on.
	 * @param TopicTagValidator    $tags     REST tag validation.
	 * @param BbpFormHandlerBridge $bridge   Native handler bridge.
	 */
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
	 * Start a thread.
	 *
	 * @since 0.6.0
	 *
	 * @param int      $forum_id  Forum to start it in.
	 * @param int      $author_id Author, already known to be signed in. The native handler
	 *                            reads the authoritative current user.
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

		// The form field cannot represent a comma inside one name. Restore the REST
		// array before extensions inspect the insertion data and WordPress assigns it.
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
	 * The gates bbPress's handler puts before anything is read off the form.
	 *
	 * ⚠ **The private and hidden tests are not repeated here**, though the browser
	 * handler writes them out. `Rest\AccessPolicy::forum()` has already asked
	 * `bbp_user_can_view_forum()` with `check_ancestors`, and that function answers the
	 * handler's two questions and nothing else: public-with-a-public-chain, or
	 * private-or-hidden-with `read_forum`. Measured, not assumed — removing the second
	 * copy changed no integration outcome, including for a public forum beneath a
	 * private parent, which is the case the ancestor walk exists for. A second
	 * implementation of a rule bbPress already owns is a second implementation to keep
	 * in step.
	 *
	 * @since 0.6.0
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

	/**
	 * One refusal, built the one way.
	 *
	 * @since 0.6.0
	 *
	 * @param string $code    Stable REST error code.
	 * @param string $message Message, carrying no markup.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	private function refuse( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
