<script setup lang="ts">
import {
    faEllipsis,
    faFire,
    faTrashCan,
    faUserGraduate,
    faXmark,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ClientsWorkspaceNav from '@/components/creditsoft/ClientsWorkspaceNav.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    clientHealthLabel,
    clientHealthRowClass,
    clientHealthScoreLabel,
    clientHealthTextClass,
} from '@/lib/client-health';
import type { ClientHealthSignal } from '@/lib/client-health';
import { formatNumber } from '@/lib/creditsoft';

const props = defineProps<{
    clients: Array<{
        id: number;
        cuid: string;
        display_name: string;
        status: string;
        current_score?: number | null;
        assigned_user?: string | null;
        cycle_count: number;
        latest_cycle?: string | null;
        client_health?: ClientHealthSignal | string | null;
        health_signal?: ClientHealthSignal | string | null;
        source_kind: string;
        billing_signal?: string | ClientHealthSignal | null;
        billing_status?: string | ClientHealthSignal | null;
        document_storage: {
            document_count: number;
            file_count: number;
            metadata_only_count: number;
            file_size_bytes: number;
            file_size_label: string;
            has_files: boolean;
        };
        import_audit: {
            pulse_imported: boolean;
            report_pulled?: boolean | null;
            report_pulled_label: string;
            profile_synced_at?: string | null;
            list_synced_at?: string | null;
            source_lane?: string | null;
            pulse_credentials_saved: boolean;
            provider_ready: boolean;
            needs_provider_credentials: boolean;
            providers: Array<{
                label: string;
                key: string;
                status: string;
                login_saved: boolean;
                login_email?: string | null;
                login_username?: string | null;
                login_identifier?: string | null;
                password_saved: boolean;
                security_answer_saved: boolean;
                ready: boolean;
                last_imported_at?: string | null;
            }>;
        };
    }>;
    importSummary: {
        total: number;
        clients: number;
        pulse_clients: number;
        leads: number;
        listed_leads?: number;
        display_leads?: number;
        terminated: number;
        fired: number;
        canceled: number;
        graduated: number;
        pulse_leads: number;
        pulse_affiliates: number;
        affiliates?: number;
        pulse_imported: number;
        pulse_report_pulled: number;
        provider_accounts: number;
        provider_ready: number;
        needs_provider_credentials: number;
        document_storage: {
            client_count: number;
            file_client_count: number;
            document_count: number;
            file_count: number;
            metadata_only_count: number;
            total_bytes: number;
            total_label: string;
        };
    };
    staff: Array<{
        id: number;
        name: string;
        role_label?: string | null;
        assigned_client_count: number;
    }>;
    assignmentModes: Array<{
        value: string;
        label: string;
        description: string;
    }>;
    crmFields: Array<{
        label: string;
        key: string;
        type: string;
        target: string;
        required: boolean;
    }>;
    affiliates: Array<{
        key: string;
        label: string;
        email: string;
        company: string;
        assigned_to: string;
        matched_user_id?: number | null;
    }>;
    companyProfile: {
        allow_round_robin: boolean;
        assigned_only_default: boolean;
    };
    filters: {
        view:
            | 'clients'
            | 'leads'
            | 'terminated'
            | 'fired'
            | 'canceled'
            | 'graduated'
            | 'all';
        search?: string | null;
        per_page: number;
        sort?: RosterSort | string | null;
        direction?: SortDirection | string | null;
    };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from?: number | null;
        to?: number | null;
        has_more_pages: boolean;
    };
}>();

type ClientRow = (typeof props.clients)[number];
type RosterMode =
    | 'clients'
    | 'leads'
    | 'terminated'
    | 'fired'
    | 'canceled'
    | 'graduated'
    | 'all';
type RosterSort =
    | 'newest'
    | 'person'
    | 'status'
    | 'provider'
    | 'files'
    | 'score'
    | 'owner'
    | 'cycle';
type SortDirection = 'asc' | 'desc';

const coerceRosterMode = (view?: string | null): RosterMode => {
    return view === 'leads' ||
        view === 'terminated' ||
        view === 'fired' ||
        view === 'canceled' ||
        view === 'graduated' ||
        view === 'all'
        ? view
        : 'clients';
};

const coerceRosterSort = (sort?: string | null): RosterSort => {
    return sort === 'person' ||
        sort === 'status' ||
        sort === 'provider' ||
        sort === 'files' ||
        sort === 'score' ||
        sort === 'owner' ||
        sort === 'cycle'
        ? sort
        : 'newest';
};

const coerceSortDirection = (direction?: string | null): SortDirection =>
    direction === 'desc' ? 'desc' : 'asc';

const rosterTabs: Array<{ label: string; value: RosterMode }> = [
    { label: 'Clients', value: 'clients' },
    { label: 'Leads', value: 'leads' },
    { label: 'Terminated', value: 'terminated' },
    { label: 'Fired', value: 'fired' },
    { label: 'Canceled', value: 'canceled' },
    { label: 'Graduated', value: 'graduated' },
    { label: 'All', value: 'all' },
];

const rosterMode = ref<RosterMode>(coerceRosterMode(props.filters.view));
const searchQuery = ref(props.filters.search ?? '');
const perPage = ref(props.filters.per_page);
const sortKey = ref<RosterSort>(coerceRosterSort(props.filters.sort));
const sortDirection = ref<SortDirection>(
    coerceSortDirection(props.filters.direction),
);
const showAdvancedCreate = ref(false);
const addClientDialogOpen = ref(false);
const addClientSaved = ref(false);
const fireDialogOpen = ref(false);
const fireClientTarget = ref<ClientRow | null>(null);
const graduateDialogOpen = ref(false);
const graduateClientTarget = ref<ClientRow | null>(null);
const deleteLeadDialogOpen = ref(false);
const deleteLeadTarget = ref<ClientRow | null>(null);
const selectedClientIds = ref<Set<number>>(new Set());
let syncingFilters = false;
let rosterVisitTimer: ReturnType<typeof window.setTimeout> | null = null;

const defaultTeam = props.staff.map((member) => member.id.toString());
const balancedMode =
    props.assignmentModes.find((mode) => mode.value === 'split_evenly')
        ?.value ?? 'split_evenly';
const singleOwnerMode =
    props.assignmentModes.find((mode) => mode.value === 'single_user')?.value ??
    'single_user';
const defaultAssignmentMode = props.companyProfile.assigned_only_default
    ? singleOwnerMode
    : balancedMode;
const defaultCrmValues = Object.fromEntries(
    props.crmFields.map((field) => [
        field.key,
        field.type === 'checkbox' ? false : '',
    ]),
) as Record<string, string | boolean>;

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    current_score: '',
    status: 'intake',
    assignment_mode: defaultAssignmentMode,
    assigned_to: props.staff[0]?.id?.toString() ?? '',
    assignment_user_ids: defaultTeam,
    affiliate_key: '',
    crm_values: defaultCrmValues,
    goals: '',
});

const fireForm = useForm({
    reason: '',
});
const graduateForm = useForm({
    notes: '',
});

const hasAssignableStaff = computed(() => props.staff.length > 0);
const rosterClients = computed(() => props.clients);
const leadCountLabel = computed(
    () =>
        props.importSummary.display_leads ??
        props.importSummary.pulse_leads ??
        props.importSummary.leads,
);
const selectedAffiliate = computed(
    () =>
        props.affiliates.find(
            (affiliate) => affiliate.key === form.affiliate_key,
        ) ?? null,
);
const visibleAffiliates = computed(() => props.affiliates.slice(0, 4));
const hiddenAffiliateCount = computed(() =>
    Math.max(0, props.affiliates.length - visibleAffiliates.value.length),
);
const affiliatePreviewLabel = computed(() =>
    visibleAffiliates.value.map((affiliate) => affiliate.label).join(' · '),
);
const crmFields = computed(() => props.crmFields);
const documentStorageSummary = computed(
    () => props.importSummary.document_storage,
);

