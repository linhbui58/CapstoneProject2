<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/vnpay.php';

try {
    $config = getVnPayConfig();
    $result = processVnPayCallback($_GET, $config);
} catch (Throwable $exception) {
    error_log('VNPay return callback failed: ' . $exception->getMessage());
    setFlash('danger', 'We could not confirm the card payment. Please check your donation status or contact support.');
    redirect(SITE_URL . '/donor/my_donations.php');
}

$donationId = (int) ($result['donation_id'] ?? 0);
$donorId = (int) ($result['donor_id'] ?? 0);
if ($result['response_code'] === '00' && $donationId > 0) {
    if (!empty($result['paid'])) {
        setFlash('success', 'Your credit card donation has been confirmed. Thank you for your contribution.');
    } else {
        setFlash('danger', 'The card payment was not completed. You can start a new donation if you still wish to contribute.');
    }
    if (isset($_SESSION['user_id'], $_SESSION['user_role'])
        && $_SESSION['user_role'] === 'donor'
        && (int) $_SESSION['user_id'] === $donorId) {
        redirect(SITE_URL . '/donor/donation_detail.php?id=' . $donationId);
    }
} else {
    setFlash('danger', 'VNPay could not confirm this card payment. Please check your donation status or contact support.');
}

redirect(SITE_URL . '/donor/my_donations.php');
