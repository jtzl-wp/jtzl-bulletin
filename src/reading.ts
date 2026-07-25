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

// This bundle is only ever enqueued in a browser, and initReading() itself
// no-ops without a BLTN config, so it is the single gate on whether there is
// anything to wire up.
initReading();
