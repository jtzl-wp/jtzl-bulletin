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

	const labelOff = toggle.textContent ?? '';
	const labelOn = toggle.dataset.bltnLabelOn ?? labelOff;

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

		function appendRows(html: string): void {
			if (!html || !container) {
				return;
			}
			const frag = document.createRange().createContextualFragment(html);
			container.appendChild(frag);
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
					appendRows(data.html);
					loading = false;
					if (data.hasMore) {
						control.setAttribute(
							'data-next',
							String(data.nextPage)
						);
						setState('idle');
						return true;
					}
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
		const reduce = window.matchMedia(
			'(prefers-reduced-motion: reduce)'
		).matches;
		el.scrollIntoView({
			behavior: reduce ? 'auto' : 'smooth',
			block: 'start',
		});
		el.classList.add('bltn-post--target');
	}

	function resolveDeepLink(): void {
		const hash = window.location.hash;
		if (!hash || hash.indexOf('#post-') !== 0) {
			return;
		}
		const id = hash.slice(1);

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
		// up or we run out of pages. Both entry points have already established
		// the target is absent, so step() goes straight to loading. The walk ends on
		// `more` being false, which is also what an exhausted control answers, so
		// there is no separate liveness check to make here.
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

	resolveDeepLink();
}

// Moderation first, and outside the config gate: it enhances markup that already works
// without it, so it must not be lost to a problem in the load-more wiring.
initModerationToggle();

// This bundle is only ever enqueued in a browser, and initReading() itself
// no-ops without a BLTN config, so it is the single gate on whether there is
// anything to wire up.
initReading();
