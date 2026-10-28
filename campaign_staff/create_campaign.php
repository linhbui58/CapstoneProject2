<?php
$pageTitle  = 'Create Campaign';
$activePage = 'create_campaign';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('campaign_staff');

$db  = getApplicationDB();
$uid = $user['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $target      = (float)($_POST['target_amount'] ?? 0);
    $start_date  = sanitize($_POST['start_date'] ?? '');
    $end_date    = sanitize($_POST['end_date'] ?? '');
    $status      = sanitize($_POST['status'] ?? 'draft');
    $image_url   = '';
    $image_type  = $_POST['image_type'] ?? 'url';

    if ($image_type === 'url') {
        $image_url = sanitize($_POST['image_url'] ?? '');
    } elseif ($image_type === 'upload' && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0) {
        $file = $_FILES['image_file'];
        if ($file['size'] > 5*1024*1024) {
            $errors[] = 'Image size must be under 5MB.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo === false) {
                $errors[] = 'Unable to verify uploaded image.';
            } else {
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
                if (!isset($extensions[$mime])) {
                    $errors[] = 'Image must be JPG, PNG, WEBP or GIF.';
                } else {
                    $filename = 'camp_'.time().'_'.bin2hex(random_bytes(8)).'.'.$extensions[$mime];
                    $upload_dir = dirname(__DIR__).'/assets/images/campaigns/';
                    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                        $errors[] = 'Failed to prepare the image upload directory.';
                    } elseif (move_uploaded_file($file['tmp_name'], $upload_dir.$filename)) {
                        $image_url = '/assets/images/campaigns/'.$filename;
                    } else {
                        $errors[] = 'Failed to upload image.';
                    }
                }
            }
        }
    }

    if (empty($title))      $errors[] = 'Campaign title is required.';
    if ($target <= 0)       $errors[] = 'Target amount must be greater than 0.';
    if (empty($start_date)) $errors[] = 'Start date is required.';
    if (empty($end_date))   $errors[] = 'End date is required.';
    if (!empty($start_date) && !empty($end_date) && $end_date <= $start_date)
        $errors[] = 'End date must be after start date.';

    if (empty($errors)) {
        $te=$db->real_escape_string($title);
        $de=$db->real_escape_string($description);
        $ie=$db->real_escape_string($image_url);
        $se=$db->real_escape_string(in_array($status,['draft','active'])?$status:'draft');
        $db->query("INSERT INTO campaigns (title,description,target_amount,start_date,end_date,status,image_url,created_by) VALUES ('$te','$de',$target,'$start_date','$end_date','$se','$ie',$uid)");
        setFlash('success', "Campaign \"$title\" created successfully!");
        redirect(SITE_URL.'/campaign_staff/campaigns.php');
    }
}

// Unsplash suggestions
$suggestions = array(
    array('Education',  'https://images.unsplash.com/photo-1497633762265-9d179a990aa6?w=600&q=80'),
    array('Healthcare', 'https://images.unsplash.com/photo-1559757148-5c350d0d3c56?w=600&q=80'),
    array('Clean Water','https://images.unsplash.com/photo-1541544181051-e46607bc22a4?w=600&q=80'),
    array('Community',  'https://images.unsplash.com/photo-1469571486292-0ba58a3f068b?w=600&q=80'),
    array('Children',   'https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?w=600&q=80'),
    array('Elderly',    'https://images.unsplash.com/photo-1576765608535-5f04d1e3f289?w=600&q=80'),
);
?>

<!-- Header -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;gap:16px;">
    <div>
        <h1 style="font-size:1.25rem;font-weight:700;color:#0f172a;margin:0 0 3px;display:flex;align-items:center;gap:9px;">
            <i class="bi bi-plus-circle-fill" style="color:#15803d;font-size:1rem;"></i>Create New Campaign
        </h1>
        <p style="color:#64748b;font-size:.79rem;margin:0;">Fill in the details to launch a new fundraising campaign</p>
    </div>
    <a href="<?= SITE_URL ?>/campaign_staff/campaigns.php"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:9px;color:#64748b;font-size:.8rem;font-weight:600;background:white;text-decoration:none;transition:all .15s;flex-shrink:0;"
       onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
        <i class="bi bi-arrow-left"></i>Back
    </a>
