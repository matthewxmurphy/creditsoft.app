<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Download, KeyRound, ScrollText, UploadCloud, WandSparkles } from 'lucide-vue-next';

const props = defineProps<{
    bundle: {
        name: string;
        version: string;
        download_name: string;
        description: string;
        download_url: string;
    };
    api_base_url: string;
    generated_token?: string | null;
    active_keys: Array<{
        id: number;
        name: string;
        masked_token: string;
        abilities: string[];
        created_at?: string | null;
        last_used_at?: string | null;
    }>;
    allowed_hosts: string[];
    capture_types: string[];
    automation_discoveries: Array<{
        id: number;
        source_system: string;
        source_product?: string | null;
        page_kind?: string | null;
        source_identifier?: string | null;
        name?: string | null;
        status?: string | null;
        category?: string | null;
        workflow_type?: string | null;
        start_condition?: string | null;
        condition_count: number;
        action_count: number;
        step_count: number;
        seen_count: number;
        last_seen_at?: string | null;
        promoted_at?: string | null;
        page_url?: string | null;
        steps: Array<{
            title?: string | null;
            timing?: string | null;
            actions: string[];
        }>;
    }>;
    captures: Array<{
        id: number;
        source_system: string;
        capture_type: string;
        page_title?: string | null;
        page_url?: string | null;
        operator_note?: string | null;
        status: string;
        created_at?: string | null;
        source_host?: string | null;
        excerpt?: string | null;
        importable_as_template: boolean;
        imported_template_key?: string | null;
    }>;
    templates: Array<{
        id: number;
        key: string;
        label: string;
        letter_type: string;
        source_system?: string | null;
        source_page_url?: string | null;
        updated_at?: string | null;
        content_excerpt?: string | null;
    }>;
}>();

const copying = ref(false);
const importingCaptureId = ref<number | null>(null);

const issueKey = () => {
    router.post('/migration-operator/api-key');
};

const importCapture = (captureId: number) => {
    importingCaptureId.value = captureId;

    router.post(`/migration-operator/captures/${captureId}/import-letter-template`, {}, {
        preserveScroll: true,
        onFinish: () => {
            importingCaptureId.value = null;
        },
    });
};

const copyToken = async () => {
    if (!props.generated_token) {
        return;
    }

    copying.value = true;

    try {
        await navigator.clipboard.writeText(props.generated_token);
    } finally {
        window.setTimeout(() => {
            copying.value = false;
        }, 1200);
    }
};

