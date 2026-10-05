<?php
declare(strict_types=1);

const PAYSTACK_BASE_URL = 'https://api.paystack.co';
const PAYSTACK_CURRENCY = 'GHS';

function paystackSecretKey(): string
{
    $key = getenv('PAYSTACK_SECRET_KEY');
    if ($key !== false && trim($key) !== '') return trim($key);
    $file = __DIR__ . '/secrets.php';
    if (is_file($file)) {
        $secrets = require $file;
        if (is_array($secrets) && !empty($secrets['paystack_secret_key'])) return trim((string)$secrets['paystack_secret_key']);
    }
    return '';
}

function paystackMode(): string
{
    $file = __DIR__ . '/secrets.php';
    if (is_file($file)) {
        $secrets = require $file;
        if (is_array($secrets) && !empty($secrets['paystack_mode'])) return (string)$secrets['paystack_mode'];
    }
    return str_starts_with(paystackSecretKey(), 'sk_live_') ? 'live' : 'test';
}

function paystackRequest(string $method, string $endpoint, ?array $payload = null): array
{
    $secret = paystackSecretKey();
    if ($secret === '') throw new RuntimeException('Paystack secret key is not configured on the server.');
    $ch = curl_init(rtrim(PAYSTACK_BASE_URL, '/') . '/' . ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$secret, 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($payload !== null) {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) { curl_close($ch); throw new RuntimeException('Unable to encode Paystack request.'); }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) throw new RuntimeException('Unable to contact Paystack: '.$error);
    $data = json_decode($body, true);
    if (!is_array($data)) throw new RuntimeException('Paystack returned an invalid response. HTTP '.$http);
    if ($http < 200 || $http >= 300 || empty($data['status'])) throw new RuntimeException((string)($data['message'] ?? ('Paystack request failed with HTTP '.$http)));
    return $data;
}

function verifyPaystackSignature(string $rawBody, string $signature): bool
{
    $secret = paystackSecretKey();
    if ($secret === '' || trim($signature) === '') return false;
    return hash_equals(hash_hmac('sha512', $rawBody, $secret), trim($signature));
}
