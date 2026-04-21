<script setup lang="ts">
import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faArrowUpRightFromSquare,
    faCircleCheck,
    faDownload,
    faKey,
    faTrashCan,
} from '@fortawesome/free-solid-svg-icons';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ConnectivityBrandMark from '@/components/creditsoft/ConnectivityBrandMark.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

type PersonalKey = {
    id: number;
    name: string;
    masked_token: string;
    last_used_at?: string | null;
    created_at?: string | null;
};

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    clientPairing: {
        generated_personal_token?: string | null;
        personal_keys: PersonalKey[];
        local_base_url: string;
        tailnet_base_url?: string | null;
        public_base_url?: string | null;
        preferred_base_url: string;
        tailscale_running: boolean;
        tailscale_dns_name?: string | null;
        browser_companion_enabled: boolean;
        browser_companion_download_url?: string | null;
        connectivity_url: string;
        api_docs_url: string;
    };
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const keyForm = useForm({
    name: 'Intranet client',
});

const normalizeUrl = (value?: string | null) =>
    (value ?? '').replace(/\/+$/, '');
const preferredBaseUrl = computed(() =>
    normalizeUrl(props.clientPairing.preferred_base_url),
);
const tailnetLaneState = computed(() => {
    if (props.clientPairing.tailscale_running) {
        return 'Best path for another approved device';
    }

    return props.clientPairing.tailnet_base_url
        ? 'Configured office path'
        : 'Tailnet not live yet';
});

const pairingLanes = computed(() => [
    {
        id: 'local',
        label: 'This machine',
        brand: 'local' as const,
        url: props.clientPairing.local_base_url,
        available: true,
        state: 'Fastest on this device',
        body: '127.0.0.1 only belongs to the device currently serving CreditSoft. Use this path when the intranet client or browser companion runs on that same device.',
    },
    {
        id: 'tailscale',
        label: 'Office tailnet',
        brand: 'tailscale' as const,
        url: props.clientPairing.tailnet_base_url ?? null,
        available: Boolean(props.clientPairing.tailnet_base_url),
        state: tailnetLaneState.value,
        body: props.clientPairing.tailnet_base_url
            ? 'Another approved office device should pair through the office tailnet instead of trying to hit 127.0.0.1 on the host device.'
            : 'Once Tailscale is running, other approved office devices should pair here instead of trying to use localhost.',
    },
    {
        id: 'public',
        label: 'Public API fallback',
        brand: 'openapi' as const,
        url: props.clientPairing.public_base_url ?? null,
        available: Boolean(props.clientPairing.public_base_url),
        state: props.clientPairing.public_base_url
            ? 'Stable outside callback lane'
            : 'No public API base configured',
        body: 'Use this only when the office needs a stable public domain for callbacks or remote access outside the tailnet.',
    },
]);

const createPersonalApiKey = () => {
    keyForm.post('/settings/profile/api-keys', {
        preserveScroll: true,
        onSuccess: () => {
            keyForm.defaults('name', 'Intranet client');
            keyForm.reset();
        },
    });
};

const revokePersonalApiKey = (id: number) => {
    router.delete(`/settings/profile/api-keys/${id}`, {
        preserveScroll: true,
    });
};

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString() : 'Never';
</script>

