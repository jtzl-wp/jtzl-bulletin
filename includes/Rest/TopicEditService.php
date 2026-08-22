<?php
/**
 * Editing a thread, over the API.
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
 * `bbp_edit_topic_handler()`'s lifecycle, with a return value where its redirect was.
 *
 * ## Why this is not a method on Rest\TopicMutationService
 *
 * It was, first — and PHPMD is what said no, at 542 lines against a gate of 350 and a
 * coupling of 10 against a gate of 10. The cut it forced is the real one: bbPress keeps
 * creating and editing in two handlers because they are two policies, not one with a
 * flag. An edit asks **no** flood check and **no** duplicate check, it *keeps* a status
 * rather than choosing one, and it has a rule a create cannot have — what to do about a
 * field the request left out. The two share collaborators and nothing else.
 *
 * ## An omitted field is a resubmission, not an absence
 *
 * bbPress's edit form always carries the whole post, so its handler has no notion of a
 * field being left out: it filters, checks and stores a title and a body on every edit.
 * A PATCH naming only one of them is therefore *merged* with what is stored before any
 * of that runs, and the full lifecycle then sees a complete post.
 *
 * ⚠ **That is load-bearing for moderation.** `bbp_check_for_moderation()` is handed the
 * title *and* the body together; running it over a content-only PATCH with an empty
 * title would let a word sitting in the stored title escape a check the browser makes.
 *
 * ## Three places this deliberately differs from the handler
 *
 * 1. **The first refusal wins**, as on create: the handler collects every complaint and
 *    redraws the form, a REST envelope carries one `code`.
 * 2. **No `unfiltered_html` escape**, as on create. kses always applies here.
 * 3. **No `bbp_topic_status` override.** A moderator naming the status an edit lands in
 *    is moderation, not authorship, and this route is author-only — so the branch that
 *    reads it is absent along with the error it raises when it is not permitted.
 *
 * Also absent, and this one is bbPress's own structure rather than a choice: the forum
 * is never moved, so the category, closed, private and hidden re-checks its handler
 * guards a *change* of forum with are unreachable and not carried.
 *
 * @since 0.6.0
 */
class TopicEditService {

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
	 * What the site has switched on.
	 *
	 * @var FeatureGate
	 * @since 0.6.0
	 */
	private FeatureGate $features;

	/**
	 * Title, content, tags and moderation.
	 *
	 * @var ContentGuard
	 * @since 0.6.0
	 */
	private ContentGuard $guard;

	/**
	 * The window a form-shaped hook runs inside.
	 *
	 * @var BbpHookScope
	 * @since 0.6.0
	 */
	private BbpHookScope $scope;

	/**
	 * The pre-insert chain, run without letting Akismet end the request.
	 *
	 * @var AkismetPreInsertAdapter
	 * @since 0.6.0
	 */
	private AkismetPreInsertAdapter $akismet;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param ContextInterface        $wp       WordPress/bbPress seam.
	 * @param RestContextInterface    $rest     REST-side WordPress/bbPress seam.
	 * @param FeatureGate             $features What the site has switched on.
	 * @param ContentGuard            $guard    Title, content, tags and moderation.
	 * @param BbpHookScope            $scope    Pre-save hook compatibility window.
	 * @param AkismetPreInsertAdapter $akismet  Pre-insert filter chain.
	 */
	public function __construct(
		ContextInterface $wp,
		RestContextInterface $rest,
		FeatureGate $features,
		ContentGuard $guard,
		BbpHookScope $scope,
		AkismetPreInsertAdapter $akismet
	) {
		$this->wp       = $wp;
		$this->rest     = $rest;
		$this->features = $features;
		$this->guard    = $guard;
		$this->scope    = $scope;
		$this->akismet  = $akismet;
	}

	/**
	 * Edit a thread, as its author.
	 *
	 * @since 0.6.0
	 *
	 * @param int                 $topic_id  Thread being edited.
	 * @param int                 $author_id Editor, already known to be its author.
	 * @param array<string,mixed> $changes   Fields the request actually carried.
	 * @return MutationResult|\WP_Error
	 */
	public function update( int $topic_id, int $author_id, array $changes ) {
		$allowed = $this->may_edit( $topic_id, $changes );

		if ( true !== $allowed ) {
			return $allowed;
		}

		$terms = $this->edited_terms( $topic_id, $changes );

		if ( ! is_array( $terms ) ) {
			return $terms;
		}

		$title   = $this->merged( $changes, 'title', $this->rest->get_post_title_raw( $topic_id ) );
		$content = $this->merged( $changes, 'content', $this->rest->get_post_content_raw( $topic_id ) );
		$title   = (string) $this->wp->apply_filters( 'bbp_edit_topic_pre_title', $title, $topic_id );
		$content = (string) $this->wp->apply_filters( 'bbp_edit_topic_pre_content', $content, $topic_id );
		$status  = $this->settle_status( $topic_id, $author_id, $title, $content );

		if ( ! is_string( $status ) ) {
			return $status;
		}

		$forum_id = $this->wp->get_topic_forum_id( $topic_id );
		$refused  = $this->scope->run(
			'bbp_edit_topic_pre_extras',
			array( $topic_id ),
			array(
				'bbp_topic_id'      => (string) $topic_id,
				'bbp_topic_title'   => $title,
				'bbp_topic_content' => $content,
				'bbp_forum_id'      => (string) $forum_id,
				'bbp_topic_tags'    => implode( ',', $terms ),
			)
		);

		if ( true !== $refused ) {
			return $refused;
		}

		return $this->save( $topic_id, $forum_id, $title, $content, $status, $terms );
	}

