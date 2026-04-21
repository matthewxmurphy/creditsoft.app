<script setup lang="ts">
import { computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faCircleCheck, faCircleExclamation, faClockRotateLeft, faKey } from '@fortawesome/free-solid-svg-icons';
import AiProviderMark from '@/components/creditsoft/AiProviderMark.vue';

const props = withDefaults(defineProps<{
    compact?: boolean;
}>(), {
    compact: false,
});

const page = usePage<{
    url: string;
    creditsoft: {
        ai: {
            defaultProvider: string;
            catalog: {
                providers: Array<{
                    name: ProviderName;
                    configured: boolean;
                    masked_key?: string | null;
                    validation?: {
                        state: string;
                        message: string;
                        checked_at?: string | null;
                    };
                }>;
            };
        };
    };
}>();

type ProviderName = 'opencode_zen' | 'openrouter_creditsoft' | 'ollama_cloud';

const providers: Array<{
    name: ProviderName;
    label: string;
    help: string;
}> = [
    {
        name: 'opencode_zen',
        label: 'OpenCode Zen',
        help: 'Big Pickle for the heaviest drafting lane.',
    },
    {
        name: 'openrouter_creditsoft',
        label: 'OpenRouter',
        help: 'Trinity or Nemotron for structured reasoning work.',
    },
    {
        name: 'ollama_cloud',
        label: 'Ollama Cloud',
        help: 'Remote Nemotron lane for heavier cloud-backed review.',
    },
];

const form = useForm({
    default_provider: page.props.creditsoft.ai.defaultProvider || 'openrouter_creditsoft',
    opencode_api_key: '',
    openrouter_api_key: '',
    ollama_cloud_api_key: '',
    redirect_to: page.url,
});

const configuredProviders = computed<Record<string, boolean>>(() => {
    return Object.fromEntries(
        (page.props.creditsoft.ai.catalog.providers ?? []).map((provider) => [provider.name, provider.configured]),
    );
});

const maskedKeys = computed<Record<string, string | null>>(() => {
    return Object.fromEntries(
        (page.props.creditsoft.ai.catalog.providers ?? []).map((provider) => [provider.name, provider.masked_key ?? null]),
    );
});

const validationStates = computed<Record<string, { state: string; message: string; checked_at?: string | null }>>(() => {
    return Object.fromEntries(
        (page.props.creditsoft.ai.catalog.providers ?? []).map((provider) => [
            provider.name,
            provider.validation ?? { state: 'missing', message: 'No API key saved yet.', checked_at: null },
        ]),
    );
});

const apiKeyFor = (provider: ProviderName) => {
    if (provider === 'opencode_zen') return form.opencode_api_key;
    if (provider === 'openrouter_creditsoft') return form.openrouter_api_key;

    return form.ollama_cloud_api_key;
};

const updateApiKey = (provider: ProviderName, value: string) => {
    if (provider === 'opencode_zen') {
        form.opencode_api_key = value;
        return;
    }

    if (provider === 'openrouter_creditsoft') {
        form.openrouter_api_key = value;
        return;
    }

    form.ollama_cloud_api_key = value;
};

const placeholderFor = (provider: ProviderName) => {
    return maskedKeys.value[provider] ?? 'Paste API key';
};

const hasNewInput = (provider: ProviderName) => {
    return apiKeyFor(provider).trim().length > 0;
};

const statusPill = (provider: ProviderName) => {
    const state = validationStates.value[provider]?.state ?? 'missing';

    if (state === 'valid') {
        return {
            label: 'Valid',
            className: 'border-emerald-300 bg-emerald-100 text-emerald-900',
            icon: faCircleCheck,
        };
    }

    if (state === 'invalid') {
        return {
            label: 'Invalid key',
            className: 'border-rose-300 bg-rose-100 text-rose-900',
            icon: faCircleExclamation,
        };
    }

    if (state === 'warning') {
        return {
            label: 'Needs check',
            className: 'border-amber-300 bg-amber-100 text-amber-900',
            icon: faClockRotateLeft,
        };
    }

    if (maskedKeys.value[provider]) {
        return {
            label: 'Saved',
            className: 'border-sky-300 bg-sky-100 text-sky-900',
            icon: faKey,
        };
    }

    return {
        label: 'Missing key',
        className: 'border-stone-300 bg-white text-stone-700',
        icon: faCircleExclamation,
    };
};

