<?php
// finalizar_actualizacion_trello.php - Último paso para completar todas las funciones de Trello
require_once 'conexion.php';

$queries = [
    // 1. Columnas para Recuperación de Contraseña
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS reset_expira DATETIME DEFAULT NULL",

    // 2. Columna para Promedio Almacenado (Cálculo Automático)
    "ALTER TABLE matriculas ADD COLUMN IF NOT EXISTS promedio DECIMAL(4,2) DEFAULT 0.00",

    // 3. Columna para identificación (si no existe, Trello la pide)
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS identificacion VARCHAR(20) DEFAULT NULL"
];

echo "<h2>Finalizando Configuración Profesional...</h2>";

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "<p style='color:green;'>✅ Operación completada o columna ya existía.</p>";
    } else {
        echo "<p style='color:red;'>❌ Error: " . $conn->error . "</p>";
    }
}

// 4. Crear un Admin por defecto para probar (opcional, pero útil para gestionar periodos)
$check_admin = $conn->query("SELECT id FROM usuarios WHERE rol = 'admin' LIMIT 1");
if ($check_admin->num_rows == 0) {
    $pass = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO usuarios (nombre, email, password, rol) VALUES ('Administrador', 'admin@unicali.edu.co', '$pass', 'admin')");
    echo "<p><b>Nota:</b> Se ha creado un usuario administrador: <b>admin@unicali.edu.co</b> con clave <b>admin123</b> para que gestiones los periodos.</p>";
}

echo "<hr><p><a href='index.php'>Ir al Login</a></p>";
