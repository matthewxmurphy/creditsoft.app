<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faFileImport, faFloppyDisk, faPlus, faTrashCan } from '@fortawesome/free-solid-svg-icons';
import DashboardWorkspaceNav from '@/components/creditsoft/DashboardWorkspaceNav.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Growth settings',
                href: '/settings/growth',
            },
        ],
    },
});

type BookingLink = {
    name: string;
    url: string;
    channel: string;
};

type Template = {
    name: string;
    type: string;
    subject: string;
};

type SendGridSettings = {
    enabled: boolean;
    api_key: string;
    from_name: string;
    from_email: string;
    reply_to_email: string;
};

type SesSettings = {
    enabled: boolean;
    access_key: string;
    secret_key: string;
    region: string;
    configuration_set: string;
    from_name: string;
    from_email: string;
    reply_to_email: string;
};

type MessagingSettings = {
    email_enabled: boolean;
    sms_enabled: boolean;
    provider: string;
    zapier_enabled: boolean;
    from_name: string;
    from_email: string;
    reply_to_email: string;
    sms_sender: string;
    sendgrid: SendGridSettings;
    ses: SesSettings;
    zapier_webhook_url: string;
    templates: Template[];
};

type CompanyProfile = {
    company_name: string;
    customer_portal_link: string;
    support_email: string;
    business_phone: string;
    security_sms_phone: string;
    fax: string;
    timezone_mode: string;
    allow_round_robin: boolean;
    assigned_only_default: boolean;
    internal_note: string;
};

type CreditReason = {
    reason: string;
    group: string;
    bureau: string;
    round: string;
};

type CrmField = {
    label: string;
    key: string;
    type: string;
    target: string;
    required: boolean;
};

type Affiliate = {
    first_name: string;
    last_name: string;
    company: string;
    email: string;
    phone: string;
    assigned_to: string;
};

const props = defineProps<{
    settings: {
        company_profile: CompanyProfile;
        signup_process: {
            default_name: string;
            shareable_link: string;
            default_sales_rep: string;
            api_driven: boolean;
            intake_endpoint: string;
            portal_uploads_to_backend: boolean;
            document_upload_endpoint: string;
            browser_capture_endpoint: string;
            auto_audit: boolean;
            pricing_required: boolean;
            contract_required: boolean;
            booking_required: boolean;
            id_docs_required: boolean;
            mobile_app_enabled: boolean;
            auto_password_email: boolean;
            follow_up_email: boolean;
        };
        messaging: MessagingSettings;
        appointments: {
            portal_booking_name: string;
            calendar_email: string;
            links: BookingLink[];
        };
        credit_settings: {
            show_default_reasons: boolean;
            reasons: CreditReason[];
        };
        crm_fields: CrmField[];
        affiliates: Affiliate[];
        activity_history: Array<{
            user_name: string;
            title: string;
            body: string;
            relative_date: string;
        }>;
    };
}>();

const defaultCompanyProfile = (): CompanyProfile => ({
    company_name: 'CreditSoft Office',
    customer_portal_link: '',
    support_email: '',
    business_phone: '',
    security_sms_phone: '',
    fax: '',
    timezone_mode: 'browser',
    allow_round_robin: true,
    assigned_only_default: false,
    internal_note: '',
});

const defaultCreditSettings = () => ({
    show_default_reasons: true,
    reasons: [] as CreditReason[],
});

const defaultMessagingSettings = (): MessagingSettings => ({
    email_enabled: true,
    sms_enabled: false,
    provider: 'builtin',
    zapier_enabled: false,
    from_name: 'CreditSoft Office',
    from_email: '',
    reply_to_email: '',
    sms_sender: '',
    sendgrid: {
        enabled: false,
        api_key: '',
        from_name: '',
        from_email: '',
        reply_to_email: '',
    },
    ses: {
        enabled: false,
        access_key: '',
        secret_key: '',
        region: 'us-west-2',
        configuration_set: '',
        from_name: '',
        from_email: '',
        reply_to_email: '',
    },
    zapier_webhook_url: '',
    templates: [] as Template[],
});

