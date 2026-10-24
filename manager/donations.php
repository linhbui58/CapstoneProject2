<?php
$pageTitle  = 'All Donations';
$activePage = 'donations';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('manager');
$db = getDB();

// Auto-add frequency column if not yet present (safe migration)
$db->query("ALTER TABLE donations ADD COLUMN IF NOT EXISTS frequency ENUM('one_time','monthly','quarterly','yearly') NOT NULL DEFAULT 'one_time' AFTER channel");

$filter_status    = $_GET['status']    ?? '';
$filter_campaign  = (int)($_GET['campaign_id'] ?? 0);
$filter_frequency = $_GET['frequency'] ?? '';
$search           = $_GET['search'] ?? '';

$where = '1=1';
if ($filter_status)    { $fs=$db->real_escape_string($filter_status);    $where.=" AND d.status='$fs'"; }
if ($filter_campaign)  { $where.=" AND d.campaign_id=$filter_campaign"; }
if ($filter_frequency) { $ff=$db->real_escape_string($filter_frequency); $where.=" AND d.frequency='$ff'"; }
if ($search)           { $s=$db->real_escape_string($search); $where.=" AND (u.name LIKE '%$s%' OR t.reference_code LIKE '%$s%' OR u.email LIKE '%$s%')"; }

$donations = $db->query("
    SELECT d.*, u.name as donor_name, u.email as donor_email,
           c.title as campaign_title,
           t.reference_code, t.verification_status, t.bank_name
    FROM donations d
    JOIN users u ON d.donor_id=u.id
    LEFT JOIN campaigns c ON d.campaign_id=c.id
    LEFT JOIN transactions t ON t.donation_id=d.id
    WHERE $where
    ORDER BY d.created_at DESC
");
$total_rows = $donations ? $donations->num_rows : 0;

// Stat counts
$stat_v   = $db->query("SELECT COUNT(*) as c, COALESCE(SUM(amount),0) as t FROM donations WHERE status='verified'")->fetch_assoc();
$stat_p   = $db->query("SELECT COUNT(*) as c, COALESCE(SUM(amount),0) as t FROM donations WHERE status='pending'")->fetch_assoc();
$stat_r   = $db->query("SELECT COUNT(*) as c, COALESCE(SUM(amount),0) as t FROM donations WHERE status='rejected'")->fetch_assoc();
$stat_all = (int)$stat_v['c'] + (int)$stat_p['c'] + (int)$stat_r['c'];

// Recurring stats
$stat_recurring = $db->query("SELECT COUNT(DISTINCT donor_id) as donors, COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM donations WHERE frequency != 'one_time' AND status='verified'")->fetch_assoc();
$recurring_donor_rate = (int)$stat_v['c'] > 0
    ? round(((int)$stat_recurring['donors'] / (int)$db->query("SELECT COUNT(DISTINCT donor_id) as c FROM donations WHERE status='verified'")->fetch_assoc()['c']) * 100, 1)
    : 0;

$campaigns = $db->query("SELECT id,title FROM campaigns ORDER BY title");

$ch_icons  = ['bank_transfer'=>'bi-bank2','e_wallet'=>'bi-phone-fill','credit_card'=>'bi-credit-card-fill','paypal'=>'bi-paypal','cash'=>'bi-cash-stack'];
$ch_labels = ['bank_transfer'=>'Bank Transfer','e_wallet'=>'E-Wallet','credit_card'=>'Credit Card','paypal'=>'PayPal','cash'=>'Cash'];
$ch_colors = ['bank_transfer'=>['#dbeafe','#1447c0'],'e_wallet'=>['#dcfce7','#15803d'],'credit_card'=>['#ede9fe','#7c3aed'],'paypal'=>['#fff7ed','#c2410c'],'cash'=>['#fef9c3','#854d0e']];

$freq_cfg  = [
    'one_time'  => ['label'=>'One Time',  'icon'=>'bi-1-circle',       'bg'=>'#f1f5f9','color'=>'#475569'],
    'monthly'   => ['label'=>'Monthly',   'icon'=>'bi-calendar-month', 'bg'=>'#dcfce7','color'=>'#15803d'],
    'quarterly' => ['label'=>'Quarterly', 'icon'=>'bi-calendar3',      'bg'=>'#dbeafe','color'=>'#1447c0'],
    'yearly'    => ['label'=>'Yearly',    'icon'=>'bi-calendar-check', 'bg'=>'#ede9fe','color'=>'#7c3aed'],
];
?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-cash-stack" style="color:#15803d;font-size:1rem;"></i>All Donations
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">View and monitor all donation records</p>
    </div>
</div>

<!-- Stat cards — 5 cards including Recurring -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px;">
    <?php
    $cards = [
        [$stat_all,                           'Total Donations',   number_format(((float)$stat_v['t']+(float)$stat_p['t']+(float)$stat_r['t'])/1000000,1).'M VND', 'bi-receipt',          '#f1f5f9','#475569'],
        [(int)$stat_v['c'],                   'Verified',          number_format((float)$stat_v['t']/1000000,1).'M VND',                                            'bi-check-circle-fill','#dcfce7','#15803d'],
        [(int)$stat_p['c'],                   'Pending',           number_format((float)$stat_p['t']/1000000,1).'M VND',                                            'bi-clock-fill',       '#fef9c3','#d97706'],
        [(int)$stat_r['c'],                   'Rejected',          number_format((float)$stat_r['t']/1000000,1).'M VND',                                            'bi-x-circle-fill',    '#fee2e2','#991b1b'],
        [(int)$stat_recurring['donors'].' donors 🔄', 'Recurring', number_format((float)$stat_recurring['total']/1000000,1).'M VND · '.$recurring_donor_rate.'% of donors', 'bi-arrow-repeat', '#dcfce7','#15803d'],
    ];
    foreach($cards as [$val,$lbl,$sub,$ic,$bg,$color]): ?>
    <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;box-shadow:0 1px 4px rgba(0,0,0,.04);transition:all .2s;"
         onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.07)'"
         onmouseout="this.style.transform='none';this.style.boxShadow='0 1px 4px rgba(0,0,0,.04)'">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:11px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;">
                <i class="bi <?= $ic ?>" style="color:<?= $color ?>;font-size:1rem;"></i>
            </div>
        </div>
        <div style="font-size:1.55rem;font-weight:800;color:#0f172a;letter-spacing:-.03em;line-height:1;margin-bottom:4px;"><?= $val ?></div>
        <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:4px;"><?= $lbl ?></div>
        <div style="font-size:.74rem;font-weight:600;color:<?= $color ?>;"><?= $sub ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Main card -->
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;">

    <!-- Toolbar -->
    <div style="padding:14px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">

        <!-- Status tabs -->
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <?php
            $tabs=[
                [''        ,'All',      $stat_all,         '#0f172a','#f1f5f9','#e2e8f0'],
                ['verified','Verified', (int)$stat_v['c'], '#15803d','#dcfce7','#bbf7d0'],
                ['pending', 'Pending',  (int)$stat_p['c'], '#854d0e','#fef9c3','#fde68a'],
                ['rejected','Rejected', (int)$stat_r['c'], '#991b1b','#fee2e2','#fecaca'],
            ];
            foreach($tabs as [$val,$label,$cnt,$color,$bg,$border]):
                $active=$filter_status===$val;
            ?>
            <a href="?status=<?= $val ?><?= $filter_campaign?'&campaign_id='.$filter_campaign:'' ?><?= $search?'&search='.urlencode($search):'' ?>"
               style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:.76rem;font-weight:700;border:1.5px solid <?= $active?$border:'#e2e8f0' ?>;background:<?= $active?$bg:'transparent' ?>;color:<?= $active?$color:'#64748b' ?>;transition:all .15s;text-decoration:none;"
               onmouseover="this.style.background='<?= $bg ?>'" onmouseout="this.style.background='<?= $active?$bg:'transparent' ?>'">
                <?= $label ?>
                <span style="background:<?= $active?$border:'#f1f5f9' ?>;color:<?= $active?$color:'#94a3b8' ?>;padding:1px 6px;border-radius:10px;font-size:.66rem;font-weight:800;"><?= $cnt ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Search + filters -->
        <form method="GET" style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;">
            <?php if($filter_status): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"><?php endif; ?>

            <!-- Search input -->
            <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;height:36px;background:white;transition:border-color .15s;" onfocusin="this.style.borderColor='#1447c0'" onfocusout="this.style.borderColor='#e2e8f0'">
                <i class="bi bi-search" style="padding:0 10px;color:#94a3b8;font-size:.82rem;flex-shrink:0;"></i>
                <input type="text" name="search"
                       style="border:none;outline:none;font-size:.81rem;color:#0f172a;width:190px;background:transparent;height:100%;padding-right:10px;"
                       placeholder="Search donor or ref..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>

            <!-- Campaign filter -->
            <select name="campaign_id" onchange="this.form.submit()"
                    style="height:36px;border:1.5px solid #e2e8f0;border-radius:9px;padding:0 12px;font-size:.8rem;color:#475569;background:white;outline:none;cursor:pointer;max-width:180px;">
                <option value="">All Campaigns</option>
                <?php if($campaigns) while($cam=$campaigns->fetch_assoc()): ?>
                <option value="<?= $cam['id'] ?>" <?= $filter_campaign==$cam['id']?'selected':'' ?>><?= htmlspecialchars(substr($cam['title'],0,28)) ?></option>
                <?php endwhile; ?>
            </select>

            <!-- Frequency filter -->
            <select name="frequency" onchange="this.form.submit()"
                    style="height:36px;border:1.5px solid #e2e8f0;border-radius:9px;padding:0 12px;font-size:.8rem;color:#475569;background:white;outline:none;cursor:pointer;">
                <option value="">All Frequencies</option>
                <option value="one_time"  <?= $filter_frequency==='one_time' ?'selected':'' ?>>One Time</option>
                <option value="monthly"   <?= $filter_frequency==='monthly'  ?'selected':'' ?>>🔄 Monthly</option>
                <option value="quarterly" <?= $filter_frequency==='quarterly'?'selected':'' ?>>🔄 Quarterly</option>
                <option value="yearly"    <?= $filter_frequency==='yearly'   ?'selected':'' ?>>🔄 Yearly</option>
            </select>

            <button type="submit"
                    style="height:36px;padding:0 15px;background:#1447c0;color:white;border:none;border-radius:9px;font-size:.8rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;transition:background .15s;white-space:nowrap;"
                    onmouseover="this.style.background='#0d2157'" onmouseout="this.style.background='#1447c0'">
                <i class="bi bi-funnel-fill" style="font-size:.76rem;"></i>Filter
            </button>
            <?php if($search||$filter_status||$filter_campaign||$filter_frequency): ?>
            <a href="<?= SITE_URL ?>/manager/donations.php"
               style="height:36px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.8rem;color:#64748b;background:white;display:flex;align-items:center;gap:4px;text-decoration:none;transition:all .15s;white-space:nowrap;"
               onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                <i class="bi bi-x-lg" style="font-size:.72rem;"></i>Clear
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="padding:10px 16px;text-align:center;font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;border-bottom:1px solid #f1f5f9;width:40px;">#</th>
                    <?php foreach(['Donor','Campaign','Amount','Channel','Frequency','Reference','Verification','Date'] as $h): ?>
                    <th style="padding:10px 16px;text-align:left;font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;border-bottom:1px solid #f1f5f9;white-space:nowrap;"><?= $h ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if($donations&&$donations->num_rows>0):
                $i=1; while($d=$donations->fetch_assoc()):
                $ch    = $d['channel']??'';
                $freq  = $d['frequency'] ?? 'one_time';
                $fc    = $freq_cfg[$freq] ?? $freq_cfg['one_time'];
                $ch_ic = $ch_icons[$ch]??'bi-credit-card';
                $ch_lb = $ch_labels[$ch]??ucfirst(str_replace('_',' ',$ch));
                [$ch_bg,$ch_co] = $ch_colors[$ch]??['#f1f5f9','#475569'];
                $vs    = $d['verification_status']??$d['status'];
                [$vs_bg,$vs_co,$vs_bd,$vs_lb] = match($vs){
                    'verified'              =>['#dcfce7','#15803d','#bbf7d0','Verified'],
                    'rejected'              =>['#fee2e2','#991b1b','#fecaca','Rejected'],
                    'pending_verification'  =>['#fef9c3','#854d0e','#fde68a','Pending verification'],
                    default                 =>['#fef9c3','#854d0e','#fde68a','Pending'],
                };
            ?>
            <tr style="border-bottom:1px solid #f9fafb;transition:background .12s;"
                onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">

                <!-- # -->
                <td style="padding:12px 16px;text-align:center;font-size:.74rem;font-weight:700;color:#cbd5e1;"><?= $i ?></td>

                <!-- Donor -->
                <td style="padding:12px 16px;min-width:150px;">
                    <div style="display:flex;align-items:center;gap:9px;">
                        <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1447c0);display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:800;color:white;flex-shrink:0;">
                            <?= strtoupper(substr($d['is_anonymous']?'A':$d['donor_name'],0,1)) ?>
                        </div>
                        <div>
                            <div style="font-size:.79rem;font-weight:700;color:#0f172a;"><?= htmlspecialchars($d['is_anonymous']?'Anonymous':$d['donor_name']) ?></div>
                            <?php if(!$d['is_anonymous']): ?>
                            <div style="font-size:.68rem;color:#94a3b8;"><?= htmlspecialchars($d['donor_email']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>

                <!-- Campaign -->
                <td style="padding:12px 16px;max-width:160px;">
                    <div style="font-size:.77rem;color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($d['campaign_title']??'') ?>">
                        <?= htmlspecialchars($d['campaign_title']??'General Donation') ?>
                    </div>
                </td>

                <!-- Amount -->
                <td style="padding:12px 16px;white-space:nowrap;">
                    <span style="font-size:.85rem;font-weight:800;color:#15803d;"><?= number_format($d['amount']/1000000,1) ?>M</span>
                    <span style="font-size:.67rem;color:#94a3b8;"> VND</span>
                </td>

                <!-- Channel -->
                <td style="padding:12px 16px;">
                    <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $ch_bg ?>;color:<?= $ch_co ?>;padding:3px 9px;border-radius:6px;font-size:.71rem;font-weight:700;white-space:nowrap;">
                        <i class="bi <?= $ch_ic ?>" style="font-size:.7rem;"></i><?= $ch_lb ?>
                    </span>
                </td>

                <!-- Frequency -->
                <td style="padding:12px 16px;">
                    <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $fc['bg'] ?>;color:<?= $fc['color'] ?>;padding:3px 9px;border-radius:6px;font-size:.71rem;font-weight:700;white-space:nowrap;">
                        <i class="bi <?= $fc['icon'] ?>" style="font-size:.7rem;"></i><?= $fc['label'] ?>
                        <?= $freq !== 'one_time' ? '🔄' : '' ?>
                    </span>
                </td>

                <!-- Reference -->
                <td style="padding:12px 16px;">
                    <?php if($d['reference_code']): ?>
                    <code style="font-size:.72rem;color:#1447c0;background:#eff6ff;padding:3px 8px;border-radius:5px;border:1px solid #dbeafe;font-family:ui-monospace,monospace;white-space:nowrap;">
                        <?= htmlspecialchars(substr($d['reference_code'],0,14)) ?>
                    </code>
                    <?php else: ?>
                    <span style="color:#e2e8f0;font-size:.8rem;">—</span>
                    <?php endif; ?>
                </td>

                <!-- Verification -->
                <td style="padding:12px 16px;">
                    <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $vs_bg ?>;color:<?= $vs_co ?>;border:1px solid <?= $vs_bd ?>;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;white-space:nowrap;">
                        <?= $vs_lb ?>
                    </span>
                </td>

                <!-- Date -->
                <td style="padding:12px 16px;font-size:.75rem;color:#64748b;white-space:nowrap;">
                    <?= formatDate($d['created_at']) ?>
                </td>
            </tr>
            <?php $i++; endwhile; else: ?>
            <tr>
                <td colspan="8" style="padding:52px 20px;text-align:center;">
                    <i class="bi bi-inbox" style="font-size:2.5rem;color:#e2e8f0;display:block;margin-bottom:12px;"></i>
                    <div style="font-size:.84rem;font-weight:600;color:#94a3b8;margin-bottom:4px;">No donations found</div>
                    <div style="font-size:.76rem;color:#cbd5e1;">Try adjusting your search or filter</div>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Footer -->
    <div style="padding:11px 20px;border-top:1px solid #f8fafc;background:#fafafa;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <span style="font-size:.73rem;color:#94a3b8;">
            Showing <strong style="color:#475569;"><?= $total_rows ?></strong> records
            <?php if($filter_status): ?> · Status: <strong style="color:#1447c0;"><?= ucfirst($filter_status) ?></strong><?php endif; ?>
            <?php if($search): ?> · Search: "<strong style="color:#1447c0;"><?= htmlspecialchars($search) ?></strong>"<?php endif; ?>
        </span>
        <?php if($search||$filter_status||$filter_campaign): ?>
        <a href="<?= SITE_URL ?>/manager/donations.php" style="font-size:.73rem;color:#1447c0;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:4px;">
            <i class="bi bi-x-circle" style="font-size:.76rem;"></i>Clear all filters
        </a>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
