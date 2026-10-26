<?php
$pageTitle  = 'All Campaigns';
$activePage = 'campaigns';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('manager');
$db = getApplicationDB();

$filter = $_GET['status'] ?? '';
$where  = $filter ? "WHERE c.status='".$db->real_escape_string($filter)."'" : '';

$campaigns = $db->query("
    SELECT c.*, u.name as created_by_name,
           COUNT(DISTINCT d.id) as donation_count,
           DATEDIFF(c.end_date,CURDATE()) as days_left,
           SUM(fa.amount) as total_allocated,
           ROUND((c.raised_amount/c.target_amount)*100,1) as pct
    FROM campaigns c
    JOIN users u ON c.created_by=u.id
    LEFT JOIN donations d ON d.campaign_id=c.id AND d.status='verified'
    LEFT JOIN fund_allocations fa ON fa.campaign_id=c.id
    $where
    GROUP BY c.id
    ORDER BY c.created_at DESC
");

// Summary counts
$counts = [];
foreach(['active','completed','draft','closed'] as $s){
    $r=$db->query("SELECT COUNT(*) as c FROM campaigns WHERE status='$s'");
    $counts[$s]=(int)$r->fetch_assoc()['c'];
}
$total_count = array_sum($counts);

$status_styles = [
    'active'    => ['bg'=>'#dcfce7','color'=>'#15803d','border'=>'#bbf7d0','label'=>'Active'],
    'completed' => ['bg'=>'#dbeafe','color'=>'#1447c0','border'=>'#bfdbfe','label'=>'Completed'],
    'draft'     => ['bg'=>'#f1f5f9','color'=>'#475569','border'=>'#e2e8f0','label'=>'Draft'],
    'closed'    => ['bg'=>'#f1f5f9','color'=>'#94a3b8','border'=>'#e2e8f0','label'=>'Closed'],
];
?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-megaphone-fill" style="color:#7c3aed;font-size:1rem;"></i>All Campaigns
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">Overview of all fundraising campaigns</p>
    </div>
</div>

<!-- Summary mini stats -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px;">
    <?php
    $mini = [
        ['All',       $total_count,       '#f1f5f9','#475569','bi-collection'],
        ['Active',    $counts['active'],   '#dcfce7','#15803d','bi-play-circle-fill'],
        ['Completed', $counts['completed'],'#dbeafe','#1447c0','bi-check-circle-fill'],
        ['Draft',     $counts['draft'],    '#fef9c3','#854d0e','bi-pencil-fill'],
        ['Closed',    $counts['closed'],   '#f1f5f9','#94a3b8','bi-x-circle-fill'],
    ];
    foreach($mini as [$lbl,$val,$bg,$color,$ic]): ?>
    <a href="?status=<?= strtolower($lbl)==='all'?'':strtolower($lbl) ?>"
       style="background:white;border:1.5px solid <?= $filter===(strtolower($lbl)==='all'?'':strtolower($lbl))?$color:'#e2e8f0' ?>;border-radius:12px;padding:13px 14px;box-shadow:0 1px 4px rgba(0,0,0,.04);display:flex;align-items:center;gap:11px;text-decoration:none;transition:all .15s;"
       onmouseover="this.style.borderColor='<?= $color ?>'" onmouseout="this.style.borderColor='<?= $filter===(strtolower($lbl)==='all'?'':strtolower($lbl))?$color:'#e2e8f0' ?>'">
        <div style="width:34px;height:34px;border-radius:9px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi <?= $ic ?>" style="color:<?= $color ?>;font-size:.88rem;"></i>
        </div>
        <div>
            <div style="font-size:1.05rem;font-weight:800;color:#0f172a;letter-spacing:-.02em;line-height:1.1;"><?= $val ?></div>
            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-top:1px;"><?= $lbl ?></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- Main table card -->
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;">

    <!-- Card header -->
    <div style="padding:14px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:7px;">
            <i class="bi bi-list-ul" style="color:#7c3aed;font-size:.88rem;"></i>
            <span style="font-size:.86rem;font-weight:700;color:#0f172a;">Campaign List</span>
            <?php if($filter): ?>
            <span style="background:#ede9fe;color:#6d28d9;padding:2px 9px;border-radius:20px;font-size:.69rem;font-weight:700;">
                <?= ucfirst($filter) ?>
            </span>
            <?php endif; ?>
        </div>
        <?php if($filter): ?>
        <a href="?" style="font-size:.76rem;color:#64748b;text-decoration:none;display:flex;align-items:center;gap:4px;"
           onmouseover="this.style.color='#0f172a'" onmouseout="this.style.color='#64748b'">
            <i class="bi bi-x-circle" style="font-size:.78rem;"></i>Clear filter
        </a>
        <?php endif; ?>
    </div>

    <!-- Table -->
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc;">
                    <?php foreach(['Title','Target','Raised','Progress','Donations','Allocated','Deadline','Status','Created By',''] as $h): ?>
                    <th style="padding:10px 16px;text-align:left;font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;border-bottom:1px solid #f1f5f9;white-space:nowrap;"><?= $h ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if($campaigns && $campaigns->num_rows>0): while($c=$campaigns->fetch_assoc()):
                $pct     = min(100,(float)($c['pct']??0));
                $done    = $pct >= 100;
                $ss      = $status_styles[$c['status']] ?? $status_styles['draft'];
                $urgent  = $c['days_left'] > 0 && $c['days_left'] <= 14;
                $ended   = $c['days_left'] <= 0;
            ?>
            <tr style="border-bottom:1px solid #f9fafb;transition:background .12s;"
                onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">

                <!-- Title -->
                <td style="padding:13px 16px;min-width:180px;max-width:220px;">
                    <a href="<?= SITE_URL ?>/manager/campaign_detail.php?id=<?= $c['id'] ?>"
                       style="font-size:.82rem;font-weight:700;color:#1447c0;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;"
                       onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                        <?= htmlspecialchars($c['title']) ?>
                    </a>
                    <?php if($c['image_url']): ?>
                    <div style="font-size:.67rem;color:#94a3b8;display:flex;align-items:center;gap:3px;margin-top:2px;">
                        <i class="bi bi-image" style="font-size:.64rem;"></i>Has image
                    </div>
                    <?php endif; ?>
                </td>

                <!-- Target -->
                <td style="padding:13px 16px;white-space:nowrap;">
                    <span style="font-size:.79rem;font-weight:600;color:#475569;"><?= number_format($c['target_amount']/1000000,0) ?>M</span>
                    <span style="font-size:.67rem;color:#94a3b8;"> VND</span>
                </td>

                <!-- Raised -->
                <td style="padding:13px 16px;white-space:nowrap;">
                    <span style="font-size:.82rem;font-weight:800;color:<?= $done?'#15803d':'#1447c0' ?>;"><?= number_format($c['raised_amount']/1000000,1) ?>M</span>
                    <span style="font-size:.67rem;color:#94a3b8;"> VND</span>
                </td>

                <!-- Progress -->
                <td style="padding:13px 16px;min-width:110px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                        <span style="font-size:.7rem;color:#94a3b8;">Progress</span>
                        <span style="font-size:.72rem;font-weight:800;color:<?= $done?'#15803d':'#1447c0' ?>;"><?= $pct ?>%</span>
                    </div>
                    <div style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?= $pct ?>%;background:<?= $done?'linear-gradient(90deg,#22c55e,#16a34a)':'linear-gradient(90deg,#3b82f6,#1447c0)' ?>;border-radius:3px;transition:width .5s;"></div>
                    </div>
                </td>

                <!-- Donations -->
                <td style="padding:13px 16px;text-align:center;">
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;background:#f1f5f9;border-radius:50%;font-size:.78rem;font-weight:800;color:#0f172a;">
                        <?= $c['donation_count'] ?>
                    </span>
                </td>

                <!-- Allocated -->
                <td style="padding:13px 16px;white-space:nowrap;">
                    <?php if($c['total_allocated']): ?>
                    <span style="font-size:.78rem;font-weight:700;color:#d97706;"><?= number_format($c['total_allocated']/1000000,1) ?>M</span>
                    <span style="font-size:.67rem;color:#94a3b8;"> VND</span>
                    <?php else: ?>
                    <span style="color:#e2e8f0;font-size:.8rem;">—</span>
                    <?php endif; ?>
                </td>

                <!-- Deadline -->
                <td style="padding:13px 16px;white-space:nowrap;">
                    <div style="font-size:.77rem;font-weight:600;color:#0f172a;"><?= date('d/m/Y',strtotime($c['end_date'])) ?></div>
                    <div style="font-size:.68rem;<?= $ended?'color:#991b1b;':($urgent?'color:#d97706;':'color:#94a3b8;') ?>">
                        <?php if($ended): ?>
                        <i class="bi bi-x-circle-fill" style="font-size:.65rem;"></i> Ended
                        <?php elseif($urgent): ?>
                        <i class="bi bi-exclamation-triangle-fill" style="font-size:.65rem;"></i> <?= $c['days_left'] ?>d left
                        <?php else: ?>
                        <?= $c['days_left'] ?>d left
                        <?php endif; ?>
                    </div>
                </td>

                <!-- Status -->
                <td style="padding:13px 16px;">
                    <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $ss['bg'] ?>;color:<?= $ss['color'] ?>;border:1px solid <?= $ss['border'] ?>;padding:4px 10px;border-radius:20px;font-size:.71rem;font-weight:700;white-space:nowrap;">
                        <?= $ss['label'] ?>
                    </span>
                </td>

                <!-- Created by -->
                <td style="padding:13px 16px;">
                    <div style="display:flex;align-items:center;gap:7px;">
                        <div style="width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#6d28d9);display:flex;align-items:center;justify-content:center;font-size:.62rem;font-weight:800;color:white;flex-shrink:0;">
                            <?= strtoupper(substr($c['created_by_name'],0,1)) ?>
                        </div>
                        <span style="font-size:.74rem;color:#475569;white-space:nowrap;"><?= htmlspecialchars(explode(' ',$c['created_by_name'])[0]) ?></span>
                    </div>
                </td>

                <!-- View detail -->
                <td style="padding:13px 16px;">
                    <a href="<?= SITE_URL ?>/manager/campaign_detail.php?id=<?= $c['id'] ?>"
                       style="display:inline-flex;align-items:center;gap:5px;padding:6px 13px;background:#eff6ff;border:1.5px solid #dbeafe;color:#1447c0;border-radius:8px;font-size:.75rem;font-weight:700;text-decoration:none;transition:all .15s;white-space:nowrap;"
                       onmouseover="this.style.background='#dbeafe';this.style.borderColor='#93c5fd'"
                       onmouseout="this.style.background='#eff6ff';this.style.borderColor='#dbeafe'">
                        <i class="bi bi-eye-fill" style="font-size:.76rem;"></i>View
                    </a>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
                <td colspan="10" style="padding:52px 20px;text-align:center;">
                    <i class="bi bi-megaphone" style="font-size:2.5rem;color:#e2e8f0;display:block;margin-bottom:12px;"></i>
                    <div style="font-size:.84rem;font-weight:600;color:#94a3b8;margin-bottom:4px;">No campaigns found</div>
                    <div style="font-size:.76rem;color:#cbd5e1;">
                        <?= $filter ? 'No '.ucfirst($filter).' campaigns' : 'Create your first campaign to get started' ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Footer -->
    <div style="padding:11px 20px;border-top:1px solid #f8fafc;background:#fafafa;display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:.73rem;color:#94a3b8;">
            Showing <strong style="color:#475569;"><?= $campaigns ? $campaigns->num_rows : 0 ?></strong> campaigns
            <?php if($filter): ?> · Status: <strong style="color:#7c3aed;"><?= ucfirst($filter) ?></strong><?php endif; ?>
        </span>
        <div style="display:flex;align-items:center;gap:6px;">
            <div style="width:8px;height:8px;border-radius:50%;background:#22c55e;"></div>
            <span style="font-size:.71rem;color:#64748b;">Active = accepting donations</span>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
