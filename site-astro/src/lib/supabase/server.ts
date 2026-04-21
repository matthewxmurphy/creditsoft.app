import { createServerClient } from '@supabase/ssr';
import type { SupabaseClient } from '@supabase/supabase-js';
import { getSupabaseEnv } from './env';

export type SiteCookieStore = {
	get: (name: string) => { value: string } | undefined;
	set: (name: string, value: string, options?: Record<string, unknown>) => void;
	delete: (name: string, options?: Record<string, unknown>) => void;
};

export function createSiteSupabaseServerClient(cookies: SiteCookieStore): SupabaseClient | null {
	const env = getSupabaseEnv();

	if (!env) {
		return null;
	}

	return createServerClient(env.url, env.serviceRoleKey ?? env.publishableKey, {
		cookies: {
			get(name) {
				return cookies.get(name)?.value;
			},
			set(name, value, options) {
				cookies.set(name, value, options);
			},
			remove(name, options) {
				cookies.delete(name, options);
			},
		},
	});
}
