<?php
/**
 * secure/mail.php
 * Config seguro basado en variables de entorno o secure/mail.env (no versionado).
 * - Producción: usa SMTP_* obligatorios.
 * - Sandbox/local: usa *_LOCAL y se activa con APP_ENV=local o detección de localhost.
 */

// Polyfill para PHP < 8
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

// Cargar opcionalmente secure/mail.env (excluido del repo)
$envFile = __DIR__ . '/mail.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        if ($k !== '') {
            putenv("$k=$v");
        }
    }
}

$env = getenv('APP_ENV') ?: null;
$forceProd = getenv('SMTP_FORCE_PROD');
$useSandbox = ($env === 'local');
$skipLocalDetection = ($env === 'prod' || $env === 'production' || $forceProd);

// Si corremos en local/CLI, preferimos sandbox salvo que se fuerce prod
if (!$useSandbox && !$skipLocalDetection) {
    $server = $_SERVER['SERVER_NAME'] ?? '';
    $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
    $isLocalHost = fn($val) => stripos($val, 'localhost') !== false
        || stripos($val, '127.0.0.1') !== false
        || stripos($val, '.test') !== false;
    if ($isLocalHost($server) || $isLocalHost($hostHeader) || PHP_SAPI === 'cli') {
        $useSandbox = true;
    }
}

// Helper: lee env y exige valor si $required=true
$envOrFail = function (string $key, $default = null, bool $required = true) {
    $val = getenv($key);
    if ($val === false || $val === '') {
        $val = $default;
    }
    if ($required && ($val === null || $val === '' || str_starts_with((string)$val, 'REPLACE_'))) {
        throw new RuntimeException("Config SMTP incompleta: define {$key} en entorno o secure/mail.env");
    }
    return $val;
};

// Si hay Gmail real en prod, no uses sandbox
$gmailUser = getenv('SMTP_USER');
if ($useSandbox && $gmailUser && stripos($gmailUser, '@gmail.com') !== false && $gmailUser !== 'tu_correo@gmail.com') {
    $useSandbox = false;
}

$prod = [
    'SMTP_HOST'   => $envOrFail('SMTP_HOST', 'smtp.gmail.com'),
    'SMTP_PORT'   => (int) $envOrFail('SMTP_PORT', 587),
    'SMTP_SECURE' => $envOrFail('SMTP_SECURE', 'tls'),
    'SMTP_USER'   => $envOrFail('SMTP_USER', null),
    'SMTP_PASS'   => $envOrFail('SMTP_PASS', null),
    'FROM_EMAIL'  => $envOrFail('FROM_EMAIL', null),
    'FROM_NAME'   => $envOrFail('FROM_NAME', 'Plataforma UNICALI'),
    'SMTP_DEBUG'  => (int) $envOrFail('SMTP_DEBUG', 0, false),
    'TIMEOUT'     => (int) $envOrFail('SMTP_TIMEOUT', 25, false),
    'ALLOW_SELF_SIGNED' => (bool) (int) $envOrFail('SMTP_ALLOW_SELF_SIGNED', 0, false),
];

$sandbox = [
    'SMTP_HOST'   => getenv('SMTP_HOST_LOCAL')   ?: 'sandbox.smtp.mailtrap.io',
    'SMTP_PORT'   => (int) (getenv('SMTP_PORT_LOCAL')   ?: 587),
    'SMTP_SECURE' => getenv('SMTP_SECURE_LOCAL') ?: 'tls',
    'SMTP_USER'   => getenv('SMTP_USER_LOCAL')   ?: 'MAILTRAP_USER',
    'SMTP_PASS'   => getenv('SMTP_PASS_LOCAL')   ?: 'MAILTRAP_PASS',
    'FROM_EMAIL'  => getenv('FROM_EMAIL_LOCAL')  ?: 'no-reply@local.test',
    'FROM_NAME'   => getenv('FROM_NAME_LOCAL')   ?: 'Plataforma UNICALI (Local)',
    'SMTP_DEBUG'  => (int) (getenv('SMTP_DEBUG_LOCAL') ?: 2),
    'TIMEOUT'     => (int) (getenv('SMTP_TIMEOUT_LOCAL') ?: 6),
    'ALLOW_SELF_SIGNED' => (bool) (int) (getenv('SMTP_ALLOW_SELF_SIGNED_LOCAL') ?: 1),
];

// Forzar sandbox si se definen explícitamente host local y no se forzó prod
if (getenv('SMTP_HOST_LOCAL') && !$skipLocalDetection) {
    $useSandbox = true;
}

return $useSandbox ? $sandbox : $prod;
