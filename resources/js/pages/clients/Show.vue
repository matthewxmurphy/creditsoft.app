<script setup lang="ts">
import { faTrashCan } from '@fortawesome/free-regular-svg-icons';
import {
    faEye,
    faEyeSlash,
    faMoneyBillWave,
    faPencil,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import AiSetupPrompt from '@/components/creditsoft/AiSetupPrompt.vue';
import BureauWordmark from '@/components/creditsoft/BureauWordmark.vue';
import ClientDocumentLightbox from '@/components/creditsoft/ClientDocumentLightbox.vue';
import ClientWorkspaceNav from '@/components/creditsoft/ClientWorkspaceNav.vue';
import MultiLineTrendChart from '@/components/creditsoft/MultiLineTrendChart.vue';
import ReviewSignalLabel from '@/components/creditsoft/ReviewSignalLabel.vue';
import ScoreGaugeDial from '@/components/creditsoft/ScoreGaugeDial.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    clientHealthBadgeClass,
    clientHealthDetail,
    clientHealthDotClass,
    clientHealthLabel,
    clientHealthPanelClass,
    clientHealthScoreLabel,
} from '@/lib/client-health';
import type { ClientHealthSignal } from '@/lib/client-health';
import { normalizeClientNameForm } from '@/lib/client-name';
import { formatDate, formatDateTime, formatNumber } from '@/lib/creditsoft';
import { formatUsPhone } from '@/lib/phone';

type ReviewSignal = {
    key: 'missing' | 'mismatch' | 'negative' | 'single';
    label: string;
    title: string;
};

type CreditReasonOption = {
    key: string;
    reason: string;
    group: string;
    bureau: string;
    round: string;
};

type ImportedScoreCard = {
    key: string;
    label: string;
    value_display: string;
    numeric?: number | null;
    grade?: string | null;
    grade_display?: string | null;
    detail_url?: string | null;
    scale?: 'score' | 'grade';
};

const props = defineProps<{
    client: {
        id: number;
        cuid?: string | null;
        display_name: string;
        first_name: string;
        middle_name?: string | null;
        last_name: string;
        name_suffix?: string | null;
        status: string;
        email?: string | null;
        secondary_email?: string | null;
        phone?: string | null;
        address_line_1?: string | null;
        address_line_2?: string | null;
        city?: string | null;
        state?: string | null;
        postal_code?: string | null;
        date_of_birth?: string | null;
        ssn_last_four?: string | null;
        current_score?: number | null;
        goals?: string | null;
        source_kind?: string | null;
        import_audit?: {
            source_kind?: string | null;
            source_assigned_to?: string | null;
            [key: string]: unknown;
        } | null;
        client_health?: ClientHealthSignal | string | null;
        billing_signal?: ClientHealthSignal | string | null;
        metadata?: {
            client_health?: ClientHealthSignal | string | null;
            [key: string]: unknown;
        } | null;
        assigned_user?: {
            id: number;
            name: string;
            email?: string | null;
        } | null;
        billing_profile?: {
            id: number;
            status: string;
            amount?: string | number | null;
            currency?: string | null;
            billing_interval?: string | null;
            last_paid_at?: string | null;
            next_due_at?: string | null;
            notes?: string | null;
            metadata?: Record<string, unknown> | null;
        } | null;
        reporting_cycles: Array<{
            id: number;
            cycle_label: string;
        }>;
        notes: Array<{ id: number; visibility: string; note: string }>;
        briefs: Array<{
            id: number;
            title: string;
            approved_at?: string | null;
        }>;
        letters: Array<{ id: number; title: string; status: string }>;
        violations: Array<{
            id: number;
            title: string;
            severity: string;
            status: string;
        }>;
        tasks: Array<{
            id: number;
            title: string;
            due_at?: string | null;
            status: string;
        }>;
        sop_runs: Array<{
            id: number;
            status: string;
            template?: { name: string } | null;
        }>;
        browser_captures: Array<{
            id: number;
            reporting_cycle_id?: number | null;
            source_type: string;
            page_title?: string | null;
            page_url?: string | null;
            extracted_text?: string | null;
            imported_at?: string | null;
            metadata?: {
                provider_key?: string | null;
                import_profile?: string | null;
                provider_capture?: {
                    provider?: string | null;
                    label?: string | null;
                    profile?: string | null;
                    bureau?: string | null;
                    as_of_date?: string | null;
                    available_report_dates?: string[];
                    bureau_scores?: Record<
                        string,
                        {
                            key: string;
                            label: string;
                            display: string;
                            score?: number | null;
                            available: boolean;
                        }
                    >;
                    scores?: Record<string, number>;
                    score_history?: Array<{
                        date: string;
                        credit?: number | null;
                        auto?: number | null;
                        insurance?: number | null;
                    }>;
                    score_history_chart?: {
                        labels: string[];
                        series: Array<{
                            key: string;
                            label: string;
                            color: string;
                            values: Array<number | null>;
                        }>;
                    } | null;
                    score_cards?: ImportedScoreCard[];
                    account_count?: number | null;
                    negative_account_count?: number | null;
                    summary?: Record<
                        string,
                        { value: string; match?: string | null }
                    >;
                    account_preview_count?: number | null;
                    account_matrix?: Array<{
                        name: string;
                        status?: string | null;
                        category?: string | null;
                        utilization?: string | null;
                        negative?: boolean;
                        coverage_label?: string | null;
                        coverage: {
                            experian: string;
                            transunion: string;
                            equifax: string;
                        };
                        evidence: Array<{
                            key: string;
                            label: string;
                            value: string;
                            match?: string | null;
                        }>;
                    }>;
                    account_matrix_count?: number | null;
                    bureau_coverage?: {
                        missing_bureaus?: string[];
                    } | null;
                } | null;
                smartcredit?: {
                    label?: string | null;
                    profile?: string | null;
                    as_of_date?: string | null;
                    available_report_dates?: string[];
                    bureau_scores?: Record<
                        string,
                        {
                            key: string;
                            label: string;
                            display: string;
                            score?: number | null;
                            available: boolean;
                        }
                    >;
                    scores?: Record<string, number>;
                    score_history?: Array<{
                        date: string;
                        credit?: number | null;
                        auto?: number | null;
                        insurance?: number | null;
                    }>;
                    score_history_chart?: {
                        labels: string[];
                        series: Array<{
                            key: string;
                            label: string;
                            color: string;
                            values: Array<number | null>;
                        }>;
                    } | null;
                    score_cards?: ImportedScoreCard[];
                    account_count?: number | null;
                    negative_account_count?: number | null;
                    summary?: Record<
                        string,
                        { value: string; match?: string | null }
                    >;
                    account_matrix?: Array<{
                        name: string;
                        status?: string | null;
                        category?: string | null;
                        utilization?: string | null;
                        negative?: boolean;
                        coverage_label?: string | null;
                        coverage: {
                            experian: string;
                            transunion: string;
                            equifax: string;
                        };
                        evidence: Array<{
                            key: string;
                            label: string;
                            value: string;
                            match?: string | null;
                        }>;
                    }>;
                    account_matrix_count?: number | null;
                    account_preview_count?: number | null;
                } | null;
                credit_karma?: {
                    label?: string | null;
                    bureau?: string | null;
                    as_of_date?: string | null;
                    scores?: Record<string, number>;
                    available_report_dates?: string[];
                    bureau_coverage?: {
                        missing_bureaus?: string[];
                    } | null;
                } | null;
            } | null;
        }>;
        documents: Array<{
            id: number;
            title: string;
            category?: string | null;
            notes?: string | null;
            file_name?: string | null;
            mime_type?: string | null;
            file_size?: number | null;
            uploaded_at?: string | null;
            reporting_cycle_id?: number | null;
            reporting_cycle?: string | null;
            file_available?: boolean;
            download_url?: string | null;
        }>;
        portal_events: Array<{
            id: number;
            source?: string | null;
            event_type: string;
            tool_key?: string | null;
            title?: string | null;
            summary?: string | null;
            message?: string | null;
            score?: number | null;
            status?: string | null;
            occurred_at?: string | null;
        }>;
        profile_snapshots?: Array<{
            id: number;
            client_cuid: string;
            source?: string | null;
            source_label?: string | null;
            is_current?: boolean;
            recorded_at?: string | null;
            mailing_label?: string | null;
            mailing_barcode?: string | null;
            mailing_barcode_symbology?: string | null;
            address_fingerprint?: string | null;
            changed_fields?: string[];
        }>;
        document_access?: {
            can_view_files: boolean;
            document_count: number;
            file_count: number;
            report_count: number;
            client_file_count: number;
            total_bytes: number;
            total_label: string;
        };
    };
    cycles: Array<{
        id: number;
        cycle_label: string;
        started_at: string;
        reviewed_at?: string | null;
        snapshot_count: number;
    }>;
    latestSummary: {
        total_accounts: number;
        over_thirty_percent: number;
        priority_disputes: number;
        changed_since_last_cycle: number;
    } | null;
    sopTemplates: Array<{
        id: number;
        name: string;
    }>;
    browserCaptureTask?: {
        label: string;
        provider?: string | null;
        model?: string | null;
    } | null;
    browserCompanion: {
        name: string;
        version: string;
        enabled: boolean;
        download_url: string | null;
    };
    browserCaptureDuplicates: {
        extra_count: number;
        group_count: number;
    };
    scoreTimeline: {
        as_of_date?: string | null;
        labels: string[];
        points: Array<{
            date: string;
            recorded_on?: string | null;
            credit?: number | null;
            auto?: number | null;
            insurance?: number | null;
        }>;
        series: Array<{
            key: string;
            label: string;
            color: string;
            values: Array<number | null>;
        }>;
        source?: {
            id: number;
            imported_at?: string | null;
            page_title?: string | null;
            provider_key?: string | null;
            provider_label?: string | null;
        } | null;
    };
    reviewState: {
        cycle_id?: number | null;
        reviewed_signatures: string[];
        dispute_signatures: string[];
        total_rows: number;
    };
    relationship: {
        can_end: boolean;
        can_delete_lead?: boolean;
        ended_at?: string | null;
        ended_reason?: string | null;
        ended_notes?: string | null;
        reason_options: Array<{
            key: string;
            label: string;
            description: string;
            outcome: string;
        }>;
    };
    creditReasonOptions: CreditReasonOption[];
    providers: Array<{
        id: number;
        provider_key: string;
        provider_label: string;
        login_email?: string | null;
        login_username?: string | null;
        status: string;
        last_imported_at?: string | null;
        notes?: string | null;
        has_stored_password: boolean;
        has_stored_security_answer: boolean;
        credential_health?: {
            blocked: boolean;
            invalidated_at?: string | null;
            invalidated_reason?: string | null;
            last_updated_at?: string | null;
            login_updated_at?: string | null;
            password_updated_at?: string | null;
            security_answer_updated_at?: string | null;
            history?: Array<Record<string, unknown>>;
        };
        metadata?: {
            archive_subject_name?: string | null;
            credentials?: {
                invalidated_at?: string | null;
                invalidated_reason?: string | null;
            } | null;
            companion?: {
                credentials?: {
                    invalid?: {
                        detected_at?: string | null;
                        reason?: string | null;
                    } | null;
                } | null;
                last_status_event?: {
                    detected_at?: string | null;
                    reason?: string | null;
                    message?: string | null;
                } | null;
            } | null;
            smartcredit?: {
                invalid_credentials?: {
                    detected_at?: string | null;
                    reason?: string | null;
                } | null;
            } | null;
            office_context?: {
                office_brand?: string | null;
                office_brand_full?: string | null;
                contact_name?: string | null;
                contact_email?: string | null;
            } | null;
        } | null;
    }>;
    providerCatalog: Array<{
        key: string;
        label: string;
        description?: string | null;
    }>;
    providerStatuses: Array<{
        key: string;
        label: string;
    }>;
}>();

type ClientDocumentRecord = (typeof props.client.documents)[number];

const activeDocumentPreview = ref<ClientDocumentRecord | null>(null);

const openDocumentPreview = (document: ClientDocumentRecord) => {
    if (!document.download_url) {
        return;
    }

    activeDocumentPreview.value = document;
};

const closeDocumentPreview = () => {
    activeDocumentPreview.value = null;
};

const page = usePage<{
    auth: {
        role?: string | null;
        can_edit_users: boolean;
    };
    creditsoft: {
        ui?: {
            review_label_style?: string | null;
        };
        ai: {
            needsSetup: boolean;
            catalog: {
                providers: Array<{
                    name: string;
                    label: string;
                    configured: boolean;
                    scope: string;
                }>;
            };
        };
    };
}>();

const clientPageTitle = computed(() => {
    const fallbackName = [
        props.client.first_name,
        props.client.middle_name,
        props.client.last_name,
        props.client.name_suffix,
    ]
        .filter(Boolean)
        .join(' ')
        .trim();

    return (
        props.client.display_name || fallbackName || `Client ${props.client.id}`
    );
});

const clientRouteKey = computed(() => String(props.client.id));

const clientHealth = computed(
    () =>
        props.client.client_health ??
        props.client.metadata?.client_health ??
        props.client.billing_signal ??
        null,
);

const clientRelationshipLabel = computed(() => {
    const sourceKind = String(
        props.client.source_kind ??
            props.client.import_audit?.source_kind ??
            '',
    );

    if (sourceKind === 'terminated') {
        return 'Terminated';
    }

    if (sourceKind === 'fired') {
        return 'Fired';
    }

    if (sourceKind === 'canceled') {
        return 'Canceled';
    }

    if (sourceKind === 'graduated') {
        return 'Graduated';
    }

    if (sourceKind === 'lead') {
        return 'Lead';
    }

    return props.client.status.replaceAll('_', ' ');
});

const reviewLabelStyle = computed(() =>
    String(page.props.creditsoft.ui?.review_label_style ?? '10'),
);

const currentSearchParams = computed(() => {
    const queryIndex = page.url.indexOf('?');

    return new URLSearchParams(
        queryIndex >= 0 ? page.url.slice(queryIndex) : '',
    );
});
const currentRosterView = computed(() => currentSearchParams.value.get('view'));
const clientProfileHref = (suffix = '') => {
    const params = new URLSearchParams();

    if (currentRosterView.value) {
        params.set('view', currentRosterView.value);
    }

    const query = params.toString();

    return `/clients/${clientRouteKey.value}${suffix}${query ? `?${query}` : ''}`;
};
const clientProfilePanelReturnHref = () => clientProfileHref();

const latestCycleId = props.cycles[0]?.id?.toString() ?? '';
const showAiSetup = ref(page.props.creditsoft.ai.needsSetup);
const editingProviderId = ref<number | null>(null);
type RevealedProviderCredentials = {
    login_password?: string | null;
    security_answer?: string | null;
    has_stored_password?: boolean;
    has_stored_security_answer?: boolean;
};
const revealedProviderCredentials = reactive<
    Record<number, RevealedProviderCredentials>
>({});
const revealProviderErrors = reactive<Record<number, string>>({});
const revealingProviderId = ref<number | null>(null);
const providerPanelRequested = computed(() =>
    page.url.includes('panel=providers'),
);
const importPanelRequested = computed(() => page.url.includes('panel=import'));
const relationshipPanelRequested = computed(() =>
    page.url.includes('panel=relationship'),
);
const focusPanelMode = computed(
    () => providerPanelRequested.value || importPanelRequested.value,
);
const showProviderEditor = ref(
    props.providers.length === 0 || providerPanelRequested.value,
);
const showBrowserCaptureEditor = ref(importPanelRequested.value);
const capturePendingDelete = ref<
    (typeof props.client.browser_captures)[number] | null
>(null);
const deleteConfirmationArmed = ref(false);
const providerSectionId = 'credit-monitoring-panel';
const importSectionId = 'import-tools-panel';
const billingSectionId = 'client-billing-panel';
const relationshipSectionId = 'client-relationship-panel';
type ClientProcessAction =
    | 'client_info'
    | 'assignment'
    | 'providers'
    | 'portal'
    | 'onboarding'
    | 'billing'
    | 'import'
    | 'violations'
    | 'letters';
type ClientProcessChecklistItem = {
    number: number;
    title: string;
    status: 'complete' | 'ready' | 'pending';
    detail: string;
    actionLabel?: string;
    action?: ClientProcessAction;
};

function scrollToPanel(panelId: string) {
    nextTick(() => {
        document.getElementById(panelId)?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    });
}

const cycleForm = useForm({
    cycle_label: '',
    source: 'manual',
    started_at: new Date().toISOString().slice(0, 10),
});

const snapshotForm = useForm<{
    reporting_cycle_id: string;
    bureau: string;
    source: string;
    report_file: File | null;
    creditor_name: string;
    account_type: string;
    balance: string;
    credit_limit: string;
    account_status: string;
    payment_status: string;
    remarks: string;
}>({
    reporting_cycle_id: latestCycleId,
    bureau: 'experian',
    source: 'manual',
    report_file: null,
    creditor_name: '',
    account_type: 'revolving',
    balance: '',
    credit_limit: '',
    account_status: '',
    payment_status: '',
    remarks: '',
});

const browserCaptureForm = useForm<{
    reporting_cycle_id: string;
    source_type: string;
    browser_name: string;
    page_title: string;
    page_url: string;
    html: string;
    capture_file: File | null;
}>({
    reporting_cycle_id: latestCycleId,
    source_type: 'dom_capture',
    browser_name: 'CreditSoft companion',
    page_title: '',
    page_url: '',
    html: '',
    capture_file: null,
});

const clientProfileForm = useForm({
    first_name: props.client.first_name ?? '',
    middle_name: props.client.middle_name ?? '',
    last_name: props.client.last_name ?? '',
    name_suffix: props.client.name_suffix ?? '',
    email: props.client.email ?? '',
    secondary_email: props.client.secondary_email ?? '',
    phone: props.client.phone ?? '',
    address_line_1: props.client.address_line_1 ?? '',
    address_line_2: props.client.address_line_2 ?? '',
    city: props.client.city ?? '',
    state: props.client.state ?? '',
    postal_code: props.client.postal_code ?? '',
    date_of_birth: props.client.date_of_birth
        ? String(props.client.date_of_birth).slice(0, 10)
        : '',
    ssn: '',
    current_score: props.client.current_score?.toString() ?? '',
    goals: props.client.goals ?? '',
});

const clientProfileEditing = ref(false);

const compactClientValue = (value?: string | number | null) => {
    const normalized = String(value ?? '').trim();

    return normalized !== '' ? normalized : 'Not set';
};

type ParsedMailingAddress = {
    street: string;
    city: string;
    state: string;
    postalCode: string;
};

