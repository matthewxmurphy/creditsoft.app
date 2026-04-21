<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import OfficeBrandLogo from '@/components/OfficeBrandLogo.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex w-full flex-col items-center gap-3 font-medium"
                    >
                        <OfficeBrandLogo
                            class-name="h-auto max-h-16 w-[95%] max-w-[26rem] object-contain md:w-auto md:max-w-none"
                            fallback-class-name="h-auto w-[95%] max-w-[26rem] md:w-auto md:max-w-none md:h-14"
                        />
                        <span class="sr-only">CreditSoft</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
