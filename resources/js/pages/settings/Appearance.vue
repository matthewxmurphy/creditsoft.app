<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import ReviewSignalLabel from '@/components/creditsoft/ReviewSignalLabel.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/appearance';

type ReviewLabelStyle = {
    key: string;
    number: number;
    label: string;
    description: string;
    recommended?: boolean;
};

const props = defineProps<{
    reviewLabelStyle: string;
    reviewLabelStyles: ReviewLabelStyle[];
    canEditReviewLabelStyle: boolean;
    galleryUrl: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});

const form = useForm({
    review_label_style: props.reviewLabelStyle,
});

const hasChanges = computed(() => form.review_label_style !== props.reviewLabelStyle);
const currentStyle = computed(() =>
    props.reviewLabelStyles.find((style) => style.key === form.review_label_style)
    ?? props.reviewLabelStyles[0],
);

const sampleSignals = [
    {
        kind: 'negative' as const,
        label: 'Negative reporting',
        title: 'Derogatory or negative reporting is present.',
    },
    {
        kind: 'missing' as const,
        label: 'Missing bureau',
        title: 'A bureau is missing this tradeline.',
    },
    {
        kind: 'mismatch' as const,
        label: 'Bureau mismatch',
        title: 'The values do not align across bureaus.',
    },
];

