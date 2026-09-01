<script setup lang="ts">
import {
    faGear,
    faFileImport,
    faSatelliteDish,
    faUserSlash,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import ClientRolodexNav from '@/components/creditsoft/ClientRolodexNav.vue';
import type { ClientHealthSignal } from '@/lib/client-health';

defineProps<{
    clientId: number;
    healthSignal?: ClientHealthSignal | string | null;
}>();

const page = usePage();

const currentSearchParams = computed(() => {
    const url = String(page.url ?? '');
    const queryIndex = url.indexOf('?');

    return new URLSearchParams(queryIndex >= 0 ? url.slice(queryIndex) : '');
});

const clientPanelHref = (clientId: number, panel: string, hash: string) => {
    const params = new URLSearchParams();
    const view = currentSearchParams.value.get('view');

    if (view) {
        params.set('view', view);
    }

    params.set('panel', panel);

    return `/clients/${clientId}?${params.toString()}#${hash}`;
};
</script>

<template>
    <div
        class="relative z-[120] flex min-w-0 flex-col gap-3 overflow-visible md:flex-row md:items-center md:justify-between"
    >
        <ClientRolodexNav :client-id="clientId" class="md:flex-1" />

        <div class="flex shrink-0 items-center justify-end gap-3">
            <DropdownMenu>
                <DropdownMenuTrigger :as-child="true">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center px-1 text-stone-500 transition hover:text-stone-900 focus-visible:outline-none data-[state=open]:text-stone-900"
                        aria-label="Client actions"
                    >
                        <FontAwesomeIcon
                            :icon="faGear"
                            class="text-[1.5rem] leading-none"
                        />
                    </button>
                </DropdownMenuTrigger>

                <DropdownMenuContent
                    align="end"
                    class="z-[240] w-72 rounded-2xl border border-stone-200 bg-white p-2 shadow-xl"
                >
                    <DropdownMenuItem
                        :as-child="true"
                        class="rounded-xl px-3 py-3"
                    >
                        <Link
                            :href="
                                clientPanelHref(
                                    clientId,
                                    'providers',
                                    'credit-monitoring-panel',
                                )
                            "
                            class="flex w-full items-start gap-3 text-left"
                        >
                            <FontAwesomeIcon
                                :icon="faSatelliteDish"
                                class="mt-0.5 text-sm text-stone-500"
                            />
                            <span class="block">
                                <span
                                    class="block text-sm font-medium text-stone-900"
                                    >Credit monitoring</span
                                >
                                <span
                                    class="block text-xs leading-5 text-stone-500"
                                    >Add or update SmartCredit, Credit Karma, or
                                    the next source.</span
                                >
                            </span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        :as-child="true"
                        class="rounded-xl px-3 py-3"
                    >
                        <Link
                            :href="
                                clientPanelHref(
                                    clientId,
                                    'import',
                                    'import-tools-panel',
                                )
                            "
                            class="flex w-full items-start gap-3 text-left"
                        >
                            <FontAwesomeIcon
                                :icon="faFileImport"
                                class="mt-0.5 text-sm text-stone-500"
                            />
                            <span class="block">
                                <span
                                    class="block text-sm font-medium text-stone-900"
                                    >Import tools</span
                                >
                                <span
                                    class="block text-xs leading-5 text-stone-500"
                                    >Safari archives, browser captures, CSV
                                    snapshots, and manual tradelines.</span
                                >
                            </span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        :as-child="true"
                        class="rounded-xl px-3 py-3"
                    >
                        <Link
                            :href="
                                clientPanelHref(
                                    clientId,
                                    'relationship',
                                    'client-relationship-panel',
                                )
                            "
                            class="flex w-full items-start gap-3 text-left"
                        >
                            <FontAwesomeIcon
                                :icon="faUserSlash"
                                class="mt-0.5 text-sm text-stone-500"
                            />
                            <span class="block">
                                <span
                                    class="block text-sm font-medium text-stone-900"
                                    >Fire / close client</span
                                >
                                <span
                                    class="block text-xs leading-5 text-stone-500"
                                    >End the relationship without deleting the
                                    dossier and record why it ended.</span
                                >
                            </span>
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </div>
</template>
