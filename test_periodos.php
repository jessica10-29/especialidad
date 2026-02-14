<?php
require_once 'conexion.php';
echo "<h2>🕵️ Diagnóstico de Datos Académicos</h2>";

// 1. Verificar Periodo Actual Configurado
$res_config = $conn->query("SELECT valor FROM configuracion WHERE clave = 'periodo_actual_id'");
$p_id = ($res_config && $res_config->num_rows > 0) ? $res_config->fetch_assoc()['valor'] : 'NO DEFINIDO';
echo "<strong>1. ID del Periodo Actual en Configuracion:</strong> $p_id <br>";

// 2. Verificar detalles del periodo
if ($p_id !== 'NO DEFINIDO') {
    $res_p = $conn->query("SELECT * FROM periodos WHERE id = $p_id");
    if ($p = $res_p->fetch_assoc()) {
        echo "<strong>2. Detalles del Periodo:</strong> " . $p['nombre'] . " (Estado: " . $p['estado'] . ")<br>";
    } else {
        echo "<strong>2. ❌ ERROR:</strong> El ID $p_id no existe en la tabla 'periodos'.<br>";
    }
}

// 3. Verificar Matrículas totales vs Periodo actual
$res_total = $conn->query("SELECT COUNT(*) as c FROM matriculas");
$total = $res_total->fetch_assoc()['c'];
echo "<strong>3. Total General de Matrículas (Cualquier periodo):</strong> $total <br>";

if ($p_id !== 'NO DEFINIDO') {
    $res_periodo = $conn->query("SELECT COUNT(*) as c FROM matriculas WHERE periodo_id = $p_id");
    $total_p = $res_periodo->fetch_assoc()['c'];
    echo "<strong>4. Matrículas en el Periodo Actual ($p_id):</strong> $total_p <br>";
}

// 4. Listar periodos existentes
echo "<h3>Listado de Periodos:</h3>";
$res_list = $conn->query("SELECT * FROM periodos");
while ($row = $res_list->fetch_assoc()) {
    echo "- ID: " . $row['id'] . " | Nombre: " . $row['nombre'] . " | " . $row['estado'] . "<br>";
}

echo "<h3>Estudiantes por Periodo:</h3>";
$res_stats = $conn->query("SELECT periodo_id, COUNT(*) as c FROM matriculas GROUP BY periodo_id");
while ($s = $res_stats->fetch_assoc()) {
    echo "- Periodo ID: " . ($s['periodo_id'] ?: 'NULL') . " | Estudiantes: " . $s['c'] . "<br>";
}
