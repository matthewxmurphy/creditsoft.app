<script setup lang="ts">
import { computed } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import type { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
    faAddressCard,
    faBrush,
    faChartLine,
    faCode,
    faCreditCard,
    faFloppyDisk,
    faPlugCircleBolt,
    faRobot,
    faShieldHalved,
    faUsersGear,
} from '@fortawesome/free-solid-svg-icons';
import { Link, usePage } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import SocialPlatformMark from '@/components/creditsoft/SocialPlatformMark.vue';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const { isCurrentOrParentUrl } = useCurrentUrl();
const page = usePage<{
    auth: {
        role?: string | null;
        can_manage_users: boolean;
        can_view_user_directory: boolean;
    };
}>();
const canViewUserDirectory = computed(() =>
    page.props.auth.can_view_user_directory
    || ['owner_admin', 'admin', 'demo_admin', 'manager'].includes(page.props.auth.role ?? ''),
);

type SettingsNavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: IconDefinition;
    socialBrand?: 'meta';
    children?: Array<{
        title: string;
        href: string;
    }>;
};

const sidebarNavItems = computed<SettingsNavItem[]>(() => [
    {
        title: 'Profile',
        href: editProfile(),
        icon: faAddressCard,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: faShieldHalved,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: faBrush,
    },
    {
        title: 'License',
        href: '/settings/license',
        icon: faCreditCard,
    },
    ...(canViewUserDirectory.value
        ? [
            {
                title: 'Accounts Manager',
                href: '/settings/users',
                icon: faUsersGear,
            },
        ]
        : []),
    {
        title: 'AI',
        href: '/settings/ai',
        icon: faRobot,
    },
    {
        title: 'API',
        href: '/settings/api',
        icon: faCode,
    },
    {
        title: 'Connectivity',
        href: '/settings/connectivity',
        icon: faPlugCircleBolt,
    },
    {
        title: 'Growth',
        href: '/settings/growth',
        icon: faChartLine,
    },
    {
        title: 'Social / Meta',
        href: '/settings/social',
        socialBrand: 'meta',
        children: [
            {
                title: 'Readiness',
                href: '/settings/social/readiness',
            },
            {
                title: 'Facebook',
                href: '/settings/social/facebook',
            },
            {
                title: 'Instagram',
                href: '/settings/social/instagram',
            },
            {
                title: 'Threads',
                href: '/settings/social/threads',
            },
            {
                title: 'Creator Challenge',
                href: '/settings/social/creator-challenge',
            },
            {
                title: 'WhatsApp',
                href: '/settings/social/whatsapp',
            },
            {
                title: 'Publishing',
                href: '/settings/social/publishing',
            },
            {
                title: 'Ads & Leads',
                href: '/settings/social/ads',
            },
        ],
    },
    {
        title: 'Backup / File System',
        href: '/settings/filesystem',
        icon: faFloppyDisk,
    },
]);
const usesWideWorkspace = computed(() =>
    page.url.startsWith('/settings/profile')
    || page.url.startsWith('/settings/users')
    || page.url.startsWith('/settings/ai')
    || page.url.startsWith('/settings/api')
    || page.url.startsWith('/settings/appearance')
    || page.url.startsWith('/settings/connectivity')
    || page.url.startsWith('/settings/growth')
    || page.url.startsWith('/settings/social')
    || page.url.startsWith('/settings/filesystem')
);
</script>

<template>
    <div class="px-0 py-2">
        <div class="space-y-5 lg:grid lg:grid-cols-[180px_minmax(0,1fr)] lg:gap-x-[10px] lg:space-y-0">
            <aside class="w-full max-w-xl lg:sticky lg:top-6 lg:self-start lg:max-h-[calc(100vh-3rem)] lg:overflow-y-auto lg:pr-0">
                <div class="mb-3 space-y-0">
                    <Heading title="Settings" variant="small" />
                    <p class="text-xs text-muted-foreground">
                        Profile and system controls
                    </p>
                </div>

                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Settings"
                >
                    <template
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                    >
                        <div class="space-y-1">
                            <Button
                                variant="ghost"
                                :class="[
                                    'h-9 w-full justify-start rounded-xl px-2.5 text-[13px]',
                                    {
                                        'border border-[#0866ff]/20 bg-[#0866ff]/8 text-stone-950': isCurrentOrParentUrl(item.href) && item.socialBrand === 'meta',
                                        'bg-muted': isCurrentOrParentUrl(item.href) && item.socialBrand !== 'meta',
                                    },
                                ]"
                                as-child
                            >
                                <Link :href="item.href">
                                    <SocialPlatformMark
                                        v-if="item.socialBrand === 'meta'"
                                        brand="meta"
                                        compact
                                        monochrome
                                        class="mr-1.5 text-[#0866ff]"
                                    />
                                    <FontAwesomeIcon v-else-if="item.icon" :icon="item.icon" class="mr-1.5 h-4 w-4 text-stone-500" />
                                    {{ item.title }}
                                </Link>
                            </Button>

                            <div
                                v-if="item.children?.length && isCurrentOrParentUrl(item.href)"
                                class="ml-3 border-l border-[#0866ff]/20 py-1 pl-3"
                            >
                                <a
                                    v-for="child in item.children"
                                    :key="child.href"
                                    :href="child.href"
                                    class="group flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs font-medium text-stone-600 transition hover:bg-[#0866ff]/8 hover:text-stone-950"
                                    :class="{
                                        'bg-[#0866ff]/8 text-stone-950': isCurrentOrParentUrl(child.href),
                                    }"
                                >
                                    <span class="size-1.5 rounded-full bg-[#0866ff]/55 transition group-hover:bg-[#0866ff]" />
                                    {{ child.title }}
                                </a>
                            </div>
                        </div>
                    </template>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1" :class="usesWideWorkspace ? 'max-w-none' : 'md:max-w-2xl'">
                <section :class="usesWideWorkspace ? 'max-w-none space-y-10' : 'max-w-xl space-y-10'">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
