import type { APIRoute } from 'astro';
import { badRequest, json, notAllowed } from '../../lib/site-data/http';
import { captureLicenseBillingIntelligence } from '../../lib/site-data/site-data';

function normalizeLicenseCode(value: unknown): string {
	if (typeof value !== 'string') {
		return '';
	}

	return value.trim().toUpperCase();
}

function stringValue(value: unknown, fallback = ''): string {
	return typeof value === 'string' && value.trim() !== '' ? value.trim() : fallback;
}

function numberValue(value: unknown): number | null {
	if (value === null || value === undefined || value === '') {
		return null;
	}

	const resolved = Number(value);

	return Number.isFinite(resolved) ? resolved : null;
}

function objectValue(value: unknown): Record<string, unknown> {
	return value && typeof value === 'object' && !Array.isArray(value)
		? (value as Record<string, unknown>)
		: {};
}

export const POST: APIRoute = async ({ request, cookies }) => {
	const input = await request.json().catch(() => null);

	if (!input || typeof input !== 'object' || Array.isArray(input)) {
		return badRequest('A JSON payload is required.');
	}

	const body = input as Record<string, unknown>;
	const licenseCode = normalizeLicenseCode(
		body.license_code ?? body.licenseCode ?? request.headers.get('x-creditsoft-license'),
	);
	const idempotencyKey = stringValue(
		body.idempotency_key ?? body.idempotencyKey ?? request.headers.get('x-creditsoft-idempotency-key'),
	);
	const billing = objectValue(body.billing);
	const client = objectValue(body.client);

	if (!licenseCode) {
		return badRequest('license_code is required.');
	}

	if (!/^[A-Z0-9]{3,12}(?:-[A-Z0-9]{3,12}){1,8}$/.test(licenseCode)) {
		return badRequest('license_code is not in the expected format.');
	}

	if (!idempotencyKey) {
		return badRequest('idempotency_key is required.');
	}

	const result = await captureLicenseBillingIntelligence(
		{
			licenseCode,
			idempotencyKey,
			sourceSystem: stringValue(body.source_system ?? body.sourceSystem, 'unknown'),
			captureType: stringValue(body.capture_type ?? body.captureType, 'legacy_billing'),
			customerName: stringValue(client.display_name ?? client.displayName, '') || null,
			clientCuid: stringValue(client.cuid, '') || null,
			amount: numberValue(billing.amount),
			status: stringValue(billing.status, '') || null,
			gatewayName: stringValue(billing.gateway_name ?? billing.gatewayName, '') || null,
			reference: stringValue(billing.reference, '') || null,
			paidAt: stringValue(billing.paid_at ?? billing.paidAt, '') || null,
			payload: body,
		},
		cookies,
	);

	return json({
		ok: true,
		source: result.source,
		data: {
			id: result.data.id,
			licenseCode: result.data.licenseCode,
			idempotencyKey: result.data.idempotencyKey,
			sourceSystem: result.data.sourceSystem,
			captureType: result.data.captureType,
			createdAt: result.data.createdAt,
		},
	});
};

export const ALL: APIRoute = async () => notAllowed(['POST']);
