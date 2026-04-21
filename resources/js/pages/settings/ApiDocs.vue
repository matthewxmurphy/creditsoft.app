<script setup lang="ts">
import { faArrowUpRightFromSquare, faCloudArrowUp, faKey, faLink } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { Head, useForm } from '@inertiajs/vue3';
import ConnectivityBrandMark from '@/components/creditsoft/ConnectivityBrandMark.vue';
import Heading from '@/components/Heading.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'API docs',
                href: '/settings/api',
            },
        ],
    },
});

const props = defineProps<{
    apiAccess: {
        embedded_url: string;
        spec_url: string;
        local_base_url: string;
        tailnet_base_url?: string | null;
        public_base_url?: string | null;
        preferred_base_url: string;
        public_api_base_url?: string | null;
        normalized_public_api_base_url?: string | null;
        configured_public_api_status: {
            state: 'none' | 'verified' | 'unreachable';
            normalized_base_url?: string | null;
            callback_url?: string | null;
            http_status?: number | null;
            message: string;
        };
        meta_callback_url?: string | null;
        meta_callback_source: 'api_domain' | 'ngrok' | 'local';
        masked_token?: string | null;
            website_bridge: {
                recommended_mode: 'tailscale' | 'ngrok' | 'local';
                recommended_target_url: string;
                tailscale_running: boolean;
                tailscale_dns_name?: string | null;
                ngrok_base_url?: string | null;
                updates_feed_url: string;
                dropin_path: string;
                wordpress_plugin_path: string;
                wordpress_plugin_zip_url: string;
            };
        endpoints: Array<{
            method: string;
            path: string;
            summary: string;
        }>;
    };
}>();

const form = useForm({
    public_api_base_url: props.apiAccess.public_api_base_url ?? '',
});

