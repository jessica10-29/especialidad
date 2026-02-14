<?php
// doctor_periodos.php - Reparador automático del sistema de periodos
require_once 'conexion.php';

echo "<h1>🩺 Doctor del Sistema Académico</h1>";

// 1. Asegurar tabla Periodos
$q1 = "CREATE TABLE IF NOT EXISTS periodos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    limite_notas DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($q1)) {
    echo "✅ Tabla 'periodos' verificada.<br>";
} else {
    echo "❌ Error al crear 'periodos': " . $conn->error . "<br>";
}

// 2. Asegurar columnas en matriculas
$cols = [
    "periodo_id" => "INT DEFAULT NULL AFTER periodo",
    "estado" => "ENUM('activo', 'inactivo') DEFAULT 'activo'" // En caso de que se use
];

foreach ($cols as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM matriculas LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE matriculas ADD COLUMN $col $def");
        echo "✅ Columna '$col' añadida a 'matriculas'.<br>";
    }
}

// 3. Crear periodo por defecto si no hay ninguno
$res_p = $conn->query("SELECT id FROM periodos LIMIT 1");
if ($res_p && $res_p->num_rows == 0) {
    $conn->query("INSERT INTO periodos (nombre, fecha_inicio, fecha_fin, estado) VALUES ('Semestre 2024-1', '2024-01-01', '2024-06-30', 'activo')");
    $pid = $conn->insert_id;
    echo "✅ Periodo por defecto creado con ID: $pid.<br>";
} else {
    $pid = $res_p->fetch_assoc()['id'];
}

// 4. Configurar el Periodo Actual en la tabla configuracion
$check_conf = $conn->query("SELECT * FROM configuracion WHERE clave = 'periodo_actual_id'");
if ($check_conf && $check_conf->num_rows == 0) {
    $conn->query("INSERT INTO configuracion (clave, valor) VALUES ('periodo_actual_id', '$pid')");
    echo "✅ Configuración 'periodo_actual_id' establecida en: $pid.<br>";
} else if ($check_conf) {
    $row = $check_conf->fetch_assoc();
    if (empty($row['valor']) || $row['valor'] == '0') {
        $conn->query("UPDATE configuracion SET valor = '$pid' WHERE clave = 'periodo_actual_id'");
        echo "✅ Configuración 'periodo_actual_id' actualizada de 0 a $pid.<br>";
    }
}

// 5. MIGRACIÓN: Vincular matriculas huérfanas
$conn->query("UPDATE matriculas SET periodo_id = $pid WHERE periodo_id IS NULL OR periodo_id = 0");
$migrados = $conn->affected_rows;
echo "✅ Migración completa: $migrados alumnos vinculados al periodo actual.<br>";

echo "<h2>¡Sistema reparado!</h2>";
echo "<a href='gestion_notas.php' style='display:inline-block; padding:10px 20px; background:#6366f1; color:white; text-decoration:none; border-radius:10px;'>Ir a Gestionar Notas</a>";
