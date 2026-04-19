<?php
// test_login.php - Diagnostico del sistema de login
require_once 'conexion.php';

echo "<h1>🔍 Diagnóstico del Login</h1>";

// Info de entorno
echo "<h3>Entorno Detectado:</h3>";
echo "<p><strong>Host:</strong> " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'Desconocido') . "</p>";
echo "<p><strong>Es Local:</strong> " . ($GLOBALS['isLocal'] ? "Sí (localhost/127.0.0.1/LAN)" : "No (Hosting Remoto)") . "</p>";
echo "<p><strong>HTTPS Activo:</strong> " . ($GLOBALS['httpsActivo'] ? "Sí" : "No") . "</p>";

// 1. Verificar conexión a BD
echo "<h3>Conexión a BD:</h3>";
if ($conn->connect_errno) {
    echo "<p style='color: red;'><strong>❌ Error de conexión:</strong> " . htmlspecialchars($conn->connect_error) . "</p>";
    exit;
} else {
    echo "<p style='color: green;'><strong>✅ Conexión exitosa</strong></p>";
    echo "<p><strong>Host usado:</strong> " . htmlspecialchars($GLOBALS['host'] ?? 'Desconocido') . ":" . ($GLOBALS['port'] ?? 3306) . "</p>";
    echo "<p><strong>Base de datos:</strong> " . htmlspecialchars($GLOBALS['db'] ?? 'Desconocida') . "</p>";
}

// 2. Verificar que la tabla usuarios existe
$result = $conn->query("SHOW TABLES LIKE 'usuarios'");
if ($result->num_rows === 0) {
    echo "<p style='color: red;'><strong>❌ Tabla 'usuarios' no existe</strong></p>";
    exit;
} else {
    echo "<p style='color: green;'><strong>✅ Tabla 'usuarios' existe</strong></p>";
}

// 3. Contar usuarios
$result = $conn->query("SELECT COUNT(*) as total FROM usuarios");
$row = $result->fetch_assoc();
echo "<h3>Base de Datos:</h3>";
echo "<p><strong>Total de usuarios:</strong> " . $row['total'] . "</p>";

// 4. Listar todos los usuarios (sin mostrar contraseñas)
if ($row['total'] === 0) {
    echo "<p style='color: orange;'><strong>⚠️ No hay usuarios. Importa bd.sql en phpMyAdmin.</strong></p>";
} else {
    echo "<h3>Usuarios disponibles:</h3>";
    $result = $conn->query("SELECT id, nombre, email, identificacion, rol FROM usuarios");
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Nombre</th><th>Email</th><th>Identificación</th><th>Rol</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['identificacion']) . "</td>";
        echo "<td>" . htmlspecialchars($row['rol']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 5. Probar función CSRF
echo "<h3>Sesión:</h3>";
$token = generar_csrf_token();
echo "<p><strong>ID de sesión:</strong> " . session_id() . "</p>";
echo "<p><strong>Token CSRF:</strong> " . (isset($_SESSION['csrf_token']) ? "✅ Generado" : "❌ No generado") . "</p>";
echo "<p><strong>Usuario autenticado:</strong> " . (isset($_SESSION['usuario_id']) ? "Sí (ID: " . $_SESSION['usuario_id'] . ")" : "No") . "</p>";

echo "<hr>";
echo "<p style='margin-top: 30px;'><a href='login.php' style='padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 5px;'>Ir al Login</a></p>";
?>

