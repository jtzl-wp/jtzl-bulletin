<?php
/**
 * The reading view's compose slot.
 *
 * @package JTZL\Bulletin
 * @since 0.5.0
 */

namespace JTZL\Bulletin\View;

use JTZL\Bulletin\WordPress\ContextInterface;

/**
 * One slot at the foot of a thread, in one of five states.
 *
 * ## Why it is here and not in the bottom bar
 *
 * The fixed bottom bar is thread Prev/Next, and it was **JT's own proposal**. A
 * compose bar in that slot is arguing with the client's own idea rather than with an
 * implementation detail, so the composer sits inline in the content flow — the same
 * resolution "load more replies" took, and for the same reason.
 *
 * ## Why it rests collapsed
 *
 * The reading view is a Read surface first. On a Read surface an empty input is
 * louder than a button — a button says "you may", an empty field says "you should" —
 * and an always-open field would also grow under the finger on focus, shifting layout
 * directly above a fixed bar. bbPress's own default is ~500px of form on every
 * thread, including the ones nobody answers.
 *
 * ⚠ **The collapse is applied by the script, never by this class.** What renders here
 * is the whole form; `reading.ts` adds `is-collapsed` to the wrapper and CSS folds it
 * behind the trigger. So a reader with no JavaScript gets *more* than intended rather
 * than less, which is the only safe direction — issue #62's scar was a control hidden
 * by a signal the script did not control.
 *
 * ## The five states, and why two of them are silent in different ways
 *
 * | State | The slot shows |
 * |---|---|
 * | Can reply | the composer |
 * | Closed forum | "This forum is closed to new posts." |
 * | Closed topic | "This thread is closed to new replies." |
 * | Logged out | "Sign in to reply", carrying this URL back |
 * | No capability | nothing at all |
 *
 * **Closed speaks and no-capability does not**, and the asymmetry is the point.
 * *Closed is a fact about this thread*, and the foot is exactly where a reader needs
 * it — the header chip was forty replies ago. *No-capability is a fact about the
 * reader*, true on every thread forever; saying it twelve times in a session is
 * nagging with no action attached. A fact that changes per screen is stated per
 * screen; a fact that never changes is not stated at all.
 *
 * The closed line is quiet sans on `--bltn-ink-3` — no box, no tint, and emphatically
 * not `--bltn-caution`. DESIGN.md is explicit that a closed thread is not a negative
 * ("often the one most worth reading: resolved, archived, canonical"), so severity is
 * the wrong channel. bbPress's own `.bbp-template-notice` band was declined for the
 * same reason.
 *
 * @since 0.5.0
 */
class ComposeSlot {

	/**
	 * A template name chosen so that bbPress will not find it.
	 *
	 * ⚠ **An empty array would be the obvious way to suppress a template part, and it
	 * raises a PHP warning.** `bbp_locate_template()` reads `$template_name` after its
	 * own `foreach` to pass into the `bbp_locate_template` action
	 * (`core/template-functions.php:99`), so with nothing to iterate the variable was
	 * never assigned. Naming a file that cannot exist lets the loop run once, find
	 * nothing, and leave `$located` false — the same outcome, without reaching into
	 * upstream's scope.
	 *
	 * @since 0.5.0
	 *
	 * @var string
	 */
	private const NOWHERE = 'jtzl-bulletin-suppressed.php';

	/**
	 * WordPress/bbPress seam.
	 *
	 * @var ContextInterface
	 */
	private ContextInterface $wp;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param ContextInterface $wp WordPress/bbPress seam.
	 */
	public function __construct( ContextInterface $wp ) {
		$this->wp = $wp;
	}

	/**
	 * Echo the slot for a topic.
	 *
	 * ⚠ **Capability is asked first, before either closed test.** A keymaster may
	 * reply on a closed topic — `bbp_current_user_can_access_create_reply_form()`
	 * short-circuits for them (`users/template.php:2326`) — so testing "closed" ahead
	 * of it would take the composer away from the one person the exception exists for.
	 *
	 * ⚠ **And closed is asked before logged-out.** On a closed thread nobody may
	 * reply, so "Sign in to reply" would be an invitation to do something signing in
	 * cannot enable. The forum's own closure is named before the topic's because it
	 * explains more: it is why every thread in that forum is closed, not just this one.
	 *
	 * @since 0.5.0
	 *
	 * @param int $topic_id Topic being read.
	 * @param int $forum_id Forum it belongs to.
	 */
	public function render( int $topic_id, int $forum_id ): void {
		if ( $this->wp->can_access_create_reply_form() ) {
			$this->composer();
			return;
		}

		if ( $this->wp->is_forum_closed( $forum_id ) ) {
			$this->note( __( 'This forum is closed to new posts.', 'jtzl-bulletin' ) );
			return;
		}

		if ( $this->wp->is_topic_closed( $topic_id ) ) {
			$this->note( __( 'This thread is closed to new replies.', 'jtzl-bulletin' ) );
			return;
		}

		if ( ! $this->wp->is_user_logged_in() ) {
			$this->sign_in();
		}
	}