</div>

<?php if(!empty($errors)): ?>
<div style="background:#fff1f2;border-left:3px solid #ef4444;padding:12px 16px;border-radius:10px;margin-bottom:18px;">
    <div style="font-weight:700;color:#b91c1c;font-size:.82rem;margin-bottom:5px;"><i class="bi bi-exclamation-circle-fill" style="margin-right:5px;"></i>Please fix the following:</div>
    <ul style="margin:0;padding-left:16px;font-size:.8rem;color:#b91c1c;"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;">

<!-- ═══ MAIN FORM ═══════════════════════════════ -->
<form method="POST" enctype="multipart/form-data">
    <!-- Step 1: Basic Info -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;margin-bottom:16px;">
        <div style="padding:13px 20px;border-bottom:1px solid #f1f5f9;background:linear-gradient(135deg,#f0fdf4,#dcfce7);display:flex;align-items:center;gap:10px;">
            <div style="width:26px;height:26px;background:#15803d;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;color:white;">1</div>
            <div>
                <div style="font-size:.84rem;font-weight:700;color:#15803d;">Basic Information</div>
                <div style="font-size:.7rem;color:#22c55e;">Campaign name and description</div>
            </div>
        </div>
        <div style="padding:20px;">
            <div style="margin-bottom:15px;">
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">Campaign Title <span style="color:#ef4444;">*</span></label>
                <input type="text" name="title" required
                       style="width:100%;height:42px;padding:0 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;color:#0f172a;outline:none;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#15803d'" onblur="this.style.borderColor='#e2e8f0'"
                       placeholder="e.g. Education for Rural Children 2026"
                       value="<?= htmlspecialchars($_POST['title']??'') ?>">
            </div>
            <div>
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">Description</label>
                <textarea name="description" rows="4"
                          style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;color:#0f172a;outline:none;transition:border-color .15s;resize:vertical;line-height:1.6;"
                          onfocus="this.style.borderColor='#15803d'" onblur="this.style.borderColor='#e2e8f0'"
                          placeholder="Describe the campaign purpose and how funds will be used..."><?= htmlspecialchars($_POST['description']??'') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Step 2: Image -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;margin-bottom:16px;">
        <div style="padding:13px 20px;border-bottom:1px solid #f1f5f9;background:linear-gradient(135deg,#faf5ff,#ede9fe);display:flex;align-items:center;gap:10px;">
            <div style="width:26px;height:26px;background:#7c3aed;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;color:white;">2</div>
            <div>
                <div style="font-size:.84rem;font-weight:700;color:#7c3aed;">Campaign Image</div>
                <div style="font-size:.7rem;color:#a78bfa;">Visual that represents your campaign</div>
            </div>
        </div>
        <div style="padding:20px;">
            <!-- Toggle -->
            <div style="display:flex;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;margin-bottom:14px;width:fit-content;">
                <button type="button" id="tabUrl" onclick="switchTab('url')"
                        style="padding:7px 16px;font-size:.78rem;font-weight:600;border:none;cursor:pointer;background:#7c3aed;color:white;transition:all .15s;">
                    <i class="bi bi-link-45deg me-1"></i>Image URL
                </button>
                <button type="button" id="tabUpload" onclick="switchTab('upload')"
                        style="padding:7px 16px;font-size:.78rem;font-weight:600;border:none;cursor:pointer;background:white;color:#475569;transition:all .15s;border-left:1px solid #e2e8f0;">
                    <i class="bi bi-upload me-1"></i>Upload File
                </button>
            </div>
            <input type="hidden" name="image_type" id="imageType" value="url">

            <!-- URL panel -->
            <div id="panelUrl">
                <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;" onfocusin="this.style.borderColor='#7c3aed'" onfocusout="this.style.borderColor='#e2e8f0'">
                    <span style="padding:0 12px;background:#f8fafc;color:#94a3b8;font-size:.82rem;border-right:1px solid #e2e8f0;height:42px;display:flex;align-items:center;">
                        <i class="bi bi-image"></i>
                    </span>
                    <input type="url" name="image_url" id="imageUrlInput"
                           style="flex:1;border:none;outline:none;padding:0 12px;height:42px;font-size:.82rem;color:#0f172a;background:white;"
                           placeholder="https://example.com/image.jpg"
                           value="<?= htmlspecialchars($_POST['image_url']??'') ?>"
                           oninput="previewUrl(this.value)">
                    <button type="button" onclick="document.getElementById('imageUrlInput').value='';clearPreview()"
                            style="padding:0 12px;border:none;background:white;color:#94a3b8;cursor:pointer;border-left:1px solid #e2e8f0;height:42px;">
                        <i class="bi bi-x-lg" style="font-size:.78rem;"></i>
                    </button>
                </div>
                <div style="font-size:.69rem;color:#94a3b8;margin-top:4px;">Paste a direct image URL. Free photos: <a href="https://unsplash.com" target="_blank" style="color:#7c3aed;">Unsplash.com</a></div>
            </div>

            <!-- Upload panel -->
            <div id="panelUpload" style="display:none;">
                <div id="dropZone" onclick="document.getElementById('imgFile').click()"
                     style="border:2px dashed #e2e8f0;border-radius:9px;padding:24px;text-align:center;cursor:pointer;background:#fafafa;transition:all .18s;"
                     ondragover="event.preventDefault();this.style.borderColor='#7c3aed';this.style.background='#faf5ff'"
                     ondragleave="this.style.borderColor='#e2e8f0';this.style.background='#fafafa'">
                    <i class="bi bi-cloud-upload" style="font-size:1.6rem;color:#94a3b8;display:block;margin-bottom:7px;"></i>
                    <div style="font-size:.82rem;font-weight:600;color:#475569;margin-bottom:3px;">Click to upload or drag & drop</div>
                    <div style="font-size:.71rem;color:#94a3b8;">JPG, PNG, WEBP — max 5MB</div>
                    <input type="file" name="image_file" id="imgFile" accept="image/*" style="display:none;" onchange="previewFile(this)">
                </div>
            </div>

            <!-- Preview -->
            <div id="imgPreview" style="display:none;margin-top:12px;">
                <div style="font-size:.73rem;font-weight:600;color:#374151;margin-bottom:5px;">Preview:</div>
                <div style="position:relative;display:inline-block;width:100%;">
                    <img id="previewImg" style="width:100%;max-height:160px;object-fit:cover;border-radius:9px;border:1.5px solid #e2e8f0;display:block;">
                    <button type="button" onclick="clearPreview()"
                            style="position:absolute;top:6px;right:6px;width:26px;height:26px;border-radius:50%;background:rgba(0,0,0,.55);color:white;border:none;cursor:pointer;font-size:.72rem;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 3: Goal & Dates -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;margin-bottom:16px;">
        <div style="padding:13px 20px;border-bottom:1px solid #f1f5f9;background:linear-gradient(135deg,#eff6ff,#dbeafe);display:flex;align-items:center;gap:10px;">
            <div style="width:26px;height:26px;background:#1447c0;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;color:white;">3</div>
            <div>
                <div style="font-size:.84rem;font-weight:700;color:#1447c0;">Goal & Timeline</div>
                <div style="font-size:.7rem;color:#3b82f6;">Fundraising target and campaign dates</div>
            </div>
        </div>
        <div style="padding:20px;">
            <!-- Target amount -->
            <div style="margin-bottom:15px;">
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">Target Amount (VND) <span style="color:#ef4444;">*</span></label>
                <div style="display:flex;align-items:center;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;height:42px;" onfocusin="this.style.borderColor='#1447c0'" onfocusout="this.style.borderColor='#e2e8f0'">
                    <span style="padding:0 12px;background:#f8fafc;color:#94a3b8;font-size:.82rem;border-right:1px solid #e2e8f0;height:100%;display:flex;align-items:center;font-weight:600;">VND</span>
                    <input type="number" name="target_amount" min="1" required
                           style="flex:1;border:none;outline:none;padding:0 13px;font-size:.86rem;font-weight:700;color:#0f172a;background:white;"
                           placeholder="e.g. 500000000"
                           value="<?= htmlspecialchars($_POST['target_amount']??'') ?>">
                </div>
                <!-- Quick amounts -->
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                    <?php foreach([50000000,100000000,200000000,500000000,1000000000] as $amt): ?>
                    <button type="button"
                            onclick="document.querySelector('[name=target_amount]').value=<?= $amt ?>"
                            style="padding:4px 11px;border:1px solid #e2e8f0;border-radius:6px;font-size:.72rem;font-weight:700;color:#475569;background:white;cursor:pointer;transition:all .14s;"
                            onmouseover="this.style.background='#eff6ff';this.style.borderColor='#bfdbfe';this.style.color='#1447c0'"
                            onmouseout="this.style.background='white';this.style.borderColor='#e2e8f0';this.style.color='#475569'">
                        <?= number_format($amt/1000000) ?>M
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Dates -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:15px;">
                <div>
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">Start Date <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="start_date" required
                           style="width:100%;height:42px;padding:0 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.83rem;color:#0f172a;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                           value="<?= htmlspecialchars($_POST['start_date']??date('Y-m-d')) ?>">
                </div>
                <div>
                    <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">End Date <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="end_date" required
                           style="width:100%;height:42px;padding:0 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.83rem;color:#0f172a;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#1447c0'" onblur="this.style.borderColor='#e2e8f0'"
                           value="<?= htmlspecialchars($_POST['end_date']??'') ?>">
                </div>
            </div>

            <!-- Status -->
            <div>
                <label style="display:block;font-size:.74rem;font-weight:600;color:#374151;margin-bottom:5px;">Initial Status</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <?php foreach([
                        ['draft','Draft','Not visible to donors yet','bi-pencil-fill','#fef9c3','#854d0e','#fde68a'],
                        ['active','Active','Visible & accepting donations','bi-play-circle-fill','#dcfce7','#15803d','#bbf7d0'],
                    ] as [$val,$label,$desc,$ic,$bg,$color,$border]):
                        $sel = ($_POST['status']??'draft')===$val;
                    ?>
                    <label style="display:flex;align-items:flex-start;gap:10px;padding:11px 13px;border:1.5px solid <?= $sel?$border:'#e2e8f0' ?>;border-radius:9px;cursor:pointer;background:<?= $sel?$bg:'white' ?>;transition:all .14s;">
                        <input type="radio" name="status" value="<?= $val ?>" <?= $sel?'checked':'' ?> style="margin-top:2px;" onchange="updateStatusCards()">
                        <div>
                            <div style="font-size:.8rem;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:5px;">
                                <i class="bi <?= $ic ?>" style="color:<?= $color ?>;font-size:.8rem;"></i><?= $label ?>
                            </div>
                            <div style="font-size:.71rem;color:#64748b;margin-top:1px;"><?= $desc ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit -->
    <button type="submit"
            style="width:100%;padding:13px;background:linear-gradient(135deg,#16a34a,#15803d);color:white;border:none;border-radius:12px;font-weight:700;font-size:.9rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 16px rgba(22,163,74,.3);transition:all .2s;letter-spacing:-.01em;"
            onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 7px 22px rgba(22,163,74,.4)'"
            onmouseout="this.style.transform='none';this.style.boxShadow='0 4px 16px rgba(22,163,74,.3)'">
        <i class="bi bi-plus-circle-fill" style="font-size:.9rem;"></i>Create Campaign
    </button>
