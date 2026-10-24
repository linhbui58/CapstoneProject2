<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/vnpay.php';

header('Content-Type: application/json; charset=utf-8');

function respondVnPayCheckout(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondVnPayCheckout(405, ['error' => 'Method not allowed.']);
}
if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'donor') {
    respondVnPayCheckout(401, ['error' => 'Please sign in as a donor.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respondVnPayCheckout(400, ['error' => 'Invalid request body.']);
}
$csrfToken = $input['csrf_token'] ?? '';
if (!is_string($csrfToken) || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respondVnPayCheckout(403, ['error' => 'Invalid security token. Refresh the page and try again.']);
}

try {
    $config = getVnPayConfig();
} catch (Throwable $exception) {
    error_log('VNPay configuration error: ' . $exception->getMessage());
    respondVnPayCheckout(503, ['error' => 'Credit card payment is not configured. Add valid VNPay credentials in config/vnpay.php on the server.']);
}
if ($config['mode'] === 'live'
    && (parse_url(SITE_URL, PHP_URL_SCHEME) !== 'https' || !parse_url(SITE_URL, PHP_URL_HOST))) {
    respondVnPayCheckout(503, ['error' => 'Live card payments require the public HTTPS site URL to be configured.']);
}

$campaignInput = filter_var($input['campaign_id'] ?? null, FILTER_VALIDATE_INT);
$amountInput = $input['amount'] ?? null;
$message = $input['message'] ?? '';
$isAnonymous = !empty($input['is_anonymous']) ? 1 : 0;
if ($campaignInput === false || $campaignInput === null || $campaignInput < 0
    || !is_numeric($amountInput) || !is_finite((float) $amountInput)
    || floor((float) $amountInput) !== (float) $amountInput
    || (float) $amountInput < 5000 || (float) $amountInput > 9999999999) {
    respondVnPayCheckout(422, ['error' => 'VNPay card payments require a whole-number donation between 5,000 and 9,999,999,999 VND.']);
}
if (!is_string($message)) {
    respondVnPayCheckout(422, ['error' => 'Invalid donation message.']);
}
$message = trim($message);
if (mb_strlen($message) > 5000) {
    respondVnPayCheckout(422, ['error' => 'The donation message is too long.']);
}

$campaignId = $campaignInput > 0 ? $campaignInput : null;
$amount = (int) $amountInput;
$donorId = (int) $_SESSION['user_id'];
$transactionStarted = false;
$db = null;
$failureStage = 'connecting to the database';
try {
    $db = getDB();
    if ($campaignId !== null) {
        $failureStage = 'checking the selected program';
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
            respondVnPayCheckout(422, ['error' => 'The selected program is not active.']);
        }
    }

    $failureStage = 'starting the card donation';
    if (!$db->begin_transaction()) {
        throw new RuntimeException('Unable to start donation transaction: ' . $db->error);
    }
    $transactionStarted = true;

    $donationMessage = $message !== '' ? $message : null;
    $donationInsert = $db->prepare($campaignId === null
        ? "INSERT INTO donations (donor_id, campaign_id, amount, channel, message, is_anonymous, status)
           VALUES (?, NULL, ?, 'credit_card', ?, ?, 'pending')"
        : "INSERT INTO donations (donor_id, campaign_id, amount, channel, message, is_anonymous, status)
           VALUES (?, ?, ?, 'credit_card', ?, ?, 'pending')"
    );
    if (!$donationInsert) {
        throw new RuntimeException('Unable to prepare donation insert: ' . $db->error);
    }
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

    $referenceCode = 'D' . $donationId . date('ymdHis') . bin2hex(random_bytes(5));
    $transactionInsert = $db->prepare(
        "INSERT INTO transactions
            (donation_id, reference_code, bank_name, account_number, amount_transferred, verification_status)
         VALUES (?, ?, 'VNPay', NULL, NULL, 'pending_verification')"
    );
    if (!$transactionInsert) {
        throw new RuntimeException('Unable to prepare payment transaction: ' . $db->error);
    }
    if (!$transactionInsert->bind_param('is', $donationId, $referenceCode) || !$transactionInsert->execute()) {
        throw new RuntimeException('Unable to save payment transaction: ' . $transactionInsert->error);
    }
    $transactionId = (int) $db->insert_id;
    $transactionInsert->close();

    $history = $db->prepare(
        "INSERT INTO verification_history (transaction_id, action, performed_by, old_status, new_status, notes)
         VALUES (?, 'submitted', ?, NULL, 'pending_verification', 'Started VNPay credit card checkout.')"
    );
    if (!$history) {
        throw new RuntimeException('Unable to prepare checkout history: ' . $db->error);
    }
    if (!$history->bind_param('ii', $transactionId, $donorId) || !$history->execute()) {
        throw new RuntimeException('Unable to save checkout history: ' . $history->error);
    }
    $history->close();

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($ipAddress) || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
        $ipAddress = '127.0.0.1';
    }
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    $parameters = [
        'vnp_Version' => '2.1.0',
        'vnp_Command' => 'pay',
        'vnp_TmnCode' => $config['tmn_code'],
        'vnp_Amount' => (string) ($amount * 100),
        'vnp_CurrCode' => 'VND',
        'vnp_BankCode' => 'INTCARD',
        'vnp_TxnRef' => $referenceCode,
        'vnp_OrderInfo' => 'Donation ' . $donationId,
        'vnp_OrderType' => 'other',
        'vnp_Locale' => 'en',
        'vnp_ReturnUrl' => SITE_URL . '/donor/vnpay_return.php',
        'vnp_IpAddr' => $ipAddress,
        'vnp_CreateDate' => $now->format('YmdHis'),
        'vnp_ExpireDate' => $now->modify('+15 minutes')->format('YmdHis'),
    ];
    $paymentUrl = createVnPayPaymentUrl($config, $parameters);

    if (!$db->commit()) {
        throw new RuntimeException('Unable to commit card donation: ' . $db->error);
    }
    $transactionStarted = false;

    respondVnPayCheckout(201, [
        'payment_url' => $paymentUrl,
        'donation_id' => $donationId,
    ]);
} catch (Throwable $exception) {
    if ($transactionStarted && $db instanceof mysqli) {
        try {
            $db->rollback();
        } catch (Throwable $rollbackException) {
            error_log('VNPay checkout rollback failed: ' . $rollbackException->getMessage());
        }
    }
    $reference = bin2hex(random_bytes(4));
    error_log(sprintf('VNPay checkout failed [%s] while %s: %s', $reference, $failureStage, $exception->getMessage()));
    respondVnPayCheckout(500, [
        'error' => sprintf('Could not start card payment while %s (reference %s).', $failureStage, $reference),
    ]);
}
