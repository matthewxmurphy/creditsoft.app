const DEFAULT_SETTINGS = {
  api_base_url: '',
  migration_operator_token: '',
  source_system: 'DisputeFox',
  operator_note: '',
};

const API_PING_PATH = '/api/v1/migration-operator/ping';
const API_CAPTURE_PATH = '/api/v1/migration-operator/captures';
const API_TEMPLATE_IMPORT_PATH = '/api/v1/migration-operator/letter-templates';
const API_CLIENT_SYNC_PATH = '/api/v1/migration-operator/clients/sync';
const LOCAL_API_CANDIDATES = [
  'http://127.0.0.1:8877',
  'http://localhost:8877',
  'http://127.0.0.1',
  'http://localhost',
  'http://127.0.0.1:8001',
  'http://localhost:8001',
];
const DEFAULT_OPERATOR_PAGE = 'http://127.0.0.1:8877/migration-operator';
const LETTER_PAGE_SETTLE_MS = 1400;
const LETTER_POST_IMPORT_PAUSE_MS = 700;

const elements = {
  pairedDot: document.getElementById('pairedDot'),
  openOpsPage: document.getElementById('openOpsPage'),
  openSettings: document.getElementById('openSettings'),
  connectionState: document.getElementById('connectionState'),
  tokenHint: document.getElementById('tokenHint'),
  activityState: document.getElementById('activityState'),
  activityLog: document.getElementById('activityLog'),
  pageMeta: document.getElementById('pageMeta'),
  fieldSummary: document.getElementById('fieldSummary'),
  operatorNote: document.getElementById('operatorNote'),
  sourceSystem: document.getElementById('sourceSystem'),
  stagePage: document.getElementById('stagePage'),
  syncClient: document.getElementById('syncClient'),
  importLetter: document.getElementById('importLetter'),
  refreshState: document.getElementById('refreshState'),
};

let settingsCache = { ...DEFAULT_SETTINGS };
let connectionState = 'empty';
let currentCapture = null;
let importInFlight = false;
let activityEntries = [];

const actionButtons = [
  'stagePage',
  'syncClient',
  'importLetter',
  'refreshState',
].map((key) => elements[key]).filter(Boolean);

const buttonLabels = new Map(actionButtons.map((button) => [button, button.textContent]));

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

function operatorPageCandidates(value) {
  return candidateBaseUrls(value).map((baseUrl) => `${baseUrl}/migration-operator`);
}

function maskToken(token) {
  if (!token) return 'No OPS key saved yet';
  if (token.length <= 6) return 'OPS key on file: ********';
  return `OPS key on file: ********${token.slice(-4)}`;
}

function setStatus(message, tone = 'info') {
  if (elements.connectionState) {
    elements.connectionState.textContent = message;
    elements.connectionState.dataset.tone = tone;
  }
}

function timestampLabel() {
  return new Date().toLocaleTimeString([], {
    hour: 'numeric',
    minute: '2-digit',
    second: '2-digit',
  });
}

function renderActivity() {
  if (elements.activityState) {
    elements.activityState.textContent = activityEntries[0]?.message || 'No actions yet. Open a page, then choose an action.';
  }

  if (elements.activityLog) {
    elements.activityLog.textContent = activityEntries.length > 0
      ? activityEntries.map((entry) => `[${entry.time}] ${entry.message}`).join('\n')
      : 'Waiting for your first click.';
  }
}

function noteActivity(message) {
  const text = toText(message).trim();
  if (!text) return;

  activityEntries = [
    { time: timestampLabel(), message: text },
    ...activityEntries,
  ].slice(0, 8);

  renderActivity();
}

function setBusy(button, busy, label) {
  for (const actionButton of actionButtons) {
    actionButton.disabled = busy;
    actionButton.textContent = buttonLabels.get(actionButton) || actionButton.textContent;
  }

  if (busy && button) {
    button.disabled = true;
    button.textContent = label || 'Working...';
  }
}