const documentStorageHeadline = computed(() => {
    const storage = documentStorageSummary.value;

    if (!storage.document_count) {
        return 'No dossier files yet';
    }

    return `${storage.total_label} across ${formatNumber(storage.file_client_count)} ${storage.file_client_count === 1 ? 'client with files' : 'clients with files'}`;
});

const documentStorageDetail = computed(() => {
    const storage = documentStorageSummary.value;

    if (!storage.document_count) {
        return 'Companion or staff uploads will appear here.';
    }

    const parts = [
        `${formatNumber(storage.document_count)} document records`,
        `${formatNumber(storage.file_count)} real files`,
    ];

    if (storage.metadata_only_count > 0) {
        parts.push(
            `${formatNumber(storage.metadata_only_count)} metadata-only`,
        );
    }

    return parts.join(' · ');
});

const clientDocumentStorageClass = (client: ClientRow) => {
    if (client.document_storage.has_files) {
        return 'text-emerald-700';
    }

    if (client.document_storage.document_count > 0) {
        return 'text-amber-700';
    }

    return 'text-stone-500';
};

const clientDocumentStorageLabel = (client: ClientRow) => {
    if (client.document_storage.has_files) {
        return client.document_storage.file_size_label;
    }

    if (client.document_storage.document_count > 0) {
        return 'Metadata only';
    }

    return 'No files';
};

const clientDocumentStorageDetail = (client: ClientRow) => {
    const storage = client.document_storage;

    if (!storage.document_count) {
        return '0 docs';
    }

    const parts = [`${formatNumber(storage.document_count)} docs`];

    if (storage.file_count > 0) {
        parts.push(`${formatNumber(storage.file_count)} files`);
    }

    if (storage.metadata_only_count > 0) {
        parts.push(`${formatNumber(storage.metadata_only_count)} metadata`);
    }

    return parts.join(' · ');
};

const toggleAssignmentMember = (memberId: string, checked: boolean) => {
    if (checked) {
        if (!form.assignment_user_ids.includes(memberId)) {
            form.assignment_user_ids = [...form.assignment_user_ids, memberId];
        }

        return;
    }

    form.assignment_user_ids = form.assignment_user_ids.filter(
        (candidate) => candidate !== memberId,
    );
};

const crmFieldOptions = (fieldKey: string) => {
    if (fieldKey === 'monitoring_provider') {
        return [
            'SmartCredit',
            'IdentityIQ',
            'Credit Karma',
            'Experian',
            'MyScoreIQ',
            'Other',
        ];
    }

    if (fieldKey === 'outsourcing_status') {
        return ['No', 'Considering', 'Partial', 'Full'];
    }

    return [];
};

const usesSelectOptions = (field: (typeof props.crmFields)[number]) =>
    (field.type === 'select' || field.type === 'radio') &&
    crmFieldOptions(field.key).length > 0;

const inputTypeForField = (field: (typeof props.crmFields)[number]) => {
    if (field.type === 'email') {
        return 'email';
    }

    if (field.type === 'phone') {
        return 'tel';
    }

    return 'text';
};

const crmStringValue = (fieldKey: string) => {
    const value = form.crm_values[fieldKey];

    return typeof value === 'string' ? value : '';
};

const setCrmStringValue = (fieldKey: string, value: string | number) => {
    form.crm_values[fieldKey] = String(value ?? '');
};

const providerLoginLabel = (
    provider: (typeof props.clients)[number]['import_audit']['providers'][number],
) => {
    const loginParts = [
        provider.login_email,
        provider.login_username,
        provider.login_identifier,
    ].filter(Boolean);

    if (loginParts.length > 0) {
        return loginParts.join(' · ');
    }

    return provider.login_saved ? 'Credentials stored' : 'Not connected';
};

const providerStateClass = (
    provider: (typeof props.clients)[number]['import_audit']['providers'][number],
) => {
    if (
        ['needs_credentials', 'blocked', 'disconnected'].includes(
            provider.status,
        )
    ) {
        return 'text-rose-700';
    }

    if (
        ['needs_client_payment', 'needs_reactivation', 'paused'].includes(
            provider.status,
        )
    ) {
        return 'text-orange-700';
    }

    if (provider.ready) {
        return 'text-emerald-700';
    }

    if (provider.login_saved) {
        return 'text-amber-700';
    }

    return 'text-stone-500';
};

const providerDotClass = (
    provider: (typeof props.clients)[number]['import_audit']['providers'][number],
) => {
    if (
        ['needs_credentials', 'blocked', 'disconnected'].includes(
            provider.status,
        )
    ) {
        return 'bg-rose-500';
    }

    if (
        ['needs_client_payment', 'needs_reactivation', 'paused'].includes(
            provider.status,
        )
    ) {
        return 'bg-orange-500';
    }

    if (provider.ready) {
        return 'bg-emerald-500';
    }

    if (provider.login_saved) {
        return 'bg-amber-500';
    }

    return 'bg-stone-300';
};

const providerStateLabel = (
    provider: (typeof props.clients)[number]['import_audit']['providers'][number],
) => {
    if (provider.status === 'needs_credentials') {
        return 'Needs credentials';
    }

    if (provider.status === 'needs_client_payment') {
        return 'Needs payment';
    }

    if (provider.status === 'needs_reactivation') {
        return 'Needs reactivation';
    }

    if (provider.status === 'blocked') {
        return 'Blocked';
    }

    if (provider.status === 'paused') {
        return 'Paused';
    }

    if (provider.status === 'disconnected') {
        return 'Disconnected';
    }

    if (provider.ready) {
        return 'Ready';
    }

    if (!provider.login_saved) {
        return 'Needs login';
    }

    if (!provider.password_saved) {
        return 'Login only';
    }

    if (provider.key === 'identityiq' && !provider.security_answer_saved) {
        return 'Needs answer';
    }

    return 'Review';
};

const clientHealthSource = (client: (typeof props.clients)[number]) =>
    client.source_kind === 'lead'
        ? null
        : (client.client_health ??
          client.health_signal ??
          client.billing_signal ??
          client.billing_status ??
          null);

const clientPaymentLabel = (client: (typeof props.clients)[number]) => {
    const label = clientHealthLabel(clientHealthSource(client));

    return label && !['No billing record', 'Billing unknown'].includes(label)
        ? label
        : null;
};

const isLeadClient = (client: (typeof props.clients)[number]) =>
    client.source_kind === 'lead';
const isTerminatedClient = (client: (typeof props.clients)[number]) =>
    client.source_kind === 'terminated' ||
    client.status.toLowerCase() === 'terminated';
const isFiredClient = (client: (typeof props.clients)[number]) =>
    client.source_kind === 'fired' || client.status.toLowerCase() === 'fired';
const isCanceledClient = (client: (typeof props.clients)[number]) =>
    client.source_kind === 'canceled' ||
    ['canceled', 'cancelled'].includes(client.status.toLowerCase());
const isGraduatedClient = (client: (typeof props.clients)[number]) =>
    client.source_kind === 'graduated' ||
    ['graduated', 'finished', 'resolved'].includes(client.status.toLowerCase());
const isEndedClient = (client: (typeof props.clients)[number]) =>
    isTerminatedClient(client) ||
    isFiredClient(client) ||
    isCanceledClient(client) ||
    isGraduatedClient(client);
const sourceKindLabel = (client: (typeof props.clients)[number]) =>
    isTerminatedClient(client)
        ? 'Terminated'
        : isFiredClient(client)
          ? 'Fired'
          : isCanceledClient(client)
            ? 'Canceled'
            : isGraduatedClient(client)
              ? 'Graduated'
              : isLeadClient(client)
                ? 'Lead'
                : 'Client';
