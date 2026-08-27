<?php
/**
 * REST-owned content checks.
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
 * Validates fields whose REST contract differs from bbPress's form contract and applies
 * moderation policy to edits until those mutations can delegate to native handlers too.
 *
 * @since 0.6.0
 */
class ContentGuard {

	/**
	 * The longest a tag name may be.
	 *
	 * The width of `wp_terms.name`. bbPress does not check it — its form hands whatever
	 * was typed to `wp_insert_term()`, and MySQL truncates or refuses depending on how
	 * the site is configured. Refusing here means a member is told, rather than finding
	 * a tag they did not write.
	 *
	 * @var int
	 * @since 0.6.0
	 */
	private const MAX_TAG_LENGTH = 200;

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
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface     $wp   WordPress/bbPress seam.
	 * @param RestContextInterface $rest REST-side WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp, RestContextInterface $rest ) {
		$this->wp   = $wp;
		$this->rest = $rest;
	}

	/**
	 * Whether a title is usable, and the answer when it is not.
	 *
	 * @since 0.6.0
	 *
	 * @param string $title    Title, already filtered.
	 * @param bool   $required Whether an empty title is a refusal; false for a reply,
	 *                         which bbPress lets go untitled.
	 * @return true|\WP_Error
	 */
	public function title( string $title, bool $required ) {
		if ( $required && '' === $title ) {
			return $this->refuse( 'invalid_title', __( 'A thread needs a title.', 'jtzl-bulletin' ), 400 );
		}

		if ( $this->rest->is_title_too_long( $title ) ) {
			return $this->refuse( 'invalid_title', __( 'That title is too long.', 'jtzl-bulletin' ), 400 );
		}

		return true;
	}

	/**
	 * Whether there is anything to post.
	 *
	 * @since 0.6.0
	 *
	 * @param string $content Content, already filtered.
	 * @return true|\WP_Error
	 */
	public function content( string $content ) {
		return '' === $content
			? $this->refuse( 'invalid_content', __( 'A post cannot be empty.', 'jtzl-bulletin' ), 400 )
			: true;
	}

	/**
	 * Tag names, normalized once, or why they cannot be used.
	 *
	 * ## What normalizing means here
	 *
	 * Whitespace off each name, and duplicates dropped case-insensitively — because
	 * WordPress resolves a term by slug, so `Shimano` and `shimano` are the same tag and
	 * sending both would have `wp_insert_post()` set it twice. The **first** spelling
	 * wins, so a member who writes a name gets their own capitalisation on a tag they
	 * are creating.
	 *
	 * ⚠ **A blank name is refused, not dropped.** A client sending `[""]` or `["  "]`
	 * meant to send a tag; silently discarding it would give them a 201 for a thread
	 * that is not tagged the way they asked. An absent or empty `tags` array is a
	 * different thing entirely and passes straight through.
	 *
	 * @since 0.6.0
	 *
	 * @param string[] $names Names as the request carried them.
	 * @return string[]|\WP_Error
	 */
	public function tags( array $names ) {
		$normalized = array();
		$seen       = array();

		foreach ( $names as $name ) {
			$name = trim( $name );

			if ( '' === $name || mb_strlen( $name, '8bit' ) > self::MAX_TAG_LENGTH ) {
				return $this->refuse( 'invalid_tags', __( 'One of those tags cannot be used.', 'jtzl-bulletin' ), 400 );
			}

			$key = mb_strtolower( $name );

			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$normalized[] = $name;
			}
		}

		return $normalized;
	}

	/**
	 * The status an *edited* post should keep, or why the edit cannot be made.
	 *
	 * ## Three differences from bbPress's create lifecycle
	 *
	 * 1. **No flood check.** The throttle is on *posting*, not on fixing a typo, and
	 *    bbPress's edit handlers never ask it. An author who has just posted may edit
	 *    immediately.
	 * 2. **No duplicate check.** `bbp_check_for_duplicate()` looks for an existing post
	 *    with the same body by the same author — which, on an edit that changes only the
	 *    title, is the post being edited. Asking it would refuse every such edit.
	 * 3. **The status is kept, not chosen.** A create picks public or pending; an edit
	 *    starts from what is already stored and only ever moves *down*, when the
	 *    moderation list catches it. A held post is not released by a clean edit — that
	 *    is a moderator's decision, not an author's.
	 *
	 * ⚠ **bbPress guards the hold with `bbp_is_topic_public()` and this does not**, for
	 * the reason `Rest\TopicMutationService::may_start()` gives about the private and
	 * hidden checks: the guard exists so that forcing pending cannot quietly *un-spam* a
	 * spammed post, and no post in that condition reaches here.
	 * `Rest\AccessPolicy::can_edit_topic()` and `::can_edit_reply()` admit only a post
	 * whose stored status is a public one, so `$current_status` is always public and the
	 * two forms agree on every input. For the three statuses that would differ — spam,
	 * trash, pending — the outcome is identical anyway: a held post that trips the list
	 * is already held. If those gates ever widen to let an author edit a held post, this
	 * is where `bbp_is_topic_public()` has to come back.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $author_id      Author ID.
	 * @param string $title          Merged title, already filtered.
	 * @param string $content        Merged content, already filtered.
	 * @param string $current_status The status stored before this edit.
	 * @return string|\WP_Error The status to store under.
	 */
	public function edited_status( int $author_id, string $title, string $content, string $current_status ) {
		// The disallowed list, exactly as on create: the refusal says nothing about why.
		if ( ! $this->rest->passes_moderation_check( $author_id, $title, $content, true ) ) {
			return $this->refuse( 'moderation_rejected', __( 'This post cannot be edited at this time.', 'jtzl-bulletin' ), 400 );
		}

		return $this->rest->passes_moderation_check( $author_id, $title, $content, false )
			? $current_status
			: $this->wp->get_pending_status_id();
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
