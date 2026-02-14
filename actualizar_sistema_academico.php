<?php
// actualizar_sistema_academico.php - Script para actualizar la BD con Periodos y Observaciones

require_once 'conexion.php';

$queries = [
    // 1. Tabla de Periodos de Edición
    "CREATE TABLE IF NOT EXISTS periodos_edicion (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        fecha_inicio DATETIME NOT NULL,
        fecha_fin DATETIME NOT NULL,
        activo TINYINT(1) DEFAULT 1
    )",

    // 2. Tabla de Solicitudes de Edición (Extraordinarias)
    "CREATE TABLE IF NOT EXISTS solicitudes_edicion (
        id INT AUTO_INCREMENT PRIMARY KEY,
        profesor_id INT NOT NULL,
        materia_id INT NOT NULL,
        periodo_id INT NOT NULL DEFAULT 0,
        motivo TEXT NOT NULL,
        estado ENUM('pendiente', 'aprobado', 'rechazado') DEFAULT 'pendiente',
        fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (profesor_id) REFERENCES usuarios(id),
        FOREIGN KEY (materia_id) REFERENCES materias(id)
    )",

    // 3. Tabla de Permisos Especiales
    "CREATE TABLE IF NOT EXISTS permisos_especiales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        profesor_id INT NOT NULL,
        materia_id INT NOT NULL,
        fecha_vencimiento DATETIME NOT NULL,
        FOREIGN KEY (profesor_id) REFERENCES usuarios(id),
        FOREIGN KEY (materia_id) REFERENCES materias(id)
    )",

    // 4. Tabla de Historial de Notas (Auditoría)
    "CREATE TABLE IF NOT EXISTS historial_notas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nota_id INT NOT NULL,
        valor_anterior DECIMAL(4,2),
        valor_nuevo DECIMAL(4,2),
        observacion_anterior TEXT,
        observacion_nueva TEXT,
        justificacion TEXT,
        profesor_id INT NOT NULL,
        fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (nota_id) REFERENCES notas(id) ON DELETE CASCADE,
        FOREIGN KEY (profesor_id) REFERENCES usuarios(id)
    )",

    // 5. Configuraciones adicionales
    "INSERT IGNORE INTO configuracion (clave, valor) VALUES 
    ('limite_observacion', '250'),
    ('cambio_grande_umbral', '1.0')" // Notas que cambien más de 1.0 requieren justificación
];

echo "<h2>Actualizando Base de Datos...</h2>";

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "<p style='color:green;'>✅ Consulta ejecutada con éxito.</p>";
    } else {
        echo "<p style='color:red;'>❌ Error en consulta: " . $conn->error . "</p>";
    }
}

echo "<h3>Configuración Inicial de Periodos:</h3>";
// Crear un periodo de ejemplo si no hay ninguno
$check = $conn->query("SELECT id FROM periodos_edicion LIMIT 1");
if ($check->num_rows == 0) {
    $inicio = date('Y-m-d H:i:s');
    $fin = date('Y-m-d H:i:s', strtotime('+15 days'));
    $conn->query("INSERT INTO periodos_edicion (nombre, fecha_inicio, fecha_fin) VALUES ('Primer Corte 2024', '$inicio', '$fin')");
    echo "<p>Se ha creado un periodo de prueba 'Primer Corte 2024' (15 días desde hoy).</p>";
}

echo "<hr><p><a href='dashboard_profesor.php'>Volver al Inicio</a></p>";
