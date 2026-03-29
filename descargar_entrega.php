<?php
require_once 'conexion.php';
verificar_sesion();

$entrega_id = (int)($_GET['id'] ?? 0);
if ($entrega_id <= 0) {
    http_response_code(400);
    exit('Solicitud invalida.');
}

$stmt = $conn->prepare(
    "SELECT e.id, e.archivo, e.estudiante_id, e.actividad_id,
            a.titulo, a.materia_id,
            m.profesor_id
     FROM entregas e
     JOIN actividades a ON a.id = e.actividad_id
     JOIN materias m ON m.id = a.materia_id
     WHERE e.id = ?
     LIMIT 1"
);

if (!$stmt) {
    http_response_code(500);
    exit('No fue posible preparar la descarga.');
}

$stmt->bind_param('i', $entrega_id);
$stmt->execute();
$entrega = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$entrega) {
    http_response_code(404);
    exit('La entrega solicitada no existe.');
}

$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
$rol = $_SESSION['rol'] ?? '';
$puedeDescargar = false;

if ($rol === 'profesor' && (int)$entrega['profesor_id'] === $usuario_id) {
    $puedeDescargar = true;
} elseif ($rol === 'estudiante' && (int)$entrega['estudiante_id'] === $usuario_id) {
    $puedeDescargar = true;
} elseif ($rol === 'admin') {
    $puedeDescargar = true;
}

if (!$puedeDescargar) {
    http_response_code(403);
    exit('No tienes permiso para descargar este PDF.');
}

$nombreArchivo = (string)$entrega['archivo'];
if (!nombre_archivo_entrega_valido($nombreArchivo)) {
    http_response_code(400);
    exit('El nombre del archivo no es valido.');
}

$directorioEntregas = __DIR__ . '/uploads/entregas';
$rutaArchivo = $directorioEntregas . '/' . basename($nombreArchivo);

if (!ruta_esta_dentro_de_directorio($rutaArchivo, $directorioEntregas) || !is_file($rutaArchivo)) {
    http_response_code(404);
    exit('El archivo PDF no fue encontrado.');
}

$errorPdf = null;
if (!validar_pdf_seguro($rutaArchivo, $errorPdf)) {
    http_response_code(415);
    exit('El PDF no paso la validacion de seguridad.');
}

$nombreDescarga = limpiar_nombre_descarga(
    'entrega_' . $entrega['actividad_id'] . '_' . $entrega_id . '.pdf',
    'entrega.pdf'
);

if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($rutaArchivo));
header('Content-Disposition: attachment; filename="' . $nombreDescarga . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

readfile($rutaArchivo);
exit;
