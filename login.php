<?php
// session_start(); // Eliminado: conexion.php maneja el inicio de sesión seguro
require_once 'conexion.php';

$error = '';

// Cabeceras adicionales orientadas al navegador para esta página
if (!headers_sent()) {
    header_remove('X-Powered-By');

    $csp = "default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
        . "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "frame-ancestors 'self'; "
        . "form-action 'self'; "
        . "base-uri 'self'";

    // Solo forzar HTTPS en subrecursos cuando esté activo o forzado en el servidor
    if (!empty($httpsActivo)) {
        $csp .= "; upgrade-insecure-requests";
    }

    header("Content-Security-Policy: {$csp}");
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // 🛡️ Verificar CSRF
    if (!isset($_POST['csrf_token']) || !verificar_csrf_token($_POST['csrf_token'])) {
        die("Error de seguridad: Solicitud inválida (CSRF). Recargue la página.");
    }

    // Limpiar datos
    $identificador = trim($_POST['identificador']); // Puede ser Email o Cédula
    $password = $_POST['password'];
    $codigo_docente = $_POST['codigo_docente'] ?? '';

    // Buscar por Email O por Identificación (Cédula/TI)
    $stmt = $conn->prepare("SELECT id, nombre, password, rol FROM usuarios WHERE email = ? OR identificacion = ?");
    $stmt->bind_param("ss", $identificador, $identificador);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {

        $usuario = $resultado->fetch_assoc();
        $login_ok = false;

        // 🔐 1. Verificar contraseña hasheada
        if (password_verify($password, $usuario['password'])) {
            $login_ok = true;
        }
        // 🔄 2. Contraseña antigua en texto plano
        else if ($password === $usuario['password']) {
            $login_ok = true;

            // Convertir a hash automáticamente
            $nuevo_hash = password_hash($password, PASSWORD_BCRYPT);
            $upd = $conn->prepare("UPDATE usuarios SET password=? WHERE id=?");
            $upd->bind_param("si", $nuevo_hash, $usuario['id']);
            $upd->execute();
            $upd->close();
        }

        // ❌ Contraseña incorrecta
        if (!$login_ok) {
            $error = "La contraseña ingresada es incorrecta.";
        }
        // 👨‍🏫 Validar código docente SOLO si es profesor
        else if (
            strtolower(trim($usuario['rol'])) === 'profesor'
            && trim(strtoupper($codigo_docente)) !== 'UNICALI_DOCENTE'
        ) {
            $error = "Acceso docente denegado. Código incorrecto.";
        }
        // ✅ Login exitoso
        else {
            // Prevenir Session Fixation
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = strtolower(trim($usuario['rol']));

            if ($_SESSION['rol'] === 'profesor') {
                header("Location: dashboard_profesor.php");
            } else {
                header("Location: dashboard_estudiante.php");
            }
            exit;
        }
    } else {
        $error = "No existe una cuenta con esos datos (Correo o Cédula).";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Unicali Segura</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="icon" type="image/png" href="favicon.png?v=3">
    <link rel="shortcut icon" href="favicon.ico?v=3">
    <link rel="apple-touch-icon" href="favicon.png?v=3">
</head>

<body>
    <div class="background-mesh"></div>

    <div class="login-container">
        <div style="position: absolute; top: 30px; left: 30px;">
            <a href="index.php" class="btn btn-outline" style="padding: 10px 15px;">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
        </div>
        <div class="glass-panel login-box fade-in" style="max-width: 480px;">
            <div class="logo-area" style="margin-bottom: 30px;">
                <i class="fa-solid fa-graduation-cap logo-large"></i>
                <h2 style="font-size: 2rem;">Acceso al Portal</h2>
                <p class="text-muted">Ingresa tus credenciales para continuar</p>
            </div>

            <?php if ($error): ?>
                <div
                    style="background: rgba(244, 63, 94, 0.1); color: #fb7185; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(244, 63, 114, 0.2);">
                    <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Token CSRF Oculto -->
                <input type="hidden" name="csrf_token" value="<?php echo generar_csrf_token(); ?>">

                <div class="input-group">
                    <label class="input-label">Usuario (Correo o Cédula)</label>
                    <input type="text" name="identificador" class="input-field" placeholder="ej: juan@email.com o 1005..." required autocomplete="username">
                </div>

                <div class="input-group">
                    <label class="input-label">Contraseña</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="password" class="input-field" placeholder="••••••••"
                            required autocomplete="current-password">
                        <button type="button" class="password-toggle" data-target="password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>


                <div class="input-group"
                    style="border: 1px dashed var(--primary); padding: 15px; border-radius: 12px; background: rgba(99, 102, 241, 0.05); margin-top: 20px;">
                    <label class="input-label" style="color: var(--primary);">
                        Código de Acceso Docente (solo profesores)
                    </label>
                    <input type="password" name="codigo_docente" class="input-field"
                        placeholder="Ingrese solo si es profesor" autocomplete="off">
                </div>



                <div style="text-align: right; margin-bottom: 25px;">
                    <a href="recover_password.php"
                        style="color: var(--primary); text-decoration: none; font-size: 0.85rem; font-weight: 500;">¿Olvidaste
                        tu contraseña?</a>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; height: 50px;">
                    Entrar Seguramente <i class="fa-solid fa-lock" style="margin-left: 8px;"></i>
                </button>
            </form>

            <div style="margin: 30px 0; border-top: 1px solid var(--glass-border);"></div>

            <p style="font-size: 0.9rem; color: var(--text-muted);">
                ¿No tienes una cuenta? <a href="registro.php"
                    style="color: var(--secondary); font-weight: 600; text-decoration: none; margin-left: 5px;">Regístrate</a>
            </p>

            <div class="security-badge">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Conexión Segura SSL - Datos Encriptados</span>
            </div>
        </div>
    </div>
    <script src="js/login.js" defer></script>
</body>

</html>
