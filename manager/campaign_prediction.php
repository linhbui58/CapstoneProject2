<?php
$pageTitle  = 'Campaign Predictions';
$activePage = 'prediction';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('manager');

$db = getDB();

// ── Fetch campaigns ──────────────────────────────────
$campaigns = $db->query("
    SELECT c.*,
           DATEDIFF(c.end_date, CURDATE()) as days_left,
           DATEDIFF(CURDATE(), c.start_date) as days_elapsed,
           DATEDIFF(c.end_date, c.start_date) as total_days,
           COUNT(DISTINCT d.id) as donation_count,
           COUNT(DISTINCT d.donor_id) as unique_donors,
           COALESCE(AVG(d.amount),0) as avg_donation,
           ROUND((c.raised_amount / c.target_amount)*100, 1) as pct_reached
    FROM campaigns c
    LEFT JOIN donations d ON d.campaign_id=c.id AND d.status='verified'
    WHERE c.status IN ('active','draft')
    GROUP BY c.id
    ORDER BY c.raised_amount DESC
");

// ── Prediction logic ─────────────────────────────────
function predictCampaign($c) {
    $days_elapsed = max(1, (int)$c['days_elapsed']);
    $days_left    = max(0, (int)$c['days_left']);
    $raised       = (float)$c['raised_amount'];
    $target       = (float)$c['target_amount'];
    $daily_rate   = $raised / $days_elapsed;
    $projected    = $raised + ($daily_rate * $days_left);
    $proj_pct     = $target > 0 ? min(200, ($projected / $target) * 100) : 0;
    $days_to_target = ($daily_rate > 0 && $raised < $target)
        ? ceil(($target - $raised) / $daily_rate) : 0;

    if ($raised >= $target)        $status = 'goal_reached';
    elseif ($proj_pct >= 90)       $status = 'on_track';
    elseif ($proj_pct >= 60)       $status = 'needs_attention';
    else                           $status = 'at_risk';

    $confidence = min(95, max(30, $days_elapsed * 2 + (int)$c['donation_count'] * 3));

    return [
        'daily_rate'     => $daily_rate,
        'projected'      => $projected,
        'proj_pct'       => round($proj_pct, 1),
        'days_to_target' => $days_to_target,
        'status'         => $status,
        'confidence'     => $confidence,
        'will_reach'     => $days_to_target > 0 && $days_to_target <= $days_left,
    ];
}

// Status config — dashboard-consistent palette
$status_cfg = [
    'goal_reached'    => [
        'label' => 'Goal Reached',
        'bg'    => '#dcfce7',
        'color' => '#15803d',
        'border'=> '#bbf7d0',
        'shadow'=> 'rgba(21,128,61,.12)',
        'icon'  => 'bi-trophy-fill',
        'bar'   => 'linear-gradient(90deg,#22c55e,#16a34a)',
    ],
    'on_track'        => [
        'label' => 'On Track',
        'bg'    => '#dbeafe',
        'color' => '#1447c0',
        'border'=> '#bfdbfe',
        'shadow'=> 'rgba(20,71,192,.12)',
        'icon'  => 'bi-graph-up-arrow',
        'bar'   => 'linear-gradient(90deg,#60a5fa,#3b82f6)',
    ],
    'needs_attention' => [
        'label' => 'Needs Attention',
        'bg'    => '#fef9c3',
        'color' => '#d97706',
        'border'=> '#fde68a',
        'shadow'=> 'rgba(217,119,6,.12)',
        'icon'  => 'bi-exclamation-triangle-fill',
        'bar'   => 'linear-gradient(90deg,#fbbf24,#f59e0b)',
    ],
    'at_risk'         => [
        'label' => 'At Risk',
        'bg'    => '#fee2e2',
        'color' => '#dc2626',
        'border'=> '#fecaca',
        'shadow'=> 'rgba(220,38,38,.12)',
        'icon'  => 'bi-x-octagon-fill',
        'bar'   => 'linear-gradient(90deg,#f87171,#ef4444)',
    ],
];

// ── Build predictions ────────────────────────────────
$summary = ['goal_reached'=>0,'on_track'=>0,'needs_attention'=>0,'at_risk'=>0];
$all_predictions = [];
if($campaigns && $campaigns->num_rows > 0) {
    $campaigns->data_seek(0);
    while($c = $campaigns->fetch_assoc()) {
        $pred = predictCampaign($c);
        $summary[$pred['status']]++;
        $all_predictions[$c['id']] = $pred;
    }
    $campaigns->data_seek(0);
}
$total_active = array_sum($summary);
?>

<!-- ═══ PAGE HEADER ════════════════════════════════ -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;gap:16px;">
    <div>
        <h1 style="font-size:1.3rem;font-weight:800;color:#0f172a;margin:0 0 4px;display:flex;align-items:center;gap:10px;">
            <span style="width:36px;height:36px;background:#dbeafe;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-graph-up-arrow" style="color:#1447c0;font-size:.9rem;"></i>
            </span>
            Campaign Predictions
            <span style="background:#dbeafe;color:#1447c0;padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:700;">Advanced Feature</span>
        </h1>
        <p style="color:#64748b;font-size:.8rem;margin:0 0 0 48px;">Forecast campaign goal attainment using linear daily run-rate model · <strong style="color:#1447c0;"><?= $total_active ?> active campaign<?= $total_active!=1?'s':'' ?></strong> analysed</p>
    </div>
    <a href="<?= SITE_URL ?>/manager/reports.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border:1.5px solid #e2e8f0;border-radius:10px;color:#64748b;font-size:.81rem;font-weight:600;background:white;text-decoration:none;transition:all .15s;white-space:nowrap;"
       onmouseover="this.style.background='#f8fafc';this.style.borderColor='#cbd5e1'" onmouseout="this.style.background='white';this.style.borderColor='#e2e8f0'">
        <i class="bi bi-bar-chart-line-fill" style="font-size:.82rem;"></i> Reports
    </a>
</div>

<!-- ═══ SUMMARY CARDS ═══════════════════════════════ -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:13px;margin-bottom:22px;">
    <?php foreach($status_cfg as $key => $cfg):
        $count = $summary[$key];
    ?>
    <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:16px 14px;box-shadow:0 1px 4px rgba(0,0,0,.04);transition:all .2s;"
         onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.08)'"
         onmouseout="this.style.transform='none';this.style.boxShadow='0 1px 4px rgba(0,0,0,.04)'">
        <div style="width:38px;height:38px;border-radius:10px;background:<?= $cfg['bg'] ?>;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <i class="bi <?= $cfg['icon'] ?>" style="color:<?= $cfg['color'] ?>;font-size:.95rem;"></i>
        </div>
        <div style="font-size:1.55rem;font-weight:800;color:#0f172a;letter-spacing:-.03em;line-height:1;margin-bottom:4px;"><?= $count ?></div>
        <div style="font-size:.76rem;font-weight:700;color:<?= $cfg['color'] ?>;margin-bottom:2px;"><?= $cfg['label'] ?></div>
        <div style="font-size:.66rem;color:#94a3b8;">campaign<?= $count!=1?'s':'' ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ═══ ALERT BANNER ════════════════════════════════ -->
