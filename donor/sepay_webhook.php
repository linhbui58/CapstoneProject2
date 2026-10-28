<?php
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function respondSePay(int $status, bool $success, string $message = ''): void
{
    http_response_code($status);
    echo json_encode(
        $success ? ['success' => true] : ['success' => false, 'message' => $message],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondSePay(405, false, 'Method not allowed.');
}

$configPath = dirname(__DIR__) . '/config/sepay.php';
$sepayConfig = [];
if (is_file($configPath)) {
    ob_start();
    try {
        $loadedConfig = require $configPath;
        $configOutput = ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        error_log('SePay configuration could not be loaded: ' . $exception->getMessage());
        respondSePay(500, false, 'Webhook configuration error.');
    }
    if ($configOutput !== '' || !is_array($loadedConfig)) {
        error_log('SePay configuration must return an array without producing output.');
        respondSePay(500, false, 'Webhook configuration error.');
    }
    $sepayConfig = $loadedConfig;
}

$apiKey = getenv('SEPAY_API_KEY');
if (!is_string($apiKey) || trim($apiKey) === '') {
    $apiKey = $sepayConfig['api_key'] ?? '';
}
if (!is_string($apiKey) || trim($apiKey) === '') {
    respondSePay(503, false, 'Webhook authentication is not configured.');
}
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!is_string($authorization) || !str_starts_with($authorization, 'Apikey ')
    || !hash_equals(trim($apiKey), substr($authorization, 7))) {
    respondSePay(401, false, 'Unauthorized.');
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 16384) {
    respondSePay(400, false, 'Invalid request body.');
}
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    respondSePay(400, false, 'Invalid JSON payload.');
}

$eventId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
$transferAmount = filter_var($payload['transferAmount'] ?? null, FILTER_VALIDATE_INT);
$accountNumber = $payload['accountNumber'] ?? null;
$transferType = $payload['transferType'] ?? null;
$content = $payload['content'] ?? null;
$gateway = $payload['gateway'] ?? null;
$transactionDate = $payload['transactionDate'] ?? null;
$bankReference = $payload['referenceCode'] ?? '';
if ($eventId === false || $eventId === null || $eventId < 1
    || $transferAmount === false || $transferAmount === null || $transferAmount < 1
    || !is_string($accountNumber) || !is_string($transferType)
    || !is_string($content) || !is_string($gateway)
    || !is_string($transactionDate) || !is_string($bankReference)) {
    respondSePay(400, false, 'Invalid transaction data.');
}

$transactionDateValue = DateTimeImmutable::createFromFormat(
    '!Y-m-d H:i:s',
    $transactionDate,
    new DateTimeZone('Asia/Ho_Chi_Minh')
);
$dateErrors = DateTimeImmutable::getLastErrors();
if (!$transactionDateValue || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))
    || $transactionDateValue->format('Y-m-d H:i:s') !== $transactionDate) {
    respondSePay(400, false, 'Invalid transaction date.');
}

if (strtolower($transferType) !== 'in') {
    respondSePay(200, true);
}
$bankAccount = getenv('VIETQR_ACCOUNT_NO');
if (!is_string($bankAccount) || trim($bankAccount) === '') {
    $bankAccount = defined('VIETQR_ACCOUNT_NO') ? constant('VIETQR_ACCOUNT_NO') : '';
}
if (!is_string($bankAccount) || trim($bankAccount) === '') {
    $vietQrConfigPath = dirname(__DIR__) . '/config/vietqr.php';
    if (is_file($vietQrConfigPath)) {
        ob_start();
        try {
            $vietQrConfig = require $vietQrConfigPath;
            $vietQrOutput = ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            error_log('VietQR configuration could not be loaded for SePay webhook: ' . $exception->getMessage());
            respondSePay(500, false, 'Receiving account configuration error.');
        }
        if ($vietQrOutput !== '' || !is_array($vietQrConfig)) {
            error_log('VietQR configuration must return an array without producing output.');
            respondSePay(500, false, 'Receiving account configuration error.');
        }
        $bankAccount = $vietQrConfig['account_no'] ?? '';
    }
}
if (!is_string($bankAccount) || trim($bankAccount) === '') {
    respondSePay(503, false, 'Receiving account is not configured.');
}
if (!hash_equals(trim($bankAccount), trim($accountNumber))) {
    respondSePay(200, true);
}

