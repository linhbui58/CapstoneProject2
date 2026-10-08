<?php
$pageTitle  = 'Fund Allocation';
$activePage = 'fund_allocation';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('campaign_staff');

$db  = getDB();
$uid = $user['id'];
$filter_campaign = (int)($_GET['campaign_id'] ?? 0);
$errors = [];

// Handle new allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campaign_id     = (int)($_POST['campaign_id'] ?? 0);
    $amount          = (float)($_POST['amount'] ?? 0);
    $purpose         = sanitize($_POST['purpose'] ?? '');
    $description     = sanitize($_POST['description'] ?? '');
    $allocation_date = sanitize($_POST['allocation_date'] ?? '');
    $status          = sanitize($_POST['status'] ?? 'planned');

    if (!$campaign_id)       $errors[] = 'Please select a campaign.';
    if ($amount <= 0)        $errors[] = 'Amount must be greater than 0.';
    if (empty($purpose))     $errors[] = 'Purpose is required.';
    if (empty($allocation_date)) $errors[] = 'Allocation date is required.';

    if (empty($errors)) {
        $camp = $db->query("SELECT raised_amount FROM campaigns WHERE id=$campaign_id")->fetch_assoc();
        $already = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM fund_allocations WHERE campaign_id=$campaign_id AND status!='completed'")->fetch_assoc()['t'];
        $available = ($camp['raised_amount']??0) - $already;
        if ($amount > $available) {
            $errors[] = 'Amount exceeds available funds ('.formatCurrency($available).').';
        }
    }

    if (empty($errors)) {
        $pe=$db->real_escape_string($purpose);
        $de=$db->real_escape_string($description);
        $se=$db->real_escape_string($status);
        $db->query("INSERT INTO fund_allocations (campaign_id,amount,purpose,description,allocated_by,allocation_date,status) VALUES ($campaign_id,$amount,'$pe','$de',$uid,'$allocation_date','$se')");
        setFlash('success','Fund allocation recorded!');
        redirect(SITE_URL.'/campaign_staff/fund_allocation.php?campaign_id='.$campaign_id);
    }
}

// Campaigns with funds
$campaigns = $db->query("SELECT id,title,raised_amount,target_amount FROM campaigns WHERE raised_amount>0 ORDER BY title");

