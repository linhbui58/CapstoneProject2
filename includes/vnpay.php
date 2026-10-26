<?php

function getVnPayConfig(bool $required = true): array
{
    $configPath = dirname(__DIR__) . '/config/vnpay.php';
    if (!is_file($configPath)) {
        if ($required) {
            throw new RuntimeException('VNPay is not configured on this server.');
        }

        return [];
    }

    ob_start();
    try {
        $config = require $configPath;
        $configOutput = ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw new RuntimeException('VNPay configuration could not be loaded.', 0, $exception);
    }

    if ($configOutput !== '' || !is_array($config)) {
        throw new RuntimeException('VNPay configuration must return an array without producing output.');
    }

    $tmnCode = $config['tmn_code'] ?? '';
    $hashSecret = $config['hash_secret'] ?? '';
    $mode = $config['mode'] ?? '';
    if (!is_string($tmnCode) || !preg_match('/^[A-Za-z0-9]{8}$/', $tmnCode)
        || !is_string($hashSecret) || trim($hashSecret) === ''
        || !in_array($mode, ['sandbox', 'live'], true)) {
        throw new RuntimeException('VNPay configuration is incomplete or invalid.');
    }

    return [
        'tmn_code' => $tmnCode,
        'hash_secret' => trim($hashSecret),
        'mode' => $mode,
    ];
}

function getVnPayGatewayUrl(array $config): string
{
    return $config['mode'] === 'live'
        ? 'https://pay.vnpay.vn/vpcpay.html'
        : 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
}

function createVnPayPaymentUrl(array $config, array $parameters): string
{
    ksort($parameters);
    $hashData = [];
    foreach ($parameters as $key => $value) {
        $hashData[] = urlencode((string) $key) . '=' . urlencode((string) $value);
    }

    $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC1738);
    $secureHash = hash_hmac('sha512', implode('&', $hashData), $config['hash_secret']);

    return getVnPayGatewayUrl($config) . '?' . $query . '&vnp_SecureHash=' . $secureHash;
}

function isValidVnPaySignature(array $parameters, string $hashSecret): bool
{
    $secureHash = $parameters['vnp_SecureHash'] ?? '';
    if (!is_string($secureHash) || !preg_match('/^[a-fA-F0-9]{128}$/', $secureHash)) {
        return false;
    }

    unset($parameters['vnp_SecureHash'], $parameters['vnp_SecureHashType']);
    $parameters = array_filter(
        $parameters,
        static fn ($value, $key): bool => substr((string) $key, 0, 4) === 'vnp_' && is_scalar($value),
        ARRAY_FILTER_USE_BOTH
    );
    ksort($parameters);

    $hashData = [];
    foreach ($parameters as $key => $value) {
        $hashData[] = urlencode((string) $key) . '=' . urlencode((string) $value);
    }
    $expectedHash = hash_hmac('sha512', implode('&', $hashData), $hashSecret);

    return hash_equals(strtolower($expectedHash), strtolower($secureHash));
}

