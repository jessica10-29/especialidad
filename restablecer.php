<?php
require 'conexion.php';

$mensaje = "";
$token_valido = false;

// Verificar si vienen el token y el email en la URL
if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = $_GET['token'];
    $email = $_GET['email'];
    $ahora = date("Y-m-d H:i:s");

    // Buscar el token en la base de datos y verificar que no haya expirado
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = :email AND token = :token AND expira >= :ahora");
    $stmt->execute(['email' => $email, 'token' => $token, 'ahora' => $ahora]);

    if ($stmt->rowCount() > 0) {
        $token_valido = true;
    } else {
        $mensaje = "El enlace es inválido o ha expirado.";
    }
}

// Procesar el formulario de cambio de contraseña
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password'])) {
    $email = $_POST['email']; // Lo recibimos del input hidden
    $token = $_POST['token'];
    $password_nueva = $_POST['password'];

    // Hashing de la contraseña (Seguridad)
    $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);

    // Actualizar la contraseña del usuario
    $stmt = $pdo->prepare("UPDATE usuarios SET password = :password WHERE email = :email");
    if ($stmt->execute(['password' => $password_hash, 'email' => $email])) {

        // Borrar el token usado para que no se pueda usar dos veces
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email");
        $stmt->execute(['email' => $email]);

        echo "¡Contraseña actualizada correctamente! <a href='login.php'>Iniciar Sesión</a>";
        exit;
    } else {
        $mensaje = "Hubo un error al actualizar.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Nueva Contraseña</title>
</head>

<body>
    <h2>Crear Nueva Contraseña</h2>
    <?php if ($mensaje): ?>
        <p style="color:red;"><?php echo $mensaje; ?></p> <?php endif; ?>

    <?php if ($token_valido): ?>
        <form method="POST" action="">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <label>Nueva Contraseña:</label><br>
            <input type="password" name="password" required minlength="6"><br><br>

            <button type="submit">Cambiar Contraseña</button>
        </form>
    <?php endif; ?>
</body>

</html>