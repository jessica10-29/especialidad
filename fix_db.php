<?php
require_once 'conexion.php';

echo "<h2>Actualizando base de datos...</h2>";

$sql = "ALTER TABLE usuarios 
        ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS reset_expira DATETIME DEFAULT NULL";

if ($conn->query($sql)) {
    echo "<p style='color:green;'>✅ Columnas reset_token y reset_expira añadidas correctamente (o ya existían).</p>";
} else {
    echo "<p style='color:red;'>❌ Error al actualizar la base de datos: " . $conn->error . "</p>";
}

echo "<p><a href='recover_password.php'>Volver a recuperación</a></p>";
