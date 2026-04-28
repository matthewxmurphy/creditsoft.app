<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AiSetupPrompt from '@/components/creditsoft/AiSetupPrompt.vue';
import ClientDocumentLightbox from '@/components/creditsoft/ClientDocumentLightbox.vue';
import ClientHealthSummary from '@/components/creditsoft/ClientHealthSummary.vue';
import ClientWorkspaceNav from '@/components/creditsoft/ClientWorkspaceNav.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { ClientHealthSignal } from '@/lib/client-health';

type TemplateEntry = {
    key: string;
    version: string;
    label: string;
    letter_type: string;
    legal_basis: string[];
    description?: string | null;
    ai_focus?: string | null;
    operator_notes?: string | null;
    content_template?: string | null;
    source_label?: string | null;
    imported?: boolean;
    recommendation_reason?: string | null;
    availability_reason?: string | null;
};

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
    letters: Array<{
        id: number;
        title: string;
        letter_type: string;
        template_key?: string | null;
        template_version?: string | null;
        status: string;
        content: string;
        reporting_cycle?: string | null;
        generated_by_ai?: boolean;
        ai_metadata?: {
            provider?: string | null;
            model?: string | null;
        } | null;
        recipient_bureau?: string | null;
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
        provider?: string | null;
        model?: string | null;
        fallbacks?: Array<{
            provider?: string | null;
            model?: string | null;
            configured?: boolean;
        }>;
    } | null;
    templates: TemplateEntry[];
    templateReview: {
        signals: {
            has_report_context: boolean;
            has_bankruptcy: boolean;
            has_inquiries: boolean;
            has_late_payments: boolean;
            has_collection_chargeoffs: boolean;
        };
        recommended: TemplateEntry[];
        available: TemplateEntry[];
        still_available: TemplateEntry[];
        hidden: TemplateEntry[];
    };
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
    (typeof props.letters)[number]['pdf_document']
> | null>(null);

const openDocumentPreview = (
    document: NonNullable<(typeof props.letters)[number]['pdf_document']>,
) => {
    activeDocumentPreview.value = document;
};

const closeDocumentPreview = () => {
    activeDocumentPreview.value = null;
};

const templateLookup = computed(() =>
    Object.fromEntries(
        props.templates.map((template) => [template.key, template]),
    ),
);

const defaultTemplateFor = (letterType: string) =>
    props.templates.find((template) => template.letter_type === letterType);

const legalBasisForTemplate = (templateKey: string) =>
    templateLookup.value[templateKey]?.legal_basis?.join(', ') ?? '';

const form = useForm({
    reporting_cycle_id: props.cycles[0]?.id?.toString() ?? '',
    title: '',
    letter_type: 'dispute',
    template_key: defaultTemplateFor('dispute')?.key ?? '',
    legal_basis: 'FCRA § 611, Metro 2 completeness',
    content: '',
});

const aiForm = useForm({
    reporting_cycle_id: props.cycles[0]?.id?.toString() ?? '',
    letter_type: 'dispute',
    template_key: defaultTemplateFor('dispute')?.key ?? '',
    legal_basis: 'FCRA § 611, Metro 2 completeness',
    operator_focus: '',
});

const aiBadge = computed(() => props.aiTask?.label ?? 'AI draft lane');

const selectedManualTemplate = computed(
    () => templateLookup.value[form.template_key] ?? null,
);
const selectedAiTemplate = computed(
    () => templateLookup.value[aiForm.template_key] ?? null,
);
const selectedManualTemplateHasBody = computed(() =>
    Boolean(selectedManualTemplate.value?.content_template),
);
const recommendedTemplates = computed(
    () => props.templateReview.recommended ?? [],
);
const stillAvailableTemplates = computed(
    () => props.templateReview.still_available ?? [],
);
const hiddenTemplates = computed(() => props.templateReview.hidden ?? []);
const visibleSignalSummary = computed(() => {
    const labels: string[] = [];

    if (props.templateReview.signals.has_collection_chargeoffs) {
        labels.push('Collection / chargeoff');
    }

    if (props.templateReview.signals.has_late_payments) {
        labels.push('Late payments');
    }

    if (props.templateReview.signals.has_inquiries) {
        labels.push('Inquiries');
    }

    if (props.templateReview.signals.has_bankruptcy) {
        labels.push('Bankruptcy / public record');
    }

    return labels;
});
const manualTemplateOptions = computed(() =>
    props.templates.filter((entry) => entry.letter_type === form.letter_type),
);
const aiTemplateOptions = computed(() =>
    props.templates.filter((entry) => entry.letter_type === aiForm.letter_type),
);

