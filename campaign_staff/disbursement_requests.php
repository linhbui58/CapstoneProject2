<?php
$pageTitle  = 'Disbursement Requests';
$activePage = 'fund_allocation';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('campaign_staff');

$db  = getDB();
$uid = $user['id'];
$errors = [];

function uploadDisbursEvidence(array $file): ?string {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload error.');
    $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed)) throw new RuntimeException('Only JPG, PNG, WebP or PDF allowed.');
    if ($file['size'] > 5 * 1024 * 1024) throw new RuntimeException('Max 5 MB.');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $dir = dirname(__DIR__) . '/assets/uploads/disbursement/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = 'dis_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) throw new RuntimeException('Failed to save file.');
    return 'assets/uploads/disbursement/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    // ── Upload evidence after disbursement ────────────────
    if ($action === 'upload_evidence') {
        $req_id = (int)($_POST['req_id'] ?? 0);
        $note   = trim($_POST['evidence_note'] ?? '');
        $row = $db->query("SELECT id, evidence_url, status FROM disbursement_requests WHERE id=$req_id AND requested_by=$uid")->fetch_assoc();
        if (!$row) { setFlash('danger', 'Request not found.'); redirect(SITE_URL . '/campaign_staff/disbursement_requests.php'); }
        if ($row['status'] !== 'approved') { setFlash('danger', 'Only approved requests can attach evidence.'); redirect(SITE_URL . '/campaign_staff/disbursement_requests.php'); }
        try {
            $ev = uploadDisbursEvidence($_FILES['evidence_file'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
            if ($ev) {
                if ($row['evidence_url']) { $old = dirname(__DIR__).'/'.$row['evidence_url']; if (file_exists($old)) @unlink($old); }
                $evSafe = $db->real_escape_string($ev);
                $nSafe  = $db->real_escape_string($note);
                $db->query("UPDATE disbursement_requests SET evidence_url='$evSafe', evidence_note='$nSafe', status='disbursed', disbursed_at=NOW() WHERE id=$req_id");

                // Also create a fund_allocation record for transparency
                $req = $db->query("SELECT * FROM disbursement_requests WHERE id=$req_id")->fetch_assoc();
                $pt = $db->real_escape_string($req['title']);
                $desc = $db->real_escape_string($req['beneficiary'] . ' — ' . substr($req['purpose'], 0, 200));
                $db->query("INSERT INTO fund_allocations (campaign_id, amount, purpose, description, allocated_by, allocation_date, status, evidence_url, evidence_note)
                            VALUES ({$req['campaign_id']}, {$req['amount']}, '$pt', '$desc', $uid, CURDATE(), 'disbursed', '$evSafe', '$nSafe')");

                setFlash('success', 'Evidence uploaded. Disbursement recorded and fund allocation updated.');
            } else {
                setFlash('warning', 'No file uploaded.');
            }
        } catch (RuntimeException $e) { setFlash('danger', $e->getMessage()); }
        redirect(SITE_URL . '/campaign_staff/disbursement_requests.php');
    }

    // ── Create new request ────────────────────────────────
    $campaign_id   = (int)($_POST['campaign_id'] ?? 0);
    $title         = trim($_POST['title'] ?? '');
    $beneficiary   = trim($_POST['beneficiary'] ?? '');
    $amount        = (float)($_POST['amount'] ?? 0);
    $purpose       = trim($_POST['purpose'] ?? '');
    $justification = trim($_POST['justification'] ?? '');

    if (!$campaign_id)     $errors[] = 'Please select a campaign.';
    if (empty($title))     $errors[] = 'Title is required.';
    if (empty($beneficiary)) $errors[] = 'Beneficiary is required.';
    if ($amount <= 0)      $errors[] = 'Amount must be greater than 0.';
    if (empty($purpose))   $errors[] = 'Purpose is required.';

    if (empty($errors)) {
        $camp = $db->query("SELECT raised_amount FROM campaigns WHERE id=$campaign_id")->fetch_assoc();
        $already_req  = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM disbursement_requests WHERE campaign_id=$campaign_id AND status IN ('pending','approved')")->fetch_assoc()['t'];
        $already_alloc= $db->query("SELECT COALESCE(SUM(amount),0) as t FROM fund_allocations WHERE campaign_id=$campaign_id AND status!='completed'")->fetch_assoc()['t'];
        $available = ($camp['raised_amount'] ?? 0) - max($already_req, $already_alloc);
        if ($amount > $available + 1) {
            $errors[] = 'Requested amount exceeds available funds (' . formatCurrency(max(0, $available)) . ').';
        }
    }

    if (empty($errors)) {
        $t  = $db->real_escape_string($title);
        $b  = $db->real_escape_string($beneficiary);
        $p  = $db->real_escape_string($purpose);
        $j  = $db->real_escape_string($justification);
        $db->query("INSERT INTO disbursement_requests (campaign_id, title, beneficiary, amount, purpose, justification, requested_by, status)
                    VALUES ($campaign_id, '$t', '$b', $amount, '$p', '$j', $uid, 'pending')");

        // Notify manager
        $managers = $db->query("SELECT id FROM users WHERE role='manager' AND is_active=1");
        $camp_t = $db->query("SELECT title FROM campaigns WHERE id=$campaign_id")->fetch_assoc()['title'] ?? '';
        $ct = $db->real_escape_string($camp_t);
        if ($managers) {
            while ($m = $managers->fetch_assoc()) {
                $mid = (int)$m['id'];
                $db->query("INSERT INTO notifications (user_id, title, message, type)
                            VALUES ($mid, 'New Disbursement Request',
                                    'Campaign Staff submitted a disbursement request for \"$ct\": $t ('.number_format($amount,0,',','.').' VND). Please review.',
                                    'system')");
            }
        }

        setFlash('success', 'Disbursement request submitted. Manager has been notified.');
        redirect(SITE_URL . '/campaign_staff/disbursement_requests.php?campaign_id=' . $campaign_id);
    }
}

$filter_campaign = (int)($_GET['campaign_id'] ?? 0);
$campaigns = $db->query("SELECT id, title, raised_amount FROM campaigns WHERE raised_amount > 0 ORDER BY title");

$where = $filter_campaign ? "WHERE dr.campaign_id=$filter_campaign" : '';
$requests = $db->query("
    SELECT dr.*, c.title as campaign_title, u.name as requested_by_name,
           rv.name as reviewed_by_name
    FROM disbursement_requests dr
    JOIN campaigns c ON dr.campaign_id = c.id
    JOIN users u ON dr.requested_by = u.id
    LEFT JOIN users rv ON dr.reviewed_by = rv.id
    $where
    ORDER BY dr.created_at DESC
");

$status_cfg = [
    'pending'   => ['bg'=>'#fef9c3','color'=>'#854d0e','border'=>'#fde68a','label'=>'Pending Review','icon'=>'bi-clock-fill'],
    'approved'  => ['bg'=>'#dcfce7','color'=>'#15803d','border'=>'#bbf7d0','label'=>'Approved','icon'=>'bi-check-circle-fill'],
    'rejected'  => ['bg'=>'#fee2e2','color'=>'#991b1b','border'=>'#fecaca','label'=>'Rejected','icon'=>'bi-x-circle-fill'],
    'disbursed' => ['bg'=>'#dbeafe','color'=>'#1447c0','border'=>'#bfdbfe','label'=>'Disbursed','icon'=>'bi-bank2'],
];

// Count pending approvals
$pending_count = (int)$db->query("SELECT COUNT(*) as c FROM disbursement_requests WHERE requested_by=$uid AND status='pending'")->fetch_assoc()['c'];
$approved_count= (int)$db->query("SELECT COUNT(*) as c FROM disbursement_requests WHERE requested_by=$uid AND status='approved'")->fetch_assoc()['c'];
?>

<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-send-fill" style="color:#1447c0;font-size:1rem;"></i>Disbursement Requests
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">Submit requests to manager for fund disbursement approval</p>
    </div>
    <?php if ($approved_count > 0): ?>
    <div style="background:#dcfce7;border:1.5px solid #bbf7d0;border-radius:11px;padding:8px 14px;font-size:.78rem;color:#15803d;font-weight:700;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-check-circle-fill"></i><?= $approved_count ?> request<?= $approved_count > 1 ? 's' : '' ?> approved — upload evidence below
    </div>
    <?php endif; ?>
</div>

<?php if ($errors): ?>
<div style="background:#fff1f2;border-left:3px solid #ef4444;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
    <div style="font-weight:700;color:#b91c1c;font-size:.79rem;margin-bottom:4px;"><i class="bi bi-exclamation-circle-fill me-1"></i>Fix errors:</div>
    <ul style="margin:0;padding-left:16px;font-size:.78rem;color:#b91c1c;"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start;">

<!-- ═══ LEFT: CREATE FORM ═══════════════════════ -->
<div style="position:sticky;top:80px;">
    <form method="POST"
          style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);">
        <input type="hidden" name="action" value="create">
        <div style="background:linear-gradient(135deg,#1447c0,#0d2157);padding:14px 20px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-plus-circle-fill" style="color:white;font-size:.88rem;"></i>
            <div>
                <div style="font-size:.85rem;font-weight:700;color:white;">New Disbursement Request</div>
                <div style="font-size:.71rem;color:rgba(255,255,255,.65);">Manager will review and approve</div>
            </div>
        </div>
        <div style="padding:18px;">
            <!-- Campaign -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.73rem;font-weight:700;color:#374151;margin-bottom:4px;">Campaign <span style="color:#ef4444;">*</span></label>
                <select name="campaign_id"
                        style="width:100%;height:40px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;background:white;outline:none;cursor:pointer;"
                        onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'">
                    <option value="">Select campaign...</option>
                    <?php if ($campaigns): $campaigns->data_seek(0); while ($c = $campaigns->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= ($filter_campaign == $c['id'] || ($_POST['campaign_id'] ?? 0) == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars(mb_strimwidth($c['title'], 0, 30, '…')) ?> (<?= number_format($c['raised_amount']/1000000,1) ?>M)
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <!-- Title -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.73rem;font-weight:700;color:#374151;margin-bottom:4px;">Request Title <span style="color:#ef4444;">*</span></label>
                <input type="text" name="title"
                       style="width:100%;height:40px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;"
                       onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="e.g. Medical supplies batch 1"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>
            <!-- Beneficiary -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.73rem;font-weight:700;color:#374151;margin-bottom:4px;">
                    Beneficiary (Đơn vị thụ hưởng) <span style="color:#ef4444;">*</span>
                </label>
                <input type="text" name="beneficiary"
                       style="width:100%;height:40px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;"
                       onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="e.g. Children Hospital No.2"
                       value="<?= htmlspecialchars($_POST['beneficiary'] ?? '') ?>">
            </div>
            <!-- Amount -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.73rem;font-weight:700;color:#374151;margin-bottom:4px;">Amount (VND) <span style="color:#ef4444;">*</span></label>
                <input type="number" name="amount" min="1"
                       style="width:100%;height:40px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;"
                       onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="0"
                       value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
            </div>
            <!-- Purpose -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.73rem;font-weight:700;color:#374151;margin-bottom:4px;">Purpose <span style="color:#ef4444;">*</span></label>
                <textarea name="purpose" rows="2"
                          style="width:100%;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.81rem;color:#0f172a;outline:none;resize:none;"
                          onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                          placeholder="Describe what this fund will be used for..."><?= htmlspecialchars($_POST['purpose'] ?? '') ?></textarea>
            </div>
            <!-- Justification -->
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:.73rem;font-weight:700;color:#374151;margin-bottom:4px;">Justification (Lý do đề xuất)</label>
                <textarea name="justification" rows="2"
                          style="width:100%;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.81rem;color:#0f172a;outline:none;resize:none;"
                          onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                          placeholder="Why is this disbursement necessary now?"><?= htmlspecialchars($_POST['justification'] ?? '') ?></textarea>
            </div>
            <button type="submit"
                    style="width:100%;padding:11px;background:linear-gradient(135deg,#1447c0,#0d2157);color:white;border:none;border-radius:10px;font-weight:700;font-size:.86rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;box-shadow:0 3px 12px rgba(20,71,192,.3);transition:all .18s;"
                    onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                <i class="bi bi-send-fill"></i>Submit Request
            </button>
        </div>
    </form>
</div>

<!-- ═══ RIGHT: REQUESTS LIST ════════════════════ -->
<div>
    <!-- Filter row -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
        <span style="font-size:.84rem;font-weight:700;color:#0f172a;">
            <?= $requests ? $requests->num_rows : 0 ?> request<?= ($requests && $requests->num_rows != 1) ? 's' : '' ?>
        </span>
        <form method="GET" style="display:flex;gap:7px;">
            <select name="campaign_id" onchange="this.form.submit()"
                    style="height:34px;border:1.5px solid #e2e8f0;border-radius:8px;padding:0 11px;font-size:.79rem;color:#475569;background:white;outline:none;cursor:pointer;max-width:200px;">
                <option value="">All Campaigns</option>
                <?php if ($campaigns): $campaigns->data_seek(0); while ($c = $campaigns->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>" <?= $filter_campaign == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(mb_strimwidth($c['title'], 0, 25, '…')) ?>
                </option>
                <?php endwhile; endif; ?>
            </select>
            <?php if ($filter_campaign): ?>
            <a href="<?= SITE_URL ?>/campaign_staff/disbursement_requests.php"
               style="height:34px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.79rem;color:#64748b;background:white;display:flex;align-items:center;gap:4px;text-decoration:none;">
                <i class="bi bi-x-lg" style="font-size:.7rem;"></i>Clear
            </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($requests && $requests->num_rows > 0): ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
    <?php while ($r = $requests->fetch_assoc()):
        $sc = $status_cfg[$r['status']] ?? $status_cfg['pending'];
        $hasEv = !empty($r['evidence_url']);
        $isPdf = $hasEv && strtolower(pathinfo($r['evidence_url'], PATHINFO_EXTENSION)) === 'pdf';
    ?>
    <div style="background:white;border:1px solid #e2e8f0;border-left:3px solid <?= $sc['border'] ?>;border-radius:12px;padding:16px 18px;box-shadow:0 1px 6px rgba(0,0,0,.04);">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px;flex-wrap:wrap;">
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;flex-wrap:wrap;">
                    <span style="font-size:.67rem;background:#dbeafe;color:#1447c0;padding:2px 9px;border-radius:20px;font-weight:700;">
                        <?= htmlspecialchars(mb_strimwidth($r['campaign_title'], 0, 25, '…')) ?>
                    </span>
                    <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;border:1px solid <?= $sc['border'] ?>;padding:2px 9px;border-radius:20px;font-size:.68rem;font-weight:700;">
                        <i class="bi <?= $sc['icon'] ?>" style="font-size:.62rem;"></i><?= $sc['label'] ?>
                    </span>
                </div>
                <div style="font-size:.88rem;font-weight:700;color:#0f172a;"><?= htmlspecialchars($r['title']) ?></div>
                <div style="font-size:.76rem;color:#64748b;margin-top:2px;">
                    <i class="bi bi-building" style="font-size:.72rem;"></i> <?= htmlspecialchars($r['beneficiary']) ?>
                </div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-size:1.1rem;font-weight:900;color:#d97706;letter-spacing:-.02em;"><?= number_format($r['amount']/1000000,1) ?>M <span style="font-size:.72rem;font-weight:600;color:#94a3b8;">VND</span></div>
                <div style="font-size:.68rem;color:#94a3b8;margin-top:2px;"><?= formatDate($r['created_at']) ?></div>
            </div>
        </div>

        <div style="font-size:.78rem;color:#475569;line-height:1.55;margin-bottom:10px;"><?= nl2br(htmlspecialchars($r['purpose'])) ?></div>

        <?php if ($r['justification']): ?>
        <div style="background:#f8fafc;border-radius:8px;padding:9px 12px;margin-bottom:10px;">
            <div style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:3px;">Justification</div>
            <div style="font-size:.76rem;color:#475569;"><?= htmlspecialchars($r['justification']) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($r['review_note']): ?>
        <div style="background:<?= $r['status']==='rejected'?'#fff1f2':'#f0fdf4' ?>;border-radius:8px;padding:9px 12px;margin-bottom:10px;border:1px solid <?= $r['status']==='rejected'?'#fecaca':'#bbf7d0' ?>;">
            <div style="font-size:.67rem;font-weight:700;color:<?= $r['status']==='rejected'?'#991b1b':'#15803d' ?>;margin-bottom:3px;display:flex;align-items:center;gap:5px;">
                <i class="bi bi-person-fill"></i>Manager's note (<?= htmlspecialchars($r['reviewed_by_name'] ?? '—') ?>)
            </div>
            <div style="font-size:.76rem;color:#374151;"><?= htmlspecialchars($r['review_note']) ?></div>
        </div>
        <?php endif; ?>

        <!-- Evidence & Approved actions -->
        <?php if ($r['status'] === 'approved' && !$hasEv): ?>
        <div style="background:#fffbeb;border:1.5px dashed #fbbf24;border-radius:9px;padding:12px 14px;">
            <div style="font-size:.75rem;font-weight:700;color:#854d0e;margin-bottom:9px;display:flex;align-items:center;gap:5px;">
                <i class="bi bi-paperclip" style="color:#d97706;"></i> Upload Receipt / Evidence
            </div>
            <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:8px;">
                <input type="hidden" name="action" value="upload_evidence">
                <input type="hidden" name="req_id" value="<?= $r['id'] ?>">
                <div style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center;">
                    <label style="cursor:pointer;">
                        <div style="border:1.5px dashed #fde68a;border-radius:7px;padding:9px 12px;background:white;text-align:center;font-size:.75rem;color:#854d0e;">
                            <i class="bi bi-cloud-upload"></i> Click to attach (JPG/PNG/PDF · 5MB)
                        </div>
                        <input type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.webp,.pdf" style="display:none;"
                               onchange="this.previousElementSibling.querySelector('div').textContent='✓ '+this.files[0].name">
                    </label>
                    <button type="submit"
                            style="height:40px;padding:0 14px;background:linear-gradient(135deg,#d97706,#b45309);color:white;border:none;border-radius:8px;font-size:.78rem;font-weight:700;cursor:pointer;white-space:nowrap;">
                        <i class="bi bi-check2-all"></i> Mark Disbursed
                    </button>
                </div>
                <input type="text" name="evidence_note" placeholder="Invoice/receipt reference (e.g. INV-2026-001)"
                       style="height:36px;padding:0 11px;border:1.5px solid #fde68a;border-radius:7px;font-size:.78rem;background:white;outline:none;color:#0f172a;">
            </form>
        </div>
        <?php elseif ($hasEv): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;">
            <?php if ($isPdf): ?>
            <a href="<?= SITE_URL . '/' . htmlspecialchars($r['evidence_url']) ?>" target="_blank"
               style="display:inline-flex;align-items:center;gap:5px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:5px 11px;border-radius:7px;font-size:.74rem;font-weight:700;text-decoration:none;">
                <i class="bi bi-file-pdf-fill"></i>Receipt PDF
            </a>
            <?php else: ?>
            <a href="<?= SITE_URL . '/' . htmlspecialchars($r['evidence_url']) ?>" target="_blank">
                <img src="<?= SITE_URL . '/' . htmlspecialchars($r['evidence_url']) ?>"
                     style="width:52px;height:40px;object-fit:cover;border-radius:6px;border:1.5px solid #e2e8f0;" alt="Receipt">
            </a>
            <?php endif; ?>
            <div>
                <div style="font-size:.74rem;font-weight:700;color:#15803d;"><i class="bi bi-check-circle-fill"></i> Disbursed on <?= formatDate($r['disbursed_at']) ?></div>
                <?php if ($r['evidence_note']): ?>
                <div style="font-size:.69rem;color:#64748b;"><?= htmlspecialchars($r['evidence_note']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:52px 20px;text-align:center;">
        <i class="bi bi-send" style="font-size:2.5rem;color:#e2e8f0;display:block;margin-bottom:12px;"></i>
        <div style="font-size:.84rem;font-weight:600;color:#94a3b8;margin-bottom:4px;">No requests yet</div>
        <div style="font-size:.76rem;color:#cbd5e1;">Submit your first disbursement request using the form</div>
    </div>
    <?php endif; ?>
</div>

</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
