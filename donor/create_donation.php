<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/payos.php';

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

// Validate frequency — must be one of the 4 allowed values, default one_time
$allowedFrequencies = ['one_time', 'monthly', 'quarterly', 'yearly'];
$frequencyInput = $input['frequency'] ?? 'one_time';
$frequency = in_array($frequencyInput, $allowedFrequencies, true) ? $frequencyInput : 'one_time';

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
try {
    $payOsConfig = getPayOsConfig();
} catch (Throwable $exception) {
    error_log('payOS configuration error: ' . $exception->getMessage());
    respond(503, ['error' => 'payOS is not configured correctly. Please contact the administrator.']);
}
if (parse_url(SITE_URL, PHP_URL_SCHEME) !== 'https' || !parse_url(SITE_URL, PHP_URL_HOST)) {
    respond(503, ['error' => 'payOS payments require a public HTTPS site URL.']);
}

try {
    $failureStage = 'connecting to the database';
    $db = getApplicationDB();
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

    $failureStage = 'starting the donation transaction';
    if (!$db->begin_transaction()) {
        throw new RuntimeException('Unable to start database transaction: ' . $db->error);
    }
    $transactionStarted = true;
    $failureStage = 'saving the donation';
    $donationInsert = $db->prepare($campaignId === null
        ? "INSERT INTO donations (donor_id, campaign_id, amount, channel, payment_provider, frequency, message, is_anonymous, status)
           VALUES (?, NULL, ?, 'bank_transfer', 'payos', ?, ?, ?, 'pending')"
        : "INSERT INTO donations (donor_id, campaign_id, amount, channel, payment_provider, frequency, message, is_anonymous, status)
           VALUES (?, ?, ?, 'bank_transfer', 'payos', ?, ?, ?, 'pending')"
    );
    if (!$donationInsert) {
        throw new RuntimeException('Unable to prepare donation insert: ' . $db->error);
    }
    $donationMessage = $message !== '' ? $message : null;
    if ($campaignId === null) {
        $bound = $donationInsert->bind_param('iissi', $donorId, $amount, $frequency, $donationMessage, $isAnonymous);
    } else {
        $bound = $donationInsert->bind_param('iiissi', $donorId, $campaignId, $amount, $frequency, $donationMessage, $isAnonymous);
    }
    if (!$bound || !$donationInsert->execute()) {
        throw new RuntimeException('Unable to save donation: ' . $donationInsert->error);
    }
    $donationInsert->close();
    $donationId = (int) $db->insert_id;

    $failureStage = 'committing the donation transaction';
    if (!$db->commit()) {
        throw new RuntimeException('Unable to commit donation transaction: ' . $db->error);
    }
    $transactionStarted = false;

    $returnUrl = SITE_URL . '/donor/donation_detail.php?donation_id=' . $donationId;
    $failureStage = 'creating the payOS checkout';
    $checkoutUrl = createPayOsPaymentLink(
        $payOsConfig,
        $donationId,
        $amount,
        $returnUrl,
        $returnUrl . '&payment=cancel'
    );

    respond(201, [
        'donation_id' => $donationId,
        'amount' => $amount,
        'frequency' => $frequency,
        'checkout_url' => $checkoutUrl,
    ]);
} catch (Throwable $exception) {
    if (!empty($transactionStarted) && isset($db) && $db instanceof mysqli) {
        try {
            $db->rollback();
        } catch (Throwable $rollbackException) {
            error_log(            'Donation rollback failed: ' . $rollbackException->getMessage());
        }
    }
    $errorCode = (string) $exception->getCode();
    if ($errorCode === '' || $errorCode === '0') {
        $errorCode = 'internal';
    }
    $errorCode = preg_match('/^[A-Za-z0-9_-]{1,32}$/', $errorCode) ? $errorCode : 'unknown';
    $errorReference = bin2hex(random_bytes(4));
    error_log(sprintf(
        'Donation creation failed [%s] while %s (code %s): %s',
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