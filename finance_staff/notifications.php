<?php
$pageTitle  = 'Notifications';
$activePage = 'notifications';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('finance_staff');
$uid      = $user['id'];
$back_url = SITE_URL . '/finance_staff/notifications.php';
$color    = '#d97706';
require_once dirname(__DIR__) . '/includes/notifications_page.php';
require_once dirname(__DIR__) . '/includes/footer.php';
