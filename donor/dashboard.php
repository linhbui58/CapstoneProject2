<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once dirname(__DIR__) . '/includes/header.php';

$db = getDB();
$uid = $user['id'];

// Stats
$total_donated = $db->query("SELECT COALESCE(SUM(amount),0) as total FROM donations WHERE donor_id=$uid AND status='verified'")->fetch_assoc()['total'];
$total_donations = $db->query("SELECT COUNT(*) as cnt FROM donations WHERE donor_id=$uid")->fetch_assoc()['cnt'];
$pending = $db->query("SELECT COUNT(*) as cnt FROM donations WHERE donor_id=$uid AND status='pending'")->fetch_assoc()['cnt'];
$campaigns_supported = $db->query("SELECT COUNT(DISTINCT campaign_id) as cnt FROM donations WHERE donor_id=$uid AND campaign_id IS NOT NULL AND status='verified'")->fetch_assoc()['cnt'];

// Recurring stats for this donor
$db->query("ALTER TABLE donations ADD COLUMN IF NOT EXISTS frequency ENUM('one_time','monthly','quarterly','yearly') NOT NULL DEFAULT 'one_time' AFTER channel");
$recurring_count = (int)$db->query("SELECT COUNT(*) as c FROM donations WHERE donor_id=$uid AND frequency != 'one_time' AND status='verified'")->fetch_assoc()['c'];
$is_recurring_donor = $recurring_count > 0;

// Recent donations
$recent = $db->query("
    SELECT d.*, c.title as campaign_title
    FROM donations d
    LEFT JOIN campaigns c ON d.campaign_id = c.id
    WHERE d.donor_id = $uid
    ORDER BY d.created_at DESC LIMIT 5
");

// Active campaigns
$campaigns = $db->query("
    SELECT *, DATEDIFF(end_date, CURDATE()) as days_left
    FROM campaigns WHERE status='active'
    ORDER BY created_at DESC LIMIT 3
");
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-speedometer2 me-2 text-primary"></i>My Dashboard</h1>
        <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars(explode(' ',$user['name'])[0]) ?>! Here's your giving summary.</p>
    </div>
    <a href="<?= SITE_URL ?>/donor/donate.php" class="btn btn-vcf">
        <i class="bi bi-heart me-2"></i>Donate Now
    </a>
</div>

<!-- Recurring donor badge (show if applicable) -->
<?php if ($is_recurring_donor): ?>
<div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;border-radius:12px;padding:13px 18px;margin-bottom:18px;display:flex;align-items:center;gap:12px;">
    <div style="font-size:24px;">🔄</div>
    <div>
        <div style="font-size:.85rem;font-weight:700;color:#15803d;">You are a Recurring Donor!</div>
        <div style="font-size:.74rem;color:#16a34a;">Thank you for your <?= $recurring_count ?> recurring contribution(s). Your consistent support makes a lasting impact.</div>
    </div>
    <a href="<?= SITE_URL ?>/donor/my_donations.php?frequency=monthly" style="margin-left:auto;font-size:.76rem;font-weight:700;color:#15803d;text-decoration:none;white-space:nowrap;">
        View recurring →
    </a>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="stat-icon bg-primary bg-opacity-10">
                    <i class="bi bi-cash-stack text-primary"></i>
                </div>
            </div>
            <div class="stat-value"><?= formatCurrency($total_donated) ?></div>
            <div class="stat-label">Total Donated</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="stat-icon bg-success bg-opacity-10">
                    <i class="bi bi-receipt text-success"></i>
                </div>
            </div>
            <div class="stat-value"><?= $total_donations ?></div>
            <div class="stat-label">Total Donations</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="stat-icon bg-warning bg-opacity-10">
                    <i class="bi bi-clock text-warning"></i>
                </div>
            </div>
            <div class="stat-value"><?= $pending ?></div>
            <div class="stat-label">Pending Verification</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="stat-icon bg-info bg-opacity-10">
                    <i class="bi bi-megaphone text-info"></i>
                </div>
            </div>
            <div class="stat-value"><?= $campaigns_supported ?></div>
            <div class="stat-label">Campaigns Supported</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Donations -->
    <div class="col-lg-7">
        <div class="table-card">
            <div class="table-header">
                <h6 class="fw-bold mb-0"><i class="bi bi-receipt me-2"></i>Recent Donations</h6>
                <a href="<?= SITE_URL ?>/donor/my_donations.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Amount</th>
                            <th>Channel</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent && $recent->num_rows > 0): ?>
                            <?php while ($r = $recent->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['campaign_title'] ?? 'General') ?></td>
                                <td class="fw-semibold"><?= formatCurrency($r['amount']) ?></td>
                                <td><span class="badge bg-light text-dark"><?= ucfirst(str_replace('_',' ',$r['channel'])) ?></span></td>
                                <td>
                                    <span class="status-pill <?= $r['status'] ?>">
                                        <i class="bi bi-<?= $r['status']==='verified'?'check-circle':'clock' ?>-fill"></i>
                                        <?= ucfirst($r['status']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= formatDate($r['created_at']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox d-block fs-3 mb-2"></i>No donations yet.
                                <a href="<?= SITE_URL ?>/donor/donate.php" class="d-block mt-1">Make your first donation</a>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Active Campaigns -->
    <div class="col-lg-5">
        <div class="table-card h-100">
            <div class="table-header">
                <h6 class="fw-bold mb-0"><i class="bi bi-collection me-2"></i>Featured Campaigns</h6>
                <a href="<?= SITE_URL ?>/donor/campaigns.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="p-3 d-flex flex-column gap-3">
                <?php if ($campaigns && $campaigns->num_rows > 0): ?>
                    <?php while ($c = $campaigns->fetch_assoc()): ?>
                        <?php $pct = getCampaignProgress($c['raised_amount'], $c['target_amount']); ?>
                        <div class="p-3 border rounded-3 hover-shadow">
                            <div class="d-flex justify-content-between mb-1">
                                <h6 class="mb-0 small fw-bold"><?= htmlspecialchars($c['title']) ?></h6>
                                <span class="small text-muted"><?= $c['days_left'] ?>d left</span>
                            </div>
                            <div class="progress mb-1" style="height:6px;">
                                <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span><?= formatCurrency($c['raised_amount']) ?></span>
                                <span class="fw-semibold text-primary"><?= $pct ?>%</span>
                            </div>
                            <a href="<?= SITE_URL ?>/donor/donate.php?campaign_id=<?= $c['id'] ?>"
                               class="btn btn-vcf btn-sm w-100 mt-2">
                                <i class="bi bi-heart me-1"></i>Support
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-4">No active campaigns</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