const savePublicApiBase = () => {
    form.put('/settings/api', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="API Docs" />

    <h1 class="sr-only">API docs</h1>

    <div class="space-y-6">
        <div class="space-y-3">
            <ConnectivityBrandMark brand="openapi" large />
            <Heading
                variant="small"
                title="Partner API"
                description="Token-protected docs, endpoints, and connection details for lead intake and client-status reads."
            />
        </div>

        <section class="rounded-[28px] border border-amber-200 bg-[linear-gradient(135deg,_rgba(251,191,36,0.18),_rgba(255,255,255,0.96)_58%,_rgba(255,255,255,1))] px-6 py-6">
            <div class="max-w-4xl">
                <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-amber-800">Next step for Meta</p>
                <h2 class="mt-3 text-xl font-semibold text-stone-950">Do not paste localhost. Give Meta one public HTTPS callback.</h2>
                <p class="mt-3 text-sm leading-6 text-amber-950">
                    Meta cannot call <span class="font-medium text-stone-950">127.0.0.1</span>, a Mac-only LAN address, or a private Tailscale URL. The clean path is a real website domain with a tiny
                    <span class="font-medium text-stone-950">/api/v1</span> bridge installed. Meta calls the website; the bridge forwards back to this office through ngrok, Tailscale, or a future reverse proxy.
                </p>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-3">
                <div class="rounded-[22px] border border-white/80 bg-white/90 px-4 py-4">
                    <p class="text-sm font-semibold text-stone-950">1. Get a temporary public lane</p>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Use <span class="font-medium text-stone-900">/settings/connectivity</span> to start ngrok in API-only mode. This gives CreditSoft an HTTPS callback for testing, but it can rotate unless ngrok has a reserved domain.
                    </p>
                </div>

                <div class="rounded-[22px] border border-white/80 bg-white/90 px-4 py-4">
                    <p class="text-sm font-semibold text-stone-950">2. Install the stable website bridge</p>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Put the PHP drop-in or WordPress plugin on the real customer domain so
                        <span class="font-medium text-stone-900">https://domain.com/api/v1/meta/callback</span>
                        exists and forwards into the office API target.
                    </p>
                </div>

                <div class="rounded-[22px] border border-white/80 bg-white/90 px-4 py-4">
                    <p class="text-sm font-semibold text-stone-950">3. Save and verify the domain here</p>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Once this page says <span class="font-medium text-stone-900">Verified callback host</span>, paste the active callback into Meta. If it still says local-only, stop and fix the bridge first.
                    </p>
                </div>
            </div>

            <div class="mt-5 rounded-[22px] border px-4 py-4 text-sm leading-6" :class="props.apiAccess.meta_callback_source === 'local' ? 'border-rose-200 bg-rose-50 text-rose-950' : 'border-emerald-200 bg-emerald-50 text-emerald-950'">
                <p class="text-[11px] font-medium uppercase tracking-[0.22em]" :class="props.apiAccess.meta_callback_source === 'local' ? 'text-rose-800' : 'text-emerald-800'">
                    {{ props.apiAccess.meta_callback_source === 'local' ? 'Not ready for Meta' : 'Callback you can register' }}
                </p>
                <p class="mt-2 break-all font-medium text-stone-950">
                    {{ props.apiAccess.meta_callback_source === 'local' ? 'No public callback is active yet. Do not use the localhost callback in Meta.' : props.apiAccess.meta_callback_url }}
                </p>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.05fr_0.95fr]">
            <form class="rounded-[24px] border border-stone-200 bg-white px-5 py-5" @submit.prevent="savePublicApiBase">
                <div class="flex items-center gap-2 text-stone-900">
                    <FontAwesomeIcon :icon="faLink" class="text-sm" />
                    <p class="text-sm font-semibold">Website bridge domain</p>
                </div>
                <p class="mt-3 text-sm leading-6 text-stone-600">
                    Paste the customer website domain that really forwards <span class="font-medium text-stone-900">/api/v1/*</span> into this CreditSoft install. You can enter the root domain or the full <span class="font-medium text-stone-900">/api/v1</span> base. CreditSoft will keep using ngrok or localhost until that bridge actually responds.
                </p>
                <label class="mt-4 block space-y-2">
                    <span class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">Customer website domain</span>
                    <input
                        v-model="form.public_api_base_url"
                        type="url"
                        class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                        placeholder="https://customer-domain.com"
                    />
                </label>
                <p v-if="form.errors.public_api_base_url" class="mt-2 text-xs leading-5 text-rose-700">
                    {{ form.errors.public_api_base_url }}
                </p>
                <p class="mt-2 text-xs leading-5 text-stone-500">
                    CreditSoft will normalize root domains to <span class="font-medium text-stone-900">/api/v1</span>. Example callback:
                    <span class="font-medium text-stone-900">https://customer-domain.com/api/v1/meta/callback</span>
                </p>
                <div
                    v-if="props.apiAccess.configured_public_api_status.state !== 'none'"
                    class="mt-4 rounded-[20px] border px-4 py-4 text-sm leading-6"
                    :class="props.apiAccess.configured_public_api_status.state === 'verified' ? 'border-emerald-200 bg-emerald-50 text-emerald-950' : 'border-amber-200 bg-amber-50 text-amber-950'"
                >
                    <p class="text-[11px] font-medium uppercase tracking-[0.22em]" :class="props.apiAccess.configured_public_api_status.state === 'verified' ? 'text-emerald-800' : 'text-amber-800'">
                        {{ props.apiAccess.configured_public_api_status.state === 'verified' ? 'Verified callback host' : 'Saved, not live yet' }}
                    </p>
                    <p class="mt-2">
                        {{ props.apiAccess.configured_public_api_status.message }}
                    </p>
                    <p v-if="props.apiAccess.normalized_public_api_base_url" class="mt-2 text-xs leading-5">
                        Normalized API base:
                        <span class="font-medium text-stone-900">{{ props.apiAccess.normalized_public_api_base_url }}</span>
                    </p>
                    <p v-if="props.apiAccess.configured_public_api_status.callback_url" class="mt-1 text-xs leading-5">
                        Callback candidate:
                        <span class="font-medium text-stone-900">{{ props.apiAccess.configured_public_api_status.callback_url }}</span>
                    </p>
                </div>
                <button type="submit" class="mt-4 inline-flex items-center gap-2 rounded-2xl bg-stone-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-stone-800" :disabled="form.processing">
                    <FontAwesomeIcon :icon="faCloudArrowUp" />
                    Save bridge domain
                </button>
            </form>

            <div class="rounded-[24px] border border-stone-200 bg-white px-5 py-5">
                <div class="flex items-center gap-2 text-stone-900">
                    <FontAwesomeIcon :icon="faLink" class="text-sm" />
                    <p class="text-sm font-semibold">Meta callback</p>
                </div>
                <p class="mt-3 text-sm leading-6 text-stone-600">
                    {{
                        props.apiAccess.meta_callback_source === 'api_domain'
                            ? 'Meta is currently using the verified website bridge domain.'
                            : (props.apiAccess.meta_callback_source === 'ngrok'
                                ? 'Meta is currently using the live ngrok callback lane.'
                                : 'Meta is currently local-only until a public lane is available.')
                    }}
                </p>
                <div class="mt-4 rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-4">
                    <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">Active callback</p>
                    <p class="mt-2 break-all text-sm leading-6 text-stone-900">
                        {{ props.apiAccess.meta_callback_url ?? 'Not public yet. CreditSoft will use localhost until a stable API base or ngrok is available.' }}
                    </p>
                </div>
                <div
                    v-if="props.apiAccess.configured_public_api_status.state === 'unreachable' && props.apiAccess.configured_public_api_status.callback_url"
                    class="mt-4 rounded-[20px] border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-950"
                >
                    <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-amber-800">Saved callback host</p>
                    <p class="mt-2 break-all font-medium text-stone-900">
                        {{ props.apiAccess.configured_public_api_status.callback_url }}
                    </p>
                    <p class="mt-2">
                        This host is still not responding the way Meta needs, so CreditSoft is staying on the active fallback lane above.
                    </p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr_0.8fr]">
            <div class="rounded-[24px] border border-stone-200 bg-white px-5 py-5">
                <div class="flex items-center gap-2 text-stone-900">
                    <FontAwesomeIcon :icon="faKey" class="text-sm" />
                    <p class="text-sm font-semibold">Auth</p>
                </div>
                <p class="mt-3 text-sm leading-6 text-stone-600">
                    Send the partner token as <span class="font-medium text-stone-900">Authorization: Bearer ...</span> or
                    <span class="font-medium text-stone-900">X-CreditSoft-Token</span>.
                </p>
                <div class="mt-4 rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-4">
                    <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">Token on file</p>
                    <p class="mt-2 text-sm font-medium text-stone-900">
                        {{ apiAccess.masked_token ?? 'No token generated yet' }}
                    </p>
                </div>
            </div>

            <div class="rounded-[24px] border border-stone-200 bg-white px-5 py-5">
                <div class="flex items-center gap-2 text-stone-900">
                    <FontAwesomeIcon :icon="faLink" class="text-sm" />
                    <p class="text-sm font-semibold">Primary base</p>
                </div>
                <p class="mt-4 break-all text-sm leading-6 text-stone-900">{{ apiAccess.preferred_base_url }}</p>
            </div>

            <div class="rounded-[24px] border border-stone-200 bg-white px-5 py-5">
                <div class="flex items-center gap-2 text-stone-900">
                    <FontAwesomeIcon :icon="faLink" class="text-sm" />
                    <p class="text-sm font-semibold">Tailnet / public</p>
                </div>
                <p class="mt-4 break-all text-sm leading-6 text-stone-900">
                    {{ apiAccess.public_base_url ?? apiAccess.tailnet_base_url ?? 'Use ngrok or Tailscale from Connectivity to expose a non-local lane.' }}
                </p>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div class="rounded-[24px] border border-stone-200 bg-white px-5 py-5">
                <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">Tailnet base</p>
                <p class="mt-3 break-all text-sm leading-6 text-stone-900">
                    {{ apiAccess.tailnet_base_url ?? 'Not available until Tailscale is running on the host.' }}
                </p>
            </div>

            <div class="rounded-[24px] border border-stone-200 bg-white px-5 py-5">
                <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">Local dev base</p>
                <p class="mt-3 break-all text-sm leading-6 text-stone-900">{{ apiAccess.local_base_url }}</p>
            </div>
        </section>

        <section class="rounded-[28px] border border-stone-300/70 bg-white/95 px-6 py-6">
            <div class="max-w-4xl">
                <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Website bridge installer</p>
                <h2 class="mt-3 text-xl font-semibold text-stone-950">Ship a tiny website bridge instead of asking the website to be the office.</h2>
                <p class="mt-3 text-sm leading-6 text-stone-600">
                    The right product is a small PHP drop-in or WordPress plugin that lands <span class="font-medium text-stone-900">/api/v1/*</span> on the customer website, forwards requests back to the office lane, and stays updateable from the CreditSoft feed.
                </p>
            </div>

            <div class="mt-5 grid gap-4 xl:grid-cols-3">
                <div class="rounded-[22px] border border-stone-200 bg-stone-50 px-4 py-4">
                    <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">What lands on the website</p>
                    <p class="mt-3 text-sm leading-6 text-stone-700">
                        Either a plain PHP drop-in at <span class="font-medium text-stone-900">{{ apiAccess.website_bridge.dropin_path }}</span> or a WordPress plugin at
                        <span class="font-medium text-stone-900">{{ apiAccess.website_bridge.wordpress_plugin_path }}</span>.
                    </p>
                    <a
                        :href="apiAccess.website_bridge.wordpress_plugin_zip_url"
                        class="mt-4 inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-3.5 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-500 hover:text-stone-950"
                    >
                        Download plugin
                        <FontAwesomeIcon :icon="faArrowUpRightFromSquare" class="text-[10px]" />
                    </a>
                </div>

                <div class="rounded-[22px] border border-stone-200 bg-stone-50 px-4 py-4">
                    <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">Recommended office target</p>
                    <p class="mt-3 text-sm leading-6 text-stone-700">
                        {{
                            apiAccess.website_bridge.recommended_mode === 'tailscale'
                                ? 'Use the private tailnet lane when the website server can already see the office over Tailscale.'
                                : (apiAccess.website_bridge.recommended_mode === 'ngrok'
                                    ? 'Use the live ngrok lane until the office has a real reverse proxy or tailnet bridge.'
                                    : 'The office is still local-only right now, so the bridge installer should not go out yet.')
                        }}
                    </p>
                    <p class="mt-3 break-all text-sm font-medium text-stone-900">{{ apiAccess.website_bridge.recommended_target_url }}</p>
                </div>

                <div class="rounded-[22px] border border-stone-200 bg-stone-50 px-4 py-4">
                    <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">Update channel</p>
                    <p class="mt-3 text-sm leading-6 text-stone-700">
                        The website bridge should pull signed updates from the CreditSoft updates feed so portal/API and callback fixes can ship without hand-editing every website again.
                    </p>
                    <p class="mt-3 break-all text-sm font-medium text-stone-900">{{ apiAccess.website_bridge.updates_feed_url }}</p>
                </div>
            </div>

            <div class="mt-5 rounded-[24px] border border-amber-200 bg-amber-50 px-5 py-5 text-sm leading-6 text-amber-950">
                <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-amber-800">Secret boundary</p>
                <p class="mt-2">
                    Do not drop raw ngrok or Tailscale admin keys into a public website directory. The website bridge should keep a bridge token and the office target URL. Tailscale enrollment keys and ngrok host credentials stay inside the office install or host-level installer.
                </p>
            </div>
        </section>

        <section class="rounded-[28px] border border-stone-300/70 bg-white/95">
            <div class="flex flex-col gap-4 border-b border-stone-200/80 px-6 py-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Included endpoints</p>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div
                            v-for="endpoint in apiAccess.endpoints"
                            :key="`${endpoint.method}:${endpoint.path}`"
                            class="rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <p class="text-xs font-medium uppercase tracking-[0.22em] text-stone-500">
                                <span class="text-stone-900">{{ endpoint.method }}</span>
                                {{ endpoint.path }}
                            </p>
                            <p class="mt-2 text-sm leading-6 text-stone-600">{{ endpoint.summary }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a
                        :href="apiAccess.spec_url"
                        target="_blank"
                        class="inline-flex items-center gap-2 rounded-full border border-stone-300 px-4 py-2 text-xs font-medium uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-500 hover:text-stone-950"
                    >
                        Raw OpenAPI YAML
                        <FontAwesomeIcon :icon="faArrowUpRightFromSquare" class="text-[10px]" />
                    </a>
                    <a
                        :href="apiAccess.embedded_url"
                        target="_blank"
                        class="inline-flex items-center gap-2 rounded-full bg-stone-950 px-4 py-2 text-xs font-medium uppercase tracking-[0.18em] text-white transition hover:bg-stone-800"
                    >
                        Open Swagger only
                        <FontAwesomeIcon :icon="faArrowUpRightFromSquare" class="text-[10px]" />
                    </a>
                </div>
            </div>

            <div class="px-4 py-4 md:px-6 md:py-6">
                <div class="overflow-hidden rounded-[24px] border border-stone-200 bg-stone-50">
                    <iframe
                        :src="apiAccess.embedded_url"
                        title="CreditSoft Partner API Explorer"
                        class="h-[70vh] w-full border-0 bg-white"
                    />
                </div>
            </div>
        </section>
    </div>
</template>
