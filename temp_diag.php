<?php
require_once 'conexion.php';
$res = $conn->query("SELECT COUNT(*) as c FROM usuarios WHERE rol = 'estudiante'");
$count = $res->fetch_assoc()['c'];
echo "TOTAL_ESTUDIANTES: " . $count . "\n";
