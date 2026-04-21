<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardWorkspaceNav from '@/components/creditsoft/DashboardWorkspaceNav.vue';
import MetricTile from '@/components/creditsoft/MetricTile.vue';
import { formatCurrency, formatDate, formatNumber } from '@/lib/creditsoft';

const props = defineProps<{
    billing: {
        settings: {
            gateway_provider?: string | null;
            gateway_status: string;
            gateway_account_label?: string | null;
            gateway_environment?: string | null;
            webhook_status?: string | null;
            gateway_connected_at?: string | null;
            payment_portal_url?: string | null;
            notes?: string | null;
        };
        stats: {
            recorded_revenue: number;
            last_paid_at?: string | null;
        };
        zelle: {
            settings: {
                enabled: boolean;
                bank_name?: string | null;
                imap_host?: string | null;
                imap_port: number;
                imap_encryption: string;
                imap_username?: string | null;
                has_password: boolean;
                masked_password?: string | null;
                imap_folder: string;
                expected_subject: string;
                trusted_domains: string;
                delete_after_import: boolean;
                last_checked_at?: string | null;
                last_error?: string | null;
            };
            stats: {
                total_messages: number;
                processed_count: number;
                needs_review_count: number;
                deleted_count: number;
                total_amount: number;
                processed_amount: number;
                needs_review_amount: number;
                last_received_at?: string | null;
            };
            messages: Array<{
                id: number;
                client_id?: number | null;
                client_name?: string | null;
                client_email?: string | null;
                amount?: number | null;
                currency: string;
                status: string;
                match_type?: string | null;
                header_status?: string | null;
                sender_name?: string | null;
                memo_email?: string | null;
                memo_text?: string | null;
                transaction_id?: string | null;
                received_at?: string | null;
                sent_on?: string | null;
                from_email?: string | null;
                subject?: string | null;
                deleted_from_mailbox_at?: string | null;
            }>;
            imap_enabled: boolean;
        };
        cash_app: {
            settings: {
                enabled: boolean;
                environment: string;
                api_base_url: string;
                client_id?: string | null;
                has_api_key_id: boolean;
                masked_api_key_id?: string | null;
                has_api_secret: boolean;
                masked_api_secret?: string | null;
                region: string;
                scope_id?: string | null;
                merchant_id?: string | null;
                redirect_url?: string | null;
                user_agent: string;
                auto_capture: boolean;
                last_checked_at?: string | null;
                last_error?: string | null;
                configured: boolean;
                network_configured: boolean;
                blocked_reason?: string | null;
            };
            stats: {
                total_requests: number;
                pending_count: number;
                approved_count: number;
                paid_count: number;
                failed_count: number;
                requested_amount: number;
            };
            requests: Array<{
                id: number;
                client_id?: number | null;
                client_name?: string | null;
                amount: number;
                currency: string;
                status: string;
                cash_app_request_id?: string | null;
                cash_app_payment_id?: string | null;
                grant_id?: string | null;
                reference_id: string;
                qr_code_image_url?: string | null;
                qr_code_svg_url?: string | null;
                mobile_url?: string | null;
                desktop_url?: string | null;
                refreshes_at?: string | null;
                expires_at?: string | null;
                approved_at?: string | null;
                paid_at?: string | null;
                last_error?: string | null;
                created_at?: string | null;
            }>;
        };
        clients: Array<{
            id: number;
            display_name: string;
            email?: string | null;
        }>;
        profiles: Array<{
            id: number;
            client_id: number;
            client_name?: string | null;
            status: string;
            amount: number;
            currency: string;
            billing_interval: string;
            monthly_amount: number;
            started_at?: string | null;
            last_paid_at?: string | null;
            next_due_at?: string | null;
            gateway_name?: string | null;
            gateway_customer_id?: string | null;
            gateway_subscription_id?: string | null;
            notes?: string | null;
        }>;
        recent_payments: Array<{
            id: number;
            client_id: number;
            client_name?: string | null;
            amount: number;
            currency: string;
            status: string;
            paid_at?: string | null;
            gateway_name?: string | null;
            gateway_transaction_id?: string | null;
            reference?: string | null;
            notes?: string | null;
        }>;
        can_manage_billing: boolean;
    };
}>();

const gatewayProviderSuggestions = [
    'Authorize.net',
    'PaymentCloud + Authorize.net',
    'PaymentCloud + NMI',
    'PaymentCloud + ACH',
    'GOAT Payments',
    'GOAT Payments + Authorize.net',
    'GOAT Payments + NMI',
    'NMI',
    'USAePay',
    'Valor PayTech',
    'Biller Genie',
    'eCheck / ACH',
    'Zelle',
    'Cash App',
    'Apple Pay',
    'Google Pay',
    'Manual / offline',
    'Other merchant account',
];

const gatewayForm = useForm({
    gateway_provider: props.billing.settings.gateway_provider ?? '',
    gateway_status: props.billing.settings.gateway_status ?? 'manual',
    gateway_account_label: props.billing.settings.gateway_account_label ?? '',
    gateway_environment: props.billing.settings.gateway_environment ?? '',
    webhook_status: props.billing.settings.webhook_status ?? '',
    gateway_connected_at: props.billing.settings.gateway_connected_at?.slice(0, 16) ?? '',
    payment_portal_url: props.billing.settings.payment_portal_url ?? '',
    notes: props.billing.settings.notes ?? '',
});

