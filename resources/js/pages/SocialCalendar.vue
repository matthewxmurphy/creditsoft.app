<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faCalendarDays,
    faGear,
    faListCheck,
    faMessage,
    faRectangleAd,
    faShareNodes,
    faTrophy,
} from '@fortawesome/free-solid-svg-icons';
import SocialCalendarWorkspace from '@/components/creditsoft/SocialCalendarWorkspace.vue';
import SocialPlatformMark from '@/components/creditsoft/SocialPlatformMark.vue';

const props = defineProps<{
    calendar: {
        page_name: string | null;
        ad_account_name: string | null;
        default_cta: string | null;
        default_destination: string | null;
        auto_publish_releases: boolean;
        auto_publish_features: boolean;
        auto_publish_reviews: boolean;
        facebook_publishing_ready: boolean;
        instagram_business_id: string | null;
        instagram_username: string | null;
        instagram_publishing_ready: boolean;
        threads_username: string | null;
        whatsapp_ready: boolean;
    };
    workspace: {
        connection_status: string;
        publishing_enabled: boolean;
        approval_required: boolean;
        lead_destination: string | null;
        creator_challenge_enabled: boolean;
        creator_challenge_sync_status: string;
        creator_challenge_name: string;
        whatsapp_ready: boolean;
    };
}>();
</script>

