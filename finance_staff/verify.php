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
    $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');

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

    // Update transaction
    if ($action === 'verified') {
        $db->query("UPDATE transactions SET verification_status='verified', verified_by=$uid, verified_at='$now', rejection_reason=NULL WHERE id=$id");
        $db->query("UPDATE donations SET status='verified' WHERE id={$txn['donation_id']}");

        // Update campaign raised amount if linked
        if ($txn['campaign_id']) {
            $db->query("UPDATE campaigns SET raised_amount = raised_amount + {$txn['amount']} WHERE id={$txn['campaign_id']}");
        }

        // Update donor total
        $db->query("UPDATE donor_profiles SET total_donated = total_donated + {$txn['amount']} WHERE user_id = (SELECT donor_id FROM donations WHERE id={$txn['donation_id']})");

        $notif_msg = "Your donation of " . formatCurrency($txn['amount']) . " has been verified successfully! Thank you for your contribution.";
        $notif_type = 'confirmation';
        $flash_msg  = 'Transaction verified successfully!';
    } else {
        $db->query("UPDATE transactions SET verification_status='rejected', verified_by=$uid, verified_at='$now', rejection_reason='$rej_esc' WHERE id=$id");
        $db->query("UPDATE donations SET status='rejected' WHERE id={$txn['donation_id']}");

        $notif_msg  = "Your donation of " . formatCurrency($txn['amount']) . " was rejected. Reason: $rejection_reason";
        $notif_type = 'rejection';
        $flash_msg  = 'Transaction has been rejected.';
    }

    // Log verification history
    $notes_esc = $db->real_escape_string($action === 'rejected' ? "Rejection reason: $rejection_reason" : 'Transaction verified');
    $db->query("INSERT INTO verification_history
                (transaction_id, action, performed_by, old_status, new_status, notes)
                VALUES ($id, '$action', $uid, '$old_status', '$action', '$notes_esc')");

    // Notify donor
    $donor_id_res = $db->query("SELECT donor_id FROM donations WHERE id={$txn['donation_id']}")->fetch_assoc();
    $donor_uid = $donor_id_res['donor_id'];
    $title_esc = $db->real_escape_string($action === 'verified' ? 'Donation Verified ✓' : 'Donation Rejected');
    $msg_esc2  = $db->real_escape_string($notif_msg);
    $db->query("INSERT INTO notifications (user_id, title, message, type)
                VALUES ($donor_uid, '$title_esc', '$msg_esc2', '$notif_type')");

    setFlash($action === 'verified' ? 'success' : 'warning', $flash_msg);
    redirect(SITE_URL . '/finance_staff/pending.php');
}

// Verification criteria checklist
$criteria = [
    'amount_match'    => 'Donation amount matches the transferred amount',
    'ref_code_valid'  => 'Reference code is valid and matches transaction records',
    'donor_info'      => 'Donor information is complete and accurate',
    'txn_date_valid'  => 'Transaction date is within acceptable range',
    'channel_match'   => 'Donation channel matches the payment method used',
];
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-shield-check me-2 text-primary"></i>Verify Transaction</h1>
        <p class="text-muted mb-0">Review donation and transaction details before verifying</p>
    </div>
    <a href="<?= SITE_URL ?>/finance_staff/pending.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Pending
    </a>
</div>

<?php if ($txn['verification_status'] !== 'pending_verification'): ?>
    <div class="alert alert-<?= $txn['verification_status'] === 'verified' ? 'success' : 'danger' ?> d-flex gap-3 align-items-center">
        <i class="bi bi-<?= $txn['verification_status'] === 'verified' ? 'check-circle-fill' : 'x-circle-fill' ?> fs-4"></i>
        <div>
            <strong>This transaction has already been <?= $txn['verification_status'] ?>.</strong>
            <?php if ($txn['rejection_reason']): ?>
                <div class="small mt-1">Rejection reason: <?= htmlspecialchars($txn['rejection_reason']) ?></div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left: Details -->
    <div class="col-lg-7">

        <!-- Donor Info -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person me-2"></i>Donor Information</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="small text-muted">Full Name</div>
                        <div class="fw-semibold"><?= htmlspecialchars($txn['is_anonymous'] ? 'Anonymous' : $txn['donor_name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Email</div>
                        <div class="fw-semibold"><?= htmlspecialchars($txn['donor_email']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Phone</div>
                        <div class="fw-semibold"><?= htmlspecialchars($txn['donor_phone'] ?? '—') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Message</div>
                        <div class="fw-semibold"><?= htmlspecialchars($txn['message'] ?? '—') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Donation Info -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-heart me-2"></i>Donation Information</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="small text-muted">Donation Amount</div>
                        <div class="fw-bold text-success fs-5"><?= formatCurrency($txn['amount']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Channel</div>
                        <div class="fw-semibold"><?= ucfirst(str_replace('_',' ',$txn['channel'])) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Campaign</div>
                        <div class="fw-semibold"><?= htmlspecialchars($txn['campaign_title'] ?? 'General Donation') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Submitted</div>
                        <div class="fw-semibold"><?= formatDateTime($txn['donation_date']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction Info -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-bank me-2"></i>Transaction Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="small text-muted">Reference Code</div>
                        <code class="fs-6"><?= htmlspecialchars($txn['reference_code'] ?? 'N/A') ?></code>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Bank Name</div>
                        <div class="fw-semibold"><?= htmlspecialchars($txn['bank_name'] ?? '—') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Account Number</div>
                        <div class="fw-semibold"><?= htmlspecialchars($txn['account_number'] ?? '—') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Transaction Date</div>
                        <div class="fw-semibold"><?= formatDateTime($txn['transaction_date']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Amount Transferred</div>
                        <div class="fw-bold <?= $txn['amount_transferred'] == $txn['amount'] ? 'text-success' : 'text-warning' ?>">
                            <?= $txn['amount_transferred'] ? formatCurrency($txn['amount_transferred']) : '—' ?>
                            <?php if ($txn['amount_transferred'] && $txn['amount_transferred'] != $txn['amount']): ?>
                                <i class="bi bi-exclamation-triangle-fill text-warning" data-bs-toggle="tooltip" title="Amount mismatch!"></i>
                            <?php elseif ($txn['amount_transferred'] == $txn['amount']): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="small text-muted">Current Status</div>
                        <span class="badge <?= getStatusBadgeClass($txn['verification_status']) ?> fs-6">
                            <?= ucfirst(str_replace('_',' ',$txn['verification_status'])) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Verification form + History -->
    <div class="col-lg-5">

        <!-- Verification Criteria -->
        <?php if ($txn['verification_status'] === 'pending_verification'): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-clipboard-check me-2"></i>Verification Criteria
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">Check all criteria before making a decision:</p>
                <?php foreach ($criteria as $key => $label): ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input criteria-check" type="checkbox" id="c_<?= $key ?>"
                               onchange="updateVerifyBtn()">
                        <label class="form-check-label small" for="c_<?= $key ?>">
                            <?= $label ?>
                        </label>
                    </div>
                <?php endforeach; ?>
                <div id="criteriaWarning" class="alert alert-warning small py-2 mt-3 d-none">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Please check all criteria before verifying.
                </div>
            </div>
        </div>

        <!-- Verification Action Form -->
        <div class="card border-2" id="verificationCard">
            <div class="card-header fw-bold">
                <i class="bi bi-gavel me-2"></i>Verification Decision
            </div>
            <div class="card-body">
                <form method="POST" id="verificationForm">
                    <input type="hidden" name="action" id="verificationAction" value="">

                    <!-- Action buttons -->
                    <div class="d-flex gap-3 mb-3">
                        <button type="button" id="verifyBtn"
                                class="btn btn-outline-success flex-grow-1"
                                onclick="selectAction('verified')">
                            <i class="bi bi-check-circle me-1"></i>Verify
                        </button>
                        <button type="button" id="rejectBtn"
                                class="btn btn-outline-danger flex-grow-1"
                                onclick="selectAction('rejected')">
                            <i class="bi bi-x-circle me-1"></i>Reject
                        </button>
                    </div>

                    <!-- Rejection reason (hidden by default) -->
                    <div id="rejectionReasonDiv" class="d-none mb-3">
                        <label class="form-label fw-semibold text-danger">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Rejection Reason <span class="text-danger">*</span>
                        </label>
                        <textarea name="rejection_reason" id="rejection_reason_input"
                                  class="form-control border-danger" rows="3"
                                  placeholder="Explain why this transaction is rejected..."></textarea>
                        <div class="form-text text-danger">This reason will be sent to the donor.</div>
                    </div>

                    <!-- Selected action indicator -->
                    <div id="actionIndicator" class="d-none alert py-2 mb-3"></div>

                    <button type="submit" id="submitBtn" class="btn btn-primary w-100 d-none" onclick="return confirmAction()">
                        <i class="bi bi-send me-2"></i>Submit Decision
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Verification History -->
        <div class="card <?= $txn['verification_status'] !== 'pending_verification' ? '' : 'mt-4' ?>">
            <div class="card-header"><i class="bi bi-clock-history me-2"></i>Verification History</div>
            <div class="card-body p-0">
                <?php if ($history && $history->num_rows > 0): ?>
                    <?php while ($h = $history->fetch_assoc()): ?>
                        <div class="d-flex gap-3 p-3 border-bottom">
                            <div style="width:36px;height:36px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;
                                background:<?= $h['action']==='verified'?'#d1e7dd':($h['action']==='rejected'?'#f8d7da':'#fff3cd') ?>">
                                <i class="bi bi-<?= $h['action']==='verified'?'check-circle-fill text-success':($h['action']==='rejected'?'x-circle-fill text-danger':'clock-fill text-warning') ?>"></i>
                            </div>
                            <div>
                                <div class="small fw-semibold"><?= ucfirst($h['action']) ?></div>
                                <div class="small text-muted">By: <?= htmlspecialchars($h['staff_name']) ?></div>
                                <?php if ($h['notes']): ?>
                                    <div class="small text-muted"><?= htmlspecialchars($h['notes']) ?></div>
                                <?php endif; ?>
                                <div class="small text-muted"><?= formatDateTime($h['created_at']) ?></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center text-muted p-4">No history yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let selectedAction = '';
const totalCriteria = <?= count($criteria) ?>;

function updateVerifyBtn() {
    const checked = document.querySelectorAll('.criteria-check:checked').length;
    const warning = document.getElementById('criteriaWarning');
    if (warning) warning.classList.toggle('d-none', checked === totalCriteria);
}

function selectAction(action) {
    selectedAction = action;
    document.getElementById('verificationAction').value = action;

    const rejectDiv = document.getElementById('rejectionReasonDiv');
    const verifyBtn = document.getElementById('verifyBtn');
    const rejectBtn = document.getElementById('rejectBtn');
    const submitBtn = document.getElementById('submitBtn');
    const indicator = document.getElementById('actionIndicator');

    if (action === 'verified') {
        rejectDiv.classList.add('d-none');
        verifyBtn.className = 'btn btn-success flex-grow-1';
        rejectBtn.className = 'btn btn-outline-danger flex-grow-1';
        indicator.className = 'alert alert-success py-2 mb-3';
        indicator.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Selected: <strong>Verify</strong> this transaction';
    } else {
        rejectDiv.classList.remove('d-none');
        rejectBtn.className = 'btn btn-danger flex-grow-1';
        verifyBtn.className = 'btn btn-outline-success flex-grow-1';
        indicator.className = 'alert alert-danger py-2 mb-3';
        indicator.innerHTML = '<i class="bi bi-x-circle-fill me-2"></i>Selected: <strong>Reject</strong> this transaction';
    }

    indicator.classList.remove('d-none');
    submitBtn.classList.remove('d-none');
    submitBtn.className = 'btn w-100 ' + (action === 'verified' ? 'btn-success' : 'btn-danger');
    submitBtn.innerHTML = '<i class="bi bi-send me-2"></i>Confirm ' + (action === 'verified' ? 'Verification' : 'Rejection');
}

function confirmAction() {
    if (!selectedAction) { alert('Please select an action.'); return false; }
    if (selectedAction === 'rejected') {
        const reason = document.getElementById('rejection_reason_input').value.trim();
        if (!reason) { alert('Please provide a rejection reason.'); return false; }
    }
    const msg = selectedAction === 'verified'
        ? 'Are you sure you want to VERIFY this transaction?'
        : 'Are you sure you want to REJECT this transaction?';
    return confirm(msg);
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