function updatePairingState() {
  const paired = Boolean(settingsCache.migration_operator_token);
  if (elements.tokenHint) elements.tokenHint.textContent = maskToken(settingsCache.migration_operator_token);

  if (elements.pairedDot) {
    elements.pairedDot.classList.remove('ready', 'saved', 'error');
    if (!paired) return;
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

async function readStoredSettings() {
  const stored = await chrome.storage.local.get(DEFAULT_SETTINGS);
  settingsCache = {
    ...DEFAULT_SETTINGS,
    ...stored,
    source_system: toText(settingsCache.source_system || stored.source_system || DEFAULT_SETTINGS.source_system) || DEFAULT_SETTINGS.source_system,
    operator_note: toText(settingsCache.operator_note || stored.operator_note || DEFAULT_SETTINGS.operator_note),
  };
  updatePairingState();
  return settingsCache;
}

function parseJsonResponse(response) {
  return response.text().then((text) => {
    if (!text) return null;
    try {
      return JSON.parse(text);
    } catch {
      return { message: text };
    }
  });
}

function apiErrorMessage(payload, status, fallback) {
  const message = payload?.message;
  if (status === 401 || status === 403) {
    return 'That OPS key was rejected. Open CreditSoft OPS, generate a fresh key, save it locally, then try again.';
  }
  if (typeof message === 'string' && message.trim() !== '') return message.trim();
  return fallback;
}

async function openOperatorPage() {
  await readStoredSettings();
  const [target] = operatorPageCandidates(settingsCache.api_base_url);
  await chrome.tabs.create({ url: target || DEFAULT_OPERATOR_PAGE });
}

async function pause(ms) {
  await new Promise((resolve) => window.setTimeout(resolve, ms));
}

async function ensureSettings() {
  await readStoredSettings();
  if (elements.operatorNote) elements.operatorNote.value = settingsCache.operator_note ?? '';
  if (elements.sourceSystem) elements.sourceSystem.value = settingsCache.source_system ?? 'DisputeFox';
  updatePairingState();
}

async function saveSettingsFromUi() {
  await readStoredSettings();

  const next = {
    api_base_url: normalizeBaseUrl(settingsCache.api_base_url),
    migration_operator_token: settingsCache.migration_operator_token,
    source_system: toText(elements.sourceSystem?.value).trim() || 'DisputeFox',
    operator_note: toText(elements.operatorNote?.value),
  };

  await chrome.storage.local.set(next);
  settingsCache = { ...settingsCache, ...next };
  updatePairingState();
  return settingsCache;
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
        }
        return baseUrl;
      }
    } catch {
      // Try the next private lane.
    }
  }

  throw new Error('Could not auto-detect the OPS API. It tries the 8877 router first, then port 80 and 8001.');
}

async function verifyConnection({ quiet = false } = {}) {
  await readStoredSettings();

  if (!settingsCache.migration_operator_token) {
    connectionState = 'empty';
    updatePairingState();
    if (!quiet) {
      setStatus('No OPS key saved yet. Use the CreditSoft OPS page to generate one.', 'warning');
      noteActivity('No OPS key found yet.');
    }
    return false;
  }

  if (!quiet) {
    setStatus('Checking CreditSoft OPS key...');
    noteActivity('Checking CreditSoft OPS key...');
  }

  try {
    await resolveApiBaseUrl(settingsCache);
    connectionState = 'ready';
    updatePairingState();
    if (!quiet) {
      setStatus('CreditSoft OPS key verified.', 'success');
      noteActivity('CreditSoft OPS key verified.');
    }
    return true;
  } catch (error) {
    connectionState = quiet && settingsCache.migration_operator_token ? 'saved' : 'error';
    updatePairingState();
    if (!quiet) {
      const message = error instanceof Error ? error.message : 'Could not verify the OPS key.';
      setStatus(message, 'error');
      noteActivity(message);
    }
    return false;
  }
}

async function captureActiveTab() {
  const tab = await getActiveTab();

  return captureTab(tab.id);
}

async function getActiveTab() {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

  if (!tab?.id) {
    throw new Error('No active tab found. Open the page you want to capture.');
  }

  return tab;
}