if (!preg_match_all('/(?:^|\s)DONATE\s+([1-9][0-9]*)\s+([1-9][0-9]*)(?=\s|$)/i', trim($content), $donateMatches)
    || count($donateMatches[0]) !== 1) {
    respondSePay(200, true);
}
$donationId = filter_var($donateMatches[1][0], FILTER_VALIDATE_INT);
$donorId = filter_var($donateMatches[2][0], FILTER_VALIDATE_INT);
if ($donationId === false || $donorId === false) {
    respondSePay(200, true);
}

$db = null;
$transactionStarted = false;
try {
    $db = getApplicationDB();
    if (!$db->begin_transaction()) {
        throw new RuntimeException('Unable to start incoming transfer processing: ' . $db->error);
    }
    $transactionStarted = true;

    $donationQuery = $db->prepare(
        "SELECT status FROM donations
         WHERE id = ? AND donor_id = ? AND channel = 'bank_transfer'
         LIMIT 1 FOR UPDATE"
    );
    if (!$donationQuery || !$donationQuery->bind_param('ii', $donationId, $donorId) || !$donationQuery->execute()) {
        throw new RuntimeException('Unable to look up matching donation: ' . ($donationQuery ? $donationQuery->error : $db->error));
    }
    $donationQuery->bind_result($donationStatus);
    if (!$donationQuery->fetch()) {
        $donationQuery->close();
        $db->rollback();
        $transactionStarted = false;
        respondSePay(200, true);
    }
    $donationQuery->close();
    if ($donationStatus !== 'pending') {
        $db->rollback();
        $transactionStarted = false;
        respondSePay(200, true);
    }

    $duplicateQuery = $db->prepare('SELECT id FROM transactions WHERE sepay_transaction_id = ? LIMIT 1');
    $eventIdString = (string) $eventId;
    if (!$duplicateQuery || !$duplicateQuery->bind_param('s', $eventIdString) || !$duplicateQuery->execute()) {
        throw new RuntimeException('Unable to check duplicate SePay event: ' . ($duplicateQuery ? $duplicateQuery->error : $db->error));
    }
    $duplicateQuery->bind_result($duplicateTransactionId);
    if ($duplicateQuery->fetch()) {
        $duplicateQuery->close();
        $db->rollback();
        $transactionStarted = false;
        respondSePay(200, true);
    }
    $duplicateQuery->close();

    $existingQuery = $db->prepare(
        'SELECT id, sepay_transaction_id, verification_status
         FROM transactions WHERE donation_id = ? LIMIT 1 FOR UPDATE'
    );
    if (!$existingQuery || !$existingQuery->bind_param('i', $donationId) || !$existingQuery->execute()) {
        throw new RuntimeException('Unable to check existing donation transaction: ' . ($existingQuery ? $existingQuery->error : $db->error));
    }
    $existingQuery->bind_result($transactionId, $existingSePayId, $existingStatus);
    $hasExistingTransaction = $existingQuery->fetch();
    $existingQuery->close();
    if ($hasExistingTransaction && ($existingSePayId !== null || $existingStatus !== 'pending_verification')) {
        $db->rollback();
        $transactionStarted = false;
        respondSePay(200, true);
    }

    $referenceCode = trim($bankReference) !== '' ? trim($bankReference) : trim($content);
    $referenceCode = substr($referenceCode, 0, 100);
    $bankName = substr(trim($gateway), 0, 100);
    $accountNumber = substr(trim($accountNumber), 0, 50);
    $incomingAmount = (float) $transferAmount;
    $eventDate = $transactionDateValue->format('Y-m-d H:i:s');

    if ($hasExistingTransaction) {
        $transactionUpdate = $db->prepare(
            'UPDATE transactions
             SET reference_code = ?, bank_name = ?, account_number = ?, transaction_date = ?,
                 amount_transferred = ?, sepay_transaction_id = ?
             WHERE id = ? AND sepay_transaction_id IS NULL AND verification_status = \'pending_verification\''
        );
        if (!$transactionUpdate || !$transactionUpdate->bind_param(
            'ssssdsi',
            $referenceCode,
            $bankName,
            $accountNumber,
            $eventDate,
            $incomingAmount,
            $eventIdString,
            $transactionId
        ) || !$transactionUpdate->execute() || $transactionUpdate->affected_rows !== 1) {
            throw new RuntimeException('Unable to attach incoming transfer: ' . ($transactionUpdate ? $transactionUpdate->error : $db->error));
        }
        $transactionUpdate->close();
    } else {
        $transactionInsert = $db->prepare(
            "INSERT INTO transactions
                (donation_id, reference_code, bank_name, account_number, transaction_date,
                 amount_transferred, sepay_transaction_id, verification_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_verification')"
        );
        if (!$transactionInsert || !$transactionInsert->bind_param(
            'issssds',
            $donationId,
            $referenceCode,
            $bankName,
            $accountNumber,
            $eventDate,
            $incomingAmount,
            $eventIdString
        ) || !$transactionInsert->execute()) {
            throw new RuntimeException('Unable to save incoming transfer: ' . ($transactionInsert ? $transactionInsert->error : $db->error));
        }
        $transactionId = (int) $db->insert_id;
        $transactionInsert->close();
    }

    $historyNotes = sprintf(
        'Incoming bank transfer of %s VND was detected automatically by SePay. Awaiting finance staff verification.',
        number_format($incomingAmount, 0, '.', ',')
    );
    $historyInsert = $db->prepare(
        "INSERT INTO verification_history (transaction_id, action, performed_by, old_status, new_status, notes)
         VALUES (?, 'submitted', ?, NULL, 'pending_verification', ?)"
    );
    if (!$historyInsert || !$historyInsert->bind_param('iis', $transactionId, $donorId, $historyNotes)
        || !$historyInsert->execute()) {
        throw new RuntimeException('Unable to save transfer verification history: ' . ($historyInsert ? $historyInsert->error : $db->error));
    }
    $historyInsert->close();

    $notificationTitle = 'Đã nhận được chuyển khoản';
    $notificationMessage = 'Quỹ đã nhận được khoản chuyển ' . number_format($incomingAmount, 0, '.', ',')
        . ' VND của bạn. Giao dịch đang chờ nhân viên tài chính xác nhận.';
    $notificationType = 'system';
    $notificationInsert = $db->prepare(
        'INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)'
    );
    if (!$notificationInsert || !$notificationInsert->bind_param('isss', $donorId, $notificationTitle, $notificationMessage, $notificationType)
        || !$notificationInsert->execute()) {
        throw new RuntimeException('Unable to notify donor about received transfer: ' . ($notificationInsert ? $notificationInsert->error : $db->error));
    }
    $notificationInsert->close();

    if (!$db->commit()) {
        throw new RuntimeException('Unable to commit incoming transfer: ' . $db->error);
    }
    $transactionStarted = false;
    respondSePay(200, true);
} catch (Throwable $exception) {
    if ($transactionStarted && $db instanceof mysqli) {
        try {
            $db->rollback();
        } catch (Throwable $rollbackException) {
            error_log('SePay webhook rollback failed: ' . $rollbackException->getMessage());
        }
    }
    error_log('SePay webhook processing failed: ' . $exception->getMessage());
    respondSePay(500, false, 'Unable to process incoming transfer.');
}
