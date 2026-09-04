/** Reading-view controls and deep-link resolution. */

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

/** A load-more control remains safe to call after removing itself. */
interface Control {
	loadNext(): Promise<boolean>;
}

/**
 * Scroll only the app region. Fall back for targets outside it, honor reduced
 * motion, and allow instant navigation for state restored by the server.
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

/** First writable field, excluding bbPress quicktag controls and disabled inputs. */
const ENTRY_FIELD =
	'input:not([type=hidden]):not([type=checkbox]):not([type=radio])' +
	':not([type=button]):not([type=submit]):not([type=reset]):not([disabled]),' +
	'textarea:not([disabled]), select:not([disabled])';

/**
 * Enable moderation mode only after its listener exists. Until `modready` is set,
 * CSS leaves server-rendered actions visible; this runs outside the config gate.
 */
function initModerationToggle(): void {
	const toggle = document.querySelector<HTMLButtonElement>(
		'[data-bltn-modtoggle]'
	);
	const thread = toggle?.closest<HTMLElement>('.bltn-thread');
	if (!toggle || !thread) {
		return;
	}

	/* Element text and server-rendered label attributes are present by construction. */
	/* v8 ignore start */
	const labelOff = toggle.textContent ?? '';
	const labelOn = toggle.dataset.bltnLabelOn ?? labelOff;
	/* v8 ignore stop */

	toggle.addEventListener('click', () => {
		const on = thread.classList.toggle('bltn-thread--moderating');
		toggle.setAttribute('aria-expanded', on ? 'true' : 'false');
		toggle.textContent = on ? labelOn : labelOff;
	});

	// Hide trays only after the toggle can restore them.
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

	// Missing attributes stay empty so the server can validate the request.
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

	/** Keep request, target, state, and label isolated per control. */
	function initControl(control: HTMLElement): Control {
		const container = document.getElementById(attr(control, 'data-target'));

		// Preserve the server-rendered, list-specific idle label.
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

		// Resolve before the final page removes the sibling control.
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

		/** Preserve keyboard focus when its control is removed, without scrolling. */
		function rehomeFocus(first: Element | null): void {
			if (!first || !control.contains(document.activeElement)) {
				return;
			}
			first.setAttribute('tabindex', '-1');
			(first as HTMLElement).focus({ preventScroll: true });
		}

		/** Count semantic rows even when a page arrives in one bbPress body wrapper. */
		const ROW_SELECTOR =
			'.bltn-row, .bltn-post, li.bbp-body ul.forum, li.bbp-body ul.topic, li.bbp-body div.reply';

		/** Read row count and focus target before appending empties the fragment. */
		function appendRows(html: string): { rows: number; first: Element | null } {
			if (!html || !container) {
				return { rows: 0, first: null };
			}
			const frag = document.createRange().createContextualFragment(html);
			const rows = frag.querySelectorAll(ROW_SELECTOR).length;
			const appended = frag.children.length;
			const first = frag.firstElementChild;
			container.appendChild(frag);
			// Unknown markup still gets an approximate live-region announcement.
			return { rows: rows > 0 ? rows : appended, first };
		}

		function loadNext(): Promise<boolean> {
			// The deep-link walker may retain an exhausted control.
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
					// Keep keyboard focus with the newly appended content.
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

	/** Force reflow between class removal and insertion to replay the highlight. */
	function highlight(el: HTMLElement): void {
		document
			.querySelectorAll('.bltn-post--target')
			.forEach((spent) => spent.classList.remove('bltn-post--target'));
		void el.offsetWidth;
		el.classList.add('bltn-post--target');
	}

	/** Bring `#post-…` into view, loading forward if necessary. */
	function goToPost(id: string): void {
		const present = document.getElementById(id);
		if (present) {
			scrollToTarget(present);
			return;
		}

		// Only reading views contain post anchors and a reply-pagination control.
		if (!document.querySelector('[id^="post-"]')) {
			return;
		}
		const walker: Control | undefined = controls[0];
		if (!walker) {
			return;
		}

		// Walk pages until the target appears or the control is exhausted.
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
	 * Enhance in-thread fragments with highlighting and pagination. Native fragment
	 * navigation remains the no-script fallback. Reply parents are not guaranteed to
	 * precede their children, so the target may be on a later page.
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
 * Add mobile keyboard and autofill hints without overriding bbPress templates.
 * Keep the website as text because bbPress accepts bare domains; inputmode does not
 * add browser validation, so script failure degrades to the working native form.
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
 * Collapse a server-rendered composer only after its controls are wired. Server-open
 * forms remain the no-script fallback; server-selected open states keep focus.
 */
function initComposeSlot(): void {
	const slot = document.querySelector<HTMLElement>('[data-bltn-compose]');
	// Forum-screen triggers live outside the compose slot.
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

	/** Expand before scrolling; prevent focus from performing a second scroll. */
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
		// Cancel the anchor fallback because `open()` expands and scrolls to the form.
		if (trigger.tagName === 'A') {
			event.preventDefault();
		}
		open();
	});

	if (slot.dataset.bltnCompose === 'open') {
		// Deep links and rejected submissions arrive open and ready for correction.
		field?.focus({ preventScroll: true });
		// Hide the collapsed-state bar before scrolling because it changes the
		// scrollable height.
		if (bar) {
			bar.hidden = true;
		}
		// Server-open forms need an instant scroll to avoid racing a fragment jump.
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
	// A renamed submit wrapper may misplace Cancel but must not remove the exit.
	(form.querySelector('.bbp-submit-wrapper') ?? form).appendChild(cancel);

	collapse();
}

/**
 * Remove the held-reply flag after its acknowledgement renders.
 *
 * This prevents repeat announcements and shared flags. `replaceState` preserves
 * navigation history while leaving the rendered message and fragment intact.
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
