<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('campaign_staff');
$db = getDB();

$active_camps   = (int)$db->query("SELECT COUNT(*) as t FROM campaigns WHERE status='active'")->fetch_assoc()['t'];
$total_camps    = (int)$db->query("SELECT COUNT(*) as t FROM campaigns")->fetch_assoc()['t'];
$total_raised   = $db->query("SELECT COALESCE(SUM(raised_amount),0) as t FROM campaigns")->fetch_assoc()['t'];
$unlinked       = (int)$db->query("SELECT COUNT(*) as t FROM donations WHERE status='verified' AND campaign_id IS NULL")->fetch_assoc()['t'];

// Campaigns with data
$campaigns = $db->query("
    SELECT c.*,
           COUNT(DISTINCT d.id) as donation_count,
           DATEDIFF(c.end_date,CURDATE()) as days_left,
           ROUND((c.raised_amount/c.target_amount)*100,1) as pct
    FROM campaigns c
    LEFT JOIN donations d ON d.campaign_id=c.id AND d.status='verified'
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 6
");

$status_styles = array(
    'active'    => array('bg'=>'#dcfce7','color'=>'#15803d','border'=>'#bbf7d0','label'=>'Active'),
    'completed' => array('bg'=>'#dbeafe','color'=>'#1447c0','border'=>'#bfdbfe','label'=>'Completed'),
    'draft'     => array('bg'=>'#f1f5f9','color'=>'#475569','border'=>'#e2e8f0','label'=>'Draft'),
    'closed'    => array('bg'=>'#f1f5f9','color'=>'#94a3b8','border'=>'#e2e8f0','label'=>'Closed'),
);
?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-grid-fill" style="color:#15803d;font-size:1rem;"></i>Campaign Dashboard
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">Manage fundraising campaigns and track progress</p>
    </div>
    <a href="<?= SITE_URL ?>/campaign_staff/create_campaign.php"
       style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:linear-gradient(135deg,#16a34a,#15803d);color:white;border-radius:10px;font-weight:700;font-size:.82rem;box-shadow:0 3px 12px rgba(22,163,74,.3);text-decoration:none;white-space:nowrap;transition:all .18s;flex-shrink:0;"
       onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
        <i class="bi bi-plus-circle-fill"></i>New Campaign
    </a>
</div>

<!-- Stat cards -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;">
    <?php
    $kpis = array(
        array($active_camps,                                'Active Campaigns',  'bi-play-circle-fill', '#dcfce7','#15803d'),
        array($total_camps,                                 'Total Campaigns',   'bi-collection-fill',  '#dbeafe','#1447c0'),
        array(number_format($total_raised/1000000,1).'M',  'Total Raised (VND)','bi-cash-stack',       '#fef9c3','#d97706'),
        array($unlinked,                                    'Unlinked Donations','bi-link-45deg',       $unlinked>0?'#fee2e2':'#f1f5f9', $unlinked>0?'#991b1b':'#94a3b8'),
    );
    foreach($kpis as $k):
        list($val,$lbl,$ic,$bg,$color) = $k;
    ?>
    <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;box-shadow:0 1px 4px rgba(0,0,0,.04);transition:all .2s;"
         onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.07)'"
         onmouseout="this.style.transform='none';this.style.boxShadow='0 1px 4px rgba(0,0,0,.04)'">
        <div style="width:40px;height:40px;border-radius:11px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;margin-bottom:12px;">
            <i class="bi <?= $ic ?>" style="color:<?= $color ?>;font-size:1rem;"></i>
        </div>
        <div style="font-size:1.55rem;font-weight:800;color:#0f172a;letter-spacing:-.03em;line-height:1;margin-bottom:4px;"><?= $val ?></div>
        <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;"><?= $lbl ?></div>
        <?php if($lbl==='Unlinked Donations' && $unlinked>0): ?>
        <a href="<?= SITE_URL ?>/campaign_staff/link_donations.php"
           style="display:inline-flex;align-items:center;gap:4px;margin-top:8px;font-size:.74rem;font-weight:700;color:#991b1b;text-decoration:none;">
            <i class="bi bi-arrow-right-circle-fill" style="font-size:.76rem;"></i>Link Now
        </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Campaign Overview -->
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;">
    <div style="padding:14px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="bi bi-collection-fill" style="color:#15803d;font-size:.88rem;"></i>
            <span style="font-size:.86rem;font-weight:700;color:#0f172a;">Campaign Overview</span>
        </div>
        <a href="<?= SITE_URL ?>/campaign_staff/campaigns.php"
           style="font-size:.78rem;font-weight:600;color:#15803d;text-decoration:none;display:flex;align-items:center;gap:4px;"
           onmouseover="this.style.color='#166534'" onmouseout="this.style.color='#15803d'">
            View All <i class="bi bi-arrow-right" style="font-size:.75rem;"></i>
        </a>
    </div>

    <?php if($campaigns && $campaigns->num_rows > 0): ?>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;">
        <?php
        $ci = 0;
        while($c = $campaigns->fetch_assoc()):
            $pct  = min(100, (float)($c['pct'] ?? 0));
            $done = $pct >= 100;
            $ss   = isset($status_styles[$c['status']]) ? $status_styles[$c['status']] : $status_styles['draft'];
            $urgent = $c['days_left'] > 0 && $c['days_left'] <= 14;
            $ended  = $c['days_left'] <= 0;
            $border_right = ($ci % 3 < 2) ? '1px solid #f1f5f9' : 'none';
            $border_bottom = ($ci < 3) ? '1px solid #f1f5f9' : 'none';
            $ci++;
        ?>
        <div style="padding:18px 20px;border-right:<?= $border_right ?>;border-bottom:<?= $border_bottom ?>;transition:background .15s;"
             onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">

            <!-- Status + actions -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $ss['bg'] ?>;color:<?= $ss['color'] ?>;border:1px solid <?= $ss['border'] ?>;padding:3px 10px;border-radius:20px;font-size:.69rem;font-weight:700;">
                    <?= $ss['label'] ?>
                </span>
                <div style="display:flex;gap:5px;">
                    <a href="<?= SITE_URL ?>/campaign_staff/edit_campaign.php?id=<?= $c['id'] ?>"
                       style="width:28px;height:28px;border-radius:7px;background:#f1f5f9;border:1px solid #e2e8f0;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:.75rem;text-decoration:none;transition:all .14s;"
                       title="Edit"
                       onmouseover="this.style.background='#dbeafe';this.style.color='#1447c0'" onmouseout="this.style.background='#f1f5f9';this.style.color='#64748b'">
                        <i class="bi bi-pencil-fill"></i>
                    </a>
                    <a href="<?= SITE_URL ?>/campaign_staff/fund_allocation.php?campaign_id=<?= $c['id'] ?>"
                       style="width:28px;height:28px;border-radius:7px;background:#f1f5f9;border:1px solid #e2e8f0;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:.75rem;text-decoration:none;transition:all .14s;"
                       title="Funds"
                       onmouseover="this.style.background='#dcfce7';this.style.color='#15803d'" onmouseout="this.style.background='#f1f5f9';this.style.color='#64748b'">
                        <i class="bi bi-pie-chart-fill"></i>
                    </a>
                </div>
            </div>

            <!-- Title -->
            <div style="font-size:.86rem;font-weight:700;color:#0f172a;margin-bottom:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= htmlspecialchars($c['title']) ?>
            </div>

            <!-- Meta -->
            <div style="font-size:.71rem;color:#94a3b8;margin-bottom:12px;display:flex;align-items:center;gap:8px;">
                <span><i class="bi bi-receipt" style="font-size:.68rem;margin-right:2px;"></i><?= $c['donation_count'] ?> donations</span>
                <span style="color:#e2e8f0;">·</span>
                <span style="color:<?= $ended?'#991b1b':($urgent?'#d97706':'#94a3b8') ?>">
                    <?php if($ended): ?><i class="bi bi-x-circle-fill" style="font-size:.65rem;"></i> Ended
                    <?php elseif($urgent): ?><i class="bi bi-exclamation-triangle-fill" style="font-size:.65rem;"></i> <?= $c['days_left'] ?>d left
                    <?php else: ?><?= $c['days_left'] ?>d left<?php endif; ?>
                </span>
            </div>

            <!-- Progress -->
            <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                <span style="font-size:.73rem;font-weight:600;color:#0f172a;"><?= number_format($c['raised_amount']/1000000,1) ?>M VND</span>
                <span style="font-size:.74rem;font-weight:800;color:<?= $done?'#15803d':'#1447c0' ?>;"><?= $pct ?>%</span>
            </div>
            <div style="height:7px;background:#f1f5f9;border-radius:4px;overflow:hidden;margin-bottom:4px;">
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $done?'linear-gradient(90deg,#22c55e,#16a34a)':'linear-gradient(90deg,#3b82f6,#1447c0)' ?>;border-radius:4px;transition:width .5s;"></div>
            </div>
            <div style="font-size:.68rem;color:#94a3b8;">Target: <?= number_format($c['target_amount']/1000000,0) ?>M VND</div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div style="padding:52px 20px;text-align:center;">
        <i class="bi bi-megaphone" style="font-size:2.5rem;color:#e2e8f0;display:block;margin-bottom:12px;"></i>
        <div style="font-size:.86rem;font-weight:600;color:#94a3b8;margin-bottom:4px;">No campaigns yet</div>
        <a href="<?= SITE_URL ?>/campaign_staff/create_campaign.php"
           style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;padding:9px 18px;background:linear-gradient(135deg,#16a34a,#15803d);color:white;border-radius:9px;font-size:.8rem;font-weight:700;text-decoration:none;">
            <i class="bi bi-plus-circle-fill"></i>Create First Campaign
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
