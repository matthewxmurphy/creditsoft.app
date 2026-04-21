import type { APIRoute } from 'astro';
import { json } from '../../lib/site-data/http';
import { loadReviews } from '../../lib/site-data/site-data';

export const GET: APIRoute = async ({ cookies }) => {
	const result = await loadReviews(cookies);

	return json({
		ok: true,
		source: result.source,
		data: result.data,
	});
};
