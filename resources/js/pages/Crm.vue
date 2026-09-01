<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faTriangleExclamation } from '@fortawesome/free-solid-svg-icons';
import { computed } from 'vue';

const props = defineProps<{
    launchUrl: string;
    fallback: boolean;
}>();

const crmProxyPrefix = '/__creditsoft/crm';
const localRouterPort = '8877';

const frameSrc = computed(() => {
    if (typeof window === 'undefined') {
        return props.launchUrl;
    }

    try {
        const launch = new URL(props.launchUrl);
        const current = window.location;
        const localRouterPorts = new Set(['', '80', localRouterPort]);
        const isLocalRouter = ['127.0.0.1', 'localhost'].includes(current.hostname)
            && localRouterPorts.has(current.port);
        const isDirectOfficeServer = ['127.0.0.1', 'localhost'].includes(current.hostname)
            && current.port === '8001';

        if (isLocalRouter && launch.origin !== current.origin) {
            return `${crmProxyPrefix}${launch.pathname}${launch.search}${launch.hash}`;
        }

        if (isDirectOfficeServer && launch.origin !== current.origin) {
            return `${current.protocol}//${current.hostname}:${localRouterPort}${crmProxyPrefix}${launch.pathname}${launch.search}${launch.hash}`;
        }
    } catch {
        return props.launchUrl;
    }

    return props.launchUrl;
});
</script>

<template>
    <Head title="CRM" />

    <section class="flex h-full min-h-0 flex-col overflow-hidden bg-white">
        <div v-if="fallback" class="flex min-h-10 shrink-0 flex-wrap items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-4 py-2">
            <div>
                <p class="flex items-center gap-2 text-sm text-amber-800">
                    <FontAwesomeIcon :icon="faTriangleExclamation" class="text-amber-600" />
                    Login handoff fell back to the CRM home screen.
                </p>
            </div>
        </div>

        <iframe
            id="creditsoft-crm-frame"
            :src="frameSrc"
            title="CreditSoft CRM"
            referrerpolicy="no-referrer"
            sandbox="allow-clipboard-read allow-clipboard-write allow-downloads allow-forms allow-modals allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts allow-storage-access-by-user-activation"
            class="h-full min-h-0 w-full flex-1 border-0 bg-white"
        />
    </section>
</template>
