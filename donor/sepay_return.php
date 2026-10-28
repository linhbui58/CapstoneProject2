<?php
require_once dirname(__DIR__) . '/includes/header.php';
requireRole('donor');

$donationId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$result = $_GET['result'] ?? '';
if (!$donationId || $donationId < 1 || !in_array($result, ['success', 'error', 'cancel'], true)) {
    setFlash('danger', 'Invalid SePay payment return.');
    redirect(SITE_URL . '/donor/my_donations.php');
}

$db = getApplicationDB();
$donorId = (int) $user['id'];
$donationQuery = $db->prepare(
    "SELECT id FROM donations WHERE id = ? AND donor_id = ? AND channel = 'bank_transfer' LIMIT 1"
);
if (!$donationQuery || !$donationQuery->bind_param('ii', $donationId, $donorId) || !$donationQuery->execute()) {
    throw new RuntimeException('Unable to verify returned SePay donation: ' . ($donationQuery ? $donationQuery->error : $db->error));
}
$donationQuery->store_result();
$ownsDonation = $donationQuery->num_rows === 1;
$donationQuery->close();
if (!$ownsDonation) {
    setFlash('danger', 'Donation not found.');
    redirect(SITE_URL . '/donor/my_donations.php');
}

if ($result === 'success') {
    setFlash('success', 'SePay returned you to the site. We are confirming the payment; finance staff will verify it after confirmation.');
} elseif ($result === 'cancel') {
    setFlash('warning', 'You cancelled the SePay checkout. No payment has been confirmed.');
} else {
    setFlash('danger', 'SePay could not complete the payment. No payment has been confirmed.');
}

redirect(SITE_URL . '/donor/donation_detail.php?id=' . (int) $donationId);
