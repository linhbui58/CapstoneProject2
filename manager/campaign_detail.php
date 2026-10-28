<?php
$pageTitle  = 'Campaign Detail';
$activePage = 'campaigns';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('manager');

$db = getApplicationDB();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    setFlash('danger', 'Campaign not found.');
    redirect(SITE_URL . '/manager/campaigns.php');
}

// Campaign info
$campaign = $db->query("
    SELECT c.*, u.name as created_by_name
    FROM campaigns c
    JOIN users u ON c.created_by = u.id
    WHERE c.id = $id
    LIMIT 1
")->fetch_assoc();

if (!$campaign) {
    setFlash('danger', 'Campaign not found.');
    redirect(SITE_URL . '/manager/campaigns.php');
}

$pct = $campaign['target_amount'] > 0
    ? min(100, round(($campaign['raised_amount'] / $campaign['target_amount']) * 100, 1))
    : 0;

// Donations for this campaign
$donations = $db->query("
    SELECT d.*, u.name as donor_name, u.email as donor_email,
           t.reference_code, t.verification_status
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    LEFT JOIN transactions t ON t.donation_id = d.id
    WHERE d.campaign_id = $id
    ORDER BY d.created_at DESC
");

// Fund allocations
$allocations = $db->query("
    SELECT fa.*, u.name as allocated_by_name
    FROM fund_allocations fa
    JOIN users u ON fa.allocated_by = u.id
    WHERE fa.campaign_id = $id
    ORDER BY fa.allocation_date DESC
");

// Stats
$total_donations    = $db->query("SELECT COUNT(*) as c FROM donations WHERE campaign_id=$id")->fetch_assoc()['c'];
$verified_donations = $db->query("SELECT COUNT(*) as c FROM donations WHERE campaign_id=$id AND status='verified'")->fetch_assoc()['c'];
$total_donors       = $db->query("SELECT COUNT(DISTINCT donor_id) as c FROM donations WHERE campaign_id=$id AND status='verified'")->fetch_assoc()['c'];
$total_allocated    = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM fund_allocations WHERE campaign_id=$id")->fetch_assoc()['t'];

$status_styles = [
    'active'    => ['bg'=>'#dcfce7','color'=>'#15803d','border'=>'#bbf7d0','label'=>'Active'],
    'completed' => ['bg'=>'#dbeafe','color'=>'#1447c0','border'=>'#bfdbfe','label'=>'Completed'],
    'draft'     => ['bg'=>'#f1f5f9','color'=>'#475569','border'=>'#e2e8f0','label'=>'Draft'],
    'closed'    => ['bg'=>'#f1f5f9','color'=>'#94a3b8','border'=>'#e2e8f0','label'=>'Closed'],
];
$ss = $status_styles[$campaign['status']] ?? $status_styles['draft'];

$alloc_styles = [
    'planned'   => ['bg'=>'#eff6ff','color'=>'#1447c0','border'=>'#bfdbfe'],
    'disbursed' => ['bg'=>'#fef9c3','color'=>'#854d0e','border'=>'#fde68a'],
    'completed' => ['bg'=>'#dcfce7','color'=>'#15803d','border'=>'#bbf7d0'],
];
?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <a href="<?= SITE_URL ?>/manager/campaigns.php"
               style="font-size:.78rem;color:#64748b;text-decoration:none;display:flex;align-items:center;gap:4px;transition:color .14s;"
               onmouseover="this.style.color='#1447c0'" onmouseout="this.style.color='#64748b'">
                <i class="bi bi-arrow-left"></i>All Campaigns
            </a>
            <span style="color:#e2e8f0;">/</span>
            <span style="font-size:.78rem;color:#94a3b8;"><?= htmlspecialchars(substr($campaign['title'],0,40)) ?>...</span>
        </div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-megaphone-fill" style="color:#7c3aed;font-size:1rem;"></i>
            <?= htmlspecialchars($campaign['title']) ?>
        </h1>
    </div>
    <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $ss['bg'] ?>;color:<?= $ss['color'] ?>;border:1.5px solid <?= $ss['border'] ?>;padding:6px 14px;border-radius:20px;font-size:.8rem;font-weight:700;flex-shrink:0;">
        <?= $ss['label'] ?>
    </span>
</div>

<!-- Top section: image + stats -->
<div style="display:grid;grid-template-columns:340px 1fr;gap:18px;margin-bottom:20px;">

    <!-- Image -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);">
        <?php if($campaign['image_url']): ?>
        <img src="<?= htmlspecialchars(campaignImageUrl($campaign['image_url']), ENT_QUOTES, 'UTF-8') ?>"
             alt="<?= htmlspecialchars($campaign['title']) ?>"
             style="width:100%;height:200px;object-fit:cover;display:block;"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div style="display:none;width:100%;height:200px;background:linear-gradient(135deg,#1e3a8a,#3b82f6);align-items:center;justify-content:center;">
            <i class="bi bi-megaphone-fill" style="font-size:3rem;color:rgba(255,255,255,.25);"></i>
        </div>
        <?php else: ?>
        <div style="width:100%;height:200px;background:linear-gradient(135deg,#1e3a8a,#3b82f6);display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-megaphone-fill" style="font-size:3rem;color:rgba(255,255,255,.25);"></i>
        </div>
        <?php endif; ?>
        <div style="padding:14px 16px;">
            <div style="font-size:.76rem;color:#64748b;line-height:1.65;"><?= nl2br(htmlspecialchars($campaign['description'])) ?></div>
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid #f1f5f9;">
                <div style="display:flex;justify-content:space-between;font-size:.73rem;margin-bottom:4px;color:#64748b;">
                    <span>Created by</span>
                    <span style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($campaign['created_by_name']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.73rem;margin-bottom:4px;color:#64748b;">
                    <span>Start date</span>
                    <span style="font-weight:600;color:#0f172a;"><?= formatDate($campaign['start_date']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.73rem;color:#64748b;">
                    <span>End date</span>
                    <span style="font-weight:600;color:#0f172a;"><?= formatDate($campaign['end_date']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div style="display:flex;flex-direction:column;gap:14px;">

        <!-- Progress card -->
        <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;padding:20px 22px;box-shadow:0 2px 8px rgba(0,0,0,.05);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px;">
                <div>
                    <div style="font-size:2rem;font-weight:800;color:<?= $pct>=100?'#15803d':'#1447c0' ?>;letter-spacing:-.04em;line-height:1;"><?= number_format($campaign['raised_amount']/1000000,1) ?>M</div>
                    <div style="font-size:.72rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-top:2px;">VND Raised</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:1.1rem;font-weight:800;color:#64748b;letter-spacing:-.02em;"><?= number_format($campaign['target_amount']/1000000,0) ?>M</div>
                    <div style="font-size:.72rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-top:2px;">Target</div>
                </div>
            </div>
            <div style="height:10px;background:#f1f5f9;border-radius:5px;overflow:hidden;margin-bottom:6px;">
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct>=100?'linear-gradient(90deg,#22c55e,#16a34a)':'linear-gradient(90deg,#3b82f6,#1447c0)' ?>;border-radius:5px;transition:width .6s;"></div>
            </div>
            <div style="font-size:.8rem;font-weight:700;color:<?= $pct>=100?'#15803d':'#1447c0' ?>;"><?= $pct ?>% of goal reached</div>
        </div>

        <!-- Mini stats grid -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
            <?php
            $mini = [
                [$total_donations,               'Total Donations',   'bi-receipt',           '#dbeafe','#1447c0'],
                [$verified_donations,            'Verified',          'bi-check-circle-fill', '#dcfce7','#15803d'],
                [$total_donors,                  'Unique Donors',     'bi-people-fill',       '#ede9fe','#7c3aed'],
                [number_format($total_allocated/1000000,1).'M','Allocated (VND)','bi-pie-chart-fill','#fef9c3','#d97706'],
            ];
            foreach($mini as list($val,$lbl,$ic,$bg,$color)): ?>
            <div style="background:white;border:1px solid #e2e8f0;border-radius:12px;padding:14px;box-shadow:0 1px 4px rgba(0,0,0,.04);">
                <div style="width:34px;height:34px;border-radius:9px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;margin-bottom:8px;">
                    <i class="bi <?= $ic ?>" style="color:<?= $color ?>;font-size:.88rem;"></i>
                </div>
                <div style="font-size:1.1rem;font-weight:800;color:#0f172a;letter-spacing:-.02em;line-height:1.1;"><?= $val ?></div>
                <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-top:3px;"><?= $lbl ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Donations + Fund Allocations -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">

    <!-- Donations table -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);">
        <div style="padding:13px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:8px;">
                <i class="bi bi-cash-stack" style="color:#15803d;font-size:.88rem;"></i>
                <span style="font-size:.86rem;font-weight:700;color:#0f172a;">Donations</span>
                <span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:700;"><?= $total_donations ?></span>
            </div>
        </div>
        <div style="overflow-x:auto;max-height:350px;overflow-y:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead style="position:sticky;top:0;z-index:1;">
                    <tr style="background:#f8fafc;">
                        <?php foreach(['Donor','Amount','Channel','Status','Date'] as $h): ?>
                        <th style="padding:9px 14px;text-align:left;font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;border-bottom:1px solid #f1f5f9;white-space:nowrap;"><?= $h ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php
                $ch_labels = ['bank_transfer'=>'Bank Transfer','e_wallet'=>'E-Wallet','credit_card'=>'Credit Card','paypal'=>'PayPal','cash'=>'Cash'];
                if($donations && $donations->num_rows > 0):
                    while($d = $donations->fetch_assoc()):
                        $vs = $d['verification_status'] ?? $d['status'];
                        [$vs_bg,$vs_co] = match($vs){
                            'verified'             => ['#dcfce7','#15803d'],
                            'rejected'             => ['#fee2e2','#991b1b'],
                            'pending_verification' => ['#fef9c3','#854d0e'],
                            default                => ['#fef9c3','#854d0e'],
                        };
                ?>
                <tr style="border-bottom:1px solid #f9fafb;" onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">
                    <td style="padding:10px 14px;">
                        <div style="display:flex;align-items:center;gap:7px;">
                            <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1447c0);display:flex;align-items:center;justify-content:center;font-size:.64rem;font-weight:800;color:white;flex-shrink:0;">
                                <?= strtoupper(substr($d['is_anonymous']?'A':$d['donor_name'],0,1)) ?>
                            </div>
                            <div>
                                <div style="font-size:.77rem;font-weight:600;color:#0f172a;"><?= htmlspecialchars($d['is_anonymous']?'Anonymous':$d['donor_name']) ?></div>
                                <?php if(!$d['is_anonymous']): ?>
                                <div style="font-size:.68rem;color:#94a3b8;"><?= htmlspecialchars($d['donor_email']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="padding:10px 14px;white-space:nowrap;">
                        <span style="font-size:.82rem;font-weight:800;color:#15803d;"><?= number_format($d['amount']/1000000,1) ?>M</span>
                        <span style="font-size:.65rem;color:#94a3b8;"> VND</span>
                    </td>
                    <td style="padding:10px 14px;font-size:.74rem;color:#475569;white-space:nowrap;">
                        <?= $ch_labels[$d['channel']] ?? ucfirst($d['channel']) ?>
                    </td>
                    <td style="padding:10px 14px;">
                        <span style="background:<?= $vs_bg ?>;color:<?= $vs_co ?>;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:700;white-space:nowrap;">
                            <?= ucfirst(str_replace('_',' ',$vs)) ?>
                        </span>
                    </td>
                    <td style="padding:10px 14px;font-size:.72rem;color:#94a3b8;white-space:nowrap;"><?= formatDate($d['created_at']) ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="5" style="padding:32px;text-align:center;color:#94a3b8;font-size:.79rem;">No donations yet</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Fund Allocations -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);">
        <div style="padding:13px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:8px;">
                <i class="bi bi-pie-chart-fill" style="color:#d97706;font-size:.88rem;"></i>
                <span style="font-size:.86rem;font-weight:700;color:#0f172a;">Fund Allocations</span>
            </div>
        </div>
        <div style="overflow-x:auto;max-height:350px;overflow-y:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead style="position:sticky;top:0;z-index:1;">
                    <tr style="background:#f8fafc;">
                        <?php foreach(['Purpose','Amount','Date','Status','Evidence'] as $h): ?>
                        <th style="padding:9px 14px;text-align:left;font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;border-bottom:1px solid #f1f5f9;white-space:nowrap;"><?= $h ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if($allocations && $allocations->num_rows > 0):
                    while($a = $allocations->fetch_assoc()):
                        $as = $alloc_styles[$a['status']] ?? $alloc_styles['planned'];
                        $hasEv = !empty($a['evidence_url']);
                        $isPdf = $hasEv && strtolower(pathinfo($a['evidence_url'], PATHINFO_EXTENSION)) === 'pdf';
                ?>
                <tr style="border-bottom:1px solid #f9fafb;" onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">
                    <td style="padding:10px 14px;min-width:120px;">
                        <div style="font-size:.79rem;font-weight:600;color:#0f172a;"><?= htmlspecialchars($a['purpose']) ?></div>
                        <?php if($a['description']): ?>
                        <div style="font-size:.68rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;"><?= htmlspecialchars($a['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding:10px 14px;white-space:nowrap;">
                        <span style="font-size:.82rem;font-weight:800;color:#d97706;"><?= number_format($a['amount']/1000000,1) ?>M</span>
                        <span style="font-size:.65rem;color:#94a3b8;"> VND</span>
                    </td>
                    <td style="padding:10px 14px;font-size:.73rem;color:#64748b;white-space:nowrap;"><?= formatDate($a['allocation_date']) ?></td>
                    <td style="padding:10px 14px;">
                        <span style="background:<?= $as['bg'] ?>;color:<?= $as['color'] ?>;border:1px solid <?= $as['border'] ?>;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:700;white-space:nowrap;">
                            <?= ucfirst($a['status']) ?>
                        </span>
                    </td>
                    <td style="padding:10px 14px;min-width:100px;">
                        <?php if ($hasEv): ?>
                            <?php if ($isPdf): ?>
                            <a href="<?= SITE_URL . '/' . htmlspecialchars($a['evidence_url']) ?>" target="_blank"
                               style="display:inline-flex;align-items:center;gap:5px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:4px 10px;border-radius:7px;font-size:.69rem;font-weight:700;text-decoration:none;">
                                <i class="bi bi-file-pdf-fill"></i>PDF
                            </a>
                            <?php else: ?>
                            <a href="<?= SITE_URL . '/' . htmlspecialchars($a['evidence_url']) ?>" target="_blank">
                                <img src="<?= SITE_URL . '/' . htmlspecialchars($a['evidence_url']) ?>"
                                     style="width:48px;height:36px;object-fit:cover;border-radius:6px;border:1.5px solid #e2e8f0;cursor:zoom-in;"
                                     title="<?= htmlspecialchars($a['evidence_note'] ?? '') ?>"
                                     alt="Evidence">
                            </a>
                            <?php endif; ?>
                            <?php if ($a['evidence_note']): ?>
                            <div style="font-size:.65rem;color:#94a3b8;margin-top:2px;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                 title="<?= htmlspecialchars($a['evidence_note']) ?>">
                                <?= htmlspecialchars($a['evidence_note']) ?>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="font-size:.69rem;color:#cbd5e1;display:flex;align-items:center;gap:3px;">
                                <i class="bi bi-dash-circle"></i>None
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="5" style="padding:32px;text-align:center;color:#94a3b8;font-size:.79rem;">No allocations recorded</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