async function captureTab(tabId) {
  if (!tabId) {
    throw new Error('No active tab found. Open the page you want to capture.');
  }

  const [result] = await chrome.scripting.executeScript({
    target: { tabId },
    func: () => {
      const MAX_VALUE_LENGTH = 180;
      const fieldSelectors = [
        'input',
        'textarea',
        'select',
        '[contenteditable="true"]',
      ];

      const short = (value, limit = MAX_VALUE_LENGTH) => {
        const text = String(value ?? '').replace(/\s+/g, ' ').trim();
        return text.length > limit ? `${text.slice(0, limit)}…` : text;
      };

      const normalizeKey = (value) => String(value ?? '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();

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

        if (tag === 'textarea') {
          return String(el.value || '').trim();
        }

        if (el.isContentEditable) {
          return String(el.innerText || el.textContent || '').trim();
        }

        return String(el.value || '').trim();
      };

      const fieldRecords = Array.from(document.querySelectorAll(fieldSelectors.join(','))).map((el) => {
        const tag = el.tagName.toLowerCase();
        const type = tag === 'input' ? (el.getAttribute('type') || 'text').toLowerCase() : tag;

        if (type === 'password' || type === 'hidden' || type === 'file') {
          return null;
        }

        const rect = el.getBoundingClientRect();
        const label = pickLabel(el);
        const rawValue = valueForField(el, tag);
        const textValue = tag === 'select'
          ? rawValue || Array.from(el.options || []).map((option) => short(option.textContent)).filter(Boolean).slice(0, 10).join(', ')
          : short(rawValue);

        return {
          field: {
            tag,
            type,
            name: short(el.getAttribute('name')),
            id: short(el.id),
            label,
            placeholder: short(el.getAttribute('placeholder')),
            autocomplete: short(el.getAttribute('autocomplete')),
            aria_label: short(el.getAttribute('aria-label')),
            value_preview: short(textValue),
            required: el.hasAttribute('required'),
            editable: el.isContentEditable || Boolean(el.getAttribute('contenteditable')),
            x: Math.round(rect.x),
            y: Math.round(rect.y),
            width: Math.round(rect.width),
            height: Math.round(rect.height),
          },
          raw_value: rawValue,
        };
      }).filter(Boolean);

      const fields = fieldRecords.map((entry) => entry.field);

      const aliasMap = {
        first_name: ['first name', 'firstname', 'fname', 'client first name', 'customer first name', 'given name'],
        last_name: ['last name', 'lastname', 'lname', 'client last name', 'customer last name', 'surname', 'family name'],
        full_name: ['full name', 'name', 'client name', 'customer name', 'account name'],
        email: ['email', 'email address', 'primary email', 'client email', 'customer email'],
        secondary_email: ['additional email', 'alternate email', 'secondary email', 'other email'],
        phone: ['phone', 'cell phone', 'mobile phone', 'mobile', 'cell', 'telephone', 'client phone', 'customer phone', 'cell phone only for sms security codes'],
        address_line_1: ['address', 'current address', 'address line 1', 'street address', 'mailing address'],
        address_line_2: ['address line 2', 'apt', 'apartment', 'suite', 'unit'],
        city: ['city', 'current city', 'mailing city'],
        state: ['state', 'current state', 'mailing state'],
        postal_code: ['zip', 'zip code', 'zipcode', 'postal code'],
        date_of_birth: ['date of birth', 'dob', 'birth date', 'birthday'],
        ssn: ['ssn', 'ssns', 'social security', 'social security number'],
        status: ['status', 'progress', 'client status', 'account status'],
        agent: ['agent', 'assigned agent', 'owner', 'assigned to'],
        sales_rep: ['sales rep', 'sales representative', 'salesperson'],
      };

      const matchesAlias = (haystack, aliases) => aliases.some((alias) => haystack === alias || haystack.includes(alias));
      const profileFields = {};
      const rawFields = [];

      for (const entry of fieldRecords) {
        const field = entry.field;
        const value = String(entry.raw_value || '').replace(/\s+/g, ' ').trim();

        if (!value) {
          continue;
        }

        const haystack = normalizeKey([
          field.label,
          field.name,
          field.id,
          field.placeholder,
          field.autocomplete,
          field.aria_label,
        ].filter(Boolean).join(' '));

        let mappedTo = '';

        for (const [target, aliases] of Object.entries(aliasMap)) {
          if (!profileFields[target] && matchesAlias(haystack, aliases)) {
            profileFields[target] = value;
            mappedTo = target;
            break;
          }
        }

        rawFields.push({
          label: field.label || field.placeholder || field.name || field.id || field.type,
          name: field.name,
          id: field.id,
          mapped_to: mappedTo,
          value,
        });
      }

      if (!profileFields.full_name) {
        const titleName = String(document.title || '').split(/\s[-–|]\s/)[0]?.trim() || '';
        if (titleName && titleName.split(/\s+/).length >= 2 && !/account|dashboard|profile|client/i.test(titleName)) {
          profileFields.full_name = titleName;
        }
      }

      if (!profileFields.full_name && (profileFields.first_name || profileFields.last_name)) {
        profileFields.full_name = [profileFields.first_name, profileFields.last_name].filter(Boolean).join(' ');
      }

      const mappedCount = Object.values(profileFields).filter(Boolean).length;
      const structuredCustomer = {
        fields: profileFields,
        raw_fields: rawFields.slice(0, 400),
        confidence: Math.min(1, mappedCount / 8),
      };

      const forms = Array.from(document.querySelectorAll('form')).map((form) => ({
        action: short(form.getAttribute('action')),
        method: short(form.getAttribute('method')) || 'get',
        text: short(form.textContent, 240),
      }));

      const titleCandidates = [
        ...Array.from(document.querySelectorAll('input[name*="letter" i], input[id*="letter" i], input[name*="title" i], input[id*="title" i]'))
          .map((el) => ({
            selector: el.name || el.id || 'input',
            value: short(el.value, 260),
          }))
          .filter((entry) => entry.value),
        ...Array.from(document.querySelectorAll('h1, h2, h3'))
          .map((el) => ({
            selector: el.tagName.toLowerCase(),
            value: short(el.textContent, 260),
          }))
          .filter((entry) => entry.value),
      ].slice(0, 16);

      const bodyCandidates = [
        ...Array.from(document.querySelectorAll('textarea[name*="body" i], textarea[id*="body" i], textarea[name*="letter" i], textarea[id*="letter" i]'))
          .map((el) => ({
            selector: el.name || el.id || 'textarea',
            value: String(el.value || '').trim(),
          }))
          .filter((entry) => entry.value.length > 40),
        ...Array.from(document.querySelectorAll('[contenteditable="true"], .ck-content, [class*="editor" i], [id*="editor" i]'))
          .map((el) => ({
            selector: el.id || el.className || el.tagName.toLowerCase(),
            value: String(el.innerText || el.textContent || '').trim(),
          }))
          .filter((entry) => entry.value.length > 40),
      ].slice(0, 12);

      const categoryField = document.querySelector('#lettercategory, select[name="lettercategory"]');
      const titleField = document.querySelector('#letterTitle, input[name="letterTitle"]');
      const descriptionField = document.querySelector('#letterDescription, textarea[name="letterDescription"]');
      const bodyField = document.querySelector('#letterEditor, textarea[name="letterEditor"], [name="letterEditor"]');
      const richEditor = document.querySelector('.ck-content, [contenteditable="true"]');

      const selectedCategory = categoryField
        ? {
            name: categoryField.getAttribute('name') || '',
            id: categoryField.getAttribute('id') || '',
            value: categoryField.value || '',
            label: short(categoryField.options?.[categoryField.selectedIndex]?.textContent || '', 260),
          }
        : null;

      const selectedTitle = titleField
        ? {
            name: titleField.getAttribute('name') || '',
            id: titleField.getAttribute('id') || '',
            value: short(titleField.value || '', 260),
          }
        : null;

      const selectedDescription = descriptionField
        ? {
            name: descriptionField.getAttribute('name') || '',
            id: descriptionField.getAttribute('id') || '',
            value: String(descriptionField.value || '').trim(),
          }
        : null;

      const selectedBodyField = bodyField || richEditor
        ? {
            name: bodyField?.getAttribute('name') || '',
            id: bodyField?.getAttribute('id') || '',
            tag: (bodyField || richEditor).tagName.toLowerCase(),
            value: String(bodyField?.value || richEditor?.innerText || richEditor?.textContent || '').trim(),
          }
        : null;

      const letterLinks = Array.from(document.querySelectorAll('a[href]'))
        .map((el) => {
          const href = el.getAttribute('href') || '';
          const absoluteUrl = (() => {
            try {
              return new URL(href, window.location.href).toString();
            } catch {
              return '';
            }
          })();

          return {
            href: absoluteUrl,
            text: short(el.textContent, 220),
          };
        })
        .filter((entry) => entry.href && (
          entry.href.includes('add-letter-library.jsp') ||
          entry.href.includes('LetterID=')
        ));

      const uniqueLetterLinks = Array.from(new Map(letterLinks.map((entry) => [entry.href, entry])).values()).slice(0, 400);

      return {
        title: document.title,
        url: window.location.href,
        html: document.documentElement.outerHTML,
        selection_text: window.getSelection()?.toString() ?? '',
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
        field_candidates: fields.slice(0, 240),
        structured_customer: structuredCustomer,
        title_candidates: titleCandidates,
        body_candidates: bodyCandidates,
        selected_category: selectedCategory,
        selected_title: selectedTitle,
        selected_description: selectedDescription,
        selected_body_field: selectedBodyField,
        letter_links: uniqueLetterLinks,
        form_count: forms.length,
        form_candidates: forms.slice(0, 24),
      };
    },
  });

  if (!result?.result) {
    throw new Error('Could not read the active page DOM.');
  }

  return result.result;
}

