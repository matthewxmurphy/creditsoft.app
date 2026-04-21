import type { APIRoute } from 'astro';
import { json } from '../../lib/site-data/http';
import { loadPayments } from '../../lib/site-data/site-data';

export const GET: APIRoute = async ({ cookies }) => {
	const result = await loadPayments(cookies);

	return json({
		ok: true,
		source: result.source,
		data: result.data,
	});
};
