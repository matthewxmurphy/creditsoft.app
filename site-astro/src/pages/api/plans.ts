import type { APIRoute } from 'astro';
import { json } from '../../lib/site-data/http';
import { loadPlans } from '../../lib/site-data/site-data';

export const GET: APIRoute = async ({ cookies }) => {
	const result = await loadPlans(cookies);

	return json({
		ok: true,
		source: result.source,
		data: result.data,
	});
};
