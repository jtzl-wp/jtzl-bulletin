<?php
/**
 * The checks both write paths share.
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
 * Flood, duplicate and moderation — asked in bbPress's order, answered in the API's
 * vocabulary.
 *
 * ## One place, because the two handlers ask identically
 *
 * `bbp_new_topic_handler()` and `bbp_new_reply_handler()` differ in almost everything —
 * what they validate, what they insert, what they fire afterwards — but this middle
 * stretch is the same four questions in the same order, against the same three bbPress
 * functions. Copied into both services it would be two places to keep in step with a
 * bbPress upgrade; here it is one, and the ordering is written down once.
 *
 * ## The order is the behaviour
 *
 * Flood, then duplicate, then the *disallowed* list, then the *moderation* list. The
 * last two are the same bbPress function under a flag and they mean opposite things:
 * failing the disallowed list refuses the post outright, failing the moderation list
 * holds it for a moderator. Asking them the other way round would hold posts that should
 * be refused.
 *
 * ⚠ **The first refusal wins, and bbPress's does not.** The browser handlers collect
 * every complaint and redraw the form with all of them listed; a REST error envelope has
 * one `code`, so the first is returned and the rest are not run. A member fixing one
 * problem may therefore meet the next one on their second attempt — which is what an app
 * showing one message at a time would do with a list anyway.
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
	 * The status this post should be created under, or why it should not be.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $author_id Author ID.
	 * @param int    $parent_id Forum ID for a topic, topic ID for a reply.
	 * @param string $post_type Post type being written.
	 * @param string $title     Title, already filtered.
	 * @param string $content   Content, already filtered.
	 * @return string|\WP_Error bbPress's public or pending status.
	 */
	public function status( int $author_id, int $parent_id, string $post_type, string $title, string $content ) {
		if ( ! $this->rest->passes_flood_check( $author_id ) ) {
			return $this->refuse( 'rate_limited', __( 'Slow down; you are posting too fast.', 'jtzl-bulletin' ), 429 );
		}

		if ( ! $this->rest->passes_duplicate_check( $post_type, $author_id, $parent_id, $content ) ) {
			return $this->refuse( 'duplicate_post', __( 'It looks as though you have already said that.', 'jtzl-bulletin' ), 409 );
		}

		// The disallowed list. ⚠ Its refusal says nothing about *why* — naming the word
		// that caught it would hand a spammer the list one guess at a time.
		if ( ! $this->rest->passes_moderation_check( $author_id, $title, $content, true ) ) {
			return $this->refuse( 'moderation_rejected', __( 'This post cannot be created at this time.', 'jtzl-bulletin' ), 400 );
		}

		return $this->rest->passes_moderation_check( $author_id, $title, $content, false )
			? $this->wp->get_public_status_id()
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
