import { computed, reactive, readonly } from 'vue';

type InstallOutcome = 'accepted' | 'dismissed';

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{
        outcome: InstallOutcome;
        platform: string;
    }>;
}

const state = reactive({
    supported: false,
    localInstallHost: false,
    canInstall: false,
    installed: false,
    prompting: false,
});

let deferredPrompt: BeforeInstallPromptEvent | null = null;
let initialized = false;

const isLocalInstallHost = () => {
    if (typeof window === 'undefined') {
        return false;
    }

    return ['127.0.0.1', 'localhost'].includes(window.location.hostname)
        || window.location.protocol === 'https:';
};

const isStandalone = () => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(display-mode: standalone)').matches
        || (window.navigator as Navigator & { standalone?: boolean }).standalone === true;
};

export const initializePwa = () => {
    if (initialized || typeof window === 'undefined') {
        return;
    }

    initialized = true;
    state.localInstallHost = isLocalInstallHost();
    state.installed = isStandalone();

    if (!state.localInstallHost || !('serviceWorker' in navigator) || !window.isSecureContext) {
        return;
    }

    state.supported = true;

    navigator.serviceWorker.register('/sw.js?v=4', { updateViaCache: 'none' })
        .then((registration) => {
            registration.update().catch(() => undefined);
        })
        .catch((error) => {
            console.warn('CreditSoft service worker registration failed.', error);
        });

    window.addEventListener('beforeinstallprompt', (event) => {
        const promptEvent = event as BeforeInstallPromptEvent;

        promptEvent.preventDefault();
        deferredPrompt = promptEvent;
        state.canInstall = true;
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        state.canInstall = false;
        state.installed = true;
        state.prompting = false;
    });
};

export const usePwaInstall = () => {
    const install = async () => {
        if (!deferredPrompt || state.prompting) {
            return false;
        }

        state.prompting = true;

        try {
            await deferredPrompt.prompt();

            const choice = await deferredPrompt.userChoice;

            deferredPrompt = null;
            state.canInstall = false;
            state.installed = choice.outcome === 'accepted' || state.installed;

            return choice.outcome === 'accepted';
        } finally {
            state.prompting = false;
        }
    };

    const installLabel = computed(() => {
        if (state.installed) {
            return 'Installed';
        }

        if (state.prompting) {
            return 'Opening...';
        }

        return 'Install App';
    });

    return {
        pwaState: readonly(state),
        install,
        installLabel,
    };
};
