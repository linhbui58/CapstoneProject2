<?php
$pageTitle  = 'Create New Transaction';
$activePage = 'transactions';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('finance_staff');

$db  = getDB();
$uid = $user['id'];
$errors = [];

$campaigns = $db->query("SELECT id, title FROM campaigns WHERE status='active' ORDER BY title");
$donors    = $db->query("SELECT id, name, email FROM users WHERE role='donor' AND is_active=1 ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donor_id    = (int)($_POST['donor_id'] ?? 0);
    $campaign_id = !empty($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : null;
    $amount      = (float)($_POST['amount'] ?? 0);
    $channel     = sanitize($_POST['channel'] ?? '');
    $ref_code    = sanitize($_POST['reference_code'] ?? '');
    $bank_name   = sanitize($_POST['bank_name'] ?? '');
    $acc_number  = sanitize($_POST['account_number'] ?? '');
    $txn_date    = sanitize($_POST['transaction_date'] ?? '');
    $txn_amount  = (float)($_POST['amount_transferred'] ?? 0);
    $message     = sanitize($_POST['message'] ?? '');
    $is_anon     = isset($_POST['is_anonymous']) ? 1 : 0;

    if (!$donor_id)       $errors[] = 'Please select a donor.';
    if ($amount <= 0)     $errors[] = 'Donation amount must be greater than 0.';
    if (empty($channel))  $errors[] = 'Please select a donation channel.';
    if (empty($ref_code)) $errors[] = 'Transaction reference code is required.';
    if (empty($txn_date)) $errors[] = 'Transaction date is required.';

    if (empty($errors)) {
        $cid_sql  = $campaign_id ? $campaign_id : 'NULL';
        $ch_esc   = $db->real_escape_string($channel);
        $msg_esc  = $db->real_escape_string($message);

        $db->query("INSERT INTO donations (donor_id, campaign_id, amount, channel, message, is_anonymous, status)
                    VALUES ($donor_id, $cid_sql, $amount, '$ch_esc', '$msg_esc', $is_anon, 'pending')");
        $donation_id = $db->insert_id;

        $ref_esc  = $db->real_escape_string($ref_code);
        $bank_esc = $db->real_escape_string($bank_name);
        $acc_esc  = $db->real_escape_string($acc_number);
        $date_esc = $db->real_escape_string($txn_date);

        $db->query("INSERT INTO transactions
                    (donation_id, reference_code, bank_name, account_number, transaction_date, amount_transferred, verification_status)
                    VALUES ($donation_id, '$ref_esc', '$bank_esc', '$acc_esc', '$date_esc', $txn_amount, 'pending_verification')");
        $txn_id = $db->insert_id;

        $db->query("INSERT INTO verification_history (transaction_id, action, performed_by, new_status, notes)
                    VALUES ($txn_id, 'submitted', $uid, 'pending_verification', 'Transaction created by Finance Staff')");

        $db->query("INSERT INTO notifications (user_id, title, message, type)
                    VALUES ($donor_id, 'Donation Recorded',
                    'A donation of " . formatCurrency($amount) . " has been recorded on your behalf and is pending verification.',
                    'system')");

        setFlash('success', 'Transaction TX-' . str_pad($txn_id,4,'0',STR_PAD_LEFT) . ' created and set to Pending Verification.');
        redirect(SITE_URL . '/finance_staff/transactions.php');
    }
}
?>

<!-- PAGE HEADER -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <span style="width:36px;height:36px;background:#dbeafe;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-plus-circle-fill" style="color:#1447c0;font-size:.9rem;"></i>
            </span>
            Create New Transaction
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0 0 0 45px;">Record a new donation transaction — will be set to <strong style="color:#d97706;">Pending Verification</strong> (FR-01)</p>
    </div>
    <a href="<?= SITE_URL ?>/finance_staff/transactions.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border:1.5px solid #e2e8f0;border-radius:10px;color:#64748b;font-size:.81rem;font-weight:600;background:white;text-decoration:none;transition:all .15s;white-space:nowrap;"
       onmouseover="this.style.background='#f8fafc';this.style.borderColor='#cbd5e1'"
       onmouseout="this.style.background='white';this.style.borderColor='#e2e8f0'">
        <i class="bi bi-arrow-left"></i> Back to List
    </a>
</div>

<?php if (!empty($errors)): ?>
<div style="background:#fff1f2;border-left:3.5px solid #ef4444;padding:13px 18px;border-radius:10px;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px;">
    <i class="bi bi-exclamation-circle-fill" style="color:#ef4444;font-size:.95rem;flex-shrink:0;margin-top:1px;"></i>
    <div>
        <div style="font-weight:700;color:#b91c1c;font-size:.82rem;margin-bottom:5px;">Please fix the following:</div>
        <ul style="margin:0;padding-left:16px;color:#b91c1c;font-size:.79rem;">
            <?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<form method="POST" id="txnForm">
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

<!-- ═══ LEFT: MAIN FORM ═════════════════════════════ -->
<div style="display:flex;flex-direction:column;gap:16px;">

    <!-- SECTION 1: Donor & Donation -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);">
        <div style="padding:13px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
            <div style="width:28px;height:28px;background:#dbeafe;border-radius:7px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-person-fill" style="color:#1447c0;font-size:.78rem;"></i>
            </div>
            <span style="font-size:.85rem;font-weight:700;color:#0f172a;">Donor & Donation Information</span>
        </div>
        <div style="padding:20px;">

            <!-- Row 1: Donor + Campaign -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <div>
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">Donor <span style="color:#ef4444;">*</span></label>
                    <select name="donor_id" required
                            style="width:100%;height:42px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;background:white;outline:none;cursor:pointer;transition:border-color .15s;"
                            onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'">
                        <option value="">Select donor...</option>
                        <?php if($donors) while($d=$donors->fetch_assoc()): ?>
                        <option value="<?= $d['id'] ?>" <?= ($_POST['donor_id']??'')==$d['id']?'selected':'' ?>>
                            <?= htmlspecialchars($d['name']) ?> — <?= htmlspecialchars($d['email']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">Campaign / Program</label>
                    <select name="campaign_id"
                            style="width:100%;height:42px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;background:white;outline:none;cursor:pointer;transition:border-color .15s;"
                            onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'">
                        <option value="">— General Donation —</option>
                        <?php if($campaigns) while($c=$campaigns->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= ($_POST['campaign_id']??'')==$c['id']?'selected':'' ?>>
                            <?= htmlspecialchars($c['title']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <!-- Row 2: Amount + Channel -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:8px;">
                <div>
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">Donation Amount (VND) <span style="color:#ef4444;">*</span></label>
                    <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;height:42px;transition:border-color .15s;" onfocusin="this.style.borderColor='#1447c0'" onfocusout="this.style.borderColor='#e2e8f0'">
                        <span style="padding:0 12px;background:#f8fafc;border-right:1px solid #e2e8f0;color:#94a3b8;font-size:.81rem;font-weight:600;height:100%;display:flex;align-items:center;">VND</span>
                        <input type="number" name="amount" min="1" required id="amountInput"
                               style="flex:1;border:none;outline:none;padding:0 12px;font-size:.84rem;font-weight:700;color:#0f172a;background:white;height:100%;"
                               placeholder="e.g. 2000000"
                               value="<?= htmlspecialchars($_POST['amount']??'') ?>">
                    </div>
                    <!-- Quick amounts -->
                    <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:8px;">
                        <?php foreach([500000,1000000,2000000,5000000,10000000] as $amt): ?>
                        <button type="button"
                                style="padding:4px 10px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:.71rem;font-weight:600;color:#475569;background:white;cursor:pointer;transition:all .14s;"
                                onmouseover="this.style.background='#eff6ff';this.style.borderColor='#bfdbfe';this.style.color='#1447c0'"
                                onmouseout="this.style.background='white';this.style.borderColor='#e2e8f0';this.style.color='#475569'"
                                onclick="document.getElementById('amountInput').value=<?= $amt ?>;document.getElementById('transferAmountInput').value=<?= $amt ?>">
                            <?= number_format($amt/1000) ?>K
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">Channel <span style="color:#ef4444;">*</span></label>
                    <select name="channel" required id="channelSelect"
                            onchange="onChannelChange(this.value)"
                            style="width:100%;height:42px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;background:white;outline:none;cursor:pointer;transition:border-color .15s;"
                            onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'">
                        <option value="">Select channel...</option>
                        <option value="bank_transfer" <?= ($_POST['channel']??'')==='bank_transfer'?'selected':'' ?>>🏦 Bank Transfer</option>
                        <option value="e_wallet"      <?= ($_POST['channel']??'')==='e_wallet'?'selected':'' ?>>📱 E-Wallet (MoMo, ZaloPay)</option>
                        <option value="credit_card"   <?= ($_POST['channel']??'')==='credit_card'?'selected':'' ?>>💳 Credit Card</option>
                        <option value="paypal"        <?= ($_POST['channel']??'')==='paypal'?'selected':'' ?>>🌐 PayPal</option>
                        <option value="cash"          <?= ($_POST['channel']??'')==='cash'?'selected':'' ?>>💵 Cash</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 2: Transaction Details -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);">
        <div style="padding:13px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;background:linear-gradient(135deg,#f8fafc,#f1f5f9);">
            <div style="width:28px;height:28px;background:#fef9c3;border-radius:7px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-receipt" style="color:#d97706;font-size:.78rem;"></i>
            </div>
            <span style="font-size:.85rem;font-weight:700;color:#0f172a;">Transaction Details</span>
        </div>
        <div style="padding:20px;">

            <!-- Row 3: Ref + Date -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <div>
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">Reference Code <span style="color:#ef4444;">*</span></label>
                    <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;height:42px;transition:border-color .15s;" onfocusin="this.style.borderColor='#1447c0'" onfocusout="this.style.borderColor='#e2e8f0'">
                        <span style="padding:0 11px;background:#f8fafc;border-right:1px solid #e2e8f0;color:#94a3b8;font-size:.88rem;height:100%;display:flex;align-items:center;">#</span>
                        <input type="text" name="reference_code" required
                               style="flex:1;border:none;outline:none;padding:0 12px;font-size:.82rem;color:#0f172a;background:white;height:100%;"
                               placeholder="e.g. VCB2026100101"
                               value="<?= htmlspecialchars($_POST['reference_code']??'') ?>">
                    </div>
                    <div style="font-size:.69rem;color:#94a3b8;margin-top:4px;">From bank/payment receipt</div>
                </div>
                <div>
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">Transaction Date <span style="color:#ef4444;">*</span></label>
                    <input type="datetime-local" name="transaction_date" required
                           style="width:100%;height:42px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                           value="<?= htmlspecialchars($_POST['transaction_date']??date('Y-m-d\TH:i')) ?>">
                </div>
            </div>

            <!-- Row 4: Bank Selector + Amount Transferred -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <!-- BANK SELECTOR with logos -->
                <div>
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">Bank / Payment Provider</label>
                    <input type="hidden" name="bank_name" id="bankNameInput" value="<?= htmlspecialchars($_POST['bank_name']??'') ?>">
                    <!-- Display trigger -->
                    <div id="bankSelector"
                         onclick="toggleBankDropdown()"
                         style="display:flex;align-items:center;gap:10px;height:42px;border:1.5px solid #e2e8f0;border-radius:9px;padding:0 12px;cursor:pointer;background:white;transition:border-color .15s;user-select:none;"
                         onmouseover="this.style.borderColor='#cbd5e1'" onmouseout="this.style.borderColor=bankSelected?'#1447c0':'#e2e8f0'">
                        <div id="bankLogoWrap" style="width:28px;height:28px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
                            <i class="bi bi-bank" style="color:#94a3b8;font-size:.82rem;" id="bankIconFallback"></i>
                        </div>
                        <span id="bankDisplay" style="flex:1;font-size:.82rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">Select bank or e-wallet...</span>
                        <i class="bi bi-chevron-down" id="bankChevron" style="color:#94a3b8;font-size:.72rem;flex-shrink:0;transition:transform .2s;"></i>
                    </div>
                    <!-- Dropdown -->
                    <div id="bankDropdown"
                         style="display:none;position:fixed;background:white;border:1.5px solid #e2e8f0;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.15);z-index:9999;width:340px;max-height:320px;overflow:hidden;">
                        <!-- Search -->
                        <div style="padding:10px 12px;border-bottom:1px solid #f1f5f9;position:sticky;top:0;background:white;z-index:1;">
                            <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:8px;overflow:hidden;height:34px;" onfocusin="this.style.borderColor='#1447c0'" onfocusout="this.style.borderColor='#e2e8f0'">
                                <i class="bi bi-search" style="padding:0 9px;color:#94a3b8;font-size:.78rem;"></i>
                                <input type="text" id="bankSearch" placeholder="Search bank..." oninput="filterBanks(this.value)"
                                       style="flex:1;border:none;outline:none;font-size:.8rem;color:#0f172a;background:transparent;height:100%;padding-right:9px;">
                            </div>
                        </div>
                        <!-- Bank list -->
                        <div id="bankList" style="overflow-y:auto;max-height:260px;padding:6px;"></div>
                    </div>
                </div>

                <div>
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">Amount Transferred (VND)</label>
                    <input type="number" name="amount_transferred" id="transferAmountInput" min="0"
                           style="width:100%;height:42px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                           placeholder="Actual amount on receipt"
                           value="<?= htmlspecialchars($_POST['amount_transferred']??'') ?>">
                    <div style="font-size:.69rem;color:#94a3b8;margin-top:4px;">Should match donation amount</div>
                </div>
            </div>

            <!-- Row 5: Account Number -->
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">Account / Transaction Number</label>
                <input type="text" name="account_number"
                       style="width:100%;height:42px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="e.g. 1234567890"
                       value="<?= htmlspecialchars($_POST['account_number']??'') ?>">
            </div>

            <!-- Row 6: Message -->
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">Message / Note (Optional)</label>
                <textarea name="message" rows="2"
                          style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;resize:none;line-height:1.55;"
                          onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                          placeholder="Optional note about this donation..."><?= htmlspecialchars($_POST['message']??'') ?></textarea>
            </div>

            <!-- Anonymous checkbox -->
            <div style="margin-bottom:20px;">
                <label style="display:inline-flex;align-items:center;gap:9px;cursor:pointer;padding:10px 14px;background:#f8fafc;border-radius:9px;border:1.5px solid #f1f5f9;transition:all .14s;"
                       onmouseover="this.style.borderColor='#e2e8f0';this.style.background='#f1f5f9'"
                       onmouseout="this.style.borderColor='#f1f5f9';this.style.background='#f8fafc'">
                    <input type="checkbox" name="is_anonymous" style="width:15px;height:15px;border-radius:4px;cursor:pointer;accent-color:#1447c0;"
                           <?= isset($_POST['is_anonymous'])?'checked':'' ?>>
                    <div>
                        <div style="font-size:.8rem;font-weight:600;color:#374151;">Mark as anonymous donation</div>
                        <div style="font-size:.69rem;color:#94a3b8;margin-top:1px;">Donor name will not be displayed publicly</div>
                    </div>
                </label>
            </div>

            <!-- Submit -->
            <button type="submit"
                    style="width:100%;padding:13px;background:linear-gradient(135deg,#1447c0,#0d2157);color:white;border:none;border-radius:11px;font-weight:700;font-size:.9rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 16px rgba(20,71,192,.3);transition:all .18s;letter-spacing:-.01em;"
                    onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 22px rgba(20,71,192,.38)'"
                    onmouseout="this.style.transform='none';this.style.boxShadow='0 4px 16px rgba(20,71,192,.3)'">
                <i class="bi bi-plus-circle-fill" style="font-size:.9rem;"></i>
                Create Transaction (Pending Verification)
            </button>
        </div>
    </div>

</div><!-- end LEFT -->

<!-- ═══ RIGHT: INFO CARDS ════════════════════════════ -->
<div style="display:flex;flex-direction:column;gap:14px;position:sticky;top:78px;">

    <!-- What happens next -->
    <div style="background:linear-gradient(135deg,#1447c0,#0d2157);border-radius:14px;padding:18px;">
        <div style="display:flex;align-items:center;gap:7px;margin-bottom:14px;">
            <i class="bi bi-info-circle-fill" style="color:white;font-size:.88rem;"></i>
            <span style="font-size:.82rem;font-weight:700;color:white;">What happens next?</span>
        </div>
        <?php foreach([
            ['bi-send-fill',         '#dbeafe','#1447c0', 'Transaction created with <strong style="color:white;">Pending Verification</strong> status'],
            ['bi-shield-check',      '#dcfce7','#15803d', 'You can verify it immediately from the Pending list'],
            ['bi-bell-fill',         '#fef9c3','#d97706', 'Donor receives a notification when verified'],
            ['bi-graph-up-arrow',    '#fed7aa','#c2410c', 'Campaign progress updates automatically'],
        ] as [$ic,$bg,$co,$txt]): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
            <div style="width:28px;height:28px;background:<?= $bg ?>;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi <?= $ic ?>" style="color:<?= $co ?>;font-size:.76rem;"></i>
            </div>
            <span style="font-size:.76rem;color:rgba(255,255,255,.8);line-height:1.45;"><?= $txt ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Required Fields -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
        <div style="display:flex;align-items:center;gap:7px;margin-bottom:12px;">
            <div style="width:26px;height:26px;background:#dcfce7;border-radius:7px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-list-check" style="color:#15803d;font-size:.76rem;"></i>
            </div>
            <span style="font-size:.82rem;font-weight:700;color:#0f172a;">Required Fields (NFR-06)</span>
        </div>
        <?php foreach(['Donor','Donation amount','Channel','Reference code','Transaction date'] as $f): ?>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:7px;">
            <i class="bi bi-check-circle-fill" style="color:#22c55e;font-size:.78rem;flex-shrink:0;"></i>
            <span style="font-size:.78rem;color:#475569;"><?= $f ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Linked Requirements -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
        <div style="display:flex;align-items:center;gap:7px;margin-bottom:12px;">
            <div style="width:26px;height:26px;background:#ede9fe;border-radius:7px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-link-45deg" style="color:#7c3aed;font-size:.8rem;"></i>
            </div>
            <span style="font-size:.82rem;font-weight:700;color:#0f172a;">Linked Requirements</span>
        </div>
        <?php foreach([
            'FR-01: Create donation/transaction record',
            'FR-07: Manage verification status',
            'FR-08: Maintain verification history',
            'NFR-06: Validate mandatory fields',
        ] as $r): ?>
        <div style="font-size:.74rem;color:#6b7280;margin-bottom:6px;padding:5px 10px;background:#f8fafc;border:1px solid #f1f5f9;border-radius:7px;"><?= $r ?></div>
        <?php endforeach; ?>
    </div>

</div><!-- end RIGHT -->
</div><!-- end GRID -->
</form>

<!-- Bank dropdown overlay to close -->
<div id="bankOverlay" onclick="closeBankDropdown()" style="display:none;position:fixed;inset:0;z-index:9998;"></div>

<script src="<?= SITE_URL ?>/assets/js/banks.js"></script>
<script>
let bankSelected = false;

const BANKS = typeof VIETNAM_BANKS !== 'undefined' ? VIETNAM_BANKS : [];

// Render bank list
function renderBankList(filter = '') {
    const list = document.getElementById('bankList');
    const filtered = filter
        ? BANKS.filter(b => b.name.toLowerCase().includes(filter.toLowerCase()) || b.code.toLowerCase().includes(filter.toLowerCase()))
        : BANKS;

    list.innerHTML = filtered.map(b => `
        <div onclick="selectBank('${b.name.replace(/'/g,"\\'")}', '${(b.logo||'').replace(/'/g,"\\'")}', '${b.color}', '${b.code}')"
             style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:9px;cursor:pointer;transition:background .12s;"
             onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
            <div style="width:40px;height:28px;border-radius:7px;background:white;border:1px solid #f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06);">
                ${b.logo
                    ? `<img src="${b.logo}" style="width:38px;height:26px;object-fit:contain;" onerror="this.parentNode.innerHTML='<span style=&quot;font-size:.58rem;font-weight:800;color:${b.color};&quot;>${b.code}</span>'">`
                    : `<span style="font-size:.6rem;font-weight:800;color:${b.color};">${b.code}</span>`
                }
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:.8rem;font-weight:700;color:#0f172a;">${b.name}</div>
                <div style="font-size:.66rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${b.fullname}</div>
            </div>
        </div>
    `).join('');
}

function toggleBankDropdown() {
    const dd = document.getElementById('bankDropdown');
    const overlay = document.getElementById('bankOverlay');
    const sel = document.getElementById('bankSelector');
    const chevron = document.getElementById('bankChevron');

    if (dd.style.display === 'none' || !dd.style.display) {
        // Position dropdown below selector
        const rect = sel.getBoundingClientRect();
        dd.style.display = 'block';
        dd.style.left = rect.left + 'px';
        dd.style.top  = (rect.bottom + 4) + 'px';
        dd.style.width = Math.max(rect.width, 320) + 'px';
        overlay.style.display = 'block';
        chevron.style.transform = 'rotate(180deg)';
        document.getElementById('bankSearch').value = '';
        renderBankList();
        setTimeout(() => document.getElementById('bankSearch').focus(), 50);
    } else {
        closeBankDropdown();
    }
}

function closeBankDropdown() {
    document.getElementById('bankDropdown').style.display = 'none';
    document.getElementById('bankOverlay').style.display = 'none';
    document.getElementById('bankChevron').style.transform = 'rotate(0deg)';
}

function selectBank(name, logo, color) {
    bankSelected = true;
    document.getElementById('bankNameInput').value = name;
    document.getElementById('bankDisplay').textContent = name;
    document.getElementById('bankDisplay').style.color = '#0f172a';
    document.getElementById('bankDisplay').style.fontWeight = '600';

    const wrap = document.getElementById('bankLogoWrap');
    const fallback = document.getElementById('bankIconFallback');
    wrap.innerHTML = '';
    if (logo) {
        const img = document.createElement('img');
        img.src = logo;
        img.style.cssText = 'width:26px;height:18px;object-fit:contain;';
        img.onerror = function() {
            this.remove();
            const sp = document.createElement('span');
            sp.style.cssText = `font-size:.64rem;font-weight:800;color:${color};`;
            sp.textContent = name.substring(0,4);
            wrap.appendChild(sp);
        };
        wrap.appendChild(img);
    } else {
        const sp = document.createElement('span');
        sp.style.cssText = `font-size:.62rem;font-weight:800;color:${color};`;
        sp.textContent = name.substring(0,4).toUpperCase();
        wrap.appendChild(sp);
    }
    document.getElementById('bankSelector').style.borderColor = '#1447c0';
    closeBankDropdown();
}

function filterBanks(val) {
    renderBankList(val);
}

function onChannelChange(val) {
    // Pre-filter bank list for e-wallet
    if (val === 'e_wallet') {
        const ewallets = ['MoMo','ZaloPay','VNPAY','CAKE','Timo'];
        document.getElementById('bankSearch').value = '';
    }
}

// Close on ESC
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeBankDropdown(); });

// Init if value already set
<?php if (!empty($_POST['bank_name'])): ?>
document.getElementById('bankDisplay').textContent = '<?= htmlspecialchars($_POST['bank_name']) ?>';
document.getElementById('bankDisplay').style.color = '#0f172a';
document.getElementById('bankDisplay').style.fontWeight = '600';
bankSelected = true;
<?php endif; ?>
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