const cloneOr = <T,>(value: T | undefined | null, fallback: T): T => {
    try {
        return JSON.parse(JSON.stringify(value ?? fallback)) as T;
    } catch {
        return JSON.parse(JSON.stringify(fallback)) as T;
    }
};
const safeActivityHistory = computed(() => Array.isArray(props.settings.activity_history) ? props.settings.activity_history : []);

const form = useForm({
    company_profile: cloneOr(props.settings.company_profile, defaultCompanyProfile()),
    signup_process: cloneOr(props.settings.signup_process, {
        default_name: 'System Default',
        shareable_link: '',
        default_sales_rep: 'Office Admin',
        api_driven: true,
        intake_endpoint: '/api/v1/clients',
        portal_uploads_to_backend: true,
        document_upload_endpoint: '/api/v1/clients/{clientCuid}/documents',
        browser_capture_endpoint: '/api/v1/browser-companion/intake',
        auto_audit: true,
        pricing_required: true,
        contract_required: true,
        booking_required: true,
        id_docs_required: true,
        mobile_app_enabled: false,
        auto_password_email: true,
        follow_up_email: true,
    }),
    messaging: cloneOr(props.settings.messaging, defaultMessagingSettings()),
    appointments: cloneOr(props.settings.appointments, {
        portal_booking_name: 'Detailed Credit Analysis Consultation',
        calendar_email: '',
        links: [] as BookingLink[],
    }),
    credit_settings: cloneOr(props.settings.credit_settings, defaultCreditSettings()),
    crm_fields: cloneOr(props.settings.crm_fields, [] as CrmField[]),
    affiliates: cloneOr(props.settings.affiliates, [] as Affiliate[]),
    activity_history: cloneOr(props.settings.activity_history, [] as Array<{
        user_name: string;
        title: string;
        body: string;
        relative_date: string;
    }>),
});

const activityImportForm = useForm<{
    activity_csv: File | null;
}>({
    activity_csv: null,
});

const addBookingLink = () => {
    form.appointments.links.push({
        name: '',
        url: '',
        channel: 'general',
    });
};

const addTemplate = () => {
    form.messaging.templates.push({
        name: '',
        type: '',
        subject: '',
    });
};

const addCreditReason = () => {
    form.credit_settings.reasons.push({
        reason: '',
        group: 'Account',
        bureau: 'all',
        round: 'any',
    });
};

const addCrmField = () => {
    form.crm_fields.push({
        label: '',
        key: '',
        type: 'text',
        target: 'client',
        required: false,
    });
};

const addAffiliate = () => {
    form.affiliates.push({
        first_name: '',
        last_name: '',
        company: '',
        email: '',
        phone: '',
        assigned_to: '',
    });
};

const submit = () => {
    form.put('/settings/growth', {
        preserveScroll: true,
    });
};

const importActivity = () => {
    activityImportForm.post('/settings/growth/activity-import', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            activityImportForm.reset();
        },
    });
};

const activityStats = computed(() => ({
    total: safeActivityHistory.value.length,
    recentImports: safeActivityHistory.value.filter((entry) => String(entry.title ?? '').toLowerCase().includes('credit report imported')).length,
    appointments: safeActivityHistory.value.filter((entry) => String(entry.title ?? '').toLowerCase().includes('appointment')).length,
}));
</script>

