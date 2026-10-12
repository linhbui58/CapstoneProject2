<?php
/**
 * VCF Wireframe Generator
 * Captures each page as static HTML using output buffering
 * Access: http://localhost/CapstoneProject2/wireframes/generate.php
 */

// Fake sessions for each role to render pages
$pages = [
    // [filename, role, user_id, user_name, user_email]
    ['manager-dashboard',    'manager',        1, 'Nguyen Thi Man',  'manager@vcf.org'],
    ['manager-campaigns',    'manager',        1, 'Nguyen Thi Man',  'manager@vcf.org'],
    ['manager-donations',    'manager',        1, 'Nguyen Thi Man',  'manager@vcf.org'],
    ['manager-reports',      'manager',        1, 'Nguyen Thi Man',  'manager@vcf.org'],
    ['manager-users',        'manager',        1, 'Nguyen Thi Man',  'manager@vcf.org'],
    ['manager-segmentation', 'manager',        1, 'Nguyen Thi Man',  'manager@vcf.org'],
    ['manager-prediction',   'manager',        1, 'Nguyen Thi Man',  'manager@vcf.org'],
    ['finance-dashboard',    'finance_staff',  2, 'Tran Van Duc',    'finance@vcf.org'],
    ['finance-transactions', 'finance_staff',  2, 'Tran Van Duc',    'finance@vcf.org'],
    ['finance-pending',      'finance_staff',  2, 'Tran Van Duc',    'finance@vcf.org'],
    ['finance-history',      'finance_staff',  2, 'Tran Van Duc',    'finance@vcf.org'],
    ['campaign-dashboard',   'campaign_staff', 3, 'Ngo Gia Nguyen',  'campaign@vcf.org'],
    ['campaign-list',        'campaign_staff', 3, 'Ngo Gia Nguyen',  'campaign@vcf.org'],
    ['campaign-create',      'campaign_staff', 3, 'Ngo Gia Nguyen',  'campaign@vcf.org'],
    ['campaign-fund',        'campaign_staff', 3, 'Ngo Gia Nguyen',  'campaign@vcf.org'],
    ['donor-dashboard',      'donor',          4, 'Do Thu Ha',       'donor@vcf.org'],
    ['donor-my-donations',   'donor',          4, 'Do Thu Ha',       'donor@vcf.org'],
    ['donor-impact',         'donor',          4, 'Do Thu Ha',       'donor@vcf.org'],
];

$url_map = [
    'manager-dashboard'    => '/CapstoneProject2/manager/dashboard.php',
    'manager-campaigns'    => '/CapstoneProject2/manager/campaigns.php',
    'manager-donations'    => '/CapstoneProject2/manager/donations.php',
    'manager-reports'      => '/CapstoneProject2/manager/reports.php',
    'manager-users'        => '/CapstoneProject2/manager/users.php',
    'manager-segmentation' => '/CapstoneProject2/manager/donor_segmentation.php',
    'manager-prediction'   => '/CapstoneProject2/manager/campaign_prediction.php',
    'finance-dashboard'    => '/CapstoneProject2/finance_staff/dashboard.php',
    'finance-transactions' => '/CapstoneProject2/finance_staff/transactions.php',
    'finance-pending'      => '/CapstoneProject2/finance_staff/pending.php',
    'finance-history'      => '/CapstoneProject2/finance_staff/history.php',
    'campaign-dashboard'   => '/CapstoneProject2/campaign_staff/dashboard.php',
    'campaign-list'        => '/CapstoneProject2/campaign_staff/campaigns.php',
    'campaign-create'      => '/CapstoneProject2/campaign_staff/create_campaign.php',
    'campaign-fund'        => '/CapstoneProject2/campaign_staff/fund_allocation.php',
    'donor-dashboard'      => '/CapstoneProject2/donor/dashboard.php',
    'donor-my-donations'   => '/CapstoneProject2/donor/my_donations.php',
    'donor-impact'         => '/CapstoneProject2/donor/impact_summary.php',
];

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>VCF Wireframe Generator</title>';
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">';
echo '<style>body{font-family:-apple-system,sans-serif;background:#0a1628;color:white;padding:32px;}
.card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:20px;margin-bottom:16px;}
.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border:none;border-radius:9px;font-size:.83rem;font-weight:700;cursor:pointer;text-decoration:none;margin-top:12px;}
.link-btn{padding:7px 14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:white;border-radius:7px;font-size:.75rem;text-decoration:none;display:inline-flex;align-items:center;gap:5px;margin:4px;}
.link-btn:hover{background:rgba(255,255,255,.15);}
h2{font-size:1.2rem;font-weight:700;margin-bottom:6px;}
p{font-size:.82rem;color:rgba(255,255,255,.55);line-height:1.65;}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:12px;}
</style></head><body>';

