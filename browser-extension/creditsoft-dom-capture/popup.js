const DEFAULT_SETTINGS = {
    api_base_url: '',
    office_token: '',
    selected_client_cuid: '',
    worker_id: '',
    disputefox_username: '',
    disputefox_password: '',
};

const API_OVERVIEW_PATH = '/api/v1';
const API_ENDPOINT_PATH = '/api/v1/browser-companion/intake';
const API_CLIENT_SYNC_PATH = '/api/v1/browser-companion/client-sync';
const API_CLIENT_DOCUMENT_PATH = '/api/v1/browser-companion/client-document';
const CLIENT_PICKER_PATH = '/api/v1/clients/picker?limit=24';
const DISPUTEFOX_LOGIN_URL = 'https://pulse.disputeprocess.com/jsp/client/login.jsp?cdn/';
const CREDITSOFT_PUBLIC_API_BASE = 'https://www.creditsoft.app';
const LOCAL_API_CANDIDATES = [
    'http://127.0.0.1:8877',
    'http://localhost:8877',
    'http://127.0.0.1',
    'http://localhost',
    CREDITSOFT_PUBLIC_API_BASE,
];

const elements = {
    clientSelect: document.getElementById('clientSelect'),
    refreshClients: document.getElementById('refreshClients'),
    selectedClient: document.getElementById('selectedClient'),
    clientEmpty: document.getElementById('clientEmpty'),
    status: document.getElementById('status'),
    tokenHint: document.getElementById('tokenHint'),
    connectionState: document.getElementById('connectionState'),
    pairedDot: document.getElementById('pairedDot'),
    openSettings: document.getElementById('openSettings'),
    goCapture: document.getElementById('goCapture'),
    integrationMenuToggle: document.getElementById('integrationMenuToggle'),
    integrationMenu: document.getElementById('integrationMenu'),
    openDisputeFoxImport: document.getElementById('openDisputeFoxImport'),
    credentialToggle: document.getElementById('credentialToggle'),
    credentialPanel: document.getElementById('credentialPanel'),
    credentialFeature: document.getElementById('credentialFeature'),
    disputefoxUsername: document.getElementById('disputefoxUsername'),
    disputefoxPassword: document.getElementById('disputefoxPassword'),
    saveDisputeFoxCredentials: document.getElementById('saveDisputeFoxCredentials'),
    syncDisputeFoxProfile: document.getElementById('syncDisputeFoxProfile'),
};

let settingsCache = { ...DEFAULT_SETTINGS };
let clientOptions = [];
let connectionState = 'empty';
let featureFlags = {
    client_sync: false,
    disputefox_credentials: false,
    create_client_if_missing: false,
};
let integrationMenuOpen = false;
let disputeFoxImportOpen = false;

function currentCycleLabel() {
    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        year: 'numeric',
    }).format(new Date()) + ' review';
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

function isDisputeFoxPulseUrl(url = '') {
    const value = toText(url).toLowerCase();

    return value.includes('pulse.disputeprocess.com')
        || value.includes('disputeprocess.com')
        || value.includes('disputefox.com');
}

