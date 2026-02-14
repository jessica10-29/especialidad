<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('profesor');

$mensaje = '';
$profesor_id = $_SESSION['usuario_id'];

function generar_codigo_materia($conn, $prefijo = 'MAT')
{
    $intentos = 0;
    do {
        $intentos++;
        $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $codigo = $prefijo . '-' . date('y') . $rand;
        $stmt = $conn->prepare("SELECT id FROM materias WHERE codigo = ?");
        $stmt->bind_param("s", $codigo);
        $stmt->execute();
        $existe = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    } while ($existe && $intentos < 10);
    return $codigo;
}

$codigo_sugerido = generar_codigo_materia($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = limpiar_dato($_POST['nombre']);
    $codigo = limpiar_dato($_POST['codigo']);
    if (empty($codigo)) {
        $codigo = generar_codigo_materia($conn);
    }
    $descripcion = limpiar_dato($_POST['descripcion']);

    // Validar código único con seguridad
    $stmt_check = $conn->prepare("SELECT id FROM materias WHERE codigo = ?");
    $stmt_check->bind_param("s", $codigo);
    $stmt_check->execute();
    $check = $stmt_check->get_result();

    if ($check && $check->num_rows > 0) {
        $mensaje = '<div class="alert alert-danger">El código de materia ya existe.</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO materias (nombre, codigo, profesor_id, descripcion) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $nombre, $codigo, $profesor_id, $descripcion);

        if ($stmt->execute()) {
            $mensaje = '<div class="alert alert-success">Materia creada exitosamente.</div>';
        } else {
            $mensaje = '<div class="alert alert-danger">Error al crear la materia: ' . $conn->error . '</div>';
        }
        $stmt->close();
    }
    $stmt_check->close();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Crear Materia</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .alert {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }
    </style>
</head>

<body>
    <div class="dashboard-grid">
        <aside class="sidebar">
            <!-- Reuse sidebar logic or include -->
            <div class="logo-area" style="text-align: center; margin-bottom: 30px;">
                <i class="fa-solid fa-graduation-cap logo-icon" style="font-size: 2rem;"></i>
                <h3>UnicaliDocente</h3>
            </div>
            <nav>
                <a href="dashboard_profesor.php" class="nav-link"><i class="fa-solid fa-house"></i> Inicio</a>
                <a href="crear_materia.php" class="nav-link active"><i class="fa-solid fa-plus-circle"></i> Nueva Materia</a>
                <a href="gestion_notas.php" class="nav-link"><i class="fa-solid fa-user-pen"></i> Gestionar Notas</a>
            </nav>
        </aside>

        <main class="main-content">
            <div style="margin-bottom: 20px;">
                <a href="dashboard_profesor.php" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i> Volver al Inicio
                </a>
            </div>
            <h1 style="margin-bottom: 20px;">Crear Nueva Materia</h1>

            <div class="card glass-panel" style="max-width: 600px;">
                <?php echo $mensaje; ?>
                <form method="POST">
                    <div class="input-group">
                        <label class="input-label">Nombre de la Materia</label>
                        <input type="text" name="nombre" class="input-field" placeholder="Ej: Matemáticas Avanzadas" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="input-group">
                            <label class="input-label">Código (autogenerado, editable)</label>
                            <input type="text" name="codigo" class="input-field" placeholder="MAT-101" value="<?php echo htmlspecialchars($codigo_sugerido); ?>">
                            <small class="text-muted">Déjalo vacío para generar uno único.</small>
                        </div>
                    </div>

                    <div class="input-group">
                        <label class="input-label">Descripción (Opcional)</label>
                        <textarea name="descripcion" class="input-field" rows="3"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save"></i> Guardar Materia
                    </button>
                    <a href="dashboard_profesor.php" class="btn btn-outline">Cancelar</a>
                </form>
            </div>
        </main>
    </div>
</body>

</html>
