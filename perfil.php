<?php
require_once 'conexion.php';
verificar_sesion();
$id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$mensaje = '';
$u = $conn->query("SELECT * FROM usuarios WHERE id = $id")->fetch_assoc();

// Self-healing: columna de código profesor si falta
$col_prof = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'codigo_profesor'");
if ($col_prof && $col_prof->num_rows === 0) {
    $conn->query("ALTER TABLE usuarios ADD COLUMN codigo_profesor VARCHAR(50) DEFAULT NULL AFTER codigo_estudiantil");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identificacion = limpiar_dato($_POST['identificacion']);
    $telefono = limpiar_dato($_POST['telefono']);
    $direccion = limpiar_dato($_POST['direccion']);
    $ciudad = limpiar_dato($_POST['ciudad']);
    $departamento = limpiar_dato($_POST['departamento']);
    $correo_inst = limpiar_dato($_POST['correo']);
    $programa = limpiar_dato($_POST['programa_academico']);
    $semestre = limpiar_dato($_POST['semestre']);
    $codigo_est = ($rol == 'estudiante') ? limpiar_dato($_POST['codigo_estudiantil']) : ($u['codigo_estudiantil'] ?? '');
    $fotoNombre = $u['foto'] ?? 'default_avatar.png';

    // Foto de perfil (opcional)
    if (isset($_FILES['foto']) && !empty($_FILES['foto']['name'])) {
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $peso_ok = $_FILES['foto']['size'] <= 2 * 1024 * 1024; // 2MB

        if (in_array($ext, $permitidas) && $peso_ok) {
            $destino = __DIR__ . '/uploads/fotos';
            if (!is_dir($destino)) {
                mkdir($destino, 0755, true);
            }
            $fotoNombre = uniqid('u_', true) . '.' . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], $destino . '/' . $fotoNombre);
        } else {
            $mensaje = '<div style="background: rgba(244, 63, 94, 0.1); color: #fb7185; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(244, 63, 114, 0.2);"><i class="fa-solid fa-circle-exclamation"></i> Imagen no válida (solo JPG/PNG/WEBP, máx 2MB).</div>';
        }
    }

    $nuevo_pass = $_POST['password'];

    // Update basic info
    $stmt = $conn->prepare("UPDATE usuarios SET identificacion = ?, telefono = ?, direccion = ?, ciudad = ?, departamento = ?, correo = ?, programa_academico = ?, semestre = ?, codigo_estudiantil = ?, foto = ? WHERE id = ?");
    $stmt->bind_param("ssssssssssi", $identificacion, $telefono, $direccion, $ciudad, $departamento, $correo_inst, $programa, $semestre, $codigo_est, $fotoNombre, $id);

    if ($stmt->execute()) {
        $mensaje = '<div style="background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(16, 185, 129, 0.2);"><i class="fa-solid fa-circle-check"></i> Perfil actualizado correctamente.</div>';
    } else {
        $mensaje = '<div style="background: rgba(244, 63, 94, 0.1); color: #fb7185; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(244, 63, 114, 0.2);"><i class="fa-solid fa-circle-exclamation"></i> Error al actualizar el perfil.</div>';
    }
    $stmt->close();

    if (!empty($nuevo_pass)) {
        $hash = password_hash($nuevo_pass, PASSWORD_BCRYPT);
        $stmt_pass = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $stmt_pass->bind_param("si", $hash, $id);
        $stmt_pass->execute();
        $stmt_pass->close();
    }
}

$u = $conn->query("SELECT * FROM usuarios WHERE id = $id")->fetch_assoc();

// Generación automática si no tiene código (para usuarios antiguos)
if ($rol == 'estudiante' && (empty($u['codigo_estudiantil']) || $u['codigo_estudiantil'] == 'N/A')) {
    $current_year = date('Y');
    $nuevo_codigo = "EST-" . $current_year . "-" . str_pad($id, 4, "0", STR_PAD_LEFT);
    $conn->query("UPDATE usuarios SET codigo_estudiantil = '$nuevo_codigo' WHERE id = $id");
    $u['codigo_estudiantil'] = $nuevo_codigo;
}

