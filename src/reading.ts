/**
 * Bulletin — reading behaviour (DOM entry).
 *
 * Two jobs: the inline "load more" controls, and resolving a deep-link to a reply
 * that lives past the first page (the initial DOM only holds page 1, so we load
 * forward until the target exists, then scroll to it).
 *
 * The control serves three paginated lists — more replies inside a thread, more
 * threads inside a forum, more forums inside the index or a parent forum — and says
 * which it is: the endpoint, the subject, and the element to append to all ride on
 * its data attributes (see View\LoadMore). So this file holds no per-screen
 * knowledge, and each control's idle label is read back from the button the server
 * already rendered rather than localised twice.
 *
 * A screen may carry more than one: a forum with sub-forums has a control for those
 * AND one for its threads. Each therefore owns its own state — its append target, its
 * label, whether a request is in flight — and they never interfere.
 *
 * Plain navigation (tapping a forum or thread, Prev/Next) needs no JavaScript —
 * those are ordinary links.
 */

import {
	buildRequestBody,
	parseLoadMoreResponse,
	type BltnConfig,
	type LoadMoreData,
} from './reading-core';

declare global {
	interface Window {
		BLTN?: BltnConfig;
	}
}

/**
 * A wired-up load-more control, as the deep-link walker sees it.
 *
 * Deliberately just the one method. A control that has served its last page removes
 * itself and its loadNext() becomes a no-op resolving false, so a caller holding a
 * stale handle needs no liveness check of its own — calling it is already safe, and
 * the false answer already stops the walk.
 */
interface Control {
	/** Fetch the next page; resolves true when a further page remains. */
	loadNext(): Promise<boolean>;
}

/**
 * Move the app's scroll region so `el` sits at its top.
 *
 * The app's own scroller is moved directly rather than through scrollIntoView(),
 * which scrolls every scrollable ancestor. That used to be load-bearing: the shell
 * clipped with `overflow: hidden`, which stops a *user* scrolling a box and does
 * nothing about the API, so the viewport went up with the scroller and left the app
 * bar above the top of the screen. Measured on an arriving permalink and on a
 * reply-context tap (issue #37).
 *
 * #66 closed that in CSS — `.bltn-app` now establishes the containing block and clips
 * without being a scroll container, so no ancestor of a target is scrollable any more.
 * This stays anyway: it says which box moves and by how much, rather than asking the
 * browser to work it out, and it is the same one line either way. scrollIntoView()
 * remains the fallback for a target outside a `.bltn-scroll` — nothing renders one
 * today, and if something does, the old behaviour is better than none.
 *
 * ⚠ **`block: 'start'` reads as "as far up as the scroller will go", and the clamp is
 * doing design work.** A composer is the last thing in its scroll region, so the
 * browser stops at the end of the content rather than at the requested offset: the
 * form lands against the foot of the screen with the thread still above it, instead of
 * being dragged to the top with a band of nothing under it.
 *
 * Instant rather than smooth is for a scroll the reader did not ask for — arriving on
 * a screen that is already in the state they wanted. Animating a journey nobody
 * started reads as the page moving by itself.
 */
function scrollAppTo(el: HTMLElement, instant = false): void {
	const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const behavior: ScrollBehavior = reduce || instant ? 'auto' : 'smooth';
	const scroller = el.closest<HTMLElement>('.bltn-scroll');

	if (scroller) {
		const delta =
			el.getBoundingClientRect().top - scroller.getBoundingClientRect().top;
		scroller.scrollTo({ top: scroller.scrollTop + delta, behavior });
	} else {
		el.scrollIntoView({ behavior, block: 'start' });
	}
}

