<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('estudiante');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $actividad_id = (int)$_POST['actividad_id'];
    $estudiante_id = $_SESSION['usuario_id'];
    $comentario = limpiar_dato($_POST['comentario'] ?? '');
    $materia_nombre = $_POST['materia_nombre'] ?? '';

    // Verificar si el portal está abierto
    $habil = es_periodo_habil(); // Debería verificar esta materia específica si hay permisos especiales
    if (!$habil) {
        die("Error: El portal de entregas está cerrado.");
    }

    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['pdf', 'zip', 'rar', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
        $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            die("Error: Tipo de archivo no permitido.");
        }

        if ($_FILES['archivo']['size'] > 5 * 1024 * 1024) {
            die("Error: El archivo supera los 5MB.");
        }

        $destino_dir = __DIR__ . '/uploads/entregas';
        if (!is_dir($destino_dir)) {
            mkdir($destino_dir, 0755, true);
        }

        $nombre_archivo = 'entrega_' . $actividad_id . '_' . $estudiante_id . '_' . time() . '.' . $ext;
        
        if (move_uploaded_file($_FILES['archivo']['tmp_name'], $destino_dir . '/' . $nombre_archivo)) {
            $stmt = $conn->prepare("INSERT INTO entregas (actividad_id, estudiante_id, archivo, comentario) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE archivo = VALUES(archivo), comentario = VALUES(comentario), fecha_entrega = CURRENT_TIMESTAMP");
            $stmt->bind_param("iiss", $actividad_id, $estudiante_id, $nombre_archivo, $comentario);
            
            if ($stmt->execute()) {
                header("Location: ver_notas.php?materia=" . urlencode($materia_nombre) . "&status=success");
            } else {
                echo "Error al guardar en base de datos: " . $conn->error;
            }
        } else {
            echo "Error al mover el archivo al servidor.";
        }
    } else {
        echo "Error en la subida del archivo.";
    }
}
?>
