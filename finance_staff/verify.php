<?php
$pageTitle  = 'Verify Transaction';
$activePage = 'verify';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('finance_staff');

$db  = getDB();
$uid = $user['id'];
$id  = (int)($_GET['id'] ?? 0);

// Load transaction
$txn = $db->query("
    SELECT t.*, d.amount, d.channel, d.message, d.is_anonymous,
           d.created_at as donation_date, d.id as donation_id,
           u.name as donor_name, u.email as donor_email, u.phone as donor_phone,
           c.title as campaign_title, c.id as campaign_id,
           c.target_amount, c.raised_amount
    FROM transactions t
    JOIN donations d ON t.donation_id = d.id
    JOIN users u ON d.donor_id = u.id
    LEFT JOIN campaigns c ON d.campaign_id = c.id
    WHERE t.id = $id
    LIMIT 1
")->fetch_assoc();

if (!$txn) {
    setFlash('danger', 'Transaction not found.');
    redirect(SITE_URL . '/finance_staff/transactions.php');
}

// Verification history
$history = $db->query("
    SELECT vh.*, u.name as staff_name
    FROM verification_history vh
    JOIN users u ON vh.performed_by = u.id
    WHERE vh.transaction_id = $id
    ORDER BY vh.created_at ASC
");

// Handle verification action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action           = $_POST['action'] ?? '';
    $rejection_input  = $_POST['rejection_reason'] ?? '';
    $rejection_reason = is_string($rejection_input) ? trim($rejection_input) : '';

    if (!in_array($action, ['verified', 'rejected'])) {
        setFlash('danger', 'Invalid action.');
        redirect(SITE_URL . '/finance_staff/verify.php?id=' . $id);
    }
    if ($action === 'rejected' && empty($rejection_reason)) {
        setFlash('danger', 'A rejection reason is required.');
        redirect(SITE_URL . '/finance_staff/verify.php?id=' . $id);
    }
    if ($txn['verification_status'] !== 'pending_verification') {
        setFlash('warning', 'This transaction has already been processed.');
        redirect(SITE_URL . '/finance_staff/verify.php?id=' . $id);
    }

    $old_status = $txn['verification_status'];
    $now        = date('Y-m-d H:i:s');
    $rej_esc    = $db->real_escape_string($rejection_reason);

    if ($action === 'verified') {
        $db->query("UPDATE transactions SET verification_status='verified', verified_by=$uid, verified_at='$now', rejection_reason=NULL WHERE id=$id");
        $db->query("UPDATE donations SET status='verified' WHERE id={$txn['donation_id']}");
        if ($txn['campaign_id']) {
            $db->query("UPDATE campaigns SET raised_amount = raised_amount + {$txn['amount']} WHERE id={$txn['campaign_id']}");
        }
        $db->query("UPDATE donor_profiles SET total_donated = total_donated + {$txn['amount']} WHERE user_id = (SELECT donor_id FROM donations WHERE id={$txn['donation_id']})");
        $notif_msg  = "Your donation of " . formatCurrency($txn['amount']) . " has been verified successfully! Thank you for your contribution.";
        $notif_type = 'confirmation';
        $flash_msg  = 'Transaction verified successfully!';
    } else {
        $db->query("UPDATE transactions SET verification_status='rejected', verified_by=$uid, verified_at='$now', rejection_reason='$rej_esc' WHERE id=$id");
        $db->query("UPDATE donations SET status='rejected' WHERE id={$txn['donation_id']}");
        $notif_msg  = "Your donation of " . formatCurrency($txn['amount']) . " was rejected. Reason: $rejection_reason";
        $notif_type = 'rejection';
        $flash_msg  = 'Transaction has been rejected.';
    }

    $notes_esc = $db->real_escape_string($action === 'rejected' ? "Rejection reason: $rejection_reason" : 'Transaction verified');
    $db->query("INSERT INTO verification_history (transaction_id, action, performed_by, old_status, new_status, notes)
                VALUES ($id, '$action', $uid, '$old_status', '$action', '$notes_esc')");

    $donor_id_res = $db->query("SELECT donor_id FROM donations WHERE id={$txn['donation_id']}")->fetch_assoc();
    $donor_uid    = $donor_id_res['donor_id'];
    $title_esc    = $db->real_escape_string($action === 'verified' ? 'Donation Verified ✓' : 'Donation Rejected');
    $msg_esc2     = $db->real_escape_string($notif_msg);
    $db->query("INSERT INTO notifications (user_id, title, message, type)
                VALUES ($donor_uid, '$title_esc', '$msg_esc2', '$notif_type')");

    setFlash($action === 'verified' ? 'success' : 'warning', $flash_msg);
    redirect(SITE_URL . '/finance_staff/pending.php');
}

// Channel config
$ch_icons_v  = ['bank_transfer'=>'bi-bank2','e_wallet'=>'bi-phone-fill','credit_card'=>'bi-credit-card-fill','paypal'=>'bi-paypal','cash'=>'bi-cash-stack'];
$ch_labels_v = ['bank_transfer'=>'Bank Transfer','e_wallet'=>'E-Wallet','credit_card'=>'Credit Card','paypal'=>'PayPal','cash'=>'Cash'];
$ch_colors_v = ['bank_transfer'=>['#dbeafe','#1447c0'],'e_wallet'=>['#dcfce7','#15803d'],'credit_card'=>['#ede9fe','#7c3aed'],'paypal'=>['#fff7ed','#c2410c'],'cash'=>['#fef9c3','#854d0e']];
$ch     = $txn['channel'] ?? '';
$ch_ic  = $ch_icons_v[$ch] ?? 'bi-credit-card';
$ch_lb  = $ch_labels_v[$ch] ?? ucfirst(str_replace('_',' ',$ch));
[$ch_bg,$ch_co] = $ch_colors_v[$ch] ?? ['#f1f5f9','#475569'];

// Status config
$s = $txn['verification_status'];
$s_cfg = match($s) {
    'verified' => ['#15803d','#dcfce7','#bbf7d0','Verified','bi-check-circle-fill'],
    'rejected' => ['#991b1b','#fee2e2','#fecaca','Rejected','bi-x-circle-fill'],
    default    => ['#854d0e','#fef9c3','#fde68a','Pending Verification','bi-clock-fill'],
};
[$sc,$sbg,$sbd,$sl,$sico] = $s_cfg;

// Amount match check
$match = $txn['amount_transferred'] && (float)$txn['amount_transferred'] == (float)$txn['amount'];

// Criteria
$criteria = [
    'amount_match'   => 'Donation amount matches the transferred amount',
    'ref_code_valid' => 'Reference code is valid and matches transaction records',
    'donor_info'     => 'Donor information is complete and accurate',
    'txn_date_valid' => 'Transaction date is within acceptable range',
    'channel_match'  => 'Donation channel matches the payment method used',
];
?>

<!-- PAGE HEADER -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <span style="width:36px;height:36px;background:#dbeafe;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-shield-check" style="color:#1447c0;font-size:.9rem;"></i>
            </span>
            Verify Transaction
            <code style="font-size:.72rem;background:#f1f5f9;color:#475569;padding:3px 9px;border-radius:6px;font-weight:600;letter-spacing:.02em;">TX-<?= str_pad($txn['id'],4,'0',STR_PAD_LEFT) ?></code>
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0 0 0 45px;">Review donation and transaction details before making a verification decision</p>
    </div>
    <a href="<?= SITE_URL ?>/finance_staff/pending.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border:1.5px solid #e2e8f0;border-radius:10px;color:#64748b;font-size:.81rem;font-weight:600;background:white;text-decoration:none;white-space:nowrap;transition:all .15s;"
       onmouseover="this.style.background='#f8fafc';this.style.borderColor='#cbd5e1'"
       onmouseout="this.style.background='white';this.style.borderColor='#e2e8f0'">
        <i class="bi bi-arrow-left"></i> Back to Pending
    </a>
</div>

<?php if ($s !== 'pending_verification'): ?>
<div style="background:<?= $s==='verified'?'#f0fdf4':'#fff1f2' ?>;border:1.5px solid <?= $s==='verified'?'#bbf7d0':'#fecaca' ?>;border-radius:12px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:13px;">
    <div style="width:38px;height:38px;background:<?= $sbg ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="bi <?= $sico ?>" style="color:<?= $sc ?>;font-size:1rem;"></i>
    </div>
    <div>
        <div style="font-size:.84rem;font-weight:700;color:<?= $sc ?>;margin-bottom:2px;">
            This transaction has already been <?= $sl ?>.
        </div>
        <?php if ($txn['rejection_reason']): ?>
        <div style="font-size:.76rem;color:<?= $sc ?>;">Reason: <?= htmlspecialchars($txn['rejection_reason']) ?></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- MAIN 2-COLUMN GRID -->
<div style="display:grid;grid-template-columns:1fr 320px;gap:18px;align-items:start;">

<!-- ═══ LEFT COLUMN ═══════════════════════════════════ -->
<div style="display:flex;flex-direction:column;gap:16px;">

    <!-- DONOR INFO -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);">
        <div style="padding:12px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;">
            <div style="width:28px;height:28px;background:#dbeafe;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-person-fill" style="color:#1447c0;font-size:.78rem;"></i>
            </div>
            <span style="font-size:.84rem;font-weight:700;color:#0f172a;">Donor Information</span>
        </div>
        <div style="padding:16px 18px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <?php foreach([
                ['Full Name', htmlspecialchars($txn['is_anonymous'] ? 'Anonymous Donor' : $txn['donor_name'])],
                ['Email',     htmlspecialchars($txn['donor_email'])],
                ['Phone',     htmlspecialchars($txn['donor_phone'] ?? '—')],
                ['Message',   htmlspecialchars($txn['message'] ?? '—')],
            ] as [$lbl,$val]): ?>
            <div>
                <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:4px;"><?= $lbl ?></div>
                <div style="font-size:.82rem;font-weight:600;color:#0f172a;"><?= $val ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- DONATION INFO -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);">
        <div style="padding:12px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;">
            <div style="width:28px;height:28px;background:#dcfce7;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-heart-fill" style="color:#15803d;font-size:.78rem;"></i>
            </div>
            <span style="font-size:.84rem;font-weight:700;color:#0f172a;">Donation Information</span>
        </div>
        <div style="padding:16px 18px;">
            <!-- Amount box -->
            <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #bbf7d0;border-radius:12px;padding:14px 18px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#15803d;margin-bottom:4px;">Donation Amount</div>
                    <div style="font-size:1.5rem;font-weight:900;color:#15803d;letter-spacing:-.03em;"><?= formatCurrency($txn['amount']) ?></div>
                </div>
                <span style="display:inline-flex;align-items:center;gap:6px;background:<?= $ch_bg ?>;color:<?= $ch_co ?>;padding:7px 14px;border-radius:9px;font-size:.77rem;font-weight:700;">
                    <i class="bi <?= $ch_ic ?>" style="font-size:.82rem;"></i><?= $ch_lb ?>
                </span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:4px;">Campaign</div>
                    <div style="font-size:.82rem;font-weight:600;color:#0f172a;"><?= htmlspecialchars($txn['campaign_title'] ?? 'General Donation') ?></div>
                </div>
                <div>
                    <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:4px;">Submitted</div>
                    <div style="font-size:.82rem;font-weight:600;color:#0f172a;"><?= formatDateTime($txn['donation_date']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- TRANSACTION DETAILS -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);">
        <div style="padding:12px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;">
            <div style="width:28px;height:28px;background:#fef9c3;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-bank2" style="color:#d97706;font-size:.78rem;"></i>
            </div>
            <span style="font-size:.84rem;font-weight:700;color:#0f172a;">Transaction Details</span>
        </div>
        <div style="padding:16px 18px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
                <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:4px;">Reference Code</div>
                <code style="font-size:.8rem;color:#1447c0;background:#eff6ff;padding:4px 9px;border-radius:6px;border:1px solid #dbeafe;font-weight:700;"><?= htmlspecialchars($txn['reference_code'] ?? 'N/A') ?></code>
            </div>
            <div>
                <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:4px;">Bank Name</div>
                <div style="font-size:.82rem;font-weight:600;color:#0f172a;"><?= htmlspecialchars($txn['bank_name'] ?? '—') ?></div>
            </div>
            <div>
                <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:4px;">Account Number</div>
                <div style="font-size:.82rem;font-weight:600;color:#0f172a;font-family:ui-monospace,monospace;"><?= htmlspecialchars($txn['account_number'] ?? '—') ?></div>
            </div>
            <div>
                <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:4px;">Transaction Date</div>
                <div style="font-size:.82rem;font-weight:600;color:#0f172a;"><?= formatDateTime($txn['transaction_date']) ?></div>
            </div>
            <div>
                <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:5px;">Amount Transferred</div>
                <div style="display:inline-flex;align-items:center;gap:6px;background:<?= $match?'#f0fdf4':'#fffbeb' ?>;border:1px solid <?= $match?'#bbf7d0':'#fde68a' ?>;padding:5px 10px;border-radius:8px;">
                    <span style="font-size:.82rem;font-weight:800;color:<?= $match?'#15803d':'#d97706' ?>;"><?= $txn['amount_transferred'] ? formatCurrency($txn['amount_transferred']) : '—' ?></span>
                    <i class="bi bi-<?= $match?'check-circle-fill':'exclamation-triangle-fill' ?>" style="color:<?= $match?'#16a34a':'#d97706' ?>;font-size:.78rem;"></i>
                </div>
            </div>
            <div>
                <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;margin-bottom:5px;">Current Status</div>
                <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $sbg ?>;color:<?= $sc ?>;border:1px solid <?= $sbd ?>;padding:5px 10px;border-radius:20px;font-size:.71rem;font-weight:700;">
                    <i class="bi <?= $sico ?>" style="font-size:.68rem;"></i><?= $sl ?>
                </span>
            </div>
        </div>
    </div>

