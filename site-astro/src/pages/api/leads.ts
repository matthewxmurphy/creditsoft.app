import type { APIRoute } from 'astro';
import {
	emailDomainHasMailServer,
	requestIp,
	verifyTurnstileToken,
} from '../../lib/lead-guard';
import { json, badRequest, notAllowed } from '../../lib/site-data/http';
import { captureLead, loadLeads } from '../../lib/site-data/site-data';

function parseMaybeNumber(value: unknown): number | null {
	if (value === null || value === undefined || value === '') {
		return null;
	}

	const numberValue = typeof value === 'string' ? Number(value) : Number(value);
	return Number.isFinite(numberValue) ? numberValue : null;
}

function parseNotes(value: unknown): Record<string, unknown> | undefined {
	if (!value) {
		return undefined;
	}

	if (typeof value === 'object' && !Array.isArray(value)) {
		return value as Record<string, unknown>;
	}

	if (typeof value === 'string') {
		return { value };
	}

	return undefined;
}

export const GET: APIRoute = async ({ cookies }) => {
	const result = await loadLeads(cookies);

	return json({
		ok: true,
		source: result.source,
		data: result.data,
	});
};

export const POST: APIRoute = async ({ request, cookies }) => {
	const contentType = request.headers.get('content-type') ?? '';
	const input = contentType.includes('application/json')
		? await request.json().catch(() => null)
		: Object.fromEntries(await request.formData().catch(() => new FormData()));

	if (!input || typeof input !== 'object') {
		return badRequest('A JSON or form payload is required.');
	}

	const name = typeof input.name === 'string' ? input.name.trim() : '';
	const email = typeof input.email === 'string' ? input.email.trim() : '';
	const turnstileToken =
		typeof input.turnstileToken === 'string'
			? input.turnstileToken.trim()
			: typeof input.turnstile_token === 'string'
				? input.turnstile_token.trim()
				: typeof input['cf-turnstile-response'] === 'string'
					? input['cf-turnstile-response'].trim()
					: '';

	if (!name || !email) {
		return badRequest('Name and email are required.');
	}

	if (!(await emailDomainHasMailServer(email))) {
		return badRequest('That email domain does not publish mail server DNS yet.');
	}

	const turnstile = await verifyTurnstileToken(turnstileToken, requestIp(request));

	if (!turnstile.ok) {
		return badRequest('The browser check did not pass. Refresh and try again.');
	}

	const result = await captureLead(
		{
			name,
			email,
			phone: typeof input.phone === 'string' ? input.phone.trim() : null,
			company: typeof input.company === 'string' ? input.company.trim() : null,
			source: typeof input.source === 'string' ? input.source.trim() : null,
			status: typeof input.status === 'string' ? input.status.trim() : null,
			planInterest: typeof input.planInterest === 'string' ? input.planInterest.trim() : null,
			currentSoftware: typeof input.currentSoftware === 'string' ? input.currentSoftware.trim() : null,
			clientCount: parseMaybeNumber(input.clientCount),
			outsourcing: typeof input.outsourcing === 'string' ? input.outsourcing.trim() : null,
			merchantProvider: typeof input.merchantProvider === 'string' ? input.merchantProvider.trim() : null,
			paymentMethods: typeof input.paymentMethods === 'string' ? input.paymentMethods.trim() : null,
			websiteStatus: typeof input.websiteStatus === 'string' ? input.websiteStatus.trim() : null,
			roiVisibility: typeof input.roiVisibility === 'string' ? input.roiVisibility.trim() : null,
			notes: parseNotes(input.notes),
		},
		cookies,
	);

	return json({
		ok: true,
		source: result.source,
		data: result.data,
	});
};

export const ALL: APIRoute = async () => notAllowed(['GET', 'POST']);
