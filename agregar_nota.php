<?php
include "conexion.php";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Nota</title>
</head>
<body>

<h2>Registrar Nota</h2>

<form action="guardar_nota.php" method="POST">

    <label>Estudiante inscrito:</label><br>
    <select name="matricula_id" required>
        <option value="">Seleccione</option>

        <?php
        $sql = "
        SELECT 
            m.id AS matricula_id,
            u.nombre AS estudiante,
            ma.nombre AS materia
        FROM matriculas m
        INNER JOIN usuarios u ON m.estudiante_id = u.id
        INNER JOIN materias ma ON m.materia_id = ma.id
        WHERE u.rol = 'estudiante'
        ORDER BY u.nombre
        ";

        $res = mysqli_query($conexion, $sql);

        if(mysqli_num_rows($res) == 0){
            echo "<option value=''>No hay estudiantes inscritos</option>";
        }

        while ($row = mysqli_fetch_assoc($res)) {
            echo "<option value='{$row['matricula_id']}'>
                    {$row['estudiante']} - {$row['materia']}
                  </option>";
        }
        ?>
    </select><br><br>

    <label>Corte:</label><br>
    <input type="text" name="corte" required><br><br>

    <label>Nota:</label><br>
    <input type="number" name="valor" step="0.1" min="0" max="5" required><br><br>

    <button type="submit">Guardar Nota</button>

</form>

</body>
</html>