</div><!-- end LEFT -->

<!-- ═══ RIGHT COLUMN ══════════════════════════════════ -->
<div style="display:flex;flex-direction:column;gap:14px;position:sticky;top:78px;">

<?php if ($s === 'pending_verification'): ?>

    <!-- VERIFICATION CRITERIA -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);">
        <div style="padding:13px 18px;background:linear-gradient(135deg,#1447c0,#0d2157);display:flex;align-items:center;gap:8px;">
            <i class="bi bi-clipboard-check" style="color:white;font-size:.88rem;"></i>
            <span style="font-size:.84rem;font-weight:700;color:white;">Verification Criteria</span>
        </div>
        <div style="padding:14px 16px;">
            <p style="font-size:.74rem;color:#64748b;margin-bottom:12px;">Check all criteria before making a decision:</p>
            <?php foreach ($criteria as $key => $label): ?>
            <label style="display:flex;align-items:flex-start;gap:9px;cursor:pointer;padding:8px 11px;border-radius:8px;margin-bottom:5px;border:1.5px solid #f1f5f9;background:white;transition:all .14s;"
                   onmouseover="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc'"
                   onmouseout="this.style.borderColor='#f1f5f9';this.style.background='white'">
                <input type="checkbox" class="criteria-check" onchange="updateCriteria()"
                       style="width:15px;height:15px;border-radius:3px;margin-top:2px;cursor:pointer;flex-shrink:0;accent-color:#1447c0;">
                <span style="font-size:.76rem;color:#374151;line-height:1.45;font-weight:500;"><?= $label ?></span>
            </label>
            <?php endforeach; ?>
            <div style="margin-top:12px;padding:9px 11px;background:#f8fafc;border-radius:8px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                    <span style="font-size:.7rem;font-weight:600;color:#64748b;">Criteria completed</span>
                    <span id="criteriaCount" style="font-size:.7rem;font-weight:800;color:#1447c0;">0 / <?= count($criteria) ?></span>
                </div>
                <div style="height:4px;background:#e2e8f0;border-radius:3px;overflow:hidden;">
                    <div id="criteriaBar" style="height:100%;width:0%;background:linear-gradient(90deg,#3b82f6,#1447c0);border-radius:3px;transition:width .3s;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- VERIFICATION DECISION -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);">
        <div style="padding:12px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;">
            <div style="width:28px;height:28px;background:#fef9c3;border-radius:7px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-gavel" style="color:#d97706;font-size:.78rem;"></i>
            </div>
            <span style="font-size:.84rem;font-weight:700;color:#0f172a;">Verification Decision</span>
        </div>
        <div style="padding:14px 16px;">
            <form method="POST" id="verificationForm">
                <input type="hidden" name="action" id="verificationAction" value="">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-bottom:12px;">
                    <button type="button" id="verifyBtn" onclick="selectAction('verified')"
                            style="padding:10px 8px;border:2px solid #bbf7d0;background:#f0fdf4;color:#15803d;border-radius:10px;font-size:.81rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .18s;">
                        <i class="bi bi-check-circle-fill" style="font-size:.86rem;"></i> Verify
                    </button>
                    <button type="button" id="rejectBtn" onclick="selectAction('rejected')"
                            style="padding:10px 8px;border:2px solid #fecaca;background:#fff1f2;color:#dc2626;border-radius:10px;font-size:.81rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .18s;">
                        <i class="bi bi-x-circle-fill" style="font-size:.86rem;"></i> Reject
                    </button>
                </div>
                <div id="actionIndicator" style="display:none;padding:9px 12px;border-radius:8px;font-size:.76rem;font-weight:600;margin-bottom:11px;align-items:center;gap:6px;"></div>
                <div id="rejectionReasonDiv" style="display:none;margin-bottom:12px;">
                    <label style="display:block;font-size:.73rem;font-weight:700;color:#dc2626;margin-bottom:5px;">
                        <i class="bi bi-exclamation-triangle-fill" style="margin-right:3px;"></i> Rejection Reason <span style="color:#dc2626;">*</span>
                    </label>
                    <textarea name="rejection_reason" id="rejection_reason_input" rows="3"
                              style="width:100%;padding:9px 11px;border:1.5px solid #fecaca;border-radius:9px;font-size:.8rem;color:#0f172a;outline:none;resize:none;background:#fff;"
                              placeholder="Explain why this transaction is being rejected..."></textarea>
                    <div style="font-size:.68rem;color:#dc2626;margin-top:3px;">This reason will be sent to the donor.</div>
                </div>
                <button type="submit" id="submitBtn" onclick="return confirmAction()"
                        style="display:none;width:100%;padding:12px;border:none;border-radius:10px;font-weight:700;font-size:.86rem;cursor:pointer;align-items:center;justify-content:center;gap:7px;"></button>
            </form>
        </div>
    </div>

