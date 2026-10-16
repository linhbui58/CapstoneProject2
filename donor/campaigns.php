<?php
$pageTitle  = 'Browse Campaigns';
$activePage = 'campaigns';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('donor');

$db = getDB();

$campaigns = $db->query("
    SELECT c.*, u.name as created_by_name,
           DATEDIFF(c.end_date, CURDATE()) as days_left,
           COUNT(d.id) as donor_count
    FROM campaigns c
    JOIN users u ON c.created_by = u.id
    LEFT JOIN donations d ON d.campaign_id = c.id AND d.status = 'verified'
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
?>

<div class="page-header">
    <h1><i class="bi bi-collection me-2 text-primary"></i>Browse Campaigns</h1>
    <p class="text-muted mb-0">Choose a cause and make a difference today</p>
</div>

<div class="row g-4">
    <?php if ($campaigns && $campaigns->num_rows > 0): ?>
        <?php while ($c = $campaigns->fetch_assoc()): ?>
            <?php $pct = getCampaignProgress($c['raised_amount'], $c['target_amount']); ?>
            <div class="col-md-6 col-lg-4">
                <div class="campaign-card h-100 d-flex flex-column">
                    <div class="campaign-img">
                        <?php if (!empty($c['image_url'])): ?>
                        <img src="<?= htmlspecialchars($c['image_url']) ?>"
                             alt="<?= htmlspecialchars($c['title']) ?>"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div style="display:none;width:100%;height:100%;align-items:center;justify-content:center;">
                            <i class="bi bi-megaphone-fill" style="font-size:2.5rem;color:rgba(255,255,255,.25);"></i>
                        </div>
                        <?php else: ?>
                        <i class="bi bi-megaphone-fill" style="font-size:2.5rem;color:rgba(255,255,255,.25);"></i>
                        <?php endif; ?>
                    </div>
                    <div class="p-4 flex-grow-1 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-success">Active</span>
                            <?php if ($c['days_left'] <= 7 && $c['days_left'] > 0): ?>
                                <span class="badge bg-danger"><i class="bi bi-clock me-1"></i>Ending soon</span>
                            <?php elseif ($c['days_left'] <= 0): ?>
                                <span class="badge bg-secondary">Ended</span>
                            <?php else: ?>
                                <span class="small text-muted"><?= $c['days_left'] ?> days left</span>
                            <?php endif; ?>
                        </div>
                        <h6 class="fw-bold mb-2"><?= htmlspecialchars($c['title']) ?></h6>
                        <p class="small text-muted flex-grow-1">
                            <?= htmlspecialchars(substr($c['description'], 0, 120)) ?>...
                        </p>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted"><?= $c['donor_count'] ?> donors</span>
                                <span class="fw-bold text-primary"><?= $pct ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mt-1">
                                <span><?= formatCurrency($c['raised_amount']) ?> raised</span>
                                <span>Goal: <?= formatCurrency($c['target_amount']) ?></span>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-auto">
                            <a href="<?= SITE_URL ?>/donor/donate.php?campaign_id=<?= $c['id'] ?>"
                               class="btn btn-vcf flex-grow-1">
                                <i class="bi bi-heart me-1"></i>Donate
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12 text-center py-5 text-muted">
            <i class="bi bi-inbox d-block fs-1 mb-3"></i>
            No active campaigns at the moment. Check back soon!
        </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
