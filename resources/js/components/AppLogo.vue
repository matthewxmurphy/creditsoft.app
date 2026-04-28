<script setup lang="ts">
import CreditsoftWordmark from '@/components/CreditsoftWordmark.vue';
</script>

<template>
    <div class="flex items-center gap-3">
        <div
            class="flex h-10 items-center justify-center rounded-xl bg-sidebar-primary/10 px-3 text-sidebar-foreground"
        >
            <CreditsoftWordmark class-name="h-5 w-auto" loading="lazy" />
        </div>
        <div class="grid flex-1 text-left text-sm leading-tight">
            <span class="truncate font-semibold">CreditSoft</span>
            <span class="truncate text-xs text-muted-foreground">Local-first intranet</span>
        </div>
    </div>
</template>