<?php endif; ?>

    <!-- VERIFICATION HISTORY -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);">
        <div style="padding:12px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:8px;">
            <div style="width:28px;height:28px;background:#ede9fe;border-radius:7px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-clock-history" style="color:#7c3aed;font-size:.78rem;"></i>
            </div>
            <span style="font-size:.84rem;font-weight:700;color:#0f172a;">Verification History</span>
        </div>
        <div style="padding:4px 0;">
        <?php if ($history && $history->num_rows > 0):
            $history->data_seek(0);
            while ($h = $history->fetch_assoc()):
                $ha = $h['action'];
                $h_map = [
                    'verified'  => ['#dcfce7','#15803d','bi-check-circle-fill'],
                    'rejected'  => ['#fee2e2','#991b1b','bi-x-circle-fill'],
                    'submitted' => ['#fef9c3','#854d0e','bi-send-fill'],
                ];
                [$h_bg,$h_co,$h_ic] = $h_map[$ha] ?? ['#fef9c3','#854d0e','bi-send-fill'];
        ?>
        <div style="display:flex;gap:11px;padding:11px 16px;border-bottom:1px solid #f9fafb;"
             onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">
            <div style="width:34px;height:34px;border-radius:50%;background:<?= $h_bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">
                <i class="bi <?= $h_ic ?>" style="color:<?= $h_co ?>;font-size:.85rem;"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:.78rem;font-weight:700;color:#0f172a;margin-bottom:1px;"><?= ucfirst($ha) ?></div>
                <div style="font-size:.7rem;color:#64748b;">By: <strong><?= htmlspecialchars($h['staff_name']) ?></strong></div>
                <?php if ($h['notes']): ?>
                <div style="font-size:.69rem;color:#94a3b8;margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($h['notes']) ?></div>
                <?php endif; ?>
                <div style="font-size:.67rem;color:#94a3b8;margin-top:2px;"><?= formatDateTime($h['created_at']) ?></div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div style="padding:28px 16px;text-align:center;color:#94a3b8;font-size:.79rem;">No history yet</div>
        <?php endif; ?>
        </div>
    </div>

