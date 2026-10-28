<?php
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function respondBankTransfer(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondBankTransfer(405, ['error' => 'Method not allowed.']);
}
if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'donor') {
    respondBankTransfer(401, ['error' => 'Please sign in as a donor.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respondBankTransfer(400, ['error' => 'Invalid request body.']);
}
$csrfToken = $input['csrf_token'] ?? '';
if (!is_string($csrfToken) || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respondBankTransfer(403, ['error' => 'Invalid security token. Refresh the page and try again.']);
}
$donationId = filter_var($input['donation_id'] ?? null, FILTER_VALIDATE_INT);
if (!$donationId || $donationId < 1) {
    respondBankTransfer(422, ['error' => 'Invalid donation.']);
}

$configPath = dirname(__DIR__) . '/config/vietqr.php';
if (!is_file($configPath)) {
    respondBankTransfer(503, ['error' => 'Fund bank details are not configured. Please contact the administrator.']);
}
ob_start();
try {
    $bankConfig = require $configPath;
    $configOutput = ob_get_clean();
} catch (Throwable $exception) {
    ob_end_clean();
    error_log('VietQR configuration could not be loaded for transfer submission: ' . $exception->getMessage());
    respondBankTransfer(500, ['error' => 'Fund bank details could not be loaded. Please contact the administrator.']);
}
if ($configOutput !== '' || !is_array($bankConfig)) {
    error_log('VietQR configuration must return an array without producing output.');
    respondBankTransfer(500, ['error' => 'Fund bank details are invalid. Please contact the administrator.']);
}

$readBankSetting = static function (string $environmentKey, string $constantName, string $configKey) use ($bankConfig): string {
    $value = getenv($environmentKey);
    if (!is_string($value) || trim($value) === '') {
        $value = defined($constantName) ? constant($constantName) : ($bankConfig[$configKey] ?? '');
    }

    return is_scalar($value) ? trim((string) $value) : '';
};
$bankId = strtoupper($readBankSetting('VIETQR_BANK_ID', 'VIETQR_BANK_ID', 'bank_id'));
$accountNumber = $readBankSetting('VIETQR_ACCOUNT_NO', 'VIETQR_ACCOUNT_NO', 'account_no');
$bankName = $readBankSetting('VIETQR_BANK_NAME', 'VIETQR_BANK_NAME', 'bank_name');
if ($bankName === '') {
    $bankName = $bankId;
}
if (!preg_match('/^[A-Z0-9]{2,20}$/', $bankId) || !preg_match('/^[0-9]{6,30}$/', $accountNumber)) {
    respondBankTransfer(503, ['error' => 'Fund bank details are missing or invalid. Please contact the administrator.']);
}

$donorId = (int) $_SESSION['user_id'];
$transactionStarted = false;
try {
    $db = getApplicationDB();
    if (!$db->begin_transaction()) {
        throw new RuntimeException('Unable to start transfer submission: ' . $db->error);
    }
    $transactionStarted = true;

    $donationQuery = $db->prepare(
        'SELECT channel, status FROM donations
         WHERE id = ? AND donor_id = ? LIMIT 1 FOR UPDATE'
    );
    if (!$donationQuery || !$donationQuery->bind_param('ii', $donationId, $donorId) || !$donationQuery->execute()) {
        throw new RuntimeException('Unable to retrieve donation: ' . ($donationQuery ? $donationQuery->error : $db->error));
    }
    $donationQuery->bind_result($channel, $donationStatus);
    if (!$donationQuery->fetch()) {
        $donationQuery->close();
        $db->rollback();
        $transactionStarted = false;
        respondBankTransfer(404, ['error' => 'Donation not found.']);
    }
    $donationQuery->close();
    if ($channel !== 'bank_transfer' || $donationStatus !== 'pending') {
        $db->rollback();
        $transactionStarted = false;
        respondBankTransfer(409, ['error' => 'This donation is no longer waiting for a bank transfer.']);
    }

    $existingQuery = $db->prepare('SELECT id FROM transactions WHERE donation_id = ? LIMIT 1');
    if (!$existingQuery || !$existingQuery->bind_param('i', $donationId) || !$existingQuery->execute()) {
        throw new RuntimeException('Unable to check transfer submission: ' . ($existingQuery ? $existingQuery->error : $db->error));
    }
    $existingQuery->bind_result($existingTransactionId);
    if ($existingQuery->fetch()) {
        $existingQuery->close();
        $db->rollback();
        $transactionStarted = false;
        respondBankTransfer(200, ['verification_status' => 'pending_verification']);
    }
    $existingQuery->close();

    $referenceCode = sprintf('DONATE %d %d', $donationId, $donorId);
    $transactionInsert = $db->prepare(
        "INSERT INTO transactions
            (donation_id, reference_code, bank_name, account_number, amount_transferred, verification_status)
         VALUES (?, ?, ?, ?, NULL, 'pending_verification')"
    );
    if (!$transactionInsert || !$transactionInsert->bind_param('isss', $donationId, $referenceCode, $bankName, $accountNumber)
        || !$transactionInsert->execute()) {
        throw new RuntimeException('Unable to submit transfer: ' . ($transactionInsert ? $transactionInsert->error : $db->error));
    }
    $transactionId = (int) $db->insert_id;
    $transactionInsert->close();

    $historyNotes = 'Donor marked the VietQR bank transfer as completed.';
    $historyInsert = $db->prepare(
        "INSERT INTO verification_history (transaction_id, action, performed_by, old_status, new_status, notes)
         VALUES (?, 'submitted', ?, NULL, 'pending_verification', ?)"
    );
    if (!$historyInsert || !$historyInsert->bind_param('iis', $transactionId, $donorId, $historyNotes)
        || !$historyInsert->execute()) {
        throw new RuntimeException('Unable to save transfer history: ' . ($historyInsert ? $historyInsert->error : $db->error));
    }
    $historyInsert->close();

    if (!$db->commit()) {
        throw new RuntimeException('Unable to commit transfer submission: ' . $db->error);
    }
    $transactionStarted = false;
    respondBankTransfer(201, ['verification_status' => 'pending_verification']);
} catch (Throwable $exception) {
    if ($transactionStarted && isset($db) && $db instanceof mysqli) {
        try {
            $db->rollback();
        } catch (Throwable $rollbackException) {
            error_log('Bank transfer submission rollback failed: ' . $rollbackException->getMessage());
        }
    }
    $reference = bin2hex(random_bytes(4));
    error_log(sprintf('Bank transfer submission failed [%s]: %s', $reference, $exception->getMessage()));
    respondBankTransfer(500, ['error' => 'Unable to submit your transfer right now. Please try again or contact support.']);
}