<?php if($summary['at_risk'] > 0): ?>
<div style="background:#fff1f2;border:1.5px solid #fecaca;border-radius:12px;padding:14px 20px;margin-bottom:12px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 8px rgba(220,38,38,.07);">
    <div style="width:38px;height:38px;background:#fee2e2;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="bi bi-exclamation-octagon-fill" style="color:#dc2626;font-size:.95rem;"></i>
    </div>
    <div style="flex:1;">
        <div style="font-size:.82rem;font-weight:700;color:#991b1b;margin-bottom:2px;"><?= $summary['at_risk'] ?> Campaign<?= $summary['at_risk']>1?'s':'' ?> At Risk</div>
        <div style="font-size:.76rem;color:#b91c1c;">At current pace, projected to reach <strong>less than 60%</strong> of goal. Consider boosting outreach or extending the deadline.</div>
    </div>
</div>
<?php endif; ?>

<?php if($summary['needs_attention'] > 0): ?>
<div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:14px 20px;margin-bottom:22px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 8px rgba(217,119,6,.07);">
    <div style="width:38px;height:38px;background:#fef9c3;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="bi bi-exclamation-triangle-fill" style="color:#d97706;font-size:.95rem;"></i>
    </div>
    <div>
        <div style="font-size:.82rem;font-weight:700;color:#854d0e;margin-bottom:2px;"><?= $summary['needs_attention'] ?> Campaign<?= $summary['needs_attention']>1?'s':'' ?> Need Attention</div>
        <div style="font-size:.76rem;color:#92400e;">Projected to reach 60–89% of goal. Step up promotion now to get back on track.</div>
    </div>
