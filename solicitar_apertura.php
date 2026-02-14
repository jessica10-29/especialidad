<?php
// solicitar_apertura.php - Formulario para que el docente pida permiso de edición
require_once 'conexion.php';
verificar_sesion();
verificar_rol('profesor');

if (!isset($_GET['materia'])) {
    header("Location: gestion_notas.php");
    exit();
}

$materia_id = (int)$_GET['materia'];
$profesor_id = $_SESSION['usuario_id'];

// Obtener datos de la materia
$stmt = $conn->prepare("SELECT nombre FROM materias WHERE id = ? AND profesor_id = ?");
$stmt->bind_param("ii", $materia_id, $profesor_id);
$stmt->execute();
$mat = $stmt->get_result()->fetch_assoc();

if (!$mat) {
    header("Location: gestion_notas.php");
    exit();
}

$mensaje = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $motivo = limpiar_dato($_POST['motivo']);

    // Verificar si ya hay una solicitud pendiente
    $check = $conn->query("SELECT id FROM solicitudes_edicion WHERE profesor_id = $profesor_id AND materia_id = $materia_id AND estado = 'pendiente'");

    if ($check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO solicitudes_edicion (profesor_id, materia_id, motivo) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $profesor_id, $materia_id, $motivo);
        if ($stmt->execute()) {
            $mensaje = '<div class="alert success">✅ Solicitud enviada correctamente. El administrador la revisará pronto.</div>';
        }
    } else {
        $mensaje = '<div class="alert" style="background: rgba(245, 158, 11, 0.1); color: #fbbf24;">⚠️ Ya tienes una solicitud pendiente para esta materia.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Solicitar Apertura Extraordinaria - Unicali</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="background-mesh"></div>
    <div class="dashboard-grid">
        <aside class="sidebar">
            <div class="logo-area" style="margin-bottom: 40px; text-align: center;">
                <i class="fa-solid fa-graduation-cap logo-icon" style="font-size: 2rem; color: var(--primary);"></i>
                <h3 style="color: white; margin-top: 10px;">Unicali<span style="color: var(--primary);">Docente</span></h3>
            </div>
            <nav>
                <a href="dashboard_profesor.php" class="nav-link">
                    <i class="fa-solid fa-house"></i> Inicio
                </a>
                <a href="gestion_notas.php" class="nav-link active">
                    <i class="fa-solid fa-user-pen"></i> Gestionar Notas
                </a>
                <a href="logout.php" class="nav-link" style="margin-top: auto; color: #f43f5e;">
                    <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header style="margin-bottom: 30px;">
                <h1 class="text-gradient">Solicitud de Apertura</h1>
                <p class="text-muted">Materia: <?php echo htmlspecialchars($mat['nombre']); ?></p>
            </header>

            <?php echo $mensaje; ?>

            <div class="card glass-panel fade-in" style="max-width: 600px; margin: 0 auto;">
                <p style="margin-bottom: 20px;">El periodo de edición de notas ha finalizado. Por favor, especifica el motivo por el cual necesitas realizar cambios extemporáneos.</p>
                <form method="POST">
                    <div class="input-group">
                        <label class="input-label">Motivo de la Solicitud</label>
                        <textarea name="motivo" class="input-field" rows="5" placeholder="Escribe aquí tu justificación..." required></textarea>
                    </div>
                    <div style="display: flex; gap: 15px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            Enviar Solicitud <i class="fa-solid fa-paper-plane" style="margin-left: 8px;"></i>
                        </button>
                        <a href="gestion_notas.php" class="btn btn-outline" style="flex: 1; text-align: center;">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>

</html>