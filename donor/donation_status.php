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
        'SELECT d.status, d.amount, d.channel, d.payment_provider,
                t.id, t.verification_status, t.rejection_reason
         FROM donations d
         LEFT JOIN transactions t ON t.donation_id = d.id
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
    $query->bind_result($status, $donationAmount, $channel, $paymentProvider, $transactionId, $verificationStatus, $rejectionReason);
    if (!$query->fetch()) {
        $query->close();
        respond(404, ['error' => 'Donation not found.']);
    }
    $query->close();

    if ($channel === 'bank_transfer' && $paymentProvider === 'payos' && empty($transactionId) && $status === 'pending') {
        $payOsConfig = getPayOsConfig();
        $paymentResponse = getPayOsPaymentRequest($payOsConfig, (int) $donationId);
        $paymentData = $paymentResponse['data'] ?? null;
        if (!is_array($paymentData) || (int) ($paymentData['orderCode'] ?? 0) !== (int) $donationId) {
            throw new RuntimeException('payOS payment status did not match the donation order.');
        }

        $paidAmount = $paymentData['amountPaid'] ?? null;
        if (($paymentData['status'] ?? '') === 'PAID'
            && is_numeric($paidAmount)
            && (float) $paidAmount === (float) $donationAmount) {
            if (!$db->begin_transaction()) {
                throw new RuntimeException('Unable to start PayOS payment confirmation: ' . $db->error);
            }
            try {
                $lockQuery = $db->prepare(
                    "SELECT d.donor_id, d.amount, d.status
                     FROM donations d
                     WHERE d.id = ? AND d.payment_provider = 'payos'
                     LIMIT 1 FOR UPDATE"
                );
                if (!$lockQuery || !$lockQuery->bind_param('i', $donationId) || !$lockQuery->execute()) {
                    throw new RuntimeException('Unable to lock donation for PayOS confirmation: ' . ($lockQuery ? $lockQuery->error : $db->error));
                }
                $lockQuery->bind_result($lockedDonorId, $lockedAmount, $lockedStatus);
                if (!$lockQuery->fetch()) {
                    $lockQuery->close();
                    throw new RuntimeException('Donation disappeared during PayOS confirmation.');
                }
                $lockQuery->close();

                $existingTransactionQuery = $db->prepare('SELECT id FROM transactions WHERE donation_id = ? LIMIT 1');
                if (!$existingTransactionQuery || !$existingTransactionQuery->bind_param('i', $donationId)
                    || !$existingTransactionQuery->execute()) {
                    throw new RuntimeException('Unable to check PayOS transaction: ' . ($existingTransactionQuery ? $existingTransactionQuery->error : $db->error));
                }
                $existingTransactionQuery->store_result();
                $hasTransaction = $existingTransactionQuery->num_rows > 0;
                $existingTransactionQuery->close();

                if (!$hasTransaction && $lockedStatus === 'pending'
                    && (float) $lockedAmount === (float) $paidAmount) {
                    $transactionDate = (new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s');
                    $referenceCode = 'PAYOS-' . $donationId;
                    $bankName = 'PayOS bank transfer';
                    $actualAmount = (float) $paidAmount;
                    $transactionInsert = $db->prepare(
                        "INSERT INTO transactions
                            (donation_id, reference_code, bank_name, transaction_date, amount_transferred, verification_status)
                         VALUES (?, ?, ?, ?, ?, 'pending_verification')"
                    );
                    if (!$transactionInsert || !$transactionInsert->bind_param(
                        'isssd',
                        $donationId,
                        $referenceCode,
                        $bankName,
                        $transactionDate,
                        $actualAmount
                    ) || !$transactionInsert->execute()) {
                        throw new RuntimeException('Unable to save PayOS payment: ' . ($transactionInsert ? $transactionInsert->error : $db->error));
                    }
                    $newTransactionId = (int) $db->insert_id;
                    $transactionInsert->close();

                    $historyNotes = sprintf(
                        'payOS confirmed receipt of %s VND. Finance staff verification is still required.',
                        number_format($actualAmount, 0, '.', ',')
                    );
                    $historyInsert = $db->prepare(
                        "INSERT INTO verification_history (transaction_id, action, performed_by, old_status, new_status, notes)
                         VALUES (?, 'submitted', ?, NULL, 'pending_verification', ?)"
                    );
                    if (!$historyInsert || !$historyInsert->bind_param('iis', $newTransactionId, $lockedDonorId, $historyNotes)
                        || !$historyInsert->execute()) {
                        throw new RuntimeException('Unable to save PayOS verification history: ' . ($historyInsert ? $historyInsert->error : $db->error));
                    }
                    $historyInsert->close();

                    $notificationTitle = 'Đã nhận được thanh toán';
                    $notificationMessage = 'payOS xác nhận quỹ đã nhận ' . number_format($actualAmount, 0, '.', ',')
                        . ' VND. Khoản quyên góp đang chờ nhân viên tài chính xác nhận.';
                    $notificationType = 'system';
                    $notificationInsert = $db->prepare(
                        'INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)'
                    );
                    if (!$notificationInsert || !$notificationInsert->bind_param(
                        'isss',
                        $lockedDonorId,
                        $notificationTitle,
                        $notificationMessage,
                        $notificationType
                    ) || !$notificationInsert->execute()) {
                        throw new RuntimeException('Unable to notify donor about PayOS payment: ' . ($notificationInsert ? $notificationInsert->error : $db->error));
                    }
                    $notificationInsert->close();
                }
                if (!$db->commit()) {
                    throw new RuntimeException('Unable to commit PayOS payment confirmation: ' . $db->error);
                }
            } catch (Throwable $exception) {
                $db->rollback();
                throw $exception;
            }

            respond(200, [
                'status' => 'pending',
                'verification_status' => 'pending_verification',
                'rejection_reason' => null,
            ]);
        }

        if (($paymentData['status'] ?? '') === 'PAID' && is_numeric($paidAmount)
            && (float) $paidAmount !== (float) $donationAmount) {
            error_log(sprintf(
                'PayOS amount mismatch for donation %d: expected %s, received %s.',
                $donationId,
                (string) $donationAmount,
                (string) $paidAmount
            ));
        }
    }

    respond(200, [
        'status' => $status,
        'verification_status' => $verificationStatus ?? 'awaiting_transfer',
        'rejection_reason' => $rejectionReason,
    ]);
} catch (Throwable $exception) {
    error_log('Donation status lookup failed: ' . $exception->getMessage());
    respond(500, ['error' => 'Unable to check the donation status right now.']);
}
