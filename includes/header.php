<?php
ob_start(); // Buffer output — prevents "headers already sent" errors
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin();
$user = getCurrentUser();
$role = $user['role'];
$initials = strtoupper(substr($user['name'], 0, 1));
$db = getApplicationDB();
$notif_count = 0;
$nr = $db->query("SELECT COUNT(*) as c FROM notifications WHERE user_id={$user['id']} AND is_read=0");
if ($nr) $notif_count = (int)$nr->fetch_assoc()['c'];

$navItems = [];
if ($role === 'donor') {
    $navItems = [
        'dashboard'    =>['label'=>'Dashboard',       'icon'=>'bi-grid-fill',        'url'=>SITE_URL.'/donor/dashboard.php'],
        'campaigns'    =>['label'=>'Browse Campaigns','icon'=>'bi-collection-fill',  'url'=>SITE_URL.'/donor/campaigns.php'],
        'donate'       =>['label'=>'Make a Donation', 'icon'=>'bi-heart-fill',       'url'=>SITE_URL.'/donor/donate.php'],
        'my_donations' =>['label'=>'My Donations',    'icon'=>'bi-receipt',          'url'=>SITE_URL.'/donor/my_donations.php'],
        'impact'       =>['label'=>'My Impact',       'icon'=>'bi-award-fill',        'url'=>SITE_URL.'/donor/impact_summary.php'],
        'notifications'=>['label'=>'Notifications',   'icon'=>'bi-bell-fill',        'url'=>SITE_URL.'/donor/notifications.php'],
    ];
} elseif ($role === 'finance_staff') {
    $navItems = [
        'dashboard'    =>['label'=>'Dashboard',            'icon'=>'bi-grid-fill',       'url'=>SITE_URL.'/finance_staff/dashboard.php'],
        'transactions' =>['label'=>'All Transactions',     'icon'=>'bi-list-check',      'url'=>SITE_URL.'/finance_staff/transactions.php'],
        'verify'       =>['label'=>'Pending Verification', 'icon'=>'bi-shield-check',    'url'=>SITE_URL.'/finance_staff/pending.php'],
        'history'      =>['label'=>'Verification History', 'icon'=>'bi-clock-history',   'url'=>SITE_URL.'/finance_staff/history.php'],
        'notifications'=>['label'=>'Notifications',        'icon'=>'bi-bell-fill',       'url'=>SITE_URL.'/finance_staff/notifications.php'],
    ];
} elseif ($role === 'campaign_staff') {
    $navItems = [
        'dashboard'      =>['label'=>'Dashboard',      'icon'=>'bi-grid-fill',       'url'=>SITE_URL.'/campaign_staff/dashboard.php'],
        'campaigns'      =>['label'=>'Campaigns',           'icon'=>'bi-megaphone-fill',  'url'=>SITE_URL.'/campaign_staff/campaigns.php'],
        'create_campaign'=>['label'=>'New Campaign',        'icon'=>'bi-plus-circle-fill','url'=>SITE_URL.'/campaign_staff/create_campaign.php'],
        'link_donations' =>['label'=>'Link Donations',      'icon'=>'bi-link-45deg',      'url'=>SITE_URL.'/campaign_staff/link_donations.php'],
        'fund_allocation'=>['label'=>'Fund Allocation',     'icon'=>'bi-pie-chart-fill',  'url'=>SITE_URL.'/campaign_staff/fund_allocation.php'],
        'disbursement_requests'=>['label'=>'Disbursement',  'icon'=>'bi-send-fill',       'url'=>SITE_URL.'/campaign_staff/disbursement_requests.php'],
        'campaign_updates'     =>['label'=>'Post Updates',  'icon'=>'bi-broadcast',       'url'=>SITE_URL.'/campaign_staff/campaign_updates.php'],
        'notifications'  =>['label'=>'Notifications',       'icon'=>'bi-bell-fill',       'url'=>SITE_URL.'/campaign_staff/notifications.php'],
    ];
} elseif ($role === 'manager') {
    $navItems = [
        'dashboard'    =>['label'=>'Dashboard',      'icon'=>'bi-grid-fill',           'url'=>SITE_URL.'/manager/dashboard.php'],
        'campaigns'    =>['label'=>'All Campaigns',  'icon'=>'bi-megaphone-fill',      'url'=>SITE_URL.'/manager/campaigns.php'],
        'donations'    =>['label'=>'All Donations',  'icon'=>'bi-cash-stack',          'url'=>SITE_URL.'/manager/donations.php'],
        'disbursement' =>['label'=>'Disbursements',  'icon'=>'bi-send-fill',           'url'=>SITE_URL.'/manager/disbursement_requests.php'],
        'reports'      =>['label'=>'Reports',         'icon'=>'bi-bar-chart-line-fill', 'url'=>SITE_URL.'/manager/reports.php'],
        'segmentation' =>['label'=>'Donor Segments',  'icon'=>'bi-diagram-3-fill',      'url'=>SITE_URL.'/manager/donor_segmentation.php'],
        'prediction'   =>['label'=>'Predictions',     'icon'=>'bi-graph-up-arrow',      'url'=>SITE_URL.'/manager/campaign_prediction.php'],
        'users'        =>['label'=>'Users',            'icon'=>'bi-people-fill',         'url'=>SITE_URL.'/manager/users.php'],
        'notifications'=>['label'=>'Notifications',   'icon'=>'bi-bell-fill',           'url'=>SITE_URL.'/manager/notifications.php'],
    ];
}

$activePage = $activePage ?? 'dashboard';

