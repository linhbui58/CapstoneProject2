<?php
$pageTitle  = 'My Donations';
$activePage = 'my_donations';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('donor');

$db  = getApplicationDB();
$uid = $user['id'];

// Auto-add frequency column if not yet present (safe migration)
$db->query("ALTER TABLE donations ADD COLUMN IF NOT EXISTS frequency ENUM('one_time','monthly','quarterly','yearly') NOT NULL DEFAULT 'one_time' AFTER channel");

// Filter
$filter_status    = $_GET['status']    ?? '';
$filter_frequency = $_GET['frequency'] ?? '';
$where = "d.donor_id = $uid";
if ($filter_status) {
    $fs = $db->real_escape_string($filter_status);
    $where .= " AND d.status = '$fs'";
}
if ($filter_frequency) {
    $ff = $db->real_escape_string($filter_frequency);
    $where .= " AND d.frequency = '$ff'";
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
        SUM(CASE WHEN status='pending'  THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status='verified' THEN 1 ELSE 0 END) as verified_count,
        SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected_count,
        SUM(CASE WHEN frequency != 'one_time' THEN 1 ELSE 0 END) as recurring_count,
        SUM(CASE WHEN frequency != 'one_time' AND status='verified' THEN amount ELSE 0 END) as recurring_total
    FROM donations WHERE donor_id=$uid
")->fetch_assoc();

// Frequency config
$freq_cfg = [
    'one_time'  => ['label'=>'One Time',  'icon'=>'bi-1-circle',       'bg'=>'#f1f5f9','color'=>'#475569'],
    'monthly'   => ['label'=>'Monthly',   'icon'=>'bi-calendar-month', 'bg'=>'#dcfce7','color'=>'#15803d'],
    'quarterly' => ['label'=>'Quarterly', 'icon'=>'bi-calendar3',      'bg'=>'#dbeafe','color'=>'#1447c0'],
    'yearly'    => ['label'=>'Yearly',    'icon'=>'bi-calendar-check', 'bg'=>'#ede9fe','color'=>'#7c3aed'],
];
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

<!-- Summary cards -->
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
        <div class="stat-card text-center" style="border-top:3px solid #15803d;">
            <div class="stat-value" style="color:#15803d;"><?= $summary['recurring_count'] ?></div>
            <div class="stat-label">Recurring Donations 🔄</div>
        </div>
    </div>
</div>

<!-- Recurring donation info banner (show if donor has recurring) -->
<?php if ((int)$summary['recurring_count'] > 0): ?>
<div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;border-radius:12px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;gap:14px;">
    <div style="width:40px;height:40px;background:#15803d;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:20px;">🔄</div>
    <div>
        <div style="font-size:.85rem;font-weight:700;color:#15803d;">You have <?= $summary['recurring_count'] ?> recurring donation(s)</div>
        <div style="font-size:.75rem;color:#16a34a;margin-top:2px;">
            Total recurring contributions: <strong><?= formatCurrency($summary['recurring_total']) ?></strong>.
            Thank you for your continued support!
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="table-card">
    <div class="table-header">
        <h6 class="fw-bold mb-0"><i class="bi bi-list me-2"></i>Donation History</h6>
        <div class="d-flex gap-2 flex-wrap">
            <!-- Status filter -->
            <?php foreach ([''=>'All', 'pending'=>'Pending', 'verified'=>'Verified', 'rejected'=>'Rejected'] as $val => $label): ?>
                <a href="?status=<?= $val ?>&frequency=<?= htmlspecialchars($filter_frequency) ?>"
                   class="btn btn-sm <?= $filter_status === $val ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
            <!-- Frequency filter -->
            <span style="border-left:1px solid #e2e8f0;margin:0 4px;"></span>
            <a href="?status=<?= htmlspecialchars($filter_status) ?>&frequency="
               class="btn btn-sm <?= $filter_frequency === '' ? 'btn-success' : 'btn-outline-success' ?>">
                All Frequency
            </a>
            <a href="?status=<?= htmlspecialchars($filter_status) ?>&frequency=one_time"
               class="btn btn-sm <?= $filter_frequency === 'one_time' ? 'btn-secondary' : 'btn-outline-secondary' ?>">
                One Time
            </a>
            <a href="?status=<?= htmlspecialchars($filter_status) ?>&frequency=monthly"
               class="btn btn-sm <?= $filter_frequency === 'monthly' ? 'btn-success' : 'btn-outline-success' ?>">
                🔄 Monthly
            </a>
            <a href="?status=<?= htmlspecialchars($filter_status) ?>&frequency=quarterly"
               class="btn btn-sm <?= $filter_frequency === 'quarterly' ? 'btn-primary' : 'btn-outline-primary' ?>">
                🔄 Quarterly
            </a>
            <a href="?status=<?= htmlspecialchars($filter_status) ?>&frequency=yearly"
               class="btn btn-sm <?= $filter_frequency === 'yearly' ? 'btn-purple' : 'btn-outline-secondary' ?>">
                🔄 Yearly
            </a>
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
                    <th>Frequency</th>
                    <th>Reference</th>
                    <th>Verification</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($donations && $donations->num_rows > 0): ?>
                    <?php $i = 1; while ($d = $donations->fetch_assoc()):
                        $freq = $d['frequency'] ?? 'one_time';
                        $fc   = $freq_cfg[$freq] ?? $freq_cfg['one_time'];
                    ?>
                    <tr>
                        <td class="text-muted small"><?= $i++ ?></td>
                        <td><?= htmlspecialchars($d['campaign_title'] ?? 'General') ?></td>
                        <td class="fw-bold text-success"><?= formatCurrency($d['amount']) ?></td>
                        <td><span class="badge bg-light text-dark"><?= ucfirst(str_replace('_',' ',$d['channel'])) ?></span></td>
                        <td>
                            <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $fc['bg'] ?>;color:<?= $fc['color'] ?>;padding:3px 9px;border-radius:6px;font-size:.71rem;font-weight:700;white-space:nowrap;">
                                <i class="bi <?= $fc['icon'] ?>" style="font-size:.68rem;"></i>
                                <?= $fc['label'] ?>
                                <?= $freq !== 'one_time' ? '🔄' : '' ?>
                            </span>
                        </td>
                        <td><code class="small"><?= htmlspecialchars($d['reference_code'] ?? '—') ?></code></td>
                        <td>
                            <span class="status-pill <?= $d['status'] ?>">
                                <i class="bi bi-<?= $d['status']==='verified'?'check-circle-fill':($d['status']==='rejected'?'x-circle-fill':'clock-fill') ?>"></i>
                                <?= empty($d['transaction_id']) && $d['status'] === 'pending'
                                    ? 'Awaiting transfer'
                                    : ucfirst(str_replace('_', ' ', $d['verification_status'] ?? $d['status'])) ?>
                            </span>
                            <?php if ($d['status'] === 'rejected' && $d['rejection_reason']): ?>
                                <div class="small text-danger mt-1">
                                    <i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($d['rejection_reason']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= formatDate($d['created_at']) ?></td>
                        <td>
                            <a href="<?= SITE_URL ?>/donor/donation_detail.php?id=<?= $d['id'] ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">
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
