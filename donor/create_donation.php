<?php
require_once dirname(__DIR__) . '/includes/auth.php';

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

$campaignInput = filter_var($input['campaign_id'] ?? null, FILTER_VALIDATE_INT);
$amountInput = $input['amount'] ?? null;
$donorId = (int) $_SESSION['user_id'];
$message = $input['message'] ?? '';
$isAnonymous = !empty($input['is_anonymous']) ? 1 : 0;

if ($campaignInput === false || $campaignInput === null || $campaignInput < 0
    || !is_numeric($amountInput) || (float) $amountInput <= 0
    || floor((float) $amountInput) !== (float) $amountInput) {
    respond(422, ['error' => 'Choose a program and enter a positive whole-number amount.']);
}
$campaignId = $campaignInput > 0 ? $campaignInput : null;

if (!is_string($message)) {
    respond(422, ['error' => 'Invalid donation message.']);
}
$message = trim($message);
if (mb_strlen($message) > 5000) {
    respond(422, ['error' => 'The donation message is too long.']);
}

$amount = (int) $amountInput;
$vietQrConfigPath = dirname(__DIR__) . '/config/vietqr.php';
$vietQrConfig = [];
if (is_file($vietQrConfigPath)) {
    ob_start();
    try {
        $loadedConfig = require $vietQrConfigPath;
        $configOutput = ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        error_log('VietQR configuration could not be loaded: ' . $exception->getMessage());
        respond(500, ['error' => 'The VietQR configuration file has invalid PHP syntax. Check config/vietqr.php.']);
    }

    if ($configOutput !== '' || !is_array($loadedConfig)) {
        error_log('VietQR configuration must return an array without producing output.');
        respond(500, ['error' => 'VietQR configuration is invalid. Check the PHP opening tag and returned array in config/vietqr.php.']);
    }
    $vietQrConfig = $loadedConfig;
}

$readVietQrSetting = static function (string $environmentKey, string $constantName, string $configKey) use ($vietQrConfig): string {
    $value = getenv($environmentKey);
    if (!is_string($value) || trim($value) === '') {
        $value = defined($constantName) ? constant($constantName) : ($vietQrConfig[$configKey] ?? '');
    }

    return is_scalar($value) ? trim((string) $value) : '';
};

$bankId        = strtoupper($readVietQrSetting('VIETQR_BANK_ID', 'VIETQR_BANK_ID', 'bank_id'));
$accountNumber = $readVietQrSetting('VIETQR_ACCOUNT_NO', 'VIETQR_ACCOUNT_NO', 'account_no');
$accountName   = $readVietQrSetting('VIETQR_ACCOUNT_NAME', 'VIETQR_ACCOUNT_NAME', 'account_name');
$bankName      = $readVietQrSetting('VIETQR_BANK_NAME', 'VIETQR_BANK_NAME', 'bank_name');
if ($bankName === '') {
    $bankName = $bankId;
}

if (!preg_match('/^[A-Z0-9]{2,20}$/', $bankId) || !preg_match('/^[0-9]{6,30}$/', $accountNumber)) {
    respond(503, ['error' => 'Fund bank details are missing or invalid. Configure bank_id and account_no in config/vietqr.php on the server.']);
}

