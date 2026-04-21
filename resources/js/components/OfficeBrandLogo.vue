<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CreditsoftWordmark from '@/components/CreditsoftWordmark.vue';

const props = withDefaults(
    defineProps<{
        className?: string;
        fallbackClassName?: string;
        loading?: 'eager' | 'lazy';
    }>(),
    {
        className: '',
        fallbackClassName: '',
        loading: 'eager',
    },
);

const page = usePage();

const branding = computed(
    () =>
        (
            page.props.creditsoft as
                | {
                      branding?: {
                          company_name?: string | null;
                          logo_name?: string | null;
                          logo_url?: string | null;
                          uploaded_at?: string | null;
                      };
                  }
                | undefined
        )?.branding ?? {},
);

const logoUrl = computed(() => {
    const url = branding.value.logo_url;
    const uploadedAt = branding.value.uploaded_at;

    if (!url) {
        return null;
    }

    return uploadedAt ? `${url}?v=${encodeURIComponent(uploadedAt)}` : url;
});

const alt = computed(
    () =>
        branding.value.logo_name || branding.value.company_name || 'CreditSoft',
);
</script>

<template>
    <img
        v-if="logoUrl"
        :src="logoUrl"
        :alt="alt"
        :class="className"
        :loading="loading"
        decoding="async"
    />
    <CreditsoftWordmark
        v-else
        :class-name="fallbackClassName || className"
        :loading="loading"
    />
</template>
