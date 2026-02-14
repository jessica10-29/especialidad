<?php
// conexion.php - Conexion a Base de Datos y funciones globales

// Forzar UTF-8 en todas las respuestas
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

$rawHost = $_SERVER['HTTP_HOST'] ?? '';
$hostSinPuerto = strtolower(explode(':', $rawHost)[0]);
$isLocal = in_array($hostSinPuerto, ['localhost', '127.0.0.1', '::1']) || php_sapi_name() === 'cli';
$httpsActivo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$forzarHttps = getenv('FORCE_HTTPS') === '1';
$httpsActivo = $httpsActivo || $forzarHttps;

// Configuracion de errores (muestra en local, oculta en produccion)
ini_set('display_errors', $isLocal ? 1 : 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Asegurar carpeta de logs y registrar en archivo
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/php-error.log');

// === Cabeceras de seguridad ===
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');

// Forzar HTTPS solo si hay certificado activo o se habilita por variable de entorno
if (!$isLocal && ($httpsActivo || $forzarHttps)) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    if (!$httpsActivo) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
        exit;
    }
}

// Cookies de sesion seguras
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => $httpsActivo,
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Cargar credenciales externas
$configPath = __DIR__ . '/secure/config.php';
if (!file_exists($configPath)) {
    exit('Falta el archivo secure/config.php con las credenciales de la base de datos.');
}
$config = require $configPath;

// Preferir variables de entorno para mayor seguridad
$host = getenv('DB_HOST') ?: ($config['DB_HOST'] ?? 'localhost');
$user = getenv('DB_USER') ?: ($config['DB_USER'] ?? 'root');
$pass = getenv('DB_PASS') ?: ($config['DB_PASS'] ?? '');
$db   = getenv('DB_NAME') ?: ($config['DB_NAME'] ?? 'universidad');

// Fallback automatico en entorno local
if ($isLocal) {
    $host = getenv('DB_HOST_LOCAL') ?: 'localhost';
    $user = getenv('DB_USER_LOCAL') ?: 'root';
    $pass = getenv('DB_PASS_LOCAL') ?: '';
    $db   = getenv('DB_NAME_LOCAL') ?: 'universidad';
}

// Evitar excepciones fatales de mysqli y manejar manualmente
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $db);

if ($conn->connect_errno) {
    error_log("Error de conexion a BD ({$host}): " . $conn->connect_error);
    http_response_code(503);
    // Respuesta limpia y sin "pantallazo rojo"
    $mensaje = $isLocal
        ? 'No se pudo conectar a la base de datos local. Verifica MySQL, usuario, contraseña y nombre de BD.'
        : 'Estamos realizando ajustes técnicos. Intenta nuevamente en unos minutos.';

    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Servicio temporalmente no disponible</title>';
    echo '<style>body{font-family:Arial,sans-serif; background:#0f172a; color:#e2e8f0; display:flex; align-items:center; justify-content:center; height:100vh; margin:0;}';
    echo '.card{background:#111827; padding:28px 32px; border-radius:14px; box-shadow:0 18px 50px rgba(0,0,0,.35); max-width:420px; text-align:center;}';
    echo '.card h1{font-size:1.25rem; margin-bottom:10px;} .card p{margin:8px 0 0; color:#cbd5e1; line-height:1.5;}';
    echo '.card small{display:block; margin-top:12px; color:#94a3b8;}</style></head><body>';
    echo '<div class="card">';
    echo '<h1>Servicio temporalmente no disponible</h1>';
    echo '<p>' . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($isLocal) {
        echo '<small>Hint: Arranca MySQL en XAMPP y confirma los datos en secure/config.php o variables DB_*_LOCAL.</small>';
    }
    echo '</div></body></html>';
    exit;
}

$conn->set_charset('utf8mb4');

// Iniciar sesion solo si no esta iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar funciones academicas (Periodos, Auditoria)
require_once __DIR__ . '/funciones_academicas.php';

// Recuperar periodo actual si el usuario ya esta autenticado
if (isset($_SESSION['usuario_id'])) {
    obtener_periodo_actual();
}

// === Funciones de ayuda ===

function verificar_sesion()
{
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit();
    }
}

function verificar_rol($rol_requerido)
{
    if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== $rol_requerido) {
        if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'estudiante') {
            header('Location: dashboard_estudiante.php');
        } elseif (isset($_SESSION['rol']) && $_SESSION['rol'] === 'profesor') {
            header('Location: dashboard_profesor.php');
        } else {
            header('Location: login.php');
        }
        exit();
    }
}

function limpiar_dato($dato)
{
    global $conn;
    return $conn->real_escape_string(trim($dato));
}

function obtener_nombre_usuario()
{
    return isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
}

function obtener_foto_usuario($foto = null)
{
    if ($foto && file_exists(__DIR__ . '/uploads/fotos/' . $foto)) {
        return 'uploads/fotos/' . $foto;
    }
    return 'https://ui-avatars.com/api/?name=User&background=6366f1&color=fff';
}

// === CSRF ===

function generar_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verificar_csrf_token($token)
{
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
