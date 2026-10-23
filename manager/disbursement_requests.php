<?php
$pageTitle  = 'Disbursement Requests';
$activePage = 'campaigns';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('manager');

$db  = getDB();
$uid = $user['id'];

// Handle review action (approve / reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $req_id     = (int)($_POST['req_id'] ?? 0);
    $action     = $_POST['action'] ?? '';
    $review_note= trim($_POST['review_note'] ?? '');

    if ($req_id && in_array($action, ['approved','rejected'])) {
        $row = $db->query("SELECT * FROM disbursement_requests WHERE id=$req_id AND status='pending'")->fetch_assoc();
        if ($row) {
            $note = $db->real_escape_string($review_note);
            $db->query("UPDATE disbursement_requests
                        SET status='$action', reviewed_by=$uid, reviewed_at=NOW(), review_note='$note'
                        WHERE id=$req_id");

            // Notify the campaign staff who submitted
            $requester_id = (int)$row['requested_by'];
            $camp_title   = $db->query("SELECT title FROM campaigns WHERE id={$row['campaign_id']}")->fetch_assoc()['title'] ?? '';
            $ct = $db->real_escape_string($camp_title);
            $rt = $db->real_escape_string($row['title']);
            $msg = $action === 'approved'
                ? "Your disbursement request \"$rt\" for campaign \"$ct\" has been APPROVED. You can now proceed with disbursement and upload evidence."
                : "Your disbursement request \"$rt\" for campaign \"$ct\" has been REJECTED." . ($review_note ? " Reason: $note" : '');
            $db->query("INSERT INTO notifications (user_id, title, message, type)
                        VALUES ($requester_id,
                                'Disbursement Request " . ucfirst($action) . "',
                                '$msg',
                                'system')");
            setFlash($action === 'approved' ? 'success' : 'warning',
                     'Request ' . ucfirst($action) . '! Staff has been notified.');
        }
    }
    redirect(SITE_URL . '/manager/disbursement_requests.php?filter=' . ($_GET['filter'] ?? ''));
}

$filter     = $_GET['filter'] ?? 'pending';
$filter_camp= (int)($_GET['campaign_id'] ?? 0);
$where_parts= [];
if ($filter && $filter !== 'all') $where_parts[] = "dr.status='$filter'";
if ($filter_camp) $where_parts[] = "dr.campaign_id=$filter_camp";
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$requests = $db->query("
    SELECT dr.*, c.title as campaign_title, c.raised_amount, c.target_amount,
           u.name as requested_by_name, u.email as requested_by_email,
           rv.name as reviewed_by_name
    FROM disbursement_requests dr
    JOIN campaigns c ON dr.campaign_id = c.id
    JOIN users u ON dr.requested_by = u.id
    LEFT JOIN users rv ON dr.reviewed_by = rv.id
    $where
    ORDER BY FIELD(dr.status,'pending','approved','disbursed','rejected'), dr.created_at DESC
");

$counts = [];
foreach (['all','pending','approved','disbursed','rejected'] as $s) {
    $w = $s === 'all' ? '' : "WHERE status='$s'";
    $counts[$s] = (int)$db->query("SELECT COUNT(*) as c FROM disbursement_requests $w")->fetch_assoc()['c'];
}

$status_cfg = [
    'pending'   => ['bg'=>'#fef9c3','color'=>'#854d0e','border'=>'#fde68a','label'=>'Pending','icon'=>'bi-clock-fill'],
    'approved'  => ['bg'=>'#dcfce7','color'=>'#15803d','border'=>'#bbf7d0','label'=>'Approved','icon'=>'bi-check-circle-fill'],
    'rejected'  => ['bg'=>'#fee2e2','color'=>'#991b1b','border'=>'#fecaca','label'=>'Rejected','icon'=>'bi-x-circle-fill'],
    'disbursed' => ['bg'=>'#dbeafe','color'=>'#1447c0','border'=>'#bfdbfe','label'=>'Disbursed','icon'=>'bi-bank2'],
];
?>

<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-send-fill" style="color:#7c3aed;font-size:1rem;"></i>Disbursement Requests
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">Review and approve fund disbursement requests from Campaign Staff</p>
    </div>
    <?php if ($counts['pending'] > 0): ?>
    <div style="background:#fef9c3;border:1.5px solid #fde68a;border-radius:11px;padding:8px 14px;font-size:.78rem;color:#854d0e;font-weight:700;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-clock-fill"></i><?= $counts['pending'] ?> pending review
    </div>
    <?php endif; ?>
</div>

<!-- Filter tabs -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <?php foreach (['all'=>'All','pending'=>'Pending','approved'=>'Approved','disbursed'=>'Disbursed','rejected'=>'Rejected'] as $val => $label):
        $active = $filter === $val;
        $sc = $status_cfg[$val] ?? ['bg'=>'#f1f5f9','color'=>'#475569','border'=>'#e2e8f0'];
    ?>
    <a href="?filter=<?= $val ?>"
       style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:.78rem;font-weight:700;border:1.5px solid <?= $active ? $sc['border'] : '#e2e8f0' ?>;background:<?= $active ? $sc['bg'] : 'white' ?>;color:<?= $active ? $sc['color'] : '#64748b' ?>;text-decoration:none;transition:all .15s;"
       onmouseover="this.style.background='<?= $sc['bg'] ?>'" onmouseout="this.style.background='<?= $active ? $sc['bg'] : 'white' ?>'">
        <?= $label ?>
        <span style="background:rgba(0,0,0,.08);padding:1px 7px;border-radius:10px;font-size:.67rem;"><?= $counts[$val] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Requests -->
<?php if ($requests && $requests->num_rows > 0): ?>
<div style="display:flex;flex-direction:column;gap:14px;">
<?php while ($r = $requests->fetch_assoc()):
    $sc = $status_cfg[$r['status']] ?? $status_cfg['pending'];
    $hasEv = !empty($r['evidence_url']);
    $isPdf = $hasEv && strtolower(pathinfo($r['evidence_url'], PATHINFO_EXTENSION)) === 'pdf';
    $camp_pct = $r['target_amount'] > 0 ? min(100, round($r['raised_amount'] / $r['target_amount'] * 100, 1)) : 0;
?>
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);">
    <!-- Header row -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #f1f5f9;flex-wrap:wrap;gap:10px;background:<?= $r['status']==='pending'?'#fffbeb':'#fafbff' ?>;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span style="font-size:.67rem;background:#dbeafe;color:#1447c0;padding:2px 9px;border-radius:20px;font-weight:700;"><?= htmlspecialchars(mb_strimwidth($r['campaign_title'], 0, 28, '…')) ?></span>
            <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;border:1px solid <?= $sc['border'] ?>;padding:3px 10px;border-radius:20px;font-size:.69rem;font-weight:700;">
                <i class="bi <?= $sc['icon'] ?>" style="font-size:.62rem;"></i><?= $sc['label'] ?>
            </span>
            <span style="font-size:.69rem;color:#94a3b8;"><?= formatDateTime($r['created_at']) ?></span>
        </div>
        <div style="font-size:1.15rem;font-weight:900;color:#d97706;letter-spacing:-.02em;">
            <?= number_format($r['amount']/1000000,1) ?>M <span style="font-size:.72rem;font-weight:600;color:#94a3b8;">VND</span>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 300px;gap:0;">
        <!-- Left: details -->
        <div style="padding:16px 18px;border-right:1px solid #f1f5f9;">
            <div style="font-size:.9rem;font-weight:700;color:#0f172a;margin-bottom:5px;"><?= htmlspecialchars($r['title']) ?></div>
            <div style="font-size:.77rem;color:#64748b;margin-bottom:10px;display:flex;align-items:center;gap:5px;">
                <i class="bi bi-building" style="font-size:.72rem;"></i>
                <strong><?= htmlspecialchars($r['beneficiary']) ?></strong>
            </div>
            <div style="font-size:.8rem;color:#374151;line-height:1.6;margin-bottom:10px;"><?= nl2br(htmlspecialchars($r['purpose'])) ?></div>

            <?php if ($r['justification']): ?>
            <div style="background:#f8fafc;border-radius:8px;padding:9px 12px;margin-bottom:10px;border-left:3px solid #cbd5e1;">
                <div style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:3px;">Justification</div>
                <div style="font-size:.78rem;color:#475569;"><?= nl2br(htmlspecialchars($r['justification'])) ?></div>
            </div>
            <?php endif; ?>

            <!-- Requested by -->
            <div style="display:flex;align-items:center;gap:8px;padding-top:10px;border-top:1px solid #f1f5f9;">
                <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#6d28d9);display:flex;align-items:center;justify-content:center;font-size:.64rem;font-weight:800;color:white;flex-shrink:0;">
                    <?= strtoupper(substr($r['requested_by_name'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-size:.76rem;font-weight:600;color:#0f172a;"><?= htmlspecialchars($r['requested_by_name']) ?></div>
                    <div style="font-size:.67rem;color:#94a3b8;"><?= htmlspecialchars($r['requested_by_email']) ?></div>
                </div>
            </div>

            <!-- Review note if exists -->
            <?php if ($r['review_note']): ?>
            <div style="margin-top:10px;background:<?= $r['status']==='rejected'?'#fff1f2':'#f0fdf4' ?>;border-radius:9px;padding:9px 12px;border:1px solid <?= $r['status']==='rejected'?'#fecaca':'#bbf7d0' ?>;">
                <div style="font-size:.69rem;font-weight:700;color:<?= $r['status']==='rejected'?'#991b1b':'#15803d' ?>;margin-bottom:3px;">
                    <i class="bi bi-person-fill"></i> Manager Note (<?= htmlspecialchars($r['reviewed_by_name'] ?? '—') ?>)
                </div>
                <div style="font-size:.78rem;color:#374151;"><?= htmlspecialchars($r['review_note']) ?></div>
            </div>
            <?php endif; ?>

            <!-- Evidence -->
            <?php if ($hasEv): ?>
            <div style="margin-top:10px;display:flex;align-items:center;gap:10px;">
                <?php if ($isPdf): ?>
                <a href="<?= SITE_URL . '/' . htmlspecialchars($r['evidence_url']) ?>" target="_blank"
                   style="display:inline-flex;align-items:center;gap:5px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:5px 12px;border-radius:8px;font-size:.74rem;font-weight:700;text-decoration:none;">
                    <i class="bi bi-file-pdf-fill"></i>View Receipt PDF
                </a>
                <?php else: ?>
                <a href="<?= SITE_URL . '/' . htmlspecialchars($r['evidence_url']) ?>" target="_blank">
                    <img src="<?= SITE_URL . '/' . htmlspecialchars($r['evidence_url']) ?>"
                         style="width:60px;height:46px;object-fit:cover;border-radius:7px;border:1.5px solid #e2e8f0;" alt="Receipt">
                </a>
                <?php endif; ?>
                <div>
                    <div style="font-size:.74rem;font-weight:700;color:#15803d;"><i class="bi bi-check-circle-fill"></i> Disbursed</div>
                    <?php if ($r['evidence_note']): ?>
                    <div style="font-size:.69rem;color:#64748b;"><?= htmlspecialchars($r['evidence_note']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: campaign info + action -->
        <div style="padding:16px 18px;">
            <!-- Campaign progress mini -->
            <div style="margin-bottom:14px;">
                <div style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:6px;">Campaign Progress</div>
                <div style="font-size:.82rem;font-weight:700;color:#1447c0;margin-bottom:2px;"><?= number_format($r['raised_amount']/1000000,1) ?>M / <?= number_format($r['target_amount']/1000000,1) ?>M VND</div>
                <div style="height:5px;background:#f1f5f9;border-radius:3px;overflow:hidden;margin-bottom:3px;">
                    <div style="height:100%;width:<?= $camp_pct ?>%;background:linear-gradient(90deg,#3b82f6,#1447c0);border-radius:3px;"></div>
                </div>
                <div style="font-size:.7rem;color:#94a3b8;"><?= $camp_pct ?>% of goal</div>
                <div style="margin-top:8px;font-size:.76rem;">
                    <span style="color:#64748b;">This request: </span>
                    <span style="font-weight:700;color:#d97706;"><?= number_format($r['amount']/1000000,1) ?>M VND</span>
                    <span style="color:#94a3b8;"> (<?= $r['raised_amount']>0?round($r['amount']/$r['raised_amount']*100,1):0 ?>% of raised)</span>
                </div>
            </div>

            <!-- Action buttons (only for pending) -->
            <?php if ($r['status'] === 'pending'): ?>
            <form method="POST" id="reviewForm<?= $r['id'] ?>">
                <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                <input type="hidden" name="action" id="reviewAction<?= $r['id'] ?>" value="">
                <div style="margin-bottom:10px;">
                    <label style="display:block;font-size:.72rem;font-weight:600;color:#374151;margin-bottom:4px;">Review Note</label>
                    <textarea name="review_note" rows="2"
                              style="width:100%;padding:7px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.79rem;color:#0f172a;outline:none;resize:none;"
                              onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'"
                              placeholder="Optional note for the staff..."></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <button type="button"
                            onclick="submitReview(<?= $r['id'] ?>,'approved')"
                            style="padding:9px;background:linear-gradient(135deg,#16a34a,#15803d);color:white;border:none;border-radius:9px;font-size:.79rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;transition:all .15s;"
                            onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                        <i class="bi bi-check2-circle"></i>Approve
                    </button>
                    <button type="button"
                            onclick="submitReview(<?= $r['id'] ?>,'rejected')"
                            style="padding:9px;background:white;border:1.5px solid #fecaca;color:#dc2626;border-radius:9px;font-size:.79rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;transition:all .15s;"
                            onmouseover="this.style.background='#fff1f2'" onmouseout="this.style.background='white'">
                        <i class="bi bi-x-circle"></i>Reject
                    </button>
                </div>
            </form>
            <?php else: ?>
            <div style="background:#f8fafc;border-radius:9px;padding:11px;text-align:center;">
                <div style="font-size:.8rem;color:#64748b;">Reviewed by</div>
                <div style="font-size:.82rem;font-weight:700;color:#0f172a;margin-top:2px;"><?= htmlspecialchars($r['reviewed_by_name'] ?? '—') ?></div>
                <?php if ($r['reviewed_at']): ?>
                <div style="font-size:.69rem;color:#94a3b8;margin-top:2px;"><?= formatDateTime($r['reviewed_at']) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endwhile; ?>
</div>
<?php else: ?>
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;padding:60px 20px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.04);">
    <i class="bi bi-send" style="font-size:2.5rem;color:#e2e8f0;display:block;margin-bottom:12px;"></i>
    <div style="font-size:.86rem;font-weight:600;color:#94a3b8;margin-bottom:4px;">No <?= $filter !== 'all' ? $filter : '' ?> requests</div>
    <div style="font-size:.77rem;color:#cbd5e1;">Disbursement requests from Campaign Staff will appear here</div>
</div>
<?php endif; ?>

<script>
function submitReview(id, action) {
    const form = document.getElementById('reviewForm' + id);
    document.getElementById('reviewAction' + id).value = action;
    if (action === 'rejected') {
        const note = form.querySelector('textarea[name=review_note]').value.trim();
        if (!note) {
            alert('Please provide a rejection reason.');
            form.querySelector('textarea').focus();
            return;
        }
    }
    const label = action === 'approved' ? 'Approve this request?' : 'Reject this request?';
    if (confirm(label)) form.submit();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
