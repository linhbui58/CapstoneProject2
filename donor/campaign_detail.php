<?php
$pageTitle  = 'Campaign Detail';
$activePage = 'campaigns';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('donor');

$db  = getDB();
$uid = $user['id'];
$id  = (int)($_GET['id'] ?? 0);

if (!$id) {
    setFlash('danger', 'Campaign not found.');
    redirect(SITE_URL . '/donor/campaigns.php');
}

// Campaign info — allow active AND completed so donors can still view past campaigns
$campaign = $db->query("
    SELECT c.*, u.name AS created_by_name,
           DATEDIFF(c.end_date, CURDATE()) AS days_left
    FROM campaigns c
    JOIN users u ON c.created_by = u.id
    WHERE c.id = $id
    LIMIT 1
")->fetch_assoc();

if (!$campaign) {
    setFlash('danger', 'Campaign not found.');
    redirect(SITE_URL . '/donor/campaigns.php');
}

$pct = $campaign['target_amount'] > 0
    ? min(100, round(($campaign['raised_amount'] / $campaign['target_amount']) * 100, 1))
    : 0;

// Aggregate stats (no PII — only public numbers)
$total_donors  = (int)$db->query("SELECT COUNT(DISTINCT donor_id) as c FROM donations WHERE campaign_id=$id AND status='verified'")->fetch_assoc()['c'];
$total_donated = (float)$db->query("SELECT COALESCE(SUM(amount),0) as t FROM donations WHERE campaign_id=$id AND status='verified'")->fetch_assoc()['t'];

// Has the current donor already donated to this campaign?
$my_donation_count = (int)$db->query("SELECT COUNT(*) as c FROM donations WHERE campaign_id=$id AND donor_id=$uid")->fetch_assoc()['c'];

// Public donor messages (exclude anonymous — they chose to hide)
$messages = $db->query("
    SELECT d.message, d.amount, d.created_at, u.name AS donor_name
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE d.campaign_id = $id
      AND d.status = 'verified'
      AND d.is_anonymous = 0
      AND d.message IS NOT NULL
      AND d.message <> ''
    ORDER BY d.created_at DESC
    LIMIT 10
");

// Public fund usage (allocations) — disbursed/completed only
$allocations = $db->query("
    SELECT purpose, description, amount, allocation_date, status
    FROM fund_allocations
    WHERE campaign_id = $id
      AND status IN ('disbursed','completed')
    ORDER BY allocation_date DESC
");

$status_styles = [
    'active'    => ['bg'=>'#dcfce7','color'=>'#15803d','border'=>'#bbf7d0','label'=>'Active'],
    'completed' => ['bg'=>'#dbeafe','color'=>'#1447c0','border'=>'#bfdbfe','label'=>'Completed'],
    'draft'     => ['bg'=>'#f1f5f9','color'=>'#475569','border'=>'#e2e8f0','label'=>'Draft'],
    'closed'    => ['bg'=>'#f1f5f9','color'=>'#94a3b8','border'=>'#e2e8f0','label'=>'Closed'],
];
$ss = $status_styles[$campaign['status']] ?? $status_styles['closed'];
?>

<!-- ── Breadcrumb / Header ──────────────────────────── -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:22px;flex-wrap:wrap;">
    <div>
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
            <a href="<?= SITE_URL ?>/donor/campaigns.php"
               style="font-size:.78rem;color:#64748b;text-decoration:none;display:flex;align-items:center;gap:4px;"
               onmouseover="this.style.color='#1447c0'" onmouseout="this.style.color='#64748b'">
                <i class="bi bi-arrow-left"></i> Browse Campaigns
            </a>
            <span style="color:#e2e8f0;">/</span>
            <span style="font-size:.78rem;color:#94a3b8;"><?= htmlspecialchars(mb_strimwidth($campaign['title'], 0, 40, '…')) ?></span>
        </div>
        <h1 style="font-size:1.3rem;font-weight:800;color:#0f172a;margin:0;line-height:1.25;">
            <?= htmlspecialchars($campaign['title']) ?>
        </h1>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
        <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $ss['bg'] ?>;color:<?= $ss['color'] ?>;border:1.5px solid <?= $ss['border'] ?>;padding:5px 13px;border-radius:20px;font-size:.78rem;font-weight:700;">
            <i class="bi bi-circle-fill" style="font-size:.45rem;"></i><?= $ss['label'] ?>
        </span>
        <?php if ($campaign['status'] === 'active'): ?>
        <a href="<?= SITE_URL ?>/donor/donate.php?campaign_id=<?= $id ?>"
           style="display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;padding:8px 18px;border-radius:10px;font-size:.83rem;font-weight:700;text-decoration:none;box-shadow:0 3px 12px rgba(239,68,68,.35);transition:all .18s;"
           onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 5px 18px rgba(239,68,68,.45)'"
           onmouseout="this.style.transform='none';this.style.boxShadow='0 3px 12px rgba(239,68,68,.35)'">
            <i class="bi bi-heart-fill" style="font-size:.8rem;"></i>Donate Now
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ── Main layout ──────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 300px;gap:22px;align-items:start;">

<!-- LEFT: image + description + messages + allocations -->
<div style="display:flex;flex-direction:column;gap:18px;">

    <!-- Campaign image -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);">
        <?php if (!empty($campaign['image_url'])): ?>
        <img src="<?= htmlspecialchars($campaign['image_url']) ?>"
             alt="<?= htmlspecialchars($campaign['title']) ?>"
             style="width:100%;height:280px;object-fit:cover;display:block;"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div style="display:none;width:100%;height:280px;background:linear-gradient(135deg,#1e3a8a,#3b82f6);align-items:center;justify-content:center;">
            <i class="bi bi-megaphone-fill" style="font-size:3.5rem;color:rgba(255,255,255,.2);"></i>
        </div>
        <?php else: ?>
        <div style="width:100%;height:280px;background:linear-gradient(135deg,#1e3a8a,#3b82f6);display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-megaphone-fill" style="font-size:3.5rem;color:rgba(255,255,255,.2);"></i>
        </div>
        <?php endif; ?>
    </div>

    <!-- Description -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;padding:22px;box-shadow:0 2px 8px rgba(0,0,0,.05);">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
            <div style="width:30px;height:30px;background:#eff6ff;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-file-text-fill" style="color:#1447c0;font-size:.82rem;"></i>
            </div>
            <span style="font-size:.9rem;font-weight:700;color:#0f172a;">About This Campaign</span>
        </div>
        <p style="font-size:.85rem;color:#475569;line-height:1.75;margin:0;"><?= nl2br(htmlspecialchars($campaign['description'])) ?></p>

        <div style="margin-top:18px;padding-top:16px;border-top:1px solid #f1f5f9;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <?php foreach ([
                ['bi-person-fill',    '#eff6ff','#1447c0', 'Organized by', htmlspecialchars($campaign['created_by_name'])],
                ['bi-calendar-event', '#f0fdf4','#15803d', 'Start date',   formatDate($campaign['start_date'])],
                ['bi-calendar-check', '#fff7ed','#c2410c', 'End date',     formatDate($campaign['end_date'])],
            ] as [$ic,$bg,$co,$lbl,$val]): ?>
            <div style="background:<?= $bg ?>;border-radius:10px;padding:11px 13px;">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                    <i class="bi <?= $ic ?>" style="color:<?= $co ?>;font-size:.78rem;"></i>
                    <span style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:<?= $co ?>;"><?= $lbl ?></span>
                </div>
                <div style="font-size:.82rem;font-weight:700;color:#0f172a;"><?= $val ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($allocations && $allocations->num_rows > 0): ?>
    <!-- How funds are used -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;padding:22px;box-shadow:0 2px 8px rgba(0,0,0,.05);">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
            <div style="width:30px;height:30px;background:#fef9c3;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-pie-chart-fill" style="color:#d97706;font-size:.82rem;"></i>
            </div>
            <span style="font-size:.9rem;font-weight:700;color:#0f172a;">How Funds Are Used</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
        <?php
        $alloc_styles = [
            'disbursed' => ['bg'=>'#fef9c3','color'=>'#854d0e'],
            'completed' => ['bg'=>'#dcfce7','color'=>'#15803d'],
        ];
        while ($a = $allocations->fetch_assoc()):
            $as = $alloc_styles[$a['status']] ?? ['bg'=>'#f1f5f9','color'=>'#64748b'];
        ?>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:1px solid #f1f5f9;border-radius:10px;background:#fafbff;">
            <div style="width:34px;height:34px;background:#fff7ed;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-check2-circle" style="color:#d97706;font-size:.88rem;"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                    <span style="font-size:.82rem;font-weight:700;color:#0f172a;"><?= htmlspecialchars($a['purpose']) ?></span>
                    <span style="background:<?= $as['bg'] ?>;color:<?= $as['color'] ?>;padding:2px 8px;border-radius:20px;font-size:.67rem;font-weight:700;white-space:nowrap;"><?= ucfirst($a['status']) ?></span>
                </div>
                <?php if ($a['description']): ?>
                <div style="font-size:.76rem;color:#64748b;margin-top:3px;line-height:1.55;"><?= htmlspecialchars($a['description']) ?></div>
                <?php endif; ?>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px;">
                    <span style="font-size:.78rem;font-weight:800;color:#d97706;"><?= formatCurrency($a['amount']) ?></span>
                    <span style="font-size:.68rem;color:#94a3b8;"><?= formatDate($a['allocation_date']) ?></span>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($messages && $messages->num_rows > 0): ?>
    <!-- Messages of support -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;padding:22px;box-shadow:0 2px 8px rgba(0,0,0,.05);">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
            <div style="width:30px;height:30px;background:#fce7f3;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-chat-heart-fill" style="color:#db2777;font-size:.82rem;"></i>
            </div>
            <span style="font-size:.9rem;font-weight:700;color:#0f172a;">Messages of Support</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
        <?php while ($m = $messages->fetch_assoc()): ?>
        <div style="padding:12px 14px;background:#fdf4ff;border:1px solid #f3e8ff;border-radius:10px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                <div style="width:26px;height:26px;background:linear-gradient(135deg,#db2777,#9333ea);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.64rem;font-weight:800;color:white;flex-shrink:0;">
                    <?= strtoupper(mb_substr($m['donor_name'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-size:.78rem;font-weight:700;color:#0f172a;"><?= htmlspecialchars($m['donor_name']) ?></div>
                    <div style="font-size:.68rem;color:#94a3b8;"><?= formatDate($m['created_at']) ?></div>
                </div>
                <span style="margin-left:auto;font-size:.74rem;font-weight:700;color:#15803d;"><?= formatCurrency($m['amount']) ?></span>
            </div>
            <p style="font-size:.8rem;color:#475569;margin:0;line-height:1.6;font-style:italic;">"<?= htmlspecialchars($m['message']) ?>"</p>
        </div>
        <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- end left col -->

<!-- RIGHT: sticky sidebar -->
<div style="display:flex;flex-direction:column;gap:14px;position:sticky;top:80px;">

    <!-- Progress card -->
    <div style="background:white;border:2px solid #1447c0;border-radius:16px;padding:20px;box-shadow:0 4px 16px rgba(20,71,192,.1);">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:10px;">Fundraising Progress</div>
        <div style="font-size:1.9rem;font-weight:800;color:<?= $pct >= 100 ? '#15803d' : '#1447c0' ?>;letter-spacing:-.04em;line-height:1;margin-bottom:2px;">
            <?= number_format($campaign['raised_amount'] / 1000000, 1) ?>M <span style="font-size:.9rem;font-weight:700;color:#94a3b8;">VND</span>
        </div>
        <div style="font-size:.74rem;color:#64748b;margin-bottom:12px;">raised of <?= formatCurrency($campaign['target_amount']) ?> goal</div>
        <div style="height:10px;background:#f1f5f9;border-radius:5px;overflow:hidden;margin-bottom:6px;">
            <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct >= 100 ? 'linear-gradient(90deg,#22c55e,#16a34a)' : 'linear-gradient(90deg,#3b82f6,#1447c0)' ?>;border-radius:5px;transition:width .6s ease;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:.76rem;margin-bottom:16px;">
            <span style="font-weight:800;color:<?= $pct >= 100 ? '#15803d' : '#1447c0' ?>;"><?= $pct ?>%</span>
            <?php if ($campaign['status'] === 'active'): ?>
                <?php if ($campaign['days_left'] > 0): ?>
                    <span style="color:#64748b;"><?= $campaign['days_left'] ?> days left</span>
                <?php else: ?>
                    <span style="color:#ef4444;font-weight:700;">Ended</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Mini stats -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
            <?php foreach ([
                ['bi-people-fill',  '#eff6ff','#1447c0', $total_donors,  'Donors'],
                ['bi-check-circle-fill','#dcfce7','#15803d', formatCurrency($total_donated), 'Verified'],
            ] as [$ic,$bg,$co,$val,$lbl]): ?>
            <div style="background:<?= $bg ?>;border-radius:10px;padding:10px 12px;">
                <div style="display:flex;align-items:center;gap:5px;margin-bottom:3px;">
                    <i class="bi <?= $ic ?>" style="color:<?= $co ?>;font-size:.75rem;"></i>
                    <span style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:<?= $co ?>;"><?= $lbl ?></span>
                </div>
                <div style="font-size:.85rem;font-weight:800;color:#0f172a;"><?= $val ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($campaign['status'] === 'active'): ?>
        <a href="<?= SITE_URL ?>/donor/donate.php?campaign_id=<?= $id ?>"
           style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border-radius:11px;font-size:.88rem;font-weight:700;text-decoration:none;box-shadow:0 3px 12px rgba(239,68,68,.35);transition:all .18s;"
           onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 5px 18px rgba(239,68,68,.45)'"
           onmouseout="this.style.transform='none';this.style.boxShadow='0 3px 12px rgba(239,68,68,.35)'">
            <i class="bi bi-heart-fill" style="font-size:.85rem;"></i>
            <?= $my_donation_count > 0 ? 'Donate Again' : 'Donate Now' ?>
        </a>
        <?php else: ?>
        <div style="text-align:center;padding:10px;background:#f1f5f9;border-radius:10px;font-size:.78rem;color:#64748b;font-weight:600;">
            <i class="bi bi-lock-fill me-1"></i>This campaign has ended
        </div>
        <?php endif; ?>
    </div>

    <?php if ($my_donation_count > 0): ?>
    <!-- Already donated badge -->
    <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #bbf7d0;border-radius:13px;padding:14px 16px;display:flex;align-items:center;gap:10px;">
        <div style="width:34px;height:34px;background:#22c55e;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-check2" style="color:white;font-size:1rem;font-weight:900;"></i>
        </div>
        <div>
            <div style="font-size:.8rem;font-weight:700;color:#15803d;">You've supported this!</div>
            <div style="font-size:.72rem;color:#16a34a;">You have <?= $my_donation_count ?> donation<?= $my_donation_count > 1 ? 's' : '' ?> to this campaign.</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Share / back -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:13px;padding:14px 16px;">
        <div style="font-size:.76rem;font-weight:700;color:#64748b;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-share-fill" style="font-size:.72rem;"></i>Quick Actions
        </div>
        <a href="<?= SITE_URL ?>/donor/campaigns.php"
           style="display:flex;align-items:center;gap:8px;padding:9px 12px;border:1px solid #e2e8f0;border-radius:9px;color:#475569;font-size:.79rem;font-weight:600;text-decoration:none;transition:all .14s;margin-bottom:8px;"
           onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
            <i class="bi bi-grid-fill" style="color:#1447c0;"></i>All Campaigns
        </a>
        <a href="<?= SITE_URL ?>/donor/my_donations.php"
           style="display:flex;align-items:center;gap:8px;padding:9px 12px;border:1px solid #e2e8f0;border-radius:9px;color:#475569;font-size:.79rem;font-weight:600;text-decoration:none;transition:all .14s;"
           onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
            <i class="bi bi-receipt" style="color:#7c3aed;"></i>My Donations
        </a>
    </div>

</div><!-- end right sidebar -->
</div><!-- end grid -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
