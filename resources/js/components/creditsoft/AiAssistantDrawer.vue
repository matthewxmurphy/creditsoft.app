<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faArrowUpRightFromSquare,
    faCircle,
    faMessage,
    faPaperPlane,
    faXmark,
} from '@fortawesome/free-solid-svg-icons';
import AiProviderMark from '@/components/creditsoft/AiProviderMark.vue';

type ProviderName = 'opencode_zen' | 'openrouter_creditsoft' | 'ollama_cloud';

type Lane = {
    name: ProviderName;
    label: string;
    purpose?: string | null;
    configured: boolean;
    chat_model?: string | null;
    validation?: {
        state: string;
        message: string;
        checked_at?: string | null;
    };
};

type SharedProps = {
    url: string;
    client?: {
        id: number;
        display_name?: string;
        first_name?: string;
        last_name?: string;
    };
    creditsoft: {
        ai: {
            defaultProvider: ProviderName;
            catalog: {
                providers: Lane[];
            };
        };
    };
};

type Message = {
    id: string;
    role: 'assistant' | 'user';
    content: string;
    meta?: {
        provider?: string | null;
        model?: string | null;
    };
};

const page = usePage<SharedProps>();
const isOpen = ref(false);
const isSending = ref(false);
const draft = ref('');
const errorMessage = ref<string | null>(null);
const messageList = ref<Message[]>([]);
const messagePane = ref<HTMLElement | null>(null);

const laneCatalog = computed(() => page.props.creditsoft.ai.catalog.providers ?? []);
const client = computed(() => page.props.client ?? null);
const clientName = computed(() => {
    if (!client.value) return null;

    return client.value.display_name
        ?? `${client.value.first_name ?? ''} ${client.value.last_name ?? ''}`.trim()
        ?? null;
});

const selectedLane = ref<ProviderName | null>(null);

const laneStatusTone = (lane: Lane) => {
    const state = lane.validation?.state ?? 'missing';

    if (state === 'valid') return 'text-emerald-500';
    if (state === 'invalid') return 'text-rose-500';
    if (state === 'warning') return 'text-amber-500';

    return 'text-stone-400';
};

const resolvedLane = computed(() => laneCatalog.value.find((lane) => lane.name === selectedLane.value) ?? null);

const pickDefaultLane = () => {
    const preferred = laneCatalog.value.find(
        (lane) => lane.name === page.props.creditsoft.ai.defaultProvider && lane.configured && lane.validation?.state !== 'invalid',
    );
    const valid = laneCatalog.value.find((lane) => lane.configured && lane.validation?.state === 'valid');
    const fallback = laneCatalog.value.find((lane) => lane.configured);

    selectedLane.value = preferred?.name ?? valid?.name ?? fallback?.name ?? laneCatalog.value[0]?.name ?? null;
};

watch(laneCatalog, () => {
    if (!selectedLane.value || !laneCatalog.value.some((lane) => lane.name === selectedLane.value)) {
        pickDefaultLane();
    }
}, { immediate: true });

watch(isOpen, async (open) => {
    if (open) {
        await nextTick();
        messagePane.value?.scrollTo({ top: messagePane.value.scrollHeight, behavior: 'smooth' });
    }
});

watch(() => messageList.value.length, async () => {
    await nextTick();
    messagePane.value?.scrollTo({ top: messagePane.value.scrollHeight, behavior: 'smooth' });
});

const canSubmit = computed(() => {
    return draft.value.trim().length > 0
        && !!resolvedLane.value
        && resolvedLane.value.configured
        && resolvedLane.value.validation?.state !== 'invalid'
        && !isSending.value;
});

