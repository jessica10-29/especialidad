<?php
/**
 * secure/mail.php
 * Perfil dual: producción (hosting) y sandbox (localhost).
 * - Producción: usa las variables SMTP_* (ej. InfinityFree: puerto 587 + STARTTLS).
 * - Sandbox: pensado para Mailtrap (evita bloqueos SMTP en tu red local).
 *
 * Cambia APP_ENV=local para forzar sandbox, APP_ENV=prod para forzar producción.
 * También puedes definir SMTP_FORCE_PROD=1 para saltar la autodetección de localhost.
 */

$env = getenv('APP_ENV');
$forceProd = getenv('SMTP_FORCE_PROD');
$useSandbox = ($env === 'local');
$skipLocalDetection = ($env === 'prod' || $env === 'production' || $forceProd);

// Si corremos desde CLI o en un host típico de desarrollo, preferimos sandbox
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

$prod = [
    'SMTP_HOST'   => getenv('SMTP_HOST')   ?: 'smtp.gmail.com',
    'SMTP_PORT'   => getenv('SMTP_PORT')   ?: 587,
    'SMTP_SECURE' => getenv('SMTP_SECURE') ?: 'tls', // ssl o tls
    'SMTP_USER'   => getenv('SMTP_USER')   ?: 'juanpabloortizcastro1@gmail.com',
    'SMTP_PASS'   => getenv('SMTP_PASS')   ?: 'mzcjvejbcoazleyr', // contraseña de aplicación
    'FROM_EMAIL'  => getenv('FROM_EMAIL')  ?: 'juanpabloortizcastro1@gmail.com',
    'FROM_NAME'   => getenv('FROM_NAME')   ?: 'Plataforma UNICALI SEGURA',
    'SMTP_DEBUG'  => getenv('SMTP_DEBUG')  ?: 0,
    'TIMEOUT'     => getenv('SMTP_TIMEOUT')?: 25,
];

// Perfil seguro para pruebas locales (evita timeouts si tu ISP/firewall bloquea 587/465)
$sandbox = [
    'SMTP_HOST'   => getenv('SMTP_HOST_LOCAL')   ?: 'sandbox.smtp.mailtrap.io',
    'SMTP_PORT'   => getenv('SMTP_PORT_LOCAL')   ?: 587,
    'SMTP_SECURE' => getenv('SMTP_SECURE_LOCAL') ?: 'tls',
    'SMTP_USER'   => getenv('SMTP_USER_LOCAL')   ?: 'MAILTRAP_USER',
    'SMTP_PASS'   => getenv('SMTP_PASS_LOCAL')   ?: 'MAILTRAP_PASS',
    'FROM_EMAIL'  => getenv('FROM_EMAIL_LOCAL')  ?: 'no-reply@local.test',
    'FROM_NAME'   => getenv('FROM_NAME_LOCAL')   ?: 'Plataforma UNICALI (Local)',
    'SMTP_DEBUG'  => getenv('SMTP_DEBUG_LOCAL')  ?: 2,
    'TIMEOUT'     => getenv('SMTP_TIMEOUT_LOCAL')?: 6,
];

// Si definiste SMTP_HOST_LOCAL explícitamente, prioriza sandbox aunque APP_ENV no sea local (a menos que fuerces prod)
if (getenv('SMTP_HOST_LOCAL') && !$skipLocalDetection) {
    $useSandbox = true;
}

return $useSandbox ? $sandbox : $prod;
