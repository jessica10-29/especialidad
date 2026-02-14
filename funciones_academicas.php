<?php
// funciones_academicas.php - Helpers para Periodos, Auditoría y Permisos

/**
 * Registra un cambio de nota en el historial de auditoría
 */
function log_cambio_nota($nota_id, $val_ant, $val_nuv, $obs_ant, $obs_nuv, $justificacion = '')
{
    global $conn;
    $profesor_id = $_SESSION['usuario_id'];

    $stmt = $conn->prepare("INSERT INTO historial_notas 
        (nota_id, valor_anterior, valor_nuevo, observacion_anterior, observacion_nueva, justificacion, profesor_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iddsssi", $nota_id, $val_ant, $val_nuv, $obs_ant, $obs_nuv, $justificacion, $profesor_id);
    return $stmt->execute();
}

/**
 * Verifica si actualmente hay un periodo de edición activo de forma global o por fechas
 */
function es_periodo_habil($materia_id = 0)
{
    global $conn;
    $ahora_fecha = date('Y-m-d');

    // 1. Obtener el periodo actual configurado
    $res_config = $conn->query("SELECT valor FROM configuracion WHERE clave = 'periodo_actual_id'");
    if (!$res_config || $res_config->num_rows == 0) return false;

    $periodo_id = (int)$res_config->fetch_assoc()['valor'];

    // 2. Verificar estado y fecha límite del periodo actual
    $res_p = $conn->query("SELECT estado, limite_notas FROM periodos WHERE id = $periodo_id");
    if ($res_p && $p = $res_p->fetch_assoc()) {
        // Si el periodo está inactivo, nadie edita (Cierre Manual)
        if ($p['estado'] == 'inactivo') return false;

        // Si hay una fecha límite y ya pasó (Cierre Automático Trello)
        if (!empty($p['limite_notas']) && $ahora_fecha > $p['limite_notas']) {
            // Solo permitimos si hay un permiso especial individual (excepción)
            if ($materia_id > 0) {
                $profesor_id = $_SESSION['usuario_id'];
                $res_esp = $conn->query("SELECT id FROM permisos_especiales 
                                         WHERE profesor_id = $profesor_id 
                                         AND materia_id = $materia_id 
                                         AND fecha_vencimiento >= '$ahora_fecha' LIMIT 1");
                if ($res_esp && $res_esp->num_rows > 0) return true;
            }
            return false;
        }
        return true;
    }

    return false;
}

/**
 * Obtiene el ID del periodo actual de forma segura, reparando la DB si es necesario (Self-Healing)
 */
function obtener_periodo_actual()
{
    global $conn;

    // 1. Intentar obtener de configuración
    $res = $conn->query("SELECT valor FROM configuracion WHERE clave = 'periodo_actual_id'");
    if ($res && $row = $res->fetch_assoc()) {
        $pid = (int)$row['valor'];
        if ($pid > 0) return $pid;
    }

    // 2. Si no hay, buscar el último periodo creado
    $res_p = $conn->query("SELECT id FROM periodos ORDER BY id DESC LIMIT 1");
    if ($res_p && $res_p->num_rows > 0) {
        $pid = (int)$res_p->fetch_assoc()['id'];
        // Reparar configuración para la próxima vez
        $conn->query("INSERT INTO configuracion (clave, valor) VALUES ('periodo_actual_id', '$pid') ON DUPLICATE KEY UPDATE valor = '$pid'");
    } else {
        // 3. Si no hay NADA, crear uno de emergencia (Self-Healing)
        $conn->query("INSERT INTO periodos (nombre, fecha_inicio, fecha_fin, estado) VALUES ('Periodo Emergencia 2024', '2024-01-01', '2024-12-31', 'activo')");
        $pid = $conn->insert_id;
        $conn->query("INSERT INTO configuracion (clave, valor) VALUES ('periodo_actual_id', '$pid') ON DUPLICATE KEY UPDATE valor = '$pid'");
    }

    // === RESCUE SYNC (Always Active) ===
    // Vincular cualquier matricula suelta al periodo actual
    $conn->query("UPDATE matriculas SET periodo_id = $pid WHERE periodo_id IS NULL OR periodo_id = 0");

    // Migración Profunda (Legacy -> New System)
    // Si hay matriculas con el nombre del periodo pero sin ID, vincularlas a sus respectivos IDs
    $conn->query("UPDATE matriculas m 
                  JOIN periodos p ON m.periodo = p.nombre 
                  SET m.periodo_id = p.id 
                  WHERE m.periodo_id IS NULL OR m.periodo_id = 0");

    return $pid;
}

/**
 * Obtiene el mensaje de estado del periodo para mostrar en la UI
 */
function obtener_estado_edicion($materia_id = 0)
{
    if (es_periodo_habil($materia_id)) {
        return '<span class="badge badge-success"><i class="fa-solid fa-lock-open"></i> Periodo Abierto</span>';
    }
    return '<span class="badge badge-danger"><i class="fa-solid fa-lock"></i> Periodo Cerrado</span>';
}
