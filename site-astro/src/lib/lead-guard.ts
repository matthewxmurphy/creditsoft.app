import { resolveMx } from 'node:dns/promises';

export const turnstileSiteKey =
	import.meta.env.PUBLIC_TURNSTILE_SITE_KEY ??
	import.meta.env.CREDITSOFT_TURNSTILE_SITE_KEY ??
	'';

function emailDomain(email: string): string | null {
	const at = email.trim().toLowerCase().lastIndexOf('@');

	if (at <= 0 || at === email.length - 1) {
		return null;
	}

	return email.slice(at + 1).trim();
}

export async function emailDomainHasMailServer(email: string): Promise<boolean> {
	const domain = emailDomain(email);

	if (!domain) {
		return false;
	}

	try {
		const mx = await resolveMx(domain);

		return mx.length > 0;
	} catch {
		return false;
	}
}

export async function verifyTurnstileToken(
	token: string | null | undefined,
	remoteIp?: string | null,
): Promise<{ ok: boolean; skipped: boolean }> {
	const secret =
		import.meta.env.TURNSTILE_SECRET_KEY ??
		import.meta.env.CREDITSOFT_TURNSTILE_SECRET_KEY ??
		import.meta.env.CLOUDFLARE_TURNSTILE_SECRET_KEY ??
		'';

	if (!secret) {
		return { ok: true, skipped: true };
	}

	if (!token) {
		return { ok: false, skipped: false };
	}

	const body = new URLSearchParams({
		secret,
		response: token,
	});

	if (remoteIp) {
		body.set('remoteip', remoteIp);
	}

	const response = await fetch('https://challenges.cloudflare.com/turnstile/v0/siteverify', {
		method: 'POST',
		headers: {
			'content-type': 'application/x-www-form-urlencoded',
		},
		body,
	}).catch(() => null);

	if (!response?.ok) {
		return { ok: false, skipped: false };
	}

	const result = (await response.json().catch(() => null)) as { success?: boolean } | null;

	return { ok: Boolean(result?.success), skipped: false };
}

export function requestIp(request: Request): string | null {
	return (
		request.headers.get('cf-connecting-ip') ??
		request.headers.get('x-real-ip') ??
		request.headers.get('x-forwarded-for')?.split(',')[0]?.trim() ??
		null
	);
}