	/**
	 * The gates an edit is asked, neither of which a create is.
	 *
	 * ⚠ **The tag capability is asked on *presence*, not on a non-empty list**, and that
	 * is deliberately not what bbPress does. Its handler asks
	 * `current_user_can( 'assign_topic_tags' )` only on the branch that *replaces* tags;
	 * a member without the capability who submits the field anyway falls through to the
	 * `elseif ( isset( ... ) )` branch, which **clears every tag on the thread** with no
	 * capability asked at all. Sending `tags: []` is assigning the empty set, so it is
	 * refused here like any other assignment. Whether the site has topic tags switched on
	 * is asked only when the request mentions them, exactly as on create.
	 *
	 * @since 0.6.0
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
	 * The tags this edit should leave on the thread.
	 *
	 * ⚠ **An untagged thread yields an empty list, where bbPress yields `array( '' )`.**
	 * Its preserve branch is `explode( ',', bbp_get_topic_tag_names( $topic_id, ',' ) )`,
	 * and exploding an empty string gives a one-element array holding an empty name.
	 * `wp_set_post_terms()` drops it, so the outcome is the same and only the shape is
	 * not; this returns the empty list that outcome implies.
	 *
	 * @since 0.6.0
	 *
	 * @param int                 $topic_id Thread being edited.
	 * @param array<string,mixed> $changes  Fields the request actually carried.
	 * @return string[]|\WP_Error Slashed tag names.
	 */
	private function edited_terms( int $topic_id, array $changes ) {
		if ( ! array_key_exists( 'tags', $changes ) ) {
			$existing = $this->rest->get_topic_tag_names( $topic_id );

			return '' === $existing ? array() : array_map( array( $this->rest, 'slash' ), explode( ',', $existing ) );
		}

		$names = $this->guard->tags( is_array( $changes['tags'] ) ? $changes['tags'] : array() );

		return is_array( $names ) ? array_map( array( $this->rest, 'slash' ), $names ) : $names;
	}

	/**
	 * A field this edit named, or the one already stored, slashed either way.
	 *
	 * Both need it: REST hands over an unslashed string, and so does the database.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string,mixed> $changes Fields the request actually carried.
	 * @param string              $key     Field name.
	 * @param string              $stored  The value currently stored.
	 * @return string
	 */
	private function merged( array $changes, string $key, string $stored ): string {
		$value = array_key_exists( $key, $changes ) ? $changes[ $key ] : $stored;

		return $this->rest->slash( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * The title, content and moderation questions, in the handler's order.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $topic_id  Thread being edited.
	 * @param int    $author_id Author ID.
	 * @param string $title     Merged, filtered title.
	 * @param string $content   Merged, filtered content.
	 * @return string|\WP_Error The status to store under.
	 */
	private function settle_status( int $topic_id, int $author_id, string $title, string $content ) {
		$checked = $this->guard->title( $title, true );

		if ( true !== $checked ) {
			return $checked;
		}

		$checked = $this->guard->content( $content );

		if ( true !== $checked ) {
			return $checked;
		}

		return $this->guard->edited_status( $author_id, $title, $content, $this->wp->get_post_status( $topic_id ) );
	}

	/**
	 * The pre-insert filter, the update, and everything bbPress does after it.
	 *
	 * ⚠ **None of create's close, trash or spam bookkeeping runs here**, because
	 * bbPress's edit handler does not run it either: those calls exist to place a *new*
	 * post into a container that is already closed or trashed, and an edit's container
	 * has not moved. The filtered status is still read, but only to decide whether the
	 * author can be handed the thread back.
	 *
	 * ⚠ **The author is the stored one, not the caller.** They are the same person here
	 * — `Rest\AccessPolicy::can_edit_topic()` saw to that — but bbPress writes
	 * `post_author` from the post rather than from the session, and an edit that quietly
	 * reassigned authorship would be a worse defect than one that refused.
	 *
	 * @since 0.6.0
	 *
	 * @param int      $topic_id Thread being edited.
	 * @param int      $forum_id Forum it sits in.
	 * @param string   $title    Merged, filtered title.
	 * @param string   $content  Merged, filtered content.
	 * @param string   $status   Status settled by the moderation checks.
	 * @param string[] $terms    Slashed tag names.
	 * @return MutationResult|\WP_Error
	 */
	private function save( int $topic_id, int $forum_id, string $title, string $content, string $status, array $terms ) {
		$data = $this->akismet->apply(
			'bbp_edit_topic_pre_insert',
			array(
				'ID'           => $topic_id,
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => $status,
				'post_parent'  => $forum_id,
				'post_author'  => $this->wp->get_post_author( $topic_id ),
				'post_type'    => $this->wp->get_topic_post_type(),
				'tax_input'    => array( $this->rest->topic_tag_taxonomy() => $terms ),
			)
		);

		// Discarded. Nothing was written, no lifecycle hook ran, and the caller is told
		// only that the request was accepted.
		if ( null === $data ) {
			return MutationResult::accepted();
		}

		$updated = $this->rest->update_post( $data );

		if ( ! is_int( $updated ) || $updated <= 0 ) {
			return $this->refuse( 'write_failed', __( 'The thread could not be saved.', 'jtzl-bulletin' ), 500 );
		}

		$this->rest->fire_edit_topic( $topic_id, $forum_id, (int) ( $data['post_author'] ?? 0 ) );
		$this->rest->record_edit( $topic_id );
		$this->rest->fire_post_extras( 'bbp_edit_topic_post_extras', $topic_id );

		$stored = (string) ( $data['post_status'] ?? '' );

		return $this->rest->get_trash_status_id() === $stored || $this->rest->get_spam_status_id() === $stored
			? MutationResult::accepted()
			: MutationResult::entity( $topic_id );
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