const submit = () => {
    form.put('/settings/ai', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('opencode_api_key', 'openrouter_api_key', 'ollama_cloud_api_key');
            router.reload({
                only: ['creditsoft'],
            });
        },
    });
};
</script>

<template>
    <form
        class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/92 shadow-[0_18px_48px_rgba(120,113,108,0.08)]"
        :class="compact ? 'w-full max-w-none' : ''"
        @submit.prevent="submit"
    >
        <div class="space-y-2 border-b border-stone-300/70 px-5 py-5">
            <p class="text-[11px] font-medium uppercase tracking-[0.3em] text-stone-500">AI setup required</p>
            <h3 class="text-xl font-semibold text-stone-950">Connect a serious-work model before drafting</h3>
            <p class="text-sm leading-6 text-stone-600">
                Paste any provider keys you want to save, then choose which one should be the default lane.
            </p>
        </div>

        <div class="grid divide-y divide-stone-200/80 lg:grid-cols-3 lg:divide-x lg:divide-y-0">
            <article
                v-for="provider in providers"
                :key="provider.name"
                class="bg-white/90 px-6 py-6"
                :class="form.default_provider === provider.name ? 'bg-[linear-gradient(180deg,rgba(255,250,235,0.72),rgba(255,255,255,0.98))]' : ''"
            >
                <div class="space-y-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-3">
                            <div class="flex min-h-8 items-center">
                                <AiProviderMark :provider="provider.name" large />
                            </div>
                            <p class="max-w-[22rem] text-sm leading-6 text-stone-600">{{ provider.help }}</p>
                        </div>
                        <span
                            class="inline-flex h-8 min-w-[7.5rem] shrink-0 items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-center text-[11px] font-medium uppercase leading-none tracking-[0.18em]"
                            :class="statusPill(provider.name).className"
                        >
                            <FontAwesomeIcon :icon="statusPill(provider.name).icon" class="mr-1.5 text-[11px]" />
                            {{ statusPill(provider.name).label }}
                        </span>
                    </div>

                    <label class="flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-stone-500">
                        <input
                            v-model="form.default_provider"
                            type="radio"
                            name="default_provider"
                            :value="provider.name"
                            class="h-4 w-4 border-stone-400 text-stone-950"
                        />
                        Default provider
                    </label>

                    <label class="block space-y-2 text-sm text-stone-700">
                        <span>API key</span>
                        <input
                            :value="apiKeyFor(provider.name)"
                            type="password"
                            class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                            :placeholder="placeholderFor(provider.name)"
                            @input="updateApiKey(provider.name, ($event.target as HTMLInputElement).value)"
                        />
                    </label>

                    <p v-if="maskedKeys[provider.name] && !hasNewInput(provider.name)" class="text-xs leading-5 text-stone-500">
                        Saved key on file: {{ maskedKeys[provider.name] }}. Paste a new key only if you want to replace it.
                    </p>
                    <p class="text-xs leading-5 text-stone-500">
                        {{ validationStates[provider.name]?.message }}
                    </p>
                </div>
            </article>
        </div>

        <div class="flex flex-col gap-4 border-t border-stone-200/80 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
            <p class="text-sm text-stone-600">
                Blank fields leave any already-saved key alone.
            </p>

            <button type="submit" class="rounded-full bg-stone-950 px-5 py-3 text-xs font-medium uppercase tracking-[0.22em] text-stone-50 transition hover:bg-stone-800">
                {{ form.processing ? 'Saving AI setup...' : 'Save provider and continue' }}
            </button>
        </div>
    </form>
</template>
