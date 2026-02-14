<?php
require_once 'conexion.php';

$mensaje = '';

// Self-healing: agregar columna para código docente si falta
$col_prof = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'codigo_profesor'");
if ($col_prof && $col_prof->num_rows === 0) {
    $conn->query("ALTER TABLE usuarios ADD COLUMN codigo_profesor VARCHAR(50) DEFAULT NULL AFTER codigo_estudiantil");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = limpiar_dato($_POST['nombre'] ?? '');
    $email = limpiar_dato($_POST['email'] ?? '');
    $identificacion = limpiar_dato($_POST['identificacion'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $tipo_usuario = $_POST['tipo_usuario'] ?? 'estudiante';

    // Campos extra para estudiantes
    $programa = isset($_POST['programa']) ? limpiar_dato($_POST['programa']) : '';
    $semestre = isset($_POST['semestre']) ? limpiar_dato($_POST['semestre']) : '';

    $codigo_docente = isset($_POST['codigo_docente']) ? $_POST['codigo_docente'] : '';

    $rol = 'estudiante';
    $valido = true;

    if (strlen($password) < 8) {
        $mensaje = '<div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> La contraseña debe tener al menos 8 caracteres.</div>';
        $valido = false;
    }

    if ($password !== $password_confirm) {
        $mensaje = '<div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> Las contraseñas no coinciden.</div>';
        $valido = false;
    }

    // Foto de perfil (opcional)
    $fotoNombre = 'default_avatar.png';
    if ($valido && isset($_FILES['foto']) && !empty($_FILES['foto']['name'])) {
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
            $mensaje = '<div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> Formato de imagen no permitido o archivo mayor a 2MB.</div>';
            $valido = false;
        }
    }

    if ($tipo_usuario == 'profesor') {
        if ($codigo_docente === 'UNICALI_DOCENTE') {
            $rol = 'profesor';
        } else {
            $mensaje = '<div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> Código de docente incorrecto.</div>';
            $valido = false;
        }
    }

    if ($valido) {
        $stmt_check = $conn->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt_check->bind_param('s', $email);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $mensaje = '<div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> El correo ya está registrado.</div>';
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('INSERT INTO usuarios (nombre, email, identificacion, password, rol, programa_academico, semestre, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssssss', $nombre, $email, $identificacion, $password_hash, $rol, $programa, $semestre, $fotoNombre);

            if ($stmt->execute()) {
                $last_id = $stmt->insert_id;
                $current_year = date('Y');
                if ($rol == 'estudiante') {
                    $nuevo_codigo = 'EST-' . $current_year . '-' . str_pad($last_id, 4, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE usuarios SET codigo_estudiantil = '$nuevo_codigo' WHERE id = $last_id");
                    $mensaje_codigo = 'Tu código estudiantil es <strong>' . $nuevo_codigo . '</strong>.';
                } else {
                    $nuevo_codigo = 'PROF-' . $current_year . '-' . str_pad($last_id, 4, '0', STR_PAD_LEFT);
                    $conn->query("UPDATE usuarios SET codigo_profesor = '$nuevo_codigo' WHERE id = $last_id");
                    $mensaje_codigo = 'Tu código docente es <strong>' . $nuevo_codigo . '</strong>.';
                }
                $mensaje = '<div class="alert-success"><i class="fa-solid fa-circle-check"></i> Registro exitoso. ' . $mensaje_codigo . ' <a href="login.php" style="color: var(--primary); font-weight: 600;">Inicia sesión aquí</a></div>';
            } else {
                $mensaje = '<div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> Error al registrar.</div>';
            }
            $stmt->close();
        }
        $stmt_check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta - Unicali Segura</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .alert-success, .alert-error {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .alert-success { background: rgba(16,185,129,.12); color: #34d399; border-color: rgba(16,185,129,.25); }
        .alert-error { background: rgba(244,63,94,.12); color: #fb7185; border-color: rgba(244,63,94,.25); }
        .register-grid { display: grid; grid-template-columns: 1.05fr 1fr; gap: 24px; align-items: stretch; }
        @media(max-width: 960px) { .register-grid { grid-template-columns: 1fr; } }
        .hero-pane { padding: 28px; border-radius: 18px; background: linear-gradient(135deg, rgba(99,102,241,.25), rgba(14,165,233,.18)); border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 20px 60px rgba(0,0,0,0.35); position: relative; overflow: hidden; }
        .hero-pane:before { content:""; position:absolute; inset:0; background: radial-gradient(circle at 20% 20%, rgba(255,255,255,.08), transparent 35%), radial-gradient(circle at 80% 10%, rgba(255,255,255,.05), transparent 30%); pointer-events:none; }
        .pill { display:inline-flex; align-items:center; gap:8px; background: rgba(255,255,255,0.08); color:#e2e8f0; padding:8px 12px; border-radius:999px; border:1px solid rgba(255,255,255,0.08); font-size:0.8rem; }
        .section-title { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 8px; }
        .two-col { display:grid; grid-template-columns: 1fr 1fr; gap:14px; }
        @media(max-width:640px){ .two-col { grid-template-columns:1fr; } }
        .avatar-upload { display:flex; align-items:center; gap:12px; padding:12px; border:1px dashed rgba(255,255,255,0.15); border-radius:12px; background: rgba(255,255,255,0.02); }
        .avatar-upload img { width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.1); }
    </style>
    <script>
        function toggleCodigo(val) {
            const div = document.getElementById('codigo-area');
            const estFields = document.getElementById('estudiante-fields');
            const programa = document.querySelector("select[name='programa']");
            const semestre = document.querySelector("select[name='semestre']");
            const codigoDoc = document.querySelector("input[name='codigo_docente']");
            const labelCodigo = document.getElementById('label-codigo-auto');

            if (val === 'profesor') {
                div.style.display = 'block';
                estFields.style.display = 'none';
                programa.required = false; semestre.required = false; programa.value = ''; semestre.value = '';
                codigoDoc.required = true;
                labelCodigo.innerText = 'Código docente se generará automáticamente al guardar.';
            } else {
                div.style.display = 'none';
                estFields.style.display = 'block';
                programa.required = true; semestre.required = true; codigoDoc.required = false; codigoDoc.value = '';
                labelCodigo.innerText = 'Código estudiantil se generará automáticamente al guardar.';
            }
        }
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye','fa-eye-slash'); }
            else { input.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
        }
        function validarCoincidencia() {
            const pass = document.getElementById('password').value;
            const pass2 = document.getElementById('password_confirm').value;
            const badge = document.getElementById('match-hint');
            if (!pass2) { badge.textContent = ''; return; }
            if (pass === pass2) { badge.style.color = '#34d399'; badge.textContent = 'Coinciden'; }
            else { badge.style.color = '#fb7185'; badge.textContent = 'No coinciden'; }
        }
    </script>
</head>

<body>
    <div class="background-mesh"></div>
    <div class="login-container" style="padding: 30px 18px;">
        <div style="position: absolute; top: 30px; left: 30px;">
            <a href="index.php" class="btn btn-outline" style="padding: 10px 15px;">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
        </div>

        <div class="register-grid">
            <div class="hero-pane">
                <div class="pill" style="margin-bottom: 14px;"><i class="fa-solid fa-shield-halved"></i> Seguridad Unicali</div>
                <h1 style="color: #e2e8f0; margin: 0 0 10px; font-size: 2rem;">Bienvenido a tu campus digital</h1>
                <p style="color: #cbd5e1; max-width: 520px; line-height: 1.6;">Crea una cuenta para gestionar materias, asistencia y calificaciones con una experiencia moderna y protegida bajo HTTPS.</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-top: 20px;">
                    <div class="pill"><i class="fa-solid fa-lock"></i> Doble validación de contraseña</div>
                    <div class="pill"><i class="fa-solid fa-qrcode"></i> Código automático por rol</div>
                    <div class="pill"><i class="fa-solid fa-image"></i> Foto de perfil desde registro</div>
                    <div class="pill"><i class="fa-solid fa-graduation-cap"></i> Listo para notas y asistencia</div>
                </div>
            </div>

            <div class="glass-panel login-box fade-in" style="max-width: 520px; width: 100%; margin: 0 auto;">
                <div class="logo-area" style="margin-bottom: 20px; text-align:center;">
                    <i class="fa-solid fa-user-plus logo-large" style="font-size: 2.3rem;"></i>
                    <h2 style="font-size: 1.6rem; margin: 6px 0;">Crear cuenta institucional</h2>
                    <p class="text-muted" id="label-codigo-auto" style="font-size:0.9rem;">Código estudiantil se generará automáticamente al guardar.</p>
                </div>

                <?php echo $mensaje; ?>

                <form method="POST" action="" enctype="multipart/form-data" autocomplete="off">
                    <div class="two-col">
                        <div class="input-group">
                            <label class="input-label">Nombre Completo</label>
                            <input type="text" name="nombre" class="input-field" placeholder="Ej. Juan Pérez" required>
                        </div>
                        <div class="input-group">
                            <label class="input-label">Cédula / Identificación</label>
                            <input type="text" name="identificacion" class="input-field" placeholder="Ej. 1005678..." required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label class="input-label">Correo Electrónico</label>
                        <input type="email" name="email" class="input-field" placeholder="juan123@gmail.com" required>
                    </div>

                    <div class="two-col">
                        <div class="input-group">
                            <label class="input-label">Tipo de Usuario</label>
                            <select name="tipo_usuario" class="input-field" onchange="toggleCodigo(this.value)" style="appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 0.65rem auto; padding-right: 2.5rem;">
                                <option value="estudiante">Estudiante</option>
                                <option value="profesor">Profesor (requiere código)</option>
                            </select>
                        </div>
                        <div class="input-group" id="codigo-area" style="display: none;">
                            <label class="input-label">Código de acceso docente</label>
                            <input type="password" name="codigo_docente" class="input-field" placeholder="Clave de autorización">
                        </div>
                    </div>

                    <div id="estudiante-fields">
                        <div class="two-col">
                            <div class="input-group">
                                <label class="input-label">Programa Académico</label>
                                <select name="programa" class="input-field" required>
                                    <option value="">-- Seleccione su programa --</option>
                                    <option value="Ingeniería de Sistemas">Ingeniería de Sistemas</option>
                                    <option value="Ingeniería de Software">Ingeniería de Software</option>
                                    <option value="Ingeniería Industrial">Ingeniería Industrial</option>
                                    <option value="Administración de Empresas">Administración de Empresas</option>
                                    <option value="Contaduría Pública">Contaduría Pública</option>
                                    <option value="Derecho">Derecho</option>
                                    <option value="Psicología">Psicología</option>
                                    <option value="Trabajo Social">Trabajo Social</option>
                                </select>
                            </div>
                            <div class="input-group">
                                <label class="input-label">Semestre Actual</label>
                                <select name="semestre" class="input-field" style="appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 0.65rem auto; padding-right: 2.5rem;" required>
                                    <option value="">-- Seleccionar Semestre --</option>
                                    <?php for ($i = 1; $i <= 10; $i++) echo "<option value='$i'>Semestre $i</option>"; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="input-group">
                        <label class="input-label">Foto de perfil (opcional)</label>
                        <div class="avatar-upload">
                            <img src="https://ui-avatars.com/api/?name=User&background=6366f1&color=fff" alt="preview" id="avatar-preview">
                            <div style="flex:1;">
                                <input type="file" name="foto" accept="image/*" class="input-field" style="padding: 10px;" onchange="document.getElementById('avatar-preview').src = window.URL.createObjectURL(this.files[0])">
                                <small class="text-muted">JPG/PNG/WEBP · Máx 2MB</small>
                            </div>
                        </div>
                    </div>

                    <div class="two-col">
                        <div class="input-group">
                            <label class="input-label">Contraseña</label>
                            <div class="input-wrapper">
                                <input type="password" name="password" id="password" class="input-field" placeholder="Mínimo 8 caracteres" required oninput="validarCoincidencia()">
                                <button type="button" class="password-toggle" onclick="togglePassword('password', this)"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="input-group">
                            <label class="input-label">Confirmar Contraseña</label>
                            <div class="input-wrapper">
                                <input type="password" name="password_confirm" id="password_confirm" class="input-field" placeholder="Repite tu contraseña" required oninput="validarCoincidencia()">
                                <button type="button" class="password-toggle" onclick="togglePassword('password_confirm', this)"><i class="fa-solid fa-eye"></i></button>
                            </div>
                            <small id="match-hint" style="font-size: 0.78rem; margin-top: 4px; display:block;"></small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 50px; margin-top: 8px;">
                        Crear Cuenta <i class="fa-solid fa-user-check" style="margin-left: 8px;"></i>
                    </button>
                </form>

                <div style="margin: 20px 0; border-top: 1px solid var(--glass-border);"></div>

                <p style="font-size: 0.9rem; color: var(--text-muted); text-align:center;">
                    ¿Ya eres parte de Unicali? <a href="login.php" style="color: var(--primary); font-weight: 600; text-decoration: none; margin-left: 5px;">Inicia sesión</a>
                </p>
            </div>
        </div>
    </div>
</body>

</html>
