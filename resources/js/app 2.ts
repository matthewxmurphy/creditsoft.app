import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import CreditsoftLayout from '@/layouts/CreditsoftLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializePwa } from '@/lib/pwa';

const appName = 'CreditSoft';

createInertiaApp({
    title: (title) => {
        if (!title) {
            return appName;
        }

        return title.startsWith(`${appName} |`) ? title : `${appName} | ${title}`;
    },
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('install/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [CreditsoftLayout, SettingsLayout];
            default:
                return CreditsoftLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// Only localhost / 127.0.0.1 gets the installable app hooks.
initializePwa();
