<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faArrowUpRightFromSquare, faRotateRight, faTriangleExclamation } from '@fortawesome/free-solid-svg-icons';

const props = defineProps<{
    launchUrl: string;
    fallback: boolean;
}>();

const reloadFrame = () => {
    const frame = document.getElementById('creditsoft-crm-frame') as HTMLIFrameElement | null;

    if (frame) {
        frame.src = props.launchUrl;
    }
};
</script>

<template>
    <Head title="CRM" />

    <section class="flex min-h-[calc(100vh-5.5rem)] flex-col overflow-hidden rounded-lg border border-stone-300/70 bg-white/95">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-200 px-4 py-3">
            <div>
                <p class="text-[11px] font-semibold tracking-[0.22em] text-stone-500 uppercase">CreditSoft CRM</p>
                <p v-if="fallback" class="mt-1 flex items-center gap-2 text-sm text-amber-800">
                    <FontAwesomeIcon :icon="faTriangleExclamation" class="text-amber-600" />
                    Login handoff fell back to the CRM home screen.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-stone-300 bg-white px-3 text-sm font-semibold text-stone-800 transition hover:bg-stone-50"
                    @click="reloadFrame"
                >
                    <FontAwesomeIcon :icon="faRotateRight" class="text-xs" />
                    Reload
                </button>
                <a
                    :href="launchUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-stone-950 px-3 text-sm font-semibold text-white transition hover:bg-stone-800"
                >
                    <FontAwesomeIcon :icon="faArrowUpRightFromSquare" class="text-xs" />
                    Browser
                </a>
            </div>
        </div>

        <iframe
            id="creditsoft-crm-frame"
            :src="launchUrl"
            title="CreditSoft CRM"
            referrerpolicy="no-referrer"
            class="min-h-[720px] flex-1 border-0 bg-white"
        />
    </section>
</template>
