<script setup lang="ts">
withDefaults(defineProps<{
    alt?: string;
    className?: string;
    loading?: 'eager' | 'lazy';
}>(), {
    alt: 'CreditSoft',
    className: '',
    loading: 'eager',
});
</script>

<template>
    <img
        src="/branding/creditsoft-wordmark.png"
        :alt="alt"
        :class="className"
        :loading="loading"
        decoding="async"
    >
</template>
