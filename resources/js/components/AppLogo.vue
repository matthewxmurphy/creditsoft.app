<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import OfficeBrandLogo from '@/components/OfficeBrandLogo.vue';

const page = usePage();

const brandName = computed(
    () =>
        (
            page.props.creditsoft as
                | {
                      branding?: {
                          company_name?: string | null;
                      };
                  }
                | undefined
        )?.branding?.company_name ?? 'CreditSoft',
);
</script>

<template>
    <div class="flex items-center gap-3">
        <div
            class="flex h-10 items-center justify-center rounded-xl bg-sidebar-primary/10 px-3 text-sidebar-foreground"
        >
            <OfficeBrandLogo
                class-name="max-h-6 max-w-24 object-contain"
                fallback-class-name="h-5 w-auto"
                loading="lazy"
            />
        </div>
        <div class="grid flex-1 text-left text-sm leading-tight">
            <span class="truncate font-semibold">{{ brandName }}</span>
            <span class="truncate text-xs text-muted-foreground"
                >Local-first intranet</span
            >
        </div>
    </div>
</template>
