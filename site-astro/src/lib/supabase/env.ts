export interface SiteSupabaseEnv {
	url: string;
	publishableKey: string;
	serviceRoleKey: string | null;
}

function readValue(...keys: string[]): string | null {
	for (const key of keys) {
		const value = import.meta.env?.[key] ?? process.env[key];
		if (typeof value === 'string' && value.trim() !== '') {
			return value.trim();
		}
	}

	return null;
}

function normalizeUrl(value: string): string {
	return value.replace(/\/+$/, '');
}

export function getSupabaseEnv(): SiteSupabaseEnv | null {
	const url = readValue('PUBLIC_SUPABASE_URL', 'SUPABASE_URL');
	const publishableKey = readValue(
		'PUBLIC_SUPABASE_PUBLISHABLE_KEY',
		'PUBLIC_SUPABASE_ANON_KEY',
		'SUPABASE_PUBLISHABLE_KEY',
		'SUPABASE_ANON_KEY',
	);
	const serviceRoleKey = readValue('SUPABASE_SERVICE_ROLE_KEY', 'SUPABASE_SECRET_KEY');

	if (!url || !publishableKey) {
		return null;
	}

	return {
		url: normalizeUrl(url),
		publishableKey,
		serviceRoleKey,
	};
}

export function hasSupabaseEnv(): boolean {
	return getSupabaseEnv() !== null;
}