async function waitForTabComplete(tabId) {
  const current = await chrome.tabs.get(tabId);

  if (current?.status === 'complete') {
    return current;
  }

  return new Promise((resolve, reject) => {
    const timeoutId = window.setTimeout(() => {
      chrome.tabs.onUpdated.removeListener(listener);
      reject(new Error('Timed out waiting for the page to finish loading.'));
    }, 20000);

    const listener = (updatedTabId, changeInfo, tab) => {
      if (updatedTabId !== tabId) {
        return;
      }

      if (changeInfo.status === 'complete') {
        window.clearTimeout(timeoutId);
        chrome.tabs.onUpdated.removeListener(listener);
        resolve(tab);
      }
    };

    chrome.tabs.onUpdated.addListener(listener);
  });
}

function renderCaptureSummary(capture) {
  if (!capture) {
    if (elements.pageMeta) elements.pageMeta.textContent = 'No page captured yet.';
    if (elements.fieldSummary) elements.fieldSummary.textContent = 'Capture a page to inspect detected fields.';
    return;
  }

  if (elements.pageMeta) {
    const letterLinkCount = Array.isArray(capture.letter_links) ? capture.letter_links.length : 0;
    const libraryLine = letterLinkCount > 0 ? `\n${letterLinkCount} letter links detected` : '';
    elements.pageMeta.textContent = `${capture.title || 'Untitled page'}\n${capture.url || ''}\n${capture.form_count || 0} forms detected${libraryLine}`;
  }

  const structured = [];

  if (capture.selected_category?.label || capture.selected_category?.value) {
    structured.push(`Category: ${capture.selected_category.label || capture.selected_category.value}`);
  }

  if (capture.selected_title?.value) {
    structured.push(`Letter title: ${capture.selected_title.value}`);
  }

  if (capture.selected_description?.value) {
    structured.push(`Description: ${String(capture.selected_description.value).slice(0, 220)}`);
  }

  if (capture.selected_body_field?.name || capture.selected_body_field?.id) {
    structured.push(`Body field: ${capture.selected_body_field.name || capture.selected_body_field.id}`);
  }

  const customerFields = capture.structured_customer?.fields || {};
  const customerLines = [
    customerFields.full_name ? `Client: ${customerFields.full_name}` : '',
    customerFields.email ? `Email: ${customerFields.email}` : '',
    customerFields.phone ? `Phone: ${customerFields.phone}` : '',
    customerFields.address_line_1 ? `Address: ${[
      customerFields.address_line_1,
      customerFields.city,
      customerFields.state,
      customerFields.postal_code,
    ].filter(Boolean).join(', ')}` : '',
  ].filter(Boolean);

  if (customerLines.length > 0) {
    structured.push(`Detected client data:\n${customerLines.join('\n')}`);
  }

  const fields = Array.isArray(capture.field_candidates) ? capture.field_candidates : [];
  const sample = fields.slice(0, 8).map((field) => {
    const pieces = [
      field.label || field.name || field.id || field.type || 'field',
      field.placeholder ? `placeholder="${field.placeholder}"` : '',
      field.value_preview ? `value="${field.value_preview}"` : '',
    ].filter(Boolean);
    return `- ${pieces.join(' | ')}`;
  });

  if (elements.fieldSummary) {
    const blocks = [];

    if (structured.length > 0) {
      blocks.push(structured.join('\n'));
    }

    if (sample.length > 0) {
      blocks.push(sample.join('\n'));
    }

    elements.fieldSummary.textContent = blocks.length > 0
      ? blocks.join('\n\n')
      : 'No usable field candidates found on this page.';
  }
}

