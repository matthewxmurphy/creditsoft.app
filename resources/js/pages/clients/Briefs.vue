<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AiSetupPrompt from '@/components/creditsoft/AiSetupPrompt.vue';
import ClientDocumentLightbox from '@/components/creditsoft/ClientDocumentLightbox.vue';
import ClientHealthSummary from '@/components/creditsoft/ClientHealthSummary.vue';
import ClientWorkspaceNav from '@/components/creditsoft/ClientWorkspaceNav.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { ClientHealthSignal } from '@/lib/client-health';
import { formatDate } from '@/lib/creditsoft';

const props = defineProps<{
    client: {
        id: number;
        display_name: string;
        status?: string | null;
    };
    clientHealth?: ClientHealthSignal | string | null;
    cycles: Array<{
        id: number;
        cycle_label: string;
    }>;
    briefs: Array<{
        id: number;
        title: string;
        content: string;
        period: string;
        approved_at?: string | null;
        reporting_cycle?: string | null;
        generated_by_ai?: boolean;
        ai_metadata?: {
            provider?: string | null;
            model?: string | null;
        } | null;
        pdf_document?: {
            id: number;
            title: string;
            file_name: string;
            uploaded_at?: string | null;
            download_url: string;
        } | null;
    }>;
    aiTask?: {
        label: string;
    } | null;
}>();

const page = usePage<{
    creditsoft: {
        ai: {
            needsSetup: boolean;
        };
    };
}>();

const showAiSetup = ref(page.props.creditsoft.ai.needsSetup);
const activeDocumentPreview = ref<NonNullable<
    (typeof props.briefs)[number]['pdf_document']
> | null>(null);

const openDocumentPreview = (
    document: NonNullable<(typeof props.briefs)[number]['pdf_document']>,
) => {
    activeDocumentPreview.value = document;
};

const closeDocumentPreview = () => {
    activeDocumentPreview.value = null;
};

const form = useForm({
    reporting_cycle_id: props.cycles[0]?.id?.toString() ?? '',
    period: 'weekly',
    title: '',
    content: '',
    approve_now: true,
});

const aiForm = useForm({
    reporting_cycle_id: props.cycles[0]?.id?.toString() ?? '',
    period: 'weekly',
    operator_focus: '',
});

const aiBadge = computed(() => props.aiTask?.label ?? 'AI brief lane');

const submit = () => {
    form.post(`/clients/${props.client.id}/briefs`, {
        preserveScroll: true,
        onSuccess: () => form.reset('title', 'content'),
    });
};

const generateAiDraft = () => {
    if (page.props.creditsoft.ai.needsSetup) {
        showAiSetup.value = true;

        return;
    }

    aiForm.post(`/clients/${props.client.id}/briefs/ai-draft`, {
        preserveScroll: true,
        onSuccess: () => aiForm.reset('operator_focus'),
    });
};
</script>

