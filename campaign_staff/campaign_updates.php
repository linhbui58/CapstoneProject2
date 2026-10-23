<?php
$pageTitle  = 'Campaign Updates';
$activePage = 'campaigns';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('campaign_staff');

$db  = getDB();
$uid = $user['id'];
$errors = [];

// Upload helper
function uploadUpdateImage(array $file): ?string {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload error: ' . $file['error']);
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed)) throw new RuntimeException('Only JPG, PNG, GIF, WebP images allowed.');
    if ($file['size'] > 5 * 1024 * 1024) throw new RuntimeException('Image must be under 5 MB.');
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $dir  = dirname(__DIR__) . '/assets/uploads/updates/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = 'upd_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) throw new RuntimeException('Failed to save image.');
    return 'assets/uploads/updates/' . $name;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $del_id = (int)($_POST['update_id'] ?? 0);
        $row = $db->query("SELECT image_url FROM campaign_updates WHERE id=$del_id AND posted_by=$uid")->fetch_assoc();
        if ($row) {
            if ($row['image_url']) {
                $p = dirname(__DIR__) . '/' . $row['image_url'];
                if (file_exists($p)) @unlink($p);
            }
            $db->query("DELETE FROM campaign_updates WHERE id=$del_id AND posted_by=$uid");
            setFlash('success', 'Update deleted.');
        }
        redirect(SITE_URL . '/campaign_staff/campaign_updates.php?campaign_id=' . (int)($_POST['campaign_id'] ?? 0));
    }

    $campaign_id = (int)($_POST['campaign_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $content     = trim($_POST['content'] ?? '');
    $published   = isset($_POST['is_published']) ? 1 : 0;

    if (!$campaign_id) $errors[] = 'Please select a campaign.';
    if (empty($title))   $errors[] = 'Title is required.';
    if (empty($content)) $errors[] = 'Content is required.';

    $image_url = null;
    if (empty($errors)) {
        try { $image_url = uploadUpdateImage($_FILES['update_image'] ?? ['error' => UPLOAD_ERR_NO_FILE]); }
        catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
    }

    if (empty($errors)) {
        $t = $db->real_escape_string($title);
        $c = $db->real_escape_string($content);
        $u = $image_url ? "'".$db->real_escape_string($image_url)."'" : 'NULL';
        $db->query("INSERT INTO campaign_updates (campaign_id, title, content, image_url, posted_by, is_published)
                    VALUES ($campaign_id, '$t', '$c', $u, $uid, $published)");

        // Notify donors who donated to this campaign
        $donors = $db->query("SELECT DISTINCT donor_id FROM donations WHERE campaign_id=$campaign_id AND status='verified'");
        $camp_title = $db->query("SELECT title FROM campaigns WHERE id=$campaign_id")->fetch_assoc()['title'] ?? '';
        $ct = $db->real_escape_string($camp_title);
        $tt = $db->real_escape_string($title);
        if ($donors) {
            while ($d = $donors->fetch_assoc()) {
                $did = (int)$d['donor_id'];
                $db->query("INSERT INTO notifications (user_id, title, message, type)
                            VALUES ($did,
                                    'Campaign Update: $ct',
                                    'New update posted: $tt. Check the campaign page for details.',
                                    'campaign_update')");
            }
        }

        setFlash('success', 'Update published! Donors have been notified.');
        redirect(SITE_URL . '/campaign_staff/campaign_updates.php?campaign_id=' . $campaign_id);
    }
}

$filter_campaign = (int)($_GET['campaign_id'] ?? 0);
$campaigns = $db->query("SELECT id, title FROM campaigns WHERE status IN ('active','completed') ORDER BY title");

$where = $filter_campaign ? "WHERE cu.campaign_id=$filter_campaign" : '';
$updates = $db->query("
    SELECT cu.*, c.title as campaign_title, u.name as posted_by_name
    FROM campaign_updates cu
    JOIN campaigns c ON cu.campaign_id = c.id
    JOIN users u ON cu.posted_by = u.id
    $where
    ORDER BY cu.created_at DESC
");
?>

<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-megaphone-fill" style="color:#7c3aed;font-size:1rem;"></i>Campaign Updates
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">Post progress updates — donors are notified automatically</p>
    </div>
</div>

<?php if ($errors): ?>
<div style="background:#fff1f2;border-left:3px solid #ef4444;padding:12px 16px;border-radius:10px;margin-bottom:16px;">
    <div style="font-weight:700;color:#b91c1c;font-size:.79rem;margin-bottom:4px;"><i class="bi bi-exclamation-circle-fill me-1"></i>Please fix:</div>
    <ul style="margin:0;padding-left:16px;font-size:.78rem;color:#b91c1c;"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start;">

<!-- ═══ LEFT: FORM ══════════════════════════════ -->
<div style="position:sticky;top:80px;">
    <form method="POST" enctype="multipart/form-data"
          style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);">
        <input type="hidden" name="action" value="create">
        <div style="background:linear-gradient(135deg,#7c3aed,#6d28d9);padding:14px 20px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-plus-circle-fill" style="color:white;font-size:.88rem;"></i>
            <div>
                <div style="font-size:.85rem;font-weight:700;color:white;">Post New Update</div>
                <div style="font-size:.71rem;color:rgba(255,255,255,.65);">Donors will receive a notification</div>
            </div>
        </div>
        <div style="padding:18px;">
            <!-- Campaign -->
            <div style="margin-bottom:13px;">
                <label style="display:block;font-size:.73rem;font-weight:700;color:#374151;margin-bottom:5px;">
                    Campaign <span style="color:#ef4444;">*</span>
                </label>
                <select name="campaign_id"
                        style="width:100%;height:40px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.83rem;color:#0f172a;background:white;outline:none;transition:border-color .15s;cursor:pointer;"
                        onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'">
                    <option value="">Select campaign...</option>
                    <?php if ($campaigns): $campaigns->data_seek(0); while ($c = $campaigns->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= ($filter_campaign == $c['id'] || ($_POST['campaign_id'] ?? 0) == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars(mb_strimwidth($c['title'], 0, 32, '…')) ?>
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>

            <!-- Title -->
            <div style="margin-bottom:13px;">
                <label style="display:block;font-size:.73rem;font-weight:700;color:#374151;margin-bottom:5px;">
                    Update Title <span style="color:#ef4444;">*</span>
                </label>
                <input type="text" name="title"
                       style="width:100%;height:40px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.83rem;color:#0f172a;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="e.g. 15 children successfully operated"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>

            <!-- Content -->
            <div style="margin-bottom:13px;">
                <label style="display:block;font-size:.73rem;font-weight:700;color:#374151;margin-bottom:5px;">
                    Content <span style="color:#ef4444;">*</span>
                </label>
                <textarea name="content" rows="5"
                          style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;color:#0f172a;outline:none;transition:border-color .15s;resize:vertical;line-height:1.55;"
                          onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'"
                          placeholder="Describe the progress in detail..."><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
            </div>

            <!-- Image upload -->
            <div style="margin-bottom:13px;">
                <label style="display:block;font-size:.73rem;font-weight:700;color:#374151;margin-bottom:5px;">
                    <i class="bi bi-image" style="color:#7c3aed;"></i> Attach Photo (Optional)
                </label>
                <label style="display:block;cursor:pointer;">
                    <div style="border:1.5px dashed #e2e8f0;border-radius:9px;padding:16px;text-align:center;background:#fafbff;transition:all .15s;"
                         ondragover="event.preventDefault();this.style.borderColor='#7c3aed'" ondragleave="this.style.borderColor='#e2e8f0'">
                        <i class="bi bi-cloud-upload" style="font-size:1.3rem;color:#c4b5fd;display:block;margin-bottom:5px;"></i>
                        <div style="font-size:.75rem;color:#7c3aed;font-weight:600;">Click to upload photo</div>
                        <div style="font-size:.67rem;color:#94a3b8;margin-top:2px;">JPG, PNG · max 5 MB</div>
                        <div id="imgName" style="display:none;margin-top:7px;font-size:.72rem;color:#15803d;font-weight:600;"></div>
                    </div>
                    <input type="file" name="update_image" accept=".jpg,.jpeg,.png,.gif,.webp"
                           style="display:none;" onchange="document.getElementById('imgName').textContent='✓ '+this.files[0].name;document.getElementById('imgName').style.display='block'">
                </label>
            </div>

            <!-- Published toggle -->
            <label style="display:flex;align-items:center;gap:9px;cursor:pointer;padding:10px 12px;background:#faf5ff;border-radius:9px;margin-bottom:14px;border:1.5px solid #ede9fe;">
                <input type="checkbox" name="is_published" checked
                       style="width:15px;height:15px;accent-color:#7c3aed;cursor:pointer;">
                <div>
                    <div style="font-size:.8rem;font-weight:600;color:#374151;">Publish immediately</div>
                    <div style="font-size:.7rem;color:#94a3b8;">Donors will be notified right away</div>
                </div>
            </label>

            <button type="submit"
                    style="width:100%;padding:11px;background:linear-gradient(135deg,#7c3aed,#6d28d9);color:white;border:none;border-radius:10px;font-weight:700;font-size:.86rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;box-shadow:0 3px 12px rgba(124,58,237,.3);transition:all .18s;"
                    onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                <i class="bi bi-send-fill"></i>Post Update
            </button>
        </div>
    </form>
</div>

<!-- ═══ RIGHT: UPDATES LIST ═════════════════════ -->
<div>
    <!-- Filter -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
        <div style="font-size:.84rem;font-weight:700;color:#0f172a;">
            <?= $updates ? $updates->num_rows : 0 ?> update<?= ($updates && $updates->num_rows != 1) ? 's' : '' ?>
        </div>
        <form method="GET" style="display:flex;gap:7px;">
            <select name="campaign_id" onchange="this.form.submit()"
                    style="height:34px;border:1.5px solid #e2e8f0;border-radius:8px;padding:0 11px;font-size:.79rem;color:#475569;background:white;outline:none;cursor:pointer;max-width:220px;">
                <option value="">All Campaigns</option>
                <?php if ($campaigns): $campaigns->data_seek(0); while ($c = $campaigns->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>" <?= $filter_campaign == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(mb_strimwidth($c['title'], 0, 28, '…')) ?>
                </option>
                <?php endwhile; endif; ?>
            </select>
            <?php if ($filter_campaign): ?>
            <a href="<?= SITE_URL ?>/campaign_staff/campaign_updates.php"
               style="height:34px;padding:0 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.79rem;color:#64748b;background:white;display:flex;align-items:center;gap:4px;text-decoration:none;">
                <i class="bi bi-x-lg" style="font-size:.7rem;"></i>Clear
            </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($updates && $updates->num_rows > 0): ?>
    <div style="display:flex;flex-direction:column;gap:14px;">
    <?php while ($u = $updates->fetch_assoc()): ?>
    <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:box-shadow .2s;"
         onmouseover="this.style.boxShadow='0 6px 20px rgba(0,0,0,.09)'" onmouseout="this.style.boxShadow='0 2px 8px rgba(0,0,0,.04)'">
        <?php if ($u['image_url']): ?>
        <img src="<?= SITE_URL . '/' . htmlspecialchars($u['image_url']) ?>"
             style="width:100%;height:180px;object-fit:cover;display:block;"
             alt="<?= htmlspecialchars($u['title']) ?>">
        <?php endif; ?>
        <div style="padding:16px 18px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:8px;">
                <div style="flex:1;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                        <span style="font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#7c3aed;background:#ede9fe;padding:2px 9px;border-radius:20px;">
                            <?= htmlspecialchars(mb_strimwidth($u['campaign_title'], 0, 25, '…')) ?>
                        </span>
                        <?php if ($u['is_published']): ?>
                        <span style="font-size:.66rem;font-weight:700;color:#15803d;background:#dcfce7;padding:2px 8px;border-radius:20px;">
                            <i class="bi bi-broadcast" style="font-size:.62rem;"></i> Published
                        </span>
                        <?php else: ?>
                        <span style="font-size:.66rem;font-weight:700;color:#854d0e;background:#fef9c3;padding:2px 8px;border-radius:20px;">Draft</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:.9rem;font-weight:700;color:#0f172a;line-height:1.35;"><?= htmlspecialchars($u['title']) ?></div>
                </div>
                <!-- Delete -->
                <form method="POST" onsubmit="return confirm('Delete this update?')" style="flex-shrink:0;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="update_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="campaign_id" value="<?= $filter_campaign ?>">
                    <button type="submit"
                            style="width:28px;height:28px;background:#fff1f2;border:1px solid #fecaca;border-radius:7px;color:#ef4444;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.76rem;transition:all .14s;"
                            onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff1f2'">
                        <i class="bi bi-trash3-fill"></i>
                    </button>
                </form>
            </div>

            <p style="font-size:.8rem;color:#475569;line-height:1.65;margin:0 0 12px;"><?= nl2br(htmlspecialchars($u['content'])) ?></p>

            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
                <div style="display:flex;align-items:center;gap:7px;">
                    <div style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#6d28d9);display:flex;align-items:center;justify-content:center;font-size:.62rem;font-weight:800;color:white;">
                        <?= strtoupper(substr($u['posted_by_name'], 0, 1)) ?>
                    </div>
                    <span style="font-size:.73rem;color:#64748b;"><?= htmlspecialchars($u['posted_by_name']) ?></span>
                </div>
                <span style="font-size:.71rem;color:#94a3b8;"><?= formatDateTime($u['created_at']) ?></span>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:52px 20px;text-align:center;">
        <i class="bi bi-megaphone" style="font-size:2.5rem;color:#e2e8f0;display:block;margin-bottom:12px;"></i>
        <div style="font-size:.84rem;font-weight:600;color:#94a3b8;margin-bottom:4px;">No updates yet</div>
        <div style="font-size:.76rem;color:#cbd5e1;">Post the first update to keep donors informed</div>
    </div>
    <?php endif; ?>
</div>

</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
