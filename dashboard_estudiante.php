<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('estudiante');

$estudiante_id = $_SESSION['usuario_id'];
$nombre_estudiante = obtener_nombre_usuario();
// Asegurar foto fresca desde BD (por si la sesión no la trae)
$foto_estudiante = obtener_foto_usuario($_SESSION['foto'] ?? null);
$res_foto_est = $conn->query("SELECT foto FROM usuarios WHERE id = $estudiante_id");
if ($res_foto_est && ($frow = $res_foto_est->fetch_assoc()) && !empty($frow['foto'])) {
    $foto_estudiante = obtener_foto_usuario($frow['foto']);
}
if (empty($foto_estudiante)) {
    $foto_estudiante = obtener_foto_usuario(null);
}

// Obtener Periodo Actual de forma segura (Self-Healing)
$p_actual_id = obtener_periodo_actual();

$sql_matriculas = "SELECT m.nombre, m.codigo, u.nombre as profesor, mat.promedio 
                   FROM matriculas mat 
                   JOIN materias m ON mat.materia_id = m.id 
                   JOIN usuarios u ON m.profesor_id = u.id 
                   WHERE mat.estudiante_id = $estudiante_id AND mat.periodo_id = $p_actual_id";
$res_matriculas = $conn->query($sql_matriculas);

// Calcular Promedio General Acumulado (Trello)
$sql_general = "SELECT AVG(promedio) as promedio_general FROM matriculas WHERE estudiante_id = $estudiante_id";
$res_general = $conn->query($sql_general);
$prom_general_val = 0;
if ($res_general && $row_gen = $res_general->fetch_assoc()) {
    $prom_general_val = number_format((float)$row_gen['promedio_general'], 1);
}

// Obtener datos para tarjetas de resumen
$total_materias = ($res_matriculas) ? $res_matriculas->num_rows : 0;
$res_ganando = $conn->query("SELECT COUNT(*) as count FROM matriculas WHERE estudiante_id = $estudiante_id AND promedio >= 3.0");
$materias_ganando = ($res_ganando && $row_g = $res_ganando->fetch_assoc()) ? (int)$row_g['count'] : 0;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial Académico - Unicali Segura</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="icon" type="image/png" href="/favicon.png?v=3">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="shortcut icon" href="/favicon.ico?v=3">
    <link rel="apple-touch-icon" href="/favicon.png?v=3">
</head>

