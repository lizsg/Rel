<?php
  session_start();

  require_once __DIR__ . '/../../config/database.php';

  error_reporting(E_ALL);
  ini_set('display_errors', 1);

  try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
      throw new Exception("Conexión fallida: " . $conn->connect_error);
    }

    $error = "";

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
      $usuario = trim($_POST["usuario"]);
      $contrasena = trim($_POST["contrasena"]);
        
      $stmt = $conn->prepare("SELECT * FROM Usuarios WHERE userName = ? AND contraseña = ?");
      if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
      }
        
      $stmt->bind_param("ss", $usuario, $contrasena);
        
      if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
      }
        
      $result = $stmt->get_result();
        
      if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
            
        $_SESSION["usuario"] = $user['userName'];
        $_SESSION["user_id"] = $user['idUsuario'];
        header("Location: ../home.php");
        exit();
      } else {
        $error = "Usuario o contraseña incorrectos";
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
  <title>Iniciar Sesión | RELEE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../../assets/css/auth-styles.css">
</head>
<body>

  <form class="login-container" method="POST" action="">
    <h2>Iniciar Sesión</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <input type="text" name="usuario" placeholder="Usuario" required />
    <input type="password" id="pass" name="contrasena" placeholder="Contraseña" required />
    <div class="toggle-pass" onclick="togglePassword()">Mostrar / Ocultar</div>

    <button class="btn-login" type="submit">Ingresar</button>

    <!-- Botón Registrarse -->
    <a href="signUp.php" class="btn-login" style="display: block; text-align: center; margin-top: 10px;">
      Registrarse
    </a>
  </form>

  <script src="../../assets/js/auth-script.js"></script>
</body>
</html>
