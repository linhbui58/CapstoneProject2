<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/vnpay.php';

header('Content-Type: application/json; charset=utf-8');

function respondVnPayIpn(string $responseCode, string $message): void
{
    echo json_encode([
        'RspCode' => $responseCode,
        'Message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondVnPayIpn('99', 'Invalid request method.');
}

try {
    $config = getVnPayConfig();
    $result = processVnPayCallback($_GET, $config);
    respondVnPayIpn($result['response_code'], $result['message']);
} catch (Throwable $exception) {
    error_log('VNPay IPN callback failed: ' . $exception->getMessage());
    respondVnPayIpn('99', 'Internal error.');
}
