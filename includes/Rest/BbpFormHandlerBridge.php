<?php
/**
 * Scoped bridge to native bbPress form handlers.
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
 * Runs one native handler inside isolated browser-compatible process state.
 */
final class BbpFormHandlerBridge {

	private const OPERATIONS = array(
		'bbp-new-topic'  => array(
			'handler'   => 'bbp-new-topic',
			'lifecycle' => 'bbp_new_topic',
			'nonce'     => 'bbp-new-topic',
			'create'    => true,
			'id_field'  => null,
			'fields'    => array( 'bbp_forum_id', 'bbp_topic_title', 'bbp_topic_content', 'bbp_topic_tags' ),
		),
		'bbp-new-reply'  => array(
			'handler'   => 'bbp-new-reply',
			'lifecycle' => 'bbp_new_reply',
			'nonce'     => 'bbp-new-reply',
			'create'    => true,
			'id_field'  => null,
			'fields'    => array( 'bbp_topic_id', 'bbp_forum_id', 'bbp_reply_content', 'bbp_reply_to' ),
		),
		'bbp-edit-topic' => array(
			'handler'   => 'bbp-edit-topic',
			'lifecycle' => 'bbp_edit_topic',
			'nonce'     => 'bbp-edit-topic_',
			'create'    => false,
			'id_field'  => 'bbp_topic_id',
			'fields'    => array( 'bbp_topic_id', 'bbp_forum_id', 'bbp_topic_title', 'bbp_topic_content', 'bbp_topic_tags' ),
		),
		'bbp-edit-reply' => array(
			'handler'   => 'bbp-edit-reply',
			'lifecycle' => 'bbp_edit_reply',
			'nonce'     => 'bbp-edit-reply_',
			'create'    => false,
			'id_field'  => 'bbp_reply_id',
			'fields'    => array( 'bbp_reply_id', 'bbp_reply_content' ),
		),
	);

	private ContextInterface $wp;

	private RestContextInterface $rest;

	private BbpErrorTranslator $translator;

