<?php
$pageTitle  = 'Notifications';
$activePage = 'notifications';
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('campaign_staff');
$uid      = $user['id'];
$back_url = SITE_URL . '/campaign_staff/notifications.php';
$color    = '#15803d';
require_once dirname(__DIR__) . '/includes/notifications_page.php';
require_once dirname(__DIR__) . '/includes/footer.php';