try {
    $failureStage = 'connecting to the database';
    $db = getDB();
    if ($campaignId !== null) {
        $failureStage = 'checking the selected campaign';
        $campaignQuery = $db->prepare("SELECT id FROM campaigns WHERE id = ? AND status = 'active' LIMIT 1");
        if (!$campaignQuery) {
            throw new RuntimeException('Unable to prepare campaign lookup: ' . $db->error);
        }
        if (!$campaignQuery->bind_param('i', $campaignId) || !$campaignQuery->execute()) {
            throw new RuntimeException('Unable to check campaign: ' . $campaignQuery->error);
        }
        $campaignQuery->store_result();
        $campaignIsActive = $campaignQuery->num_rows > 0;
        $campaignQuery->close();
        if (!$campaignIsActive) {
            respond(422, ['error' => 'The selected program is not active.']);
        }
    }

    $transferInfo = sprintf('DONATE %d %d', $campaignId, $donorId);

    $failureStage = 'starting the donation transaction';
    if (!$db->begin_transaction()) {
        throw new RuntimeException('Unable to start database transaction: ' . $db->error);
    }
    $transactionStarted = true;
    $failureStage = 'saving the donation';
    $donationInsert = $db->prepare($campaignId === null
        ? "INSERT INTO donations (donor_id, campaign_id, amount, channel, message, is_anonymous, status)
           VALUES (?, NULL, ?, 'bank_transfer', ?, ?, 'pending')"
        : "INSERT INTO donations (donor_id, campaign_id, amount, channel, message, is_anonymous, status)
           VALUES (?, ?, ?, 'bank_transfer', ?, ?, 'pending')"
    );
    if (!$donationInsert) {
        throw new RuntimeException('Unable to prepare donation insert: ' . $db->error);
    }
    $donationMessage = $message !== '' ? $message : null;
    if ($campaignId === null) {
        $bound = $donationInsert->bind_param('iisi', $donorId, $amount, $donationMessage, $isAnonymous);
    } else {
        $bound = $donationInsert->bind_param('iiisi', $donorId, $campaignId, $amount, $donationMessage, $isAnonymous);
    }
    if (!$bound || !$donationInsert->execute()) {
        throw new RuntimeException('Unable to save donation: ' . $donationInsert->error);
    }
    $donationInsert->close();
    $donationId = (int) $db->insert_id;

    $failureStage = 'saving the bank transaction';
    $transactionInsert = $db->prepare(
        "INSERT INTO transactions
            (donation_id, reference_code, bank_name, account_number, amount_transferred, verification_status)
         VALUES (?, ?, ?, ?, NULL, 'pending_verification')"
    );
    if (!$transactionInsert) {
        throw new RuntimeException('Unable to prepare transaction insert: ' . $db->error);
    }
    if (!$transactionInsert->bind_param('isss', $donationId, $transferInfo, $bankName, $accountNumber)
        || !$transactionInsert->execute()) {
        throw new RuntimeException('Unable to save bank transaction: ' . $transactionInsert->error);
    }
    $transactionInsert->close();
    $transactionId = (int) $db->insert_id;

    $failureStage = 'saving the verification history';
    $historyInsert = $db->prepare(
        "INSERT INTO verification_history (transaction_id, action, performed_by, old_status, new_status, notes)
         VALUES (?, 'submitted', ?, NULL, 'pending_verification', ?)"
    );
    if (!$historyInsert) {
        throw new RuntimeException('Unable to prepare verification history insert: ' . $db->error);
    }
    $historyNotes = 'Donation submitted through VietQR.';
    if (!$historyInsert->bind_param('iis', $transactionId, $donorId, $historyNotes)
        || !$historyInsert->execute()) {
        throw new RuntimeException('Unable to save verification history: ' . $historyInsert->error);
    }
    $historyInsert->close();
    $failureStage = 'committing the donation transaction';
    if (!$db->commit()) {
        throw new RuntimeException('Unable to commit donation transaction: ' . $db->error);
    }
    $transactionStarted = false;

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
    if (!empty($transactionStarted) && isset($db) && $db instanceof mysqli) {
        try {
            $db->rollback();
        } catch (Throwable $rollbackException) {
            error_log('QR donation rollback failed: ' . $rollbackException->getMessage());
        }
    }
    $errorCode = (string) $exception->getCode();
    if ($errorCode === '' || $errorCode === '0') {
        $errorCode = 'internal';
    }
    $errorCode = preg_match('/^[A-Za-z0-9_-]{1,32}$/', $errorCode) ? $errorCode : 'unknown';
    $errorReference = bin2hex(random_bytes(4));
    error_log(sprintf(
        'QR donation creation failed [%s] while %s (code %s): %s',
        $errorReference,
        $failureStage ?? 'processing the request',
        $errorCode,
        $exception->getMessage()
    ));
    respond(500, [
        'error' => sprintf(
            'Failed while %s (error code %s, reference %s).',
            $failureStage ?? 'processing the donation',
            $errorCode,
            $errorReference
        ),
    ]);
}