/**
 * Bulletin — reading behaviour (DOM entry).
 *
 * Two jobs: the inline "load more" control, and resolving a deep-link to a reply
 * that lives past the first page (the initial DOM only holds page 1, so we load
 * forward until the target exists, then scroll to it).
 *
 * The control serves both paginated lists — more replies inside a thread, more
 * threads inside a forum — and says which it is: the endpoint, the subject, and
 * the element to append to all ride on its data attributes (see View\LoadMore).
 * So this file holds no per-screen knowledge, and its idle label is read back
 * from the button the server already rendered rather than localised twice.
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
	let loadmore = document.querySelector<HTMLElement>('.bltn-loadmore');
	let loading = false;

	// The label the server rendered, kept so the idle state can be restored
	// verbatim after loading or an error — including which list it names.
	const idleLabel =
		loadmore
			?.querySelector<HTMLButtonElement>('.bltn-loadmore__btn')
			?.textContent?.trim() ?? '';

	// A control the server rendered carries all of these; one that somehow does
	// not still yields a well-formed request the server can refuse, rather than a
	// null that would throw on the way out.
	const attr = (el: Element, name: string): string =>
		el.getAttribute(name) ?? '';

	// Where rows land. Resolved from the control so the same script serves the
	// replies container on one screen and the thread list on another.
	const container = loadmore
		? document.getElementById(attr(loadmore, 'data-target'))
		: null;

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

	function appendRows(html: string): void {
		if (!html || !container) {
			return;
		}
		const frag = document.createRange().createContextualFragment(html);
		container.appendChild(frag);
	}

	// Takes the control explicitly: every caller already holds a non-null one, so
	// re-checking here would be an unreachable guard.
	function setState(
		control: HTMLElement,
		state: 'idle' | 'loading' | 'error'
	): void {
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

	function loadNext(): Promise<boolean> {
		const control = loadmore;
		if (!control || loading) {
			return Promise.resolve(false);
		}
		loading = true;
		setState(control, 'loading');
		const next = parseInt(control.getAttribute('data-next') ?? '', 10) || 2;

		return fetchPage(control, next)
			.then((data) => {
				appendRows(data.html);
				loading = false;
				if (data.hasMore) {
					control.setAttribute('data-next', String(data.nextPage));
					setState(control, 'idle');
					return true;
				}
				control.parentNode?.removeChild(control);
				loadmore = null;
				return false;
			})
			.catch(() => {
				loading = false;
				setState(control, 'error');
				return false;
			});
	}

	if (loadmore) {
		loadmore.addEventListener('click', (event) => {
			const target = event.target as HTMLElement | null;
			if (target?.closest('.bltn-loadmore__btn')) {
				event.preventDefault();
				void loadNext();
			}
		});
	}

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
		// produce one — the rows this list loads are threads, not posts. Without
		// this, a stale or crafted '#post-' fragment on a forum URL would page the
		// whole forum into the DOM, unasked. The reading view always renders the
		// opening post's anchor, so a genuine deep-link is never turned away.
		if (!document.querySelector('[id^="post-"]')) {
			return;
		}

		// Not in the initial DOM — walk forward a page at a time until it shows
		// up or we run out of pages. Both entry points have already established
		// the target is absent, so step() goes straight to loading.
		const step = (): void => {
			if (!loadmore) {
				return;
			}
			void loadNext().then((more) => {
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