const parseEmbeddedMailingAddress = (
    value?: string | null,
): ParsedMailingAddress | null => {
    const raw = String(value ?? '')
        .replace(/\s+/g, ' ')
        .trim();

    if (raw === '') {
        return null;
    }

    const commaMatch = raw.match(
        /^(.+),\s*([^,]+),\s*([A-Za-z]{2}|[A-Za-z][A-Za-z .'-]*?)\s+(\d{5}(?:-\d{4})?)$/,
    );

    if (!commaMatch) {
        return null;
    }

    const [, street, city, state, postalCode] = commaMatch.map((part) =>
        part.trim(),
    );

    if (!street || !city || !state || !postalCode) {
        return null;
    }

    return { street, city, state, postalCode };
};

const clientMailingAddress = computed(() => {
    const parsed = parseEmbeddedMailingAddress(props.client.address_line_1);
    const city = String(props.client.city ?? '').trim() || parsed?.city || '';
    const state =
        String(props.client.state ?? '').trim() || parsed?.state || '';
    const postalCode =
        String(props.client.postal_code ?? '').trim() ||
        parsed?.postalCode ||
        '';
    const street =
        parsed?.street || String(props.client.address_line_1 ?? '').trim();

    return {
        street,
        addressLine2: String(props.client.address_line_2 ?? '').trim(),
        city,
        state,
        postalCode,
    };
});

const clientLocationLine = computed(() => {
    const cityState = [
        clientMailingAddress.value.city,
        clientMailingAddress.value.state,
    ]
        .map((part) => String(part ?? '').trim())
        .filter(Boolean)
        .join(', ');

    return [cityState, clientMailingAddress.value.postalCode]
        .map((part) => String(part ?? '').trim())
        .filter(Boolean)
        .join(' ');
});

const clientHasStreetAddress = computed(() =>
    Boolean(clientMailingAddress.value.street),
);

const clientMailingLabelLines = computed(() => {
    const lines = [
        props.client.display_name || 'Name missing',
        clientHasStreetAddress.value
            ? clientMailingAddress.value.street
            : 'Address info missing',
    ]
        .map((line) => String(line ?? '').trim())
        .filter(Boolean);

    const line2 = clientMailingAddress.value.addressLine2;

    if (line2 !== '') {
        lines.push(line2);
    }

    lines.push(clientLocationLine.value || 'City, State ZIP missing');
    lines.push('United States');

    return lines;
});

const latestProfileSnapshot = computed(
    () => props.client.profile_snapshots?.[0] ?? null,
);

const latestProfilePostalBarcodeValue = computed(() => {
    const barcode = latestProfileSnapshot.value?.mailing_barcode;

    if (barcode) {
        return barcode;
    }

    if (!clientHasStreetAddress.value) {
        return 'address info missing';
    }

    return clientMailingAddress.value.postalCode
        ? 'ZIP+4 needed'
        : 'ZIP missing';
});

const profileSnapshotCount = computed(
    () => props.client.profile_snapshots?.length ?? 0,
);

const profileSnapshotFieldLabel = (field: string) =>
    field
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const profileSnapshotChangedFields = (fields?: string[]) =>
    (fields ?? []).slice(0, 4).map(profileSnapshotFieldLabel).join(', ');

const zodiacSignForDate = (value?: string | null) => {
    const raw = String(value ?? '').trim();
    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (!match) {
        return null;
    }

    const month = Number(match[2]);
    const day = Number(match[3]);

    if (!month || !day) {
        return null;
    }

    if ((month === 1 && day >= 20) || (month === 2 && day <= 18)) {
        return 'Aquarius';
    }

    if ((month === 2 && day >= 19) || (month === 3 && day <= 20)) {
        return 'Pisces';
    }

    if ((month === 3 && day >= 21) || (month === 4 && day <= 19)) {
        return 'Aries';
    }

    if ((month === 4 && day >= 20) || (month === 5 && day <= 20)) {
        return 'Taurus';
    }

    if ((month === 5 && day >= 21) || (month === 6 && day <= 20)) {
        return 'Gemini';
    }

    if ((month === 6 && day >= 21) || (month === 7 && day <= 22)) {
        return 'Cancer';
    }

    if ((month === 7 && day >= 23) || (month === 8 && day <= 22)) {
        return 'Leo';
    }

    if ((month === 8 && day >= 23) || (month === 9 && day <= 22)) {
        return 'Virgo';
    }

    if ((month === 9 && day >= 23) || (month === 10 && day <= 22)) {
        return 'Libra';
    }

    if ((month === 10 && day >= 23) || (month === 11 && day <= 21)) {
        return 'Scorpio';
    }

    if ((month === 11 && day >= 22) || (month === 12 && day <= 21)) {
        return 'Sagittarius';
    }

    return 'Capricorn';
};

const clientZodiacSign = computed(() =>
    zodiacSignForDate(props.client.date_of_birth),
);

const clientProfileSummaryItems = computed(() => [
    {
        label: 'Email',
        value: compactClientValue(props.client.email),
    },
    {
        label: 'Phone',
        value: compactClientValue(props.client.phone),
    },
    {
        label: 'DOB',
        value: props.client.date_of_birth
            ? formatDate(props.client.date_of_birth)
            : 'Not set',
    },
    {
        label: 'Zodiac',
        value: clientZodiacSign.value ?? 'DOB needed',
    },
    {
        label: 'SSN',
        value: props.client.ssn_last_four
            ? `Ending ${props.client.ssn_last_four}`
            : 'Not saved',
    },
    {
        label: 'Current score',
        value: props.client.current_score
            ? formatNumber(props.client.current_score)
            : 'N/A',
    },
    {
        label: 'History',
        value: `${profileSnapshotCount.value} snapshot${profileSnapshotCount.value === 1 ? '' : 's'}`,
    },
]);

const providerForm = useForm({
    provider_key: props.providerCatalog[0]?.key ?? 'smartcredit',
    provider_label: props.providerCatalog[0]?.label ?? 'SmartCredit',
    login_email: '',
    login_username: '',
    login_password: '',
    security_answer: '',
    status: props.providerStatuses[0]?.key ?? 'connected',
    notes: '',
});

const relationshipForm = useForm({
    ended_reason: props.relationship.ended_reason ?? '',
    ended_notes: props.relationship.ended_notes ?? '',
    ended_at: props.relationship.ended_at
        ? String(props.relationship.ended_at).slice(0, 10)
        : new Date().toISOString().slice(0, 10),
});
const manualBillingForm = useForm({
    kind: 'cash',
    amount: props.client.billing_profile?.amount
        ? String(props.client.billing_profile.amount)
        : '0.00',
    currency: props.client.billing_profile?.currency ?? 'USD',
    paid_at: new Date().toISOString().slice(0, 10),
    billing_interval:
        props.client.billing_profile?.billing_interval ?? 'monthly',
    notes: '',
});
const deleteLeadForm = useForm({});
const deleteLeadConfirmationArmed = ref(false);

const aiProviderCount = computed(
    () =>
        page.props.creditsoft.ai.catalog.providers.filter(
            (provider) => provider.configured,
        ).length,
);
const latestScorePoint = computed(
    () => props.scoreTimeline.points.at(-1) ?? null,
);
const latestScoreCapture = computed(() =>
    props.scoreTimeline.source?.id
        ? (props.client.browser_captures.find(
              (capture) => capture.id === props.scoreTimeline.source?.id,
          ) ?? null)
        : (props.client.browser_captures.find((capture) => {
              const providerCapture = resolveProviderCapture(capture) as any;

              return (
                  providerCapture?.profile === 'score_tracker' ||
                  capture.metadata?.smartcredit?.profile === 'score_tracker'
              );
          }) ?? null),
);
const scoreExperienceProviderKey = computed(
    () =>
        props.scoreTimeline.source?.provider_key ??
        (latestScoreCapture.value
            ? captureProviderKey(latestScoreCapture.value)
            : null),
);
const smartCreditScoreCards = computed<ImportedScoreCard[]>(() => {
    if (scoreExperienceProviderKey.value !== 'smartcredit') {
        return [];
    }

    const providerCapture = latestScoreCapture.value
        ? (resolveProviderCapture(latestScoreCapture.value) as any)
        : null;
    const cards =
        providerCapture?.score_cards ??
        latestScoreCapture.value?.metadata?.smartcredit?.score_cards ??
        [];

    return Array.isArray(cards) ? cards : [];
});
const mappedScoreExperience = computed(
    () =>
        scoreExperienceProviderKey.value === 'smartcredit' &&
        (props.scoreTimeline.series.length > 0 ||
            smartCreditScoreCards.value.length > 0),
);
const recentScorePoints = computed(() =>
    props.scoreTimeline.points.slice().reverse().slice(0, 5),
);
const providerCatalogMap = computed(() =>
    Object.fromEntries(
        props.providerCatalog.map((provider) => [provider.key, provider]),
    ),
);
const selectedProviderCatalog = computed(
    () => providerCatalogMap.value[providerForm.provider_key] ?? null,
);
const smartCreditProvider = computed(
    () =>
        props.providers.find(
            (provider) => provider.provider_key === 'smartcredit',
        ) ?? null,
);
const displayProviderNotes = (provider: (typeof props.providers)[number]) => {
    const note = provider.notes?.trim() ?? '';

    if (!note) {
        return '';
    }

    if (
        note.includes('Ashley placeholder') ||
        note.includes('filename-only Ashley placeholder')
    ) {
        return 'Imported from SmartCredit archive files. Save the customer SmartCredit login here to automate future pulls.';
    }

    return note;
};
const providerNeedsSecurityAnswer = (
    provider: (typeof props.providers)[number],
) =>
    provider.provider_key === 'identityiq' &&
    !provider.has_stored_security_answer;
const canRevealProviderCredentials = computed(
    () =>
        page.props.auth.can_edit_users ||
        ['owner_admin', 'admin'].includes(page.props.auth.role ?? ''),
);
const providerHasRevealableCredentials = (
    provider: (typeof props.providers)[number],
) => provider.has_stored_password || provider.has_stored_security_answer;
const storedProviderCredentials = (
    provider: (typeof props.providers)[number],
) => revealedProviderCredentials[provider.id] ?? null;
const providerCredentialReasonLabel = (reason?: string | null) => {
    const normalized = (reason ?? '').replaceAll('_', ' ').trim();

    return normalized ? normalized : 'provider rejected the saved login';
};
const providerCredentialIssue = (
    provider: (typeof props.providers)[number],
) => {
    const health = provider.credential_health;
    const invalidatedAt =
        health?.invalidated_at ??
        provider.metadata?.credentials?.invalidated_at ??
        provider.metadata?.companion?.credentials?.invalid?.detected_at ??
        provider.metadata?.smartcredit?.invalid_credentials?.detected_at ??
        provider.metadata?.companion?.last_status_event?.detected_at ??
        null;
    const reason =
        health?.invalidated_reason ??
        provider.metadata?.credentials?.invalidated_reason ??
        provider.metadata?.companion?.credentials?.invalid?.reason ??
        provider.metadata?.smartcredit?.invalid_credentials?.reason ??
        provider.metadata?.companion?.last_status_event?.reason ??
        null;
    const blocked =
        health?.blocked ||
        ['needs_credentials', 'blocked', 'disconnected'].includes(
            provider.status,
        );

    if (!blocked && !invalidatedAt) {
        return null;
    }

    return {
        invalidatedAt,
        reason: providerCredentialReasonLabel(reason),
    };
};
const smartCreditNeedsLogin = computed(() => {
    const provider = smartCreditProvider.value;

    if (!provider) {
        return true;
    }

    return (
        !provider.login_email &&
        !provider.login_username &&
        !provider.has_stored_password
    );
});

const clientInformationComplete = computed(() =>
    Boolean(
        (props.client.first_name || props.client.display_name) &&
        (props.client.last_name || props.client.display_name) &&
        (props.client.email || props.client.phone),
    ),
);
const assignedTeamComplete = computed(() =>
    Boolean(props.client.assigned_user?.name),
);
const sourceAssignedTo = computed(() =>
    String(props.client.import_audit?.source_assigned_to ?? '').trim(),
);
const portalAccessReady = computed(() => Boolean(props.client.email));
const onboardingCampaignStarted = computed(() =>
    props.client.sop_runs.some((run) =>
        (run.template?.name ?? '').toLowerCase().includes('boarding'),
    ),
);
const billingReady = computed(() =>
    Boolean(props.client.billing_profile?.status),
);
const manualBillingKindLabel = computed(() =>
    String(manualBillingForm.kind)
        .replace('cash_app', 'Cash App')
        .replace('owner_comp', 'Owner comp')
        .replace('pro_bono', 'Pro bono')
        .replaceAll('_', ' ')
        .replace(/^\w/, (character) => character.toUpperCase()),
);
const billingProfileSummary = computed(() => {
    const profile = props.client.billing_profile;

    if (!profile) {
        return 'No billing profile is attached yet.';
    }

    const amount = Number.parseFloat(String(profile.amount ?? '0'));
    const amountLabel = Number.isFinite(amount)
        ? new Intl.NumberFormat('en-US', {
              style: 'currency',
              currency: profile.currency ?? 'USD',
          }).format(amount)
        : 'Amount not set';
    const interval = profile.billing_interval
        ? ` / ${String(profile.billing_interval).replaceAll('_', ' ')}`
        : '';
    const paid = profile.last_paid_at
        ? `Last paid ${formatDate(profile.last_paid_at)}.`
        : 'No recent paid date saved.';

    return `${String(profile.status).replaceAll('_', ' ')}${interval} · ${amountLabel} · ${paid}`;
});
const manualBillingIsComped = computed(() =>
    ['pro_bono', 'owner_comp'].includes(String(manualBillingForm.kind)),
);

watch(
    () => manualBillingForm.kind,
    (kind) => {
        if (['pro_bono', 'owner_comp'].includes(String(kind))) {
            manualBillingForm.amount = '0.00';
            manualBillingForm.billing_interval = 'lifetime';
        }
    },
);
const documentSearchText = (document: ClientDocumentRecord) =>
    [
        document.category,
        document.title,
        document.file_name,
        document.notes,
        document.reporting_cycle,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
const reportDocumentKind = (document: ClientDocumentRecord) => {
    const category = String(document.category ?? '').toLowerCase();
    const text = documentSearchText(document);

    if (
        ['credit_report', 'credit_reports', 'credit_report_pdf'].includes(
            category,
        ) ||
        [
            'credit report',
            '3-bureau',
            '3 bureau',
            'smart credit report',
            'smartcredit report',
        ].some((needle) => text.includes(needle))
    ) {
        return 'Credit report';
    }

    if (
        ['progress', 'progress_report', 'client_progress'].includes(category) ||
        ['progress report', 'client progress'].some((needle) =>
            text.includes(needle),
        )
    ) {
        return 'Progress';
    }

    if (
        ['audit', 'audit_report'].includes(category) ||
        ['audit report', 'credit audit'].some((needle) => text.includes(needle))
    ) {
        return 'Audit';
    }

    return null;
};
const progressReportDocuments = computed(() =>
    props.client.documents.filter((document) => reportDocumentKind(document)),
);
const clientFileDocuments = computed(() =>
    props.client.documents.filter((document) => !reportDocumentKind(document)),
);
const documentAccess = computed(
    () =>
        props.client.document_access ?? {
            can_view_files: true,
            document_count: props.client.documents.length,
            file_count: props.client.documents.length,
            report_count: progressReportDocuments.value.length,
            client_file_count: clientFileDocuments.value.length,
            total_bytes: props.client.documents.reduce(
                (total, document) => total + Number(document.file_size ?? 0),
                0,
            ),
            total_label: '',
        },
);
const visibleReportDocumentCount = computed(() =>
    documentAccess.value.can_view_files
        ? progressReportDocuments.value.length
        : documentAccess.value.report_count,
);
const visibleClientFileCount = computed(() =>
    documentAccess.value.can_view_files
        ? clientFileDocuments.value.length
        : documentAccess.value.client_file_count,
);
const hasCreditReportDocument = computed(() =>
    progressReportDocuments.value.some(
        (document) => reportDocumentKind(document) === 'Credit report',
    ),
);
const creditReportImported = computed(
    () =>
        hasCreditReportDocument.value ||
        props.client.browser_captures.some((capture) =>
            Boolean(
                capture.metadata?.provider_capture ||
                capture.metadata?.smartcredit ||
                capture.metadata?.credit_karma,
            ),
        ),
);
const roundOneDisputesReady = computed(
    () =>
        props.client.letters.some((letter) =>
            [
                'approved',
                'exported',
                'sent',
                'mailed',
                'complete',
                'completed',
            ].includes(letter.status),
        ) ||
        props.client.violations.some((violation) =>
            ['confirmed', 'dispute_ready', 'in_progress', 'open'].includes(
                violation.status,
            ),
        ),
);
const lettersReady = computed(() => props.client.letters.length > 0);
const clientProcessChecklist = computed<ClientProcessChecklistItem[]>(() => [
    {
        number: 1,
        title: 'Complete All Client Information',
        status: clientInformationComplete.value ? 'complete' : 'pending',
        detail: clientInformationComplete.value
            ? 'Name and at least one contact lane are saved.'
            : 'Finish the client identity and contact fields before processing.',
        actionLabel: 'Edit',
        action: 'client_info',
    },
    {
        number: 2,
        title: 'Assign Agent, Sales Person',
        status: assignedTeamComplete.value ? 'complete' : 'pending',
        detail: assignedTeamComplete.value
            ? `Assigned to ${props.client.assigned_user?.name}.`
            : sourceAssignedTo.value
              ? `Source owner ${sourceAssignedTo.value} needs a matching CreditSoft staff account.`
              : 'No assigned operator is visible on this file yet.',
        actionLabel: 'Edit',
        action: 'assignment',
    },
    {
        number: 3,
        title: 'Activate Client Portal Access',
        status: portalAccessReady.value ? 'ready' : 'pending',
        detail: portalAccessReady.value
            ? 'Client email is present for portal invite and API handoff.'
            : 'Save a client email before portal access can be activated.',
        actionLabel: 'Edit',
        action: 'portal',
    },
    {
        number: 4,
        title: 'Initiate On-Boarding Campaign',
        status: onboardingCampaignStarted.value ? 'complete' : 'pending',
        detail: onboardingCampaignStarted.value
            ? 'An onboarding SOP/campaign has been started.'
            : 'No onboarding SOP/campaign is attached yet.',
        actionLabel: 'View',
        action: 'onboarding',
    },
    {
        number: 5,
        title: 'Setup Billing Information',
        status: billingReady.value ? 'complete' : 'pending',
        detail: billingReady.value
            ? `Billing is ${props.client.billing_profile?.status.replaceAll('_', ' ')}${props.client.billing_profile?.billing_interval ? ` / ${props.client.billing_profile.billing_interval}` : ''}.`
            : 'No billing profile is attached to this client yet.',
        actionLabel: 'Edit',
        action: 'billing',
    },
    {
        number: 6,
        title: 'Import Credit Report',
        status: creditReportImported.value ? 'complete' : 'pending',
        detail: creditReportImported.value
            ? `${props.client.browser_captures.length} import record${props.client.browser_captures.length === 1 ? '' : 's'} attached.`
            : 'Import SmartCredit, IdentityIQ, Credit Karma, or a browser capture.',
        actionLabel: 'Import',
        action: 'import',
    },
    {
        number: 7,
        title: 'Complete Round 1 Disputes',
        status: roundOneDisputesReady.value ? 'ready' : 'pending',
        detail: roundOneDisputesReady.value
            ? 'Dispute work is started or letters are ready for review.'
            : 'Confirm violations and generate round 1 disputes.',
        actionLabel: 'View',
        action: 'violations',
    },
    {
        number: 8,
        title: 'Print / Send Letters',
        status: lettersReady.value ? 'ready' : 'pending',
        detail: lettersReady.value
            ? `${props.client.letters.length} letter${props.client.letters.length === 1 ? '' : 's'} attached to this file.`
            : 'No letters are attached yet.',
        actionLabel: 'View',
        action: 'letters',
    },
]);
const completedProcessSteps = computed(
    () =>
        clientProcessChecklist.value.filter(
            (item) => item.status === 'complete' || item.status === 'ready',
        ).length,
);
const checklistDotClass = (status: ClientProcessChecklistItem['status']) => {
    if (status === 'complete') {
        return 'bg-emerald-500';
    }

    if (status === 'ready') {
        return 'bg-amber-400';
    }

    return 'bg-stone-300';
};
const checklistStatusLabel = (status: ClientProcessChecklistItem['status']) => {
    if (status === 'complete') {
        return 'Complete';
    }

    if (status === 'ready') {
        return 'Ready';
    }

    return 'Needs setup';
};
const documentCategoryLabel = (category?: string | null) =>
    ({
        audit_report: 'audit report',
        credit_report: 'credit report',
        progress_report: 'progress report',
        client_documents: 'client document',
    })[String(category || '')] ??
    String(category || 'document').replaceAll('_', ' ');
const formatFileSize = (bytes?: number | null) => {
    if (!bytes || bytes < 1) {
        return null;
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size >= 10 || unitIndex === 0 ? size.toFixed(0) : size.toFixed(1)} ${units[unitIndex]}`;
};
const latestProgressReportDocument = computed(
    () =>
        progressReportDocuments.value.slice().sort((left, right) => {
            const leftTime = left.uploaded_at
                ? new Date(left.uploaded_at).getTime()
                : 0;
            const rightTime = right.uploaded_at
                ? new Date(right.uploaded_at).getTime()
                : 0;

            return rightTime - leftTime;
        })[0] ?? null,
);
const reportCycleNextStep = computed(() => {
    const reviewed = props.reviewState.reviewed_signatures.length;
    const total = props.reviewState.total_rows;

    if (!creditReportImported.value) {
        return 'Import report';
    }

    if (total > 0 && reviewed < total) {
        return 'Finish audit';
    }

    if (props.reviewState.dispute_signatures.length > 0) {
        return 'Continue disputes';
    }

    if (!lettersReady.value) {
        return 'Prepare letters';
    }

    return 'Cycle current';
});
const reportCyclePatternCards = computed(() => [
    {
        label: 'Latest file',
        value: latestProgressReportDocument.value?.uploaded_at
            ? formatDate(latestProgressReportDocument.value.uploaded_at)
            : latestSmartCreditThreeBureauCapture.value?.imported_at
              ? formatDate(
                    latestSmartCreditThreeBureauCapture.value.imported_at,
                )
              : 'Pending',
        hint:
            latestProgressReportDocument.value?.title ??
            latestSmartCreditThreeBureauCapture.value?.page_title ??
            'No report artifact attached yet.',
    },
    {
        label: 'Report files',
        value: String(progressReportArtifactCount.value),
        hint: 'Audit, progress, credit-report, and capture records.',
    },
    {
        label: 'Reviewed',
        value: `${props.reviewState.reviewed_signatures.length}/${props.reviewState.total_rows || 0}`,
        hint: props.reviewState.dispute_signatures.length
            ? `${props.reviewState.dispute_signatures.length} dispute signal${props.reviewState.dispute_signatures.length === 1 ? '' : 's'} open.`
            : 'No dispute signals started from this cycle yet.',
    },
    {
        label: 'Next step',
        value: reportCycleNextStep.value,
        hint:
            reportCycleNextStep.value === 'Cycle current'
                ? 'Ready for the next report refresh.'
                : 'Use the current cycle pattern to keep work moving.',
    },
]);

const runClientProcessAction = (action?: ClientProcessAction) => {
    switch (action) {
        case 'client_info':
            window.scrollTo({ top: 0, behavior: 'smooth' });
            break;
        case 'assignment':
            router.get('/settings/users');
            break;
        case 'providers':
            showProviderEditor.value = true;
            scrollToPanel(providerSectionId);
            break;
        case 'portal':
            router.get('/settings/api');
            break;
        case 'onboarding':
            scrollToPanel(relationshipSectionId);
            break;
        case 'billing':
            scrollToPanel(billingSectionId);
            break;
        case 'import':
            showBrowserCaptureEditor.value = true;
            scrollToPanel(importSectionId);
            break;
        case 'violations':
            router.get(clientProfileHref('/violations'));
            break;
        case 'letters':
            router.get(clientProfileHref('/letters'));
            break;
    }
};

watch(
    smartCreditNeedsLogin,
    (needsLogin) => {
        if (needsLogin) {
            showProviderEditor.value = true;
        }
    },
    { immediate: true },
);

watch(
    providerPanelRequested,
    (requested) => {
        if (requested) {
            showProviderEditor.value = true;
            scrollToPanel(providerSectionId);
        }
    },
    { immediate: true },
);

watch(
    importPanelRequested,
    (requested) => {
        showBrowserCaptureEditor.value = requested;

        if (requested) {
            scrollToPanel(importSectionId);
        }
    },
    { immediate: true },
);

watch(
    relationshipPanelRequested,
    (requested) => {
        if (requested) {
            scrollToPanel(relationshipSectionId);
        }
    },
    { immediate: true },
);

watch(
    () => providerForm.provider_key,
    (nextKey, previousKey) => {
        const nextEntry = providerCatalogMap.value[nextKey];
        const previousEntry = previousKey
            ? providerCatalogMap.value[previousKey]
            : null;

        if (nextKey === 'custom') {
            if (providerForm.provider_label === previousEntry?.label) {
                providerForm.provider_label = '';
            }

            return;
        }

        if (
            providerForm.provider_label === '' ||
            providerForm.provider_label === previousEntry?.label
        ) {
            providerForm.provider_label = nextEntry?.label ?? '';
        }
    },
);

const resolveProviderCapture = (
    capture: (typeof props.client.browser_captures)[number],
) =>
    capture.metadata?.provider_capture ??
    capture.metadata?.credit_karma ??
    capture.metadata?.smartcredit ??
    null;

const captureProviderKey = (
    capture: (typeof props.client.browser_captures)[number],
) =>
    capture.metadata?.provider_key ??
    (resolveProviderCapture(capture) as { provider?: string | null } | null)
        ?.provider ??
    (capture.metadata?.credit_karma
        ? 'credit_karma'
        : capture.metadata?.smartcredit
          ? 'smartcredit'
          : null);

const providerBadgeLabel = (
    capture: (typeof props.client.browser_captures)[number],
) => {
    const providerKey = captureProviderKey(capture);
    const providerCapture = resolveProviderCapture(capture) as any;

    if (providerKey === 'credit_karma') {
        return providerCapture?.bureau
            ? `Credit Karma ${providerCapture.bureau}`
            : 'Credit Karma import';
    }

    return providerCapture?.label ?? 'Provider import';
};

const capturePreview = (
    capture: (typeof props.client.browser_captures)[number],
) => {
    const providerKey = captureProviderKey(capture);
    const providerCapture = resolveProviderCapture(capture) as any;

    if (!providerCapture) {
        return [];
    }

    if (providerKey === 'credit_karma') {
        const chips: string[] = [];

        if (providerCapture.bureau) {
            chips.push(providerCapture.bureau);
        }

        if (providerCapture.scores?.credit) {
            chips.push(`credit ${providerCapture.scores.credit}`);
        }

        if (providerCapture.available_report_dates?.length) {
            chips.push(
                `${providerCapture.available_report_dates.length} report dates`,
            );
        }

        if (
            providerCapture.bureau_coverage?.missing_bureaus?.includes(
                'experian',
            )
        ) {
            chips.push('experian missing');
        }

        return chips.slice(0, 6);
    }

    const smartCredit = capture.metadata?.smartcredit;

    if (!smartCredit) {
        return [];
    }

    const chips: string[] = [];

    if (smartCredit.profile === 'score_tracker') {
        Object.entries(smartCredit.scores ?? {}).forEach(([key, value]) => {
            chips.push(`${key.replaceAll('_', ' ')} ${value}`);
        });

        if (smartCredit.score_history_chart?.labels.length) {
            chips.push(
                `${smartCredit.score_history_chart.labels.length} score points`,
            );
        }
    }

    if (smartCredit.account_count) {
        chips.push(`${smartCredit.account_count} accounts`);
    }

    if (smartCredit.negative_account_count) {
        chips.push(`${smartCredit.negative_account_count} negative`);
    }

    Object.entries(smartCredit.summary ?? {})
        .slice(0, 4)
        .forEach(([key, value]) => {
            chips.push(`${key.replaceAll('_', ' ')} ${value.value}`);
        });

    return chips.slice(0, 6);
};

const captureChart = (
    capture: (typeof props.client.browser_captures)[number],
) => (resolveProviderCapture(capture) as any)?.score_history_chart ?? null;

const hasCaptureChart = (
    capture: (typeof props.client.browser_captures)[number],
) => {
    const chart = captureChart(capture);

    return Boolean(chart && chart.labels.length > 1 && chart.series.length > 0);
};

const isStructuredSmartCreditThreeBureauCapture = (
    capture: (typeof props.client.browser_captures)[number],
) => {
    const smartCredit = capture.metadata?.smartcredit as any;
    const accountMatrixCount = Number(smartCredit?.account_matrix_count ?? 0);
    const accountMatrix = Array.isArray(smartCredit?.account_matrix)
        ? smartCredit.account_matrix
        : [];
    const summary =
        smartCredit?.summary && typeof smartCredit.summary === 'object'
            ? Object.values(smartCredit.summary)
            : [];
    const bureauScores =
        smartCredit?.bureau_scores &&
        typeof smartCredit.bureau_scores === 'object'
            ? Object.values(smartCredit.bureau_scores)
            : [];

    return (
        accountMatrixCount > 0 ||
        accountMatrix.length > 0 ||
        summary.length > 0 ||
        bureauScores.length > 0
    );
};

const compactText = (value?: string | null, limit = 220) => {
    const normalized = (value ?? '').replace(/\s+/g, ' ').trim();

    if (!normalized) {
        return '';
    }

    return normalized.length > limit
        ? `${normalized.slice(0, limit).trimEnd()}...`
        : normalized;
};

const formatCaptureTimestamp = (value?: string | null) => {
    if (!value) {
        return 'Saved';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        timeZoneName: 'short',
    }).format(new Date(value));
};

const sortCapturesByImportedAt = (
    left: (typeof props.client.browser_captures)[number],
    right: (typeof props.client.browser_captures)[number],
) => {
    const leftTime = left.imported_at
        ? new Date(left.imported_at).getTime()
        : 0;
    const rightTime = right.imported_at
        ? new Date(right.imported_at).getTime()
        : 0;

    return rightTime - leftTime;
};

const latestSmartCreditThreeBureauCapture = computed(
    () =>
        props.client.browser_captures
            .filter(
                (capture) =>
                    (capture.metadata?.smartcredit as any)?.profile ===
                    'three_bureau_report',
            )
            .slice()
            .sort(sortCapturesByImportedAt)
            .find(isStructuredSmartCreditThreeBureauCapture) ??
        props.client.browser_captures
            .filter(
                (capture) =>
                    (capture.metadata?.smartcredit as any)?.profile ===
                    'three_bureau_report',
            )
            .slice()
            .sort(sortCapturesByImportedAt)[0] ??
        null,
);

const captureSearchText = (
    capture: (typeof props.client.browser_captures)[number],
) =>
    [
        capture.source_type,
        capture.page_title,
        capture.page_url,
        (resolveProviderCapture(capture) as any)?.profile,
        (resolveProviderCapture(capture) as any)?.label,
    ]
        .filter(Boolean)
        .join(' ')
        .replaceAll('_', ' ')
        .toLowerCase();

const captureReportKind = (
    capture: (typeof props.client.browser_captures)[number],
) => {
    const providerCapture = resolveProviderCapture(capture) as any;
    const profile = String(providerCapture?.profile ?? '').toLowerCase();
    const text = captureSearchText(capture);

    if (
        [
            'three_bureau_report',
            'smart_credit_report',
            'credit_report',
            'credit_reports',
        ].includes(profile) ||
        ['credit report', '3-bureau', '3 bureau'].some((needle) =>
            text.includes(needle),
        )
    ) {
        return 'Credit report';
    }

    if (
        ['progress', 'progress_report', 'client_progress'].includes(profile) ||
        ['progress report', 'client progress'].some((needle) =>
            text.includes(needle),
        )
    ) {
        return 'Progress';
    }

    if (
        ['audit', 'audit_report'].includes(profile) ||
        ['audit report', 'credit audit'].some((needle) => text.includes(needle))
    ) {
        return 'Audit';
    }

    return null;
};

const progressReportCaptures = computed(() =>
    props.client.browser_captures
        .filter((capture) => captureReportKind(capture))
        .slice()
        .sort(sortCapturesByImportedAt),
);

const progressReportArtifactCount = computed(
    () =>
        visibleReportDocumentCount.value + progressReportCaptures.value.length,
);

const activeReviewCycleId = computed(
    () =>
        latestSmartCreditThreeBureauCapture.value?.reporting_cycle_id ??
        props.reviewState.cycle_id ??
        props.cycles[0]?.id ??
        null,
);

const latestSmartCreditReportCapture = computed(
    () =>
        props.client.browser_captures
            .filter(
                (capture) =>
                    (capture.metadata?.smartcredit as any)?.profile ===
                    'smart_credit_report',
            )
            .slice()
            .sort(sortCapturesByImportedAt)[0] ?? null,
);

const smartCreditBureauOrder = ['experian', 'transunion', 'equifax'] as const;

const smartCreditCoverageCopy: Record<string, string> = {
    only: 'Only',
    missing: 'Missing',
    match: 'Match',
    pair: '2-match',
    diff: 'Mismatch',
    unknown: 'Unknown',
};

const normalizedResolvedStatuses = new Set([
    'closed',
    'resolved',
    'complete',
    'completed',
    'sent',
    'done',
]);

const formatSummaryKey = (key: string) =>
    key
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());

const parseCount = (value?: string | number | null) => {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    const normalized = String(value ?? '').replace(/[^0-9.-]/g, '');

    if (!normalized) {
        return null;
    }

    const parsed = Number.parseInt(normalized, 10);

    return Number.isFinite(parsed) ? parsed : null;
};

const smartCreditSummaryRows = (
    capture?: (typeof props.client.browser_captures)[number] | null,
) => {
    if (!capture) {
        return [];
    }

    const smartCredit = capture.metadata?.smartcredit as any;
    const summary = smartCredit?.summary ?? {};
    const orderedKeys = [
        'total_accounts',
        'open_accounts',
        'closed_accounts',
        'delinquent_accounts',
        'derogatory_accounts',
        'balances',
        'payments',
        'inquiries',
    ];

    return orderedKeys
        .filter((key) => summary[key])
        .map((key) => ({
            key,
            label: formatSummaryKey(key),
            value: (summary[key] as { value?: string }).value ?? 'N/A',
            match: (summary[key] as { match?: string | null }).match ?? null,
        }))
        .slice(0, 8);
};

const smartCreditBureauScores = (
    capture?: (typeof props.client.browser_captures)[number] | null,
) => {
    if (!capture) {
        return [];
    }

    const smartCredit = capture.metadata?.smartcredit as any;
    const rawScores = smartCredit?.bureau_scores ?? {};

    return smartCreditBureauOrder
        .map((key) => rawScores[key])
        .filter(Boolean)
        .map((row: any) => ({
            key: row.key,
            label: String(row.label ?? row.key ?? '').replace('®', ''),
            display: row.display ?? 'Unavailable',
            available: Boolean(row.available),
        }));
};

const smartCreditAvailableBureauScoreCount = (
    capture?: (typeof props.client.browser_captures)[number] | null,
) =>
    smartCreditBureauScores(capture).filter(
        (score) => score.available && parseCount(score.display) !== null,
    ).length;

const smartCreditBureauMatrixRows = (
    capture?: (typeof props.client.browser_captures)[number] | null,
) => {
    if (!capture) {
        return [];
    }

    const smartCredit = capture.metadata?.smartcredit as any;
    const rawRows = smartCredit?.account_matrix ?? [];

    return (Array.isArray(rawRows) ? rawRows : []).map((row: any) => ({
        name: row?.name ?? 'Unnamed tradeline',
        status: row?.status ?? null,
        category: row?.category ?? null,
        utilization: row?.utilization ?? null,
        negative: Boolean(row?.negative),
        coverageLabel: row?.coverage_label ?? null,
        coverage: {
            experian: row?.coverage?.experian ?? 'unknown',
            transunion: row?.coverage?.transunion ?? 'unknown',
            equifax: row?.coverage?.equifax ?? 'unknown',
        },
        evidence: Array.isArray(row?.evidence) ? row.evidence : [],
    }));
};

const smartCreditCoverageText = (state?: string | null) =>
    smartCreditCoverageCopy[state ?? 'unknown'] ?? 'Unknown';

const smartCreditCoverageClass = (state?: string | null) => ({
    'border-emerald-300 bg-emerald-50 text-emerald-900': state === 'match',
    'border-sky-300 bg-sky-50 text-sky-900': state === 'only',
    'border-amber-300 bg-amber-50 text-amber-900': state === 'pair',
    'border-rose-300 bg-rose-50 text-rose-900': state === 'diff',
    'border-stone-300 bg-stone-100 text-stone-500':
        state === 'missing' || state === 'unknown',
});

const smartCreditBureauLabel = (bureauKey: string) =>
    ({
        transunion: 'TransUnion',
        experian: 'Experian',
        equifax: 'Equifax',
    })[bureauKey] ?? bureauKey;

const primaryScoreCaptureId = computed(
    () =>
        props.scoreTimeline.source?.id ?? latestScoreCapture.value?.id ?? null,
);

const latestSmartCreditThreeBureauScoreCapture = computed(
    () =>
        props.client.browser_captures
            .filter(
                (capture) =>
                    (capture.metadata?.smartcredit as any)?.profile ===
                    'three_bureau_report',
            )
            .slice()
            .sort(sortCapturesByImportedAt)
            .find(
                (capture) => smartCreditAvailableBureauScoreCount(capture) > 0,
            ) ?? null,
);

const smartCreditThreeBureauScoreCapture = computed(
    () =>
        latestSmartCreditThreeBureauScoreCapture.value ??
        latestSmartCreditThreeBureauCapture.value,
);

const smartCreditThreeBureauMatrixCapture = computed(() => {
    const latestCapture = latestSmartCreditThreeBureauCapture.value;

    if (smartCreditBureauMatrixRows(latestCapture as any).length > 0) {
        return latestCapture;
    }

    return smartCreditThreeBureauScoreCapture.value;
});

const smartCreditThreeBureauScoreFallbackActive = computed(() =>
    Boolean(
        latestSmartCreditThreeBureauCapture.value &&
        smartCreditThreeBureauScoreCapture.value &&
        latestSmartCreditThreeBureauCapture.value.id !==
            smartCreditThreeBureauScoreCapture.value.id,
    ),
);

const shouldRenderCaptureChart = (
    capture: (typeof props.client.browser_captures)[number],
) =>
    hasCaptureChart(capture) &&
    captureChart(capture) &&
    capture.id !== primaryScoreCaptureId.value;

const smartCreditRowSignature = (
    row: ReturnType<typeof smartCreditBureauMatrixRows>[number],
) =>
    [
        row.name.trim().toLowerCase(),
        (row.status ?? '').trim().toLowerCase(),
        (row.category ?? '').trim().toLowerCase(),
        row.coverage.experian,
        row.coverage.transunion,
        row.coverage.equifax,
    ].join('|');

const smartCreditThreeBureauData = computed(
    () =>
        (latestSmartCreditThreeBureauCapture.value?.metadata
            ?.smartcredit as any) ?? null,
);

const reviewedSignatureSet = computed(
    () => new Set(props.reviewState.reviewed_signatures),
);
const disputeSignatureSet = computed(
    () => new Set(props.reviewState.dispute_signatures),
);
const smartCreditTotalReviewRows = computed(() => {
    const matrixCount =
        parseCount(smartCreditThreeBureauData.value?.account_matrix_count) ?? 0;

    if (matrixCount > 0) {
        return matrixCount;
    }

    return smartCreditBureauMatrixRows(
        smartCreditThreeBureauMatrixCapture.value as any,
    ).length;
});

const smartCreditReviewedCount = computed(
    () =>
        smartCreditBureauMatrixRows(
            smartCreditThreeBureauMatrixCapture.value as any,
        ).filter((row) =>
            reviewedSignatureSet.value.has(smartCreditRowSignature(row)),
        ).length,
);

const smartCreditUtilizationTargetCount = computed(
    () =>
        smartCreditBureauMatrixRows(
            smartCreditThreeBureauMatrixCapture.value as any,
        ).filter((row) => {
            const utilization = parseCount(row.utilization);

            return utilization !== null && utilization > 30;
        }).length,
);

const smartCreditPriorityRows = computed(() =>
    smartCreditBureauMatrixRows(
        smartCreditThreeBureauMatrixCapture.value as any,
    ).filter(
        (row) =>
            row.negative ||
            Object.values(row.coverage).some((state) =>
                ['diff', 'missing', 'only'].includes(state),
            ),
    ),
);

const smartCreditSuggestedDisputeCount = computed(
    () => smartCreditPriorityRows.value.length,
);

const resolvedViolationCount = computed(
    () =>
        props.client.violations.filter((violation) =>
            normalizedResolvedStatuses.has(
                String(violation.status ?? '').toLowerCase(),
            ),
        ).length,
);

const openViolationCount = computed(
    () =>
        props.client.violations.filter(
            (violation) =>
                !normalizedResolvedStatuses.has(
                    String(violation.status ?? '').toLowerCase(),
                ),
        ).length,
);

const currentScoreMetric = computed(() => {
    if (props.client.current_score) {
        return props.client.current_score;
    }

    const availableScores = smartCreditBureauScores(
        smartCreditThreeBureauScoreCapture.value as any,
    )
        .map((bureau) => parseCount(bureau.display))
        .filter((score): score is number => score !== null);

    return availableScores.length ? Math.min(...availableScores) : 'N/A';
});

const accountsReviewedMetric = computed(() => {
    if (smartCreditTotalReviewRows.value > 0) {
        return `${formatNumber(smartCreditReviewedCount.value)}/${formatNumber(smartCreditTotalReviewRows.value)}`;
    }

    return formatNumber(smartCreditReviewedCount.value);
});

const priorityDisputesMetric = computed(() => {
    return `${formatNumber(resolvedViolationCount.value)}/${formatNumber(openViolationCount.value)}`;
});

const utilizationTargetsMetric = computed(() => {
    const normalizedTargets = props.latestSummary?.over_thirty_percent ?? 0;

    if (normalizedTargets > 0) {
        return formatNumber(normalizedTargets);
    }

    return formatNumber(smartCreditUtilizationTargetCount.value);
});

const clientHealthMetrics = computed(() => [
    {
        label: 'Current score',
        value: currentScoreMetric.value,
        hint: 'Latest known score snapshot',
    },
    {
        label: 'Accounts reviewed',
        value: accountsReviewedMetric.value,
        hint: 'Rows you explicitly marked reviewed',
    },
    {
        label: 'Priority disputes',
        value: priorityDisputesMetric.value,
        hint: 'Resolved / open dispute items',
    },
    {
        label: 'Utilization targets',
        value: utilizationTargetsMetric.value,
        hint: 'Accounts over the 30% utilization line',
    },
]);

const selectedRelationshipReason = computed(
    () =>
        props.relationship.reason_options.find(
            (reason) => reason.key === relationshipForm.ended_reason,
        ) ?? null,
);

const relationshipOutcomeLabel = computed(() =>
    !selectedRelationshipReason.value
        ? 'Choose a reason'
        : selectedRelationshipReason.value.outcome === 'graduated'
          ? 'Graduated'
          : selectedRelationshipReason.value.outcome === 'canceled'
            ? 'Canceled'
            : 'Terminated',
);

const endedRelationshipReasonLabel = computed(
    () =>
        props.relationship.reason_options.find(
            (reason) => reason.key === props.relationship.ended_reason,
        )?.label ??
        props.relationship.ended_reason ??
        null,
);

const smartCreditBureauCards = computed(() => {
    const scoreMap = Object.fromEntries(
        smartCreditBureauScores(
            smartCreditThreeBureauScoreCapture.value as any,
        ).map((bureau) => [bureau.key, bureau]),
    );
    const rows = smartCreditBureauMatrixRows(
        smartCreditThreeBureauMatrixCapture.value as any,
    );

    return smartCreditBureauOrder.map((bureauKey) => {
        const bureauRows = rows.filter(
            (row) => row.coverage[bureauKey] !== 'missing',
        );

        return {
            key: bureauKey,
            label: smartCreditBureauLabel(bureauKey),
            score: scoreMap[bureauKey] ?? {
                key: bureauKey,
                label: smartCreditBureauLabel(bureauKey),
                display: 'Unavailable',
                available: false,
            },
            tradelineCount: bureauRows.length,
            matchedCount: rows.filter(
                (row) => row.coverage[bureauKey] === 'match',
            ).length,
            pairCount: rows.filter((row) => row.coverage[bureauKey] === 'pair')
                .length,
            onlyCount: rows.filter((row) => row.coverage[bureauKey] === 'only')
                .length,
            mismatchCount: rows.filter(
                (row) => row.coverage[bureauKey] === 'diff',
            ).length,
            missingCount: rows.filter(
                (row) => row.coverage[bureauKey] === 'missing',
            ).length,
        };
    });
});

const smartCreditMetroFlags = (
    row: ReturnType<typeof smartCreditBureauMatrixRows>[number],
) => {
    const flags: ReviewSignal[] = [];

    if (row.negative) {
        flags.push({
            key: 'negative',
            label: 'Negative reporting',
            title: 'Derogatory or negative reporting is present on this tradeline.',
        });
    }

    if (Object.values(row.coverage).some((state) => state === 'diff')) {
        flags.push({
            key: 'mismatch',
            label: 'Bureau mismatch',
            title: 'The reporting differs across bureaus and needs Metro 2 review.',
        });
    }

    if (Object.values(row.coverage).some((state) => state === 'missing')) {
        flags.push({
            key: 'missing',
            label: 'Missing bureau',
            title: 'At least one bureau is missing this tradeline.',
        });
    }

    if (Object.values(row.coverage).some((state) => state === 'only')) {
        flags.push({
            key: 'single',
            label: 'Single-bureau',
            title: 'Only one bureau is carrying this version of the account.',
        });
    }

    return flags;
};

const smartCreditReasonSelections = reactive<Record<string, string>>({});

const smartCreditReasonGroup = (
    row: ReturnType<typeof smartCreditBureauMatrixRows>[number],
) => {
    const category = String(row.category ?? '').toLowerCase();

    if (category.includes('inquiry')) {
        return 'Inquiry';
    }

    if (category.includes('bankrupt') || category.includes('public')) {
        return 'Public Records';
    }

    if (category.includes('collection')) {
        return 'Collections';
    }

    return 'Account';
};

const smartCreditReasonBureau = (
    row: ReturnType<typeof smartCreditBureauMatrixRows>[number],
) => {
    const presentBureaus = Object.entries(row.coverage)
        .filter(([, state]) => state !== 'missing')
        .map(([bureau]) => bureau);

    return presentBureaus.length === 1 ? presentBureaus[0] : 'all';
};

const smartCreditReasonOptions = (
    row: ReturnType<typeof smartCreditBureauMatrixRows>[number],
) => {
    const bureau = smartCreditReasonBureau(row);
    const group = smartCreditReasonGroup(row);

    const matching = props.creditReasonOptions.filter((reason) => {
        const bureauMatches =
            reason.bureau === 'all' || reason.bureau === bureau;
        const groupMatches =
            reason.group === group ||
            (group === 'Account' &&
                ['Account', 'Collections'].includes(reason.group));

        return bureauMatches && groupMatches;
    });

    return matching.length ? matching : props.creditReasonOptions;
};

const selectedSmartCreditReason = (
    row: ReturnType<typeof smartCreditBureauMatrixRows>[number],
) => {
    const signature = smartCreditRowSignature(row);
    const current = smartCreditReasonSelections[signature];

    if (current) {
        return current;
    }

    const fallback = smartCreditReasonOptions(row)[0]?.reason ?? '';
    smartCreditReasonSelections[signature] = fallback;

    return fallback;
};

const updateSmartCreditReason = (
    row: ReturnType<typeof smartCreditBureauMatrixRows>[number],
    value: string,
) => {
    smartCreditReasonSelections[smartCreditRowSignature(row)] = value;
};

const isSmartCreditRowReviewed = (
    row: ReturnType<typeof smartCreditBureauMatrixRows>[number],
) => reviewedSignatureSet.value.has(smartCreditRowSignature(row));

const hasSmartCreditDispute = (
    row: ReturnType<typeof smartCreditBureauMatrixRows>[number],
) => disputeSignatureSet.value.has(smartCreditRowSignature(row));

const recentBrowserCaptures = computed(() => {
    const seenStructuredImports = new Set<string>();

    return props.client.browser_captures.filter((capture) => {
        if (captureReportKind(capture)) {
            return false;
        }

        if (!hasStructuredProviderCapture(capture)) {
            return true;
        }

        const providerCapture = resolveProviderCapture(capture) as any;
        const signature = [
            captureProviderKey(capture) ?? 'provider',
            providerCapture?.profile ?? capture.page_title ?? 'capture',
        ].join(':');

        if (seenStructuredImports.has(signature)) {
            return false;
        }

        seenStructuredImports.add(signature);

        return true;
    });
});

const smartCreditAccountPreviewRows = (
    capture?: (typeof props.client.browser_captures)[number] | null,
) => {
    if (!capture) {
        return [];
    }

    const smartCredit = capture.metadata?.smartcredit as any;
    const rawRows =
        smartCredit?.account_preview ?? smartCredit?.accounts_preview ?? [];

    return (Array.isArray(rawRows) ? rawRows : [])
        .map((row: any) => ({
            name: row?.name ?? 'Unnamed account',
            primary: row?.status ?? row?.type ?? null,
            secondary: row?.utilization ?? row?.balance ?? null,
            negative: Boolean(row?.negative),
        }))
        .slice(0, 8);
};

const hasStructuredProviderCapture = (
    capture: (typeof props.client.browser_captures)[number],
) => Boolean(resolveProviderCapture(capture));

const captureSummary = (
    capture: (typeof props.client.browser_captures)[number],
) => {
    const providerKey = captureProviderKey(capture);
    const providerCapture = resolveProviderCapture(capture) as any;

    if (providerKey === 'credit_karma' && providerCapture) {
        const bureau = providerCapture.bureau
            ? `${providerCapture.bureau} `
            : '';
        const score = providerCapture.scores?.credit
            ? `Current score ${providerCapture.scores.credit}.`
            : null;
        const asOf = providerCapture.as_of_date
            ? `Latest report date ${providerCapture.as_of_date}.`
            : null;
        const missingExperian =
            providerCapture.bureau_coverage?.missing_bureaus?.includes(
                'experian',
            )
                ? 'Credit Karma only includes Equifax and TransUnion, so Experian is still missing.'
                : null;

        return [
            `Imported Credit Karma ${bureau}credit report.`.replace(
                /\s+/g,
                ' ',
            ),
            score,
            asOf,
            missingExperian,
        ]
            .filter(Boolean)
            .join(' ');
    }

    const smartCredit = capture.metadata?.smartcredit;

    if (!smartCredit) {
        return (
            compactText(capture.extracted_text, 220) ||
            'Stored for later review.'
        );
    }

    if (smartCredit.profile === 'score_tracker') {
        const scores = smartCredit.scores ?? {};
        const snapshots = smartCredit.score_history_chart?.labels.length ?? 0;
        const bits = [
            scores.credit ? `credit ${scores.credit}` : null,
            scores.auto ? `auto ${scores.auto}` : null,
            scores.insurance ? `insurance ${scores.insurance}` : null,
        ].filter(Boolean);

        return [
            snapshots
                ? `Imported SmartCredit score history with ${snapshots} snapshots.`
                : 'Imported SmartCredit score history.',
            bits.length ? `Latest scores: ${bits.join(', ')}.` : null,
        ]
            .filter(Boolean)
            .join(' ');
    }

    const summary = smartCredit.summary ?? {};
    const totalAccounts = summary.total_accounts?.value;
    const openAccounts = summary.open_accounts?.value;
    const derogatoryAccounts = summary.derogatory_accounts?.value;
    const previewCount = smartCredit.account_preview_count;
    const asOf = smartCredit.as_of_date
        ? `As of ${smartCredit.as_of_date}.`
        : null;
    const bureauScores = Object.values(smartCredit.bureau_scores ?? {})
        .map(
            (bureau: any) =>
                `${String(bureau.label ?? bureau.key ?? '').replace('®', '')} ${bureau.display}`,
        )
        .filter(Boolean);

    return [
        'Imported SmartCredit 3-bureau report.',
        asOf,
        bureauScores.length ? `Scores: ${bureauScores.join(', ')}.` : null,
        totalAccounts ? `${totalAccounts} total accounts.` : null,
        openAccounts ? `${openAccounts} open accounts.` : null,
        derogatoryAccounts
            ? `${derogatoryAccounts} derogatory accounts.`
            : null,
        previewCount ? `${previewCount} tradeline previews extracted.` : null,
    ]
        .filter(Boolean)
        .join(' ');
};

const markSmartCreditRowReviewed = (
    row: ReturnType<typeof smartCreditBureauMatrixRows>[number],
) => {
    if (!activeReviewCycleId.value || isSmartCreditRowReviewed(row)) {
        return;
    }

    router.post(
        `/clients/${clientRouteKey.value}/cycles/${activeReviewCycleId.value}/import-review/review`,
        {
            row_signature: smartCreditRowSignature(row),
            row_name: row.name,
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const startSmartCreditDispute = (
    row: ReturnType<typeof smartCreditBureauMatrixRows>[number],
) => {
    if (!activeReviewCycleId.value || hasSmartCreditDispute(row)) {
        return;
    }

    router.post(
        `/clients/${clientRouteKey.value}/cycles/${activeReviewCycleId.value}/import-review/dispute`,
        {
            row_signature: smartCreditRowSignature(row),
            row_name: row.name,
            row_status: row.status,
            row_category: row.category,
            dispute_reason: selectedSmartCreditReason(row),
            flags: smartCreditMetroFlags(row).map((flag) => flag.label),
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const createCycle = () => {
    cycleForm.post(`/clients/${clientRouteKey.value}/cycles`, {
        preserveScroll: true,
        onSuccess: () => cycleForm.reset('cycle_label'),
    });
};

const saveClientProfile = () => {
    normalizeClientNameForm(clientProfileForm);
    clientProfileForm.phone = formatUsPhone(clientProfileForm.phone);

    clientProfileForm.patch(`/clients/${clientRouteKey.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            clientProfileForm.reset('ssn');
            clientProfileEditing.value = false;
        },
    });
};

const normalizeClientProfileName = () => {
    normalizeClientNameForm(clientProfileForm);
};

const endClientRelationship = () => {
    relationshipForm.post(`/clients/${clientRouteKey.value}/end-relationship`, {
        preserveScroll: true,
    });
};

const saveManualBilling = () => {
    manualBillingForm.post(`/clients/${clientRouteKey.value}/billing/manual`, {
        preserveScroll: true,
        onSuccess: () => manualBillingForm.reset('notes'),
    });
};

const armDeleteLead = () => {
    deleteLeadConfirmationArmed.value = true;
};

const deleteLead = () => {
    if (!deleteLeadConfirmationArmed.value) {
        armDeleteLead();

        return;
    }

    deleteLeadForm.delete(`/clients/${clientRouteKey.value}`, {
        preserveScroll: false,
        onFinish: () => {
            deleteLeadConfirmationArmed.value = false;
        },
    });
};

const importSnapshot = () => {
    snapshotForm.post(`/clients/${clientRouteKey.value}/snapshots`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            snapshotForm.reset(
                'report_file',
                'creditor_name',
                'balance',
                'credit_limit',
                'account_status',
                'payment_status',
                'remarks',
            );
            snapshotForm.source = 'manual';
            snapshotForm.reporting_cycle_id =
                props.cycles[0]?.id?.toString() ?? '';
        },
    });
};