// Allocations
$where = $filter_campaign ? "WHERE fa.campaign_id=$filter_campaign" : '';
$allocations = $db->query("
    SELECT fa.*,c.title as campaign_title,u.name as allocated_by_name
    FROM fund_allocations fa
    JOIN campaigns c ON fa.campaign_id=c.id
    JOIN users u ON fa.allocated_by=u.id
    $where
    ORDER BY fa.created_at DESC
");

// Summary for selected campaign
$summary = null;
if ($filter_campaign) {
    $ci = $db->query("SELECT raised_amount,title FROM campaigns WHERE id=$filter_campaign")->fetch_assoc();
    $allocated = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM fund_allocations WHERE campaign_id=$filter_campaign")->fetch_assoc()['t'];
    $summary = array(
        'raised'    => (float)($ci['raised_amount']??0),
        'allocated' => (float)$allocated,
        'available' => (float)($ci['raised_amount']??0) - (float)$allocated,
        'title'     => $ci['title']??'',
    );
}

$status_styles = array(
    'planned'   => array('bg'=>'#eff6ff','color'=>'#1447c0','border'=>'#bfdbfe','label'=>'Planned'),
    'disbursed' => array('bg'=>'#fef9c3','color'=>'#854d0e','border'=>'#fde68a','label'=>'Disbursed'),
    'completed' => array('bg'=>'#dcfce7','color'=>'#15803d','border'=>'#bbf7d0','label'=>'Completed'),
);
?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-pie-chart-fill" style="color:#d97706;font-size:1rem;"></i>Fund Allocation
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">Track how raised funds are allocated and used</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start;">

<!-- ═══ LEFT: FORM ═══════════════════════════════ -->
<div style="display:flex;flex-direction:column;gap:14px;position:sticky;top:80px;">

    <?php if($errors): ?>
    <div style="background:#fff1f2;border-left:3px solid #ef4444;padding:11px 14px;border-radius:10px;">
        <div style="font-weight:700;color:#b91c1c;font-size:.79rem;margin-bottom:4px;"><i class="bi bi-exclamation-circle-fill me-1"></i>Fix errors:</div>
        <ul style="margin:0;padding-left:16px;font-size:.77rem;color:#b91c1c;"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <!-- Campaign summary card -->
    <?php if($summary): ?>
    <div style="background:linear-gradient(135deg,#fffbeb,#fef9c3);border:1px solid #fde68a;border-radius:14px;padding:16px 18px;">
        <div style="font-size:.76rem;font-weight:700;color:#854d0e;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-bar-chart-fill"></i><?= htmlspecialchars(substr($summary['title'],0,30)) ?>...
        </div>
        <?php foreach([
            ['Raised',    formatCurrency($summary['raised']),    '#15803d'],
            ['Allocated', formatCurrency($summary['allocated']), '#d97706'],
            ['Available', formatCurrency($summary['available']), $summary['available']>0?'#1447c0':'#991b1b'],
        ] as list($l,$v,$c)): ?>
        <div style="display:flex;justify-content:space-between;margin-bottom:7px;font-size:.77rem;">
            <span style="color:#92400e;font-weight:500;"><?= $l ?></span>
            <span style="font-weight:800;color:<?= $c ?>;"><?= $v ?></span>
        </div>
        <?php endforeach; ?>
        <!-- Available bar -->
        <?php $avail_pct = $summary['raised']>0 ? min(100,round(($summary['available']/$summary['raised'])*100,1)) : 0; ?>
        <div style="height:6px;background:rgba(0,0,0,.08);border-radius:3px;overflow:hidden;margin-top:6px;">
            <div style="height:100%;width:<?= $avail_pct ?>%;background:linear-gradient(90deg,#3b82f6,#1447c0);border-radius:3px;"></div>
        </div>
        <div style="font-size:.68rem;color:#92400e;margin-top:3px;"><?= $avail_pct ?>% available for allocation</div>
    </div>
    <?php endif; ?>

    <!-- New allocation form -->
    <form method="POST" style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
        <div style="padding:13px 18px;border-bottom:1px solid #f1f5f9;background:linear-gradient(135deg,#fffbeb,#fef9c3);display:flex;align-items:center;gap:8px;">
            <i class="bi bi-plus-circle-fill" style="color:#d97706;font-size:.88rem;"></i>
            <span style="font-size:.84rem;font-weight:700;color:#854d0e;">New Allocation</span>
        </div>
        <div style="padding:16px;">
            <!-- Campaign -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.73rem;font-weight:600;color:#374151;margin-bottom:4px;">Campaign <span style="color:#ef4444;">*</span></label>
                <select name="campaign_id"
                        style="width:100%;height:38px;padding:0 11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;background:white;outline:none;transition:border-color .15s;cursor:pointer;"
                        onfocus="this.style.borderColor='#d97706'" onblur="this.style.borderColor='#e2e8f0'">
                    <option value="">Select campaign...</option>
                    <?php if($campaigns): $campaigns->data_seek(0); while($c=$campaigns->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= ($filter_campaign==$c['id']||($_POST['campaign_id']??0)==$c['id'])?'selected':'' ?>>
                        <?= htmlspecialchars(substr($c['title'],0,30)) ?>... (<?= number_format($c['raised_amount']/1000000,1) ?>M)
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <!-- Purpose -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.73rem;font-weight:600;color:#374151;margin-bottom:4px;">Purpose <span style="color:#ef4444;">*</span></label>
                <input type="text" name="purpose"
                       style="width:100%;height:38px;padding:0 11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#d97706'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="e.g. School supplies"
                       value="<?= htmlspecialchars($_POST['purpose']??'') ?>">
            </div>
            <!-- Amount -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.73rem;font-weight:600;color:#374151;margin-bottom:4px;">Amount (VND) <span style="color:#ef4444;">*</span></label>
                <input type="number" name="amount" min="1"
                       style="width:100%;height:38px;padding:0 11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#d97706'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="0"
                       value="<?= htmlspecialchars($_POST['amount']??'') ?>">
            </div>
            <!-- Description -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.73rem;font-weight:600;color:#374151;margin-bottom:4px;">Description</label>
                <textarea name="description" rows="2"
                          style="width:100%;padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.81rem;color:#0f172a;outline:none;transition:border-color .15s;resize:none;"
                          onfocus="this.style.borderColor='#d97706'" onblur="this.style.borderColor='#e2e8f0'"
                          placeholder="Details..."><?= htmlspecialchars($_POST['description']??'') ?></textarea>
            </div>
            <!-- Date + Status -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                <div>
                    <label style="display:block;font-size:.73rem;font-weight:600;color:#374151;margin-bottom:4px;">Date <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="allocation_date"
                           style="width:100%;height:38px;padding:0 11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.81rem;color:#0f172a;outline:none;"
                           value="<?= htmlspecialchars($_POST['allocation_date']??date('Y-m-d')) ?>">
                </div>
                <div>
                    <label style="display:block;font-size:.73rem;font-weight:600;color:#374151;margin-bottom:4px;">Status</label>
                    <select name="status"
                            style="width:100%;height:38px;padding:0 11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.81rem;color:#0f172a;background:white;outline:none;cursor:pointer;">
                        <option value="planned">Planned</option>
                        <option value="disbursed">Disbursed</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>
            <button type="submit"
                    style="width:100%;padding:10px;background:linear-gradient(135deg,#d97706,#b45309);color:white;border:none;border-radius:10px;font-weight:700;font-size:.84rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;box-shadow:0 3px 10px rgba(217,119,6,.3);transition:all .18s;"
                    onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                <i class="bi bi-save-fill"></i>Save Allocation
            </button>
        </div>
    </form>
</div>

<!-- ═══ RIGHT: RECORDS TABLE ═════════════════════ -->
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;">
    <div style="padding:13px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="bi bi-list-ul" style="color:#d97706;font-size:.88rem;"></i>
            <span style="font-size:.86rem;font-weight:700;color:#0f172a;">Allocation Records</span>
        </div>
        <!-- Campaign filter -->
        <form method="GET" style="display:flex;align-items:center;gap:7px;">
            <select name="campaign_id" onchange="this.form.submit()"
                    style="height:34px;border:1.5px solid #e2e8f0;border-radius:8px;padding:0 11px;font-size:.79rem;color:#475569;background:white;outline:none;cursor:pointer;max-width:200px;">
                <option value="">All Campaigns</option>
                <?php if($campaigns): $campaigns->data_seek(0); while($c=$campaigns->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>" <?= $filter_campaign==$c['id']?'selected':'' ?>>
                    <?= htmlspecialchars(substr($c['title'],0,25)) ?>...
                </option>
                <?php endwhile; endif; ?>
            </select>
            <?php if($filter_campaign): ?>
            <a href="<?= SITE_URL ?>/campaign_staff/fund_allocation.php"
               style="height:34px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.79rem;color:#64748b;background:white;display:flex;align-items:center;gap:4px;text-decoration:none;"
               onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                <i class="bi bi-x-lg" style="font-size:.7rem;"></i>Clear
            </a>
            <?php endif; ?>
        </form>
    </div>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc;">
                    <?php foreach(['Campaign','Purpose','Amount','Date','Status','By'] as $h): ?>
                    <th style="padding:9px 16px;text-align:left;font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;border-bottom:1px solid #f1f5f9;white-space:nowrap;"><?= $h ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if($allocations && $allocations->num_rows > 0): while($a=$allocations->fetch_assoc()):
                $ss = isset($status_styles[$a['status']]) ? $status_styles[$a['status']] : $status_styles['planned'];
            ?>
            <tr style="border-bottom:1px solid #f9fafb;transition:background .12s;"
                onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">
                <td style="padding:11px 16px;max-width:140px;">
                    <div style="font-size:.78rem;font-weight:600;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($a['campaign_title']) ?></div>
                </td>
                <td style="padding:11px 16px;min-width:140px;">
                    <div style="font-size:.79rem;font-weight:600;color:#0f172a;margin-bottom:2px;"><?= htmlspecialchars($a['purpose']) ?></div>
                    <?php if($a['description']): ?>
                    <div style="font-size:.7rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:180px;"><?= htmlspecialchars($a['description']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="padding:11px 16px;white-space:nowrap;">
                    <span style="font-size:.84rem;font-weight:800;color:#d97706;"><?= number_format($a['amount']/1000000,1) ?>M</span>
                    <span style="font-size:.67rem;color:#94a3b8;"> VND</span>
                </td>
                <td style="padding:11px 16px;font-size:.76rem;color:#64748b;white-space:nowrap;"><?= formatDate($a['allocation_date']) ?></td>
                <td style="padding:11px 16px;">
                    <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $ss['bg'] ?>;color:<?= $ss['color'] ?>;border:1px solid <?= $ss['border'] ?>;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;white-space:nowrap;">
                        <?= $ss['label'] ?>
                    </span>
                </td>
                <td style="padding:11px 16px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <div style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#6d28d9);display:flex;align-items:center;justify-content:center;font-size:.62rem;font-weight:800;color:white;flex-shrink:0;">
                            <?= strtoupper(substr($a['allocated_by_name'],0,1)) ?>
                        </div>
                        <span style="font-size:.74rem;color:#475569;white-space:nowrap;"><?= htmlspecialchars(explode(' ',$a['allocated_by_name'])[0]) ?></span>
                    </div>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
                <td colspan="6" style="padding:48px 20px;text-align:center;">
                    <i class="bi bi-inbox" style="font-size:2.2rem;color:#e2e8f0;display:block;margin-bottom:10px;"></i>
                    <div style="font-size:.82rem;font-weight:600;color:#94a3b8;margin-bottom:3px;">No allocations yet</div>
                    <div style="font-size:.75rem;color:#cbd5e1;">Record your first fund allocation using the form</div>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Footer -->
    <div style="padding:10px 18px;border-top:1px solid #f8fafc;background:#fafafa;">
        <span style="font-size:.72rem;color:#94a3b8;">
            <?= $allocations ? $allocations->num_rows : 0 ?> allocation record<?= ($allocations&&$allocations->num_rows!=1)?'s':'' ?>
            <?php if($filter_campaign): ?> · Filtered by campaign<?php endif; ?>
        </span>
    </div>
</div>

</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
