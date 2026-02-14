<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('estudiante');

$id = $_SESSION['usuario_id'];

// Obtener todas las materias y calcular promedios (Query estandarizada)
$sql = "SELECT m.nombre, m.codigo, 
        (SELECT SUM(valor * CASE 
            WHEN corte='Corte 1' THEN 0.3 
            WHEN corte='Corte 2' THEN 0.3 
            WHEN corte='Final' THEN 0.4 
            ELSE 0 END) 
         FROM notas n 
         JOIN matriculas mat ON n.matricula_id = mat.id 
         WHERE mat.materia_id = m.id AND mat.estudiante_id = $id) as promedio
        FROM materias m
        JOIN matriculas mat ON m.id = mat.materia_id
        WHERE mat.estudiante_id = $id";

$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Mis Notas - Unicali</title>
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>

<body>
    <div class="container" style="margin-top: 50px;">
        <div class="glass-panel card">
            <h2 style="margin-bottom: 20px;"><i class="fa-solid fa-graduation-cap"></i> Resumen de Calificaciones</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Materia</th>
                            <th>Código</th>
                            <th>Promedio Final</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res && $res->num_rows > 0): while ($n = $res->fetch_assoc()):
                                $prom = $n['promedio'] ? number_format((float)$n['promedio'], 1) : '0.0';
                        ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($n['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($n['codigo']); ?></td>
                                    <td style="font-weight: 600; color: <?php echo $prom >= 3 ? 'var(--success)' : 'var(--danger)'; ?>;">
                                        <?php echo $prom; ?>
                                    </td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr>
                                <td colspan="3" style="text-align:center;">No tienes materias inscritas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <a href="dashboard_estudiante.php" class="btn btn-outline">Volver al Panel</a>
                <a href="pdf.php" target="_blank" class="btn btn-primary"><i class="fa-solid fa-file-pdf"></i> Descargar Reporte Completo</a>
            </div>
        </div>
    </div>
</body>

</html>