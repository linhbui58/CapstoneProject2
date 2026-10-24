<?php
$pageTitle  = 'Donation Detail';
$activePage = 'my_donations';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('donor');

$db  = getDB();
$uid = $user['id'];
$id  = (int)($_GET['id'] ?? 0);

$donation = $db->query("
    SELECT d.*, c.title as campaign_title, c.target_amount,
           t.id as txn_id, t.reference_code, t.bank_name, t.account_number,
           t.transaction_date, t.amount_transferred, t.verification_status,
           t.rejection_reason, t.verified_at,
           verifier.name as verified_by_name
    FROM donations d
    LEFT JOIN campaigns c ON d.campaign_id = c.id
    LEFT JOIN transactions t ON t.donation_id = d.id
    LEFT JOIN users verifier ON t.verified_by = verifier.id
    WHERE d.id = $id AND d.donor_id = $uid
    LIMIT 1
")->fetch_assoc();

if (!$donation) {
    setFlash('danger', 'Donation not found.');
    redirect(SITE_URL . '/donor/my_donations.php');
}

// Verification history
$history = $db->query("
    SELECT vh.*, u.name as staff_name
    FROM verification_history vh
    JOIN users u ON vh.performed_by = u.id
    WHERE vh.transaction_id = {$donation['txn_id']}
    ORDER BY vh.created_at ASC
");
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-receipt me-2 text-primary"></i>Donation Detail</h1>
    </div>
    <a href="<?= SITE_URL ?>/donor/my_donations.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <!-- Donation Info -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-heart-fill text-danger me-2"></i>Donation Information</span>
                <span class="status-pill <?= $donation['status'] ?>">
                    <?= ucfirst($donation['status']) ?>
                </span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Donation ID</dt>
                    <dd class="col-sm-8"><code>#<?= $donation['id'] ?></code></dd>

                    <dt class="col-sm-4 text-muted">Campaign</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($donation['campaign_title'] ?? 'General Donation') ?></dd>

                    <dt class="col-sm-4 text-muted">Amount</dt>
                    <dd class="col-sm-8 fw-bold text-success fs-5"><?= formatCurrency($donation['amount']) ?></dd>

                    <dt class="col-sm-4 text-muted">Channel</dt>
                    <dd class="col-sm-8"><?= ucfirst(str_replace('_', ' ', $donation['channel'])) ?></dd>

                    <dt class="col-sm-4 text-muted">Frequency</dt>
                    <dd class="col-sm-8">
                        <?php
                        $freq_map = [
                            'one_time'  => ['One Time',  '#f1f5f9','#475569'],
                            'monthly'   => ['Monthly 🔄',  '#dcfce7','#15803d'],
                            'quarterly' => ['Quarterly 🔄', '#dbeafe','#1447c0'],
                            'yearly'    => ['Yearly 🔄',    '#ede9fe','#7c3aed'],
                        ];
                        $fq = $donation['frequency'] ?? 'one_time';
                        $fm = $freq_map[$fq] ?? $freq_map['one_time'];
                        ?>
                        <span style="background:<?= $fm[1] ?>;color:<?= $fm[2] ?>;padding:2px 10px;border-radius:6px;font-size:.8rem;font-weight:700;">
                            <?= $fm[0] ?>
                        </span>
                    </dd>

                    <dt class="col-sm-4 text-muted">Anonymous</dt>
                    <dd class="col-sm-8"><?= $donation['is_anonymous'] ? 'Yes' : 'No' ?></dd>

                    <dt class="col-sm-4 text-muted">Message</dt>
                    <dd class="col-sm-8"><?= $donation['message'] ? htmlspecialchars($donation['message']) : '<span class="text-muted">—</span>' ?></dd>

                    <dt class="col-sm-4 text-muted">Submitted</dt>
                    <dd class="col-sm-8"><?= formatDateTime($donation['created_at']) ?></dd>
                </dl>
            </div>
        </div>

        <!-- Transaction Info -->
        <div class="card">
            <div class="card-header"><i class="bi bi-bank me-2"></i>Transaction Details</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Reference Code</dt>
                    <dd class="col-sm-8"><code><?= htmlspecialchars($donation['reference_code'] ?? 'N/A') ?></code></dd>

                    <dt class="col-sm-4 text-muted">Bank Name</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($donation['bank_name'] ?? '—') ?></dd>

                    <dt class="col-sm-4 text-muted">Account Number</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($donation['account_number'] ?? '—') ?></dd>

                    <dt class="col-sm-4 text-muted">Transaction Date</dt>
                    <dd class="col-sm-8"><?= formatDateTime($donation['transaction_date']) ?></dd>

                    <dt class="col-sm-4 text-muted">Amount Transferred</dt>
                    <dd class="col-sm-8"><?= $donation['amount_transferred'] ? formatCurrency($donation['amount_transferred']) : '—' ?></dd>

                    <dt class="col-sm-4 text-muted">Verification</dt>
                    <dd class="col-sm-8">
                        <span class="status-pill <?= str_replace('_verification','',$donation['verification_status'] ?? 'pending') ?>">
                            <?= ucfirst(str_replace('_', ' ', $donation['verification_status'] ?? 'pending')) ?>
                        </span>
                    </dd>

                    <?php if ($donation['rejection_reason']): ?>
                    <dt class="col-sm-4 text-muted">Rejection Reason</dt>
                    <dd class="col-sm-8 text-danger"><?= htmlspecialchars($donation['rejection_reason']) ?></dd>
                    <?php endif; ?>

                    <?php if ($donation['verified_by_name']): ?>
                    <dt class="col-sm-4 text-muted">Verified By</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($donation['verified_by_name']) ?></dd>

                    <dt class="col-sm-4 text-muted">Verified At</dt>
                    <dd class="col-sm-8"><?= formatDateTime($donation['verified_at']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>

    <!-- Verification Timeline -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-2"></i>Verification Timeline</div>
            <div class="card-body">
                <?php if ($history && $history->num_rows > 0): ?>
                    <div class="timeline">
                        <?php while ($h = $history->fetch_assoc()): ?>
                            <div class="d-flex gap-3 mb-3">
                                <div class="flex-shrink-0 text-center" style="width:36px;">
                                    <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;
                                        background:<?= $h['action']==='verified'?'#d1e7dd':($h['action']==='rejected'?'#f8d7da':'#fff3cd') ?>">
                                        <i class="bi bi-<?= $h['action']==='verified'?'check-circle-fill text-success':($h['action']==='rejected'?'x-circle-fill text-danger':'clock-fill text-warning') ?>"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 pb-3 border-bottom">
                                    <div class="fw-semibold small"><?= ucfirst($h['action']) ?></div>
                                    <div class="small text-muted">By: <?= htmlspecialchars($h['staff_name']) ?></div>
                                    <?php if ($h['notes']): ?>
                                        <div class="small text-muted"><?= htmlspecialchars($h['notes']) ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted"><?= formatDateTime($h['created_at']) ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-3">No history available</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($donation['status'] === 'verified'): ?>
        <!-- Receipt -->
        <div class="card mt-4 border-success">
            <div class="card-body text-center p-4">
                <i class="bi bi-patch-check-fill text-success d-block mb-2" style="font-size:2.5rem;"></i>
                <h6 class="fw-bold text-success">Donation Verified!</h6>
                <p class="small text-muted mb-3">
                    Your donation of <strong><?= formatCurrency($donation['amount']) ?></strong>
                    has been successfully verified and recorded.
                </p>
                <div class="small text-muted">Thank you for your generous contribution! 🙏</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