	/**
	 * The reply form itself — bbPress's — wrapped so the script can collapse it.
	 *
	 * The form itself is bbPress's, rendered through its template part — this plugin
	 * registers no template stack and never has, so the fields, the nonces, the
	 * anonymous block and the whole write path stay upstream's. What is ours is the
	 * wrapper, the trigger, and the stylesheet.
	 *
	 * `data-bltn-compose` carries the resting state rather than the script deriving
	 * it: a deep link (`?bbp_reply_to={id}#new-post`) names a post the reader has
	 * already chosen to answer, so the composer opens focused instead of asking them
	 * to ask again. The server knows that; the script should not have to re-read the
	 * query string to find out.
	 *
	 * ⚠ **Asked of the request, not of `bbp_get_form_reply_to()`** — see
	 * `ContextInterface::get_requested_reply_to()`. That helper falls back to the
	 * current loop reply's stored parent, and this renders directly after a replies
	 * loop, so it would have been answering a question about bbPress's loop state.
	 *
	 * @since 0.5.0
	 */
	private function composer(): void {
		$state = $this->opens_expanded() ? 'open' : 'collapsible';

		printf(
			'<div class="bltn-compose" data-bltn-compose="%s">',
			esc_attr( $state )
		);

		/*
		 * The trigger. Hidden by default and revealed by the collapse class, so the
		 * no-JS document shows the form and never a button that cannot open anything.
		 * `aria-expanded` starts true for the same reason: what is rendered IS open.
		 */
		printf(
			'<button type="button" class="bltn-compose__open" aria-expanded="true" aria-controls="new-post" data-bltn-compose-open data-bltn-cancel="%s">%s</button>',
			esc_attr__( 'Cancel', 'jtzl-bulletin' ),
			esc_html__( 'Write a reply', 'jtzl-bulletin' )
		);

		echo '<div class="bltn-compose__form">';
		$this->form();
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Render bbPress's reply form with its legend reduced to text.
	 *
	 * ⚠ **This closes a hole we would otherwise open on our own tier.** bbPress builds
	 * the legend as `printf( esc_html__( 'Reply To: %s' ), bbp_get_topic_title() )`
	 * (`form-reply.php:30`) — the *format string* is escaped and the substitution is
	 * not, so a title carrying markup lands in the document as markup. That is
	 * bbPress's behaviour on every theme and not a bug we introduced, but until P4 no
	 * takeover screen rendered that template: the reading view escaped the title in all
	 * three places it appeared. Rendering the form here is what puts it in reach, so
	 * closing it is this phase's job.
	 *
	 * `wp_strip_all_tags()` rather than `esc_html()`, and the difference matters: a
	 * title legitimately containing `&amp;` would come back as `&amp;amp;` from
	 * escaping something bbPress then escapes again. Stripping removes markup and
	 * leaves entities alone — and it is the right transform on its own terms, because
	 * this string is an accessible name, and a name is text.
	 *
	 * Added and removed around the single call rather than left on, in a `finally` for
	 * the same reason `WordPressContext::moderation_links()` uses one: bbPress runs
	 * plugin hooks while rendering, and one of those throwing would otherwise leave the
	 * filter attached for the rest of the request — stripping markup out of a title
	 * somewhere that wanted it.
	 *
	 * @since 0.5.0
	 */
	private function form(): void {
		$text_only = static fn( $title ) => is_string( $title ) ? wp_strip_all_tags( $title ) : $title;

		/*
		 * And no allowed-tags list. It is a notice box enumerating every permitted HTML
		 * tag with its attributes, nested between the textarea and the submit — a wall
		 * of markup on a phone.
		 *
		 * ⚠ Dropped because decision 1 supplies the replacement, not merely to save
		 * room: with the quicktags strip kept, the buttons ARE the discoverable version
		 * of this information, and the list is the desktop-era fallback for people
		 * hand-writing HTML. Removing something that has a better replacement in place
		 * is the strongest form of the subtraction rule, not the weakest. It stays on
		 * the reskin tier, where bbPress's own layout makes it unremarkable and the
		 * edit forms are reached by people more likely to be writing markup on purpose.
		 *
		 * Note this is usually moot and deliberately not relied on being so: the
		 * template renders nothing at all unless a site switches `bbp_use_wp_editor`
		 * off (`form-allowed-tags.php:13`), which is exactly the configuration where a
		 * plain textarea has no strip and the list would be the loudest thing on the
		 * screen.
		 */
		$no_allowed_tags = static fn( $templates, $slug, $name ) =>
			( 'form' === $slug && 'allowed-tags' === $name )
				? array( self::NOWHERE )
				: $templates;

		add_filter( 'bbp_get_topic_title', $text_only );
		add_filter( 'bbp_get_template_part', $no_allowed_tags, 10, 3 );

		try {
			bbp_get_template_part( 'form', 'reply' );
		} finally {
			remove_filter( 'bbp_get_topic_title', $text_only );
			remove_filter( 'bbp_get_template_part', $no_allowed_tags, 10 );
		}
	}

	/**
	 * Whether the composer must arrive open rather than at rest.
	 *
	 * Two reasons, and the second is a bug report rather than a design decision.
	 *
	 * **A deep link** (`?bbp_reply_to={id}#new-post`) names a post the reader has
	 * already chosen to answer. Asking them to press "Write a reply" after they pressed
	 * "Reply To" asks the same question twice.
	 *
	 * ⚠ **A rejected reply, and this one is not optional.** bbPress prints validation
	 * failures INSIDE the form (`bbp_template_notices()` in `form-reply.php`), and it
	 * puts the reader's own typed content back in the field with them. Collapsing that
	 * hides both: the reader submits, the page reloads, and **nothing appears to have
	 * happened** — the exact silent failure this phase set out to fix, reintroduced one
	 * layer further down by the collapse that came after the fix. Reported from the
	 * fixture, reproduced with `errorInDom: true, errorVisible: false`.
	 *
	 * Asked here rather than left to the script, for the same reason the deep-link
	 * state is: the server knows, and a script that had to discover it would be reading
	 * markup bbPress owns.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	private function opens_expanded(): bool {
		return $this->wp->has_errors() || $this->wp->get_requested_reply_to() > 0;
	}

	/**
	 * One quiet line: a fact about this thread, stated where a reader meets it.
	 *
	 * @since 0.5.0
	 *
	 * @param string $text The line.
	 */
	private function note( string $text ): void {
		printf(
			'<div class="bltn-compose"><p class="bltn-compose__note">%s</p></div>',
			esc_html( $text )
		);
	}

	/**
	 * The sign-in control, carrying this URL — query string included — back.
	 *
	 * ⚠ **The query string is the load-bearing part.** `?bbp_reply_to={id}` names the
	 * post the reader meant to answer; a redirect built from the topic permalink alone
	 * loses it across the login round-trip, and they come back to a composer aimed at
	 * the thread instead of at the post. bbPress's own form gets this right by
	 * defaulting `bbp_redirect_to_field()` to `REQUEST_URI`, and substituting our own
	 * control for that form must not regress it.
	 *
	 * This is a substitution and it cuts against issue #18's precedent, which chose to
	 * render WordPress's own password form inside our shell rather than replace it.
	 * Different situation: #18's prompt IS the screen's entire content, with nothing
	 * else to show. bbPress's login form here would be an appendage under a fully
	 * readable thread — and it hardcodes `autocomplete="off"` on both credential
	 * fields, which fights iOS Keychain and every password manager, unfixably without
	 * owning a template copy.
	 *
	 * @since 0.5.0
	 */
	private function sign_in(): void {
		$here = $this->wp->get_current_url();

		printf(
			'<div class="bltn-compose"><a class="bltn-compose__open" href="%s">%s</a></div>',
			esc_url( $this->wp->get_login_url( $here ) ),
			esc_html__( 'Sign in to reply', 'jtzl-bulletin' )
		);
	}
}
