<?php
// test_login.php - Diagnostico del sistema de login
require_once 'conexion.php';

echo "<h2>Diagnóstico del Login</h2>";

// 1. Verificar conexión a BD
if ($conn->connect_errno) {
    echo "<p style='color: red;'>❌ Error de conexión a BD: " . $conn->connect_error . "</p>";
    exit;
} else {
    echo "<p style='color: green;'>✅ Conexión a BD exitosa</p>";
}

// 2. Verificar que la tabla usuarios existe
$result = $conn->query("SHOW TABLES LIKE 'usuarios'");
if ($result->num_rows === 0) {
    echo "<p style='color: red;'>❌ Tabla 'usuarios' no existe</p>";
    exit;
} else {
    echo "<p style='color: green;'>✅ Tabla 'usuarios' existe</p>";
}

// 3. Contar usuarios
$result = $conn->query("SELECT COUNT(*) as total FROM usuarios");
$row = $result->fetch_assoc();
echo "<p>Total de usuarios en BD: <strong>" . $row['total'] . "</strong></p>";

// 4. Listar todos los usuarios (sin mostrar contraseñas)
echo "<h3>Usuarios disponibles:</h3>";
$result = $conn->query("SELECT id, nombre, email, identificacion, rol FROM usuarios");
if ($result->num_rows === 0) {
    echo "<p style='color: red;'>⚠️ No hay usuarios en la base de datos. Debes crear algunos.</p>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Identificación</th><th>Rol</th></tr>";
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
session_start();
echo "<h3>Prueba de CSRF:</h3>";
$token = generar_csrf_token();
echo "<p>Token generado: <code>" . substr($token, 0, 20) . "...</code></p>";
echo "<p>Token en sesión: " . (isset($_SESSION['csrf_token']) ? "✅ Sí" : "❌ No") . "</p>";

// 6. Info de sesión
echo "<h3>Estado de Sesión:</h3>";
echo "<p>ID de sesión: " . session_id() . "</p>";
echo "<p>Usuario autenticado: " . (isset($_SESSION['usuario_id']) ? "Sí (ID: " . $_SESSION['usuario_id'] . ")" : "No") . "</p>";

echo "<hr>";
echo "<p><a href='login.php'>Volver a Login</a></p>";
?>
