const DEFAULT_SETTINGS = {
    api_base_url: '',
    office_token: '',
    worker_id: '',
    disputefox_username: '',
    disputefox_password: '',
};

const API_OVERVIEW_PATH = '/api/v1';
const API_ENDPOINT_PATH = '/api/v1/browser-companion/intake';
const API_CLIENT_SYNC_PATH = '/api/v1/browser-companion/client-sync';
const API_CLIENT_DOCUMENT_PATH = '/api/v1/browser-companion/client-document';
const API_PROVIDER_STATUS_PATH = '/api/v1/browser-companion/provider-status';
const API_AUTOMATION_DISCOVERY_PATH = '/api/v1/browser-companion/automation-discovery';
const DEFAULT_PROVIDER_KEY = 'smartcredit';
const NEXT_READY_ACCOUNT_PATH = '/api/v1/browser-companion/next-account';
const DISPUTEFOX_LOGIN_URL = 'https://pulse.disputeprocess.com/jsp/client/login.jsp?cdn/';
const PULSE_ADMIN_BASE_URL = 'https://pulse.disputeprocess.com/jsp/admin/';
const PULSE_MIGRATION_LANES = [
    {
        key: 'clients',
        label: 'Clients',
        url: `${PULSE_ADMIN_BASE_URL}customer_and_lead_list.jsp?type=clients`,
    },
    {
        key: 'leads',
        label: 'Leads',
        url: `${PULSE_ADMIN_BASE_URL}customer_and_lead_list.jsp?type=leads`,
    },
    {
        key: 'affiliates',
        label: 'Affiliates',
        url: `${PULSE_ADMIN_BASE_URL}my_affiliate.jsp`,
    },
    {
        key: 'invoices',
        label: 'Invoices',
        url: `${PULSE_ADMIN_BASE_URL}billing_report.jsp`,
    },
    {
        key: 'automation',
        label: 'AutoFox',
        url: `${PULSE_ADMIN_BASE_URL}autofox.jsp`,
    },
];
const PULSE_PROFILE_PROCESS_LANES = PULSE_MIGRATION_LANES.filter((lane) => ['clients', 'leads'].includes(lane.key));
const PULSE_PROFILE_PROCESS_LIMIT = 500;
const CONTEXT_POLL_MS = 1400;
const LOCAL_API_CANDIDATES = [
    'http://127.0.0.1',
    'http://localhost',
    'http://127.0.0.1:8001',
    'http://localhost:8001',
];

const elements = {
    panel: document.getElementById('sidepanelRoot'),
    nextSectionEyebrow: document.getElementById('nextSectionEyebrow'),
    nextSectionTitle: document.getElementById('nextSectionTitle'),
    nextSectionHelp: document.getElementById('nextSectionHelp'),
    nextClientCard: document.getElementById('nextClientCard'),
    nextClientEmpty: document.getElementById('nextClientEmpty'),
    activityList: document.getElementById('activityList'),
    status: document.getElementById('status'),
    tokenHint: document.getElementById('tokenHint'),
    connectionState: document.getElementById('connectionState'),
    pairedDot: document.getElementById('pairedDot'),
    openSettings: document.getElementById('openSettings'),
    goCapture: document.getElementById('goCapture'),
    processingTitle: document.getElementById('processingTitle'),
    processingDetail: document.getElementById('processingDetail'),
    integrationMenuToggle: document.getElementById('integrationMenuToggle'),
    integrationMenu: document.getElementById('integrationMenu'),
    openDisputeFoxImport: document.getElementById('openDisputeFoxImport'),
    credentialToggle: document.getElementById('credentialToggle'),
    credentialPanel: document.getElementById('credentialPanel'),
    credentialFeature: document.getElementById('credentialFeature'),
    disputefoxUsername: document.getElementById('disputefoxUsername'),
    disputefoxPassword: document.getElementById('disputefoxPassword'),
    saveDisputeFoxCredentials: document.getElementById('saveDisputeFoxCredentials'),
    importDisputeFoxAll: document.getElementById('importDisputeFoxAll'),
    importDisputeFoxClients: document.getElementById('importDisputeFoxClients'),
    importDisputeFoxLeads: document.getElementById('importDisputeFoxLeads'),
    importDisputeFoxInvoices: document.getElementById('importDisputeFoxInvoices'),
    importDisputeFoxAffiliates: document.getElementById('importDisputeFoxAffiliates'),
    importDisputeFoxAutomation: document.getElementById('importDisputeFoxAutomation'),
    processPulseProfiles: document.getElementById('processPulseProfiles'),
    closeDisputeFoxImport: document.getElementById('closeDisputeFoxImport'),
    syncDisputeFoxProfile: document.getElementById('syncDisputeFoxProfile'),
};

let settingsCache = { ...DEFAULT_SETTINGS };
let nextReadyAccount = null;
let connectionState = 'empty';
let activityEntries = [];
let activePageContext = {
    url: '',
    title: '',
    provider_key: null,
};
let featureFlags = {
    client_sync: false,
    disputefox_credentials: false,
    create_client_if_missing: false,
};
let integrationMenuOpen = false;
let disputeFoxImportOpen = false;
let lastContextSignature = '';
let contextWatcherId = null;
let syncActiveContextPromise = null;
let runnerState = {
    active: false,
    busy: false,
    account: null,
    completedSteps: [],
    forceUpdate: false,
};

function disputeFoxImportButtons() {
    return [
        elements.importDisputeFoxAll,
        elements.importDisputeFoxClients,
        elements.importDisputeFoxLeads,
        elements.importDisputeFoxInvoices,
        elements.importDisputeFoxAffiliates,
        elements.importDisputeFoxAutomation,
    ].filter(Boolean);
}

function currentCycleLabel() {
    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        year: 'numeric',
    }).format(new Date()) + ' review';
}

function clientNameFromAccount(account) {
    return toText(account?.client?.display_name).trim() || 'the queued client';
}

function accountRunnerKey(account) {
    return [
        toText(account?.client?.cuid),
        toText(account?.provider_account?.provider_key),
    ].join('::');
}

function completedStepKey(account, stepKey) {
    return `${accountRunnerKey(account)}::${stepKey}`;
}

function providerCapturePlan(providerKey) {
    switch (providerKey) {
        case 'smartcredit':
            return ['smartcredit_3b', 'smartcredit_report', 'smartcredit_scores'];
        case 'credit_karma':
            return ['credit_karma_transunion', 'credit_karma_equifax'];
        case 'identityiq':
            return ['identityiq_dashboard', 'identityiq_report', 'identityiq_monitoring', 'identityiq_alerts'];
        default:
            return [];
    }
}

function detectCaptureStep(context) {
    const url = toText(context?.url).toLowerCase();
    const title = toText(context?.title).toLowerCase();
    const providerKey = toText(context?.provider_key);

    if (providerKey === 'smartcredit') {
        if (url.includes('/member/credit-report/smart-3b/') || title.includes('3-bureau credit report')) {
            return 'smartcredit_3b';
        }

        if (url.includes('/member/smart-credit-report.htm') || title.includes('smart credit report')) {
            return 'smartcredit_report';
        }

        if (url.includes('/member/scores/score-tracker.htm') || title.includes('score tracker')) {
            return 'smartcredit_scores';
        }
    }

    if (providerKey === 'credit_karma') {
        if (url.includes('/credit-health/transunion/credit-report') || title.includes('transunion')) {
            return 'credit_karma_transunion';
        }

        if (url.includes('/credit-health/equifax/credit-report') || title.includes('equifax')) {
            return 'credit_karma_equifax';
        }
    }

    if (providerKey === 'identityiq') {
        if (url.includes('/dashboard.aspx') || title.includes('dashboard')) {
            return 'identityiq_dashboard';
        }

        if (url.includes('/creditreport.aspx') || title.includes('credit report')) {
            return 'identityiq_report';
        }

        if (url.includes('/identity-monitoring') || title.includes('monitoring')) {
            return 'identityiq_monitoring';
        }

        if (url.includes('/alerts-credit.aspx') || title.includes('alerts')) {
            return 'identityiq_alerts';
        }
    }

    return null;
}

function isSmartCreditReactivationContext(context) {
    const url = toText(context?.url).trim().toLowerCase();
    const title = toText(context?.title).trim().toLowerCase();
    const providerKey = toText(context?.provider_key);

    if (providerKey !== 'smartcredit' && !url.includes('smartcredit.com')) {
        return false;
    }

    return url.includes('/member/account/reactivation.htm')
        || title.includes('reactivation')
        || title.includes('reactivate smartcredit')
        || title.includes('reactivate account');
}

function providerStepUrl(providerKey, stepKey) {
    switch (stepKey) {
        case 'smartcredit_3b':
            return 'https://www.smartcredit.com/member/credit-report/smart-3b/';
        case 'smartcredit_report':
            return 'https://www.smartcredit.com/member/smart-credit-report.htm';
        case 'smartcredit_scores':
            return 'https://www.smartcredit.com/member/scores/score-tracker.htm';
        case 'credit_karma_transunion':
            return 'https://www.creditkarma.com/credit-health/transunion/credit-report';
        case 'credit_karma_equifax':
            return 'https://www.creditkarma.com/credit-health/equifax/credit-report';
        case 'identityiq_dashboard':
            return 'https://member.identityiq.com/Dashboard.aspx';
        case 'identityiq_report':
            return 'https://member.identityiq.com/CreditReport.aspx';
        case 'identityiq_monitoring':
            return 'https://member.identityiq.com/identity-monitoring';
        case 'identityiq_alerts':
            return 'https://member.identityiq.com/alerts-credit.aspx';
        default:
            return providerKey === 'smartcredit'
                ? 'https://www.smartcredit.com/member/credit-report/smart-3b/'
                : providerKey === 'credit_karma'
                    ? 'https://www.creditkarma.com/credit-health/transunion/credit-report'
                    : providerKey === 'identityiq'
                        ? 'https://member.identityiq.com/Dashboard.aspx'
                    : '';
    }
}

function nextPendingCaptureStep(account) {
    const providerKey = toText(account?.provider_account?.provider_key);
    const plan = providerCapturePlan(providerKey);

    return plan.find((stepKey) => !runnerState.completedSteps.includes(completedStepKey(account, stepKey))) ?? null;
}

function toText(value) {
    return typeof value === 'string' ? value : '';
}

function maskToken(token) {
    if (!token) {
        return 'No API key saved yet';
    }

    if (token.length <= 6) {
        return 'Saved API key on file: ********';
    }

    return `Saved API key on file: ********${token.slice(-4)}`;
}

function normalizeBaseUrl(value) {
    const trimmed = toText(value).trim();

    if (!trimmed) {
        return '';
    }

    return trimmed.replace(/\/+$/, '');
}

function candidateBaseUrls(value) {
    const preferred = normalizeBaseUrl(value);
    const ordered = preferred ? [preferred, ...LOCAL_API_CANDIDATES] : [...LOCAL_API_CANDIDATES];

    return [...new Set(ordered.map((entry) => normalizeBaseUrl(entry)).filter(Boolean))];
}

function activeRunnerAccount() {
    return runnerState.account ?? nextReadyAccount ?? null;
}

function updateProcessingHero() {
    const processing = runnerState.active;
    const account = activeRunnerAccount();
    const clientName = account?.client?.display_name ? toText(account.client.display_name).trim() : '';
    const providerKey = toText(account?.provider_account?.provider_key || activePageContext?.provider_key).trim();
    const providerName = providerKey ? providerLabel(providerKey) : 'provider';
    const statusText = toText(elements.status?.textContent).trim();

    elements.panel?.classList.toggle('is-processing', processing);

    if (elements.processingTitle) {
        elements.processingTitle.textContent = processing
            ? (clientName ? `Updating reports for ${clientName}` : `Updating ${providerName}`)
            : 'Provider report update';
    }

    if (elements.processingDetail) {
        elements.processingDetail.textContent = processing
            ? (statusText || `Working through ${providerName} and preparing the next report page.`)
            : 'CreditSoft is waiting for the next provider report update.';
    }
}

function setStatus(message, tone = 'info') {
    if (!elements.status) {
        return;
    }

    elements.status.textContent = message;
    elements.status.dataset.tone = tone;
    updateProcessingHero();
}

function renderActivity() {
    if (!elements.activityList) {
        return;
    }

    const items = activityEntries.length > 0
        ? activityEntries
        : [{ label: 'Waiting for the next report update.', tone: 'info' }];

    elements.activityList.innerHTML = items.map((item) => `
      <div class="activity-item" data-tone="${escapeHtml(item.tone)}">
        <span class="activity-dot" aria-hidden="true"></span>
        <span>${escapeHtml(item.label)}</span>
      </div>
    `).join('');
}

function resetActivity(label, tone = 'info') {
    activityEntries = [{ label, tone }];
    renderActivity();
}

function contextSignature(context) {
    return [
        toText(context?.url),
        toText(context?.title),
        toText(context?.provider_key),
    ].join('||');
}

function providerLabel(providerKey) {
    return ({
        smartcredit: 'SmartCredit',
        credit_karma: 'Credit Karma',
        identityiq: 'IdentityIQ',
        myscoreiq: 'MyScoreIQ',
        experian: 'Experian',
        equifax: 'Equifax',
        transunion: 'TransUnion',
    })[providerKey] ?? 'provider';
}

function detectProviderFromContext(url = '', title = '') {
    const haystack = `${toText(url).toLowerCase()} ${toText(title).toLowerCase()}`;

    if (haystack.includes('creditkarma.com') || haystack.includes('credit karma')) {
        return 'credit_karma';
    }

    if (haystack.includes('smartcredit.com') || haystack.includes('smartcredit')) {
        return 'smartcredit';
    }

    if (haystack.includes('identityiq.com') || haystack.includes('identityiq')) {
        return 'identityiq';
    }

    if (haystack.includes('myscoreiq.com') || haystack.includes('myscoreiq')) {
        return 'myscoreiq';
    }

    if (haystack.includes('experian.com')) {
        return 'experian';
    }

    if (haystack.includes('equifax.com')) {
        return 'equifax';
    }

    if (haystack.includes('transunion.com')) {
        return 'transunion';
    }

    return null;
}

function isUnsupportedBrowserPage(url = '') {
    return /^(chrome|chrome-extension|devtools|about|edge|brave):\/\//i.test(toText(url).trim());
}

function isDisputeFoxPulseUrl(url = '') {
    const value = toText(url).toLowerCase();

    return value.includes('pulse.disputeprocess.com')
        || value.includes('disputeprocess.com')
        || value.includes('disputefox.com');
}

function isPulseCustomerProfileUrl(url = '') {
    const value = toText(url).toLowerCase();

    return isDisputeFoxPulseUrl(value) && value.includes('customer_dashboard.jsp');
}

function isPulseCustomerListUrl(url = '') {
    const value = toText(url).toLowerCase();

    return isDisputeFoxPulseUrl(value) && value.includes('customer_and_lead_list.jsp');
}

function isPulseNonClientListUrl(url = '') {
    const value = toText(url).toLowerCase();

    return isDisputeFoxPulseUrl(value)
        && (
            value.includes('client_invoices.jsp')
            || value.includes('billing_report.jsp')
            || value.includes('my_affiliate.jsp')
        );
}

function isDisputeFoxImportContext() {
    return disputeFoxImportOpen;
}

function isImportMenuContext() {
    return integrationMenuOpen && !disputeFoxImportOpen;
}

function isPulseLoginUrl(url = '') {
    const value = toText(url).toLowerCase();

    return isDisputeFoxPulseUrl(value)
        && (
            value.includes('/login.jsp')
            || value.includes('/jsp/client/login')
            || value.includes('/jsp/admin/login')
        );
}

function pulseMigrationLaneForUrl(url = '') {
    const value = toText(url).toLowerCase();

    if (!isDisputeFoxPulseUrl(value)) {
        return null;
    }

    if (value.includes('customer_dashboard.jsp')) {
        return { key: 'profile', label: 'Client profile', url: '' };
    }

    if (value.includes('customer_and_lead_list.jsp')) {
        let type = '';

        try {
            type = new URL(url).searchParams.get('type')?.toLowerCase() || '';
        } catch (_) {
            type = value.includes('type=leads') ? 'leads' : 'clients';
        }

        return type.includes('lead')
            ? PULSE_MIGRATION_LANES.find((lane) => lane.key === 'leads')
            : PULSE_MIGRATION_LANES.find((lane) => lane.key === 'clients');
    }

    if (value.includes('client_invoices.jsp') || value.includes('billing_report.jsp') || value.includes('invoice')) {
        return PULSE_MIGRATION_LANES.find((lane) => lane.key === 'invoices');
    }

    if (value.includes('my_affiliate.jsp') || value.includes('affiliate')) {
        return PULSE_MIGRATION_LANES.find((lane) => lane.key === 'affiliates');
    }

    if (value.includes('autofox') || value.includes('workflow_service')) {
        return PULSE_MIGRATION_LANES.find((lane) => lane.key === 'automation');
    }

    return null;
}

function pulseNextMigrationLane(url = '') {
    const current = pulseMigrationLaneForUrl(url);

    if (!current || current.key === 'profile') {
        return PULSE_MIGRATION_LANES[0] ?? null;
    }

    const currentIndex = PULSE_MIGRATION_LANES.findIndex((lane) => lane.key === current.key);

    return currentIndex >= 0
        ? PULSE_MIGRATION_LANES[currentIndex + 1] ?? null
        : PULSE_MIGRATION_LANES[0] ?? null;
}

function pulseCurrentLaneLabel(url = '') {
    const lane = pulseMigrationLaneForUrl(url);

    if (lane?.label) {
        return lane.label;
    }

    return isDisputeFoxPulseUrl(url) ? 'DisputeFox' : 'Not opened yet';
}