const handleFile = (event: Event) => {
    const input = event.target as HTMLInputElement;
    snapshotForm.report_file = input.files?.[0] ?? null;
    snapshotForm.source = input.files?.[0] ? 'csv' : 'manual';
};

const handleBrowserCaptureFile = (event: Event) => {
    const input = event.target as HTMLInputElement;
    browserCaptureForm.capture_file = input.files?.[0] ?? null;

    if (!input.files?.[0]) {
        browserCaptureForm.source_type = 'dom_capture';

        return;
    }

    browserCaptureForm.source_type = input.files[0].name.endsWith('.webarchive')
        ? 'safari_webarchive'
        : 'companion_capture';
};

const importBrowserCapture = () => {
    browserCaptureForm.post(
        `/clients/${clientRouteKey.value}/browser-captures`,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                browserCaptureForm.reset(
                    'page_title',
                    'page_url',
                    'html',
                    'capture_file',
                );
                browserCaptureForm.reporting_cycle_id =
                    props.cycles[0]?.id?.toString() ?? '';
                browserCaptureForm.source_type = 'dom_capture';
                browserCaptureForm.browser_name = 'CreditSoft companion';
                showBrowserCaptureEditor.value = false;

                if (importPanelRequested.value) {
                    router.get(
                        clientProfilePanelReturnHref(),
                        {},
                        {
                            preserveScroll: true,
                            preserveState: true,
                            replace: true,
                        },
                    );
                }
            },
        },
    );
};

