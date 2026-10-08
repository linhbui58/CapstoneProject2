<?php
$pageTitle  = 'Notifications';
$activePage = 'notifications';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('donor');
$uid      = $user['id'];
$back_url = SITE_URL . '/donor/notifications.php';
$color    = '#1447c0';
require_once dirname(__DIR__) . '/includes/notifications_page.php';
require_once dirname(__DIR__) . '/includes/footer.php';
