<?php
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, ['error' => 'Method not allowed.']);
}

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'donor') {
    respond(401, ['error' => 'Please sign in as a donor.']);
}

$donationId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$donationId || $donationId < 1) {
    respond(400, ['error' => 'Invalid donation ID.']);
}

try {
    $db = getApplicationDB();
    $query = $db->prepare(
        'SELECT d.status, t.verification_status, t.rejection_reason
         FROM donations d
         JOIN transactions t ON t.donation_id = d.id
         WHERE d.id = ? AND d.donor_id = ?
         LIMIT 1'
    );
    if (!$query) {
        throw new RuntimeException('Unable to prepare donation status lookup: ' . $db->error);
    }
    $donorId = (int) $_SESSION['user_id'];
    if (!$query->bind_param('ii', $donationId, $donorId) || !$query->execute()) {
        throw new RuntimeException('Unable to retrieve donation status: ' . $query->error);
    }
    $query->bind_result($status, $verificationStatus, $rejectionReason);
    if (!$query->fetch()) {
        $query->close();
        respond(404, ['error' => 'Donation not found.']);
    }
    $query->close();

    respond(200, [
        'status' => $status,
        'verification_status' => $verificationStatus,
        'rejection_reason' => $rejectionReason,
    ]);
} catch (Throwable $exception) {
    error_log('Donation status lookup failed: ' . $exception->getMessage());
    respond(500, ['error' => 'Unable to check the donation status right now.']);
}