const resetProviderForm = () => {
    editingProviderId.value = null;
    providerForm.reset();
    providerForm.provider_key = props.providerCatalog[0]?.key ?? 'smartcredit';
    providerForm.provider_label =
        props.providerCatalog[0]?.label ?? 'SmartCredit';
    providerForm.status = props.providerStatuses[0]?.key ?? 'connected';
};

const editProvider = (provider: (typeof props.providers)[number]) => {
    showProviderEditor.value = true;
    editingProviderId.value = provider.id;
    providerForm.provider_key = provider.provider_key;
    providerForm.provider_label = provider.provider_label;
    providerForm.login_email = provider.login_email ?? '';
    providerForm.login_username = provider.login_username ?? '';
    providerForm.login_password = '';
    providerForm.security_answer = '';
    providerForm.status = provider.status;
    providerForm.notes = provider.notes ?? '';
};

const startSmartCreditSetup = () => {
    showProviderEditor.value = true;
    scrollToPanel(providerSectionId);
    const provider = smartCreditProvider.value;

    if (provider) {
        editProvider(provider);

        return;
    }

    resetProviderForm();
    providerForm.provider_key = 'smartcredit';
    providerForm.provider_label =
        providerCatalogMap.value.smartcredit?.label ?? 'SmartCredit';
};

const saveProvider = () => {
    providerForm.post(`/clients/${clientRouteKey.value}/providers`, {
        preserveScroll: true,
        onSuccess: () => {
            resetProviderForm();
            showProviderEditor.value = false;

            if (providerPanelRequested.value) {
                router.get(
                    clientProfilePanelReturnHref(),
                    {},
                    {
                        preserveScroll: true,
                        preserveState: true,
                        replace: true,
                    },
                );
            }
        },
    });
};

const removeProvider = (provider: (typeof props.providers)[number]) => {
    router.delete(`/clients/${clientRouteKey.value}/providers/${provider.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            delete revealedProviderCredentials[provider.id];
            delete revealProviderErrors[provider.id];

            if (editingProviderId.value === provider.id) {
                resetProviderForm();
            }
        },
    });
};

const hideProviderCredentials = (
    provider: (typeof props.providers)[number],
) => {
    delete revealedProviderCredentials[provider.id];
    delete revealProviderErrors[provider.id];
};

const revealProviderCredentials = async (
    provider: (typeof props.providers)[number],
) => {
    if (
        !canRevealProviderCredentials.value ||
        !providerHasRevealableCredentials(provider)
    ) {
        return;
    }

    if (storedProviderCredentials(provider)) {
        hideProviderCredentials(provider);

        return;
    }

    revealingProviderId.value = provider.id;
    delete revealProviderErrors[provider.id];

    try {
        const response = await fetch(
            `/clients/${clientRouteKey.value}/providers/${provider.id}/credentials`,
            {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        const payload = (await response.json().catch(() => null)) as {
            data?: RevealedProviderCredentials;
            message?: string;
        } | null;

        if (!response.ok) {
            throw new Error(
                payload?.message ??
                    'Unable to reveal saved provider credentials.',
            );
        }

        if (!payload?.data) {
            throw new Error(
                'Saved provider credentials were not returned by the office app.',
            );
        }

        revealedProviderCredentials[provider.id] = payload.data;
    } catch (error) {
        revealProviderErrors[provider.id] =
            error instanceof Error
                ? error.message
                : 'Unable to reveal saved provider credentials.';
    } finally {
        revealingProviderId.value = null;
    }
};

const toggleProviderEditor = () => {
    if (showProviderEditor.value) {
        resetProviderForm();

        if (providerPanelRequested.value) {
            showProviderEditor.value = false;
            router.get(
                clientProfilePanelReturnHref(),
                {},
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );

            return;
        }
    }

    showProviderEditor.value = !showProviderEditor.value;

    if (showProviderEditor.value) {
        scrollToPanel(providerSectionId);
    }
};

const closeBrowserCaptureEditor = () => {
    showBrowserCaptureEditor.value = false;

    if (importPanelRequested.value) {
        router.get(
            clientProfilePanelReturnHref(),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }
};

const openDeleteBrowserCaptureDialog = (
    capture: (typeof props.client.browser_captures)[number],
) => {
    capturePendingDelete.value = capture;
    deleteConfirmationArmed.value = false;
};

const closeDeleteBrowserCaptureDialog = () => {
    capturePendingDelete.value = null;
    deleteConfirmationArmed.value = false;
};

const armDeleteBrowserCapture = () => {
    deleteConfirmationArmed.value = true;
};

const deleteBrowserCapture = () => {
    if (!capturePendingDelete.value || !deleteConfirmationArmed.value) {
        return;
    }

    router.delete(
        `/clients/${clientRouteKey.value}/browser-captures/${capturePendingDelete.value.id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                closeDeleteBrowserCaptureDialog();
            },
        },
    );
};

