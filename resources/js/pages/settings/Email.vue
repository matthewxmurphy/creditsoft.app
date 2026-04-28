<script setup lang="ts">
import {
    faCircleCheck,
    faCircleExclamation,
    faEnvelopeOpenText,
    faKey,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmailProviderMark from '@/components/creditsoft/EmailProviderMark.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'SMTP / Email',
                href: '/settings/email',
            },
        ],
    },
});

type EmailProvider =
    | 'custom_smtp'
    | 'microsoft_365'
    | 'google_workspace'
    | 'amazon_ses'
    | 'sendgrid'
    | 'mailgun'
    | 'zoho_mail'
    | 'postmark'
    | 'brevo'
    | 'smtp_com';

type Provider = {
    key: EmailProvider;
    section: string;
    label: string;
    logo: string;
    help: string;
};

type ProviderSetting = {
    configured?: boolean;
    host?: string;
    port?: string;
    scheme?: 'tls' | 'ssl';
    username?: string;
    masked_password?: string | null;
    domain?: string;
    region?: string;
    masked_sendgrid_api_key?: string | null;
};

const props = defineProps<{
    section: string;
    settings: {
        enabled: boolean;
        use_local_sendmail?: boolean;
        sendmail_path?: string | null;
        provider: EmailProvider;
        mailer: string;
        from_name: string;
        from_email: string;
        reply_to_email: string;
        host: string;
        port: string;
        scheme: string;
        username: string;
        masked_password?: string | null;
        domain: string;
        region: string;
        masked_sendgrid_api_key?: string | null;
        provider_settings?: Partial<Record<EmailProvider, ProviderSetting>>;
    };
    providers: Provider[];
}>();

const sectionProvider = computed<EmailProvider>(() => {
    return (
        props.providers.find((provider) => provider.section === props.section)
            ?.key ??
        props.settings.provider ??
        'custom_smtp'
    );
});

const providerSetting = (provider: EmailProvider): ProviderSetting | null =>
    props.settings.provider_settings?.[provider] ?? null;
const initialProviderSettings = providerSetting(sectionProvider.value);
const initialProviderIsActive =
    sectionProvider.value === props.settings.provider;

const form = useForm({
    enabled: props.settings.enabled,
    use_local_sendmail: props.settings.use_local_sendmail ?? false,
    provider: sectionProvider.value,
    from_name: props.settings.from_name || 'CreditSoft Office',
    from_email: props.settings.from_email || '',
    reply_to_email: props.settings.reply_to_email || '',
    host:
        initialProviderSettings?.host ||
        (initialProviderIsActive ? props.settings.host : ''),
    port: Number(
        initialProviderSettings?.port ||
            (initialProviderIsActive ? props.settings.port : 587),
    ),
    scheme: (initialProviderSettings?.scheme ||
        (initialProviderIsActive ? props.settings.scheme : 'tls')) as
        | 'tls'
        | 'ssl',
    username:
        initialProviderSettings?.username ||
        (initialProviderIsActive ? props.settings.username : ''),
    password: '',
    api_key: '',
    domain:
        initialProviderSettings?.domain ||
        (initialProviderIsActive ? props.settings.domain : ''),
    region:
        initialProviderSettings?.region ||
        (initialProviderIsActive ? props.settings.region : 'us-east-1'),
    redirect_to: `/settings/email/${props.section}`,
});

const activeProvider = computed(
    () =>
        props.providers.find((provider) => provider.key === form.provider) ??
        props.providers[0],
);
const activeProviderSettings = computed(() => providerSetting(form.provider));
const isCustom = computed(() => form.provider === 'custom_smtp');
const isGoogle = computed(() => form.provider === 'google_workspace');
const isAmazon = computed(() => form.provider === 'amazon_ses');
const isSendGrid = computed(() => form.provider === 'sendgrid');
const isMailgun = computed(() => form.provider === 'mailgun');
const hostCanBeEdited = computed(() => isCustom.value || isMailgun.value);

const providerDefaults: Partial<
    Record<
        EmailProvider,
        {
            host: string;
            port: number;
            scheme: 'tls' | 'ssl';
            username?: string;
        }
    >
> = {
    microsoft_365: {
        host: 'smtp.office365.com',
        port: 587,
        scheme: 'tls',
    },
    google_workspace: {
        host: 'smtp.gmail.com',
        port: 587,
        scheme: 'tls',
    },
    sendgrid: {
        host: 'smtp.sendgrid.net',
        port: 587,
        scheme: 'tls',
        username: 'apikey',
    },
    amazon_ses: {
        host: `email-smtp.${form.region || 'us-east-1'}.amazonaws.com`,
        port: 587,
        scheme: 'tls',
    },
    mailgun: {
        host: 'smtp.mailgun.org',
        port: 587,
        scheme: 'tls',
    },
    zoho_mail: {
        host: 'smtp.zoho.com',
        port: 587,
        scheme: 'tls',
    },
    postmark: {
        host: 'smtp.postmarkapp.com',
        port: 587,
        scheme: 'tls',
    },
    brevo: {
        host: 'smtp-relay.brevo.com',
        port: 587,
        scheme: 'tls',
    },
    smtp_com: {
        host: 'send.smtp.com',
        port: 587,
        scheme: 'tls',
    },
};