const sourceKindClass = (client: (typeof props.clients)[number]) => {
    if (isTerminatedClient(client)) {
        return 'text-sky-700';
    }

    if (isFiredClient(client)) {
        return 'text-rose-700';
    }

    if (isCanceledClient(client)) {
        return 'text-orange-700';
    }

    if (isGraduatedClient(client)) {
        return 'text-emerald-700';
    }

    return isLeadClient(client) ? 'text-amber-700' : 'text-blue-700';
};
const clientStatusLabel = (client: (typeof props.clients)[number]) =>
    isTerminatedClient(client)
        ? 'Terminated'
        : isFiredClient(client)
          ? 'Fired'
          : isCanceledClient(client)
            ? 'Canceled'
            : isGraduatedClient(client)
              ? 'Graduated'
              : client.status.replaceAll('_', ' ');

const blockedProviderStatuses = [
    'needs_credentials',
    'blocked',
    'disconnected',
    'needs_client_payment',
    'needs_reactivation',
    'paused',
];

const clientProcessingState = (client: ClientRow) => {
    if (isEndedClient(client)) {
        return 'inactive';
    }

    if (isLeadClient(client)) {
        return 'intake';
    }

    const providers = client.import_audit.providers;
    const hasBlockedProvider = providers.some((provider) =>
        blockedProviderStatuses.includes(provider.status),
    );

    if (
        client.import_audit.needs_provider_credentials ||
        hasBlockedProvider ||
        providers.length === 0 ||
        !client.import_audit.provider_ready
    ) {
        return 'needs_work';
    }

    if (client.import_audit.report_pulled === false) {
        return 'needs_work';
    }

    return 'ready';
};

const clientProcessingDotClass = (client: ClientRow) => {
    const state = clientProcessingState(client);

    if (state === 'ready') {
        return 'bg-emerald-500 ring-2 ring-emerald-100';
    }

    if (state === 'needs_work') {
        return 'bg-rose-500 ring-2 ring-rose-100';
    }

    if (state === 'intake') {
        return 'bg-amber-400 ring-2 ring-amber-100';
    }

    return 'bg-sky-400 ring-2 ring-sky-100';
};

const clientProcessingLabel = (client: ClientRow) => {
    const state = clientProcessingState(client);

    if (state === 'ready') {
        return 'Provider ready and report processing is current.';
    }

    if (state === 'intake') {
        return 'Lead needs intake before report processing.';
    }

    if (state === 'inactive') {
        return `${sourceKindLabel(client)} record is archived outside the active processing queue.`;
    }

    if (client.import_audit.needs_provider_credentials) {
        return 'Needs provider credentials before processing.';
    }

    if (!client.import_audit.providers.length) {
        return 'Needs a monitoring provider before processing.';
    }

    if (!client.import_audit.provider_ready) {
        return 'Saved monitoring access needs staff review.';
    }

    if (client.import_audit.report_pulled === false) {
        return 'Provider is ready; next report still needs processing.';
    }

    return 'Needs staff or companion review before processing.';
};
const paginationLabel = computed(() => {
    if (props.pagination.total === 0) {
        return '0 shown';
    }

    return `${formatNumber(props.pagination.from ?? 1)}-${formatNumber(
        props.pagination.to ?? props.pagination.total,
    )} of ${formatNumber(props.pagination.total)} shown`;
});
const paginationPages = computed(() => {
    const last = Math.max(1, props.pagination.last_page);
    const current = Math.min(Math.max(1, props.pagination.current_page), last);
    let start = Math.max(1, current - 2);
    const end = Math.min(last, start + 4);
    start = Math.max(1, Math.min(start, Math.max(1, end - 4)));

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
});
const addClientReadyToSave = computed(
    () => form.first_name.trim() !== '' && form.last_name.trim() !== '',
);
const addClientReadyToAutoSave = computed(
    () =>
        addClientReadyToSave.value &&
        form.email.trim() !== '' &&
        form.phone.trim() !== '',
);
const fireReasonReady = computed(() => fireForm.reason.trim() !== '');
const visibleClientIds = computed(() =>
    rosterClients.value.map((client) => client.id),
);
const selectedVisibleCount = computed(
    () =>
        visibleClientIds.value.filter((id) => selectedClientIds.value.has(id))
            .length,
);
const allVisibleSelected = computed(
    () =>
        visibleClientIds.value.length > 0 &&
        selectedVisibleCount.value === visibleClientIds.value.length,
);
const selectedCountLabel = computed(() =>
    selectedVisibleCount.value === 1
        ? '1 selected'
        : `${formatNumber(selectedVisibleCount.value)} selected`,
);

const resetAddClientForm = () => {
    form.reset(
        'first_name',
        'last_name',
        'email',
        'phone',
        'current_score',
        'affiliate_key',
        'goals',
    );
    form.status = 'intake';
    form.assignment_mode = defaultAssignmentMode;
    form.assigned_to = props.staff[0]?.id?.toString() ?? '';
    form.assignment_user_ids = [...defaultTeam];
    form.crm_values = { ...defaultCrmValues };
    form.clearErrors();
    addClientSaved.value = false;
    showAdvancedCreate.value = false;
};

const closeSavedAddClientDialog = () => {
    window.setTimeout(() => {
        addClientDialogOpen.value = false;
        resetAddClientForm();
    }, 650);
};

const promoteLead = (client: (typeof props.clients)[number]) => {
    router.post(
        `/clients/${client.id}/promote`,
        {},
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

const openFireDialog = (client: ClientRow) => {
    fireClientTarget.value = client;
    fireForm.reset();
    fireForm.clearErrors();
    fireDialogOpen.value = true;
};

const updateFireDialogOpen = (open: boolean) => {
    fireDialogOpen.value = open;

    if (!open) {
        fireClientTarget.value = null;
        fireForm.reset();
        fireForm.clearErrors();
    }
};

const submitFireClient = () => {
    const client = fireClientTarget.value;

    if (!client) {
        return;
    }

    fireForm.reason = fireForm.reason.trim();
    fireForm.post(`/clients/${client.id}/fire`, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            updateFireDialogOpen(false);
        },
    });
};

const openGraduateDialog = (client: ClientRow) => {
    graduateClientTarget.value = client;
    graduateForm.reset();
    graduateForm.clearErrors();
    graduateDialogOpen.value = true;
};

const updateGraduateDialogOpen = (open: boolean) => {
    graduateDialogOpen.value = open;

    if (!open) {
        graduateClientTarget.value = null;
        graduateForm.reset();
        graduateForm.clearErrors();
    }
};

const submitGraduateClient = () => {
    const client = graduateClientTarget.value;

    if (!client) {
        return;
    }

    graduateForm.notes = graduateForm.notes.trim();
    graduateForm.post(`/clients/${client.id}/graduate`, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            updateGraduateDialogOpen(false);
        },
    });
};

const openDeleteLeadDialog = (client: ClientRow) => {
    deleteLeadTarget.value = client;
    deleteLeadDialogOpen.value = true;
};

const updateDeleteLeadDialogOpen = (open: boolean) => {
    deleteLeadDialogOpen.value = open;

    if (!open) {
        deleteLeadTarget.value = null;
    }
};

const deleteLead = () => {
    const client = deleteLeadTarget.value;

    if (!client) {
        return;
    }

    router.delete(`/clients/${client.id}`, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            updateDeleteLeadDialogOpen(false);
        },
    });
};

const toggleClientSelection = (clientId: number, checked: boolean) => {
    const next = new Set(selectedClientIds.value);

    if (checked) {
        next.add(clientId);
    } else {
        next.delete(clientId);
    }

    selectedClientIds.value = next;
};

const toggleVisibleSelection = (checked: boolean) => {
    const next = new Set(selectedClientIds.value);

    for (const id of visibleClientIds.value) {
        if (checked) {
            next.add(id);
        } else {
            next.delete(id);
        }
    }

    selectedClientIds.value = next;
};

