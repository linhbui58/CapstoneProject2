<?php
$pageTitle  = 'Reports';
$activePage = 'reports';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('manager');

$db = getApplicationDB();

// Use verified donation records as the source of truth for fundraising totals.
$total_raised = (float)$db->query("SELECT COALESCE(SUM(amount),0) as t FROM donations WHERE status='verified'")->fetch_assoc()['t'];
$total_donors = (int)$db->query("SELECT COUNT(DISTINCT donor_id) as t FROM donations WHERE status='verified'")->fetch_assoc()['t'];
$campaign_counts = $db->query("SELECT COUNT(*) as total, SUM(status='active') as active FROM campaigns")->fetch_assoc();
$total_campaigns = (int)$campaign_counts['total'];
$active_campaigns = (int)$campaign_counts['active'];
$verified_txns = (int)$db->query("SELECT COUNT(*) as t FROM transactions WHERE verification_status='verified'")->fetch_assoc()['t'];
$pending_txns = (int)$db->query("SELECT COUNT(*) as t FROM transactions WHERE verification_status='pending_verification'")->fetch_assoc()['t'];
$rejected_txns = (int)$db->query("SELECT COUNT(*) as t FROM transactions WHERE verification_status='rejected'")->fetch_assoc()['t'];
$total_allocated = (float)$db->query("SELECT COALESCE(SUM(amount),0) as t FROM fund_allocations")->fetch_assoc()['t'];
$avg_donation_val = (float)$db->query("SELECT COALESCE(AVG(amount),0) as avg FROM donations WHERE status='verified'")->fetch_assoc()['avg'];
$campaign_verified_raised = (float)$db->query("SELECT COALESCE(SUM(amount),0) as t FROM donations WHERE status='verified' AND campaign_id IS NOT NULL")->fetch_assoc()['t'];
$allocation_difference = $total_allocated - $campaign_verified_raised;