if ($rol == 'profesor' && (empty($u['codigo_profesor']) || $u['codigo_profesor'] == 'N/A')) {
    $current_year = date('Y');
    $nuevo_codigo = "PROF-" . $current_year . "-" . str_pad($id, 4, "0", STR_PAD_LEFT);
    $conn->query("UPDATE usuarios SET codigo_profesor = '$nuevo_codigo' WHERE id = $id");
    $u['codigo_profesor'] = $nuevo_codigo;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Perfil - Unicali</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
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
                <h3 style="color: white; margin-top: 10px;">Unicali<span style="color: var(--primary);"><?php echo $rol == 'profesor' ? 'Docente' : 'Estudiante'; ?></span></h3>
            </div>
            <nav>
                <a href="<?php echo $rol == 'profesor' ? 'dashboard_profesor.php' : 'dashboard_estudiante.php'; ?>" class="nav-link">
                    <i class="fa-solid fa-house"></i> Inicio
                </a>
                <?php if ($rol == 'profesor'): ?>
                    <a href="gestion_materias.php" class="nav-link">
                        <i class="fa-solid fa-book"></i> Mis Materias
                    </a>
                    <a href="gestion_notas.php" class="nav-link">
                        <i class="fa-solid fa-user-pen"></i> Gestionar Notas
                    </a>
                    <a href="asistencia.php" class="nav-link">
                        <i class="fa-solid fa-clipboard-user"></i> Asistencia
                    </a>
                    <a href="perfil.php" class="nav-link active">
                        <i class="fa-solid fa-gear"></i> Configuración
                    </a>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
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
                        <a href="perfil.php" class="nav-link active">
                            <i class="fa-solid fa-gear"></i> Mi Perfil
                        </a>
                    </div>
                <?php endif; ?>
                <a href="logout.php" class="nav-link" style="margin-top: auto; color: #f43f5e;">
                    <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header style="margin-bottom: 30px;">
                <h1 class="text-gradient">Mi Perfil</h1>
                <p class="text-muted">Gestiona tu información de seguridad y acceso</p>
            </header>

            <div style="max-width: 600px; margin: 0 auto;">
                <?php echo $mensaje; ?>

                <div class="card glass-panel fade-in">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <?php $foto_url = obtener_foto_usuario($u['foto']); ?>
                        <img src="<?php echo htmlspecialchars($foto_url); ?>" alt="avatar" style="width: 110px; height: 110px; object-fit: cover; border-radius: 999px; border: 3px solid var(--primary); margin-bottom: 12px; background: rgba(99,102,241,0.1);">
                        <h2 style="margin-bottom: 5px;"><?php echo htmlspecialchars($u['nombre']); ?></h2>
                        <span style="background: var(--primary); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;"><?php echo $rol; ?></span>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="responsive-layout-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="input-group">
                                <label class="input-label">Nombre Completo</label>
                                <input type="text" value="<?php echo htmlspecialchars($u['nombre']); ?>" class="input-field" disabled style="opacity: 0.6; cursor: not-allowed;">
                            </div>
                            <div class="input-group">
                                <label class="input-label">Email de Login</label>
                                <input type="email" value="<?php echo htmlspecialchars($u['email']); ?>" class="input-field" disabled style="opacity: 0.6; cursor: not-allowed;">
                            </div>
                        </div>

                        <div class="input-group">
                            <label class="input-label">Actualizar foto de perfil</label>
                            <input type="file" name="foto" accept="image/*" class="input-field" style="padding: 10px;">
                            <small class="text-muted">JPG/PNG/WEBP • Máx 2MB</small>
                        </div>

                        <div class="responsive-layout-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="input-group">
                                <label class="input-label">Cédula</label>
                                <input type="text" name="identificacion" value="<?php echo htmlspecialchars($u['identificacion'] ?? ''); ?>" class="input-field" placeholder="No especificado">
                            </div>
                            <div class="input-group">
                                <label class="input-label">Teléfono</label>
                                <input type="text" name="telefono" value="<?php echo htmlspecialchars($u['telefono'] ?? ''); ?>" class="input-field" placeholder="No especificado">
                            </div>
                        </div>

                        <div class="input-group">
                            <label class="input-label">Dirección de residencia</label>
                            <input type="text" name="direccion" value="<?php echo htmlspecialchars($u['direccion'] ?? ''); ?>" class="input-field" placeholder="No especificado">
                        </div>

                        <div class="responsive-layout-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="input-group">
                                <label class="input-label">Ciudad</label>
                                <input type="text" name="ciudad" value="<?php echo htmlspecialchars($u['ciudad'] ?? ''); ?>" class="input-field" placeholder="No especificado">
                            </div>
                            <div class="input-group">
                                <label class="input-label">Departamento</label>
                                <input type="text" name="departamento" value="<?php echo htmlspecialchars($u['departamento'] ?? ''); ?>" class="input-field" placeholder="No especificado">
                            </div>
                        </div>

                        <div class="input-group">
                            <label class="input-label">Correo</label>
                            <input type="email" name="correo_institucional" value="<?php echo htmlspecialchars($u['correo_institucional'] ?? ''); ?>" class="input-field" placeholder="No especificado">
                        </div>

                        <div class="input-group">
                            <label class="input-label">Programa Académico</label>
                            <input type="text" name="programa_academico" value="<?php echo htmlspecialchars($u['programa_academico'] ?? ''); ?>" class="input-field" placeholder="No especificado">
                        </div>

                        <?php if ($rol == 'estudiante'): ?>
                            <div class="responsive-layout-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="input-group">
                                    <label class="input-label">Semestre Actual</label>
                                    <select name="semestre" class="input-field" style="appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 0.65rem auto; padding-right: 2.5rem;">
                                        <option value="">-- No especificado --</option>
                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo ($u['semestre'] == $i) ? 'selected' : ''; ?>>Semestre <?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label class="input-label">Código Estudiantil (Automático)</label>
                                    <input type="text" name="codigo_estudiantil" value="<?php echo htmlspecialchars($u['codigo_estudiantil'] ?? 'Generándose...'); ?>" class="input-field" readonly style="background: rgba(0,0,0,0.05); cursor: not-allowed; border: 1px dashed var(--primary); color: var(--primary); font-weight: 700;">
                                    <p style="font-size: 0.65rem; color: var(--secondary); margin-top: 4px;">Identificador único institucional generado por el sistema.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="input-group">
                                <label class="input-label">Código Docente (Automático)</label>
                                <input type="text" value="<?php echo htmlspecialchars($u['codigo_profesor'] ?? 'Generándose...'); ?>" class="input-field" readonly style="background: rgba(0,0,0,0.05); cursor: not-allowed; border: 1px dashed var(--primary); color: var(--primary); font-weight: 700;">
                                <p style="font-size: 0.65rem; color: var(--secondary); margin-top: 4px;">Identificador único del docente generado por el sistema.</p>
                            </div>
                        <?php endif; ?>

                        <div class="input-group">
                            <label class="input-label">Cambiar Contraseña</label>
                            <div class="input-wrapper">
                                <input type="password" name="password" id="password" class="input-field" placeholder="Dejar en blanco para no cambiar">
                                <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px; justify-content: center; height: 50px; font-weight: 600;">
                            Actualizar Todo <i class="fa-solid fa-save" style="margin-left: 8px;"></i>
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

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
