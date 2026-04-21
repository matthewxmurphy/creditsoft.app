export function json(data: unknown, init?: ResponseInit): Response {
	return new Response(JSON.stringify(data, null, 2), {
		...init,
		headers: {
			'content-type': 'application/json; charset=utf-8',
			...(init?.headers ?? {}),
		},
	});
}

export function notAllowed(allowed: string[]): Response {
	return json(
		{
			ok: false,
			error: 'Method not allowed',
			allowed,
		},
		{
			status: 405,
			headers: { Allow: allowed.join(', ') },
		},
	);
}

export function badRequest(error: string, details?: Record<string, unknown>): Response {
	return json(
		{
			ok: false,
			error,
			...(details ? { details } : {}),
		},
		{
			status: 400,
		},
	);
}
