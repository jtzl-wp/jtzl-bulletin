/**
 * Pure, DOM-free helpers for the reading view.
 *
 * Kept separate from reading.ts (the DOM entry) so they can be unit-tested
 * without a browser and so the esbuild entry stays export-free.
 */

export interface LoadMoreData {
	html: string;
	page: number;
	nextPage: number;
	hasMore: boolean;
}

export interface BltnI18n {
	loading?: string;
	error?: string;
	/** Live-region announcement for a single appended row. */
	loadedOne?: string;
	/** Live-region announcement for several; carries a `%d` the script fills. */
	loadedMany?: string;
}

export interface BltnConfig {
	ajaxUrl: string;
	i18n?: BltnI18n;
}

/**
 * Build the form-encoded body for a load-more request.
 *
 * The subject's parameter name is carried by the control rather than fixed here,
 * because the same request shape serves several lists: replies within a topic,
 * threads within a forum, forums within a forum.
 *
 * A control may also have no subject, and then none is sent. The subscribed-forums
 * list on a profile belongs to a user, and which user is already settled by the URL
 * the request goes to — bbPress's AJAX URL is the current page's own. Sending an
 * empty parameter would put a subject in the body that means nothing.
 *
 * @param action bbPress AJAX action name.
 * @param param  Name of the subject parameter ('topic', 'forum'), or '' for none.
 * @param id     Subject ID (as a string, straight from the DOM attribute).
 * @param page   1-based page number.
 * @return URL-encoded request body.
 */
export function buildRequestBody(
	action: string,
	param: string,
	id: string,
	page: number
): string {
	const body = new URLSearchParams();
	body.set('action', action);
	if (param !== '') {
		body.set(param, id);
	}
	body.set('paged', String(page));
	return body.toString();
}

/**
 * Validate and normalise a load-more JSON response.
 *
 * Throws when the payload isn't a successful, well-formed response so the caller
 * can surface a retry affordance rather than appending garbage.
 *
 * @param payload Parsed JSON from the endpoint.
 * @return Normalised load-more data.
 */
export function parseLoadMoreResponse(payload: unknown): LoadMoreData {
	if (!payload || typeof payload !== 'object') {
		throw new Error('Unexpected response');
	}

	const envelope = payload as { success?: unknown; data?: unknown };
	if (
		envelope.success !== true ||
		!envelope.data ||
		typeof envelope.data !== 'object'
	) {
		throw new Error('Unexpected response');
	}

	const data = envelope.data as Record<string, unknown>;
	return {
		html: typeof data.html === 'string' ? data.html : '',
		page: typeof data.page === 'number' ? data.page : 0,
		nextPage: typeof data.nextPage === 'number' ? data.nextPage : 0,
		hasMore: data.hasMore === true,
	};
}
