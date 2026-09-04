/** DOM-free request and response helpers. */

export interface LoadMoreData {
	html: string;
	page: number;
	nextPage: number;
	hasMore: boolean;
}

export interface BltnI18n {
	loading?: string;
	error?: string;
	loadedOne?: string;
	loadedMany?: string;
}

export interface BltnConfig {
	ajaxUrl: string;
	i18n?: BltnI18n;
}

/**
 * Build a load-more request body. The control supplies the subject parameter;
 * subject-less lists omit it because their URL identifies the subject.
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

/** Reject malformed responses before their HTML reaches the document. */
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
