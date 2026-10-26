<?php
$pageTitle  = 'Campaign Predictions';
$activePage = 'prediction';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('manager');

$db = getApplicationDB();

// ── Fetch campaigns ──────────────────────────────────
$campaigns = $db->query("
    SELECT c.id, c.title, c.target_amount, c.status, c.start_date, c.end_date,
           COALESCE(d.raised_amount,0) as raised_amount,
           DATEDIFF(c.end_date, CURDATE()) as days_left,
           DATEDIFF(CURDATE(), c.start_date) as days_elapsed,
           COALESCE(d.donation_count,0) as donation_count,
           COALESCE(d.unique_donors,0) as unique_donors,
           COALESCE(d.avg_donation,0) as avg_donation,
           CASE WHEN c.target_amount > 0
                THEN ROUND(COALESCE(d.raised_amount,0) / c.target_amount * 100,1)
                ELSE 0 END as pct_reached
    FROM campaigns c
    LEFT JOIN (
        SELECT campaign_id, SUM(amount) as raised_amount, COUNT(*) as donation_count,
               COUNT(DISTINCT donor_id) as unique_donors, AVG(amount) as avg_donation
        FROM donations
        WHERE status='verified' AND campaign_id IS NOT NULL
        GROUP BY campaign_id
    ) d ON d.campaign_id=c.id
    WHERE c.status IN ('active','draft')
    GROUP BY c.id
    ORDER BY raised_amount DESC, c.title ASC
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

    return [
        'daily_rate'     => $daily_rate,
        'projected'      => $projected,
        'proj_pct'       => round($proj_pct, 1),
        'days_to_target' => $days_to_target,
        'status'         => $status,
    ];
}

