<?php
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
    'db_charset' => env_value('CRIME_DB_CHARSET', 'utf8mb4')
];