function buildPayload(capture, captureType, settings) {
  return {
    source_system: toText(settings.source_system).trim() || 'DisputeFox',
    capture_type: captureType,
    page_title: capture.title,
    page_url: capture.url,
    operator_note: toText(settings.operator_note).trim(),
    html: capture.html,
    metadata: {
      browser_name: capture.browser_name,
      user_agent: capture.user_agent,
      captured_at: capture.captured_at,
      selection_text: capture.selection_text,
      field_candidates: capture.field_candidates,
      structured_customer: capture.structured_customer,
      title_candidates: capture.title_candidates,
      body_candidates: capture.body_candidates,
      selected_category: capture.selected_category,
      selected_title: capture.selected_title,
      selected_description: capture.selected_description,
      selected_body_field: capture.selected_body_field,
      form_count: capture.form_count,
      form_candidates: capture.form_candidates,
      capture_mode: captureType,
      origin: 'me-operator',
    },
  };
}

async function postJson(path, payload, settings, fallbackMessage) {
  const apiBaseUrl = await resolveApiBaseUrl(settings);
  const response = await fetch(`${apiBaseUrl}${path}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${settings.migration_operator_token}`,
      'X-CreditSoft-Token': settings.migration_operator_token,
    },
    body: JSON.stringify(payload),
  });

  const parsed = await parseJsonResponse(response);

  if (!response.ok) {
    throw new Error(apiErrorMessage(parsed, response.status, fallbackMessage));
  }

  return parsed;
}