const quickPrompts = computed(() => {
    if (client.value) {
        return [
            'Scan top Metro 2 conflicts',
            'What is the next best action?',
            'Draft the next brief outline',
        ];
    }

    return [
        'What should staff review first?',
        'How should we use the 3 lanes?',
        'Give me a dispute QA checklist',
    ];
});

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const sendMessage = async (seed?: string) => {
    if (seed) {
        draft.value = seed;
    }

    const message = draft.value.trim();

    if (!message || !resolvedLane.value || !resolvedLane.value.configured || resolvedLane.value.validation?.state === 'invalid') {
        return;
    }

    const userMessage: Message = {
        id: `user-${Date.now()}`,
        role: 'user',
        content: message,
    };
    const priorHistory = messageList.value
        .slice(-6)
        .map((entry) => ({
            role: entry.role,
            content: entry.content,
        }));

    messageList.value.push(userMessage);
    draft.value = '';
    errorMessage.value = null;
    isSending.value = true;

    try {
        const response = await fetch('/internal/ai/chat', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                lane: resolvedLane.value.name,
                message,
                client_id: client.value?.id ?? null,
                history: priorHistory,
            }),
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message ?? 'The selected AI lane could not answer right now.');
        }

        messageList.value.push({
            id: `assistant-${Date.now()}`,
            role: 'assistant',
            content: payload.reply,
            meta: {
                provider: payload.meta?.provider ?? resolvedLane.value.label,
                model: payload.meta?.model ?? resolvedLane.value.chat_model ?? null,
            },
        });
    } catch (error) {
        const messageText = error instanceof Error ? error.message : 'The selected AI lane could not answer right now.';
        errorMessage.value = messageText;
        messageList.value.push({
            id: `assistant-error-${Date.now()}`,
            role: 'assistant',
            content: messageText,
        });
    } finally {
        isSending.value = false;
    }
};
</script>