const profileForm = useForm({
    client_id: props.billing.clients[0]?.id?.toString() ?? '',
    status: 'active',
    amount: '',
    currency: 'USD',
    billing_interval: 'monthly',
    started_at: '',
    last_paid_at: '',
    next_due_at: '',
    gateway_name: '',
    gateway_customer_id: '',
    gateway_subscription_id: '',
    notes: '',
});

const paymentForm = useForm({
    client_id: props.billing.clients[0]?.id?.toString() ?? '',
    amount: '',
    currency: 'USD',
    status: 'paid',
    paid_at: '',
    gateway_name: '',
    gateway_transaction_id: '',
    reference: '',
    notes: '',
});

const zelleForm = useForm({
    enabled: props.billing.zelle.settings.enabled,
    bank_name: props.billing.zelle.settings.bank_name ?? 'Chase',
    imap_host: props.billing.zelle.settings.imap_host ?? '',
    imap_port: String(props.billing.zelle.settings.imap_port ?? 993),
    imap_encryption: props.billing.zelle.settings.imap_encryption ?? 'ssl',
    imap_username: props.billing.zelle.settings.imap_username ?? '',
    imap_password: '',
    imap_folder: props.billing.zelle.settings.imap_folder ?? 'INBOX',
    expected_subject: props.billing.zelle.settings.expected_subject ?? 'You received money with Zelle®',
    trusted_domains: props.billing.zelle.settings.trusted_domains ?? 'chase.com,zellepay.com,zelle.com,jpmorgan.com',
    delete_after_import: props.billing.zelle.settings.delete_after_import ?? true,
});

const zelleSyncForm = useForm({});
const cashAppSettingsForm = useForm({
    enabled: props.billing.cash_app.settings.enabled,
    environment: props.billing.cash_app.settings.environment ?? 'sandbox',
    api_base_url: props.billing.cash_app.settings.api_base_url ?? 'https://sandbox.api.cash.app',
    client_id: props.billing.cash_app.settings.client_id ?? '',
    api_key_id: '',
    api_secret: '',
    region: props.billing.cash_app.settings.region ?? 'PDX',
    scope_id: props.billing.cash_app.settings.scope_id ?? '',
    merchant_id: props.billing.cash_app.settings.merchant_id ?? '',
    redirect_url: props.billing.cash_app.settings.redirect_url ?? '',
    user_agent: props.billing.cash_app.settings.user_agent ?? 'CreditSoft Intranet',
    auto_capture: props.billing.cash_app.settings.auto_capture ?? false,
});
const cashAppRequestForm = useForm({
    client_id: props.billing.clients[0]?.id?.toString() ?? '',
    amount: '',
    currency: 'USD',
    redirect_url: props.billing.cash_app.settings.redirect_url ?? '',
});
const cashAppSyncForm = useForm({});

const submitGatewaySettings = () => gatewayForm.transform((data) => ({
    ...data,
    gateway_connected_at: data.gateway_connected_at || null,
    payment_portal_url: data.payment_portal_url || null,
})).put('/dashboard/billing-settings');

const submitBillingProfile = () => profileForm.post('/dashboard/billing-profiles', {
    preserveScroll: true,
    onSuccess: () => profileForm.reset('amount', 'started_at', 'last_paid_at', 'next_due_at', 'gateway_customer_id', 'gateway_subscription_id', 'notes'),
});

const submitPayment = () => paymentForm.post('/dashboard/payments', {
    preserveScroll: true,
    onSuccess: () => paymentForm.reset('amount', 'paid_at', 'gateway_transaction_id', 'reference', 'notes'),
});

const submitZelleSettings = () => zelleForm.transform((data) => ({
    ...data,
    enabled: data.enabled ? 1 : 0,
    delete_after_import: data.delete_after_import ? 1 : 0,
    imap_port: Number(data.imap_port || 993),
    imap_password: data.imap_password || null,
})).put('/dashboard/zelle-settings', {
    preserveScroll: true,
    onSuccess: () => zelleForm.reset('imap_password'),
});

const syncZelle = () => zelleSyncForm.post('/dashboard/zelle/sync', {
    preserveScroll: true,
});

const submitCashAppSettings = () => cashAppSettingsForm.transform((data) => ({
    ...data,
    enabled: Boolean(data.enabled),
    auto_capture: Boolean(data.auto_capture),
    api_key_id: data.api_key_id || null,
    api_secret: data.api_secret || null,
})).put('/dashboard/cash-app-settings', {
    preserveScroll: true,
    onSuccess: () => cashAppSettingsForm.reset('api_key_id', 'api_secret'),
});

const createCashAppRequest = () => cashAppRequestForm.post('/dashboard/cash-app/requests', {
    preserveScroll: true,
    onSuccess: () => cashAppRequestForm.reset('amount'),
});

const syncCashAppRequest = (requestId: number) => cashAppSyncForm.post(`/dashboard/cash-app/requests/${requestId}/sync`, {
    preserveScroll: true,
});

const activeBillingProfiles = computed(() =>
    props.billing.profiles.filter((profile) => ['active', 'trial'].includes(profile.status)).length,
);

const recentPaymentCount = computed(() => props.billing.recent_payments.length);

const lastPaymentLabel = computed(() =>
    props.billing.stats.last_paid_at ? formatDateTime(props.billing.stats.last_paid_at) : 'None',
);