// Status colors
$status_cfg = [
    'goal_reached'    => [
        'label' => 'Goal Reached',
        'bg'    => '#dcfce7',
        'color' => '#15803d',
        'border'=> '#bbf7d0',
        'icon'  => 'bi-trophy',
    ],
    'on_track'        => [
        'label' => 'On Track',
        'bg'    => '#dbeafe',
        'color' => '#1447c0',
        'border'=> '#bfdbfe',
        'icon'  => 'bi-graph-up-arrow',
    ],
    'needs_attention' => [
        'label' => 'Needs Attention',
        'bg'    => '#fef9c3',
        'color' => '#d97706',
        'border'=> '#fde68a',
        'icon'  => 'bi-exclamation-circle',
    ],
    'at_risk'         => [
        'label' => 'At Risk',
        'bg'    => '#fee2e2',
        'color' => '#dc2626',
        'border'=> '#fecaca',
        'icon'  => 'bi-flag',
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
$total_campaigns_analyzed = array_sum($summary);
?>

<style>
.prediction-heading { position:relative;display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:20px;padding:25px 28px;overflow:hidden;border:1px solid #dce8f2;border-radius:14px;background:linear-gradient(112deg,#f2f8ff 0%,#f6fbfa 58%,#fff 100%); }
.prediction-heading:after { position:absolute;top:-72px;right:120px;width:210px;height:210px;border:1px solid rgba(49,95,159,.09);border-radius:50%;box-shadow:0 0 0 26px rgba(49,95,159,.035),0 0 0 54px rgba(49,95,159,.025);content:"";pointer-events:none; }
.prediction-heading-copy,.prediction-heading-action { position:relative;z-index:1; }
.prediction-eyebrow { display:inline-flex;align-items:center;gap:7px;margin-bottom:8px;color:#426b92;font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase; }
.prediction-heading h1 { margin:0;color:#172b45;font-size:1.55rem;font-weight:750;letter-spacing:-.035em; }
.prediction-heading p { margin:6px 0 0;color:#66788a;font-size:.82rem; }
.prediction-heading .prediction-count { color:#315f85;font-weight:700; }
.prediction-heading-action { flex:none;padding:9px 13px;border-color:#cad9e6;background:rgba(255,255,255,.8);color:#315f85; }
.prediction-summary { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:20px; }
.prediction-summary-item { display:flex;align-items:center;gap:13px;min-width:0;padding:15px 17px;border:1px solid #e1e8ef;border-left:3px solid var(--status-color);border-radius:11px;background:linear-gradient(135deg,var(--status-bg),#fff 105%);box-shadow:0 3px 10px rgba(23,43,69,.035); }
.prediction-summary-icon { display:grid;width:38px;height:38px;flex:0 0 38px;place-items:center;border:1px solid rgba(23,43,69,.07);border-radius:11px;background:rgba(255,255,255,.72);color:var(--status-color);font-size:1rem; }
.prediction-summary-value { color:#172b45;font-size:1.35rem;font-weight:750;line-height:1.05; }
.prediction-summary-label { margin-top:4px;color:#637386;font-size:.73rem;font-weight:550; }
.prediction-table-wrap { overflow:hidden;border:1px solid #e1e8ef;border-radius:13px;background:#fff;box-shadow:0 5px 20px rgba(23,43,69,.045); }
.prediction-table-heading { display:flex;justify-content:space-between;align-items:center;gap:16px;padding:18px 21px;border-bottom:1px solid #e9eef3;background:linear-gradient(100deg,#fff,#f8fbfd); }
.prediction-table-heading h2 { margin:0;color:#233a54;font-size:1rem;font-weight:700;letter-spacing:-.01em; }
.prediction-table-heading p { margin:4px 0 0;color:#728195;font-size:.76rem; }
.prediction-table-hint { display:flex;align-items:center;gap:7px;color:#758599;font-size:.71rem; }
.prediction-table { width:100%;min-width:800px;border-collapse:separate;border-spacing:0; }
.prediction-table th { padding:11px 14px;border-bottom:1px solid #e8edf2;background:#f5f8fb;color:#718094;text-align:left;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.065em;white-space:nowrap; }
.prediction-table td { padding:14px;border-bottom:1px solid #edf1f5;color:#425267;vertical-align:middle;font-size:.77rem; }
.prediction-table tbody tr { transition:background-color .16s ease; }
.prediction-table tbody tr:hover { background:#f8fbfd; }
.prediction-table tbody tr:last-child td { border-bottom:0; }
.prediction-campaign { min-width:190px;color:#243b55!important;font-weight:700!important; }
.prediction-campaign small { display:block;margin-top:5px;color:#8290a0;font-size:.68rem;font-weight:450; }
.prediction-status { display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border:1px solid var(--status-border);border-radius:20px;background:var(--status-bg);color:var(--status-color);font-size:.68rem;font-weight:700;white-space:nowrap; }
.prediction-status:before { width:6px;height:6px;border-radius:50%;background:currentColor;content:""; }
.prediction-progress { min-width:140px; }
.prediction-progress-label { display:flex;justify-content:space-between;gap:8px;margin-bottom:7px;color:#65758a;font-size:.69rem; }
.prediction-progress-label strong { color:#354b64;font-size:.71rem;font-weight:700; }
.prediction-progress-track { height:7px;background:#e9eef3;border-radius:10px;overflow:hidden; }
.prediction-progress-fill { height:100%;border-radius:10px;background:linear-gradient(90deg,#5ba4bd,#327b9d);transition:width .3s ease; }
.prediction-forecast { color:#243b55!important;font-size:.8rem!important;font-weight:750!important;white-space:nowrap; }
.prediction-forecast small { display:block;margin-top:4px;color:#8190a1;font-size:.68rem;font-weight:500; }
.prediction-details summary { display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border:1px solid #dbe5ee;border-radius:7px;background:#f7fafc;color:#3b688d;font-size:.71rem;font-weight:700;cursor:pointer;list-style:none;white-space:nowrap; }
.prediction-details summary::-webkit-details-marker { display:none; }
.prediction-details summary:after { content:"+";font-size:.9rem;line-height:1; }
.prediction-details[open] summary:after { content:"−"; }
.prediction-details-content { padding:16px 18px;border-top:1px solid #e9eef3;background:linear-gradient(120deg,#f8fbfd,#fff); }
.prediction-detail-grid { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px 22px; }
.prediction-detail-label { color:#7b8999;font-size:.67rem; }
.prediction-detail-value { margin-top:4px;color:#2f4660;font-size:.78rem;font-weight:700; }
.prediction-detail-insight { margin-top:14px;padding:11px 13px;border-left:3px solid #77a5b8;border-radius:0 7px 7px 0;background:#f0f7f9;color:#526b7d;font-size:.74rem;line-height:1.55; }
.prediction-empty { padding:48px 20px!important;color:#66788a!important;text-align:center; }
.prediction-method { display:flex;gap:11px;margin-top:15px;padding:14px 16px;border:1px solid #e1eaf0;border-radius:10px;background:#f7fafc;color:#6d7d8e;font-size:.73rem;line-height:1.6; }
.prediction-method-icon { flex:0 0 auto;margin-top:1px;color:#50809b;font-size:1rem; }
.prediction-method strong { color:#344c64; }
@media (max-width:767.98px) {
    .prediction-heading { align-items:flex-start;padding:20px 18px; }
    .prediction-heading h1 { font-size:1.3rem; }
    .prediction-heading:after { right:-90px; }
    .prediction-heading-action { align-self:flex-start;padding:8px 10px;font-size:.7rem; }
    .prediction-summary { grid-template-columns:repeat(2,minmax(0,1fr));gap:9px; }
    .prediction-summary-item { gap:9px;padding:12px 11px; }
    .prediction-summary-icon { width:32px;height:32px;flex-basis:32px;border-radius:9px; }
    .prediction-summary-value { font-size:1.18rem; }
    .prediction-summary-label { font-size:.68rem; }
    .prediction-table-heading { align-items:flex-start;padding:15px; }
    .prediction-table-hint { display:none; }
    .prediction-table { min-width:0;display:block; }
    .prediction-table thead { display:none; }
    .prediction-table tbody { display:grid;gap:11px;padding:11px;background:#f5f8fb; }
    .prediction-table tbody tr { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 12px;padding:12px;border:1px solid #e2e9f0;border-radius:10px;background:#fff;box-shadow:0 2px 6px rgba(23,43,69,.035); }
    .prediction-table tbody tr:hover { background:#fff; }
    .prediction-table td { min-width:0;padding:9px 4px;border-bottom:1px solid #f0f3f6;font-size:.74rem; }
    .prediction-table td:before { display:block;margin-bottom:6px;color:#8290a0;font-size:.61rem;font-weight:700;letter-spacing:.055em;text-transform:uppercase;content:attr(data-label); }
    .prediction-table td.prediction-campaign { grid-column:1/-1;padding-top:4px;font-size:.83rem!important; }
    .prediction-table td.prediction-campaign:before { content:none; }
    .prediction-table td:nth-child(2) { grid-column:1/-1;padding-top:5px; }
    .prediction-table td:nth-child(3),.prediction-table td:nth-child(4) { grid-column:1/-1; }
    .prediction-table td:nth-child(7) { grid-column:1/-1;border-bottom:0; }
    .prediction-campaign small { font-size:.67rem; }
    .prediction-progress { min-width:0; }
    .prediction-details-content { padding:13px 10px; }
    .prediction-detail-grid { grid-template-columns:repeat(2,minmax(0,1fr));gap:13px 10px; }
    .prediction-detail-value { font-size:.73rem;overflow-wrap:anywhere; }
    .prediction-empty { grid-column:1/-1; }
}
@media (max-width:380px) {
    .prediction-heading { flex-direction:column;gap:12px; }
    .prediction-heading-action { align-self:flex-start; }
}
</style>

<section class="prediction-heading">
    <div class="prediction-heading-copy">
        <div class="prediction-eyebrow"><i class="bi bi-stars"></i> FUNDRAISING OVERVIEW</div>
        <h1>Campaign forecast</h1>
        <p>Estimated campaign progress at the scheduled end date · <span class="prediction-count"><?= $total_campaigns_analyzed ?> campaigns analysed</span></p>
    </div>
    <a href="<?= SITE_URL ?>/manager/reports.php" class="prediction-heading-action btn btn-outline-secondary btn-sm">
        <i class="bi bi-bar-chart-line me-1"></i>Fundraising report
    </a>
</section>

<section class="prediction-summary" aria-label="Campaign forecast summary">
    <?php foreach ($status_cfg as $key => $cfg): ?>
    <div class="prediction-summary-item" style="--status-color:<?= $cfg['color'] ?>;--status-bg:<?= $cfg['bg'] ?>;">
        <span class="prediction-summary-icon" aria-hidden="true"><i class="bi <?= $cfg['icon'] ?>"></i></span>
        <div>
            <div class="prediction-summary-value"><?= (int)$summary[$key] ?></div>
            <div class="prediction-summary-label"><?= htmlspecialchars($cfg['label'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</section>

<section class="prediction-table-wrap">
    <header class="prediction-table-heading">
        <div>
            <h2>Campaign outlook</h2>
            <p>Current verified donations compared with the estimated result at the campaign end date</p>
        </div>
        <div class="prediction-table-hint"><i class="bi bi-info-circle"></i>Select “Details” for supporting figures</div>
    </header>
    <div class="table-responsive">
        <table class="prediction-table">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Status</th>
                    <th>Current progress</th>
                    <th>Forecast at end date</th>
                    <th>Daily average</th>
                    <th>Days left</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($campaigns && $campaigns->num_rows > 0): ?>
                    <?php
                    $campaigns->data_seek(0);
                    while ($c = $campaigns->fetch_assoc()):
                        $pred = $all_predictions[$c['id']];
                        $cfg = $status_cfg[$pred['status']];
                        $pct_current = (float)$c['pct_reached'];
                        $pct_current_bar = min(100, max(0, $pct_current));
                        $pct_projected_bar = min(100, max(0, (float)$pred['proj_pct']));
                        $days_left = max(0, (int)$c['days_left']);
                    ?>
                    <tr>
                        <td class="prediction-campaign" data-label="Campaign">
                            <?= htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8') ?>
                            <small><?= htmlspecialchars(date('d M Y', strtotime($c['start_date'])), ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars(date('d M Y', strtotime($c['end_date'])), ENT_QUOTES, 'UTF-8') ?></small>
                        </td>
                        <td data-label="Forecast status">
                            <span class="prediction-status" style="--status-bg:<?= $cfg['bg'] ?>;--status-border:<?= $cfg['border'] ?>;--status-color:<?= $cfg['color'] ?>;">
                                <?= htmlspecialchars($cfg['label'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td data-label="Current progress">
                            <div class="prediction-progress">
                                <div class="prediction-progress-label">
                                    <span><?= formatCurrency((float)$c['raised_amount']) ?></span>
                                    <strong><?= number_format($pct_current, 1) ?>%</strong>
                                </div>
                                <div class="prediction-progress-track" aria-label="<?= number_format($pct_current, 1) ?> percent of target">
                                    <div class="prediction-progress-fill" style="width:<?= $pct_current_bar ?>%;"></div>
                                </div>
                            </div>
                        </td>
                        <td class="prediction-forecast" data-label="Forecast at end date">
                            <?= formatCurrency((float)$pred['projected']) ?>
                            <small><?= number_format((float)$pred['proj_pct'], 1) ?>% of <?= formatCurrency((float)$c['target_amount']) ?></small>
                            <div class="prediction-progress-track mt-2" aria-label="<?= number_format((float)$pred['proj_pct'], 1) ?> percent projected">
                                <div class="prediction-progress-fill" style="width:<?= $pct_projected_bar ?>%;background:<?= $cfg['color'] ?>;"></div>
                            </div>
                        </td>
                        <td data-label="Daily average"><?= formatCurrency((float)$pred['daily_rate']) ?><small class="d-block text-muted">per day</small>                        </td>
                        <td data-label="Days left"><?= $days_left ?></td>
                        <td data-label="Supporting figures">
                            <details class="prediction-details">
                                <summary><i class="bi bi-list-ul"></i> Details</summary>
                                <div class="prediction-details-content">
                                    <div class="prediction-detail-grid">
                                        <div>
                                            <div class="prediction-detail-label">Fundraising target</div>
                                            <div class="prediction-detail-value"><?= formatCurrency((float)$c['target_amount']) ?></div>
                                        </div>
                                        <div>
                                            <div class="prediction-detail-label">Verified donations</div>
                                            <div class="prediction-detail-value"><?= number_format((int)$c['donation_count']) ?></div>
                                        </div>
                                        <div>
                                            <div class="prediction-detail-label">Unique donors</div>
                                            <div class="prediction-detail-value"><?= number_format((int)$c['unique_donors']) ?></div>
                                        </div>
                                        <div>
                                            <div class="prediction-detail-label">Average donation</div>
                                            <div class="prediction-detail-value"><?= formatCurrency((float)$c['avg_donation']) ?></div>
                                        </div>
                                        <div>
                                            <div class="prediction-detail-label">Projected remaining amount</div>
                                            <div class="prediction-detail-value"><?= formatCurrency(max(0, (float)$c['target_amount'] - (float)$pred['projected'])) ?></div>
                                        </div>
                                        <div>
                                            <div class="prediction-detail-label">Estimated time to target at current pace</div>
                                            <div class="prediction-detail-value"><?= $pred['days_to_target'] > 0 ? number_format((int)$pred['days_to_target']) . ' days' : ((float)$c['raised_amount'] >= (float)$c['target_amount'] ? 'Target reached' : 'Not currently estimated') ?></div>
                                        </div>
                                        <div>
                                            <div class="prediction-detail-label">Elapsed days included in average</div>
                                            <div class="prediction-detail-value"><?= max(0, (int)$c['days_elapsed']) ?> days</div>
                                        </div>
                                    </div>
                                    <div class="prediction-detail-insight">
                                        <?php if ($pred['status'] === 'goal_reached'): ?>
                                            Verified donations have reached the campaign target.
                                        <?php elseif ($pred['status'] === 'on_track'): ?>
                                            At the observed average daily rate, projected fundraising reaches at least 90% of the target by the scheduled end date.
                                        <?php elseif ($pred['status'] === 'needs_attention'): ?>
                                            At the observed average daily rate, projected fundraising reaches 60–89% of the target. Review campaign activity and remaining time.
                                        <?php else: ?>
                                            At the observed average daily rate, projected fundraising reaches less than 60% of the target. Review campaign activity and remaining time.
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </details>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="prediction-empty">No active or draft campaigns are available for forecasting.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<aside class="prediction-method">
    <i class="prediction-method-icon bi bi-lightbulb" aria-hidden="true"></i>
    <div><strong>How to read this forecast:</strong> Forecast = verified funds raised + (average daily verified funds × days remaining). The daily average is calculated from the campaign start date to today. This is a straight-line estimate, not a statistical prediction; it assumes the observed rate continues and does not account for seasonality or future fundraising activity.</div>
</aside>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
