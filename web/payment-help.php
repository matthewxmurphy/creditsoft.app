<?php
$page_title = "I Paid But Haven't Gotten My License";
$page_description = 'Look up a CreditSoft Zelle or Cash App payment before opening a support ticket.';
require __DIR__ . '/header.php';
?>
<style>
    .payment-help-shell { max-width:1180px; margin:0 auto; padding:38px 20px 70px; }
    .payment-help-hero { display:grid; grid-template-columns:minmax(0, 1fr) minmax(320px, .72fr); gap:24px; align-items:stretch; margin-bottom:24px; }
    .payment-help-card { background:rgba(255,255,255,.95); border:1px solid rgba(120,113,108,.16); border-radius:28px; padding:28px; box-shadow:0 24px 56px rgba(15,23,42,.08); }
    .payment-help-kicker { display:inline-flex; padding:8px 13px; border-radius:999px; background:#fff7ed; color:#9a3412; border:1px solid #fdba74; font-size:12px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; margin-bottom:16px; }
    .payment-help-card h1 { font-size:clamp(2.1rem,5vw,4.4rem); line-height:.95; margin:0 0 16px; }
    .payment-help-card h2 { font-size:28px; line-height:1.05; margin:0 0 12px; }
    .payment-help-card p { color:var(--gray); margin:0 0 16px; }
    .payment-help-list { display:grid; gap:12px; margin:20px 0 0; padding:0; list-style:none; }
    .payment-help-list li { background:#f8fafc; border:1px solid var(--border); border-radius:18px; padding:14px 16px; color:#334155; }
    .payment-help-list strong { display:block; color:var(--dark); margin-bottom:4px; }
    .faq-grid { display:grid; grid-template-columns:.86fr 1.14fr; gap:24px; align-items:start; }
    .faq-box { display:grid; gap:12px; }
    .faq-item { background:white; border:1px solid rgba(120,113,108,.16); border-radius:20px; padding:18px 20px; }
    .faq-item strong { display:block; font-size:16px; margin-bottom:6px; }
    .faq-item p { margin:0; color:var(--gray); }
    .payment-form { display:grid; gap:14px; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .form-group { display:grid; gap:8px; }
    .form-group label { font-size:12px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; color:var(--gray); }
    .form-group input, .form-group textarea { width:100%; border:1px solid rgba(120,113,108,.18); border-radius:18px; padding:14px 16px; font-size:16px; background:white; }
    .form-group textarea { min-height:108px; resize:vertical; }
    .form-group input[type="file"] { padding:12px; }
    .hidden-field { position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden; }
    .ticket-note { border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; border-radius:20px; padding:16px 18px; line-height:1.55; }
    .lookup-result { display:none; border-radius:22px; padding:18px 20px; border:1px solid #d6d3d1; background:#fafaf9; line-height:1.55; }
    .lookup-result strong { display:block; color:#111827; font-size:18px; margin-bottom:6px; }
    .lookup-result .license-key { display:inline-block; margin:12px 0 8px; padding:10px 12px; border-radius:12px; background:#111827; color:#fff; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-weight:800; letter-spacing:.04em; }
    .lookup-result a { color:#111827; font-weight:800; }
    .lookup-result.success { background:#f0fdf4; border-color:#86efac; color:#166534; }
    .lookup-result.warning { background:#fff7ed; border-color:#fdba74; color:#9a3412; }
    .drop-zone { border:2px dashed #cbd5e1; background:#f8fafc; border-radius:22px; padding:20px; text-align:center; transition:border-color .16s ease, background .16s ease, transform .16s ease; cursor:pointer; }
    .drop-zone strong { display:block; color:#111827; margin-bottom:6px; }
    .drop-zone small { color:var(--gray); }
    .drop-zone.is-dragover { border-color:var(--primary); background:#fffbeb; transform:translateY(-1px); }
    .drop-zone-file { display:none; margin-top:10px; color:#111827; font-weight:800; }
    .form-actions { display:grid; gap:10px; }
    .status-msg { display:none; border-radius:18px; padding:14px 16px; font-size:14px; }
    .status-msg.error { background:#fff1f2; border:1px solid #fda4af; color:#9f1239; }
    .status-msg.success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    .btn-submit { width:100%; border:0; border-radius:999px; background:var(--primary); color:white; padding:16px 20px; font-size:16px; font-weight:800; cursor:pointer; }
    .btn-submit:hover { background:var(--primary-dark); }
    .btn-submit.secondary { display:none; background:#111827; }
    .btn-submit.secondary:hover { background:#374151; }
    @media (max-width:920px) { .payment-help-hero, .faq-grid, .form-row { grid-template-columns:1fr; } }
</style>

<main class="payment-help-shell">
    <section class="payment-help-hero">
        <div class="payment-help-card">
            <div class="payment-help-kicker">Payment FAQ</div>
            <h1>I paid but haven't gotten my license.</h1>
            <p>Before opening a ticket, CreditSoft can look for the payment and either show the license, issue it, or tell you if the payment was short.</p>
            <ul class="payment-help-list">
                <li><strong>Fastest match:</strong> your CreditSoft account email in the Zelle memo.</li>
                <li><strong>If the memo was missing:</strong> enter the name on the payment, amount sent, date, and confirmation or transaction number.</li>
                <li><strong>If lookup cannot solve it:</strong> drag in a screenshot and open a ticket with the same details.</li>
            </ul>
        </div>
        <div class="payment-help-card">
            <h2>For future payments</h2>
            <p>Always include your CreditSoft account email in the Zelle memo. That is the fastest way for the payment checker to match the payment and issue or renew the license automatically.</p>
            <div class="ticket-note">The number your bank shows may be a customer confirmation code, while CreditSoft may also see a separate bank transaction number in the Chase/Zelle email. Either one can help when the rest of the details line up.</div>
        </div>
    </section>

    <section class="faq-grid">
        <div class="faq-box">
            <div class="faq-item">
                <strong>Why did my license not show up?</strong>
                <p>The payment may not have included your account email in the memo, the payer name may be different, the amount may not match a current license price, or the Zelle email has not arrived yet.</p>
            </div>
            <div class="faq-item">
                <strong>What should the memo say?</strong>
                <p>Use the same email you used for CreditSoft. Example: <strong>you@company.com</strong>. Do not use only “CreditSoft”, “license”, or “test” as the memo.</p>
            </div>
            <div class="faq-item">
                <strong>What if someone else paid for me?</strong>
                <p>Use the lookup with that payer's name, Zelle email or phone, amount, and your CreditSoft account email. If it cannot solve it, attach a screenshot and open a ticket.</p>
            </div>
        </div>

        <div class="payment-help-card">
            <h2>Find my payment</h2>
            <p>CreditSoft will try the lookup first. A support ticket only opens if the lookup cannot safely issue or show the license.</p>
            <form id="paymentTicketForm" class="payment-form" enctype="multipart/form-data">
                <div id="ticketError" class="status-msg error"></div>
                <div id="ticketSuccess" class="status-msg success"></div>
                <div id="lookupResult" class="lookup-result"></div>
                <input class="hidden-field" type="text" name="website" tabindex="-1" autocomplete="off">

                <div class="form-row">
                    <div class="form-group">
                        <label for="customerName">Your name</label>
                        <input id="customerName" name="customer_name" type="text" placeholder="Your name">
                    </div>
                    <div class="form-group">
                        <label for="customerEmail">CreditSoft email</label>
                        <input id="customerEmail" name="customer_email" type="email" placeholder="you@company.com" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="customerPhone">Phone</label>
                        <input id="customerPhone" name="customer_phone" type="tel" placeholder="(555) 555-5555">
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount paid</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0" placeholder="89.95">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="paymentDate">Payment date</label>
                        <input id="paymentDate" name="payment_date" type="date">
                    </div>
                    <div class="form-group">
                        <label for="transactionId">Transaction or Confirmation Number</label>
                        <input id="transactionId" name="transaction_id" type="text" placeholder="Bank transaction, confirmation, or reference ID">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="payerName">Who paid?</label>
                        <input id="payerName" name="payer_name" type="text" placeholder="Name shown in Zelle">
                    </div>
                    <div class="form-group">
                        <label for="paymentSource">Zelle email or phone</label>
                        <input id="paymentSource" name="payment_source" type="text" placeholder="Email or phone that sent payment" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="memoUsed">Memo used</label>
                    <input id="memoUsed" name="memo_used" type="text" placeholder="What did the memo say? Leave blank if none.">
                </div>

                <div class="form-group">
                    <label for="paymentScreenshot">Screenshot attachment</label>
                    <div id="paymentDropZone" class="drop-zone" role="button" tabindex="0">
                        <strong>Drag proof here, or click to upload</strong>
                        <small>HEIC, HEIF, JPG, PNG, WebP, GIF, or PDF under 10 MB.</small>
                        <span id="dropZoneFile" class="drop-zone-file"></span>
                    </div>
                    <input id="paymentScreenshot" name="payment_screenshot" type="file" accept=".png,.jpg,.jpeg,.webp,.gif,.heic,.heif,.pdf,image/png,image/jpeg,image/webp,image/gif,image/heic,image/heif,application/pdf" style="display:none;">
                </div>

                <div class="form-group">
                    <label for="notes">Anything else?</label>
                    <textarea id="notes" name="notes" placeholder="Example: my spouse paid, I forgot the memo, the Zelle name is different, or I used a business account."></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit" data-mode="lookup">Find my payment</button>
                    <button type="button" id="openTicketButton" class="btn-submit secondary">Open payment ticket with these details</button>
                </div>
            </form>
        </div>
    </section>
</main>

<script>
const paymentTicketForm = document.getElementById('paymentTicketForm');
const ticketError = document.getElementById('ticketError');
const ticketSuccess = document.getElementById('ticketSuccess');
const lookupResult = document.getElementById('lookupResult');
const openTicketButton = document.getElementById('openTicketButton');
const paymentDropZone = document.getElementById('paymentDropZone');
const paymentScreenshot = document.getElementById('paymentScreenshot');
const dropZoneFile = document.getElementById('dropZoneFile');

function showTicketMessage(target, message) {
    target.textContent = message;
    target.style.display = 'block';
}

function showLookupResult(kind, html) {
    lookupResult.className = `lookup-result ${kind}`;
    lookupResult.innerHTML = html;
    lookupResult.style.display = 'block';
}

function clearTicketMessages() {
    ticketError.style.display = 'none';
    ticketSuccess.style.display = 'none';
    lookupResult.style.display = 'none';
    openTicketButton.style.display = 'none';
}

function paymentFormPayload() {
    const formData = new FormData(paymentTicketForm);
    return {
        customer_name: String(formData.get('customer_name') || '').trim(),
        customer_email: String(formData.get('customer_email') || '').trim(),
        customer_phone: String(formData.get('customer_phone') || '').trim(),
        amount: String(formData.get('amount') || '').trim(),
        payment_date: String(formData.get('payment_date') || '').trim(),
        transaction_id: String(formData.get('transaction_id') || '').trim(),
        payer_name: String(formData.get('payer_name') || '').trim(),
        payment_source: String(formData.get('payment_source') || '').trim(),
        memo_used: String(formData.get('memo_used') || '').trim(),
        notes: String(formData.get('notes') || '').trim(),
    };
}

function money(value) {
    const number = Number(value);
    if (!Number.isFinite(number)) {
        return '-';
    }

    return new Intl.NumberFormat('en-US', {style: 'currency', currency: 'USD'}).format(number);
}

function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[character]));
}

function renderLookupResponse(data) {
    if (data.status === 'license_ready' || data.status === 'license_issued') {
        showLookupResult('success', `
            <strong>${escapeHtml(data.status === 'license_issued' ? 'License issued.' : 'License found.')}</strong>
            <div>${escapeHtml(data.message || 'CreditSoft matched your payment.')}</div>
            <span class="license-key">${escapeHtml(data.license_key || '')}</span>
            ${data.expires_at ? `<div>Expires: ${escapeHtml(data.expires_at)}</div>` : ''}
            ${data.onboarding_url ? `<div><a href="${escapeHtml(data.onboarding_url)}">Finish onboarding</a></div>` : ''}
            ${data.client_portal_url ? `<div><a href="${escapeHtml(data.client_portal_url)}">Open billing history</a></div>` : ''}
        `);
        openTicketButton.style.display = 'none';
        return;
    }

    if (data.status === 'balance_due') {
        const balanceLead = data.requires_main_license
            ? 'Node payment found, but a main CreditSoft license is required.'
            : 'Payment found, but a balance is still due.';
        showLookupResult('warning', `
            <strong>${escapeHtml(balanceLead)}</strong>
            <div>Paid: ${money(data.paid_amount)}. Expected: ${money(data.expected_amount)}. Balance due: ${money(data.balance_due)}.</div>
            <div>If this does not look right, attach proof and open a ticket below.</div>
        `);
        openTicketButton.style.display = 'block';
        return;
    }

    showLookupResult('warning', `
        <strong>Support review needed.</strong>
        <div>${escapeHtml(data.message || 'CreditSoft could not safely match that payment yet.')}</div>
        <div>Attach a screenshot if you have one, then open a ticket with these same details.</div>
    `);
    openTicketButton.style.display = 'block';
}

function setDropZoneFile(file) {
    if (!file) {
        dropZoneFile.textContent = '';
        dropZoneFile.style.display = 'none';
        return;
    }

    dropZoneFile.textContent = file.name;
    dropZoneFile.style.display = 'block';
}

paymentTicketForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearTicketMessages();

    const submitButton = paymentTicketForm.querySelector('.btn-submit');
    submitButton.disabled = true;
    submitButton.textContent = 'Looking up payment...';

    try {
        const response = await fetch('/api/payment-license-lookup.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(paymentFormPayload())
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || data.error || 'Could not look up that payment.');
        }

        renderLookupResponse(data);
    } catch (error) {
        showTicketMessage(ticketError, error.message || 'Something went wrong. Please try again.');
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Find my payment';
    }
});

openTicketButton?.addEventListener('click', async () => {
    clearTicketMessages();
    openTicketButton.disabled = true;
    openTicketButton.textContent = 'Opening ticket...';

    try {
        const response = await fetch('/api/payment-support-ticket.php', {
            method: 'POST',
            body: new FormData(paymentTicketForm)
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Could not open the payment ticket.');
        }

        showTicketMessage(ticketSuccess, `Payment ticket ${data.ticket_number} opened. Watch your email for confirmation.`);
        paymentTicketForm.reset();
        setDropZoneFile(null);
    } catch (error) {
        showTicketMessage(ticketError, error.message || 'Something went wrong. Please try again.');
        openTicketButton.style.display = 'block';
    } finally {
        openTicketButton.disabled = false;
        openTicketButton.textContent = 'Open payment ticket with these details';
    }
});

paymentDropZone?.addEventListener('click', () => paymentScreenshot?.click());
paymentDropZone?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        paymentScreenshot?.click();
    }
});
paymentScreenshot?.addEventListener('change', () => setDropZoneFile(paymentScreenshot.files?.[0] || null));

['dragenter', 'dragover'].forEach((eventName) => {
    paymentDropZone?.addEventListener(eventName, (event) => {
        event.preventDefault();
        paymentDropZone.classList.add('is-dragover');
    });
});

['dragleave', 'drop'].forEach((eventName) => {
    paymentDropZone?.addEventListener(eventName, (event) => {
        event.preventDefault();
        paymentDropZone.classList.remove('is-dragover');
    });
});

paymentDropZone?.addEventListener('drop', (event) => {
    const file = event.dataTransfer?.files?.[0];
    if (!file || !paymentScreenshot) {
        return;
    }

    const transfer = new DataTransfer();
    transfer.items.add(file);
    paymentScreenshot.files = transfer.files;
    setDropZoneFile(file);
});
</script>

<?php require __DIR__ . '/footer.php'; ?>