function processVnPayCallback(array $parameters, array $config): array
{
    if (!isValidVnPaySignature($parameters, $config['hash_secret'])
        || ($parameters['vnp_TmnCode'] ?? '') !== $config['tmn_code']) {
        return ['response_code' => '97', 'message' => 'Invalid signature.'];
    }

    $referenceCode = $parameters['vnp_TxnRef'] ?? '';
    $amountInCents = $parameters['vnp_Amount'] ?? '';
    $responseCode = $parameters['vnp_ResponseCode'] ?? null;
    $transactionStatus = $parameters['vnp_TransactionStatus'] ?? null;
    if (!is_string($referenceCode) || !preg_match('/^[A-Za-z0-9]{1,100}$/', $referenceCode)
        || !is_string($amountInCents) || !ctype_digit($amountInCents)
        || !is_string($responseCode) || !preg_match('/^[0-9]{2}$/', $responseCode)
        || !is_string($transactionStatus) || !preg_match('/^[0-9]{2}$/', $transactionStatus)) {
        return ['response_code' => '99', 'message' => 'Invalid payment data.'];
    }

    $db = getApplicationDB();
    $transactionStarted = false;
    try {
        if (!$db->begin_transaction()) {
            throw new RuntimeException('Unable to start VNPay callback transaction: ' . $db->error);
        }
        $transactionStarted = true;

        $lookup = $db->prepare(
            'SELECT t.id, d.id, d.donor_id, d.campaign_id, d.amount, d.status, t.verification_status
             FROM transactions t
             JOIN donations d ON d.id = t.donation_id
             WHERE t.reference_code = ? AND d.channel = \'credit_card\'
             LIMIT 1 FOR UPDATE'
        );
        if (!$lookup) {
            throw new RuntimeException('Unable to prepare VNPay transaction lookup: ' . $db->error);
        }
        if (!$lookup->bind_param('s', $referenceCode) || !$lookup->execute()) {
            throw new RuntimeException('Unable to look up VNPay transaction: ' . $lookup->error);
        }
        $lookup->bind_result($transactionId, $donationId, $donorId, $campaignId, $amountVnd, $donationStatus, $verificationStatus);
        if (!$lookup->fetch()) {
            $lookup->close();
            $db->rollback();
            $transactionStarted = false;
            return ['response_code' => '01', 'message' => 'Order not found.'];
        }
        $lookup->close();

        $expectedAmount = number_format((float) $amountVnd * 100, 0, '.', '');
        $providedAmount = ltrim($amountInCents, '0');
        if ($providedAmount === '') {
            $providedAmount = '0';
        }
        if ($providedAmount !== $expectedAmount) {
            $db->rollback();
            $transactionStarted = false;
            return ['response_code' => '04', 'message' => 'Invalid amount.'];
        }

        if (in_array($donationStatus, ['verified', 'rejected'], true)
            && $verificationStatus === $donationStatus) {
            $db->commit();
            $transactionStarted = false;
            return [
                'response_code' => '00',
                'message' => 'Confirm Success',
                'donation_id' => (int) $donationId,
                'donor_id' => (int) $donorId,
                'paid' => $donationStatus === 'verified',
            ];
        }

        if ($donationStatus !== 'pending' || $verificationStatus !== 'pending_verification') {
            $db->rollback();
            $transactionStarted = false;
            return ['response_code' => '02', 'message' => 'Order already confirmed.'];
        }

        $paid = $responseCode === '00' && $transactionStatus === '00';
        $transactionId = (int) $transactionId;
        $donationId = (int) $donationId;
        $donorId = (int) $donorId;
        if ($paid) {
            $updateTransaction = $db->prepare(
                "UPDATE transactions SET verification_status = 'verified', transaction_date = NOW(),
                 amount_transferred = ?, verified_at = NOW()
                 WHERE id = ? AND verification_status = 'pending_verification'"
            );
            if (!$updateTransaction) {
                throw new RuntimeException('Unable to prepare VNPay payment update: ' . $db->error);
            }
            if (!$updateTransaction->bind_param('di', $amountVnd, $transactionId) || !$updateTransaction->execute()
                || $updateTransaction->affected_rows !== 1) {
                throw new RuntimeException('Unable to verify VNPay transaction: ' . $updateTransaction->error);
            }
            $updateTransaction->close();

            $updateDonation = $db->prepare("UPDATE donations SET status = 'verified' WHERE id = ? AND status = 'pending'");
            if (!$updateDonation || !$updateDonation->bind_param('i', $donationId) || !$updateDonation->execute()
                || $updateDonation->affected_rows !== 1) {
                throw new RuntimeException('Unable to verify donation: ' . ($updateDonation ? $updateDonation->error : $db->error));
            }
            $updateDonation->close();

            if ($campaignId !== null) {
                $campaignId = (int) $campaignId;
                $updateCampaign = $db->prepare('UPDATE campaigns SET raised_amount = raised_amount + ? WHERE id = ?');
                if (!$updateCampaign || !$updateCampaign->bind_param('di', $amountVnd, $campaignId) || !$updateCampaign->execute()) {
                    throw new RuntimeException('Unable to update campaign total: ' . ($updateCampaign ? $updateCampaign->error : $db->error));
                }
                $updateCampaign->close();
            }

            $updateDonor = $db->prepare('UPDATE donor_profiles SET total_donated = total_donated + ? WHERE user_id = ?');
            if (!$updateDonor || !$updateDonor->bind_param('di', $amountVnd, $donorId) || !$updateDonor->execute()) {
                throw new RuntimeException('Unable to update donor total: ' . ($updateDonor ? $updateDonor->error : $db->error));
            }
            $updateDonor->close();

            $historyNotes = 'VNPay card payment completed. Transaction number: '
                . (string) ($parameters['vnp_TransactionNo'] ?? '');
            $history = $db->prepare(
                "INSERT INTO verification_history (transaction_id, action, performed_by, old_status, new_status, notes)
                 VALUES (?, 'verified', ?, 'pending_verification', 'verified', ?)"
            );
            if (!$history || !$history->bind_param('iis', $transactionId, $donorId, $historyNotes) || !$history->execute()) {
                throw new RuntimeException('Unable to save VNPay verification history: ' . ($history ? $history->error : $db->error));
            }
            $history->close();

            $notificationMessage = 'Your credit card donation of '
                . number_format((float) $amountVnd, 0, '.', ',')
                . ' VND has been verified. Thank you for your generous contribution.';
            $notification = $db->prepare(
                "INSERT INTO notifications (user_id, title, message, type)
                 VALUES (?, 'Donation Verified', ?, 'confirmation')"
            );
            if (!$notification || !$notification->bind_param('is', $donorId, $notificationMessage) || !$notification->execute()) {
                throw new RuntimeException('Unable to notify donor about VNPay payment: ' . ($notification ? $notification->error : $db->error));
            }
            $notification->close();
        } else {
            $reason = 'VNPay payment was not completed (response code ' . $responseCode . ').';
            $updateTransaction = $db->prepare(
                "UPDATE transactions SET verification_status = 'rejected', rejection_reason = ?
                 WHERE id = ? AND verification_status = 'pending_verification'"
            );
            if (!$updateTransaction || !$updateTransaction->bind_param('si', $reason, $transactionId) || !$updateTransaction->execute()
                || $updateTransaction->affected_rows !== 1) {
                throw new RuntimeException('Unable to record failed VNPay payment: ' . ($updateTransaction ? $updateTransaction->error : $db->error));
            }
            $updateTransaction->close();

            $updateDonation = $db->prepare("UPDATE donations SET status = 'rejected' WHERE id = ? AND status = 'pending'");
            if (!$updateDonation || !$updateDonation->bind_param('i', $donationId) || !$updateDonation->execute()
                || $updateDonation->affected_rows !== 1) {
                throw new RuntimeException('Unable to reject failed donation: ' . ($updateDonation ? $updateDonation->error : $db->error));
            }
            $updateDonation->close();

            $history = $db->prepare(
                "INSERT INTO verification_history (transaction_id, action, performed_by, old_status, new_status, notes)
                 VALUES (?, 'rejected', ?, 'pending_verification', 'rejected', ?)"
            );
            if (!$history || !$history->bind_param('iis', $transactionId, $donorId, $reason) || !$history->execute()) {
                throw new RuntimeException('Unable to save failed VNPay payment history: ' . ($history ? $history->error : $db->error));
            }
            $history->close();
        }

        if (!$db->commit()) {
            throw new RuntimeException('Unable to commit VNPay payment result: ' . $db->error);
        }
        $transactionStarted = false;

        return [
            'response_code' => '00',
            'message' => 'Confirm Success',
            'donation_id' => $donationId,
            'donor_id' => $donorId,
            'paid' => $paid,
        ];
    } catch (Throwable $exception) {
        if ($transactionStarted) {
            $db->rollback();
        }
        throw $exception;
    }
}
