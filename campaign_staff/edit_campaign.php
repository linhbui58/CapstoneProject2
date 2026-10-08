<?php
$pageTitle  = 'Edit Campaign';
$activePage = 'campaigns';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('campaign_staff');

$db     = getDB();
$id     = (int)($_GET['id'] ?? 0);
$errors = [];

$campaign = $db->query("SELECT * FROM campaigns WHERE id=$id LIMIT 1")->fetch_assoc();
if (!$campaign) {
    setFlash('danger', 'Campaign not found.');
    redirect(SITE_URL . '/campaign_staff/campaigns.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $target      = (float)($_POST['target_amount'] ?? 0);
    $start_date  = sanitize($_POST['start_date'] ?? '');
    $end_date    = sanitize($_POST['end_date'] ?? '');
    $status      = sanitize($_POST['status'] ?? 'draft');
    $image_url   = $campaign['image_url']; // keep existing by default
    $image_type  = $_POST['image_type'] ?? 'url';

    if ($image_type === 'url') {
        $image_url = sanitize($_POST['image_url'] ?? '');
    } elseif ($image_type === 'upload' && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0) {
        $file    = $_FILES['image_file'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Image must be JPG, PNG, WEBP or GIF.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image size must be under 5MB.';
        } else {
            $filename = 'camp_' . time() . '_' . uniqid() . '.' . $ext;
            $dest     = dirname(__DIR__) . '/assets/images/campaigns/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $image_url = SITE_URL . '/assets/images/campaigns/' . $filename;
            } else {
                $errors[] = 'Failed to upload image.';
            }
        }
    } elseif ($image_type === 'remove') {
        $image_url = '';
    }

    if (empty($title))      $errors[] = 'Title is required.';
    if ($target <= 0)       $errors[] = 'Target amount must be > 0.';
    if (empty($start_date) || empty($end_date)) $errors[] = 'Dates are required.';
    if (!empty($start_date) && !empty($end_date) && $end_date <= $start_date)
        $errors[] = 'End date must be after start date.';

    if (empty($errors)) {
        $te  = $db->real_escape_string($title);
        $de  = $db->real_escape_string($description);
        $se  = $db->real_escape_string($status);
        $ie  = $db->real_escape_string($image_url);
        $db->query("UPDATE campaigns SET title='$te', description='$de', target_amount=$target,
                    start_date='$start_date', end_date='$end_date', status='$se', image_url='$ie'
                    WHERE id=$id");
        setFlash('success', "Campaign updated successfully!");
        redirect(SITE_URL . '/campaign_staff/campaigns.php');
    }

    // Merge POST back for re-display
    $campaign = array_merge($campaign, $_POST, ['image_url' => $image_url]);
}

$current_img = $campaign['image_url'] ?? '';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-pencil me-2" style="color:#d97706;"></i>Edit Campaign</h1>
        <p style="color:#64748b;font-size:.8rem;margin:2px 0 0;">Update campaign details and image</p>
    </div>
    <a href="<?= SITE_URL ?>/campaign_staff/campaigns.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:9px;color:#64748b;font-size:.81rem;font-weight:600;background:white;transition:all .15s;text-decoration:none;">
        <i class="bi bi-arrow-left"></i>Back
    </a>
</div>

<?php if (!empty($errors)): ?>
<div style="background:#fff1f2;border-left:3px solid #ef4444;padding:12px 16px;border-radius:10px;margin-bottom:18px;">
    <div style="font-weight:700;color:#b91c1c;font-size:.82rem;margin-bottom:5px;">
        <i class="bi bi-exclamation-circle-fill me-2"></i>Please fix the following:
    </div>
    <ul style="margin:0;padding-left:18px;color:#b91c1c;font-size:.8rem;">
        <?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;">

    <!-- Main form -->
    <form method="POST" enctype="multipart/form-data"
          style="background:white;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">

        <div style="padding:14px 20px;border-bottom:1px solid #f1f5f9;font-size:.86rem;font-weight:700;color:#0f172a;">
            <i class="bi bi-megaphone me-2" style="color:#d97706;"></i>Campaign Details
        </div>
        <div style="padding:22px;">

            <!-- Title -->
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">
                    Campaign Title <span style="color:#ef4444;">*</span>
                </label>
                <input type="text" name="title" required
                       style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;color:#0f172a;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                       value="<?= htmlspecialchars($campaign['title']) ?>">
            </div>

            <!-- Description -->
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">Description</label>
                <textarea name="description" rows="4"
                          style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;color:#0f172a;outline:none;transition:border-color .15s;resize:vertical;"
                          onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"><?= htmlspecialchars($campaign['description']) ?></textarea>
            </div>

            <!-- ══ IMAGE SECTION ══ -->
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:8px;">
                    Campaign Image
                </label>

                <!-- Current image -->
                <?php if ($current_img): ?>
                <div id="currentImgWrap" style="margin-bottom:12px;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
                    <div style="font-size:.73rem;font-weight:600;color:#374151;margin-bottom:7px;">Current image:</div>
                    <div style="position:relative;display:inline-block;">
                        <img src="<?= htmlspecialchars($current_img) ?>"
                             style="width:100%;max-height:160px;object-fit:cover;border-radius:8px;display:block;"
                             onerror="this.parentElement.innerHTML='<div style=\'padding:12px;color:#94a3b8;font-size:.78rem;\'>Image not available</div>'">
                    </div>
                    <button type="button" onclick="confirmRemove()"
                            style="margin-top:8px;padding:5px 12px;border:1px solid #fecaca;border-radius:7px;background:#fff1f2;color:#b91c1c;font-size:.74rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;transition:all .14s;"
                            onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff1f2'">
                        <i class="bi bi-trash3"></i>Remove image
                    </button>
                </div>
                <?php endif; ?>

                <!-- Toggle tabs -->
                <div style="display:flex;gap:0;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;margin-bottom:12px;width:fit-content;">
                    <button type="button" id="tabUrl"
                            onclick="switchTab('url')"
                            style="padding:7px 16px;font-size:.78rem;font-weight:600;border:none;cursor:pointer;background:#1447c0;color:white;transition:all .15s;">
                        <i class="bi bi-link-45deg me-1"></i>Image URL
                    </button>
                    <button type="button" id="tabUpload"
                            onclick="switchTab('upload')"
                            style="padding:7px 16px;font-size:.78rem;font-weight:600;border:none;cursor:pointer;background:white;color:#475569;transition:all .15s;border-left:1px solid #e2e8f0;">
                        <i class="bi bi-upload me-1"></i>Upload File
                    </button>
                </div>

                <input type="hidden" name="image_type" id="imageType" value="url">

                <!-- URL panel -->
                <div id="panelUrl">
                    <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;" onfocusin="this.style.borderColor='#1447c0'" onfocusout="this.style.borderColor='#e2e8f0'">
                        <span style="padding:9px 12px;background:#f8fafc;color:#94a3b8;font-size:.82rem;border-right:1px solid #e2e8f0;">
                            <i class="bi bi-image"></i>
                        </span>
                        <input type="url" name="image_url" id="imageUrlInput"
                               style="flex:1;border:none;outline:none;padding:9px 12px;font-size:.82rem;color:#0f172a;background:white;"
                               placeholder="https://example.com/image.jpg"
                               value="<?= htmlspecialchars($current_img) ?>"
                               oninput="previewUrl(this.value)">
                        <button type="button" onclick="document.getElementById('imageUrlInput').value='';clearNewPreview()"
                                style="padding:9px 12px;border:none;background:white;color:#94a3b8;cursor:pointer;border-left:1px solid #e2e8f0;">
                            <i class="bi bi-x-lg" style="font-size:.8rem;"></i>
                        </button>
                    </div>
                    <div style="font-size:.7rem;color:#94a3b8;margin-top:4px;">
                        Leave empty to keep current image. Free photos: <a href="https://unsplash.com" target="_blank" style="color:#1447c0;">Unsplash.com</a>
                    </div>
                </div>

                <!-- Upload panel -->
                <div id="panelUpload" style="display:none;">
                    <div id="dropZone"
                         style="border:2px dashed #e2e8f0;border-radius:9px;padding:24px 20px;text-align:center;cursor:pointer;transition:all .2s;background:#fafafa;"
                         onclick="document.getElementById('imageFile').click()"
                         ondragover="event.preventDefault();this.style.borderColor='#1447c0';this.style.background='#eff6ff'"
                         ondragleave="this.style.borderColor='#e2e8f0';this.style.background='#fafafa'"
                         ondrop="handleDrop(event)">
                        <i class="bi bi-cloud-upload" style="font-size:1.6rem;color:#94a3b8;display:block;margin-bottom:7px;"></i>
                        <div style="font-size:.82rem;font-weight:600;color:#475569;margin-bottom:3px;">Click to upload or drag & drop</div>
                        <div style="font-size:.71rem;color:#94a3b8;">JPG, PNG, WEBP — max 5MB</div>
                        <input type="file" name="image_file" id="imageFile" accept="image/*"
                               style="display:none;" onchange="previewFile(this)">
                    </div>
                </div>

                <!-- New image preview -->
                <div id="newPreview" style="display:none;margin-top:12px;">
                    <div style="font-size:.73rem;font-weight:600;color:#374151;margin-bottom:5px;">New image preview:</div>
                    <img id="newPreviewImg"
                         style="width:100%;max-height:150px;object-fit:cover;border-radius:9px;border:1.5px solid #bbf7d0;display:block;">
                </div>
            </div>
            <!-- ══ END IMAGE ══ -->

            <!-- Target amount -->
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">
                    Target Amount (VND) <span style="color:#ef4444;">*</span>
                </label>
                <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;" onfocusin="this.style.borderColor='#1447c0'" onfocusout="this.style.borderColor='#e2e8f0'">
                    <span style="padding:9px 12px;background:#f8fafc;color:#94a3b8;font-size:.82rem;border-right:1px solid #e2e8f0;">VND</span>
                    <input type="number" name="target_amount" min="1" required
                           style="flex:1;border:none;outline:none;padding:9px 12px;font-size:.84rem;color:#0f172a;background:white;"
                           value="<?= htmlspecialchars($campaign['target_amount']) ?>">
                </div>
            </div>

            <!-- Dates -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">
                        Start Date <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="date" name="start_date" required
                           style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;color:#0f172a;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                           value="<?= htmlspecialchars($campaign['start_date']) ?>">
                </div>
                <div>
                    <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">
                        End Date <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="date" name="end_date" required
                           style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;color:#0f172a;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                           value="<?= htmlspecialchars($campaign['end_date']) ?>">
                </div>
            </div>

            <!-- Status -->
            <div style="margin-bottom:22px;">
                <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:5px;">Status</label>
                <select name="status"
                        style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;color:#0f172a;background:white;outline:none;transition:border-color .15s;"
                        onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'">
                    <?php foreach(['draft'=>'Draft (not visible)','active'=>'Active','completed'=>'Completed','closed'=>'Closed'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= $campaign['status']===$v?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Submit -->
            <div style="display:flex;gap:12px;">
                <button type="submit"
                        style="flex:1;padding:11px;background:linear-gradient(135deg,#d97706,#b45309);color:white;border:none;border-radius:10px;font-weight:700;font-size:.87rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;box-shadow:0 4px 14px rgba(217,119,6,.3);transition:all .18s;"
                        onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">
                    <i class="bi bi-save-fill"></i>Save Changes
                </button>
                <a href="<?= SITE_URL ?>/campaign_staff/campaigns.php"
                   style="padding:11px 20px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:.84rem;font-weight:600;color:#475569;background:white;text-decoration:none;display:flex;align-items:center;">
                    Cancel
                </a>
            </div>

        </div>
    </form>

    <!-- Right sidebar -->
    <div style="display:flex;flex-direction:column;gap:14px;position:sticky;top:80px;">

        <!-- Campaign info -->
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:14px;padding:18px;">
            <div style="font-size:.8rem;font-weight:700;color:#854d0e;margin-bottom:10px;">
                <i class="bi bi-info-circle me-2"></i>Campaign Info
            </div>
            <div style="font-size:.76rem;color:#92400e;line-height:1.7;">
                <div style="margin-bottom:5px;"><strong>ID:</strong> #<?= $campaign['id'] ?></div>
                <div style="margin-bottom:5px;"><strong>Raised:</strong> <?= formatCurrency($campaign['raised_amount']) ?></div>
                <div style="margin-bottom:5px;"><strong>Target:</strong> <?= formatCurrency($campaign['target_amount']) ?></div>
                <div><strong>Progress:</strong> <?= $campaign['target_amount'] > 0 ? round(($campaign['raised_amount']/$campaign['target_amount'])*100,1) : 0 ?>%</div>
            </div>
        </div>

        <!-- Image suggestions -->
        <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;padding:18px;">
            <div style="font-size:.8rem;font-weight:700;color:#0f172a;margin-bottom:10px;">
                <i class="bi bi-stars me-2" style="color:#d97706;"></i>Quick Suggestions
            </div>
            <?php
            $suggestions = [
                ['Education', 'https://images.unsplash.com/photo-1497633762265-9d179a990aa6?w=600&q=80'],
                ['Healthcare', 'https://images.unsplash.com/photo-1559757148-5c350d0d3c56?w=600&q=80'],
                ['Clean Water', 'https://images.unsplash.com/photo-1541544181051-e46607bc22a4?w=600&q=80'],
                ['Community',  'https://images.unsplash.com/photo-1469571486292-0ba58a3f068b?w=600&q=80'],
                ['Children',   'https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?w=600&q=80'],
                ['Elderly',    'https://images.unsplash.com/photo-1576765608535-5f04d1e3f289?w=600&q=80'],
            ];
            foreach($suggestions as [$label,$url]): ?>
            <button type="button" onclick="useSuggested('<?= $url ?>')"
                    style="display:flex;align-items:center;gap:9px;width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;background:white;cursor:pointer;margin-bottom:6px;transition:all .14s;text-align:left;"
                    onmouseover="this.style.background='#f8fafc';this.style.borderColor='#bfdbfe'"
                    onmouseout="this.style.background='white';this.style.borderColor='#e2e8f0'">
                <img src="<?= $url ?>" style="width:40px;height:28px;object-fit:cover;border-radius:5px;flex-shrink:0;"
                     onerror="this.style.display='none'">
                <span style="font-size:.76rem;font-weight:600;color:#374151;"><?= $label ?></span>
                <i class="bi bi-arrow-right" style="color:#94a3b8;font-size:.75rem;margin-left:auto;"></i>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('imageType').value = tab;
    const isUrl = tab === 'url';
    document.getElementById('panelUrl').style.display    = isUrl ? 'block' : 'none';
    document.getElementById('panelUpload').style.display = isUrl ? 'none'  : 'block';
    document.getElementById('tabUrl').style.background    = isUrl ? '#1447c0' : 'white';
    document.getElementById('tabUrl').style.color         = isUrl ? 'white'   : '#475569';
    document.getElementById('tabUpload').style.background = isUrl ? 'white'   : '#1447c0';
    document.getElementById('tabUpload').style.color      = isUrl ? '#475569' : 'white';
}

function previewUrl(url) {
    if (!url) { clearNewPreview(); return; }
    const img = document.getElementById('newPreviewImg');
    img.onload  = () => document.getElementById('newPreview').style.display = 'block';
    img.onerror = () => clearNewPreview();
    img.src = url;
}

function previewFile(input) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('newPreviewImg').src = e.target.result;
        document.getElementById('newPreview').style.display = 'block';
        document.getElementById('dropZone').style.borderColor = '#16a34a';
        document.getElementById('dropZone').style.background  = '#f0fdf4';
        document.getElementById('dropZone').innerHTML =
            '<i class="bi bi-check-circle-fill" style="font-size:1.4rem;color:#16a34a;display:block;margin-bottom:5px;"></i>' +
            '<div style="font-size:.82rem;font-weight:600;color:#15803d;">' + input.files[0].name + '</div>' +
            '<input type="file" name="image_file" id="imageFile" accept="image/*" style="display:none;" onchange="previewFile(this)">';
    };
    reader.readAsDataURL(input.files[0]);
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('dropZone').style.borderColor = '#e2e8f0';
    document.getElementById('dropZone').style.background  = '#fafafa';
    const file = e.dataTransfer.files[0];
    if (!file || !file.type.startsWith('image/')) return;
    const dt = new DataTransfer();
    dt.items.add(file);
    document.getElementById('imageFile').files = dt.files;
    previewFile(document.getElementById('imageFile'));
}

function useSuggested(url) {
    switchTab('url');
    document.getElementById('imageUrlInput').value = url;
    previewUrl(url);
}

function clearNewPreview() {
    document.getElementById('newPreview').style.display = 'none';
    document.getElementById('newPreviewImg').src = '';
}

function confirmRemove() {
    if (confirm('Remove the current campaign image?')) {
        document.getElementById('imageType').value = 'remove';
        document.getElementById('currentImgWrap').style.display = 'none';
        document.getElementById('imageUrlInput').value = '';
        clearNewPreview();
    }
}

// Init
window.onload = function() {
    const v = document.getElementById('imageUrlInput').value;
    if (v) {
        // Show preview only if URL changed from current
    }
};
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
