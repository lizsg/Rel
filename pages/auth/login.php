<?php
  session_start();

  $usuario_valido = "lizbeth";
  $contrasena_valida = "1234";
  $error = "";

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $contrasena = trim($_POST["contrasena"]);

    if ($usuario === $usuario_valido && $contrasena === $contrasena_valida) {
      $_SESSION["usuario"] = $usuario;
      header("Location: ../../pages/home.php");
      exit();
    } else {
      $error = "Usuario o contraseña incorrectos.";
    }
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
  </form>

  <script src="../../assets/js/auth-script.js"></script>
</body>
</html>