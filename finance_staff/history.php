<?php
$pageTitle  = 'Verification History';
$activePage = 'history';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('finance_staff');

$db = getApplicationDB();
$filter_action = $_GET['action'] ?? '';
$where = '1=1';
if ($filter_action) { $fa=$db->real_escape_string($filter_action); $where.=" AND vh.action='$fa'"; }

$history = $db->query("
    SELECT vh.*, t.reference_code, d.amount, d.channel,
           donor.name as donor_name,
           staff.name as staff_name,
           c.title as campaign_title
    FROM verification_history vh
    JOIN transactions t ON vh.transaction_id=t.id
    JOIN donations d ON t.donation_id=d.id
    JOIN users donor ON d.donor_id=donor.id
    JOIN users staff ON vh.performed_by=staff.id
    LEFT JOIN campaigns c ON d.campaign_id=c.id
    WHERE $where
    ORDER BY vh.created_at DESC
    LIMIT 100
");

// Count per action
$counts = [];
foreach(['submitted','verified','rejected','resubmitted'] as $a) {
    $r = $db->query("SELECT COUNT(*) as c FROM verification_history WHERE action='$a'");
    $counts[$a] = (int)$r->fetch_assoc()['c'];
}
$total = array_sum($counts);

$action_styles = [
    'verified'    => ['bg'=>'#dcfce7','color'=>'#15803d','border'=>'#bbf7d0','icon'=>'bi-check-circle-fill',  'label'=>'Verified'],
    'rejected'    => ['bg'=>'#fee2e2','color'=>'#991b1b','border'=>'#fecaca','icon'=>'bi-x-circle-fill',     'label'=>'Rejected'],
    'submitted'   => ['bg'=>'#fef9c3','color'=>'#854d0e','border'=>'#fde68a','icon'=>'bi-send-fill',         'label'=>'Submitted'],
    'resubmitted' => ['bg'=>'#ede9fe','color'=>'#6d28d9','border'=>'#ddd6fe','icon'=>'bi-arrow-clockwise',   'label'=>'Resubmitted'],
];
?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
            <div style="width:34px;height:34px;background:linear-gradient(135deg,#7c3aed,#6d28d9);border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 10px rgba(124,58,237,.3);">
                <i class="bi bi-clock-history" style="color:white;font-size:.88rem;"></i>
            </div>
            <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0;">Verification History</h1>
        </div>
        <p style="color:#64748b;font-size:.79rem;margin:0 0 0 44px;">Complete audit trail of all verification actions</p>
    </div>
    <a href="<?= SITE_URL ?>/finance_staff/transactions.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:9px;color:#64748b;font-size:.8rem;font-weight:600;background:white;text-decoration:none;white-space:nowrap;transition:all .15s;"
       onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
        <i class="bi bi-list-check"></i>All Transactions
    </a>
</div>

<!-- Summary stats -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px;">
    <?php
    $stat_items = [
        ['Total',       $total,            '#dbeafe','#1447c0','bi-journal-check'],
        ['Submitted',   $counts['submitted'],'#fef9c3','#854d0e','bi-send-fill'],
        ['Verified',    $counts['verified'], '#dcfce7','#15803d','bi-check-circle-fill'],
        ['Rejected',    $counts['rejected'], '#fee2e2','#991b1b','bi-x-circle-fill'],
        ['Resubmitted', $counts['resubmitted'],'#ede9fe','#6d28d9','bi-arrow-clockwise'],
    ];
    foreach($stat_items as [$lbl,$val,$bg,$color,$ic]): ?>
    <div style="background:white;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;box-shadow:0 1px 4px rgba(0,0,0,.04);display:flex;align-items:center;gap:12px;">
        <div style="width:36px;height:36px;border-radius:9px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi <?= $ic ?>" style="color:<?= $color ?>;font-size:.88rem;"></i>
        </div>
        <div>
            <div style="font-size:1.15rem;font-weight:800;color:#0f172a;letter-spacing:-.02em;line-height:1.1;"><?= $val ?></div>
            <div style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-top:2px;"><?= $lbl ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Main table card -->
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.05);overflow:hidden;">

    <!-- Top bar: title + filter tabs -->
    <div style="padding:14px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <div style="display:flex;align-items:center;gap:7px;">
            <i class="bi bi-shield-check" style="color:#7c3aed;font-size:.9rem;"></i>
            <span style="font-size:.86rem;font-weight:700;color:#0f172a;">Audit Log</span>
        </div>
        <!-- Filter pills -->
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <?php
            $tabs = [
                [''            ,'All',         $total,                  '#0f172a','#f1f5f9','#e2e8f0'],
                ['submitted'   ,'Submitted',   $counts['submitted'],    '#854d0e','#fef9c3','#fde68a'],
                ['verified'    ,'Verified',    $counts['verified'],     '#15803d','#dcfce7','#bbf7d0'],
                ['rejected'    ,'Rejected',    $counts['rejected'],     '#991b1b','#fee2e2','#fecaca'],
                ['resubmitted' ,'Resubmitted', $counts['resubmitted'], '#6d28d9','#ede9fe','#ddd6fe'],
            ];
            foreach($tabs as [$val,$label,$cnt,$color,$bg,$border]):
                $active = $filter_action === $val;
            ?>
            <a href="?action=<?= $val ?>"
               style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:.75rem;font-weight:700;border:1.5px solid <?= $active?$border:'#e2e8f0' ?>;background:<?= $active?$bg:'transparent' ?>;color:<?= $active?$color:'#64748b' ?>;transition:all .15s;text-decoration:none;"
               onmouseover="this.style.background='<?= $bg ?>'" onmouseout="this.style.background='<?= $active?$bg:'transparent' ?>'">
                <?= $label ?>
                <span style="background:<?= $active?$border:'#f1f5f9' ?>;color:<?= $active?$color:'#94a3b8' ?>;padding:1px 6px;border-radius:10px;font-size:.66rem;font-weight:800;"><?= $cnt ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Table -->
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc;">
                    <?php foreach(['Date / Time','Action','Reference','Donor','Amount','Campaign','Performed By','Notes'] as $h): ?>
                    <th style="padding:10px 16px;text-align:left;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;border-bottom:1px solid #f1f5f9;white-space:nowrap;"><?= $h ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if($history && $history->num_rows > 0): while($h=$history->fetch_assoc()):
                $a     = $h['action'];
                $style = $action_styles[$a] ?? $action_styles['submitted'];
            ?>
                <tr style="border-bottom:1px solid #f9fafb;transition:background .12s;"
                    onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">

                    <!-- DateTime -->
                    <td style="padding:12px 16px;white-space:nowrap;">
                        <div style="font-size:.78rem;font-weight:600;color:#0f172a;"><?= date('d/m/Y', strtotime($h['created_at'])) ?></div>
                        <div style="font-size:.7rem;color:#94a3b8;"><?= date('H:i', strtotime($h['created_at'])) ?></div>
                    </td>

                    <!-- Action badge -->
                    <td style="padding:12px 16px;">
                        <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $style['bg'] ?>;color:<?= $style['color'] ?>;border:1px solid <?= $style['border'] ?>;padding:4px 10px;border-radius:20px;font-size:.71rem;font-weight:700;white-space:nowrap;">
                            <i class="bi <?= $style['icon'] ?>" style="font-size:.68rem;"></i>
                            <?= $style['label'] ?>
                        </span>
                    </td>

                    <!-- Reference -->
                    <td style="padding:12px 16px;">
                        <a href="<?= SITE_URL ?>/finance_staff/verify.php?id=<?= $h['transaction_id'] ?>"
                           style="font-family:ui-monospace,monospace;font-size:.74rem;font-weight:700;color:#1447c0;text-decoration:none;white-space:nowrap;letter-spacing:.02em;">
                            <?= htmlspecialchars(substr($h['reference_code']??'—',0,16)) ?>
                        </a>
                    </td>

                    <!-- Donor -->
                    <td style="padding:12px 16px;min-width:140px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1447c0);display:flex;align-items:center;justify-content:center;font-size:.64rem;font-weight:800;color:white;flex-shrink:0;">
                                <?= strtoupper(substr($h['donor_name'],0,1)) ?>
                            </div>
                            <span style="font-size:.78rem;font-weight:600;color:#0f172a;"><?= htmlspecialchars($h['donor_name']) ?></span>
                        </div>
                    </td>

                    <!-- Amount -->
                    <td style="padding:12px 16px;white-space:nowrap;">
                        <span style="font-size:.82rem;font-weight:800;color:#15803d;"><?= number_format($h['amount']) ?></span>
                        <span style="font-size:.68rem;color:#94a3b8;margin-left:2px;">VND</span>
                    </td>

                    <!-- Campaign -->
                    <td style="padding:12px 16px;max-width:160px;">
                        <div style="font-size:.76rem;color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= htmlspecialchars($h['campaign_title']??'General') ?>
                        </div>
                    </td>

                    <!-- Performed by -->
                    <td style="padding:12px 16px;">
                        <div style="display:flex;align-items:center;gap:7px;">
                            <div style="width:24px;height:24px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:.62rem;font-weight:700;color:#475569;flex-shrink:0;border:1px solid #e2e8f0;">
                                <?= strtoupper(substr($h['staff_name'],0,1)) ?>
                            </div>
                            <span style="font-size:.76rem;color:#0f172a;white-space:nowrap;font-weight:500;"><?= htmlspecialchars(explode(' ',$h['staff_name'])[0]) ?></span>
                        </div>
                    </td>

                    <!-- Notes -->
                    <td style="padding:12px 16px;max-width:180px;">
                        <?php if($h['notes']): ?>
                        <span style="font-size:.74rem;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;" title="<?= htmlspecialchars($h['notes']) ?>">
                            <?= htmlspecialchars($h['notes']) ?>
                        </span>
                        <?php else: ?>
                        <span style="color:#e2e8f0;font-size:.8rem;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr>
                    <td colspan="8" style="padding:52px 20px;text-align:center;">
                        <i class="bi bi-clock-history" style="font-size:2.5rem;color:#e2e8f0;display:block;margin-bottom:12px;"></i>
                        <div style="font-size:.84rem;font-weight:600;color:#94a3b8;margin-bottom:4px;">No history found</div>
                        <div style="font-size:.76rem;color:#cbd5e1;">Verification actions will appear here</div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Footer -->
    <div style="padding:11px 20px;border-top:1px solid #f8fafc;background:#fafafa;display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:.73rem;color:#94a3b8;">
            Showing latest <strong style="color:#475569;"><?= $history ? $history->num_rows : 0 ?></strong> records
            <?php if($filter_action): ?> · Filter: <strong style="color:#7c3aed;"><?= ucfirst($filter_action) ?></strong><?php endif; ?>
        </span>
        <?php if($filter_action): ?>
        <a href="<?= SITE_URL ?>/finance_staff/history.php"
           style="font-size:.73rem;color:#7c3aed;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:4px;">
            <i class="bi bi-x-circle" style="font-size:.76rem;"></i>Clear filter
        </a>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