/**
 * The first thing in a composer a member types into.
 *
 * ⚠ **Neither "the textarea" nor "the first focusable element" is right, and both were
 * tried.** bbPress's own field order says why, measured on 2.6.14:
 *
 * | Form | DOM order |
 * |---|---|
 * | `form-topic.php` | anonymous fields (l.80) → **title** (l.86) → 11 quicktag buttons → body |
 * | `form-reply.php` | anonymous fields (l.68) → 11 quicktag buttons → **body** |
 *
 * Taking the textarea skips the topic form's **title**, which is the one field
 * `bbp_new_topic_handler()` refuses without — so a member wrote a whole post and was
 * answered "Your topic needs a title." for a field the composer had moved them past
 * (#118). Taking the first *focusable* element lands on the `b` quicktag button on the
 * reply form, because those eleven `input[type=button]`s sit between the anonymous
 * block and the body.
 *
 * So the rule is the first **entry** field: buttons, checkboxes, radios and hidden
 * inputs are not places to start, and a disabled field cannot be focused at all —
 * `focus()` on one silently does nothing, which is the failure this must not have.
 *
 * It follows rather than special-cases. On the topic form it resolves to the title; on
 * the reply form to the body; and for a logged-out visitor on either, to the anonymous
 * **name** — which is correct for the same reason the title is, since bbPress refuses
 * an anonymous post with no name or email. "Start where the form starts" needs no
 * screen to be named, and survives a template stack reordering anything.
 */
const ENTRY_FIELD =
	'input:not([type=hidden]):not([type=checkbox]):not([type=radio])' +
	':not([type=button]):not([type=submit]):not([type=reset]):not([disabled]),' +
	'textarea:not([disabled]), select:not([disabled])';

/**
 * Moderation mode (issue #36).
 *
 * The trays are already in the DOM — server-rendered under the thread header and under
 * every post — so this only flips the class the stylesheet keys on. Nothing is fetched,
 * injected or re-applied, which is why a reply appended by a load-more control needs no
 * involvement here: it lands inside the same article and inherits the state.
 *
 * `bltn-thread--modready` is the contract, and it is what makes the degradation safe.
 * The stylesheet hides a tray ONLY inside a thread carrying that class, and shows the
 * toggle only there too — so until this function has actually run and attached its
 * listener, a moderator sees every tray inline and no toggle, which is exactly bbPress's
 * own behaviour. An earlier version keyed the hiding on `@media (scripting: enabled)`,
 * which asks whether scripting is on rather than whether THIS ran: a bundle that 404s,
 * throws before this line, or finds no BLTN config would have left the toggle visible and
 * inert with every tray hidden. A broken control is worse than no control (raised by Qodo
 * on #62).
 *
 * Deliberately outside initReading() and called before it: moderation needs neither the
 * AJAX endpoint nor the localised strings, so it must not inherit that function's early
 * return on a missing config, nor an exception thrown anywhere inside it.
 *
 * The label names the next action ("Moderate" → "Done") and aria-expanded carries the
 * state the label therefore cannot. No aria-controls: the mode reveals the thread's tray
 * AND a tray under every post, so naming one region would describe less than the button
 * already does.
 */
function initModerationToggle(): void {
	const toggle = document.querySelector<HTMLButtonElement>(
		'[data-bltn-modtoggle]'
	);
	const thread = toggle?.closest<HTMLElement>('.bltn-thread');
	if (!toggle || !thread) {
		return;
	}

	/*
	 * Both fallbacks are type ceremony, not behaviour, and neither side of them can
	 * be reached from markup we produce — so they are excluded rather than covered by
	 * a test asserting a state that cannot occur. `Node.textContent` is typed
	 * `string | null` because it IS null on a document, a doctype or a notation; on an
	 * element it never is. And `DOMStringMap` values are `string | undefined` because
	 * the attribute may be absent, while `View\ModerationActions::render_toggle()`
	 * always writes `data-bltn-label-on` — an empty translation would leave it present
	 * and empty, which is a value, not a miss.
	 *
	 * Same rule the PHP side applies to its ABSPATH guards: mark what is unreachable
	 * by construction, never what is merely untested.
	 *
	 * v8 has no per-branch marker, so the two lines leave the report entirely — but
	 * not the suite. "works with no BLTN config at all" clicks the toggle and asserts
	 * it reads "Done", which is both labels resolved and swapped; the assertions are
	 * what protect these lines, and always were.
	 */
	/* v8 ignore start */
	const labelOff = toggle.textContent ?? '';
	const labelOn = toggle.dataset.bltnLabelOn ?? labelOff;
	/* v8 ignore stop */

	toggle.addEventListener('click', () => {
		const on = thread.classList.toggle('bltn-thread--moderating');
		toggle.setAttribute('aria-expanded', on ? 'true' : 'false');
		toggle.textContent = on ? labelOn : labelOff;
	});

	// Last, so there is no instant in which the trays are hidden by a toggle that
	// cannot yet answer a click.
	thread.classList.add('bltn-thread--modready');
}

