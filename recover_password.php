<?php
require_once 'conexion.php';
require_once 'funciones_mail.php';
// Asegurar UTF-8 y mostrar errores en esta página (evita pantalla en blanco si algo falla)
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(60);

// Log de fatal errors por si el hosting suprime output
$fatalLog = __DIR__ . '/logs/mail-send.log';
register_shutdown_function(function () use ($fatalLog) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $msg = '[' . date('Y-m-d H:i:s') . "] FATAL {$e['message']} in {$e['file']}:{$e['line']}";
        file_put_contents($fatalLog, $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log ligero para evitar pantalla en blanco si el hosting suprime errores
    $logFile = __DIR__ . '/logs/mail-send.log';
    $log = function ($msg) use ($logFile) {
        file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
    };

    try {
        $search = trim($_POST['email'] ?? '');

        // Buscar usuario por Email o Identificacion
        $stmt = $conn->prepare('SELECT id, email, nombre FROM usuarios WHERE email = ? OR identificacion = ?');
        if (!$stmt) {
            $log('Prepare usuarios error: ' . $conn->error);
            throw new Exception('No se pudo preparar la consulta de usuario.');
        }
        $stmt->bind_param('ss', $search, $search);
        if (!$stmt->execute()) {
            $log('Execute usuarios error: ' . $stmt->error);
            throw new Exception('No se pudo ejecutar la consulta de usuario.');
        }
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            $email = $user['email'];
            $nombre = $user['nombre'];

            $token = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Limpiamos tokens previos para ese email e insertamos uno nuevo en password_resets
            $del = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
            if (!$del) {
                $log('Prepare delete reset error: ' . $conn->error);
                throw new Exception('No se pudo preparar el borrado de tokens previos.');
            }
            $del->bind_param('s', $email);
            if (!$del->execute()) {
                $log('Execute delete reset error: ' . $del->error);
                throw new Exception('No se pudo limpiar tokens previos.');
            }

            $ins = $conn->prepare('INSERT INTO password_resets (email, token, expira) VALUES (?, ?, ?)');
            if (!$ins) {
                $log('Prepare insert reset error: ' . $conn->error);
                throw new Exception('No se pudo preparar el guardado del token.');
            }
            $ins->bind_param('sss', $email, $token, $expira);
            if (!$ins->execute()) {
                $log('Insert reset error: ' . $ins->error);
                throw new Exception('No se pudo guardar el token de recuperacion.');
            }

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
            $basePath = ($basePath === '' || $basePath === '.') ? '' : $basePath;
            $link = sprintf('%s://%s%s/reset_password.php?token=%s', $protocol, $host, $basePath, urlencode($token));

            $log('Agregando correo a cola para ' . $email);

            // Usar sistema de cola en lugar de envío directo (sin timeout)
            if (enviar_correo_recuperacion($email, $nombre, $link)) {
                $log('✓ Correo agregado a cola exitosamente');
                header('Location: recover_password.php?ok=1');
                exit;
            } else {
                $log('Error al agregar correo a cola');
                throw new Exception('No se pudo procesar el envío del correo. Intenta más tarde.');
            }
        } else {
            $safeSearch = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');
            $error_envio = "El correo o identificacion '$safeSearch' no esta registrado.";
        }
    } catch (Exception $e) {
        $log('Excepcion: ' . $e->getMessage());
        $error_envio = 'No pudimos procesar tu solicitud: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contrase&#241;a - Unicali Segura</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="background-mesh"></div>

    <div class="login-container">
        <div style="position: absolute; top: 30px; left: 30px;">
            <a href="login.php" class="btn btn-outline" style="padding: 10px 15px;">
                <i class="fa-solid fa-arrow-left"></i> Volver al Login
            </a>
        </div>

        <div class="glass-panel login-box fade-in" style="max-width: 480px;">
            <div class="logo-area" style="margin-bottom: 30px;">
                <i class="fa-solid fa-key logo-large" style="color: var(--primary);"></i>
                <h2 style="font-size: 2rem;">&#191;Olvidaste tu clave?</h2>
                <p class="text-muted">Ingresa tu correo o n&#250;mero de identificaci&#243;n para recibir un enlace de recuperaci&#243;n.</p>
            </div>

            <?php if (isset($_GET['ok'])): ?>
                <div style="background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 15px; border-radius: 10px; margin-bottom: 25px; font-size: 0.9rem; border: 1px solid rgba(16, 185, 129, 0.2); text-align: center;">
                    <i class="fa-solid fa-circle-check"></i> Si los datos coinciden, hemos enviado las instrucciones a tu correo personal. Revisa tu bandeja de entrada.
                </div>
            <?php endif; ?>

            <?php if (isset($error_envio)): ?>
                <div style="background: rgba(244, 63, 94, 0.1); color: #fb7185; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(244, 63, 114, 0.2); text-align: center;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error_envio; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label class="input-label">Correo o Identificaci&#243;n (C&#233;dula/TI)</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user-shield" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--primary); opacity: 0.5;"></i>
                        <input type="text" name="email" class="input-field" placeholder="Ej: p.segura@unicali.edu.co o 1002938..." required style="padding-left: 45px;">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; height: 50px; margin-top: 10px;">
                    Enviar Enlace <i class="fa-solid fa-paper-plane" style="margin-left: 8px;"></i>
                </button>
            </form>

            <div class="security-badge" style="margin-top: 30px;">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Protecci&#243;n de Datos Unicali Segura</span>
            </div>
        </div>
    </div>
</body>

</html>