const pruneBrowserCaptureDuplicates = () => {
    if (!props.browserCaptureDuplicates.extra_count) {
        return;
    }

    if (
        !window.confirm(
            `Prune ${props.browserCaptureDuplicates.extra_count} duplicate browser capture(s) from today?`,
        )
    ) {
        return;
    }

    router.delete(
        `/clients/${clientRouteKey.value}/browser-captures/prune-duplicates`,
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head :title="`Client | ${clientPageTitle}`" />

    <h1 class="sr-only">{{ client.display_name }}</h1>

    <div class="space-y-8">
        <ClientWorkspaceNav
            :client-id="client.id"
            :health-signal="clientHealth"
            class="relative isolate z-50 -mt-[10px] mr-8 -mb-[22px] ml-10 w-auto overflow-visible"
        />

        <AiSetupPrompt v-if="showAiSetup && !focusPanelMode" compact />

        <section
            v-if="!focusPanelMode"
            class="relative z-0 mt-[25px] mr-5 ml-10 rounded-[28px] border p-5 shadow-sm"
            :class="clientHealthPanelClass(clientHealth)"
        >
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div class="min-w-0">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Client health
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <h2
                            class="truncate text-2xl font-semibold tracking-tight text-stone-950"
                        >
                            {{ clientPageTitle }}
                        </h2>
                        <span
                            v-if="clientHealthLabel(clientHealth)"
                            class="inline-flex max-w-full items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] uppercase"
                            :class="clientHealthBadgeClass(clientHealth)"
                        >
                            <span
                                class="size-2 rounded-full"
                                :class="clientHealthDotClass(clientHealth)"
                            />
                            <span>{{ clientHealthLabel(clientHealth) }}</span>
                        </span>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-stone-700">
                        {{
                            clientHealthDetail(clientHealth) ||
                            'No client health signal has been calculated yet.'
                        }}
                    </p>
                </div>

                <div class="text-left sm:text-right">
                    <p
                        class="text-4xl font-semibold tracking-tight text-stone-950"
                    >
                        {{ clientHealthScoreLabel(clientHealth) ?? '50/100' }}
                    </p>
                    <p
                        class="mt-1 text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                    >
                        Internal score
                    </p>
                    <p
                        class="mt-2 text-sm font-medium text-stone-700 capitalize"
                    >
                        {{ clientRelationshipLabel }}
                    </p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="metric in clientHealthMetrics"
                    :key="metric.label"
                    class="min-h-24 rounded-lg border border-stone-300/65 bg-white/55 px-4 py-3 shadow-[0_1px_0_rgba(0,0,0,0.03)] backdrop-blur"
                >
                    <p
                        class="text-[10px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                    >
                        {{ metric.label }}
                    </p>
                    <p
                        class="mt-3 text-3xl font-semibold tracking-tight text-stone-950"
                    >
                        {{ metric.value }}
                    </p>
                    <p class="mt-1 text-sm leading-5 text-stone-600">
                        {{ metric.hint }}
                    </p>
                </div>
            </div>
        </section>

        <section
            v-if="!focusPanelMode"
            :id="billingSectionId"
            class="mr-5 ml-10 scroll-mt-24 rounded-[28px] border border-stone-300/70 bg-white/85 p-5 shadow-sm"
        >
            <div
                class="flex flex-wrap items-start justify-between gap-4 border-b border-stone-300/70 pb-4"
            >
                <div class="flex min-w-0 items-start gap-3">
                    <FontAwesomeIcon
                        :icon="faMoneyBillWave"
                        class="mt-1 h-5 w-5 text-emerald-700"
                    />
                    <div class="min-w-0">
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            Billing access
                        </p>
                        <h2
                            class="mt-1 text-xl font-semibold tracking-tight text-stone-950"
                        >
                            Manual payment, pro bono, or owner comp
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-stone-600">
                            {{ billingProfileSummary }}
                        </p>
                    </div>
                </div>
                <div class="text-left sm:text-right">
                    <p
                        class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                    >
                        Client ID
                    </p>
                    <p class="mt-1 font-mono text-sm text-stone-900">
                        {{ client.cuid ?? client.id }}
                    </p>
                </div>
            </div>

            <form
                class="mt-5 grid gap-4 xl:grid-cols-[0.82fr_0.52fr_0.52fr_0.52fr_1fr_auto]"
                @submit.prevent="saveManualBilling"
            >
                <div class="space-y-2">
                    <label
                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                        >Record type</label
                    >
                    <select
                        v-model="manualBillingForm.kind"
                        class="h-11 w-full rounded-xl border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="cash">Cash</option>
                        <option value="zelle">Zelle</option>
                        <option value="cash_app">Cash App</option>
                        <option value="check">Check</option>
                        <option value="manual">Manual</option>
                        <option value="pro_bono">Pro bono</option>
                        <option value="owner_comp">Owner comp</option>
                    </select>
                    <p
                        v-if="manualBillingForm.errors.kind"
                        class="text-xs text-rose-700"
                    >
                        {{ manualBillingForm.errors.kind }}
                    </p>
                </div>

                <div class="space-y-2">
                    <label
                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                        >Amount</label
                    >
                    <Input
                        v-model="manualBillingForm.amount"
                        inputmode="decimal"
                        :disabled="manualBillingIsComped"
                    />
                    <p
                        v-if="manualBillingForm.errors.amount"
                        class="text-xs text-rose-700"
                    >
                        {{ manualBillingForm.errors.amount }}
                    </p>
                </div>

                <div class="space-y-2">
                    <label
                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                        >Paid on</label
                    >
                    <Input v-model="manualBillingForm.paid_at" type="date" />
                    <p
                        v-if="manualBillingForm.errors.paid_at"
                        class="text-xs text-rose-700"
                    >
                        {{ manualBillingForm.errors.paid_at }}
                    </p>
                </div>

                <div class="space-y-2">
                    <label
                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                        >Term</label
                    >
                    <select
                        v-model="manualBillingForm.billing_interval"
                        class="h-11 w-full rounded-xl border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="monthly">Monthly</option>
                        <option value="annual">Annual</option>
                        <option value="one_time">One-time</option>
                        <option value="lifetime">Lifetime</option>
                    </select>
                    <p
                        v-if="manualBillingForm.errors.billing_interval"
                        class="text-xs text-rose-700"
                    >
                        {{ manualBillingForm.errors.billing_interval }}
                    </p>
                </div>

                <div class="space-y-2">
                    <label
                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                        >Note</label
                    >
                    <Input
                        v-model="manualBillingForm.notes"
                        :placeholder="`${manualBillingKindLabel} note`"
                    />
                    <p
                        v-if="manualBillingForm.errors.notes"
                        class="text-xs text-rose-700"
                    >
                        {{ manualBillingForm.errors.notes }}
                    </p>
                </div>

                <div class="flex items-end">
                    <button
                        type="submit"
                        class="h-11 rounded-full bg-stone-950 px-4 text-xs font-medium tracking-[0.2em] whitespace-nowrap text-stone-50 uppercase transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="manualBillingForm.processing"
                    >
                        {{
                            manualBillingForm.processing
                                ? 'Saving...'
                                : 'Save billing'
                        }}
                    </button>
                </div>
            </form>
        </section>

        <section
            v-if="!focusPanelMode"
            class="mr-5 ml-10 rounded-[28px] border border-stone-300/70 bg-white/85 p-5 shadow-sm"
        >
            <div class="space-y-5">
                <div
                    class="flex items-start justify-between gap-4 border-b border-stone-300/70 pb-4"
                >
                    <div>
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            Personal & contact info
                        </p>
                    </div>
                    <button
                        type="button"
                        class="-mt-1 inline-flex items-center gap-2 text-sm font-medium text-stone-500 transition hover:text-stone-950"
                        :aria-label="
                            clientProfileEditing
                                ? 'Close profile editor'
                                : 'Edit personal and contact info'
                        "
                        :title="
                            clientProfileEditing
                                ? 'Close editor'
                                : 'Edit profile'
                        "
                        @click="clientProfileEditing = !clientProfileEditing"
                    >
                        <FontAwesomeIcon :icon="faPencil" class="text-lg" />
                        <span class="sr-only">
                            {{ clientProfileEditing ? 'Close edit' : 'Edit' }}
                        </span>
                    </button>
                </div>

                <div
                    v-if="!clientProfileEditing"
                    class="grid gap-6 xl:grid-cols-[minmax(22rem,1.15fr)_minmax(20rem,0.9fr)_minmax(16rem,0.7fr)]"
                >
                    <div>
                        <p
                            class="text-[11px] font-semibold tracking-[0.24em] text-stone-500 uppercase"
                        >
                            Mail to
                        </p>

                        <address
                            class="mt-4 min-h-32 space-y-1 font-serif text-xl leading-7 text-stone-950 not-italic"
                        >
                            <span
                                v-for="line in clientMailingLabelLines"
                                :key="line"
                                class="block"
                            >
                                {{ line }}
                            </span>
                        </address>

                        <p
                            class="mt-4 font-mono text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                        >
                            Client ID {{ client.cuid || `local-${client.id}` }}
                        </p>

                        <div
                            class="mt-5 border-t border-dashed border-stone-300 pt-3"
                        >
                            <p
                                class="font-mono text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                            >
                                Barcode {{ latestProfilePostalBarcodeValue }}
                            </p>
                        </div>
                    </div>

                    <div class="grid content-start gap-3 sm:grid-cols-2">
                        <div
                            v-for="item in clientProfileSummaryItems"
                            :key="item.label"
                            class="border-t border-stone-200 pt-3"
                        >
                            <p
                                class="text-[10px] font-semibold tracking-[0.2em] text-stone-500 uppercase"
                            >
                                {{ item.label }}
                            </p>
                            <p class="mt-1 text-sm font-medium text-stone-950">
                                {{ item.value }}
                            </p>
                        </div>
                    </div>

                    <div class="content-start">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >
                                    Profile history
                                </p>
                                <p
                                    class="mt-1 text-xs leading-5 text-stone-600"
                                >
                                    {{ profileSnapshotCount }} saved
                                    {{
                                        profileSnapshotCount === 1
                                            ? 'record'
                                            : 'records'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="client.profile_snapshots?.length"
                            class="mt-3 space-y-3"
                        >
                            <div
                                v-for="snapshot in client.profile_snapshots.slice(
                                    0,
                                    3,
                                )"
                                :key="snapshot.id"
                                class="border-t border-stone-200 pt-3"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <p
                                        class="text-sm font-medium text-stone-950"
                                    >
                                        {{
                                            snapshot.source_label ||
                                            snapshot.source ||
                                            'Profile update'
                                        }}
                                    </p>
                                    <p class="text-[11px] text-stone-500">
                                        {{
                                            formatDateTime(snapshot.recorded_at)
                                        }}
                                    </p>
                                </div>
                                <p
                                    class="mt-1 text-xs leading-5 text-stone-600"
                                >
                                    {{
                                        profileSnapshotChangedFields(
                                            snapshot.changed_fields,
                                        ) || 'Initial profile snapshot'
                                    }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-sm leading-6 text-stone-600">
                            No profile snapshots yet.
                        </p>
                    </div>
                </div>

                <form
                    v-if="clientProfileEditing"
                    class="space-y-5"
                    @submit.prevent="saveClientProfile"
                >
                    <div class="space-y-6">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.2em] text-stone-500 uppercase"
                            >
                                Legal name
                            </p>
                            <div class="mt-3 grid gap-4 lg:grid-cols-4">
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >First name</span
                                    >
                                    <Input
                                        v-model="clientProfileForm.first_name"
                                        autocomplete="given-name"
                                        @blur="normalizeClientProfileName"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >Middle name</span
                                    >
                                    <Input
                                        v-model="clientProfileForm.middle_name"
                                        autocomplete="additional-name"
                                        @blur="normalizeClientProfileName"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >Last name</span
                                    >
                                    <Input
                                        v-model="clientProfileForm.last_name"
                                        autocomplete="family-name"
                                        @blur="normalizeClientProfileName"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >Suffix</span
                                    >
                                    <Input
                                        v-model="clientProfileForm.name_suffix"
                                        autocomplete="honorific-suffix"
                                        placeholder="Jr, Sr, III"
                                        @blur="normalizeClientProfileName"
                                    />
                                </label>
                            </div>
                        </div>

                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.2em] text-stone-500 uppercase"
                            >
                                Contact
                            </p>
                            <div
                                class="mt-3 grid gap-4 lg:grid-cols-2 xl:grid-cols-4"
                            >
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >Email</span
                                    >
                                    <Input
                                        v-model="clientProfileForm.email"
                                        type="email"
                                        autocomplete="email"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >Phone</span
                                    >
                                    <Input
                                        v-model="clientProfileForm.phone"
                                        autocomplete="tel"
                                        @blur="
                                            clientProfileForm.phone =
                                                formatUsPhone(
                                                    clientProfileForm.phone,
                                                )
                                        "
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >Secondary email</span
                                    >
                                    <Input
                                        v-model="
                                            clientProfileForm.secondary_email
                                        "
                                        type="email"
                                    />
                                </label>
                            </div>
                        </div>

                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.2em] text-stone-500 uppercase"
                            >
                                Address
                            </p>
                            <div
                                class="mt-3 grid gap-4 lg:grid-cols-2 xl:grid-cols-4"
                            >
                                <label class="space-y-2 lg:col-span-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >Street address</span
                                    >
                                    <Input
                                        v-model="
                                            clientProfileForm.address_line_1
                                        "
                                        autocomplete="address-line1"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >Apt / suite</span
                                    >
                                    <Input
                                        v-model="
                                            clientProfileForm.address_line_2
                                        "
                                        autocomplete="address-line2"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >City</span
                                    >
                                    <Input
                                        v-model="clientProfileForm.city"
                                        autocomplete="address-level2"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >State</span
                                    >
                                    <Input
                                        v-model="clientProfileForm.state"
                                        autocomplete="address-level1"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >Postal code</span
                                    >
                                    <Input
                                        v-model="clientProfileForm.postal_code"
                                        autocomplete="postal-code"
                                    />
                                </label>
                            </div>
                        </div>

                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.2em] text-stone-500 uppercase"
                            >
                                Personal details
                            </p>
                            <div class="mt-3 grid gap-4 lg:grid-cols-3">
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >Date of birth</span
                                    >
                                    <Input
                                        v-model="
                                            clientProfileForm.date_of_birth
                                        "
                                        type="date"
                                        autocomplete="bday"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                        >Current score</span
                                    >
                                    <Input
                                        v-model="
                                            clientProfileForm.current_score
                                        "
                                        inputmode="numeric"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                    >
                                        SSN
                                    </span>
                                    <Input
                                        v-model="clientProfileForm.ssn"
                                        autocomplete="off"
                                        inputmode="numeric"
                                        placeholder="Enter when available"
                                        type="password"
                                    />
                                    <p class="text-xs leading-5 text-stone-500">
                                        {{
                                            client.ssn_last_four
                                                ? `Stored ending ${client.ssn_last_four}. Full number is never shown here.`
                                                : 'Not saved yet. Save the full number or last four when the office has it.'
                                        }}
                                    </p>
                                    <p
                                        v-if="clientProfileForm.errors.ssn"
                                        class="text-xs text-rose-700"
                                    >
                                        {{ clientProfileForm.errors.ssn }}
                                    </p>
                                </label>
                            </div>
                        </div>
                    </div>

                    <label class="block space-y-2">
                        <span
                            class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                            >Goals / intake notes</span
                        >
                        <Textarea
                            v-model="clientProfileForm.goals"
                            class="min-h-24"
                        />
                    </label>

                    <div class="flex items-center justify-end gap-3">
                        <p
                            v-if="clientProfileForm.recentlySuccessful"
                            class="text-sm font-medium text-emerald-700"
                        >
                            Saved.
                        </p>
                        <button
                            type="submit"
                            class="rounded-full bg-stone-950 px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-50 uppercase transition hover:bg-stone-800 disabled:opacity-50"
                            :disabled="clientProfileForm.processing"
                        >
                            {{
                                clientProfileForm.processing
                                    ? 'Saving'
                                    : 'Save profile'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section
            v-if="!focusPanelMode && mappedScoreExperience"
            class="space-y-6"
        >
            <div
                class="rounded-[28px] border border-stone-300/70 bg-stone-50/75 p-5"
            >
                <div class="border-b border-stone-300/70 pb-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Imported SmartCredit score history
                    </p>
                    <p class="text-sm text-stone-600">
                        {{
                            scoreTimeline.as_of_date
                                ? `SmartCredit score history as of ${scoreTimeline.as_of_date}.`
                                : 'Rebuilt from the latest imported SmartCredit score capture.'
                        }}
                    </p>
                </div>

                <div class="mt-4">
                    <MultiLineTrendChart
                        :labels="scoreTimeline.labels"
                        :series="scoreTimeline.series"
                        :height="320"
                    />
                </div>
            </div>

            <div
                class="grid gap-6"
                :class="
                    smartCreditScoreCards.length > 0
                        ? 'xl:grid-cols-[0.9fr_1.1fr]'
                        : ''
                "
            >
                <div class="space-y-4">
                    <div
                        class="space-y-4 rounded-[28px] border border-stone-300/70 bg-white/85 p-5"
                    >
                        <div class="border-b border-stone-300/70 pb-3">
                            <p
                                class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                            >
                                Latest imported SmartCredit scores
                            </p>
                            <p class="text-sm text-stone-600">
                                {{
                                    scoreTimeline.source?.page_title ??
                                    'Latest SmartCredit score capture'
                                }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="point in recentScorePoints"
                                :key="point.recorded_on ?? point.date"
                                class="flex items-center justify-between rounded-2xl border border-stone-300/70 bg-stone-50/60 px-4 py-3"
                            >
                                <div>
                                    <p class="font-medium text-stone-900">
                                        {{ point.date }}
                                    </p>
                                    <p
                                        class="text-xs tracking-[0.2em] text-stone-500 uppercase"
                                    >
                                        {{
                                            point.recorded_on
                                                ? formatDate(point.recorded_on)
                                                : 'Imported score point'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="flex flex-wrap gap-2 text-xs font-medium tracking-[0.18em] text-stone-600 uppercase"
                                >
                                    <span
                                        v-if="
                                            point.credit !== null &&
                                            point.credit !== undefined
                                        "
                                        >Credit {{ point.credit }}</span
                                    >
                                    <span
                                        v-if="
                                            point.auto !== null &&
                                            point.auto !== undefined
                                        "
                                        >Auto {{ point.auto }}</span
                                    >
                                    <span
                                        v-if="
                                            point.insurance !== null &&
                                            point.insurance !== undefined
                                        "
                                        >Insurance {{ point.insurance }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="smartCreditScoreCards.length > 0"
                    class="grid gap-4 sm:grid-cols-2"
                >
                    <ScoreGaugeDial
                        v-for="card in smartCreditScoreCards"
                        :key="card.key"
                        :label="card.label"
                        :value-display="card.value_display"
                        :numeric="card.numeric ?? null"
                        :grade="card.grade_display ?? card.grade ?? null"
                        :detail-url="card.detail_url ?? null"
                        :scale="card.scale ?? 'score'"
                    />
                </div>
            </div>
        </section>

        <section
            v-else-if="!focusPanelMode && scoreTimeline.series.length > 0"
            class="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]"
        >
            <div
                class="rounded-[28px] border border-stone-300/70 bg-stone-50/75 p-5"
            >
                <div class="border-b border-stone-300/70 pb-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Imported score history
                    </p>
                    <p class="text-sm text-stone-600">
                        {{
                            scoreTimeline.as_of_date
                                ? `${scoreTimeline.source?.provider_label ?? 'Imported'} score history as of ${scoreTimeline.as_of_date}.`
                                : 'Rebuilt from the latest imported score capture.'
                        }}
                    </p>
                </div>

                <div class="mt-4">
                    <MultiLineTrendChart
                        :labels="scoreTimeline.labels"
                        :series="scoreTimeline.series"
                        :height="280"
                    />
                </div>
            </div>

            <div
                class="space-y-4 rounded-[28px] border border-stone-300/70 bg-white/85 p-5"
            >
                <div class="border-b border-stone-300/70 pb-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Latest imported scores
                    </p>
                    <p class="text-sm text-stone-600">
                        {{
                            scoreTimeline.source?.page_title ??
                            `Latest ${scoreTimeline.source?.provider_label ?? 'provider'} score capture`
                        }}
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div
                        class="rounded-2xl border border-stone-300/70 bg-stone-50/80 px-4 py-3"
                    >
                        <p
                            class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                        >
                            Credit
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-stone-950">
                            {{ latestScorePoint?.credit ?? 'N/A' }}
                        </p>
                    </div>
                    <div
                        class="rounded-2xl border border-stone-300/70 bg-stone-50/80 px-4 py-3"
                    >
                        <p
                            class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                        >
                            Auto
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-stone-950">
                            {{ latestScorePoint?.auto ?? 'N/A' }}
                        </p>
                    </div>
                    <div
                        class="rounded-2xl border border-stone-300/70 bg-stone-50/80 px-4 py-3"
                    >
                        <p
                            class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                        >
                            Insurance
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-stone-950">
                            {{ latestScorePoint?.insurance ?? 'N/A' }}
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="point in recentScorePoints"
                        :key="point.recorded_on ?? point.date"
                        class="flex items-center justify-between rounded-2xl border border-stone-300/70 bg-stone-50/60 px-4 py-3"
                    >
                        <div>
                            <p class="font-medium text-stone-900">
                                {{ point.date }}
                            </p>
                            <p
                                class="text-xs tracking-[0.2em] text-stone-500 uppercase"
                            >
                                {{
                                    point.recorded_on
                                        ? formatDate(point.recorded_on)
                                        : 'Imported score point'
                                }}
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap gap-2 text-xs font-medium tracking-[0.18em] text-stone-600 uppercase"
                        >
                            <span
                                v-if="
                                    point.credit !== null &&
                                    point.credit !== undefined
                                "
                                >Credit {{ point.credit }}</span
                            >
                            <span
                                v-if="
                                    point.auto !== null &&
                                    point.auto !== undefined
                                "
                                >Auto {{ point.auto }}</span
                            >
                            <span
                                v-if="
                                    point.insurance !== null &&
                                    point.insurance !== undefined
                                "
                                >Insurance {{ point.insurance }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="
                !focusPanelMode &&
                (latestSmartCreditThreeBureauCapture ||
                    latestSmartCreditReportCapture)
            "
            class="space-y-4"
        >
            <div
                v-if="latestSmartCreditThreeBureauCapture"
                class="space-y-5 rounded-[28px] border border-stone-300/70 bg-stone-50/75 p-5"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-4 border-b border-stone-300/70 pb-4"
                >
                    <div>
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            3-bureau review
                        </p>
                        <p class="text-lg font-semibold text-stone-950">
                            {{
                                latestSmartCreditThreeBureauCapture.page_title ||
                                '3-Bureau Credit Report & Scores'
                            }}
                        </p>
                        <p class="mt-2 text-sm text-stone-600">
                            {{
                                captureSummary(
                                    latestSmartCreditThreeBureauCapture,
                                )
                            }}
                        </p>
                        <p
                            v-if="smartCreditThreeBureauScoreFallbackActive"
                            class="mt-2 text-xs font-medium text-amber-700"
                        >
                            Latest capture did not include bureau scores; using
                            the most recent scored SmartCredit report from
                            {{
                                smartCreditThreeBureauScoreCapture?.imported_at
                                    ? formatCaptureTimestamp(
                                          smartCreditThreeBureauScoreCapture.imported_at,
                                      )
                                    : 'the prior capture'
                            }}.
                        </p>
                    </div>
                    <div class="text-right">
                        <p
                            class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                        >
                            Imported
                        </p>
                        <p class="mt-2 text-sm font-medium text-stone-900">
                            {{
                                latestSmartCreditThreeBureauCapture.imported_at
                                    ? formatCaptureTimestamp(
                                          latestSmartCreditThreeBureauCapture.imported_at,
                                      )
                                    : 'Imported'
                            }}
                        </p>
                        <div
                            v-if="
                                smartCreditThreeBureauData
                                    ?.available_report_dates?.length
                            "
                            class="mt-3 flex flex-wrap justify-end gap-2"
                        >
                            <span
                                v-for="date in smartCreditThreeBureauData.available_report_dates"
                                :key="date"
                                class="rounded-full border border-stone-300 bg-white px-3 py-1 text-[11px] font-medium tracking-[0.18em] text-stone-700 uppercase"
                            >
                                {{ date }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-3">
                    <article
                        v-for="bureau in smartCreditBureauCards"
                        :key="bureau.key"
                        class="space-y-4 rounded-[24px] border border-stone-300/70 bg-white/90 p-5"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <BureauWordmark
                                :bureau="bureau.key"
                                class="h-7 w-auto max-w-[170px] object-contain"
                            />
                            <div class="text-right">
                                <p
                                    class="text-3xl font-semibold tracking-tight text-stone-950"
                                >
                                    {{ bureau.score.display }}
                                </p>
                                <p
                                    class="mt-1 text-[11px] tracking-[0.18em] uppercase"
                                    :class="
                                        bureau.score.available
                                            ? 'text-emerald-700'
                                            : 'text-amber-700'
                                    "
                                >
                                    {{
                                        bureau.score.available
                                            ? 'Score available'
                                            : 'Score unavailable'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                class="rounded-2xl border border-stone-300/70 bg-stone-50/80 px-4 py-3"
                            >
                                <p
                                    class="text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    Tradelines
                                </p>
                                <p
                                    class="mt-2 text-xl font-semibold text-stone-950"
                                >
                                    {{ formatNumber(bureau.tradelineCount) }}
                                </p>
                            </div>
                            <div
                                class="rounded-2xl border border-stone-300/70 bg-stone-50/80 px-4 py-3"
                            >
                                <p
                                    class="text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    Missing
                                </p>
                                <p
                                    class="mt-2 text-xl font-semibold text-stone-950"
                                >
                                    {{ formatNumber(bureau.missingCount) }}
                                </p>
                            </div>
                            <div
                                class="rounded-2xl border border-stone-300/70 bg-stone-50/80 px-4 py-3"
                            >
                                <p
                                    class="text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    Mismatches
                                </p>
                                <p
                                    class="mt-2 text-xl font-semibold text-stone-950"
                                >
                                    {{ formatNumber(bureau.mismatchCount) }}
                                </p>
                            </div>
                            <div
                                class="rounded-2xl border border-stone-300/70 bg-stone-50/80 px-4 py-3"
                            >
                                <p
                                    class="text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    Matches
                                </p>
                                <p
                                    class="mt-2 text-xl font-semibold text-stone-950"
                                >
                                    {{ formatNumber(bureau.matchedCount) }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="grid gap-5 xl:grid-cols-[0.72fr_1.28fr]">
                    <div
                        class="space-y-3 rounded-[24px] border border-stone-300/70 bg-white/90 p-5"
                    >
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            Imported summary
                        </p>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <div
                                v-for="row in smartCreditSummaryRows(
                                    latestSmartCreditThreeBureauCapture,
                                )"
                                :key="row.key"
                                class="rounded-2xl border border-stone-300/70 bg-stone-50/80 px-4 py-3"
                            >
                                <p
                                    class="text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    {{ row.label }}
                                </p>
                                <p
                                    class="mt-2 text-lg font-semibold text-stone-950"
                                >
                                    {{ row.value }}
                                </p>
                                <p
                                    v-if="row.match"
                                    class="mt-1 text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    {{ row.match }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="
                            smartCreditBureauMatrixRows(
                                smartCreditThreeBureauMatrixCapture,
                            ).length
                        "
                        class="space-y-3 rounded-[24px] border border-stone-300/70 bg-white/90 p-5"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                >
                                    Metro 2 inaccuracy review
                                </p>
                                <p class="mt-2 text-sm text-stone-600">
                                    Side-by-side bureau coverage for the
                                    imported SmartCredit tradelines.
                                </p>
                            </div>
                            <div
                                class="flex flex-wrap gap-2 text-[11px] font-medium tracking-[0.18em] text-stone-600 uppercase"
                            >
                                <span
                                    class="rounded-full border border-stone-300 bg-stone-50 px-3 py-1"
                                    >Reviewed {{ accountsReviewedMetric }}</span
                                >
                                <span
                                    class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-amber-900"
                                    >Disputes {{ priorityDisputesMetric }}</span
                                >
                                <span
                                    class="rounded-full border border-sky-300 bg-sky-50 px-3 py-1 text-sky-900"
                                    >Suggested
                                    {{ smartCreditSuggestedDisputeCount }}</span
                                >
                            </div>
                        </div>

                        <div
                            class="overflow-hidden rounded-[22px] border border-stone-300/70"
                        >
                            <div
                                class="grid grid-cols-[minmax(0,1.25fr)_minmax(0,0.62fr)_minmax(0,0.62fr)_minmax(0,0.62fr)_minmax(0,0.95fr)] gap-px bg-stone-300/70 text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                            >
                                <div class="bg-stone-50 px-4 py-3">
                                    Tradeline
                                </div>
                                <div
                                    v-for="bureauKey in smartCreditBureauOrder"
                                    :key="`bureau-heading-${bureauKey}`"
                                    class="flex items-center justify-center bg-stone-50 px-3 py-3"
                                >
                                    <BureauWordmark
                                        :bureau="bureauKey"
                                        class="h-4 w-auto max-w-[110px] object-contain"
                                    />
                                </div>
                                <div class="bg-stone-50 px-4 py-3">Flags</div>
                            </div>

                            <div
                                v-for="row in smartCreditBureauMatrixRows(
                                    smartCreditThreeBureauMatrixCapture,
                                )"
                                :key="`${row.name}-${row.status ?? 'row'}`"
                                class="grid grid-cols-[minmax(0,1.25fr)_minmax(0,0.62fr)_minmax(0,0.62fr)_minmax(0,0.62fr)_minmax(0,0.95fr)] gap-px border-t border-stone-300/70 bg-stone-300/70 text-sm"
                            >
                                <div class="bg-white px-4 py-3">
                                    <p class="font-medium text-stone-900">
                                        {{ row.name }}
                                    </p>
                                    <p class="mt-1 text-xs text-stone-500">
                                        {{
                                            row.status ??
                                            row.category ??
                                            'Tradeline imported from SmartCredit'
                                        }}
                                    </p>
                                    <p
                                        v-if="row.utilization"
                                        class="mt-1 text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                                    >
                                        {{ row.utilization }} utilization
                                    </p>
                                </div>

                                <div
                                    v-for="bureauKey in smartCreditBureauOrder"
                                    :key="`${row.name}-${bureauKey}`"
                                    class="flex items-center justify-center bg-white px-2 py-3"
                                >
                                    <span
                                        class="rounded-full border px-2 py-1 text-[11px] font-medium tracking-[0.16em] uppercase"
                                        :class="
                                            smartCreditCoverageClass(
                                                row.coverage[bureauKey],
                                            )
                                        "
                                    >
                                        {{
                                            smartCreditCoverageText(
                                                row.coverage[bureauKey],
                                            )
                                        }}
                                    </span>
                                </div>

                                <div class="space-y-2 bg-white px-4 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        <ReviewSignalLabel
                                            v-for="flag in smartCreditMetroFlags(
                                                row,
                                            )"
                                            :key="`${row.name}-${flag.key}`"
                                            :kind="flag.key"
                                            :label="flag.label"
                                            :title="flag.title"
                                            :style-key="reviewLabelStyle"
                                        />
                                    </div>
                                    <select
                                        v-if="
                                            smartCreditMetroFlags(row).length &&
                                            creditReasonOptions.length
                                        "
                                        :value="selectedSmartCreditReason(row)"
                                        class="w-full rounded-full border border-stone-300 bg-white px-3 py-2 text-[11px] font-medium text-stone-800"
                                        @change="
                                            updateSmartCreditReason(
                                                row,
                                                (
                                                    $event.target as HTMLSelectElement
                                                ).value,
                                            )
                                        "
                                    >
                                        <option
                                            v-for="reason in smartCreditReasonOptions(
                                                row,
                                            )"
                                            :key="`${smartCreditRowSignature(row)}-${reason.key}`"
                                            :value="reason.reason"
                                        >
                                            {{ reason.reason }}
                                        </option>
                                    </select>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="rounded-full border px-3 py-1 text-[11px] font-medium tracking-[0.16em] uppercase transition"
                                            :class="
                                                isSmartCreditRowReviewed(row)
                                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-900'
                                                    : 'border-stone-300 bg-stone-50 text-stone-700 hover:border-stone-500'
                                            "
                                            :disabled="
                                                isSmartCreditRowReviewed(row)
                                            "
                                            @click="
                                                markSmartCreditRowReviewed(row)
                                            "
                                        >
                                            {{
                                                isSmartCreditRowReviewed(row)
                                                    ? 'Reviewed'
                                                    : 'Mark reviewed'
                                            }}
                                        </button>
                                        <button
                                            v-if="
                                                smartCreditMetroFlags(row)
                                                    .length
                                            "
                                            type="button"
                                            class="rounded-full border px-3 py-1 text-[11px] font-medium tracking-[0.16em] uppercase transition"
                                            :class="
                                                hasSmartCreditDispute(row)
                                                    ? 'border-amber-300 bg-amber-50 text-amber-900'
                                                    : 'border-rose-300 bg-rose-50 text-rose-900 hover:border-rose-500'
                                            "
                                            :disabled="
                                                hasSmartCreditDispute(row)
                                            "
                                            @click="
                                                startSmartCreditDispute(row)
                                            "
                                        >
                                            {{
                                                hasSmartCreditDispute(row)
                                                    ? 'Dispute open'
                                                    : 'Start dispute'
                                            }}
                                        </button>
                                        <a
                                            v-if="hasSmartCreditDispute(row)"
                                            :href="`/clients/${clientRouteKey}/violations`"
                                            class="inline-flex items-center rounded-full border border-stone-300 bg-white px-3 py-1 text-[11px] font-medium tracking-[0.16em] text-stone-700 uppercase transition hover:border-stone-500"
                                        >
                                            Open disputes
                                        </a>
                                    </div>
                                    <div class="space-y-1">
                                        <p
                                            v-for="field in row.evidence.slice(
                                                0,
                                                2,
                                            )"
                                            :key="`${row.name}-${field.key}`"
                                            class="text-xs leading-5 text-stone-600"
                                        >
                                            <span
                                                class="font-medium text-stone-900"
                                                >{{ field.label }}:</span
                                            >
                                            {{ field.value }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="latestSmartCreditReportCapture"
                class="rounded-[28px] border border-stone-300/70 bg-white/85 p-5"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-300/70 pb-3"
                >
                    <div>
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            Smart Credit Report detail
                        </p>
                        <p class="text-sm font-semibold text-stone-950">
                            {{
                                latestSmartCreditReportCapture.page_title ||
                                'Smart Credit Report'
                            }}
                        </p>
                    </div>
                    <p
                        class="text-xs tracking-[0.22em] text-stone-500 uppercase"
                    >
                        {{
                            latestSmartCreditReportCapture.imported_at
                                ? formatCaptureTimestamp(
                                      latestSmartCreditReportCapture.imported_at,
                                  )
                                : 'Imported'
                        }}
                    </p>
                </div>

                <div
                    v-if="
                        smartCreditAccountPreviewRows(
                            latestSmartCreditReportCapture,
                        ).length
                    "
                    class="mt-4 grid gap-3 lg:grid-cols-2 xl:grid-cols-3"
                >
                    <div
                        v-for="row in smartCreditAccountPreviewRows(
                            latestSmartCreditReportCapture,
                        )"
                        :key="`${row.name}-${row.primary ?? 'row'}`"
                        class="rounded-2xl border border-stone-300/70 bg-stone-50/75 px-4 py-3"
                    >
                        <p class="font-medium text-stone-900">{{ row.name }}</p>
                        <p class="mt-1 text-sm text-stone-500">
                            {{ row.primary ?? 'Type unavailable' }}
                        </p>
                        <p
                            v-if="row.secondary"
                            class="mt-2 text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                        >
                            {{ row.secondary }}
                        </p>
                        <p
                            v-if="row.negative"
                            class="mt-2 text-[11px] tracking-[0.18em] text-rose-600 uppercase"
                        >
                            Negative
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="!importPanelRequested"
            :id="providerSectionId"
            class="scroll-mt-24 space-y-4"
        >
            <div class="space-y-4">
                <div class="border-b border-stone-300/70 pb-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Credit monitoring systems
                    </p>
                    <p class="text-sm text-stone-600">
                        Attach or change the source this client uses for
                        imports, monitoring, and browser automation.
                    </p>
                </div>

                <div
                    v-if="smartCreditNeedsLogin"
                    class="rounded-[24px] border border-amber-300/80 bg-amber-50/80 px-4 py-4"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-amber-800 uppercase"
                            >
                                SmartCredit login needed
                            </p>
                            <p class="mt-2 text-sm leading-6 text-amber-950">
                                The SmartCredit archive is already attached to
                                this client, but no SmartCredit login has been
                                saved yet. Use the form on the right to save the
                                customer's SmartCredit email or username plus
                                password locally.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-full border border-amber-400 bg-white px-4 py-2 text-xs font-medium tracking-[0.22em] text-amber-900 uppercase transition hover:border-amber-500"
                            @click="startSmartCreditSetup"
                        >
                            Save SmartCredit login
                        </button>
                    </div>
                </div>

                <div v-if="providers.length" class="space-y-3">
                    <div
                        v-for="provider in providers"
                        :key="provider.id"
                        class="rounded-[24px] border border-stone-300/70 bg-stone-50/75 p-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p
                                        class="text-lg font-semibold text-stone-950"
                                    >
                                        {{ provider.provider_label }}
                                    </p>
                                    <span
                                        class="rounded-full border border-stone-300 bg-white px-3 py-1 text-[11px] font-medium tracking-[0.22em] text-stone-600 uppercase"
                                    >
                                        {{
                                            provider.status.replaceAll('_', ' ')
                                        }}
                                    </span>
                                    <span
                                        v-if="provider.has_stored_password"
                                        class="rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-[11px] font-medium tracking-[0.18em] text-emerald-900 uppercase"
                                    >
                                        Password saved
                                    </span>
                                    <span
                                        v-if="
                                            provider.has_stored_security_answer
                                        "
                                        class="rounded-full border border-sky-300 bg-sky-50 px-3 py-1 text-[11px] font-medium tracking-[0.18em] text-sky-900 uppercase"
                                    >
                                        Security answer saved
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-stone-600">
                                    {{
                                        provider.login_email ||
                                        provider.login_username ||
                                        'No provider login saved yet.'
                                    }}
                                </p>
                                <p
                                    v-if="
                                        provider.provider_key ===
                                            'smartcredit' &&
                                        !provider.login_email &&
                                        !provider.login_username &&
                                        !provider.has_stored_password
                                    "
                                    class="mt-2 text-sm text-amber-800"
                                >
                                    SmartCredit import is attached, but the
                                    customer's SmartCredit login has not been
                                    saved yet.
                                </p>
                                <p
                                    v-if="providerNeedsSecurityAnswer(provider)"
                                    class="mt-2 text-sm text-amber-800"
                                >
                                    IdentityIQ needs the saved security answer
                                    before the browser companion can finish
                                    sign-in. We do not need the question text,
                                    just the answer they use.
                                </p>
                                <div
                                    v-if="providerCredentialIssue(provider)"
                                    class="mt-3 rounded-2xl border border-rose-300/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-950"
                                >
                                    <p class="font-semibold">
                                        Credentials need an update
                                    </p>
                                    <p class="mt-1 leading-6">
                                        Saved login stopped working{{
                                            providerCredentialIssue(provider)
                                                ?.invalidatedAt
                                                ? ` on ${formatDate(providerCredentialIssue(provider)?.invalidatedAt ?? '')}`
                                                : ''
                                        }}:
                                        {{
                                            providerCredentialIssue(provider)
                                                ?.reason
                                        }}.
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    v-if="
                                        canRevealProviderCredentials &&
                                        providerHasRevealableCredentials(
                                            provider,
                                        )
                                    "
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full border border-amber-300 bg-amber-50 px-4 py-2 text-xs font-medium tracking-[0.22em] text-amber-900 uppercase transition hover:border-amber-500 disabled:cursor-wait disabled:opacity-60"
                                    :disabled="
                                        revealingProviderId === provider.id
                                    "
                                    @click="revealProviderCredentials(provider)"
                                >
                                    <FontAwesomeIcon
                                        :icon="
                                            storedProviderCredentials(provider)
                                                ? faEyeSlash
                                                : faEye
                                        "
                                        class="text-[0.9rem]"
                                    />
                                    <span>{{
                                        storedProviderCredentials(provider)
                                            ? 'Hide'
                                            : revealingProviderId ===
                                                provider.id
                                              ? 'Opening'
                                              : 'Reveal'
                                    }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full border border-stone-300 bg-white px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-700 uppercase transition hover:border-stone-500"
                                    @click="editProvider(provider)"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full border border-rose-300 bg-rose-50 px-4 py-2 text-xs font-medium tracking-[0.22em] text-rose-700 uppercase transition hover:border-rose-400"
                                    @click="removeProvider(provider)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="storedProviderCredentials(provider)"
                            class="mt-4 rounded-2xl border border-amber-300/70 bg-amber-50/80 px-4 py-3 text-sm text-stone-900"
                        >
                            <div class="grid gap-3 md:grid-cols-2">
                                <div
                                    v-if="
                                        storedProviderCredentials(provider)
                                            ?.login_password
                                    "
                                >
                                    <p
                                        class="text-[11px] font-medium tracking-[0.22em] text-amber-800 uppercase"
                                    >
                                        Password
                                    </p>
                                    <code
                                        class="mt-2 block rounded-xl border border-amber-200 bg-white/90 px-3 py-2 font-mono text-sm break-all text-stone-950"
                                    >
                                        {{
                                            storedProviderCredentials(provider)
                                                ?.login_password
                                        }}
                                    </code>
                                </div>
                                <div
                                    v-if="
                                        storedProviderCredentials(provider)
                                            ?.security_answer
                                    "
                                >
                                    <p
                                        class="text-[11px] font-medium tracking-[0.22em] text-amber-800 uppercase"
                                    >
                                        Security answer
                                    </p>
                                    <code
                                        class="mt-2 block rounded-xl border border-amber-200 bg-white/90 px-3 py-2 font-mono text-sm break-all text-stone-950"
                                    >
                                        {{
                                            storedProviderCredentials(provider)
                                                ?.security_answer
                                        }}
                                    </code>
                                </div>
                            </div>
                            <p
                                v-if="
                                    !storedProviderCredentials(provider)
                                        ?.login_password &&
                                    !storedProviderCredentials(provider)
                                        ?.security_answer
                                "
                                class="text-sm text-amber-900"
                            >
                                This provider has credential flags saved, but no
                                decrypted value came back from the office app.
                            </p>
                        </div>

                        <p
                            v-if="revealProviderErrors[provider.id]"
                            class="mt-3 text-sm text-rose-700"
                        >
                            {{ revealProviderErrors[provider.id] }}
                        </p>

                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <div
                                class="rounded-2xl border border-stone-300/70 bg-white/90 px-4 py-3"
                            >
                                <p
                                    class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                                >
                                    Last import
                                </p>
                                <p class="mt-2 text-sm text-stone-900">
                                    {{
                                        provider.last_imported_at
                                            ? formatDate(
                                                  provider.last_imported_at,
                                              )
                                            : 'No imports recorded yet'
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="
                                    provider.metadata?.office_context
                                        ?.office_brand ||
                                    provider.metadata?.office_context
                                        ?.contact_name ||
                                    provider.metadata?.office_context
                                        ?.contact_email
                                "
                                class="rounded-2xl border border-stone-300/70 bg-white/90 px-4 py-3"
                            >
                                <p
                                    class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                                >
                                    Imported via
                                </p>
                                <p class="mt-2 text-sm text-stone-900">
                                    {{
                                        provider.metadata?.office_context
                                            ?.office_brand ||
                                        provider.metadata?.office_context
                                            ?.office_brand_full
                                    }}
                                </p>
                                <p class="text-sm text-stone-600">
                                    {{
                                        provider.metadata?.office_context
                                            ?.contact_name ||
                                        provider.metadata?.office_context
                                            ?.contact_email
                                    }}
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="displayProviderNotes(provider)"
                            class="mt-4 text-sm leading-6 text-stone-600"
                        >
                            {{ displayProviderNotes(provider) }}
                        </p>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-[24px] border border-dashed border-stone-300 bg-white/70 px-5 py-6 text-sm text-stone-600"
                >
                    No providers are attached to this client yet. Add
                    SmartCredit or the next source platform here before
                    automating imports.
                </div>
            </div>

            <form
                v-if="showProviderEditor"
                class="space-y-4 rounded-[28px] border border-stone-300/70 bg-white/85 p-5"
                @submit.prevent="saveProvider"
            >
                <div class="border-b border-stone-300/70 pb-3">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                            >
                                Provider settings
                            </p>
                            <p
                                v-if="
                                    providerForm.provider_key ===
                                        'smartcredit' ||
                                    providerForm.provider_key === 'credit_karma'
                                "
                                class="mt-2 text-sm text-stone-700"
                            >
                                Put the customer's
                                {{
                                    providerForm.provider_key === 'credit_karma'
                                        ? 'Credit Karma'
                                        : 'SmartCredit'
                                }}
                                login here. Use the login email if they sign in
                                with email, or use the username/member id if
                                that is what the site expects. The password
                                stays local in CreditSoft.
                            </p>
                            <p
                                v-else-if="
                                    providerForm.provider_key === 'identityiq'
                                "
                                class="mt-2 text-sm text-stone-700"
                            >
                                IdentityIQ adds a security-answer step after
                                login. Save the email, password, and the answer
                                the customer uses on that follow-up screen. We
                                do not need the question text or extra personal
                                details just to finish sign-in. The password and
                                answer stay local in CreditSoft.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-full border border-stone-300 bg-white px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-700 uppercase transition hover:border-stone-500"
                            @click="toggleProviderEditor"
                        >
                            Done
                        </button>
                    </div>
                </div>

                <div class="space-y-3">
                    <label
                        class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >Provider</label
                    >
                    <select
                        v-model="providerForm.provider_key"
                        class="h-10 rounded-xl border border-input bg-transparent px-3 text-sm"
                    >
                        <option
                            v-for="provider in providerCatalog"
                            :key="provider.key"
                            :value="provider.key"
                        >
                            {{ provider.label }}
                        </option>
                    </select>
                    <p class="text-sm text-stone-500">
                        {{
                            selectedProviderCatalog?.description ??
                            'Use a custom provider when the source platform is new.'
                        }}
                    </p>
                </div>

                <div
                    v-if="providerForm.provider_key === 'custom'"
                    class="space-y-3"
                >
                    <label
                        class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >Custom label</label
                    >
                    <Input
                        v-model="providerForm.provider_label"
                        placeholder="Custom provider name"
                    />
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-3">
                        <label
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            {{
                                providerForm.provider_key === 'smartcredit'
                                    ? 'SmartCredit email'
                                    : providerForm.provider_key ===
                                        'credit_karma'
                                      ? 'Credit Karma email'
                                      : providerForm.provider_key ===
                                          'identityiq'
                                        ? 'IdentityIQ email'
                                        : 'Login email'
                            }}
                        </label>
                        <Input
                            v-model="providerForm.login_email"
                            type="email"
                            placeholder="client@example.com"
                        />
                    </div>
                    <div class="space-y-3">
                        <label
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            {{
                                providerForm.provider_key === 'smartcredit'
                                    ? 'SmartCredit username or member id'
                                    : providerForm.provider_key ===
                                        'credit_karma'
                                      ? 'Credit Karma username'
                                      : providerForm.provider_key ===
                                          'identityiq'
                                        ? 'IdentityIQ username if different'
                                        : 'Username'
                            }}
                        </label>
                        <Input
                            v-model="providerForm.login_username"
                            :placeholder="
                                providerForm.provider_key === 'identityiq'
                                    ? 'Optional if IdentityIQ uses a separate username'
                                    : 'Optional username or member id'
                            "
                        />
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-3">
                        <label
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            {{
                                providerForm.provider_key === 'smartcredit'
                                    ? 'SmartCredit password'
                                    : providerForm.provider_key ===
                                        'credit_karma'
                                      ? 'Credit Karma password'
                                      : providerForm.provider_key ===
                                          'identityiq'
                                        ? 'IdentityIQ password'
                                        : 'Password'
                            }}
                        </label>
                        <Input
                            v-model="providerForm.login_password"
                            type="password"
                            :placeholder="
                                editingProviderId
                                    ? 'Leave blank to keep saved password'
                                    : providerForm.provider_key === 'identityiq'
                                      ? 'IdentityIQ password'
                                      : 'Optional password'
                            "
                        />
                    </div>
                    <div class="space-y-3">
                        <template
                            v-if="providerForm.provider_key === 'identityiq'"
                        >
                            <label
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                >IdentityIQ security answer only</label
                            >
                            <Input
                                v-model="providerForm.security_answer"
                                type="password"
                                :placeholder="
                                    editingProviderId
                                        ? 'Leave blank to keep saved answer'
                                        : 'Just the answer they use on the site'
                                "
                            />
                        </template>
                        <template v-else>
                            <label
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                >Status</label
                            >
                            <select
                                v-model="providerForm.status"
                                class="h-10 rounded-xl border border-input bg-transparent px-3 text-sm"
                            >
                                <option
                                    v-for="status in providerStatuses"
                                    :key="status.key"
                                    :value="status.key"
                                >
                                    {{ status.label }}
                                </option>
                            </select>
                        </template>
                    </div>
                </div>

                <div
                    v-if="providerForm.provider_key === 'identityiq'"
                    class="space-y-3"
                >
                    <label
                        class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >Status</label
                    >
                    <select
                        v-model="providerForm.status"
                        class="h-10 rounded-xl border border-input bg-transparent px-3 text-sm"
                    >
                        <option
                            v-for="status in providerStatuses"
                            :key="status.key"
                            :value="status.key"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div class="space-y-3">
                    <label
                        class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >Notes</label
                    >
                    <Textarea
                        v-model="providerForm.notes"
                        placeholder="Archive origin, login reminders, MFA notes, or automation handoff details."
                    />
                </div>

                <button
                    type="submit"
                    class="rounded-full bg-stone-950 px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-50 uppercase transition hover:bg-stone-800"
                >
                    {{ editingProviderId ? 'Save provider' : 'Add provider' }}
                </button>
            </form>
        </section>

        <section :id="relationshipSectionId" class="scroll-mt-24 space-y-4">
            <div
                class="rounded-[28px] border border-stone-300/70 bg-white/85 p-5"
            >
                <div class="border-b border-stone-300/70 pb-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Client relationship
                    </p>
                    <p class="text-sm text-stone-600">
                        End the relationship without deleting the dossier. The
                        file, imports, notes, and audit trail stay intact.
                    </p>
                </div>

                <div
                    v-if="relationship.can_end"
                    class="mt-4 grid gap-6 xl:grid-cols-[1.08fr_0.92fr]"
                >
                    <form
                        class="space-y-4"
                        @submit.prevent="endClientRelationship"
                    >
                        <div class="space-y-2">
                            <label
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                >Why is this relationship ending?</label
                            >
                            <select
                                v-model="relationshipForm.ended_reason"
                                class="h-10 w-full rounded-xl border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="" disabled>
                                    Choose only when this client is actually
                                    ending
                                </option>
                                <option
                                    v-for="reason in relationship.reason_options"
                                    :key="reason.key"
                                    :value="reason.key"
                                >
                                    {{ reason.label }}
                                </option>
                            </select>
                            <p class="text-sm text-stone-600">
                                {{
                                    selectedRelationshipReason?.description ??
                                    'This client is still active/intake until you choose a reason and submit this form.'
                                }}
                            </p>
                            <p
                                v-if="relationshipForm.errors.ended_reason"
                                class="text-xs text-rose-700"
                            >
                                {{ relationshipForm.errors.ended_reason }}
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label
                                    class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                    >Ended on</label
                                >
                                <Input
                                    v-model="relationshipForm.ended_at"
                                    type="date"
                                />
                                <p
                                    v-if="relationshipForm.errors.ended_at"
                                    class="text-xs text-rose-700"
                                >
                                    {{ relationshipForm.errors.ended_at }}
                                </p>
                            </div>

                            <div
                                class="rounded-[20px] border border-stone-300/70 bg-stone-50/80 px-4 py-3"
                            >
                                <p
                                    class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                                >
                                    Outcome status
                                </p>
                                <p
                                    class="mt-2 text-lg font-semibold text-stone-950"
                                >
                                    {{ relationshipOutcomeLabel }}
                                </p>
                                <p class="mt-1 text-sm text-stone-600">
                                    {{
                                        relationshipOutcomeLabel ===
                                        'Choose a reason'
                                            ? 'No relationship change has been selected.'
                                            : relationshipOutcomeLabel ===
                                                'Graduated'
                                              ? 'Use this when the client no longer needs help or goals were met.'
                                              : relationshipOutcomeLabel ===
                                                  'Canceled'
                                                ? 'Use this when the client asked to cancel before the work was finished.'
                                                : 'Use this when the office is ending the relationship early.'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                >Internal notes</label
                            >
                            <Textarea
                                v-model="relationshipForm.ended_notes"
                                placeholder="Add payment context, behavior notes, or any support detail the office should retain."
                            />
                            <p
                                v-if="relationshipForm.errors.ended_notes"
                                class="text-xs text-rose-700"
                            >
                                {{ relationshipForm.errors.ended_notes }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            class="rounded-full border border-rose-300 bg-rose-50 px-4 py-2 text-xs font-medium tracking-[0.22em] text-rose-800 uppercase transition hover:border-rose-400"
                            :disabled="
                                relationshipForm.processing ||
                                !relationshipForm.ended_reason
                            "
                        >
                            {{
                                relationshipForm.processing
                                    ? 'Saving…'
                                    : relationshipOutcomeLabel ===
                                        'Choose a reason'
                                      ? 'Choose ending reason'
                                      : relationshipOutcomeLabel === 'Graduated'
                                        ? 'Move to Graduated'
                                        : relationshipOutcomeLabel ===
                                            'Canceled'
                                          ? 'Move to Canceled'
                                          : 'Fire client without deleting'
                            }}
                        </button>
                    </form>

                    <div
                        class="space-y-3 rounded-[24px] border border-stone-300/70 bg-stone-50/80 p-4"
                    >
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            What stays in place
                        </p>
                        <div class="space-y-2 text-sm leading-6 text-stone-600">
                            <p>The dossier stays in CreditSoft.</p>
                            <p>
                                Notes, imported reports, letters, and audit
                                history remain attached.
                            </p>
                            <p>
                                This changes the relationship status instead of
                                deleting evidence.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="relationship.can_delete_lead"
                    class="mt-4 rounded-[24px] border border-rose-300/80 bg-rose-50/75 p-4"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-rose-800 uppercase"
                            >
                                Delete lead
                            </p>
                            <p class="mt-2 text-sm leading-6 text-rose-950">
                                Remove this lead from the intake queue. Use this
                                only for junk/test leads, duplicates, or leads
                                that should not stay in the roster.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-10 shrink-0 items-center rounded-md border border-rose-300 bg-white px-4 text-xs font-medium tracking-[0.18em] text-rose-800 uppercase shadow-sm transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="deleteLeadForm.processing"
                            @click="deleteLead"
                        >
                            {{
                                deleteLeadForm.processing
                                    ? 'Deleting...'
                                    : deleteLeadConfirmationArmed
                                      ? 'Confirm delete lead'
                                      : 'Delete lead'
                            }}
                        </button>
                    </div>
                    <p
                        v-if="deleteLeadForm.errors.client"
                        class="mt-3 text-xs text-rose-800"
                    >
                        {{ deleteLeadForm.errors.client }}
                    </p>
                </div>

                <div
                    v-if="!relationship.can_end"
                    class="mt-4 grid gap-4 xl:grid-cols-[0.9fr_1.1fr]"
                >
                    <div
                        class="rounded-[24px] border border-stone-300/70 bg-stone-50/80 p-4"
                    >
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            Relationship ended
                        </p>
                        <p class="mt-2 text-lg font-semibold text-stone-950">
                            {{ endedRelationshipReasonLabel ?? 'Ended' }}
                        </p>
                        <p class="mt-1 text-sm text-stone-600">
                            {{
                                relationship.ended_at
                                    ? `Recorded on ${formatDate(relationship.ended_at)}.`
                                    : 'The client is no longer active.'
                            }}
                        </p>
                    </div>

                    <div
                        class="rounded-[24px] border border-stone-300/70 bg-white/90 p-4"
                    >
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            Internal notes
                        </p>
                        <p class="mt-2 text-sm leading-6 text-stone-700">
                            {{
                                relationship.ended_notes ||
                                'No extra internal notes were saved for the relationship ending.'
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-8 xl:grid-cols-[0.9fr_1.1fr]">
            <div class="space-y-6">
                <div class="border-b border-stone-300/70 pb-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Case snapshot
                    </p>
                    <p class="text-sm text-stone-600">
                        {{
                            client.goals ||
                            'Add outcome goals and intake notes to frame this case.'
                        }}
                    </p>
                </div>

                <div class="space-y-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Reporting cycles
                    </p>
                    <div class="space-y-2">
                        <div
                            v-for="cycle in cycles"
                            :key="cycle.id"
                            class="flex items-center justify-between rounded-2xl border border-stone-300/70 bg-stone-50/70 px-4 py-3"
                        >
                            <div>
                                <p class="font-medium text-stone-950">
                                    {{ cycle.cycle_label }}
                                </p>
                                <p class="text-sm text-stone-500">
                                    Started {{ formatDate(cycle.started_at) }}
                                </p>
                            </div>
                            <div
                                class="text-right text-xs tracking-[0.22em] text-stone-500 uppercase"
                            >
                                <p>{{ cycle.snapshot_count }} snapshots</p>
                                <p>
                                    {{
                                        cycle.reviewed_at ? 'Reviewed' : 'Open'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Open work
                    </p>
                    <div class="space-y-2">
                        <div
                            v-for="task in client.tasks"
                            :key="task.id"
                            class="rounded-2xl border border-stone-300/70 bg-stone-50/70 px-4 py-3"
                        >
                            <div class="flex items-center justify-between">
                                <p class="font-medium text-stone-900">
                                    {{ task.title }}
                                </p>
                                <p
                                    class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                                >
                                    {{ task.status }}
                                </p>
                            </div>
                            <p class="text-sm text-stone-500">
                                {{
                                    task.due_at
                                        ? formatDate(task.due_at)
                                        : 'No due date'
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-1">
                <form class="space-y-3" @submit.prevent="createCycle">
                    <div class="border-b border-stone-300/70 pb-3">
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            New reporting cycle
                        </p>
                        <p class="text-sm text-stone-600">
                            Open a new monthly or weekly review window.
                        </p>
                    </div>
                    <Input
                        v-model="cycleForm.cycle_label"
                        placeholder="April 2026 review"
                    />
                    <div class="grid gap-3 md:grid-cols-2">
                        <select
                            v-model="cycleForm.source"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="manual">Manual</option>
                            <option value="csv">CSV</option>
                        </select>
                        <Input v-model="cycleForm.started_at" type="date" />
                    </div>
                    <button
                        type="submit"
                        class="rounded-full bg-stone-950 px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-50 uppercase transition hover:bg-stone-800"
                    >
                        Create cycle
                    </button>
                </form>
            </div>
        </section>

        <section
            v-if="!providerPanelRequested"
            class="grid gap-8 xl:grid-cols-[0.9fr_1.1fr]"
        >
            <div v-if="!importPanelRequested" class="space-y-4">
                <div class="border-b border-stone-300/70 pb-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        AI operator deck
                    </p>
                    <p class="text-sm text-stone-600">
                        {{
                            aiProviderCount
                                ? `${aiProviderCount} provider lanes configured for supervised drafting.`
                                : 'No AI provider is configured yet. Connect one before using AI drafting.'
                        }}
                    </p>
                </div>

                <div
                    class="rounded-[24px] border border-stone-300/70 bg-stone-50/70 p-4"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p class="font-medium text-stone-950">
                                {{
                                    browserCaptureTask?.label ??
                                    'Browser intake summarization'
                                }}
                            </p>
                            <p class="text-sm text-stone-600">
                                Use browser captures plus the case record as the
                                evidence layer for AI drafting.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full border border-stone-300 px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-700 uppercase transition hover:border-stone-500"
                            @click="showAiSetup = true"
                        >
                            AI settings
                        </button>
                    </div>
                </div>
            </div>

            <section
                :id="importSectionId"
                v-if="showBrowserCaptureEditor"
                class="scroll-mt-24 space-y-4 rounded-[24px] border border-stone-300/70 bg-stone-50/70 p-4"
            >
                <div
                    class="flex items-center justify-between gap-4 border-b border-stone-300/70 pb-3"
                >
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Import tools
                    </p>
                    <button
                        type="button"
                        class="rounded-full border border-stone-300 px-3 py-1 text-[11px] font-medium tracking-[0.22em] text-stone-600 uppercase transition hover:border-stone-500 hover:text-stone-900"
                        @click="closeBrowserCaptureEditor"
                    >
                        Close
                    </button>
                </div>

                <form
                    class="space-y-3 rounded-[20px] border border-stone-300/70 bg-white/80 p-4"
                    @submit.prevent="importSnapshot"
                >
                    <div class="border-b border-stone-300/70 pb-3">
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            Snapshot import
                        </p>
                        <p class="text-sm text-stone-600">
                            Upload CSV or quick-add one tradeline manually for
                            this cycle.
                        </p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <select
                            v-model="snapshotForm.reporting_cycle_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option
                                v-for="cycle in cycles"
                                :key="cycle.id"
                                :value="cycle.id.toString()"
                            >
                                {{ cycle.cycle_label }}
                            </option>
                        </select>
                        <select
                            v-model="snapshotForm.bureau"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="experian">Experian</option>
                            <option value="transunion">TransUnion</option>
                            <option value="equifax">Equifax</option>
                        </select>
                    </div>
                    <input
                        class="block w-full text-sm text-stone-600"
                        type="file"
                        accept=".csv,text/csv"
                        @change="handleFile"
                    />
                    <div class="grid gap-3 md:grid-cols-2">
                        <Input
                            v-model="snapshotForm.creditor_name"
                            placeholder="Creditor name"
                        />
                        <Input
                            v-model="snapshotForm.account_type"
                            placeholder="Revolving"
                        />
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <Input
                            v-model="snapshotForm.balance"
                            type="number"
                            placeholder="Balance"
                        />
                        <Input
                            v-model="snapshotForm.credit_limit"
                            type="number"
                            placeholder="Credit limit"
                        />
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <Input
                            v-model="snapshotForm.account_status"
                            placeholder="Account status"
                        />
                        <Input
                            v-model="snapshotForm.payment_status"
                            placeholder="Payment status"
                        />
                    </div>
                    <Textarea
                        v-model="snapshotForm.remarks"
                        placeholder="Any bureau-specific remarks or missing information"
                    />
                    <button
                        type="submit"
                        class="rounded-full bg-amber-400 px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-950 uppercase transition hover:bg-amber-300"
                    >
                        Save snapshot
                    </button>
                </form>

                <form class="space-y-3" @submit.prevent="importBrowserCapture">
                    <div class="border-b border-stone-300/70 pb-3">
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            Browser import
                        </p>
                        <p class="text-sm text-stone-600">
                            Archive a Safari file, browser capture, or raw DOM
                            into this cycle.
                        </p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <select
                            v-model="browserCaptureForm.reporting_cycle_id"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option
                                v-for="cycle in cycles"
                                :key="cycle.id"
                                :value="cycle.id.toString()"
                            >
                                {{ cycle.cycle_label }}
                            </option>
                        </select>
                        <Input
                            v-model="browserCaptureForm.browser_name"
                            placeholder="CreditSoft companion, Chrome, Safari"
                        />
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <Input
                            v-model="browserCaptureForm.page_title"
                            placeholder="Page title"
                        />
                        <Input
                            v-model="browserCaptureForm.page_url"
                            placeholder="https://example.com/report"
                        />
                    </div>
                    <Textarea
                        v-model="browserCaptureForm.html"
                        placeholder="Paste DOM HTML here if the companion tool or browser gives you raw page markup."
                    />
                    <input
                        class="block w-full text-sm text-stone-600"
                        type="file"
                        accept=".json,.html,.htm,.txt,.mhtml,.webarchive"
                        @change="handleBrowserCaptureFile"
                    />
                    <button
                        type="submit"
                        class="rounded-full bg-amber-400 px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-950 uppercase transition hover:bg-amber-300"
                    >
                        Store browser capture
                    </button>
                </form>
            </section>
        </section>

        <section
            v-if="!focusPanelMode"
            class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]"
        >
            <div
                class="rounded-[28px] border border-stone-300/70 bg-white/90 p-5 shadow-sm shadow-stone-200/40"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-4 border-b border-stone-300/70 pb-4"
                >
                    <div>
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            Client process checklist
                        </p>
                        <p class="mt-2 max-w-2xl text-sm text-stone-600">
                            Intake, assignment, portal, billing, report import,
                            disputes, and letters in the same order a file
                            should move.
                        </p>
                    </div>
                    <div class="text-left sm:text-right">
                        <p
                            class="text-3xl font-semibold tracking-tight text-stone-950"
                        >
                            {{ completedProcessSteps }}/8
                        </p>
                        <p
                            class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                        >
                            Ready steps
                        </p>
                    </div>
                </div>

                <div class="mt-3 divide-y divide-stone-200/80">
                    <div
                        v-for="item in clientProcessChecklist"
                        :key="item.number"
                        class="grid gap-3 py-3 sm:grid-cols-[2.75rem_1fr_auto] sm:items-center"
                    >
                        <div class="flex items-center gap-3">
                            <p
                                class="w-8 text-lg font-semibold tabular-nums"
                                :class="
                                    item.status === 'complete'
                                        ? 'text-emerald-700'
                                        : item.status === 'ready'
                                          ? 'text-amber-700'
                                          : 'text-stone-400'
                                "
                            >
                                {{ item.number }}
                            </p>
                            <span
                                class="size-2 rounded-full sm:hidden"
                                :class="checklistDotClass(item.status)"
                            />
                        </div>

                        <div>
                            <div
                                class="flex flex-wrap items-center gap-x-3 gap-y-1"
                            >
                                <p class="font-semibold text-stone-950">
                                    {{ item.number }} - {{ item.title }}
                                </p>
                                <span
                                    class="hidden size-2 rounded-full sm:inline-flex"
                                    :class="checklistDotClass(item.status)"
                                />
                                <span
                                    class="text-[10px] font-semibold tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    {{ checklistStatusLabel(item.status) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm leading-6 text-stone-600">
                                {{ item.detail }}
                            </p>
                        </div>

                        <button
                            v-if="item.actionLabel"
                            type="button"
                            class="justify-self-start text-[11px] font-semibold tracking-[0.22em] text-amber-700 uppercase transition hover:text-stone-950 sm:justify-self-end"
                            @click="runClientProcessAction(item.action)"
                        >
                            {{ item.actionLabel }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                class="rounded-[28px] border border-stone-300/70 bg-stone-50/80 p-5 shadow-sm shadow-stone-200/40"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-4 border-b border-stone-300/70 pb-4"
                >
                    <div>
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            Progress & Credit Reports
                        </p>
                        <p class="mt-2 text-sm text-stone-600">
                            Audit, progress, and credit report records for the
                            current work cycle.
                        </p>
                    </div>
                    <p
                        class="text-3xl font-semibold tracking-tight text-stone-950"
                    >
                        {{ progressReportArtifactCount }}
                    </p>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="card in reportCyclePatternCards"
                        :key="card.label"
                        class="min-h-24 rounded-lg border border-stone-300/65 bg-white/65 px-4 py-3"
                    >
                        <p
                            class="text-[10px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                        >
                            {{ card.label }}
                        </p>
                        <p
                            class="mt-3 text-xl font-semibold tracking-tight text-stone-950"
                        >
                            {{ card.value }}
                        </p>
                        <p class="mt-1 line-clamp-2 text-sm text-stone-600">
                            {{ card.hint }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="
                        !documentAccess.can_view_files &&
                        documentAccess.report_count
                    "
                    class="mt-4 rounded-2xl border border-stone-300 bg-white/80 p-4"
                >
                    <p class="font-semibold text-stone-900">
                        {{ documentAccess.report_count }} report file{{
                            documentAccess.report_count === 1 ? '' : 's'
                        }}
                        attached.
                    </p>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Manager access is required to open customer documents.
                        This account can only see that files exist.
                    </p>
                </div>

                <div
                    v-if="
                        progressReportDocuments.length ||
                        progressReportCaptures.length
                    "
                    class="mt-3 divide-y divide-stone-200/80"
                >
                    <button
                        v-for="document in progressReportDocuments"
                        :key="`report-document-${document.id}`"
                        type="button"
                        class="block w-full py-3 text-left transition"
                        :class="
                            document.download_url ? 'hover:text-amber-700' : ''
                        "
                        :disabled="!document.download_url"
                        @click="openDocumentPreview(document)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="truncate font-semibold text-stone-950"
                                >
                                    {{
                                        document.title ||
                                        document.file_name ||
                                        'Report record'
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-xs tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    {{ reportDocumentKind(document) }}
                                    <span v-if="document.reporting_cycle">
                                        · {{ document.reporting_cycle }}</span
                                    >
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p
                                    v-if="formatFileSize(document.file_size)"
                                    class="text-xs font-medium text-stone-500"
                                >
                                    {{ formatFileSize(document.file_size) }}
                                </p>
                                <p
                                    v-else-if="!document.download_url"
                                    class="rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-semibold tracking-[0.16em] text-amber-800 uppercase"
                                >
                                    Missing file
                                </p>
                            </div>
                        </div>
                        <p class="mt-2 truncate text-sm text-stone-600">
                            {{
                                document.file_name ||
                                'Stored inside the report cycle'
                            }}
                            <span v-if="document.uploaded_at">
                                · {{ formatDate(document.uploaded_at) }}</span
                            >
                        </p>
                    </button>
                    <div
                        v-for="capture in progressReportCaptures"
                        :key="`report-capture-${capture.id}`"
                        class="block py-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="truncate font-semibold text-stone-950"
                                >
                                    {{
                                        capture.page_title ||
                                        'Browser report capture'
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-xs tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    {{ captureReportKind(capture) }} · Browser
                                    capture
                                </p>
                            </div>
                            <p
                                class="shrink-0 text-xs font-medium text-stone-500"
                            >
                                {{
                                    formatCaptureTimestamp(capture.imported_at)
                                }}
                            </p>
                        </div>
                        <p class="mt-2 truncate text-sm text-stone-600">
                            {{
                                capture.page_url ||
                                'Stored from companion import'
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="
                        documentAccess.can_view_files ||
                        !documentAccess.report_count
                    "
                    class="mt-4 rounded-2xl border border-dashed border-stone-300 bg-white/70 p-4"
                >
                    <p class="font-semibold text-stone-900">
                        No report-cycle files attached yet.
                    </p>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        The next imported audit, progress file, or credit report
                        will land here.
                    </p>
                </div>
            </div>

            <div
                class="rounded-[28px] border border-stone-300/70 bg-stone-50/80 p-5 shadow-sm shadow-stone-200/40"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-4 border-b border-stone-300/70 pb-4"
                >
                    <div>
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            Client documents
                        </p>
                        <p class="mt-2 text-sm text-stone-600">
                            Latest non-report files attached to this client.
                        </p>
                    </div>
                    <p
                        class="text-3xl font-semibold tracking-tight text-stone-950"
                    >
                        {{ visibleClientFileCount }}
                    </p>
                </div>

                <div
                    v-if="
                        !documentAccess.can_view_files &&
                        documentAccess.client_file_count
                    "
                    class="mt-4 rounded-2xl border border-stone-300 bg-white/80 p-4"
                >
                    <p class="font-semibold text-stone-900">
                        {{ documentAccess.client_file_count }} client file{{
                            documentAccess.client_file_count === 1 ? '' : 's'
                        }}
                        attached.
                    </p>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Manager access is required to open customer documents.
                        This account can only see that files exist.
                    </p>
                </div>

                <div
                    v-if="clientFileDocuments.length"
                    class="mt-3 divide-y divide-stone-200/80"
                >
                    <button
                        v-for="document in clientFileDocuments"
                        :key="document.id"
                        type="button"
                        class="block w-full py-3 text-left transition"
                        :class="
                            document.download_url ? 'hover:text-amber-700' : ''
                        "
                        :disabled="!document.download_url"
                        @click="openDocumentPreview(document)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="truncate font-semibold text-stone-950"
                                >
                                    {{
                                        document.title ||
                                        document.file_name ||
                                        'Client document'
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-xs tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    {{
                                        documentCategoryLabel(document.category)
                                    }}
                                    <span v-if="document.reporting_cycle">
                                        · {{ document.reporting_cycle }}</span
                                    >
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p
                                    v-if="formatFileSize(document.file_size)"
                                    class="text-xs font-medium text-stone-500"
                                >
                                    {{ formatFileSize(document.file_size) }}
                                </p>
                                <p
                                    v-else-if="!document.download_url"
                                    class="rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-semibold tracking-[0.16em] text-amber-800 uppercase"
                                >
                                    Missing file
                                </p>
                            </div>
                        </div>
                        <p class="mt-2 truncate text-sm text-stone-600">
                            {{
                                document.file_name ||
                                'Stored inside the client file'
                            }}
                            <span v-if="document.uploaded_at">
                                · {{ formatDate(document.uploaded_at) }}</span
                            >
                        </p>
                        <p
                            v-if="!document.download_url"
                            class="mt-2 text-xs leading-5 text-amber-800"
                        >
                            CreditSoft has the DisputeFox record, but the file
                            is not attached yet. The local inbox will attach it
                            automatically when the download is available.
                        </p>
                    </button>
                </div>

                <div
                    v-else-if="
                        documentAccess.can_view_files ||
                        !documentAccess.client_file_count
                    "
                    class="mt-4 rounded-2xl border border-dashed border-stone-300 bg-white/70 p-4"
                >
                    <p class="font-semibold text-stone-900">
                        No client-only documents attached yet.
                    </p>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Attach supporting files from the client workflow and
                        they will show here beside the checklist.
                    </p>
                </div>
            </div>
        </section>

        <section v-if="!focusPanelMode" class="grid gap-6 lg:grid-cols-4">
            <div v-if="client.portal_events.length" class="space-y-3">
                <p
                    class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                >
                    Portal signals
                </p>
                <div
                    v-for="event in client.portal_events"
                    :key="event.id"
                    class="rounded-2xl border border-amber-200/80 bg-amber-50/60 px-4 py-3"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p class="min-w-0 font-semibold text-stone-950">
                            {{
                                event.title ||
                                event.tool_key?.replaceAll('_', ' ') ||
                                event.event_type.replaceAll('_', ' ')
                            }}
                        </p>
                        <p
                            v-if="event.score"
                            class="shrink-0 text-sm font-semibold text-stone-900"
                        >
                            {{ event.score }}
                        </p>
                    </div>
                    <p
                        class="mt-1 text-[11px] tracking-[0.2em] text-stone-500 uppercase"
                    >
                        {{ event.event_type.replaceAll('_', ' ') }}
                        <span v-if="event.occurred_at">
                            · {{ formatDate(event.occurred_at) }}</span
                        >
                    </p>
                    <p
                        v-if="event.summary || event.message"
                        class="mt-2 line-clamp-3 text-sm leading-6 text-stone-700"
                    >
                        {{ event.summary || event.message }}
                    </p>
                    <p
                        v-if="event.status"
                        class="mt-2 text-xs font-medium text-stone-600 capitalize"
                    >
                        {{ event.status.replaceAll('_', ' ') }}
                    </p>
                </div>
            </div>

            <div class="space-y-3">
                <p
                    class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                >
                    Recent notes
                </p>
                <div
                    v-for="note in client.notes"
                    :key="note.id"
                    class="rounded-2xl border border-stone-300/70 bg-stone-50/70 px-4 py-3"
                >
                    <p
                        class="text-xs tracking-[0.22em] text-stone-500 uppercase"
                    >
                        {{ note.visibility.replaceAll('_', ' ') }}
                    </p>
                    <p class="mt-2 text-sm text-stone-800">{{ note.note }}</p>
                </div>
            </div>

            <div class="space-y-3">
                <p
                    class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                >
                    Recent violations
                </p>
                <div
                    v-for="violation in client.violations"
                    :key="violation.id"
                    class="rounded-2xl border border-stone-300/70 bg-stone-50/70 px-4 py-3"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-medium text-stone-900">
                            {{ violation.title }}
                        </p>
                        <p
                            class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                        >
                            {{ violation.severity }}
                        </p>
                    </div>
                    <p class="text-sm text-stone-500">{{ violation.status }}</p>
                </div>
            </div>

            <div class="space-y-3">
                <p
                    class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                >
                    SOP runs
                </p>
                <div
                    v-for="run in client.sop_runs"
                    :key="run.id"
                    class="rounded-2xl border border-stone-300/70 bg-stone-50/70 px-4 py-3"
                >
                    <p class="font-medium text-stone-900">
                        {{ run.template?.name ?? 'Checklist' }}
                    </p>
                    <p class="text-sm text-stone-500">{{ run.status }}</p>
                </div>
            </div>
        </section>

        <section v-if="!focusPanelMode" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Recent import records
                    </p>
                    <p class="text-sm text-stone-600">
                        Only the latest copy of each structured import is shown
                        here.
                    </p>
                </div>
                <button
                    v-if="browserCaptureDuplicates.extra_count > 0"
                    type="button"
                    class="rounded-full border border-stone-300 px-4 py-2 text-[11px] font-medium tracking-[0.22em] text-stone-700 uppercase transition hover:border-stone-500 hover:text-stone-900"
                    @click="pruneBrowserCaptureDuplicates"
                >
                    Prune today's duplicates ({{
                        browserCaptureDuplicates.extra_count
                    }})
                </button>
            </div>
            <div
                v-for="capture in recentBrowserCaptures"
                :key="capture.id"
                class="rounded-2xl border border-stone-300/70 bg-stone-50/70 px-4 py-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-medium text-stone-900">
                            {{ capture.page_title || 'Untitled capture' }}
                        </p>
                        <p
                            class="text-xs tracking-[0.22em] text-stone-500 uppercase"
                        >
                            {{ capture.source_type.replaceAll('_', ' ') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <p class="text-sm text-stone-500">
                            {{ formatCaptureTimestamp(capture.imported_at) }}
                        </p>
                        <button
                            type="button"
                            class="inline-flex size-9 items-center justify-center rounded-full text-stone-500 transition hover:text-red-700"
                            title="Delete import"
                            aria-label="Delete import"
                            @click="openDeleteBrowserCaptureDialog(capture)"
                        >
                            <FontAwesomeIcon
                                :icon="faTrashCan"
                                class="text-[1rem]"
                            />
                        </button>
                    </div>
                </div>
                <p class="mt-2 text-sm text-stone-600">
                    {{ capture.page_url }}
                </p>
                <div
                    v-if="resolveProviderCapture(capture)"
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <span
                        class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-[11px] font-medium tracking-[0.18em] text-amber-900 uppercase"
                    >
                        {{ providerBadgeLabel(capture) }}
                    </span>
                    <span
                        v-for="item in capturePreview(capture)"
                        :key="item"
                        class="rounded-full border border-stone-300 bg-white px-3 py-1 text-[11px] font-medium tracking-[0.18em] text-stone-700 uppercase"
                    >
                        {{ item }}
                    </span>
                </div>
                <div
                    v-if="shouldRenderCaptureChart(capture)"
                    class="mt-4 rounded-[24px] border border-stone-300/70 bg-white/90 p-4"
                >
                    <div
                        class="mb-3 flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                            >
                                Imported score history
                            </p>
                            <p class="text-sm text-stone-600">
                                CreditSoft rebuilt this trend from the imported
                                provider snapshot.
                            </p>
                        </div>
                        <p
                            class="text-xs tracking-[0.22em] text-stone-500 uppercase"
                        >
                            {{ captureChart(capture)?.labels.length }} snapshots
                        </p>
                    </div>
                    <MultiLineTrendChart
                        :labels="captureChart(capture)?.labels ?? []"
                        :series="captureChart(capture)?.series ?? []"
                        :height="200"
                    />
                </div>
                <p class="mt-3 text-sm leading-6 text-stone-800">
                    {{ captureSummary(capture) }}
                </p>
                <details
                    v-if="
                        capture.extracted_text &&
                        !hasStructuredProviderCapture(capture)
                    "
                    class="mt-3 rounded-2xl border border-stone-300/70 bg-white/80 px-4 py-3"
                >
                    <summary
                        class="cursor-pointer text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                    >
                        Raw imported page text
                    </summary>
                    <p
                        class="mt-3 text-xs leading-5 break-words whitespace-pre-wrap text-stone-600"
                    >
                        {{ compactText(capture.extracted_text, 1200) }}
                    </p>
                </details>
            </div>
        </section>

        <Dialog
            :open="Boolean(capturePendingDelete)"
            @update:open="
                (open) => {
                    if (!open) closeDeleteBrowserCaptureDialog();
                }
            "
        >
            <DialogContent
                class="max-w-lg rounded-[28px] border-stone-300/80 bg-stone-50 p-6"
                :show-close-button="false"
            >
                <DialogHeader class="space-y-3 text-left">
                    <DialogTitle class="text-xl font-semibold text-stone-950">
                        Delete imported browser record?
                    </DialogTitle>
                    <DialogDescription
                        class="space-y-3 text-sm leading-6 text-stone-600"
                    >
                        <p>
                            Audit trail will show that you deleted this imported
                            record.
                        </p>
                        <p
                            v-if="capturePendingDelete"
                            class="rounded-2xl border border-stone-300/70 bg-white/85 px-4 py-3 text-stone-800"
                        >
                            <span class="block font-medium text-stone-950">{{
                                capturePendingDelete.page_title ||
                                'Untitled capture'
                            }}</span>
                            <span class="block">{{
                                formatCaptureTimestamp(
                                    capturePendingDelete.imported_at,
                                )
                            }}</span>
                        </p>
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="mt-2 gap-2 sm:justify-between">
                    <button
                        type="button"
                        class="rounded-full border border-stone-300 px-4 py-2 text-xs font-medium tracking-[0.22em] text-stone-700 uppercase transition hover:border-stone-500 hover:text-stone-900"
                        @click="closeDeleteBrowserCaptureDialog"
                    >
                        Cancel
                    </button>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        <button
                            v-if="!deleteConfirmationArmed"
                            type="button"
                            class="rounded-full border border-amber-300 bg-amber-50 px-4 py-2 text-xs font-medium tracking-[0.22em] text-amber-900 uppercase transition hover:border-amber-400 hover:bg-amber-100"
                            @click="armDeleteBrowserCapture"
                        >
                            Yes
                        </button>
                        <button
                            v-else
                            type="button"
                            class="rounded-full bg-red-600 px-4 py-2 text-xs font-medium tracking-[0.22em] text-white uppercase transition hover:bg-red-700"
                            @click="deleteBrowserCapture"
                        >
                            Delete
                        </button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ClientDocumentLightbox
            :open="Boolean(activeDocumentPreview)"
            :document="activeDocumentPreview"
            @close="closeDocumentPreview"
        />
    </div>
</template>
