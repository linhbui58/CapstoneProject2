<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/pdo.php';

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
    $pdo = getPDO();
    $query = $pdo->prepare(
        'SELECT d.status, t.verification_status, t.rejection_reason
         FROM donations d
         JOIN transactions t ON t.donation_id = d.id
         WHERE d.id = :donation_id AND d.donor_id = :donor_id
         LIMIT 1'
    );
    $query->execute([
        'donation_id' => $donationId,
        'donor_id' => (int) $_SESSION['user_id'],
    ]);
    $donation = $query->fetch();

    if (!$donation) {
        respond(404, ['error' => 'Donation not found.']);
    }

    respond(200, [
        'status' => $donation['status'],
        'verification_status' => $donation['verification_status'],
        'rejection_reason' => $donation['rejection_reason'],
    ]);
} catch (Throwable $exception) {
    error_log('Donation status lookup failed: ' . $exception->getMessage());
    respond(500, ['error' => 'Unable to check the donation status right now.']);
}
