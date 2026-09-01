const DEFAULT_SETTINGS = {
  api_base_url: '',
  migration_operator_token: '',
};

const API_PING_PATH = '/api/v1/migration-operator/ping';
const LOCAL_API_CANDIDATES = [
  'http://127.0.0.1:8877',
  'http://localhost:8877',
  'http://127.0.0.1',
  'http://localhost',
  'http://127.0.0.1:8001',
  'http://localhost:8001',
];

const elements = {
  apiBaseUrl: document.getElementById('apiBaseUrl'),
  migrationToken: document.getElementById('migrationToken'),
  connectionState: document.getElementById('connectionState'),
  openOperatorPage: document.getElementById('openOperatorPage'),
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

  if (!trimmed) return '';
  return trimmed.replace(/\/+$/, '');
}

function candidateBaseUrls(value) {
  const preferred = normalizeBaseUrl(value);
  const ordered = preferred ? [preferred, ...LOCAL_API_CANDIDATES] : [...LOCAL_API_CANDIDATES];

  return [...new Set(ordered.map((entry) => normalizeBaseUrl(entry)).filter(Boolean))];
}

function maskToken(token) {
  if (!token) return 'No OPS key saved yet';
  if (token.length <= 6) return 'OPS key on file: ********';
  return `OPS key on file: ********${token.slice(-4)}`;
}

function setStatus(message) {
  if (elements.status) elements.status.textContent = message;
}

function connectionLabel() {
  if (connectionState === 'ready') return 'OPS API verified';
  if (connectionState === 'error') return 'Saved OPS key was rejected';
  return settingsCache.migration_operator_token
    ? 'OPS key saved locally'
    : 'Save settings to pair this OPS lane';
}

function operatorPageCandidates(value) {
  return candidateBaseUrls(value).map((baseUrl) => `${baseUrl}/migration-operator`);
}

function syncForm(settings) {
  if (elements.apiBaseUrl) elements.apiBaseUrl.value = settings.api_base_url ?? '';
  if (elements.migrationToken) elements.migrationToken.value = '';
  if (elements.connectionState) elements.connectionState.textContent = connectionLabel();
}

async function ensureSettings() {
  const stored = await chrome.storage.local.get(DEFAULT_SETTINGS);
  settingsCache = { ...DEFAULT_SETTINGS, ...stored };
  syncForm(settingsCache);
}

function readFormSettings() {
  return {
    api_base_url: normalizeBaseUrl(elements.apiBaseUrl?.value),
    migration_operator_token: toText(elements.migrationToken?.value).trim(),
  };
}

async function saveSettings({ preserveBlankToken = true } = {}) {
  const next = readFormSettings();

  if (preserveBlankToken && !next.migration_operator_token) {
    next.migration_operator_token = settingsCache.migration_operator_token ?? '';
  }

  await chrome.storage.local.set(next);
  settingsCache = { ...settingsCache, ...next };
  syncForm(settingsCache);

  return settingsCache;
}

async function parseJsonResponse(response) {
  const text = await response.text();

  if (!text) return null;
  try {
    return JSON.parse(text);
  } catch {
    return { message: text };
  }
}

function apiErrorMessage(payload, status, fallback) {
  const message = payload?.message;

  if (status === 401 || status === 403) {
    return 'That OPS key was rejected. Save a valid owner-only key and test again.';
  }

  if (typeof message === 'string' && message.trim() !== '') return message.trim();
  return fallback;
}

async function resolveApiBaseUrl(settings) {
  const candidates = candidateBaseUrls(settings.api_base_url);

  for (const baseUrl of candidates) {
    try {
      const response = await fetch(`${baseUrl}${API_PING_PATH}`, {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${settings.migration_operator_token}`,
          'X-CreditSoft-Token': settings.migration_operator_token,
        },
      });
      const parsed = await parseJsonResponse(response);

      if (!response.ok) continue;

      if (parsed?.data || parsed?.ok) {
        if (settingsCache.api_base_url !== baseUrl) {
          await chrome.storage.local.set({ api_base_url: baseUrl });
          settingsCache = { ...settingsCache, api_base_url: baseUrl };
          syncForm(settingsCache);
        }

        return baseUrl;
      }
    } catch {
      // Try the next private lane.
    }
  }

  throw new Error('Could not auto-detect the OPS API. It tries the 8877 router first, then port 80 and 8001.');
}

async function testPing() {
  const settings = await saveSettings();

  if (!settings.migration_operator_token) {
    throw new Error('Save your CreditSoft OPS key first.');
  }

  const apiBaseUrl = await resolveApiBaseUrl(settings);
  const response = await fetch(`${apiBaseUrl}${API_PING_PATH}`, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${settings.migration_operator_token}`,
      'X-CreditSoft-Token': settings.migration_operator_token,
    },
  });

  const parsed = await parseJsonResponse(response);

  if (!response.ok) {
    throw new Error(apiErrorMessage(parsed, response.status, 'Could not reach the CreditSoft OPS API.'));
  }

  return parsed?.data?.message || 'CreditSoft OPS API ready.';
}

async function verifyConnection({ quiet = false } = {}) {
  if (!settingsCache.migration_operator_token) {
    connectionState = 'empty';
    syncForm(settingsCache);
    return false;
  }

  if (!quiet) setStatus('Checking CreditSoft OPS key...');

  try {
    const message = await testPing();
    connectionState = 'ready';
    syncForm(settingsCache);

    if (!quiet) setStatus(message);
    return true;
  } catch (error) {
    connectionState = 'error';
    syncForm(settingsCache);

    if (!quiet) {
      setStatus(error instanceof Error ? error.message : 'Could not verify the OPS key.');
    }

    return false;
  }
}

elements.saveSettings?.addEventListener('click', async () => {
  try {
    setStatus('Saving OPS settings...');
    await saveSettings();
    connectionState = settingsCache.migration_operator_token ? 'saved' : 'empty';
    syncForm(settingsCache);
    setStatus('OPS settings saved.');
  } catch (error) {
    setStatus(error instanceof Error ? error.message : 'Could not save OPS settings.');
  }
});

elements.testConnection?.addEventListener('click', async () => {
  await verifyConnection();
});

ensureSettings().then(() => {
  if (settingsCache.migration_operator_token) {
    verifyConnection({ quiet: true }).catch(() => {});
  }
}).catch((error) => {
  setStatus(error instanceof Error ? error.message : 'Could not load OPS settings.');
});

elements.openOperatorPage?.addEventListener('click', async () => {
  const [target] = operatorPageCandidates(elements.apiBaseUrl?.value || settingsCache.api_base_url || '');
  await chrome.tabs.create({ url: target || 'http://127.0.0.1:8001/migration-operator' });
});