</div>
<?php endif; ?>

<!-- ═══ PREDICTION CARDS GRID ════════════════════════ -->
<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">
<?php
if($campaigns && $campaigns->num_rows > 0):
    $campaigns->data_seek(0);
    while($c = $campaigns->fetch_assoc()):
        $pred = $all_predictions[$c['id']];
        $cfg  = $status_cfg[$pred['status']];
        $pct_current = min(100, (float)$c['pct_reached']);
        $pct_proj    = min(100, $pred['proj_pct']);
?>
<div style="background:white;border:1.5px solid <?= $cfg['border'] ?>;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);transition:all .2s;"
     onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.09)'"
     onmouseout="this.style.transform='none';this.style.boxShadow='0 2px 8px rgba(0,0,0,.05)'">

    <!-- ── Card Header ── -->
    <div style="padding:13px 18px;background:<?= $cfg['bg'] ?>;border-bottom:1px solid <?= $cfg['border'] ?>;display:flex;align-items:center;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:32px;height:32px;background:white;border:1.5px solid <?= $cfg['border'] ?>;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                <i class="bi <?= $cfg['icon'] ?>" style="color:<?= $cfg['color'] ?>;font-size:.85rem;"></i>
            </div>
            <div>
                <div style="font-size:.82rem;font-weight:700;color:<?= $cfg['color'] ?>;"><?= $cfg['label'] ?></div>
                <div style="font-size:.67rem;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:180px;"><?= htmlspecialchars($c['title']) ?></div>
            </div>
        </div>
        <!-- Confidence meter -->
        <div style="text-align:right;">
            <div style="font-size:.63rem;color:#94a3b8;margin-bottom:3px;">Confidence</div>
            <div style="display:flex;align-items:center;gap:5px;">
                <div style="width:48px;height:5px;background:#e2e8f0;border-radius:3px;overflow:hidden;">
                    <div style="height:100%;width:<?= $pred['confidence'] ?>%;background:<?= $cfg['color'] ?>;border-radius:3px;"></div>
                </div>
                <span style="font-size:.74rem;font-weight:700;color:<?= $cfg['color'] ?>;"><?= $pred['confidence'] ?>%</span>
            </div>
        </div>
    </div>

    <div style="padding:18px 20px;">

        <!-- ── Progress Bars ── -->
        <div style="margin-bottom:16px;">
            <!-- Current -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                <span style="font-size:.72rem;font-weight:600;color:#64748b;display:flex;align-items:center;gap:4px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:#1447c0;display:inline-block;"></span>Current
                </span>
                <span style="font-size:.78rem;font-weight:800;color:#0f172a;"><?= $pct_current ?>%</span>
            </div>
            <div style="height:8px;background:#f1f5f9;border-radius:5px;overflow:hidden;margin-bottom:10px;">
                <div style="height:100%;width:<?= $pct_current ?>%;background:linear-gradient(90deg,#60a5fa,#1447c0);border-radius:5px;transition:width .6s;"></div>
            </div>
            <!-- Projected -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                <span style="font-size:.72rem;font-weight:600;color:#64748b;display:flex;align-items:center;gap:4px;">
                    <i class="bi bi-graph-up-arrow" style="font-size:.7rem;color:<?= $cfg['color'] ?>;"></i>Projected at deadline
                </span>
                <span style="font-size:.78rem;font-weight:800;color:<?= $cfg['color'] ?>;"><?= $pred['proj_pct'] ?>%</span>
            </div>
            <div style="height:8px;background:#f1f5f9;border-radius:5px;overflow:hidden;position:relative;">
                <div style="position:absolute;height:100%;width:<?= $pct_current ?>%;background:linear-gradient(90deg,#60a5fa,#1447c0);border-radius:5px;opacity:.3;"></div>
                <div style="position:absolute;height:100%;width:<?= $pct_proj ?>%;background:<?= $cfg['bar'] ?>;border-radius:5px;"></div>
            </div>
            <div style="font-size:.65rem;color:#94a3b8;margin-top:4px;text-align:right;">Blue = current · Solid = projected</div>
        </div>

        <!-- ── Stats Grid ── -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;">
            <?php
            $stats = [
                [formatCurrency($c['raised_amount']),         'Raised',          '#0f172a'],
                [formatCurrency($c['target_amount']),         'Target',          '#64748b'],
                [formatCurrency((int)$pred['daily_rate']).'/d','Daily Rate',     $cfg['color']],
                [formatCurrency((int)$pred['projected']),     'Projected',       $cfg['color']],
                [max(0,$c['days_left']).'d',                  'Days Left',       $c['days_left']<=14?'#dc2626':'#475569'],
                [$c['donation_count'].' txns',                'Donations',       '#475569'],
            ];
            foreach($stats as [$val,$lbl,$col]):
            ?>
            <div style="background:#f8fafc;border:1px solid #eef2f7;border-radius:10px;padding:10px 8px;text-align:center;">
                <div style="font-size:.77rem;font-weight:800;color:<?= $col ?>;line-height:1.2;margin-bottom:3px;"><?= $val ?></div>
                <div style="font-size:.61rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;"><?= $lbl ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Insight Box ── -->
        <div style="background:<?= $cfg['bg'] ?>;border:1px solid <?= $cfg['border'] ?>;border-radius:9px;padding:10px 13px;display:flex;align-items:flex-start;gap:9px;">
            <i class="bi bi-lightbulb-fill" style="color:<?= $cfg['color'] ?>;font-size:.82rem;flex-shrink:0;margin-top:2px;"></i>
            <div style="font-size:.76rem;color:#374151;line-height:1.55;">
                <?php
                $insight_map = [
                    'goal_reached'    => 'This campaign has <strong>already reached its fundraising goal</strong>. Consider closing it or setting a stretch target.',
                    'on_track'        => 'At <strong>'.formatCurrency((int)$pred['daily_rate']).' / day</strong>, this campaign is projected to reach <strong>'.$pred['proj_pct'].'%</strong> of its goal — well on track.',
                    'needs_attention' => 'Donation pace is slowing. Still needs <strong>'.formatCurrency((int)max(0,$c['target_amount']-$c['raised_amount'])).'</strong> more. Boost social outreach to recover.',
                    'at_risk'         => '<strong style="color:#dc2626;">Critical:</strong> At current pace, only reaching <strong>'.$pred['proj_pct'].'%</strong> of goal. Immediate action needed — feature this campaign prominently.',
                ];
                echo isset($insight_map[$pred['status']]) ? $insight_map[$pred['status']] : 'Monitor this campaign closely.';
                ?>
            </div>
        </div>
    </div>