</form>

<!-- ═══ SIDEBAR ══════════════════════════════════ -->
<div style="display:flex;flex-direction:column;gap:14px;position:sticky;top:80px;">

    <!-- Tips -->
    <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #bbf7d0;border-radius:14px;padding:16px 18px;">
        <div style="font-size:.8rem;font-weight:700;color:#15803d;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-lightbulb-fill"></i>Image Tips
        </div>
        <div style="font-size:.76rem;color:#166534;line-height:1.7;">
            <div style="margin-bottom:5px;">📐 <strong>Best size:</strong> 800×500px (16:9)</div>
            <div style="margin-bottom:5px;">🆓 <strong>Free images:</strong> <a href="https://unsplash.com" target="_blank" style="color:#15803d;font-weight:700;">Unsplash.com</a></div>
            <div>📦 <strong>Upload:</strong> Max 5MB, JPG/PNG/WEBP</div>
        </div>
    </div>

    <!-- Quick suggestions -->
    <div style="background:white;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">
        <div style="padding:12px 16px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:7px;">
            <i class="bi bi-stars" style="color:#d97706;font-size:.86rem;"></i>
            <span style="font-size:.83rem;font-weight:700;color:#0f172a;">Quick Suggestions</span>
        </div>
        <div style="padding:8px 0;">
            <?php foreach($suggestions as list($label,$url)): ?>
            <button type="button" onclick="useSuggested('<?= $url ?>')"
                    style="display:flex;align-items:center;gap:10px;width:100%;padding:8px 14px;border:none;background:white;cursor:pointer;transition:all .13s;text-align:left;"
                    onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                <img src="<?= $url ?>" style="width:42px;height:30px;object-fit:cover;border-radius:5px;flex-shrink:0;border:1px solid #e2e8f0;" onerror="this.style.display='none'">
                <span style="font-size:.79rem;font-weight:600;color:#374151;flex:1;"><?= $label ?></span>
                <i class="bi bi-arrow-right" style="color:#94a3b8;font-size:.74rem;"></i>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Required fields -->
    <div style="background:#fafafa;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;">
        <div style="font-size:.79rem;font-weight:700;color:#0f172a;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-list-check" style="color:#15803d;"></i>Required Fields
        </div>
        <?php foreach(['Campaign title','Target amount','Start date','End date'] as $f): ?>
        <div style="display:flex;align-items:center;gap:7px;margin-bottom:7px;font-size:.77rem;color:#475569;">
            <i class="bi bi-check-circle-fill" style="color:#22c55e;font-size:.76rem;flex-shrink:0;"></i><?= $f ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</div>

