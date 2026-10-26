<?php
$pageTitle  = 'Fund Allocation';
$activePage = 'fund_allocation';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('campaign_staff');

$db  = getApplicationDB();
$uid = $user['id'];
$filter_campaign = (int)($_GET['campaign_id'] ?? 0);
$errors = [];

// ── Upload helper ────────────────────────────────────────
function uploadEvidence(array $file): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload error code: ' . $file['error']);
    }

    $allowedMime = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
    $allowedExt  = ['jpg','jpeg','png','gif','webp','pdf'];
    $maxSize     = 5 * 1024 * 1024; // 5 MB

    if ($file['size'] > $maxSize) {
        throw new RuntimeException('File too large. Maximum size is 5 MB.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMime, true)) {
        throw new RuntimeException('Invalid file type. Only JPG, PNG, GIF, WebP and PDF are allowed.');
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Invalid file extension.');
    }

    $uploadDir = dirname(__DIR__) . '/assets/uploads/evidence/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'ev_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest     = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }

    return 'assets/uploads/evidence/' . $filename;
}

// ── Handle POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? 'create';

    // ── Update evidence on existing allocation ────────────
    if ($action === 'update_evidence') {
        $alloc_id      = (int)($_POST['alloc_id'] ?? 0);
        $evidence_note = trim($_POST['evidence_note'] ?? '');
        if (!$alloc_id) { $errors[] = 'Invalid allocation.'; }

        if (empty($errors)) {
            try {
                $evidence_url = uploadEvidence($_FILES['evidence_file'] ?? ['error' => UPLOAD_ERR_NO_FILE]);

                // Check ownership (must belong to a campaign this staff manages)
                $check = $db->query("SELECT id, evidence_url FROM fund_allocations WHERE id=$alloc_id LIMIT 1");
                $row   = $check ? $check->fetch_assoc() : null;
                if (!$row) { $errors[] = 'Allocation not found.'; }

                if (empty($errors)) {
                    // Delete old file if replacing
                    if ($evidence_url && !empty($row['evidence_url'])) {
                        $oldPath = dirname(__DIR__) . '/' . $row['evidence_url'];
                        if (file_exists($oldPath)) @unlink($oldPath);
                    }

                    $urlToSave   = $evidence_url ?? $row['evidence_url'];
                    $noteSafe    = $db->real_escape_string($evidence_note);
                    $urlSafe     = $db->real_escape_string($urlToSave ?? '');

                    $db->query("UPDATE fund_allocations
                                SET evidence_url  = " . ($urlSafe ? "'$urlSafe'" : 'evidence_url') . ",
                                    evidence_note = '$noteSafe'
                                WHERE id = $alloc_id");
                    setFlash('success', 'Evidence updated successfully.');
                    redirect(SITE_URL . '/campaign_staff/fund_allocation.php?campaign_id=' . $filter_campaign);
                }
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    // ── Create new allocation ─────────────────────────────
    if ($action === 'create') {
        $campaign_id     = (int)($_POST['campaign_id'] ?? 0);
        $amount          = (float)($_POST['amount'] ?? 0);
        $purpose         = sanitize($_POST['purpose'] ?? '');
        $description     = sanitize($_POST['description'] ?? '');
        $allocation_date = sanitize($_POST['allocation_date'] ?? '');
        $status          = sanitize($_POST['status'] ?? 'planned');
        $evidence_note   = trim($_POST['evidence_note'] ?? '');

        if (!$campaign_id)       $errors[] = 'Please select a campaign.';
        if ($amount <= 0)        $errors[] = 'Amount must be greater than 0.';
        if (empty($purpose))     $errors[] = 'Purpose is required.';
        if (empty($allocation_date)) $errors[] = 'Allocation date is required.';

        // Upload
        $evidence_url = null;
        if (empty($errors)) {
            try {
                $evidence_url = uploadEvidence($_FILES['evidence_file'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (empty($errors)) {
            $camp    = $db->query("SELECT raised_amount FROM campaigns WHERE id=$campaign_id")->fetch_assoc();
            $already = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM fund_allocations WHERE campaign_id=$campaign_id AND status!='completed'")->fetch_assoc()['t'];
            $available = ($camp['raised_amount'] ?? 0) - $already;
            if ($amount > $available) {
                $errors[] = 'Amount exceeds available funds (' . formatCurrency($available) . ').';
            }
        }

        if (empty($errors)) {
            $pe   = $db->real_escape_string($purpose);
            $de   = $db->real_escape_string($description);
            $se   = $db->real_escape_string($status);
            $ne   = $db->real_escape_string($evidence_note);
            $ue   = $db->real_escape_string($evidence_url ?? '');
            $db->query("INSERT INTO fund_allocations
                            (campaign_id, amount, purpose, description, allocated_by, allocation_date, status, evidence_url, evidence_note)
                        VALUES
                            ($campaign_id, $amount, '$pe', '$de', $uid, '$allocation_date', '$se',
                             " . ($ue ? "'$ue'" : 'NULL') . ",
                             " . ($ne ? "'$ne'" : 'NULL') . ")");
            setFlash('success', 'Fund allocation recorded!');
            redirect(SITE_URL . '/campaign_staff/fund_allocation.php?campaign_id=' . $campaign_id);
        }
    }
}

// Campaigns with funds
$campaigns = $db->query("SELECT id,title,raised_amount,target_amount FROM campaigns WHERE raised_amount>0 ORDER BY title");

// Allocations
$where = $filter_campaign ? "WHERE fa.campaign_id=$filter_campaign" : '';
$allocations = $db->query("
    SELECT fa.*, c.title as campaign_title, u.name as allocated_by_name
    FROM fund_allocations fa
    JOIN campaigns c ON fa.campaign_id = c.id
    JOIN users u ON fa.allocated_by = u.id
    $where
    ORDER BY fa.created_at DESC
");

// Summary for selected campaign
$summary = null;
if ($filter_campaign) {
    $ci        = $db->query("SELECT raised_amount, title FROM campaigns WHERE id=$filter_campaign")->fetch_assoc();
    $allocated = $db->query("SELECT COALESCE(SUM(amount),0) as t FROM fund_allocations WHERE campaign_id=$filter_campaign")->fetch_assoc()['t'];
    $summary   = [
        'raised'    => (float)($ci['raised_amount'] ?? 0),
        'allocated' => (float)$allocated,
        'available' => (float)($ci['raised_amount'] ?? 0) - (float)$allocated,
        'title'     => $ci['title'] ?? '',
    ];
}

$status_styles = [
    'planned'   => ['bg'=>'#eff6ff','color'=>'#1447c0','border'=>'#bfdbfe','label'=>'Planned'],
    'disbursed' => ['bg'=>'#fef9c3','color'=>'#854d0e','border'=>'#fde68a','label'=>'Disbursed'],
    'completed' => ['bg'=>'#dcfce7','color'=>'#15803d','border'=>'#bbf7d0','label'=>'Completed'],
];
?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-pie-chart-fill" style="color:#d97706;font-size:1rem;"></i>Fund Allocation
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">Track how raised funds are allocated and used — attach receipts as evidence</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:380px 1fr;gap:20px;align-items:start;">

<!-- ═══ LEFT: FORM ═══════════════════════════════ -->
<div style="display:flex;flex-direction:column;gap:14px;position:sticky;top:80px;">

    <?php if ($errors): ?>
    <div style="background:#fff1f2;border-left:3px solid #ef4444;padding:11px 14px;border-radius:10px;">
        <div style="font-weight:700;color:#b91c1c;font-size:.79rem;margin-bottom:4px;"><i class="bi bi-exclamation-circle-fill me-1"></i>Fix errors:</div>
        <ul style="margin:0;padding-left:16px;font-size:.77rem;color:#b91c1c;"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <!-- Campaign summary card -->
    <?php if ($summary): ?>
    <div style="background:linear-gradient(135deg,#fffbeb,#fef9c3);border:1px solid #fde68a;border-radius:14px;padding:16px 18px;">
        <div style="font-size:.76rem;font-weight:700;color:#854d0e;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-bar-chart-fill"></i><?= htmlspecialchars(mb_strimwidth($summary['title'], 0, 30, '…')) ?>
        </div>
        <?php foreach ([
            ['Raised',    formatCurrency($summary['raised']),    '#15803d'],
            ['Allocated', formatCurrency($summary['allocated']), '#d97706'],
            ['Available', formatCurrency($summary['available']), $summary['available'] > 0 ? '#1447c0' : '#991b1b'],
        ] as [$l,$v,$c]): ?>
        <div style="display:flex;justify-content:space-between;margin-bottom:7px;font-size:.77rem;">
            <span style="color:#92400e;font-weight:500;"><?= $l ?></span>
            <span style="font-weight:800;color:<?= $c ?>;"><?= $v ?></span>
        </div>
        <?php endforeach; ?>
        <?php $avail_pct = $summary['raised'] > 0 ? min(100, round(($summary['available'] / $summary['raised']) * 100, 1)) : 0; ?>
        <div style="height:6px;background:rgba(0,0,0,.08);border-radius:3px;overflow:hidden;margin-top:6px;">
            <div style="height:100%;width:<?= $avail_pct ?>%;background:linear-gradient(90deg,#3b82f6,#1447c0);border-radius:3px;"></div>
        </div>
        <div style="font-size:.68rem;color:#92400e;margin-top:3px;"><?= $avail_pct ?>% available for allocation</div>
    </div>
    <?php endif; ?>

    <!-- New allocation form -->
    <form method="POST" enctype="multipart/form-data"
          style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
        <input type="hidden" name="action" value="create">
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
                    <?php if ($campaigns): $campaigns->data_seek(0); while ($c = $campaigns->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= ($filter_campaign == $c['id'] || ($_POST['campaign_id'] ?? 0) == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars(mb_strimwidth($c['title'], 0, 28, '…')) ?> (<?= number_format($c['raised_amount'] / 1000000, 1) ?>M)
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
                       value="<?= htmlspecialchars($_POST['purpose'] ?? '') ?>">
            </div>
            <!-- Amount -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.73rem;font-weight:600;color:#374151;margin-bottom:4px;">Amount (VND) <span style="color:#ef4444;">*</span></label>
                <input type="number" name="amount" min="1"
                       style="width:100%;height:38px;padding:0 11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#d97706'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="0"
                       value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
            </div>
            <!-- Description -->
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:.73rem;font-weight:600;color:#374151;margin-bottom:4px;">Description</label>
                <textarea name="description" rows="2"
                          style="width:100%;padding:8px 11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.81rem;color:#0f172a;outline:none;transition:border-color .15s;resize:none;"
                          onfocus="this.style.borderColor='#d97706'" onblur="this.style.borderColor='#e2e8f0'"
                          placeholder="Details..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <!-- Date + Status -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                <div>
                    <label style="display:block;font-size:.73rem;font-weight:600;color:#374151;margin-bottom:4px;">Date <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="allocation_date"
                           style="width:100%;height:38px;padding:0 11px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.81rem;color:#0f172a;outline:none;"
                           value="<?= htmlspecialchars($_POST['allocation_date'] ?? date('Y-m-d')) ?>">
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

            <!-- ── EVIDENCE UPLOAD ── -->
            <div style="border:1.5px dashed #fde68a;border-radius:10px;padding:13px;margin-bottom:14px;background:#fffbeb;">
                <div style="font-size:.73rem;font-weight:700;color:#854d0e;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                    <i class="bi bi-paperclip" style="color:#d97706;"></i>Evidence / Receipt (Optional)
                </div>
                <label style="display:block;cursor:pointer;">
                    <div id="dropZone"
                         style="border:1.5px dashed #fbbf24;border-radius:8px;padding:16px 12px;text-align:center;background:white;transition:all .15s;cursor:pointer;"
                         ondragover="event.preventDefault();this.style.background='#fef9c3'" ondragleave="this.style.background='white'"
                         ondrop="handleDrop(event)">
                        <i class="bi bi-cloud-upload" style="font-size:1.4rem;color:#fbbf24;display:block;margin-bottom:6px;"></i>
                        <div style="font-size:.76rem;color:#854d0e;font-weight:600;">Click or drag file here</div>
                        <div style="font-size:.67rem;color:#a16207;margin-top:2px;">JPG, PNG, PDF — max 5 MB</div>
                        <div id="fileNameDisplay" style="display:none;margin-top:8px;font-size:.73rem;color:#15803d;font-weight:600;"></div>
                    </div>
                    <input type="file" name="evidence_file" id="evidenceInput" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                           style="display:none;" onchange="showFileName(this)">
                </label>
                <div style="margin-top:9px;">
                    <label style="display:block;font-size:.71rem;font-weight:600;color:#92400e;margin-bottom:3px;">Evidence Note</label>
                    <input type="text" name="evidence_note"
                           style="width:100%;height:34px;padding:0 10px;border:1.5px solid #fde68a;border-radius:8px;font-size:.79rem;color:#0f172a;outline:none;background:white;"
                           placeholder="e.g. Invoice #INV-2026-001 from supplier"
                           value="<?= htmlspecialchars($_POST['evidence_note'] ?? '') ?>">
                </div>
            </div>

            <button type="submit"
                    style="width:100%;padding:10px;background:linear-gradient(135deg,#d97706,#b45309);color:white;border:none;border-radius:10px;font-weight:700;font-size:.84rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;box-shadow:0 3px 10px rgba(217,119,6,.3);transition:all .18s;"
                    onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                <i class="bi bi-save-fill"></i>Save Allocation
            </button>
        </div>
    </form>

    <!-- Info card -->
    <div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fde68a;border-radius:14px;padding:16px 18px;">
        <div style="font-size:.76rem;font-weight:700;color:#854d0e;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-shield-check-fill" style="color:#d97706;"></i>Transparency Policy
        </div>
        <?php foreach([
            ['bi-eye-fill',       'Disbursed allocations are visible to donors'],
            ['bi-paperclip',      'Attach receipts as evidence for each allocation'],
            ['bi-bar-chart-fill', 'Donors can track how their money is used'],
        ] as [$ic,$txt]): ?>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:7px;font-size:.74rem;color:#92400e;">
            <i class="bi <?= $ic ?>" style="color:#d97706;font-size:.72rem;flex-shrink:0;"></i><?= $txt ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══ RIGHT: RECORDS TABLE ════════════════════ -->
<div style="background:white;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 2px 10px rgba(0,0,0,.05);overflow:hidden;">
    <div style="padding:13px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="bi bi-list-ul" style="color:#d97706;font-size:.88rem;"></i>
            <span style="font-size:.86rem;font-weight:700;color:#0f172a;">Allocation Records</span>
            <span style="background:#fef9c3;color:#854d0e;padding:2px 9px;border-radius:20px;font-size:.67rem;font-weight:700;">
                <?= $allocations ? $allocations->num_rows : 0 ?> records
            </span>
        </div>
        <!-- Campaign filter -->
        <form method="GET" style="display:flex;align-items:center;gap:7px;">
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
                    <?php foreach (['Campaign','Purpose','Amount','Date','Status','Evidence','By'] as $h): ?>
                    <th style="padding:9px 14px;text-align:left;font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#94a3b8;border-bottom:1px solid #f1f5f9;white-space:nowrap;"><?= $h ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($allocations && $allocations->num_rows > 0): while ($a = $allocations->fetch_assoc()):
                $ss = $status_styles[$a['status']] ?? $status_styles['planned'];
                $hasEvidence = !empty($a['evidence_url']);
                $isPdf = $hasEvidence && strtolower(pathinfo($a['evidence_url'], PATHINFO_EXTENSION)) === 'pdf';
            ?>
            <tr style="border-bottom:1px solid #f9fafb;transition:background .12s;"
                onmouseover="this.style.background='#fafbff'" onmouseout="this.style.background='white'">
                <td style="padding:11px 14px;max-width:130px;">
                    <div style="font-size:.77rem;font-weight:600;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($a['campaign_title']) ?></div>
                </td>
                <td style="padding:11px 14px;min-width:140px;">
                    <div style="font-size:.79rem;font-weight:600;color:#0f172a;margin-bottom:2px;"><?= htmlspecialchars($a['purpose']) ?></div>
                    <?php if ($a['description']): ?>
                    <div style="font-size:.7rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;"><?= htmlspecialchars($a['description']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="padding:11px 14px;white-space:nowrap;">
                    <span style="font-size:.84rem;font-weight:800;color:#d97706;"><?= number_format($a['amount'] / 1000000, 1) ?>M</span>
                    <span style="font-size:.67rem;color:#94a3b8;"> VND</span>
                </td>
                <td style="padding:11px 14px;font-size:.76rem;color:#64748b;white-space:nowrap;"><?= formatDate($a['allocation_date']) ?></td>
                <td style="padding:11px 14px;">
                    <span style="display:inline-flex;align-items:center;gap:4px;background:<?= $ss['bg'] ?>;color:<?= $ss['color'] ?>;border:1px solid <?= $ss['border'] ?>;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;white-space:nowrap;">
                        <?= $ss['label'] ?>
                    </span>
                </td>
                <!-- Evidence column -->
                <td style="padding:11px 14px;min-width:120px;">
                    <?php if ($hasEvidence): ?>
                        <?php if ($isPdf): ?>
                        <a href="<?= SITE_URL . '/' . htmlspecialchars($a['evidence_url']) ?>" target="_blank"
                           style="display:inline-flex;align-items:center;gap:5px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:4px 9px;border-radius:7px;font-size:.7rem;font-weight:700;text-decoration:none;"
                           title="<?= htmlspecialchars($a['evidence_note'] ?? '') ?>">
                            <i class="bi bi-file-pdf-fill"></i>PDF
                        </a>
                        <?php else: ?>
                        <a href="<?= SITE_URL . '/' . htmlspecialchars($a['evidence_url']) ?>" target="_blank"
                           title="<?= htmlspecialchars($a['evidence_note'] ?? '') ?>">
                            <img src="<?= SITE_URL . '/' . htmlspecialchars($a['evidence_url']) ?>"
                                 style="width:44px;height:36px;object-fit:cover;border-radius:6px;border:1.5px solid #e2e8f0;cursor:zoom-in;"
                                 alt="Evidence">
                        </a>
                        <?php endif; ?>
                        <?php if ($a['evidence_note']): ?>
                        <div style="font-size:.65rem;color:#94a3b8;margin-top:2px;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($a['evidence_note']) ?>">
                            <?= htmlspecialchars($a['evidence_note']) ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Upload evidence button -->
                        <button onclick="openEvidenceModal(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['purpose'])) ?>')"
                                style="display:inline-flex;align-items:center;gap:4px;background:#f8fafc;color:#64748b;border:1.5px dashed #cbd5e1;padding:4px 9px;border-radius:7px;font-size:.7rem;font-weight:600;cursor:pointer;transition:all .14s;"
                                onmouseover="this.style.background='#fffbeb';this.style.borderColor='#fbbf24';this.style.color='#854d0e'"
                                onmouseout="this.style.background='#f8fafc';this.style.borderColor='#cbd5e1';this.style.color='#64748b'">
                            <i class="bi bi-paperclip"></i>Attach
                        </button>
                    <?php endif; ?>
                </td>
                <td style="padding:11px 14px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <div style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#6d28d9);display:flex;align-items:center;justify-content:center;font-size:.62rem;font-weight:800;color:white;flex-shrink:0;">
                            <?= strtoupper(substr($a['allocated_by_name'], 0, 1)) ?>
                        </div>
                        <span style="font-size:.74rem;color:#475569;white-space:nowrap;"><?= htmlspecialchars(explode(' ', $a['allocated_by_name'])[0]) ?></span>
                    </div>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
                <td colspan="7" style="padding:48px 20px;text-align:center;">
                    <i class="bi bi-inbox" style="font-size:2.2rem;color:#e2e8f0;display:block;margin-bottom:10px;"></i>
                    <div style="font-size:.82rem;font-weight:600;color:#94a3b8;margin-bottom:3px;">No allocations yet</div>
                    <div style="font-size:.75rem;color:#cbd5e1;">Record your first fund allocation using the form</div>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div style="padding:10px 18px;border-top:1px solid #f8fafc;background:#fafafa;display:flex;align-items:center;gap:8px;">
        <i class="bi bi-shield-check-fill" style="color:#22c55e;font-size:.76rem;"></i>
        <span style="font-size:.72rem;color:#94a3b8;">
            <?= $allocations ? $allocations->num_rows : 0 ?> record<?= ($allocations && $allocations->num_rows != 1) ? 's' : '' ?>
            <?php if ($filter_campaign): ?> · Filtered by campaign<?php endif; ?>
            · Evidence-backed allocations shown to donors for transparency
        </span>
    </div>
</div>

</div>

<!-- ══ Evidence Upload Modal ══════════════════════════ -->
<div id="evidenceModal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
    <div style="background:white;border-radius:18px;padding:28px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
            <div>
                <div style="font-size:.95rem;font-weight:800;color:#0f172a;">Attach Evidence</div>
                <div id="modalPurpose" style="font-size:.76rem;color:#94a3b8;margin-top:2px;"></div>
            </div>
            <button onclick="closeEvidenceModal()"
                    style="width:28px;height:28px;background:#f1f5f9;border:none;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.8rem;color:#64748b;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="evidenceModalForm">
            <input type="hidden" name="action" value="update_evidence">
            <input type="hidden" name="alloc_id" id="modalAllocId" value="">
            <?php if ($filter_campaign): ?>
            <input type="hidden" name="campaign_id" value="<?= $filter_campaign ?>">
            <?php endif; ?>

            <!-- File upload zone -->
            <label style="display:block;cursor:pointer;margin-bottom:14px;">
                <div style="border:2px dashed #fbbf24;border-radius:10px;padding:24px;text-align:center;background:#fffbeb;transition:all .15s;"
                     ondragover="event.preventDefault();this.style.background='#fef9c3'" ondragleave="this.style.background='#fffbeb'"
                     ondrop="handleModalDrop(event)">
                    <i class="bi bi-cloud-arrow-up-fill" style="font-size:2rem;color:#fbbf24;display:block;margin-bottom:8px;"></i>
                    <div style="font-size:.82rem;font-weight:600;color:#854d0e;">Click to upload or drag & drop</div>
                    <div style="font-size:.71rem;color:#a16207;margin-top:3px;">JPG, PNG, PDF · max 5 MB</div>
                    <div id="modalFileDisplay" style="display:none;margin-top:10px;padding:6px 12px;background:white;border-radius:7px;border:1px solid #fde68a;font-size:.76rem;color:#15803d;font-weight:600;"></div>
                </div>
                <input type="file" name="evidence_file" id="modalFileInput" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                       style="display:none;" onchange="showModalFileName(this)">
            </label>

            <!-- Note -->
            <div style="margin-bottom:18px;">
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">
                    Evidence Note <span style="font-size:.67rem;color:#94a3b8;">(e.g. Invoice #INV-2026-001)</span>
                </label>
                <input type="text" name="evidence_note" id="modalNote"
                       style="width:100%;height:40px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.83rem;color:#0f172a;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#d97706'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="Describe the receipt or invoice...">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <button type="button" onclick="closeEvidenceModal()"
                        style="padding:10px;border:1.5px solid #e2e8f0;background:white;border-radius:10px;font-size:.84rem;font-weight:600;cursor:pointer;color:#64748b;">
                    Cancel
                </button>
                <button type="submit"
                        style="padding:10px;background:linear-gradient(135deg,#d97706,#b45309);color:white;border:none;border-radius:10px;font-size:.84rem;font-weight:700;cursor:pointer;box-shadow:0 3px 10px rgba(217,119,6,.3);">
                    <i class="bi bi-paperclip me-1"></i>Attach
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// File input display
function showFileName(input) {
    const d = document.getElementById('fileNameDisplay');
    if (input.files && input.files[0]) {
        d.textContent = '✓ ' + input.files[0].name;
        d.style.display = 'block';
    }
}
function handleDrop(e) {
    e.preventDefault();
    const dt = e.dataTransfer;
    if (dt.files && dt.files[0]) {
        const inp = document.getElementById('evidenceInput');
        inp.files = dt.files;
        showFileName(inp);
    }
    e.currentTarget.style.background = 'white';
}

// Modal
function openEvidenceModal(allocId, purpose) {
    document.getElementById('modalAllocId').value = allocId;
    document.getElementById('modalPurpose').textContent = 'For: ' + purpose;
    document.getElementById('modalNote').value = '';
    document.getElementById('modalFileDisplay').style.display = 'none';
    const m = document.getElementById('evidenceModal');
    m.style.display = 'flex';
    setTimeout(() => m.style.opacity = '1', 10);
}
function closeEvidenceModal() {
    document.getElementById('evidenceModal').style.display = 'none';
}
function showModalFileName(input) {
    const d = document.getElementById('modalFileDisplay');
    if (input.files && input.files[0]) {
        d.textContent = '✓ ' + input.files[0].name;
        d.style.display = 'block';
    }
}
function handleModalDrop(e) {
    e.preventDefault();
    if (e.dataTransfer.files[0]) {
        const inp = document.getElementById('modalFileInput');
        inp.files = e.dataTransfer.files;
        showModalFileName(inp);
    }
    e.currentTarget.style.background = '#fffbeb';
}
document.getElementById('evidenceModal').addEventListener('click', function(e) {
    if (e.target === this) closeEvidenceModal();
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
