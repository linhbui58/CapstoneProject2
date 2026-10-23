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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Method not allowed.']);
}

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'donor') {
    respond(401, ['error' => 'Please sign in as a donor.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(400, ['error' => 'Invalid request body.']);
}

$csrfToken = $input['csrf_token'] ?? '';
if (!is_string($csrfToken) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respond(403, ['error' => 'Invalid security token. Refresh the page and try again.']);
}

$campaignId = filter_var($input['campaign_id'] ?? null, FILTER_VALIDATE_INT);
$amountInput = $input['amount'] ?? null;
$donorId = (int) $_SESSION['user_id'];
$message = $input['message'] ?? '';
$isAnonymous = !empty($input['is_anonymous']) ? 1 : 0;

if (!$campaignId || !is_numeric($amountInput) || (float) $amountInput <= 0 || floor((float) $amountInput) !== (float) $amountInput) {
    respond(422, ['error' => 'Choose an active campaign and enter a positive whole-number amount.']);
}

if (!is_string($message)) {
    respond(422, ['error' => 'Invalid donation message.']);
}
$message = trim($message);
if (mb_strlen($message) > 5000) {
    respond(422, ['error' => 'The donation message is too long.']);
}

$amount = (int) $amountInput;

// Read VietQR config — prefer environment variables (production), fall back to constants (XAMPP/local)
$bankId       = strtoupper(trim((string) (getenv('VIETQR_BANK_ID')      ?: (defined('VIETQR_BANK_ID')      ? VIETQR_BANK_ID      : ''))));
$accountNumber= trim((string)           (getenv('VIETQR_ACCOUNT_NO')    ?: (defined('VIETQR_ACCOUNT_NO')    ? VIETQR_ACCOUNT_NO    : '')));
$accountName  = trim((string)           (getenv('VIETQR_ACCOUNT_NAME')  ?: (defined('VIETQR_ACCOUNT_NAME')  ? VIETQR_ACCOUNT_NAME  : '')));
$bankName     = trim((string)           (getenv('VIETQR_BANK_NAME')     ?: (defined('VIETQR_BANK_NAME')     ? VIETQR_BANK_NAME     : $bankId)));

if (!preg_match('/^[A-Z0-9]{2,20}$/', $bankId) || !preg_match('/^[0-9]{6,30}$/', $accountNumber)) {
    respond(503, ['error' => 'The fund bank account is not configured for VietQR.']);
}

try {
    $pdo = getPDO();
    $campaignQuery = $pdo->prepare("SELECT id FROM campaigns WHERE id = :id AND status = 'active' LIMIT 1");
    $campaignQuery->execute(['id' => $campaignId]);
    if (!$campaignQuery->fetch()) {
        respond(422, ['error' => 'The selected campaign is not active.']);
    }

    $transferInfo = sprintf('DONATE %d %d', $campaignId, $donorId);

    $pdo->beginTransaction();
    $donationInsert = $pdo->prepare(
        "INSERT INTO donations (donor_id, campaign_id, amount, channel, message, is_anonymous, status)
         VALUES (:donor_id, :campaign_id, :amount, 'bank_transfer', :message, :is_anonymous, 'pending')"
    );
    $donationInsert->execute([
        'donor_id' => $donorId,
        'campaign_id' => $campaignId,
        'amount' => $amount,
        'message' => $message !== '' ? $message : null,
        'is_anonymous' => $isAnonymous,
    ]);
    $donationId = (int) $pdo->lastInsertId();

    $transactionInsert = $pdo->prepare(
        "INSERT INTO transactions
            (donation_id, reference_code, bank_name, account_number, amount_transferred, verification_status)
         VALUES (:donation_id, :reference_code, :bank_name, :account_number, NULL, 'pending_verification')"
    );
    $transactionInsert->execute([
        'donation_id' => $donationId,
        'reference_code' => $transferInfo,
        'bank_name' => $bankName,
        'account_number' => $accountNumber,
    ]);
    $transactionId = (int) $pdo->lastInsertId();

    $historyInsert = $pdo->prepare(
        "INSERT INTO verification_history (transaction_id, action, performed_by, old_status, new_status, notes)
         VALUES (:transaction_id, 'submitted', :performed_by, NULL, 'pending_verification', :notes)"
    );
    $historyInsert->execute([
        'transaction_id' => $transactionId,
        'performed_by' => $donorId,
        'notes' => 'Donation submitted through VietQR.',
    ]);
    $pdo->commit();

    $qrPath = sprintf(
        'https://img.vietqr.io/image/%s-%s-qr_only.png',
        rawurlencode($bankId),
        rawurlencode($accountNumber)
    );
    $qrUrl = $qrPath . '?' . http_build_query([
        'amount' => $amount,
        'addInfo' => $transferInfo,
        'accountName' => $accountName,
    ], '', '&', PHP_QUERY_RFC3986);

    respond(201, [
        'donation_id' => $donationId,
        'amount' => $amount,
        'bank_name' => $bankName,
        'account_number' => $accountNumber,
        'transfer_info' => $transferInfo,
        'qr_url' => $qrUrl,
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('QR donation creation failed: ' . $exception->getMessage());
    respond(500, ['error' => 'Unable to create the donation right now. Please try again.']);
}