/**
 * Bulletin — reading-view behaviour.
 *
 * Two jobs: the inline "load more replies" control, and resolving a deep-link to
 * a reply that lives past the first page (the initial DOM only holds page 1, so
 * we load forward until the target exists, then scroll to it).
 *
 * Plain navigation (tapping a forum or thread, Prev/Next) needs no JavaScript —
 * those are ordinary links.
 */
(function () {
	'use strict';

	if (typeof window.BLTN === 'undefined') {
		return;
	}

	var i18n = BLTN.i18n || {};
	var container = document.getElementById('bltn-replies');
	var loadmore = document.querySelector('.bltn-loadmore');
	var loading = false;

	/**
	 * Ask the server for one page of replies.
	 *
	 * @param {number} page
	 * @returns {Promise<Object>} resolves with { html, page, nextPage, hasMore }
	 */
	function fetchPage(page) {
		var body = new URLSearchParams();
		body.set('action', BLTN.action);
		body.set('topic', loadmore.getAttribute('data-topic'));
		body.set('paged', page);

		return fetch(BLTN.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
			credentials: 'same-origin'
		}).then(function (res) {
			if (!res.ok) {
				throw new Error('HTTP ' + res.status);
			}
			return res.json();
		}).then(function (payload) {
			if (!payload || !payload.success || !payload.data) {
				throw new Error('Unexpected response');
			}
			return payload.data;
		});
	}

	function appendReplies(html) {
		if (!html) {
			return;
		}
		var frag = document.createRange().createContextualFragment(html);
		container.appendChild(frag);
	}

	function setState(state) {
		if (!loadmore) {
			return;
		}
		var btn = loadmore.querySelector('.bltn-loadmore__btn');
		loadmore.classList.toggle('is-loading', state === 'loading');
		loadmore.classList.toggle('is-error', state === 'error');
		if (state === 'loading') {
			btn.textContent = i18n.loading || 'Loading…';
			btn.disabled = true;
		} else if (state === 'error') {
			btn.textContent = i18n.error || 'Could not load more. Tap to retry.';
			btn.disabled = false;
		} else {
			btn.textContent = i18n.loadMore || 'Load more replies';
			btn.disabled = false;
		}
	}

	/**
	 * Load the next page and append it.
	 *
	 * @returns {Promise<boolean>} whether more pages remain afterward.
	 */
	function loadNext() {
		if (!loadmore || loading) {
			return Promise.resolve(false);
		}
		loading = true;
		setState('loading');
		var next = parseInt(loadmore.getAttribute('data-next'), 10) || 2;

		return fetchPage(next).then(function (data) {
			appendReplies(data.html);
			loading = false;
			if (data.hasMore) {
				loadmore.setAttribute('data-next', data.nextPage);
				setState('idle');
				return true;
			}
			loadmore.parentNode.removeChild(loadmore);
			loadmore = null;
			return false;
		}).catch(function () {
			loading = false;
			setState('error');
			return false;
		});
	}

	if (loadmore) {
		loadmore.addEventListener('click', function (e) {
			if (e.target.closest('.bltn-loadmore__btn')) {
				e.preventDefault();
				loadNext();
			}
		});
	}

	function scrollToTarget(id) {
		var el = document.getElementById(id);
		if (!el) {
			return;
		}
		var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
		el.classList.add('bltn-post--target');
	}

	/**
	 * Resolve a #post-{id} deep-link that may target a reply on a later page.
	 */
	function resolveDeepLink() {
		var hash = window.location.hash;
		if (!hash || hash.indexOf('#post-') !== 0) {
			return;
		}
		var id = hash.slice(1);

		if (document.getElementById(id)) {
			scrollToTarget(id);
			return;
		}

		// Not in the initial DOM — walk forward a page at a time until it shows
		// up or we run out of pages.
		(function step() {
			if (document.getElementById(id)) {
				scrollToTarget(id);
				return;
			}
			if (!loadmore) {
				return;
			}
			loadNext().then(function (more) {
				if (document.getElementById(id)) {
					scrollToTarget(id);
				} else if (more) {
					step();
				}
			});
		})();
	}

	resolveDeepLink();
})();
