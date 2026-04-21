import type { APIRoute } from 'astro';
import { json } from '../../lib/site-data/http';
import { loadUpdateFeed } from '../../lib/site-data/site-data';

export const GET: APIRoute = async ({ cookies }) => {
	const result = await loadUpdateFeed(cookies);
	const feed = result.data;

	return json({
		product: 'CreditSoft Intranet',
		channel: 'stable',
		source: result.source,
		latest_version: feed.latestVersion,
		latest_build: feed.latestBuild,
		current_version: feed.currentVersion,
		current_build: feed.currentBuild,
		headline: 'Office update available',
		summary: feed.notes,
		notes: feed.notes ? [feed.notes] : [],
		download_url: feed.downloadUrl,
		browser_companion: {
			label: 'CreditSoft Browser Companion',
			latest_version: '0.5.6',
			download_url: feed.browserCompanionUrl,
			trial_days: 7,
			trial_label: '7-day companion trial',
			renewal_url: 'https://updates.creditsoft.app/renewal/',
		},
		browser_companion_url: feed.browserCompanionUrl,
		renewal_url: 'https://updates.creditsoft.app/renewal/',
		support_url: feed.supportUrl,
		update_required: false,
		checked_at: new Date().toISOString(),
	});
};
