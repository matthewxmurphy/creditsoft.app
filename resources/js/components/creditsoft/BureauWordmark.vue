<script setup lang="ts">
import { computed, useAttrs } from 'vue';

const props = defineProps<{
    bureau: 'experian' | 'transunion' | 'equifax';
}>();

const attrs = useAttrs();

const bureauMeta = computed(() => ({
    experian: {
        label: 'Experian',
        src: '/branding/bureaus/experian.svg',
    },
    transunion: {
        label: 'TransUnion',
        src: '/branding/bureaus/transunion.svg',
    },
    equifax: {
        label: 'Equifax',
        src: '/branding/bureaus/equifax.svg',
    },
}[props.bureau]));
</script>

<template>
    <img
        v-bind="attrs"
        :src="bureauMeta.src"
        :alt="bureauMeta.label"
        :title="bureauMeta.label"
    />
</template>
