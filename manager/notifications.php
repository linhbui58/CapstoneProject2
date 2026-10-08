<?php
$pageTitle  = 'Notifications';
$activePage = 'notifications';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('manager');
$uid      = $user['id'];
$back_url = SITE_URL . '/manager/notifications.php';
$color    = '#ef4444';
require_once dirname(__DIR__) . '/includes/notifications_page.php';
require_once dirname(__DIR__) . '/includes/footer.php';
