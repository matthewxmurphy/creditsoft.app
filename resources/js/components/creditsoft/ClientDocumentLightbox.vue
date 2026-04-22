<script setup lang="ts">
import { computed } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type LightboxDocument = {
    id?: number | string;
    title?: string | null;
    file_name?: string | null;
    mime_type?: string | null;
    file_size?: number | null;
    download_url?: string | null;
};

const props = defineProps<{
    open: boolean;
    document: LightboxDocument | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const documentTitle = computed(
    () =>
        props.document?.title || props.document?.file_name || 'Client document',
);

const documentFileName = computed(
    () => props.document?.file_name || documentTitle.value,
);

const documentUrl = computed(() =>
    appendPreviewQuery(String(props.document?.download_url || '')),
);

const lowerFileName = computed(() => documentFileName.value.toLowerCase());
const lowerMimeType = computed(() =>
    String(props.document?.mime_type || '').toLowerCase(),
);

const isImage = computed(
    () =>
        lowerMimeType.value.startsWith('image/') ||
        /\.(avif|gif|jpe?g|png|webp)$/i.test(lowerFileName.value),
);

const isPdf = computed(
    () =>
        lowerMimeType.value === 'application/pdf' ||
        /\.pdf$/i.test(lowerFileName.value),
);

const canPreview = computed(() => Boolean(documentUrl.value));

const formatFileSize = (bytes?: number | null) => {
    if (!bytes || bytes < 1) {
        return null;
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size >= 10 || unitIndex === 0 ? size.toFixed(0) : size.toFixed(1)} ${units[unitIndex]}`;
};

const appendPreviewQuery = (url: string) => {
    if (!url || /[?&]preview=/.test(url)) {
        return url;
    }

    const hashIndex = url.indexOf('#');
    const base = hashIndex >= 0 ? url.slice(0, hashIndex) : url;
    const hash = hashIndex >= 0 ? url.slice(hashIndex) : '';
    const separator = base.includes('?') ? '&' : '?';

    return `${base}${separator}preview=1${hash}`;
};

const onOpenChange = (value: boolean) => {
    if (!value) {
        emit('close');
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent
            class="max-h-[92vh] gap-0 overflow-hidden rounded-[24px] border-stone-300 bg-stone-950 p-0 text-white sm:max-w-[min(1180px,calc(100vw-2rem))]"
        >
            <DialogHeader
                class="border-b border-white/10 bg-stone-950 px-5 py-4 pr-12"
            >
                <DialogTitle class="truncate text-base font-semibold">
                    {{ documentTitle }}
                </DialogTitle>
                <DialogDescription
                    class="flex flex-wrap items-center gap-2 text-xs text-stone-400"
                >
                    <span class="truncate">{{ documentFileName }}</span>
                    <span v-if="formatFileSize(document?.file_size)">
                        {{ formatFileSize(document?.file_size) }}
                    </span>
                </DialogDescription>
            </DialogHeader>

            <div class="min-h-[62vh] bg-stone-100">
                <div
                    v-if="!canPreview"
                    class="flex min-h-[62vh] items-center justify-center px-6 text-center text-stone-700"
                >
                    <div>
                        <p class="text-lg font-semibold text-stone-950">
                            No file attached yet.
                        </p>
                        <p class="mt-2 max-w-md text-sm leading-6">
                            This record has metadata, but there is not a stored
                            file to preview.
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="isImage"
                    class="flex min-h-[62vh] items-center justify-center bg-stone-950 p-4"
                >
                    <img
                        :src="documentUrl"
                        :alt="documentTitle"
                        class="max-h-[72vh] max-w-full rounded-lg object-contain shadow-2xl shadow-black/40"
                    />
                </div>

                <iframe
                    v-else
                    :src="documentUrl"
                    :title="documentTitle"
                    class="h-[72vh] w-full border-0 bg-white"
                />
            </div>

            <div
                v-if="document?.download_url"
                class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 bg-stone-950 px-5 py-3"
            >
                <p class="text-xs text-stone-400">
                    {{
                        isPdf
                            ? 'PDF preview'
                            : isImage
                              ? 'Image preview'
                              : 'File preview'
                    }}
                </p>
                <a
                    :href="document.download_url"
                    download
                    class="rounded-full border border-white/20 px-4 py-2 text-[11px] font-semibold tracking-[0.18em] text-white uppercase transition hover:border-amber-300 hover:text-amber-200"
                >
                    Download
                </a>
            </div>
        </DialogContent>
    </Dialog>
</template>