async function captureToCreditSoft({ captureType, importLetterTemplate = false }) {
  const settings = await saveSettingsFromUi();
  if (!settings.migration_operator_token) {
    await openOperatorPage();
    throw new Error('No OPS key saved. CreditSoft OPS was opened so you can generate one.');
  }

  if (connectionState !== 'ready') {
    const verified = await verifyConnection();
    if (!verified) return;
  }

  setStatus('Reading the current page...');
  noteActivity('Reading the current page...');
  const capture = await captureActiveTab();
  currentCapture = capture;
  renderCaptureSummary(capture);

  const payload = buildPayload(capture, captureType, settings);
  noteActivity(importLetterTemplate ? 'Sending the letter to CreditSoft...' : 'Staging the page in CreditSoft...');
  const result = importLetterTemplate
    ? await postJson(API_TEMPLATE_IMPORT_PATH, {
        ...payload,
        capture_type: 'letter_detail',
        label: capture.title,
        operator_notes: payload.operator_note,
        content_template: '',
      }, settings, 'CreditSoft rejected the letter template import.')
    : await postJson(API_CAPTURE_PATH, payload, settings, 'CreditSoft rejected the capture.');

  if (importLetterTemplate) {
    const templateLabel = result?.data?.template?.label || capture.title || 'Imported template';
    setStatus(`Imported ${templateLabel} into CreditSoft.`, 'success');
    noteActivity(`Imported ${templateLabel}.`);
    return;
  }

  const id = result?.data?.id ?? result?.data?.capture?.id ?? 'staged';
  setStatus(`Capture staged (${id}).`, 'success');
  noteActivity(`Capture staged (${id}).`);
}

