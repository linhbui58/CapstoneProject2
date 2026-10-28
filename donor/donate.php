<?php
$pageTitle  = 'Make a Donation';
$activePage = 'donate';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('donor');
require_once dirname(__DIR__) . '/includes/vnpay.php';
require_once dirname(__DIR__) . '/includes/sepay_pg.php';

$db  = getApplicationDB();
$uid = $user['id'];
$sepayConfigError = '';
try {
    $sepayConfig = getSePayGatewayConfig(false);
} catch (Throwable $exception) {
    error_log('SePay Payment Gateway configuration error on donation page: ' . $exception->getMessage());
    $sepayConfig = [];
    $sepayConfigError = 'SePay bank-transfer payments are not configured correctly. Please contact the administrator.';
}
$sepayReady = isset($sepayConfig['merchant_id'], $sepayConfig['merchant_secret_key'], $sepayConfig['mode']);
$vnpayConfigError = '';
try {
    $vnpayConfig = getVnPayConfig(false);
} catch (Throwable $exception) {
    error_log('VNPay configuration error on donation page: ' . $exception->getMessage());
    $vnpayConfig = [];
    $vnpayConfigError = 'Credit card payments are not configured correctly. Please contact the administrator.';
}
$vnpayReady = isset($vnpayConfig['tmn_code'], $vnpayConfig['hash_secret'], $vnpayConfig['mode']);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$campaign_id = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : null;
$campaign    = null;
if ($campaign_id) {
    $res = $db->query("SELECT * FROM campaigns WHERE id=$campaign_id AND status='active'");
    if ($res && $res->num_rows > 0) $campaign = $res->fetch_assoc();
}
$all_campaigns = $db->query("SELECT id, title FROM campaigns WHERE status='active' ORDER BY title");

?>

<!-- Page header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
    <div>
        <h1 style="font-size:1.3rem;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:10px;">
            <span style="width:36px;height:36px;background:linear-gradient(135deg,#ef4444,#f97316);border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 10px rgba(239,68,68,.3);">
                <i class="bi bi-heart-fill" style="color:white;font-size:.88rem;"></i>
            </span>
            Make a Donation
        </h1>
        <p style="color:#64748b;font-size:.8rem;margin:4px 0 0 46px;">Complete payment through SePay; finance staff will verify it after payment is received</p>
    </div>
    <a href="<?= SITE_URL ?>/donor/campaigns.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:9px;color:#64748b;font-size:.81rem;font-weight:600;background:white;text-decoration:none;transition:all .15s;"
       onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
        <i class="bi bi-arrow-left"></i>Back
    </a>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:22px;align-items:start;">

