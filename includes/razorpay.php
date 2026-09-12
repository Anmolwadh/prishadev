<?php
/**
 * Razorpay Payment Gateway Integration - Prisha Enterprises
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function razorpay_get_credentials(): array
{
    static $creds = null;
    if ($creds !== null) {
        return $creds;
    }

    $enabled = get_setting('razorpay_enabled', '1');
    $keyId = get_setting('razorpay_key_id', 'rzp_test_TbDuFqvITs1a92');
    $keySecret = get_setting('razorpay_key_secret', 'Q5g6n8xT05olD7kAM2GNEUEA');
    $mode = get_setting('razorpay_mode', 'test');

    $creds = [
        'enabled' => ($enabled === '1' || $enabled === 'yes' || $enabled === 'true'),
        'key_id' => trim((string)$keyId),
        'key_secret' => trim((string)$keySecret),
        'mode' => strtolower(trim((string)$mode)) === 'live' ? 'live' : 'test',
    ];

    return $creds;
}

function razorpay_is_enabled(): bool
{
    $creds = razorpay_get_credentials();
    return $creds['enabled'] && !empty($creds['key_id']) && !empty($creds['key_secret']);
}

function razorpay_create_order(float $amount, string $receipt, array $notes = []): array
{
    $creds = razorpay_get_credentials();
    if (!razorpay_is_enabled()) {
        return ['success' => false, 'error' => 'Razorpay payment gateway is not enabled.'];
    }

    $amountPaise = (int)round($amount * 100);
    if ($amountPaise < 100) {
        return ['success' => false, 'error' => 'Order amount must be at least ₹1.00.'];
    }

    $payload = [
        'amount' => $amountPaise,
        'currency' => 'INR',
        'receipt' => substr($receipt, 0, 40),
        'payment_capture' => 1, // Auto-capture payment upon authorization
        'notes' => $notes,
    ];

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    if ($ch === false) {
        return ['success' => false, 'error' => 'Unable to initialize cURL for payment gateway.'];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_USERPWD => $creds['key_id'] . ':' . $creds['key_secret'],
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 25,
    ]);

    // On local Windows dev, disable local cert check if running on Windows; on live Linux, verify SSL
    if (stripos(PHP_OS, 'WIN') !== false) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    }

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlErr !== '') {
        error_log('Razorpay API request error: ' . $curlErr);
        return ['success' => false, 'error' => 'Connection to Razorpay failed: ' . $curlErr];
    }

    $data = json_decode((string)$response, true);
    if ($httpCode >= 200 && $httpCode < 300 && isset($data['id'])) {
        return [
            'success' => true,
            'order_id' => $data['id'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'INR',
            'key_id' => $creds['key_id'],
        ];
    }

    $errMsg = $data['error']['description'] ?? 'Failed to create Razorpay order (HTTP ' . $httpCode . ')';
    error_log('Razorpay Order Creation Failed: ' . json_encode($data));
    return ['success' => false, 'error' => $errMsg];
}

function razorpay_verify_signature(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature): bool
{
    $creds = razorpay_get_credentials();
    if (empty($creds['key_secret'])) {
        return false;
    }

    $expected = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $creds['key_secret']);
    return hash_equals($expected, $razorpaySignature);
}
