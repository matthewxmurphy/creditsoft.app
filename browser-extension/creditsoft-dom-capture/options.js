const DEFAULT_SETTINGS = {
    api_base_url: '',
    office_token: '',
    worker_id: '',
};

const API_OVERVIEW_PATH = '/api/v1';
const CREDITSOFT_PUBLIC_API_BASE = 'https://www.creditsoft.app';
const LOCAL_API_CANDIDATES = [
    'http://127.0.0.1:8877',
    'http://localhost:8877',
    'http://127.0.0.1',
    'http://localhost',
    CREDITSOFT_PUBLIC_API_BASE,
];

const elements = {
    apiBaseUrl: document.getElementById('apiBaseUrl'),
    officeToken: document.getElementById('officeToken'),
    tokenHint: document.getElementById('tokenHint'),
    connectionState: document.getElementById('connectionState'),
    saveSettings: document.getElementById('saveSettings'),
    testConnection: document.getElementById('testConnection'),
    status: document.getElementById('status'),
};

let settingsCache = { ...DEFAULT_SETTINGS };
let connectionState = 'empty';

function toText(value) {
    return typeof value === 'string' ? value : '';
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

function maskToken(token) {
    if (!token) {
        return 'No API key saved yet';
    }

    if (token.length <= 6) {
        return 'Saved API key on file: ********';
    }

    return `Saved API key on file: ********${token.slice(-4)}`;
}

function setStatus(message) {
    if (elements.status) {
        elements.status.textContent = message;
    }
}

function connectionLabel() {
    if (connectionState === 'ready') {
        return 'CreditSoft API key verified';
    }

    if (connectionState === 'error') {
        return 'Saved API key was rejected';
    }

    return settingsCache.office_token
        ? 'CreditSoft API key saved locally'
        : 'Save settings to pair this browser';
}

function syncForm(settings) {
    if (elements.apiBaseUrl) elements.apiBaseUrl.value = settings.api_base_url ?? '';
    if (elements.officeToken) elements.officeToken.value = '';
    if (elements.tokenHint) elements.tokenHint.textContent = maskToken(settings.office_token ?? '');
    if (elements.connectionState) {
        elements.connectionState.textContent = connectionLabel();
    }
}

async function loadSettings() {
    const stored = await chrome.storage.local.get(DEFAULT_SETTINGS);
    settingsCache = {
        ...DEFAULT_SETTINGS,
        ...stored,
    };
    settingsCache.worker_id = await ensureWorkerId(settingsCache.worker_id);

    syncForm(settingsCache);

    if (settingsCache.office_token) {
        await verifyConnection({ quiet: true });
    }
}

function readFormSettings() {
    return {
        api_base_url: normalizeBaseUrl(elements.apiBaseUrl?.value),
        office_token: toText(elements.officeToken?.value).trim(),
        worker_id: settingsCache.worker_id,
    };
}

async function saveSettings({ preserveBlankToken = true } = {}) {
    const next = readFormSettings();

    if (preserveBlankToken && !next.office_token) {
        next.office_token = settingsCache.office_token ?? '';
    }

    await chrome.storage.local.set(next);
    settingsCache = {
        ...settingsCache,
        ...next,
    };

    syncForm(settingsCache);

    return settingsCache;
}

async function testConnection() {
    const settings = await saveSettings();

    if (!settings.office_token) {
        throw new Error('Save a CreditSoft API key first.');
    }

    const apiBaseUrl = await resolveApiBaseUrl(settings);
    const response = await fetch(`${apiBaseUrl}${API_OVERVIEW_PATH}`, {
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

    return parsed?.data?.name || 'Connected to CreditSoft.';
}

async function verifyConnection({ quiet = false } = {}) {
    if (!settingsCache.office_token) {
        connectionState = 'empty';
        syncForm(settingsCache);
        return false;
    }

    if (!quiet) {
        setStatus('Checking CreditSoft API key...');
    }

    try {
        const message = await testConnection();
        connectionState = 'ready';
        syncForm(settingsCache);

        if (!quiet) {
            setStatus(message);
        }

        return true;
    } catch (error) {
        connectionState = 'error';
        syncForm(settingsCache);

        if (!quiet) {
            setStatus(error instanceof Error ? error.message : 'Could not reach CreditSoft.');
        }

        return false;
    }
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
                    syncForm(settingsCache);
                }

                return baseUrl;
            }
        } catch {
            // Try the next local lane.
        }
    }

    throw new Error('Could not auto-detect the local CreditSoft API. It tries the local router first, then localhost port 80.');
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
        return 'That API key was rejected. Save a valid CreditSoft API key and test again.';
    }

    if (typeof message === 'string' && message.trim() !== '') {
        return message.trim();
    }

    return fallback;
}

elements.saveSettings?.addEventListener('click', async () => {
    try {
        setStatus('Saving extension settings...');
        await saveSettings();
        connectionState = settingsCache.office_token ? 'saved' : 'empty';
        syncForm(settingsCache);
        setStatus('Extension settings saved.');
    } catch (error) {
        setStatus(error instanceof Error ? error.message : 'Could not save extension settings.');
    }
});

elements.testConnection?.addEventListener('click', async () => {
    await verifyConnection();
});

loadSettings().catch((error) => {
    setStatus(error instanceof Error ? error.message : 'Could not load extension settings.');
});
