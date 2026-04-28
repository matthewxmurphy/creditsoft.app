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
$saleLabel = $isLifetime ? 'Ten-year license interest lane' : ($billing === 'yearly' ? 'Yearly pricing keeps the early-adopter sale, then Zelle takes another 10% off today.' : 'Monthly pricing reflects the early-adopter sale, then Zelle takes another 10% off today.');

$zellePayee = 'Matthew Murphy';
$zelleHandle = 'hello@creditsoft.app';
$cashAppTag = '$creditsoft';
$cashAppUrl = 'https://cash.app/$creditsoft';
$processingNote = 'Zelle and Cash App payments can take up to 8 hours to process.';
$memo = 'Use your checkout number or account email';
$qrPayload = implode("\n", array_filter([
    'CreditSoft checkout',
    'Payee: '.$zellePayee,
    'Zelle: '.$zelleHandle,
    $zellePrice !== null ? 'Zelle amount: $'.number_format((float) $zellePrice, 2) : null,
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
    <title><?= htmlspecialchars($selected['name'].' Checkout', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="Create a CreditSoft checkout account, then pay by Zelle or Cash App with a trackable reference.">
    <link rel="stylesheet" href="<?= htmlspecialchars(update_creditsoft_site_url('assets/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <div class="brand">
                <p class="eyebrow">Checkout lane</p>
                <h1 class="title">Create the checkout first. Pay with a clean reference second.</h1>
                <p class="lede">This creates the customer account reference for the dashboard. After checkout is created, use either Zelle or Cash App and put the checkout number or account email in the memo/note so the license can be matched automatically.</p>
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
                    <div class="price-amount" style="font-size:30px;">Ten-year license interest</div>
                <?php endif; ?>
                <p><?= htmlspecialchars($saleLabel, ENT_QUOTES, 'UTF-8') ?></p>

                <ul class="summary-list">
                    <?php foreach ($selected['features'] as $feature): ?>
                        <li><?= htmlspecialchars($feature, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>

                <div class="callout info" style="margin-top:18px;">Checkout is the account creation step. Payment choices appear after the checkout number is saved.</div>
            </section>

            <section class="panel">
                <h2>Account checkout</h2>
                <p>Use the email that should own the CreditSoft customer dashboard and license. The payment note can be the checkout number or this email.</p>

                <form id="checkoutForm" class="form-grid">
                    <div id="checkoutError" class="status-msg error"></div>
                    <div id="checkoutSuccess" class="status-msg success"></div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="customerEmail">Account email</label>
                            <input type="email" id="customerEmail" name="customer_email" placeholder="you@company.com" required>
                        </div>
                        <div class="form-group">
                            <label for="customerPhone">Phone</label>
                            <input type="tel" id="customerPhone" name="customer_phone" placeholder="(555) 555-5555" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="officeName">Office name</label>
                            <input type="text" id="officeName" name="office_name" placeholder="Your office or company name" required>
                        </div>
                        <div class="form-group">
                            <label for="paymentSource">Expected payer</label>
                            <input type="text" id="paymentSource" name="payment_source" placeholder="Optional Zelle email, phone, or Cash App tag">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="checkoutNotes">Notes</label>
                        <textarea id="checkoutNotes" name="notes" placeholder="Optional: billing contact, different payer name, or anything else useful for matching."></textarea>
                    </div>

                    <button type="submit" class="btn">Create checkout</button>
                </form>

                <section id="paymentOptions" class="payment-options" aria-live="polite">
                    <div class="callout success">
                        <strong>Checkout created:</strong>
                        <span id="checkoutNumber"></span>
                        <div class="small-note">Put <strong id="memoReference"></strong> or <strong id="memoEmail"></strong> in the Zelle memo or Cash App note.</div>
                    </div>

                    <div class="payment-card-grid">
                        <article class="payment-card">
                            <div class="payment-card-head">
                                <span>Zelle</span>
                                <?php if ($zellePrice !== null): ?><strong>$<?= number_format((float) $zellePrice, 2) ?></strong><?php endif; ?>
                            </div>
                            <img src="<?= htmlspecialchars($qrImage, ENT_QUOTES, 'UTF-8') ?>" alt="CreditSoft Zelle checkout QR code">
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
                                    <div class="zelleMemoText"></div>
                                </div>
                            </div>
                        </article>

                        <article class="payment-card">
                            <div class="payment-card-head">
                                <span>Cash App</span>
                                <?php if ($displayPrice !== null): ?><strong>$<?= number_format((float) $displayPrice, 2) ?></strong><?php endif; ?>
                            </div>
                            <img src="<?= htmlspecialchars($cashAppQrImage, ENT_QUOTES, 'UTF-8') ?>" alt="CreditSoft Cash App checkout QR code">
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
                                    <div class="zelleMemoText"></div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="callout warning"><?= htmlspecialchars($processingNote, ENT_QUOTES, 'UTF-8') ?> The license checker matches trusted payment emails against the checkout number, account email, amount, phone, and payer notes.</div>
                    <a id="dashboardLink" class="btn secondary" href="https://www.creditsoft.app/client-portal.php">Open customer dashboard</a>
                </section>
            </section>
        </div>
    </div>

    <script>
    const checkoutForm = document.getElementById('checkoutForm');
    const checkoutError = document.getElementById('checkoutError');
    const checkoutSuccess = document.getElementById('checkoutSuccess');
    const paymentOptions = document.getElementById('paymentOptions');
    const checkoutNumber = document.getElementById('checkoutNumber');
    const memoReference = document.getElementById('memoReference');
    const memoEmail = document.getElementById('memoEmail');
    const dashboardLink = document.getElementById('dashboardLink');

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
            payment_method: 'zelle_or_cash_app',
            customer_email: document.getElementById('customerEmail').value.trim(),
            customer_phone: document.getElementById('customerPhone').value.trim(),
            payment_source: document.getElementById('paymentSource').value.trim(),
            office_name: document.getElementById('officeName').value.trim(),
            notes: document.getElementById('checkoutNotes').value.trim(),
        };

        if (!payload.customer_email || !payload.customer_phone || !payload.office_name) {
            showCheckoutMessage(checkoutError, 'Please fill in the account email, phone, and office name.');
            return;
        }

        const submitButton = checkoutForm.querySelector('.btn');
        submitButton.disabled = true;
        submitButton.textContent = 'Creating checkout...';

        try {
            const response = await fetch(<?= json_encode(update_creditsoft_site_url('api/checkout-request.php')) ?>, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Unable to create the checkout.');
            }

            const reference = data.checkout_number || data.checkout_reference || '';
            const email = data.customer_email || payload.customer_email;
            const memo = reference ? `${reference} or ${email}` : email;

            checkoutNumber.textContent = reference;
            memoReference.textContent = reference;
            memoEmail.textContent = email;
            document.querySelectorAll('.zelleMemoText').forEach((node) => {
                node.textContent = memo;
            });
            if (data.client_portal_url) {
                dashboardLink.href = data.client_portal_url;
            }

            showCheckoutMessage(checkoutSuccess, 'Checkout saved. Use one of the payment options below and include the checkout number or account email in the note.');
            paymentOptions.style.display = 'grid';
            paymentOptions.scrollIntoView({behavior: 'smooth', block: 'start'});
        } catch (error) {
            showCheckoutMessage(checkoutError, error.message || 'Something went wrong. Please try again.');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Create checkout';
        }
    });
    </script>
</body>
</html>
