<?php
$pageTitle  = 'Create New Transaction';
$activePage = 'transactions';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('finance_staff');

$db  = getDB();
$uid = $user['id'];
$errors = [];

// All active campaigns
$campaigns = $db->query("SELECT id, title FROM campaigns WHERE status='active' ORDER BY title");

// All donors
$donors = $db->query("SELECT id, name, email FROM users WHERE role='donor' AND is_active=1 ORDER BY name");

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

    // Validation — FR-01, NFR-06
    if (!$donor_id)       $errors[] = 'Please select a donor.';
    if ($amount <= 0)     $errors[] = 'Donation amount must be greater than 0.';
    if (empty($channel))  $errors[] = 'Please select a donation channel.';
    if (empty($ref_code)) $errors[] = 'Transaction reference code is required.';
    if (empty($txn_date)) $errors[] = 'Transaction date is required.';

    if (empty($errors)) {
        $cid_sql = $campaign_id ? $campaign_id : 'NULL';
        $ch_esc  = $db->real_escape_string($channel);
        $msg_esc = $db->real_escape_string($message);

        // Insert donation
        $db->query("INSERT INTO donations (donor_id, campaign_id, amount, channel, message, is_anonymous, status)
                    VALUES ($donor_id, $cid_sql, $amount, '$ch_esc', '$msg_esc', $is_anon, 'pending')");
        $donation_id = $db->insert_id;

        // Insert transaction
        $ref_esc  = $db->real_escape_string($ref_code);
        $bank_esc = $db->real_escape_string($bank_name);
        $acc_esc  = $db->real_escape_string($acc_number);
        $date_esc = $db->real_escape_string($txn_date);

        $db->query("INSERT INTO transactions
                    (donation_id, reference_code, bank_name, account_number, transaction_date, amount_transferred, verification_status)
                    VALUES ($donation_id, '$ref_esc', '$bank_esc', '$acc_esc', '$date_esc', $txn_amount, 'pending_verification')");
        $txn_id = $db->insert_id;

        // Log history — FR-08
        $db->query("INSERT INTO verification_history (transaction_id, action, performed_by, new_status, notes)
                    VALUES ($txn_id, 'submitted', $uid, 'pending_verification', 'Transaction created by Finance Staff')");

        // Notify donor
        $db->query("INSERT INTO notifications (user_id, title, message, type)
                    VALUES ($donor_id, 'Donation Recorded',
                    'A donation of " . formatCurrency($amount) . " has been recorded on your behalf and is pending verification.',
                    'system')");

        setFlash('success', 'Transaction TX-' . str_pad($txn_id,4,'0',STR_PAD_LEFT) . ' created and set to Pending Verification.');
        redirect(SITE_URL . '/finance_staff/transactions.php');
    }
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 style="font-size:1.3rem;font-weight:700;color:#0f172a;">Create New Transaction</h1>
        <p style="color:#64748b;font-size:.8rem;margin:2px 0 0;">Record a new donation transaction — will be set to Pending Verification (FR-01)</p>
    </div>
    <a href="<?= SITE_URL ?>/finance_staff/transactions.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:9px;color:#64748b;font-size:.81rem;font-weight:600;background:white;transition:all .15s;text-decoration:none;"
       onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
        <i class="bi bi-arrow-left"></i>Back to List
    </a>
</div>

<?php if (!empty($errors)): ?>
<div style="background:#fff1f2;border-left:3px solid #ef4444;padding:12px 16px;border-radius:10px;margin-bottom:18px;">
    <div style="font-weight:700;color:#b91c1c;font-size:.82rem;margin-bottom:6px;">
        <i class="bi bi-exclamation-circle-fill me-2"></i>Please fix the following:
    </div>
    <ul style="margin:0;padding-left:18px;color:#b91c1c;font-size:.8rem;">
        <?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">

    <!-- Main form -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">
        <div style="padding:14px 20px;border-bottom:1px solid #f1f5f9;font-size:.85rem;font-weight:700;color:#0f172a;">
            <i class="bi bi-person me-2" style="color:#1447c0;"></i>Donor & Donation Information
        </div>
        <div style="padding:22px;">
        <form method="POST" id="txnForm">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <!-- Donor -->
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">
                        Donor <span style="color:#ef4444;">*</span>
                    </label>
                    <select name="donor_id" required
                            style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;background:white;outline:none;transition:border-color .15s;"
                            onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'">
                        <option value="">Select donor...</option>
                        <?php if($donors) while($d=$donors->fetch_assoc()): ?>
                        <option value="<?= $d['id'] ?>" <?= ($_POST['donor_id']??'')==$d['id']?'selected':'' ?>>
                            <?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['email']) ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <!-- Campaign -->
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">
                        Campaign / Program
                    </label>
                    <select name="campaign_id"
                            style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;background:white;outline:none;transition:border-color .15s;"
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

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <!-- Amount -->
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">
                        Donation Amount (VND) <span style="color:#ef4444;">*</span>
                    </label>
                    <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;transition:border-color .15s;" onfocusin="this.style.borderColor='#1447c0'" onfocusout="this.style.borderColor='#e2e8f0'">
                        <span style="padding:9px 12px;background:#f8fafc;color:#94a3b8;font-size:.82rem;border-right:1px solid #e2e8f0;">VND</span>
                        <input type="number" name="amount" min="1" required
                               style="flex:1;border:none;outline:none;padding:9px 12px;font-size:.82rem;color:#0f172a;background:white;"
                               placeholder="e.g. 2000000"
                               value="<?= htmlspecialchars($_POST['amount']??'') ?>">
                    </div>
                    <!-- Quick amounts -->
                    <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:7px;">
                        <?php foreach([500000,1000000,2000000,5000000,10000000] as $amt): ?>
                        <button type="button"
                                style="padding:3px 9px;border:1px solid #e2e8f0;border-radius:6px;font-size:.7rem;color:#475569;background:white;cursor:pointer;transition:all .14s;"
                                onmouseover="this.style.background='#eff6ff';this.style.borderColor='#bfdbfe';this.style.color='#1447c0'"
                                onmouseout="this.style.background='white';this.style.borderColor='#e2e8f0';this.style.color='#475569'"
                                onclick="document.querySelector('[name=amount]').value=<?= $amt ?>">
                            <?= number_format($amt/1000) ?>K
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Channel -->
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">
                        Channel <span style="color:#ef4444;">*</span>
                    </label>
                    <select name="channel" required
                            style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;background:white;outline:none;transition:border-color .15s;"
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

            <!-- Divider -->
            <div style="height:1px;background:#f1f5f9;margin:18px 0;"></div>
            <div style="font-size:.82rem;font-weight:700;color:#0f172a;margin-bottom:14px;">
                <i class="bi bi-receipt me-2" style="color:#1447c0;"></i>Transaction Details
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <!-- Reference code -->
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">
                        Reference Code <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="text" name="reference_code" required
                           style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                           placeholder="e.g. VCB2026100101"
                           value="<?= htmlspecialchars($_POST['reference_code']??'') ?>">
                    <div style="font-size:.7rem;color:#94a3b8;margin-top:3px;">From bank/payment receipt</div>
                </div>
                <!-- Txn date -->
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">
                        Transaction Date <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="datetime-local" name="transaction_date" required
                           style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                           value="<?= htmlspecialchars($_POST['transaction_date']??date('Y-m-d\TH:i')) ?>">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <!-- Bank name -->
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">Bank Name</label>
                    <input type="text" name="bank_name"
                           style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                           placeholder="e.g. Vietcombank"
                           value="<?= htmlspecialchars($_POST['bank_name']??'') ?>">
                </div>
                <!-- Amount transferred -->
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">Amount Transferred (VND)</label>
                    <input type="number" name="amount_transferred" min="0"
                           style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                           placeholder="Actual amount on receipt"
                           value="<?= htmlspecialchars($_POST['amount_transferred']??'') ?>">
                    <div style="font-size:.7rem;color:#94a3b8;margin-top:3px;">Should match donation amount</div>
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">Message / Note (Optional)</label>
                <textarea name="message" rows="2"
                          style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;resize:none;"
                          onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                          placeholder="Optional note about this donation..."><?= htmlspecialchars($_POST['message']??'') ?></textarea>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.8rem;color:#475569;">
                    <input type="checkbox" name="is_anonymous" style="width:15px;height:15px;border-radius:4px;"
                           <?= isset($_POST['is_anonymous'])?'checked':'' ?>>
                    Mark as anonymous donation
                </label>
            </div>

            <button type="submit"
                    style="width:100%;padding:12px;background:linear-gradient(135deg,#1447c0,#0d2157);color:white;border:none;border-radius:10px;font-weight:700;font-size:.87rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 14px rgba(20,71,192,.3);transition:all .18s;"
                    onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                <i class="bi bi-plus-circle-fill"></i>Create Transaction (Pending Verification)
            </button>
        </form>
        </div>
    </div>

    <!-- Right sidebar info -->
    <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- FR info box -->
        <div style="background:#eff6ff;border:1px solid #dbeafe;border-radius:14px;padding:18px;">
            <div style="font-size:.8rem;font-weight:700;color:#1447c0;margin-bottom:10px;">
                <i class="bi bi-info-circle-fill me-2"></i>What happens next?
            </div>
            <?php foreach([
                ['bi-1-circle-fill','Transaction created with <strong>Pending Verification</strong> status'],
                ['bi-2-circle-fill','You can verify it immediately from the Pending list'],
                ['bi-3-circle-fill','Donor receives a notification when verified'],
                ['bi-4-circle-fill','Campaign progress updates automatically'],
            ] as [$ico,$txt]): ?>
            <div style="display:flex;gap:9px;margin-bottom:9px;align-items:flex-start;">
                <i class="bi <?= $ico ?>" style="color:#1447c0;font-size:.9rem;flex-shrink:0;margin-top:1px;"></i>
                <span style="font-size:.77rem;color:#1e40af;line-height:1.5;"><?= $txt ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Mandatory fields notice -->
        <div style="background:#fafafa;border:1px solid #e2e8f0;border-radius:14px;padding:18px;">
            <div style="font-size:.8rem;font-weight:700;color:#0f172a;margin-bottom:10px;">
                <i class="bi bi-list-check me-2" style="color:#16a34a;"></i>Required Fields (NFR-06)
            </div>
            <?php foreach(['Donor','Donation amount','Channel','Reference code','Transaction date'] as $f): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:7px;font-size:.77rem;color:#475569;">
                <i class="bi bi-check-circle-fill" style="color:#22c55e;font-size:.8rem;"></i><?= $f ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Linked requirements -->
        <div style="background:#fafafa;border:1px solid #e2e8f0;border-radius:14px;padding:18px;">
            <div style="font-size:.8rem;font-weight:700;color:#0f172a;margin-bottom:10px;">
                <i class="bi bi-link-45deg me-2" style="color:#7c3aed;"></i>Linked Requirements
            </div>
            <?php foreach(['FR-01: Create donation/transaction record','FR-07: Manage verification status','FR-08: Maintain verification history','NFR-06: Validate mandatory fields'] as $r): ?>
            <div style="font-size:.74rem;color:#6b7280;margin-bottom:5px;padding:4px 9px;background:white;border:1px solid #f1f5f9;border-radius:6px;">
                <?= $r ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
