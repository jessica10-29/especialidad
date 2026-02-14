<?php
// test_config.php - Diagnóstico de Sistema Unicali
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "\n=== INICIO DIAGNOSTICO ===\n";

// 1. Probar Conexión
echo "[1] Probando conexion.php... ";
if (file_exists('conexion.php')) {
    require_once 'conexion.php';
    if ($conn->ping()) {
        echo "OK (Conectado a " . $conn->host_info . ")\n";
    } else {
        echo "FALLO: " . $conn->error . "\n";
        exit;
    }
} else {
    echo "FALLO: No existe conexion.php\n";
    exit;
}

// 2. Verificar Tablas Críticas
$tablas_necesarias = ['usuarios', 'materias', 'matriculas', 'notas', 'periodos', 'configuracion'];
echo "[2] Verificando tablas...\n";
$res = $conn->query("SHOW TABLES");
$tablas_existentes = [];
while ($row = $res->fetch_array()) {
    $tablas_existentes[] = $row[0];
}

foreach ($tablas_necesarias as $t) {
    echo "    - Tabla '$t': ";
    if (in_array($t, $tablas_existentes)) {
        echo "OK\n";
    } else {
        echo "FALTA !!!\n";
    }
}

// 3. Verificar Columnas Nuevas
echo "[3] Verificando columnas nuevas en 'matriculas'...\n";
$cols = $conn->query("SHOW COLUMNS FROM matriculas LIKE 'periodo_id'");
if ($cols && $cols->num_rows > 0) {
    echo "    - Columna 'periodo_id': OK\n";
} else {
    echo "    - Columna 'periodo_id': FALTA !!!\n";
}

// 4. Verificar Periodo Actual
echo "[4] Verificando Periodo Actual (Self-Healing)...\n";
$pid = obtener_periodo_actual();
echo "    - ID Periodo Actual: $pid\n";
$res_p = $conn->query("SELECT * FROM periodos WHERE id = $pid");
if ($res_p && $row = $res_p->fetch_assoc()) {
    echo "    - Nombre: " . $row['nombre'] . "\n";
    echo "    - Estado: " . $row['estado'] . "\n";
} else {
    echo "    - ERROR: El periodo ID $pid no existe en la tabla periodos.\n";
}

// 5. Verificar Usuarios
echo "[5] Recuento de Usuarios:\n";
$res_u = $conn->query("SELECT rol, COUNT(*) as c FROM usuarios GROUP BY rol");
while ($row = $res_u->fetch_assoc()) {
    echo "    - " . ucfirst($row['rol']) . "s: " . $row['c'] . "\n";
}

echo "=== FIN DIAGNOSTICO ===\n";