<script>
function switchTab(tab){
    document.getElementById('imageType').value=tab;
    const isUrl=tab==='url';
    document.getElementById('panelUrl').style.display=isUrl?'block':'none';
    document.getElementById('panelUpload').style.display=isUrl?'none':'block';
    document.getElementById('tabUrl').style.background=isUrl?'#7c3aed':'white';
    document.getElementById('tabUrl').style.color=isUrl?'white':'#475569';
    document.getElementById('tabUpload').style.background=isUrl?'white':'#7c3aed';
    document.getElementById('tabUpload').style.color=isUrl?'#475569':'white';
}
function previewUrl(url){
    if(!url){clearPreview();return;}
    const img=document.getElementById('previewImg');
    img.onload=()=>document.getElementById('imgPreview').style.display='block';
    img.onerror=()=>clearPreview();
    img.src=url;
}
function previewFile(input){
    if(!input.files[0])return;
    const reader=new FileReader();
    reader.onload=e=>{document.getElementById('previewImg').src=e.target.result;document.getElementById('imgPreview').style.display='block';};
    reader.readAsDataURL(input.files[0]);
}
function useSuggested(url){
    switchTab('url');
    document.getElementById('imageUrlInput').value=url;
    previewUrl(url);
}
function clearPreview(){
    document.getElementById('imgPreview').style.display='none';
    document.getElementById('previewImg').src='';
}
window.onload=function(){const v=document.getElementById('imageUrlInput').value;if(v)previewUrl(v);};
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
