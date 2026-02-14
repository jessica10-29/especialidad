<?php
// debug_login.php
require_once 'conexion.php';

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
