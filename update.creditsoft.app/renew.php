<?php
require __DIR__.'/common.php';

$zellePayee = 'Matthew Murphy';
$zelleHandle = 'z@creditsoft.app';
$processingNote = 'Payments can take up to 8 hours to process.';
$plan = (string) ($_GET['plan'] ?? 'enterprise');
$billing = (string) ($_GET['billing'] ?? 'monthly');
$zelleQuote = update_creditsoft_zelle_quote($plan, $billing);
$memo = 'Use the email on your CreditSoft account/license';
$qrPayload = implode("\n", array_filter([
    'CreditSoft office renewal',
    'Payee: '.$zellePayee,
    'Zelle: '.$zelleHandle,
    $zelleQuote['zelle_amount_label'] ? 'Zelle amount: '.$zelleQuote['zelle_amount_label'] : null,
    'Memo: '.$memo,
    $processingNote,
]));
$qrImage = update_creditsoft_qr_uri($qrPayload);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Renew CreditSoft</title>
    <meta name="description" content="CreditSoft office renewal lane with Zelle QR and payment matching details.">
    <link rel="stylesheet" href="<?= htmlspecialchars(update_creditsoft_site_url('assets/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <div class="brand">
                <p class="eyebrow">Renewal lane</p>
                <h1 class="title">Renew the office and get the browser companion back online.</h1>
                <p class="lede">Use this page when the office license expires, when the intranet needs a replacement key, or when the browser companion needs to be re-enabled. <?= htmlspecialchars($processingNote, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <a class="nav-pill" href="<?= htmlspecialchars(update_creditsoft_site_url(), ENT_QUOTES, 'UTF-8') ?>">Back to update lane</a>
        </div>

        <div class="grid">
            <section class="panel">
                <h2>Renew with Zelle</h2>
                <p>Send the renewal through Zelle, use the email address on the CreditSoft account/license as the memo, and then submit the office and payer details right away so we can match the payment to the correct installation.</p>
                <div class="detail-grid" style="margin-top:18px;">
                    <div class="detail-line">
                        <strong>What to include</strong>
                        <div>Office name, payer email or phone, and the license key if you already have it.</div>
                    </div>
                    <div class="detail-line">
                        <strong>What happens next</strong>
                        <div>We match the transfer, update the office license, and the workspace or browser companion comes back online after processing.</div>
                    </div>
                    <div class="detail-line">
                        <strong>Need help</strong>
                        <div>hello@creditsoft.app</div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="qr-wrap">
                    <div class="qr-stage">
                        <img src="<?= htmlspecialchars($qrImage, ENT_QUOTES, 'UTF-8') ?>" alt="CreditSoft renewal QR code">
                        <p class="qr-note">Scan this live Zelle QR, then use the email address on your CreditSoft account/license as the memo for faster processing.</p>
                    </div>
                    <div class="detail-grid">
                        <?php if ($zelleQuote['base_amount_label']): ?>
                            <div class="detail-line">
                                <strong>License price</strong>
                                <div><?= htmlspecialchars($zelleQuote['base_amount_label'].'/'.$zelleQuote['interval_label'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="detail-line">
                                <strong>Zelle discount</strong>
                                <div>10% off<?= $zelleQuote['discount_amount_label'] ? ' (-'.htmlspecialchars($zelleQuote['discount_amount_label'], ENT_QUOTES, 'UTF-8').')' : '' ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="detail-line">
                            <strong>Payee</strong>
                            <div><?= htmlspecialchars($zellePayee, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="detail-line">
                            <strong>Zelle destination</strong>
                            <div><?= htmlspecialchars($zelleHandle, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="detail-line">
                            <strong>Memo</strong>
                            <div><?= htmlspecialchars($memo, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <?php if ($zelleQuote['zelle_amount_label']): ?>
                            <div class="detail-line">
                                <strong>Zelle total</strong>
                                <div><?= htmlspecialchars($zelleQuote['zelle_amount_label'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="callout warning"><?= htmlspecialchars($processingNote, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>

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
                            <label for="payerEmail">Payer email</label>
                            <input type="email" id="payerEmail" name="payer_email" placeholder="Email used for payment" required>
                        </div>
                        <div class="form-group">
                            <label for="payerPhone">Payer phone</label>
                            <input type="tel" id="payerPhone" name="payer_phone" placeholder="Phone used for payment" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="renewNotes">Anything else to match against?</label>
                        <textarea id="renewNotes" name="notes" placeholder="Examples: different business name on the Zelle account, office admin contact, or anything else helpful."></textarea>
                    </div>

                    <div class="callout info">Submit this right after payment so we can match the renewal faster. If the browser companion is down because the office expired, this is the lane that gets it moving again.</div>

                    <button type="submit" class="btn">I sent the renewal payment</button>
                </form>
            </section>
        </div>
    </div>

    <script>
    const renewForm = document.getElementById('renewForm');
    const renewError = document.getElementById('renewError');
    const renewSuccess = document.getElementById('renewSuccess');

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
            office_name: document.getElementById('officeName').value.trim(),
            license_key: document.getElementById('licenseKey').value.trim(),
            payer_email: document.getElementById('payerEmail').value.trim(),
            payer_phone: document.getElementById('payerPhone').value.trim(),
            notes: document.getElementById('renewNotes').value.trim(),
        };

        if (!payload.office_name || !payload.payer_email || !payload.payer_phone) {
            showRenewMessage(renewError, 'Please fill in the office name, payer email, and payer phone.');
            return;
        }

        const submitButton = renewForm.querySelector('.btn');
        submitButton.disabled = true;
        submitButton.textContent = 'Saving your renewal notice...';

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

            showRenewMessage(renewSuccess, 'Renewal notice saved. Payments can take up to 8 hours to process.');
            renewForm.reset();
        } catch (error) {
            showRenewMessage(renewError, error.message || 'Something went wrong. Please try again.');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'I sent the renewal payment';
        }
    });
    </script>
</body>
</html>