<template>
    <div class="pointer-events-none fixed bottom-0 right-3 z-20 flex flex-col items-end gap-0 overflow-visible md:right-5">
        <Transition
            enter-active-class="transition duration-250 ease-out"
            enter-from-class="translate-y-8 scale-[0.97] opacity-0"
            enter-to-class="translate-y-0 scale-100 opacity-100"
            leave-active-class="transition duration-180 ease-in"
            leave-from-class="translate-y-0 scale-100 opacity-100"
            leave-to-class="translate-y-6 scale-[0.98] opacity-0"
        >
            <section
                v-if="isOpen"
                class="pointer-events-auto mb-[-28px] flex h-[min(82vh,46rem)] w-[min(39rem,calc(100vw-2rem))] origin-bottom-right flex-col overflow-hidden rounded-[30px] border border-stone-300/80 bg-white text-stone-950 shadow-[0_32px_80px_rgba(28,25,23,0.18)]"
            >
            <div class="flex items-start justify-between gap-4 border-b border-stone-200 px-5 py-4">
                <div class="space-y-1">
                    <div>
                        <p class="text-lg font-semibold text-stone-950">CreditSoft Copilot</p>
                        <p class="text-[11px] leading-4 text-stone-500">
                            {{ clientName ? `Working inside ${clientName}` : 'Workspace-level casework assistant' }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="flex size-10 items-center justify-center rounded-full border border-stone-300 text-stone-500 transition hover:border-stone-500 hover:text-stone-950"
                    aria-label="Close AI assistant"
                    @click="isOpen = false"
                >
                    <FontAwesomeIcon :icon="faXmark" />
                </button>
            </div>

            <div class="border-b border-stone-200 px-5 py-4">
                <div class="grid gap-2.5 md:grid-cols-3">
                    <button
                        v-for="lane in laneCatalog"
                        :key="lane.name"
                        type="button"
                        class="relative flex h-12 min-h-12 items-center justify-center rounded-[16px] border px-3 transition"
                        :class="selectedLane === lane.name
                            ? 'border-amber-400 bg-amber-50 shadow-[0_18px_40px_rgba(245,158,11,0.12)]'
                            : 'border-stone-200 bg-stone-50 hover:border-stone-400 hover:bg-white'"
                        :aria-label="`${lane.label} lane`"
                        @click="selectedLane = lane.name"
                    >
                        <AiProviderMark :provider="lane.name" centered large />
                        <FontAwesomeIcon :icon="faCircle" class="absolute right-3 top-3 text-[9px]" :class="laneStatusTone(lane)" />
                    </button>
                </div>

                <div v-if="resolvedLane" class="mt-2 flex flex-wrap items-center gap-2 text-[10px] uppercase tracking-[0.18em] text-stone-500">
                    <AiProviderMark :provider="resolvedLane.name" />
                    <span class="rounded-full border border-stone-200 bg-stone-50 px-2 py-0.5 text-stone-500">
                        {{ resolvedLane.chat_model ?? 'Model not defined' }}
                    </span>
                </div>
                <div class="mt-2 h-px bg-stone-200" />
            </div>

            <div ref="messagePane" class="flex-1 space-y-4 overflow-y-auto bg-stone-50/60 px-5 py-4">
                <div
                    v-for="message in messageList"
                    :key="message.id"
                    class="flex"
                    :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[88%] rounded-[24px] px-4 py-3 shadow-sm"
                        :class="message.role === 'user' ? 'bg-amber-300 text-stone-950' : 'border border-stone-200 bg-white text-stone-900'"
                    >
                        <p class="whitespace-pre-wrap text-sm leading-6">{{ message.content }}</p>
                        <p v-if="message.meta?.provider || message.meta?.model" class="mt-2 text-[11px] uppercase tracking-[0.2em] text-stone-500">
                            {{ message.meta?.provider }}<span v-if="message.meta?.model"> • {{ message.meta?.model }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-3 border-t border-stone-200 bg-stone-50/70 px-5 py-4">
                <div class="grid grid-cols-3 gap-1.5">
                    <button
                        v-for="prompt in quickPrompts"
                        :key="prompt"
                        type="button"
                        class="rounded-full border border-stone-300 bg-white px-2 py-1 text-[9px] uppercase tracking-[0.12em] text-stone-600 transition hover:border-stone-500 hover:text-stone-950"
                        @click="sendMessage(prompt)"
                    >
                        {{ prompt }}
                    </button>
                </div>

                <div v-if="resolvedLane?.validation?.state === 'invalid'" class="rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ resolvedLane.validation?.message }}
                    <Link href="/settings/ai" class="ml-2 inline-flex items-center gap-1 font-medium text-rose-700 underline underline-offset-4">
                        Fix AI settings
                        <FontAwesomeIcon :icon="faArrowUpRightFromSquare" class="text-[11px]" />
                    </Link>
                </div>

                <div v-else-if="!resolvedLane?.configured" class="rounded-2xl border border-stone-300 bg-stone-50 px-4 py-3 text-sm text-stone-600">
                    This lane is not configured yet.
                    <Link href="/settings/ai" class="ml-2 inline-flex items-center gap-1 font-medium text-stone-800 underline underline-offset-4">
                        Open AI settings
                        <FontAwesomeIcon :icon="faArrowUpRightFromSquare" class="text-[11px]" />
                    </Link>
                </div>

                <div v-if="errorMessage" class="rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-700">
                    {{ errorMessage }}
                </div>

                <div class="rounded-[22px] border border-stone-300 bg-white px-3 py-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.8)]">
                    <textarea
                        v-model="draft"
                        rows="3"
                        class="w-full resize-none bg-transparent text-sm leading-6 text-stone-900 placeholder:text-stone-400 focus:outline-none"
                        placeholder="Ask for a Metro 2 review, dispute angle, summary, next action, or workflow check..."
                    />
                    <div class="mt-3 flex items-center justify-between gap-3 border-t border-stone-200 pt-3">
                        <p class="text-xs leading-5 text-stone-500">
                            All AI replies stay local and are logged to the audit trail.
                        </p>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full bg-stone-950 px-4 py-2 text-xs font-medium uppercase tracking-[0.2em] text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:bg-stone-300 disabled:text-stone-500"
                            :disabled="!canSubmit"
                            @click="sendMessage()"
                        >
                            <span>{{ isSending ? 'Thinking' : 'Send' }}</span>
                            <FontAwesomeIcon :icon="faPaperPlane" />
                        </button>
                    </div>
                </div>
            </div>
            </section>
        </Transition>

        <button
            type="button"
            class="pointer-events-auto inline-flex h-[58px] w-[68px] -translate-y-[47px] items-start justify-center rounded-t-[22px] border border-b-0 border-stone-300/70 bg-stone-950 pt-2 text-stone-50 shadow-[0_-8px_24px_rgba(28,25,23,0.16)] transition hover:bg-stone-900"
            aria-label="Open AI chat"
            @click="isOpen = !isOpen"
        >
            <span class="flex size-8 items-center justify-center rounded-full bg-amber-300 text-stone-950">
                <FontAwesomeIcon :icon="faMessage" />
            </span>
        </button>
    </div>
</template>