<!-- ══ MAIN FORM ══════════════════════════════════ -->
<form method="POST" id="donateForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

    <!-- Step 1: Campaign & Amount -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;margin-bottom:16px;">
        <div style="background:linear-gradient(135deg,#1447c0,#0d2157);padding:14px 20px;border-bottom:1px solid #0d2157;display:flex;align-items:center;gap:10px;">
            <div style="width:26px;height:26px;background:rgba(255,255,255,.25);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;color:white;flex-shrink:0;">1</div>
            <div>
                <div style="font-size:.84rem;font-weight:700;color:white;">Donation Details</div>
                <div style="font-size:.71rem;color:rgba(255,255,255,.75);">Choose your campaign and amount</div>
            </div>
        </div>
        <div style="padding:20px;">

            <!-- Campaign select -->
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">Campaign / Program</label>
                <select name="campaign_id" required
                        style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.83rem;color:#0f172a;background:white;outline:none;transition:border-color .15s;"
                        onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'">
                    <option value="0" <?= !$campaign_id ? 'selected' : '' ?>>Where it's needed most</option>
                    <?php if ($all_campaigns): $all_campaigns->data_seek(0); while ($ac=$all_campaigns->fetch_assoc()): ?>
                    <option value="<?= $ac['id'] ?>" <?= ($campaign_id==$ac['id'])?'selected':'' ?>>
                        <?= htmlspecialchars($ac['title']) ?>
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>

            <?php if ($campaign): ?>
            <!-- Campaign progress banner -->
            <?php $pct = $campaign['target_amount']>0 ? min(100,round(($campaign['raised_amount']/$campaign['target_amount'])*100,1)) : 0; ?>
            <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #bbf7d0;border-radius:11px;padding:14px 16px;margin-bottom:16px;">
                <div style="font-size:.72rem;color:#15803d;font-weight:600;margin-bottom:4px;">Selected campaign:</div>
                <div style="font-size:.85rem;font-weight:700;color:#15803d;margin-bottom:8px;"><?= htmlspecialchars($campaign['title']) ?></div>
                <div style="height:5px;background:rgba(0,0,0,.08);border-radius:3px;overflow:hidden;margin-bottom:5px;">
                    <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,#22c55e,#16a34a);border-radius:3px;"></div>
                </div>
                <div style="font-size:.71rem;color:#16a34a;"><?= formatCurrency($campaign['raised_amount']) ?> raised of <?= formatCurrency($campaign['target_amount']) ?> goal (<?= $pct ?>%)</div>
            </div>
            <?php endif; ?>

            <!-- Amount + quick pick -->
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">
                    Donation Amount (VND) <span style="color:#ef4444;">*</span>
                </label>
                <!-- Quick amounts -->
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:10px;">
                    <?php foreach([500000,1000000,2000000,5000000,10000000,20000000] as $amt): ?>
                    <button type="button"
                            onclick="setAmount(<?= $amt ?>)"
                            class="amt-btn"
                            data-val="<?= $amt ?>"
                            style="padding:9px 8px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.79rem;font-weight:700;color:#475569;background:white;cursor:pointer;transition:all .15s;text-align:center;">
                        <?= number_format($amt/1000) ?>K
                    </button>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;transition:border-color .15s;" id="amtWrap" onfocusin="this.style.borderColor='#1447c0'" onfocusout="this.style.borderColor='#e2e8f0'">
                    <span style="padding:0 12px;background:#f8fafc;color:#94a3b8;font-size:.82rem;border-right:1px solid #e2e8f0;height:44px;display:flex;align-items:center;font-weight:600;">VND</span>
                    <input type="number" name="amount" id="amountInput" min="1" required
                           style="flex:1;border:none;outline:none;padding:0 13px;height:44px;font-size:.9rem;font-weight:700;color:#0f172a;background:white;"
                           placeholder="Enter custom amount"
                           value="<?= htmlspecialchars($_POST['amount']??'') ?>"
                           oninput="clearAmtBtns()">
                </div>
            </div>

            <div style="margin-top:18px;">
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:8px;">Select Payment Method</label>
                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;">
                    <?php foreach ([
                        ['bank_transfer', 'SePay VietQR', 'bi-bank', '#1447c0'],
                        ['credit_card', 'Credit Card', 'bi-credit-card', '#475569'],
                    ] as [$method, $label, $icon, $color]): ?>
                    <label class="payment-method-label" style="display:flex;align-items:center;justify-content:center;gap:7px;padding:11px 8px;border:1.5px solid <?= $method === 'bank_transfer' ? '#1447c0' : '#e2e8f0' ?>;border-radius:9px;background:<?= $method === 'bank_transfer' ? '#dbeafe' : 'white' ?>;cursor:pointer;font-size:.77rem;font-weight:700;color:<?= $color ?>;text-align:center;">
                        <input type="radio" name="payment_method" value="<?= $method ?>" <?= $method === 'bank_transfer' ? 'checked' : '' ?> <?= ($method === 'bank_transfer' && !$sepayReady) || ($method === 'credit_card' && !$vnpayReady) ? 'disabled' : '' ?> style="position:absolute;opacity:0;pointer-events:none;">
                        <i class="bi <?= $icon ?>"></i><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php if (!$sepayReady): ?>
                <div role="status" style="margin-top:9px;padding:10px 12px;border-radius:8px;background:#fff7ed;color:#9a3412;font-size:.75rem;">
                    <?= htmlspecialchars($sepayConfigError !== '' ? $sepayConfigError : 'SePay Payment Gateway credentials are not set up yet.', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>
                <?php if (!$vnpayReady): ?>
                <div role="status" style="margin-top:9px;padding:10px 12px;border-radius:8px;background:#fff7ed;color:#9a3412;font-size:.75rem;">
                    <?= htmlspecialchars($vnpayConfigError !== '' ? $vnpayConfigError : 'Credit card payments are not set up yet.', ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php else: ?>
                <div style="margin-top:8px;font-size:.72rem;color:#64748b;">Credit card payments are securely processed by VNPay. Supported cards depend on your merchant account.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="cardPaymentPanel" style="display:none;background:white;border:1px solid #cbd5e1;border-radius:12px;padding:18px;margin-bottom:16px;">
        <div style="font-size:.9rem;font-weight:700;color:#0f172a;margin-bottom:5px;">Secure credit card payment</div>
        <div style="font-size:.76rem;color:#64748b;margin-bottom:13px;">You will be redirected to VNPay to complete payment using an eligible card. This website does not collect card details.</div>
        <button type="submit" id="cardDonateButton" style="width:100%;padding:13px;background:#1447c0;color:white;border:0;border-radius:9px;font-weight:700;font-size:.9rem;cursor:pointer;">
            <i class="bi bi-credit-card me-2"></i>Pay by Credit Card
        </button>
        <div id="cardPaymentStatus" role="status" aria-live="polite" style="display:none;margin-top:12px;padding:11px;border-radius:8px;font-size:.8rem;"></div>
    </div>

    <!-- Step 2: Transaction Details -->
    <div style="display:none;background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;margin-bottom:16px;">
        <div style="background:linear-gradient(135deg,#7c3aed,#6d28d9);padding:14px 20px;border-bottom:1px solid #5b21b6;display:flex;align-items:center;gap:10px;">
            <div style="width:26px;height:26px;background:rgba(255,255,255,.25);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;color:white;flex-shrink:0;">2</div>
            <div>
                <div style="font-size:.84rem;font-weight:700;color:white;">Transaction Details</div>
                <div style="font-size:.71rem;color:rgba(255,255,255,.75);">From your bank/payment receipt</div>
            </div>
        </div>
        <div style="padding:20px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">
                        Reference Code <span style="color:#ef4444;">*</span>
                    </label>
                    <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;height:44px;" onfocusin="this.style.borderColor='#7c3aed'" onfocusout="this.style.borderColor='#e2e8f0'">
                        <span style="padding:0 11px;color:#94a3b8;border-right:1px solid #f3f4f6;height:100%;display:flex;align-items:center;"><i class="bi bi-hash"></i></span>
                        <input type="text" name="reference_code"
                               style="flex:1;border:none;outline:none;padding:0 12px;font-size:.83rem;color:#0f172a;background:transparent;height:100%;"
                               placeholder="e.g. VCB2026001"
                               value="<?= htmlspecialchars($_POST['reference_code']??'') ?>">
                    </div>
                    <div style="font-size:.69rem;color:#94a3b8;margin-top:3px;">From your bank/payment receipt</div>
                </div>
                <div>
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">
                        Transaction Date <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="datetime-local" name="transaction_date"
                           style="width:100%;height:44px;padding:0 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.83rem;color:#0f172a;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'"
                           value="<?= htmlspecialchars($_POST['transaction_date']??date('Y-m-d\TH:i')) ?>">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div style="position:relative;">
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">Bank / Payment Provider</label>

                    <!-- Bank selector trigger -->
                    <div id="bankSelector" onclick="toggleBankDropdown()"
                         style="display:flex;align-items:center;gap:10px;border:1.5px solid #e2e8f0;border-radius:9px;padding:7px 13px;cursor:pointer;background:white;transition:all .15s;min-height:44px;"
                         onmouseover="this.style.borderColor='#7c3aed'" onmouseout="if(!bankOpen)this.style.borderColor='#e2e8f0'">
                        <div id="bankLogoWrap" style="width:32px;height:20px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-bank" id="bankDefaultIcon" style="color:#94a3b8;font-size:1rem;"></i>
                            <img id="bankLogoImg" style="max-width:32px;max-height:18px;object-fit:contain;display:none;">
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div id="bankName" style="font-size:.83rem;font-weight:600;color:#94a3b8;">Select bank or e-wallet...</div>
                            <div id="bankFullname" style="font-size:.69rem;color:#94a3b8;display:none;"></div>
                        </div>
                        <i class="bi bi-chevron-down" id="bankChevron" style="font-size:.72rem;color:#94a3b8;transition:transform .2s;flex-shrink:0;"></i>
                    </div>

                    <!-- Hidden input -->
                    <input type="hidden" name="bank_name" id="bankNameInput" value="<?= htmlspecialchars($_POST['bank_name']??'') ?>">

                    <!-- Dropdown -->
                    <div id="bankDropdown"
                         style="display:none;position:fixed;z-index:9999;background:white;border:1.5px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.15);overflow:hidden;">
                        <!-- Search -->
                        <div style="padding:10px 12px;border-bottom:1px solid #f1f5f9;">
                            <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:8px;overflow:hidden;height:34px;" onfocusin="this.style.borderColor='#7c3aed'" onfocusout="this.style.borderColor='#e2e8f0'">
                                <i class="bi bi-search" style="padding:0 10px;color:#94a3b8;font-size:.8rem;flex-shrink:0;"></i>
                                <input type="text" id="bankSearch"
                                       style="flex:1;border:none;outline:none;font-size:.8rem;color:#0f172a;background:transparent;"
                                       placeholder="Search bank..."
                                       oninput="filterBanks(this.value)">
                            </div>
                        </div>
                        <!-- Bank list -->
                        <div id="bankList" style="overflow-y:auto;max-height:260px;padding:6px;">
                        </div>
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">Amount Transferred (VND)</label>
                    <input type="number" name="amount_transferred"
                           style="width:100%;height:44px;padding:0 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.83rem;color:#0f172a;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'"
                           placeholder="Actual amount on receipt"
                           value="<?= htmlspecialchars($_POST['amount_transferred']??'') ?>">
                </div>
            </div>
            <div>
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">Account / Transaction Number</label>
                <input type="text" name="account_number"
                       style="width:100%;height:44px;padding:0 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.83rem;color:#0f172a;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="e.g. 1234567890"
                       value="<?= htmlspecialchars($_POST['account_number']??'') ?>">
            </div>
        </div>
    </div>

    <!-- Step 3: Additional -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;margin-bottom:20px;">
        <div style="background:linear-gradient(135deg,#16a34a,#15803d);padding:14px 20px;border-bottom:1px solid #166534;display:flex;align-items:center;gap:10px;">
            <div style="width:26px;height:26px;background:rgba(255,255,255,.25);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;color:white;flex-shrink:0;">3</div>
            <div>
                <div style="font-size:.84rem;font-weight:700;color:white;">Additional Information</div>
                <div style="font-size:.71rem;color:rgba(255,255,255,.75);">Optional message and preferences</div>
            </div>
        </div>
        <div style="padding:20px;">
            <div style="margin-bottom:14px;">
                <label for="donorEmail" style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">Email Address</label>
                <input type="email" id="donorEmail" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly
                       style="width:100%;height:44px;padding:0 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.83rem;color:#475569;background:#f8fafc;">
            </div>

            <!-- Donation Frequency -->
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:8px;">
                    Donation Frequency
                    <span style="background:#eff6ff;color:#1447c0;font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:10px;margin-left:5px;">Recurring support 🔄</span>
                </label>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;" id="frequencyGrid">
                    <?php foreach([
                        ['one_time',  'One Time',  'bi-1-circle',      'Make a single donation'],
                        ['monthly',   'Monthly',   'bi-calendar-month','Donate every month'],
                        ['quarterly', 'Quarterly', 'bi-calendar3',     'Donate every quarter'],
                        ['yearly',    'Yearly',    'bi-calendar-check','Donate once a year'],
                    ] as [$val, $label, $icon, $hint]): ?>
                    <label class="freq-label"
                           data-val="<?= $val ?>"
                           style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 6px;border:1.5px solid <?= $val === 'one_time' ? '#1447c0' : '#e2e8f0' ?>;border-radius:9px;background:<?= $val === 'one_time' ? '#eff6ff' : 'white' ?>;cursor:pointer;text-align:center;transition:all .15s;position:relative;">
                        <input type="radio" name="frequency" value="<?= $val ?>" <?= $val === 'one_time' ? 'checked' : '' ?>
                               style="position:absolute;opacity:0;pointer-events:none;"
                               onchange="updateFrequencyUI()">
                        <i class="bi <?= $icon ?>" style="font-size:1rem;color:<?= $val === 'one_time' ? '#1447c0' : '#94a3b8' ?>;"></i>
                        <span style="font-size:.72rem;font-weight:700;color:<?= $val === 'one_time' ? '#1447c0' : '#475569' ?>;"><?= $label ?></span>
                        <span style="font-size:.62rem;color:#94a3b8;line-height:1.3;"><?= $hint ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div id="recurringNote" style="display:none;margin-top:8px;padding:9px 12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:.74rem;color:#15803d;">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    <strong>Recurring donation:</strong> Each period you will receive a reminder to submit your donation. Finance staff will verify each payment individually.
                </div>
            </div>

            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">Message of Support (Optional)</label>
                <textarea name="message" rows="3"
                          style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.83rem;color:#0f172a;outline:none;transition:border-color .15s;resize:none;"
                          onfocus="this.style.borderColor='#16a34a'" onblur="this.style.borderColor='#e2e8f0'"
                          placeholder="Leave a message of support for this campaign..."><?= htmlspecialchars($_POST['message']??'') ?></textarea>
            </div>
            <label style="display:flex;align-items:center;gap:9px;cursor:pointer;padding:10px 13px;background:#f8fafc;border-radius:9px;">
                <input type="checkbox" name="is_anonymous" style="width:15px;height:15px;border-radius:4px;cursor:pointer;" <?= isset($_POST['is_anonymous'])?'checked':'' ?>>
                <div>
                    <div style="font-size:.8rem;font-weight:600;color:#374151;">Make this donation anonymous</div>
                    <div style="font-size:.71rem;color:#94a3b8;">Your name will not be shown publicly</div>
                </div>
            </label>
        </div>
    </div>

    <!-- Submit -->
    <button type="submit" id="bankDonateButton"
            style="width:100%;padding:14px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border:none;border-radius:12px;font-weight:700;font-size:.92rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;box-shadow:0 4px 18px rgba(239,68,68,.35);transition:all .2s;letter-spacing:-.01em;"
            onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 7px 24px rgba(239,68,68,.45)'"
            onmouseout="this.style.transform='none';this.style.boxShadow='0 4px 18px rgba(239,68,68,.35)'">
        <i class="bi bi-qr-code" style="font-size:.9rem;"></i>
        Continue to SePay
    </button>

</form>

<!-- ══ SIDEBAR INFO ═══════════════════════════════ -->
<div style="display:flex;flex-direction:column;gap:14px;position:sticky;top:80px;">

    <!-- What happens next -->
    <div style="background:linear-gradient(135deg,#1447c0,#0d2157);border:1px solid #0d2157;border-radius:14px;padding:18px;">
        <div style="font-size:.8rem;font-weight:700;color:white;margin-bottom:12px;display:flex;align-items:center;gap:7px;">
            <i class="bi bi-info-circle-fill"></i>What happens next?
        </div>
        <?php foreach([
            ['bi-send-fill',          '#dbeafe','#1447c0', 'You create a donation and continue to SePay checkout'],
            ['bi-shield-check-fill',  '#ede9fe','#7c3aed', 'After payment arrives, Finance Staff reviews the transaction'],
            ['bi-bell-fill',          '#dcfce7','#16a34a', 'You receive confirmation notification'],
            ['bi-graph-up-arrow',     '#fff7ed','#c2410c', 'Campaign progress updates automatically'],
        ] as [$ic,$bg,$color,$txt]): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:9px;">
            <div style="width:28px;height:28px;background:<?= $bg ?>;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi <?= $ic ?>" style="color:<?= $color ?>;font-size:.78rem;"></i>
            </div>
            <span style="font-size:.77rem;color:rgba(255,255,255,.8);line-height:1.4;"><?= $txt ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Donation summary -->
    <div style="background:white;border:2px solid #1447c0;border-radius:14px;padding:18px;" id="summaryCard">
        <div style="font-size:.8rem;font-weight:700;color:#0f172a;margin-bottom:12px;display:flex;align-items:center;gap:7px;">
            <i class="bi bi-receipt" style="color:#7c3aed;"></i>Donation Summary
        </div>
        <div id="summaryAmount" style="font-size:1.6rem;font-weight:800;color:#16a34a;letter-spacing:-.03em;margin-bottom:4px;">—</div>
        <div id="paymentSummaryHint" style="font-size:.72rem;color:#94a3b8;margin-bottom:12px;">Continue to SePay to complete the VietQR bank transfer</div>
        <div style="height:1px;background:#f1f5f9;margin-bottom:12px;"></div>
        <div style="display:flex;justify-content:space-between;font-size:.76rem;margin-bottom:7px;">
            <span style="color:#64748b;">Campaign</span>
            <span style="font-weight:600;color:#0f172a;text-align:right;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" id="summCamp">General</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:.76rem;margin-bottom:7px;">
            <span style="color:#64748b;">Channel</span>
            <span style="font-weight:600;color:#0f172a;" id="summChannel">Bank Transfer</span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:.76rem;margin-bottom:7px;">
            <span style="color:#64748b;">Frequency</span>
            <span style="font-weight:600;color:#0f172a;" id="summFrequency">
                <span style="background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:5px;font-size:.7rem;font-weight:700;">One Time</span>
            </span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:.76rem;">
            <span style="color:#64748b;">Status after submit</span>
            <span id="summStatus" style="background:#fef9c3;color:#854d0e;padding:2px 8px;border-radius:5px;font-size:.7rem;font-weight:700;">Awaiting Payment</span>
        </div>
    </div>

    <!-- Required fields -->
    <div style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:14px;padding:16px;">
        <div style="font-size:.78rem;font-weight:700;color:#0f172a;margin-bottom:10px;display:flex;align-items:center;gap:7px;">
            <i class="bi bi-list-check" style="color:#16a34a;"></i>Required fields
        </div>
        <?php foreach(['Active campaign','Donation amount'] as $f): ?>
        <div style="display:flex;align-items:center;gap:7px;margin-bottom:6px;font-size:.76rem;color:#475569;">
            <i class="bi bi-check-circle-fill" style="color:#22c55e;font-size:.78rem;"></i><?= $f ?>
        </div>
        <?php endforeach; ?>
    </div>

</div>
</div>

<script>
// Amount quick select
function setAmount(v) {
    document.getElementById('amountInput').value = v;
    updateSummary();
    document.querySelectorAll('.amt-btn').forEach(b => {
        const active = parseInt(b.dataset.val) === v;
        b.style.background   = active ? '#eff6ff' : 'white';
        b.style.borderColor  = active ? '#1447c0' : '#e2e8f0';
        b.style.color        = active ? '#1447c0' : '#475569';
    });
}
function clearAmtBtns() {
    document.querySelectorAll('.amt-btn').forEach(b => {
        b.style.background  = 'white';
        b.style.borderColor = '#e2e8f0';
        b.style.color       = '#475569';
    });
    updateSummary();
}

const vnpayEndpoint = <?= json_encode(SITE_URL . '/donor/vnpay_checkout.php') ?>;
const vnpayReady = <?= json_encode($vnpayReady) ?>;
const sepayReady = <?= json_encode($sepayReady) ?>;
const donationForm = document.getElementById('donateForm');
const bankDonateButton = document.getElementById('bankDonateButton');
const cardDonateButton = document.getElementById('cardDonateButton');
const cardPaymentPanel = document.getElementById('cardPaymentPanel');

function getPaymentMethod() {
    return donationForm.querySelector('input[name="payment_method"]:checked')?.value || 'bank_transfer';
}

function setPaymentMethodUI() {
    const method = getPaymentMethod();
    const bankTransfer = method === 'bank_transfer';
    document.getElementById('amountInput').min = bankTransfer ? '1' : '5000';
    const paymentLabels = document.querySelectorAll('.payment-method-label');
    paymentLabels.forEach(label => {
        const input = label.querySelector('input');
        const selected = input.checked;
        const color = input.value === 'credit_card' ? '#475569' : '#1447c0';
        label.style.borderColor = selected ? color : '#e2e8f0';
        label.style.background = selected ? (input.value === 'bank_transfer' ? '#dbeafe' : '#f1f5f9') : 'white';
    });

    bankDonateButton.style.display = bankTransfer ? 'flex' : 'none';
    cardDonateButton.style.display = bankTransfer ? 'none' : 'block';
    cardPaymentPanel.style.display = bankTransfer ? 'none' : 'block';
    document.getElementById('summChannel').textContent = method === 'credit_card' ? 'Credit Card via VNPay' : 'Bank Transfer';
    document.getElementById('paymentSummaryHint').textContent = method === 'bank_transfer'
        ? 'Continue to SePay to complete your VietQR bank transfer'
        : 'VNPay securely processes your card payment in VND.';
    document.getElementById('summStatus').textContent = method === 'bank_transfer'
        ? 'Awaiting Payment'
        : 'Verified after VNPay confirms card payment.';
}

// Summary update
function updateSummary() {
    const amt = parseFloat(document.getElementById('amountInput').value)||0;
    const amtEl = document.getElementById('summaryAmount');
    if(amt>0) amtEl.textContent = new Intl.NumberFormat('vi-VN').format(amt)+' VND';
    else amtEl.textContent='—';

    const camp = document.querySelector('select[name=campaign_id]');
    const campText = camp.options[camp.selectedIndex].text;
    document.getElementById('summCamp').textContent = camp.value && camp.value !== '0' ? campText.substring(0,30) : "Where it's needed most";

    // Update frequency badge in summary
    const freqInput = donationForm.querySelector('input[name="frequency"]:checked');
    const freqVal   = freqInput ? freqInput.value : 'one_time';
    const freqMap   = {
        one_time:  {label:'One Time',  bg:'#f1f5f9',  color:'#475569'},
        monthly:   {label:'Monthly 🔄',  bg:'#dcfce7',  color:'#15803d'},
        quarterly: {label:'Quarterly 🔄', bg:'#dbeafe',  color:'#1447c0'},
        yearly:    {label:'Yearly 🔄',    bg:'#ede9fe',  color:'#7c3aed'},
    };
    const f = freqMap[freqVal] || freqMap.one_time;
    document.getElementById('summFrequency').innerHTML =
        `<span style="background:${f.bg};color:${f.color};padding:2px 8px;border-radius:5px;font-size:.7rem;font-weight:700;">${f.label}</span>`;
}

// Frequency selector UI
function updateFrequencyUI() {
    const selected = donationForm.querySelector('input[name="frequency"]:checked')?.value || 'one_time';
    document.querySelectorAll('.freq-label').forEach(lbl => {
        const v = lbl.dataset.val;
        const active = v === selected;
        const colorMap = {one_time:'#1447c0', monthly:'#15803d', quarterly:'#1447c0', yearly:'#7c3aed'};
        const bgMap    = {one_time:'#eff6ff',  monthly:'#f0fdf4',  quarterly:'#eff6ff',  yearly:'#faf5ff'};
        lbl.style.borderColor = active ? colorMap[v] : '#e2e8f0';
        lbl.style.background  = active ? bgMap[v]    : 'white';
        lbl.querySelector('i').style.color     = active ? colorMap[v] : '#94a3b8';
        lbl.querySelector('span').style.color  = active ? colorMap[v] : '#475569';
    });
    // Show/hide recurring note
    const note = document.getElementById('recurringNote');
    if (note) note.style.display = selected === 'one_time' ? 'none' : 'block';
    updateSummary();
}

document.getElementById('amountInput').addEventListener('input', updateSummary);
document.querySelector('select[name=campaign_id]').addEventListener('change', updateSummary);
donationForm.querySelectorAll('input[name="payment_method"]').forEach(input => {
    input.addEventListener('change', setPaymentMethodUI);
});
donationForm.querySelectorAll('input[name="frequency"]').forEach(input => {
    input.addEventListener('change', updateFrequencyUI);
});
updateSummary();
setPaymentMethodUI();

donationForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    const form = event.currentTarget;
    const paymentMethod = getPaymentMethod();
    if (paymentMethod === 'credit_card') {
        if (!vnpayReady) {
            showCardPaymentStatus('VNPay is not configured. Please choose bank transfer or contact the administrator.', 'error');
            return;
        }
        if (!form.reportValidity()) return;
        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonHtml = submitButton.innerHTML;
        const formData = new FormData(form);
        submitButton.disabled = true;
        submitButton.textContent = 'Connecting to VNPay...';
        try {
            const response = await fetch(vnpayEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    campaign_id: formData.get('campaign_id'),
                    amount: formData.get('amount'),
                    message: formData.get('message'),
                    is_anonymous: formData.has('is_anonymous'),
                    csrf_token: formData.get('csrf_token')
                })
            });
            const responseText = await response.text();
            let result;
            try {
                result = JSON.parse(responseText);
            } catch {
                throw new Error(`The server returned an invalid VNPay response (HTTP ${response.status}).`);
            }
            if (!response.ok) {
                throw new Error(result.error || `Unable to start card payment (HTTP ${response.status}).`);
            }
            window.location.assign(result.payment_url);
        } catch (error) {
            showCardPaymentStatus(error.message, 'error');
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonHtml;
        }
        return;
    }
    if (!sepayReady) {
        alert('SePay bank-transfer payments are not configured. Please contact the administrator.');
        return;
    }
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonHtml = submitButton.innerHTML;
    const formData = new FormData(form);
    const payload = {
        campaign_id: formData.get('campaign_id'),
        amount: formData.get('amount'),
        frequency: formData.get('frequency') || 'one_time',
        message: formData.get('message'),
        is_anonymous: formData.has('is_anonymous'),
        csrf_token: formData.get('csrf_token')
    };

    submitButton.disabled = true;
    submitButton.textContent = 'Connecting to SePay...';
    try {
        const response = await fetch(<?= json_encode(SITE_URL . '/donor/create_donation.php') ?>, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });
        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch {
            throw new Error(`The server returned an invalid response (HTTP ${response.status}). Check the hosting PHP error log.`);
        }
        if (!response.ok) {
            throw new Error(result.error || `Unable to start SePay checkout (HTTP ${response.status}).`);
        }
        const checkoutForm = document.createElement('form');
        checkoutForm.method = 'POST';
        checkoutForm.action = result.checkout_url;
        Object.entries(result.checkout_fields).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            checkoutForm.appendChild(input);
        });
        document.body.appendChild(checkoutForm);
        checkoutForm.submit();
    } catch (error) {
        alert(error.message);
    } finally {
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonHtml;
    }
});

