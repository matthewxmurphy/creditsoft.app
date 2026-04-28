<script setup lang="ts">
withDefaults(defineProps<{
    brand: 'meta' | 'whatsapp' | 'instagram' | 'threads';
    large?: boolean;
    compact?: boolean;
    rail?: boolean;
    monochrome?: boolean;
}>(), {
    large: false,
    compact: false,
    rail: false,
    monochrome: false,
});
</script>

<template>
    <div class="flex items-center justify-center">
        <template v-if="brand === 'meta'">
            <svg
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
                class="w-auto"
                :class="compact ? 'h-[18px]' : (rail ? 'h-[18px]' : (large ? 'h-10' : 'h-6'))"
                aria-label="Meta"
                fill="none"
            >
                <path
                    d="M3.25 14.4C3.25 10.05 5.23 7.1 7.76 7.1C9.82 7.1 11.12 9.31 12 11.23C12.88 9.31 14.18 7.1 16.24 7.1C18.77 7.1 20.75 10.05 20.75 14.4C20.75 16.61 19.83 18.13 18.36 18.13C16.47 18.13 14.98 15.59 12.96 11.53L12.01 9.61L11.04 11.53C9.03 15.59 7.53 18.13 5.64 18.13C4.17 18.13 3.25 16.61 3.25 14.4Z"
                    :stroke="monochrome ? 'currentColor' : '#0866FF'"
                    stroke-width="1.85"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </template>

        <template v-else-if="brand === 'whatsapp'">
            <svg
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
                class="w-auto"
                :class="compact ? 'h-[18px]' : (rail ? 'h-[18px]' : (large ? 'h-10' : 'h-6'))"
                aria-label="WhatsApp"
                fill="none"
            >
                <path
                    d="M12 3.25C7.42 3.25 3.75 6.85 3.75 11.26C3.75 13 4.33 14.67 5.38 16.03L4.45 20.25L8.8 19.08C9.99 19.83 11.37 20.25 12.8 20.25C17.38 20.25 21.05 16.66 21.05 12.24C21.05 7.84 16.95 3.25 12 3.25Z"
                    :fill="monochrome ? 'none' : '#25D366'"
                    :stroke="monochrome ? 'currentColor' : '#25D366'"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <path
                    d="M9.4 8.98C9.73 8.74 10.06 8.8 10.28 9.09L11.28 10.45C11.48 10.72 11.47 11.09 11.22 11.31L10.74 11.73C10.56 11.88 10.51 12.14 10.62 12.35C11.17 13.42 12.07 14.3 13.17 14.82C13.38 14.92 13.63 14.87 13.78 14.69L14.17 14.24C14.4 13.98 14.79 13.96 15.07 14.17L16.39 15.14C16.68 15.36 16.74 15.68 16.52 16.01C16.04 16.71 15.35 17.06 14.61 16.95C12.28 16.6 9.21 13.59 8.47 11.21C8.25 10.48 8.65 9.75 9.4 8.98Z"
                    :fill="monochrome ? 'currentColor' : 'white'"
                />
            </svg>
        </template>

        <template v-else-if="brand === 'instagram'">
            <svg
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
                class="w-auto"
                :class="compact ? 'h-[18px]' : (rail ? 'h-[18px]' : (large ? 'h-10' : 'h-6'))"
                aria-label="Instagram"
                fill="none"
            >
                <rect
                    x="4.25"
                    y="4.25"
                    width="15.5"
                    height="15.5"
                    rx="4.4"
                    :stroke="monochrome ? 'currentColor' : '#E4405F'"
                    stroke-width="1.7"
                />
                <circle
                    cx="12"
                    cy="12"
                    r="3.55"
                    :stroke="monochrome ? 'currentColor' : '#E4405F'"
                    stroke-width="1.7"
                />
                <circle
                    cx="16.55"
                    cy="7.45"
                    r="1.05"
                    :fill="monochrome ? 'currentColor' : '#E4405F'"
                />
            </svg>
        </template>

        <template v-else>
            <svg
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
                class="w-auto"
                :class="compact ? 'h-[18px]' : (rail ? 'h-[18px]' : (large ? 'h-10' : 'h-6'))"
                aria-label="Threads"
                fill="none"
            >
                <path
                    d="M16.95 10.8C16.58 6.92 14.62 4.75 11.6 4.75C8.23 4.75 6.25 7.27 6.25 11.62C6.25 16.1 8.46 19.25 12 19.25C15.11 19.25 17.55 17.35 17.55 14.72C17.55 12.35 15.77 10.78 12.72 10.78H11.88"
                    :stroke="monochrome ? 'currentColor' : '#111111'"
                    stroke-width="1.75"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <path
                    d="M12.07 14.98C13.37 14.98 14.42 14.26 14.42 13.25C14.42 12.24 13.37 11.55 12.02 11.55C10.63 11.55 9.58 12.35 9.58 13.35C9.58 14.36 10.58 14.98 12.07 14.98Z"
                    :stroke="monochrome ? 'currentColor' : '#111111'"
                    stroke-width="1.75"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </template>
    </div>
</template>
