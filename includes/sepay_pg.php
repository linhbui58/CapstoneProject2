<?php

function getSePayGatewayConfig(bool $required = true): array
{
    $configPath = dirname(__DIR__) . '/config/sepay.php';
    $config = [];
    if (is_file($configPath)) {
        ob_start();
        try {
            $loadedConfig = require $configPath;
            $configOutput = ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw new RuntimeException('SePay configuration could not be loaded.', 0, $exception);
        }

        if ($configOutput !== '' || !is_array($loadedConfig)) {
            throw new RuntimeException('SePay configuration must return an array without producing output.');
        }
        $config = $loadedConfig;
    }

    $readSetting = static function (string $environmentKey, string $configKey) use ($config): string {
        $value = getenv($environmentKey);
        if (!is_string($value) || trim($value) === '') {
            $value = $config[$configKey] ?? '';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    };

    $merchantId = $readSetting('SEPAY_PG_MERCHANT_ID', 'merchant_id');
    $secretKey = $readSetting('SEPAY_PG_SECRET_KEY', 'merchant_secret_key');
    $mode = $readSetting('SEPAY_PG_MODE', 'mode');

    if ($merchantId === '' && $secretKey === '' && $mode === '' && !$required) {
        return [];
    }

    if (!in_array($mode, ['sandbox', 'production'], true)
        || !preg_match($mode === 'sandbox' ? '/^SP-TEST-[A-Za-z0-9]+$/' : '/^SP-LIVE-[A-Za-z0-9]+$/', $merchantId)
        || $secretKey === '') {
        throw new RuntimeException('SePay Payment Gateway configuration is incomplete or does not match the selected environment.');
    }

    return [
        'merchant_id' => $merchantId,
        'merchant_secret_key' => $secretKey,
        'mode' => $mode,
    ];
}

function getSePayCheckoutUrl(array $config): string
{
    return $config['mode'] === 'production'
        ? 'https://pay.sepay.vn/v1/checkout/init'
        : 'https://pay-sandbox.sepay.vn/v1/checkout/init';
}

function signSePayCheckoutFields(array $fields, string $secretKey): string
{
    $signedFields = [
        'merchant',
        'currency',
        'order_amount',
        'operation',
        'order_description',
        'payment_method',
        'order_invoice_number',
        'customer_id',
        'success_url',
        'error_url',
        'cancel_url',
    ];
    $signedValues = [];
    foreach ($signedFields as $field) {
        if (isset($fields[$field])) {
            $signedValues[] = $field . '=' . $fields[$field];
        }
    }

    return base64_encode(hash_hmac('sha256', implode(',', $signedValues), $secretKey, true));
}

function getSePayIpnSecret(): string
{
    $secret = getenv('SEPAY_PG_IPN_SECRET');
    if (is_string($secret) && trim($secret) !== '') {
        return trim($secret);
    }

    $configPath = dirname(__DIR__) . '/config/sepay.php';
    if (!is_file($configPath)) {
        return '';
    }

    ob_start();
    try {
        $config = require $configPath;
        $configOutput = ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw new RuntimeException('SePay IPN configuration could not be loaded.', 0, $exception);
    }
    if ($configOutput !== '' || !is_array($config)) {
        throw new RuntimeException('SePay configuration must return an array without producing output.');
    }

    $secret = $config['ipn_secret_key'] ?? '';
    return is_string($secret) ? trim($secret) : '';
}