	private AkismetDiscardGuard $akismet;

	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		BbpErrorTranslator $translator,
		AkismetDiscardGuard $akismet
	) {
		$this->wp         = $wp;
		$this->rest       = $rest;
		$this->translator = $translator;
		$this->akismet    = $akismet;
	}

	/**
	 * REST mutations must execute the native bbPress form-handler lifecycle and restore request-global state afterward.
	 *
	 * @param string              $action      Native form action.
	 * @param int                 $object_id   Zero for creates; stored ID for edits.
	 * @param array<string,mixed> $form_values Sanitized, slashed form values.
	 * @return MutationResult|\WP_Error
	 */
	public function run( string $action, int $object_id, array $form_values ) {
		$operation = $this->validate( $action, $object_id, $form_values );
		$nonce     = $operation['create'] ? $operation['nonce'] : $operation['nonce'] . $object_id;
		$values    = array_merge(
			$form_values,
			array(
				'action'   => $operation['handler'],
				'_wpnonce' => $this->rest->create_nonce( $nonce ),
			)
		);

		return $this->invoke( $operation, $values );
	}

	/**
	 * Data contract.
	 *
	 * @param string              $action      Requested operation.
	 * @param int                 $object_id   Stored object ID.
	 * @param array<string,mixed> $form_values Form values.
	 * @return array{handler:string,lifecycle:string,nonce:string,create:bool,id_field:string|null,fields:string[]}
	 * @throws \InvalidArgumentException When the operation, ID, or fields are not allowed.
	 */
	private function validate( string $action, int $object_id, array $form_values ): array {
		if ( ! isset( self::OPERATIONS[ $action ] ) ) {
			throw new \InvalidArgumentException( 'Unknown bbPress form action.' );
		}

		$operation = self::OPERATIONS[ $action ];
		if ( ( $operation['create'] && 0 !== $object_id ) || ( ! $operation['create'] && $object_id <= 0 ) ) {
			throw new \InvalidArgumentException( 'Invalid object ID for bbPress form action.' );
		}

		$this->validate_fields( $operation['fields'], $form_values );

		$id_field = $operation['id_field'];
		if ( null !== $id_field && (int) ( $form_values[ $id_field ] ?? 0 ) !== $object_id ) {
			throw new \InvalidArgumentException( 'Form object ID does not match the requested object.' );
		}

		return $operation;
	}

	/**
	 * Data contract.
	 *
	 * @param string[]            $allowed Allowed form field names.
	 * @param array<string,mixed> $values Submitted form values.
	 * @throws \InvalidArgumentException When a field is not allowed.
	 */
	private function validate_fields( array $allowed, array $values ): void {
		foreach ( array_keys( $values ) as $field ) {
			if ( ! in_array( $field, $allowed, true ) ) {
				throw new \InvalidArgumentException( 'Unexpected bbPress form field.' );
			}
		}
	}

	/**
	 * Data contract.
	 *
	 * @param array{handler:string,lifecycle:string,nonce:string,create:bool,id_field:string|null,fields:string[]} $operation Operation definition.
	 * @param array<string,mixed>                                                                                  $values    Complete form values.
	 * @return MutationResult|\WP_Error
	 * @throws \RuntimeException When an extension throws during the handler.
	 */
	private function invoke( array $operation, array $values ) {
		$written_id = 0;
		$redirected = false;
		$discarded  = false;
		$errors     = new \WP_Error();
		$sentinel   = new \RuntimeException( 'bbpress_redirect' );
		$lifecycle  = function ( $post_id ) use ( &$written_id ): void {
			if ( $written_id <= 0 && (int) $post_id > 0 ) {
				$written_id = (int) $post_id;
			}
		};
		$redirect   = function () use ( &$redirected, $sentinel ) {
			$redirected = true;
			throw $sentinel;
		};
		$depth      = $this->rest->current_filter_depth();

		$this->wp->add_action( $operation['lifecycle'], $lifecycle, PHP_INT_MAX, 1 );
		$this->wp->add_filter( 'wp_redirect', $redirect, PHP_INT_MAX, 2 );
		$previous_errors  = $this->rest->swap_bbp_errors( $errors );
		$previous_globals = $this->rest->swap_handler_globals( $values );

		try {
			try {
				$discarded = $this->akismet->run(
					fn() => $this->rest->run_bbp_form_handler( $operation['handler'] )
				);
			} catch ( \RuntimeException $thrown ) {
				if ( $thrown !== $sentinel ) {
					throw $thrown;
				}
			}
		} finally {
			$this->wp->remove_filter_callback( $operation['lifecycle'], $lifecycle, PHP_INT_MAX );
			$this->wp->remove_filter_callback( 'wp_redirect', $redirect, PHP_INT_MAX );
			$this->rest->restore_handler_globals( $previous_globals );
			$this->rest->swap_bbp_errors( $previous_errors );
			$this->rest->unwind_filters( $depth );
		}

		return $this->result( $discarded, $redirected, $written_id, $errors );
	}

	/**
	 * Data contract.
	 *
	 * @param bool      $discarded  Whether Akismet discarded before insertion.
	 * @param bool      $redirected Whether the native success redirect was trapped.
	 * @param int       $written_id Lifecycle post ID.
	 * @param \WP_Error $errors     Native error bag.
	 * @return MutationResult|\WP_Error
	 */
	private function result( bool $discarded, bool $redirected, int $written_id, \WP_Error $errors ) {
		if ( $discarded ) {
			return MutationResult::accepted();
		}

		if ( $redirected && $written_id > 0 ) {
			$status = $this->wp->get_post_status( $written_id );
			$hidden = in_array( $status, array( $this->rest->get_spam_status_id(), $this->rest->get_trash_status_id() ), true );

			return $hidden ? MutationResult::accepted() : MutationResult::entity( $written_id );
		}

		if ( $redirected ) {
			return $this->failed();
		}

		$translated = $this->translator->first( $errors );

		return $translated ?? $this->failed();
	}

	private function failed(): \WP_Error {
		return new \WP_Error(
			'write_failed',
			__( 'The post could not be saved.', 'jtzl-bulletin' ),
			array( 'status' => 500 )
		);
	}
}