const zelleLogo = '/assets/vendor-logos/zelle.svg';
const cashAppLogo = '/assets/vendor-logos/cash-app.svg';
const zelleLastSyncLabel = computed(() =>
    props.billing.zelle.settings.last_checked_at ? formatDateTime(props.billing.zelle.settings.last_checked_at) : 'Never checked',
);
const zelleLastReceivedLabel = computed(() =>
    props.billing.zelle.stats.last_received_at ? formatDateTime(props.billing.zelle.stats.last_received_at) : 'No Zelle mail saved',
);
const cashAppLastCheckLabel = computed(() =>
    props.billing.cash_app.settings.last_checked_at ? formatDateTime(props.billing.cash_app.settings.last_checked_at) : 'Never checked',
);

const labelize = (value?: string | null) => value ? value.replaceAll('_', ' ') : 'Not set';
const formatDateTime = (value?: string | null) => value ? new Date(value).toLocaleString() : 'Not recorded';
</script>

<template>
    <Head title="Billing and Revenue" />

    <div class="space-y-8">
        <section class="flex items-start justify-between gap-4 border-b border-stone-300/70 pb-4">
            <div>
                <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Office billing</p>
                <h1 class="text-2xl font-semibold tracking-tight text-stone-950">Billing and revenue</h1>
                <p class="text-sm text-stone-600">Use real payment data instead of placeholder revenue and keep the office payment lane in one place.</p>
            </div>
            <DashboardWorkspaceNav />
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <MetricTile label="Recorded revenue" :value="formatCurrency(billing.stats.recorded_revenue)" hint="Paid events logged in this office" />
            <MetricTile label="Last payment" :value="lastPaymentLabel" hint="Most recent paid event" />
            <MetricTile label="Billing profiles" :value="formatNumber(billing.profiles.length)" hint="Clients with a billing setup saved" />
            <MetricTile label="Active billing" :value="formatNumber(activeBillingProfiles)" hint="Profiles marked active or trial" />
            <MetricTile label="Zelle collected" :value="formatCurrency(billing.zelle.stats.processed_amount)" hint="Matched Zelle payments saved into client billing" />
            <MetricTile label="Zelle review" :value="formatNumber(billing.zelle.stats.needs_review_count)" hint="Zelle messages needing memo/client/header review" />
            <MetricTile label="Zelle ledger" :value="formatNumber(billing.zelle.stats.total_messages)" hint="Saved Zelle payment messages with transaction IDs" />
            <MetricTile label="Inbox cleanup" :value="formatNumber(billing.zelle.stats.deleted_count)" hint="Imported messages deleted from the mailbox" />
        </section>

        <section class="space-y-6 rounded-[2rem] border border-stone-300/70 bg-white/80 p-6 shadow-sm ring-1 ring-stone-200/40">
            <div class="flex items-end justify-between gap-4 border-b border-stone-300/70 pb-4">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Gateway and collections</p>
                    <h2 class="text-xl font-semibold tracking-tight text-stone-950">Keep gateway status, recurring plans, and payment events together.</h2>
                    <p class="text-sm text-stone-600">This page is where the office should track merchant-account setup, recurring client amounts, and actual collections.</p>
                </div>
                <div class="text-right text-xs uppercase tracking-[0.22em] text-stone-500">
                    <p>{{ formatNumber(recentPaymentCount) }} payment events</p>
                    <p>{{ billing.stats.last_paid_at ? formatDateTime(billing.stats.last_paid_at) : 'No payments recorded yet' }}</p>
                </div>
            </div>

            <div v-if="billing.can_manage_billing" class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                <form class="space-y-4 rounded-3xl border border-stone-300/70 bg-stone-50/70 p-5" @submit.prevent="submitGatewaySettings">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Gateway connection</p>
                        <p class="mt-1 text-sm text-stone-600">Record the merchant-account lane the office is using and whether the connection is healthy.</p>
                    </div>

                    <datalist id="creditsoft-gateway-providers">
                        <option v-for="provider in gatewayProviderSuggestions" :key="provider" :value="provider" />
                    </datalist>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="space-y-2 text-sm text-stone-700">
                            <span>Gateway provider</span>
                            <input v-model="gatewayForm.gateway_provider" list="creditsoft-gateway-providers" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Authorize.net, PaymentCloud + NMI, ACH, etc." />
                            <p class="text-xs leading-5 text-stone-500">Recommended lanes here: Authorize.net, PaymentCloud or GOAT Payments-backed gateways like Authorize.net or NMI, ACH/eCheck, and offline collection methods when needed.</p>
                        </label>
                        <label class="space-y-2 text-sm text-stone-700">
                            <span>Gateway status</span>
                            <select v-model="gatewayForm.gateway_status" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm">
                                <option value="manual">Manual</option>
                                <option value="connected">Connected</option>
                                <option value="needs_attention">Needs attention</option>
                                <option value="disconnected">Disconnected</option>
                            </select>
                        </label>
                        <label class="space-y-2 text-sm text-stone-700">
                            <span>Account label</span>
                            <input v-model="gatewayForm.gateway_account_label" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Main merchant account" />
                        </label>
                        <label class="space-y-2 text-sm text-stone-700">
                            <span>Environment</span>
                            <input v-model="gatewayForm.gateway_environment" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Live, sandbox, mixed" />
                        </label>
                        <label class="space-y-2 text-sm text-stone-700">
                            <span>Webhook status</span>
                            <input v-model="gatewayForm.webhook_status" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Healthy, pending, failing" />
                        </label>
                        <label class="space-y-2 text-sm text-stone-700">
                            <span>Connected at</span>
                            <input v-model="gatewayForm.gateway_connected_at" type="datetime-local" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" />
                        </label>
                    </div>

                    <label class="space-y-2 text-sm text-stone-700">
                        <span>Hosted payment page / portal URL</span>
                        <input v-model="gatewayForm.payment_portal_url" type="url" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="https://..." />
                    </label>

                    <label class="space-y-2 text-sm text-stone-700">
                        <span>Connection notes</span>
                        <textarea v-model="gatewayForm.notes" rows="3" class="border-input w-full rounded-2xl border bg-white px-3 py-2 text-sm" placeholder="Webhook caveats, who reconciles payouts, gateway notes, and next steps." />
                    </label>

                    <button type="submit" class="inline-flex h-10 items-center rounded-full bg-stone-900 px-5 text-sm font-medium text-white transition hover:bg-stone-700" :disabled="gatewayForm.processing">
                        Save gateway settings
                    </button>
                </form>

                <div class="space-y-6">
                    <form class="space-y-4 rounded-3xl border border-stone-300/70 bg-stone-50/70 p-5" @submit.prevent="submitBillingProfile">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Recurring billing</p>
                            <p class="mt-1 text-sm text-stone-600">Set what a client pays so MRR and member counts stop being made up.</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <label class="space-y-2 text-sm text-stone-700 xl:col-span-2">
                                <span>Client</span>
                                <select v-model="profileForm.client_id" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm">
                                    <option value="">Choose client</option>
                                    <option v-for="client in billing.clients" :key="client.id" :value="String(client.id)">{{ client.display_name }}</option>
                                </select>
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Status</span>
                                <select v-model="profileForm.status" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm">
                                    <option value="active">Active</option>
                                    <option value="trial">Trial</option>
                                    <option value="paused">Paused</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Amount</span>
                                <input v-model="profileForm.amount" type="number" step="0.01" min="0" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="149.00" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Currency</span>
                                <input v-model="profileForm.currency" type="text" maxlength="3" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm uppercase" placeholder="USD" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Interval</span>
                                <select v-model="profileForm.billing_interval" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm">
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="annual">Annual</option>
                                    <option value="lifetime">Lifetime</option>
                                </select>
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Started</span>
                                <input v-model="profileForm.started_at" type="date" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Last paid</span>
                                <input v-model="profileForm.last_paid_at" type="date" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Next due</span>
                                <input v-model="profileForm.next_due_at" type="date" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Gateway name</span>
                                <input v-model="profileForm.gateway_name" list="creditsoft-gateway-providers" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Authorize.net" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Customer ID</span>
                                <input v-model="profileForm.gateway_customer_id" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="cus_..." />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Subscription ID</span>
                                <input v-model="profileForm.gateway_subscription_id" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="sub_..." />
                            </label>
                        </div>

                        <label class="space-y-2 text-sm text-stone-700">
                            <span>Profile notes</span>
                            <textarea v-model="profileForm.notes" rows="2" class="border-input w-full rounded-2xl border bg-white px-3 py-2 text-sm" placeholder="Discounts, special billing terms, annual notes, or follow-up details." />
                        </label>

                        <button type="submit" class="inline-flex h-10 items-center rounded-full bg-stone-900 px-5 text-sm font-medium text-white transition hover:bg-stone-700" :disabled="profileForm.processing">
                            Save billing profile
                        </button>
                    </form>

                    <form class="space-y-4 rounded-3xl border border-stone-300/70 bg-stone-50/70 p-5" @submit.prevent="submitPayment">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Record payment</p>
                            <p class="mt-1 text-sm text-stone-600">Log when someone actually paid so the office reflects real collections.</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <label class="space-y-2 text-sm text-stone-700 xl:col-span-2">
                                <span>Client</span>
                                <select v-model="paymentForm.client_id" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm">
                                    <option value="">Choose client</option>
                                    <option v-for="client in billing.clients" :key="client.id" :value="String(client.id)">{{ client.display_name }}</option>
                                </select>
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Amount</span>
                                <input v-model="paymentForm.amount" type="number" step="0.01" min="0" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="149.00" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Status</span>
                                <select v-model="paymentForm.status" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm">
                                    <option value="paid">Paid</option>
                                    <option value="pending">Pending</option>
                                    <option value="failed">Failed</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Currency</span>
                                <input v-model="paymentForm.currency" type="text" maxlength="3" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm uppercase" placeholder="USD" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Paid at</span>
                                <input v-model="paymentForm.paid_at" type="datetime-local" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Gateway</span>
                                <input v-model="paymentForm.gateway_name" list="creditsoft-gateway-providers" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Authorize.net" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Transaction ID</span>
                                <input v-model="paymentForm.gateway_transaction_id" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="pi_..." />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Reference</span>
                                <input v-model="paymentForm.reference" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Invoice or note" />
                            </label>
                        </div>

                        <label class="space-y-2 text-sm text-stone-700">
                            <span>Payment notes</span>
                            <textarea v-model="paymentForm.notes" rows="2" class="border-input w-full rounded-2xl border bg-white px-3 py-2 text-sm" placeholder="Manual collection, retry, refund reason, or anything ops should know." />
                        </label>

                        <button type="submit" class="inline-flex h-10 items-center rounded-full bg-emerald-600 px-5 text-sm font-medium text-white transition hover:bg-emerald-500" :disabled="paymentForm.processing">
                            Save payment
                        </button>
                    </form>
                </div>
            </div>

            <p
                v-else
                class="rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 px-4 py-5 text-sm text-stone-500"
            >
                Billing details are visible here, but only management accounts can edit gateway settings, recurring amounts, and payment records.
            </p>

            <section class="space-y-5 rounded-[1.75rem] border border-violet-200 bg-violet-50/45 p-5 ring-1 ring-violet-100/70">
                <div class="flex flex-col gap-4 border-b border-violet-200/80 pb-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex items-start gap-5">
                        <div class="shrink-0 pt-1">
                            <img :src="zelleLogo" alt="Zelle" class="h-8 w-auto" />
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight text-stone-950">Match Zelle memos to client email.</h2>
                            <p class="mt-1 max-w-3xl text-sm leading-6 text-stone-600">
                                This inbox should only receive bank/Zelle payment notices. CreditSoft reads the exact subject, checks Chase/Zelle sender headers,
                                pulls the amount, memo, transaction number, and date, then matches the memo email to a client record.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form v-if="billing.can_manage_billing" @submit.prevent="syncZelle">
                            <button
                                type="submit"
                                class="inline-flex h-10 items-center rounded-full bg-violet-700 px-5 text-sm font-medium text-white transition hover:bg-violet-600 disabled:opacity-60"
                                :disabled="zelleSyncForm.processing || !billing.zelle.imap_enabled"
                            >
                                Sync Zelle inbox
                            </button>
                        </form>
                        <span class="inline-flex h-10 items-center rounded-full border border-violet-200 bg-white px-4 text-xs font-semibold uppercase tracking-[0.18em] text-violet-700">
                            {{ billing.zelle.settings.enabled ? 'Enabled' : 'Off' }}
                        </span>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-violet-200 bg-white/85 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-violet-700">Processed</p>
                        <p class="mt-2 text-2xl font-semibold text-stone-950">{{ formatCurrency(billing.zelle.stats.processed_amount) }}</p>
                        <p class="mt-1 text-xs text-stone-500">{{ formatNumber(billing.zelle.stats.processed_count) }} client payments created</p>
                    </div>
                    <div class="rounded-2xl border border-violet-200 bg-white/85 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-violet-700">Needs review</p>
                        <p class="mt-2 text-2xl font-semibold text-stone-950">{{ formatCurrency(billing.zelle.stats.needs_review_amount) }}</p>
                        <p class="mt-1 text-xs text-stone-500">{{ formatNumber(billing.zelle.stats.needs_review_count) }} memo, client, or header items</p>
                    </div>
                    <div class="rounded-2xl border border-violet-200 bg-white/85 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-violet-700">Last sync</p>
                        <p class="mt-2 text-sm font-semibold text-stone-950">{{ zelleLastSyncLabel }}</p>
                        <p class="mt-1 text-xs text-stone-500">{{ billing.zelle.settings.last_error || 'No sync error saved' }}</p>
                    </div>
                    <div class="rounded-2xl border border-violet-200 bg-white/85 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-violet-700">Last payment mail</p>
                        <p class="mt-2 text-sm font-semibold text-stone-950">{{ zelleLastReceivedLabel }}</p>
                        <p class="mt-1 text-xs text-stone-500">{{ formatNumber(billing.zelle.stats.deleted_count) }} imported messages cleaned from inbox</p>
                    </div>
                </div>

                <div v-if="billing.can_manage_billing" class="grid gap-5 xl:grid-cols-[0.85fr_1.15fr]">
                    <form class="space-y-4 rounded-3xl border border-violet-200 bg-white/85 p-5" @submit.prevent="submitZelleSettings">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-violet-700">Inbox setup</p>
                                <p class="mt-1 text-sm text-stone-600">Use the dedicated bank-approved Zelle email, not a general support inbox.</p>
                            </div>
                            <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">
                                <input v-model="zelleForm.enabled" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-violet-700" />
                                Enabled
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Bank</span>
                                <input v-model="zelleForm.bank_name" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Chase" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Mailbox username</span>
                                <input v-model="zelleForm.imap_username" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="z@yourdomain.com" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>IMAP host</span>
                                <input v-model="zelleForm.imap_host" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="mail.yourdomain.com" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Password</span>
                                <input v-model="zelleForm.imap_password" type="password" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Paste only to replace saved password" />
                                <p v-if="billing.zelle.settings.has_password" class="text-xs text-stone-500">{{ billing.zelle.settings.masked_password }}</p>
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Port</span>
                                <input v-model="zelleForm.imap_port" type="number" min="1" max="65535" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Security</span>
                                <select v-model="zelleForm.imap_encryption" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm">
                                    <option value="ssl">SSL/TLS</option>
                                    <option value="tls">STARTTLS</option>
                                    <option value="none">None</option>
                                </select>
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Folder</span>
                                <input v-model="zelleForm.imap_folder" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="INBOX" />
                            </label>
                            <label class="flex items-end gap-2 rounded-2xl border border-violet-100 bg-violet-50/80 px-4 py-3 text-sm text-stone-700">
                                <input v-model="zelleForm.delete_after_import" type="checkbox" class="mb-1 h-4 w-4 rounded border-stone-300 text-violet-700" />
                                <span>Delete imported payment emails after they are saved to the ledger.</span>
                            </label>
                        </div>

                        <label class="space-y-2 text-sm text-stone-700">
                            <span>Exact subject to trust</span>
                            <input v-model="zelleForm.expected_subject" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" />
                        </label>

                        <label class="space-y-2 text-sm text-stone-700">
                            <span>Trusted sender domains</span>
                            <textarea v-model="zelleForm.trusted_domains" rows="2" class="border-input w-full rounded-2xl border bg-white px-3 py-2 text-sm" placeholder="chase.com,zellepay.com,zelle.com,jpmorgan.com" />
                        </label>

                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900">
                            Keep this inbox dedicated to Zelle/Chase notices only. The fastest clean match is the client email address in the Zelle memo.
                        </div>

                        <button type="submit" class="inline-flex h-10 items-center rounded-full bg-stone-900 px-5 text-sm font-medium text-white transition hover:bg-stone-700" :disabled="zelleForm.processing">
                            Save Zelle mailbox
                        </button>
                    </form>

                    <section class="space-y-3 rounded-3xl border border-violet-200 bg-white/85 p-5">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-violet-700">Transaction ledger</p>
                            <p class="mt-1 text-sm text-stone-600">Every saved Zelle message keeps amount, transaction number, memo, bank proof, client match, and inbox cleanup state.</p>
                        </div>

                        <div class="space-y-2">
                            <div v-for="message in billing.zelle.messages" :key="message.id" class="rounded-2xl border border-stone-200 bg-stone-50/70 px-4 py-3">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div class="min-w-0">
                                        <p class="font-medium text-stone-950">{{ message.client_name || message.sender_name || 'Unmatched Zelle payment' }}</p>
                                        <p class="text-sm text-stone-500">
                                            {{ message.memo_email || message.memo_text || 'No memo email detected' }}
                                        </p>
                                        <p class="mt-1 text-xs leading-5 text-stone-500">
                                            Txn {{ message.transaction_id || 'missing' }} · {{ labelize(message.match_type) }} · {{ labelize(message.header_status) }}
                                        </p>
                                    </div>
                                    <div class="text-left text-xs uppercase tracking-[0.18em] text-stone-500 md:text-right">
                                        <p class="text-sm font-semibold text-stone-950">{{ message.amount !== null && message.amount !== undefined ? formatCurrency(message.amount) : 'No amount' }}</p>
                                        <p>{{ labelize(message.status) }}</p>
                                        <p>{{ message.sent_on || (message.received_at ? formatDate(message.received_at) : 'No date') }}</p>
                                        <p v-if="message.deleted_from_mailbox_at">Inbox cleaned</p>
                                    </div>
                                </div>
                            </div>
                            <p v-if="billing.zelle.messages.length === 0" class="rounded-2xl border border-dashed border-violet-200 bg-violet-50/60 px-4 py-5 text-sm text-stone-500">
                                No Zelle messages are saved yet. Save the mailbox settings, then sync the inbox after a payment notice arrives.
                            </p>
                        </div>
                    </section>
                </div>

                <p v-else class="rounded-2xl border border-dashed border-violet-200 bg-white/70 px-4 py-5 text-sm text-stone-500">
                    Zelle payment history is visible here, but only management accounts can edit mailbox credentials or run an inbox sync.
                </p>

                <p v-if="!billing.zelle.imap_enabled" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    PHP IMAP is not loaded in the running intranet PHP process. If IMAP was just installed, restart the 127.0.0.1 PHP server so CreditSoft can read the Zelle mailbox.
                </p>
            </section>

            <section class="space-y-5 rounded-[1.75rem] border border-emerald-200 bg-emerald-50/55 p-5 ring-1 ring-emerald-100/80">
                <div class="flex flex-col gap-4 border-b border-emerald-200/80 pb-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex items-start gap-5">
                        <div class="shrink-0 pt-1">
                            <img :src="cashAppLogo" alt="Cash App" class="h-8 w-auto" />
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold tracking-tight text-stone-950">Create real Cash App Pay requests.</h2>
                            <p class="mt-1 max-w-3xl text-sm leading-6 text-stone-600">
                                CreditSoft calls the Cash App Pay Customer Request API when credentials are saved. If the required Client ID and Scope ID are missing, request creation stays blocked and shows exactly what setup is missing.
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex h-10 items-center rounded-full border border-emerald-200 bg-white px-4 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">
                        {{ billing.cash_app.settings.configured ? 'API configured' : 'Setup required' }}
                    </span>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-emerald-200 bg-white/85 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-emerald-700">API requests</p>
                        <p class="mt-2 text-2xl font-semibold text-stone-950">{{ formatNumber(billing.cash_app.stats.total_requests) }}</p>
                        <p class="mt-1 text-xs text-stone-500">Saved Cash App Pay request records</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-white/85 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-emerald-700">Pending</p>
                        <p class="mt-2 text-2xl font-semibold text-stone-950">{{ formatNumber(billing.cash_app.stats.pending_count) }}</p>
                        <p class="mt-1 text-xs text-stone-500">Waiting on customer approval</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-white/85 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-emerald-700">Approved / paid</p>
                        <p class="mt-2 text-2xl font-semibold text-stone-950">{{ formatNumber(billing.cash_app.stats.approved_count + billing.cash_app.stats.paid_count) }}</p>
                        <p class="mt-1 text-xs text-stone-500">Returned by Cash App API sync</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-white/85 p-4">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-emerald-700">Last API check</p>
                        <p class="mt-2 text-sm font-semibold text-stone-950">{{ cashAppLastCheckLabel }}</p>
                        <p class="mt-1 text-xs text-stone-500">{{ billing.cash_app.settings.last_error || 'No Cash App API error saved' }}</p>
                    </div>
                </div>

                <p v-if="billing.cash_app.settings.blocked_reason" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                    {{ billing.cash_app.settings.blocked_reason }}
                </p>

                <div v-if="billing.can_manage_billing" class="grid gap-5 xl:grid-cols-[0.95fr_1.05fr]">
                    <form class="space-y-4 rounded-3xl border border-emerald-200 bg-white/85 p-5" @submit.prevent="submitCashAppSettings">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-emerald-700">API setup</p>
                                <p class="mt-1 text-sm text-stone-600">Customer Request API needs Client ID and Scope ID. Network capture also needs API key, secret, region, and Merchant ID.</p>
                            </div>
                            <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">
                                <input v-model="cashAppSettingsForm.enabled" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-emerald-700" />
                                Enabled
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Environment</span>
                                <select v-model="cashAppSettingsForm.environment" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm">
                                    <option value="sandbox">Sandbox</option>
                                    <option value="production">Production</option>
                                </select>
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>API base URL</span>
                                <input v-model="cashAppSettingsForm.api_base_url" type="url" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="https://sandbox.api.cash.app" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Client ID</span>
                                <input v-model="cashAppSettingsForm.client_id" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Cash App client id" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Scope ID</span>
                                <input v-model="cashAppSettingsForm.scope_id" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Merchant, brand, or client scope id" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>API key ID</span>
                                <input v-model="cashAppSettingsForm.api_key_id" type="password" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Paste only to replace saved key id" />
                                <p v-if="billing.cash_app.settings.has_api_key_id" class="text-xs text-stone-500">{{ billing.cash_app.settings.masked_api_key_id }}</p>
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>API secret</span>
                                <input v-model="cashAppSettingsForm.api_secret" type="password" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Paste only to replace saved secret" />
                                <p v-if="billing.cash_app.settings.has_api_secret" class="text-xs text-stone-500">{{ billing.cash_app.settings.masked_api_secret }}</p>
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Region</span>
                                <input v-model="cashAppSettingsForm.region" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="PDX" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Merchant ID</span>
                                <input v-model="cashAppSettingsForm.merchant_id" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="MMI_..." />
                            </label>
                        </div>

                        <label class="space-y-2 text-sm text-stone-700">
                            <span>Redirect URL</span>
                            <input v-model="cashAppSettingsForm.redirect_url" type="url" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="https://..." />
                        </label>

                        <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>User-Agent</span>
                                <input v-model="cashAppSettingsForm.user_agent" type="text" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" />
                            </label>
                            <label class="flex items-end gap-2 rounded-2xl border border-emerald-100 bg-emerald-50/80 px-4 py-3 text-sm text-stone-700">
                                <input v-model="cashAppSettingsForm.auto_capture" type="checkbox" class="mb-1 h-4 w-4 rounded border-stone-300 text-emerald-700" />
                                <span>Auto capture after grant approval</span>
                            </label>
                        </div>

                        <button type="submit" class="inline-flex h-10 items-center rounded-full bg-stone-900 px-5 text-sm font-medium text-white transition hover:bg-stone-700" :disabled="cashAppSettingsForm.processing">
                            Save Cash App API
                        </button>
                    </form>

                    <section class="space-y-4 rounded-3xl border border-emerald-200 bg-white/85 p-5">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-emerald-700">Create API request</p>
                            <p class="mt-1 text-sm text-stone-600">This sends a real Customer Request API call. It does not create placeholder records when setup is missing.</p>
                        </div>

                        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="createCashAppRequest">
                            <label class="space-y-2 text-sm text-stone-700 md:col-span-2">
                                <span>Client</span>
                                <select v-model="cashAppRequestForm.client_id" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm">
                                    <option value="">No client link</option>
                                    <option v-for="client in billing.clients" :key="client.id" :value="String(client.id)">{{ client.display_name }}</option>
                                </select>
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Amount</span>
                                <input v-model="cashAppRequestForm.amount" type="number" step="0.01" min="0.01" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="89.95" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700">
                                <span>Currency</span>
                                <input v-model="cashAppRequestForm.currency" type="text" maxlength="3" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm uppercase" placeholder="USD" />
                            </label>
                            <label class="space-y-2 text-sm text-stone-700 md:col-span-2">
                                <span>Override redirect URL</span>
                                <input v-model="cashAppRequestForm.redirect_url" type="url" class="border-input h-10 w-full rounded-xl border bg-white px-3 text-sm" placeholder="Use saved redirect URL" />
                            </label>
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-full bg-emerald-700 px-5 text-sm font-medium text-white transition hover:bg-emerald-600 disabled:opacity-60 md:col-span-2" :disabled="cashAppRequestForm.processing || !billing.cash_app.settings.configured">
                                Create Cash App Pay request
                            </button>
                        </form>

                        <div class="space-y-2">
                            <div v-for="request in billing.cash_app.requests" :key="request.id" class="rounded-2xl border border-stone-200 bg-stone-50/70 px-4 py-3">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div class="min-w-0">
                                        <p class="font-medium text-stone-950">{{ request.client_name || 'Cash App API request' }}</p>
                                        <p class="text-sm text-stone-500">{{ request.cash_app_request_id || request.reference_id }}</p>
                                        <p v-if="request.last_error" class="mt-1 text-xs leading-5 text-rose-700">{{ request.last_error }}</p>
                                        <div class="mt-2 flex flex-wrap gap-3 text-xs font-semibold text-emerald-700">
                                            <a v-if="request.desktop_url" :href="request.desktop_url" target="_blank" rel="noreferrer">Open desktop URL</a>
                                            <a v-if="request.mobile_url" :href="request.mobile_url" target="_blank" rel="noreferrer">Open mobile URL</a>
                                            <a v-if="request.qr_code_image_url" :href="request.qr_code_image_url" target="_blank" rel="noreferrer">Open QR</a>
                                        </div>
                                    </div>
                                    <div class="text-left text-xs uppercase tracking-[0.18em] text-stone-500 md:text-right">
                                        <p class="text-sm font-semibold text-stone-950">{{ formatCurrency(request.amount) }}</p>
                                        <p>{{ labelize(request.status) }}</p>
                                        <p>{{ request.expires_at ? `Expires ${formatDateTime(request.expires_at)}` : (request.created_at ? formatDateTime(request.created_at) : 'No API date') }}</p>
                                        <button type="button" class="mt-2 inline-flex h-8 items-center rounded-full border border-emerald-200 bg-white px-3 text-[11px] font-semibold text-emerald-700 transition hover:border-emerald-400" :disabled="cashAppSyncForm.processing || !request.cash_app_request_id" @click="syncCashAppRequest(request.id)">
                                            Sync API
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <p v-if="billing.cash_app.requests.length === 0" class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/70 px-4 py-5 text-sm text-stone-500">
                                No Cash App Pay API requests have been created yet.
                            </p>
                        </div>
                    </section>
                </div>

                <p v-else class="rounded-2xl border border-dashed border-emerald-200 bg-white/70 px-4 py-5 text-sm text-stone-500">
                    Cash App Pay API history is visible here, but only management accounts can edit credentials or create requests.
                </p>
            </section>

            <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <section class="space-y-3">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Client billing roster</p>
                        <p class="text-sm text-stone-600">This is the real MRR roster driving the office revenue line.</p>
                    </div>
                    <div class="space-y-2">
                        <div v-for="profile in billing.profiles" :key="profile.id" class="rounded-2xl border border-stone-300/70 bg-stone-50/70 px-4 py-3">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-stone-900">{{ profile.client_name }}</p>
                                    <p class="text-sm text-stone-500">
                                        {{ formatCurrency(profile.amount) }} {{ profile.billing_interval }} · {{ profile.status }}
                                    </p>
                                    <p v-if="profile.notes" class="mt-1 text-xs leading-5 text-stone-500">{{ profile.notes }}</p>
                                </div>
                                <div class="text-right text-xs uppercase tracking-[0.22em] text-stone-500">
                                    <p>{{ formatCurrency(profile.monthly_amount) }}/mo</p>
                                    <p>{{ profile.last_paid_at ? `Paid ${formatDate(profile.last_paid_at)}` : 'No payment yet' }}</p>
                                    <p>{{ profile.next_due_at ? `Due ${formatDate(profile.next_due_at)}` : 'No next due date' }}</p>
                                </div>
                            </div>
                        </div>
                        <p v-if="billing.profiles.length === 0" class="rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 px-4 py-5 text-sm text-stone-500">
                            No billing profiles yet. Use the setup forms above to save what clients actually pay.
                        </p>
                    </div>
                </section>

                <section class="space-y-3">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Recent payments</p>
                        <p class="text-sm text-stone-600">Latest payment events the office has recorded.</p>
                    </div>
                    <div class="space-y-2">
                        <div v-for="payment in billing.recent_payments" :key="payment.id" class="rounded-2xl border border-stone-300/70 bg-stone-50/70 px-4 py-3">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-stone-900">{{ payment.client_name }}</p>
                                    <p class="text-sm text-stone-500">{{ payment.gateway_name || 'Manual gateway' }} · {{ payment.status }}</p>
                                    <p v-if="payment.reference || payment.gateway_transaction_id" class="mt-1 text-xs text-stone-500">
                                        {{ payment.reference || payment.gateway_transaction_id }}
                                    </p>
                                </div>
                                <div class="text-right text-xs uppercase tracking-[0.22em] text-stone-500">
                                    <p>{{ formatCurrency(payment.amount) }}</p>
                                    <p>{{ payment.paid_at ? formatDateTime(payment.paid_at) : 'No payment date' }}</p>
                                </div>
                            </div>
                        </div>
                        <p v-if="billing.recent_payments.length === 0" class="rounded-2xl border border-dashed border-stone-300 bg-stone-50/60 px-4 py-5 text-sm text-stone-500">
                            No payments logged yet. Record the first one here so CreditSoft starts reflecting real collections.
                        </p>
                    </div>
                </section>
            </div>
        </section>
    </div>
</template>