// Pending verification count for finance staff
$pending_count = 0;
if ($role === 'finance_staff') {
    $pr = $db->query("SELECT COUNT(*) as c FROM transactions WHERE verification_status='pending_verification'");
    if ($pr) $pending_count = (int)$pr->fetch_assoc()['c'];
}

$roleLabel = getRoleLabel($role);
$roleColors = [
    'donor'          =>['bg'=>'#eff6ff','text'=>'#1e40af'],
    'finance_staff'  =>['bg'=>'#fffbeb','text'=>'#854d0e'],
    'campaign_staff' =>['bg'=>'#f0fdf4','text'=>'#15803d'],
    'manager'        =>['bg'=>'#fff1f2','text'=>'#991b1b'],
];
$rc = $roleColors[$role] ?? ['bg'=>'#f1f5f9','text'=>'#475569'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'VCF') ?> - VCF System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">

<!-- ═══ SIDEBAR ══════════════════════════════════ -->
<nav class="sidebar" id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-heart-fill"></i></div>
        <div class="brand-text">
            <strong>VCF System</strong>
            <span>Donation & Fundraising</span>
        </div>
    </div>

    <!-- Nav -->
    <div class="sidebar-nav">
        <div class="nav-section-label">Menu</div>
        <?php foreach ($navItems as $key => $item): ?>
        <a href="<?= $item['url'] ?>" class="nav-link <?= $activePage===$key?'active':'' ?>">
            <i class="bi <?= $item['icon'] ?>"></i>
            <?= $item['label'] ?>
            <?php if ($key === 'notifications' && $notif_count > 0): ?>
                <span class="badge bg-danger ms-auto" style="font-size:.6rem;"><?= $notif_count ?></span>
            <?php endif; ?>
            <?php if ($key === 'verify' && $pending_count > 0): ?>
                <span class="badge ms-auto" style="background:#f59e0b;color:#0f172a;font-size:.6rem;"><?= $pending_count ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- User + Sign Out at bottom -->
    <div class="sidebar-footer">
        <!-- User info -->
        <div class="sidebar-user">
            <div class="user-ava"><?= $initials ?></div>
            <div class="user-info" style="flex:1;min-width:0;">
                <div class="name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="role-tag"><?= $roleLabel ?></div>
            </div>
        </div>
        <!-- Sign Out button -->
        <a href="<?= SITE_URL ?>/auth/logout.php"
           style="display:flex;align-items:center;justify-content:center;gap:7px;
                  margin-top:8px;padding:8px 12px;border-radius:8px;
                  background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.15);
                  color:rgba(248,113,113,.8);font-size:.76rem;font-weight:600;
                  transition:all .15s;text-decoration:none;"
           onmouseover="this.style.background='rgba(248,113,113,.16)';this.style.color='#f87171'"
           onmouseout="this.style.background='rgba(248,113,113,.08)';this.style.color='rgba(248,113,113,.8)'">
            <i class="bi bi-box-arrow-left" style="font-size:.85rem;"></i>
            Sign Out
        </a>
    </div>
</nav>

<!-- ═══ MAIN ══════════════════════════════════════ -->
<div class="main-content">

    <!-- Topbar -->
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="topbar-btn d-lg-none" id="sidebarToggle" style="border:none;background:transparent;">
                <i class="bi bi-list" style="font-size:1.1rem;"></i>
            </button>
            <div class="page-breadcrumb d-none d-sm-block">
                <a href="<?= SITE_URL ?>">Home</a>
                <span class="sep">/</span>
                <span class="current"><?= htmlspecialchars($pageTitle ?? '') ?></span>
            </div>
        </div>
        <div class="topbar-right">
            <!-- Notif bell -->
            <?php
            $notif_url = SITE_URL.'/'.match($role){'donor'=>'donor','finance_staff'=>'finance_staff','campaign_staff'=>'campaign_staff',default=>'manager'}.'/notifications.php';
            ?>
            <a href="<?= $notif_url ?>" class="topbar-btn" style="position:relative;">
                <i class="bi bi-bell" style="font-size:.9rem;color:#64748b;"></i>
                <?php if ($notif_count > 0): ?><span class="notif-dot"></span><?php endif; ?>
            </a>
            <div class="topbar-divider"></div>
            <!-- User dropdown -->
            <div class="dropdown">
                <button class="topbar-user" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="topbar-ava"><?= $initials ?></div>
                    <div class="d-none d-md-block text-start">
                        <div class="topbar-user-name"><?= htmlspecialchars(explode(' ',$user['name'])[0]) ?></div>
                        <div class="topbar-user-role"><?= $roleLabel ?></div>
                    </div>
                    <i class="bi bi-chevron-down d-none d-md-block" style="font-size:.62rem;color:#94a3b8;margin-left:2px;"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="min-width:195px;">
                    <li class="px-3 pt-2 pb-1">
                        <div style="font-size:.8rem;font-weight:700;color:#0f172a;"><?= htmlspecialchars($user['name']) ?></div>
                        <div style="font-size:.7rem;color:#94a3b8;margin-top:1px;"><?= htmlspecialchars($user['email']) ?></div>
                        <span style="display:inline-block;margin-top:5px;padding:2px 9px;border-radius:5px;font-size:.67rem;font-weight:700;background:<?= $rc['bg'] ?>;color:<?= $rc['text'] ?>;">
                            <?= $roleLabel ?>
                        </span>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item" href="<?= SITE_URL ?>/auth/logout.php" style="color:#ef4444;">
                            <i class="bi bi-box-arrow-left me-2"></i>Sign Out
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Page content -->
    <main class="page-content">
        <?php showFlash(); ?>
