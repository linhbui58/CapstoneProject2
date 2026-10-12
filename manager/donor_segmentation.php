<?php
$pageTitle  = 'Donor Segmentation';
$activePage = 'segmentation';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('manager');

$db = getDB();

// ── Ensure advanced columns exist (auto-migrate) ──
$db->query("ALTER TABLE donor_profiles
    ADD COLUMN IF NOT EXISTS segment ENUM('new','repeat','loyal','high_value','lapsed') DEFAULT 'new',
    ADD COLUMN IF NOT EXISTS segment_updated_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS total_campaigns INT DEFAULT 0");

// ── Auto-update segments ──────────────────────────
$db->query("
    UPDATE donor_profiles dp
    JOIN (
        SELECT d.donor_id,
               COUNT(d.id) as cnt,
               COALESCE(SUM(d.amount),0) as total,
               MAX(d.created_at) as last_donation,
               COUNT(DISTINCT d.campaign_id) as camps
        FROM donations d
        WHERE d.status='verified'
        GROUP BY d.donor_id
    ) s ON dp.user_id = s.donor_id
    SET dp.segment = CASE
            WHEN s.total >= 10000000             THEN 'high_value'
            WHEN s.cnt   >= 5                    THEN 'loyal'
            WHEN s.cnt   >= 2                    THEN 'repeat'
            WHEN DATEDIFF(NOW(),s.last_donation) > 90 THEN 'lapsed'
            ELSE 'new'
        END,
        dp.total_donated   = s.total,
        dp.total_campaigns = s.camps,
        dp.segment_updated_at = NOW()
");

// ── Segment counts ────────────────────────────────
$seg_counts = [];
foreach(['new','repeat','loyal','high_value','lapsed'] as $seg) {
    $r = $db->query("SELECT COUNT(*) as c FROM donor_profiles WHERE segment='$seg'");
    $seg_counts[$seg] = $r ? (int)$r->fetch_assoc()['c'] : 0;
}
$total_donors = array_sum($seg_counts);

// ── Segment totals ────────────────────────────────
$seg_totals = [];
foreach(['new','repeat','loyal','high_value','lapsed'] as $seg) {
    $r = $db->query("SELECT COALESCE(SUM(total_donated),0) as t FROM donor_profiles WHERE segment='$seg'");
    $seg_totals[$seg] = $r ? (float)$r->fetch_assoc()['t'] : 0;
}

// ── Filter ────────────────────────────────────────
$filter = $_GET['segment'] ?? '';
$where  = $filter ? "AND dp.segment='".($db->real_escape_string($filter))."'" : '';

$donors = $db->query("
    SELECT u.name, u.email, u.phone, u.created_at as joined,
           dp.segment, dp.total_donated, dp.total_campaigns,
           dp.segment_updated_at,
           COUNT(d.id) as donation_count,
           MAX(d.created_at) as last_donation
    FROM donor_profiles dp
    JOIN users u ON dp.user_id = u.id
    LEFT JOIN donations d ON d.donor_id = u.id AND d.status='verified'
    WHERE 1=1 $where
    GROUP BY dp.user_id
    ORDER BY dp.total_donated DESC
");

// Segment config — dashboard-consistent palette (pastel icon boxes, solid colors)
$seg_cfg = [
    'new'        => [
        'label'   => 'New Donor',
        'desc'    => '1 donation · getting started',
        'icon'    => 'bi-person-plus-fill',
        'grad'    => 'linear-gradient(135deg,#3b82f6,#1447c0)',
        'bg'      => '#dbeafe',
        'color'   => '#1447c0',
        'border'  => '#bfdbfe',
        'shadow'  => 'rgba(20,71,192,.15)',
        'rec'     => 'Send welcome email + campaign spotlight',
    ],
    'repeat'     => [
        'label'   => 'Repeat Donor',
        'desc'    => '2–4 donations · engaged',
        'icon'    => 'bi-arrow-repeat',
        'grad'    => 'linear-gradient(135deg,#22c55e,#15803d)',
        'bg'      => '#dcfce7',
        'color'   => '#15803d',
        'border'  => '#bbf7d0',
        'shadow'  => 'rgba(21,128,61,.15)',
        'rec'     => 'Share campaign updates & milestones',
    ],
    'loyal'      => [
        'label'   => 'Loyal Donor',
        'desc'    => '5+ donations · highly engaged',
        'icon'    => 'bi-heart-fill',
        'grad'    => 'linear-gradient(135deg,#a855f7,#7c3aed)',
        'bg'      => '#ede9fe',
        'color'   => '#7c3aed',
        'border'  => '#ddd6fe',
        'shadow'  => 'rgba(124,58,237,.15)',
        'rec'     => 'Send impact report + loyalty recognition badge',
    ],
    'high_value' => [
        'label'   => 'High-Value',
        'desc'    => 'Total ≥ 10M VND · top contributor',
        'icon'    => 'bi-star-fill',
        'grad'    => 'linear-gradient(135deg,#f59e0b,#d97706)',
        'bg'      => '#fef9c3',
        'color'   => '#d97706',
        'border'  => '#fde68a',
        'shadow'  => 'rgba(217,119,6,.15)',
        'rec'     => 'Personalized thank-you + VIP recognition',
    ],
    'lapsed'     => [
        'label'   => 'Lapsed Donor',
        'desc'    => 'No donation > 90 days · re-engage',
        'icon'    => 'bi-clock-history',
        'grad'    => 'linear-gradient(135deg,#f87171,#dc2626)',
        'bg'      => '#fee2e2',
        'color'   => '#dc2626',
        'border'  => '#fecaca',
        'shadow'  => 'rgba(220,38,38,.15)',
        'rec'     => 'Re-engagement email + featured campaign offer',
    ],
];
?>

<!-- ═══ PAGE HEADER ══════════════════════════════════ -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;gap:16px;">
    <div>
        <h1 style="font-size:1.3rem;font-weight:800;color:#0f172a;margin:0 0 4px;display:flex;align-items:center;gap:10px;">
            <span style="width:36px;height:36px;background:#ede9fe;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-diagram-3-fill" style="color:#7c3aed;font-size:.9rem;"></i>
            </span>
            Donor Segmentation
            <span style="background:#ede9fe;color:#6d28d9;padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:700;">Advanced Feature</span>
        </h1>
        <p style="color:#64748b;font-size:.8rem;margin:0 0 0 48px;">Auto-classify donors by behaviour · <strong style="color:#7c3aed;"><?= $total_donors ?> donors</strong> tracked</p>
    </div>
    <a href="<?= SITE_URL ?>/manager/reports.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border:1.5px solid #e2e8f0;border-radius:10px;color:#64748b;font-size:.81rem;font-weight:600;background:white;text-decoration:none;transition:all .15s;white-space:nowrap;"
       onmouseover="this.style.background='#f8fafc';this.style.borderColor='#cbd5e1'" onmouseout="this.style.background='white';this.style.borderColor='#e2e8f0'">
        <i class="bi bi-bar-chart-line-fill" style="font-size:.82rem;"></i> Reports
    </a>
</div>

<!-- ═══ SEGMENT CARDS ════════════════════════════════ -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:13px;margin-bottom:22px;">
    <?php foreach($seg_cfg as $key => $cfg):
        $is_active = $filter === $key;
    ?>
    <a href="?segment=<?= $is_active ? '' : $key ?>"
       style="background:white;border:1.5px solid <?= $is_active ? $cfg['color'] : '#e2e8f0' ?>;border-radius:14px;padding:16px 14px;box-shadow:<?= $is_active ? '0 4px 14px '.$cfg['shadow'] : '0 1px 4px rgba(0,0,0,.04)' ?>;transition:all .18s;text-decoration:none;display:block;"
       onmouseover="this.style.borderColor='<?= $cfg['color'] ?>';this.style.boxShadow='0 4px 14px <?= $cfg['shadow'] ?>'"
       onmouseout="this.style.borderColor='<?= $is_active ? $cfg['color'] : '#e2e8f0' ?>'; this.style.boxShadow='<?= $is_active ? '0 4px 14px '.$cfg['shadow'] : '0 1px 4px rgba(0,0,0,.04)' ?>'">
        <!-- Icon box (pastel, like dashboard) -->
        <div style="width:38px;height:38px;border-radius:10px;background:<?= $cfg['bg'] ?>;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <i class="bi <?= $cfg['icon'] ?>" style="color:<?= $cfg['color'] ?>;font-size:.95rem;"></i>
        </div>
        <div style="font-size:1.35rem;font-weight:800;color:#0f172a;letter-spacing:-.03em;line-height:1;margin-bottom:3px;"><?= $seg_counts[$key] ?></div>
        <div style="font-size:.76rem;font-weight:700;color:<?= $cfg['color'] ?>;margin-bottom:2px;"><?= $cfg['label'] ?></div>
        <div style="font-size:.67rem;color:#94a3b8;line-height:1.4;margin-bottom:<?= $seg_totals[$key] > 0 ? '8' : '0' ?>px;"><?= $cfg['desc'] ?></div>
        <?php if($seg_totals[$key] > 0): ?>
        <div style="background:<?= $cfg['bg'] ?>;border:1px solid <?= $cfg['border'] ?>;border-radius:7px;padding:4px 8px;display:inline-flex;align-items:center;gap:4px;">
            <i class="bi bi-cash-stack" style="color:<?= $cfg['color'] ?>;font-size:.68rem;"></i>
            <span style="font-size:.71rem;font-weight:700;color:<?= $cfg['color'] ?>;"><?= number_format($seg_totals[$key]/1000000,1) ?>M VND</span>
        </div>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- ═══ ALERT BANNERS ════════════════════════════════ -->
<?php if($seg_counts['lapsed'] > 0): ?>
<div style="background:#fff1f2;border:1.5px solid #fecaca;border-radius:12px;padding:14px 20px;margin-bottom:12px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 8px rgba(220,38,38,.07);">
    <div style="width:38px;height:38px;background:#fee2e2;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;font-size:.95rem;"></i>
    </div>
    <div style="flex:1;">
        <div style="font-size:.81rem;font-weight:700;color:#991b1b;margin-bottom:2px;">Re-engagement Opportunity</div>
        <div style="font-size:.75rem;color:#b91c1c;"><strong><?= $seg_counts['lapsed'] ?> donor<?= $seg_counts['lapsed']>1?'s':'' ?></strong> haven't donated in over 90 days. Send a targeted re-engagement campaign to bring them back.</div>
    </div>
    <a href="?segment=lapsed"
       style="padding:7px 14px;background:#dc2626;color:white;border-radius:8px;font-size:.75rem;font-weight:700;text-decoration:none;white-space:nowrap;"
       onmouseover="this.style.background='#b91c1c'" onmouseout="this.style.background='#dc2626'">
        View Lapsed →
    </a>
</div>
<?php endif; ?>

<?php if($seg_counts['high_value'] > 0): ?>
<div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:14px 20px;margin-bottom:22px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 8px rgba(217,119,6,.07);">
    <div style="width:38px;height:38px;background:#fef9c3;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="bi bi-star-fill" style="color:#d97706;font-size:.95rem;"></i>
    </div>
    <div>
        <div style="font-size:.81rem;font-weight:700;color:#854d0e;margin-bottom:2px;">Top Contributor Recognition</div>
        <div style="font-size:.75rem;color:#92400e;"><strong><?= $seg_counts['high_value'] ?> high-value donor<?= $seg_counts['high_value']>1?'s':'' ?></strong> have contributed ≥ 10M VND each. Send personalized acknowledgements to strengthen their loyalty.</div>
    </div>
</div>
<?php endif; ?>

<!-- ═══ DONOR TABLE ═══════════════════════════════════ -->
<div style="background:white;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 4px 20px rgba(0,0,0,.06);overflow:hidden;">

    <!-- Table header -->
    <div style="padding:13px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:9px;">
            <i class="bi bi-list-ul" style="color:#7c3aed;font-size:.88rem;"></i>
            <span style="font-size:.86rem;font-weight:700;color:#0f172a;">
                <?= $filter ? ($seg_cfg[$filter]['label']??ucfirst($filter)).' Donors' : 'All Donors' ?>
            </span>
            <span style="background:#ede9fe;color:#6d28d9;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:700;">
                <?= $donors ? $donors->num_rows : 0 ?>
            </span>
        </div>
        <!-- Filter tabs -->
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <a href="?" style="padding:4px 11px;border-radius:20px;font-size:.74rem;font-weight:600;border:1.5px solid <?= !$filter?'#7c3aed':'#e2e8f0' ?>;background:<?= !$filter?'#ede9fe':'white' ?>;color:<?= !$filter?'#7c3aed':'#64748b' ?>;text-decoration:none;transition:all .15s;">All</a>
            <?php foreach($seg_cfg as $key=>$cfg): ?>
            <a href="?segment=<?= $key ?>"
               style="padding:4px 11px;border-radius:20px;font-size:.74rem;font-weight:600;border:1.5px solid <?= $filter===$key?$cfg['color']:'#e2e8f0' ?>;background:<?= $filter===$key?$cfg['bg']:'white' ?>;color:<?= $filter===$key?$cfg['color']:'#64748b' ?>;text-decoration:none;transition:all .15s;">
                <?= $cfg['label'] ?> <span style="font-size:.67rem;opacity:.85;">(<?= $seg_counts[$key] ?>)</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#fafafa;">
                    <?php foreach(['Donor','Segment','Total Donated','Donations','Campaigns','Last Donation','Joined','Recommendation'] as $h): ?>
                    <th style="padding:11px 18px;text-align:left;font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;border-bottom:2px solid #f1f5f9;white-space:nowrap;"><?= $h ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if($donors && $donors->num_rows > 0): while($d=$donors->fetch_assoc()):
                $seg = $d['segment'] ?? 'new';
                $cfg = $seg_cfg[$seg] ?? $seg_cfg['new'];
                $rec_map = [
                    'new'        => 'Send welcome + campaign spotlight',
                    'repeat'     => 'Share campaign updates & milestones',
                    'loyal'      => 'Send impact report + loyalty badge',
                    'high_value' => 'Personalized thank-you + VIP recognition',
                    'lapsed'     => 'Re-engagement email + featured campaign',
                ];
                $rec = $rec_map[$seg] ?? 'Send campaign updates';
            ?>
            <tr style="border-bottom:1px solid #f3f4f6;transition:background .12s;"
                onmouseover="this.style.background='#fafaff'" onmouseout="this.style.background='white'">

                <!-- Donor -->
                <td style="padding:13px 18px;min-width:170px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:34px;height:34px;border-radius:50%;background:<?= $cfg['bg'] ?>;border:1.5px solid <?= $cfg['border'] ?>;display:flex;align-items:center;justify-content:center;font-size:.76rem;font-weight:800;color:<?= $cfg['color'] ?>;flex-shrink:0;">
                            <?= strtoupper(substr($d['name'],0,1)) ?>
                        </div>
                        <div>
                            <div style="font-size:.81rem;font-weight:700;color:#0f172a;"><?= htmlspecialchars($d['name']) ?></div>
                            <div style="font-size:.69rem;color:#94a3b8;margin-top:1px;"><?= htmlspecialchars($d['email']) ?></div>
                        </div>
                    </div>
                </td>

                <!-- Segment badge -->
                <td style="padding:13px 18px;">
                    <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $cfg['bg'] ?>;color:<?= $cfg['color'] ?>;border:1px solid <?= $cfg['border'] ?>;padding:4px 10px;border-radius:20px;font-size:.71rem;font-weight:700;white-space:nowrap;">
                        <i class="bi <?= $cfg['icon'] ?>" style="font-size:.68rem;"></i><?= $cfg['label'] ?>
                    </span>
                </td>

                <!-- Total donated -->
                <td style="padding:13px 18px;white-space:nowrap;">
                    <div style="font-size:.88rem;font-weight:800;color:#0f172a;"><?= number_format($d['total_donated']/1000000,1) ?>M</div>
                    <div style="font-size:.66rem;color:#94a3b8;margin-top:1px;">VND</div>
                </td>

                <!-- Donations count -->
                <td style="padding:13px 18px;text-align:center;">
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;background:<?= $cfg['bg'] ?>;border:1.5px solid <?= $cfg['border'] ?>;border-radius:50%;font-size:.8rem;font-weight:800;color:<?= $cfg['color'] ?>;">
                        <?= $d['donation_count'] ?>
                    </span>
                </td>

                <!-- Campaigns -->
                <td style="padding:13px 18px;text-align:center;">
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;background:#f1f5f9;border-radius:50%;font-size:.8rem;font-weight:700;color:#475569;">
                        <?= $d['total_campaigns'] ?>
                    </span>
                </td>

                <!-- Last donation -->
                <td style="padding:13px 18px;white-space:nowrap;">
                    <?php if($d['last_donation']): ?>
                        <div style="font-size:.77rem;font-weight:600;color:#334155;"><?= formatDate($d['last_donation']) ?></div>
                        <?php
                        $days = (int)((time() - strtotime($d['last_donation'])) / 86400);
                        $day_color = $days > 90 ? '#dc2626' : ($days > 30 ? '#d97706' : '#15803d');
                        echo '<div style="font-size:.67rem;color:'.$day_color.';margin-top:2px;font-weight:600;">'.$days.'d ago</div>';
                        ?>
                    <?php else: ?>
                        <span style="color:#cbd5e1;font-size:.8rem;">—</span>
                    <?php endif; ?>
                </td>

                <!-- Joined -->
                <td style="padding:13px 18px;font-size:.76rem;color:#64748b;white-space:nowrap;">
                    <?= formatDate($d['joined']) ?>
                </td>

                <!-- Recommendation -->
                <td style="padding:13px 18px;min-width:210px;">
                    <div style="display:flex;align-items:flex-start;gap:7px;background:<?= $cfg['bg'] ?>;border-radius:8px;padding:7px 10px;border:1px solid <?= $cfg['border'] ?>;">
                        <i class="bi bi-lightbulb-fill" style="color:<?= $cfg['color'] ?>;font-size:.76rem;flex-shrink:0;margin-top:1px;"></i>
                        <span style="font-size:.73rem;color:#374151;line-height:1.45;font-weight:500;"><?= $rec ?></span>
                    </div>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="8" style="padding:60px;text-align:center;">
                <i class="bi bi-people" style="font-size:2.5rem;color:#e2e8f0;display:block;margin-bottom:12px;"></i>
                <div style="font-size:.86rem;font-weight:600;color:#94a3b8;">No donors found in this segment</div>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div style="padding:12px 22px;border-top:1px solid #f1f5f9;background:#fafafa;display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:.72rem;color:#94a3b8;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-arrow-clockwise" style="color:#7c3aed;"></i>
            Segments auto-updated on page load · Based on verified donations only
        </span>
        <span style="font-size:.72rem;color:#94a3b8;">Total: <strong style="color:#0f172a;"><?= $total_donors ?></strong> donors across all segments</span>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
