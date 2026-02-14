<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('profesor');

$actividad_id = (int)$_GET['actividad'];
$profesor_id = $_SESSION['usuario_id'];

// Verificar pertenencia
$check = $conn->query("SELECT a.titulo, m.nombre as materia FROM actividades a JOIN materias m ON a.materia_id = m.id WHERE a.id = $actividad_id AND m.profesor_id = $profesor_id");
if (!$check || $check->num_rows === 0) {
    die("No tienes permiso para ver estas entregas.");
}
$act = $check->fetch_assoc();

$entregas = $conn->query("SELECT e.*, u.nombre as estudiante, u.email FROM entregas e JOIN usuarios u ON e.estudiante_id = u.id WHERE e.actividad_id = $actividad_id ORDER BY e.fecha_entrega DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Entregas: <?php echo htmlspecialchars($act['titulo']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <div class="background-mesh"></div>
    <div class="main-content" style="margin: 40px auto; max-width: 1000px; width: 95%;">
        <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 class="text-gradient"><?php echo htmlspecialchars($act['titulo']); ?></h1>
                <p class="text-muted">Entregas de: <?php echo htmlspecialchars($act['materia']); ?></p>
            </div>
            <a href="gestion_actividades.php" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        </div>

        <div class="card glass-panel fade-in" style="padding: 0; overflow: hidden;">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Fecha Entrega</th>
                            <th>Comentario</th>
                            <th>Archivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($entregas && $entregas->num_rows > 0): ?>
                            <?php while ($e = $entregas->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($e['estudiante']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($e['email']); ?></small>
                                    </td>
                                    <td><?php echo date('d M, Y H:i', strtotime($e['fecha_entrega'])); ?></td>
                                    <td style="max-width: 250px; font-size: 0.85rem;"><?php echo htmlspecialchars($e['comentario'] ?: 'Sin comentario'); ?></td>
                                    <td>
                                        <a href="uploads/entregas/<?php echo $e['archivo']; ?>" target="_blank" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.8rem;">
                                            <i class="fa-solid fa-download"></i> Descargar
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" align="center" style="padding: 30px;">No hay entregas registradas aún.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