echo '<h2 style="font-size:1.5rem;margin-bottom:4px;">VCF Wireframe Generator</h2>';
echo '<p style="margin-bottom:24px;">Since pages require login, use these direct links with pre-set sessions, OR use the manual screenshot method below.</p>';

echo '<div class="card">';
echo '<h2>⚡ Method 1 — Direct Links (Login required)</h2>';
echo '<p>Login to each role first, then click the page links:</p>';

$roles = [
    'manager'        => ['❤️ Manager',        'manager@vcf.org',  '#fee2e2','#991b1b'],
    'finance_staff'  => ['💛 Finance Staff',   'finance@vcf.org',  '#fffbeb','#854d0e'],
    'campaign_staff' => ['💚 Campaign Staff',  'campaign@vcf.org', '#f0fdf4','#15803d'],
    'donor'          => ['💙 Donor',           'donor@vcf.org',    '#eff6ff','#1e40af'],
];

foreach($roles as $role => [$label, $email, $bg, $color]) {
    echo "<div style='margin-bottom:16px;padding:14px;background:rgba(255,255,255,.04);border-radius:10px;border:1px solid rgba(255,255,255,.06);'>";
    echo "<div style='font-size:.82rem;font-weight:700;color:white;margin-bottom:8px;'>$label — Login: <code style='background:$bg;color:$color;padding:1px 8px;border-radius:4px;'>$email</code> / pass: <code style='background:$bg;color:$color;padding:1px 8px;border-radius:4px;'>password</code></div>";
    foreach($url_map as $name => $url) {
        $page_role = explode('-', $name)[0];
        $role_check = $role === 'finance_staff' ? 'finance' : ($role === 'campaign_staff' ? 'campaign' : $role);
        if(strpos($name, $role_check) === 0) {
            $page_label = ucwords(str_replace('-', ' ', str_replace($role_check.'-', '', $name)));
            echo "<a href='http://localhost$url' target='_blank' class='link-btn'><i class='bi bi-box-arrow-up-right'></i>$page_label</a>";
        }
    }
    echo "</div>";
}
echo '</div>';

echo '<div class="card">';
echo '<h2>📸 Method 2 — Screenshot Tool (Giống 100%)</h2>';
echo '<p>1. Đăng nhập vào từng role<br>2. Mở từng trang bên trên<br>3. Nhấn <strong>F12 → Ctrl+Shift+P → "Capture full size screenshot"</strong><br>4. Import PNG vào Figma</p>';
echo '<div class="grid">';
foreach($url_map as $name => $url) {
    $page_label = ucwords(str_replace('-', ' ', $name));
    echo "<div style='background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);border-radius:8px;padding:10px;text-align:center;'>";
    echo "<div style='font-size:.74rem;font-weight:600;color:white;margin-bottom:4px;'>$page_label</div>";
    echo "<a href='http://localhost$url' target='_blank' style='font-size:.68rem;color:rgba(255,255,255,.4);text-decoration:none;font-family:monospace;'>$url</a>";
    echo "</div>";
}
echo '</div>';
echo '</div>';

echo '<div class="card">';
echo '<h2>🔗 Wireframe Index</h2>';
echo '<a href="index.html" class="btn"><i class="bi bi-grid-fill"></i>View Wireframe Hub</a>';
echo '</div>';

echo '</body></html>';
?>
