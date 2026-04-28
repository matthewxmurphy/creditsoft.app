<?php
require __DIR__.'/common.php';

$zellePayee = 'Matthew Murphy';
$zelleHandle = 'hello@creditsoft.app';
$cashAppTag = '$creditsoft';
$cashAppUrl = 'https://cash.app/$creditsoft';
$processingNote = 'Zelle and Cash App payments can take up to 8 hours to process.';
$plan = (string) ($_GET['plan'] ?? 'enterprise');
$billing = (string) ($_GET['billing'] ?? 'monthly');
$zelleQuote = update_creditsoft_zelle_quote($plan, $billing);
$memo = 'Use your renewal number, license key, or account email';
$qrPayload = implode("\n", array_filter([
    'CreditSoft office renewal',
    'Payee: '.$zellePayee,
    'Zelle: '.$zelleHandle,
    $zelleQuote['zelle_amount_label'] ? 'Zelle amount: '.$zelleQuote['zelle_amount_label'] : null,
    'Memo: '.$memo,
    $processingNote,
]));
$qrImage = update_creditsoft_qr_uri($qrPayload);
$cashAppQrImage = update_creditsoft_generated_qr_uri($cashAppUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Renew CreditSoft</title>
    <meta name="description" content="Create a CreditSoft renewal reference, then pay by Zelle or Cash App with a trackable note.">
    <link rel="stylesheet" href="<?= htmlspecialchars(update_creditsoft_site_url('assets/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <div class="brand">
                <p class="eyebrow">Renewal lane</p>
                <h1 class="title">Create the renewal reference before sending payment.</h1>
                <p class="lede">Use this page when the office license expires, when the intranet needs a replacement key, or when the browser companion needs to be re-enabled. After the renewal notice is saved, pay with Zelle or Cash App and put the renewal number, license key, or account email in the memo/note.</p>
            </div>
            <a class="nav-pill" href="<?= htmlspecialchars(update_creditsoft_site_url(), ENT_QUOTES, 'UTF-8') ?>">Back to update lane</a>
        </div>

        <div class="grid">
            <section class="panel">
                <h2>Renew with Zelle or Cash App</h2>
                <p>Create the renewal notice first so the license checker has a reference. Then send the payment through either lane below.</p>
                <div class="detail-grid" style="margin-top:18px;">
                    <?php if ($zelleQuote['base_amount_label']): ?>
                        <div class="detail-line">
                            <strong>License price</strong>
                            <div><?= htmlspecialchars($zelleQuote['base_amount_label'].'/'.$zelleQuote['interval_label'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="detail-line">
                            <strong>Zelle discount</strong>
                            <div>10% off<?= $zelleQuote['discount_amount_label'] ? ' (-'.htmlspecialchars($zelleQuote['discount_amount_label'], ENT_QUOTES, 'UTF-8').')' : '' ?></div>
                        </div>
                        <div class="detail-line">
                            <strong>Zelle total</strong>
                            <div><?= htmlspecialchars($zelleQuote['zelle_amount_label'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="detail-line">
                        <strong>Need help</strong>
                        <div>hello@creditsoft.app</div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <h2>Renewal notice</h2>
                <form id="renewForm" class="form-grid">
                    <div id="renewError" class="status-msg error"></div>
                    <div id="renewSuccess" class="status-msg success"></div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="officeName">Office name</label>
                            <input type="text" id="officeName" name="office_name" placeholder="Office or company name" required>
                        </div>
                        <div class="form-group">
                            <label for="licenseKey">License key</label>
                            <input type="text" id="licenseKey" name="license_key" placeholder="CSFT-XXXX-XXXX-XXXX">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="payerEmail">Account email</label>
                            <input type="email" id="payerEmail" name="payer_email" placeholder="Email on the CreditSoft account" required>
                        </div>
                        <div class="form-group">
                            <label for="payerPhone">Phone</label>
                            <input type="tel" id="payerPhone" name="payer_phone" placeholder="Phone used for payment" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="renewNotes">Notes</label>
                        <textarea id="renewNotes" name="notes" placeholder="Optional: different business name on the Zelle/Cash App account, office admin contact, or anything else helpful."></textarea>
                    </div>

                    <button type="submit" class="btn">Create renewal notice</button>
                </form>

                <section id="renewPaymentOptions" class="payment-options" aria-live="polite">
                    <div class="callout success">
                        <strong>Renewal notice created:</strong>
                        <span id="renewalNumber"></span>
                        <div class="small-note">Put <strong id="renewMemoReference"></strong> or <strong id="renewMemoEmail"></strong> in the Zelle memo or Cash App note.</div>
                    </div>

                    <div class="payment-card-grid">
                        <article class="payment-card">
                            <div class="payment-card-head">
                                <span>Zelle</span>
                                <?php if ($zelleQuote['zelle_amount_label']): ?><strong><?= htmlspecialchars($zelleQuote['zelle_amount_label'], ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>
                            </div>
                            <img src="<?= htmlspecialchars($qrImage, ENT_QUOTES, 'UTF-8') ?>" alt="CreditSoft renewal Zelle QR code">
                            <div class="detail-grid">
                                <div class="detail-line">
                                    <strong>Payee</strong>
                                    <div><?= htmlspecialchars($zellePayee, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="detail-line">
                                    <strong>Zelle email</strong>
                                    <div><?= htmlspecialchars($zelleHandle, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="detail-line">
                                    <strong>Memo</strong>
                                    <div class="renewMemoText"></div>
                                </div>
                            </div>
                        </article>

                        <article class="payment-card">
                            <div class="payment-card-head">
                                <span>Cash App</span>
                                <?php if ($zelleQuote['base_amount_label']): ?><strong><?= htmlspecialchars($zelleQuote['base_amount_label'], ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>
                            </div>
                            <img src="<?= htmlspecialchars($cashAppQrImage, ENT_QUOTES, 'UTF-8') ?>" alt="CreditSoft renewal Cash App QR code">
                            <div class="detail-grid">
                                <div class="detail-line">
                                    <strong>Cashtag</strong>
                                    <div><?= htmlspecialchars($cashAppTag, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="detail-line">
                                    <strong>Link</strong>
                                    <div><a href="<?= htmlspecialchars($cashAppUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($cashAppUrl, ENT_QUOTES, 'UTF-8') ?></a></div>
                                </div>
                                <div class="detail-line">
                                    <strong>Note</strong>
                                    <div class="renewMemoText"></div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="callout warning"><?= htmlspecialchars($processingNote, ENT_QUOTES, 'UTF-8') ?></div>
                </section>
            </section>
        </div>
    </div>

    <script>
    const renewForm = document.getElementById('renewForm');
    const renewError = document.getElementById('renewError');
    const renewSuccess = document.getElementById('renewSuccess');
    const renewPaymentOptions = document.getElementById('renewPaymentOptions');
    const renewalNumber = document.getElementById('renewalNumber');
    const renewMemoReference = document.getElementById('renewMemoReference');
    const renewMemoEmail = document.getElementById('renewMemoEmail');

    function showRenewMessage(target, message) {
        target.textContent = message;
        target.style.display = 'block';
    }

    function clearRenewMessages() {
        renewError.style.display = 'none';
        renewSuccess.style.display = 'none';
    }

    renewForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearRenewMessages();

        const payload = {
            plan: <?= json_encode($zelleQuote['plan_key']) ?>,
            billing: <?= json_encode($zelleQuote['billing']) ?>,
            plan_name: <?= json_encode($zelleQuote['plan_name']) ?>,
            base_amount: <?= json_encode($zelleQuote['base_amount']) ?>,
            zelle_discount_percent: <?= json_encode($zelleQuote['discount_percent']) ?>,
            zelle_discount_amount: <?= json_encode($zelleQuote['discount_amount']) ?>,
            amount: <?= json_encode($zelleQuote['zelle_amount']) ?>,
            payment_method: 'zelle_or_cash_app',
            office_name: document.getElementById('officeName').value.trim(),
            license_key: document.getElementById('licenseKey').value.trim(),
            payer_email: document.getElementById('payerEmail').value.trim(),
            payer_phone: document.getElementById('payerPhone').value.trim(),
            notes: document.getElementById('renewNotes').value.trim(),
        };

        if (!payload.office_name || !payload.payer_email || !payload.payer_phone) {
            showRenewMessage(renewError, 'Please fill in the office name, account email, and phone.');
            return;
        }

        const submitButton = renewForm.querySelector('.btn');
        submitButton.disabled = true;
        submitButton.textContent = 'Creating renewal notice...';

        try {
            const response = await fetch(<?= json_encode(update_creditsoft_site_url('api/renew-request.php')) ?>, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Unable to save the renewal request.');
            }

            const reference = data.renewal_number || data.renewal_reference || '';
            const email = data.payer_email || payload.payer_email;
            const memo = reference ? `${reference} or ${email}` : email;

            renewalNumber.textContent = reference;
            renewMemoReference.textContent = reference;
            renewMemoEmail.textContent = email;
            document.querySelectorAll('.renewMemoText').forEach((node) => {
                node.textContent = memo;
            });

            showRenewMessage(renewSuccess, 'Renewal notice saved. Use one of the payment options below and include the renewal number, license key, or account email in the note.');
            renewPaymentOptions.style.display = 'grid';
            renewPaymentOptions.scrollIntoView({behavior: 'smooth', block: 'start'});
        } catch (error) {
            showRenewMessage(renewError, error.message || 'Something went wrong. Please try again.');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Create renewal notice';
        }
    });
    </script>
</body>
</html>
