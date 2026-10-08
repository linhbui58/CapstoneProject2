<?php
$pageTitle  = 'Transaction Management';
$activePage = 'transactions';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('finance_staff');

$db = getDB();
$filter_status = $_GET['status'] ?? '';
$search        = $_GET['search'] ?? '';

$where = '1=1';
if ($filter_status) { $fs=$db->real_escape_string($filter_status); $where.=" AND t.verification_status='$fs'"; }
if ($search) { $s=$db->real_escape_string($search); $where.=" AND (t.reference_code LIKE '%$s%' OR u.name LIKE '%$s%' OR c.title LIKE '%$s%')"; }

$transactions = $db->query("
    SELECT t.*, d.amount, d.channel, d.created_at as donation_date, d.id as donation_id,
           u.name as donor_name, u.email as donor_email,
           c.title as campaign_title,
           verifier.name as verified_by_name
    FROM transactions t
    JOIN donations d ON t.donation_id=d.id
    JOIN users u ON d.donor_id=u.id
    LEFT JOIN campaigns c ON d.campaign_id=c.id
    LEFT JOIN users verifier ON t.verified_by=verifier.id
    WHERE $where
    ORDER BY t.created_at DESC
");
$total_rows = $transactions ? $transactions->num_rows : 0;

$counts = [];
foreach(['pending_verification','verified','rejected'] as $s2){
    $r=$db->query("SELECT COUNT(*) as cnt FROM transactions WHERE verification_status='$s2'");
    $counts[$s2]=(int)$r->fetch_assoc()['cnt'];
}
$total_all = array_sum($counts);

// Channel icon map
$ch_icons = ['bank_transfer'=>'bi-bank2','e_wallet'=>'bi-phone-fill','credit_card'=>'bi-credit-card-fill','paypal'=>'bi-paypal','cash'=>'bi-cash-stack'];
$ch_colors = ['bank_transfer'=>['#dbeafe','#1447c0'],'e_wallet'=>['#dcfce7','#15803d'],'credit_card'=>['#ede9fe','#7c3aed'],'paypal'=>['#fff7ed','#c2410c'],'cash'=>['#fef9c3','#854d0e']];
?>

<!-- Page header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;">Transaction Management</h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">View, filter and manage all donation transactions</p>
    </div>
    <div style="display:flex;gap:9px;flex-shrink:0;">
        <a href="<?= SITE_URL ?>/finance_staff/create_transaction.php"
           style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:linear-gradient(135deg,#1447c0,#0d2157);color:white;border-radius:9px;font-weight:700;font-size:.8rem;box-shadow:0 3px 10px rgba(20,71,192,.25);text-decoration:none;transition:all .18s;white-space:nowrap;"
           onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
            <i class="bi bi-plus-lg"></i>+ Create New Transaction
        </a>
        <a href="<?= SITE_URL ?>/finance_staff/pending.php"
           style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:linear-gradient(135deg,#f59e0b,#d97706);color:white;border-radius:9px;font-weight:700;font-size:.8rem;box-shadow:0 3px 10px rgba(245,158,11,.25);text-decoration:none;transition:all .18s;white-space:nowrap;"
           onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
            <i class="bi bi-shield-check"></i>Pending
            <?php if($counts['pending_verification']>0): ?>
            <span style="background:rgba(0,0,0,.18);color:white;padding:1px 7px;border-radius:20px;font-size:.7rem;font-weight:800;"><?= $counts['pending_verification'] ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<!-- Main card -->
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.05);overflow:hidden;">

    <!-- Top bar: tabs + search -->
    <div style="padding:14px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">

        <!-- Status pill tabs -->
        <div style="display:flex;align-items:center;gap:6px;">
            <?php
            $tabs = [
                [''                    ,'All',     $total_all,                    '#0f172a','#f1f5f9','#e2e8f0'],
                ['pending_verification','Pending', $counts['pending_verification'],'#854d0e','#fef9c3','#fde68a'],
                ['verified'            ,'Verified',$counts['verified'],            '#15803d','#dcfce7','#bbf7d0'],
                ['rejected'            ,'Rejected',$counts['rejected'],            '#991b1b','#fee2e2','#fecaca'],
            ];
            foreach($tabs as [$val,$label,$cnt,$color,$bg,$border]):
                $active = $filter_status===$val;
            ?>
            <a href="?status=<?= $val ?><?= $search?'&search='.urlencode($search):'' ?>"
               style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:.76rem;font-weight:700;border:1.5px solid <?= $active?$border:'#e2e8f0' ?>;background:<?= $active?$bg:'transparent' ?>;color:<?= $active?$color:'#64748b' ?>;transition:all .15s;text-decoration:none;"
               onmouseover="this.style.background='<?= $bg ?>'" onmouseout="this.style.background='<?= $active?$bg:'transparent' ?>'">
                <?= $label ?>
                <span style="background:<?= $active?$border:'#f1f5f9' ?>;color:<?= $active?$color:'#94a3b8' ?>;padding:1px 6px;border-radius:10px;font-size:.67rem;font-weight:800;"><?= $cnt ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Search + filter -->
        <form method="GET" style="display:flex;align-items:center;gap:7px;">
            <?php if($filter_status): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"><?php endif; ?>
            <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;height:36px;background:white;transition:border-color .15s;" onfocusin="this.style.borderColor='#1447c0'" onfocusout="this.style.borderColor='#e2e8f0'">
                <i class="bi bi-search" style="padding:0 10px;color:#94a3b8;font-size:.82rem;flex-shrink:0;"></i>
                <input type="text" name="search"
                       style="border:none;outline:none;font-size:.81rem;color:#0f172a;width:200px;background:transparent;height:100%;padding-right:12px;"
                       placeholder="Search by ID, donor name..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="status" onchange="this.form.submit()"
                    style="height:36px;border:1.5px solid #e2e8f0;border-radius:9px;padding:0 12px;font-size:.81rem;color:#475569;background:white;outline:none;cursor:pointer;">
                <option value="" <?= !$filter_status?'selected':'' ?>>All Status</option>
                <option value="pending_verification" <?= $filter_status==='pending_verification'?'selected':'' ?>>Pending</option>
                <option value="verified" <?= $filter_status==='verified'?'selected':'' ?>>Verified</option>
                <option value="rejected" <?= $filter_status==='rejected'?'selected':'' ?>>Rejected</option>
            </select>
            <button type="submit" style="height:36px;padding:0 16px;background:#1447c0;color:white;border:none;border-radius:9px;font-size:.8rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;transition:background .15s;white-space:nowrap;"
                    onmouseover="this.style.background='#0d2157'" onmouseout="this.style.background='#1447c0'">
                <i class="bi bi-funnel-fill" style="font-size:.78rem;"></i>Filter
            </button>
            <?php if($search||$filter_status): ?>
            <a href="<?= SITE_URL ?>/finance_staff/transactions.php"
               style="height:36px;padding:0 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.8rem;color:#64748b;background:white;display:flex;align-items:center;gap:4px;text-decoration:none;transition:all .15s;white-space:nowrap;"
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
                    <?php foreach(['TX ID','Donor','Campaign','Amount','Channel','Date','Status','Verified By','Action'] as $h): ?>
                    <th style="padding:10px 16px;text-align:left;font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;border-bottom:1px solid #f1f5f9;white-space:nowrap;"><?= $h ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if($transactions && $transactions->num_rows>0):
                $transactions->data_seek(0);
                while($t=$transactions->fetch_assoc()):
                    $s = $t['verification_status'];
                    [$sc,$sbg,$sbd,$sl,$sico] = match($s){
                        'verified' => ['#15803d','#dcfce7','#bbf7d0','Verified','bi-check-circle-fill'],
                        'rejected' => ['#991b1b','#fee2e2','#fecaca','Rejected','bi-x-circle-fill'],
                        default    => ['#854d0e','#fef9c3','#fde68a','Pending','bi-clock-fill'],
                    };
                    $ch    = $t['channel'] ?? '';
                    $ch_ic = $ch_icons[$ch] ?? 'bi-credit-card';
                    [$ch_bg,$ch_co] = $ch_colors[$ch] ?? ['#f1f5f9','#475569'];
            ?>
                <tr style="border-bottom:1px solid #f9fafb;transition:background .12s;"
                    onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">

                    <!-- TX ID -->
                    <td style="padding:13px 16px;">
                        <a href="<?= SITE_URL ?>/finance_staff/verify.php?id=<?= $t['id'] ?>"
                           style="font-family:ui-monospace,monospace;font-size:.77rem;font-weight:700;color:#1447c0;text-decoration:none;letter-spacing:.02em;">
                            TX-<?= str_pad($t['id'],4,'0',STR_PAD_LEFT) ?>
                        </a>
                    </td>

                    <!-- Donor -->
                    <td style="padding:13px 16px;min-width:150px;">
                        <div style="display:flex;align-items:center;gap:9px;">
                            <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1447c0);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;color:white;flex-shrink:0;">
                                <?= strtoupper(substr($t['donor_name'],0,1)) ?>
                            </div>
                            <div>
                                <div style="font-size:.79rem;font-weight:600;color:#0f172a;line-height:1.3;"><?= htmlspecialchars($t['donor_name']) ?></div>
                                <div style="font-size:.69rem;color:#94a3b8;"><?= htmlspecialchars($t['donor_email']) ?></div>
                            </div>
                        </div>
                    </td>

                    <!-- Campaign -->
                    <td style="padding:13px 16px;max-width:150px;">
                        <div style="font-size:.77rem;color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= htmlspecialchars($t['campaign_title'] ?? 'General Donation') ?>
                        </div>
                    </td>

                    <!-- Amount -->
                    <td style="padding:13px 16px;white-space:nowrap;">
                        <span style="font-size:.84rem;font-weight:800;color:#15803d;"><?= number_format($t['amount']) ?></span>
                        <span style="font-size:.69rem;color:#94a3b8;margin-left:2px;">VND</span>
                    </td>

                    <!-- Channel -->
                    <td style="padding:13px 16px;">
                        <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $ch_bg ?>;color:<?= $ch_co ?>;padding:4px 9px;border-radius:7px;font-size:.71rem;font-weight:700;white-space:nowrap;">
                            <i class="bi <?= $ch_ic ?>" style="font-size:.72rem;"></i>
                            <?= ucfirst(str_replace('_',' ',$ch)) ?>
                        </span>
                    </td>

                    <!-- Date -->
                    <td style="padding:13px 16px;font-size:.76rem;color:#64748b;white-space:nowrap;">
                        <?= formatDate($t['transaction_date'] ?: $t['donation_date']) ?>
                    </td>

                    <!-- Status -->
                    <td style="padding:13px 16px;">
                        <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $sbg ?>;color:<?= $sc ?>;border:1px solid <?= $sbd ?>;padding:4px 10px;border-radius:20px;font-size:.71rem;font-weight:700;white-space:nowrap;">
                            <i class="bi <?= $sico ?>" style="font-size:.68rem;"></i>
                            <?= $sl ?>
                        </span>
                    </td>

                    <!-- Verified By -->
                    <td style="padding:13px 16px;font-size:.76rem;color:#64748b;">
                        <?php if($t['verified_by_name']): ?>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <div style="width:22px;height:22px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:.62rem;font-weight:700;color:#475569;flex-shrink:0;">
                                <?= strtoupper(substr($t['verified_by_name'],0,1)) ?>
                            </div>
                            <span style="font-size:.74rem;"><?= htmlspecialchars(explode(' ',$t['verified_by_name'])[0]) ?></span>
                        </div>
                        <?php else: ?>
                        <span style="color:#e2e8f0;font-size:.8rem;">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Action -->
                    <td style="padding:13px 16px;">
                        <a href="<?= SITE_URL ?>/finance_staff/verify.php?id=<?= $t['id'] ?>"
                           style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;font-size:.75rem;font-weight:600;transition:all .15s;text-decoration:none;white-space:nowrap;
                           <?= $s==='pending_verification'
                               ? 'background:linear-gradient(135deg,#1447c0,#0d2157);color:white;box-shadow:0 2px 8px rgba(20,71,192,.25);'
                               : 'background:#f8fafc;color:#475569;border:1px solid #e2e8f0;' ?>"
                           onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                            <?php if($s==='pending_verification'): ?>
                                <i class="bi bi-shield-check" style="font-size:.8rem;"></i>Verify
                            <?php else: ?>
                                <i class="bi bi-eye" style="font-size:.78rem;"></i>View Details
                            <?php endif; ?>
                        </a>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr>
                    <td colspan="9" style="padding:52px 20px;text-align:center;">
                        <i class="bi bi-inbox" style="font-size:2.5rem;color:#e2e8f0;display:block;margin-bottom:12px;"></i>
                        <div style="font-size:.84rem;font-weight:600;color:#94a3b8;margin-bottom:4px;">No transactions found</div>
                        <div style="font-size:.76rem;color:#cbd5e1;">Try adjusting your search or filter criteria</div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Footer -->
    <div style="padding:12px 20px;border-top:1px solid #f8fafc;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;background:#fafafa;">
        <span style="font-size:.74rem;color:#94a3b8;">
            Showing <strong style="color:#475569;"><?= $total_rows ?></strong> of <strong style="color:#475569;"><?= $total_all ?></strong> transactions
            <?php if($search): ?> · Search: "<strong style="color:#1447c0;"><?= htmlspecialchars($search) ?></strong>"<?php endif; ?>
            <?php if($filter_status): ?> · Filter: <strong style="color:#1447c0;"><?= ucfirst(str_replace('_',' ',$filter_status)) ?></strong><?php endif; ?>
        </span>
        <?php if($search||$filter_status): ?>
        <a href="<?= SITE_URL ?>/finance_staff/transactions.php"
           style="font-size:.74rem;color:#1447c0;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:4px;">
            <i class="bi bi-x-circle" style="font-size:.78rem;"></i>Clear all filters
        </a>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
