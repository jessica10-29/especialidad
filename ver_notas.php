<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('estudiante');

if (!isset($_GET['materia'])) {
    header("Location: dashboard_estudiante.php");
    exit();
}

$nombre_materia = urldecode($_GET['materia']);
$estudiante_id = $_SESSION['usuario_id'];

// Buscar ID materia
$stmt = $conn->prepare("SELECT id, codigo, descripcion, profesor_id FROM materias WHERE nombre = ?");
$stmt->bind_param("s", $nombre_materia);
$stmt->execute();
$mat_info = $stmt->get_result()->fetch_assoc();

if (!$mat_info) {
    header("Location: dashboard_estudiante.php");
    exit();
}

$materia_id = $mat_info['id'];

// Obtener la matricula_id para la materia y el estudiante
$stmt_matricula = $conn->prepare("SELECT id FROM matriculas WHERE estudiante_id = ? AND materia_id = ?");
$stmt_matricula->bind_param("ii", $estudiante_id, $materia_id);
$stmt_matricula->execute();
$matricula_info = $stmt_matricula->get_result()->fetch_assoc();

if (!$matricula_info) {
    // Si no hay matrícula, no hay notas para mostrar
    $matricula_id = 0; // O manejar el error de otra forma
} else {
    $matricula_id = $matricula_info['id'];
}

// Obtener materias para el selector (Trello)
$sql_materias = "SELECT m.id, m.nombre FROM materias m
                 JOIN matriculas mat ON m.id = mat.materia_id
                 WHERE mat.estudiante_id = $estudiante_id";
$res_materias = $conn->query($sql_materias);

// Obtener notas actuales
$sql_notas = "SELECT * FROM notas WHERE matricula_id = $matricula_id";
$res_notas = $conn->query($sql_notas);
$notas_estudiante = [];
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
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas: <?php echo htmlspecialchars($nombre_materia); ?> - Unicali</title>
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
            <header style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
                <div>
                    <h1 class="text-gradient"><?php echo htmlspecialchars($nombre_materia); ?></h1>
                    <p class="text-muted"><?php echo htmlspecialchars($mat_info['codigo']); ?> • <?php echo htmlspecialchars($mat_info['descripcion']); ?></p>
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
            </div>

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
</body>

</html>