</div>
<?php endwhile;
else: ?>
<div style="grid-column:1/-1;background:white;border:2px dashed #e2e8f0;border-radius:18px;padding:64px;text-align:center;">
    <i class="bi bi-graph-up-arrow" style="font-size:3rem;color:#e2e8f0;display:block;margin-bottom:14px;"></i>
    <div style="font-size:.9rem;font-weight:700;color:#94a3b8;margin-bottom:6px;">No active campaigns to predict</div>
    <div style="font-size:.78rem;color:#cbd5e1;">Active campaigns will appear here for analysis</div>
</div>
<?php endif; ?>
</div>

<!-- ═══ METHODOLOGY NOTE ════════════════════════════ -->
<div style="background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border:1.5px solid #bae6fd;border-radius:12px;padding:14px 20px;margin-top:20px;display:flex;align-items:flex-start;gap:10px;">
    <div style="width:32px;height:32px;background:#0284c7;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">
        <i class="bi bi-info-circle-fill" style="color:white;font-size:.8rem;"></i>
    </div>
    <div style="font-size:.76rem;color:#0c4a6e;line-height:1.7;">
        <strong>Prediction Methodology:</strong> Linear daily run-rate model —
        <em>daily_rate = total_raised ÷ days_elapsed</em> →
        <em>projected = raised + daily_rate × days_remaining</em>.
        Confidence score increases with more data points. Projections assume consistent pace and do not account for seasonal spikes or marketing campaigns.
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
