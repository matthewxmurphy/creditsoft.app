<script setup lang="ts">
type ProviderName = 'opencode_zen' | 'openrouter_creditsoft' | 'ollama_cloud';

withDefaults(defineProps<{
    provider: ProviderName;
    centered?: boolean;
    large?: boolean;
}>(), {
    centered: false,
    large: false,
});
</script>

<template>
    <div
        class="flex items-center gap-2 text-stone-950"
        :class="centered ? 'justify-center' : ''"
    >
        <template v-if="provider === 'opencode_zen'">
            <img
                src="/branding/providers/opencode-wordmark-light.svg"
                alt="OpenCode"
                class="shrink-0 object-contain"
                :class="large ? 'h-5 w-auto' : 'h-4 w-auto'"
            />
        </template>

        <template v-else-if="provider === 'openrouter_creditsoft'">
            <img
                src="/branding/providers/openrouter.svg"
                alt="OpenRouter"
                class="shrink-0 object-contain"
                :class="large ? 'h-5 w-5' : 'h-4 w-4'"
            />
            <span
                class="font-medium tracking-tight text-stone-950"
                :class="large ? 'text-[0.98rem]' : 'text-[0.9rem]'"
            >
                OpenRouter
            </span>
        </template>

        <template v-else>
            <img
                src="/branding/providers/ollama.svg"
                alt="Ollama Cloud"
                class="shrink-0 object-contain"
                :class="large ? 'h-5 w-5' : 'h-4.5 w-4.5'"
            />
        </template>
    </div>
</template>
