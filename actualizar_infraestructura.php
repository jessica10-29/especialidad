<?php
require_once 'conexion.php';

echo "<h1>Actualizando Infraestructura Académica...</h1>";

// 1. Crear tabla de Periodos (Trello Card 18)
$sql_periodos = "CREATE TABLE IF NOT EXISTS periodos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    limite_notas DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_periodos)) {
    echo "<p>✅ Tabla 'periodos' lista.</p>";
} else {
    echo "<p>❌ Error en 'periodos': " . $conn->error . "</p>";
}

// 2. Modificar tabla materias para que sea más robusta (Trello Card 9)
// Asegurar que existe la columna estado en materias para desactivar/eliminar lógicamente
$check_materia_estado = $conn->query("SHOW COLUMNS FROM materias LIKE 'estado'");
if ($check_materia_estado->num_rows == 0) {
    $conn->query("ALTER TABLE materias ADD COLUMN estado ENUM('activo', 'inactivo') DEFAULT 'activo' AFTER descripcion");
    echo "<p>✅ Columna 'estado' añadida a 'materias'.</p>";
}

// 3. Vincular matriculas a periodos de forma numérica si es necesario
// Por ahora mantendremos el campo string 'periodo' en matriculas por compatibilidad, 
// pero añadiremos 'periodo_id' para la nueva lógica.
$check_periodo_id = $conn->query("SHOW COLUMNS FROM matriculas LIKE 'periodo_id'");
if ($check_periodo_id->num_rows == 0) {
    $conn->query("ALTER TABLE matriculas ADD COLUMN periodo_id INT DEFAULT NULL AFTER periodo");
    $conn->query("ALTER TABLE matriculas ADD FOREIGN KEY (periodo_id) REFERENCES periodos(id)");
    echo "<p>✅ Columna 'periodo_id' añadida a 'matriculas'.</p>";
}

// 4. Crear un periodo inicial si no hay ninguno y configurar el actual
$check_empty = $conn->query("SELECT id FROM periodos");
if ($check_empty->num_rows == 0) {
    $conn->query("INSERT INTO periodos (nombre, fecha_inicio, fecha_fin, estado) VALUES ('Semestre 2024-1', '2024-01-01', '2024-06-30', 'activo')");
    $periodo_id = $conn->insert_id;
    echo "<p>✅ Periodo inicial creado.</p>";
} else {
    $periodo_id = $check_empty->fetch_assoc()['id'];
}

// 4.1 Definir el periodo_actual_id en configuracion si no existe
$check_conf = $conn->query("SELECT id FROM configuracion WHERE clave = 'periodo_actual_id'");
if ($check_conf->num_rows == 0) {
    $conn->query("INSERT INTO configuracion (clave, valor) VALUES ('periodo_actual_id', '$periodo_id')");
    echo "<p>✅ Configuración 'periodo_actual_id' inicializada con ID: $periodo_id.</p>";
} else {
    // Si ya existe pero el valor es 0 o vacío, forzar el primer periodo
    $val = $conn->query("SELECT valor FROM configuracion WHERE clave = 'periodo_actual_id'")->fetch_assoc()['valor'];
    if (empty($val) || $val == '0') {
        $conn->query("UPDATE configuracion SET valor = '$periodo_id' WHERE clave = 'periodo_actual_id'");
        echo "<p>✅ Configuración 'periodo_actual_id' corregida a ID: $periodo_id.</p>";
    }
}

// 4.2 MIGRACIÓN CRÍTICA: Vincular matrículas antiguas al periodo actual (Trello fix)
$conn->query("UPDATE matriculas SET periodo_id = $periodo_id WHERE periodo_id IS NULL OR periodo_id = 0");
echo "<p>✅ Migración: Estudiantes antiguos vinculados al periodo actual (" . $conn->affected_rows . " registros).</p>";

// 5. Expandir tabla de Asistencia (Trello Card 20)
// Añadiremos 'tardanza' y aseguraremos que use matricula_id para mayor precisión profesional.
$conn->query("ALTER TABLE asistencia MODIFY COLUMN estado ENUM('Presente', 'Ausente', 'Justificado', 'Tardanza') NOT NULL");
echo "<p>✅ ENUM de 'asistencia' actualizado (Tardanza añadida).</p>";

// 6. Tabla de Justificaciones
$sql_justificaciones = "CREATE TABLE IF NOT EXISTS justificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asistencia_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    motivo TEXT NOT NULL,
    archivo_adjunto VARCHAR(255),
    estado ENUM('pendiente', 'aprobada', 'rechazada') DEFAULT 'pendiente',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asistencia_id) REFERENCES asistencia(id) ON DELETE CASCADE
)";
if ($conn->query($sql_justificaciones)) {
    echo "<p>✅ Tabla 'justificaciones' lista.</p>";
}

// 7. Configuración Bancaria Institucional (Para Documentos Profesionales)
$banco_fields = [
    'inst_banco_nombre' => 'Banco de la República',
    'inst_banco_cuenta' => '000-123456-78',
    'inst_banco_tipo' => 'Ahorros',
    'inst_nit' => '900.123.456-1'
];

foreach ($banco_fields as $clave => $valor) {
    $check_b = $conn->query("SELECT id FROM configuracion WHERE clave = '$clave'");
    if ($check_b->num_rows == 0) {
        $conn->query("INSERT INTO configuracion (clave, valor) VALUES ('$clave', '$valor')");
    }
}
echo "<p>✅ Datos bancarios institucionales inicializados.</p>";

echo "<h2>Actualización completada exitosamente.</h2>";
echo "<a href='admin_periodos.php'>Ir a Gestión de Periodos</a>";
