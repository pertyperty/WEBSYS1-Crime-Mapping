<?php
function load_env_file(string $path): void
{
    static $loaded = [];
    if (isset($loaded[$path])) {
        return;
    }

    if (!is_file($path)) {
        $loaded[$path] = true;
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        $loaded[$path] = true;
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        $value = trim($value, "\"'");

        if (getenv($name) === false) {
            putenv("{$name}={$value}");
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    $loaded[$path] = true;
}

load_env_file(__DIR__ . '/.env');
load_env_file(__DIR__ . '/.env.local');

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }

    if ($value === false || $value === '' || $value === null) {
        return $default;
    }

    return (string) $value;
}

return [
    'db_host' => env_value('CRIME_DB_HOST', '127.0.0.1'),
    'db_name' => env_value('CRIME_DB_NAME', 'crime_mapping'),
    'db_user' => env_value('CRIME_DB_USER', 'root'),
    'db_pass' => env_value('CRIME_DB_PASS', ''),
    'db_charset' => env_value('CRIME_DB_CHARSET', 'utf8mb4'),
    'sms_provider' => env_value('CRIME_SMS_PROVIDER', 'mock'),
    'sms_twilio_account_sid' => env_value('CRIME_SMS_TWILIO_SID'),
    'sms_twilio_auth_token' => env_value('CRIME_SMS_TWILIO_TOKEN'),
    'sms_twilio_from' => env_value('CRIME_SMS_TWILIO_FROM'),
    'sms_semaphore_api_key' => env_value('CRIME_SMS_SEMAPHORE_API_KEY'),
    'sms_semaphore_sender_name' => env_value('CRIME_SMS_SEMAPHORE_SENDER_NAME', 'CrimeAlert'),
    'sms_semaphore_api_url' => env_value('CRIME_SMS_SEMAPHORE_API_URL', 'https://api.semaphore.co/api/v4/messages')
];