async function syncClientDataToCreditSoft() {
  const settings = await saveSettingsFromUi();
  if (!settings.migration_operator_token) {
    await openOperatorPage();
    throw new Error('No OPS key saved. CreditSoft OPS was opened so you can generate one.');
  }

  if (connectionState !== 'ready') {
    const verified = await verifyConnection();
    if (!verified) return;
  }

  setStatus('Reading client data from the current page...');
  noteActivity('Reading client data from the current page...');
  const capture = await captureActiveTab();
  currentCapture = capture;
  renderCaptureSummary(capture);

  const client = capture.structured_customer || {};
  const detectedFields = client.fields || {};
  const hasClientData = ['first_name', 'last_name', 'full_name', 'email', 'phone', 'date_of_birth', 'ssn']
    .some((key) => String(detectedFields[key] || '').trim() !== '');

  if (!hasClientData) {
    throw new Error('No client profile fields were detected. Open the DisputeFox account/profile page and try again.');
  }

  const result = await postJson(API_CLIENT_SYNC_PATH, {
    source_system: toText(settings.source_system).trim() || 'DisputeFox',
    page_title: capture.title,
    page_url: capture.url,
    operator_note: toText(settings.operator_note).trim(),
    client,
    metadata: {
      browser_name: capture.browser_name,
      user_agent: capture.user_agent,
      captured_at: capture.captured_at,
      origin: 'me-operator',
      capture_mode: 'client_sync',
    },
  }, settings, 'CreditSoft rejected the client data sync.');

  const syncedClient = result?.data?.client || {};
  const displayName = syncedClient.display_name || detectedFields.full_name || 'client';
  setStatus(`${syncedClient.created ? 'Created' : 'Updated'} ${displayName} in CreditSoft.`, 'success');
  noteActivity(`${syncedClient.created ? 'Created' : 'Updated'} ${displayName}.`);
}

async function importLetterLibraryFromCurrentPage() {
  if (importInFlight) {
    return;
  }

  importInFlight = true;

  try {
    const settings = await saveSettingsFromUi();

    if (!settings.migration_operator_token) {
      await openOperatorPage();
      throw new Error('No OPS key saved. CreditSoft OPS was opened so you can generate one.');
    }

    if (connectionState !== 'ready') {
      const verified = await verifyConnection();
      if (!verified) {
        return;
      }
    }

    const tab = await getActiveTab();
    noteActivity('Scanning the current page...');
    const libraryCapture = await captureTab(tab.id);
    currentCapture = libraryCapture;
    renderCaptureSummary(libraryCapture);

    const letterLinks = Array.isArray(libraryCapture.letter_links)
      ? libraryCapture.letter_links.map((entry) => entry.href).filter(Boolean)
      : [];

    if (letterLinks.length === 0) {
      noteActivity('No library links found. Importing the current page as one letter.');
      await captureToCreditSoft({ captureType: 'letter_detail', importLetterTemplate: true });
      return;
    }

    const startingUrl = libraryCapture.url;
    let importedCount = 0;
    const failures = [];

    noteActivity(`Found ${letterLinks.length} letters. Starting import...`);

    for (let index = 0; index < letterLinks.length; index += 1) {
      const link = letterLinks[index];
      setStatus(`Importing letter ${index + 1} of ${letterLinks.length}...`);
      noteActivity(`Importing letter ${index + 1} of ${letterLinks.length}...`);

      await chrome.tabs.update(tab.id, { url: link });
      await waitForTabComplete(tab.id);
      await pause(LETTER_PAGE_SETTLE_MS);

      try {
        const detailCapture = await captureTab(tab.id);
        currentCapture = detailCapture;
        renderCaptureSummary(detailCapture);

        const payload = buildPayload(detailCapture, 'letter_detail', settings);

        await postJson(API_TEMPLATE_IMPORT_PATH, {
          ...payload,
          capture_type: 'letter_detail',
          label: detailCapture.title,
          operator_notes: payload.operator_note,
          content_template: '',
        }, settings, 'CreditSoft rejected the letter template import.');

        importedCount += 1;
        noteActivity(`Imported ${detailCapture.selected_title?.value || detailCapture.title || `letter ${index + 1}`}.`);
        await pause(LETTER_POST_IMPORT_PAUSE_MS);
      } catch (error) {
        failures.push(link);
        const message = error instanceof Error ? error.message : `Could not import letter ${index + 1}.`;
        setStatus(message, 'error');
        noteActivity(`Letter ${index + 1} failed.`);
        await pause(LETTER_POST_IMPORT_PAUSE_MS);
      }
    }

    if (startingUrl) {
      await chrome.tabs.update(tab.id, { url: startingUrl });
      await waitForTabComplete(tab.id).catch(() => {});
    }

    if (failures.length > 0) {
      setStatus(`Imported ${importedCount} letters. ${failures.length} failed and should be retried from the library page.`, 'error');
      noteActivity(`Imported ${importedCount} letters. ${failures.length} failed.`);
      return;
    }

    setStatus(`Imported ${importedCount} letters into CreditSoft.`, 'success');
    noteActivity(`Imported ${importedCount} letters into CreditSoft.`);
  } finally {
    importInFlight = false;
  }
}