const saveReviewLabelStyle = () => {
    if (!props.canEditReviewLabelStyle) {
        return;
    }

    form.put('/settings/appearance', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Appearance Settings" />

    <h1 class="sr-only">Appearance settings</h1>

    <div class="space-y-8">
        <section class="overflow-hidden rounded-[32px] border border-stone-300/70 bg-white/95">
            <div class="grid gap-0 xl:grid-cols-[minmax(0,1.2fr)_340px]">
                <div class="space-y-8 px-6 py-6 lg:px-8 lg:py-8">
                    <div class="space-y-4">
                        <p class="text-[11px] font-medium uppercase tracking-[0.3em] text-stone-500">Appearance control room</p>
                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-end">
                            <div class="space-y-3">
                                <h1 class="text-3xl font-semibold tracking-[-0.03em] text-stone-950 md:text-4xl">
                                    Tune the way CreditSoft flags Metro 2 review.
                                </h1>
                                <p class="max-w-2xl text-sm leading-7 text-stone-600">
                                    Theme stays local to this browser. Review labels are an intranet-wide choice, so the owner can make the review surface feel sharper without changing what the labels actually mean.
                                </p>
                            </div>

                            <div class="rounded-[24px] border border-stone-300/70 bg-stone-50/80 px-5 py-4">
                                <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">Current production style</p>
                                <p class="mt-2 text-lg font-semibold text-stone-950">{{ currentStyle?.label }}</p>
                                <p class="mt-1 text-sm leading-6 text-stone-600">{{ currentStyle?.description }}</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <ReviewSignalLabel
                                        v-for="signal in sampleSignals"
                                        :key="`current-${signal.kind}`"
                                        :kind="signal.kind"
                                        :label="signal.label"
                                        :title="signal.title"
                                        :style-key="form.review_label_style"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 border-t border-stone-200/80 pt-6 lg:grid-cols-[minmax(0,1.25fr)_minmax(280px,0.75fr)]">
                        <div class="space-y-4">
                            <Heading
                                variant="small"
                                title="Theme preview"
                                description="This browser only. Use the tabs to set light, dark, or system mode for your own machine."
                            />
                            <div class="rounded-[24px] border border-stone-300/70 bg-stone-50/60 p-4">
                                <AppearanceTabs />
                            </div>
                        </div>

                        <div class="space-y-4">
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Reference</p>
                            <div class="space-y-3 rounded-[24px] border border-stone-300/70 bg-stone-950 px-5 py-5 text-stone-50">
                                <p class="text-base font-semibold">Style gallery</p>
                                <p class="text-sm leading-6 text-stone-300">
                                    Open the raw gallery if you want the numbered lab page while choosing an intranet default.
                                </p>
                                <div class="flex flex-wrap gap-3">
                                    <a
                                        :href="galleryUrl"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="inline-flex items-center justify-center rounded-full border border-stone-700 bg-stone-900 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-100 transition hover:border-amber-300 hover:text-amber-200"
                                    >
                                        Open style gallery
                                    </a>
                                    <span class="inline-flex items-center rounded-full border border-stone-700 px-4 py-2 text-[11px] font-medium uppercase tracking-[0.22em] text-stone-400">
                                        12 numbered options
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="border-t border-stone-200/80 bg-[linear-gradient(180deg,rgba(251,246,236,0.96)_0%,rgba(246,239,227,0.92)_100%)] px-6 py-6 xl:border-l xl:border-t-0">
                    <div class="space-y-4 xl:sticky xl:top-6">
                        <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Owner note</p>
                        <div class="space-y-3">
                            <p class="text-lg font-semibold text-stone-950">One choice, every dossier.</p>
                            <p class="text-sm leading-6 text-stone-600">
                                The labels still say what they mean. Icons only help scanning speed, so no extra legend has to sit on the live client page.
                            </p>
                        </div>

                        <div class="space-y-3 rounded-[24px] border border-stone-300/70 bg-white/85 p-4">
                            <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">What changes</p>
                            <ul class="space-y-2 text-sm leading-6 text-stone-700">
                                <li>Smaller review markers across SmartCredit import surfaces.</li>
                                <li>Hover text still explains the icon meaning where needed.</li>
                                <li>Theme remains your browser preference, not the office default.</li>
                            </ul>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-stone-300/70 pt-4">
                            <p class="text-sm text-stone-600">
                                {{ canEditReviewLabelStyle ? 'Saving here updates the dossier review labels across the intranet.' : 'You can preview the options here, but only owner and admin accounts can change the office default.' }}
                            </p>
                            <Button
                                type="button"
                                class="w-full"
                                :disabled="!canEditReviewLabelStyle || form.processing || !hasChanges"
                                @click="saveReviewLabelStyle"
                            >
                                Save review style
                            </Button>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section class="space-y-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-2">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Label gallery</p>
                    <h2 class="text-2xl font-semibold tracking-[-0.02em] text-stone-950">Choose the review language that fits your office</h2>
                    <p class="max-w-3xl text-sm leading-7 text-stone-600">
                        These stay text-first, use icons only to speed up scanning, and are scaled down from the older pill-heavy treatment.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">
                    <span>Selected option {{ currentStyle?.number }}</span>
                    <span v-if="currentStyle?.recommended" class="text-amber-800">Recommended</span>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                <button
                    v-for="style in reviewLabelStyles"
                    :key="style.key"
                    type="button"
                    class="group text-left transition"
                    :disabled="!canEditReviewLabelStyle"
                    @click="form.review_label_style = style.key"
                >
                    <div
                        class="h-full rounded-[26px] border px-5 py-5 transition"
                        :class="form.review_label_style === style.key ? 'border-stone-950 bg-white shadow-[0_18px_44px_rgba(31,26,23,0.09)]' : 'border-stone-300/70 bg-white/75 hover:border-stone-500 hover:bg-white'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">
                                    Option {{ style.number }}
                                </p>
                                <h3 class="text-base font-semibold text-stone-950">{{ style.label }}</h3>
                            </div>

                            <span
                                class="inline-flex min-w-[32px] items-center justify-center rounded-full border px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.18em]"
                                :class="form.review_label_style === style.key ? 'border-stone-950 bg-stone-950 text-white' : 'border-stone-300 bg-stone-50 text-stone-600'"
                            >
                                {{ style.number }}
                            </span>
                        </div>

                        <p class="mt-3 text-sm leading-6 text-stone-600">{{ style.description }}</p>

                        <div class="mt-5 rounded-[22px] border border-stone-200/80 bg-stone-50/80 p-4">
                            <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">Sample row</p>
                            <p class="mt-2 text-sm font-semibold text-stone-950">Capital One Bank</p>
                            <p class="mt-1 text-sm text-stone-500">Charge-off · 45% utilization · needs review</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <ReviewSignalLabel
                                    v-for="signal in sampleSignals"
                                    :key="`${style.key}-${signal.kind}`"
                                    :kind="signal.kind"
                                    :label="signal.label"
                                    :title="signal.title"
                                    :style-key="style.key"
                                />
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <span
                                v-if="style.recommended"
                                class="inline-flex rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-amber-900"
                            >
                                Recommended
                            </span>
                            <span v-else class="text-[11px] font-medium uppercase tracking-[0.18em] text-stone-400">Preview only</span>

                            <span
                                class="text-[11px] font-semibold uppercase tracking-[0.2em]"
                                :class="form.review_label_style === style.key ? 'text-stone-950' : 'text-stone-500 group-hover:text-stone-900'"
                            >
                                {{ form.review_label_style === style.key ? 'Selected' : 'Use this style' }}
                            </span>
                        </div>
                    </div>
                </button>
            </div>
        </section>
    </div>
</template>
