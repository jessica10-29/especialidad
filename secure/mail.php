<?php
/**
 * secure/mail.php
 * Perfil dual: produccion (hosting) y sandbox (localhost).
 * - Produccion: usa variables de entorno SMTP_* (ej. InfinityFree/SMTP externo).
 * - Sandbox: pensado para Mailtrap (evita bloqueos SMTP en redes locales).
 *
 * Cambia APP_ENV=local para forzar sandbox, APP_ENV=prod para forzar produccion.
 * Tambien puedes definir SMTP_FORCE_PROD=1 para saltar la autodeteccion de localhost.
 *
 * Seguridad: no se versionan credenciales. Usa secure/mail.env o variables de entorno.
 */

// Polyfill para PHP < 8
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

// Carga opcional de secure/mail.env (excluido en .gitignore)
$envFile = __DIR__ . '/mail.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        if (str_starts_with($trimmed, '#')) {
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

// Si corremos desde CLI o en un host tipico de desarrollo, preferimos sandbox
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

// Helper: lee variable de entorno y exige valor si $required=true
$envOrFail = function (string $key, $default = null, bool $required = true) {
    $val = getenv($key);
    if ($val === false || $val === '') {
        $val = $default;
    }
    if ($required && ($val === null || $val === '' || stripos((string) $val, 'REPLACE_') === 0)) {
        throw new RuntimeException("Config SMTP incompleta: define {$key} en el entorno o secure/mail.env");
    }
    return $val;
};

// Reconocimiento de cuenta Google: si el usuario ya puso un Gmail real, prioriza prod
$gmailPersonalizado = getenv('SMTP_USER') ?: 'tu_correo@gmail.com';
$prod_user = $gmailPersonalizado;
if ($useSandbox && stripos($prod_user, '@gmail.com') !== false && $prod_user !== 'tu_correo@gmail.com') {
    $useSandbox = false;
}

$prod = [
    'SMTP_HOST'   => $envOrFail('SMTP_HOST', 'smtp.gmail.com'),
    'SMTP_PORT'   => (int) $envOrFail('SMTP_PORT', 587),
    'SMTP_SECURE' => $envOrFail('SMTP_SECURE', 'tls'), // ssl o tls
    'SMTP_USER'   => $envOrFail('SMTP_USER', 'REPLACE_SMTP_USER'),
    'SMTP_PASS'   => $envOrFail('SMTP_PASS', null), // sin valor por defecto para evitar fugas
    'FROM_EMAIL'  => $envOrFail('FROM_EMAIL', 'REPLACE_FROM_EMAIL'),
    'FROM_NAME'   => $envOrFail('FROM_NAME', 'Plataforma UNICALI'),
    'SMTP_DEBUG'  => (int) $envOrFail('SMTP_DEBUG', 0, false),
    'TIMEOUT'     => (int) $envOrFail('SMTP_TIMEOUT', 25, false),
    'ALLOW_SELF_SIGNED' => (bool) (int) $envOrFail('SMTP_ALLOW_SELF_SIGNED', 0, false),
];

// Perfil seguro para pruebas locales (evita timeouts si tu ISP/firewall bloquea 587/465)
$sandbox = [
    'SMTP_HOST'   => getenv('SMTP_HOST_LOCAL')   ?: 'sandbox.smtp.mailtrap.io',
    'SMTP_PORT'   => (int) (getenv('SMTP_PORT_LOCAL')   ?: 587),
    'SMTP_SECURE' => getenv('SMTP_SECURE_LOCAL') ?: 'tls',
    'SMTP_USER'   => getenv('SMTP_USER_LOCAL')   ?: 'MAILTRAP_USER',
    'SMTP_PASS'   => getenv('SMTP_PASS_LOCAL')   ?: 'MAILTRAP_PASS',
    'FROM_EMAIL'  => getenv('FROM_EMAIL_LOCAL')  ?: 'no-reply@local.test',
    'FROM_NAME'   => getenv('FROM_NAME_LOCAL')   ?: 'Plataforma UNICALI (Local)',
    'SMTP_DEBUG'  => (int) (getenv('SMTP_DEBUG_LOCAL') ?: 2),
    'TIMEOUT'     => (int) (getenv('SMTP_TIMEOUT_LOCAL')?: 6),
    'ALLOW_SELF_SIGNED' => (bool) (int) (getenv('SMTP_ALLOW_SELF_SIGNED_LOCAL') ?: 1),
];

// Si definiste SMTP_HOST_LOCAL explicitamente, prioriza sandbox aunque APP_ENV no sea local (a menos que fuerces prod)
if (getenv('SMTP_HOST_LOCAL') && !$skipLocalDetection) {
    $useSandbox = true;
}

return $useSandbox ? $sandbox : $prod;