elements.openOpsPage?.addEventListener('click', async () => {
  await openOperatorPage();
});

elements.openSettings?.addEventListener('click', async () => {
  await chrome.runtime.openOptionsPage();
});

elements.refreshState?.addEventListener('click', async () => {
  try {
    setBusy(elements.refreshState, true, 'Checking...');
    noteActivity('Refresh clicked.');
    await saveSettingsFromUi();
    await verifyConnection();
    if (currentCapture) renderCaptureSummary(currentCapture);
  } finally {
    setBusy(null, false);
  }
});

elements.stagePage?.addEventListener('click', async () => {
  try {
    setBusy(elements.stagePage, true, 'Staging...');
    noteActivity('Stage current page clicked.');
    await captureToCreditSoft({ captureType: 'generic' });
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Could not stage the current page.';
    setStatus(message, 'error');
    noteActivity(message);
  } finally {
    setBusy(null, false);
  }
});

elements.syncClient?.addEventListener('click', async () => {
  try {
    setBusy(elements.syncClient, true, 'Syncing...');
    noteActivity('Sync client data clicked.');
    await syncClientDataToCreditSoft();
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Could not sync client data.';
    setStatus(message, 'error');
    noteActivity(message);
  } finally {
    setBusy(null, false);
  }
});

elements.importLetter?.addEventListener('click', async () => {
  try {
    setBusy(elements.importLetter, true, 'Working...');
    noteActivity('Import letter or library clicked.');
    await importLetterLibraryFromCurrentPage();
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Could not import the letter template.';
    setStatus(message, 'error');
    noteActivity(message);
  } finally {
    setBusy(null, false);
  }
});

ensureSettings()
  .then(() => {
    renderActivity();
    noteActivity('CreditSoft OPS ready.');
    if (settingsCache.migration_operator_token) {
      verifyConnection({ quiet: true }).catch(() => {});
    }
  })
  .catch((error) => {
    const message = error instanceof Error ? error.message : 'Could not load CreditSoft OPS settings.';
    setStatus(message, 'error');
    noteActivity(message);
  });

chrome.storage.onChanged?.addListener((changes, areaName) => {
  if (areaName !== 'local') {
    return;
  }

  const relevantKeys = ['api_base_url', 'migration_operator_token', 'source_system', 'operator_note'];
  const touched = relevantKeys.some((key) => Object.prototype.hasOwnProperty.call(changes, key));

  if (!touched) {
    return;
  }

  settingsCache = {
    ...settingsCache,
    ...Object.fromEntries(Object.entries(changes).map(([key, value]) => [key, value?.newValue])),
  };

  updatePairingState();
  noteActivity('OPS settings updated.');
});