const formatDate = (value?: string | null) => {
    if (!value) {
        return 'Just now';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
};
</script>

<template>
    <Head title="CreditSoft OPS" />

    <div class="space-y-8">
        <section class="rounded-[32px] border border-stone-300/80 bg-[radial-gradient(circle_at_top_left,_rgba(245,178,35,0.16),_transparent_32%),linear-gradient(180deg,_rgba(255,255,255,0.96),_rgba(247,240,226,0.94))] p-6 shadow-[0_28px_60px_-44px_rgba(28,25,23,0.55)]">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl space-y-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.34em] text-stone-500">Internal only</p>
                    <div>
                        <h1 class="text-3xl font-semibold tracking-tight text-stone-950">CreditSoft OPS</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-stone-600">
                            Use your private owner-only migration key to capture live pages from DisputeFox and other approved systems, then stage or import them directly into CreditSoft. This stays out of the customer product surface.
                        </p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-[24px] border border-stone-200/80 bg-white/90 p-4">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-stone-500">API base</p>
                            <p class="mt-2 break-all text-sm font-semibold text-stone-900">{{ api_base_url }}</p>
                        </div>
                        <div class="rounded-[24px] border border-stone-200/80 bg-white/90 p-4">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-stone-500">Bundle</p>
                            <p class="mt-2 text-sm font-semibold text-stone-900">{{ bundle.name }}</p>
                            <p class="mt-1 text-xs text-stone-500">v{{ bundle.version }}</p>
                        </div>
                        <div class="rounded-[24px] border border-stone-200/80 bg-white/90 p-4">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-stone-500">Allowed hosts</p>
                            <p class="mt-2 text-sm font-semibold text-stone-900">{{ allowed_hosts.length }}</p>
                        </div>
                        <div class="rounded-[24px] border border-stone-200/80 bg-white/90 p-4">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-stone-500">Imported templates</p>
                            <p class="mt-2 text-sm font-semibold text-stone-900">{{ templates.length }}</p>
                        </div>
                        <div class="rounded-[24px] border border-stone-200/80 bg-white/90 p-4">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-stone-500">Automation discoveries</p>
                            <p class="mt-2 text-sm font-semibold text-stone-900">{{ automation_discoveries.length }}</p>
                        </div>
                    </div>
                </div>

                <div class="w-full max-w-[360px] space-y-4 rounded-[28px] border border-stone-300/80 bg-white/90 p-5 shadow-[0_22px_50px_-40px_rgba(28,25,23,0.4)]">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-stone-500">Run lane</p>
                        <p class="mt-2 text-sm leading-6 text-stone-600">
                            1. Generate a fresh OPS key. 2. Download and unzip the bundle. 3. Load it unpacked in Chrome. 4. Save the key once, then capture the live page you are on.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full bg-stone-950 px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.2em] text-white transition hover:bg-stone-800"
                            @click="issueKey"
                        >
                            <KeyRound class="h-4 w-4" />
                            Generate fresh key
                        </button>
                        <a
                            :href="bundle.download_url"
                            class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.2em] text-stone-800 transition hover:border-stone-500"
                        >
                            <Download class="h-4 w-4" />
                            Download bundle
                        </a>
                    </div>
                    <div
                        v-if="generated_token"
                        class="rounded-[22px] border border-emerald-200 bg-emerald-50/90 p-4"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-800">Generated key</p>
                        <p class="mt-2 break-all rounded-[16px] bg-white/90 px-3 py-3 font-mono text-xs leading-6 text-stone-800">
                            {{ generated_token }}
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                class="rounded-full border border-emerald-300 bg-white px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-800"
                                @click="copyToken"
                            >
                                {{ copying ? 'Copied' : 'Copy key' }}
                            </button>
                            <p class="text-xs leading-5 text-emerald-900">Full value is only shown here once.</p>
                        </div>
                    </div>
                    <div
                        v-else
                        class="rounded-[22px] border border-amber-200 bg-amber-50/90 p-4"
                    >
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-amber-800">Lost the plain key?</p>
                        <p class="mt-2 text-sm leading-6 text-amber-950">
                            You cannot read an old full key back out once it has been generated. If the OPS key is gone, generate a fresh one here and paste it into the extension again.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
            <div class="rounded-[30px] border border-stone-300/80 bg-white/90 p-5 shadow-[0_24px_54px_-44px_rgba(28,25,23,0.5)]">
                <div class="flex items-center gap-3 border-b border-stone-200 pb-4">
                    <UploadCloud class="h-5 w-5 text-stone-500" />
                    <div>
                        <h2 class="text-lg font-semibold text-stone-950">Staged captures</h2>
                        <p class="text-sm text-stone-600">Raw pages and operator imports land here first. Letter captures can be promoted again from this screen if needed.</p>
                    </div>
                </div>
                <div class="mt-5 space-y-4">
                    <div
                        v-for="capture in captures"
                        :key="capture.id"
                        class="rounded-[24px] border border-stone-200/80 bg-stone-50/70 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full border border-stone-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-600">{{ capture.source_system }}</span>
                                    <span class="rounded-full border border-stone-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-600">{{ capture.capture_type }}</span>
                                    <span class="rounded-full border border-stone-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-600">{{ capture.status }}</span>
                                </div>
                                <div>
                                    <p class="font-semibold text-stone-950">{{ capture.page_title || 'Untitled capture' }}</p>
                                    <p class="mt-1 break-all text-xs text-stone-500">{{ capture.page_url || capture.source_host }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs uppercase tracking-[0.18em] text-stone-400">{{ formatDate(capture.created_at) }}</p>
                                <p v-if="capture.imported_template_key" class="mt-2 text-xs font-medium text-emerald-700">
                                    Imported as {{ capture.imported_template_key }}
                                </p>
                            </div>
                        </div>
                        <p v-if="capture.excerpt" class="mt-3 text-sm leading-6 text-stone-600">{{ capture.excerpt }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <button
                                v-if="capture.importable_as_template && !capture.imported_template_key"
                                type="button"
                                class="rounded-full border border-stone-300 bg-white px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-800"
                                @click="importCapture(capture.id)"
                            >
                                {{ importingCaptureId === capture.id ? 'Importing…' : 'Import as template' }}
                            </button>
                            <span
                                v-else-if="capture.imported_template_key"
                                class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-800"
                            >
                                Template committed
                            </span>
                        </div>
                    </div>
                    <p v-if="captures.length === 0" class="rounded-[22px] border border-dashed border-stone-300 bg-stone-50/70 px-4 py-6 text-sm leading-6 text-stone-500">
                        Nothing staged yet. Once the internal Chrome operator posts a page, it will show up here.
                    </p>
                </div>
            </div>

            <div class="space-y-6">
                <section class="rounded-[30px] border border-stone-300/80 bg-white/90 p-5 shadow-[0_24px_54px_-44px_rgba(28,25,23,0.5)]">
                    <div class="flex items-center gap-3 border-b border-stone-200 pb-4">
                        <WandSparkles class="h-5 w-5 text-stone-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-stone-950">Automation discoveries</h2>
                            <p class="text-sm text-stone-600">Companion-spotted workflow patterns land here before anything becomes a global CreditSoft automation.</p>
                        </div>
                    </div>
                    <div class="mt-5 space-y-4">
                        <div
                            v-for="discovery in automation_discoveries"
                            :key="discovery.id"
                            class="rounded-[24px] border border-stone-200/80 bg-stone-50/70 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border border-stone-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-600">{{ discovery.source_product || discovery.source_system }}</span>
                                        <span v-if="discovery.status" class="rounded-full border border-stone-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-600">{{ discovery.status }}</span>
                                        <span v-if="discovery.promoted_at" class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-800">Promoted</span>
                                    </div>
                                    <p class="mt-3 font-semibold text-stone-950">{{ discovery.name || 'Untitled automation' }}</p>
                                    <p class="mt-1 break-all text-xs text-stone-500">{{ discovery.page_url || discovery.source_identifier || discovery.page_kind }}</p>
                                </div>
                                <p class="text-xs uppercase tracking-[0.18em] text-stone-400">{{ formatDate(discovery.last_seen_at) }}</p>
                            </div>
                            <div class="mt-4 grid gap-2 text-xs text-stone-600 sm:grid-cols-4">
                                <div class="rounded-2xl border border-stone-200 bg-white px-3 py-2">
                                    <span class="block uppercase tracking-[0.16em] text-stone-400">Seen</span>
                                    <span class="mt-1 block font-semibold text-stone-900">{{ discovery.seen_count }}</span>
                                </div>
                                <div class="rounded-2xl border border-stone-200 bg-white px-3 py-2">
                                    <span class="block uppercase tracking-[0.16em] text-stone-400">Conditions</span>
                                    <span class="mt-1 block font-semibold text-stone-900">{{ discovery.condition_count }}</span>
                                </div>
                                <div class="rounded-2xl border border-stone-200 bg-white px-3 py-2">
                                    <span class="block uppercase tracking-[0.16em] text-stone-400">Actions</span>
                                    <span class="mt-1 block font-semibold text-stone-900">{{ discovery.action_count }}</span>
                                </div>
                                <div class="rounded-2xl border border-stone-200 bg-white px-3 py-2">
                                    <span class="block uppercase tracking-[0.16em] text-stone-400">Steps</span>
                                    <span class="mt-1 block font-semibold text-stone-900">{{ discovery.step_count }}</span>
                                </div>
                            </div>
                            <div v-if="discovery.workflow_type || discovery.start_condition || discovery.category" class="mt-3 flex flex-wrap gap-2">
                                <span v-if="discovery.workflow_type" class="rounded-full border border-stone-200 bg-white px-3 py-1 text-xs text-stone-600">{{ discovery.workflow_type }}</span>
                                <span v-if="discovery.start_condition" class="rounded-full border border-stone-200 bg-white px-3 py-1 text-xs text-stone-600">{{ discovery.start_condition }}</span>
                                <span v-if="discovery.category" class="rounded-full border border-stone-200 bg-white px-3 py-1 text-xs text-stone-600">{{ discovery.category }}</span>
                            </div>
                            <div v-if="discovery.steps.length" class="mt-4 space-y-2">
                                <div
                                    v-for="step in discovery.steps"
                                    :key="`${discovery.id}-${step.title}`"
                                    class="rounded-2xl border border-stone-200 bg-white px-3 py-2"
                                >
                                    <p class="text-sm font-medium text-stone-900">{{ step.title }}</p>
                                    <p class="mt-1 text-xs text-stone-500">
                                        {{ [step.timing, step.actions.join(', ')].filter(Boolean).join(' · ') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p
                            v-if="automation_discoveries.length === 0"
                            class="rounded-[22px] border border-dashed border-stone-300 bg-stone-50/70 px-4 py-6 text-sm leading-6 text-stone-500"
                        >
                            No automation patterns spotted yet. Open an AutoFox workflow or workflow list, then press Go / Sync Pulse in the companion.
                        </p>
                    </div>
                </section>

                <section class="rounded-[30px] border border-stone-300/80 bg-white/90 p-5 shadow-[0_24px_54px_-44px_rgba(28,25,23,0.5)]">
                    <div class="flex items-center gap-3 border-b border-stone-200 pb-4">
                        <KeyRound class="h-5 w-5 text-stone-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-stone-950">Active OPS keys</h2>
                            <p class="text-sm text-stone-600">Masked keys already issued for this owner account. If you do not have the plain value anymore, generate a fresh one above.</p>
                        </div>
                    </div>
                    <div class="mt-5 space-y-4">
                        <div
                            v-for="key in active_keys"
                            :key="key.id"
                            class="rounded-[24px] border border-stone-200/80 bg-stone-50/70 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-stone-950">{{ key.name }}</p>
                                    <p class="mt-1 font-mono text-xs text-stone-500">{{ key.masked_token }}</p>
                                </div>
                                <p class="text-xs uppercase tracking-[0.18em] text-stone-400">
                                    {{ formatDate(key.created_at) }}
                                </p>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="ability in key.abilities"
                                    :key="ability"
                                    class="rounded-full border border-stone-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-600"
                                >
                                    {{ ability }}
                                </span>
                            </div>
                            <p class="mt-3 text-xs text-stone-500">
                                Last used: {{ formatDate(key.last_used_at) }}
                            </p>
                        </div>
                        <p
                            v-if="active_keys.length === 0"
                            class="rounded-[22px] border border-dashed border-stone-300 bg-stone-50/70 px-4 py-6 text-sm leading-6 text-stone-500"
                        >
                            No OPS keys issued yet. Generate your first key from the card above.
                        </p>
                    </div>
                </section>

                <section class="rounded-[30px] border border-stone-300/80 bg-white/90 p-5 shadow-[0_24px_54px_-44px_rgba(28,25,23,0.5)]">
                    <div class="flex items-center gap-3 border-b border-stone-200 pb-4">
                        <ScrollText class="h-5 w-5 text-stone-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-stone-950">Imported letter templates</h2>
                            <p class="text-sm text-stone-600">These are already inside CreditSoft and available in the client letters workspace.</p>
                        </div>
                    </div>
                    <div class="mt-5 space-y-4">
                        <div
                            v-for="template in templates"
                            :key="template.id"
                            class="rounded-[24px] border border-stone-200/80 bg-stone-50/70 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border border-stone-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-600">{{ template.letter_type }}</span>
                                        <span v-if="template.source_system" class="rounded-full border border-stone-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-600">{{ template.source_system }}</span>
                                    </div>
                                    <p class="mt-3 font-semibold text-stone-950">{{ template.label }}</p>
                                    <p class="mt-1 font-mono text-xs text-stone-500">{{ template.key }}</p>
                                </div>
                                <p class="text-xs uppercase tracking-[0.18em] text-stone-400">{{ formatDate(template.updated_at) }}</p>
                            </div>
                            <p v-if="template.content_excerpt" class="mt-3 text-sm leading-6 text-stone-600">{{ template.content_excerpt }}</p>
                            <p v-if="template.source_page_url" class="mt-3 break-all text-xs text-stone-500">{{ template.source_page_url }}</p>
                        </div>
                        <p v-if="templates.length === 0" class="rounded-[22px] border border-dashed border-stone-300 bg-stone-50/70 px-4 py-6 text-sm leading-6 text-stone-500">
                            No imported templates yet. Use the operator popup on a DisputeFox letter page, then come back here to verify it landed cleanly.
                        </p>
                    </div>
                </section>

                <section class="rounded-[30px] border border-stone-300/80 bg-white/90 p-5 shadow-[0_24px_54px_-44px_rgba(28,25,23,0.5)]">
                    <div class="flex items-center gap-3">
                        <WandSparkles class="h-5 w-5 text-stone-500" />
                        <div>
                            <h2 class="text-lg font-semibold text-stone-950">Capture rules</h2>
                            <p class="text-sm text-stone-600">This is intentionally narrower than the customer companion.</p>
                        </div>
                    </div>
                    <ul class="mt-4 space-y-2 text-sm leading-6 text-stone-600">
                        <li>Only your personal `migration_operator` key can use this lane.</li>
                        <li>The popup can stage any approved page, but letter import is the current focus.</li>
                        <li>The operator is for migration work and internal review, not customer deployment.</li>
                        <li>Allowed hosts are locked server-side: {{ allowed_hosts.join(', ') }}.</li>
                    </ul>
                </section>
            </div>
        </section>
    </div>
</template>
