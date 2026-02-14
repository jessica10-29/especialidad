<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('estudiante');

$id = $_SESSION['usuario_id'];

// Obtener todas las observaciones de las notas
$sql = "SELECT n.observacion, n.corte, m.nombre as materia, u.nombre as profesor, n.updated_at
        FROM notas n
        JOIN matriculas mat ON n.matricula_id = mat.id
        JOIN materias m ON mat.materia_id = m.id
        JOIN usuarios u ON m.profesor_id = u.id
        WHERE mat.estudiante_id = $id AND n.observacion IS NOT NULL AND n.observacion != ''
        ORDER BY n.updated_at DESC";

$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Observaciones del Docente - Unicali</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="background-mesh"></div>
    <div class="dashboard-grid">
        <aside class="sidebar">
            <div class="logo-area" style="margin-bottom: 40px; text-align: center;">
                <i class="fa-solid fa-graduation-cap logo-icon" style="font-size: 2rem; color: var(--primary);"></i>
                <h3 style="color: white; margin-top: 10px;">Unicali<span style="color: var(--primary);">Estudiante</span></h3>
            </div>
            <nav>
                <a href="dashboard_estudiante.php" class="nav-link">
                    <i class="fa-solid fa-house"></i> Inicio
                </a>
                <a href="generar_documento.php?tipo=estudio" target="_blank" class="nav-link" style="color: #fbbf24; font-weight: 700;">
                    <i class="fa-solid fa-certificate"></i> Certificado Oficial
                </a>
                <a href="ver_asistencia.php" class="nav-link">
                    <i class="fa-solid fa-calendar-check"></i> Mis Asistencias
                </a>
                <a href="ver_notas.php" class="nav-link">
                    <i class="fa-solid fa-chart-line"></i> Mis Notas
                </a>
                <a href="historial.php" class="nav-link">
                    <i class="fa-solid fa-receipt"></i> Historial Académico
                </a>
                <a href="observaciones.php" class="nav-link active">
                    <i class="fa-solid fa-comment-dots"></i> Observaciones
                </a>
                <a href="perfil.php" class="nav-link">
                    <i class="fa-solid fa-gear"></i> Configuración
                </a>
                <a href="logout.php" class="nav-link" style="margin-top: auto; color: #f43f5e;">
                    <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header style="margin-bottom: 30px;">
                <h1 class="text-gradient">Buzón de Retroalimentación</h1>
                <p class="text-muted">Comentarios y notas adicionales enviadas por tus docentes</p>
            </header>

            <div style="display: grid; gap: 20px;">
                <?php if ($res && $res->num_rows > 0): while ($obs = $res->fetch_assoc()): ?>
                        <div class="card glass-panel fade-in" style="border-left: 4px solid var(--secondary);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="background: rgba(6, 182, 212, 0.1); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa-solid fa-comment-dots" style="color: var(--secondary);"></i>
                                    </div>
                                    <h3 style="font-size: 1.05rem;"><?php echo htmlspecialchars($obs['materia']); ?></h3>
                                </div>
                                <span class="text-muted" style="font-size: 0.8rem;">
                                    <i class="fa-regular fa-calendar-alt"></i> <?php echo date('d M, Y', strtotime($obs['updated_at'])); ?>
                                </span>
                            </div>

                            <div style="background: rgba(255,255,255,0.03); padding: 20px; border-radius: 12px; margin-bottom: 15px; position: relative;">
                                <i class="fa-solid fa-quote-left" style="position: absolute; top: 10px; left: 10px; opacity: 0.1; font-size: 1.5rem;"></i>
                                <p style="font-size: 0.95rem; line-height: 1.6; color: #cbd5e1; padding-left: 15px;">
                                    <?php echo htmlspecialchars($obs['observacion']); ?>
                                </p>
                            </div>

                            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 8px; font-size: 0.85rem;">
                                <span class="text-muted">Enviado por:</span>
                                <span style="color: var(--primary); font-weight: 600;">Prof. <?php echo htmlspecialchars($obs['profesor']); ?></span>
                                <span class="text-muted">•</span>
                                <span style="background: rgba(99, 102, 241, 0.1); color: var(--primary); padding: 2px 10px; border-radius: 20px; font-size: 0.75rem;"><?php echo $obs['corte']; ?></span>
                            </div>
                        </div>
                    <?php endwhile;
                else: ?>
                    <div class="card glass-panel fade-in" style="text-align: center; padding: 60px;">
                        <div style="background: rgba(255,255,255,0.05); width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-regular fa-face-smile-wink" style="font-size: 3rem; color: #94a3b8;"></i>
                        </div>
                        <h2 style="margin-bottom: 10px;">¡Todo al día!</h2>
                        <p class="text-muted">No tienes observaciones pendientes de tus docentes por el momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>