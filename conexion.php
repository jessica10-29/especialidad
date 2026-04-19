<?php
// conexion.php - Conexion a Base de Datos y funciones globales

// Forzar UTF-8 en todas las respuestas
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// Detectar si estamos en localhost para no forzar HTTPS en desarrollo
$hostActual  = $_SERVER['HTTP_HOST'] ?? '';
// Considera local también redes privadas (LAN) para no forzar HTTPS cuando no hay certificado
$isLocal     = preg_match('/^(localhost|127\\.0\\.0\\.1)(:\\d+)?$/', $hostActual) === 1
    || preg_match('/^(10\\.|192\\.168\\.|172\\.(1[6-9]|2[0-9]|3[01])\\.)/', $hostActual) === 1
    || gethostname() === 'JEFFERSON-PC';  // Fallback para hostname local
$httpsActivo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
// Forzar HTTPS: siempre en hosting; en localhost solo si FORZAR_HTTPS=1
$forzarEnv = getenv('FORZAR_HTTPS');
$forzarHttps = $forzarEnv === '1' ? true : (!$isLocal && $forzarEnv !== '0');

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

// === Cabeceras de seguridad === (solo si no hay salida previa)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
}

// Forzar HTTPS solo si hay certificado activo o se habilita por variable de entorno
if (!$isLocal && ($httpsActivo || $forzarHttps) && !headers_sent()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    if (!$httpsActivo) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
        exit;
    }
}

// Cookies de sesion seguras
$cookieParams = session_get_cookie_params();
if (!headers_sent()) {
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => $httpsActivo,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// Cargar credenciales externas
$configPath = __DIR__ . '/secure/config.php';
if (!file_exists($configPath)) {
    exit('Falta el archivo secure/config.php con las credenciales de la base de datos.');
}
$config = require $configPath;

// Seleccionar credenciales segun entorno (local vs hosting)
// Por defecto usa la base local; para forzar la remota define FORZAR_LOCAL_DB=0
$usarLocal = ($isLocal || getenv('FORZAR_LOCAL_DB') === '1') && getenv('FORZAR_LOCAL_DB') !== '0';

if ($usarLocal) {
    $host = $config['DB_HOST_LOCAL'] ?? 'localhost';
    $port = intval($config['DB_PORT_LOCAL'] ?? 3306);
    $user = $config['DB_USER_LOCAL'] ?? 'root';
    $pass = $config['DB_PASS_LOCAL'] ?? '';
    $db   = $config['DB_NAME_LOCAL'] ?? 'universidad';
} else {
    $host = $config['DB_HOST'] ?? '';
    $port = intval($config['DB_PORT'] ?? 3306);
    $user = $config['DB_USER'] ?? '';
    $pass = $config['DB_PASS'] ?? '';
    $db   = $config['DB_NAME'] ?? '';
    
    // FALLBACK: Si credenciales remotas están vacías, usa las locales
    if (empty($host) || empty($user) || empty($db)) {
        $host = $config['DB_HOST_LOCAL'] ?? 'localhost';
        $port = intval($config['DB_PORT_LOCAL'] ?? 3306);
        $user = $config['DB_USER_LOCAL'] ?? 'root';
        $pass = $config['DB_PASS_LOCAL'] ?? '';
        $db   = $config['DB_NAME_LOCAL'] ?? 'universidad';
    }
}

// Evitar excepciones fatales de mysqli y manejar manualmente
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_errno) {
    error_log("Error de conexion a BD ({$host}): " . $conn->connect_error);
    http_response_code(503);
    // Respuesta limpia y sin "pantallazo rojo"
    $mensaje = $isLocal
        ? 'No se pudo conectar a la base de datos local. Verifica MySQL, usuario, contrase&ntilde;a y nombre de BD.'
        : 'Estamos realizando ajustes t&eacute;cnicos. Intenta nuevamente en unos minutos.';

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
        echo '<small>Hint: Arranca MySQL en XAMPP y confirma los datos en secure/config.php o variables DB_*_LOCAL.';
        echo ' Error de conexión: ' . htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8') . '</small>';
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
    // Tolerar valores nulos o no escalares para evitar avisos de PHP 8.2+
    if ($dato === null || is_array($dato) || is_object($dato)) {
        return '';
    }
    $valor = trim((string)$dato);
    return $conn->real_escape_string($valor);
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

function nombre_archivo_entrega_valido($nombre)
{
    if (!is_string($nombre) || $nombre === '') {
        return false;
    }

    return preg_match('/^entrega_[0-9]+_[0-9]+_[A-Za-z0-9_-]+\.pdf$/i', $nombre) === 1;
}

function ruta_esta_dentro_de_directorio($ruta, $directorioBase)
{
    $baseReal = realpath($directorioBase);
    $rutaReal = realpath($ruta);

    if ($baseReal === false || $rutaReal === false) {
        return false;
    }

    $baseNormalizada = rtrim(str_replace('\\', '/', $baseReal), '/') . '/';
    $rutaNormalizada = str_replace('\\', '/', $rutaReal);

    return strpos($rutaNormalizada, $baseNormalizada) === 0;
}

function limpiar_nombre_descarga($nombre, $fallback = 'documento.pdf')
{
    $nombre = trim((string)$nombre);
    $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre);
    $nombre = trim($nombre, '._-');

    if ($nombre === '') {
        $nombre = $fallback;
    }

    if (strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'pdf') {
        $nombre .= '.pdf';
    }

    return $nombre;
}