const handleClientSelection = (clientId: number, event: Event) => {
    toggleClientSelection(clientId, (event.target as HTMLInputElement).checked);
};

const handleVisibleSelection = (event: Event) => {
    toggleVisibleSelection((event.target as HTMLInputElement).checked);
};

const clearSelection = () => {
    selectedClientIds.value = new Set();
};

const defaultSortDirection = (sort: RosterSort): SortDirection =>
    ['newest', 'provider', 'files', 'score', 'cycle'].includes(sort)
        ? 'desc'
        : 'asc';

const sortIndicator = (sort: RosterSort) => {
    if (sortKey.value !== sort) {
        return '';
    }

    return sortDirection.value === 'asc' ? '^' : 'v';
};

const sortLabel = (sort: RosterSort, label: string) => {
    if (sortKey.value !== sort) {
        return `Sort by ${label}`;
    }

    return `Sorted by ${label} ${sortDirection.value === 'asc' ? 'ascending' : 'descending'}`;
};

const sortHeaderClass = (sort: RosterSort) =>
    sortKey.value === sort
        ? 'text-stone-950'
        : 'text-stone-500 hover:text-stone-950';

const ariaSort = (sort: RosterSort) => {
    if (sortKey.value !== sort) {
        return 'none';
    }

    return sortDirection.value === 'asc' ? 'ascending' : 'descending';
};

const setRosterSort = (sort: RosterSort) => {
    const nextDirection =
        sortKey.value === sort
            ? sortDirection.value === 'asc'
                ? 'desc'
                : 'asc'
            : defaultSortDirection(sort);

    sortKey.value = sort;
    sortDirection.value = nextDirection;
    visitRoster({ sort, direction: nextDirection, page: 1 });
};

