<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('estudiante');

$estudiante_id = $_SESSION['usuario_id'];

// Listado de materias inscritas para el estudiante (se usa tanto en la vista de selección como en detalle)
$materias_disponibles = $conn->query("SELECT m.id, m.nombre, m.codigo, m.descripcion 
                                      FROM materias m 
                                      JOIN matriculas mat ON m.id = mat.materia_id 
                                      WHERE mat.estudiante_id = $estudiante_id 
                                      ORDER BY m.nombre");

$modo_lista = !isset($_GET['materia']); // true cuando viene desde el menú "Mis Notas" sin seleccionar materia

$notas_estudiante = [];
$promedio_final = 0;
$nombre_materia = '';
$mat_info = null;
$matricula_id = 0;

if (!$modo_lista) {
    $nombre_materia = urldecode($_GET['materia']);

    // Buscar ID materia y validar que pertenece al estudiante
    $stmt = $conn->prepare("SELECT m.id, m.codigo, m.descripcion, m.profesor_id 
                            FROM materias m 
                            JOIN matriculas mat ON m.id = mat.materia_id 
                            WHERE m.nombre = ? AND mat.estudiante_id = ?");
    $stmt->bind_param("si", $nombre_materia, $estudiante_id);
    $stmt->execute();
    $mat_info = $stmt->get_result()->fetch_assoc();

    if ($mat_info) {
        $materia_id = $mat_info['id'];

        // Datos del profesor para mostrar foto en la vista de estudiante
        $profesor_nombre = '';
        $profesor_foto = '';
        if (!empty($mat_info['profesor_id'])) {
            $prof_id = (int)$mat_info['profesor_id'];
            $res_prof = $conn->query("SELECT nombre, foto FROM usuarios WHERE id = $prof_id");
            if ($res_prof && $prof_row = $res_prof->fetch_assoc()) {
                $profesor_nombre = $prof_row['nombre'];
                $profesor_foto = obtener_foto_usuario($prof_row['foto']);
            }
        }

        // Obtener la matricula_id para la materia y el estudiante
        $stmt_matricula = $conn->prepare("SELECT id FROM matriculas WHERE estudiante_id = ? AND materia_id = ?");
        $stmt_matricula->bind_param("ii", $estudiante_id, $materia_id);
        $stmt_matricula->execute();
        $matricula_info = $stmt_matricula->get_result()->fetch_assoc();

        $matricula_id = $matricula_info ? (int)$matricula_info['id'] : 0;

        // Obtener notas actuales
        $sql_notas = "SELECT * FROM notas WHERE matricula_id = $matricula_id";
        $res_notas = $conn->query($sql_notas);
        while ($n = $res_notas->fetch_assoc()) {
            $notas_estudiante[$n['corte']] = $n;
        }

        // Calcular promedio con pesos: 20, 20, 20, 30, 10
        $suma = 0;
        $cortes_pesos_calc = [
            'Corte 1' => 0.2,
            'Corte 2' => 0.2,
            'Corte 3' => 0.2,
            'Examen Final' => 0.3,
            'Seguimiento' => 0.1
        ];

        foreach ($cortes_pesos_calc as $corte_nombre => $peso) {
            if (isset($notas_estudiante[$corte_nombre])) {
                $suma += (float)$notas_estudiante[$corte_nombre]['valor'] * $peso;
            }
        }
        $promedio_final = number_format($suma, 1);
    } else {
        // Si la materia no pertenece al estudiante, volvemos a la lista
        $modo_lista = true;
    }
}

$page_title = $modo_lista ? "Mis Calificaciones - Unicali" : "Notas: " . htmlspecialchars($nombre_materia) . " - Unicali";
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="mobile-toggle" id="side-toggle">
        <i class="fa-solid fa-bars"></i>
    </div>
    <div class="mobile-overlay" id="mobile-overlay"></div>
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
                <a href="ver_notas.php" class="nav-link active">
                    <i class="fa-solid fa-chart-line"></i> Mis Notas
                </a>
                <a href="historial.php" class="nav-link">
                    <i class="fa-solid fa-receipt"></i> Historial Académico
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
            <?php if ($modo_lista): ?>
                <header style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <h1 class="text-gradient">Mis Calificaciones</h1>
                        <p class="text-muted">Selecciona una materia para ver tus notas detalladas.</p>
                    </div>
                </header>
                <?php if (!empty($profesor_nombre)): ?>
                <div style="display:flex; align-items:center; gap:12px; margin-bottom: 20px;">
                    <div style="width:46px; height:46px; border-radius:50%; background: url('<?php echo htmlspecialchars($profesor_foto); ?>') center/cover; border:2px solid var(--primary); box-shadow:0 0 0 3px rgba(99,102,241,0.15);"></div>
                    <div>
                        <strong><?php echo htmlspecialchars($profesor_nombre); ?></strong>
                        <p class="text-muted" style="margin:0; font-size:0.8rem;">Profesor responsable de la materia</p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="responsive-layout-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px;">
                    <?php if ($materias_disponibles && $materias_disponibles->num_rows > 0): ?>
                        <?php while ($m = $materias_disponibles->fetch_assoc()): 
                            $m_id = $m['id'];
                            $prof_nombre = 'No asignado';
                            $prof_foto = obtener_foto_usuario(null);
                            
                            // Obtener nombre y foto del profesor para este curso
                            $q_prof = $conn->query("SELECT nombre, foto FROM usuarios WHERE id = (SELECT profesor_id FROM materias WHERE id = $m_id)");
                            if ($q_prof && $p_row = $q_prof->fetch_assoc()) {
                                $prof_nombre = $p_row['nombre'];
                                $prof_foto = obtener_foto_usuario($p_row['foto']);
                            }
                        ?>
                            <div class="card glass-panel fade-in" style="padding: 20px;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                                    <div>
                                        <span style="font-size: 0.75rem; font-weight: 700; color: var(--primary); background: rgba(99,102,241,0.12); padding: 4px 10px; border-radius: 6px; text-transform: uppercase;"><?php echo htmlspecialchars($m['codigo']); ?></span>
                                        <h3 style="margin-top: 8px; margin-bottom: 6px;"><?php echo htmlspecialchars($m['nombre']); ?></h3>
                                        <p class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($m['descripcion'] ?? ''); ?></p>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: url('<?php echo htmlspecialchars($prof_foto); ?>') center/cover; border: 2px solid var(--primary); margin-left: auto; margin-bottom: 5px;" title="Docente: <?php echo htmlspecialchars($prof_nombre); ?>"></div>
                                        <small class="text-muted" style="font-size: 0.7rem; display: block; max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($prof_nombre); ?></small>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 10px; margin-top: 12px;">
                                    <a href="ver_notas.php?materia=<?php echo urlencode($m['nombre']); ?>" class="btn btn-primary" style="flex: 1; height: 42px; justify-content: center;">
                                        <i class="fa-solid fa-chart-line"></i> Ver calificaciones
                                    </a>
                                    <a href="pdf.php?materia=<?php echo urlencode($m['nombre']); ?>" target="_blank" class="btn btn-outline" style="width: 44px; height: 42px; display: flex; align-items: center; justify-content: center; border-color: rgba(239,68,68,0.3); color: #f87171;">
                                        <i class="fa-solid fa-file-pdf"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="card glass-panel" style="padding: 30px; text-align: center;">
                            <i class="fa-solid fa-folder-open" style="font-size: 2rem; opacity: 0.2; display: block; margin-bottom: 10px;"></i>
                            <p class="text-muted">Aún no tienes materias inscritas.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <header style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <h1 class="text-gradient"><?php echo htmlspecialchars($nombre_materia); ?></h1>
                        <p class="text-muted"><?php echo htmlspecialchars($mat_info['codigo'] ?? ''); ?> • <?php echo htmlspecialchars($mat_info['descripcion'] ?? ''); ?></p>
                    </div>
                    <a href="pdf.php?materia=<?php echo urlencode($nombre_materia); ?>" class="btn btn-outline" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: #f87171;">
                        <i class="fa-solid fa-file-pdf"></i> Informe PDF
                    </a>
                </header>

                <div class="stats-grid" style="margin-bottom: 30px;">
                    <div class="card glass-panel fade-in">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <p class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Promedio Definitivo</p>
                                <h2 style="font-size: 1.8rem; margin: 5px 0; color: <?php echo $promedio_final >= 3 ? '#34d399' : '#fb7185'; ?>;">
                                    <?php echo $promedio_final; ?>
                                </h2>
                                <span style="font-size: 0.75rem; color: #94a3b8;">5 Cortes Académicos</span>
                            </div>
                            <div style="background: rgba(99, 102, 241, 0.1); width: 50px; height: 50px; border-radius: 15px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-award" style="color: var(--primary); font-size: 1.2rem;"></i>
                            </div>
                        </div>
                    </div>
                    <?php 
                    $habil = es_periodo_habil($materia_id);
                    $p_res = $conn->query("SELECT limite_notas FROM periodos WHERE id = (SELECT valor FROM configuracion WHERE clave = 'periodo_actual_id')");
                    $limite_p = ($p_res && $row_p = $p_res->fetch_assoc()) ? $row_p['limite_notas'] : 'No definida';
                    ?>
                    <div class="card glass-panel fade-in" style="border-color: <?php echo $habil ? 'rgba(52, 211, 153, 0.3)' : 'rgba(244, 63, 94, 0.3)'; ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <p class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Estado del Portal</p>
                                <h2 style="font-size: 1.4rem; margin: 5px 0; color: <?php echo $habil ? '#34d399' : '#fb7185'; ?>;">
                                    <?php echo $habil ? 'Abierto' : 'Periodo Cerrado'; ?>
                                </h2>
                                <span style="font-size: 0.75rem; color: #94a3b8;">Límite: <?php echo $limite_p; ?></span>
                            </div>
                            <div style="background: <?php echo $habil ? 'rgba(52, 211, 153, 0.1)' : 'rgba(244, 63, 94, 0.1)'; ?>; width: 50px; height: 50px; border-radius: 15px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid <?php echo $habil ? 'fa-lock-open' : 'fa-lock'; ?>" style="color: <?php echo $habil ? '#34d399' : '#fb7185'; ?>; font-size: 1.2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$habil): ?>
                    <div class="card glass-panel fade-in" style="background: rgba(244, 63, 94, 0.05); border: 1px dashed #fb7185; padding: 20px; margin-bottom: 30px; text-align: center;">
                        <i class="fa-solid fa-clock-rotate-left" style="font-size: 2rem; color: #fb7185; margin-bottom: 10px;"></i>
                        <h3 style="color: #fb7185;">¡Atención! Plazo Vencido</h3>
                        <p class="text-muted">El portal de gestión de trabajos y calificaciones para este periodo ha cerrado. No se permiten nuevas entregas ni modificaciones.</p>
                    </div>
                <?php endif; ?>

                <h3 style="margin-bottom: 20px;">Detalle de Calificaciones</h3>
                <div class="card glass-panel fade-in" style="padding: 0; overflow: hidden;">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th style="padding-left: 20px;">Corte / Evaluación</th>
                                    <th>Criterio (%)</th>
                                    <th>Calificación</th>
                                    <th>Retroalimentación</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $cortes = [
                                    'Corte 1' => ['peso' => 20, 'icono' => 'fa-1', 'label' => 'Parcial 1'],
                                    'Corte 2' => ['peso' => 20, 'icono' => 'fa-2', 'label' => 'Parcial 2'],
                                    'Corte 3' => ['peso' => 20, 'icono' => 'fa-3', 'label' => 'Quices/Talleres'],
                                    'Examen Final' => ['peso' => 30, 'icono' => 'fa-file-signature', 'label' => 'Eva. Final'],
                                    'Seguimiento' => ['peso' => 10, 'icono' => 'fa-list-check', 'label' => 'Seg. Docente']
                                ];

                                if ($matricula_id > 0) : // Only show if there's a valid matricula
                                    foreach ($cortes as $nombre_corte => $info):
                                        $nota = isset($notas_estudiante[$nombre_corte]) ? $notas_estudiante[$nombre_corte] : null;
                                        $valor = $nota ? (float)$nota['valor'] : 0;

                                        // Lógica Semáforo (Trello)
                                        $color = '#fb7185'; // Rojo
                                        $texto_est = 'En Riesgo';
                                        if ($valor >= 4.0) {
                                            $color = '#34d399';
                                            $texto_est = 'Sobresaliente';
                                        } else if ($valor >= 3.0) {
                                            $color = '#fbbf24';
                                            $texto_est = 'Aprobado';
                                        }
                                ?>
                                        <tr>
                                            <td style="padding-left: 20px;">
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <i class="fa-solid <?php echo $info['icono']; ?>" style="color: var(--primary); opacity: 0.5;"></i>
                                                    <strong><?php echo $info['label']; ?></strong>
                                                </div>
                                            </td>
                                            <td align="center"><?php echo $info['peso']; ?>%</td>
                                            <td align="center">
                                                <div style="font-size: 1.1rem; font-weight: 800; color: <?php echo $color; ?>;">
                                                    <?php echo $nota ? number_format($valor, 1) : '-'; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($nota && $nota['observacion']): ?>
                                                    <div style="background: rgba(255,255,255,0.03); padding: 8px; border-radius: 6px; font-size: 0.8rem;">
                                                        <?php echo htmlspecialchars($nota['observacion']); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size: 0.75rem;">Sin comentarios.</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span style="background: <?php echo $color; ?>15; color: <?php echo $color; ?>; padding: 4px 10px; border-radius: 12px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase;">
                                                    <?php echo $nota ? $texto_est : 'Pendiente'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach;
                                else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 40px;">
                                            <i class="fa-solid fa-folder-open" style="font-size: 2rem; opacity: 0.2; display: block; margin-bottom: 10px;"></i>
                                            <p class="text-muted">No se han registrado notas para esta materia.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="margin-top: 40px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <h3>Trabajos y Actividades</h3>
                    <?php if ($habil): ?>
                        <span class="badge badge-success">Sube tus trabajos antes del cierre</span>
                    <?php endif; ?>
                </div>

                <div class="card glass-panel fade-in" style="padding: 20px; margin-bottom: 30px;">
                    <?php
                    $sql_act = "SELECT a.*, (SELECT COUNT(*) FROM entregas e WHERE e.actividad_id = a.id AND e.estudiante_id = $estudiante_id) as entregado 
                                FROM actividades a 
                                WHERE a.materia_id = $materia_id 
                                ORDER BY a.fecha_limite ASC";
                    $res_act = $conn->query($sql_act);

                    if ($res_act && $res_act->num_rows > 0): ?>
                        <div style="display: grid; gap: 15px;">
                            <?php while ($act = $res_act->fetch_assoc()): 
                                $vencida = !empty($act['fecha_limite']) && strtotime($act['fecha_limite']) < time();
                                $puedes_subir = $habil && !$vencida;
                            ?>
                                <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                                    <div style="flex: 1; min-width: 200px;">
                                        <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($act['titulo']); ?></h4>
                                        <p class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($act['descripcion']); ?></p>
                                        <div style="display: flex; gap: 10px; margin-top: 8px; font-size: 0.75rem;">
                                            <span><i class="fa-solid fa-calendar"></i> Límite: <?php echo $act['fecha_limite'] ? date('d M, Y H:i', strtotime($act['fecha_limite'])) : 'Sin plazo'; ?></span>
                                            <?php if ($act['entregado'] > 0): ?>
                                                <span style="color: #34d399; font-weight: 700;"><i class="fa-solid fa-circle-check"></i> Trabajo Entregado</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 10px;">
                                        <?php if ($act['entregado'] > 0): ?>
                                            <a href="#" class="btn btn-outline" style="font-size: 0.8rem; border-color: #34d399; color: #34d399;">
                                                <i class="fa-solid fa-eye"></i> Ver Mi Entrega
                                            </a>
                                        <?php elseif ($puedes_subir): ?>
                                            <button onclick="abrirModalSubir(<?php echo $act['id']; ?>, '<?php echo addslashes($act['titulo']); ?>')" class="btn btn-primary" style="font-size: 0.8rem;">
                                                <i class="fa-solid fa-upload"></i> Subir Trabajo
                                            </button>
                                        <?php else: ?>
                                            <span class="badge" style="background: rgba(244, 63, 94, 0.1); color: #fb7185;">No disponible</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px;">
                            <i class="fa-solid fa-folder-open" style="font-size: 2rem; opacity: 0.2; margin-bottom: 10px; display: block;"></i>
                            <p class="text-muted">No se han asignado trabajos para esta materia.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal para Subir Trabajo -->
    <div id="modal-subir" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;">
        <div class="glass-panel" style="max-width: 500px; width: 90%; padding: 30px;">
            <h3 id="modal-titulo" style="margin-bottom: 20px;">Subir Trabajo</h3>
            <form id="form-subida" action="procesar_entrega.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="actividad_id" id="act-id">
                <input type="hidden" name="materia_nombre" value="<?php echo htmlspecialchars($nombre_materia); ?>">
                
                <div class="input-group">
                    <label class="input-label">Archivo (PDF, ZIP, DOCX - Max 5MB)</label>
                    <input type="file" name="archivo" class="input-field" required style="padding: 10px;">
                </div>

                <div class="input-group">
                    <label class="input-label">Comentario (Opcional)</label>
                    <textarea name="comentario" class="input-field" rows="3" placeholder="Mensaje para el profesor..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Enviar Entrega</button>
                    <button type="button" onclick="cerrarModal()" class="btn btn-outline" style="flex: 1;">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalSubir(id, titulo) {
            document.getElementById('act-id').value = id;
            document.getElementById('modal-titulo').innerText = 'Subir: ' + titulo;
            document.getElementById('modal-subir').style.display = 'flex';
        }

        function cerrarModal() {
            document.getElementById('modal-subir').style.display = 'none';
        }
    </script>
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
</body>

</html>
