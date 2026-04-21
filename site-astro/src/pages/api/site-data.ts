import type { APIRoute } from 'astro';
import { json } from '../../lib/site-data/http';
import {
	loadInstallAds,
	loadLeads,
	loadPayments,
	loadPlans,
	loadReviews,
	loadUpdateFeed,
} from '../../lib/site-data/site-data';

export const GET: APIRoute = async ({ cookies }) => {
	const [plans, reviews, payments, leads, updateFeed, installAds] = await Promise.all([
		loadPlans(cookies),
		loadReviews(cookies),
		loadPayments(cookies),
		loadLeads(cookies),
		loadUpdateFeed(cookies),
		loadInstallAds(cookies),
	]);

	return json({
		ok: true,
		sources: {
			plans: plans.source,
			reviews: reviews.source,
			payments: payments.source,
			leads: leads.source,
			updateFeed: updateFeed.source,
			installAds: installAds.source,
		},
		data: {
			plans: plans.data,
			reviews: reviews.data,
			payments: payments.data,
			leads: leads.data,
			updateFeed: updateFeed.data,
			installAds: installAds.data,
		},
	});
};