</div><!-- end RIGHT -->
</div><!-- end GRID -->

<script>
const totalCriteria = <?= count($criteria) ?>;
let selectedAction = '';

function updateCriteria() {
    const checked = document.querySelectorAll('.criteria-check:checked').length;
    const pct = (checked / totalCriteria) * 100;
    document.getElementById('criteriaBar').style.width = pct + '%';
    document.getElementById('criteriaCount').textContent = checked + ' / ' + totalCriteria;
    document.getElementById('criteriaBar').style.background =
        pct === 100 ? 'linear-gradient(90deg,#22c55e,#16a34a)' : 'linear-gradient(90deg,#3b82f6,#1447c0)';
}

function selectAction(action) {
    selectedAction = action;
    document.getElementById('verificationAction').value = action;
    const vBtn = document.getElementById('verifyBtn');
    const rBtn = document.getElementById('rejectBtn');
    const rDiv = document.getElementById('rejectionReasonDiv');
    const ind  = document.getElementById('actionIndicator');
    const sub  = document.getElementById('submitBtn');

    if (action === 'verified') {
        vBtn.style.cssText = 'padding:10px 8px;border:2px solid #15803d;background:#15803d;color:white;border-radius:10px;font-size:.81rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 3px 10px rgba(21,128,61,.25);';
        rBtn.style.cssText = 'padding:10px 8px;border:2px solid #fecaca;background:#fff1f2;color:#dc2626;border-radius:10px;font-size:.81rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;';
        rDiv.style.display = 'none';
        ind.style.cssText  = 'display:flex;padding:9px 12px;border-radius:8px;font-size:.76rem;font-weight:600;margin-bottom:11px;align-items:center;gap:6px;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;';
        ind.innerHTML      = '<i class="bi bi-check-circle-fill"></i> Selected: <strong>Verify</strong> this transaction';
        sub.style.cssText  = 'display:flex;width:100%;padding:12px;border:none;border-radius:10px;font-weight:700;font-size:.86rem;cursor:pointer;align-items:center;justify-content:center;gap:7px;background:linear-gradient(135deg,#16a34a,#15803d);color:white;box-shadow:0 4px 14px rgba(21,128,61,.28);';
        sub.innerHTML      = '<i class="bi bi-check-circle-fill"></i> Confirm Verification';
    } else {
        rBtn.style.cssText = 'padding:10px 8px;border:2px solid #dc2626;background:#dc2626;color:white;border-radius:10px;font-size:.81rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 3px 10px rgba(220,38,38,.25);';
        vBtn.style.cssText = 'padding:10px 8px;border:2px solid #bbf7d0;background:#f0fdf4;color:#15803d;border-radius:10px;font-size:.81rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;';
        rDiv.style.display = 'block';
        ind.style.cssText  = 'display:flex;padding:9px 12px;border-radius:8px;font-size:.76rem;font-weight:600;margin-bottom:11px;align-items:center;gap:6px;background:#fff1f2;color:#dc2626;border:1px solid #fecaca;';
        ind.innerHTML      = '<i class="bi bi-x-circle-fill"></i> Selected: <strong>Reject</strong> this transaction';
        sub.style.cssText  = 'display:flex;width:100%;padding:12px;border:none;border-radius:10px;font-weight:700;font-size:.86rem;cursor:pointer;align-items:center;justify-content:center;gap:7px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;box-shadow:0 4px 14px rgba(239,68,68,.28);';
        sub.innerHTML      = '<i class="bi bi-x-circle-fill"></i> Confirm Rejection';
    }
}

function confirmAction() {
    if (!selectedAction) { alert('Please select Verify or Reject.'); return false; }
    if (selectedAction === 'rejected') {
        const reason = document.getElementById('rejection_reason_input').value.trim();
        if (!reason) { alert('Please provide a rejection reason.'); return false; }
    }
    return confirm(selectedAction === 'verified' ? '✓ Confirm VERIFY this transaction?' : '✗ Confirm REJECT this transaction?');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