const mergeFieldGroups = [
    {
        key: 'client',
        label: 'Client info',
        description:
            'Imported letters often expect the client identity and contact lane first.',
        fields: [
            '[client_first_name]',
            '[client_last_name]',
            '[client_email]',
            '[client_city]',
            '[client_state]',
            '[client_postal_code]',
            '[client_address]',
            '[bdate]',
            '[ss_number]',
            '[t_no]',
        ],
    },
    {
        key: 'bureau',
        label: 'Bureau and case context',
        description:
            'Useful when the legacy letter body expects bureau-specific or dispute-summary tokens.',
        fields: [
            '[bureau_name]',
            '[bureau_address]',
            '[curr_date]',
            '[dispute_item_and_explanation]',
            '[account_name_number]',
            '[account , dispute_reason]',
        ],
    },
    {
        key: 'advanced',
        label: 'Personal info and inquiry lanes',
        description:
            'These showed up repeatedly in the imported library and are the main mapping candidates next.',
        fields: [
            '[personalInformation_disputeInstruction]',
            '[personalInformation_disputeReason]',
            '[inquiries_explanation]',
            '[detailed_accounts_collections]',
        ],
    },
];

const copiedMergeToken = ref<string | null>(null);
let copiedMergeTokenTimer: ReturnType<typeof setTimeout> | null = null;

const prettifyStatus = (status: string) =>
    status.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const prettifyLetterType = (letterType: string) =>
    letterType
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());