function profileHasClientFields(profile) {
    const fields = profile?.fields || {};

    return ['full_name', 'first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'ssn']
        .some((key) => String(fields[key] || '').trim() !== '');
}

function captureLooksLikeClientProfile(capture) {
    const profile = capture?.structured_customer || {};
    const pageKind = toText(profile.page_kind).toLowerCase();
    const url = capture?.url || activePageContext.url || '';

    if (isDisputeFoxPulseUrl(url)) {
        return (pageKind === 'profile' || isPulseCustomerProfileUrl(url)) && profileHasClientFields(profile);
    }

    return profileHasClientFields(profile);
}

function captureLooksLikeAutomationDiscovery(capture) {
    const automation = capture?.structured_automation || {};
    const pageKind = toText(automation.page_kind).toLowerCase();
    const workflows = Array.isArray(automation.workflows) ? automation.workflows : [];

    return pageKind.startsWith('automation-')
        && workflows.some((workflow) => toText(workflow?.name || workflow?.source_identifier).trim() !== '');
}

function isSupportedProviderPageContext(context) {
    const url = toText(context?.url).trim();

    if (!url || isUnsupportedBrowserPage(url)) {
        return false;
    }

    if (!/^https?:\/\//i.test(url)) {
        return false;
    }

    return Boolean(context?.provider_key);
}

function isCaptureReadyPageContext(context) {
    if (!isSupportedProviderPageContext(context)) {
        return false;
    }

    const url = toText(context?.url).trim().toLowerCase();
    const title = toText(context?.title).trim().toLowerCase();

    switch (context?.provider_key) {
        case 'smartcredit':
            return url.includes('/member/')
                && (
                    url.includes('/credit-report/')
                    || url.includes('/smart-credit-report')
                    || url.includes('/score-tracker')
                    || title.includes('3-bureau credit report')
                    || title.includes('smart credit report')
                    || title.includes('score tracker')
                );
        case 'credit_karma':
            return url.includes('/credit-health/')
                && url.includes('/credit-report');
        case 'identityiq':
            return url.includes('/dashboard.aspx')
                || url.includes('/creditreport.aspx')
                || url.includes('/identity-monitoring')
                || url.includes('/alerts-credit.aspx');
        default:
            return true;
    }
}

async function getActiveTabContext() {
    const [tab] = await chrome.tabs.query({
        active: true,
        currentWindow: true,
    });

    const url = toText(tab?.url);
    const title = toText(tab?.title);

    return {
        url,
        title,
        provider_key: detectProviderFromContext(url, title),
    };
}

function providerStartUrlFromAccount(account) {
    return toText(account?.provider_account?.companion?.start_url).trim();
}

function providerLogoutUrlFromAccount(account) {
    return toText(account?.provider_account?.companion?.logout_url).trim();
}

async function navigateActiveTab(url) {
    const targetUrl = toText(url).trim();

    if (!targetUrl) {
        throw new Error('No provider page is configured for this account yet.');
    }

    const [tab] = await chrome.tabs.query({
        active: true,
        currentWindow: true,
    });

    if (!tab?.id) {
        throw new Error('No active tab found to navigate.');
    }

    await chrome.tabs.update(tab.id, { url: targetUrl });
}

function setRunnerAccount(account) {
    runnerState.account = account ?? null;
    nextReadyAccount = account ?? null;
    syncForm();
}

function startRunner(account = null, options = {}) {
    runnerState.active = true;
    runnerState.account = account ?? null;
    runnerState.completedSteps = [];
    runnerState.forceUpdate = Boolean(options.forceUpdate);
    updateProcessingHero();
}

function stopRunner() {
    runnerState.active = false;
    runnerState.busy = false;
    runnerState.account = null;
    runnerState.completedSteps = [];
    runnerState.forceUpdate = false;
    updateProcessingHero();
}

function delay(ms) {
    return new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });
}

async function executeOnActiveTab(func, args = []) {
    const [tab] = await chrome.tabs.query({
        active: true,
        currentWindow: true,
    });

    if (!tab?.id) {
        throw new Error('No active tab found.');
    }

    const [result] = await chrome.scripting.executeScript({
        target: { tabId: tab.id },
        func,
        args,
    });

    return result?.result ?? null;
}

async function inspectPageAutomationState() {
    if (!isSupportedProviderPageContext(activePageContext)) {
        return {
            hasPasswordField: false,
            hasLoginField: false,
            hasLoginTrigger: false,
            hasSecurityAnswerField: false,
            isSecurityQuestionPage: false,
        };
    }

    return await executeOnActiveTab(() => {
        const isVisible = (element) => {
            if (!(element instanceof HTMLElement)) {
                return false;
            }

            const style = window.getComputedStyle(element);

            return style.display !== 'none'
                && style.visibility !== 'hidden'
                && style.opacity !== '0'
                && element.offsetParent !== null;
        };

        const passwordField = Array.from(document.querySelectorAll('input[type="password"]'))
            .find(isVisible);
        const fieldRoot = passwordField?.closest('form') ?? document;
        const loginField = Array.from(fieldRoot.querySelectorAll('input'))
            .find((element) => {
                if (element === passwordField || !isVisible(element)) {
                    return false;
                }

                const type = (element.getAttribute('type') || 'text').toLowerCase();
                const name = `${element.getAttribute('name') || ''} ${element.id || ''} ${element.getAttribute('autocomplete') || ''}`.toLowerCase();

                return ['email', 'text', 'search'].includes(type)
                    || name.includes('email')
                    || name.includes('user')
                    || name.includes('login')
                    || name.includes('member');
            });

        const loginTrigger = Array.from(document.querySelectorAll('a, button'))
            .find((element) => {
                if (!isVisible(element)) {
                    return false;
                }

                const label = `${element.textContent || ''} ${element.getAttribute('aria-label') || ''}`.toLowerCase();

                return /log ?in|sign ?in/.test(label);
            });

        const url = window.location.href.toLowerCase();
        const title = document.title.toLowerCase();
        const securityQuestionPage = url.includes('security-question') || title.includes('security question');
        const securityAnswerField = Array.from(document.querySelectorAll('input, textarea'))
            .find((element) => {
                if (!isVisible(element)) {
                    return false;
                }

                const type = (element.getAttribute('type') || 'text').toLowerCase();

                if (['hidden', 'password', 'email', 'submit', 'button', 'checkbox', 'radio'].includes(type)) {
                    return false;
                }

                const name = [
                    element.getAttribute('name') || '',
                    element.id || '',
                    element.getAttribute('placeholder') || '',
                    element.getAttribute('aria-label') || '',
                ].join(' ').toLowerCase();

                if (name.includes('answer') || name.includes('security')) {
                    return true;
                }

                return securityQuestionPage && ['text', 'search'].includes(type);
            });

        return {
            hasPasswordField: Boolean(passwordField),
            hasLoginField: Boolean(loginField),
            hasLoginTrigger: Boolean(loginTrigger),
            hasSecurityAnswerField: Boolean(securityAnswerField),
            isSecurityQuestionPage: securityQuestionPage,
        };
    });
}

async function openProviderLoginTrigger() {
    return await executeOnActiveTab(() => {
        const isVisible = (element) => {
            if (!(element instanceof HTMLElement)) {
                return false;
            }

            const style = window.getComputedStyle(element);

            return style.display !== 'none'
                && style.visibility !== 'hidden'
                && style.opacity !== '0'
                && element.offsetParent !== null;
        };

        const loginTrigger = Array.from(document.querySelectorAll('a, button'))
            .find((element) => {
                if (!isVisible(element)) {
                    return false;
                }

                const label = `${element.textContent || ''} ${element.getAttribute('aria-label') || ''}`.toLowerCase();

                return /log ?in|sign ?in/.test(label);
            });

        if (!loginTrigger) {
            return { clicked: false };
        }

        loginTrigger.click();

        return { clicked: true };
    });
}

