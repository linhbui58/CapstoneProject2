<?php
$pageTitle  = 'Pending Verification';
$activePage = 'verify';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('finance_staff');

$db = getApplicationDB();

$pending = $db->query("
    SELECT t.*, d.amount, d.channel, d.message, d.is_anonymous,
           d.created_at as donation_date, d.id as donation_id,
           u.name as donor_name, u.email as donor_email, u.phone as donor_phone,
           c.title as campaign_title
    FROM transactions t
    JOIN donations d ON t.donation_id = d.id
    JOIN users u ON d.donor_id = u.id
    LEFT JOIN campaigns c ON d.campaign_id = c.id
    WHERE t.verification_status = 'pending_verification'
    ORDER BY t.created_at DESC
");
$count = $pending ? $pending->num_rows : 0;

$ch_icons  = ['bank_transfer'=>'bi-bank2','e_wallet'=>'bi-phone-fill','credit_card'=>'bi-credit-card-fill','paypal'=>'bi-paypal','cash'=>'bi-cash-stack'];
$ch_labels = ['bank_transfer'=>'Bank Transfer','e_wallet'=>'E-Wallet','credit_card'=>'Credit Card','paypal'=>'PayPal','cash'=>'Cash'];
$ch_colors = ['bank_transfer'=>['#dbeafe','#1447c0'],'e_wallet'=>['#dcfce7','#15803d'],'credit_card'=>['#ede9fe','#7c3aed'],'paypal'=>['#fff7ed','#c2410c'],'cash'=>['#fef9c3','#854d0e']];
?>

<!-- Page header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;gap:16px;">
    <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
            <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0;">Pending Verification</h1>
            <?php if($count > 0): ?>
            <span style="background:linear-gradient(135deg,#f59e0b,#d97706);color:white;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:800;box-shadow:0 2px 8px rgba(245,158,11,.3);">
                <?= $count ?> pending
            </span>
            <?php endif; ?>
        </div>
        <p style="color:#64748b;font-size:.79rem;margin:0;">Review and verify incoming donation transactions</p>
    </div>
    <a href="<?= SITE_URL ?>/finance_staff/transactions.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:9px;color:#64748b;font-size:.8rem;font-weight:600;background:white;text-decoration:none;white-space:nowrap;transition:all .15s;"
       onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
        <i class="bi bi-list-check"></i>All Transactions
    </a>
</div>

<?php if($count === 0): ?>
<!-- Empty state -->
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;padding:64px 20px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.04);">
    <div style="width:72px;height:72px;background:linear-gradient(135deg,#dcfce7,#bbf7d0);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
        <i class="bi bi-check-all" style="font-size:1.8rem;color:#16a34a;"></i>
    </div>
    <div style="font-size:1.05rem;font-weight:700;color:#0f172a;margin-bottom:6px;">All caught up!</div>
    <div style="font-size:.82rem;color:#64748b;margin-bottom:20px;">No transactions are currently pending verification.</div>
    <a href="<?= SITE_URL ?>/finance_staff/transactions.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:9px 20px;background:#eff6ff;border:1.5px solid #dbeafe;color:#1447c0;border-radius:9px;font-size:.82rem;font-weight:700;text-decoration:none;">
        <i class="bi bi-list-check"></i>View All Transactions
    </a>
</div>

