import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, type DefineComponent } from 'vue';
import { initializeTheme } from '@/composables/useAppearance';
import CreditsoftLayout from '@/layouts/CreditsoftLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializePwa } from '@/lib/pwa';

const appName = 'CreditSoft';

type PageLayoutMeta = {
    title?: string;
    description?: string;
    breadcrumbs?: Array<{
        title: string;
        href: string;
    }>;
};

type InertiaPageModule = {
    default: DefineComponent & {
        layout?: unknown;
    };
};

const pageLayouts = (name: string): unknown => {
    switch (true) {
        case name === 'Welcome':
            return undefined;
        case name.startsWith('install/'):
            return undefined;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return [CreditsoftLayout, SettingsLayout];
        default:
            return CreditsoftLayout;
    }
};

const isPageLayoutMeta = (value: unknown): value is PageLayoutMeta => {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return false;
    }

    return 'title' in value || 'description' in value || 'breadcrumbs' in value;
};

createInertiaApp({
    title: (title) => {
        if (!title) {
            return appName;
        }

        return title.startsWith(`${appName} |`) ? title : `${appName} | ${title}`;
    },
    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        );

        const pageModule = page as unknown as InertiaPageModule;
        const component = pageModule.default as DefineComponent & {
            layout?: any;
        };
        const defaultLayout = pageLayouts(name);

        if (component.layout === undefined) {
            component.layout = defaultLayout;
        } else if (isPageLayoutMeta(component.layout)) {
            const layoutMeta = component.layout;

            if (name.startsWith('auth/')) {
                component.layout = (_render: typeof h, currentPage: unknown) =>
                    h(AuthLayout as never, layoutMeta, () => currentPage);
            } else {
                component.layout = defaultLayout;
            }
        }

        return component;
    },
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(App, props),
        })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
initializeFlashToast();
initializePwa();