<template>
    <Head title="Social Calendar" />

    <div class="space-y-6">
        <section class="overflow-hidden rounded-[30px] border border-stone-300/70 bg-[radial-gradient(circle_at_top_left,_rgba(8,102,255,0.14),_transparent_30%),linear-gradient(135deg,_rgba(255,255,255,0.98),_rgba(248,250,252,0.96))] px-6 py-6 shadow-[0_18px_48px_rgba(15,23,42,0.06)] lg:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-3">
                    <div class="flex items-center gap-4">
                        <SocialPlatformMark brand="meta" large monochrome class="text-[#0866ff] drop-shadow-[0_14px_24px_rgba(8,102,255,0.16)]" />
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Social / Meta workspace</p>
                            <h1 class="text-2xl font-semibold tracking-tight text-stone-950 sm:text-[2rem]">
                                Work the Meta lane from a real page view.
                            </h1>
                        </div>
                    </div>
                    <p class="max-w-3xl text-sm leading-7 text-stone-600">
                        This is the main rail destination for Meta work: content planning, publishing review, creator challenge tracking, reply follow-up, lead handoff, and the social media calendar. Settings stay under `/settings/social`.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        href="/settings/social"
                        class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-900 transition hover:border-stone-500 hover:text-stone-950"
                    >
                        <FontAwesomeIcon :icon="faGear" />
                        Social settings
                    </a>
                    <a
                        href="/calendar"
                        class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-900 transition hover:border-stone-500 hover:text-stone-950"
                    >
                        <FontAwesomeIcon :icon="faCalendarDays" />
                        Office calendar
                    </a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-5">
            <a href="#social-calendar" class="group rounded-[28px] border border-stone-200 bg-white px-5 py-5 transition hover:border-stone-400 hover:shadow-[0_18px_44px_rgba(15,23,42,0.07)]">
                <FontAwesomeIcon :icon="faCalendarDays" class="text-2xl text-stone-800" />
                <p class="mt-4 text-sm font-semibold text-stone-950">Content calendar</p>
                <p class="mt-2 text-sm leading-6 text-stone-600">Plan month, week, day, agenda, and meeting blocks for Meta content.</p>
                <p class="mt-4 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500 group-hover:text-stone-950">Open calendar</p>
            </a>

            <a href="/settings/social/publishing" class="group rounded-[28px] border border-stone-200 bg-white px-5 py-5 transition hover:border-stone-400 hover:shadow-[0_18px_44px_rgba(15,23,42,0.07)]">
                <FontAwesomeIcon :icon="faShareNodes" class="text-2xl text-stone-800" />
                <p class="mt-4 text-sm font-semibold text-stone-950">Publish queue</p>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    {{ props.workspace.publishing_enabled ? (props.workspace.approval_required ? 'Publishing is on with approval required.' : 'Publishing is on without approval gate.') : 'Publishing is staged until settings are enabled.' }}
                </p>
                <p class="mt-4 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500 group-hover:text-stone-950">Review controls</p>
            </a>

            <a href="/settings/social/creator-challenge" class="group rounded-[28px] border border-stone-200 bg-white px-5 py-5 transition hover:border-stone-400 hover:shadow-[0_18px_44px_rgba(15,23,42,0.07)]">
                <FontAwesomeIcon :icon="faTrophy" class="text-2xl text-stone-800" />
                <p class="mt-4 text-sm font-semibold text-stone-950">{{ props.workspace.creator_challenge_name }}</p>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    {{ props.workspace.creator_challenge_enabled ? `Challenge is on. Sync status: ${props.workspace.creator_challenge_sync_status}.` : 'Challenge scoring is staged until enabled.' }}
                </p>
                <p class="mt-4 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500 group-hover:text-stone-950">Open scoring</p>
            </a>

            <a href="/settings/social/whatsapp" class="group rounded-[28px] border border-stone-200 bg-white px-5 py-5 transition hover:border-stone-400 hover:shadow-[0_18px_44px_rgba(15,23,42,0.07)]">
                <FontAwesomeIcon :icon="faMessage" class="text-2xl text-stone-800" />
                <p class="mt-4 text-sm font-semibold text-stone-950">Replies and handoff</p>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    {{ props.workspace.whatsapp_ready ? 'WhatsApp is ready for lead follow-up and reminders.' : 'WhatsApp still needs a business number or display number.' }}
                </p>
                <p class="mt-4 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500 group-hover:text-stone-950">Open handoff</p>
            </a>

            <a href="/settings/social/ads" class="group rounded-[28px] border border-stone-200 bg-white px-5 py-5 transition hover:border-stone-400 hover:shadow-[0_18px_44px_rgba(15,23,42,0.07)]">
                <FontAwesomeIcon :icon="faRectangleAd" class="text-2xl text-stone-800" />
                <p class="mt-4 text-sm font-semibold text-stone-950">Lead ads</p>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    Lead destination is {{ String(props.workspace.lead_destination || 'website').replace(/_/g, ' ') }}.
                </p>
                <p class="mt-4 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500 group-hover:text-stone-950">Review ads</p>
            </a>
        </section>

        <section class="overflow-hidden rounded-[30px] border border-stone-300/70 bg-white/95">
            <div class="grid gap-4 px-6 py-5 md:grid-cols-3">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Connection</p>
                    <p class="mt-2 text-sm font-semibold text-stone-950">{{ props.workspace.connection_status }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Page</p>
                    <p class="mt-2 text-sm font-semibold text-stone-950">{{ props.calendar.page_name || 'No Page selected' }}</p>
                </div>
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Ad account</p>
                    <p class="mt-2 text-sm font-semibold text-stone-950">{{ props.calendar.ad_account_name || 'No ad account selected' }}</p>
                </div>
            </div>
        </section>

        <SocialCalendarWorkspace
            eyebrow="Social media calendar"
            title="Plan Meta content without crowding the settings page."
            description="Use this page for content, publishing, agenda, meetings, and AI planning. Connection and scoring controls stay under Social settings."
            settings-href="/settings/social"
            :page-name="props.calendar.page_name"
            :ad-account-name="props.calendar.ad_account_name"
            :default-cta="props.calendar.default_cta"
            :default-destination="props.calendar.default_destination"
            :auto-publish-releases="props.calendar.auto_publish_releases"
            :auto-publish-features="props.calendar.auto_publish_features"
            :auto-publish-reviews="props.calendar.auto_publish_reviews"
            :facebook-publishing-ready="props.calendar.facebook_publishing_ready"
            :instagram-business-id="props.calendar.instagram_business_id"
            :instagram-username="props.calendar.instagram_username"
            :instagram-publishing-ready="props.calendar.instagram_publishing_ready"
            :threads-username="props.calendar.threads_username"
            :whatsapp-ready="props.calendar.whatsapp_ready"
        />
    </div>
</template>