const defaultHost = computed(() => {
    if (isAmazon.value) {
        return `email-smtp.${form.region || 'us-east-1'}.amazonaws.com`;
    }

    return (
        providerDefaults[form.provider]?.host ||
        form.host ||
        'mail.yourdomain.com'
    );
});

const secretPlaceholder = computed(() => {
    const saved = activeProviderSettings.value;

    if (isSendGrid.value) {
        return (
            saved?.masked_sendgrid_api_key ??
            saved?.masked_password ??
            props.settings.masked_sendgrid_api_key ??
            props.settings.masked_password ??
            'Paste SendGrid API key'
        );
    }

    return (
        saved?.masked_password ??
        props.settings.masked_password ??
        'Paste password or SMTP secret'
    );
});

const statusPill = computed(() => {
    if (!form.enabled) {
        return {
            label: 'Email paused',
            className: 'border-stone-300 bg-white text-stone-700',
            icon: faCircleExclamation,
        };
    }

    if (props.settings.mailer === 'sendmail' && props.settings.sendmail_path) {
        return {
            label: 'Local relay saved',
            className: 'border-emerald-300 bg-emerald-100 text-emerald-900',
            icon: faCircleCheck,
        };
    }

    if (props.settings.mailer === 'smtp' && props.settings.host) {
        return {
            label: 'SMTP saved',
            className: 'border-emerald-300 bg-emerald-100 text-emerald-900',
            icon: faCircleCheck,
        };
    }

    return {
        label: 'Needs SMTP',
        className: 'border-amber-300 bg-amber-100 text-amber-900',
        icon: faKey,
    };
});

const providerButtonClass = (provider: EmailProvider) => {
    if (form.provider === provider) {
        return 'border-amber-400 bg-amber-50 shadow-sm ring-2 ring-amber-300/70';
    }

    if (providerSetting(provider)?.configured) {
        return 'border-emerald-300 bg-emerald-50/40 hover:border-emerald-400';
    }

    return 'border-stone-200 bg-white hover:border-amber-300 hover:bg-amber-50/40';
};

const setProvider = (provider: EmailProvider) => {
    const saved = providerSetting(provider);
    const defaults = providerDefaults[provider];

    form.provider = provider;
    form.redirect_to = `/settings/email/${props.providers.find((item) => item.key === provider)?.section ?? 'smtp'}`;
    form.host = saved?.host || '';
    form.port = Number(saved?.port || 587);
    form.scheme = (saved?.scheme || 'tls') as 'tls' | 'ssl';
    form.username = saved?.username || '';
    form.domain = saved?.domain || '';
    form.region = saved?.region || form.region || 'us-east-1';
    form.password = '';
    form.api_key = '';

    if (provider === 'amazon_ses') {
        form.host = `email-smtp.${form.region || 'us-east-1'}.amazonaws.com`;
    } else if (defaults) {
        form.host = defaults.host;
    }

    if (defaults) {
        form.port = defaults.port;
        form.scheme = defaults.scheme;
    }

    if (provider === 'google_workspace' || provider === 'microsoft_365') {
        form.username = saved?.username || form.from_email;
    } else if (defaults?.username) {
        form.username = defaults.username;
    }
};

const submit = () => {
    form.put('/settings/email', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('password', 'api_key');
            router.reload({
                only: ['settings', 'providers', 'creditsoft'],
            });
        },
    });
};
</script>

