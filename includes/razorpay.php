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

function razorpay_http_post(string $url, array $payload, array $creds): array
{
    $jsonPayload = json_encode($payload);
    $authHeader = 'Basic ' . base64_encode($creds['key_id'] . ':' . $creds['key_secret']);

    // Attempt 1: cURL (if available in PHP runtime)
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_USERPWD => $creds['key_id'] . ':' . $creds['key_secret'],
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT => 25,
            ]);

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

            if ($response !== false && $curlErr === '') {
                return [
                    'success' => true,
                    'status_code' => $httpCode,
                    'body' => (string)$response,
                ];
            }
            error_log('Razorpay cURL failed, falling back to stream context: ' . $curlErr);
        }
    }

    // Attempt 2: Native PHP Stream Context fallback (works without ext-curl)
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: {$authHeader}\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $jsonPayload,
            'timeout' => 25,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => stripos(PHP_OS, 'WIN') === false,
            'verify_peer_name' => stripos(PHP_OS, 'WIN') === false,
        ],
    ];

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);

    $httpCode = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                $httpCode = (int)$matches[1];
            }
        }
    }

    if ($response === false) {
        $err = error_get_last();
        $msg = $err['message'] ?? 'Network connection failed';
        return [
            'success' => false,
            'status_code' => $httpCode ?: 500,
            'error' => 'Connection to Razorpay failed: ' . $msg,
        ];
    }

    return [
        'success' => true,
        'status_code' => $httpCode,
        'body' => (string)$response,
    ];
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

    $res = razorpay_http_post('https://api.razorpay.com/v1/orders', $payload, $creds);
    if (!$res['success']) {
        return ['success' => false, 'error' => $res['error'] ?? 'Connection to Razorpay failed.'];
    }

    $httpCode = $res['status_code'];
    $data = json_decode((string)($res['body'] ?? ''), true);

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