function showCardPaymentStatus(message, type) {
    const status = document.getElementById('cardPaymentStatus');
    status.style.display = 'block';
    status.style.background = type === 'success' ? '#dcfce7' : '#fee2e2';
    status.style.color = type === 'success' ? '#166534' : '#991b1b';
    status.textContent = message;
}

// ── Bank selector ──────────────────────────────
const BANKS = [
    { code:'VCB',   name:'Vietcombank',      fullname:'Ngân hàng Ngoại thương VN',       color:'#006438', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-Vietcombank.png' },
    { code:'VTB',   name:'VietinBank',       fullname:'Ngân hàng Công thương VN',        color:'#c8000a', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-Vietinbank.png' },
    { code:'BIDV',  name:'BIDV',             fullname:'Ngân hàng Đầu tư & Phát triển',   color:'#004a97', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-BIDV.png' },
    { code:'ACB',   name:'ACB',              fullname:'Ngân hàng Á Châu',                color:'#004a8e', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-ACB-2020.png' },
    { code:'TCB',   name:'Techcombank',      fullname:'Ngân hàng Kỹ thương VN',          color:'#e31f26', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-Techcombank-TCB.png' },
    { code:'MB',    name:'MBBank',           fullname:'Ngân hàng Quân đội',              color:'#9b0000', logo:'https://cdn.haitrieu.com/wp-content/uploads/2021/12/Logo-MBBank.png' },
    { code:'VPB',   name:'VPBank',           fullname:'Ngân hàng VN Thịnh Vượng',        color:'#007b40', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-VPBank.png' },
    { code:'STB',   name:'Sacombank',        fullname:'Ngân hàng Sài Gòn Thương Tín',    color:'#005bac', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-Sacombank.png' },
    { code:'TPB',   name:'TPBank',           fullname:'Ngân hàng Tiên Phong',            color:'#8b1a1a', logo:'https://cdn.haitrieu.com/wp-content/uploads/2021/12/Logo-TPBank.png' },
    { code:'HDB',   name:'HDBank',           fullname:'Ngân hàng Phát triển TP.HCM',     color:'#1a3e6c', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-HDBank.png' },
    { code:'SHB',   name:'SHB',              fullname:'Ngân hàng Sài Gòn - Hà Nội',      color:'#c8000a', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-SHB.png' },
    { code:'EIB',   name:'Eximbank',         fullname:'Ngân hàng Xuất Nhập khẩu VN',     color:'#004a8e', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-Eximbank.png' },
    { code:'MSB',   name:'MSB',              fullname:'Ngân hàng Hàng Hải VN',           color:'#e31f26', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-MSB.png' },
    { code:'OCB',   name:'OCB',              fullname:'Ngân hàng Phương Đông',           color:'#005bac', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-OCB.png' },
    { code:'LPB',   name:'LienVietPostBank', fullname:'Ngân hàng Bưu điện Liên Việt',    color:'#0073be', logo:'https://cdn.haitrieu.com/wp-content/uploads/2021/12/Logo-LienViet-PostBank.png' },
    { code:'SCB',   name:'SCB',              fullname:'Ngân hàng Sài Gòn',              color:'#b22222', logo:null },
    { code:'NAB',   name:'Nam A Bank',       fullname:'Ngân hàng Nam Á',                color:'#c00000', logo:null },
    { code:'SEAB',  name:'SeABank',          fullname:'Ngân hàng Đông Nam Á',           color:'#d4000d', logo:null },
    { code:'NCB',   name:'NCB',              fullname:'Ngân hàng Quốc Dân',             color:'#004a8e', logo:null },
    // E-wallets
    { code:'MOMO',  name:'MoMo',             fullname:'Ví điện tử MoMo',               color:'#ae2070', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/10/Logo-MoMo-Square.png', divider:'E-Wallets' },
    { code:'ZALO',  name:'ZaloPay',          fullname:'Ví điện tử ZaloPay',            color:'#0068ff', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-ZaloPay.png' },
    { code:'VNPAY', name:'VNPAY',            fullname:'Cổng thanh toán VNPAY',         color:'#005bac', logo:'https://cdn.haitrieu.com/wp-content/uploads/2022/01/Logo-VNPAY-QR.png' },
    { code:'SHOPEE',name:'ShopeePay',        fullname:'Ví điện tử ShopeePay',          color:'#ee4d2d', logo:null },
    { code:'OTHER', name:'Other / Khác',     fullname:'Ngân hàng hoặc tổ chức khác',   color:'#475569', logo:null },
];

let bankOpen = false;
let selectedBank = null;

// Pre-select if value exists
const existingBank = '<?= htmlspecialchars($_POST['bank_name']??'') ?>';
if (existingBank) {
    const found = BANKS.find(b => b.name === existingBank || b.code === existingBank);
    if (found) setTimeout(() => selectBank(found), 100);
}

function renderBankList(filter) {
    const list = document.getElementById('bankList');
    list.innerHTML = '';
    let hasDivider = false;
    BANKS.forEach(b => {
        if (filter && !b.name.toLowerCase().includes(filter.toLowerCase()) && !b.fullname.toLowerCase().includes(filter.toLowerCase())) return;
        // Divider for e-wallets
        if (b.divider && !filter) {
            const div = document.createElement('div');
            div.style.cssText = 'padding:6px 10px 3px;font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;';
            div.textContent = b.divider;
            list.appendChild(div);
        }
        const item = document.createElement('div');
        item.style.cssText = 'display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:9px;cursor:pointer;transition:background .12s;';
        item.innerHTML = `
            <div style="width:36px;height:24px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:#f8fafc;border-radius:5px;overflow:hidden;border:1px solid #f1f5f9;">
                ${b.logo
                    ? `<img src="${b.logo}" style="max-width:34px;max-height:22px;object-fit:contain;" onerror="this.style.display='none';this.nextSibling.style.display='block'"><span style="display:none;font-size:.62rem;font-weight:800;color:${b.color};">${b.code}</span>`
                    : `<span style="font-size:.62rem;font-weight:800;color:${b.color};">${b.code}</span>`
                }
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:.8rem;font-weight:700;color:#0f172a;">${b.name}</div>
                <div style="font-size:.68rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${b.fullname}</div>
            </div>
            ${selectedBank && selectedBank.code===b.code ? '<i class="bi bi-check2" style="color:#7c3aed;font-size:.9rem;flex-shrink:0;"></i>' : ''}
        `;
        item.onmouseover = () => item.style.background = '#f8fafc';
        item.onmouseout  = () => item.style.background = selectedBank && selectedBank.code===b.code ? '#faf5ff' : 'transparent';
        if (selectedBank && selectedBank.code===b.code) item.style.background='#faf5ff';
        item.onclick = () => selectBank(b);
        list.appendChild(item);
    });
    if (list.children.length === 0) {
        list.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8;font-size:.8rem;">No banks found</div>';
    }
}

function selectBank(bank) {
    selectedBank = bank;
    document.getElementById('bankNameInput').value = bank.name;
    document.getElementById('bankName').textContent = bank.name;
    document.getElementById('bankName').style.color = '#0f172a';
    document.getElementById('bankFullname').textContent = bank.fullname;
    document.getElementById('bankFullname').style.display = 'block';
    // Logo
    const logoImg = document.getElementById('bankLogoImg');
    const defIcon = document.getElementById('bankDefaultIcon');
    if (bank.logo) {
        logoImg.src = bank.logo;
        logoImg.style.display = 'block';
        defIcon.style.display = 'none';
        logoImg.onerror = () => {
            logoImg.style.display = 'none';
            defIcon.style.display = 'block';
            defIcon.style.color = bank.color;
            defIcon.className = 'bi bi-bank';
        };
    } else {
        logoImg.style.display = 'none';
        defIcon.style.display = 'block';
        defIcon.style.color = bank.color;
        defIcon.className = 'bi bi-bank';
    }
    // Selector border color
    document.getElementById('bankSelector').style.borderColor = bank.color;
    closeBankDropdown();
}

function toggleBankDropdown() {
    bankOpen ? closeBankDropdown() : openBankDropdown();
}

function openBankDropdown() {
    bankOpen = true;
    const dd  = document.getElementById('bankDropdown');
    const sel = document.getElementById('bankSelector');
    const rect = sel.getBoundingClientRect();

    // Use fixed positioning so it breaks out of any overflow:hidden parent
    dd.style.position = 'fixed';
    dd.style.top      = (rect.bottom + 4) + 'px';
    dd.style.left     = rect.left + 'px';
    dd.style.width    = rect.width + 'px';
    dd.style.display  = 'block';

    document.getElementById('bankChevron').style.transform = 'rotate(180deg)';
    document.getElementById('bankSelector').style.borderColor = '#7c3aed';
    document.getElementById('bankSearch').value = '';
    document.getElementById('bankSearch').focus();
    renderBankList('');
}

function closeBankDropdown() {
    bankOpen = false;
    document.getElementById('bankDropdown').style.display = 'none';
    document.getElementById('bankChevron').style.transform = 'rotate(0deg)';
    if (!selectedBank) document.getElementById('bankSelector').style.borderColor = '#e2e8f0';
}

function filterBanks(q) { renderBankList(q); }

// Close on outside click
document.addEventListener('click', e => {
    const dd = document.getElementById('bankDropdown');
    const sel = document.getElementById('bankSelector');
    if (dd && sel && !dd.contains(e.target) && !sel.contains(e.target)) {
        closeBankDropdown();
    }
});

// Reposition on scroll/resize
window.addEventListener('scroll', () => {
    if (bankOpen) {
        const sel  = document.getElementById('bankSelector');
        const dd   = document.getElementById('bankDropdown');
        const rect = sel.getBoundingClientRect();
        dd.style.top   = (rect.bottom + 4) + 'px';
        dd.style.left  = rect.left + 'px';
        dd.style.width = rect.width + 'px';
    }
}, true);

window.addEventListener('resize', () => {
    if (bankOpen) {
        const sel  = document.getElementById('bankSelector');
        const dd   = document.getElementById('bankDropdown');
        const rect = sel.getBoundingClientRect();
        dd.style.top   = (rect.bottom + 4) + 'px';
        dd.style.left  = rect.left + 'px';
        dd.style.width = rect.width + 'px';
    }
});

// Pre-select amount if value exists
const existingAmt = <?= json_encode($_POST['amount'] ?? '') ?>;
if(existingAmt) {
    const v = parseInt(existingAmt);
    document.querySelectorAll('.amt-btn').forEach(b => {
        if(parseInt(b.dataset.val)===v){b.style.background='#eff6ff';b.style.borderColor='#1447c0';b.style.color='#1447c0';}
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
