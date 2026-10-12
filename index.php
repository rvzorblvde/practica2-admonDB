<?php
// Carga la conexión
require __DIR__ . '/connection.php';

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$errores = [];   // Mensajes de error a mostrar
$exito   = '';   // Mensaje de éxito

$datos = ['nombre' => '', 'fecha_nacimiento' => '', 'sexo' => '', 'correo' => ''];

// Validaciones de tipos de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($datos as $campo => $vacio) {
        $datos[$campo] = trim($_POST[$campo] ?? '');
    }

    // Obligatorio y longitud maxima de 150 caracteres
    if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) > 150) {
        $errores[] = 'El nombre es obligatorio (máximo 150 caracteres).';
    }

    // Formato y fechas validas
    $fecha = DateTime::createFromFormat('Y-m-d', $datos['fecha_nacimiento']);
    if (!$fecha || $fecha->format('Y-m-d') !== $datos['fecha_nacimiento']
        || $fecha > new DateTime('today')) {
        $errores[] = 'La fecha de nacimiento no es válida.';
    }

    // Limitar los posibles valores a los definidos
    if (!in_array($datos['sexo'], ['Masculino', 'Femenino'], true)) {
        $errores[] = 'Selecciona un sexo válido.';
    }

    // Email unico y longitud de 150
    if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) || mb_strlen($datos['correo']) > 150) {
        $errores[] = 'El correo no es válido.';
    }

    // Inserción en la BD
    if (!$errores) {
        try {
            $sql = "INSERT INTO circulacion.Usuario (nombre, fecha_nacimiento, sexo, correo)
                    VALUES (:nombre, :fecha, :sexo, :correo)
                    RETURNING id_usuario";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre' => $datos['nombre'],
                ':fecha'  => $datos['fecha_nacimiento'],
                ':sexo'   => $datos['sexo'],
                ':correo' => $datos['correo'],
            ]);

            $id    = $stmt->fetchColumn();
            $exito = "Usuario registrado correctamente con ID $id.";

            $datos = array_fill_keys(array_keys($datos), ''); // Vaciar campos para próxima inserción

        } catch (PDOException $ex) {
            // Códigos de error
            if ($ex->getCode() === '23505') {
                // 23505 = violación de UNIQUE -> el correo ya existe
                $errores[] = 'Ese correo ya está registrado.';
            } elseif ($ex->getCode() === '23514') {
                // 23514 = violación de CHECK
                $errores[] = 'Algún dato no cumple las restricciones de la base de datos.';
            } else {
                $errores[] = 'Error al guardar: ' . $ex->getMessage();
            }
        }
    }
}

$usuarios = $pdo->query(
    "SELECT id_usuario, nombre, fecha_nacimiento, sexo, correo
    FROM circulacion.Usuario
    ORDER BY id_usuario DESC"
)->fetchAll();
?>

<!-- Formulario en HTML -->
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios</title>
</head>

<body>

    <h2>Registrar usuario</h2>
    <?php if ($exito): ?>
        <p><strong><?= e($exito) ?></strong></p>
    <?php endif; ?>

    <?php foreach ($errores as $err): ?>
        <p><strong>Error: <?= e($err) ?></strong></p>
    <?php endforeach; ?>

    <form method="POST" action="index.php">
        <label for="nombre">Nombre completo</label><br>
        <input type="text" id="nombre" name="nombre" maxlength="150" value="<?= e($datos['nombre']) ?>" required>
        <br><br>

        <label for="fecha_nacimiento">Fecha de nacimiento</label><br>
        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= e($datos['fecha_nacimiento']) ?>" required>
        <br><br>

        <label for="sexo">Sexo</label><br>
        <select id="sexo" name="sexo" required>
            <option value="">-- Selecciona --</option>
            <option value="Masculino" <?= $datos['sexo'] === 'Masculino' ? 'selected' : '' ?>>Masculino</option>
            <option value="Femenino"  <?= $datos['sexo'] === 'Femenino'  ? 'selected' : '' ?>>Femenino</option>
        </select>
        <br><br>

        <label for="correo">Correo electrónico</label><br>
        <input type="email" id="correo" name="correo" maxlength="150" value="<?= e($datos['correo']) ?>" required>
        <br><br>

        <button type="submit">Registrar</button>
    </form>
    <hr>
</body>
</html>