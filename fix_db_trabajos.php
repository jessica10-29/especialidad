<?php
require_once 'conexion.php';

// 1. Crear tabla de Actividades (Tareas)
$sql1 = "CREATE TABLE IF NOT EXISTS actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    materia_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_limite DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
)";

// 2. Crear tabla de Entregas (Submissions)
$sql2 = "CREATE TABLE IF NOT EXISTS entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    archivo VARCHAR(255) NOT NULL,
    comentario TEXT,
    fecha_entrega TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    calificacion DECIMAL(4, 2) DEFAULT NULL,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
)";

if ($conn->query($sql1) && $conn->query($sql2)) {
    echo "Tablas de actividades y entregas creadas/verificadas con éxito.<br>";
} else {
    echo "Error al crear tablas: " . $conn->error . "<br>";
}

// Asegurar carpeta de entregas
$destino = __DIR__ . '/uploads/entregas';
if (!is_dir($destino)) {
    if (mkdir($destino, 0755, true)) {
        echo "Carpeta uploads/entregas creada.<br>";
    } else {
        echo "Error al crear carpeta uploads/entregas.<br>";
    }
}
?>
