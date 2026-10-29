<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('finance_staff');
$db = getApplicationDB();

$pending  = (int)$db->query("SELECT COUNT(*) as t FROM transactions WHERE verification_status='pending_verification'")->fetch_assoc()['t'];
$verified = (int)$db->query("SELECT COUNT(*) as t FROM transactions WHERE verification_status='verified'")->fetch_assoc()['t'];
$rejected = (int)$db->query("SELECT COUNT(*) as t FROM transactions WHERE verification_status='rejected'")->fetch_assoc()['t'];
$total_verified_amt = $db->query("SELECT COALESCE(SUM(d.amount),0) as t FROM donations d WHERE d.status='verified'")->fetch_assoc()['t'];

$pending_list = $db->query("
    SELECT t.*,d.amount,d.channel,d.created_at as don_date,
           u.name as donor_name,u.email as donor_email,
           c.title as camp_title
    FROM transactions t
    JOIN donations d ON t.donation_id=d.id
    JOIN users u ON d.donor_id=u.id
    LEFT JOIN campaigns c ON d.campaign_id=c.id
    WHERE t.verification_status='pending_verification'
    ORDER BY t.created_at DESC LIMIT 6
");

$recent = $db->query("
    SELECT vh.*,t.reference_code,d.amount,
           donor.name as donor_name, staff.name as staff_name
    FROM verification_history vh
    JOIN transactions t ON vh.transaction_id=t.id
    JOIN donations d ON t.donation_id=d.id
    JOIN users donor ON d.donor_id=donor.id
    JOIN users staff ON vh.performed_by=staff.id
    ORDER BY vh.created_at DESC LIMIT 7
");

$ch_icons  = ['bank_transfer'=>'bi-bank2','e_wallet'=>'bi-phone-fill','credit_card'=>'bi-credit-card-fill','paypal'=>'bi-paypal','cash'=>'bi-cash-stack'];
$ch_labels = ['bank_transfer'=>'Bank Transfer','e_wallet'=>'E-Wallet','credit_card'=>'Credit Card','paypal'=>'PayPal','cash'=>'Cash'];
?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-grid-fill" style="color:#d97706;font-size:1rem;"></i>Finance Dashboard
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">Transaction verification and donation management</p>
    </div>
    <a href="<?= SITE_URL ?>/finance_staff/pending.php"
       style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:linear-gradient(135deg,#f59e0b,#d97706);color:white;border-radius:10px;font-weight:700;font-size:.82rem;box-shadow:0 3px 12px rgba(245,158,11,.3);text-decoration:none;white-space:nowrap;transition:all .18s;flex-shrink:0;"
       onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
        <i class="bi bi-shield-check"></i>Verify Transactions
        <?php if($pending>0): ?>
        <span style="background:rgba(0,0,0,.18);color:white;padding:1px 8px;border-radius:20px;font-size:.7rem;font-weight:800;"><?= $pending ?></span>
        <?php endif; ?>
    </a>
</div>

<!-- Stat cards -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;">
    <?php
    $stats = [
        [$pending,  'Pending Verification','bi-clock-fill',         '#fef9c3','#d97706','#fde68a', true],
        [$verified, 'Verified',            'bi-check-circle-fill',  '#dcfce7','#15803d','#bbf7d0', false],
        [$rejected, 'Rejected',            'bi-x-circle-fill',      '#fee2e2','#991b1b','#fecaca', false],
        [null,      'Total Verified Amt',  'bi-cash-stack',         '#dbeafe','#1447c0','#bfdbfe', false],
    ];
    foreach($stats as $i => [$val,$lbl,$ic,$bg,$color,$border,$is_pending]): ?>
    <div style="background:white;border:1px solid <?= $is_pending&&$pending>0?$border:'#e2e8f0' ?>;border-radius:14px;padding:18px 20px;box-shadow:0 2px 8px rgba(0,0,0,.04);<?= $is_pending&&$pending>0?'border-left:4px solid '.$color.';':'' ?>transition:all .2s;"
         onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.08)'"
         onmouseout="this.style.transform='none';this.style.boxShadow='0 2px 8px rgba(0,0,0,.04)'">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <div style="width:40px;height:40px;border-radius:11px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;">
                <i class="bi <?= $ic ?>" style="color:<?= $color ?>;font-size:1rem;"></i>
            </div>
            <?php if($is_pending && $pending>0): ?>
            <span style="background:<?= $bg ?>;color:<?= $color ?>;padding:2px 9px;border-radius:20px;font-size:.68rem;font-weight:800;border:1px solid <?= $border ?>;">Action needed</span>
            <?php endif; ?>
        </div>
        <?php if($i===3): ?>
        <div style="font-size:1.1rem;font-weight:800;color:#0f172a;letter-spacing:-.02em;line-height:1.1;margin-bottom:4px;"><?= number_format($total_verified_amt/1000000,1) ?>M VND</div>
        <?php else: ?>
        <div style="font-size:1.7rem;font-weight:800;color:#0f172a;letter-spacing:-.03em;line-height:1;margin-bottom:4px;"><?= $val ?></div>
        <?php endif; ?>
        <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;"><?= $lbl ?></div>
        <?php if($is_pending && $pending>0): ?>
        <a href="<?= SITE_URL ?>/finance_staff/pending.php"
           style="display:flex;align-items:center;justify-content:center;gap:5px;margin-top:10px;padding:7px;background:<?= $bg ?>;border:1px solid <?= $border ?>;color:<?= $color ?>;border-radius:8px;font-size:.76rem;font-weight:700;text-decoration:none;transition:all .15s;"
           onmouseover="this.style.filter='brightness(.95)'" onmouseout="this.style.filter='none'">
            <i class="bi bi-arrow-right-circle-fill" style="font-size:.78rem;"></i>Review Now
        </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Main content: pending + activity -->
<div style="display:grid;grid-template-columns:1fr 300px;gap:18px;">

    <!-- Left: Pending list -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04);">
        <div style="padding:14px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:8px;">
                <i class="bi bi-clock-fill" style="color:#d97706;font-size:.88rem;"></i>
                <span style="font-size:.86rem;font-weight:700;color:#0f172a;">Pending Verification</span>
                <?php if($pending>0): ?>
                <span style="background:#fef9c3;color:#854d0e;border:1px solid #fde68a;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:800;"><?= $pending ?></span>
                <?php endif; ?>
            </div>
            <a href="<?= SITE_URL ?>/finance_staff/pending.php"
               style="font-size:.78rem;font-weight:700;color:#d97706;text-decoration:none;display:flex;align-items:center;gap:4px;"
               onmouseover="this.style.color='#b45309'" onmouseout="this.style.color='#d97706'">
                View All <i class="bi bi-arrow-right" style="font-size:.75rem;"></i>
            </a>
        </div>

        <?php if($pending_list && $pending_list->num_rows > 0): ?>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <?php foreach(['Reference','Donor','Amount','Channel','Date','Action'] as $h): ?>
                        <th style="padding:9px 16px;text-align:left;font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;border-bottom:1px solid #f1f5f9;white-space:nowrap;"><?= $h ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php while($t=$pending_list->fetch_assoc()):
                    $ch = $t['channel']??'';
                    $ch_ic = $ch_icons[$ch]??'bi-credit-card';
                    $ch_lb = $ch_labels[$ch]??ucfirst(str_replace('_',' ',$ch));
                ?>
                <tr style="border-bottom:1px solid #f9fafb;transition:background .12s;"
                    onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background='white'">
                    <td style="padding:11px 16px;">
                        <a href="<?= SITE_URL ?>/finance_staff/verify.php?id=<?= $t['id'] ?>"
                           style="font-family:ui-monospace,monospace;font-size:.74rem;font-weight:700;color:#1447c0;text-decoration:none;">
                            <?= htmlspecialchars(substr($t['reference_code']??'—',0,14)) ?>
                        </a>
                    </td>
                    <td style="padding:11px 16px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:27px;height:27px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1447c0);display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800;color:white;flex-shrink:0;">
                                <?= strtoupper(substr($t['donor_name'],0,1)) ?>
                            </div>
                            <div>
                                <div style="font-size:.78rem;font-weight:600;color:#0f172a;"><?= htmlspecialchars($t['donor_name']) ?></div>
                                <div style="font-size:.68rem;color:#94a3b8;"><?= htmlspecialchars($t['donor_email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:11px 16px;white-space:nowrap;">
                        <span style="font-size:.82rem;font-weight:800;color:#15803d;"><?= number_format($t['amount']) ?></span>
                        <span style="font-size:.67rem;color:#94a3b8;margin-left:1px;">VND</span>
                    </td>
                    <td style="padding:11px 16px;">
                        <span style="display:inline-flex;align-items:center;gap:4px;background:#f1f5f9;color:#475569;padding:3px 8px;border-radius:6px;font-size:.7rem;font-weight:600;white-space:nowrap;">
                            <i class="bi <?= $ch_ic ?>" style="font-size:.68rem;"></i><?= $ch_lb ?>
                        </span>
                    </td>
                    <td style="padding:11px 16px;font-size:.74rem;color:#64748b;white-space:nowrap;">
                        <?= formatDate($t['created_at']) ?>
                    </td>
                    <td style="padding:11px 16px;">
                        <a href="<?= SITE_URL ?>/finance_staff/verify.php?id=<?= $t['id'] ?>"
                           style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;background:linear-gradient(135deg,#1447c0,#0d2157);color:white;border-radius:8px;font-size:.74rem;font-weight:700;text-decoration:none;box-shadow:0 2px 6px rgba(20,71,192,.25);transition:all .15s;white-space:nowrap;"
                           onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                            <i class="bi bi-shield-check" style="font-size:.78rem;"></i>Verify
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="padding:48px 20px;text-align:center;">
            <div style="width:60px;height:60px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                <i class="bi bi-check-all" style="font-size:1.6rem;color:#16a34a;"></i>
            </div>
            <div style="font-size:.88rem;font-weight:700;color:#0f172a;margin-bottom:4px;">All caught up!</div>
            <div style="font-size:.78rem;color:#64748b;">No transactions pending verification</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right: Recent activity -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04);">
        <div style="padding:14px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:8px;">
                <i class="bi bi-activity" style="color:#7c3aed;font-size:.88rem;"></i>
                <span style="font-size:.86rem;font-weight:700;color:#0f172a;">Recent Activity</span>
            </div>
            <a href="<?= SITE_URL ?>/finance_staff/history.php"
               style="font-size:.76rem;font-weight:600;color:#7c3aed;text-decoration:none;"
               onmouseover="this.style.color='#6d28d9'" onmouseout="this.style.color='#7c3aed'">History</a>
        </div>

        <div style="padding:8px 0;">
        <?php if($recent && $recent->num_rows>0): while($a=$recent->fetch_assoc()):
            $action = $a['action'];
            [$a_bg,$a_color,$a_ic] = match($action){
                'verified'  => ['#dcfce7','#15803d','bi-check-circle-fill'],
                'rejected'  => ['#fee2e2','#991b1b','bi-x-circle-fill'],
                default     => ['#fef9c3','#854d0e','bi-send-fill'],
            };
        ?>
        <div style="display:flex;gap:12px;padding:10px 16px;border-bottom:1px solid #f9fafb;transition:background .12s;"
             onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">
            <!-- icon -->
            <div style="width:34px;height:34px;border-radius:50%;background:<?= $a_bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">
                <i class="bi <?= $a_ic ?>" style="color:<?= $a_color ?>;font-size:.85rem;"></i>
            </div>
            <!-- content -->
            <div style="flex:1;min-width:0;">
                <div style="font-size:.78rem;font-weight:700;color:#0f172a;margin-bottom:1px;">
                    <?= ucfirst($action) ?>
                    <span style="font-weight:800;color:#15803d;">- <?= number_format($a['amount']) ?> VND</span>
                </div>
                <div style="font-size:.71rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?= htmlspecialchars($a['donor_name']) ?>
                </div>
                <div style="font-size:.68rem;color:#94a3b8;margin-top:2px;"><?= formatDateTime($a['created_at']) ?></div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div style="padding:32px 16px;text-align:center;color:#94a3b8;font-size:.8rem;">No activity yet</div>
        <?php endif; ?>
        </div>

        <div style="padding:10px 16px;border-top:1px solid #f1f5f9;background:#fafafa;">
            <a href="<?= SITE_URL ?>/finance_staff/history.php"
               style="display:flex;align-items:center;justify-content:center;gap:5px;padding:8px;border:1.5px solid #ede9fe;border-radius:8px;color:#7c3aed;font-size:.77rem;font-weight:700;text-decoration:none;background:white;transition:all .15s;"
               onmouseover="this.style.background='#faf5ff'" onmouseout="this.style.background='white'">
                <i class="bi bi-clock-history" style="font-size:.8rem;"></i>View Full History
            </a>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
