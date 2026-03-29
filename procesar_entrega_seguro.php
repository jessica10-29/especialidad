<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('estudiante');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo no permitido.');
}

if (!verificar_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Error: Token de seguridad invalido.');
}

$actividad_id = (int)($_POST['actividad_id'] ?? 0);
$estudiante_id = (int)$_SESSION['usuario_id'];
$comentario = limpiar_dato($_POST['comentario'] ?? '');
$materia_nombre = (string)($_POST['materia_nombre'] ?? '');

if ($actividad_id <= 0) {
    http_response_code(400);
    exit('Error: Actividad invalida.');
}

$stmtActividad = $conn->prepare(
    "SELECT a.id, a.fecha_limite, a.materia_id
     FROM actividades a
     JOIN matriculas mat ON mat.materia_id = a.materia_id
     WHERE a.id = ? AND mat.estudiante_id = ?
     LIMIT 1"
);

if (!$stmtActividad) {
    http_response_code(500);
    exit('Error en la base de datos: ' . $conn->error);
}

$stmtActividad->bind_param('ii', $actividad_id, $estudiante_id);
$stmtActividad->execute();
$actividad = $stmtActividad->get_result()->fetch_assoc();
$stmtActividad->close();

if (!$actividad) {
    http_response_code(403);
    exit('Error: No tienes permiso para entregar archivos en esta actividad.');
}

$materia_id = (int)$actividad['materia_id'];
if (!es_periodo_habil($materia_id)) {
    http_response_code(403);
    exit('Error: El portal de entregas esta cerrado.');
}

if (!empty($actividad['fecha_limite']) && strtotime($actividad['fecha_limite']) < time()) {
    http_response_code(403);
    exit('Error: La fecha limite para esta entrega ya vencio.');
}

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit('Error: Debes adjuntar un archivo PDF valido.');
}

$ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    http_response_code(400);
    exit('Error: Solo se permiten archivos PDF.');
}

if ($_FILES['archivo']['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    exit('Error: El archivo supera los 5MB.');
}

if (!is_uploaded_file($_FILES['archivo']['tmp_name'])) {
    http_response_code(400);
    exit('Error: La carga del archivo no es valida.');
}

$errorPdf = null;
if (!validar_pdf_seguro($_FILES['archivo']['tmp_name'], $errorPdf, 5 * 1024 * 1024)) {
    http_response_code(400);
    exit('Error: ' . $errorPdf);
}

$destino_dir = __DIR__ . '/uploads/entregas';
if (!is_dir($destino_dir) && !mkdir($destino_dir, 0755, true) && !is_dir($destino_dir)) {
    http_response_code(500);
    exit("Error: No fue posible preparar la carpeta 'uploads/entregas'.");
}

$nombre_archivo = 'entrega_' . $actividad_id . '_' . $estudiante_id . '_' . bin2hex(random_bytes(8)) . '.pdf';
$rutaDestino = $destino_dir . '/' . $nombre_archivo;

if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaDestino)) {
    http_response_code(500);
    exit("Error al mover el archivo. Revisa los permisos de la carpeta 'uploads/entregas'.");
}

$stmt = $conn->prepare(
    "INSERT INTO entregas (actividad_id, estudiante_id, archivo, comentario)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
     archivo = VALUES(archivo),
     comentario = VALUES(comentario),
     fecha_entrega = CURRENT_TIMESTAMP"
);

if (!$stmt) {
    @unlink($rutaDestino);
    http_response_code(500);
    exit('Error en la base de datos: ' . $conn->error);
}

$stmt->bind_param('iiss', $actividad_id, $estudiante_id, $nombre_archivo, $comentario);

if (!$stmt->execute()) {
    @unlink($rutaDestino);
    http_response_code(500);
    exit('Error al ejecutar: ' . $stmt->error);
}

header('Location: ver_notas.php?materia=' . urlencode($materia_nombre) . '&status=success');
exit();