function initReading(): void {
	const config = window.BLTN;
	if (!config) {
		return;
	}

	// Resolve labels once. Spreading an absent i18n object is a no-op, so any key
	// the server didn't localise keeps its default.
	const labels = {
		loading: 'Loading…',
		error: 'Could not load more. Tap to retry.',
		loadedOne: '1 more loaded.',
		loadedMany: '%d more loaded.',
		...config.i18n,
	};
	const ajaxUrl = config.ajaxUrl;

	// A control the server rendered carries all of these; one that somehow does
	// not still yields a well-formed request the server can refuse, rather than a
	// null that would throw on the way out.
	const attr = (el: Element, name: string): string =>
		el.getAttribute(name) ?? '';

	function fetchPage(
		control: HTMLElement,
		page: number
	): Promise<LoadMoreData> {
		const body = buildRequestBody(
			attr(control, 'data-action'),
			attr(control, 'data-param'),
			attr(control, 'data-id'),
			page
		);

		return fetch(ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type':
					'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body,
			credentials: 'same-origin',
		})
			.then((res) => {
				if (!res.ok) {
					throw new Error('HTTP ' + res.status);
				}
				return res.json();
			})
			.then((payload) => parseLoadMoreResponse(payload));
	}

	/**
	 * Wire one control and close over everything specific to it.
	 *
	 * Nothing here is shared between controls, which is the point: on a screen with
	 * two lists, a request in flight for one must not disable the other, and each
	 * has its own append target and its own idle label naming its own list.
	 */
	function initControl(control: HTMLElement): Control {
		// Where rows land. Resolved from the control so the same script serves the
		// replies container on one screen and a forum or thread list on another.
		const container = document.getElementById(attr(control, 'data-target'));

		// The label the server rendered, kept so the idle state can be restored
		// verbatim after loading or an error — including which list it names.
		const idleLabel =
			control
				.querySelector<HTMLButtonElement>('.bltn-loadmore__btn')
				?.textContent?.trim() ?? '';

		let loading = false;
		let live = true;

		function setState(state: 'idle' | 'loading' | 'error'): void {
			const btn = control.querySelector<HTMLButtonElement>(
				'.bltn-loadmore__btn'
			);
			control.classList.toggle('is-loading', state === 'loading');
			control.classList.toggle('is-error', state === 'error');
			if (!btn) {
				return;
			}
			if (state === 'loading') {
				btn.textContent = labels.loading;
				btn.disabled = true;
			} else if (state === 'error') {
				btn.textContent = labels.error;
				btn.disabled = false;
			} else {
				btn.textContent = idleLabel;
				btn.disabled = false;
			}
		}

		// Resolved at init, not at append time, because the control removes itself
		// on its last page and this element is its sibling — after the removal
		// there is no control left to look next to, and the last page is the one
		// whose arrival most needs announcing.
		const status = control.nextElementSibling?.matches(
			'[data-bltn-loadmore-status]'
		)
			? (control.nextElementSibling as HTMLElement)
			: null;

		/** Announce what arrived, for a reader who cannot see it arrive. */
		function announce(added: number): void {
			if (!status || added < 1) {
				return;
			}
			status.textContent =
				added === 1
					? labels.loadedOne
					: labels.loadedMany.replace('%d', String(added));
		}

		/**
		 * Move focus off the control before it is removed.
		 *
		 * Takes the element rather than a count. Deriving it from
		 * `children[length - appended]` was the first shape of this and needed a
		 * guard for an index that could not actually occur — `appendRows()` already
		 * holds the node, so handing it over removes both the arithmetic and the
		 * unreachable branch.
		 *
		 * Only fires when the control actually held focus. In a browser a pointer
		 * click on a `<button>` focuses it, so that is the usual case; what the guard
		 * protects is programmatic activation, where focus is elsewhere and moving it
		 * would be unasked-for. `preventScroll` is what keeps it from moving the
		 * viewport either way.
		 */
		function rehomeFocus(first: Element | null): void {
			if (!first || !control.contains(document.activeElement)) {
				return;
			}
			first.setAttribute('tabindex', '-1');
			(first as HTMLElement).focus({ preventScroll: true });
		}

		/**
		 * The row shapes every list this control serves can arrive in.
		 *
		 * Counting the container's direct children instead would be wrong on one list
		 * and right on the rest, which is the worst kind of wrong. The Subscribed
		 * Forums continuation appends each page as a single `ul.bbp-forums >
		 * li.bbp-body` block (see DESIGN.md #50 — both stylesheets select on that
		 * chain), so a page of five forums is ONE child and would have announced
		 * "1 more loaded." The takeover lists append one element per row.
		 */
		const ROW_SELECTOR =
			'.bltn-row, .bltn-post, li.bbp-body ul.forum, li.bbp-body ul.topic, li.bbp-body div.reply';

		/**
		 * What arrived, in the two forms the callers need.
		 *
		 * `rows` is what a reader would say arrived, and it is what gets announced.
		 * `first` is the element focus lands on when the control removes itself. They
		 * are separate because the row count and the appended-element count are not
		 * the same number on every list: the Subscribed Forums continuation appends a
		 * whole page as ONE `ul.bbp-forums > li.bbp-body` block (DESIGN.md #50), so
		 * five forums arrive as five rows and one child. Announcing the child count
		 * there said "1 more loaded."
		 *
		 * Both are read off the fragment before it is appended — `appendChild` empties
		 * it, but a node reference taken beforehand stays valid and is then in the
		 * document, which is exactly what focus needs.
		 */
		function appendRows(html: string): { rows: number; first: Element | null } {
			if (!html || !container) {
				return { rows: 0, first: null };
			}
			const frag = document.createRange().createContextualFragment(html);
			const rows = frag.querySelectorAll(ROW_SELECTOR).length;
			const appended = frag.children.length;
			const first = frag.firstElementChild;
			container.appendChild(frag);
			// The appended-element count is the fallback for `rows`, so a list whose
			// markup none of the selectors above anticipates still announces something
			// rather than silently nothing.
			return { rows: rows > 0 ? rows : appended, first };
		}

		function loadNext(): Promise<boolean> {
			// A control that has served its last page removed itself from the
			// document; a handle to it may still be held by the deep-link walker.
			if (!live || loading) {
				return Promise.resolve(false);
			}
			loading = true;
			setState('loading');
			const next = parseInt(attr(control, 'data-next'), 10) || 2;

			return fetchPage(control, next)
				.then((data) => {
					const added = appendRows(data.html);
					loading = false;
					announce(added.rows);
					if (data.hasMore) {
						control.setAttribute(
							'data-next',
							String(data.nextPage)
						);
						setState('idle');
						return true;
					}
					// Last page: the control goes, so whatever focus it held has to
					// be put somewhere first. Removing a focused element sends focus
					// to <body>, which on a long thread returns a keyboard reader to
					// the top of the document — past everything they just loaded.
					// It lands on the first newly appended row instead, which is
					// where the reader was going.
					rehomeFocus(added.first);
					control.parentNode?.removeChild(control);
					live = false;
					return false;
				})
				.catch(() => {
					loading = false;
					setState('error');
					return false;
				});
		}

		control.addEventListener('click', (event) => {
			const target = event.target as HTMLElement | null;
			if (target?.closest('.bltn-loadmore__btn')) {
				event.preventDefault();
				void loadNext();
			}
		});

		return { loadNext };
	}

	const controls = Array.from(
		document.querySelectorAll<HTMLElement>('.bltn-loadmore')
	).map(initControl);

	// Takes the element rather than an id: callers resolve it first, so a
	// null-check in here would be unreachable.
	function scrollToTarget(el: HTMLElement): void {
		scrollAppTo(el);
		highlight(el);
	}

	/**
	 * Flash the target, every time — including a second visit to the same post.
	 *
	 * `.bltn-post--target` runs a one-shot animation on class insertion, and adding
	 * a class an element already carries changes nothing, so simply adding it went
	 * silent the moment this became reachable more than once per page. That is not
	 * hypothetical: two replies answering the same post give two context links to
	 * the same anchor, which the fixture has. Measured before and after — one running
	 * animation on the first tap, zero on the second (raised by Gitar).
	 *
	 * So the previous target is cleared first, which also stops a spent class
	 * lingering on posts the reader has left behind. The `offsetWidth` read between
	 * the two is a synchronous reflow: without it the removal and the re-add collapse
	 * into one style recalculation, the browser sees no change, and nothing replays.
	 * It is the one place in this file that reads layout on purpose.
	 */
	function highlight(el: HTMLElement): void {
		document
			.querySelectorAll('.bltn-post--target')
			.forEach((spent) => spent.classList.remove('bltn-post--target'));
		void el.offsetWidth;
		el.classList.add('bltn-post--target');
	}

	/**
	 * Bring `#post-…` into view, loading forward first if it is not here yet.
	 *
	 * Shared by the two ways a reader can name a post: the URL they arrived on, and
	 * a reply-context link they tapped inside the thread (issue #37). Both want the
	 * same three things — scroll, highlight, and page forward when the target lives
	 * past the DOM — so neither gets its own half of them.
	 */
	function goToPost(id: string): void {
		const present = document.getElementById(id);
		if (present) {
			scrollToTarget(present);
			return;
		}

		// Nothing on this page is a post anchor, so walking forward could never
		// produce one — the rows a forum screen loads are threads and sub-forums,
		// not posts. Without this, a stale or crafted '#post-' fragment on a forum
		// URL would page the whole forum into the DOM, unasked. The reading view
		// always renders the opening post's anchor, so a genuine deep-link is never
		// turned away.
		//
		// This guard is also what makes the first control the right one to walk: it
		// confines walking to the reading view, and the reading view renders exactly
		// one control, for its replies. Remove the guard and a forum screen would
		// start paging its sub-forums looking for a post.
		if (!document.querySelector('[id^="post-"]')) {
			return;
		}
		const walker: Control | undefined = controls[0];
		if (!walker) {
			return;
		}

		// Not in the initial DOM — walk forward a page at a time until it shows
		// up or we run out of pages. Every caller has already established the target
		// is absent, so step() goes straight to loading. The walk ends on `more`
		// being false, which is also what an exhausted control answers, so there is
		// no separate liveness check to make here.
		const step = (): void => {
			void walker.loadNext().then((more) => {
				const found = document.getElementById(id);
				if (found) {
					scrollToTarget(found);
				} else if (more) {
					step();
				}
			});
		};
		step();
	}

	function resolveDeepLink(): void {
		const hash = window.location.hash;
		if (!hash || hash.indexOf('#post-') !== 0) {
			return;
		}
		goToPost(hash.slice(1));
	}

	/**
	 * In-thread links to another post — today, only the reply-context line.
	 *
	 * The native fragment jump is not good enough here, and that was measured rather
	 * than assumed. Three things go wrong when the browser handles it:
	 *
	 *  1. No highlight. `.bltn-post--target` is a class this file adds, not `:target`,
	 *     so a jump the browser performs lands the reader somewhere with nothing to
	 *     say which post they were sent to.
	 *  2. The shell came apart — fixed in CSS since, by #66. The root document was
	 *     scrollable on any long screen, so a native jump scrolled it as well as the
	 *     scroller and took the app bar off the top. Recorded because the cause was
	 *     not the one first written here: it was never the `1fr` track's automatic
	 *     minimum, but an absolutely positioned box with no positioned ancestor,
	 *     which the shell's clip could not reach. See `.bltn-app` in bulletin.css.
	 *  3. A parent that is not loaded yet does nothing at all. Rare — a parent is
	 *     normally older than its child and the view loads forward from page 1 — but
	 *     an import writing `_bbp_reply_to` directly, or a moderator repointing one
	 *     (bbp_validate_reply_to() checks neither date nor order), can put it on a
	 *     later page.
	 *
	 * preventDefault() answers 1 and 3 — the highlight fires, and goToPost() pages
	 * forward when it has to — and answered 2 on this one path until #66 answered it
	 * everywhere. Without this script the link still
	 * navigates — degraded, not broken — which is why it can live behind the config
	 * gate rather than beside the moderation toggle.
	 */
	function initPostLinks(): void {
		document.addEventListener('click', (event) => {
			const link = (event.target as HTMLElement | null)?.closest?.<
				HTMLAnchorElement
			>('a[href^="#post-"]');
			// The href is read back rather than asserted from the selector, so an
			// empty one falls through to the guard in goToPost() instead of a walk.
			const href = link?.getAttribute('href') ?? '';
			if (!link || href.length < 2) {
				return;
			}
			event.preventDefault();
			goToPost(href.slice(1));
		});
	}

	initPostLinks();
	resolveDeepLink();
}