const visitRoster = (
    overrides: Partial<{
        view: RosterMode;
        search: string;
        page: number;
        per_page: number;
        sort: RosterSort;
        direction: SortDirection;
    }> = {},
    debounceMs = 0,
) => {
    if (rosterVisitTimer) {
        window.clearTimeout(rosterVisitTimer);
        rosterVisitTimer = null;
    }

    const payload = {
        view: overrides.view ?? rosterMode.value,
        search: (overrides.search ?? searchQuery.value).trim(),
        page: overrides.page ?? 1,
        per_page: overrides.per_page ?? perPage.value,
        sort: overrides.sort ?? sortKey.value,
        direction: overrides.direction ?? sortDirection.value,
    };

    const run = () => {
        router.get('/clients', payload, {
            only: ['clients', 'filters', 'pagination', 'importSummary'],
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    if (debounceMs > 0) {
        rosterVisitTimer = window.setTimeout(run, debounceMs);

        return;
    }

    run();
};

const setRosterMode = (mode: RosterMode) => {
    rosterMode.value = mode;
};

const goToRosterPage = (page: number) => {
    const target = Math.min(Math.max(1, page), props.pagination.last_page);

    if (target !== props.pagination.current_page) {
        visitRoster({ page: target });
    }
};

const clientProfileHref = (client: ClientRow) =>
    `/clients/${client.id}?view=${encodeURIComponent(rosterMode.value)}`;

watch(
    () => props.filters,
    (filters) => {
        syncingFilters = true;
        rosterMode.value = coerceRosterMode(filters.view);
        searchQuery.value = filters.search ?? '';
        perPage.value = filters.per_page;
        sortKey.value = coerceRosterSort(filters.sort);
        sortDirection.value = coerceSortDirection(filters.direction);
        window.queueMicrotask(() => {
            syncingFilters = false;
        });
    },
    { deep: true },
);

watch(rosterMode, (view) => {
    if (!syncingFilters) {
        visitRoster({ view, page: 1 });
    }
});

watch(searchQuery, (search) => {
    if (!syncingFilters) {
        visitRoster({ search, page: 1 }, 250);
    }
});

watch(perPage, (value) => {
    if (!syncingFilters) {
        visitRoster({ per_page: value, page: 1 });
    }
});

watch(
    () => props.clients,
    (clients) => {
        const visible = new Set(clients.map((client) => client.id));
        const next = new Set(
            [...selectedClientIds.value].filter((id) => visible.has(id)),
        );

        if (next.size !== selectedClientIds.value.size) {
            selectedClientIds.value = next;
        }
    },
);

onBeforeUnmount(() => {
    if (rosterVisitTimer) {
        window.clearTimeout(rosterVisitTimer);
    }
});

watch(selectedAffiliate, (affiliate) => {
    if (
        !affiliate ||
        form.assignment_mode !== 'single_user' ||
        !affiliate.matched_user_id
    ) {
        return;
    }

    form.assigned_to = affiliate.matched_user_id.toString();
});

const submit = () => {
    if (!addClientReadyToSave.value || form.processing) {
        return;
    }

    addClientSaved.value = false;
    form.transform((data) => ({
        ...data,
        return_to_roster: true,
    })).post('/clients', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            addClientSaved.value = true;
            closeSavedAddClientDialog();
        },
    });
};

const maybeAutoSaveClient = () => {
    if (addClientReadyToAutoSave.value && !addClientSaved.value) {
        submit();
    }
};

const updateAddClientDialogOpen = (open: boolean) => {
    addClientDialogOpen.value = open;

    if (!open && !form.processing) {
        resetAddClientForm();
    }
};
</script>

<template>
    <Head title="Clients" />

    <h1 class="sr-only">Clients</h1>

    <div class="space-y-6">
        <section
            class="flex flex-col gap-3 border-b border-stone-300/70 pb-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p class="text-xs font-semibold text-stone-500 uppercase">
                    Client workspace
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <h2
                        class="text-2xl font-semibold tracking-tight text-stone-950"
                    >
                        Clients
                    </h2>
                    <button
                        type="button"
                        class="rounded-md bg-stone-950 px-3.5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-stone-800"
                        @click="addClientDialogOpen = true"
                    >
                        Add client
                    </button>
                </div>
                <p class="mt-1 text-sm text-stone-600">
                    Roster, provider access, and billing health in one working
                    view.
                </p>
            </div>
            <ClientsWorkspaceNav mode="index" />
        </section>

        <Dialog
            :open="addClientDialogOpen"
            @update:open="updateAddClientDialogOpen"
        >
            <DialogContent
                class="max-h-[88svh] overflow-y-auto sm:max-w-2xl"
                :show-close-button="!form.processing"
            >
                <DialogHeader class="text-left">
                    <DialogTitle>Add client</DialogTitle>
                    <DialogDescription>
                        Fast intake for a new client dossier. Use More when
                        routing or CRM fields matter.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-3" @submit.prevent="submit">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <Input
                            v-model="form.first_name"
                            placeholder="First name"
                            @blur="maybeAutoSaveClient"
                        />
                        <Input
                            v-model="form.last_name"
                            placeholder="Last name"
                            @blur="maybeAutoSaveClient"
                        />
                        <Input
                            v-model="form.email"
                            placeholder="Email"
                            @blur="maybeAutoSaveClient"
                        />
                        <Input
                            v-model="form.phone"
                            placeholder="Phone"
                            @blur="maybeAutoSaveClient"
                        />
                    </div>

                    <button
                        type="button"
                        class="text-xs font-medium text-stone-600 underline-offset-4 hover:text-stone-950 hover:underline"
                        @click="showAdvancedCreate = !showAdvancedCreate"
                    >
                        {{
                            showAdvancedCreate ? 'Hide advanced' : 'More fields'
                        }}
                    </button>

                    <div
                        v-if="showAdvancedCreate"
                        class="space-y-3 border-t border-stone-200 pt-3"
                    >
                        <div class="grid gap-2 sm:grid-cols-2">
                            <Input
                                v-model="form.current_score"
                                placeholder="Score"
                                type="number"
                                @blur="maybeAutoSaveClient"
                            />
                            <select
                                v-model="form.status"
                                class="h-9 rounded-md border border-input bg-white px-3 text-sm text-stone-900"
                                @blur="maybeAutoSaveClient"
                            >
                                <option value="intake">Intake</option>
                                <option value="active_review">
                                    Active review
                                </option>
                                <option value="at_risk">At risk</option>
                                <option value="monitoring">Monitoring</option>
                            </select>
                        </div>

                        <select
                            v-if="affiliates.length"
                            v-model="form.affiliate_key"
                            class="h-9 w-full rounded-md border border-input bg-white px-3 text-sm text-stone-900"
                            @blur="maybeAutoSaveClient"
                        >
                            <option value="">Direct office lead</option>
                            <option
                                v-for="affiliate in affiliates"
                                :key="affiliate.key"
                                :value="affiliate.key"
                            >
                                {{ affiliate.label }}
                            </option>
                        </select>
                        <p
                            v-if="selectedAffiliate"
                            class="text-xs leading-5 text-stone-500"
                        >
                            {{
                                selectedAffiliate.email ||
                                selectedAffiliate.company ||
                                selectedAffiliate.label
                            }}
                            <span v-if="selectedAffiliate.assigned_to">
                                · Owner lane {{ selectedAffiliate.assigned_to }}
                            </span>
                        </p>

                        <div class="space-y-2">
                            <label
                                class="text-xs font-semibold text-stone-500 uppercase"
                            >
                                Routing
                            </label>
                            <select
                                v-model="form.assignment_mode"
                                class="h-9 w-full rounded-md border border-input bg-white px-3 text-sm text-stone-900"
                                @blur="maybeAutoSaveClient"
                            >
                                <option
                                    v-for="mode in assignmentModes"
                                    :key="mode.value"
                                    :value="mode.value"
                                >
                                    {{ mode.label }}
                                </option>
                            </select>
                            <p class="text-xs leading-5 text-stone-500">
                                {{
                                    assignmentModes.find(
                                        (mode) =>
                                            mode.value === form.assignment_mode,
                                    )?.description
                                }}
                            </p>
                        </div>

                        <div
                            v-if="form.assignment_mode === 'single_user'"
                            class="space-y-2"
                        >
                            <label
                                class="text-xs font-semibold text-stone-500 uppercase"
                            >
                                Owner
                            </label>
                            <select
                                v-model="form.assigned_to"
                                class="h-9 w-full rounded-md border border-input bg-white px-3 text-sm text-stone-900"
                                :disabled="!hasAssignableStaff"
                                @blur="maybeAutoSaveClient"
                            >
                                <option
                                    v-for="member in staff"
                                    :key="member.id"
                                    :value="member.id.toString()"
                                >
                                    {{ member.name }} ·
                                    {{ member.assigned_client_count }} assigned
                                </option>
                            </select>
                            <p
                                v-if="form.errors.assigned_to"
                                class="text-xs text-rose-700"
                            >
                                {{ form.errors.assigned_to }}
                            </p>
                        </div>

                        <div v-else class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="member in staff"
                                :key="member.id"
                                class="flex items-center gap-2 rounded-md border border-stone-200 bg-white px-3 py-2 text-sm text-stone-700"
                            >
                                <input
                                    :checked="
                                        form.assignment_user_ids.includes(
                                            member.id.toString(),
                                        )
                                    "
                                    class="h-4 w-4 rounded border-stone-400 text-stone-950"
                                    type="checkbox"
                                    @change="
                                        toggleAssignmentMember(
                                            member.id.toString(),
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                    @blur="maybeAutoSaveClient"
                                />
                                <span class="min-w-0">
                                    <span
                                        class="block truncate font-medium text-stone-900"
                                    >
                                        {{ member.name }}
                                    </span>
                                    <span
                                        class="block truncate text-xs text-stone-500"
                                    >
                                        {{ member.role_label ?? 'Staff' }} ·
                                        {{ member.assigned_client_count }}
                                        assigned
                                    </span>
                                </span>
                            </label>
                            <p
                                v-if="form.errors.assignment_user_ids"
                                class="text-xs text-rose-700"
                            >
                                {{ form.errors.assignment_user_ids }}
                            </p>
                        </div>

                        <div
                            v-if="crmFields.length"
                            class="grid gap-2 sm:grid-cols-2"
                        >
                            <template
                                v-for="field in crmFields"
                                :key="field.key"
                            >
                                <label
                                    v-if="field.type === 'checkbox'"
                                    class="flex items-center gap-2 rounded-md border border-stone-200 bg-white px-3 py-2 text-sm text-stone-700"
                                >
                                    <input
                                        v-model="form.crm_values[field.key]"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-stone-400 text-stone-950"
                                        @blur="maybeAutoSaveClient"
                                    />
                                    <span class="truncate">{{
                                        field.label
                                    }}</span>
                                </label>
                                <label v-else class="space-y-1">
                                    <span
                                        class="text-xs font-semibold text-stone-500"
                                    >
                                        {{ field.label }}
                                    </span>
                                    <select
                                        v-if="usesSelectOptions(field)"
                                        v-model="form.crm_values[field.key]"
                                        class="h-9 w-full rounded-md border border-input bg-white px-3 text-sm text-stone-900"
                                        @blur="maybeAutoSaveClient"
                                    >
                                        <option value="">
                                            Select
                                            {{ field.label.toLowerCase() }}
                                        </option>
                                        <option
                                            v-for="option in crmFieldOptions(
                                                field.key,
                                            )"
                                            :key="`${field.key}-${option}`"
                                            :value="option"
                                        >
                                            {{ option }}
                                        </option>
                                    </select>
                                    <Textarea
                                        v-else-if="field.type === 'textarea'"
                                        :model-value="crmStringValue(field.key)"
                                        :placeholder="field.label"
                                        @update:model-value="
                                            (value) =>
                                                setCrmStringValue(
                                                    field.key,
                                                    value,
                                                )
                                        "
                                        @blur="maybeAutoSaveClient"
                                    />
                                    <Input
                                        v-else
                                        :model-value="crmStringValue(field.key)"
                                        :placeholder="field.label"
                                        :type="inputTypeForField(field)"
                                        @update:model-value="
                                            (value) =>
                                                setCrmStringValue(
                                                    field.key,
                                                    value,
                                                )
                                        "
                                        @blur="maybeAutoSaveClient"
                                    />
                                </label>
                            </template>
                        </div>

                        <Textarea
                            v-model="form.goals"
                            placeholder="Goal or intake note"
                            @blur="maybeAutoSaveClient"
                        />
                    </div>

                    <DialogFooter class="gap-2 pt-2 sm:justify-between">
                        <p
                            class="min-h-5 text-xs"
                            :class="
                                addClientSaved
                                    ? 'text-emerald-700'
                                    : 'text-stone-500'
                            "
                        >
                            {{
                                addClientSaved
                                    ? 'Saved. Closing...'
                                    : addClientReadyToAutoSave
                                      ? 'Ready to autosave on field exit.'
                                      : addClientReadyToSave
                                        ? 'Names are ready. Add email and phone to autosave, or save now.'
                                        : 'First and last name are required.'
                            }}
                        </p>
                        <button
                            type="submit"
                            class="rounded-md bg-stone-950 px-4 py-2 text-sm font-medium text-stone-50 transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="
                                !hasAssignableStaff ||
                                !addClientReadyToSave ||
                                form.processing
                            "
                        >
                            {{ form.processing ? 'Saving...' : 'Save now' }}
                        </button>
                        <p
                            v-if="!hasAssignableStaff"
                            class="text-xs text-rose-700"
                        >
                            Add staff first.
                        </p>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="fireDialogOpen" @update:open="updateFireDialogOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader class="text-left">
                    <DialogTitle class="flex items-center gap-2">
                        <span
                            class="inline-flex size-9 items-center justify-center rounded-full bg-amber-100 text-amber-700"
                        >
                            <FontAwesomeIcon :icon="faFire" />
                        </span>
                        Fire/archive client
                    </DialogTitle>
                    <DialogDescription>
                        The dossier stays in records, moves to Fired, and can
                        still be found through search.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitFireClient">
                    <div
                        class="rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm font-medium text-stone-800"
                    >
                        {{
                            fireClientTarget?.display_name ?? 'Selected client'
                        }}
                    </div>
                    <label class="block space-y-1.5">
                        <span class="text-sm font-medium text-stone-800">
                            Why was this client fired?
                        </span>
                        <Textarea
                            v-model="fireForm.reason"
                            class="min-h-28"
                            placeholder="Example: nonpayment, unresponsive, abusive behavior, compliance risk, or client requested cancellation."
                            required
                        />
                    </label>
                    <p
                        v-if="fireForm.errors.reason"
                        class="rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-sm text-rose-700"
                    >
                        {{ fireForm.errors.reason }}
                    </p>
                    <DialogFooter class="gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-100"
                            @click="updateFireDialogOpen(false)"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="fireForm.processing || !fireReasonReady"
                        >
                            {{
                                fireForm.processing
                                    ? 'Archiving...'
                                    : 'Move to Fired'
                            }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="graduateDialogOpen"
            @update:open="updateGraduateDialogOpen"
        >
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader class="text-left">
                    <DialogTitle class="flex items-center gap-2">
                        <span
                            class="inline-flex size-9 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"
                        >
                            <FontAwesomeIcon :icon="faUserGraduate" />
                        </span>
                        Move to Graduated
                    </DialogTitle>
                    <DialogDescription>
                        This closes the active relationship and keeps the full
                        dossier searchable.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitGraduateClient">
                    <div
                        class="rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm font-medium text-stone-800"
                    >
                        {{
                            graduateClientTarget?.display_name ??
                            'Selected client'
                        }}
                    </div>
                    <label class="block space-y-1.5">
                        <span class="text-sm font-medium text-stone-800">
                            Internal note
                        </span>
                        <Textarea
                            v-model="graduateForm.notes"
                            class="min-h-24"
                            placeholder="Optional: goals completed, client finished, or no longer needs active service."
                        />
                    </label>
                    <p
                        v-if="graduateForm.errors.notes"
                        class="rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-sm text-rose-700"
                    >
                        {{ graduateForm.errors.notes }}
                    </p>
                    <DialogFooter class="gap-2">
                        <button
                            type="button"
                            class="rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-100"
                            @click="updateGraduateDialogOpen(false)"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="graduateForm.processing"
                        >
                            {{
                                graduateForm.processing
                                    ? 'Saving...'
                                    : 'Confirm Graduated'
                            }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="deleteLeadDialogOpen"
            @update:open="updateDeleteLeadDialogOpen"
        >
            <DialogContent class="sm:max-w-[460px]">
                <DialogHeader class="text-left">
                    <DialogTitle class="flex items-center gap-2">
                        <span
                            class="inline-flex size-9 items-center justify-center rounded-full bg-rose-100 text-rose-700"
                        >
                            <FontAwesomeIcon :icon="faTrashCan" />
                        </span>
                        Delete lead
                    </DialogTitle>
                    <DialogDescription>
                        This removes the lead from the roster. Client dossiers
                        should be fired or graduated instead.
                    </DialogDescription>
                </DialogHeader>
                <div
                    class="rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm font-medium text-stone-800"
                >
                    {{ deleteLeadTarget?.display_name ?? 'Selected lead' }}
                </div>
                <DialogFooter class="gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-100"
                        @click="updateDeleteLeadDialogOpen(false)"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-rose-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-800"
                        @click="deleteLead"
                    >
                        Confirm Delete
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <div class="space-y-3">
            <section class="space-y-3">
                <div class="flex flex-col gap-3 pb-3">
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="min-w-0 lg:max-w-3xl">
                            <p
                                class="text-xs font-semibold text-stone-500 uppercase"
                            >
                                Clients and leads
                            </p>
                            <p class="mt-1 text-sm text-stone-600">
                                {{ paginationLabel }} ·
                                {{ formatNumber(importSummary.clients) }}
                                clients ·
                                {{ formatNumber(leadCountLabel) }} leads ·
                                {{ formatNumber(importSummary.terminated) }}
                                terminated ·
                                {{ formatNumber(importSummary.fired) }} fired ·
                                {{ formatNumber(importSummary.canceled) }}
                                canceled ·
                                {{ formatNumber(importSummary.graduated) }}
                                graduated ·
                                {{
                                    formatNumber(
                                        importSummary.affiliates ??
                                            importSummary.pulse_affiliates,
                                    )
                                }}
                                affiliates
                            </p>
                        </div>

                        <div class="flex flex-col gap-2 lg:items-end">
                            <div class="max-w-sm text-left lg:text-right">
                                <p
                                    class="text-xs font-semibold text-stone-500 uppercase"
                                >
                                    Dossier files
                                    <span class="text-stone-900">
                                        {{
                                            formatNumber(
                                                documentStorageSummary.file_client_count,
                                            )
                                        }}
                                    </span>
                                </p>
                                <p
                                    class="mt-1 text-xs font-medium text-stone-700"
                                >
                                    {{ documentStorageHeadline }}
                                </p>
                                <p class="mt-0.5 text-[11px] text-stone-500">
                                    {{ documentStorageDetail }}
                                </p>
                            </div>

                            <div
                                v-if="affiliates.length"
                                class="max-w-sm text-left lg:text-right"
                            >
                                <p
                                    class="text-xs font-semibold text-stone-500 uppercase"
                                >
                                    Affiliates
                                    <span class="text-stone-900">
                                        {{
                                            formatNumber(
                                                importSummary.affiliates ??
                                                    importSummary.pulse_affiliates,
                                            )
                                        }}
                                    </span>
                                </p>
                                <p class="mt-1 truncate text-xs text-stone-500">
                                    {{ affiliatePreviewLabel }}
                                    <span
                                        v-if="hiddenAffiliateCount > 0"
                                        class="font-medium text-stone-700"
                                    >
                                        +{{
                                            formatNumber(hiddenAffiliateCount)
                                        }}
                                    </span>
                                </p>
                            </div>
                            <div
                                class="flex flex-wrap items-center gap-1.5 lg:justify-end"
                            >
                                <span
                                    v-if="selectedVisibleCount > 0"
                                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-stone-300 bg-white px-2.5 text-xs font-semibold text-stone-700"
                                >
                                    {{ selectedCountLabel }}
                                    <button
                                        type="button"
                                        class="text-stone-400 transition hover:text-stone-900"
                                        aria-label="Clear selected clients"
                                        @click="clearSelection"
                                    >
                                        <FontAwesomeIcon :icon="faXmark" />
                                    </button>
                                </span>
                                <span
                                    class="px-1 text-xs font-medium text-stone-500"
                                >
                                    {{ paginationLabel }}
                                </span>
                                <select
                                    v-model.number="perPage"
                                    class="h-8 rounded-md border border-input bg-white px-2 text-xs font-medium text-stone-700"
                                    aria-label="Rows per page"
                                >
                                    <option :value="10">10</option>
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                    <option :value="100">100</option>
                                </select>
                                <button
                                    type="button"
                                    class="rounded-md border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:border-stone-500 disabled:cursor-not-allowed disabled:opacity-45"
                                    :disabled="pagination.current_page <= 1"
                                    @click="
                                        goToRosterPage(
                                            pagination.current_page - 1,
                                        )
                                    "
                                >
                                    Previous
                                </button>
                                <button
                                    v-for="page in paginationPages"
                                    :key="`roster-page-top-${page}`"
                                    type="button"
                                    class="min-w-9 rounded-md border px-3 py-1.5 text-xs font-medium transition"
                                    :class="
                                        page === pagination.current_page
                                            ? 'border-stone-950 bg-stone-950 text-white'
                                            : 'border-stone-300 bg-white text-stone-700 hover:border-stone-500'
                                    "
                                    @click="goToRosterPage(page)"
                                >
                                    {{ page }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:border-stone-500 disabled:cursor-not-allowed disabled:opacity-45"
                                    :disabled="
                                        pagination.current_page >=
                                        pagination.last_page
                                    "
                                    @click="
                                        goToRosterPage(
                                            pagination.current_page + 1,
                                        )
                                    "
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex w-full flex-col gap-2 md:flex-row md:items-center"
                    >
                        <div class="w-full md:w-[340px] md:shrink-0">
                            <Input
                                v-model="searchQuery"
                                class="h-9 w-full"
                                placeholder="Search roster"
                                type="search"
                            />
                        </div>

                        <div
                            class="flex min-w-0 flex-1 flex-wrap items-center justify-start gap-x-1.5 gap-y-1 text-[13px] font-semibold md:justify-end"
                            aria-label="Roster view"
                        >
                            <template
                                v-for="(tab, index) in rosterTabs"
                                :key="tab.value"
                            >
                                <button
                                    type="button"
                                    class="border-b-2 px-0 pb-0.5 leading-5 whitespace-nowrap text-stone-500 transition hover:text-stone-950"
                                    :class="
                                        rosterMode === tab.value
                                            ? 'border-amber-500 text-stone-950'
                                            : 'border-transparent hover:border-stone-300'
                                    "
                                    @click="setRosterMode(tab.value)"
                                >
                                    {{ tab.label }}
                                </button>
                                <span
                                    v-if="index < rosterTabs.length - 1"
                                    class="-ml-0.5 text-xs text-stone-300"
                                    aria-hidden="true"
                                >
                                    |
                                </span>
                            </template>
                        </div>
                    </div>
                </div>

                <div
                    class="overflow-x-auto rounded-lg border border-stone-300/80 bg-white shadow-sm shadow-stone-200/40"
                >
                    <table class="w-full min-w-[1136px] table-fixed text-sm">
                        <thead
                            class="border-b border-stone-200 bg-stone-100/70 text-left text-xs font-semibold text-stone-500"
                        >
                            <tr>
                                <th class="w-[4%] px-4 py-2.5">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-stone-300 text-stone-950"
                                        :checked="allVisibleSelected"
                                        :aria-label="`Select all ${rosterClients.length} visible roster records`"
                                        @change="handleVisibleSelection"
                                    />
                                </th>
                                <th
                                    class="w-[23%] px-4 py-2.5"
                                    :aria-sort="ariaSort('person')"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 font-semibold transition"
                                        :class="sortHeaderClass('person')"
                                        :aria-label="sortLabel('person', 'person')"
                                        @click="setRosterSort('person')"
                                    >
                                        <span>Person</span>
                                        <span class="w-2 text-[10px]">{{
                                            sortIndicator('person')
                                        }}</span>
                                    </button>
                                </th>
                                <th
                                    class="w-[9%] px-4 py-2.5"
                                    :aria-sort="ariaSort('status')"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 font-semibold transition"
                                        :class="sortHeaderClass('status')"
                                        :aria-label="sortLabel('status', 'status')"
                                        @click="setRosterSort('status')"
                                    >
                                        <span>Status</span>
                                        <span class="w-2 text-[10px]">{{
                                            sortIndicator('status')
                                        }}</span>
                                    </button>
                                </th>
                                <th
                                    class="w-[22%] px-4 py-2.5"
                                    :aria-sort="ariaSort('provider')"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 font-semibold transition"
                                        :class="sortHeaderClass('provider')"
                                        :aria-label="sortLabel('provider', 'provider count')"
                                        @click="setRosterSort('provider')"
                                    >
                                        <span>Provider</span>
                                        <span class="w-2 text-[10px]">{{
                                            sortIndicator('provider')
                                        }}</span>
                                    </button>
                                </th>
                                <th
                                    class="w-[11%] px-4 py-2.5"
                                    :aria-sort="ariaSort('files')"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 font-semibold transition"
                                        :class="sortHeaderClass('files')"
                                        aria-label="Sort by real stored file size"
                                        title="Sort by real stored file size, then file count"
                                        @click="setRosterSort('files')"
                                    >
                                        <span>Files</span>
                                        <span class="w-2 text-[10px]">{{
                                            sortIndicator('files')
                                        }}</span>
                                    </button>
                                </th>
                                <th
                                    class="w-[7%] px-4 py-2.5"
                                    :aria-sort="ariaSort('score')"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 font-semibold transition"
                                        :class="sortHeaderClass('score')"
                                        :aria-label="sortLabel('score', 'score')"
                                        @click="setRosterSort('score')"
                                    >
                                        <span>Score</span>
                                        <span class="w-2 text-[10px]">{{
                                            sortIndicator('score')
                                        }}</span>
                                    </button>
                                </th>
                                <th
                                    class="w-[10%] px-4 py-2.5"
                                    :aria-sort="ariaSort('owner')"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 font-semibold transition"
                                        :class="sortHeaderClass('owner')"
                                        :aria-label="sortLabel('owner', 'owner')"
                                        @click="setRosterSort('owner')"
                                    >
                                        <span>Owner</span>
                                        <span class="w-2 text-[10px]">{{
                                            sortIndicator('owner')
                                        }}</span>
                                    </button>
                                </th>
                                <th
                                    class="w-[6%] px-4 py-2.5"
                                    :aria-sort="ariaSort('cycle')"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 font-semibold transition"
                                        :class="sortHeaderClass('cycle')"
                                        :aria-label="sortLabel('cycle', 'cycle')"
                                        @click="setRosterSort('cycle')"
                                    >
                                        <span>Cycle</span>
                                        <span class="w-2 text-[10px]">{{
                                            sortIndicator('cycle')
                                        }}</span>
                                    </button>
                                </th>
                                <th class="w-[6%] px-4 py-2.5">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-200">
                            <tr
                                v-for="client in rosterClients"
                                :key="client.id"
                                class="transition"
                                :class="
                                    clientHealthRowClass(
                                        clientHealthSource(client),
                                    )
                                "
                            >
                                <td class="px-4 py-3 align-middle">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-stone-300 text-stone-950"
                                        :checked="
                                            selectedClientIds.has(client.id)
                                        "
                                        :aria-label="`Select ${client.display_name}`"
                                        @change="
                                            handleClientSelection(
                                                client.id,
                                                $event,
                                            )
                                        "
                                    />
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <div
                                        class="flex min-w-0 items-start gap-2.5"
                                    >
                                        <span
                                            class="mt-1.5 size-2.5 shrink-0 rounded-full"
                                            :class="
                                                clientProcessingDotClass(client)
                                            "
                                            :title="
                                                clientProcessingLabel(client)
                                            "
                                            :aria-label="
                                                clientProcessingLabel(client)
                                            "
                                        />
                                        <div class="min-w-0">
                                            <Link
                                                :href="
                                                    clientProfileHref(client)
                                                "
                                                class="block truncate font-semibold text-stone-950 hover:text-blue-700"
                                            >
                                                {{ client.display_name }}
                                            </Link>
                                            <p
                                                class="mt-0.5 truncate text-xs"
                                                :class="sourceKindClass(client)"
                                            >
                                                {{ sourceKindLabel(client) }} ·
                                                {{ client.cuid }}
                                            </p>
                                            <p
                                                v-if="
                                                    clientPaymentLabel(client)
                                                "
                                                class="mt-1 truncate text-xs font-medium"
                                                :class="
                                                    clientHealthTextClass(
                                                        clientHealthSource(
                                                            client,
                                                        ),
                                                    )
                                                "
                                            >
                                                {{ clientPaymentLabel(client) }}
                                                <span
                                                    v-if="
                                                        clientHealthScoreLabel(
                                                            clientHealthSource(
                                                                client,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    ·
                                                    {{
                                                        clientHealthScoreLabel(
                                                            clientHealthSource(
                                                                client,
                                                            ),
                                                        )
                                                    }}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 align-middle text-stone-600"
                                >
                                    {{ clientStatusLabel(client) }}
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <div
                                        v-if="isLeadClient(client)"
                                        class="flex items-center justify-between gap-3 rounded-md border border-amber-200 bg-amber-50/70 px-3 py-2"
                                    >
                                        <span
                                            class="min-w-0 text-xs text-amber-900"
                                        >
                                            Lead intake. Provider rows stay
                                            hidden until this is promoted.
                                        </span>
                                        <button
                                            type="button"
                                            class="shrink-0 rounded-md bg-stone-950 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-stone-800"
                                            @click="promoteLead(client)"
                                        >
                                            Make client
                                        </button>
                                    </div>
                                    <div v-else class="space-y-1.5">
                                        <div
                                            v-for="provider in client
                                                .import_audit.providers"
                                            :key="provider.key"
                                            class="grid grid-cols-[0.5rem_minmax(0,1fr)] gap-2"
                                        >
                                            <span
                                                class="mt-1.5 h-2 w-2 rounded-full"
                                                :class="
                                                    providerDotClass(provider)
                                                "
                                            />
                                            <div class="min-w-0">
                                                <div
                                                    class="flex min-w-0 items-center gap-2"
                                                >
                                                    <span
                                                        class="truncate font-medium text-stone-900"
                                                    >
                                                        {{ provider.label }}
                                                    </span>
                                                    <span
                                                        class="shrink-0 text-xs font-medium"
                                                        :class="
                                                            providerStateClass(
                                                                provider,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            providerStateLabel(
                                                                provider,
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                                <p
                                                    class="truncate text-xs leading-5 text-stone-500"
                                                >
                                                    {{
                                                        providerLoginLabel(
                                                            provider,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                        <p
                                            v-if="
                                                !client.import_audit.providers
                                                    .length
                                            "
                                            class="text-xs text-stone-500"
                                        >
                                            No monitoring login
                                        </p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="space-y-0.5">
                                        <p
                                            class="truncate text-sm font-semibold"
                                            :class="
                                                clientDocumentStorageClass(
                                                    client,
                                                )
                                            "
                                        >
                                            {{
                                                clientDocumentStorageLabel(
                                                    client,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-stone-500"
                                        >
                                            {{
                                                clientDocumentStorageDetail(
                                                    client,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 align-middle text-stone-600"
                                >
                                    {{ client.current_score ?? 'N/A' }}
                                </td>
                                <td
                                    class="px-4 py-3 align-middle text-stone-600"
                                >
                                    {{
                                        client.assigned_user ??
                                        'Needs assignment'
                                    }}
                                </td>
                                <td
                                    class="px-4 py-3 align-middle text-stone-600"
                                >
                                    {{
                                        client.latest_cycle ??
                                        `${client.cycle_count}`
                                    }}
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <button
                                        v-if="isLeadClient(client)"
                                        type="button"
                                        class="inline-flex size-8 items-center justify-center rounded-md text-stone-500 transition hover:bg-rose-50 hover:text-rose-700 focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 focus-visible:outline-none"
                                        :aria-label="`Delete lead ${client.display_name}`"
                                        :title="`Delete lead ${client.display_name}`"
                                        @click="openDeleteLeadDialog(client)"
                                    >
                                        <FontAwesomeIcon :icon="faTrashCan" />
                                    </button>
                                    <DropdownMenu
                                        v-else-if="!isEndedClient(client)"
                                    >
                                        <DropdownMenuTrigger :as-child="true">
                                            <button
                                                type="button"
                                                class="inline-flex size-8 items-center justify-center rounded-md text-stone-500 transition hover:bg-stone-100 hover:text-stone-950 focus-visible:ring-2 focus-visible:ring-stone-500 focus-visible:ring-offset-2 focus-visible:outline-none data-[state=open]:bg-stone-100 data-[state=open]:text-stone-950"
                                                :aria-label="`Actions for ${client.display_name}`"
                                                :title="`Actions for ${client.display_name}`"
                                            >
                                                <FontAwesomeIcon
                                                    :icon="faEllipsis"
                                                />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            class="z-[80] w-56 rounded-xl border border-stone-200 bg-white p-1 text-stone-900 shadow-xl shadow-stone-900/10"
                                        >
                                            <DropdownMenuItem
                                                class="cursor-pointer rounded-lg px-3 py-2.5 text-sm font-medium text-stone-800 focus:bg-stone-100 focus:text-stone-950 data-[highlighted]:bg-stone-100 data-[highlighted]:text-stone-950"
                                                @click="openFireDialog(client)"
                                            >
                                                <FontAwesomeIcon
                                                    :icon="faFire"
                                                    class="text-amber-700"
                                                />
                                                <span>Fire/archive</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="cursor-pointer rounded-lg px-3 py-2.5 text-sm font-medium text-stone-800 focus:bg-stone-100 focus:text-stone-950 data-[highlighted]:bg-stone-100 data-[highlighted]:text-stone-950"
                                                @click="
                                                    openGraduateDialog(client)
                                                "
                                            >
                                                <FontAwesomeIcon
                                                    :icon="faUserGraduate"
                                                    class="text-emerald-700"
                                                />
                                                <span>Graduate</span>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <span
                                        v-else
                                        class="text-xs font-semibold"
                                        :class="sourceKindClass(client)"
                                    >
                                        {{ sourceKindLabel(client) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="rosterClients.length === 0">
                                <td
                                    class="px-4 py-8 text-center text-sm text-stone-500"
                                    colspan="9"
                                >
                                    No records in this view.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="flex flex-col gap-3 border-t border-stone-200 pt-3 sm:flex-row sm:items-center sm:justify-end"
                >
                    <div
                        class="flex flex-wrap items-center gap-1.5 sm:justify-end"
                    >
                        <span
                            v-if="selectedVisibleCount > 0"
                            class="inline-flex h-8 items-center gap-1.5 rounded-md border border-stone-300 bg-white px-2.5 text-xs font-semibold text-stone-700"
                        >
                            {{ selectedCountLabel }}
                            <button
                                type="button"
                                class="text-stone-400 transition hover:text-stone-900"
                                aria-label="Clear selected clients"
                                @click="clearSelection"
                            >
                                <FontAwesomeIcon :icon="faXmark" />
                            </button>
                        </span>
                        <span class="px-1 text-xs font-medium text-stone-500">
                            {{ paginationLabel }}
                        </span>
                        <select
                            v-model.number="perPage"
                            class="h-8 rounded-md border border-input bg-white px-2 text-xs font-medium text-stone-700"
                            aria-label="Rows per page"
                        >
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                        <button
                            type="button"
                            class="rounded-md border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:border-stone-500 disabled:cursor-not-allowed disabled:opacity-45"
                            :disabled="pagination.current_page <= 1"
                            @click="goToRosterPage(pagination.current_page - 1)"
                        >
                            Previous
                        </button>
                        <button
                            v-for="page in paginationPages"
                            :key="`roster-page-${page}`"
                            type="button"
                            class="min-w-9 rounded-md border px-3 py-1.5 text-xs font-medium transition"
                            :class="
                                page === pagination.current_page
                                    ? 'border-stone-950 bg-stone-950 text-white'
                                    : 'border-stone-300 bg-white text-stone-700 hover:border-stone-500'
                            "
                            @click="goToRosterPage(page)"
                        >
                            {{ page }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 transition hover:border-stone-500 disabled:cursor-not-allowed disabled:opacity-45"
                            :disabled="
                                pagination.current_page >= pagination.last_page
                            "
                            @click="goToRosterPage(pagination.current_page + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
