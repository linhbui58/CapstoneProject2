<?php
$pageTitle  = 'Create New Transaction';
$activePage = 'transactions';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/donation_acknowledgement.php';
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

        // Finance Staff creates transaction → auto-verified immediately
        $db->query("INSERT INTO donations (donor_id, campaign_id, amount, channel, message, is_anonymous, status)
                    VALUES ($donor_id, $cid_sql, $amount, '$ch_esc', '$msg_esc', $is_anon, 'verified')");
        $donation_id = $db->insert_id;

        $ref_esc  = $db->real_escape_string($ref_code);
        $bank_esc = $db->real_escape_string($bank_name);
        $acc_esc  = $db->real_escape_string($acc_number);
        $date_esc = $db->real_escape_string($txn_date);
        $txn_amount_val = $txn_amount > 0 ? $txn_amount : $amount;

        $db->query("INSERT INTO transactions
                    (donation_id, reference_code, bank_name, account_number, transaction_date, amount_transferred, verification_status, verified_by, verified_at)
                    VALUES ($donation_id, '$ref_esc', '$bank_esc', '$acc_esc', '$date_esc', $txn_amount_val, 'verified', $uid, NOW())");
        $txn_id = $db->insert_id;

        // Update campaign raised amount if linked
        if ($campaign_id) {
            $db->query("UPDATE campaigns SET raised_amount = raised_amount + $amount WHERE id = $campaign_id");
        }

        // Update donor profile total
        $db->query("UPDATE donor_profiles SET total_donated = total_donated + $amount WHERE user_id = $donor_id");

        // Verification history — submitted + verified in one step
        $db->query("INSERT INTO verification_history (transaction_id, action, performed_by, new_status, notes)
                    VALUES ($txn_id, 'submitted', $uid, 'pending_verification', 'Transaction created by Finance Staff')");
        $db->query("INSERT INTO verification_history (transaction_id, action, performed_by, old_status, new_status, notes)
                    VALUES ($txn_id, 'verified', $uid, 'pending_verification', 'verified', 'Auto-verified: created directly by Finance Staff')");

        // Notify donor
        $camp_name = $campaign_id
            ? $db->query("SELECT title FROM campaigns WHERE id=$campaign_id")->fetch_assoc()['title'] ?? 'the campaign'
            : 'our fundraising program';
        $camp_esc = $db->real_escape_string($camp_name);
        $db->query("INSERT INTO notifications (user_id, title, message, type)
                    VALUES ($donor_id, 'Donation Verified ✓',
                    'Your donation of " . formatCurrency($amount) . " to \"$camp_esc\" has been recorded and verified by Finance Staff. Thank you! Your printable receipt is ready under My Donations > View Details.',
                    'confirmation')");

        $emailStatusMessage = '';
        try {
            $emailResult = $db->query("
                SELECT d.id AS donation_id, d.amount, d.created_at AS donation_date,
                       t.reference_code, u.name AS donor_name, u.email AS donor_email,
                       c.title AS campaign_title
                FROM donations d
                JOIN transactions t ON t.donation_id = d.id
                JOIN users u ON u.id = d.donor_id
                LEFT JOIN campaigns c ON c.id = d.campaign_id
                WHERE t.id = $txn_id AND t.verification_status = 'verified'
                LIMIT 1
            ");
            if (!$emailResult) {
                throw new RuntimeException('Unable to load verified donation details for email.');
            }
            $emailData = $emailResult->fetch_assoc();
            if (!$emailData) {
                throw new RuntimeException('Verified donation details were not found for email.');
            }
            sendDonationAcknowledgement($emailData);
            $emailStatusMessage = ' A thank-you email was sent to the donor.';
        } catch (Throwable $emailException) {
            error_log('Donation acknowledgement email failed for transaction ' . $txn_id . ': ' . $emailException->getMessage());
            $emailStatusMessage = ' The in-app notification and receipt are available, but the thank-you email could not be sent. Check mail configuration.';
        }

        setFlash('success', 'Transaction TX-' . str_pad($txn_id,4,'0',STR_PAD_LEFT) . ' created and verified successfully.' . $emailStatusMessage);
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
        <p style="color:#64748b;font-size:.79rem;margin:0 0 0 45px;">Record a donation — Finance Staff-created transactions are <strong style="color:#15803d;">auto-verified immediately</strong></p>
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
                <div style="position:relative;">
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">Donor <span style="color:#ef4444;">*</span></label>
                    <input type="hidden" name="donor_id" id="donorIdInput" value="<?= htmlspecialchars($_POST['donor_id']??'') ?>" required>
                    <!-- Search input -->
                    <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;height:42px;transition:border-color .15s;" id="donorInputWrap"
                         onfocusin="this.style.borderColor='#1447c0'" onfocusout="this.style.borderColor='#e2e8f0'">
                        <span style="padding:0 11px;background:#f8fafc;border-right:1px solid #e2e8f0;color:#94a3b8;height:100%;display:flex;align-items:center;">
                            <i class="bi bi-search" style="font-size:.8rem;"></i>
                        </span>
                        <input type="text" id="donorSearch" autocomplete="off"
                               style="flex:1;border:none;outline:none;padding:0 12px;font-size:.83rem;color:#0f172a;background:white;height:100%;"
                               placeholder="Type name or email to search..."
                               value="<?php
                                   if (!empty($_POST['donor_id'])) {
                                       $d_row = $db->query("SELECT name,email FROM users WHERE id=".(int)$_POST['donor_id'])->fetch_assoc();
                                       echo htmlspecialchars(($d_row['name']??'') . ' — ' . ($d_row['email']??''));
                                   }
                               ?>"
                               oninput="filterDonors(this.value)"
                               onfocus="showDonorDropdown()"
                        >
                        <button type="button" id="donorClearBtn"
                                onclick="clearDonor()"
                                style="padding:0 11px;background:transparent;border:none;color:#94a3b8;cursor:pointer;display:<?= !empty($_POST['donor_id'])?'flex':'none' ?>;align-items:center;"
                                title="Clear">
                            <i class="bi bi-x-lg" style="font-size:.72rem;"></i>
                        </button>
                    </div>
                    <!-- Dropdown results -->
                    <div id="donorDropdown"
                         style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:white;border:1.5px solid #e2e8f0;border-radius:11px;box-shadow:0 8px 28px rgba(0,0,0,.12);z-index:999;max-height:240px;overflow-y:auto;">
                    </div>
                    <div id="donorSelected" style="font-size:.69rem;margin-top:4px;color:<?= !empty($_POST['donor_id'])?'#15803d':'#94a3b8' ?>;">
                        <?= !empty($_POST['donor_id']) ? '<i class="bi bi-check-circle-fill"></i> Donor selected' : 'Start typing to search donors' ?>
                    </div>
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
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">
                        <span id="refCodeLabel">Reference Code</span> <span style="color:#ef4444;">*</span>
                    </label>
                    <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;height:42px;transition:border-color .15s;" onfocusin="this.style.borderColor='#1447c0'" onfocusout="this.style.borderColor='#e2e8f0'">
                        <span style="padding:0 11px;background:#f8fafc;border-right:1px solid #e2e8f0;color:#94a3b8;font-size:.88rem;height:100%;display:flex;align-items:center;">#</span>
                        <input type="text" name="reference_code" required
                               style="flex:1;border:none;outline:none;padding:0 12px;font-size:.82rem;color:#0f172a;background:white;height:100%;"
                               placeholder="e.g. VCB2026100101"
                               value="<?= htmlspecialchars($_POST['reference_code']??'') ?>">
                    </div>
                    <div id="refCodeHint" style="font-size:.69rem;color:#94a3b8;margin-top:4px;">From bank/payment receipt</div>
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
                <div id="bankSelectorRow">
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
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">
                        <span id="amtTransferredLabel">Amount Transferred (VND)</span>
                    </label>
                    <input type="number" name="amount_transferred" id="transferAmountInput" min="0"
                           style="width:100%;height:42px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                           placeholder="Actual amount on receipt"
                           value="<?= htmlspecialchars($_POST['amount_transferred']??'') ?>">
                    <div id="amtTransferredHint" style="font-size:.69rem;color:#94a3b8;margin-top:4px;">Should match donation amount</div>
                </div>
            </div>

            <!-- Row 5: Account Number -->
            <div style="margin-bottom:16px;" id="accountNumberRow">
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:6px;">Account / Transaction Number</label>
                <input type="text" name="account_number"
                       style="width:100%;height:42px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="e.g. 1234567890"
                       value="<?= htmlspecialchars($_POST['account_number']??'') ?>">
            </div>

            <!-- Cash info banner -->
            <div id="cashInfoBanner" style="display:none;background:linear-gradient(135deg,#fffbeb,#fef9c3);border:1.5px solid #fde68a;border-radius:11px;padding:13px 16px;margin-bottom:16px;align-items:flex-start;gap:10px;">
                <i class="bi bi-cash-coin" style="color:#d97706;font-size:1.1rem;flex-shrink:0;margin-top:1px;"></i>
                <div>
                    <div style="font-size:.79rem;font-weight:700;color:#854d0e;margin-bottom:4px;">Cash Donation — What to fill in</div>
                    <ul style="margin:0;padding-left:16px;font-size:.75rem;color:#92400e;line-height:1.7;">
                        <li><strong>Receipt Number</strong>: Auto-generated above — you can edit it (e.g. CASH-20261023-001)</li>
                        <li><strong>Cash Amount Received</strong>: Enter the exact cash amount collected</li>
                        <li><strong>Transaction Date</strong>: Date and time you received the cash</li>
                        <li>Bank and account number fields are hidden — not needed for cash</li>
                    </ul>
                </div>
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
                <i class="bi bi-check-circle-fill" style="font-size:.9rem;"></i>
                Create &amp; Verify Transaction
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
            ['bi-check-circle-fill', '#dcfce7','#15803d', 'Transaction created and <strong style="color:white;">auto-verified</strong> immediately'],
            ['bi-graph-up-arrow',    '#fed7aa','#c2410c', 'Campaign raised amount updates automatically'],
            ['bi-bell-fill',         '#fef9c3','#d97706', 'Donor receives a verified confirmation notification'],
            ['bi-link-45deg',        '#dbeafe','#1447c0', 'If no campaign selected, use Link Donations to assign'],
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
            <span style="font-size:.82rem;font-weight:700;color:#0f172a;">Required Fields</span>
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
    const isCash    = val === 'cash';
    const isOnline  = ['bank_transfer','e_wallet','credit_card','paypal'].includes(val);

    // ── Reference Code label + placeholder ──────────
    const refLabel = document.getElementById('refCodeLabel');
    const refInput = document.querySelector('input[name=reference_code]');
    const refHint  = document.getElementById('refCodeHint');
    if (isCash) {
        refLabel.textContent = 'Receipt Number *';
        refInput.placeholder = 'e.g. CASH-2026-001';
        refHint.textContent  = 'Self-generated receipt number for audit trail';
        // Auto-generate suggestion
        if (!refInput.value) {
            const now  = new Date();
            const ymd  = now.getFullYear() + String(now.getMonth()+1).padStart(2,'0') + String(now.getDate()).padStart(2,'0');
            const seq  = String(Math.floor(Math.random()*900)+100);
            refInput.value = 'CASH-' + ymd + '-' + seq;
        }
    } else {
        refLabel.textContent = 'Reference Code *';
        refInput.placeholder = 'e.g. VCB2026100101';
        refHint.textContent  = 'From bank/payment receipt';
        if (refInput.value.startsWith('CASH-')) refInput.value = '';
    }

    // ── Bank selector: hide for cash ────────────────
    const bankRow = document.getElementById('bankSelectorRow');
    if (bankRow) bankRow.style.display = isCash ? 'none' : '';

    // ── Account number: hide for cash ───────────────
    const accRow = document.getElementById('accountNumberRow');
    if (accRow) accRow.style.display = isCash ? 'none' : '';

    // ── Amount transferred label ─────────────────────
    const amtLabel = document.getElementById('amtTransferredLabel');
    if (amtLabel) amtLabel.textContent = isCash ? 'Cash Amount Received (VND)' : 'Amount Transferred (VND)';
    const amtHint = document.getElementById('amtTransferredHint');
    if (amtHint) amtHint.textContent = isCash ? 'Actual cash amount collected' : 'Should match donation amount';

    // ── Cash info banner ─────────────────────────────
    const cashBanner = document.getElementById('cashInfoBanner');
    if (cashBanner) cashBanner.style.display = isCash ? 'flex' : 'none';
}

// Close on ESC
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeBankDropdown(); closeDonorDropdown(); } });

// Init if value already set
<?php if (!empty($_POST['bank_name'])): ?>
document.getElementById('bankDisplay').textContent = '<?= htmlspecialchars($_POST['bank_name']) ?>';
document.getElementById('bankDisplay').style.color = '#0f172a';
document.getElementById('bankDisplay').style.fontWeight = '600';
bankSelected = true;
<?php endif; ?>

// Trigger channel change on page load if channel already selected (e.g. POST-back)
<?php if (!empty($_POST['channel'])): ?>
onChannelChange('<?= htmlspecialchars($_POST['channel']) ?>');
<?php endif; ?>

// ── Donor Search Autocomplete ──────────────────────
// Build donor data from PHP
const DONORS = <?php
    $donor_list = [];
    $dr = $db->query("SELECT id, name, email FROM users WHERE role='donor' AND is_active=1 ORDER BY name");
    if ($dr) while ($row = $dr->fetch_assoc()) {
        $donor_list[] = ['id'=>(int)$row['id'], 'name'=>$row['name'], 'email'=>$row['email']];
    }
    echo json_encode($donor_list, JSON_UNESCAPED_UNICODE);
?>;

let donorDropdownVisible = false;

function filterDonors(query) {
    const dd = document.getElementById('donorDropdown');
    if (!query.trim()) { closeDonorDropdown(); return; }

    const q = query.toLowerCase();
    const matches = DONORS.filter(d =>
        d.name.toLowerCase().includes(q) || d.email.toLowerCase().includes(q)
    ).slice(0, 8);

    if (matches.length === 0) {
        dd.innerHTML = '<div style="padding:16px;text-align:center;font-size:.79rem;color:#94a3b8;">No donors found</div>';
    } else {
        dd.innerHTML = matches.map(d => `
            <div onclick="selectDonor(${d.id}, '${d.name.replace(/'/g,"\\'")}', '${d.email.replace(/'/g,"\\'")}' )"
                 style="display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;transition:background .12s;border-bottom:1px solid #f8fafc;"
                 onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='white'">
                <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1447c0,#3b82f6);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;color:white;flex-shrink:0;">
                    ${d.name.trim().charAt(0).toUpperCase()}
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:.82rem;font-weight:700;color:#0f172a;">${d.name}</div>
                    <div style="font-size:.71rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${d.email}</div>
                </div>
            </div>
        `).join('');
    }
    dd.style.display = 'block';
    donorDropdownVisible = true;
}

function showDonorDropdown() {
    const q = document.getElementById('donorSearch').value;
    if (q.trim().length > 0) filterDonors(q);
}

function selectDonor(id, name, email) {
    document.getElementById('donorIdInput').value = id;
    document.getElementById('donorSearch').value  = name + ' — ' + email;
    document.getElementById('donorClearBtn').style.display = 'flex';
    document.getElementById('donorSelected').innerHTML = '<i class="bi bi-check-circle-fill" style="color:#22c55e;"></i> <span style="color:#15803d;font-weight:600;">' + name + ' selected</span>';
    document.getElementById('donorInputWrap').style.borderColor = '#22c55e';
    closeDonorDropdown();
}

function clearDonor() {
    document.getElementById('donorIdInput').value = '';
    document.getElementById('donorSearch').value  = '';
    document.getElementById('donorClearBtn').style.display = 'none';
    document.getElementById('donorSelected').innerHTML = 'Start typing to search donors';
    document.getElementById('donorSelected').style.color = '#94a3b8';
    document.getElementById('donorInputWrap').style.borderColor = '#e2e8f0';
    document.getElementById('donorSearch').focus();
}

function closeDonorDropdown() {
    document.getElementById('donorDropdown').style.display = 'none';
    donorDropdownVisible = false;
}

// Close donor dropdown on outside click
document.addEventListener('click', e => {
    const wrap = document.getElementById('donorInputWrap');
    const dd   = document.getElementById('donorDropdown');
    if (wrap && dd && !wrap.contains(e.target) && !dd.contains(e.target)) {
        closeDonorDropdown();
    }
});

// Validate hidden donor_id before submit
document.getElementById('txnForm').addEventListener('submit', function(e) {
    const donorId = document.getElementById('donorIdInput').value;
    if (!donorId) {
        e.preventDefault();
        document.getElementById('donorInputWrap').style.borderColor = '#ef4444';
        document.getElementById('donorSelected').innerHTML = '<span style="color:#ef4444;font-weight:600;"><i class="bi bi-exclamation-circle-fill"></i> Please select a donor from the list</span>';
        document.getElementById('donorSearch').focus();
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
