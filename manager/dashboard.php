<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('manager');
$db = getApplicationDB();

$total_raised   = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM donations WHERE status='verified'")->fetch_assoc()['t'];
$total_donors   = $db->query("SELECT COUNT(DISTINCT donor_id) as t FROM donations WHERE status='verified'")->fetch_assoc()['t'];
$active_camp    = $db->query("SELECT COUNT(*) as t FROM campaigns WHERE status='active'")->fetch_assoc()['t'];
$pending_verif  = $db->query("SELECT COUNT(*) as t FROM transactions WHERE verification_status='pending_verification'")->fetch_assoc()['t'];
$total_donat    = $db->query("SELECT COUNT(*) as t FROM donations")->fetch_assoc()['t'];
$total_alloc    = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM fund_allocations")->fetch_assoc()['t'];

// Recurring donors KPI
$db->query("ALTER TABLE donations ADD COLUMN IF NOT EXISTS frequency ENUM('one_time','monthly','quarterly','yearly') NOT NULL DEFAULT 'one_time' AFTER channel");
$recurring_donors_count = $db->query("SELECT COUNT(DISTINCT donor_id) as t FROM donations WHERE frequency != 'one_time' AND status='verified'")->fetch_assoc()['t'];
$recurring_donor_pct    = $total_donors > 0 ? round(($recurring_donors_count / $total_donors) * 100, 1) : 0;

// Monthly trend
$monthly = $db->query("SELECT DATE_FORMAT(created_at,'%b') as lbl, DATE_FORMAT(created_at,'%Y-%m') as ym, COALESCE(SUM(amount),0) as total, COUNT(*) as cnt FROM donations WHERE status='verified' AND created_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY ym,lbl ORDER BY ym ASC");
$m_labels=[]; $m_amounts=[]; $m_counts=[];
if($monthly) while($m=$monthly->fetch_assoc()){$m_labels[]=$m['lbl'];$m_amounts[]=(float)$m['total'];$m_counts[]=(int)$m['cnt'];}

// Channel breakdown
$channels=$db->query("SELECT channel,COUNT(*) as cnt,COALESCE(SUM(amount),0) as total FROM donations WHERE status='verified' GROUP BY channel ORDER BY total DESC");
$ch_labels=[]; $ch_data=[];
if($channels) while($c=$channels->fetch_assoc()){$ch_labels[]=ucfirst(str_replace('_',' ',$c['channel']));$ch_data[]=(float)$c['total'];}

// Top campaigns
$top_camps=$db->query("SELECT id,title,target_amount,raised_amount,status, ROUND((raised_amount/target_amount)*100,1) as pct FROM campaigns ORDER BY raised_amount DESC LIMIT 5");

// Recent donations
$recent=$db->query("SELECT d.*,u.name as donor_name,c.title as camp_title FROM donations d JOIN users u ON d.donor_id=u.id LEFT JOIN campaigns c ON d.campaign_id=c.id ORDER BY d.created_at DESC LIMIT 7");

// Top donors
$top_donors=$db->query("SELECT u.name,SUM(d.amount) as total,COUNT(d.id) as cnt FROM donations d JOIN users u ON d.donor_id=u.id WHERE d.status='verified' GROUP BY d.donor_id ORDER BY total DESC LIMIT 5");
?>