/**
 * Give the anonymous author fields the keyboards and autofill they should have had.
 *
 * bbPress hardcodes all three as `type="text"` in `form-anonymous.php`, and puts
 * `autocomplete="off"` on the name — so a visitor posting without an account gets a
 * QWERTY keyboard for an email address and no autofill for their own name, which is
 * the single biggest friction point in mobile form entry.
 *
 * ⚠ **Done here rather than by overriding the template, and that is the constraint
 * rather than the convenience.** This plugin registers no `bbp_register_template_stack()`
 * and never has; owning a copy of that file would mean owning every future change
 * bbPress makes to the anonymous write path — nonces, hidden fields, capability
 * checks — to gain three attributes. Applied by script, the worst case is exactly
 * bbPress's own behaviour, which already works.
 *
 * ⚠ **The website field keeps `type="text"`.** `type="url"` would make the browser
 * reject a bare `example.com`, which bbPress itself accepts and stores — so promoting
 * it would be us rejecting input upstream considers valid. The keyboard hint and the
 * autofill token are safe because neither validates anything.
 */
function improveAnonymousFields(form: HTMLElement): void {
	const set = (
		id: string,
		attrs: Record<string, string>,
	): void => {
		const input = form.querySelector<HTMLInputElement>(`#${id}`);
		if (!input) {
			return;
		}
		for (const [name, value] of Object.entries(attrs)) {
			input.setAttribute(name, value);
		}
	};

	set('bbp_anonymous_author', {
		autocomplete: 'name',
		autocapitalize: 'words',
	});
	set('bbp_anonymous_email', {
		type: 'email',
		inputmode: 'email',
		autocomplete: 'email',
		autocapitalize: 'none',
		spellcheck: 'false',
	});
	set('bbp_anonymous_website', {
		inputmode: 'url',
		autocomplete: 'url',
		autocapitalize: 'none',
		spellcheck: 'false',
	});
}