const prettifyBureau = (bureau?: string | null) =>
    ({
        equifax: 'Equifax',
        experian: 'Experian',
        transunion: 'TransUnion',
    })[bureau ?? ''] ??
    (bureau ?? '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());

const copyMergeToken = async (token: string) => {
    try {
        await navigator.clipboard.writeText(token);
        copiedMergeToken.value = token;

        if (copiedMergeTokenTimer) {
            clearTimeout(copiedMergeTokenTimer);
        }

        copiedMergeTokenTimer = setTimeout(() => {
            copiedMergeToken.value = null;
        }, 1800);
    } catch {
        copiedMergeToken.value = null;
    }
};

const applyTemplateToManual = (template: TemplateEntry) => {
    form.letter_type = template.letter_type;
    form.template_key = template.key;
    form.legal_basis = template.legal_basis.join(', ');

    if (!form.title.trim()) {
        form.title = template.label;
    }

    if (!form.content.trim() && template.content_template?.trim()) {
        form.content = template.content_template.trim();
    }
};

const applyTemplateToAi = (template: TemplateEntry) => {
    aiForm.letter_type = template.letter_type;
    aiForm.template_key = template.key;
    aiForm.legal_basis = template.legal_basis.join(', ');
};

const submit = () => {
    form.post(`/clients/${props.client.id}/letters`, {
        preserveScroll: true,
        onSuccess: () => form.reset('title', 'content'),
    });
};

const loadTemplateBody = () => {
    const contentTemplate =
        selectedManualTemplate.value?.content_template?.trim();

    if (!contentTemplate) {
        return;
    }

    form.content = contentTemplate;

    if (!form.title.trim()) {
        form.title = selectedManualTemplate.value?.label ?? form.title;
    }
};

const generateAiDraft = () => {
    if (page.props.creditsoft.ai.needsSetup) {
        showAiSetup.value = true;

        return;
    }

    aiForm.post(`/clients/${props.client.id}/letters/ai-draft`, {
        preserveScroll: true,
        onSuccess: () => aiForm.reset('operator_focus'),
    });
};

const setStatus = (id: number, status: string) => {
    router.patch(
        `/clients/${props.client.id}/letters/${id}`,
        { status },
        { preserveScroll: true },
    );
};

watch(
    () => form.letter_type,
    (letterType) => {
        const nextTemplate = props.templates.find(
            (template) =>
                template.letter_type === letterType &&
                template.key === form.template_key,
        )
            ? templateLookup.value[form.template_key]
            : defaultTemplateFor(letterType);

        form.template_key = nextTemplate?.key ?? '';
        form.legal_basis =
            legalBasisForTemplate(form.template_key) || form.legal_basis;
    },
);

watch(
    () => aiForm.letter_type,
    (letterType) => {
        const nextTemplate = props.templates.find(
            (template) =>
                template.letter_type === letterType &&
                template.key === aiForm.template_key,
        )
            ? templateLookup.value[aiForm.template_key]
            : defaultTemplateFor(letterType);

        aiForm.template_key = nextTemplate?.key ?? '';
        aiForm.legal_basis =
            legalBasisForTemplate(aiForm.template_key) || aiForm.legal_basis;
    },
);

watch(
    () => form.template_key,
    (templateKey) => {
        if (templateKey) {
            form.legal_basis =
                legalBasisForTemplate(templateKey) || form.legal_basis;

            if (!form.content.trim()) {
                const contentTemplate =
                    templateLookup.value[templateKey]?.content_template?.trim();

                if (contentTemplate) {
                    form.content = contentTemplate;
                }
            }

            if (!form.title.trim()) {
                form.title =
                    templateLookup.value[templateKey]?.label ?? form.title;
            }
        }
    },
);

watch(
    () => aiForm.template_key,
    (templateKey) => {
        if (templateKey) {
            aiForm.legal_basis =
                legalBasisForTemplate(templateKey) || aiForm.legal_basis;
        }
    },
);
</script>

<template>
    <Head :title="`Letters - ${client.display_name}`" />

    <h1 class="sr-only">{{ client.display_name }} letters</h1>

    <div class="space-y-8">
        <ClientWorkspaceNav
            :client-id="client.id"
            :health-signal="clientHealth"
        />

        <ClientHealthSummary :client="client" :health-signal="clientHealth" />

        <AiSetupPrompt v-if="showAiSetup" compact />

        <section class="grid gap-8 xl:grid-cols-[0.85fr_1.15fr]">
            <div class="space-y-6">
                <section
                    class="rounded-[28px] border border-stone-300/70 bg-white/90 p-4 shadow-[0_18px_45px_-35px_rgba(28,25,23,0.28)]"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-200/80 pb-3"
                    >
                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                            >
                                Legacy variable reference
                            </p>
                            <p class="mt-1 text-sm leading-6 text-stone-600">
                                Imported letters still carry source-system
                                bracket fields. Keep this as the mapping helper
                                instead of a floating legend.
                            </p>
                        </div>
                        <p
                            v-if="copiedMergeToken"
                            class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-medium tracking-[0.18em] text-emerald-700 uppercase"
                        >
                            Copied {{ copiedMergeToken }}
                        </p>
                    </div>
                    <div class="mt-4 space-y-3">
                        <details
                            v-for="group in mergeFieldGroups"
                            :key="group.key"
                            class="rounded-[22px] border border-stone-200/80 bg-stone-50/80 px-4 py-3"
                            :open="group.key === 'client'"
                        >
                            <summary
                                class="cursor-pointer list-none text-sm font-semibold text-stone-950"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span>{{ group.label }}</span>
                                    <span
                                        class="text-[10px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >{{ group.fields.length }} fields</span
                                    >
                                </div>
                            </summary>
                            <p class="mt-2 text-sm leading-6 text-stone-600">
                                {{ group.description }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    v-for="field in group.fields"
                                    :key="field"
                                    type="button"
                                    class="rounded-full border border-stone-300/80 bg-white px-3 py-1.5 text-[11px] font-medium tracking-[0.02em] text-stone-700 transition hover:border-stone-500 hover:text-stone-950"
                                    @click="copyMergeToken(field)"
                                >
                                    {{ field }}
                                </button>
                            </div>
                        </details>
                    </div>
                </section>

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
                                    AI draft lane
                                </p>
                                <p class="text-sm text-stone-600">
                                    Generate a review-ready client letter in
                                    first person.
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
                            v-model="aiForm.letter_type"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="dispute">Dispute</option>
                            <option value="follow_up">Follow up</option>
                            <option value="escalation">Escalation</option>
                        </select>
                        <select
                            v-model="aiForm.template_key"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option
                                v-for="template in aiTemplateOptions"
                                :key="template.key"
                                :value="template.key"
                            >
                                {{ template.label }} · v{{ template.version }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <Input
                            v-model="aiForm.legal_basis"
                            placeholder="Comma separated legal basis"
                        />
                        <p
                            class="rounded-2xl border border-stone-200/70 bg-white px-3 py-2 text-sm text-stone-600"
                        >
                            {{
                                templateLookup[aiForm.template_key]
                                    ?.description ??
                                templateLookup[aiForm.template_key]
                                    ?.operator_notes ??
                                'Select a template to anchor the draft.'
                            }}
                        </p>
                    </div>
                    <Textarea
                        v-model="aiForm.operator_focus"
                        placeholder="Optional: tell the AI which violations or narrative to emphasize."
                    />
                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            class="rounded-full bg-amber-400 px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-950 uppercase transition hover:bg-amber-300"
                        >
                            Generate AI draft
                        </button>
                        <button
                            type="button"
                            class="rounded-full border border-stone-300 px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-700 uppercase transition hover:border-stone-500"
                            @click="showAiSetup = true"
                        >
                            AI settings
                        </button>
                    </div>
                </form>

                <form class="space-y-3" @submit.prevent="submit">
                    <div class="border-b border-stone-300/70 pb-3">
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            Manual draft letter
                        </p>
                        <p class="text-sm text-stone-600">
                            Use this when you want to edit or write the client
                            letter directly.
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
                        <Input
                            v-model="form.title"
                            placeholder="Capital One dispute letter"
                        />
                        <select
                            v-model="form.letter_type"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="dispute">Dispute</option>
                            <option value="follow_up">Follow up</option>
                            <option value="escalation">Escalation</option>
                        </select>
                    </div>
                    <select
                        v-model="form.template_key"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option
                            v-for="template in manualTemplateOptions"
                            :key="template.key"
                            :value="template.key"
                        >
                            {{ template.label }} · v{{ template.version }}
                        </option>
                    </select>
                    <div
                        v-if="selectedManualTemplate"
                        class="rounded-[18px] border border-stone-200/80 bg-white px-4 py-3 text-sm text-stone-600"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <p class="font-medium text-stone-900">
                                    {{ selectedManualTemplate.label }}
                                </p>
                                <p
                                    class="mt-1 text-xs tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    {{
                                        selectedManualTemplate.source_label ??
                                        'CreditSoft core'
                                    }}
                                </p>
                            </div>
                            <button
                                v-if="selectedManualTemplateHasBody"
                                type="button"
                                class="rounded-full border border-stone-300 bg-white px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] text-stone-700 uppercase"
                                @click="loadTemplateBody"
                            >
                                Load body
                            </button>
                        </div>
                        <p class="mt-2 leading-6">
                            {{
                                selectedManualTemplate.description ??
                                selectedManualTemplate.operator_notes ??
                                selectedManualTemplate.ai_focus ??
                                'No description saved for this template yet.'
                            }}
                        </p>
                    </div>
                    <Input
                        v-model="form.legal_basis"
                        placeholder="Comma separated legal basis"
                    />
                    <Textarea
                        v-model="form.content"
                        placeholder="Draft the actual letter body here."
                    />
                    <button
                        type="submit"
                        class="rounded-full bg-stone-950 px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-50 uppercase transition hover:bg-stone-800"
                    >
                        Save draft
                    </button>
                </form>
            </div>

            <section class="space-y-3">
                <section
                    class="rounded-[28px] border border-stone-300/70 bg-stone-50/75 p-4"
                >
                    <div class="border-b border-stone-300/70 pb-3">
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            Template workflow
                        </p>
                        <p class="text-sm text-stone-600">
                            Show the best next letters first, keep the rest
                            available, and explain what was hidden from this
                            file.
                        </p>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="signal in visibleSignalSummary"
                            :key="signal"
                            class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-medium tracking-[0.18em] text-emerald-700 uppercase"
                        >
                            {{ signal }}
                        </span>
                        <span
                            v-if="visibleSignalSummary.length === 0"
                            class="rounded-full border border-stone-300 bg-white px-3 py-1 text-[11px] font-medium tracking-[0.18em] text-stone-600 uppercase"
                        >
                            No issue-specific report signals detected yet
                        </span>
                    </div>

                    <div
                        class="mt-4 rounded-[24px] border border-amber-200/80 bg-amber-50/85 p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p
                                    class="text-[11px] font-medium tracking-[0.28em] text-amber-800 uppercase"
                                >
                                    Best next
                                </p>
                                <p class="mt-1 text-sm text-stone-700">
                                    These fit the imported file best right now.
                                </p>
                            </div>
                            <span
                                class="rounded-full border border-amber-200 bg-white/80 px-3 py-1 text-[10px] font-medium tracking-[0.18em] text-amber-700 uppercase"
                            >
                                {{ recommendedTemplates.length }} shown
                            </span>
                        </div>
                        <div
                            v-if="recommendedTemplates.length"
                            class="mt-4 space-y-3"
                        >
                            <div
                                v-for="template in recommendedTemplates"
                                :key="template.key"
                                class="rounded-[20px] border border-amber-200/80 bg-white/90 px-4 py-3"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-stone-950"
                                        >
                                            {{ template.label }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs tracking-[0.18em] text-stone-500 uppercase"
                                        >
                                            {{
                                                template.source_label ??
                                                'CreditSoft core'
                                            }}
                                            ·
                                            {{
                                                prettifyLetterType(
                                                    template.letter_type,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="rounded-full border border-stone-300 bg-white px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] text-stone-700 uppercase"
                                            @click="
                                                applyTemplateToManual(template)
                                            "
                                        >
                                            Use in manual
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-full border border-amber-300 bg-amber-100 px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] text-amber-900 uppercase"
                                            @click="applyTemplateToAi(template)"
                                        >
                                            Use in AI
                                        </button>
                                    </div>
                                </div>
                                <p
                                    class="mt-2 text-sm leading-6 text-stone-600"
                                >
                                    {{
                                        template.recommendation_reason ??
                                        template.description ??
                                        template.operator_notes ??
                                        template.ai_focus ??
                                        'Fits this file right now.'
                                    }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-sm leading-6 text-stone-600">
                            No strong next-letter recommendation yet. The page
                            will fall back to the broader available library
                            until more report context is imported.
                        </p>
                    </div>

                    <details
                        class="mt-4 rounded-[24px] border border-stone-200/80 bg-white/90 p-4"
                    >
                        <summary class="cursor-pointer list-none">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                    >
                                        Still available
                                    </p>
                                    <p class="mt-1 text-sm text-stone-600">
                                        Templates that still fit this file, but
                                        are not in the first recommended lane.
                                    </p>
                                </div>
                                <span
                                    class="rounded-full border border-stone-300 bg-stone-50 px-3 py-1 text-[10px] font-medium tracking-[0.18em] text-stone-600 uppercase"
                                >
                                    {{ stillAvailableTemplates.length }}
                                </span>
                            </div>
                        </summary>
                        <div
                            v-if="stillAvailableTemplates.length"
                            class="mt-4 grid gap-3 md:grid-cols-2"
                        >
                            <div
                                v-for="template in stillAvailableTemplates"
                                :key="template.key"
                                class="rounded-[18px] border border-stone-200/80 bg-stone-50/80 px-4 py-3"
                            >
                                <p class="text-sm font-semibold text-stone-950">
                                    {{ template.label }}
                                </p>
                                <p
                                    class="mt-1 text-xs tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    {{
                                        template.source_label ??
                                        'CreditSoft core'
                                    }}
                                    ·
                                    {{
                                        prettifyLetterType(template.letter_type)
                                    }}
                                </p>
                                <p
                                    class="mt-2 text-sm leading-6 text-stone-600"
                                >
                                    {{
                                        template.description ??
                                        template.operator_notes ??
                                        template.ai_focus ??
                                        'Still available for this file.'
                                    }}
                                </p>
                            </div>
                        </div>
                    </details>

                    <details
                        class="mt-4 rounded-[24px] border border-stone-200/80 bg-white/90 p-4"
                    >
                        <summary class="cursor-pointer list-none">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                    >
                                        Hidden for this file
                                    </p>
                                    <p class="mt-1 text-sm text-stone-600">
                                        Issue-specific templates removed because
                                        the imported report does not support
                                        them.
                                    </p>
                                </div>
                                <span
                                    class="rounded-full border border-stone-300 bg-stone-50 px-3 py-1 text-[10px] font-medium tracking-[0.18em] text-stone-600 uppercase"
                                >
                                    {{ hiddenTemplates.length }}
                                </span>
                            </div>
                        </summary>
                        <div
                            v-if="hiddenTemplates.length"
                            class="mt-4 grid gap-3 md:grid-cols-2"
                        >
                            <div
                                v-for="template in hiddenTemplates"
                                :key="template.key"
                                class="rounded-[18px] border border-stone-200/80 bg-stone-50/80 px-4 py-3"
                            >
                                <p class="text-sm font-semibold text-stone-950">
                                    {{ template.label }}
                                </p>
                                <p
                                    class="mt-1 text-xs tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    {{
                                        template.source_label ??
                                        'CreditSoft core'
                                    }}
                                    ·
                                    {{
                                        prettifyLetterType(template.letter_type)
                                    }}
                                </p>
                                <p
                                    class="mt-2 text-sm leading-6 text-stone-600"
                                >
                                    {{
                                        template.availability_reason ??
                                        'Hidden because it does not fit this file.'
                                    }}
                                </p>
                            </div>
                        </div>
                    </details>
                </section>

                <div class="border-b border-stone-300/70 pb-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Review-ready letters
                    </p>
                    <p class="text-sm text-stone-600">
                        Letters appear here as client-facing drafts, ready for
                        review, approval, and PDF export.
                    </p>
                </div>
                <div class="space-y-3">
                    <div
                        v-for="letter in letters"
                        :key="letter.id"
                        class="rounded-[24px] border border-stone-300/70 bg-stone-50/70 p-4"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-medium text-stone-950">
                                    {{ letter.title }}
                                </p>
                                <p
                                    v-if="letter.reporting_cycle"
                                    class="mt-1 text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    {{ letter.reporting_cycle }}
                                </p>
                            </div>
                            <div
                                class="flex flex-wrap items-center justify-end gap-2"
                            >
                                <span
                                    class="rounded-full border border-stone-300/80 bg-white px-2.5 py-1 text-[11px] tracking-[0.18em] text-stone-600 uppercase"
                                >
                                    {{ prettifyLetterType(letter.letter_type) }}
                                </span>
                                <span
                                    v-if="letter.recipient_bureau"
                                    class="rounded-full border border-stone-300/80 bg-white px-2.5 py-1 text-[11px] tracking-[0.18em] text-stone-600 uppercase"
                                >
                                    {{
                                        prettifyBureau(letter.recipient_bureau)
                                    }}
                                </span>
                                <span
                                    class="rounded-full border border-stone-300 px-2.5 py-1 text-[11px] tracking-[0.18em] text-stone-600 uppercase"
                                >
                                    {{ prettifyStatus(letter.status) }}
                                </span>
                            </div>
                        </div>
                        <div
                            class="mt-4 rounded-[28px] border border-stone-200/80 bg-white px-6 py-6 shadow-[0_18px_45px_-35px_rgba(28,25,23,0.45)]"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-4 border-b border-stone-200/80 pb-4"
                            >
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                    >
                                        Outgoing letter draft
                                    </p>
                                    <p
                                        class="mt-1 text-lg font-semibold text-stone-950"
                                    >
                                        {{ letter.title }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p
                                        class="text-[11px] tracking-[0.22em] text-stone-400 uppercase"
                                    >
                                        {{ client.display_name }}
                                    </p>
                                    <p
                                        v-if="letter.generated_by_ai"
                                        class="mt-1 text-[11px] tracking-[0.18em] text-amber-700 uppercase"
                                    >
                                        AI draft
                                    </p>
                                </div>
                            </div>
                            <div
                                class="mt-5 font-serif text-[15px] leading-7 whitespace-pre-line text-stone-800"
                            >
                                {{ letter.content }}
                            </div>
                        </div>
                        <div
                            v-if="letter.pdf_document"
                            class="mt-4 rounded-[20px] border border-stone-200/80 bg-white/90 px-3 py-2 text-sm text-stone-600"
                        >
                            <p
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                            >
                                PDF copy
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <span>{{ letter.pdf_document.file_name }}</span>
                                <button
                                    type="button"
                                    class="rounded-full border border-stone-300 px-3 py-1.5 text-[11px] tracking-[0.18em] text-stone-700 uppercase transition hover:border-stone-500"
                                    @click="
                                        openDocumentPreview(letter.pdf_document)
                                    "
                                >
                                    Open PDF
                                </button>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-full border border-stone-300 px-3 py-1.5 text-[11px] tracking-[0.18em] text-stone-600 uppercase"
                                @click="setStatus(letter.id, 'approved')"
                            >
                                Mark approved
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-stone-300 px-3 py-1.5 text-[11px] tracking-[0.18em] text-stone-600 uppercase"
                                @click="setStatus(letter.id, 'exported')"
                            >
                                {{
                                    letter.pdf_document
                                        ? 'Refresh PDF'
                                        : 'Export PDF'
                                }}
                            </button>
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