<template>
    <Head title="Growth Settings" />

    <div class="space-y-6">
        <DashboardWorkspaceNav />

        <section class="space-y-2">
            <h1 class="text-xl font-semibold text-stone-950">Growth data and credit control room</h1>
            <p class="max-w-3xl text-sm leading-6 text-stone-600">
                Pull the best parts of the old admin lanes into CreditSoft: office profile, Growth data, credit settings, signup flow, booking links, built-in email delivery, optional provider integrations, CRM fields, affiliates, and team activity history.
            </p>
        </section>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Growth data</p>
                    <h2 class="mt-2 text-lg font-semibold text-stone-950">Office profile and staff rules</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-600">
                        Pull the practical office settings out of the old admin: public phone, portal link, timezone rules, and whether the system should default toward assigned-only or round-robin behavior.
                    </p>
                </div>
                <div class="space-y-5 px-6 py-6">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Office name</span>
                            <input v-model="form.company_profile.company_name" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Customer portal link</span>
                            <input v-model="form.company_profile.customer_portal_link" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="https://..." />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Support email</span>
                            <input v-model="form.company_profile.support_email" type="email" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Office phone</span>
                            <input v-model="form.company_profile.business_phone" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">SMS security phone</span>
                            <input v-model="form.company_profile.security_sms_phone" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Fax</span>
                            <input v-model="form.company_profile.fax" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2 lg:col-span-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Timezone mode</span>
                            <select v-model="form.company_profile.timezone_mode" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900">
                                <option value="browser">Based on browser time zone</option>
                                <option value="strict_est">Strictly EST</option>
                            </select>
                        </label>
                        <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                            <input v-model="form.company_profile.allow_round_robin" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900">Allow round robin by default</span>
                                <span class="block leading-6">Use this as the office baseline for new web-form or affiliate lead routing.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                            <input v-model="form.company_profile.assigned_only_default" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900">Assigned-only visibility by default</span>
                                <span class="block leading-6">Good for tighter ownership lanes where staff should not see every lead or client by default.</span>
                            </span>
                        </label>
                        <label class="space-y-2 lg:col-span-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Internal note</span>
                            <textarea v-model="form.company_profile.internal_note" rows="3" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Office rule, staffing note, or setup reminder"></textarea>
                        </label>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Signup process</p>
                    <h2 class="mt-2 text-lg font-semibold text-stone-950">Default onboarding flow</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-600">
                        Keep the office-compliance steps visible: pricing, contract, booking, ID docs, and auto-audit instead of treating signup like a single button.
                    </p>
                </div>
                <div class="grid gap-4 px-6 py-6 lg:grid-cols-2">
                    <label class="space-y-2">
                        <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Flow name</span>
                        <input v-model="form.signup_process.default_name" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                    </label>
                    <label class="space-y-2">
                        <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Default sales rep</span>
                        <input v-model="form.signup_process.default_sales_rep" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                    </label>
                    <label class="space-y-2 lg:col-span-2">
                        <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Shareable signup link</span>
                        <input v-model="form.signup_process.shareable_link" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="https://..." />
                    </label>
                    <div class="rounded-[24px] border border-amber-200 bg-amber-50/80 px-4 py-4 lg:col-span-2">
                        <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-amber-700">API-driven signup lane</p>
                        <p class="mt-2 text-sm leading-6 text-amber-950">
                            The signup process should not stop at a web form. When this is on, CreditSoft treats intake and client dashboard uploads as backend API actions that create or update real local dossiers.
                        </p>
                    </div>
                    <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <input v-model="form.signup_process.api_driven" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                        <span class="space-y-1 text-sm text-stone-700">
                            <span class="block font-medium text-stone-900">API-driven intake enabled</span>
                            <span class="block leading-6">Use the local API for signup and client creation instead of treating onboarding like a disconnected marketing form.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <input v-model="form.signup_process.portal_uploads_to_backend" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                        <span class="space-y-1 text-sm text-stone-700">
                            <span class="block font-medium text-stone-900">Client dashboard uploads go to backend</span>
                            <span class="block leading-6">Documents and browser captures should land inside the client dossier instead of getting trapped in a separate portal lane.</span>
                        </span>
                    </label>
                    <label class="space-y-2">
                        <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Lead intake endpoint</span>
                        <input v-model="form.signup_process.intake_endpoint" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="/api/v1/clients" />
                    </label>
                    <label class="space-y-2">
                        <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Client document upload endpoint</span>
                        <input v-model="form.signup_process.document_upload_endpoint" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="/api/v1/clients/{clientCuid}/documents" />
                    </label>
                    <label class="space-y-2 lg:col-span-2">
                        <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Browser capture endpoint</span>
                        <input v-model="form.signup_process.browser_capture_endpoint" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="/api/v1/browser-companion/intake" />
                    </label>
                    <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <input v-model="form.signup_process.auto_audit" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                        <span class="space-y-1 text-sm text-stone-700">
                            <span class="block font-medium text-stone-900">Auto-audit enabled</span>
                            <span class="block leading-6">Run the audit lane as part of the signup flow instead of asking staff to remember it later.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <input v-model="form.signup_process.pricing_required" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                        <span class="space-y-1 text-sm text-stone-700">
                            <span class="block font-medium text-stone-900">Pricing step required</span>
                            <span class="block leading-6">Keep package choice visible in the onboarding flow instead of making it feel hidden.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <input v-model="form.signup_process.contract_required" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                        <span class="space-y-1 text-sm text-stone-700">
                            <span class="block font-medium text-stone-900">Contract step required</span>
                            <span class="block leading-6">Keep agreement signing in the actual process instead of assuming it happened somewhere else.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <input v-model="form.signup_process.booking_required" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                        <span class="space-y-1 text-sm text-stone-700">
                            <span class="block font-medium text-stone-900">Booking step required</span>
                            <span class="block leading-6">Force the calendar handoff so consultations happen right after lead signup.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <input v-model="form.signup_process.id_docs_required" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                        <span class="space-y-1 text-sm text-stone-700">
                            <span class="block font-medium text-stone-900">ID docs required</span>
                            <span class="block leading-6">Collect the compliance documents early instead of chasing them later inside the client lane.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <input v-model="form.signup_process.auto_password_email" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                        <span class="space-y-1 text-sm text-stone-700">
                            <span class="block font-medium text-stone-900">Auto-password email</span>
                            <span class="block leading-6">Generate the credential email automatically when the flow is complete.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <input v-model="form.signup_process.follow_up_email" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                        <span class="space-y-1 text-sm text-stone-700">
                            <span class="block font-medium text-stone-900">Follow-up email</span>
                            <span class="block leading-6">Keep the post-signup reminder email active for leads who stop before finishing.</span>
                        </span>
                    </label>
                </div>
            </section>

            <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Credit settings</p>
                    <h2 class="mt-2 text-lg font-semibold text-stone-950">Dispute reasons and bureau policy lane</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-600">
                        Keep the office dispute-reason list under your control. Turn defaults on or off, then build the custom reasons you actually want your team using.
                    </p>
                </div>
                <div class="space-y-5 px-6 py-6">
                    <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <input v-model="form.credit_settings.show_default_reasons" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                        <span class="space-y-1 text-sm text-stone-700">
                            <span class="block font-medium text-stone-900">Show default dispute reasons</span>
                            <span class="block leading-6">Turn this off if you want dropdowns and import flows to lean fully on your office’s custom reason list.</span>
                        </span>
                    </label>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Custom reason bank</p>
                            <p class="text-sm text-stone-600">Group reasons by lane so disputes, inquiries, public records, and collections stay clean.</p>
                        </div>
                        <button type="button" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-3.5 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-400 hover:bg-stone-100" @click="addCreditReason">
                            <FontAwesomeIcon :icon="faPlus" class="text-[11px]" />
                            <span>Add reason</span>
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div v-for="(reason, index) in form.credit_settings.reasons" :key="`reason-${index}`" class="grid gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4 lg:grid-cols-[1.8fr_0.8fr_0.7fr_0.7fr_auto]">
                            <textarea v-model="reason.reason" rows="3" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Dispute reason text"></textarea>
                            <select v-model="reason.group" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900">
                                <option value="Account">Account</option>
                                <option value="Collections">Collections</option>
                                <option value="Inquiry">Inquiry</option>
                                <option value="Public Records">Public Records</option>
                                <option value="Personal Information">Personal Information</option>
                            </select>
                            <select v-model="reason.bureau" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900">
                                <option value="all">All bureaus</option>
                                <option value="equifax">Equifax</option>
                                <option value="experian">Experian</option>
                                <option value="transunion">TransUnion</option>
                            </select>
                            <select v-model="reason.round" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900">
                                <option value="any">Any round</option>
                                <option value="1">Round 1</option>
                                <option value="2">Round 2</option>
                                <option value="3">Round 3</option>
                                <option value="4+">Round 4+</option>
                            </select>
                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center self-center rounded-lg text-sm text-rose-700 transition hover:bg-rose-50" :aria-label="`Remove custom reason ${index + 1}`" title="Remove reason" @click="form.credit_settings.reasons.splice(index, 1)">
                                <FontAwesomeIcon :icon="faTrashCan" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Messaging and delivery</p>
                    <h2 class="mt-2 text-lg font-semibold text-stone-950">Email delivery, SMS, and optional external hooks</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-600">
                        CreditSoft does not need Zapier to send office email. Keep the built-in lane active, then switch to SendGrid or Amazon SES when you want a dedicated outbound provider.
                    </p>
                </div>
                <div class="space-y-6 px-6 py-6">
                    <div class="rounded-[24px] border border-emerald-200 bg-emerald-50/80 px-4 py-4 text-sm leading-6 text-emerald-900">
                        <p class="font-medium">Zapier is optional.</p>
                        <p>
                            Use it only if you want to push CreditSoft events into outside automations. Email delivery can stay fully inside the office with the built-in lane, SendGrid, or Amazon SES.
                        </p>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-3">
                        <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                            <input v-model="form.messaging.email_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900">Email enabled</span>
                                <span class="block leading-6">Keep branded delivery active from inside CreditSoft.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                            <input v-model="form.messaging.sms_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900">SMS enabled</span>
                                <span class="block leading-6">Let the office send credential and follow-up nudges.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                            <input v-model="form.messaging.zapier_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900">Zapier hook enabled</span>
                                <span class="block leading-6">Only turn this on when the office wants outside automation after the core setup already works.</span>
                            </span>
                        </label>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-[1.1fr_1fr]">
                        <div class="rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Default delivery lane</p>
                            <p class="mt-2 text-sm leading-6 text-stone-600">Pick the provider CreditSoft should treat as the office default when sending email.</p>
                            <select v-model="form.messaging.provider" class="mt-4 w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900">
                                <option value="builtin">Built-in office email</option>
                                <option value="sendgrid">SendGrid</option>
                                <option value="ses">Amazon SES</option>
                            </select>
                        </div>
                        <div class="rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Why this matters</p>
                            <ul class="mt-2 space-y-2 text-sm leading-6 text-stone-600">
                                <li>Built-in is the fastest path when you just need the office sending cleanly.</li>
                                <li>SendGrid is a good fit when you want dedicated deliverability tooling and a simple API key lane.</li>
                                <li>Amazon SES fits teams already living in AWS or wanting a lower-cost volume sender.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">From name</span>
                            <input v-model="form.messaging.from_name" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">From email</span>
                            <input v-model="form.messaging.from_email" type="email" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Reply-to email</span>
                            <input v-model="form.messaging.reply_to_email" type="email" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">SMS sender</span>
                            <input v-model="form.messaging.sms_sender" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                    </div>
                    <div class="grid gap-4 xl:grid-cols-3">
                        <div class="space-y-4 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                            <div class="space-y-1">
                                <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Built-in office email</p>
                                <h3 class="text-base font-semibold text-stone-950">Fastest setup lane</h3>
                                <p class="text-sm leading-6 text-stone-600">Use the office from-name, from-email, and reply-to fields above. No outside workflow builder required.</p>
                            </div>
                        </div>
                        <div class="space-y-4 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                            <label class="flex items-start gap-3">
                                <input v-model="form.messaging.sendgrid.enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                                <span class="space-y-1 text-sm text-stone-700">
                                    <span class="block font-medium text-stone-900">SendGrid ready</span>
                                    <span class="block leading-6">Keep this on when the office wants SendGrid available as a dedicated delivery lane.</span>
                                </span>
                            </label>
                            <label class="space-y-2">
                                <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">SendGrid API key</span>
                                <input v-model="form.messaging.sendgrid.api_key" type="password" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="SG..." />
                            </label>
                            <label class="space-y-2">
                                <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">SendGrid from name</span>
                                <input v-model="form.messaging.sendgrid.from_name" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="CreditSoft Office" />
                            </label>
                            <label class="space-y-2">
                                <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">SendGrid from email</span>
                                <input v-model="form.messaging.sendgrid.from_email" type="email" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                            </label>
                            <label class="space-y-2">
                                <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">SendGrid reply-to</span>
                                <input v-model="form.messaging.sendgrid.reply_to_email" type="email" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                            </label>
                        </div>
                        <div class="space-y-4 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                            <label class="flex items-start gap-3">
                                <input v-model="form.messaging.ses.enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                                <span class="space-y-1 text-sm text-stone-700">
                                    <span class="block font-medium text-stone-900">Amazon SES ready</span>
                                    <span class="block leading-6">Use this lane when the office wants AWS-native sending and cost-efficient outbound email.</span>
                                </span>
                            </label>
                            <label class="space-y-2">
                                <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">SES access key</span>
                                <input v-model="form.messaging.ses.access_key" type="password" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                            </label>
                            <label class="space-y-2">
                                <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">SES secret key</span>
                                <input v-model="form.messaging.ses.secret_key" type="password" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                            </label>
                            <div class="grid gap-4 lg:grid-cols-2">
                                <label class="space-y-2">
                                    <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">SES region</span>
                                    <input v-model="form.messaging.ses.region" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="us-west-2" />
                                </label>
                                <label class="space-y-2">
                                    <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Config set</span>
                                    <input v-model="form.messaging.ses.configuration_set" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Optional" />
                                </label>
                            </div>
                            <label class="space-y-2">
                                <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">SES from name</span>
                                <input v-model="form.messaging.ses.from_name" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="CreditSoft Office" />
                            </label>
                            <label class="space-y-2">
                                <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">SES from email</span>
                                <input v-model="form.messaging.ses.from_email" type="email" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                            </label>
                            <label class="space-y-2">
                                <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">SES reply-to</span>
                                <input v-model="form.messaging.ses.reply_to_email" type="email" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                            </label>
                        </div>
                    </div>
                    <div class="space-y-4 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <div class="space-y-1">
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Optional Zapier hook</p>
                            <p class="text-sm leading-6 text-stone-600">Leave this blank unless you specifically want CreditSoft to push events into a Zapier workflow after the office messaging lane already works.</p>
                        </div>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Zapier webhook</span>
                            <input v-model="form.messaging.zapier_webhook_url" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="https://hooks.zapier.com/..." />
                        </label>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Templates</p>
                                <p class="text-sm text-stone-600">Keep the core email templates visible inside CreditSoft instead of hiding the office voice inside a vendor dashboard.</p>
                            </div>
                        <button type="button" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-3.5 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-400 hover:bg-stone-100" @click="addTemplate">
                            <FontAwesomeIcon :icon="faPlus" class="text-[11px]" />
                            <span>Add template</span>
                        </button>
                        </div>
                        <div class="space-y-3">
                            <div v-for="(template, index) in form.messaging.templates" :key="`template-${index}`" class="grid gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4 lg:grid-cols-[1fr_1fr_0.9fr_auto]">
                                <input v-model="template.name" type="text" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Template name" />
                                <input v-model="template.subject" type="text" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Email subject" />
                                <input v-model="template.type" type="text" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Template type" />
                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center self-center rounded-lg text-sm text-rose-700 transition hover:bg-rose-50" :aria-label="`Remove message template ${index + 1}`" title="Remove template" @click="form.messaging.templates.splice(index, 1)">
                                <FontAwesomeIcon :icon="faTrashCan" />
                            </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Appointments</p>
                    <h2 class="mt-2 text-lg font-semibold text-stone-950">Booking links and portal handoff</h2>
                </div>
                <div class="space-y-4 px-6 py-6">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Portal booking shown to clients</span>
                            <input v-model="form.appointments.portal_booking_name" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Calendar account email</span>
                            <input v-model="form.appointments.calendar_email" type="email" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Shareable bookings</p>
                            <p class="text-sm text-stone-600">Consultations, followups, and review calls can live here even before a full calendar integration.</p>
                        </div>
                        <button type="button" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-3.5 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-400 hover:bg-stone-100" @click="addBookingLink">
                            <FontAwesomeIcon :icon="faPlus" class="text-[11px]" />
                            <span>Add booking link</span>
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div v-for="(link, index) in form.appointments.links" :key="`booking-${index}`" class="grid gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4 lg:grid-cols-[1fr_1.4fr_0.8fr_auto]">
                            <input v-model="link.name" type="text" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Booking name" />
                            <input v-model="link.url" type="text" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Shareable booking URL" />
                            <input v-model="link.channel" type="text" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Channel" />
                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center self-center rounded-lg text-sm text-rose-700 transition hover:bg-rose-50" :aria-label="`Remove booking link ${index + 1}`" title="Remove booking link" @click="form.appointments.links.splice(index, 1)">
                                <FontAwesomeIcon :icon="faTrashCan" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">CRM fields</p>
                    <h2 class="mt-2 text-lg font-semibold text-stone-950">Custom data points for leads and clients</h2>
                </div>
                <div class="space-y-4 px-6 py-6">
                    <div class="flex items-center justify-between gap-4">
                        <p class="max-w-2xl text-sm leading-6 text-stone-600">Build the fields your office actually needs on top of the core dossier instead of hoping every team works from the same fixed header list.</p>
                        <button type="button" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-3.5 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-400 hover:bg-stone-100" @click="addCrmField">
                            <FontAwesomeIcon :icon="faPlus" class="text-[11px]" />
                            <span>Add field</span>
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div v-for="(field, index) in form.crm_fields" :key="`crm-${index}`" class="grid gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4 lg:grid-cols-[1fr_1fr_0.8fr_0.8fr_auto_auto]">
                            <input v-model="field.label" type="text" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Label" />
                            <input v-model="field.key" type="text" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="field_key" />
                            <select v-model="field.type" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900">
                                <option value="text">Text</option>
                                <option value="email">Email</option>
                                <option value="textarea">Textarea</option>
                                <option value="phone">Phone</option>
                                <option value="checkbox">Checkbox</option>
                                <option value="select">Select</option>
                                <option value="radio">Radio</option>
                            </select>
                            <select v-model="field.target" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900">
                                <option value="lead">Lead</option>
                                <option value="client">Client</option>
                                <option value="both">Both</option>
                            </select>
                            <label class="flex items-center justify-center gap-2 rounded-2xl border border-stone-300 bg-white px-3 py-3 text-sm text-stone-700">
                                <input v-model="field.required" type="checkbox" class="h-4 w-4 rounded border-stone-400 text-stone-950" />
                                Required
                            </label>
                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center self-center rounded-lg text-sm text-rose-700 transition hover:bg-rose-50" :aria-label="`Remove CRM field ${index + 1}`" title="Remove CRM field" @click="form.crm_fields.splice(index, 1)">
                                <FontAwesomeIcon :icon="faTrashCan" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Affiliates</p>
                    <h2 class="mt-2 text-lg font-semibold text-stone-950">Referral partners and assigned owners</h2>
                </div>
                <div class="space-y-4 px-6 py-6">
                    <div class="flex items-center justify-between gap-4">
                        <div class="grid gap-1 text-sm text-stone-600">
                            <span>{{ form.affiliates.length }} affiliate partners on file</span>
                            <span>Use this lane for round-robin leads, outside partners, and assigned relationship owners.</span>
                        </div>
                        <button type="button" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-3.5 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-400 hover:bg-stone-100" @click="addAffiliate">
                            <FontAwesomeIcon :icon="faPlus" class="text-[11px]" />
                            <span>Add affiliate</span>
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div v-for="(affiliate, index) in form.affiliates" :key="`affiliate-${index}`" class="grid gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4 lg:grid-cols-[0.8fr_0.8fr_1fr_1fr_0.9fr_0.9fr_auto]">
                            <input v-model="affiliate.first_name" type="text" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="First name" />
                            <input v-model="affiliate.last_name" type="text" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Last name" />
                            <input v-model="affiliate.company" type="text" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Company" />
                            <input v-model="affiliate.email" type="email" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Email" />
                            <input v-model="affiliate.phone" type="text" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Phone" />
                            <input v-model="affiliate.assigned_to" type="text" class="rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Assigned to" />
                            <button type="button" class="inline-flex h-9 w-9 items-center justify-center self-center rounded-lg text-sm text-rose-700 transition hover:bg-rose-50" :aria-label="`Remove affiliate ${index + 1}`" title="Remove affiliate" @click="form.affiliates.splice(index, 1)">
                                <FontAwesomeIcon :icon="faTrashCan" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-stone-950 px-5 py-2.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-white shadow-lg shadow-stone-900/15 transition hover:bg-stone-800" :disabled="form.processing">
                    <FontAwesomeIcon :icon="faFloppyDisk" class="text-[11px]" />
                    {{ form.processing ? 'Saving growth settings...' : 'Save growth settings' }}
                </button>
            </div>
        </form>

        <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
            <div class="border-b border-stone-200/80 px-6 py-5">
                <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Team activity history</p>
                <h2 class="mt-2 text-lg font-semibold text-stone-950">Import and review recent office activity</h2>
            </div>
            <div class="space-y-5 px-6 py-6">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">Imported rows</p>
                        <p class="mt-3 text-3xl font-semibold text-stone-950">{{ activityStats.total }}</p>
                    </div>
                    <div class="rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">Report imports</p>
                        <p class="mt-3 text-3xl font-semibold text-stone-950">{{ activityStats.recentImports }}</p>
                    </div>
                    <div class="rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">Appointments</p>
                        <p class="mt-3 text-3xl font-semibold text-stone-950">{{ activityStats.appointments }}</p>
                    </div>
                </div>

                <form class="space-y-4 rounded-[24px] border border-dashed border-stone-300 bg-stone-50 px-5 py-5" @submit.prevent="importActivity">
                    <div>
                        <p class="text-sm font-medium text-stone-900">Import team activity CSV</p>
                        <p class="mt-1 text-sm leading-6 text-stone-600">Use the exported history to seed the office activity lane while we keep building out the native timeline.</p>
                    </div>
                    <input type="file" accept=".csv,text/csv" class="block w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900" @change="activityImportForm.activity_csv = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-900 transition hover:bg-stone-100" :disabled="activityImportForm.processing || !activityImportForm.activity_csv">
                        <FontAwesomeIcon :icon="faFileImport" class="text-[11px]" />
                        {{ activityImportForm.processing ? 'Importing history...' : 'Import activity history' }}
                    </button>
                </form>

                <div class="space-y-3">
                    <div v-if="safeActivityHistory.length === 0" class="rounded-[24px] border border-stone-200 bg-stone-50 px-5 py-5 text-sm leading-6 text-stone-600">
                        No activity history imported yet. Once a CSV is imported, the office can review lead conversion, report imports, uploads, and appointment events in one place.
                    </div>
                    <article v-for="(entry, index) in safeActivityHistory.slice(0, 18)" :key="`activity-${index}`" class="rounded-[24px] border border-stone-200 bg-white px-5 py-4 shadow-sm">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-stone-950">{{ entry.title || 'Activity' }}</p>
                                <p class="text-sm text-stone-600">{{ entry.user_name || 'System' }}</p>
                                <p class="text-sm leading-6 text-stone-600">{{ entry.body }}</p>
                            </div>
                            <span class="inline-flex rounded-full bg-stone-100 px-3 py-1 text-xs font-medium uppercase tracking-[0.18em] text-stone-500">{{ entry.relative_date }}</span>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </div>
</template>
