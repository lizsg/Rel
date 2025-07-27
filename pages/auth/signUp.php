<?php
session_start();

require_once __DIR__ . '/../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$mensaje = "";

try {
  $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
  if ($conn->connect_error) {
    throw new Exception("Conexión fallida: " . $conn->connect_error);
  }

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST["nombre"]);
    $username = trim($_POST["username"]);
    $correo = trim($_POST["correo"]);
    $password = trim($_POST["password"]);
    $fechaNacimiento = $_POST["fechaNacimiento"];
    $telefono = trim($_POST["telefono"]);

    $stmt = $conn->prepare("SELECT * FROM Usuarios WHERE userName = ? OR correo = ? OR telefono = ?");
    if (!$stmt) {
      throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param("sss", $username, $correo, $telefono);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
      $mensaje = "Ya existe un usuario con ese nombre, correo o teléfono.";
    } else {
      // Aquí podrías usar password_hash si lo deseas
      $stmtInsert = $conn->prepare("INSERT INTO Usuarios (nombre, userName, correo, contraseña, fechaNacimiento, telefono) 
        VALUES (?, ?, ?, ?, ?, ?)");
      if (!$stmtInsert) {
        throw new Exception("Error al preparar el insert: " . $conn->error);
      }

      $stmtInsert->bind_param("ssssss", $nombre, $username, $correo, $password, $fechaNacimiento, $telefono);
      if ($stmtInsert->execute()) {
        $mensaje = "Registro exitoso. <a href='login.php'>Inicia sesión aquí</a>.";
      } else {
        $mensaje = "Error al registrar: " . $stmtInsert->error;
      }
      $stmtInsert->close();
    }

    $stmt->close();
  }

  $conn->close();
} catch (Exception $e) {
  die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Usuario | RELEE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../../assets/css/signUp.css">
</head>
<body>

  <form class="login-container" method="POST" action="">
    <h2>Registro de Usuario</h2>

    <?php if ($mensaje): ?>
      <div class="<?= str_contains($mensaje, 'exitoso') ? 'success' : 'error' ?>">
        <?= $mensaje ?>
      </div>
    <?php endif; ?>

    <input type="text" name="nombre" placeholder="nombre" required />
    <input type="text" name="username" placeholder="Usuario" required />
    <input type="email" name="correo" placeholder="Correo electrónico" required />
    <input type="password" id="pass" name="password" placeholder="Contraseña" required />
    <div class="toggle-pass" onclick="togglePassword()">Mostrar / Ocultar</div>
    <input type="date" name="fechaNacimiento" required />
    <input type="text" name="telefono" placeholder="Teléfono" required />

    <button class="btn-login" type="submit">Registrarse</button>
  </form>

  <script src="../../assets/js/auth-script.js"></script>
</body>
</html>
