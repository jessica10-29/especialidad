<?php
// verificar.php - Validación Pública de Documentos
require_once 'conexion.php';

// No requerimos sesión iniciada ya que es una página de validación pública para terceros.

$folio = isset($_GET['folio']) ? $_GET['folio'] : '';
$valido = false;
$usuario = null;

if (!empty($folio) && strpos($folio, 'UC-') === 0) {
    $id_str = substr($folio, 3);
    $id = (int)$id_str;

    if ($id > 0) {
        $stmt = $conn->prepare("SELECT nombre, created_at, rol FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $usuario = $res->fetch_assoc();
            if ($usuario['rol'] === 'estudiante') {
                $valido = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Certificado | Unicali</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --primary: #6366f1;
            --success: #10b981;
            --danger: #f43f5e;
            --bg: #0f172a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .background-mesh {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 10% 10%, rgba(6, 182, 212, 0.1) 0%, transparent 40%);
            z-index: -1;
        }

        .verify-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 35px;
        }

        .icon-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.2);
        }

        .icon-danger {
            background: rgba(244, 63, 94, 0.1);
            color: var(--danger);
            box-shadow: 0 0 30px rgba(244, 63, 94, 0.2);
        }

        h1 {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.5);
            font-size: 14px;
            margin-bottom: 30px;
        }

        .info-box {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            padding: 20px;
            text-align: left;
            margin-bottom: 30px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 10px;
        }

        .info-row:last-child {
            margin-bottom: 0;
            border: none;
            padding-bottom: 0;
        }

        .label {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            font-weight: 700;
        }

        .value {
            font-size: 14px;
            font-weight: 600;
        }

        .btn-home {
            display: inline-block;
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-home:hover {
            background: #4f46e5;
            transform: translateY(-2px);
        }

        .footer {
            margin-top: 30px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>

<body>
    <div class="background-mesh"></div>

    <div class="verify-card">
        <?php if ($valido): ?>
            <div class="status-icon icon-success">
                <i class="fa-solid fa-check-double"></i>
            </div>
            <h1>Documento Válido</h1>
            <p class="subtitle">Este certificado ha sido emitido y verificado por Unicali Segura.</p>

            <div class="info-box">
                <div class="info-row">
                    <span class="label">Estudiante</span>
                    <span class="value"><?php echo htmlspecialchars($usuario['nombre']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Folio</span>
                    <span class="value"><?php echo htmlspecialchars($folio); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Estado Académico</span>
                    <span class="value" style="color: var(--success);">Inscrito / Activo</span>
                </div>
                <div class="info-row">
                    <span class="label">Fecha Emisión</span>
                    <span class="value"><?php echo date('d M, Y'); ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="status-icon icon-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h1>Documento Inválido</h1>
            <p class="subtitle">No hemos podido encontrar un registro oficial para este folio.</p>

            <div style="padding: 20px; color: var(--danger); font-size: 13px; font-weight: 600; margin-bottom: 20px;">
                Por favor, verifique el código QR o contacte con la institución.
            </div>
        <?php endif; ?>

        <a href="index.php" class="btn-home">Ir al Inicio</a>

        <div class="footer">
            &copy; <?php echo date('Y'); ?> Unicali Segura • Sistema de Validación Digital
        </div>
    </div>
</body>

</html>