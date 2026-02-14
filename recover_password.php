<?php
require_once 'conexion.php';
// Forzamos UTF-8 en la respuesta para evitar textos mal codificados en algunos navegadores
header('Content-Type: text/html; charset=UTF-8');

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $search = trim($_POST['email']);

    // Buscar usuario por Email o por Identificación (Soporte Trello)
    $stmt = $conn->prepare("SELECT id, email, nombre FROM usuarios WHERE email = ? OR identificacion = ?");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $email = $user['email'];
        $nombre = $user['nombre'];

        $token = bin2hex(random_bytes(32));
        $expira = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $upd = $conn->prepare(
            "UPDATE usuarios 
             SET reset_token=?, reset_expira=? 
             WHERE email=?"
        );
        $upd->bind_param("sss", $token, $expira, $email);
        if (!$upd->execute()) {
            echo "<h3>Error al actualizar la base de datos:</h3>";
            echo "Error: " . $conn->error;
            echo "<p>¿Ya ejecutaste el archivo <b>fix_db.php</b>?</p>";
            exit;
        }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $link = "$protocol://$host/reset_password.php?token=$token";

        require_once 'config_mail.php';

        try {
            $mail = obtener_mailer();
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Recuperación de contraseña';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #333;'>
                    <h2 style='color: #0d6efd;'>Recuperar contraseña</h2>
                    <p>Hola,</p>
                    <p>Has solicitado restablecer tu contraseña en la <strong>Plataforma UNICALI</strong>. Haz clic en el botón de abajo para continuar:</p>
                    <p style='margin: 30px 0;'>
                        <a href='$link' style='
                            padding: 12px 24px;
                            background-color: #0d6efd;
                            color: white;
                            text-decoration: none;
                            border-radius: 5px;
                            font-weight: bold;'>
                            Cambiar contraseña
                        </a>
                    </p>
                    <p style='font-size: 0.9em; color: #666;'>Este enlace vencerá en 1 hora por razones de seguridad.</p>
                    <hr style='border: 0; border-top: 1px solid #eee; margin-top: 30px;'>
                    <p style='font-size: 0.8em; color: #999;'>Si no solicitaste este cambio, puedes ignorar este correo.</p>
                </div>
            ";

            $mail->send();
            header("Location: recover_password.php?ok=1");
            exit;
        } catch (Exception $e) {
            // Fallback para entorno local: registrar el correo en logs y simular éxito
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $forceProd = getenv('SMTP_FORCE_PROD');
            $isLocal = !$forceProd && (
                stripos($host, 'localhost') !== false
                || stripos($host, '127.0.0.1') !== false
                || getenv('APP_ENV') === 'local'
            );

            if ($isLocal) {
                $logPath = __DIR__ . '/logs/mail-local.log';
                $payload = "---- " . date('Y-m-d H:i:s') . " ----\n"
                    . "TO: {$email}\nSUBJECT: Recuperación de contraseña\nLINK: {$link}\n\n"
                    . "HTML:\n" . strip_tags($mail->Body) . "\n\n";
                file_put_contents($logPath, $payload, FILE_APPEND | LOCK_EX);
                header("Location: recover_password.php?ok=1&simulado=1");
                exit;
            }

            $error_info = $mail->ErrorInfo;
            if (strpos($error_info, 'authenticate') !== false) {
                $error_envio = "<b>Error de Autenticación:</b> Google rechazó la clave. <br>1. Revisa que tu 'Contraseña de aplicación' sea correcta. <br>2. Confirma en tu Gmail el aviso de 'Inicio de sesión bloqueado'.";
            } else {
                $error_envio = "Error al enviar: " . $error_info;
            }
        }
    } else {
        $safeSearch = htmlspecialchars($search, ENT_QUOTES, "UTF-8");
        $error_envio = "El correo o identificación '$safeSearch' no está registrado.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Unicali Segura</title>
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
                <h2 style="font-size: 2rem;">¿Olvidaste tu clave?</h2>
                <p class="text-muted">Ingresa tu correo o número de identificación para recibir un enlace de recuperación.</p>
            </div>

            <?php if (isset($_GET['ok'])): ?>
                <div style="background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 15px; border-radius: 10px; margin-bottom: 25px; font-size: 0.9rem; border: 1px solid rgba(16, 185, 129, 0.2); text-align: center;">
                    <i class="fa-solid fa-circle-check"></i> Si los datos coinciden, hemos enviado las instrucciones a tu correo institucional. Revisa tu bandeja de entrada.
                </div>
            <?php endif; ?>

            <?php if (isset($error_envio)): ?>
                <div style="background: rgba(244, 63, 94, 0.1); color: #fb7185; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(244, 63, 114, 0.2); text-align: center;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error_envio; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label class="input-label">Correo o Identificación (Cédula/TI)</label>
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
                <span>Protección de Datos Unicali Segura</span>
            </div>
        </div>
    </div>
</body>

</html>
