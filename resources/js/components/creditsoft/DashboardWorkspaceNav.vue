<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faChartColumn,
    faCreditCard,
    faGear,
    faShareNodes,
    faSatelliteDish,
} from '@fortawesome/free-solid-svg-icons';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
</script>

<template>
    <div class="flex justify-end">
        <DropdownMenu>
            <DropdownMenuTrigger :as-child="true">
                <button
                    type="button"
                    class="inline-flex items-center justify-center px-1 text-stone-500 transition hover:text-stone-900 focus-visible:outline-none data-[state=open]:text-stone-900"
                    aria-label="Office actions"
                >
                    <FontAwesomeIcon :icon="faGear" class="text-[1.5rem] leading-none" />
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" class="w-72 rounded-2xl border border-stone-200 bg-white p-2 shadow-xl">
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <Link href="/billing" class="flex w-full items-start gap-3 text-left">
                        <FontAwesomeIcon :icon="faCreditCard" class="mt-0.5 text-sm text-stone-500" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Billing and revenue</span>
                            <span class="block text-xs leading-5 text-stone-500">Update gateway status, recurring billing, and real payment history.</span>
                        </span>
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <Link href="/cfo" class="flex w-full items-start gap-3 text-left">
                        <FontAwesomeIcon :icon="faChartColumn" class="mt-0.5 text-sm text-stone-500" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Open CFO</span>
                            <span class="block text-xs leading-5 text-stone-500">Jump into the finance workspace for trends, projections, and reconciliations.</span>
                        </span>
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <Link href="/settings/connectivity" class="flex w-full items-start gap-3 text-left">
                        <FontAwesomeIcon :icon="faSatelliteDish" class="mt-0.5 text-sm text-stone-500" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Connectivity</span>
                            <span class="block text-xs leading-5 text-stone-500">Manage Tailscale, ngrok, backups, and external access lanes tied to office operations.</span>
                        </span>
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <Link href="/settings/growth" class="flex w-full items-start gap-3 text-left">
                        <FontAwesomeIcon :icon="faShareNodes" class="mt-0.5 text-sm text-stone-500" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Growth and signup</span>
                            <span class="block text-xs leading-5 text-stone-500">Manage company profile, credit settings, signup flow, booking links, affiliates, and activity history in one lane.</span>
                        </span>
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
