<?php
$pageTitle  = 'My Donations';
$activePage = 'my_donations';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('donor');

$db  = getDB();
$uid = $user['id'];

// Filter
$filter_status = $_GET['status'] ?? '';
$where = "d.donor_id = $uid";
if ($filter_status) {
    $fs = $db->real_escape_string($filter_status);
    $where .= " AND d.status = '$fs'";
}

$donations = $db->query("
    SELECT d.*, c.title as campaign_title,
           t.reference_code, t.verification_status, t.rejection_reason,
           t.id as transaction_id
    FROM donations d
    LEFT JOIN campaigns c ON d.campaign_id = c.id
    LEFT JOIN transactions t ON t.donation_id = d.id
    WHERE $where
    ORDER BY d.created_at DESC
");

// Summary
$summary = $db->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='verified' THEN amount ELSE 0 END) as total_verified,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status='verified' THEN 1 ELSE 0 END) as verified_count,
        SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM donations WHERE donor_id=$uid
")->fetch_assoc();
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-receipt me-2 text-primary"></i>My Donations</h1>
        <p class="text-muted mb-0">Track all your donation submissions and verification status</p>
    </div>
    <a href="<?= SITE_URL ?>/donor/donate.php" class="btn btn-vcf">
        <i class="bi bi-heart me-2"></i>New Donation
    </a>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card text-center">
            <div class="stat-value"><?= formatCurrency($summary['total_verified']) ?></div>
            <div class="stat-label">Total Verified</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card text-center">
            <div class="stat-value text-success"><?= $summary['verified_count'] ?></div>
            <div class="stat-label">Verified</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card text-center">
            <div class="stat-value text-warning"><?= $summary['pending_count'] ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card text-center">
            <div class="stat-value text-danger"><?= $summary['rejected_count'] ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="table-card">
    <div class="table-header">
        <h6 class="fw-bold mb-0"><i class="bi bi-list me-2"></i>Donation History</h6>
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach ([''=>'All', 'pending'=>'Pending', 'verified'=>'Verified', 'rejected'=>'Rejected'] as $val => $label): ?>
                <a href="?status=<?= $val ?>"
                   class="btn btn-sm <?= $filter_status === $val ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 searchable-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Campaign</th>
                    <th>Amount</th>
                    <th>Channel</th>
                    <th>Reference</th>
                    <th>Verification</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($donations && $donations->num_rows > 0): ?>
                    <?php $i = 1; while ($d = $donations->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted small"><?= $i++ ?></td>
                        <td><?= htmlspecialchars($d['campaign_title'] ?? 'General') ?></td>
                        <td class="fw-bold text-success"><?= formatCurrency($d['amount']) ?></td>
                        <td><span class="badge bg-light text-dark"><?= ucfirst(str_replace('_',' ',$d['channel'])) ?></span></td>
                        <td><code class="small"><?= htmlspecialchars($d['reference_code'] ?? 'N/A') ?></code></td>
                        <td>
                            <span class="status-pill <?= $d['status'] ?>">
                                <i class="bi bi-<?= $d['status']==='verified'?'check-circle-fill':($d['status']==='rejected'?'x-circle-fill':'clock-fill') ?>"></i>
                                <?= ucfirst($d['verification_status'] ?? $d['status']) ?>
                            </span>
                            <?php if ($d['status'] === 'rejected' && $d['rejection_reason']): ?>
                                <div class="small text-danger mt-1">
                                    <i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($d['rejection_reason']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= formatDate($d['created_at']) ?></td>
                        <td>
                            <?php if ($d['transaction_id']): ?>
                                <a href="<?= SITE_URL ?>/donor/donation_detail.php?id=<?= $d['id'] ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-inbox d-block fs-2 mb-2"></i>
                        No donations found.
                        <a href="<?= SITE_URL ?>/donor/donate.php" class="d-block mt-1">Make your first donation</a>
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
