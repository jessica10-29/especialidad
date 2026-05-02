<?php
// conexion.php - Conexion a Base de Datos y funciones globales

// Forzar UTF-8 en todas las respuestas
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// Detectar si estamos en localhost para no forzar HTTPS en desarrollo
$hostActual  = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
$forwardedProtoRaw = trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$forwardedProto = strtolower($forwardedProtoRaw);
if (strpos($forwardedProto, ',') !== false) {
    $forwardedProto = strtolower(trim((string)explode(',', $forwardedProto, 2)[0]));
}
// Considera local también redes privadas (LAN) para no forzar HTTPS cuando no hay certificado
$isLocal     = preg_match('/^(localhost|127\\.0\\.0\\.1)(:\\d+)?$/', $hostActual) === 1
    || preg_match('/^(10\\.|192\\.168\\.|172\\.(1[6-9]|2[0-9]|3[01])\\.)/', $hostActual) === 1
    || gethostname() === 'JEFFERSON-PC';  // Fallback para hostname local
$httpsActivo = (
    (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || $forwardedProto === 'https'
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on'
    || strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https'
    || (string)($_SERVER['SERVER_PORT'] ?? '') === '443'
);
// Forzar HTTPS solo si se habilita explícitamente en la variable de entorno
$forzarEnv = getenv('FORZAR_HTTPS');
$forzarHttps = $forzarEnv === '1' || ($forzarEnv !== '0' && !$isLocal);

// Configuracion de errores (muestra en local, oculta en produccion)
@ini_set('expose_php', '0');
ini_set('display_errors', $isLocal ? 1 : 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

// Asegurar carpeta de logs y registrar en archivo
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
ini_set('error_log', $logDir . '/php-error.log');

// === Cabeceras de seguridad === (solo si no hay salida previa)
if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
}

// Forzar HTTPS solo si hay certificado activo y si se habilita por variable de entorno
if (!$isLocal && $httpsActivo && !headers_sent()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

if (!$isLocal && $forzarHttps && !$httpsActivo && !headers_sent()) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
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

function renderizar_pantalla_servicio_no_disponible($mensaje, $esLocal = false, $detalleTecnico = '')
{
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Servicio temporalmente no disponible</title>';
    echo '<link rel="stylesheet" href="css/estilos.css">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">';
    echo '<style>.status-card{max-width:560px}.status-icon{width:84px;height:84px;margin:0 auto 20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;background:rgba(245,158,11,.14);color:#fbbf24;border:1px solid rgba(245,158,11,.2)}.status-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:24px}.status-card p{color:var(--text-muted)}.status-hint{margin-top:14px;padding:12px 14px;border-radius:12px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);font-size:.85rem;line-height:1.55;color:#cbd5e1}</style>';
    echo '</head><body><div class="background-mesh"></div><div class="login-container"><div class="glass-panel login-box fade-in status-card">';
    echo '<div class="status-icon"><i class="fa-solid fa-database"></i></div>';
    echo '<h1 style="font-size:2rem; margin-bottom:12px;">Servicio temporalmente no disponible</h1>';
    echo '<p>' . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($esLocal && $detalleTecnico !== '') {
        echo '<div class="status-hint"><strong>Detalle local:</strong> ' . htmlspecialchars($detalleTecnico, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo '<div class="status-actions">';
    echo '<a href="index.php" class="btn btn-outline"><i class="fa-solid fa-house"></i> Inicio</a>';
    echo '<a href="login.php" class="btn btn-primary"><i class="fa-solid fa-rotate-right"></i> Reintentar</a>';
    echo '</div>';
    echo '<div class="security-badge" style="margin-top:28px;"><i class="fa-solid fa-shield-halved"></i><span>Unicali Segura</span></div>';
    echo '</div></div></body></html>';
}

function obtener_alerta_conexion_html($mensaje)
{
    return '<div style="background: rgba(245, 158, 11, 0.12); color: #fcd34d; padding: 14px 16px; border-radius: 12px; margin-bottom: 25px; font-size: 0.9rem; border: 1px solid rgba(245, 158, 11, 0.22); text-align: center;">'
        . '<i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i>'
        . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8')
        . '</div>';
}

function normalizar_configuracion_bd($host, $port)
{
    $host = trim((string)$host);
    $port = trim((string)$port);

    if ($host !== '' && stripos($host, 'mysql://') === 0) {
        $partes = parse_url($host);
        if ($partes !== false) {
            $host = trim((string)($partes['host'] ?? $host));
            if (!empty($partes['port'])) {
                $port = (string)$partes['port'];
            }
        }
    }

    if ($host !== '' && strpos($host, ':') !== false && preg_match('/^[^:]+:\d+$/', $host) === 1) {
        [$hostExtraido, $portExtraido] = explode(':', $host, 2);
        $host = trim($hostExtraido);
        if ($port === '') {
            $port = trim($portExtraido);
        }
    }

    if (preg_match('/^\d+$/', $host) === 1 && preg_match('/^[A-Za-z0-9._-]+$/', $port) === 1 && preg_match('/^\d+$/', $port) !== 1) {
        [$host, $port] = [$port, $host];
    } elseif ($host === '' && preg_match('/^[A-Za-z0-9._-]+$/', $port) === 1 && preg_match('/^\d+$/', $port) !== 1) {
        $host = $port;
        $port = '3306';
    }

    $portNormalizado = intval($port);
    if ($portNormalizado <= 0 || $portNormalizado > 65535) {
        $portNormalizado = 3306;
    }

    return [
        'host' => $host,
        'port' => $portNormalizado,
    ];
}

// Cargar credenciales externas
$configPath = __DIR__ . '/secure/config.php';
$permiteContinuarSinBd = defined('PERMITIR_CONTINUAR_SIN_BD') && PERMITIR_CONTINUAR_SIN_BD;
$dbDisponible = false;
$dbErrorPublico = '';
$dbErrorTecnico = '';
$dbHostUtilizado = '';

if (!file_exists($configPath)) {
    $dbErrorPublico = 'Falta la configuración de base de datos del sitio.';
    $dbErrorTecnico = 'No existe el archivo secure/config.php.';
    if ($permiteContinuarSinBd) {
        $config = [];
    } else {
        renderizar_pantalla_servicio_no_disponible($dbErrorPublico, $isLocal, $dbErrorTecnico);
        exit;
    }
} else {
    $config = require $configPath;
}

// Seleccionar credenciales segun entorno (local vs hosting)
// Por defecto usa la base local; para forzar la remota define FORZAR_LOCAL_DB=0
$usarLocal = ($isLocal || getenv('FORZAR_LOCAL_DB') === '1') && getenv('FORZAR_LOCAL_DB') !== '0';

if ($usarLocal) {
    $host = $config['DB_HOST_LOCAL'] ?? 'localhost';
    $port = $config['DB_PORT_LOCAL'] ?? 3306;
    $user = $config['DB_USER_LOCAL'] ?? 'root';
    $pass = $config['DB_PASS_LOCAL'] ?? '';
    $db   = $config['DB_NAME_LOCAL'] ?? 'universidad';
} else {
    $host = $config['DB_HOST'] ?? '';
    $port = $config['DB_PORT'] ?? 3306;
    $user = $config['DB_USER'] ?? '';
    $pass = $config['DB_PASS'] ?? '';
    $db   = $config['DB_NAME'] ?? '';
    
    // FALLBACK: Si credenciales remotas están vacías, usa las locales
    if (empty($host) || empty($user) || empty($db)) {
        $host = $config['DB_HOST_LOCAL'] ?? 'localhost';
        $port = $config['DB_PORT_LOCAL'] ?? 3306;
        $user = $config['DB_USER_LOCAL'] ?? 'root';
        $pass = $config['DB_PASS_LOCAL'] ?? '';
        $db   = $config['DB_NAME_LOCAL'] ?? 'universidad';
    }
}

$configBdNormalizada = normalizar_configuracion_bd($host, $port);
$host = $configBdNormalizada['host'];
$port = $configBdNormalizada['port'];

function obtener_hosts_bd_candidatos($host)
{
    $host = trim((string)$host);
    if ($host === '') {
        return [''];
    }

    $candidatos = [$host];
    if (preg_match('/^(sql\d+)\.(infinityfree\.com|epizy\.com|byetcluster\.com)$/i', $host, $coincidencias)) {
        $prefijo = strtolower($coincidencias[1]);
        $dominioActual = strtolower($coincidencias[2]);
        $dominiosAlternativos = ['epizy.com', 'infinityfree.com', 'byetcluster.com'];

        foreach ($dominiosAlternativos as $dominio) {
            if ($dominio === $dominioActual) {
                continue;
            }
            $candidatos[] = $prefijo . '.' . $dominio;
        }
    }

    return array_values(array_unique($candidatos));
}

// Evitar excepciones fatales de mysqli y manejar manualmente
mysqli_report(MYSQLI_REPORT_OFF);
$hostsIntentados = obtener_hosts_bd_candidatos($host);
$erroresConexion = [];
$conn = null;

if (!$usarLocal && preg_match('/^\d+$/', trim((string)$host)) === 1) {
    error_log("Configuracion sospechosa de BD: DB_HOST parece un puerto ({$host}). Revisa tus credenciales remotas.");
}

foreach ($hostsIntentados as $hostCandidato) {
    $mysqli = mysqli_init();
    if ($mysqli === false) {
        $erroresConexion[] = $hostCandidato . ' => No se pudo inicializar mysqli.';
        continue;
    }

    // Evita que el login quede colgado demasiado tiempo si el host remoto no responde.
    mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 8);
    $conexionOk = @$mysqli->real_connect($hostCandidato, $user, $pass, $db, $port);

    if ($conexionOk) {
        $conn = $mysqli;
        $host = $hostCandidato;
        $dbHostUtilizado = $hostCandidato;
        $dbDisponible = true;
        break;
    }

    $erroresConexion[] = $hostCandidato . ' => ' . ($mysqli->connect_error ?: 'Sin detalles del controlador MySQL.');
    $mysqli->close();
}

if (!($conn instanceof mysqli)) {
    $detalleError = implode(' | ', $erroresConexion);
    error_log("Error de conexion a BD. Hosts intentados: " . implode(', ', $hostsIntentados) . ". Detalle: " . $detalleError);
    $dbErrorPublico = $isLocal
        ? 'No se pudo conectar a la base de datos local. Verifica MySQL, usuario, contraseña y nombre de BD.'
        : 'No pudimos conectar el portal con la base de datos en este momento. Intenta nuevamente en unos minutos.';
    $dbErrorTecnico = $erroresConexion ? end($erroresConexion) : 'Sin detalles del controlador MySQL.';

    if (!$permiteContinuarSinBd) {
        renderizar_pantalla_servicio_no_disponible($dbErrorPublico, $isLocal, (string)$dbErrorTecnico);
        exit;
    }
}

if ($dbDisponible) {
    $conn->set_charset('utf8mb4');
}

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
    if (!($conn instanceof mysqli)) {
        return $valor;
    }
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

function conexion_bd_disponible()
{
    global $dbDisponible;
    return $dbDisponible === true;
}

function obtener_mensaje_conexion_bd()
{
    global $dbErrorPublico;
    return $dbErrorPublico;
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
 * Normaliza una URL base para uso publico.
 * En dominios publicos fuerza https; en localhost/LAN conserva http.
 */
function normalizar_base_url_segura(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    $partes = @parse_url($url);
    if (!is_array($partes) || empty($partes['host'])) {
        return rtrim($url, '/');
    }

    $host = strtolower((string)$partes['host']);
    $scheme = strtolower((string)($partes['scheme'] ?? 'https'));
    $path = isset($partes['path']) ? rtrim((string)$partes['path'], '/') : '';
    $query = isset($partes['query']) ? '?' . $partes['query'] : '';

    $hostEsLocal = $host === 'localhost' || $host === '::1' || es_ip_local_o_privada($host);
    if (!$hostEsLocal) {
        $scheme = 'https';
    } elseif ($scheme !== 'http' && $scheme !== 'https') {
        $scheme = 'http';
    }

    $port = '';
    if (isset($partes['port'])) {
        $portNumber = (string)$partes['port'];
        $esPuertoPorDefecto = ($scheme === 'https' && $portNumber === '443') || ($scheme === 'http' && $portNumber === '80');
        if (!$esPuertoPorDefecto) {
            $port = ':' . $portNumber;
        }
    }

    return "{$scheme}://{$host}{$port}{$path}{$query}";
}

/**
 * Devuelve una URL base accesible para escáneres externos (mismo WiFi),
 * priorizando APP_URL/PUBLIC_BASE_URL y evitando usar localhost en el QR.
 */
function construir_base_url()
{
    global $config;

    $envUrl = getenv('APP_URL') ?: getenv('PUBLIC_BASE_URL');
    if (!empty($envUrl)) {
        return rtrim(normalizar_base_url_segura($envUrl), '/');
    }

    if (is_array($config)) {
        $configUrl = trim((string)($config['APP_URL'] ?? $config['PUBLIC_BASE_URL'] ?? ''));
        if ($configUrl !== '') {
            return rtrim(normalizar_base_url_segura($configUrl), '/');
        }
    }

    $forwardProto = trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if (strpos($forwardProto, ',') !== false) {
        $forwardProto = trim((string)explode(',', $forwardProto)[0]);
    }
    $forwardSsl = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
    $requestScheme = strtolower(trim((string)($_SERVER['REQUEST_SCHEME'] ?? '')));
    $httpsFlag = strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
    $serverPort = (string)($_SERVER['SERVER_PORT'] ?? '');
    $protocol = (
        $forwardProto === 'https'
        || $forwardSsl === 'on'
        || $requestScheme === 'https'
        || ($httpsFlag !== '' && $httpsFlag !== 'off')
        || $serverPort === '443'
    ) ? 'https' : 'http';

    $host = trim((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    if (strpos($host, ',') !== false) {
        $host = trim((string)explode(',', $host)[0]);
    }
    $port = '';

    // Normalizar host y puerto si vienen juntos
    if (preg_match('/^\[(.+)\]:(\d+)$/', $host, $matches)) {
        $host = $matches[1];
        $port = $matches[2];
    } elseif (preg_match('/^([^:]+):(\d+)$/', $host, $matches)) {
        $host = $matches[1];
        $port = $matches[2];
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

    $hostEsLocal = preg_match('/^(localhost|::1)$/i', $host) === 1 || es_ip_local_o_privada($host);
    if ($hostEsLocal) {
        // Evita enlaces https hacia IPs privadas/locales sin certificado valido.
        $protocol = 'http';
    }

    // Puerto si no es el estándar
    if ($port === '' && isset($_SERVER['SERVER_PORT']) && !in_array((string)$_SERVER['SERVER_PORT'], ['80', '443'])) {
        $port = $_SERVER['SERVER_PORT'];
    }
    if ($hostEsLocal && in_array((string)$port, ['443', '8443'], true)) {
        $port = '';
    }
    $portPart = ($port && !in_array((string)$port, ['80', '443'])) ? ':' . $port : '';

    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = ($scriptDir === '/' ? '' : $scriptDir);

    return normalizar_base_url_segura("{$protocol}://{$host}{$portPart}{$scriptDir}");
}

/**
 * Determina si una IP pertenece a localhost o a una red privada.
 */
function es_ip_local_o_privada(?string $ip = null): bool
{
    $ip = trim((string)($ip ?? ($_SERVER['REMOTE_ADDR'] ?? '')));
    if ($ip === '') {
        return false;
    }

    if (in_array($ip, ['127.0.0.1', '::1'], true)) {
        return true;
    }

    return preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $ip) === 1;
}

/**
 * Indica si la petición actual proviene del mismo equipo o de la red local.
 */
function es_peticion_local(): bool
{
    global $isLocal;

    if (!empty($isLocal)) {
        return true;
    }

    return es_ip_local_o_privada($_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * Obtiene el token de mantenimiento. En local permite un valor por defecto;
 * en producción exige configurar MAIL_QUEUE_TOKEN.
 */
function obtener_token_mantenimiento(): string
{
    global $config;

    $token = trim((string)getenv('MAIL_QUEUE_TOKEN'));
    if ($token !== '') {
        return $token;
    }

    if (is_array($config)) {
        $tokenConfig = trim((string)($config['MAIL_QUEUE_TOKEN'] ?? ''));
        if ($tokenConfig !== '') {
            return $tokenConfig;
        }
    }

    return es_peticion_local() ? 'DESARROLLO_LOCAL_2025' : '';
}

/**
 * Valida si el token de mantenimiento sigue usando un valor inseguro.
 */
function token_mantenimiento_inseguro(?string $token = null): bool
{
    $token = trim((string)($token ?? obtener_token_mantenimiento()));
    return $token === '' || hash_equals('DESARROLLO_LOCAL_2025', $token);
}

/**
 * Bloquea herramientas de soporte que no deben exponerse en producción.
 */
function exigir_herramienta_local(string $nombreHerramienta = 'Esta herramienta'): void
{
    if (es_peticion_local()) {
        return;
    }

    http_response_code(403);
    exit($nombreHerramienta . ' solo esta disponible en localhost o en tu red privada.');
}

/**
 * Valida el token de mantenimiento en peticiones web.
 */
function validar_token_mantenimiento_en_peticion(): string
{
    $tokenSecreto = obtener_token_mantenimiento();
    if ($tokenSecreto === '') {
        http_response_code(503);
        exit('Configura MAIL_QUEUE_TOKEN antes de usar esta herramienta en produccion.');
    }

    if (!es_peticion_local() && token_mantenimiento_inseguro($tokenSecreto)) {
        http_response_code(503);
        exit('MAIL_QUEUE_TOKEN debe cambiarse por un valor unico antes de publicar.');
    }

    $tokenEnviado = (string)($_GET['token'] ?? $_POST['token'] ?? '');
    if ($tokenEnviado === '' || !hash_equals($tokenSecreto, $tokenEnviado)) {
        http_response_code(403);
        exit('Acceso denegado.');
    }

    return $tokenSecreto;
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