<template>
    <Head :title="`Briefs - ${client.display_name}`" />

    <h1 class="sr-only">{{ client.display_name }} briefs</h1>

    <div class="space-y-8">
        <ClientWorkspaceNav
            :client-id="client.id"
            :health-signal="clientHealth"
        />

        <ClientHealthSummary :client="client" :health-signal="clientHealth" />

        <AiSetupPrompt v-if="showAiSetup" compact />

        <section class="grid gap-8 xl:grid-cols-[0.85fr_1.15fr]">
            <div class="space-y-6">
                <form
                    class="space-y-3 rounded-[28px] border border-stone-300/70 bg-stone-50/75 p-4"
                    @submit.prevent="generateAiDraft"
                >
                    <div class="border-b border-stone-300/70 pb-3">
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                                >
                                    AI brief lane
                                </p>
                                <p class="text-sm text-stone-600">
                                    Generate a supervised shareable brief from
                                    the latest cycle and browser evidence.
                                </p>
                            </div>
                            <span
                                class="rounded-full border border-stone-300 bg-white px-3 py-1.5 text-[11px] tracking-[0.18em] text-stone-600 uppercase"
                            >
                                {{ aiBadge }}
                            </span>
                        </div>
                    </div>
                    <select
                        v-model="aiForm.reporting_cycle_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option
                            v-for="cycle in cycles"
                            :key="cycle.id"
                            :value="cycle.id.toString()"
                        >
                            {{ cycle.cycle_label }}
                        </option>
                    </select>
                    <div class="grid gap-3 md:grid-cols-2">
                        <select
                            v-model="aiForm.period"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                        <button
                            type="button"
                            class="rounded-full border border-stone-300 px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-700 uppercase transition hover:border-stone-500"
                            @click="showAiSetup = true"
                        >
                            AI settings
                        </button>
                    </div>
                    <Textarea
                        v-model="aiForm.operator_focus"
                        placeholder="Optional: add operator guidance for tone, priorities, or what to emphasize."
                    />
                    <button
                        type="submit"
                        class="rounded-full bg-amber-400 px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-950 uppercase transition hover:bg-amber-300"
                    >
                        Generate AI brief
                    </button>
                </form>

                <form class="space-y-3" @submit.prevent="submit">
                    <div class="border-b border-stone-300/70 pb-3">
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            Shareable case brief
                        </p>
                        <p class="text-sm text-stone-600">
                            Approved briefs can enter the sanitized signal
                            outbox.
                        </p>
                    </div>
                    <select
                        v-model="form.reporting_cycle_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option
                            v-for="cycle in cycles"
                            :key="cycle.id"
                            :value="cycle.id.toString()"
                        >
                            {{ cycle.cycle_label }}
                        </option>
                    </select>
                    <div class="grid gap-3 md:grid-cols-2">
                        <select
                            v-model="form.period"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                        <Input
                            v-model="form.title"
                            placeholder="April weekly brief"
                        />
                    </div>
                    <Textarea
                        v-model="form.content"
                        placeholder="Summarize progress, focus items, and next actions in clean language."
                    />
                    <label
                        class="flex items-center gap-2 text-sm text-stone-600"
                    >
                        <input
                            v-model="form.approve_now"
                            type="checkbox"
                            class="rounded border-stone-300"
                        />
                        Approve immediately and queue sanitized signal
                    </label>
                    <button
                        type="submit"
                        class="rounded-full bg-stone-950 px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-50 uppercase transition hover:bg-stone-800"
                    >
                        Save brief
                    </button>
                </form>
            </div>

            <section class="space-y-3">
                <div class="border-b border-stone-300/70 pb-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Approved narrative
                    </p>
                    <p class="text-sm text-stone-600">
                        If the queue was empty, CreditSoft seeded the first
                        brief and saved a PDF packet for review.
                    </p>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="brief in briefs"
                        :key="brief.id"
                        class="rounded-[24px] border border-stone-300/70 bg-stone-50/70 p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-medium text-stone-950">
                                    {{ brief.title }}
                                </p>
                                <p
                                    class="text-xs tracking-[0.22em] text-stone-500 uppercase"
                                >
                                    {{ brief.period }}
                                </p>
                                <p
                                    v-if="brief.reporting_cycle"
                                    class="mt-2 text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    {{ brief.reporting_cycle }}
                                </p>
                                <p
                                    v-if="brief.generated_by_ai"
                                    class="mt-2 text-[11px] tracking-[0.18em] text-amber-700 uppercase"
                                >
                                    AI drafted via
                                    {{
                                        brief.ai_metadata?.provider ??
                                        'configured provider'
                                    }}
                                    /
                                    {{
                                        brief.ai_metadata?.model ??
                                        'default model'
                                    }}
                                </p>
                            </div>
                            <p
                                class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                            >
                                {{
                                    brief.approved_at
                                        ? `Approved ${formatDate(brief.approved_at)}`
                                        : 'Draft'
                                }}
                            </p>
                        </div>
                        <p class="mt-3 text-sm text-stone-700">
                            {{ brief.content }}
                        </p>
                        <div
                            v-if="brief.pdf_document"
                            class="mt-4 rounded-[20px] border border-stone-200/80 bg-white/90 px-3 py-2 text-sm text-stone-600"
                        >
                            <p
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                            >
                                Review packet PDF
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <span>{{ brief.pdf_document.file_name }}</span>
                                <button
                                    type="button"
                                    class="rounded-full border border-stone-300 px-3 py-1.5 text-[11px] tracking-[0.18em] text-stone-700 uppercase transition hover:border-stone-500"
                                    @click="
                                        openDocumentPreview(brief.pdf_document)
                                    "
                                >
                                    Open PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </section>

        <ClientDocumentLightbox
            :open="Boolean(activeDocumentPreview)"
            :document="activeDocumentPreview"
            @close="closeDocumentPreview"
        />
    </div>
</template>
