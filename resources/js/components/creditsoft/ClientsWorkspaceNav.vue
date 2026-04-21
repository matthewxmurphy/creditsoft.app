<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faFileImport,
    faGear,
    faUsers,
} from '@fortawesome/free-solid-svg-icons';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const props = withDefaults(
    defineProps<{
        mode?: 'index' | 'import';
    }>(),
    {
        mode: 'index',
    },
);
</script>

<template>
    <div class="flex justify-end">
        <DropdownMenu>
            <DropdownMenuTrigger :as-child="true">
                <button
                    type="button"
                    class="inline-flex items-center justify-center px-1 text-stone-500 transition hover:text-stone-900 focus-visible:outline-none data-[state=open]:text-stone-900"
                    aria-label="Client workspace actions"
                >
                    <FontAwesomeIcon
                        :icon="faGear"
                        class="text-[1.5rem] leading-none"
                    />
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="end"
                class="w-72 rounded-2xl border border-stone-200 bg-white p-2 shadow-xl"
            >
                <DropdownMenuItem
                    v-if="mode === 'import'"
                    :as-child="true"
                    class="rounded-xl px-3 py-3"
                >
                    <Link
                        href="/clients"
                        class="flex w-full items-start gap-3 text-left"
                    >
                        <FontAwesomeIcon
                            :icon="faUsers"
                            class="mt-0.5 text-sm text-stone-500"
                        />
                        <span class="block">
                            <span
                                class="block text-sm font-medium text-stone-900"
                                >Client roster</span
                            >
                            <span class="block text-xs leading-5 text-stone-500"
                                >Jump back to the live dossier roster and local
                                intake lane.</span
                            >
                        </span>
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem
                    v-else
                    :as-child="true"
                    class="rounded-xl px-3 py-3"
                >
                    <Link
                        href="/clients/import"
                        class="flex w-full items-start gap-3 text-left"
                    >
                        <FontAwesomeIcon
                            :icon="faFileImport"
                            class="mt-0.5 text-sm text-stone-500"
                        />
                        <span class="block">
                            <span
                                class="block text-sm font-medium text-stone-900"
                                >Client import</span
                            >
                            <span class="block text-xs leading-5 text-stone-500"
                                >Bring in a client export, review the mapping,
                                and keep migration work out of the roster
                                page.</span
                            >
                        </span>
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
