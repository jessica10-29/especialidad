<?php
require_once 'conexion.php';

if (!isset($_GET['token'])) {
    header("Location: login.php");
    exit;
}

$token = $_GET['token'];

$sql = $conn->prepare(
    "SELECT id FROM usuarios 
     WHERE reset_token=? AND reset_expira > NOW()"
);
$sql->bind_param("s", $token);
$sql->execute();
$res = $sql->get_result();

if ($res->num_rows === 0) {
    die("Enlace inválido o vencido");
}

$usuario = $res->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $pass_raw = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($pass_raw !== $confirm) {
        $error = "Las contraseñas no coinciden.";
    } else if (strlen($pass_raw) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } else {
        $password = password_hash($pass_raw, PASSWORD_DEFAULT);

        $upd = $conn->prepare(
            "UPDATE usuarios 
             SET password=?, reset_token=NULL, reset_expira=NULL 
             WHERE id=?"
        );
        $upd->bind_param("si", $password, $usuario['id']);
        $upd->execute();

        header("Location: login.php?reset=ok");
        exit;
    }
}
$error = isset($error) ? $error : '';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Contraseña - Unicali Segura</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="background-mesh"></div>

    <div class="login-container">
        <div class="glass-panel login-box fade-in" style="max-width: 480px;">

            <div class="logo-area" style="margin-bottom: 30px;">
                <i class="fa-solid fa-lock-open logo-large" style="color: var(--primary);"></i>
                <h2 style="font-size: 2rem;">Restablecer Clave</h2>
                <p class="text-muted">Crea una nueva contraseña segura para tu cuenta.</p>
            </div>

            <?php if ($error): ?>
                <div
                    style="background: rgba(244,63,94,.1); color:#fb7185; padding:12px; border-radius:10px; margin-bottom:25px; font-size:.85rem; text-align:center;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <!-- NUEVA CONTRASEÑA -->
                <div class="input-group">
                    <label class="input-label">Nueva Contraseña</label>

                    <!-- INSTRUCCIONES -->
                    <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 10px; line-height: 1.5;">
                        <strong>Requisitos de la contraseña:</strong>
                        <ul style="margin-top: 6px; padding-left: 18px;">
                            <li>Mínimo 8 caracteres</li>
                            <li>Al menos una letra mayúscula</li>
                            <li>Al menos un número</li>
                            <li>Al menos un símbolo especial (@, #, $, %, etc.)</li>
                        </ul>
                        <span>Evita usar contraseñas fáciles o repetidas.</span>
                    </div>

                    <!-- INPUT -->
                    <div style="position: relative; width: 100%;">
                        <input type="password" id="password" name="password" class="input-field"
                            placeholder="Mínimo 8 caracteres" required style="padding-right: 45px;">

                        <span onclick="togglePassword('password', this)" style="
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 10;
          ">
                            <i class="fa-solid fa-eye"></i>
                        </span>
                    </div>

                    <small id="strengthText"></small>




                    <!-- CONFIRMAR CONTRASEÑA -->
                    <div class="input-group">
                        <label class="input-label">Confirmar Contraseña</label>
                        <div style="position: relative;">
                            <input type="password" id="confirm_password" name="confirm_password" class="input-field"
                                placeholder="Repite la contraseña" required>

                            <span onclick="togglePassword('confirm_password', this)"
                                style="position:absolute; right:15px; top:50%; transform:translateY(-50%); cursor:pointer;">
                                <i class="fa-solid fa-eye"></i>
                            </span>
                        </div>
                        <small id="matchText"></small>
                    </div>

                    <button type="submit" class="btn btn-primary"
                        style="width:100%; height:50px; margin-top:10px; background:#10b981;">
                        Actualizar Contraseña <i class="fa-solid fa-shield-check" style="margin-left:8px;"></i>
                    </button>
            </form>

        </div>
    </div>

    <script>
        function togglePassword(id, el) {
            const input = document.getElementById(id);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            el.innerHTML = show
                ? '<i class="fa-solid fa-eye-slash"></i>'
                : '<i class="fa-solid fa-eye"></i>';
        }

        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const strengthText = document.getElementById('strengthText');
        const matchText = document.getElementById('matchText');

        function passwordStrength(p) {
            let s = 0;
            if (p.length >= 8) s++;
            if (/[A-Z]/.test(p)) s++;
            if (/[0-9]/.test(p)) s++;
            if (/[^A-Za-z0-9]/.test(p)) s++;
            return s;
        }

        password.addEventListener('input', () => {
            const m = ['', 'Débil', 'Media', 'Segura', 'Muy segura'];
            strengthText.textContent = m[passwordStrength(password.value)];
        });

        confirmPassword.addEventListener('input', () => {
            matchText.textContent =
                confirmPassword.value === password.value
                    ? 'Las contraseñas coinciden'
                    : 'Las contraseñas no coinciden';
        });

        document.querySelector('form').addEventListener('submit', e => {
            if (passwordStrength(password.value) < 3) {
                e.preventDefault();
                alert('La contraseña no es lo suficientemente segura');
            }
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
            }
        });
    </script>

</body>

</html>