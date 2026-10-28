<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/sepay_pg.php';

header('Content-Type: application/json; charset=utf-8');

function respondSePayPgIpn(int $status, bool $success, string $message = ''): void
{
    http_response_code($status);
    echo json_encode(
        $success ? ['success' => true] : ['success' => false, 'message' => $message],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondSePayPgIpn(405, false, 'Method not allowed.');
}

try {
    $ipnSecret = getSePayIpnSecret();
} catch (Throwable $exception) {
    error_log('SePay Payment Gateway IPN configuration error: ' . $exception->getMessage());
    respondSePayPgIpn(500, false, 'IPN configuration error.');
}
if ($ipnSecret === '') {
    respondSePayPgIpn(503, false, 'IPN authentication is not configured.');
}

$providedSecret = $_SERVER['HTTP_X_SECRET_KEY'] ?? '';
if (!is_string($providedSecret) || !hash_equals($ipnSecret, $providedSecret)) {
    respondSePayPgIpn(401, false, 'Unauthorized.');
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 32768) {
    respondSePayPgIpn(400, false, 'Invalid request body.');
}
$payload = json_decode($rawBody, true);
if (!is_array($payload)
    || !is_array($payload['order'] ?? null)
    || !is_array($payload['transaction'] ?? null)) {
    respondSePayPgIpn(400, false, 'Invalid IPN payload.');
}

if (($payload['notification_type'] ?? '') !== 'ORDER_PAID'
    || ($payload['order']['order_status'] ?? '') !== 'CAPTURED') {
    respondSePayPgIpn(200, true);
}

$invoiceNumber = $payload['order']['order_invoice_number'] ?? '';
$orderAmount = $payload['order']['order_amount'] ?? null;
$currency = $payload['order']['order_currency'] ?? '';
$transactionAmount = $payload['transaction']['transaction_amount'] ?? null;
$transactionReference = $payload['transaction']['transaction_id'] ?? '';
$transactionDate = $payload['transaction']['transaction_date'] ?? '';
$paymentMethod = $payload['transaction']['payment_method'] ?? 'SePay';
if (!is_string($invoiceNumber) || !preg_match('/^VCFD-([1-9][0-9]*)$/', $invoiceNumber, $invoiceMatch)
    || !is_numeric($orderAmount) || !is_finite((float) $orderAmount) || (float) $orderAmount <= 0
    || $currency !== 'VND'
    || ($transactionAmount !== null && (!is_numeric($transactionAmount)
        || !is_finite((float) $transactionAmount) || (float) $transactionAmount <= 0))
    || !is_string($transactionReference) || !is_string($transactionDate) || !is_string($paymentMethod)) {
    respondSePayPgIpn(400, false, 'Invalid paid order data.');
}
$donationId = filter_var($invoiceMatch[1], FILTER_VALIDATE_INT);
if ($donationId === false || $donationId < 1) {
    respondSePayPgIpn(400, false, 'Invalid invoice number.');
}

$amountReceived = $transactionAmount !== null ? (float) $transactionAmount : (float) $orderAmount;
$eventDate = DateTimeImmutable::createFromFormat(
    '!Y-m-d H:i:s',
    $transactionDate,
    new DateTimeZone('Asia/Ho_Chi_Minh')
);
$dateErrors = DateTimeImmutable::getLastErrors();
if (!$eventDate || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))) {
    $eventDate = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
}
$eventDateString = $eventDate->format('Y-m-d H:i:s');
$transactionReference = $transactionReference !== ''
    ? substr($transactionReference, 0, 100)
    : substr($invoiceNumber, 0, 100);
$paymentMethod = substr('SePay ' . $paymentMethod, 0, 100);