<style>
.manager-dashboard-heading { align-items:center!important;margin-bottom:17px!important;padding-bottom:13px;border-bottom:1px solid #e8edf3; }
.manager-dashboard-heading h1 { color:#172b45!important;font-size:1.3rem!important;letter-spacing:-.025em; }
.manager-dashboard-heading p { color:#718096!important;font-size:.76rem!important; }
.manager-dashboard-report-link { padding:8px 13px!important;border-radius:8px!important;background:#1746a2!important;box-shadow:0 2px 6px rgba(23,70,162,.18)!important;font-size:.75rem!important; }
.manager-dashboard-kpis { grid-template-columns:repeat(6,minmax(0,1fr))!important;gap:10px!important;margin-bottom:16px!important; }
.manager-dashboard-kpi { min-width:0;padding:12px 12px!important;border-color:#e5eaf1!important;border-radius:11px!important;box-shadow:0 2px 7px rgba(15,23,42,.035)!important; }
.manager-dashboard-kpi-icon { width:32px!important;height:32px!important;margin-bottom:8px!important;border-radius:9px!important; }
.manager-dashboard-kpi-value { color:#172b45!important;font-size:1rem!important;font-weight:750!important;white-space:nowrap; }
.manager-dashboard-kpi-label { color:#8190a1!important;font-size:.59rem!important;letter-spacing:.055em!important; }
.manager-dashboard-recurring { margin-bottom:15px!important;padding:12px 16px!important;border:1px solid #dbe8f2!important;border-radius:11px!important;background:linear-gradient(100deg,#f4f9ff,#f7fbff)!important; }
.manager-dashboard-recurring-icon { display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:10px;background:#e5efff;color:#2455a6;font-size:1rem; }
.manager-dashboard-recurring-title { color:#294565!important;font-size:.8rem!important; }
.manager-dashboard-recurring-description { color:#718096!important;font-size:.69rem!important; }
.manager-dashboard-recurring-link { padding:7px 12px!important;border-radius:7px!important;background:#2455a6!important;font-size:.71rem!important; }
.manager-dashboard-charts { grid-template-columns:minmax(0,1fr) 275px!important;gap:13px!important;margin-bottom:15px!important; }
.manager-dashboard-chart-card,.manager-dashboard-panel { min-width:0;background:#fff;border:1px solid #e4eaf1!important;border-radius:12px!important;box-shadow:0 2px 8px rgba(15,23,42,.035)!important; }
.manager-dashboard-chart-card { padding:15px 17px!important; }
.manager-dashboard-card-title { color:#263b54!important;font-size:.82rem!important; }
.manager-dashboard-bottom { grid-template-columns:minmax(0,1.1fr) minmax(0,1fr) minmax(190px,.7fr)!important;gap:13px!important; }
.manager-dashboard-panel { overflow:hidden; }
.manager-dashboard-panel-heading { min-height:41px;padding:11px 14px!important;background:linear-gradient(100deg,#fff,#fafbfc); }
.manager-dashboard-panel-title { color:#263b54!important;font-size:.8rem!important; }
.manager-dashboard-table-scroll { overflow-x:auto; }
.manager-dashboard-recent-table { min-width:590px;width:100%;border-collapse:collapse; }
.manager-dashboard-recent-table thead th { background:#f8fafc!important;color:#8290a0!important;font-size:.59rem!important; }
.manager-dashboard-recent-table tbody tr { transition:background-color .14s ease; }
.manager-dashboard-recent-table tbody tr:hover { background:#f8faff!important; }
.manager-dashboard-campaign-row,.manager-dashboard-donor-row { transition:background-color .14s ease; }
.manager-dashboard-campaign-row:hover,.manager-dashboard-donor-row:hover { background:#f8faff!important; }
.manager-dashboard-chart-canvas { display:block;max-height:220px;width:100%!important; }
.manager-dashboard-channel-canvas { display:block;max-height:180px;width:100%!important; }
@media (max-width:1200px) {
    .manager-dashboard-kpis { grid-template-columns:repeat(3,minmax(0,1fr))!important; }
    .manager-dashboard-charts { grid-template-columns:minmax(0,1fr) 250px!important; }
    .manager-dashboard-bottom { grid-template-columns:repeat(2,minmax(0,1fr))!important; }
    .manager-dashboard-bottom > :first-child { grid-column:1/-1; }
}
@media (max-width:767.98px) {
    .manager-dashboard-heading { align-items:flex-start!important; }
    .manager-dashboard-kpis { grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:8px!important; }
    .manager-dashboard-kpi { padding:11px!important; }
    .manager-dashboard-charts,.manager-dashboard-bottom { grid-template-columns:minmax(0,1fr)!important; }
    .manager-dashboard-bottom > :first-child { grid-column:auto; }
    .manager-dashboard-chart-card { padding:13px!important; }
    .manager-dashboard-channel-canvas { max-height:210px; }
    .manager-dashboard-recurring { align-items:flex-start!important; }
}
@media (max-width:420px) {
    .manager-dashboard-heading { flex-direction:column;gap:10px!important; }
    .manager-dashboard-report-link { align-self:flex-start; }
    .manager-dashboard-recurring { gap:10px!important; }
    .manager-dashboard-recurring-copy { align-items:flex-start!important; }
}
</style>

<!-- Header -->
<div class="manager-dashboard-heading" style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-grid-fill" style="color:#ef4444;font-size:1rem;"></i>Dashboard
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">Fundraising performance overview — <?= date('F Y') ?></p>
    </div>
    <a class="manager-dashboard-report-link" href="<?= SITE_URL ?>/manager/reports.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:linear-gradient(135deg,#1447c0,#0d2157);color:white;border-radius:10px;font-weight:700;font-size:.82rem;box-shadow:0 3px 10px rgba(20,71,192,.25);text-decoration:none;white-space:nowrap;transition:all .18s;flex-shrink:0;"
       onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
        <i class="bi bi-bar-chart-line-fill"></i>Full Reports
    </a>
</div>

<!-- KPI Cards -->
<div class="manager-dashboard-kpis" style="display:grid;grid-template-columns:repeat(6,1fr);gap:13px;margin-bottom:22px;">
    <?php
    $kpis = [
        [number_format($total_raised/1000000,1).'M VND','Total Raised',       'bi-cash-stack',          '#dbeafe','#1447c0'],
        [$total_donors,                                  'Total Donors',        'bi-people-fill',          '#dcfce7','#15803d'],
        [$active_camp,                                   'Active Campaigns',    'bi-megaphone-fill',       '#ede9fe','#7c3aed'],
        [$pending_verif,                                 'Pending Verify',      'bi-clock-fill',           '#fef9c3','#d97706'],
        [$total_donat,                                   'Total Donations',     'bi-receipt',              '#ccfbf1','#0f766e'],
        [number_format($total_alloc/1000000,1).'M VND', 'Funds Allocated',     'bi-pie-chart-fill',       '#fed7aa','#c2410c'],
    ];
    foreach($kpis as [$val,$lbl,$ic,$bg,$color]): ?>
    <div class="manager-dashboard-kpi" style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:16px 14px;box-shadow:0 1px 4px rgba(0,0,0,.04);transition:all .2s;"
         onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.08)'"
         onmouseout="this.style.transform='none';this.style.boxShadow='0 1px 4px rgba(0,0,0,.04)'">
        <div class="manager-dashboard-kpi-icon" style="width:38px;height:38px;border-radius:10px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;margin-bottom:10px;">
            <i class="bi <?= $ic ?>" style="color:<?= $color ?>;font-size:.95rem;"></i>
        </div>
        <div class="manager-dashboard-kpi-value" style="font-size:1.1rem;font-weight:800;color:#0f172a;letter-spacing:-.02em;line-height:1.1;margin-bottom:4px;"><?= $val ?></div>
        <div class="manager-dashboard-kpi-label" style="font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;"><?= $lbl ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recurring donors highlight bar -->
<div class="manager-dashboard-recurring" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;border-radius:13px;padding:14px 20px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="manager-dashboard-recurring-copy" style="display:flex;align-items:center;gap:12px;">
        <div class="manager-dashboard-recurring-icon" aria-hidden="true"><i class="bi bi-arrow-repeat"></i></div>
        <div>
            <div class="manager-dashboard-recurring-title" style="font-size:.86rem;font-weight:700;color:#15803d;">Recurring Donor Rate: <strong><?= $recurring_donor_pct ?>%</strong> (<?= $recurring_donors_count ?> of <?= $total_donors ?> donors)</div>
            <div class="manager-dashboard-recurring-description" style="font-size:.73rem;color:#16a34a;margin-top:2px;">Donors who have committed to monthly, quarterly, or yearly giving — key donor retention KPI</div>
        </div>
    </div>
    <a class="manager-dashboard-recurring-link" href="<?= SITE_URL ?>/manager/donations.php?frequency=monthly"
       style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;background:#15803d;color:white;border-radius:8px;font-size:.78rem;font-weight:700;text-decoration:none;white-space:nowrap;transition:opacity .15s;"
       onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        View Recurring <i class="bi bi-arrow-right"></i>
    </a>
</div>

<!-- Charts row -->
<div class="manager-dashboard-charts" style="display:grid;grid-template-columns:1fr 280px;gap:16px;margin-bottom:18px;">

    <!-- Monthly bar chart -->
    <div class="manager-dashboard-chart-card" style="background:white;border:1px solid #e2e8f0;border-radius:16px;padding:18px 20px;box-shadow:0 2px 8px rgba(0,0,0,.04);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:7px;">
                <i class="bi bi-bar-chart-line-fill" style="color:#1447c0;font-size:.88rem;"></i>
                <span class="manager-dashboard-card-title" style="font-size:.86rem;font-weight:700;color:#0f172a;">Monthly Donation Trend</span>
            </div>
            <a href="<?= SITE_URL ?>/manager/reports.php"
               style="font-size:.76rem;font-weight:600;color:#1447c0;text-decoration:none;display:flex;align-items:center;gap:4px;"
               onmouseover="this.style.color='#0d2157'" onmouseout="this.style.color='#1447c0'">
                Full Report <i class="bi bi-arrow-right" style="font-size:.72rem;"></i>
            </a>
        </div>
        <canvas id="monthlyChart" class="manager-dashboard-chart-canvas" style="max-height:210px;"></canvas>
    </div>

    <!-- Doughnut -->
    <div class="manager-dashboard-chart-card" style="background:white;border:1px solid #e2e8f0;border-radius:16px;padding:18px 20px;box-shadow:0 2px 8px rgba(0,0,0,.04);">
        <div style="display:flex;align-items:center;gap:7px;margin-bottom:14px;">
            <i class="bi bi-pie-chart-fill" style="color:#7c3aed;font-size:.88rem;"></i>
            <span class="manager-dashboard-card-title" style="font-size:.86rem;font-weight:700;color:#0f172a;">Donation Channels</span>
        </div>
        <?php if(!empty($ch_labels)): ?>
        <canvas id="channelChart" class="manager-dashboard-channel-canvas" style="max-height:160px;"></canvas>
        <?php else: ?>
        <div style="text-align:center;color:#94a3b8;padding:32px 0;font-size:.8rem;">No data yet</div>
        <?php endif; ?>
    </div>
</div>

<!-- Bottom row -->
<div class="manager-dashboard-bottom" style="display:grid;grid-template-columns:1fr 1fr 220px;gap:16px;">

    <!-- Recent Donations -->
    <div class="manager-dashboard-panel" style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04);">
        <div class="manager-dashboard-panel-heading" style="padding:13px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:7px;">
                <i class="bi bi-cash-stack" style="color:#15803d;font-size:.86rem;"></i>
                <span class="manager-dashboard-panel-title" style="font-size:.84rem;font-weight:700;color:#0f172a;">Recent Donations</span>
            </div>
            <a href="<?= SITE_URL ?>/manager/donations.php"
               style="font-size:.75rem;font-weight:600;color:#1447c0;text-decoration:none;">View All</a>
        </div>
        <div class="manager-dashboard-table-scroll" style="overflow-x:auto;">
        <table class="manager-dashboard-recent-table" style="width:100%;border-collapse:collapse;">
            <thead><tr style="background:#f8fafc;">
                <?php foreach(['Donor','Campaign','Amount','Status','Date'] as $h): ?>
                <th style="padding:8px 14px;font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;border-bottom:1px solid #f1f5f9;text-align:left;white-space:nowrap;"><?= $h ?></th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php if($recent&&$recent->num_rows>0): while($r=$recent->fetch_assoc()):
                [$s_bg,$s_co] = match($r['status']){
                    'verified'=>['#dcfce7','#15803d'],
                    'rejected'=>['#fee2e2','#991b1b'],
                    default   =>['#fef9c3','#854d0e'],
                };
            ?>
            <tr style="border-bottom:1px solid #f9fafb;" onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">
                <td style="padding:10px 14px;">
                    <div style="display:flex;align-items:center;gap:7px;">
                        <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1447c0);display:flex;align-items:center;justify-content:center;font-size:.62rem;font-weight:800;color:white;flex-shrink:0;">
                            <?= strtoupper(substr($r['is_anonymous']?'A':$r['donor_name'],0,1)) ?>
                        </div>
                        <span style="font-size:.77rem;font-weight:600;color:#0f172a;white-space:nowrap;"><?= htmlspecialchars($r['is_anonymous']?'Anonymous':$r['donor_name']) ?></span>
                    </div>
                </td>
                <td style="padding:10px 14px;font-size:.74rem;color:#64748b;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($r['camp_title']??'General') ?>
                </td>
                <td style="padding:10px 14px;white-space:nowrap;">
                    <span style="font-size:.8rem;font-weight:800;color:#15803d;"><?= number_format($r['amount']/1000) ?>K</span>
                    <span style="font-size:.66rem;color:#94a3b8;"> VND</span>
                </td>
                <td style="padding:10px 14px;">
                    <span style="background:<?= $s_bg ?>;color:<?= $s_co ?>;padding:2px 8px;border-radius:20px;font-size:.67rem;font-weight:700;"><?= ucfirst($r['status']) ?></span>
                </td>
                <td style="padding:10px 14px;font-size:.72rem;color:#94a3b8;white-space:nowrap;"><?= formatDate($r['created_at']) ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="5" style="padding:32px;text-align:center;color:#94a3b8;font-size:.79rem;">No donations yet</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Campaign Performance -->
    <div class="manager-dashboard-panel" style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04);">
        <div class="manager-dashboard-panel-heading" style="padding:13px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:7px;">
                <i class="bi bi-trophy-fill" style="color:#d97706;font-size:.86rem;"></i>
                <span class="manager-dashboard-panel-title" style="font-size:.84rem;font-weight:700;color:#0f172a;">Campaign Performance</span>
            </div>
            <a href="<?= SITE_URL ?>/manager/campaigns.php"
               style="font-size:.75rem;font-weight:600;color:#1447c0;text-decoration:none;">All</a>
        </div>
        <div style="padding:6px 0;">
        <?php if($top_camps&&$top_camps->num_rows>0): while($cp=$top_camps->fetch_assoc()):
            $pct=min(100,(float)($cp['pct']??0));
            $done=$pct>=100;
        ?>
        <div class="manager-dashboard-campaign-row" style="padding:10px 18px;border-bottom:1px solid #f9fafb;"
             onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                <span style="font-size:.78rem;font-weight:600;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:170px;"><?= htmlspecialchars($cp['title']) ?></span>
                <span style="font-size:.75rem;font-weight:800;color:<?= $done?'#15803d':'#1447c0' ?>;flex-shrink:0;margin-left:8px;"><?= $pct ?>%</span>
            </div>
            <div style="height:5px;background:#f1f5f9;border-radius:3px;overflow:hidden;margin-bottom:4px;">
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $done?'linear-gradient(90deg,#22c55e,#16a34a)':'linear-gradient(90deg,#3b82f6,#1447c0)' ?>;border-radius:3px;transition:width .5s;"></div>
            </div>
            <div style="font-size:.69rem;color:#94a3b8;"><?= number_format($cp['raised_amount']/1000000,1) ?>M / <?= number_format($cp['target_amount']/1000000,0) ?>M VND</div>
        </div>
        <?php endwhile; endif; ?>
        </div>
    </div>

    <!-- Top Donors -->
    <div class="manager-dashboard-panel" style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04);">
        <div class="manager-dashboard-panel-heading" style="padding:13px 16px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:7px;">
            <i class="bi bi-star-fill" style="color:#f59e0b;font-size:.86rem;"></i>
            <span class="manager-dashboard-panel-title" style="font-size:.84rem;font-weight:700;color:#0f172a;">Top Donors</span>
        </div>
        <div style="padding:6px 0;">
        <?php if($top_donors&&$top_donors->num_rows>0):
            $rank=1; while($td=$top_donors->fetch_assoc()): ?>
        <div class="manager-dashboard-donor-row" style="display:flex;align-items:center;gap:10px;padding:9px 16px;border-bottom:1px solid #f9fafb;"
             onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">
            <span style="font-size:.7rem;font-weight:800;color:#cbd5e1;width:14px;text-align:center;flex-shrink:0;"><?= $rank ?></span>
            <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1447c0);display:flex;align-items:center;justify-content:center;font-size:.66rem;font-weight:800;color:white;flex-shrink:0;">
                <?= strtoupper(substr($td['name'],0,1)) ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:.76rem;font-weight:600;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($td['name']) ?></div>
                <div style="font-size:.67rem;color:#94a3b8;"><?= $td['cnt'] ?> donation<?= $td['cnt']>1?'s':'' ?></div>
            </div>
            <div style="font-size:.73rem;font-weight:800;color:#15803d;flex-shrink:0;white-space:nowrap;"><?= number_format($td['total']/1000000,1) ?>M</div>
        </div>
        <?php $rank++; endwhile; endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family="-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
Chart.defaults.font.size=11;

// Monthly bar
const ctx1=document.getElementById('monthlyChart');
if(ctx1){new Chart(ctx1,{type:'bar',data:{labels:<?= json_encode($m_labels) ?>,datasets:[{label:'Donations (VND)',data:<?= json_encode($m_amounts) ?>,backgroundColor:'rgba(20,71,192,.7)',borderColor:'#1447c0',borderWidth:0,borderRadius:6,yAxisID:'y'},{label:'Count',data:<?= json_encode($m_counts) ?>,type:'line',borderColor:'#ef4444',backgroundColor:'rgba(239,68,68,.08)',borderWidth:2,pointRadius:4,pointBackgroundColor:'#ef4444',tension:.35,yAxisID:'y1'}]},options:{responsive:true,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'top',labels:{boxWidth:9,padding:12,color:'#64748b'}}},scales:{x:{grid:{display:false},ticks:{color:'#94a3b8'}},y:{beginAtZero:true,grid:{color:'rgba(0,0,0,.04)'},ticks:{color:'#94a3b8',callback:v=>new Intl.NumberFormat('vi-VN',{notation:'compact'}).format(v)}},y1:{beginAtZero:true,position:'right',grid:{drawOnChartArea:false},ticks:{color:'#94a3b8'}}}}})}

// Doughnut
<?php if(!empty($ch_labels)): ?>
const ctx2=document.getElementById('channelChart');
if(ctx2){new Chart(ctx2,{type:'doughnut',data:{labels:<?= json_encode($ch_labels) ?>,datasets:[{data:<?= json_encode($ch_data) ?>,backgroundColor:['#1447c0','#15803d','#d97706','#7c3aed','#0f766e'],borderWidth:2,borderColor:'#fff',hoverOffset:4}]},options:{responsive:true,cutout:'65%',plugins:{legend:{position:'bottom',labels:{boxWidth:9,padding:10,color:'#64748b'}}}}})}
<?php endif; ?>
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
