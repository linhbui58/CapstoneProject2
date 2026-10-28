<?php

function getPayOsConfig(bool $required = true): array
{
    $configPath = dirname(__DIR__) . '/config/payos.php';
    $config = [];
    if (is_file($configPath)) {
        ob_start();
        try {
            $loadedConfig = require $configPath;
            $configOutput = ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw new RuntimeException('payOS configuration could not be loaded.', 0, $exception);
        }

        if ($configOutput !== '' || !is_array($loadedConfig)) {
            throw new RuntimeException('payOS configuration must return an array without producing output.');
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

    $clientId = $readSetting('PAYOS_CLIENT_ID', 'client_id');
    $apiKey = $readSetting('PAYOS_API_KEY', 'api_key');
    $checksumKey = $readSetting('PAYOS_CHECKSUM_KEY', 'checksum_key');

    if ($clientId === '' && $apiKey === '' && $checksumKey === '' && !$required) {
        return [];
    }
    if ($clientId === '' || $apiKey === '' || $checksumKey === '') {
        throw new RuntimeException('payOS configuration requires client_id, api_key, and checksum_key.');
    }

    return [
        'client_id' => $clientId,
        'api_key' => $apiKey,
        'checksum_key' => $checksumKey,
    ];
}

function createPayOsPaymentLink(array $config, int $orderCode, int $amount, string $returnUrl, string $cancelUrl): string
{
    $description = 'DONATE';
    $signatureData = sprintf(
        'amount=%d&cancelUrl=%s&description=%s&orderCode=%d&returnUrl=%s',
        $amount,
        $cancelUrl,
        $description,
        $orderCode,
        $returnUrl
    );
    $body = [
        'orderCode' => $orderCode,
        'amount' => $amount,
        'description' => $description,
        'returnUrl' => $returnUrl,
        'cancelUrl' => $cancelUrl,
        'signature' => hash_hmac('sha256', $signatureData, $config['checksum_key']),
    ];
    $response = callPayOsApi($config, 'POST', '/v2/payment-requests', $body);
    $checkoutUrl = $response['data']['checkoutUrl'] ?? null;
    if (!is_string($checkoutUrl) || filter_var($checkoutUrl, FILTER_VALIDATE_URL) === false
        || parse_url($checkoutUrl, PHP_URL_SCHEME) !== 'https') {
        throw new RuntimeException('payOS did not return a valid secure checkout URL.');
    }

    return $checkoutUrl;
}

function getPayOsPaymentRequest(array $config, int $orderCode): array
{
    return callPayOsApi($config, 'GET', '/v2/payment-requests/' . $orderCode);
}

function callPayOsApi(array $config, string $method, string $path, ?array $body = null): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required to connect to payOS.');
    }

    $curl = curl_init('https://api-merchant.payos.vn' . $path);
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize the payOS API request.');
    }

    $headers = [
        'x-client-id: ' . $config['client_id'],
        'x-api-key: ' . $config['api_key'],
        'Accept: application/json',
    ];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if ($body !== null) {
        $encodedBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encodedBody)) {
            curl_close($curl);
            throw new RuntimeException('Unable to encode the payOS API request.');
        }
        $options[CURLOPT_POSTFIELDS] = $encodedBody;
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }
    if (!curl_setopt_array($curl, $options)) {
        curl_close($curl);
        throw new RuntimeException('Unable to configure the payOS API request.');
    }

    $responseBody = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if (!is_string($responseBody)) {
        throw new RuntimeException('payOS API request failed: ' . $curlError);
    }
    $response = json_decode($responseBody, true);
    if (!is_array($response)) {
        throw new RuntimeException('payOS returned an invalid response (HTTP ' . $httpStatus . ').');
    }
    if ($httpStatus < 200 || $httpStatus >= 300 || ($response['code'] ?? null) !== '00') {
        $description = is_string($response['desc'] ?? null) ? $response['desc'] : 'Unknown API error';
        throw new RuntimeException('payOS API request failed (HTTP ' . $httpStatus . '): ' . $description);
    }

    return $response;
}
