<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faBullseye,
    faCalendarDays,
    faGear,
    faRectangleAd,
    faShareNodes,
    faTrophy,
} from '@fortawesome/free-solid-svg-icons';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import SocialPlatformMark from '@/components/creditsoft/SocialPlatformMark.vue';
</script>

<template>
    <div class="flex justify-end">
        <DropdownMenu>
            <DropdownMenuTrigger :as-child="true">
                <button
                    type="button"
                    class="inline-flex items-center justify-center px-1 text-stone-500 transition hover:text-stone-900 focus-visible:outline-none data-[state=open]:text-stone-900"
                    aria-label="Social settings"
                >
                    <FontAwesomeIcon :icon="faGear" class="text-[1.5rem] leading-none" />
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" class="w-72 rounded-2xl border border-stone-200 bg-white p-2 shadow-xl">
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <a href="/calendar/social" class="flex w-full items-start gap-3 text-left">
                        <SocialPlatformMark brand="meta" compact monochrome class="mt-0.5 text-[#0866ff]" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Social / Meta workspace</span>
                            <span class="block text-xs leading-5 text-stone-500">Open the main rail page for content, publishing, and action lanes.</span>
                        </span>
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <a href="/calendar" class="flex w-full items-start gap-3 text-left">
                        <FontAwesomeIcon :icon="faCalendarDays" class="mt-0.5 text-sm text-stone-500" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Office calendar</span>
                            <span class="block text-xs leading-5 text-stone-500">Open scheduled consults, follow-ups, and staff calendar visibility.</span>
                        </span>
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <a href="/settings/social/readiness" class="flex w-full items-start gap-3 text-left">
                        <FontAwesomeIcon :icon="faBullseye" class="mt-0.5 text-sm text-stone-500" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Meta readiness</span>
                            <span class="block text-xs leading-5 text-stone-500">See blockers, callback status, next moves, and paste-ready Meta dashboard values.</span>
                        </span>
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <a href="/settings/social/facebook" class="flex w-full items-start gap-3 text-left">
                        <SocialPlatformMark brand="meta" compact monochrome class="mt-0.5 text-[#0866ff]" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Meta business</span>
                            <span class="block text-xs leading-5 text-stone-500">Jump to the app, business, and callback settings.</span>
                        </span>
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <a href="/settings/social/whatsapp" class="flex w-full items-start gap-3 text-left">
                        <SocialPlatformMark brand="whatsapp" compact monochrome class="mt-0.5 text-[#25d366]" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">WhatsApp lane</span>
                            <span class="block text-xs leading-5 text-stone-500">Open the direct-message setup and follow-up lane.</span>
                        </span>
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <a href="/settings/social/instagram" class="flex w-full items-start gap-3 text-left">
                        <SocialPlatformMark brand="instagram" compact monochrome class="mt-0.5 text-[#e4405f]" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Instagram lane</span>
                            <span class="block text-xs leading-5 text-stone-500">Open the professional account, draft, and publishing-readiness setup.</span>
                        </span>
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <a href="/settings/social/threads" class="flex w-full items-start gap-3 text-left">
                        <SocialPlatformMark brand="threads" compact monochrome class="mt-0.5 text-stone-950" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Threads lane</span>
                            <span class="block text-xs leading-5 text-stone-500">Save the profile, manual workflow, and future Threads API setup.</span>
                        </span>
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <a href="/settings/social/publishing" class="flex w-full items-start gap-3 text-left">
                        <FontAwesomeIcon :icon="faShareNodes" class="mt-0.5 text-sm text-stone-500" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Publishing</span>
                            <span class="block text-xs leading-5 text-stone-500">Jump to Page posting, cadence, and approval controls.</span>
                        </span>
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <a href="/settings/social/creator-challenge" class="flex w-full items-start gap-3 text-left">
                        <FontAwesomeIcon :icon="faTrophy" class="mt-0.5 text-sm text-stone-500" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Creator challenge</span>
                            <span class="block text-xs leading-5 text-stone-500">Open the scoring model, weekly goals, and winner settings.</span>
                        </span>
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <a href="/settings/social/ads" class="flex w-full items-start gap-3 text-left">
                        <FontAwesomeIcon :icon="faRectangleAd" class="mt-0.5 text-sm text-stone-500" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Ads and leads</span>
                            <span class="block text-xs leading-5 text-stone-500">Open the ad account, budget, and lead destination lane.</span>
                        </span>
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem :as-child="true" class="rounded-xl px-3 py-3">
                    <a href="/settings/social" class="flex w-full items-start gap-3 text-left">
                        <FontAwesomeIcon :icon="faGear" class="mt-0.5 text-sm text-stone-500" />
                        <span class="block">
                            <span class="block text-sm font-medium text-stone-900">Social settings</span>
                            <span class="block text-xs leading-5 text-stone-500">Jump to the saved Meta, creator challenge, publishing, ads, and WhatsApp controls.</span>
                        </span>
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
