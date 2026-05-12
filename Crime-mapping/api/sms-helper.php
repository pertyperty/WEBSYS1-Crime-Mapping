<?php

function normalize_phone_number(string $phone): string
{
    $normalized = trim($phone);
    $normalized = preg_replace('/[^0-9+]/', '', $normalized);

    if ($normalized === '') {
        return '';
    }

    if ($normalized[0] !== '+') {
        $normalized = '+' . $normalized;
    }

    return $normalized;
}

function is_valid_phone_number(string $phone): bool
{
    $normalized = normalize_phone_number($phone);
    return (bool) preg_match('/^\+\d{10,15}$/', $normalized);
}

function should_send_sms_for_notification_type(string $notificationType): bool
{
    return in_array($notificationType, ['high_severity', 'status_update'], true);
}

function send_sms_via_provider(string $phone, string $message, array $config): array
{
    $provider = strtolower(trim((string) ($config['sms_provider'] ?? 'mock')));

    if ($provider === 'mock') {
        return [
            'success' => true,
            'error' => null,
            'provider' => 'mock'
        ];
    }

    if ($provider === 'twilio') {
        $sid = $config['sms_twilio_account_sid'] ?? '';
        $token = $config['sms_twilio_auth_token'] ?? '';
        $from = $config['sms_twilio_from'] ?? '';

        if ($sid === '' || $token === '' || $from === '') {
            return [
                'success' => false,
                'error' => 'Twilio configuration is incomplete.',
                'provider' => 'twilio'
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'error' => 'cURL is required for Twilio SMS delivery.',
                'provider' => 'twilio'
            ];
        }

        $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', rawurlencode($sid));
        $payload = http_build_query([
            'From' => $from,
            'To' => $phone,
            'Body' => $message
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $sid . ':' . $token);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || $httpCode >= 400) {
            $error = $curlError ?: sprintf('Twilio API error %d', $httpCode);
            return [
                'success' => false,
                'error' => $error,
                'provider' => 'twilio',
                'response' => $responseBody
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'provider' => 'twilio',
            'response' => $responseBody
        ];
    }

    if ($provider === 'semaphore') {
        $apiKey = $config['sms_semaphore_api_key'] ?? '';
        $sender = $config['sms_semaphore_sender_name'] ?? '';
        $url = $config['sms_semaphore_api_url'] ?? 'https://api.semaphore.co/api/v4/messages';

        if ($apiKey === '') {
            return [
                'success' => false,
                'error' => 'Semaphore API key is not configured.',
                'provider' => 'semaphore'
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'error' => 'cURL is required for Semaphore SMS delivery.',
                'provider' => 'semaphore'
            ];
        }

        $payload = http_build_query([
            'apikey' => $apiKey,
            'number' => $phone,
            'message' => $message,
            'sendername' => $sender
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || $httpCode >= 400) {
            $error = $curlError ?: sprintf('Semaphore API error %d', $httpCode);
            return [
                'success' => false,
                'error' => $error,
                'provider' => 'semaphore',
                'response' => $responseBody
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'provider' => 'semaphore',
            'response' => $responseBody
        ];
    }

    return [
        'success' => false,
        'error' => 'Unsupported SMS provider.',
        'provider' => $provider
    ];
}

function send_sms_message(string $phone, string $message, array $config): array
{
    $normalized = normalize_phone_number($phone);
    if (!is_valid_phone_number($normalized)) {
        return [
            'success' => false,
            'error' => 'Invalid phone number format.',
            'provider' => $config['sms_provider'] ?? 'mock'
        ];
    }

    return send_sms_via_provider($normalized, $message, $config);
}

function enqueue_notification_sms(PDO $pdo, int $notificationId, int $userId, string $phone, string $message): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notification_sms_queue (notification_id, user_id, phone, message, status, attempts)
        VALUES (:notification_id, :user_id, :phone, :message, :status, :attempts)'
    );

    $stmt->execute([
        ':notification_id' => $notificationId,
        ':user_id' => $userId,
        ':phone' => normalize_phone_number($phone),
        ':message' => $message,
        ':status' => 'pending',
        ':attempts' => 0
    ]);
}
