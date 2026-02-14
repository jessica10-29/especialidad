<?php
// admin_solicitudes.php - Gestión de Solicitudes Extraordinarias de Apertura
require_once 'conexion.php';
verificar_sesion();
verificar_rol('admin');

$mensaje = '';

// 1. Aprobar/Rechazar Solicitud
if (isset($_GET['accion']) && isset($_GET['id']) && isset($_GET['csrf'])) {

    // Verificar CSRF
    if (!verificar_csrf_token($_GET['csrf'])) {
        die("Error de seguridad: Token CSRF inválido.");
    }

    $id = (int)$_GET['id'];
    $accion = $_GET['accion']; // 'aprobar' o 'rechazar'

    if ($accion === 'aprobar') {
        // Obtener datos de la solicitud
        $q = $conn->prepare("SELECT profesor_id, materia_id FROM solicitudes_edicion WHERE id = ?");
        $q->bind_param("i", $id);
        $q->execute();
        $res = $q->get_result();
        $sol = $res->fetch_assoc();

        if ($sol) {
            $prof_id = $sol['profesor_id'];
            $mat_id = $sol['materia_id'];
            $vencimiento = date('Y-m-d H:i:s', strtotime('+48 hours')); // Apertura por 48 horas

            // Crear permiso especial
            $stmt = $conn->prepare("INSERT INTO permisos_especiales (profesor_id, materia_id, fecha_vencimiento) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $prof_id, $mat_id, $vencimiento);
            $stmt->execute();

            // Actualizar estado
            $stmt = $conn->prepare("UPDATE solicitudes_edicion SET estado = 'aprobado' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            $mensaje = '<div class="alert success">✅ Solicitud aprobada: Apertura por 48 horas habilitada.</div>';
        }
    } else {
        $stmt = $conn->prepare("UPDATE solicitudes_edicion SET estado = 'rechazado' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $mensaje = '<div class="alert" style="background: rgba(244,63,94,0.1); color: #fb7185;">❌ Solicitud rechazada.</div>';
    }
}

$solicitudes = $conn->query("SELECT s.*, u.nombre as profesor, m.nombre as materia 
                             FROM solicitudes_edicion s
                             JOIN usuarios u ON s.profesor_id = u.id
                             JOIN materias m ON s.materia_id = m.id
                             WHERE s.estado = 'pendiente' 
                             ORDER BY s.fecha_solicitud DESC");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Solicitudes de Apertura - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .success {
            background: rgba(16, 185, 129, 0.1);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .sol-card {
            margin-bottom: 15px;
            border-left: 4px solid var(--primary);
        }
    </style>
</head>

<body>
    <div class="background-mesh"></div>
    <div class="dashboard-grid">
        <aside class="sidebar">
            <div class="logo-area" style="margin-bottom: 40px; text-align: center;">
                <i class="fa-solid fa-shield-halved logo-icon" style="font-size: 2rem; color: var(--primary);"></i>
                <h3 style="color: white; margin-top: 10px;">Unicali<span style="color: var(--primary);">Admin</span></h3>
            </div>
            <nav>
                <a href="admin_periodos.php" class="nav-link">
                    <i class="fa-solid fa-calendar-days"></i> Periodos Notas
                </a>
                <a href="admin_solicitudes.php" class="nav-link active">
                    <i class="fa-solid fa-envelope-open-text"></i> Solicitudes Apertura
                </a>
                <a href="logout.php" class="nav-link" style="margin-top: auto; color: #f43f5e;">
                    <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header style="margin-bottom: 30px;">
                <h1 class="text-gradient">Bandeja de Solicitudes</h1>
                <p class="text-muted">Aprueba o rechaza peticiones de docentes para subir notas fuera de fecha</p>
            </header>

            <?php echo $mensaje; ?>

            <div style="display: grid; gap: 20px;">
                <?php if ($solicitudes->num_rows > 0): while ($s = $solicitudes->fetch_assoc()): ?>
                        <div class="card glass-panel sol-card fade-in">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <h4 style="margin-bottom: 5px; color: var(--primary);"><i class="fa-solid fa-user-tie"></i> <?php echo htmlspecialchars($s['profesor']); ?></h4>
                                    <p style="font-size: 0.9rem; margin-bottom: 10px;">Solicita apertura para <strong><?php echo htmlspecialchars($s['materia']); ?></strong></p>
                                    <div style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 8px; font-size: 0.85rem; font-style: italic;">
                                        "<?php echo htmlspecialchars($s['motivo']); ?>"
                                    </div>
                                    <small class="text-muted" style="display: block; margin-top: 10px;">Enviado el: <?php echo date('d/m/Y H:i', strtotime($s['fecha_solicitud'])); ?></small>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <a href="?accion=aprobar&id=<?php echo $s['id']; ?>&csrf=<?php echo generar_csrf_token(); ?>" class="btn btn-primary" style="padding: 10px 15px; background: #10b981;">
                                        <i class="fa-solid fa-check"></i> Aprobar
                                    </a>
                                    <a href="?accion=rechazar&id=<?php echo $s['id']; ?>&csrf=<?php echo generar_csrf_token(); ?>" class="btn btn-outline" style="padding: 10px 15px; border-color: #f43f5e; color: #fb7185;">
                                        <i class="fa-solid fa-xmark"></i> Rechazar
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile;
                else: ?>
                    <div style="text-align: center; padding: 50px; opacity: 0.5;">
                        <i class="fa-solid fa-inbox" style="font-size: 3rem;"></i>
                        <p>No hay solicitudes pendientes.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>