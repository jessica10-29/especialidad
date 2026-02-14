<?php
include "conexion.php";

$estudiante_id = $_POST['estudiante_id'];
$materia_id    = $_POST['materia_id'];
$periodo_id    = $_POST['periodo_id'];
$tiene_col_periodo = false;
$col_check = $conn->query("SHOW COLUMNS FROM matriculas LIKE 'periodo'");
if ($col_check && $col_check->num_rows > 0) {
    $tiene_col_periodo = true;
}

/* evitar duplicados */
$verificar = "SELECT id FROM matriculas 
              WHERE estudiante_id='$estudiante_id' 
              AND materia_id='$materia_id' 
              AND periodo_id='$periodo_id'";
$res = mysqli_query($conn, $verificar);

if (mysqli_num_rows($res) > 0) {
    echo "⚠️ El estudiante ya está inscrito.";
    exit;
}

/* obtener nombre del periodo */
$p = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT nombre FROM periodos WHERE id='$periodo_id'")
);

if ($tiene_col_periodo) {
    $sql = "INSERT INTO matriculas 
    (estudiante_id, materia_id, periodo, periodo_id)
    VALUES 
    ('$estudiante_id','$materia_id','{$p['nombre']}','$periodo_id')";
} else {
    $sql = "INSERT INTO matriculas 
    (estudiante_id, materia_id, periodo_id)
    VALUES 
    ('$estudiante_id','$materia_id','$periodo_id')";
}

if (mysqli_query($conn, $sql)) {
    echo "✅ Estudiante inscrito correctamente.";
} else {
    echo "❌ Error: " . mysqli_error($conn);
}
