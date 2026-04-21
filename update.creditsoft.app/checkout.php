<?php
require __DIR__.'/common.php';

$plan = $_GET['plan'] ?? 'professional';
$billing = $_GET['billing'] ?? 'monthly';
$plans = update_creditsoft_plan_catalog();
$selected = $plans[$plan] ?? $plans['professional'];
$isLifetime = $billing === 'lifetime';
$displayPrice = $isLifetime ? null : ($billing === 'yearly' ? $selected['yearly'] : $selected['monthly']);
$displayListPrice = $isLifetime ? null : ($billing === 'yearly' ? $selected['yearly_list'] : $selected['monthly_list']);
$zelleQuote = update_creditsoft_zelle_quote($plan, $billing);
$zellePrice = $isLifetime ? null : $zelleQuote['zelle_amount'];
$zelleDiscount = $isLifetime ? null : $zelleQuote['discount_amount'];
$intervalLabel = $billing === 'yearly' ? 'year' : 'month';
$saleLabel = $isLifetime ? 'Lifetime interest lane' : ($billing === 'yearly' ? 'Yearly pricing keeps the early-adopter sale, then Zelle takes another 10% off today.' : 'Monthly pricing reflects the early-adopter sale, then Zelle takes another 10% off today.');

$zellePayee = 'Matthew Murphy';
$zelleHandle = 'z@creditsoft.app';
$processingNote = 'Payments can take up to 8 hours to process.';
$memo = 'Use the email you want tied to CreditSoft';
$qrPayload = implode("\n", array_filter([
    'CreditSoft checkout',
    'Payee: '.$zellePayee,
    'Zelle: '.$zelleHandle,
    $zellePrice !== null ? 'Zelle amount: $'.number_format((float) $zellePrice, 2) : null,
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
    <title><?= htmlspecialchars($selected['name'].' Checkout', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="CreditSoft checkout lane with Zelle QR and payment matching details.">
    <link rel="stylesheet" href="<?= htmlspecialchars(update_creditsoft_site_url('assets/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <div class="brand">
                <p class="eyebrow">Checkout lane</p>
                <h1 class="title">Send the payment, then tell us exactly where it is coming from.</h1>
                <p class="lede">Use the Zelle QR code or the details beside it, then submit the email or phone the payment is coming from so we can match it cleanly. <?= htmlspecialchars($processingNote, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <a class="nav-pill" href="https://creditsoft.app/pricing">Back to pricing</a>
        </div>

        <div class="grid">
            <section class="panel">
                <h2><?= htmlspecialchars($selected['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                <?php if ($displayPrice !== null): ?>
                    <span class="price-list">$<?= number_format((float) $displayListPrice, 2) ?>/<?= $intervalLabel ?></span>
                    <div class="price-amount">$<?= number_format((float) $displayPrice, 2) ?><span>/<?= $intervalLabel ?></span></div>
                <?php else: ?>
                    <div class="price-amount" style="font-size:30px;">Lifetime interest</div>
                <?php endif; ?>
                <p><?= htmlspecialchars($saleLabel, ENT_QUOTES, 'UTF-8') ?></p>

                <ul class="summary-list">
                    <?php foreach ($selected['features'] as $feature): ?>
                        <li><?= htmlspecialchars($feature, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="panel">
                <div class="qr-wrap">
                    <div class="qr-stage">
                        <img src="<?= htmlspecialchars($qrImage, ENT_QUOTES, 'UTF-8') ?>" alt="CreditSoft Zelle checkout QR code">
                        <p class="qr-note">Scan this live Zelle QR, then use the email address you want tied to CreditSoft as the memo for faster processing.</p>
                    </div>

                    <div class="detail-grid">
                        <?php if ($displayPrice !== null): ?>
                            <div class="detail-line">
                                <strong>Plan price</strong>
                                <div>$<?= number_format((float) $displayPrice, 2) ?>/<?= $intervalLabel ?></div>
                            </div>
                            <div class="detail-line">
                                <strong>Zelle discount</strong>
                                <div>10% off<?= $zelleDiscount !== null ? ' (-$'.number_format((float) $zelleDiscount, 2).')' : '' ?></div>
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
                        <?php if ($displayPrice !== null): ?>
                            <div class="detail-line">
                                <strong>Zelle total</strong>
                                <div>$<?= number_format((float) $zellePrice, 2) ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="callout warning"><?= htmlspecialchars($processingNote, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>

                <form id="checkoutForm" class="form-grid">
                    <div id="checkoutError" class="status-msg error"></div>
                    <div id="checkoutSuccess" class="status-msg success"></div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="customerEmail">Your email</label>
                            <input type="email" id="customerEmail" name="customer_email" placeholder="you@company.com" required>
                        </div>
                        <div class="form-group">
                            <label for="customerPhone">Your phone</label>
                            <input type="tel" id="customerPhone" name="customer_phone" placeholder="(555) 555-5555" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="paymentSource">Payment came from</label>
                            <input type="text" id="paymentSource" name="payment_source" placeholder="Zelle email or phone used for payment" required>
                        </div>
                        <div class="form-group">
                            <label for="officeName">Office name</label>
                            <input type="text" id="officeName" name="office_name" placeholder="Your office or company name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="checkoutNotes">Anything we should match against?</label>
                        <textarea id="checkoutNotes" name="notes" placeholder="Examples: paid from a spouse's phone, different business name on Zelle, or anything else helpful."></textarea>
                    </div>

                    <div class="callout info">Submit this right after you send the payment so we can match it faster. We’ll use the payer email or phone plus the memo to find the transfer cleanly.</div>

                    <button type="submit" class="btn">I sent the payment</button>
                </form>
            </section>
        </div>
    </div>

    <script>
    const checkoutForm = document.getElementById('checkoutForm');
    const checkoutError = document.getElementById('checkoutError');
    const checkoutSuccess = document.getElementById('checkoutSuccess');

    function showCheckoutMessage(target, message) {
        target.textContent = message;
        target.style.display = 'block';
    }

    function clearCheckoutMessages() {
        checkoutError.style.display = 'none';
        checkoutSuccess.style.display = 'none';
    }

    checkoutForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearCheckoutMessages();

        const payload = {
            plan: <?= json_encode($plan) ?>,
            billing: <?= json_encode($billing) ?>,
            plan_name: <?= json_encode($selected['name']) ?>,
            amount: <?= json_encode($zellePrice ?? $displayPrice) ?>,
            customer_email: document.getElementById('customerEmail').value.trim(),
            customer_phone: document.getElementById('customerPhone').value.trim(),
            payment_source: document.getElementById('paymentSource').value.trim(),
            office_name: document.getElementById('officeName').value.trim(),
            notes: document.getElementById('checkoutNotes').value.trim(),
        };

        if (!payload.customer_email || !payload.customer_phone || !payload.payment_source) {
            showCheckoutMessage(checkoutError, 'Please fill in your email, phone, and the email or phone the payment came from.');
            return;
        }

        const submitButton = checkoutForm.querySelector('.btn');
        submitButton.disabled = true;
        submitButton.textContent = 'Saving your payment notice...';

        try {
            const response = await fetch(<?= json_encode(update_creditsoft_site_url('api/checkout-request.php')) ?>, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Unable to save the checkout request.');
            }

            showCheckoutMessage(checkoutSuccess, 'Payment notice saved. Watch your email and phone. Zelle payments can take up to 8 hours to process.');
            checkoutForm.reset();
        } catch (error) {
            showCheckoutMessage(checkoutError, error.message || 'Something went wrong. Please try again.');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'I sent the payment';
        }
    });
    </script>
</body>
</html>