function validar_pdf_seguro($rutaArchivo, &$error = null, $maxBytes = null)
{
    $error = null;

    if (!is_string($rutaArchivo) || $rutaArchivo === '' || !is_file($rutaArchivo) || !is_readable($rutaArchivo)) {
        $error = 'El archivo PDF no existe o no se puede leer.';
        return false;
    }

    $tamano = @filesize($rutaArchivo);
    if ($tamano === false || $tamano <= 0) {
        $error = 'El archivo PDF esta vacio o no se pudo medir.';
        return false;
    }

    if ($maxBytes !== null && $tamano > (int)$maxBytes) {
        $error = 'El archivo PDF supera el tamano permitido.';
        return false;
    }

    $manejador = @fopen($rutaArchivo, 'rb');
    if ($manejador === false) {
        $error = 'No fue posible abrir el archivo PDF.';
        return false;
    }

    $firma = @fread($manejador, 5);
    @fclose($manejador);

    if ($firma !== '%PDF-') {
        $error = 'El archivo no tiene una firma PDF valida.';
        return false;
    }

    $mimeType = null;
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mimeType = @finfo_file($finfo, $rutaArchivo) ?: null;
            @finfo_close($finfo);
        }
    }

    $mimePermitidos = [
        'application/pdf',
        'application/x-pdf',
        'application/acrobat',
        'applications/vnd.pdf',
        'text/pdf',
        'text/x-pdf',
        'application/octet-stream',
    ];

    if ($mimeType !== null && !in_array(strtolower($mimeType), $mimePermitidos, true)) {
        $error = 'El tipo MIME del archivo no corresponde a un PDF permitido.';
        return false;
    }

    $contenido = @file_get_contents($rutaArchivo);
    if ($contenido === false) {
        $error = 'No fue posible inspeccionar el contenido del PDF.';
        return false;
    }

    $patronesPeligrosos = [
        '/JavaScript',
        '/JS',
        '/OpenAction',
        '/AA',
        '/Launch',
        '/RichMedia',
        '/EmbeddedFile',
        '/XFA',
        '/SubmitForm',
        '/ImportData',
    ];

    foreach ($patronesPeligrosos as $patron) {
        if (stripos($contenido, $patron) !== false) {
            $error = 'El PDF contiene elementos activos o incrustados no permitidos.';
            return false;
        }
    }

    return true;
}

/**
 * Devuelve una URL base accesible para escáneres externos (mismo WiFi),
 * priorizando APP_URL/PUBLIC_BASE_URL y evitando usar localhost en el QR.
 */
function construir_base_url()
{
    $envUrl = getenv('APP_URL') ?: getenv('PUBLIC_BASE_URL');
    if (!empty($envUrl)) {
        return rtrim($envUrl, '/');
    }

    $forwardProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
    $protocol = $forwardProto ? (($forwardProto === 'https') ? 'https' : 'http')
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http');

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $port = '';

    // Normalizar host y puerto si vienen juntos
    if (strpos($host, ':') !== false) {
        [$hostOnly, $portPart] = explode(':', $host, 2);
        $host = $hostOnly;
        $port = $portPart;
    }

    $candidatos = [];
    if ($host === '' || preg_match('/^(localhost|127\\.0\\.0\\.1|::1)$/', $host)) {
        if (!empty($_SERVER['SERVER_ADDR'])) {
            $candidatos[] = $_SERVER['SERVER_ADDR'];
        }
        $lan = gethostbyname(gethostname());
        if ($lan) {
            $candidatos[] = $lan;
        }
    }

    foreach ($candidatos as $cand) {
        if ($cand && !preg_match('/^(127\\.0\\.0\\.1|::1)$/', $cand)) {
            $host = $cand;
            break;
        }
    }

    if ($host === '') {
        $host = '127.0.0.1';
    }

    // Puerto si no es el estándar
    if ($port === '' && isset($_SERVER['SERVER_PORT']) && !in_array((string)$_SERVER['SERVER_PORT'], ['80', '443'])) {
        $port = $_SERVER['SERVER_PORT'];
    }
    $portPart = ($port && !in_array((string)$port, ['80', '443'])) ? ':' . $port : '';

    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = ($scriptDir === '/' ? '' : $scriptDir);

    return "{$protocol}://{$host}{$portPart}{$scriptDir}";
}

/**
 * Descarga un recurso con timeout corto. Usa cURL si está disponible;
 * si no, recurre a file_get_contents con contexto.
 */
function descargar_con_timeout(string $url, int $timeout = 5)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'UnicaliQR/1.0',
        ]);
        $data = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($data !== false && $http >= 200 && $http < 300) {
            return $data;
        }
    }

    $context = stream_context_create([
        'http' => ['timeout' => $timeout],
        'https' => ['timeout' => $timeout, 'verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $data = @file_get_contents($url, false, $context);
    return $data === false ? null : $data;
}

/**
 * Construye la URL de verificación pública para un folio dado.
 */
function url_verificacion(string $folio): string
{
    return rtrim(construir_base_url(), '/') . '/verificar.php?folio=' . rawurlencode($folio);
}

// === AUTO-PROCESADOR DE COLA DE CORREOS EN LOCALHOST ===
// Ejecuta automáticamente en background para procesar correos pendientes
if ($isLocal && php_sapi_name() !== 'cli' && !headers_sent()) {
    register_shutdown_function(function() {
        @include_once __DIR__ . '/procesar_cola_auto.php';
    });
}
