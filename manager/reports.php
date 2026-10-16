<?php
$pageTitle  = 'Reports';
$activePage = 'reports';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('manager');

$db = getDB();

// Overall stats
$total_raised    = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM donations WHERE status='verified'")->fetch_assoc()['t'];
$total_donors    = $db->query("SELECT COUNT(DISTINCT donor_id) as t FROM donations WHERE status='verified'")->fetch_assoc()['t'];
$total_campaigns = $db->query("SELECT COUNT(*) as t FROM campaigns")->fetch_assoc()['t'];
$active_campaigns= $db->query("SELECT COUNT(*) as t FROM campaigns WHERE status='active'")->fetch_assoc()['t'];
$verified_txns   = $db->query("SELECT COUNT(*) as t FROM transactions WHERE verification_status='verified'")->fetch_assoc()['t'];
$rejected_txns   = $db->query("SELECT COUNT(*) as t FROM transactions WHERE verification_status='rejected'")->fetch_assoc()['t'];
$total_allocated = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM fund_allocations")->fetch_assoc()['t'];
$avg_donation    = $db->query("SELECT COALESCE(AVG(amount),0) as t FROM donations WHERE status='verified'")->fetch_assoc()['t'];

// Campaign performance
$camp_perf = $db->query("
    SELECT c.title, c.target_amount, c.raised_amount, c.status,
           COUNT(d.id) as donations,
           ROUND((c.raised_amount/c.target_amount)*100,1) as pct,
           SUM(fa.amount) as allocated
    FROM campaigns c
    LEFT JOIN donations d ON d.campaign_id=c.id AND d.status='verified'
    LEFT JOIN fund_allocations fa ON fa.campaign_id=c.id
    GROUP BY c.id
    ORDER BY c.raised_amount DESC
");

// Monthly trend (12 months)
$monthly = $db->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') as label,
           DATE_FORMAT(created_at,'%Y-%m') as ym,
           COUNT(*) as cnt,
           COALESCE(SUM(amount),0) as total
    FROM donations WHERE status='verified'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY ym, label ORDER BY ym ASC
");
$m_labels=[]; $m_amounts=[]; $m_counts=[];
if ($monthly) { while($m=$monthly->fetch_assoc()) { $m_labels[]=$m['label']; $m_amounts[]=(float)$m['total']; $m_counts[]=(int)$m['cnt']; } }

// Channel breakdown
$channels = $db->query("
    SELECT channel, COUNT(*) as cnt, COALESCE(SUM(amount),0) as total
    FROM donations WHERE status='verified'
    GROUP BY channel ORDER BY total DESC
");
$ch_labels=[]; $ch_amounts=[];
if ($channels) { while($c=$channels->fetch_assoc()) { $ch_labels[]=ucfirst(str_replace('_',' ',$c['channel'])); $ch_amounts[]=(float)$c['total']; } }

// Donor retention
$repeat_donors = $db->query("SELECT COUNT(*) as cnt FROM (SELECT donor_id FROM donations WHERE status='verified' GROUP BY donor_id HAVING COUNT(*)>1) t")->fetch_assoc()['cnt'];
$retention_rate = $total_donors > 0 ? round(($repeat_donors/$total_donors)*100,1) : 0;
$ack_time = $db->query("SELECT COALESCE(AVG(TIMESTAMPDIFF(HOUR,d.created_at,t.verified_at)),0) as avg FROM transactions t JOIN donations d ON t.donation_id=d.id WHERE t.verification_status='verified' AND t.verified_at IS NOT NULL")->fetch_assoc()['avg'];

// ── KPI Metrics (per assignment rubric) ──────────────────────────────────────

// KPI-1: Campaign target-attainment rate
// % of active/completed campaigns that have reached ≥100% of their target
$total_camps_eval = (int)$db->query("SELECT COUNT(*) as c FROM campaigns WHERE status IN ('active','completed')")->fetch_assoc()['c'];
$reached_target   = (int)$db->query("SELECT COUNT(*) as c FROM campaigns WHERE status IN ('active','completed') AND raised_amount >= target_amount")->fetch_assoc()['c'];
$target_attainment = $total_camps_eval > 0 ? round(($reached_target / $total_camps_eval) * 100, 1) : 0;

// KPI-2: Donor retention / repeat-donation rate (already computed above as $retention_rate)
// Shown again as KPI card with context

// KPI-3: Average donation value (VND) — verified donations only
$avg_donation_val = $db->query("SELECT COALESCE(AVG(amount),0) as avg FROM donations WHERE status='verified'")->fetch_assoc()['avg'];

// KPI-4: Fund utilization transparency rate
// % of raised funds that have been allocated to a purpose
$total_raised_f   = (float)$db->query("SELECT COALESCE(SUM(raised_amount),0) as t FROM campaigns")->fetch_assoc()['t'];
$total_alloc_f    = (float)$db->query("SELECT COALESCE(SUM(amount),0) as t FROM fund_allocations")->fetch_assoc()['t'];
$fund_util_rate   = $total_raised_f > 0 ? round(($total_alloc_f / $total_raised_f) * 100, 1) : 0;
$unallocated      = max(0, $total_raised_f - $total_alloc_f);

// KPI-5: Acknowledgement turnaround time (hours) — already computed as $ack_time
// Average hours from donation submission to verification
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-bar-chart-line me-2 text-danger"></i>Fundraising Reports</h1>
        <p class="text-muted mb-0">Evidence-based fundraising performance overview</p>
    </div>
    <button onclick="window.print()" class="btn btn-outline-secondary">
        <i class="bi bi-printer me-2"></i>Print Report
    </button>
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <?php $kpis = [
        ['Total Raised',        formatCurrency($total_raised),   'cash-stack',      'cfe2ff', 'primary'],
        ['Total Donors',        $total_donors,                    'people',          'd1e7dd', 'success'],
        ['Avg Donation',        formatCurrency($avg_donation),   'graph-up',        'fff3cd', 'warning'],
        ['Retention Rate',      $retention_rate.'%',              'arrow-repeat',    'e2d9f3', 'purple'],
        ['Verified Txns',       $verified_txns,                   'shield-check',    'd1e7dd', 'success'],
        ['Acknowledgement',     round($ack_time,1).' hrs avg',   'clock',           'f8d7da', 'danger'],
        ['Total Allocated',     formatCurrency($total_allocated), 'pie-chart',       'd1e7dd', 'success'],
        ['Active Campaigns',    $active_campaigns.'/'.$total_campaigns,'megaphone', 'cfe2ff', 'primary'],
    ]; ?>
    <?php foreach($kpis as $k): ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#<?= $k[3] ?>;">
                <i class="bi bi-<?= $k[2] ?>" style="color:var(--vcf-<?= $k[4] === 'purple' ? 'primary' : $k[4] ?>);"></i>
            </div>
            <div class="stat-value" style="font-size:1rem;"><?= $k[1] ?></div>
            <div class="stat-label"><?= $k[0] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── KPI Performance Indicators ─────────────────────── -->
<div style="margin-bottom:22px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
        <span style="width:28px;height:28px;background:#dbeafe;border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-speedometer2" style="color:#1447c0;font-size:.78rem;"></i>
        </span>
        <span style="font-size:.92rem;font-weight:700;color:#0f172a;">Performance KPIs</span>
        <span style="background:#dbeafe;color:#1447c0;padding:2px 9px;border-radius:20px;font-size:.68rem;font-weight:700;">Evaluation rubric metrics</span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:13px;">

        <!-- KPI-1: Campaign Target-Attainment Rate -->
        <?php
        $kpi1_color = $target_attainment >= 50 ? '#15803d' : ($target_attainment >= 25 ? '#d97706' : '#dc2626');
        $kpi1_bg    = $target_attainment >= 50 ? '#dcfce7'  : ($target_attainment >= 25 ? '#fef9c3'  : '#fee2e2');
        ?>
        <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:16px 14px;box-shadow:0 1px 4px rgba(0,0,0,.04);position:relative;overflow:hidden;">
            <div style="position:absolute;top:0;left:0;right:0;height:3px;background:<?= $kpi1_bg ?>;"></div>
            <div style="width:36px;height:36px;border-radius:10px;background:<?= $kpi1_bg ?>;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                <i class="bi bi-trophy-fill" style="color:<?= $kpi1_color ?>;font-size:.88rem;"></i>
            </div>
            <div style="font-size:1.5rem;font-weight:900;color:<?= $kpi1_color ?>;letter-spacing:-.04em;line-height:1;margin-bottom:4px;"><?= $target_attainment ?>%</div>
            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:5px;">Target Attainment</div>
            <div style="font-size:.68rem;color:#64748b;line-height:1.4;"><?= $reached_target ?> of <?= $total_camps_eval ?> campaigns reached 100%</div>
        </div>

        <!-- KPI-2: Donor Retention Rate -->
        <?php
        $kpi2_color = $retention_rate >= 60 ? '#15803d' : ($retention_rate >= 30 ? '#d97706' : '#dc2626');
        $kpi2_bg    = $retention_rate >= 60 ? '#dcfce7'  : ($retention_rate >= 30 ? '#fef9c3'  : '#fee2e2');
        ?>
        <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:16px 14px;box-shadow:0 1px 4px rgba(0,0,0,.04);position:relative;overflow:hidden;">
            <div style="position:absolute;top:0;left:0;right:0;height:3px;background:<?= $kpi2_bg ?>;"></div>
            <div style="width:36px;height:36px;border-radius:10px;background:<?= $kpi2_bg ?>;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                <i class="bi bi-arrow-repeat" style="color:<?= $kpi2_color ?>;font-size:.88rem;"></i>
            </div>
            <div style="font-size:1.5rem;font-weight:900;color:<?= $kpi2_color ?>;letter-spacing:-.04em;line-height:1;margin-bottom:4px;"><?= $retention_rate ?>%</div>
            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:5px;">Donor Retention</div>
            <div style="font-size:.68rem;color:#64748b;line-height:1.4;"><?= $repeat_donors ?> of <?= $total_donors ?> donors gave again</div>
        </div>

        <!-- KPI-3: Average Donation Value -->
        <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:16px 14px;box-shadow:0 1px 4px rgba(0,0,0,.04);position:relative;overflow:hidden;">
            <div style="position:absolute;top:0;left:0;right:0;height:3px;background:#dbeafe;"></div>
            <div style="width:36px;height:36px;border-radius:10px;background:#dbeafe;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                <i class="bi bi-cash-coin" style="color:#1447c0;font-size:.88rem;"></i>
            </div>
            <div style="font-size:1.3rem;font-weight:900;color:#1447c0;letter-spacing:-.03em;line-height:1;margin-bottom:4px;"><?= number_format($avg_donation_val/1000000,1) ?>M</div>
            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:5px;">Avg Donation (VND)</div>
            <div style="font-size:.68rem;color:#64748b;line-height:1.4;">Per verified donation</div>
        </div>

        <!-- KPI-4: Fund Utilization Transparency Rate -->
        <?php
        $kpi4_color = $fund_util_rate >= 70 ? '#15803d' : ($fund_util_rate >= 40 ? '#d97706' : '#dc2626');
        $kpi4_bg    = $fund_util_rate >= 70 ? '#dcfce7'  : ($fund_util_rate >= 40 ? '#fef9c3'  : '#fee2e2');
        ?>
        <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:16px 14px;box-shadow:0 1px 4px rgba(0,0,0,.04);position:relative;overflow:hidden;">
            <div style="position:absolute;top:0;left:0;right:0;height:3px;background:<?= $kpi4_bg ?>;"></div>
            <div style="width:36px;height:36px;border-radius:10px;background:<?= $kpi4_bg ?>;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                <i class="bi bi-pie-chart-fill" style="color:<?= $kpi4_color ?>;font-size:.88rem;"></i>
            </div>
            <div style="font-size:1.5rem;font-weight:900;color:<?= $kpi4_color ?>;letter-spacing:-.04em;line-height:1;margin-bottom:4px;"><?= $fund_util_rate ?>%</div>
            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:5px;">Fund Utilization</div>
            <div style="font-size:.68rem;color:#64748b;line-height:1.4;"><?= number_format($total_alloc_f/1000000,1) ?>M of <?= number_format($total_raised_f/1000000,1) ?>M allocated</div>
            <div style="height:4px;background:#f1f5f9;border-radius:2px;overflow:hidden;margin-top:8px;">
                <div style="height:100%;width:<?= min(100,$fund_util_rate) ?>%;background:<?= $kpi4_color ?>;border-radius:2px;transition:width .5s;"></div>
            </div>
        </div>

        <!-- KPI-5: Acknowledgement Turnaround Time -->
        <?php
        $ack_h      = round((float)$ack_time, 1);
        $kpi5_color = $ack_h <= 24 ? '#15803d' : ($ack_h <= 72 ? '#d97706' : '#dc2626');
        $kpi5_bg    = $ack_h <= 24 ? '#dcfce7'  : ($ack_h <= 72 ? '#fef9c3'  : '#fee2e2');
        $kpi5_label = $ack_h <= 24 ? 'Excellent' : ($ack_h <= 72 ? 'Acceptable' : 'Needs attention');
        ?>
        <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:16px 14px;box-shadow:0 1px 4px rgba(0,0,0,.04);position:relative;overflow:hidden;">
            <div style="position:absolute;top:0;left:0;right:0;height:3px;background:<?= $kpi5_bg ?>;"></div>
            <div style="width:36px;height:36px;border-radius:10px;background:<?= $kpi5_bg ?>;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
                <i class="bi bi-clock-fill" style="color:<?= $kpi5_color ?>;font-size:.88rem;"></i>
            </div>
            <div style="font-size:1.5rem;font-weight:900;color:<?= $kpi5_color ?>;letter-spacing:-.04em;line-height:1;margin-bottom:4px;"><?= $ack_h ?>h</div>
            <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:5px;">Acknowledgement Time</div>
            <div style="font-size:.68rem;color:#64748b;line-height:1.4;">Avg submission → verified</div>
            <span style="display:inline-flex;align-items:center;gap:3px;background:<?= $kpi5_bg ?>;border-radius:5px;padding:2px 7px;margin-top:6px;">
                <span style="font-size:.63rem;font-weight:700;color:<?= $kpi5_color ?>;"><?= $kpi5_label ?></span>
            </span>
        </div>

    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Monthly Chart -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header fw-bold"><i class="bi bi-bar-chart-line me-2"></i>Monthly Donation Trend (12 months)</div>
            <div class="card-body"><canvas id="monthlyChart" height="160"></canvas></div>
        </div>
    </div>
    <!-- Channel Pie -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-bold"><i class="bi bi-pie-chart me-2"></i>By Channel</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <?php if (!empty($ch_labels)): ?>
                    <canvas id="channelChart" height="200"></canvas>
                <?php else: ?>
                    <div class="text-muted">No data</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Campaign Performance Table -->
<div class="table-card mb-4">
    <div class="table-header">
        <h6 class="fw-bold mb-0"><i class="bi bi-trophy me-2"></i>Campaign Performance</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Target</th>
                    <th>Raised</th>
                    <th>Progress</th>
                    <th>Donations</th>
                    <th>Allocated</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($camp_perf && $camp_perf->num_rows > 0): ?>
                    <?php while($cp=$camp_perf->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($cp['title']) ?></td>
                        <td class="small"><?= formatCurrency($cp['target_amount']) ?></td>
                        <td class="fw-bold text-success"><?= formatCurrency($cp['raised_amount']) ?></td>
                        <td style="min-width:120px;">
                            <div class="progress mb-1" style="height:6px;">
                                <div class="progress-bar <?= $cp['pct']>=100?'bg-success':'' ?>" style="width:<?= min(100,$cp['pct']??0) ?>%"></div>
                            </div>
                            <div class="small text-muted"><?= min(100,$cp['pct']??0) ?>%</div>
                        </td>
                        <td class="text-center"><?= $cp['donations'] ?></td>
                        <td><?= $cp['allocated'] ? formatCurrency($cp['allocated']) : '—' ?></td>
                        <td><span class="badge <?= getStatusBadgeClass($cp['status']) ?>"><?= ucfirst($cp['status']) ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No campaigns yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Monthly bar chart
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($m_labels) ?>,
        datasets: [
            { label: 'Amount (VND)', data: <?= json_encode($m_amounts) ?>, backgroundColor: 'rgba(46,134,193,0.7)', borderColor:'#2e86c1', borderWidth:1, borderRadius:6, yAxisID:'y' },
            { label: 'Count', data: <?= json_encode($m_counts) ?>, type:'line', borderColor:'#e74c3c', backgroundColor:'transparent', borderWidth:2, pointRadius:4, yAxisID:'y1' }
        ]
    },
    options: {
        responsive:true,
        interaction:{mode:'index',intersect:false},
        plugins:{legend:{position:'top'}},
        scales:{
            y:{beginAtZero:true,ticks:{callback:v=>new Intl.NumberFormat('vi-VN').format(v)}},
            y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false}}
        }
    }
});

// Channel doughnut
<?php if (!empty($ch_labels)): ?>
new Chart(document.getElementById('channelChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($ch_labels) ?>,
        datasets: [{ data: <?= json_encode($ch_amounts) ?>, backgroundColor:['#2e86c1','#27ae60','#e74c3c','#f39c12','#8e44ad'], borderWidth:2 }]
    },
    options: { responsive:true, plugins:{legend:{position:'bottom'}} }
});
<?php endif; ?>
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
