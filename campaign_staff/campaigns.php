<?php
$pageTitle  = 'Campaigns';
$activePage = 'campaigns';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('campaign_staff');
$db  = getApplicationDB();
$uid = $user['id'];

$filter = $_GET['status'] ?? '';
$where  = $filter ? "WHERE c.status='".$db->real_escape_string($filter)."'" : '';

$campaigns = $db->query("
    SELECT c.*,
           COUNT(DISTINCT d.id) as donation_count,
           DATEDIFF(c.end_date,CURDATE()) as days_left,
           ROUND((c.raised_amount/c.target_amount)*100,1) as pct
    FROM campaigns c
    LEFT JOIN donations d ON d.campaign_id=c.id AND d.status='verified'
    $where
    GROUP BY c.id
    ORDER BY c.created_at DESC
");

$counts = array();
foreach(array('active','draft','completed','closed') as $s){
    $r=$db->query("SELECT COUNT(*) as c FROM campaigns WHERE status='$s'");
    $counts[$s]=(int)$r->fetch_assoc()['c'];
}
$total = array_sum($counts);

$status_styles = array(
    'active'    => array('bg'=>'#dcfce7','color'=>'#15803d','border'=>'#bbf7d0','label'=>'Active'),
    'completed' => array('bg'=>'#dbeafe','color'=>'#1447c0','border'=>'#bfdbfe','label'=>'Completed'),
    'draft'     => array('bg'=>'#fef9c3','color'=>'#854d0e','border'=>'#fde68a','label'=>'Draft'),
    'closed'    => array('bg'=>'#f1f5f9','color'=>'#94a3b8','border'=>'#e2e8f0','label'=>'Closed'),
);
?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-megaphone-fill" style="color:#15803d;font-size:1rem;"></i>Campaigns
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">Manage all fundraising campaigns</p>
    </div>
    <a href="<?= SITE_URL ?>/campaign_staff/create_campaign.php"
       style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:linear-gradient(135deg,#16a34a,#15803d);color:white;border-radius:10px;font-weight:700;font-size:.82rem;box-shadow:0 3px 12px rgba(22,163,74,.3);text-decoration:none;white-space:nowrap;transition:all .18s;flex-shrink:0;"
       onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
        <i class="bi bi-plus-circle-fill"></i>New Campaign
    </a>
</div>

<!-- Filter tabs -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <?php
    $tabs = array(
        array('','All',$total,'#0f172a','#f1f5f9','#e2e8f0'),
        array('active','Active',$counts['active'],'#15803d','#dcfce7','#bbf7d0'),
        array('draft','Draft',$counts['draft'],'#854d0e','#fef9c3','#fde68a'),
        array('completed','Completed',$counts['completed'],'#1447c0','#dbeafe','#bfdbfe'),
        array('closed','Closed',$counts['closed'],'#94a3b8','#f1f5f9','#e2e8f0'),
    );
    foreach($tabs as $tab):
        list($val,$label,$cnt,$color,$bg,$border) = $tab;
        $active = $filter === $val;
    ?>
    <a href="?status=<?= $val ?>"
       style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:.78rem;font-weight:700;border:1.5px solid <?= $active?$border:'#e2e8f0' ?>;background:<?= $active?$bg:'white' ?>;color:<?= $active?$color:'#64748b' ?>;transition:all .15s;text-decoration:none;"
       onmouseover="this.style.background='<?= $bg ?>'" onmouseout="this.style.background='<?= $active?$bg:'white' ?>'">
        <?= $label ?>
        <span style="background:<?= $active?$border:'#f1f5f9' ?>;color:<?= $active?$color:'#94a3b8' ?>;padding:1px 7px;border-radius:10px;font-size:.67rem;font-weight:800;"><?= $cnt ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Campaign grid -->
<?php if($campaigns && $campaigns->num_rows > 0): ?>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:18px;">
    <?php while($c=$campaigns->fetch_assoc()):
        $pct  = min(100,(float)($c['pct']??0));
        $done = $pct >= 100;
        $ss   = isset($status_styles[$c['status']]) ? $status_styles[$c['status']] : $status_styles['draft'];
        $urgent = $c['days_left']>0 && $c['days_left']<=14;
        $ended  = $c['days_left']<=0;
    ?>
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);transition:all .2s;display:flex;flex-direction:column;"
         onmouseover="this.style.boxShadow='0 8px 28px rgba(0,0,0,.1)';this.style.transform='translateY(-2px)'"
         onmouseout="this.style.boxShadow='0 2px 8px rgba(0,0,0,.05)';this.style.transform='none'">

        <!-- Campaign image -->
        <div style="height:150px;position:relative;overflow:hidden;background:#dbeafe;flex-shrink:0;">
            <?php if($c['image_url']): ?>
            <img src="<?= htmlspecialchars($c['image_url']) ?>"
                 alt="<?= htmlspecialchars($c['title']) ?>"
                 style="width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s;"
                 onerror="this.style.display='none'"
                 onmouseover="this.style.transform='scale(1.04)'" onmouseout="this.style.transform='scale(1)'">
            <?php else: ?>
            <div style="width:100%;height:100%;background:linear-gradient(135deg,#1e3a8a,#3b82f6);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-megaphone-fill" style="font-size:2.5rem;color:rgba(255,255,255,.2);"></i>
            </div>
            <?php endif; ?>
            <div style="position:absolute;inset:0;background:linear-gradient(180deg,transparent 40%,rgba(0,0,0,.4));"></div>

            <!-- Status badge -->
            <div style="position:absolute;top:10px;left:10px;z-index:2;">
                <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $ss['bg'] ?>;color:<?= $ss['color'] ?>;border:1px solid <?= $ss['border'] ?>;padding:3px 10px;border-radius:20px;font-size:.67rem;font-weight:700;backdrop-filter:blur(4px);">
                    <?= $ss['label'] ?>
                </span>
            </div>

            <!-- Days left -->
            <div style="position:absolute;top:10px;right:10px;z-index:2;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);color:rgba(255,255,255,.9);padding:3px 9px;border-radius:20px;font-size:.66rem;font-weight:600;">
                <?php if($ended): ?>Ended
                <?php elseif($urgent): ?><span style="color:#fbbf24;"><?= $c['days_left'] ?>d left</span>
                <?php else: ?><?= $c['days_left'] ?>d left<?php endif; ?>
            </div>
        </div>

        <!-- Body -->
        <div style="padding:16px 18px;flex:1;display:flex;flex-direction:column;">
            <div style="font-size:.86rem;font-weight:700;color:#0f172a;margin-bottom:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= htmlspecialchars($c['title']) ?>
            </div>

            <div style="font-size:.74rem;color:#64748b;margin-bottom:12px;display:flex;align-items:center;gap:8px;">
                <span><i class="bi bi-receipt" style="font-size:.7rem;margin-right:2px;"></i><?= $c['donation_count'] ?> donations</span>
                <span style="color:#e2e8f0;">·</span>
                <span><?= date('d/m/Y',strtotime($c['start_date'])) ?> – <?= date('d/m/Y',strtotime($c['end_date'])) ?></span>
            </div>

            <!-- Progress -->
            <div style="margin-bottom:13px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                    <span style="font-size:.73rem;font-weight:600;color:#0f172a;"><?= number_format($c['raised_amount']/1000000,1) ?>M VND raised</span>
                    <span style="font-size:.74rem;font-weight:800;color:<?= $done?'#15803d':'#1447c0' ?>;"><?= $pct ?>%</span>
                </div>
                <div style="height:7px;background:#f1f5f9;border-radius:4px;overflow:hidden;margin-bottom:4px;">
                    <div style="height:100%;width:<?= $pct ?>%;background:<?= $done?'linear-gradient(90deg,#22c55e,#16a34a)':'linear-gradient(90deg,#3b82f6,#1447c0)' ?>;border-radius:4px;transition:width .5s;"></div>
                </div>
                <div style="font-size:.68rem;color:#94a3b8;">Target: <?= number_format($c['target_amount']/1000000,0) ?>M VND</div>
            </div>

            <!-- Actions -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-top:auto;">
                <a href="<?= SITE_URL ?>/campaign_staff/edit_campaign.php?id=<?= $c['id'] ?>"
                   style="display:flex;align-items:center;justify-content:center;gap:5px;padding:8px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.74rem;font-weight:600;color:#475569;text-decoration:none;transition:all .15s;"
                   onmouseover="this.style.background='#f8fafc';this.style.borderColor='#1447c0';this.style.color='#1447c0'"
                   onmouseout="this.style.background='white';this.style.borderColor='#e2e8f0';this.style.color='#475569'">
                    <i class="bi bi-pencil-fill" style="font-size:.72rem;"></i>Edit
                </a>
                <a href="<?= SITE_URL ?>/campaign_staff/fund_allocation.php?campaign_id=<?= $c['id'] ?>"
                   style="display:flex;align-items:center;justify-content:center;gap:5px;padding:8px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:8px;font-size:.74rem;font-weight:700;color:#15803d;text-decoration:none;transition:all .15s;"
                   onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='#f0fdf4'">
                    <i class="bi bi-pie-chart-fill" style="font-size:.72rem;"></i>Funds
                </a>
                <a href="<?= SITE_URL ?>/campaign_staff/campaign_updates.php?campaign_id=<?= $c['id'] ?>"
                   style="display:flex;align-items:center;justify-content:center;gap:5px;padding:8px;background:#faf5ff;border:1.5px solid #e9d5ff;border-radius:8px;font-size:.74rem;font-weight:700;color:#7c3aed;text-decoration:none;transition:all .15s;"
                   onmouseover="this.style.background='#ede9fe'" onmouseout="this.style.background='#faf5ff'">
                    <i class="bi bi-broadcast" style="font-size:.72rem;"></i>Updates
                </a>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<?php else: ?>
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;padding:64px 20px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.04);">
    <i class="bi bi-megaphone" style="font-size:3rem;color:#e2e8f0;display:block;margin-bottom:14px;"></i>
    <div style="font-size:.9rem;font-weight:700;color:#94a3b8;margin-bottom:5px;">No campaigns found</div>
    <div style="font-size:.8rem;color:#cbd5e1;margin-bottom:18px;"><?= $filter ? 'No '.ucfirst($filter).' campaigns' : 'Create your first campaign to get started' ?></div>
    <a href="<?= SITE_URL ?>/campaign_staff/create_campaign.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:10px 22px;background:linear-gradient(135deg,#16a34a,#15803d);color:white;border-radius:10px;font-size:.83rem;font-weight:700;text-decoration:none;box-shadow:0 3px 10px rgba(22,163,74,.3);">
        <i class="bi bi-plus-circle-fill"></i>Create Campaign
    </a>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