$db = null;
$transactionStarted = false;
try {
    $db = getApplicationDB();
    if (!$db->begin_transaction()) {
        throw new RuntimeException('Unable to start SePay IPN processing: ' . $db->error);
    }
    $transactionStarted = true;

    $donationQuery = $db->prepare(
        "SELECT donor_id, amount, status FROM donations
         WHERE id = ? AND channel = 'bank_transfer'
         LIMIT 1 FOR UPDATE"
    );
    if (!$donationQuery || !$donationQuery->bind_param('i', $donationId) || !$donationQuery->execute()) {
        throw new RuntimeException('Unable to retrieve SePay donation: ' . ($donationQuery ? $donationQuery->error : $db->error));
    }
    $donationQuery->bind_result($donorId, $donationAmount, $donationStatus);
    if (!$donationQuery->fetch()) {
        $donationQuery->close();
        $db->rollback();
        $transactionStarted = false;
        error_log('SePay IPN referenced an unknown donation invoice: ' . $invoiceNumber);
        respondSePayPgIpn(200, true);
    }
    $donationQuery->close();

    $existingQuery = $db->prepare('SELECT id FROM transactions WHERE donation_id = ? LIMIT 1');
    if (!$existingQuery || !$existingQuery->bind_param('i', $donationId) || !$existingQuery->execute()) {
        throw new RuntimeException('Unable to check duplicate SePay payment: ' . ($existingQuery ? $existingQuery->error : $db->error));
    }
    $existingQuery->bind_result($existingTransactionId);
    if ($existingQuery->fetch()) {
        $existingQuery->close();
        $db->rollback();
        $transactionStarted = false;
        respondSePayPgIpn(200, true);
    }
    $existingQuery->close();
    if ($donationStatus !== 'pending') {
        $db->rollback();
        $transactionStarted = false;
        respondSePayPgIpn(200, true);
    }

    $transactionInsert = $db->prepare(
        "INSERT INTO transactions
            (donation_id, reference_code, bank_name, account_number, transaction_date,
             amount_transferred, verification_status)
         VALUES (?, ?, ?, NULL, ?, ?, 'pending_verification')"
    );
    if (!$transactionInsert || !$transactionInsert->bind_param(
        'isssd',
        $donationId,
        $transactionReference,
        $paymentMethod,
        $eventDateString,
        $amountReceived
    ) || !$transactionInsert->execute()) {
        throw new RuntimeException('Unable to save SePay payment: ' . ($transactionInsert ? $transactionInsert->error : $db->error));
    }
    $transactionId = (int) $db->insert_id;
    $transactionInsert->close();

    $historyNotes = sprintf(
        'SePay confirmed receipt of %s VND. Finance staff verification is still required.',
        number_format($amountReceived, 0, '.', ',')
    );
    if (number_format($amountReceived, 2, '.', '') !== number_format((float) $donationAmount, 2, '.', '')) {
        $historyNotes .= ' The received amount differs from the donation amount.';
    }
    $historyInsert = $db->prepare(
        "INSERT INTO verification_history (transaction_id, action, performed_by, old_status, new_status, notes)
         VALUES (?, 'submitted', ?, NULL, 'pending_verification', ?)"
    );
    if (!$historyInsert || !$historyInsert->bind_param('iis', $transactionId, $donorId, $historyNotes)
        || !$historyInsert->execute()) {
        throw new RuntimeException('Unable to save SePay payment history: ' . ($historyInsert ? $historyInsert->error : $db->error));
    }
    $historyInsert->close();

    $notificationTitle = 'Đã nhận được thanh toán';
    $notificationMessage = 'SePay đã xác nhận quỹ nhận được ' . number_format($amountReceived, 0, '.', ',')
        . ' VND. Khoản quyên góp đang chờ nhân viên tài chính xác nhận.';
    $notificationType = 'system';
    $notificationInsert = $db->prepare(
        'INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)'
    );
    if (!$notificationInsert || !$notificationInsert->bind_param('isss', $donorId, $notificationTitle, $notificationMessage, $notificationType)
        || !$notificationInsert->execute()) {
        throw new RuntimeException('Unable to notify donor about SePay payment: ' . ($notificationInsert ? $notificationInsert->error : $db->error));
    }
    $notificationInsert->close();

    if (!$db->commit()) {
        throw new RuntimeException('Unable to commit SePay payment: ' . $db->error);
    }
    $transactionStarted = false;
    respondSePayPgIpn(200, true);
} catch (Throwable $exception) {
    if ($transactionStarted && $db instanceof mysqli) {
        try {
            $db->rollback();
        } catch (Throwable $rollbackException) {
            error_log('SePay IPN rollback failed: ' . $rollbackException->getMessage());
        }
    }
    error_log('SePay IPN processing failed: ' . $exception->getMessage());
    respondSePayPgIpn(500, false, 'Unable to process payment notification.');
}