<body>
    <div class="background-mesh"></div>

    <div class="mobile-toggle" id="side-toggle">
        <i class="fa-solid fa-bars"></i>
    </div>
    <div class="mobile-overlay" id="mobile-overlay"></div>

    <div class="dashboard-grid">
        <aside class="sidebar">
            <div class="logo-area" style="margin-bottom: 40px; text-align: center;">
                <i class="fa-solid fa-graduation-cap logo-icon" style="font-size: 2rem; color: var(--primary);"></i>
                <h3 style="color: white; margin-top: 10px;">
                    Unicali
                    <span style="background: linear-gradient(135deg, #fbbf24, #f59e0b, #f97316); -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block;">
                        Segura
                    </span>
                    <span style="color: var(--primary);">Estudiante</span>
                </h3>
            </div>
            <nav>
                <a href="dashboard_estudiante.php" class="nav-link active">
                    <i class="fa-solid fa-house"></i> Inicio
                </a>
                <a href="pdf.php" target="_blank" class="nav-link" style="color: #22d3ee; font-weight: 700;">
                    <i class="fa-solid fa-file-pdf"></i> Certificado de Notas
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
                <a href="historial.php" class="nav-link">
                    <i class="fa-solid fa-receipt"></i> Historial Pago
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
            <!-- SECCIÓN DE CERTIFICADOS SUPREMA (IMPULSADA) -->
            <!-- SECCIÓN DE CERTIFICADOS SUPREMA (IMPULSADA) -->
            <header style="margin-bottom: 30px;">
                <div class="responsive-header" style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <h1 class="text-gradient responsive-text-xl" style="font-size: 2.2rem;">Hola, <?php echo htmlspecialchars($nombre_estudiante); ?></h1>
                        <p class="text-muted">Estado actual de tu formación académica en Unicali Segura.</p>
                    </div>
                    <div class="responsive-btn-header" style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
                        <div style="width: 110px; height: 110px; border-radius: 50%; border: 4px solid rgba(255,255,255,0.22); box-shadow: 0 12px 28px rgba(0,0,0,0.35); overflow: hidden; background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.25), rgba(255,255,255,0.05)); display: flex; align-items: center; justify-content: center;">
                            <img src="<?php echo htmlspecialchars($foto_estudiante); ?>" alt="Foto de perfil" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <a href="perfil.php" class="btn btn-primary" style="background: linear-gradient(90deg, var(--primary), #8b5cf6); color: #fff;">
                            <i class="fa-solid fa-user-pen"></i> Editar Perfil
                        </a>
                    </div>
                </div>
            </header>

            <div class="responsive-flex-column" style="display:flex; flex-wrap: wrap; gap: 16px; align-items: stretch; justify-content: center; margin-bottom: 25px;">
                <?php 
                $habil_global = es_periodo_habil();
                $p_res = $conn->query("SELECT nombre, limite_notas FROM periodos WHERE id = $p_actual_id");
                $ano_actual = date('Y');
                $p_info = ($p_res && $p_res->num_rows > 0) ? $p_res->fetch_assoc() : ['nombre' => "Emergencia $ano_actual", 'limite_notas' => null];
                $nombre_periodo = $p_info['nombre'] ?? "Emergencia $ano_actual";
                // Si el nombre trae un año anterior, reemplazarlo por el año actual
                if (stripos($nombre_periodo, 'emergencia') !== false) {
                    $nombre_periodo = preg_replace('/20\\d{2}/', $ano_actual, $nombre_periodo);
                }
                $limite_notas = $p_info['limite_notas'] ?? null;
                $fecha_limite = $limite_notas ? date('d M, Y', strtotime($limite_notas)) : date('d M, Y'); // usa fecha actual si no hay límite
                $vencida = $limite_notas ? strtotime($limite_notas) < time() : false;
                $estado_portal = $habil_global ? 'Abierto' : 'Cerrado';
                ?>
                <div class="card glass-panel fade-in" style="flex: 1 1 300px; background: <?php echo $habil_global ? 'rgba(16, 185, 129, 0.1)' : 'rgba(244, 63, 94, 0.1)'; ?>; border: 1px solid <?php echo $habil_global ? '#10b981' : '#f43f5e'; ?>; padding: 20px; display: flex; align-items: center; gap: 20px;">
                    <div style="font-size: 2.5rem; color: <?php echo $habil_global ? '#10b981' : '#f43f5e'; ?>;">
                        <i class="fa-solid <?php echo $habil_global ? 'fa-door-open' : 'fa-door-closed'; ?>"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0; color: white;">Estado Académico: <?php echo htmlspecialchars($nombre_periodo); ?></h4>
                        <p style="margin: 5px 0; font-size: 0.9rem; color: rgba(255,255,255,0.7);">
                            <?php if ($habil_global): ?>
                                El portal está <strong><?php echo $estado_portal; ?></strong>. Fecha límite de trabajos: <?php echo $fecha_limite; ?><?php echo $vencida ? ' (Vencida)' : ''; ?>
                            <?php else: ?>
                                El portal está <strong><?php echo $estado_portal; ?></strong>. El periodo de gestión ha finalizado.
                            <?php endif; ?>
                        </p>
                        <div style="margin-top: 8px;">
                            <a href="ver_notas.php" class="btn btn-outline" style="padding:8px 12px; font-size:0.85rem;">
                                Ver entregas y fechas
                            </a>
                        </div>
                    </div>
                </div>



            </div>

            <!-- Grid de Estadísticas (Trello: Resumen de promedio) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px;">
                <div class="card glass-panel fade-in" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(167, 139, 250, 0.1) 100%); border: 1px solid rgba(99, 102, 241, 0.2);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Promedio General</p>
                            <h2 style="font-size: 2.5rem; margin: 10px 0; color: <?php echo $prom_general_val >= 3 ? '#34d399' : '#fb7185'; ?>;"><?php echo $prom_general_val; ?></h2>
                            <p style="font-size: 0.75rem; opacity: 0.6;"><i class="fa-solid fa-arrow-trend-up"></i> Rendimiento Actual</p>
                        </div>
                        <div style="width: 60px; height: 60px; background: rgba(99, 102, 241, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-star-half-stroke" style="color: var(--primary); font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>

                <div class="card glass-panel fade-in">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Materias Activas</p>
                            <h2 style="font-size: 2rem; margin: 10px 0;"><?php echo $total_materias; ?></h2>
                            <p style="font-size: 0.75rem; color: #34d399;"><i class="fa-solid fa-circle-check"></i> <?php echo $materias_ganando; ?> Aprobadas</p>
                        </div>
                        <div style="width: 60px; height: 60px; background: rgba(16, 185, 129, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-book-open-reader" style="color: #34d399; font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>

                <div class="card glass-panel fade-in">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Puntualidad</p>
                            <h2 style="font-size: 2rem; margin: 10px 0;">95%</h2>
                            <p style="font-size: 0.75rem; opacity: 0.6;"><i class="fa-solid fa-clock"></i> Último mes</p>
                        </div>

                    </div>
                    <div style="width: 60px; height: 60px; background: rgba(6, 182, 212, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-calendar-check" style="color: #06b6d4; font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>

            <div class="responsive-layout-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start;">

                <div>
                    <h3 style="margin-bottom: 20px;"><i class="fa-solid fa-bookmark" style="color: var(--primary);"></i> Mis Materias Inscritas (Progreso)</h3>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                        <?php
                        $res_matriculas->data_seek(0);
                        if ($res_matriculas && $res_matriculas->num_rows > 0) {
                            while ($row = $res_matriculas->fetch_assoc()) {
                                // Calcular progreso visual: Cuantas notas tiene de 5 posibles
                                $mid = $row['codigo']; // Usamos codigo o ID
                                // Necesitamos el ID real de matricula para contar notas
                                $res_m_id = $conn->query("SELECT id FROM matriculas WHERE estudiante_id = $estudiante_id AND materia_id = (SELECT id FROM materias WHERE codigo = '" . $row['codigo'] . "')");
                                $m_id_real = ($res_m_id && $res_m_id->num_rows > 0) ? $res_m_id->fetch_assoc()['id'] : 0;

                                $num_notas = 0;
                                if ($m_id_real > 0) {
                                    $q_count = $conn->query("SELECT COUNT(*) as count FROM notas WHERE matricula_id = $m_id_real");
                                    $num_notas = $q_count->fetch_assoc()['count'];
                                }
                                $progreso = ($num_notas / 5) * 100;

                                echo '<div class="card glass-panel fade-in" style="padding: 16px 18px; border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; gap: 8px;">';
                                echo '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; gap: 10px;">';
                                echo '<div>';
                                echo '<span style="font-size: 0.78rem; font-weight: 700; color: var(--primary); background: rgba(99,102,241,0.1); padding: 3px 8px; border-radius: 4px; text-transform: uppercase;">' . htmlspecialchars($row['codigo']) . '</span>';
                                echo '<h4 style="margin-top: 4px; font-size: 1.16rem;">' . htmlspecialchars($row['nombre']) . '</h4>';
                                echo '<p class="text-muted" style="font-size: 0.84rem; margin:2px 0 0;">Docente: ' . htmlspecialchars($row['profesor']) . '</p>';
                                echo '</div>';
                                echo '<div style="text-align: right;">';
                                echo '<span style="font-size: 1.24rem; font-weight: 800; color: ' . ($row['promedio'] >= 3 ? '#34d399' : '#fb7185') . ';">' . number_format($row['promedio'], 1) . '</span>';
                                echo '<p style="font-size: 0.68rem; opacity: 0.55; margin-top: -2px;">Nota</p>';
                                echo '</div>';
                                echo '</div>';

                                // Trello: Progreso por materia
                                echo '<div style="margin-bottom: 8px;">';
                                echo '<div style="display: flex; justify-content: space-between; font-size: 0.72rem; margin-bottom: 4px;">';
                                echo '<span>Avance del Curso</span>';
                                echo '<span>' . round($progreso) . '%</span>';
                                echo '</div>';
                                echo '<div style="height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden;">';
                                echo '<div style="width: ' . $progreso . '%; height: 100%; background: var(--primary); transition: width 1s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 0 8px var(--primary);"></div>';
                                echo '</div>';
                                echo '</div>';

                                echo '<div style="display: flex; gap: 8px; margin-top: 10px;">';
                                echo '<a href="ver_notas.php?materia=' . urlencode($row['nombre']) . '" class="btn btn-outline" style="flex: 1; font-size: 0.86rem; padding: 8px 10px;">Ver Detalle</a>';
                                echo '<a href="pdf.php?materia=' . urlencode($row['nombre']) . '" target="_blank" class="btn btn-outline" style="width: 40px; padding: 0; display: flex; align-items: center; justify-content: center; border-color: rgba(239,68,68,0.4); color: #f87171;"><i class="fa-solid fa-file-pdf"></i></a>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="card glass-panel" style="text-align: center; padding: 32px;">';
                            echo '<p class="text-muted">No tienes materias inscritas.</p>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Barra Lateral Derecha: Acciones Rápidas (Trello: Acceso rápido) -->
                <div>
                    <h3 style="margin-bottom: 20px;"><i class="fa-solid fa-bolt" style="color: #fbbf24;"></i> Herramientas</h3>

                    <a href="generar_documento.php?tipo=estudio" target="_blank" class="card glass-panel fade-in" style="display: flex; gap: 15px; align-items: center; text-decoration: none; padding: 20px; transition: transform 0.2s; border: 1px solid rgba(251, 191, 36, 0.3);">
                        <div style="background: rgba(251, 191, 36, 0.1); width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-certificate" style="color: #fbbf24;"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 2px;">Certificado de Estudio</h4>
                            <p class="text-muted" style="font-size: 0.7rem;">Documento oficial de matrícula.</p>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="font-size: 0.8rem; opacity: 0.3;"></i>
                    </a>

                    <a href="historial.php" class="card glass-panel fade-in" style="display: flex; gap: 15px; align-items: center; text-decoration: none; padding: 20px; margin-top: 15px; transition: transform 0.2s;">
                        <div style="background: rgba(99, 102, 241, 0.1); width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-receipt" style="color: var(--primary);"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 2px;">Historial Académico</h4>
                            <p class="text-muted" style="font-size: 0.7rem;">Certificados y periodos previos.</p>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="font-size: 0.8rem; opacity: 0.3;"></i>
                    </a>

                    <a href="observaciones.php" class="card glass-panel fade-in" style="display: flex; gap: 15px; align-items: center; text-decoration: none; padding: 20px; margin-top: 15px; transition: transform 0.2s;">
                        <div style="background: rgba(6, 182, 212, 0.1); width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-comment-medical" style="color: #06b6d4;"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 2px;">Observaciones Docentes</h4>
                            <p class="text-muted" style="font-size: 0.7rem;">Feedback de tus profesores.</p>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="font-size: 0.8rem; opacity: 0.3;"></i>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        const btn = document.getElementById('side-toggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('mobile-overlay');

        const toggleMenu = () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            const icon = btn.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.classList.replace('fa-bars', 'fa-xmark');
            } else {
                icon.classList.replace('fa-xmark', 'fa-bars');
            }
        };

        btn.onclick = toggleMenu;
        overlay.onclick = toggleMenu;
    </script>
    <a href="generar_documento.php?tipo=estudio" target="_blank" class="fab-cert" title="Descargar Certificado de Estudio">
        <i class="fa-solid fa-file-pdf"></i>
    </a>
</body>

</html>