<?php else: ?>
<!-- Cards grid -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:18px;">
    <?php while($t = $pending->fetch_assoc()):
        $ch    = $t['channel'] ?? '';
        $ch_ic = $ch_icons[$ch]  ?? 'bi-credit-card';
        $ch_lb = $ch_labels[$ch] ?? ucfirst(str_replace('_',' ',$ch));
        [$ch_bg,$ch_co] = $ch_colors[$ch] ?? ['#f1f5f9','#475569'];
        $initials = strtoupper(substr($t['is_anonymous']?'A':$t['donor_name'],0,1));
    ?>
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.05);transition:all .2s;"
         onmouseover="this.style.boxShadow='0 8px 28px rgba(0,0,0,.1)';this.style.transform='translateY(-2px)'"
         onmouseout="this.style.boxShadow='0 2px 10px rgba(0,0,0,.05)';this.style.transform='none'">

        <!-- Card header -->
        <div style="padding:12px 16px;background:linear-gradient(135deg,#fffbeb,#fef9c3);border-bottom:1px solid #fde68a;display:flex;align-items:center;justify-content:space-between;">
            <div style="font-family:ui-monospace,monospace;font-size:.72rem;font-weight:700;color:#854d0e;letter-spacing:.02em;">
                <?= htmlspecialchars($t['reference_code'] ?? 'REF-'.str_pad($t['id'],4,'0',STR_PAD_LEFT)) ?>
            </div>
            <span style="background:#f59e0b;color:white;padding:2px 9px;border-radius:20px;font-size:.66rem;font-weight:800;letter-spacing:.03em;">PENDING</span>
        </div>

        <!-- Donor info -->
        <div style="padding:16px 16px 14px;">
            <div style="display:flex;align-items:center;gap:11px;margin-bottom:14px;">
                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1447c0);display:flex;align-items:center;justify-content:center;font-size:.88rem;font-weight:800;color:white;flex-shrink:0;box-shadow:0 3px 8px rgba(20,71,192,.2);">
                    <?= $initials ?>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:.84rem;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= htmlspecialchars($t['is_anonymous']?'Anonymous Donor':$t['donor_name']) ?>
                    </div>
                    <div style="font-size:.71rem;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= htmlspecialchars($t['donor_email']) ?>
                    </div>
                </div>
            </div>

            <!-- Amount highlight -->
            <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #bbf7d0;border-radius:10px;padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:.72rem;color:#15803d;font-weight:600;">Donation Amount</span>
                <span style="font-size:1.05rem;font-weight:800;color:#15803d;letter-spacing:-.02em;"><?= number_format($t['amount']) ?> VND</span>
            </div>

            <!-- Details grid -->
            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px;">
                <?php
                $rows = [
                    ['Channel',  '<span style="display:inline-flex;align-items:center;gap:5px;background:'.$ch_bg.';color:'.$ch_co.';padding:3px 9px;border-radius:6px;font-size:.71rem;font-weight:700;"><i class=\'bi '.$ch_ic.'\' style=\'font-size:.7rem;\'></i>'.$ch_lb.'</span>'],
                    ['Campaign', '<span style="font-size:.76rem;color:#0f172a;font-weight:500;text-align:right;display:block;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'.htmlspecialchars($t['campaign_title']??'General Donation').'</span>'],
                    ['Bank',     '<span style="font-size:.76rem;font-weight:600;color:#1447c0;">'.htmlspecialchars($t['bank_name']??'—').'</span>'],
                    ['Txn Date', '<span style="font-size:.76rem;color:#475569;">'.formatDate($t['transaction_date']).'</span>'],
                ];
                foreach($rows as [$lbl,$val]): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <span style="font-size:.71rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.04em;flex-shrink:0;"><?= $lbl ?></span>
                    <?= $val ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Submitted timestamp -->
            <div style="display:flex;align-items:center;gap:5px;padding:7px 10px;background:#f8fafc;border-radius:8px;margin-bottom:14px;">
                <i class="bi bi-clock" style="color:#94a3b8;font-size:.76rem;"></i>
                <span style="font-size:.72rem;color:#64748b;">Submitted <?= formatDateTime($t['created_at']) ?></span>
            </div>

            <!-- CTA button -->
            <a href="<?= SITE_URL ?>/finance_staff/verify.php?id=<?= $t['id'] ?>"
               style="display:flex;align-items:center;justify-content:center;gap:7px;padding:11px;background:linear-gradient(135deg,#1447c0,#0d2157);color:white;border-radius:10px;font-size:.82rem;font-weight:700;text-decoration:none;box-shadow:0 3px 12px rgba(20,71,192,.3);transition:all .2s;letter-spacing:-.01em;"
               onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 5px 18px rgba(20,71,192,.4)'"
               onmouseout="this.style.transform='none';this.style.boxShadow='0 3px 12px rgba(20,71,192,.3)'">
                <i class="bi bi-shield-check" style="font-size:.88rem;"></i>
                Review &amp; Verify
            </a>
        </div>
    </div>
    <?php endwhile; ?>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
