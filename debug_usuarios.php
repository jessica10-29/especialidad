<?php
// debug_login.php
require_once 'conexion.php';

// Restringir a entorno local o CLI para no exponer hashes en produccion
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$esLocal = in_array($ip, ['127.0.0.1', '::1']) || preg_match('/^(10\\.|192\\.168\\.|172\\.(1[6-9]|2[0-9]|3[01])\\.)/', $ip);
if (getenv('APP_ENV') !== 'local' && !$esLocal && PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Debug de usuarios deshabilitado en produccion.');
}

echo "<h1>🔍 Debug de Usuarios</h1>";

$res = $conn->query("SELECT id, nombre, email, identificacion, rol, password FROM usuarios");

if ($res) {
    echo "<table border='1'>
    <tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Email</th>
        <th>Identificación</th>
        <th>Rol</th>
        <th>Password (Hash/Plain)</th>
        <th>Test '123456'</th>
    </tr>";
    while ($u = $res->fetch_assoc()) {
        $test_pass = '123456';
        $is_ok = password_verify($test_pass, $u['password']) ? '✅ MATCH' : ($u['password'] === $test_pass ? '✅ PLAIN MATCH' : '❌ NO');

        echo "<tr>
            <td>{$u['id']}</td>
            <td>{$u['nombre']}</td>
            <td>{$u['email']}</td>
            <td>{$u['identificacion']}</td>
            <td>{$u['rol']}</td>
            <td><code style='font-size:10px;'>{$u['password']}</code></td>
            <td>$is_ok</td>
        </tr>";
    }
    echo "</table>";
} else {
    echo "Error en la consulta: " . $conn->error;
}

echo "<h2>Estado de sesión</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