/**
 * The compose slot's resting state (P4).
 *
 * ⚠ **The server renders the form OPEN and this collapses it.** Every other
 * arrangement fails in the wrong direction: a server-collapsed form needs script to
 * become reachable, so a bundle that 404s or throws leaves a reader with a button
 * that opens nothing. Collapsing here means the worst case is bbPress's own
 * behaviour — the whole form, inline, working. Same contract as the moderation
 * toggle above, and the same scar behind it (#62).
 *
 * `data-bltn-compose` carries the state the SERVER decided. A deep link
 * (`?bbp_reply_to={id}#new-post`) names a post the reader has already chosen to
 * answer, so the composer stays open and takes focus; asking them to press "Write a
 * reply" after they pressed "Reply To" is asking the same question twice. The server
 * knows that from `bbp_get_form_reply_to()`, so this never parses the query string.
 *
 * Deliberately outside initReading(), like the moderation toggle: the slot needs
 * neither the AJAX endpoint nor the localised strings, so it must not inherit that
 * function's early return on a missing config.
 */
function initComposeSlot(): void {
	const slot = document.querySelector<HTMLElement>('[data-bltn-compose]');
	// ⚠ Looked up on the DOCUMENT, not inside the slot. On the reading view the
	// trigger sits in the slot; on the forum screen it is in the fixed bar below
	// <main>, and there is only ever one composer on a screen. Scoping to the slot
	// worked for the first screen and would have silently done nothing on the second.
	const trigger = document.querySelector<HTMLElement>('[data-bltn-compose-open]');
	const form = slot?.querySelector<HTMLElement>('.bltn-compose__form');
	if (!slot || !trigger || !form) {
		return;
	}
	// The bar is the collapsed representation of the composer, so it goes away while
	// the composer is open. Null on the reading view, where the trigger is inline and
	// the CSS folds it with the rest of the slot.
	const bar = trigger.closest<HTMLElement>('.bltn-composebar');

	const field = form.querySelector<HTMLElement>(ENTRY_FIELD);

	improveAnonymousFields(form);

	/**
	 * Open the composer, bring it into view, and put the caret in it.
	 *
	 * ⚠ **The scroll is the whole point of this function, not a flourish.** Without
	 * it, tapping "Start a thread" measured `visiblePx: 0` — a 660px form opening
	 * 1,956px below the top of the scroll region, on a forum screen whose control is
	 * *fixed to the bottom of the viewport* and therefore nowhere near it. The bar
	 * vanished, nothing arrived, and the button read as broken. The reading view was
	 * milder and wrong the same way: at the foot of a long thread, 102px of a 533px
	 * form — the legend, and neither the field nor Submit. Both measured on the
	 * fixture at 390×844 before the fix.
	 *
	 * `preventScroll` on the focus, because the scroll above it is already the
	 * considered one: a browser scrolling to a focused field aims to make the *caret*
	 * visible and stops as soon as it is, which on a 660px form is its last line. The
	 * two together would have the screen arrive twice, in different places.
	 *
	 * Order matters and is measured, not assumed: the class comes off and the bar goes
	 * away first, because both change the height of the scroll region, and geometry
	 * read before them describes a screen that no longer exists.
	 */
	const open = (): void => {
		slot.classList.remove('is-collapsed');
		trigger.setAttribute('aria-expanded', 'true');
		if (bar) {
			bar.hidden = true;
		}
		field?.focus({ preventScroll: true });
		scrollAppTo(form);
	};

	const collapse = (): void => {
		slot.classList.add('is-collapsed');
		trigger.setAttribute('aria-expanded', 'false');
		if (bar) {
			bar.hidden = false;
		}
	};

	trigger.addEventListener('click', (event) => {
		// The forum screen's trigger is an anchor to #new-post, which is how it works
		// without this script. Cancelled only where there is a default to cancel —
		// the jump would land on a form this script has just collapsed, so the browser
		// would arrive at a fold instead of a composer. open() then does the travelling
		// itself, to a form it has already expanded.
		if (trigger.tagName === 'A') {
			event.preventDefault();
		}
		open();
	});

	if (slot.dataset.bltnCompose === 'open') {
		// A deep link, or a submission the server sent back. Nothing to collapse, but
		// the caret still belongs in the field: the reader arrived here having already
		// said which post they are answering, or with a correction to make.
		field?.focus({ preventScroll: true });
		// The bar goes away for the reason it goes away on a tap — it IS the collapsed
		// representation of a composer that is not collapsed. Left up it is worse here
		// than anywhere: this branch adds no Cancel, so a fixed teal "Start a thread"
		// would be the largest control on a screen whose actual next action is the
		// Submit it sits below. Unreachable until now only because nobody was ever
		// scrolled far enough to see the two disagree. Before the scroll, like in
		// open(), because it is the scroll region's height that changes.
		if (bar) {
			bar.hidden = true;
		}
		// ⚠ **And a rejected submission has to be travelled to, exactly like a tap.**
		// The server already refuses to collapse a form carrying bbPress's validation
		// errors — but bbPress's forms post to the current URL with no fragment, so
		// the reader lands at the TOP of the forum list or the thread with the error
		// and their own text at the foot, out of sight. That is the same "nothing
		// happened" this phase set out to fix, surviving one layer further down.
		// Instant: the reader did not ask to travel, and a deep link's own `#new-post`
		// is already aiming here, so an animation would either race it or replay it.
		scrollAppTo(form, true);
		return;
	}

	/*
	 * The way back out, added here rather than rendered by the server because there
	 * is nothing to cancel back TO without this script — an uncollapsed form has no
	 * resting state to return to, so a server-rendered Cancel would be a control that
	 * does nothing on the one path that matters.
	 *
	 * Secondary fill: closing the composer is not the thing the screen exists to do,
	 * and it sits beside a submit that is.
	 */
	const cancel = document.createElement('button');
	cancel.type = 'button';
	cancel.className = 'bltn-compose__cancel';
	cancel.textContent = trigger.dataset.bltnCancel ?? 'Cancel';
	cancel.addEventListener('click', () => {
		collapse();
		trigger.focus();
	});
	// Both of bbPress's forms use .bbp-submit-wrapper today (form-reply.php:163,
	// form-topic.php:202), so this resolves on the first try on either screen. The
	// fallback is for a template stack that renames it: a misplaced Cancel is
	// recoverable, a missing one strands a reader inside an open composer.
	(form.querySelector('.bbp-submit-wrapper') ?? form).appendChild(cancel);

	collapse();
}

/**
 * Take the held-reply acknowledgement out of the address bar once it has been read.
 *
 * The flag is how the server knows to print "Your reply is awaiting review" on the
 * screen bbPress redirects to (View\HeldNotice). Left in place it would re-announce
 * on every reload and travel with a shared link, so it is stripped the moment the
 * page it belongs to has rendered.
 *
 * `replaceState`, not `pushState`: this is not a place in the reader's history, and
 * a Back that returned to the same screen wearing the same banner would be worse
 * than not cleaning up at all. The message itself is untouched — removing the
 * sentence the reader is mid-way through reading is the one thing this must not do.
 */
function stripHeldFlag(): void {
	const url = new URL(window.location.href);
	if (!url.searchParams.has('bltn_held')) {
		return;
	}
	url.searchParams.delete('bltn_held');
	window.history.replaceState(window.history.state, '', url.toString());
}

// Moderation first, and outside the config gate: it enhances markup that already works
// without it, so it must not be lost to a problem in the load-more wiring.
initModerationToggle();
initComposeSlot();
stripHeldFlag();

// This bundle is only ever enqueued in a browser, and initReading() itself
// no-ops without a BLTN config, so it is the single gate on whether there is
// anything to wire up.
initReading();