async function submitProviderLogin(account) {
    const providerKey = toText(account?.provider_account?.provider_key).trim();
    const loginValue = toText(account?.provider_account?.preferred_login?.value).trim();
    const passwordValue = toText(account?.provider_account?.login_password).trim();
    const securityAnswerValue = toText(account?.provider_account?.security_answer).trim();

    if (!loginValue || !passwordValue) {
        return { submitted: false };
    }

    return await executeOnActiveTab((credentials) => {
        const isVisible = (element) => {
            if (!(element instanceof HTMLElement)) {
                return false;
            }

            const style = window.getComputedStyle(element);

            return style.display !== 'none'
                && style.visibility !== 'hidden'
                && style.opacity !== '0'
                && element.offsetParent !== null;
        };

        const setInputValue = (element, value) => {
            const prototype = Object.getPrototypeOf(element);
            const descriptor = Object.getOwnPropertyDescriptor(prototype, 'value');

            if (descriptor?.set) {
                descriptor.set.call(element, value);
            } else {
                element.value = value;
            }

            element.dispatchEvent(new Event('input', { bubbles: true }));
            element.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const currentUrl = window.location.href.toLowerCase();
        const currentTitle = document.title.toLowerCase();
        const securityQuestionPage = credentials.providerKey === 'identityiq'
            && (currentUrl.includes('security-question') || currentTitle.includes('security question'));

        if (securityQuestionPage) {
            const answerField = Array.from(document.querySelectorAll('input, textarea'))
                .find((element) => {
                    if (!isVisible(element)) {
                        return false;
                    }

                    const type = (element.getAttribute('type') || 'text').toLowerCase();

                    if (['hidden', 'password', 'email', 'submit', 'button', 'checkbox', 'radio'].includes(type)) {
                        return false;
                    }

                    const name = [
                        element.getAttribute('name') || '',
                        element.id || '',
                        element.getAttribute('placeholder') || '',
                        element.getAttribute('aria-label') || '',
                    ].join(' ').toLowerCase();

                    return name.includes('answer')
                        || name.includes('security')
                        || ['text', 'search'].includes(type);
                });

            if (!answerField || !credentials.securityAnswer) {
                return { submitted: false, reason: 'missing-security-answer' };
            }

            setInputValue(answerField, credentials.securityAnswer);

            const fieldRoot = answerField.closest('form') ?? document;
            const submitButton = Array.from(fieldRoot.querySelectorAll('button, input[type="submit"]'))
                .find((element) => {
                    if (!isVisible(element)) {
                        return false;
                    }

                    const label = `${element.textContent || ''} ${element.getAttribute('value') || ''} ${element.getAttribute('aria-label') || ''}`.toLowerCase();

                    return /continue|submit|verify|next|answer/.test(label) || element.getAttribute('type') === 'submit';
                });

            if (submitButton instanceof HTMLElement) {
                submitButton.click();
                return { submitted: true, method: 'security-question-button' };
            }

            if (fieldRoot instanceof HTMLFormElement) {
                fieldRoot.requestSubmit?.();

                if (!fieldRoot.requestSubmit) {
                    fieldRoot.submit();
                }

                return { submitted: true, method: 'security-question-form' };
            }

            return { submitted: false, reason: 'missing-security-submit' };
        }

        const passwordField = Array.from(document.querySelectorAll('input[type="password"]'))
            .find(isVisible);

        if (!passwordField) {
            return { submitted: false, reason: 'missing-password-field' };
        }

        const fieldRoot = passwordField.closest('form') ?? document;
        const loginField = Array.from(fieldRoot.querySelectorAll('input'))
            .find((element) => {
                if (element === passwordField || !isVisible(element)) {
                    return false;
                }

                const type = (element.getAttribute('type') || 'text').toLowerCase();
                const name = `${element.getAttribute('name') || ''} ${element.id || ''} ${element.getAttribute('autocomplete') || ''}`.toLowerCase();

                return ['email', 'text', 'search'].includes(type)
                    || name.includes('email')
                    || name.includes('user')
                    || name.includes('login')
                    || name.includes('member');
            });

        if (loginField) {
            setInputValue(loginField, credentials.login);
        }

        setInputValue(passwordField, credentials.password);

        const submitButton = Array.from(fieldRoot.querySelectorAll('button, input[type="submit"]'))
            .find((element) => {
                if (!isVisible(element)) {
                    return false;
                }

                const label = `${element.textContent || ''} ${element.getAttribute('value') || ''} ${element.getAttribute('aria-label') || ''}`.toLowerCase();

                return /log ?in|sign ?in|continue|submit/.test(label) || element.getAttribute('type') === 'submit';
            });

        if (submitButton instanceof HTMLElement) {
            submitButton.click();
            return { submitted: true, method: 'button' };
        }

        if (fieldRoot instanceof HTMLFormElement) {
            fieldRoot.requestSubmit?.();

            if (!fieldRoot.requestSubmit) {
                fieldRoot.submit();
            }

            return { submitted: true, method: 'form' };
        }

        return { submitted: false, reason: 'missing-submit' };
    }, [{
        providerKey,
        login: loginValue,
        password: passwordValue,
        securityAnswer: securityAnswerValue,
    }]);
}

async function advanceToCaptureReady(account) {
    const providerKey = toText(account?.provider_account?.provider_key).trim();
    const providerName = providerLabel(providerKey);
    const clientName = toText(account?.client?.display_name).trim() || 'the queued client';

    for (let step = 0; step < 5; step += 1) {
        await syncActiveContext({ loadQueue: false, quiet: true });

        if (isCaptureReadyPageContext(activePageContext)) {
            const currentStep = detectCaptureStep(activePageContext);

            if (currentStep && !runnerState.completedSteps.includes(completedStepKey(account, currentStep))) {
                return true;
            }

            const pendingStep = nextPendingCaptureStep(account);

            if (pendingStep) {
                const pendingUrl = providerStepUrl(providerKey, pendingStep);

                if (pendingUrl && toText(activePageContext.url).trim() !== pendingUrl) {
                    pushActivity(`Opening the next ${providerName} page for ${clientName}.`, 'info');
                    setStatus(`Opening the next ${providerName} page for ${clientName}...`, 'info');
                    await navigateActiveTab(pendingUrl);
                    await delay(1800);
                    continue;
                }
            }

            return true;
        }

        const onProviderPage = isSupportedProviderPageContext(activePageContext)
            && activePageContext.provider_key === providerKey;

        if (!onProviderPage) {
            const pendingStep = nextPendingCaptureStep(account);
            const targetUrl = providerStepUrl(providerKey, pendingStep) || providerStartUrlFromAccount(account);

            if (!targetUrl) {
                throw new Error(`No start page is configured for ${providerName} yet.`);
            }

            pushActivity(`Opening ${providerName} for ${clientName}.`, 'success');
            setStatus(`Opening ${providerName} for ${clientName}.`, 'success');
            await navigateActiveTab(targetUrl);
            await delay(1800);
            continue;
        }

        const pageState = await inspectPageAutomationState();

        if (pageState?.hasSecurityAnswerField && account?.provider_account?.security_answer) {
            const submitted = await submitProviderLogin(account);

            if (submitted?.submitted) {
                pushActivity(`Answering the ${providerName} security question for ${clientName}.`, 'success');
                setStatus(`Answering the ${providerName} security question for ${clientName}...`, 'success');
                await delay(2200);
                continue;
            }
        }

        if (pageState?.hasPasswordField && account?.provider_account?.login_password) {
            const submitted = await submitProviderLogin(account);

            if (submitted?.submitted) {
                pushActivity(`Signing into ${providerName} for ${clientName}.`, 'success');
                setStatus(`Signing into ${providerName} for ${clientName}.`, 'success');
                await delay(2200);
                continue;
            }
        }

        if (pageState?.hasLoginTrigger) {
            const clicked = await openProviderLoginTrigger();

            if (clicked?.clicked) {
                pushActivity(`Opening the ${providerName} sign-in step.`, 'info');
                setStatus(`Opening the ${providerName} sign-in step...`, 'info');
                await delay(1200);
                continue;
            }
        }

        const pendingStep = nextPendingCaptureStep(account);
        const targetUrl = providerStepUrl(providerKey, pendingStep) || providerStartUrlFromAccount(account);

        if (targetUrl && toText(activePageContext.url).trim() !== targetUrl) {
            pushActivity(`Redirecting to the ${providerName} report page.`, 'info');
            setStatus(`Redirecting to the ${providerName} report page...`, 'info');
            await navigateActiveTab(targetUrl);
            await delay(1800);
            continue;
        }

        break;
    }

    await syncActiveContext({ loadQueue: false, quiet: true });

    return isCaptureReadyPageContext(activePageContext);
}

function pushActivity(label, tone = 'info') {
    activityEntries = [
        {
            label,
            tone,
        },
        ...activityEntries,
    ].slice(0, 4);

    renderActivity();
}

function syncPairingState() {
    const paired = Boolean(settingsCache.office_token);

    if (elements.tokenHint) {
        elements.tokenHint.textContent = maskToken(settingsCache.office_token);
    }

    if (elements.connectionState) {
        elements.connectionState.textContent = paired
            ? connectionLabel()
            : 'Open extension settings to save a CreditSoft API key';
    }

    if (elements.pairedDot) {
        elements.pairedDot.classList.remove('ready', 'saved', 'error');
        elements.pairedDot.title = paired ? connectionLabel() : 'No CreditSoft API key saved yet';
        elements.pairedDot.setAttribute('aria-label', elements.pairedDot.title);

        if (!paired) {
            return;
        }

        if (connectionState === 'ready') {
            elements.pairedDot.classList.add('ready');
            return;
        }

        if (connectionState === 'error') {
            elements.pairedDot.classList.add('error');
            return;
        }

        elements.pairedDot.classList.add('saved');
    }
}

function connectionLabel() {
    if (connectionState === 'ready') {
        return 'CreditSoft API key verified';
    }

    if (connectionState === 'error') {
        return 'Saved API key was rejected';
    }

    return 'CreditSoft API key saved locally';
}

function renderNextClient() {
    if (!elements.nextClientCard || !elements.nextClientEmpty || !elements.goCapture) {
        return;
    }

    const onProviderPage = isSupportedProviderPageContext(activePageContext);
    const captureReady = isCaptureReadyPageContext(activePageContext);
    const pairedAndReady = Boolean(settingsCache.office_token) && connectionState === 'ready';

    if (isImportMenuContext()) {
        nextReadyAccount = null;

        if (elements.nextSectionEyebrow) {
            elements.nextSectionEyebrow.textContent = 'Import';
        }

        if (elements.nextSectionTitle) {
            elements.nextSectionTitle.textContent = 'Choose a source';
        }

        if (elements.nextSectionHelp) {
            elements.nextSectionHelp.textContent = '';
            elements.nextSectionHelp.hidden = true;
        }

        elements.nextClientCard.hidden = true;
        elements.nextClientCard.innerHTML = '';
        elements.nextClientEmpty.hidden = false;
        elements.nextClientEmpty.textContent = 'Choose DisputeFox migration to import legacy CRM data.';
        elements.goCapture.textContent = 'Import';
        elements.goCapture.disabled = true;
        return;
    }

    if (isDisputeFoxImportContext()) {
        nextReadyAccount = null;
        const laneLabel = pulseCurrentLaneLabel(activePageContext.url);

        if (elements.nextSectionEyebrow) {
            elements.nextSectionEyebrow.textContent = 'Import';
        }

        if (elements.nextSectionTitle) {
            elements.nextSectionTitle.textContent = 'DisputeFox import';
        }

        if (elements.nextSectionHelp) {
            elements.nextSectionHelp.hidden = false;
            elements.nextSectionHelp.textContent = 'List imports bring over legacy CRM rows only. Profile details can bring over client fields and saved files when DisputeFox exposes them.';
        }

        elements.nextClientCard.hidden = true;
        elements.nextClientCard.innerHTML = '';
        elements.nextClientEmpty.hidden = false;
        elements.nextClientEmpty.textContent = isDisputeFoxPulseUrl(activePageContext.url)
            ? `Current source: ${laneLabel}. Choose a list import, profile details, or current page.`
            : 'Open DisputeFox, log in, then choose an import action.';
        elements.goCapture.textContent = isDisputeFoxPulseUrl(activePageContext.url) ? 'Import' : 'Open DisputeFox';
        elements.goCapture.disabled = false;
        return;
    }

    if (elements.nextSectionEyebrow) {
        elements.nextSectionEyebrow.textContent = 'Update';
    }

    if (elements.nextSectionTitle) {
        elements.nextSectionTitle.textContent = 'Provider reports';
    }

    if (elements.nextSectionHelp) {
        elements.nextSectionHelp.textContent = '';
        elements.nextSectionHelp.hidden = true;
    }

    elements.goCapture.textContent = 'Update';

    if (!onProviderPage) {
        nextReadyAccount = null;
        elements.nextClientCard.hidden = true;
        elements.nextClientCard.innerHTML = '';
        elements.nextClientEmpty.hidden = false;
        elements.nextClientEmpty.textContent = isUnsupportedBrowserPage(activePageContext.url)
            ? 'Update opens the next provider report page. Browser internal pages cannot be captured.'
            : 'Update opens the next provider report page.';
        elements.goCapture.disabled = !pairedAndReady;
        return;
    }

    const client = nextReadyAccount?.client ?? null;
    const provider = nextReadyAccount?.provider_account ?? null;

    if (!client || !provider) {
        elements.nextClientCard.hidden = true;
        elements.nextClientCard.innerHTML = '';
        elements.nextClientEmpty.hidden = false;
        elements.nextClientEmpty.textContent = settingsCache.office_token
            ? `No ${providerLabel(activePageContext.provider_key)} report update is queued.`
            : 'Save a CreditSoft API key, then the next report update will appear here.';
        elements.goCapture.disabled = true;
        return;
    }

    const latestCycle = client.latest_reporting_cycle?.cycle_label
        ? `<span class="tiny-pill">${escapeHtml(client.latest_reporting_cycle.cycle_label)}</span>`
        : '';
    const email = client.email ? escapeHtml(client.email) : 'Client email not saved yet';
    const loginState = provider.has_stored_password
        ? `${provider.provider_label || 'Provider'} login saved`
        : 'Provider login needed';
    const providerPill = provider.provider_label
        ? `<span class="tiny-pill">${escapeHtml(provider.provider_label)}</span>`
        : '';

    elements.nextClientCard.hidden = false;
    elements.nextClientEmpty.hidden = true;
    elements.nextClientCard.innerHTML = `
        <div class="selected-card">
        <div class="selected-top">
          <div class="name">${escapeHtml(client.display_name)}</div>
          ${providerPill || '<span class="tiny-pill">Ready now</span>'}
        </div>
        <div class="meta">${email}</div>
        <div class="selected-actions">
          <div class="meta">${latestCycle || 'Loaded from the report update queue.'}</div>
          <div class="meta">${escapeHtml(loginState)}</div>
        </div>
        </div>
    `;
    elements.goCapture.disabled = !pairedAndReady;

    if (!captureReady && elements.nextClientEmpty) {
        elements.nextClientEmpty.hidden = false;
        elements.nextClientEmpty.textContent = `Update will open ${providerLabel(provider.provider_key)} for ${client.display_name}.`;
    } else if (elements.nextClientEmpty) {
        elements.nextClientEmpty.hidden = true;
        elements.nextClientEmpty.textContent = '';
    }
}

function syncForm() {
    syncPairingState();
    renderNextClient();
    syncIntegrationMenu();
    syncCredentialForm();
    syncFeatureVisibility();
    updateProcessingHero();
}

function syncIntegrationMenu() {
    if (elements.integrationMenu) {
        elements.integrationMenu.hidden = !integrationMenuOpen;
    }

    if (elements.integrationMenuToggle) {
        elements.integrationMenuToggle.setAttribute('aria-expanded', integrationMenuOpen ? 'true' : 'false');
    }
}

function syncCredentialForm() {
    if (elements.disputefoxUsername) {
        elements.disputefoxUsername.value = settingsCache.disputefox_username || '';
    }

    if (elements.disputefoxPassword) {
        elements.disputefoxPassword.value = settingsCache.disputefox_password || '';
    }
}

function syncFeatureVisibility() {
    const enabled = Boolean(featureFlags.client_sync && featureFlags.disputefox_credentials);
    const ready = enabled && connectionState === 'ready';

    if (elements.credentialFeature) {
        elements.credentialFeature.hidden = !disputeFoxImportOpen;
    }

    if (elements.syncDisputeFoxProfile) {
        elements.syncDisputeFoxProfile.disabled = !ready;
    }

    disputeFoxImportButtons().forEach((button) => {
        button.disabled = !ready;
    });

    if (elements.processPulseProfiles) {
        elements.processPulseProfiles.disabled = !ready;
    }

    if (elements.closeDisputeFoxImport) {
        elements.closeDisputeFoxImport.disabled = !disputeFoxImportOpen;
    }
}

function applyFeatureFlags(payload) {
    const features = payload?.data?.features || payload?.meta?.features || {};

    if (!features || typeof features !== 'object') {
        return;
    }

    featureFlags = {
        ...featureFlags,
        ...features,
    };
    syncFeatureVisibility();
}

function readFormSettings() {
    return {
        api_base_url: normalizeBaseUrl(settingsCache.api_base_url),
        office_token: toText(settingsCache.office_token).trim(),
        worker_id: settingsCache.worker_id,
        disputefox_username: settingsCache.disputefox_username,
        disputefox_password: settingsCache.disputefox_password,
    };
}

function readCredentialSettings() {
    return {
        disputefox_username: toText(elements.disputefoxUsername?.value).trim(),
        disputefox_password: toText(elements.disputefoxPassword?.value),
    };
}

async function loadSettings() {
    const stored = await chrome.storage.local.get(DEFAULT_SETTINGS);
    settingsCache = {
        ...DEFAULT_SETTINGS,
        ...stored,
    };
    settingsCache.worker_id = await ensureWorkerId(settingsCache.worker_id);
    activePageContext = await getActiveTabContext();
    lastContextSignature = contextSignature(activePageContext);

    syncForm();
    startContextWatcher();

    if (settingsCache.office_token) {
        await verifyConnection({ quiet: true });

        if (connectionState === 'ready') {
            await loadNextClient({ quiet: true });
        }
    }
}

async function saveSettingsFromForm() {
    const next = readFormSettings();

    await chrome.storage.local.set(next);
    settingsCache = {
        ...settingsCache,
        ...next,
    };

    syncForm();

    return settingsCache;
}

async function syncActiveContext({ loadQueue = false, quiet = true } = {}) {
    if (syncActiveContextPromise) {
        return syncActiveContextPromise;
    }

    syncActiveContextPromise = (async () => {
        const nextContext = await getActiveTabContext();
        const nextSignature = contextSignature(nextContext);
        const changed = nextSignature !== lastContextSignature;

        activePageContext = nextContext;
        lastContextSignature = nextSignature;

        if (!changed && !loadQueue) {
            return;
        }

        if (loadQueue && settingsCache.office_token && connectionState === 'ready') {
            await loadNextClient({ quiet });
            return;
        }

        syncForm();
    })();

    try {
        await syncActiveContextPromise;
    } finally {
        syncActiveContextPromise = null;
    }
}

function startContextWatcher() {
    if (contextWatcherId !== null) {
        clearInterval(contextWatcherId);
    }

    contextWatcherId = window.setInterval(() => {
        void (async () => {
            const shouldLoadProviderQueue = !runnerState.active && !integrationMenuOpen && !disputeFoxImportOpen;

            await syncActiveContext({ loadQueue: shouldLoadProviderQueue, quiet: true });

            if (runnerState.active) {
                await continueRunner({ source: 'watcher' });
            }
        })();
    }, CONTEXT_POLL_MS);
}

async function captureActiveTab() {
    const [tab] = await chrome.tabs.query({
        active: true,
        currentWindow: true,
    });

    if (!tab?.id) {
        throw new Error('No active tab found. Open the page you want to capture.');
    }

    const results = await chrome.scripting.executeScript({
        target: { tabId: tab.id, allFrames: true },
        func: async () => {
            const short = (value, limit = 500) => {
                const text = String(value ?? '').replace(/\s+/g, ' ').trim();
                return text.length > limit ? `${text.slice(0, limit)}…` : text;
            };
            const normalizeKey = (value) => String(value ?? '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
            const visibleEnough = (element) => {
                if (!(element instanceof HTMLElement)) {
                    return false;
                }

                const style = window.getComputedStyle(element);
                const rect = element.getBoundingClientRect();

                return style.display !== 'none'
                    && style.visibility !== 'hidden'
                    && style.opacity !== '0'
                    && rect.width > 0
                    && rect.height > 0;
            };
            const currentUrl = window.location.href;
            const isPulsePage = /pulse\.disputeprocess\.com$/i.test(window.location.hostname);
            const hasProfileMarker = /customer_dashboard\.jsp/i.test(currentUrl)
                || Boolean(document.querySelector('title[customer_name], #client_card_info_name, .client_card_info_name'));
            const ignoredPulseTable = (table) => /^(DFMessage|DFAllMessage|DFPortalMessage|DFLeadChat|DFClientsDocuments)/i.test(table.id || '');
            const hasUsableRecordRows = (table) => {
                if (!(table instanceof HTMLTableElement) || ignoredPulseTable(table)) {
                    return false;
                }

                return table.querySelectorAll('thead th').length >= 2
                    && table.querySelectorAll('tbody tr').length > 0;
            };
            const hasRecordTable = !hasProfileMarker
                && Array.from(document.querySelectorAll('#clientsORleadsDatatable, #affiliate_table, #client_invoice_table, table.dataTable, table.datatable, table'))
                    .some(hasUsableRecordRows);
            const pageKind = hasProfileMarker ? 'profile' : (hasRecordTable ? 'record-list' : 'page');
            const absoluteUrl = (value) => {
                const text = String(value ?? '').trim();

                if (!text || text === '#') {
                    return '';
                }

                try {
                    return new URL(text, currentUrl).href;
                } catch (_) {
                    return text;
                }
            };
            const mimeFromFilename = (value) => {
                const name = String(value ?? '').toLowerCase();

                if (name.endsWith('.pdf')) return 'application/pdf';
                if (name.endsWith('.png')) return 'image/png';
                if (name.endsWith('.jpg') || name.endsWith('.jpeg')) return 'image/jpeg';
                if (name.endsWith('.gif')) return 'image/gif';
                if (name.endsWith('.webp')) return 'image/webp';
                if (name.endsWith('.heic') || name.endsWith('.heif')) return 'image/heif';

                return '';
            };
            const filenameFromPath = (value) => {
                const text = String(value ?? '').trim();

                if (!text) {
                    return '';
                }

                try {
                    const url = new URL(text, currentUrl);
                    const pathname = decodeURIComponent(url.pathname || '');
                    return pathname.split('/').filter(Boolean).pop() || '';
                } catch (_) {
                    return text.split(/[/?#]/)[0].split('/').filter(Boolean).pop() || '';
                }
            };
            const clientUidFromPage = () => {
                try {
                    const id = new URL(currentUrl).searchParams.get('id');

                    if (id) return id;
                } catch (_) {
                    // Keep extraction resilient on malformed third-party URLs.
                }

                const selectors = [
                    '#client_document_ref_client_u_id',
                    '#idForUpdateClient',
                    'input[name="client_u_id"]',
                    'input[name="clientId_for_updation"]',
                    'input[name="dispute_ref_client_u_id"]',
                ];

                for (const selector of selectors) {
                    const value = document.querySelector(selector)?.getAttribute('value') || document.querySelector(selector)?.value;

                    if (value) return String(value).trim();
                }

                const match = document.documentElement.outerHTML.match(/\bvar\s+clientId\s*=\s*['"]([^'"]+)['"]/i);

                return match?.[1] || '';
            };
            const normalizePulseDocument = (doc, source = 'pulse-api') => {
                const sourcePath = String(doc?.client_document_url || doc?.source_path || '').trim();
                const downloadUrl = absoluteUrl(doc?.client_document_full_url || doc?.download_url || '');
                const previewUrl = absoluteUrl(doc?.preview_url || (sourcePath ? `/static-resources/client_documents/${sourcePath}` : ''));
                const fileName = String(doc?.file_name || filenameFromPath(sourcePath || downloadUrl) || '').trim();
                const title = short(doc?.client_document_name_text || doc?.title || fileName || 'DisputeFox document', 255);

                if (!title && !downloadUrl && !sourcePath) {
                    return null;
                }

                return {
                    source_system: 'disputefox',
                    source,
                    source_document_uid: short(doc?.client_document_u_id || doc?.source_document_uid || '', 255),
                    source_client_uid: short(doc?.client_uid || doc?.source_client_uid || clientUidFromPage(), 255),
                    title,
                    category: doc?.is_creditReport === '1' || doc?.is_credit_report === true ? 'credit_report' : 'pulse_client_document',
                    file_name: short(fileName || title, 255),
                    mime_type: short(doc?.mime_type || mimeFromFilename(fileName || sourcePath), 255),
                    file_size: Number(doc?.file_size || 0) || 0,
                    uploaded_at_label: short(doc?.client_document_date || doc?.uploaded_at_label || '', 120),
                    download_url: downloadUrl,
                    preview_url: previewUrl,
                    source_path: short(sourcePath, 2048),
                    is_credit_report: doc?.is_creditReport === '1' || doc?.is_credit_report === true,
                };
            };
            const detectPulseDocuments = async () => {
                const documents = [];
                const seen = new Set();
                const addDocument = (documentRecord) => {
                    if (!documentRecord) {
                        return;
                    }

                    const key = [
                        documentRecord.source_document_uid,
                        documentRecord.download_url,
                        documentRecord.title,
                    ].filter(Boolean).join('|').toLowerCase();

                    if (!key || seen.has(key)) {
                        return;
                    }

                    seen.add(key);
                    documents.push(documentRecord);
                };

                for (const row of Array.from(document.querySelectorAll('.messages-modal-document-list .df-sidebar-document-row')).slice(0, 80)) {
                    const args = Array.from(String(row.getAttribute('onclick') || '').matchAll(/'([^']*)'/g)).map((match) => match[1]);

                    addDocument(normalizePulseDocument({
                        source_document_uid: (row.id || '').replace(/^document_/, '') || args[2] || '',
                        source_client_uid: args[3] || '',
                        client_document_url: args[0] || '',
                        client_document_full_url: args[1] || '',
                        client_document_name_text: row.querySelector('.messages-modal-client-doc')?.textContent || '',
                        client_document_date: row.querySelector('.messages-modal-item-time')?.textContent || '',
                    }, 'pulse-sidebar'));
                }

                const selectedDownload = document.querySelector('#df_downloadDocument')?.getAttribute('href') || '';
                const selectedPreview = document.querySelector('#documentPreviewIdSidebar, #documentPreviewImgIdSidebar')?.getAttribute('src') || '';

                if (selectedDownload || selectedPreview) {
                    addDocument(normalizePulseDocument({
                        source_document_uid: document.querySelector('#df_markDocumentViewed')?.getAttribute('data_client_document_u_id') || '',
                        source_client_uid: document.querySelector('#df_markDocumentViewed')?.getAttribute('data-clientuid') || '',
                        client_document_full_url: selectedDownload,
                        preview_url: selectedPreview,
                        client_document_name_text: document.querySelector('.messages-modal-document-list .activeDoconSidebar .messages-modal-client-doc')?.textContent || filenameFromPath(selectedDownload || selectedPreview),
                        client_document_date: document.querySelector('.messages-modal-document-list .activeDoconSidebar .messages-modal-item-time')?.textContent || '',
                    }, 'pulse-selected-document'));
                }

                const clientUid = clientUidFromPage();

                if (/pulse\.disputeprocess\.com$/i.test(window.location.hostname) && clientUid) {
                    try {
                        const response = await fetch(`${window.location.origin}/clientDashboard`, {
                            method: 'POST',
                            credentials: 'include',
                            headers: {
                                Accept: 'application/json, text/javascript, */*; q=0.01',
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: new URLSearchParams({
                                method: 'getDocumentListByClientId',
                                client_u_id: clientUid,
                                documentListForSidebar: '1',
                            }),
                        });
                        const payload = await response.json().catch(() => null);

                        for (const doc of Array.isArray(payload?.data) ? payload.data : []) {
                            addDocument(normalizePulseDocument(doc, 'pulse-api'));
                        }
                    } catch (_) {
                        // Pulse sometimes blocks background AJAX until the drawer is opened; DOM capture still catches visible rows.
                    }
                }

                return documents.slice(0, 100);
            };
            const cleanProfileTitle = (value) => {
                const cleaned = short(value, 160)
                    .replace(/\s*\|\s*.*$/, '')
                    .replace(/\s*[-–]\s*(account|overview|profile|details|client)\s*$/i, '')
                    .trim();
                const normalized = cleaned.toLowerCase();

                if (!cleaned || ['main dashboard', 'clients', 'leads', 'affiliates', 'invoices', 'welcome'].includes(normalized)) {
                    return '';
                }

                return cleaned;
            };
            const pickLabel = (el) => {
                const id = el.id ? document.querySelector(`label[for="${CSS.escape(el.id)}"]`) : null;
                const wrap = el.closest('label');
                const ariaLabel = el.getAttribute('aria-label');
                const ariaLabelledBy = el.getAttribute('aria-labelledby');
                const labelledBy = ariaLabelledBy
                    ? Array.from(document.querySelectorAll(`#${ariaLabelledBy.split(/\s+/).map((part) => CSS.escape(part)).join(', #')}`))
                        .map((node) => short(node.textContent))
                        .filter(Boolean)
                        .join(' ')
                    : '';

                return short(id?.textContent || wrap?.textContent || ariaLabel || labelledBy || '');
            };
            const valueForField = (el, tag) => {
                if (tag === 'select') {
                    const selected = el.options?.[el.selectedIndex];
                    return String(selected?.textContent || el.value || '').trim();
                }

                if (tag === 'textarea') return String(el.value || '').trim();
                if (el.isContentEditable) return String(el.innerText || el.textContent || '').trim();

                return String(el.value || '').trim();
            };
            const aliases = {
                first_name: ['first name', 'first', 'firstname', 'fname', 'client first name', 'customer first name', 'consumer first name'],
                last_name: ['last name', 'last', 'lastname', 'lname', 'client last name', 'customer last name', 'consumer last name'],
                full_name: ['full name', 'name', 'client', 'client name', 'customer', 'customer name', 'consumer', 'consumer name', 'account name'],
                email: ['email', 'email address', 'primary email', 'client email', 'customer email'],
                secondary_email: ['additional email', 'alternate email', 'secondary email', 'other email'],
                phone: ['phone', 'phone number', 'primary phone', 'home phone', 'cell phone', 'mobile phone', 'mobile', 'cell', 'telephone', 'cell phone only for sms security codes'],
                address_line_1: ['address', 'current address', 'address 1', 'address line 1', 'street address', 'mailing address'],
                address_line_2: ['address 2', 'address line 2', 'apt', 'apartment', 'suite', 'unit'],
                city: ['city', 'current city', 'mailing city'],
                state: ['state', 'current state', 'mailing state'],
                postal_code: ['zip', 'zip code', 'zipcode', 'postal code', 'postal'],
                date_of_birth: ['date of birth', 'dob', 'd o b', 'birth date', 'birthday'],
                ssn: ['ssn', 'ssns', 'ss', 'social', 'social security', 'social security number'],
                status: ['status', 'progress', 'client status', 'account status', 'client progress'],
                source_record_id: ['source record id', 'external record id', 'external customer id', 'external client id', 'pulse customer id', 'customer uid', 'client uid'],
                source_record_int_id: ['source record integer id', 'external integer id', 'client int id', 'customer int id', 'pulse client int id'],
                reporting_stage: ['stage', 'current stage', 'stage in process', 'stage in processs'],
                assigned_to: ['assigned to', 'agent', 'sales person', 'owner'],
            };
            const pulseProfileHiddenFieldAllowed = (el) => {
                if (!isPulsePage || pageKind !== 'profile') {
                    return false;
                }

                const haystack = normalizeKey([
                    pickLabel(el),
                    el.getAttribute('name'),
                    el.id,
                    el.getAttribute('placeholder'),
                    el.getAttribute('autocomplete'),
                    el.getAttribute('aria-label'),
                ].filter(Boolean).join(' '));

                if (!haystack) {
                    return false;
                }

                return [
                    'client ssn',
                    'social security number',
                    'client dob',
                    'date of birth',
                    'client password',
                    'monitoring agency',
                    'credit monitoring',
                    'monitoring username',
                    'monitoring password',
                    'import failed monitoring',
                    'secret key',
                    'last 4 of ssn',
                    'last four of ssn',
                ].some((needle) => haystack.includes(needle));
            };
            const records = Array.from(document.querySelectorAll('input, textarea, select, [contenteditable="true"]'))
                .map((el) => {
                    const tag = el.tagName.toLowerCase();
                    const type = tag === 'input' ? (el.getAttribute('type') || 'text').toLowerCase() : tag;

                    if (['hidden', 'file'].includes(type)) return null;
                    if (type === 'password' && !(isPulsePage && pageKind === 'profile')) return null;
                    if (!visibleEnough(el) && !pulseProfileHiddenFieldAllowed(el)) return null;

                    const label = pickLabel(el);
                    const value = valueForField(el, tag);

                    return {
                        label,
                        name: short(el.getAttribute('name')),
                        id: short(el.id),
                        placeholder: short(el.getAttribute('placeholder')),
                        autocomplete: short(el.getAttribute('autocomplete')),
                        aria_label: short(el.getAttribute('aria-label')),
                        type,
                        value,
                    };
                })
                .filter(Boolean)
                .filter((entry) => entry.value);

            const addTextRecord = (label, value, source = 'text') => {
                const cleanLabel = short(label, 120).replace(/:$/, '').trim();
                const cleanValue = short(value, 500);

                if (!cleanLabel || !cleanValue || cleanLabel === cleanValue) {
                    return;
                }

                records.push({
                    label: cleanLabel,
                    name: '',
                    id: '',
                    placeholder: '',
                    autocomplete: '',
                    aria_label: '',
                    type: source,
                    value: cleanValue,
                });
            };

            if (pageKind === 'profile') {
                const titleCustomerName = document.querySelector('title[customer_name]')?.getAttribute('customer_name');
                const cardName = document.querySelector('#client_card_info_name, .client_card_info_name')?.textContent;
                const titleName = cleanProfileTitle(document.title);

                addTextRecord('customer name', titleCustomerName || cardName || titleName, 'profile-marker');

                try {
                    const sourceId = new URL(currentUrl).searchParams.get('id');

                    if (sourceId) {
                        addTextRecord('source record id', sourceId, 'url');
                    }
                } catch (_) {
                    // Keep extraction resilient on malformed third-party URLs.
                }

                const html = document.documentElement.outerHTML;
                const clientIdMatch = html.match(/\bvar\s+clientId\s*=\s*['"]([^'"]+)['"]/i);

                if (clientIdMatch?.[1]) {
                    addTextRecord('source record id', clientIdMatch[1], 'script');
                }

                const forcePaymentRaw = window.localStorage?.getItem('forcePaymentData');

                if (forcePaymentRaw) {
                    try {
                        const forcePayment = JSON.parse(forcePaymentRaw);

                        if (forcePayment?.client_u_id) {
                            addTextRecord('source record id', forcePayment.client_u_id, 'local-storage');
                        }

                        if (forcePayment?.client_id) {
                            addTextRecord('source record integer id', forcePayment.client_id, 'local-storage');
                        }
                    } catch (_) {
                        // Third-party localStorage can be stale or partial; ignore parse failures.
                    }
                }

                for (const row of Array.from(document.querySelectorAll('.customer-side-panel-ssn, .profile-row, .detail-row, .info-row')).slice(0, 120)) {
                    const label = row.querySelector('.customer-side-panel-ssn-left, .label, .field-label, dt')?.textContent;
                    const value = row.querySelector('.customer-side-panel-ssn-right, .value, .field-value, dd')?.textContent;

                    addTextRecord(label, value, 'detail-row');
                }

                const selectorPairs = [
                    ['#customer-side-panel-ssn-right', 'ssn'],
                    ['#customer-side-panel-ssn-right-dob', 'date of birth'],
                    ['#customer-side-panel-ssn-right-cellnumber', 'cell phone'],
                    ['#customer-side-panel-ssn-right-home-phone-number', 'home phone'],
                    ['#customer-side-panel-ssn-right-email', 'email'],
                    ['#customer-side-panel-client-id-right-started', 'source record integer id'],
                ];

                for (const [selector, label] of selectorPairs) {
                    addTextRecord(label, document.querySelector(selector)?.textContent, 'profile-selector');
                }
            }

            const listRecords = [];
            const textFrom = (selector, root = document) => short(root.querySelector(selector)?.innerText || root.querySelector(selector)?.textContent || '', 500);
            const detectPulseInvoiceDetail = () => {
                const modal = ['#ViewInvoiceModal', '#previewModal']
                    .map((selector) => document.querySelector(selector))
                    .find((candidate) => candidate && visibleEnough(candidate));

                if (!modal) {
                    return null;
                }

                const modalText = short(modal.innerText || modal.textContent || '', 2000);
                const invoiceId = textFrom('#invoice_id_number', modal)
                    || textFrom('#invoiceIdDisplay', modal)
                    || (modalText.match(/Invoice\s*(?:ID\s*Number|#)\s*([A-Z0-9-]+)/i)?.[1] || '');
                const clientName = textFrom('#invoice_customer_name', modal)
                    || textFrom('#clientName', modal);
                const clientUid = String(modal.querySelector('#client_id_number')?.dataset?.client_id || '').trim();
                const sourceInvoiceId = String(modal.querySelector('#invoice_id_number')?.dataset?.invoice_id || '').trim();
                const dueDate = textFrom('#total_due_date', modal)
                    || textFrom('#dueDateDisplay', modal)
                    || textFrom('#invoice_created_date', modal);
                const total = textFrom('#grand_total', modal)
                    || textFrom('#grandTotalDisplay', modal);

                if (!invoiceId && !clientName && !total) {
                    return null;
                }

                const lineItems = Array.from(modal.querySelectorAll('.view-invoice-min-height .view-invoice-item-row, #tbody tr'))
                    .map((row) => {
                        const cells = Array.from(row.querySelectorAll('th, td'))
                            .map((cell) => short(cell.innerText || cell.textContent, 250))
                            .filter(Boolean);

                        if (cells.length > 0) {
                            return {
                                id: cells[0] || '',
                                description: cells[1] || cells[0] || '',
                                quantity: cells[2] || '',
                                amount: cells[3] || '',
                                discount: cells[4] || '',
                                total: cells[5] || cells[cells.length - 1] || '',
                            };
                        }

                        const description = short(row.querySelector('.view-invoice-tem')?.innerText || row.querySelector('.view-invoice-tem')?.textContent || '', 250);
                        const amount = short(row.querySelector('.text-block-81')?.innerText || row.querySelector('.text-block-81')?.textContent || '', 120);

                        return description || amount
                            ? { id: '', description, quantity: '', amount, discount: '', total: amount }
                            : null;
                    })
                    .filter(Boolean)
                    .slice(0, 80);

                return {
                    table_id: 'disputefox_invoice_detail',
                    source_record_id: sourceInvoiceId || invoiceId.replace(/^DISF/i, ''),
                    source_record_int_id: sourceInvoiceId,
                    profile_url: clientUid ? absoluteUrl(`customer_dashboard.jsp?id=${clientUid}`) : '',
                    values: {
                        'Invoice ID': invoiceId,
                        'Client': clientName,
                        'Create Date': textFrom('#invoice_created_date', modal),
                        'Due Date': dueDate.replace(/^Total Due on\s*/i, ''),
                        'Payment Type': textFrom('#proceedCard', modal),
                        'Amount': total,
                        'Status': textFrom('.view-invoice-no-due', modal) || 'invoice_detail',
                        'Sub Total': textFrom('#sub-total', modal) || textFrom('#subTotalDisplay', modal),
                        'Sales Tax': textFrom('#salesTaxDisplay', modal),
                        'Notes': textFrom('#notesForCustomerText', modal),
                        'Company': textFrom('#invoice_company_name', modal) || textFrom('#companyName', modal),
                        'Client Address': [
                            textFrom('#invoice_customer_street', modal) || textFrom('#clientAddress', modal),
                            textFrom('#invoice_customer_city', modal) || textFrom('#clientCityAndState', modal),
                            textFrom('#clientCountryAndPostalCode', modal),
                        ].filter(Boolean).join(' '),
                    },
                    line_items: lineItems,
                    modal_source: modal.id || 'invoice-modal',
                };
            };
            const dataTables = Array.from(document.querySelectorAll('#clientsORleadsDatatable, #affiliate_table, #client_invoice_table, table.dataTable, table.datatable, table'))
                .filter(hasUsableRecordRows)
                .filter((table, index, tables) => tables.indexOf(table) === index)
                .slice(0, 8);

            for (const table of dataTables) {
                const headers = Array.from(table.querySelectorAll('thead th'))
                    .map((cell) => short(cell.innerText || cell.textContent, 100))
                    .map((header, index) => header || `Column ${index + 1}`);

                for (const row of Array.from(table.querySelectorAll('tbody tr')).slice(0, 2000)) {
                    const cells = Array.from(row.querySelectorAll('td'))
                        .map((cell) => short(cell.innerText || cell.textContent, 500));

                    if (cells.filter(Boolean).length < 2) {
                        continue;
                    }

                    const record = {
                        table_id: table.id || '',
                        source_record_id: row.querySelector('[data-clientid]')?.getAttribute('data-clientid')
                            || row.querySelector('input.client-list-check[value]')?.getAttribute('value')
                            || '',
                        source_record_int_id: row.querySelector('[data-clientintid]')?.getAttribute('data-clientintid') || '',
                        profile_url: row.querySelector('a[href*="customer_dashboard.jsp?id="]')?.href || '',
                        values: {},
                    };

                    cells.forEach((value, index) => {
                        record.values[headers[index] || `Column ${index + 1}`] = value;
                    });

                    listRecords.push(record);
                }
            }

            const invoiceDetail = detectPulseInvoiceDetail();

            if (invoiceDetail) {
                listRecords.unshift(invoiceDetail);
            }

            for (const row of Array.from(document.querySelectorAll('tr')).slice(0, 600)) {
                const cells = Array.from(row.querySelectorAll('th, td'))
                    .map((cell) => short(cell.innerText || cell.textContent, 500))
                    .filter(Boolean);

                if (cells.length >= 2) {
                    addTextRecord(cells[0], cells.slice(1).join(' '), 'table');
                }
            }

            const terms = Array.from(document.querySelectorAll('dt'));
            for (const term of terms.slice(0, 400)) {
                const value = term.nextElementSibling?.matches('dd')
                    ? term.nextElementSibling.textContent
                    : '';
                addTextRecord(term.textContent, value, 'definition');
            }

            for (const node of Array.from(document.querySelectorAll('p, li, div, span')).slice(0, 1600)) {
                const text = short(node.innerText || node.textContent, 260);

                if (!text || text.length > 240 || !text.includes(':')) {
                    continue;
                }

                const match = text.match(/^([^:]{2,70}):\s*(.{1,180})$/);

                if (match) {
                    addTextRecord(match[1], match[2], 'label-text');
                }
            }

            const bodyLines = short(document.body?.innerText || '', 12000)
                .split(/\n+/)
                .map((line) => short(line, 260))
                .filter(Boolean);

            for (const line of bodyLines.slice(0, 500)) {
                const match = line.match(/^([^:]{2,70}):\s*(.{1,180})$/);

                if (match) {
                    addTextRecord(match[1], match[2], 'body-line');
                }
            }

            const uniqueBy = (items, keyFor) => {
                const seen = new Set();

                return items.filter((item) => {
                    const key = String(keyFor(item) || '').toLowerCase();

                    if (!key || seen.has(key)) {
                        return false;
                    }

                    seen.add(key);

                    return true;
                });
            };
            const keyForLabel = (value) => String(value ?? '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
            const cleanAutomationLabel = (value, limit = 160) => short(String(value ?? '')
                .replace(/\bnewfeatureimage\b/gi, '')
                .replace(/^\+\s*/, '')
                .replace(/\s+/g, ' ')
                .trim(), limit);
            const selectedText = (selector) => {
                const select = document.querySelector(selector);
                const option = select?.options?.[select.selectedIndex];

                return cleanAutomationLabel(option?.textContent || option?.value || '');
            };
            const automationCatalogFromOptions = (selector, source) => uniqueBy(
                Array.from(document.querySelectorAll(`${selector} option`))
                    .map((option) => ({
                        key: keyForLabel(option.value || option.textContent),
                        label: cleanAutomationLabel(option.textContent || option.value, 140),
                        source,
                    }))
                    .filter((item) => item.key && item.label && item.key !== 'select'),
                (item) => item.key,
            ).slice(0, 140);
            const automationActionCatalog = () => {
                const known = [
                    'Email',
                    'SMS',
                    'Portal Message',
                    'Create Task',
                    'Create Note',
                    'Portal Access On/Off',
                    'API Action',
                    'Update Data Fields',
                    'Mobile App SMS',
                ];
                const items = Array.from(document.querySelectorAll('.dropdown-item, .df-steps-item-title, .condition-name'))
                    .map((node) => cleanAutomationLabel(node.innerText || node.textContent, 140))
                    .map((label) => {
                        const matched = known.find((candidate) => label.toLowerCase().includes(candidate.toLowerCase()));

                        return matched || label;
                    })
                    .filter((label) => known.includes(label));

                return uniqueBy([...known.filter((label) => items.includes(label)), ...items]
                    .map((label) => ({
                        key: keyForLabel(label),
                        label,
                        source: 'autofox-action',
                    })), (item) => item.key).slice(0, 80);
            };
            const automationStepActions = (container) => uniqueBy(
                Array.from(container.querySelectorAll('.df-steps-item-title, .condition-name, .dropdown-item'))
                    .map((node) => cleanAutomationLabel(node.innerText || node.textContent, 120))
                    .filter((label) => label && !['Details', 'New Action', 'New Step', '+ Add'].includes(label))
                    .map((label) => label.replace(/\s+Message$/i, ' Message')),
                (label) => label,
            ).slice(0, 20);
            const automationSteps = () => {
                const detailSteps = Array.from(document.querySelectorAll('.workflow-step-section')).slice(0, 100)
                    .map((section) => {
                        const title = cleanAutomationLabel(section.querySelector('.af-steps-title')?.textContent, 160);
                        const timing = cleanAutomationLabel(section.querySelector('.af-steps-starts-text')?.textContent, 120);
                        const status = cleanAutomationLabel(section.querySelector('.af-steps-status')?.textContent, 80);

                        return title ? {
                            title,
                            timing,
                            status,
                            actions: automationStepActions(section),
                        } : null;
                    })
                    .filter(Boolean);

                if (detailSteps.length > 0) {
                    return detailSteps;
                }

                return Array.from(document.querySelectorAll('.autofox-steps-div')).slice(0, 100)
                    .map((section) => ({
                        title: cleanAutomationLabel(section.closest('tr')?.querySelector('.client-grid-name')?.textContent || 'AutoFox workflow', 160),
                        timing: '',
                        status: cleanAutomationLabel(section.querySelector('.type_name, .fox-status-active, .fox-status-inactive')?.textContent, 80),
                        actions: automationStepActions(section),
                    }))
                    .filter((step) => step.title || step.actions.length);
            };
            const workflowFieldFromText = (text, label) => {
                const pattern = new RegExp(`${label}\\s*-\\s*([^\\n\\r]+)`, 'i');
                const match = text.match(pattern);

                return cleanAutomationLabel(match?.[1] || '', 140);
            };
            const workflowIdFromHref = (element) => {
                const href = element?.getAttribute?.('href') || '';

                try {
                    const url = new URL(href, currentUrl);

                    return url.searchParams.get('autofox_id') || '';
                } catch (_) {
                    const match = href.match(/autofox_id=(\d+)/i);

                    return match?.[1] || '';
                }
            };
            const detectAutomationDiscovery = () => {
                const pageText = document.body?.innerText || '';
                const lowerUrl = currentUrl.toLowerCase();
                const lowerTitle = document.title.toLowerCase();
                const hasAutoFoxMarker = lowerUrl.includes('autofox')
                    || lowerTitle.includes('autofox')
                    || pageText.toLowerCase().includes('autofox workflow')
                    || Boolean(document.querySelector('#workflow_name, #workflow_service_Table, #workflowActiveStatus'));

                if (!hasAutoFoxMarker) {
                    return null;
                }

                let autofoxId = '';

                try {
                    autofoxId = new URL(currentUrl).searchParams.get('autofox_id') || '';
                } catch (_) {
                    autofoxId = '';
                }

                const actionCatalog = automationActionCatalog();
                const conditionCatalog = automationCatalogFromOptions('select.conditions', 'start-condition');
                const suspensionCatalog = automationCatalogFromOptions('select.conditionsSuspends', 'suspension-condition');
                const pageKind = document.querySelector('#workflow_name, #workflowActiveStatus, .workflow-step-section')
                    ? 'automation-workflow'
                    : 'automation-workflow-list';
                const labelSample = bodyLines
                    .filter((line) => /autofox|workflow|condition|action|step|invoice|client|lead/i.test(line))
                    .slice(0, 40);
                const listWorkflows = Array.from(document.querySelectorAll('#workflow_service_Table tbody tr')).slice(0, 120)
                    .map((row) => {
                        const info = row.querySelector('.autofox-info-div');

                        if (!info) {
                            return null;
                        }

                        const text = info.innerText || '';
                        const editLink = row.querySelector('a[href*="createautofox.jsp"]');
                        const name = cleanAutomationLabel(info.querySelector('.client-grid-name')?.textContent, 220);
                        const sourceIdentifier = workflowIdFromHref(editLink)
                            || row.querySelector('[data-id]')?.getAttribute('data-id')
                            || row.innerHTML.match(/workflowStartStop\((\d+)/)?.[1]
                            || '';
                        const rowActions = automationStepActions(row);

                        return name ? {
                            source_identifier: sourceIdentifier,
                            name,
                            status: cleanAutomationLabel(row.querySelector('.type_name, .fox-status-active, .fox-status-inactive')?.textContent, 80),
                            created_label: workflowFieldFromText(text, 'Created'),
                            workflow_type: workflowFieldFromText(text, 'AutoFox Type'),
                            start_condition: workflowFieldFromText(text, 'Start Condition'),
                            category: '',
                            action_catalog: rowActions.map((label) => ({
                                key: keyForLabel(label),
                                label,
                                source: 'workflow-row',
                            })),
                            steps: [{
                                title: name,
                                timing: workflowFieldFromText(text, 'Start Condition'),
                                status: cleanAutomationLabel(row.querySelector('.type_name, .fox-status-active, .fox-status-inactive')?.textContent, 80),
                                actions: rowActions,
                            }],
                            label_sample: short(text, 800).split(/\n+/).filter(Boolean).slice(0, 12),
                        } : null;
                    })
                    .filter(Boolean);
                const workflowNameInput = document.querySelector('#workflow_name');
                const detailName = cleanAutomationLabel(workflowNameInput?.value || workflowNameInput?.getAttribute('placeholder'), 255);
                const detailWorkflow = detailName || autofoxId || document.querySelector('#workflowActiveStatus')
                    ? {
                        source_identifier: autofoxId
                            || document.querySelector('#workflow_id_main, #workflow_id_step, input[name="workflow_id"]')?.value
                            || '',
                        name: detailName || cleanAutomationLabel(document.querySelector('.page-titles, .af-steps-title')?.textContent, 255),
                        status: cleanAutomationLabel(document.querySelector('#workflowActiveStatus')?.textContent, 80),
                        category: selectedText('#workflow_category'),
                        workflow_type: cleanAutomationLabel(document.querySelector('input[name="workflowFor"]:checked')?.closest('label')?.textContent, 120),
                        start_condition: cleanAutomationLabel(document.querySelector('input[name="workflowStart"]:checked')?.closest('label')?.textContent, 120),
                        condition_catalog: conditionCatalog,
                        suspension_condition_catalog: suspensionCatalog,
                        action_catalog: actionCatalog,
                        steps: automationSteps(),
                        label_sample: labelSample,
                    }
                    : null;
                const workflows = listWorkflows.length > 0
                    ? listWorkflows
                    : (detailWorkflow ? [detailWorkflow] : []);

                return {
                    source_system: 'disputefox',
                    source_product: 'AutoFox',
                    page_kind: pageKind,
                    workflow: detailWorkflow,
                    workflows,
                    condition_catalog: uniqueBy([...conditionCatalog, ...suspensionCatalog], (item) => item.key),
                    action_catalog: actionCatalog,
                    label_sample: labelSample,
                    detected_at: new Date().toISOString(),
                };
            };

            const documents = pageKind === 'profile' ? await detectPulseDocuments() : [];
            const fields = {};
            const rawFields = [];

            for (const record of records) {
                const haystack = normalizeKey([
                    record.label,
                    record.name,
                    record.id,
                    record.placeholder,
                    record.autocomplete,
                    record.aria_label,
                ].filter(Boolean).join(' '));
                let mappedTo = '';

                for (const [target, targetAliases] of Object.entries(aliases)) {
                    if (pageKind === 'record-list' && !['source_record_id', 'source_record_int_id'].includes(target)) {
                        continue;
                    }

                    if (!fields[target] && targetAliases.some((alias) => haystack === alias || haystack.includes(alias))) {
                        fields[target] = record.value;
                        mappedTo = target;
                        break;
                    }
                }

                rawFields.push({
                    label: record.label || record.placeholder || record.name || record.id || record.type,
                    name: record.name,
                    id: record.id,
                    source: record.type,
                    mapped_to: mappedTo,
                    value: record.value,
                });
            }

            if (!fields.full_name && pageKind === 'profile') {
                const titleName = cleanProfileTitle(document.querySelector('title[customer_name]')?.getAttribute('customer_name') || document.title);
                if (titleName && titleName.split(/\s+/).length >= 2) {
                    fields.full_name = titleName;
                }
            }

            if (pageKind === 'profile') {
                const profileText = (selector) => short(document.querySelector(selector)?.innerText || document.querySelector(selector)?.textContent || '', 240);
                const titleName = cleanProfileTitle(
                    document.querySelector('title[customer_name]')?.getAttribute('customer_name')
                    || document.querySelector('#client_card_info_name, .client_card_info_name')?.textContent
                    || document.title,
                );
                const sourceRecordId = (() => {
                    try {
                        return new URL(currentUrl).searchParams.get('id') || '';
                    } catch (_) {
                        return '';
                    }
                })();
                const profileOverrides = {
                    full_name: titleName,
                    ssn: profileText('#customer-side-panel-ssn-right'),
                    date_of_birth: profileText('#customer-side-panel-ssn-right-dob'),
                    email: profileText('#customer-side-panel-ssn-right-email'),
                    phone: profileText('#customer-side-panel-ssn-right-cellnumber') || profileText('#customer-side-panel-ssn-right-home-phone-number'),
                    address_line_1: profileText('.client_card_info_address').replace(/\s*,?\s*Kansas City/i, ', Kansas City'),
                    source_record_id: sourceRecordId,
                    source_record_int_id: profileText('#customer-side-panel-client-id-right-started'),
                    assigned_to: profileText('#customer-side-panel-assignedto-agent-name'),
                };

                for (const [key, value] of Object.entries(profileOverrides)) {
                    const cleaned = short(value, 240);

                    if (cleaned && !['on', 'off', 'select', '0'].includes(cleaned.toLowerCase())) {
                        fields[key] = cleaned;
                    }
                }
            }

            if (!fields.full_name && (fields.first_name || fields.last_name)) {
                fields.full_name = [fields.first_name, fields.last_name].filter(Boolean).join(' ');
            }

            const mappedCount = Object.values(fields).filter(Boolean).length;

            return {
                title: document.title,
                url: window.location.href,
                dom_html: document.documentElement.outerHTML,
                selection_text: window.getSelection()?.toString() ?? '',
                structured_customer: {
                    fields,
                    raw_fields: rawFields.slice(0, 400),
                    list_records: listRecords.slice(0, 2000),
                    documents,
                    page_kind: pageKind,
                    confidence: Math.min(1, mappedCount / 8),
                },
                structured_automation: detectAutomationDiscovery(),
                browser_name: (() => {
                const ua = navigator.userAgent;

                if (ua.includes('Edg/')) return 'Edge';
                if (ua.includes('Firefox/')) return 'Firefox';
                if (ua.includes('Safari/') && !ua.includes('Chrome/')) return 'Safari';
                if (ua.includes('Chrome/')) return 'Chrome';

                return 'Browser';
            })(),
            user_agent: navigator.userAgent,
            captured_at: new Date().toISOString(),
            package_name: 'CreditSoft Browser Companion',
            capture_source: 'companion_capture',
            };
        },
    });

    const captures = results.map((result) => result?.result).filter(Boolean);

    if (captures.length === 0) {
        throw new Error('Could not read the active page DOM.');
    }

    const primary = captures[0];
    const mergedFields = {};
    const rawFields = [];
    const listRecords = [];
    const documents = [];
    const pageKinds = [];
    const automationCaptures = [];

    for (const capture of captures) {
        for (const [key, value] of Object.entries(capture.structured_customer?.fields || {})) {
            if (!mergedFields[key] && String(value || '').trim() !== '') {
                mergedFields[key] = value;
            }
        }

        rawFields.push(...(capture.structured_customer?.raw_fields || []));
        listRecords.push(...(capture.structured_customer?.list_records || []));
        documents.push(...(capture.structured_customer?.documents || []));

        if (capture.structured_customer?.page_kind) {
            pageKinds.push(capture.structured_customer.page_kind);
        }

        if (capture.structured_automation) {
            automationCaptures.push(capture.structured_automation);
        }
    }

    const mappedCount = Object.values(mergedFields).filter(Boolean).length;
    const pageKind = pageKinds.includes('profile')
        ? 'profile'
        : (pageKinds.includes('record-list') ? 'record-list' : 'page');
    const structuredAutomation = automationCaptures.length > 0
        ? {
            ...automationCaptures[0],
            workflows: automationCaptures.flatMap((capture) => capture.workflows || []).slice(0, 160),
            condition_catalog: automationCaptures.flatMap((capture) => capture.condition_catalog || []).slice(0, 200),
            action_catalog: automationCaptures.flatMap((capture) => capture.action_catalog || []).slice(0, 120),
        }
        : null;

    return {
        ...primary,
        title: tab.title || primary.title,
        url: tab.url || primary.url,
        dom_html: captures.map((capture) => capture.dom_html).filter(Boolean).join('\n<!-- CreditSoft frame break -->\n'),
        structured_customer: {
            fields: mergedFields,
            raw_fields: rawFields.slice(0, 800),
            list_records: listRecords.slice(0, 2000),
            documents: documents.slice(0, 100),
            page_kind: pageKind,
            confidence: Math.min(1, mappedCount / 8),
        },
        structured_automation: structuredAutomation,
    };
}

async function fetchNextClient(settings, pageContext, options = {}) {
    const params = new URLSearchParams();
    const providerKey = pageContext?.provider_key ?? null;
    const excludeProviderAccountId = Number.isFinite(options?.excludeProviderAccountId)
        ? Number(options.excludeProviderAccountId)
        : null;
    const forceUpdate = Boolean(options?.forceUpdate);

    if (providerKey) {
        params.set('provider_key', providerKey);
    }

    if (pageContext?.url) {
        params.set('page_url', pageContext.url);
    }

    if (pageContext?.title) {
        params.set('page_title', pageContext.title);
    }

    if (excludeProviderAccountId) {
        params.set('exclude_provider_account_id', String(excludeProviderAccountId));
    }

    if (forceUpdate) {
        params.set('force_update', '1');
    }

    params.set('worker_id', settings.worker_id);

    const apiBaseUrl = await resolveApiBaseUrl(settings);
    const endpoint = `${apiBaseUrl}${NEXT_READY_ACCOUNT_PATH}?${params.toString()}`;

    const response = await fetch(endpoint, {
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${settings.office_token}`,
            'X-CreditSoft-Token': settings.office_token,
        },
    });

    const parsed = await parseJsonResponse(response);

    if (!response.ok) {
        throw new Error(apiErrorMessage(parsed, response.status, 'Could not load clients from CreditSoft.'));
    }

    return parsed;
}

async function fetchApiOverview(settings) {
    const apiBaseUrl = await resolveApiBaseUrl(settings);
    const endpoint = `${apiBaseUrl}${API_OVERVIEW_PATH}`;

    const response = await fetch(endpoint, {
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${settings.office_token}`,
            'X-CreditSoft-Token': settings.office_token,
        },
    });

    const parsed = await parseJsonResponse(response);

    if (!response.ok) {
        throw new Error(apiErrorMessage(parsed, response.status, 'Could not reach CreditSoft.'));
    }

    return parsed;
}

async function fetchQueuedAccountForContext(settings, { preferCurrentPage = true, excludeProviderAccountId = null, forceUpdate = false } = {}) {
    const requestContext = preferCurrentPage && isSupportedProviderPageContext(activePageContext)
        ? activePageContext
        : {
            provider_key: null,
            page_url: '',
            page_title: '',
        };

    const parsed = await fetchNextClient(settings, requestContext, { excludeProviderAccountId, forceUpdate });

    return extractNextReadyClient(parsed);
}

function extractNextReadyClient(parsed) {
    const payload = parsed?.data ?? parsed?.client ?? parsed?.next_client ?? parsed;

    if (Array.isArray(payload)) {
        return payload[0] ?? null;
    }

    if (payload && typeof payload === 'object') {
        return payload;
    }

    return null;
}

async function loadNextClient({ quiet = false } = {}) {
    if (!settingsCache.office_token) {
        nextReadyAccount = null;
        connectionState = 'empty';
        syncForm();
        return;
    }

    activePageContext = await getActiveTabContext();
    lastContextSignature = contextSignature(activePageContext);
    const onProviderPage = isSupportedProviderPageContext(activePageContext);
    const laneLabel = providerLabel(activePageContext.provider_key);

    if (!onProviderPage) {
        nextReadyAccount = null;
        renderNextClient();
        syncPairingState();
        const helperMessage = isUnsupportedBrowserPage(activePageContext.url)
            ? 'Browser internal pages cannot be captured. Update opens the next provider report page.'
            : 'Ready.';

        setStatus('', 'info');

        if (!quiet) {
            resetActivity(helperMessage);
        }

        return;
    }

    if (!quiet) {
        pushActivity(`Checking CreditSoft for the next ${laneLabel} report update.`);
        setStatus(`Loading next ${laneLabel} report update...`);
    }

    try {
        const parsed = await fetchNextClient(settingsCache, activePageContext);
        applyFeatureFlags(parsed);
        nextReadyAccount = extractNextReadyClient(parsed);
        connectionState = 'ready';
        renderNextClient();
        syncPairingState();

        if (!nextReadyAccount?.client) {
            setStatus(`No ${laneLabel}-ready client is queued right now. Anything already imported today stays skipped until tomorrow.`, 'info');

            if (!quiet) {
                pushActivity(`No ${laneLabel}-ready client is queued right now. Anything already imported today stays skipped until tomorrow.`, 'warn');
            }
            return;
        }

        if (!quiet) {
            pushActivity(`Loaded ${nextReadyAccount.client.display_name} for a ${laneLabel} report update.`, 'success');
        }

        setStatus(
            isCaptureReadyPageContext(activePageContext)
                ? `Ready to update ${nextReadyAccount.client.display_name} in CreditSoft.`
                : `Update will open ${laneLabel} for ${nextReadyAccount.client.display_name}.`,
            isCaptureReadyPageContext(activePageContext) ? 'success' : 'info',
        );
    } catch (error) {
        nextReadyAccount = null;
        connectionState = error instanceof Error && error.message.includes('valid CreditSoft API key')
            ? 'error'
            : connectionState;
        syncForm();

        if (!quiet) {
            pushActivity(error instanceof Error ? error.message : 'Could not load the next report update.', 'error');
            setStatus(error instanceof Error ? error.message : 'Could not load report updates.', 'error');
        }
    }
}

async function markSmartCreditNeedsPaymentAndAdvance(settings, account) {
    const clientName = clientNameFromAccount(account);
    const providerAccountId = Number(account?.provider_account?.id);

    if (!Number.isFinite(providerAccountId)) {
        throw new Error('CreditSoft could not identify the SmartCredit provider account to mark.');
    }

    pushActivity(`${clientName} landed on SmartCredit reactivation. Marking the account and skipping it.`, 'warn');
    setStatus(`${clientName} needs SmartCredit payment/reactivation. Skipping to the next client...`, 'warn');

    await postProviderAccountStatus({
        provider_account_id: providerAccountId,
        status: 'needs_client_payment',
        reason: 'smartcredit_reactivation',
        message: 'SmartCredit redirected the companion to account reactivation, so this client likely needs to renew or pay SmartCredit before reports can be pulled.',
        page_url: activePageContext.url,
        page_title: activePageContext.title,
        worker_id: settings.worker_id,
        companion: {
            name: 'CreditSoft Browser Companion',
            version: chrome.runtime.getManifest().version,
        },
    }, settings);

    const nextAccount = await fetchQueuedAccountForContext(settings, {
        preferCurrentPage: false,
        excludeProviderAccountId: providerAccountId,
        forceUpdate: runnerState.forceUpdate,
    });
    const logoutUrl = providerLogoutUrlFromAccount(account);

    if (!nextAccount?.client || !nextAccount?.provider_account) {
        stopRunner();
        setRunnerAccount(null);
        pushActivity(`${clientName} was noted as needing SmartCredit payment/reactivation. Queue complete for now.`, 'success');
        setStatus(`${clientName} needs SmartCredit payment/reactivation. Queue complete for now.`, 'success');

        if (logoutUrl) {
            await navigateActiveTab(logoutUrl);
        }

        return true;
    }

    runnerState.completedSteps = [];
    setRunnerAccount(nextAccount);
    pushActivity(`Continuing with ${clientNameFromAccount(nextAccount)}.`, 'info');
    setStatus(`Continuing with ${clientNameFromAccount(nextAccount)}...`, 'info');

    if (logoutUrl) {
        await navigateActiveTab(logoutUrl);

        return true;
    }

    const pendingStep = nextPendingCaptureStep(nextAccount);
    const targetUrl = providerStepUrl(toText(nextAccount?.provider_account?.provider_key), pendingStep)
        || providerStartUrlFromAccount(nextAccount);

    if (targetUrl) {
        await navigateActiveTab(targetUrl);
    }

    return true;
}

async function continueRunner({ source = 'manual' } = {}) {
    if (!runnerState.active || runnerState.busy) {
        return;
    }

    runnerState.busy = true;

    try {
        const settings = readFormSettings();
        settingsCache = {
            ...settingsCache,
            ...settings,
        };
        await syncActiveContext({ loadQueue: false, quiet: true });

        if (!settings.office_token) {
            stopRunner();
            throw new Error('Save a CreditSoft API key first from Extension options.');
        }

        if (connectionState !== 'ready') {
            const verified = await verifyConnection({ quiet: source !== 'manual' });

            if (!verified) {
                stopRunner();
                return;
            }
        }

        let account = runnerState.account;

        if (!account?.client?.cuid) {
            if (source === 'manual') {
                pushActivity('Checking CreditSoft for the next report update.');
                setStatus('Checking CreditSoft for the next report update...', 'info');
            }

            account = await fetchQueuedAccountForContext(settings, {
                preferCurrentPage: true,
                forceUpdate: runnerState.forceUpdate || source === 'manual',
            });

            if (!account?.client || !account?.provider_account) {
                stopRunner();
                setRunnerAccount(null);
                setStatus('No report update is waiting right now. Reports already pulled today stay skipped until tomorrow.', 'info');
                pushActivity('No report update is waiting right now. Reports already pulled today stay skipped until tomorrow.', 'warn');
                return;
            }

            setRunnerAccount(account);

            if (source === 'manual') {
                pushActivity(`Loaded ${clientNameFromAccount(account)} for a ${providerLabel(account.provider_account.provider_key)} report update.`, 'success');
            }
        } else {
            setRunnerAccount(account);
        }

        if (
            toText(account?.provider_account?.provider_key) === 'smartcredit'
            && isSmartCreditReactivationContext(activePageContext)
        ) {
            await markSmartCreditNeedsPaymentAndAdvance(settings, account);
            return;
        }

        const ready = await advanceToCaptureReady(account);

        await syncActiveContext({ loadQueue: false, quiet: true });

        if (
            toText(account?.provider_account?.provider_key) === 'smartcredit'
            && isSmartCreditReactivationContext(activePageContext)
        ) {
            await markSmartCreditNeedsPaymentAndAdvance(settings, account);
            return;
        }

        if (!ready) {
            setStatus(`Updating ${providerLabel(account.provider_account.provider_key)} reports for ${clientNameFromAccount(account)}...`, 'info');
            return;
        }

        setRunnerAccount(account);
        const currentStep = detectCaptureStep(activePageContext);

        if (currentStep && runnerState.completedSteps.includes(completedStepKey(account, currentStep))) {
            const pendingStep = nextPendingCaptureStep(account);

            if (pendingStep) {
                const pendingUrl = providerStepUrl(toText(account?.provider_account?.provider_key), pendingStep);

                if (pendingUrl) {
                    pushActivity(`Opening the next page for ${clientNameFromAccount(account)}.`, 'info');
                    setStatus(`Opening the next page for ${clientNameFromAccount(account)}...`, 'info');
                    await navigateActiveTab(pendingUrl);
                    return;
                }
            }
        }

        pushActivity(`Reading the current page for ${clientNameFromAccount(account)}.`);
        setStatus(`Reading ${clientNameFromAccount(account)}...`, 'info');

        const capture = await captureActiveTab();
        const payload = buildPayload(capture, settings);

        pushActivity('Sending the current page into CreditSoft.');
        setStatus(`Sending ${clientNameFromAccount(account)} to CreditSoft...`, 'info');

        const result = await postCapture(payload, settings);
        const importedClientName = result?.data?.client?.display_name ?? clientNameFromAccount(account);
        const capturedStep = currentStep ?? detectCaptureStep({
            url: capture.url,
            title: capture.title,
            provider_key: payload.provider_key,
        });

        if (capturedStep) {
            const stepKey = completedStepKey(account, capturedStep);

            if (!runnerState.completedSteps.includes(stepKey)) {
                runnerState.completedSteps.push(stepKey);
            }
        }

        pushActivity(`Capture delivered for ${importedClientName}.`, 'success');
        setStatus(`Capture delivered for ${importedClientName}.`, 'success');

        const pendingStep = nextPendingCaptureStep(account);

        if (pendingStep) {
            const pendingUrl = providerStepUrl(toText(account?.provider_account?.provider_key), pendingStep);

            if (pendingUrl) {
                pushActivity(`Continuing with ${importedClientName} on the next page.`, 'info');
                setStatus(`Continuing with ${importedClientName} on the next page...`, 'info');
                await navigateActiveTab(pendingUrl);
                return;
            }
        }

        const nextAccount = await fetchQueuedAccountForContext(settings, {
            preferCurrentPage: false,
            excludeProviderAccountId: account?.provider_account?.id ?? null,
            forceUpdate: runnerState.forceUpdate,
        });

        const logoutUrl = providerLogoutUrlFromAccount(account);

        if (logoutUrl) {
            pushActivity(`Logging out of ${providerLabel(toText(account?.provider_account?.provider_key))} for ${importedClientName}.`, 'info');
            setStatus(`Logging out of ${providerLabel(toText(account?.provider_account?.provider_key))}...`, 'info');

            if (!nextAccount?.client || !nextAccount?.provider_account) {
                await navigateActiveTab(logoutUrl);
                stopRunner();
                setRunnerAccount(null);
                pushActivity('Queue complete for now.', 'success');
                setStatus('Queue complete for now.', 'success');
                return;
            }

            runnerState.completedSteps = [];
            runnerState.account = nextAccount;
            nextReadyAccount = nextAccount;
            syncForm();
            await navigateActiveTab(logoutUrl);
            return;
        }

        if (!nextAccount?.client || !nextAccount?.provider_account) {
            stopRunner();
            setRunnerAccount(null);
            pushActivity('Queue complete for now.', 'success');
            return;
        }

        runnerState.completedSteps = [];
        runnerState.account = nextAccount;
        nextReadyAccount = nextAccount;
        syncForm();
        pushActivity(`Continuing with ${clientNameFromAccount(nextAccount)}.`, 'info');
        setStatus(`Continuing with ${clientNameFromAccount(nextAccount)}...`, 'info');
    } catch (error) {
        stopRunner();
        throw error;
    } finally {
        runnerState.busy = false;
    }
}

async function verifyConnection({ quiet = false } = {}) {
    if (!settingsCache.office_token) {
        connectionState = 'empty';
        syncPairingState();
        return false;
    }

    if (!quiet) {
        pushActivity('Checking the saved CreditSoft API key.');
        setStatus('Checking CreditSoft API key...');
    }

    try {
        const overview = await fetchApiOverview({
            ...settingsCache,
            api_base_url: normalizeBaseUrl(settingsCache.api_base_url),
        });
        applyFeatureFlags(overview);
        connectionState = 'ready';
        syncPairingState();

        if (!quiet) {
            pushActivity('CreditSoft API key verified.', 'success');
            setStatus('CreditSoft API key verified.', 'success');
        }

        return true;
    } catch (error) {
        connectionState = 'error';
        nextReadyAccount = null;
        featureFlags = {
            client_sync: false,
            disputefox_credentials: false,
            create_client_if_missing: false,
        };
        renderNextClient();
        syncPairingState();
        syncFeatureVisibility();

        if (!quiet) {
            pushActivity(error instanceof Error ? error.message : 'Could not verify the CreditSoft API key.', 'error');
            setStatus(error instanceof Error ? error.message : 'Could not verify the CreditSoft API key.', 'error');
        }

        return false;
    }
}

function buildPayload(capture, settings) {
    return {
        ...capture,
        api_base_url: settings.api_base_url,
        office_token_present: Boolean(settings.office_token),
        client_cuid: nextReadyAccount?.client?.cuid ?? '',
        provider_key: nextReadyAccount?.provider_account?.provider_key ?? detectProviderFromContext(capture.url, capture.title) ?? DEFAULT_PROVIDER_KEY,
        cycle_label: currentCycleLabel(),
        source_type: 'companion_capture',
        capture_source: capture.capture_source || 'companion_capture',
        worker_id: settings.worker_id,
        companion: {
            name: 'CreditSoft Browser Companion',
            version: chrome.runtime.getManifest().version,
        },
    };
}

async function postCapture(payload, settings) {
    const apiBaseUrl = await resolveApiBaseUrl(settings);
    const endpoint = `${apiBaseUrl}${API_ENDPOINT_PATH}`;

    const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${settings.office_token}`,
            'X-CreditSoft-Token': settings.office_token,
        },
        body: JSON.stringify(payload),
    });

    const parsed = await parseJsonResponse(response);

    if (!response.ok) {
        throw new Error(apiErrorMessage(parsed, response.status, 'CreditSoft rejected the capture.'));
    }

    return parsed;
}

async function postProviderAccountStatus(payload, settings) {
    const apiBaseUrl = await resolveApiBaseUrl(settings);
    const endpoint = `${apiBaseUrl}${API_PROVIDER_STATUS_PATH}`;

    const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${settings.office_token}`,
            'X-CreditSoft-Token': settings.office_token,
        },
        body: JSON.stringify(payload),
    });

    const parsed = await parseJsonResponse(response);

    if (!response.ok) {
        throw new Error(apiErrorMessage(parsed, response.status, 'CreditSoft could not update the provider status.'));
    }

    return parsed;
}

async function postClientSync(payload, settings) {
    const apiBaseUrl = await resolveApiBaseUrl(settings);
    const endpoint = `${apiBaseUrl}${API_CLIENT_SYNC_PATH}`;

    const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${settings.office_token}`,
            'X-CreditSoft-Token': settings.office_token,
        },
        body: JSON.stringify(payload),
    });

    const parsed = await parseJsonResponse(response);

    if (!response.ok) {
        throw new Error(apiErrorMessage(parsed, response.status, 'CreditSoft rejected the DisputeFox client sync.'));
    }

    return parsed;
}

function contentDispositionFilename(value) {
    const header = String(value || '');
    const encoded = header.match(/filename\*=UTF-8''([^;]+)/i)?.[1];

    if (encoded) {
        try {
            return decodeURIComponent(encoded);
        } catch (_) {
            return encoded;
        }
    }

    return header.match(/filename="?([^";]+)"?/i)?.[1] || '';
}

function safePulseDocumentFilename(documentRecord, response) {
    const headerName = contentDispositionFilename(response?.headers?.get?.('content-disposition'));
    const fallback = documentRecord?.file_name || documentRecord?.title || 'pulse-document';
    const fromUrl = (() => {
        try {
            const url = new URL(documentRecord?.download_url || documentRecord?.preview_url || '');
            return decodeURIComponent(url.pathname.split('/').filter(Boolean).pop() || '');
        } catch (_) {
            return '';
        }
    })();
    const candidate = String(headerName || fromUrl || fallback).replace(/[\\/:*?"<>|]+/g, '-').trim();

    return candidate || 'pulse-document';
}

async function fetchPulseDocumentFile(documentRecord) {
    const url = documentRecord?.download_url || documentRecord?.preview_url || '';

    if (!url) {
        throw new Error('DisputeFox did not expose a document download URL.');
    }

    const response = await fetch(url, {
        credentials: 'include',
        redirect: 'follow',
    });

    if (!response.ok) {
        throw new Error(`DisputeFox returned HTTP ${response.status} for the document download.`);
    }

    const contentType = response.headers.get('content-type') || documentRecord.mime_type || 'application/octet-stream';
    const blob = await response.blob();

    if (contentType.includes('text/html') && blob.size < 1024 * 1024) {
        throw new Error('DisputeFox returned a page instead of the document file. Open the document drawer and try again.');
    }

    return {
        blob,
        fileName: safePulseDocumentFilename(documentRecord, response),
        mimeType: contentType || blob.type || 'application/octet-stream',
    };
}

async function postClientDocument(clientCuid, documentRecord, filePayload, settings, capture) {
    const apiBaseUrl = await resolveApiBaseUrl(settings);
    const endpoint = `${apiBaseUrl}${API_CLIENT_DOCUMENT_PATH}`;
    const form = new FormData();

    form.append('client_cuid', clientCuid);
    form.append('source_system', 'disputefox');
    form.append('page_title', capture?.title || '');
    form.append('page_url', capture?.url || '');
    form.append('worker_id', settings.worker_id || '');

    for (const [key, value] of Object.entries(documentRecord || {})) {
        if (value === undefined || value === null) {
            continue;
        }

        form.append(`document[${key}]`, String(value));
    }

    if (filePayload?.blob) {
        form.append(
            'document_file',
            new File([filePayload.blob], filePayload.fileName, {
                type: filePayload.mimeType || filePayload.blob.type || 'application/octet-stream',
            }),
        );
    }

    const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${settings.office_token}`,
            'X-CreditSoft-Token': settings.office_token,
        },
        body: form,
    });
    const parsed = await parseJsonResponse(response);

    if (!response.ok) {
        throw new Error(apiErrorMessage(parsed, response.status, 'CreditSoft rejected the DisputeFox document upload.'));
    }

    return parsed;
}

async function uploadPulseDocumentsForClient(documents, clientCuid, settings, capture) {
    const records = (Array.isArray(documents) ? documents : [])
        .filter((documentRecord) => documentRecord?.download_url || documentRecord?.preview_url)
        .slice(0, 25);
    const stats = {
        attempted: records.length,
        uploaded: 0,
        failed: 0,
        last_error: '',
    };

    for (const documentRecord of records) {
        try {
            const filePayload = await fetchPulseDocumentFile(documentRecord);
            await postClientDocument(clientCuid, {
                ...documentRecord,
                file_name: filePayload.fileName || documentRecord.file_name,
                mime_type: filePayload.mimeType || documentRecord.mime_type,
                file_size: filePayload.blob?.size || documentRecord.file_size || 0,
            }, filePayload, settings, capture);
            stats.uploaded++;
        } catch (error) {
            stats.failed++;
            stats.last_error = error instanceof Error ? error.message.replace(/\bPulse\b/g, 'DisputeFox') : 'Could not import a DisputeFox document.';
        }
    }

    return stats;
}

async function postAutomationDiscovery(payload, settings) {
    const apiBaseUrl = await resolveApiBaseUrl(settings);
    const endpoint = `${apiBaseUrl}${API_AUTOMATION_DISCOVERY_PATH}`;

    const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${settings.office_token}`,
            'X-CreditSoft-Token': settings.office_token,
        },
        body: JSON.stringify(payload),
    });

    const parsed = await parseJsonResponse(response);

    if (!response.ok) {
        throw new Error(apiErrorMessage(parsed, response.status, 'CreditSoft rejected the automation discovery.'));
    }

    return parsed;
}

async function syncAutomationDiscovery(capture, settings) {
    const automation = capture.structured_automation || {};
    const result = await postAutomationDiscovery({
        source_system: automation.source_system || 'disputefox',
        page_title: capture.title,
        page_url: capture.url,
        worker_id: settings.worker_id,
        automation,
        companion: {
            name: 'CreditSoft Browser Companion',
            version: chrome.runtime.getManifest().version,
        },
    }, settings);
    const count = result?.data?.count || result?.data?.discoveries?.length || 0;
    const created = result?.data?.created || 0;
    const updated = result?.data?.updated || 0;
    const noun = count === 1 ? 'workflow' : 'workflows';
    const message = `Saved ${count} AutoFox ${noun} to CreditSoft OPS (${created} new, ${updated} updated).`;

    pushActivity(message, 'success');
    setStatus(message, 'success');

    return result;
}

async function syncDisputeFoxRecordList(capture = null, options = {}) {
    const credentials = readCredentialSettings();
    await chrome.storage.local.set(credentials);
    settingsCache = {
        ...settingsCache,
        ...credentials,
    };

    const settings = await saveSettingsFromForm();

    if (!settings.office_token) {
        throw new Error('Save a CreditSoft API key first from Extension options.');
    }

    if (!featureFlags.client_sync) {
        throw new Error('This CreditSoft API key does not have browser companion client sync enabled.');
    }

    if (connectionState !== 'ready') {
        const verified = await verifyConnection();

        if (!verified) return null;
    }

    const listCapture = capture || await captureActiveTab();
    const profile = listCapture.structured_customer || {};
    const rowCount = profile.list_records?.length || 0;

    if (rowCount < 1) {
        throw new Error('This DisputeFox page did not expose any list rows to import.');
    }

    pushActivity(`Sending ${rowCount} DisputeFox list rows into CreditSoft.`);
    setStatus(`Sending ${rowCount} DisputeFox list rows into CreditSoft...`, 'info');

    const result = await postClientSync({
        client_cuid: null,
        client_profile: profile,
        source_system: 'disputefox',
        page_title: listCapture.title,
        page_url: listCapture.url,
        worker_id: settings.worker_id,
        create_client_if_missing: true,
        credentials: {
            username: settings.disputefox_username || null,
            password_saved: Boolean(settings.disputefox_password),
        },
    }, settings);

    const data = result?.data || {};
    const imported = Number(data.created || 0)
        + Number(data.updated || 0)
        + Number(data.payments_created || 0)
        + Number(data.payments_updated || 0)
        + Number(data.captures_created || 0);
    const listKind = String(data.list_kind || 'list').replaceAll('_', ' ');
    const message = (result?.message || `Imported ${rowCount} DisputeFox ${listKind} rows.`)
        .replace(/\bPulse\b/g, 'DisputeFox');

    pushActivity(`${message} (${imported} saved/updated).`, 'success');
    setStatus(`${message} (${imported} saved/updated).`, 'success');

    if (options.advance === true) {
        await advancePulseMigrationFrom(listCapture);
    }

    return result;
}

async function syncDisputeFoxProfile() {
    const credentials = readCredentialSettings();
    await chrome.storage.local.set(credentials);
    settingsCache = {
        ...settingsCache,
        ...credentials,
    };

    const settings = await saveSettingsFromForm();

    if (!settings.office_token) {
        throw new Error('Save a CreditSoft API key first from Extension options.');
    }

    if (!featureFlags.client_sync) {
        throw new Error('This CreditSoft API key does not have browser companion client sync enabled.');
    }

    if (connectionState !== 'ready') {
        const verified = await verifyConnection();

        if (!verified) return;
    }

    await syncActiveContext({ loadQueue: false, quiet: true });

    const sessionPrompt = isDisputeFoxPulseUrl(activePageContext.url)
        ? await resolvePulseSessionPrompt()
        : null;

    if (sessionPrompt === 'blocked') {
        return;
    }

    pushActivity('Reading DisputeFox profile fields.');
    setStatus('Reading DisputeFox profile fields...', 'info');
    const capture = await captureActiveTab();
    const profile = capture.structured_customer || {};
    const fields = profile.fields || {};
    const hasProfile = captureLooksLikeClientProfile(capture);

    if (!hasProfile && profile.page_kind === 'record-list' && (profile.list_records?.length || 0) > 0) {
        return await syncDisputeFoxRecordList(capture, { advance: false });
    }

    if (captureLooksLikeAutomationDiscovery(capture)) {
        return await syncAutomationDiscovery(capture, settings);
    }

    if (!hasProfile) {
        const labels = (profile.raw_fields || [])
            .map((field) => field.label)
            .filter(Boolean)
            .slice(0, 6)
            .join(', ');
        const listHint = profile.page_kind === 'record-list'
            ? 'This is a list page. Press Current page to import the visible rows, or open a client/lead row to import one full profile.'
            : 'Open the DisputeFox client detail/profile page and try again.';
        throw new Error(labels
            ? `No client profile fields were detected yet. I saw labels like: ${labels}. ${listHint}`
            : `No client profile fields were detected yet. ${listHint}`);
    }

    const result = await postClientSync({
        client_cuid: nextReadyAccount?.client?.cuid || null,
        client_profile: profile,
        source_system: 'disputefox',
        page_title: capture.title,
        page_url: capture.url,
        worker_id: settings.worker_id,
        create_client_if_missing: true,
        credentials: {
            username: settings.disputefox_username || null,
            password_saved: Boolean(settings.disputefox_password),
        },
    }, settings);

    const client = result?.data?.client;
    const displayName = client?.display_name || fields.full_name || 'client';
    const documents = Array.isArray(profile.documents) ? profile.documents : [];
    const syncedDocuments = result?.data?.documents || {};
    const providerAccounts = Array.isArray(result?.data?.provider_accounts?.providers)
        ? result.data.provider_accounts.providers
        : [];
    let documentMessage = '';

    if (client?.cuid && documents.length > 0) {
        pushActivity(`Found ${documents.length} DisputeFox document records for ${displayName}.`, 'info');
        setStatus(`Found ${documents.length} DisputeFox document records. Importing files when DisputeFox allows it...`, 'info');
        const uploadStats = await uploadPulseDocumentsForClient(documents, client.cuid, settings, capture);

        if (uploadStats.uploaded > 0) {
            documentMessage = ` Imported ${uploadStats.uploaded} file${uploadStats.uploaded === 1 ? '' : 's'}.`;
        } else if (Number(syncedDocuments.total || 0) > 0) {
            documentMessage = ` Staged ${syncedDocuments.total} document record${Number(syncedDocuments.total) === 1 ? '' : 's'}; open the DisputeFox document drawer if file downloads are blocked.`;
        }

        if (uploadStats.failed > 0 && uploadStats.uploaded === 0 && uploadStats.last_error) {
            pushActivity(uploadStats.last_error, 'warn');
        }
    } else if (Number(syncedDocuments.total || 0) > 0) {
        documentMessage = ` Staged ${syncedDocuments.total} document record${Number(syncedDocuments.total) === 1 ? '' : 's'}.`;
    }

    const providerMessage = providerAccounts.length > 0
        ? ` Attached ${providerAccounts.map((provider) => provider.provider_label || provider.provider_key).join(', ')} provider login${providerAccounts.length === 1 ? '' : 's'}.`
        : '';

    pushActivity(`Synced ${displayName} from DisputeFox.${documentMessage}${providerMessage}`, 'success');
    setStatus(`Synced ${displayName} from DisputeFox.${documentMessage}${providerMessage}`, 'success');

    if (client?.cuid) {
        nextReadyAccount = {
            ...(nextReadyAccount || {}),
            client,
        };
        renderNextClient();
    }

    return result;
}

async function resolvePulseSessionPrompt() {
    if (!isDisputeFoxPulseUrl(activePageContext.url)) {
        return false;
    }

    const result = await executeOnActiveTab(() => {
        const pageText = (document.body?.innerText || '').replace(/\s+/g, ' ').trim().toLowerCase();

        if (!pageText.includes('multiple simultaneous logins')) {
            return { handled: false };
        }

        const controls = Array.from(document.querySelectorAll('button, a, input[type="button"], input[type="submit"]'));
        const continueControl = controls.find((element) => {
            const label = [
                element.textContent || '',
                element.getAttribute('value') || '',
                element.getAttribute('aria-label') || '',
                element.getAttribute('title') || '',
            ].join(' ').replace(/\s+/g, ' ').trim().toLowerCase();

            return label.includes('continue and logout')
                || label.includes('continue & logout')
                || (label.includes('continue') && label.includes('logout'));
        });

        if (!(continueControl instanceof HTMLElement)) {
            return { handled: false, blocked: true };
        }

        continueControl.click();

        return { handled: true };
    });

    if (result?.handled) {
        pushActivity('DisputeFox asked to end the other session. Continuing with this browser.', 'success');
        setStatus('DisputeFox asked to end the other session. Continuing with this browser...', 'success');
        await delay(1800);
        await syncActiveContext({ loadQueue: false, quiet: true });

        return 'handled';
    }

    if (result?.blocked) {
        pushActivity('DisputeFox is blocking the page with a session prompt. Click Continue and Logout other sessions, then try again.', 'warn');
        setStatus('DisputeFox is blocking the page with a session prompt. Click Continue and Logout other sessions, then try again.', 'warn');

        return 'blocked';
    }

    return null;
}

async function openPulseClientsLane() {
    return await executeOnActiveTab(() => {
        const textFor = (element) => [
            element.textContent || '',
            element.getAttribute('aria-label') || '',
            element.getAttribute('title') || '',
            element.getAttribute('href') || '',
            element.getAttribute('data-href') || '',
        ].join(' ').replace(/\s+/g, ' ').trim().toLowerCase();
        const isVisible = (element) => {
            if (!(element instanceof HTMLElement)) {
                return false;
            }

            const style = window.getComputedStyle(element);

            return style.display !== 'none'
                && style.visibility !== 'hidden'
                && style.opacity !== '0'
                && element.offsetParent !== null;
        };
        const candidates = Array.from(document.querySelectorAll('a, button, [role="button"], [onclick], [data-href]'))
            .filter(isVisible);
        const clientsTrigger = document.querySelector('#clientshrefTab')
            || candidates.find((element) => {
            const label = textFor(element);

            return label === 'clients'
                || label.startsWith('clients ')
                || label.includes('active_clients')
                || label.includes('customer_and_lead_list.jsp')
                || label.includes('/client')
                || label.includes('clientlist')
                || label.includes('client_list')
                || label.includes('clients.jsp');
        });

        if (!clientsTrigger) {
            return { clicked: false };
        }

        const dataHref = clientsTrigger.getAttribute('data-href');

        if (dataHref && dataHref !== '#') {
            const url = new URL(dataHref, window.location.href).toString();
            window.location.href = url;

            return {
                clicked: true,
                label: clientsTrigger.textContent?.trim() || dataHref,
                url,
            };
        }

        clientsTrigger.click();

        return {
            clicked: true,
            label: clientsTrigger.textContent?.trim() || clientsTrigger.getAttribute('href') || 'Clients',
        };
    });
}

async function openPulseVisibleRecord() {
    return await executeOnActiveTab(() => {
        const isVisible = (element) => {
            if (!(element instanceof HTMLElement)) {
                return false;
            }

            const style = window.getComputedStyle(element);

            return style.display !== 'none'
                && style.visibility !== 'hidden'
                && style.opacity !== '0'
                && element.offsetParent !== null;
        };
        const table = document.querySelector('#clientsORleadsDatatable') || document.querySelector('table.dataTable');
        const selected = table?.querySelector('tbody input.client-list-check:checked')?.closest('tr');
        const row = selected || Array.from(table?.querySelectorAll('tbody tr') || [])
            .find((candidate) => isVisible(candidate) && candidate.querySelector('a[href*="customer_dashboard.jsp?id="]'));
        const link = row?.querySelector('a[href*="customer_dashboard.jsp?id="]');

        if (!link) {
            return { opened: false };
        }

        const url = new URL(link.getAttribute('href'), window.location.href).toString();
        const name = row.querySelector('.client-grid-name')?.textContent?.trim()
            || link.textContent?.trim()
            || 'selected DisputeFox record';

        window.location.href = url;

        return {
            opened: true,
            selected: Boolean(selected),
            name,
            url,
        };
    });
}

async function savePulseCredentialSettings() {
    const credentials = readCredentialSettings();
    await chrome.storage.local.set(credentials);
    settingsCache = {
        ...settingsCache,
        ...credentials,
    };

    return await saveSettingsFromForm();
}

async function ensurePulseMigrationReady() {
    const settings = await savePulseCredentialSettings();

    if (!settings.office_token) {
        throw new Error('Save a CreditSoft API key first from Extension options.');
    }

    if (connectionState !== 'ready') {
        const verified = await verifyConnection();

        if (!verified) {
            throw new Error('CreditSoft could not verify this API key yet.');
        }
    }

    if (!featureFlags.client_sync) {
        throw new Error('This CreditSoft API key does not have browser companion client sync enabled.');
    }

    return settings;
}

function finishPulseMigrationMode(message = 'DisputeFox import complete. Companion is back to provider report updates.', tone = 'success') {
    disputeFoxImportOpen = false;
    integrationMenuOpen = false;

    if (elements.credentialPanel) {
        elements.credentialPanel.hidden = true;
    }

    syncForm();
    resetActivity(message, tone);
    setStatus(message, tone);
}

async function advancePulseMigrationFrom(captureOrUrl = null) {
    const url = typeof captureOrUrl === 'string'
        ? captureOrUrl
        : (captureOrUrl?.url || activePageContext.url || '');
    const nextLane = pulseNextMigrationLane(url);

    if (!nextLane) {
        finishPulseMigrationMode();
        return null;
    }

    pushActivity(`Moving to DisputeFox ${nextLane.label}.`, 'info');
    setStatus(`Moving to DisputeFox ${nextLane.label}...`, 'info');
    await navigateActiveTab(nextLane.url);
    await delay(1800);
    await syncActiveContext({ loadQueue: false, quiet: true });

    return nextLane;
}

async function importPulseMigrationLane(lane, settings) {
    await syncActiveContext({ loadQueue: false, quiet: true });

    if (isPulseLoginUrl(activePageContext.url)) {
        pushActivity('DisputeFox is on the login screen. Log in, then press the import action again.', 'warn');
        setStatus('DisputeFox is on the login screen. Log in, then press the import action again.', 'warn');

        return { blocked: true, imported: false };
    }

    const sessionPrompt = await resolvePulseSessionPrompt();

    if (sessionPrompt === 'blocked') {
        return { blocked: true, imported: false };
    }

    await syncActiveContext({ loadQueue: false, quiet: true });
    pushActivity(`Reading DisputeFox ${lane.label}.`, 'info');
    setStatus(`Reading DisputeFox ${lane.label}...`, 'info');

    const expanded = await expandPulseListRows().catch(() => ({ expanded: false }));

    if (expanded?.expanded) {
        pushActivity(`Expanded DisputeFox ${lane.label} list to ${expanded.label || expanded.value}.`, 'info');
        await delay(2200);
    }

    const capture = await captureActiveTab();

    if (captureLooksLikeAutomationDiscovery(capture)) {
        const result = await syncAutomationDiscovery(capture, settings);
        const count = result?.data?.count || result?.data?.discoveries?.length || 0;

        return {
            blocked: false,
            imported: true,
            summary: count > 0 ? `${lane.label}: ${count} workflows` : `${lane.label}: workflows saved`,
        };
    }

    const rowCount = capture.structured_customer?.list_records?.length || 0;

    if (capture.structured_customer?.page_kind === 'record-list' && rowCount > 0) {
        const result = await syncDisputeFoxRecordList(capture, { advance: false });
        const data = result?.data || {};
        const saved = Number(data.created || 0)
            + Number(data.updated || 0)
            + Number(data.payments_created || 0)
            + Number(data.payments_updated || 0)
            + Number(data.captures_created || 0);

        return {
            blocked: false,
            imported: true,
            summary: `${lane.label}: ${rowCount} rows${saved > 0 ? `/${saved} saved` : ''}`,
        };
    }

    const message = rowCount > 0
        ? `DisputeFox ${lane.label} showed ${rowCount} rows, but the importer could not classify this table yet.`
        : `DisputeFox ${lane.label} loaded, but no importable rows were visible.`;

    pushActivity(message, 'warn');
    setStatus(message, 'warn');

    return { blocked: false, imported: false };
}

async function runPulseMigration(lanes = PULSE_MIGRATION_LANES, title = 'list import') {
    disputeFoxImportOpen = true;
    integrationMenuOpen = false;
    syncForm();

    const settings = await ensurePulseMigrationReady();
    let importedAny = false;
    const importedSummaries = [];

    for (const lane of lanes) {
        await syncActiveContext({ loadQueue: false, quiet: true });

        if (pulseMigrationLaneForUrl(activePageContext.url)?.key !== lane.key) {
            pushActivity(`Opening DisputeFox ${lane.label}.`, 'info');
            setStatus(`Opening DisputeFox ${lane.label}...`, 'info');
            await navigateActiveTab(lane.url);
            await delay(2200);
            await syncActiveContext({ loadQueue: false, quiet: true });
        }

        const result = await importPulseMigrationLane(lane, settings);

        if (result.blocked) {
            return;
        }

        importedAny = importedAny || result.imported;

        if (result.imported && result.summary) {
            importedSummaries.push(result.summary);
        }
    }

    finishPulseMigrationMode(
        importedAny
            ? `DisputeFox ${title} complete. ${importedSummaries.join(' | ')}. Companion is back to provider report updates.`
            : `DisputeFox ${title} finished, but no visible rows were imported. Check the active filters and try again.`,
        importedAny ? 'success' : 'warn',
    );
}

function pulseRecordValue(record, labels = []) {
    const values = record?.values || {};
    const entries = Object.entries(values);

    for (const label of labels) {
        const found = entries.find(([key]) => key.toLowerCase() === label.toLowerCase());

        if (found && toText(found[1]).trim() !== '') {
            return toText(found[1]).trim();
        }
    }

    return '';
}

function pulseProfileTargetsFromCapture(capture) {
    const records = Array.isArray(capture?.structured_customer?.list_records)
        ? capture.structured_customer.list_records
        : [];
    const seen = new Set();

    return records
        .map((record, index) => {
            const url = toText(record?.profile_url).trim();
            const name = pulseRecordValue(record, ['Name', 'Client', 'Client Name'])
                || `DisputeFox profile ${index + 1}`;

            return { url, name };
        })
        .filter((target) => target.url && target.url.includes('customer_dashboard.jsp'))
        .filter((target) => {
            const key = target.url.toLowerCase();

            if (seen.has(key)) {
                return false;
            }

            seen.add(key);

            return true;
        });
}

async function expandPulseListRows() {
    return await executeOnActiveTab(() => {
        const selects = Array.from(document.querySelectorAll('select#lengthDrop, select[name*="length"], select[aria-controls]'));

        for (const select of selects) {
            if (!(select instanceof HTMLSelectElement) || select.options.length < 1) {
                continue;
            }

            const currentValue = Number(select.value) || 0;
            const options = Array.from(select.options)
                .map((option) => ({
                    value: option.value,
                    number: Number(option.value) || 0,
                    label: (option.textContent || '').trim().toLowerCase(),
                    isAll: option.classList.contains('all_client') || (option.textContent || '').toLowerCase().includes('all'),
                }))
                .filter((option) => option.value !== '');
            const target = options.find((option) => option.isAll)
                || options.sort((a, b) => b.number - a.number)[0];

            if (!target || target.number <= currentValue) {
                continue;
            }

            select.value = target.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            select.dispatchEvent(new Event('input', { bubbles: true }));

            return {
                expanded: true,
                value: target.value,
                label: target.label || target.value,
            };
        }

        return { expanded: false };
    });
}

async function capturePulseListForProfiles(lane) {
    const expanded = await expandPulseListRows().catch(() => ({ expanded: false }));

    if (expanded?.expanded) {
        pushActivity(`Expanded DisputeFox ${lane.label} list to ${expanded.label || expanded.value}.`, 'info');
        await delay(2200);
    }

    let capture = null;

    for (let attempt = 0; attempt < 3; attempt += 1) {
        capture = await captureActiveTab();

        if ((capture?.structured_customer?.list_records?.length || 0) > 0) {
            return capture;
        }

        await delay(1200);
    }

    return capture;
}

async function processPulseProfilesForLane(lane, settings, seenUrls) {
    await syncActiveContext({ loadQueue: false, quiet: true });

    if (pulseMigrationLaneForUrl(activePageContext.url)?.key !== lane.key) {
        pushActivity(`Opening DisputeFox ${lane.label} to collect profile links.`, 'info');
        setStatus(`Opening DisputeFox ${lane.label}...`, 'info');
        await navigateActiveTab(lane.url);
        await delay(2400);
        await syncActiveContext({ loadQueue: false, quiet: true });
    }

    const sessionPrompt = await resolvePulseSessionPrompt();

    if (sessionPrompt === 'blocked') {
        return { blocked: true, rows: 0, targets: 0, processed: 0, failed: 0 };
    }

    pushActivity(`Reading DisputeFox ${lane.label} profile links.`, 'info');
    setStatus(`Reading DisputeFox ${lane.label} profile links...`, 'info');
    const listCapture = await capturePulseListForProfiles(lane);
    const rows = listCapture?.structured_customer?.list_records?.length || 0;

    if (rows > 0) {
        await syncDisputeFoxRecordList(listCapture, { advance: false }).catch((error) => {
            pushActivity(error instanceof Error ? error.message.replace(/\bPulse\b/g, 'DisputeFox') : `Could not save DisputeFox ${lane.label} list rows first.`, 'warn');
        });
    }

    const targets = pulseProfileTargetsFromCapture(listCapture)
        .filter((target) => {
            const key = target.url.toLowerCase();

            if (seenUrls.has(key)) {
                return false;
            }

            seenUrls.add(key);

            return true;
        });

    if (targets.length < 1) {
        pushActivity(`No profile links were visible on DisputeFox ${lane.label}.`, 'warn');

        return { blocked: false, rows, targets: 0, processed: 0, failed: 0 };
    }

    let processed = 0;
    let failed = 0;
    const failures = [];
    const cappedTargets = targets.slice(0, PULSE_PROFILE_PROCESS_LIMIT);

    for (const [index, target] of cappedTargets.entries()) {
        pushActivity(`Opening ${lane.label} profile ${index + 1}/${cappedTargets.length}: ${target.name}.`, 'info');
        setStatus(`Importing ${target.name} (${index + 1}/${cappedTargets.length})...`, 'info');
        await navigateActiveTab(target.url);
        await delay(2400);
        await syncActiveContext({ loadQueue: false, quiet: true });

        const profilePrompt = await resolvePulseSessionPrompt();

        if (profilePrompt === 'blocked') {
            return { blocked: true, rows, targets: targets.length, processed, failed };
        }

        try {
            await syncDisputeFoxProfile();
            processed++;
        } catch (error) {
            failed++;
            const message = error instanceof Error ? error.message : `Could not import ${target.name}.`;
            failures.push({
                name: target.name,
                url: target.url,
                message,
            });
            pushActivity(`${target.name}: ${message}`, 'warn');
        }

        await delay(450);
    }

    if (targets.length > cappedTargets.length) {
        pushActivity(`Imported the first ${cappedTargets.length} ${lane.label} profiles. ${targets.length - cappedTargets.length} remain visible for another pass.`, 'warn');
    }

    return {
        blocked: false,
        rows,
        targets: targets.length,
        processed,
        failed,
        failures,
    };
}

async function runPulseProfileProcessing() {
    disputeFoxImportOpen = true;
    integrationMenuOpen = false;
    syncForm();

    const settings = await ensurePulseMigrationReady();
    const seenUrls = new Set();
    const summaries = [];
    const failedProfiles = [];
    const supportingSummaries = [];
    let totalProcessed = 0;
    let totalFailed = 0;

    for (const lane of PULSE_PROFILE_PROCESS_LANES) {
        const result = await processPulseProfilesForLane(lane, settings, seenUrls);

        if (result.blocked) {
            setStatus('DisputeFox profile import paused by a session prompt.', 'warn');
            return;
        }

        totalProcessed += result.processed;
        totalFailed += result.failed;
        failedProfiles.push(...(result.failures || []).map((failure) => ({
            ...failure,
            lane: lane.label,
        })));
        summaries.push(`${lane.label}: ${result.processed}/${result.targets} profiles`);
    }

    for (const lane of PULSE_MIGRATION_LANES.filter((migrationLane) => !PULSE_PROFILE_PROCESS_LANES.some((profileLane) => profileLane.key === migrationLane.key))) {
        await syncActiveContext({ loadQueue: false, quiet: true });

        if (pulseMigrationLaneForUrl(activePageContext.url)?.key !== lane.key) {
            pushActivity(`Opening DisputeFox ${lane.label} for supporting records.`, 'info');
            setStatus(`Opening DisputeFox ${lane.label}...`, 'info');
            await navigateActiveTab(lane.url);
            await delay(2200);
            await syncActiveContext({ loadQueue: false, quiet: true });
        }

        const result = await importPulseMigrationLane(lane, settings);

        if (result.blocked) {
            setStatus('DisputeFox profile import paused by a session prompt.', 'warn');
            return;
        }

        if (result.imported && result.summary) {
            supportingSummaries.push(result.summary);
        }
    }

    const failureSummary = failedProfiles.length > 0
        ? ` Failed: ${failedProfiles
            .slice(0, 5)
            .map((failure) => `${failure.lane} ${failure.name}`)
            .join('; ')}${failedProfiles.length > 5 ? `; plus ${failedProfiles.length - 5} more` : ''}.`
        : '';

    if (failedProfiles.length > 0) {
        pushActivity(
            `DisputeFox profiles needing retry: ${failedProfiles.map((failure) => `${failure.lane} ${failure.name}`).join('; ')}.`,
            'warn',
        );
    }

    const supportingSummary = supportingSummaries.length > 0
        ? ` Supporting lists: ${supportingSummaries.join(' | ')}.`
        : ' Supporting lists checked; no invoice, affiliate, or automation rows were imported.';

    finishPulseMigrationMode(
        totalProcessed > 0
            ? `DisputeFox profile import complete. ${summaries.join(' | ')}. Legacy reports/documents were staged and downloaded where DisputeFox allowed file access.${supportingSummary}${totalFailed > 0 ? ` ${totalFailed} failed.` : ''}${failureSummary}`
            : 'DisputeFox profile import finished, but no profile links were visible to import.',
        totalProcessed > 0 ? 'success' : 'warn',
    );
}

async function handleDisputeFoxGo() {
    disputeFoxImportOpen = true;
    await syncActiveContext({ loadQueue: false, quiet: true });

    if (!isDisputeFoxPulseUrl(activePageContext.url)) {
        await navigateActiveTab(DISPUTEFOX_LOGIN_URL);
        syncForm();
        pushActivity('Opened the DisputeFox login page.', 'info');
        setStatus('Opened the DisputeFox login page. Log in, then choose an import action.', 'info');

        return;
    }

    const sessionPrompt = await resolvePulseSessionPrompt();

    if (sessionPrompt === 'blocked') {
        return;
    }

    const capture = await captureActiveTab();

    if (captureLooksLikeClientProfile(capture)) {
        await syncDisputeFoxProfile();

        return;
    }

    await runPulseProfileProcessing();
}

async function parseJsonResponse(response) {
    const text = await response.text();

    if (!text) {
        return null;
    }

    try {
        return JSON.parse(text);
    } catch {
        return { message: text };
    }
}

function apiErrorMessage(payload, status, fallback) {
    const message = payload?.message;

    if (status === 401) {
        return 'Save a valid CreditSoft API key in Extension options, then try again.';
    }

    if (typeof message === 'string' && message.trim() !== '') {
        return message.trim();
    }

    return fallback;
}

async function ensureWorkerId(existingValue) {
    const existing = toText(existingValue).trim();

    if (existing) {
        return existing;
    }

    const workerId = typeof crypto?.randomUUID === 'function'
        ? crypto.randomUUID()
        : `worker-${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;

    await chrome.storage.local.set({ worker_id: workerId });

    return workerId;
}

async function resolveApiBaseUrl(settings) {
    const candidates = candidateBaseUrls(settings.api_base_url);

    for (const baseUrl of candidates) {
        try {
            const response = await fetch(`${baseUrl}${API_OVERVIEW_PATH}`, {
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${settings.office_token}`,
                    'X-CreditSoft-Token': settings.office_token,
                },
            });
            const parsed = await parseJsonResponse(response);

            if (!response.ok) {
                continue;
            }

            if (parsed?.data?.name) {
                if (settingsCache.api_base_url !== baseUrl) {
                    await chrome.storage.local.set({ api_base_url: baseUrl });
                    settingsCache = {
                        ...settingsCache,
                        api_base_url: baseUrl,
                    };
                }

                return baseUrl;
            }
        } catch {
            // Try the next local lane.
        }
    }

    throw new Error('Could not auto-detect the local CreditSoft API. It tries port 80 first, then 8001.');
}

function escapeHtml(value) {
    return toText(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

elements.openSettings?.addEventListener('click', async () => {
    await chrome.runtime.openOptionsPage();
});

elements.integrationMenuToggle?.addEventListener('click', () => {
    integrationMenuOpen = !integrationMenuOpen;
    syncForm();
    const message = integrationMenuOpen
        ? 'Choose an import source. DisputeFox migration imports legacy CRM data; Update pulls fresh provider reports.'
        : 'Import systems menu closed.';

    resetActivity(message, integrationMenuOpen ? 'info' : 'success');
    setStatus(message, integrationMenuOpen ? 'info' : 'success');
});

elements.openDisputeFoxImport?.addEventListener('click', async () => {
    disputeFoxImportOpen = true;
    integrationMenuOpen = false;
    syncIntegrationMenu();
    syncFeatureVisibility();

    if (elements.credentialPanel) {
        elements.credentialPanel.hidden = false;
    }

    try {
        if (!isDisputeFoxPulseUrl(activePageContext.url)) {
            await navigateActiveTab(DISPUTEFOX_LOGIN_URL);
        }
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Could not open the DisputeFox login page.';
        pushActivity(message, 'error');
        setStatus(message, 'error');

        return;
    }

    const message = 'DisputeFox import is active. Use Import all lists for CRM rows only, or Profile details for client fields and saved files.';
    resetActivity(message, 'info');
    setStatus(message, 'info');
});

elements.credentialToggle?.addEventListener('click', () => {
    disputeFoxImportOpen = true;
    syncFeatureVisibility();

    if (elements.credentialPanel) {
        elements.credentialPanel.hidden = !elements.credentialPanel.hidden;
    }
});

elements.saveDisputeFoxCredentials?.addEventListener('click', async () => {
    const credentials = readCredentialSettings();
    await chrome.storage.local.set(credentials);
    settingsCache = {
        ...settingsCache,
        ...credentials,
    };
    const message = credentials.disputefox_username
        ? 'DisputeFox credentials saved locally.'
        : 'DisputeFox credential fields cleared.';
    pushActivity(message, credentials.disputefox_username ? 'success' : 'warn');
    setStatus(message, credentials.disputefox_username ? 'success' : 'warn');
});

async function runDisputeFoxListImportFromButton(button, lanes, activeLabel, restingLabel) {
    try {
        button.disabled = true;
        button.textContent = 'Importing...';
        await runPulseMigration(lanes, activeLabel);
    } catch (error) {
        const message = error instanceof Error ? error.message.replace(/\bPulse\b/g, 'DisputeFox') : 'Could not import DisputeFox lists.';
        pushActivity(message, 'error');
        setStatus(message, 'error');
    } finally {
        button.textContent = restingLabel;
        syncFeatureVisibility();
    }
}

elements.importDisputeFoxAll?.addEventListener('click', async () => {
    await runDisputeFoxListImportFromButton(elements.importDisputeFoxAll, PULSE_MIGRATION_LANES, 'all lists import', 'Import all lists');
});

elements.importDisputeFoxClients?.addEventListener('click', async () => {
    await runDisputeFoxListImportFromButton(elements.importDisputeFoxClients, PULSE_MIGRATION_LANES.filter((lane) => lane.key === 'clients'), 'Clients import', 'Clients');
});

elements.importDisputeFoxLeads?.addEventListener('click', async () => {
    await runDisputeFoxListImportFromButton(elements.importDisputeFoxLeads, PULSE_MIGRATION_LANES.filter((lane) => lane.key === 'leads'), 'Leads import', 'Leads');
});

elements.importDisputeFoxInvoices?.addEventListener('click', async () => {
    await runDisputeFoxListImportFromButton(elements.importDisputeFoxInvoices, PULSE_MIGRATION_LANES.filter((lane) => lane.key === 'invoices'), 'Invoices import', 'Invoices');
});

elements.importDisputeFoxAffiliates?.addEventListener('click', async () => {
    await runDisputeFoxListImportFromButton(elements.importDisputeFoxAffiliates, PULSE_MIGRATION_LANES.filter((lane) => lane.key === 'affiliates'), 'Affiliates import', 'Affiliates');
});

elements.importDisputeFoxAutomation?.addEventListener('click', async () => {
    await runDisputeFoxListImportFromButton(elements.importDisputeFoxAutomation, PULSE_MIGRATION_LANES.filter((lane) => lane.key === 'automation'), 'Automation import', 'Automation');
});

elements.processPulseProfiles?.addEventListener('click', async () => {
    try {
        elements.processPulseProfiles.disabled = true;
        elements.processPulseProfiles.textContent = 'Importing...';
        await runPulseProfileProcessing();
    } catch (error) {
        const message = error instanceof Error ? error.message.replace(/\bPulse\b/g, 'DisputeFox') : 'Could not import DisputeFox profiles.';
        pushActivity(message, 'error');
        setStatus(message, 'error');
    } finally {
        elements.processPulseProfiles.textContent = 'Profile details';
        syncFeatureVisibility();
    }
});

elements.closeDisputeFoxImport?.addEventListener('click', () => {
    finishPulseMigrationMode('DisputeFox import is off. Companion is back to provider report updates.');
});

elements.syncDisputeFoxProfile?.addEventListener('click', async () => {
    try {
        elements.syncDisputeFoxProfile.disabled = true;
        elements.syncDisputeFoxProfile.textContent = 'Syncing...';
        await syncDisputeFoxProfile();
    } catch (error) {
        pushActivity(error instanceof Error ? error.message : 'Could not sync DisputeFox client data.', 'error');
        setStatus(error instanceof Error ? error.message : 'Could not sync DisputeFox client data.', 'error');
    } finally {
        elements.syncDisputeFoxProfile.textContent = 'Current page';
        syncFeatureVisibility();
    }
});

elements.goCapture?.addEventListener('click', async () => {
    try {
        await syncActiveContext({ loadQueue: false, quiet: true });

        if (isDisputeFoxImportContext()) {
            await handleDisputeFoxGo();

            return;
        }

        startRunner(nextReadyAccount, { forceUpdate: true });
        await continueRunner({ source: 'manual' });
    } catch (error) {
        pushActivity(error instanceof Error ? error.message : 'Could not update or import from this page.', 'error');
        setStatus(error instanceof Error ? error.message : 'Could not update or import from this page.', 'error');
    }
});

loadSettings().catch((error) => {
    pushActivity(error instanceof Error ? error.message : 'Could not load extension settings.', 'error');
    setStatus(error instanceof Error ? error.message : 'Could not load extension settings.', 'error');
});