function captureLooksLikeClientProfile(capture) {
    const profile = capture?.structured_customer || {};
    const pageKind = toText(profile.page_kind).toLowerCase();
    const url = capture?.url || '';
    const fields = profile.fields || {};
    const hasProfile = ['full_name', 'first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'ssn']
        .some((key) => String(fields[key] || '').trim() !== '');

    if (isDisputeFoxPulseUrl(url)) {
        return pageKind === 'profile' && hasProfile;
    }

    return hasProfile;
}

async function navigateActiveTab(url) {
    const targetUrl = toText(url).trim();

    if (!targetUrl) {
        return;
    }

    const [tab] = await chrome.tabs.query({
        active: true,
        currentWindow: true,
    });

    if (tab?.id) {
        await chrome.tabs.update(tab.id, { url: targetUrl });
        return;
    }

    await chrome.tabs.create({ url: targetUrl });
}

function candidateBaseUrls(value) {
    const preferred = normalizeBaseUrl(value);
    const ordered = preferred ? [preferred, ...LOCAL_API_CANDIDATES] : [...LOCAL_API_CANDIDATES];

    return [...new Set(ordered.map((entry) => normalizeBaseUrl(entry)).filter(Boolean))];
}

function setStatus(message, tone = 'info') {
    if (!elements.status) {
        return;
    }

    elements.status.textContent = message;
    elements.status.dataset.tone = tone;
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

function renderSelectedClient() {
    if (!elements.selectedClient || !elements.clientEmpty) {
        return;
    }

    const client = clientOptions.find((entry) => entry.cuid === settingsCache.selected_client_cuid);

    if (!client) {
        elements.selectedClient.hidden = true;
        elements.selectedClient.innerHTML = '';
        elements.clientEmpty.hidden = false;
        elements.clientEmpty.textContent = settingsCache.office_token
            ? 'Choose a client, then press Capture current page.'
            : 'Save a CreditSoft API key, then load clients from CreditSoft.';
        return;
    }

    const latestCycle = client.latest_reporting_cycle?.cycle_label
        ? `<span class="tiny-pill">${escapeHtml(client.latest_reporting_cycle.cycle_label)}</span>`
        : '';
    const email = client.email ? escapeHtml(client.email) : 'No email on file';

    elements.selectedClient.hidden = false;
    elements.clientEmpty.hidden = true;
    elements.selectedClient.innerHTML = `
      <div class="selected-card">
        <div class="selected-top">
          <div class="name">${escapeHtml(client.display_name)}</div>
          ${latestCycle}
        </div>
        <div class="meta">${email}</div>
      </div>
    `;
}

function renderClientOptions() {
    if (!elements.clientSelect) {
        return;
    }

    const previous = settingsCache.selected_client_cuid;
    elements.clientSelect.innerHTML = '';

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = clientOptions.length > 0
        ? 'Choose a client'
        : 'No clients available';
    elements.clientSelect.appendChild(placeholder);

    for (const client of clientOptions) {
        const option = document.createElement('option');
        option.value = client.cuid;
        option.textContent = client.email
            ? `${client.display_name} · ${client.email}`
            : client.display_name;
        elements.clientSelect.appendChild(option);
    }

    const stillExists = clientOptions.some((client) => client.cuid === previous);
    settingsCache.selected_client_cuid = stillExists
        ? previous
        : (clientOptions[0]?.cuid ?? '');

    elements.clientSelect.value = settingsCache.selected_client_cuid;
    renderSelectedClient();
}

function syncForm() {
    syncPairingState();
    renderClientOptions();
    syncIntegrationMenu();
    syncCredentialForm();
    syncFeatureVisibility();
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

    if (elements.credentialFeature) {
        elements.credentialFeature.hidden = !disputeFoxImportOpen;
    }

    if (elements.syncDisputeFoxProfile) {
        elements.syncDisputeFoxProfile.disabled = !enabled || connectionState !== 'ready';
    }
}

function readFormSettings() {
    return {
        api_base_url: normalizeBaseUrl(settingsCache.api_base_url),
        office_token: toText(settingsCache.office_token).trim(),
        selected_client_cuid: toText(elements.clientSelect?.value).trim(),
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

    syncForm();

    if (settingsCache.office_token) {
        await verifyConnection({ quiet: true });

        if (connectionState === 'ready') {
            await loadClients();
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
            const ignoredPulseTable = (table) =>
                /^(DFMessage|DFAllMessage|DFPortalMessage|DFLeadChat|DFClientsDocuments|bureauForFreezeLetter)$/i.test(table.id || '');
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
                const raw = String(value ?? '').trim();
                const candidates = [raw];

                try {
                    const url = new URL(raw, currentUrl);
                    candidates.push(url.searchParams.get('file') || '', url.pathname || '');
                } catch (_) {
                    // Plain file names still work below.
                }

                const name = candidates.filter(Boolean).join(' ').toLowerCase();

                if (/\.(pdf)(?:$|[?#\s])/.test(name)) return 'application/pdf';
                if (/\.(png)(?:$|[?#\s])/.test(name)) return 'image/png';
                if (/\.(jpg|jpeg)(?:$|[?#\s])/.test(name)) return 'image/jpeg';
                if (/\.(gif)(?:$|[?#\s])/.test(name)) return 'image/gif';
                if (/\.(webp)(?:$|[?#\s])/.test(name)) return 'image/webp';
                if (/\.(heic|heif)(?:$|[?#\s])/.test(name)) return 'image/heif';

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
            const pulseDownloadUrl = (value) => {
                try {
                    const url = new URL(value || '', currentUrl);

                    return url;
                } catch (_) {
                    return null;
                }
            };
            const titleFromDocumentUrl = (value) => {
                const url = pulseDownloadUrl(value);
                const docName = url?.searchParams.get('docName') || '';

                return docName ? short(docName, 255) : '';
            };
            const filenameFromDocumentUrl = (value) => {
                const url = pulseDownloadUrl(value);
                const file = url?.searchParams.get('file') || '';

                if (file) {
                    return filenameFromPath(file);
                }

                const filename = filenameFromPath(value);

                return filename === 'document' ? '' : filename;
            };
            const uidFromDocumentUrl = (value) => {
                const filename = filenameFromDocumentUrl(value);

                return filename ? filename.replace(/\.[a-z0-9]+$/i, '') : '';
            };
            const previewPathFromOnclick = (value) => {
                const match = String(value || '').match(/(?:previewDocument|editUploadedDocument)\('([^']+)'/i);

                return match?.[1] || '';
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
                const sourcePreviewPath = sourcePath && !/^\/?document\?/i.test(sourcePath)
                    ? `/static-resources/client_documents/${sourcePath.replace(/^\/+/, '')}`
                    : '';
                const previewUrl = absoluteUrl(doc?.preview_url || sourcePreviewPath);
                const fileName = String(doc?.file_name || filenameFromDocumentUrl(downloadUrl) || filenameFromPath(sourcePath) || '').trim();
                const title = short(doc?.client_document_name_text || doc?.title || titleFromDocumentUrl(downloadUrl) || fileName || 'Client document', 255);
                const downloadParams = pulseDownloadUrl(downloadUrl)?.searchParams;
                const documentCategory = (() => {
                    const rawCategory = String(doc?.category || '')
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '_')
                        .replace(/^_+|_+$/g, '');
                    const text = [rawCategory, title, fileName, doc?.notes]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase();

                    if (
                        downloadParams?.get('isCreditReport') === '1' ||
                        doc?.is_creditReport === '1' ||
                        doc?.is_credit_report === true ||
                        ['credit_report', 'credit_reports', 'credit_report_pdf'].includes(rawCategory) ||
                        ['credit report', '3-bureau', '3 bureau', 'smart credit report', 'smartcredit report'].some((needle) => text.includes(needle))
                    ) {
                        return 'credit_report';
                    }

                    if (
                        ['progress', 'progress_report', 'client_progress'].includes(rawCategory) ||
                        ['progress report', 'client progress'].some((needle) => text.includes(needle))
                    ) {
                        return 'progress_report';
                    }

                    if (
                        ['audit', 'audit_report'].includes(rawCategory) ||
                        ['audit report', 'credit audit'].some((needle) => text.includes(needle))
                    ) {
                        return 'audit_report';
                    }

                    return rawCategory || 'client_documents';
                })();

                if (!title && !downloadUrl && !sourcePath) {
                    return null;
                }

                return {
                    source_system: 'disputefox',
                    source,
                    source_document_uid: short(doc?.client_document_u_id || doc?.source_document_uid || uidFromDocumentUrl(downloadUrl) || '', 255),
                    source_client_uid: short(doc?.client_uid || doc?.source_client_uid || clientUidFromPage(), 255),
                    title,
                    category: documentCategory,
                    file_name: short(fileName || title, 255),
                    mime_type: short(doc?.mime_type || mimeFromFilename(fileName || sourcePath), 255),
                    file_size: Number(doc?.file_size || 0) || 0,
                    uploaded_at_label: short(doc?.client_document_date || doc?.uploaded_at_label || '', 120),
                    download_url: downloadUrl,
                    preview_url: previewUrl,
                    source_path: short(sourcePath, 2048),
                    is_credit_report: downloadParams?.get('isCreditReport') === '1' || doc?.is_creditReport === '1' || doc?.is_credit_report === true,
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
                const clientUid = clientUidFromPage();

                for (const row of Array.from(document.querySelectorAll('#RequiredIdentificationDiv .documents-row1, #clientUploadedDocumentsDiv .documents-row1, #progressCreditReportUploadedDiv .documents-row1')).slice(0, 160)) {
                    const downloadLink = row.querySelector('a[href*="/document"][href*="method=clientDocument"], a[href*="/document"][href*="method=downloadCreditAuditReport"], a.documents-email-link[href*="/document"]');
                    const downloadHref = downloadLink?.getAttribute('href') || '';
                    const downloadUrl = pulseDownloadUrl(downloadHref);
                    const method = downloadUrl?.searchParams.get('method') || '';

                    if (!downloadUrl || !['clientDocument', 'downloadCreditAuditReport'].includes(method)) {
                        continue;
                    }

                    const downloadClientUid = downloadUrl.searchParams.get('clientUid') || downloadUrl.searchParams.get('client_u_id') || '';

                    if (downloadClientUid && clientUid && downloadClientUid !== clientUid) {
                        continue;
                    }

                    const rowTitle = row.querySelector('.documents-row-name-text')?.textContent || titleFromDocumentUrl(downloadHref);
                    const previewPath = Array.from(row.querySelectorAll('[onclick]'))
                        .map((element) => previewPathFromOnclick(element.getAttribute('onclick')))
                        .find(Boolean);
                    const isAudit = method === 'downloadCreditAuditReport' || /audit report/i.test(rowTitle);
                    const isCreditReport = downloadUrl.searchParams.get('isCreditReport') === '1' || row.closest('#progressCreditReportUploadedDiv') && !isAudit;

                    addDocument(normalizePulseDocument({
                        source_document_uid: row.querySelector('[data-docid]')?.getAttribute('data-docid') || uidFromDocumentUrl(downloadHref),
                        source_client_uid: downloadClientUid || clientUid,
                        client_document_full_url: downloadHref,
                        preview_url: previewPath ? `/static-resources/client_documents/${previewPath.replace(/^\/+/, '')}` : '',
                        client_document_name_text: rowTitle,
                        client_document_date: row.querySelector('.documents-row-date i')?.getAttribute('title') || row.querySelector('.documents-row-date')?.textContent || '',
                        category: isAudit ? 'audit_report' : (isCreditReport ? 'credit_report' : 'client_documents'),
                        file_name: filenameFromDocumentUrl(downloadHref),
                        is_credit_report: isCreditReport,
                    }, 'disputefox-document-row'));
                }

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
            const dataTables = Array.from(document.querySelectorAll('#clientsORleadsDatatable, #affiliate_table, #client_invoice_table, table.dataTable, table.datatable, table'))
                .filter(hasUsableRecordRows)
                .filter((table, index, tables) => tables.indexOf(table) === index)
                .slice(0, 16);

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
    }

    const mappedCount = Object.values(mergedFields).filter(Boolean).length;
    const pageKind = pageKinds.includes('profile')
        ? 'profile'
        : (pageKinds.includes('record-list') ? 'record-list' : 'page');

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
    };
}

async function fetchClientOptions(settings) {
    const apiBaseUrl = await resolveApiBaseUrl(settings);
    const endpoint = `${apiBaseUrl}${CLIENT_PICKER_PATH}`;

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

    return {
        clients: Array.isArray(parsed?.data) ? parsed.data : [],
        features: parsed?.meta?.features || {},
    };
}

async function loadClients() {
    if (!settingsCache.office_token) {
        clientOptions = [];
        connectionState = 'empty';
        syncForm();
        return;
    }

    setStatus('Loading clients from CreditSoft...');

    try {
        const result = await fetchClientOptions(settingsCache);
        clientOptions = result.clients;
        featureFlags = {
            ...featureFlags,
            ...result.features,
        };
        connectionState = 'ready';
        renderClientOptions();
        syncPairingState();
        syncFeatureVisibility();
        setStatus(clientOptions.length > 0 ? 'Client list refreshed.' : 'No clients returned from CreditSoft.', clientOptions.length > 0 ? 'success' : 'warn');
    } catch (error) {
        clientOptions = [];
        featureFlags = {
            client_sync: false,
            disputefox_credentials: false,
            create_client_if_missing: false,
        };
        connectionState = error instanceof Error && error.message.includes('valid CreditSoft API key')
            ? 'error'
            : connectionState;
        syncForm();
        setStatus(error instanceof Error ? error.message : 'Could not load clients.', 'error');
    }
}

async function verifyConnection({ quiet = false } = {}) {
    if (!settingsCache.office_token) {
        connectionState = 'empty';
        syncPairingState();
        return false;
    }

    if (!quiet) {
        setStatus('Checking CreditSoft API key...');
    }

    try {
        await resolveApiBaseUrl(settingsCache);
        connectionState = 'ready';
        syncPairingState();

        if (!quiet) {
            setStatus('CreditSoft API key verified.', 'success');
        }

        return true;
    } catch (error) {
        connectionState = 'error';
        clientOptions = [];
        renderClientOptions();
        syncPairingState();

        if (!quiet) {
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
        client_cuid: settings.selected_client_cuid,
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

function pulseDocumentCandidateUrls(documentRecord) {
    const sourcePath = String(documentRecord?.source_path || '').trim();
    const absoluteSourcePath = (() => {
        if (!sourcePath) {
            return '';
        }

        if (/^https?:\/\//i.test(sourcePath)) {
            return sourcePath;
        }

        if (/^\/?document\?/i.test(sourcePath)) {
            return `https://pulse.disputeprocess.com/${sourcePath.replace(/^\/+/, '')}`;
        }

        return '';
    })();
    const staticSourcePath = sourcePath && !absoluteSourcePath
        ? `https://pulse.disputeprocess.com/static-resources/client_documents/${sourcePath.replace(/^\/+/, '')}`
        : '';
    const candidates = [
        documentRecord?.download_url,
        absoluteSourcePath,
        staticSourcePath,
        documentRecord?.preview_url,
    ];

    return Array.from(new Set(candidates.map((value) => String(value || '').trim()).filter(Boolean)));
}

async function pulseDocumentBlobLooksLikeThumbnail(blob, contentType) {
    const normalizedType = String(contentType || blob?.type || '').toLowerCase();

    if (!normalizedType.startsWith('image/')) {
        return false;
    }

    if ((blob?.size || 0) > 0 && (blob?.size || 0) < 8192) {
        return true;
    }

    if ((blob?.size || 0) < 65536 && typeof createImageBitmap === 'function') {
        try {
            const bitmap = await createImageBitmap(blob);
            const looksTiny = bitmap.width <= 180 && bitmap.height <= 180;
            bitmap.close?.();

            return looksTiny;
        } catch (_) {
            return false;
        }
    }

    return false;
}

function safePulseDocumentFilename(documentRecord, response, sourceUrl = '') {
    const headerName = contentDispositionFilename(response?.headers?.get?.('content-disposition'));
    const fallback = documentRecord?.file_name || documentRecord?.title || 'disputefox-document';
    const fromUrl = (() => {
        try {
            const url = new URL(sourceUrl || response?.url || documentRecord?.download_url || documentRecord?.preview_url || '');
            const file = url.searchParams.get('file') || '';

            const filename = decodeURIComponent(file || url.pathname.split('/').filter(Boolean).pop() || '');

            return filename === 'document' ? '' : filename;
        } catch (_) {
            return '';
        }
    })();
    const candidate = String(headerName || fromUrl || fallback).replace(/[\\/:*?"<>|]+/g, '-').trim();

    return candidate || 'disputefox-document';
}

async function fetchPulseDocumentFile(documentRecord) {
    const candidateUrls = pulseDocumentCandidateUrls(documentRecord);

    if (candidateUrls.length === 0) {
        throw new Error('DisputeFox did not expose a document download URL.');
    }

    let lastError = null;
    let tinyPreviewSeen = false;

    for (const url of candidateUrls) {
        try {
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

            if (candidateUrls.length > 1 && (await pulseDocumentBlobLooksLikeThumbnail(blob, contentType))) {
                tinyPreviewSeen = true;
                continue;
            }

            return {
                blob,
                fileName: safePulseDocumentFilename(documentRecord, response, url),
                mimeType: contentType || blob.type || 'application/octet-stream',
            };
        } catch (error) {
            lastError = error;
        }
    }

    if (tinyPreviewSeen) {
        throw new Error('DisputeFox only returned thumbnail-sized document images. Open the document drawer and try again.');
    }

    throw lastError || new Error('DisputeFox did not return the document file.');
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
        throw new Error(apiErrorMessage(parsed, response.status, 'CreditSoft rejected the document upload.'));
    }

    return parsed;
}

async function uploadPulseDocumentsForClient(documents, clientCuid, settings, capture) {
    const records = (Array.isArray(documents) ? documents : [])
        .filter((documentRecord) => documentRecord?.download_url || documentRecord?.preview_url)
        .slice(0, 80);
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
            stats.last_error = error instanceof Error ? error.message : 'Could not import a document.';
        }
    }

    return stats;
}

async function syncDisputeFoxRecordList(capture = null) {
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
        throw new Error('This Pulse page did not expose any list rows to import.');
    }

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
    const message = result?.message || `Imported ${rowCount} DisputeFox list rows.`;

    setStatus(`${message} (${imported} saved/updated).`, 'success');

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

    setStatus('Reading DisputeFox profile fields...');
    const capture = await captureActiveTab();
    const profile = capture.structured_customer || {};
    const fields = profile.fields || {};
    const hasProfile = captureLooksLikeClientProfile(capture);

    if (!hasProfile && profile.page_kind === 'record-list' && (profile.list_records?.length || 0) > 0) {
        await syncDisputeFoxRecordList(capture);

        return;
    }

    if (!hasProfile) {
        const labels = (profile.raw_fields || [])
            .map((field) => field.label)
            .filter(Boolean)
            .slice(0, 6)
            .join(', ');
        const listHint = profile.page_kind === 'record-list'
            ? 'This is a list page. Open one client/lead row first, then sync the profile.'
            : 'Open the Pulse client detail/profile page and try again.';
        throw new Error(labels
            ? `No client profile fields were detected yet. I saw labels like: ${labels}. ${listHint}`
            : `No client profile fields were detected yet. ${listHint}`);
    }

    const result = await postClientSync({
        client_cuid: settings.selected_client_cuid || null,
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
    let documentMessage = '';

    if (client?.cuid && documents.length > 0) {
        setStatus(`Found ${documents.length} document records. Importing files when the source allows it...`, 'info');
        const uploadStats = await uploadPulseDocumentsForClient(documents, client.cuid, settings, capture);

        if (uploadStats.uploaded > 0) {
            documentMessage = ` Imported ${uploadStats.uploaded} file${uploadStats.uploaded === 1 ? '' : 's'}.`;
        } else if (Number(syncedDocuments.total || 0) > 0) {
            documentMessage = ` Staged ${syncedDocuments.total} document record${Number(syncedDocuments.total) === 1 ? '' : 's'}; open the document drawer if file downloads are blocked.`;
        }
    } else if (Number(syncedDocuments.total || 0) > 0) {
        documentMessage = ` Staged ${syncedDocuments.total} document record${Number(syncedDocuments.total) === 1 ? '' : 's'}.`;
    }

    setStatus(`Synced ${displayName} from DisputeFox.${documentMessage}`, 'success');

    if (client?.cuid) {
        settingsCache.selected_client_cuid = client.cuid;
        await chrome.storage.local.set({ selected_client_cuid: client.cuid });
        await loadClients();
    }
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

    throw new Error('Could not auto-detect the local CreditSoft API. It tries the local router first, then localhost port 80.');
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
    syncIntegrationMenu();
    setStatus(
        integrationMenuOpen
            ? 'Choose a source. Report pulls run from the side panel queue; legacy CRM pages can be imported here.'
            : 'Source menu closed.',
        integrationMenuOpen ? 'info' : 'success',
    );
});

elements.openDisputeFoxImport?.addEventListener('click', async () => {
    disputeFoxImportOpen = true;
    integrationMenuOpen = false;
    syncIntegrationMenu();
    syncFeatureVisibility();

    if (elements.credentialPanel) {
        elements.credentialPanel.hidden = false;
    }

    const [tab] = await chrome.tabs.query({
        active: true,
        currentWindow: true,
    });

    try {
        if (!isDisputeFoxPulseUrl(tab?.url)) {
            await navigateActiveTab(DISPUTEFOX_LOGIN_URL);
        }
    } catch (error) {
        setStatus(error instanceof Error ? error.message : 'Could not open the DisputeFox login page.', 'error');

        return;
    }

    setStatus('DisputeFox import is open. Use Import current page here, or open the side panel for the full legacy import pass.', 'info');
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
    setStatus(credentials.disputefox_username ? 'DisputeFox credentials saved locally.' : 'DisputeFox credential fields cleared.', credentials.disputefox_username ? 'success' : 'warn');
});

elements.syncDisputeFoxProfile?.addEventListener('click', async () => {
    try {
        elements.syncDisputeFoxProfile.disabled = true;
        elements.syncDisputeFoxProfile.textContent = 'Importing...';
        await syncDisputeFoxProfile();
    } catch (error) {
        setStatus(error instanceof Error ? error.message : 'Could not sync DisputeFox client data.', 'error');
    } finally {
        elements.syncDisputeFoxProfile.textContent = 'Import current page';
        syncFeatureVisibility();
    }
});

elements.refreshClients?.addEventListener('click', async () => {
    await saveSettingsFromForm();
    const verified = await verifyConnection();

    if (verified) {
        await loadClients();
    }
});

elements.clientSelect?.addEventListener('change', async () => {
    await saveSettingsFromForm();
    renderSelectedClient();
});

elements.goCapture?.addEventListener('click', async () => {
    try {
        const settings = await saveSettingsFromForm();

        if (!settings.office_token) {
            throw new Error('Save a CreditSoft API key first from Extension options.');
        }

        if (connectionState !== 'ready') {
            const verified = await verifyConnection();

            if (!verified) {
                return;
            }
        }

        if (!settings.selected_client_cuid) {
            throw new Error('Choose a client before capturing the current page.');
        }

        setStatus('Reading current page for the selected client...');
        const capture = await captureActiveTab();
        const payload = buildPayload(capture, settings);

        setStatus('Sending current page capture to CreditSoft...');
        const result = await postCapture(payload, settings);
        const clientName = result?.data?.client?.display_name ?? 'the client';

        setStatus(`Capture delivered for ${clientName}.`, 'success');
    } catch (error) {
        setStatus(error instanceof Error ? error.message : 'Could not send capture.', 'error');
    }
});

loadSettings().catch((error) => {
    setStatus(error instanceof Error ? error.message : 'Could not load extension settings.', 'error');
});