// Campaign performance
$camp_perf = $db->query("
    SELECT c.id, c.title, c.target_amount, c.status,
           COALESCE(d.donation_count,0) as donations,
           COALESCE(d.verified_raised,0) as raised_amount,
           CASE WHEN c.target_amount > 0
                THEN ROUND(COALESCE(d.verified_raised,0) / c.target_amount * 100,1)
                ELSE 0 END as pct,
           COALESCE(fa.allocated,0) as allocated
    FROM campaigns c
    LEFT JOIN (
        SELECT campaign_id, COUNT(*) as donation_count, SUM(amount) as verified_raised
        FROM donations
        WHERE status='verified' AND campaign_id IS NOT NULL
        GROUP BY campaign_id
    ) d ON d.campaign_id=c.id
    LEFT JOIN (
        SELECT campaign_id, SUM(amount) as allocated
        FROM fund_allocations
        GROUP BY campaign_id
    ) fa ON fa.campaign_id=c.id
    ORDER BY raised_amount DESC, c.title ASC
");

// Monthly trend for the last 12 months.
$monthly = $db->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') as label,
           DATE_FORMAT(created_at,'%Y-%m') as ym,
           COUNT(*) as cnt,
           COALESCE(SUM(amount),0) as total
    FROM donations WHERE status='verified'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
      AND created_at <= NOW()
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
$ack_time = $db->query("SELECT COALESCE(AVG(TIMESTAMPDIFF(SECOND,d.created_at,t.verified_at) / 3600),0) as avg FROM transactions t JOIN donations d ON t.donation_id=d.id WHERE t.verification_status='verified' AND t.verified_at IS NOT NULL")->fetch_assoc()['avg'];

$campaign_target_stats = $db->query("
    SELECT COUNT(*) as total,
           COALESCE(SUM(CASE WHEN COALESCE(d.verified_raised,0) >= c.target_amount THEN 1 ELSE 0 END),0) as reached
    FROM campaigns c
    LEFT JOIN (
        SELECT campaign_id, SUM(amount) as verified_raised
        FROM donations
        WHERE status='verified' AND campaign_id IS NOT NULL
        GROUP BY campaign_id
    ) d ON d.campaign_id=c.id
    WHERE c.status IN ('active','completed')
")->fetch_assoc();
$total_camps_eval = (int)$campaign_target_stats['total'];
$reached_target = (int)$campaign_target_stats['reached'];
$target_attainment = $total_camps_eval > 0 ? round(($reached_target / $total_camps_eval) * 100, 1) : 0;

$total_donors_verified = $total_donors;
$recurring_donors      = (int)$db->query("SELECT COUNT(DISTINCT donor_id) as c FROM donations WHERE frequency != 'one_time' AND status='verified'")->fetch_assoc()['c'];
$recurring_donor_rate  = $total_donors_verified > 0 ? round(($recurring_donors / $total_donors_verified) * 100, 1) : 0;
$recurring_revenue     = (float)$db->query("SELECT COALESCE(SUM(amount),0) as t FROM donations WHERE frequency != 'one_time' AND status='verified'")->fetch_assoc()['t'];
$allocation_rows = $db->query("SELECT status, COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM fund_allocations GROUP BY status");
$allocation_by_status = [];
while ($allocation = $allocation_rows->fetch_assoc()) {
    $allocation_by_status[$allocation['status']] = [
        'count' => (int)$allocation['cnt'],
        'total' => (float)$allocation['total'],
    ];
}
$allocation_status_labels = ['planned' => 'planned', 'disbursed' => 'disbursed', 'completed' => 'completed'];
$allocation_status_summary = [];
foreach ($allocation_status_labels as $status => $label) {
    if (isset($allocation_by_status[$status])) {
        $status_data = $allocation_by_status[$status];
        $allocation_status_summary[] = $status_data['count'] . ' ' . $label . ' (' . formatCurrency($status_data['total']) . ')';
    }
}
$allocation_status_summary = $allocation_status_summary ? implode(', ', $allocation_status_summary) : 'No allocation records';
$allocation_comparison = abs($allocation_difference) < 0.01
    ? 'Recorded allocations of ' . formatCurrency($total_allocated) . ' match verified campaign-linked donations.'
    : ($allocation_difference > 0
        ? 'Recorded allocations of ' . formatCurrency($total_allocated) . ' exceed verified campaign-linked donations of ' . formatCurrency($campaign_verified_raised) . ' by ' . formatCurrency($allocation_difference) . '; review against opening balances or other funding sources.'
        : 'Verified campaign-linked donations of ' . formatCurrency($campaign_verified_raised) . ' exceed recorded allocations of ' . formatCurrency($total_allocated) . ' by ' . formatCurrency(abs($allocation_difference)) . '; this is not an available-balance figure.');
$campaign_status_styles = [
    'active' => 'background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;',
    'completed' => 'background:#dbeafe;color:#1447c0;border:1px solid #bfdbfe;',
    'draft' => 'background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;',
    'closed' => 'background:#f1f5f9;color:#94a3b8;border:1px solid #e2e8f0;',
];
// Average hours from donation submission to verification
?>

<style>
.report-heading { display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:20px; }
.report-heading h1 { margin:0;font-size:1.35rem;font-weight:700;color:#172033;letter-spacing:-.02em; }
.report-heading p { margin:5px 0 0;color:#667085;font-size:.84rem; }
.report-meta { color:#667085;font-size:.76rem;text-align:right; }
.report-summary { display:flex;align-items:center;justify-content:space-between;gap:24px;padding:22px 26px;margin-bottom:22px;background:#fff;border:1px solid #e2e6ec;border-radius:10px; }
.report-summary-label { color:#667085;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em; }
.report-summary-total { margin-top:6px;color:#172033;font-size:1.9rem;font-weight:700;letter-spacing:-.04em;line-height:1.15; }
.report-summary-note { margin-top:5px;color:#667085;font-size:.8rem; }
.report-summary-details { display:flex;gap:34px; }
.report-summary-detail { min-width:112px;padding-left:22px;border-left:1px solid #e5e7eb; }
.report-summary-detail strong { display:block;color:#263247;font-size:1.1rem;font-weight:650; }
.report-summary-detail span { display:block;margin-top:4px;color:#667085;font-size:.75rem; }
.report-section-title { margin:0;color:#263247;font-size:.95rem;font-weight:650; }
.report-section-note { margin:4px 0 0;color:#667085;font-size:.76rem; }
.report-chart-card { height:100%;background:#fff;border:1px solid #e2e6ec;border-radius:10px;overflow:hidden; }
.report-chart-heading { padding:15px 18px;border-bottom:1px solid #edf0f3; }
.report-chart-body { min-height:275px;padding:16px 18px;position:relative; }
.report-chart-body canvas { max-height:240px; }
.report-table-heading { padding:16px 18px;border-bottom:1px solid #edf0f3; }
.report-indicators { margin:0 0 20px;background:#fff;border:1px solid #e2e6ec;border-radius:10px;overflow:hidden; }
.report-indicators-heading { padding:16px 18px;border-bottom:1px solid #edf0f3; }
.report-indicators table { width:100%;min-width:640px;border-collapse:collapse; }
.report-indicators th,.report-indicators td { padding:11px 18px;border-bottom:1px solid #f0f2f5;text-align:left;vertical-align:top;font-size:.78rem; }
.report-indicators th { background:#f8f9fb;color:#667085;font-size:.67rem;font-weight:650;text-transform:uppercase;letter-spacing:.04em; }
.report-indicators td:first-child { width:25%;color:#344054;font-weight:600; }
.report-indicators td:nth-child(2) { width:25%;color:#263247;font-weight:600;white-space:nowrap; }
.report-indicators td:last-child { color:#667085; }
.report-indicators tr:last-child td { border-bottom:0; }
.report-footnote { margin:12px 2px 22px;color:#7b8492;font-size:.74rem; }
@media (max-width:767.98px) {
    .report-heading { align-items:flex-start; }
    .report-meta { display:none; }
    .report-summary { align-items:flex-start;flex-direction:column;gap:18px;padding:18px; }
    .report-summary-details { width:100%;gap:0; }
    .report-summary-detail { flex:1;min-width:0;padding-left:12px; }
    .report-summary-detail:first-child { padding-left:0;border-left:0; }
}
@media print {
    .sidebar,.topbar,.report-print-button,footer { display:none !important; }
    .page-content { padding:0 !important; }
    .report-heading { margin-bottom:14px; }
    .report-summary,.report-chart-card,.table-card { box-shadow:none !important;break-inside:avoid; }
    .report-chart-body { min-height:230px; }
    body { background:#fff !important; }
}
</style>

<section class="report-heading">
    <div>
        <h1>Fundraising Report</h1>
        <p>All-time verified fundraising totals and campaign performance</p>
    </div>
    <div class="d-flex align-items-end gap-3">
        <div class="report-meta">Generated <?= date('d M Y') ?><br>Figures include verified donations</div>
        <a href="<?= SITE_URL ?>/includes/export_excel.php?type=manager_report" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm report-print-button">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</section>

<section class="report-summary" aria-label="Fundraising summary">
    <div>
        <div class="report-summary-label">Total funds raised</div>
        <div class="report-summary-total"><?= formatCurrency($total_raised) ?></div>
        <div class="report-summary-note">From donations verified by the finance team</div>
    </div>
    <div class="report-summary-details">
        <div class="report-summary-detail">
            <strong><?= number_format((int)$total_donors) ?></strong>
            <span>Donors</span>
        </div>
        <div class="report-summary-detail">
            <strong><?= (int)$active_campaigns ?> of <?= (int)$total_campaigns ?></strong>
            <span>Active campaigns</span>
        </div>
    </div>
</section>

<div class="row g-4 mb-4">
    <!-- Monthly Chart -->
    <div class="col-lg-8">
        <section class="report-chart-card">
            <div class="report-chart-heading">
                <h2 class="report-section-title">Monthly donation trend</h2>
                <p class="report-section-note">Verified donation amount and donation count, last 12 months</p>
            </div>
            <div class="report-chart-body">
                <?php if (!empty($m_labels)): ?>
                    <canvas id="monthlyChart" aria-label="Monthly donation trend chart"></canvas>
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center h-100 text-muted" style="min-height:200px;">
                        <div class="text-center">
                            <p class="mb-0 small">No verified donation data available</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <!-- Donation Channel -->
    <div class="col-lg-4">
        <section class="report-chart-card">
            <div class="report-chart-heading">
                <h2 class="report-section-title">Donations by channel</h2>
                <p class="report-section-note">Share of all-time verified donation value</p>
            </div>
            <div class="report-chart-body d-flex align-items-center justify-content-center">
                <?php if (!empty($ch_labels)): ?>
                    <canvas id="channelChart" aria-label="Donations by channel chart"></canvas>
                <?php else: ?>
                    <p class="mb-0 small text-muted">No verified donation data available</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php $ack_h = round((float)$ack_time, 1); ?>
<section class="report-indicators" aria-label="Additional report indicators">
    <div class="report-indicators-heading">
        <h2 class="report-section-title">Performance and operations</h2>
        <p class="report-section-note">Additional measures to put fundraising results in context</p>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Indicator</th>
                    <th>Result</th>
                    <th>Interpretation</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Campaign target attainment</td>
                    <td><?= (int)$reached_target ?> of <?= (int)$total_camps_eval ?> (<?= number_format((float)$target_attainment, 1) ?>%)</td>
                    <td>Active or completed campaigns that have reached their recorded target</td>
                </tr>
                <tr>
                    <td>Returning donors</td>
                    <td><?= (int)$repeat_donors ?> of <?= (int)$total_donors ?> (<?= number_format((float)$retention_rate, 1) ?>%)</td>
                    <td>Donors with more than one verified donation</td>
                </tr>
                <tr>
                    <td>Average donation</td>
                    <td><?= formatCurrency((float)$avg_donation_val) ?></td>
                    <td>Average amount per verified donation</td>
                </tr>
                <tr>
                    <td>Fund allocation</td>
                    <td><?= formatCurrency($total_allocated) ?></td>
                    <td><?= htmlspecialchars($allocation_status_summary, ENT_QUOTES, 'UTF-8') ?>. <?= htmlspecialchars($allocation_comparison, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <tr>
                    <td>Verification turnaround</td>
                    <td><?= number_format($ack_h, 1) ?> hours</td>
                    <td>Average time from donation submission to transaction verification</td>
                </tr>
                <tr>
                    <td>Transaction review</td>
                    <td><?= number_format((int)$verified_txns) ?> verified; <?= number_format((int)$pending_txns) ?> pending; <?= number_format((int)$rejected_txns) ?> rejected</td>
                    <td>Current transaction counts by finance review status</td>
                </tr>
                <tr>
                    <td>Donations marked as recurring</td>
                    <td><?= (int)$recurring_donors ?> of <?= (int)$total_donors_verified ?> donors (<?= number_format((float)$recurring_donor_rate, 1) ?>%); <?= formatCurrency((float)$recurring_revenue) ?></td>
                    <td>Verified donation records with a recurring frequency selected; this does not represent an automatic payment schedule</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<p class="report-footnote">Figures are calculated from current database records. The monthly chart covers the last 12 months; the total and channel chart include all verified donations. Campaign raised amounts are recalculated from verified donation records, while allocations are summed from allocation records.</p>

<!-- Campaign Performance Table -->
<div class="table-card report-table mb-4">
    <div class="report-table-heading">
        <h2 class="report-section-title">Campaign performance</h2>
        <p class="report-section-note">Progress and allocation by campaign</p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Target</th>
                    <th>Verified raised</th>
                    <th>Progress</th>
                    <th>Verified donations</th>
                    <th>Recorded allocations</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($camp_perf && $camp_perf->num_rows > 0): ?>
                    <?php while($cp=$camp_perf->fetch_assoc()): $campaign_status_style = $campaign_status_styles[$cp['status']] ?? $campaign_status_styles['draft']; ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($cp['title']) ?></td>
                        <td class="small"><?= formatCurrency($cp['target_amount']) ?></td>
                        <td class="fw-bold text-success"><?= formatCurrency($cp['raised_amount']) ?></td>
                        <td style="min-width:120px;">
                            <div class="progress mb-1" style="height:6px;">
                                <div class="progress-bar <?= $cp['pct']>=100?'bg-success':'' ?>" style="width:<?= min(100,$cp['pct']??0) ?>%"></div>
                            </div>
                            <div class="small text-muted"><?= number_format((float)$cp['pct'], 1) ?>%</div>
                        </td>
                        <td class="text-center"><?= $cp['donations'] ?></td>
                        <td><?= $cp['allocated'] ? formatCurrency($cp['allocated']) : '—' ?></td>
                        <td><span class="badge" style="<?= $campaign_status_style ?>"><?= htmlspecialchars(ucfirst($cp['status']), ENT_QUOTES, 'UTF-8') ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No campaigns yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="<?= SITE_URL ?>/assets/js/chart.umd.min.js"></script>
<script>
// Monthly bar chart
<?php if (!empty($m_labels)): ?>
try {
(function () {
    var ctx = document.getElementById('monthlyChart');
    if (!ctx) { console.error('[Reports] monthlyChart canvas NOT FOUND'); return; }
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($m_labels) ?>,
            datasets: [
                {
                    label: 'Amount (VND)',
                    data: <?= json_encode($m_amounts) ?>,
                    backgroundColor: 'rgba(20,71,192,.7)',
                    borderColor: '#1447c0',
                    borderWidth: 0,
                    borderRadius: 6,
                    yAxisID: 'y'
                },
                {
                    label: 'Verified donations',
                    data: <?= json_encode($m_counts) ?>,
                    type: 'line',
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239,68,68,.08)',
                    borderWidth: 2,
                    pointRadius: 4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: function(v) { return new Intl.NumberFormat('vi-VN').format(v); } }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
})();
} catch(e) { console.error('[Reports] monthlyChart error:', e); }
<?php endif; ?>

<?php if (!empty($ch_labels)): ?>
try {
(function () {
    var ctx = document.getElementById('channelChart');
    if (!ctx) { console.error('[Reports] channelChart canvas NOT FOUND'); return; }
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($ch_labels) ?>,
            datasets: [{
                data: <?= json_encode($ch_amounts) ?>,
                backgroundColor: ['#1447c0','#15803d','#d97706','#7c3aed','#0f766e'],
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            cutout: '60%',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 9, padding: 10 } } }
        }
    });
})();
} catch(e) { console.error('[Reports] channelChart error:', e); }
<?php endif; ?>
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