<template>
    <Head title="Profile Settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="space-y-10">
        <section class="max-w-xl space-y-6">
            <Heading
                variant="small"
                title="Profile information"
                description="Update your name and email address"
            />

            <Form
                v-bind="ProfileController.update()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        class="mt-1 block w-full"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                        placeholder="Full name"
                    />
                    <InputError class="mt-2" :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        name="email"
                        :default-value="user.email"
                        required
                        autocomplete="username"
                        placeholder="Email address"
                    />
                    <InputError class="mt-2" :message="errors.email" />
                </div>

                <div v-if="mustVerifyEmail && !user.email_verified_at">
                    <p class="-mt-4 text-sm text-muted-foreground">
                        Your email address is unverified.
                        <Link
                            :href="send()"
                            as="button"
                            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        >
                            Click here to resend the verification email.
                        </Link>
                    </p>

                    <div
                        v-if="status === 'verification-link-sent'"
                        class="mt-2 text-sm font-medium text-green-600"
                    >
                        A new verification link has been sent to your email
                        address.
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        :disabled="processing"
                        data-test="update-profile-button"
                    >
                        Save
                    </Button>
                </div>
            </Form>
        </section>

        <section
            class="overflow-hidden rounded-[30px] border border-stone-300/70 bg-white/95"
        >
            <div class="border-b border-stone-200/80 px-6 py-6">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="max-w-3xl">
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            Intranet client pairing
                        </p>
                        <h2 class="mt-2 text-xl font-semibold text-stone-950">
                            Your personal key should follow you. `127.0.0.1`
                            should not.
                        </h2>
                        <p class="mt-3 text-sm leading-7 text-stone-600">
                            The same personal CreditSoft key can power the
                            browser companion now and the future intranet client
                            next. This page stays user-specific, while
                            office-wide Tailscale, ngrok, and the shared website
                            key stay in Connectivity.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a
                            v-if="
                                props.clientPairing.browser_companion_enabled &&
                                props.clientPairing
                                    .browser_companion_download_url
                            "
                            :href="
                                props.clientPairing
                                    .browser_companion_download_url
                            "
                            class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-4 py-2 text-xs font-medium tracking-[0.2em] text-stone-800 uppercase transition hover:border-stone-500"
                        >
                            <FontAwesomeIcon :icon="faDownload" />
                            Browser companion
                        </a>
                        <Link
                            :href="props.clientPairing.connectivity_url"
                            class="inline-flex items-center gap-2 rounded-full bg-stone-950 px-4 py-2 text-xs font-medium tracking-[0.2em] text-white uppercase transition hover:bg-stone-800"
                        >
                            <FontAwesomeIcon :icon="faArrowUpRightFromSquare" />
                            Office connectivity
                        </Link>
                    </div>
                </div>
            </div>

            <div
                class="grid gap-6 px-6 py-6 xl:grid-cols-[minmax(0,1fr)_420px]"
            >
                <div class="space-y-6">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div
                            v-for="lane in pairingLanes"
                            :key="lane.id"
                            class="rounded-[24px] border px-4 py-4"
                            :class="[
                                lane.available
                                    ? 'border-stone-200 bg-stone-50'
                                    : 'border-stone-200/80 bg-white',
                                lane.id === 'public' ? 'lg:col-span-2' : '',
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <ConnectivityBrandMark
                                    :brand="lane.brand"
                                    compact
                                />
                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-semibold text-stone-950"
                                    >
                                        {{ lane.label }}
                                    </p>
                                    <p
                                        class="text-[11px] tracking-[0.2em] uppercase"
                                        :class="
                                            normalizeUrl(lane.url) ===
                                            preferredBaseUrl
                                                ? 'text-emerald-700'
                                                : 'text-stone-500'
                                        "
                                    >
                                        {{
                                            normalizeUrl(lane.url) ===
                                            preferredBaseUrl
                                                ? 'Preferred now'
                                                : lane.state
                                        }}
                                    </p>
                                </div>
                            </div>
                            <div
                                class="mt-3 rounded-2xl border border-stone-200 bg-white px-3 py-3"
                            >
                                <p
                                    class="text-[10px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                >
                                    API path
                                </p>
                                <p
                                    class="mt-1 overflow-hidden font-mono text-xs text-ellipsis whitespace-nowrap text-stone-700"
                                    :title="lane.url ?? 'Not available yet'"
                                >
                                    {{ lane.url ?? 'Not available yet' }}
                                </p>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-stone-600">
                                {{ lane.body }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-[24px] border border-stone-200 bg-stone-50 px-5 py-5"
                    >
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            How this should work
                        </p>
                        <p class="mt-2 text-sm leading-6 text-stone-900">
                            If the client is on the same device as CreditSoft,
                            use the local API. If it is on another approved
                            office device, use the Tailscale path. Only fall
                            back to the public API domain when the office truly
                            needs outside callback or remote access.
                        </p>
                        <p class="mt-3 text-xs leading-5 text-stone-500">
                            Tailscale admin keys, reserved domains, and relay
                            behavior remain office controls under Connectivity
                            because they affect every device, not just your user
                            profile.
                        </p>
                    </div>

                    <div
                        v-if="props.clientPairing.generated_personal_token"
                        class="rounded-[24px] border border-emerald-300 bg-emerald-50 px-5 py-5"
                    >
                        <div class="flex items-center gap-2 text-emerald-800">
                            <FontAwesomeIcon :icon="faCircleCheck" />
                            <p class="text-sm font-semibold">
                                New personal API key
                            </p>
                        </div>
                        <p
                            class="mt-3 rounded-2xl bg-white px-4 py-3 font-mono text-sm break-all text-stone-900"
                        >
                            {{ props.clientPairing.generated_personal_token }}
                        </p>
                        <p class="mt-2 text-xs leading-5 text-emerald-800">
                            Copy it now. CreditSoft only shows the full value
                            right after creation.
                        </p>
                    </div>
                </div>

                <div class="space-y-5">
                    <div
                        class="rounded-[24px] border border-stone-200 bg-white px-5 py-5"
                    >
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon
                                :icon="faKey"
                                class="text-stone-700"
                            />
                            <p class="text-sm font-semibold text-stone-950">
                                Personal client keys
                            </p>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-stone-600">
                            Generate one key per user device or pairing lane.
                            This is the right credential for the browser
                            companion today and for the intranet client flow
                            next.
                        </p>

                        <div
                            class="mt-4 flex flex-col gap-3 md:flex-row md:items-end"
                        >
                            <label class="flex-1 space-y-2">
                                <span
                                    class="text-xs font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >Key label</span
                                >
                                <input
                                    v-model="keyForm.name"
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    placeholder="Intranet client"
                                />
                                <span
                                    v-if="keyForm.errors.name"
                                    class="text-xs text-rose-700"
                                    >{{ keyForm.errors.name }}</span
                                >
                            </label>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-full bg-stone-950 px-5 py-3 text-xs font-medium tracking-[0.2em] text-white uppercase transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:bg-stone-300"
                                :disabled="keyForm.processing"
                                @click="createPersonalApiKey"
                            >
                                <FontAwesomeIcon :icon="faKey" />
                                Generate key
                            </button>
                        </div>

                        <div
                            v-if="props.clientPairing.personal_keys.length"
                            class="mt-4 space-y-3"
                        >
                            <div
                                v-for="key in props.clientPairing.personal_keys"
                                :key="key.id"
                                class="rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-4"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-medium text-stone-900"
                                        >
                                            {{ key.name }}
                                        </p>
                                        <p
                                            class="mt-1 font-mono text-xs text-stone-600"
                                        >
                                            {{ key.masked_token }}
                                        </p>
                                        <p
                                            class="mt-2 text-xs leading-5 text-stone-500"
                                        >
                                            Created
                                            {{ formatDateTime(key.created_at) }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs leading-5 text-stone-500"
                                        >
                                            Last used
                                            {{
                                                formatDateTime(key.last_used_at)
                                            }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-3 py-2 text-[11px] font-medium tracking-[0.18em] text-stone-700 uppercase transition hover:border-rose-300 hover:text-rose-700"
                                        @click="revokePersonalApiKey(key.id)"
                                    >
                                        <FontAwesomeIcon :icon="faTrashCan" />
                                        Revoke
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p v-else class="mt-4 text-sm leading-6 text-stone-600">
                            No personal API keys have been created for this
                            account yet.
                        </p>
                    </div>

                    <div
                        class="rounded-[24px] border border-stone-200 bg-stone-50 px-5 py-5"
                    >
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            Next layer
                        </p>
                        <p class="mt-2 text-sm leading-6 text-stone-900">
                            A true CreditSoft intranet client should pair with
                            this personal key first, then discover the best API
                            path in this order: local host, office tailnet, then
                            the public API fallback.
                        </p>
                        <p class="mt-3 text-xs leading-5 text-stone-500">
                            That keeps the user credential in one place while
                            office ACL, Tailscale enrollment, and public tunnel
                            policy remain shared infrastructure controls.
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link
                                :href="props.clientPairing.connectivity_url"
                                class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-3 py-2 text-[11px] font-medium tracking-[0.18em] text-stone-700 uppercase transition hover:border-stone-500"
                            >
                                Office network controls
                            </Link>
                            <Link
                                :href="props.clientPairing.api_docs_url"
                                class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-3 py-2 text-[11px] font-medium tracking-[0.18em] text-stone-700 uppercase transition hover:border-stone-500"
                            >
                                API docs
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <DeleteUser />
    </div>
</template>