<template>
    <Head title="SMTP / Email Settings" />

    <div class="space-y-6">
        <section class="space-y-2">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <h1 class="text-xl font-semibold text-stone-950">
                        SMTP and email delivery
                    </h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-600">
                        Configure the office mail lane for client notices, CRM
                        follow-up, report update requests, and billing
                        reminders.
                    </p>
                </div>
                <span
                    class="inline-flex h-8 min-w-[8.5rem] shrink-0 items-center justify-center rounded-full border px-3 text-center text-[11px] leading-none font-semibold tracking-[0.18em] uppercase"
                    :class="statusPill.className"
                >
                    <FontAwesomeIcon
                        :icon="statusPill.icon"
                        class="mr-1.5 text-[11px]"
                    />
                    {{ statusPill.label }}
                </span>
            </div>
        </section>

        <form class="space-y-6" @submit.prevent="submit">
            <section
                class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95"
            >
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <p
                        class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                    >
                        Provider
                    </p>
                    <h2 class="mt-2 text-lg font-semibold text-stone-950">
                        Choose the office sender
                    </h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-600">
                        Save several sender lanes locally, then pick which lane
                        sends office email now.
                    </p>
                    <p class="mt-1 text-xs font-medium text-stone-500">
                        Used across CRM, website, and automation.
                    </p>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-5">
                    <button
                        v-for="provider in providers"
                        :key="provider.key"
                        type="button"
                        class="relative grid min-h-[116px] content-center justify-items-center gap-1.5 rounded-2xl border px-3 py-3 text-center transition"
                        :class="providerButtonClass(provider.key)"
                        :aria-label="`${provider.label} email lane`"
                        :title="provider.label"
                        @click="setProvider(provider.key)"
                    >
                        <span
                            class="flex h-[90px] w-full max-w-[170px] items-center justify-center px-3"
                        >
                            <EmailProviderMark :provider="provider.key" hero />
                        </span>
                        <span
                            class="min-h-8 max-w-[10.5rem] text-[11px] leading-4 text-stone-500"
                        >
                            {{ provider.help }}
                        </span>
                        <FontAwesomeIcon
                            v-if="
                                form.provider === provider.key ||
                                providerSetting(provider.key)?.configured
                            "
                            :icon="faCircleCheck"
                            class="absolute top-3 right-3 text-sm"
                            :class="
                                form.provider === provider.key
                                    ? 'text-amber-600'
                                    : 'text-emerald-600'
                            "
                        />
                    </button>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95"
            >
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="flex items-center gap-4">
                            <EmailProviderMark
                                :provider="form.provider"
                                large
                            />
                            <div>
                                <p
                                    class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                >
                                    Active setup
                                </p>
                                <h2
                                    class="mt-1 text-lg font-semibold text-stone-950"
                                >
                                    {{ activeProvider.label }}
                                </h2>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 lg:items-end">
                            <label
                                class="flex items-center gap-3 text-sm font-medium text-stone-700"
                            >
                                <input
                                    v-model="form.enabled"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-stone-400 text-stone-950"
                                />
                                Email enabled
                            </label>
                            <label
                                class="flex max-w-md items-start gap-3 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-700"
                            >
                                <input
                                    v-model="form.use_local_sendmail"
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                                />
                                <span>
                                    <span
                                        class="block font-medium text-stone-900"
                                        >Local msmtp relay</span
                                    >
                                    <span
                                        class="mt-1 block text-xs leading-5 text-stone-600"
                                    >
                                        On sends through the container sendmail
                                        path. Off sends directly to the remote
                                        SMTP host.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 px-6 py-6">
                    <div class="grid gap-4 lg:grid-cols-3">
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >From name</span
                            >
                            <input
                                v-model="form.from_name"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="CreditSoft Office"
                            />
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >From email</span
                            >
                            <input
                                v-model="form.from_email"
                                type="email"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="support@yourdomain.com"
                            />
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Reply-to email</span
                            >
                            <input
                                v-model="form.reply_to_email"
                                type="email"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="support@yourdomain.com"
                            />
                        </label>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <label v-if="isAmazon" class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >SES region</span
                            >
                            <input
                                v-model="form.region"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="us-east-1"
                                @input="
                                    form.host = `email-smtp.${form.region || 'us-east-1'}.amazonaws.com`
                                "
                            />
                        </label>
                        <label
                            class="space-y-2"
                            :class="isAmazon ? '' : 'lg:col-span-2'"
                        >
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >SMTP host</span
                            >
                            <input
                                v-model="form.host"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                :placeholder="defaultHost"
                                :readonly="!hostCanBeEdited"
                            />
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Port</span
                            >
                            <input
                                v-model="form.port"
                                type="number"
                                min="1"
                                max="65535"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                            />
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Security</span
                            >
                            <select
                                v-model="form.scheme"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                            >
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                            </select>
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                            >
                                {{ isSendGrid ? 'SMTP username' : 'Username' }}
                            </span>
                            <input
                                v-model="form.username"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                :placeholder="
                                    isSendGrid
                                        ? 'apikey'
                                        : isGoogle
                                          ? 'user@yourdomain.com'
                                          : 'SMTP username'
                                "
                                :readonly="isSendGrid"
                            />
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                            >
                                {{
                                    isSendGrid
                                        ? 'SendGrid API key'
                                        : 'Password / SMTP secret'
                                }}
                            </span>
                            <input
                                v-if="isSendGrid"
                                v-model="form.api_key"
                                type="password"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                :placeholder="secretPlaceholder"
                            />
                            <input
                                v-else
                                v-model="form.password"
                                type="password"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                :placeholder="secretPlaceholder"
                            />
                        </label>
                        <label class="space-y-2 lg:col-span-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >EHLO domain</span
                            >
                            <input
                                v-model="form.domain"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="mail.yourdomain.com"
                            />
                        </label>
                    </div>

                    <div
                        class="flex flex-col gap-4 border-t border-stone-200/80 pt-5 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <p
                            class="flex items-center gap-2 text-sm text-stone-600"
                        >
                            <FontAwesomeIcon
                                :icon="faEnvelopeOpenText"
                                class="text-amber-500"
                            />
                            Blank secret fields keep the saved password/API key.
                        </p>
                        <button
                            type="submit"
                            class="rounded-full bg-stone-950 px-5 py-3 text-xs font-medium tracking-[0.22em] text-stone-50 uppercase transition hover:bg-stone-800"
                        >
                            Save email settings
                        </button>
                    </div>
                </div>
            </section>
        </form>
    </div>
</template>
