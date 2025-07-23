<?php
session_start();

$usuario_valido = "lizbeth";
$contrasena_valida = "1234";
$error = "";

// Validación de formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST["usuario"]);
    $contrasena = trim($_POST["contrasena"]);

    if ($usuario === $usuario_valido && $contrasena === $contrasena_valida) {
        $_SESSION["usuario"] = $usuario;
        header("Location: main.php");
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
  <style>
    body {
      margin: 0;
      background-color: #ffffff;
      font-family: 'Segoe UI', sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .login-container {
      background-color: #fdf6f0;
      border-radius: 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
      padding: 40px;
      width: 90%;
      max-width: 400px;
    }

    h2 {
      text-align: center;
      color: #6b4226;
      margin-bottom: 25px;
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 12px;
      margin: 10px 0 20px 0;
      border: 2px solid #a3b18a;
      border-radius: 8px;
      font-size: 16px;
      background-color: #fffdfc;
      color: #4e3b2b;
    }

    .btn-login {
      width: 100%;
      background-color: #588157;
      color: white;
      border: none;
      padding: 12px;
      font-size: 16px;
      border-radius: 10px;
      cursor: pointer;
      transition: background 0.3s;
    }

    .btn-login:hover {
      background-color: #3a5a40;
    }

    .toggle-pass {
      text-align: right;
      font-size: 14px;
      margin-top: -15px;
      margin-bottom: 20px;
      color: #6b584c;
      cursor: pointer;
    }

    .error {
      background-color: #f8d7da;
      color: #842029;
      padding: 10px;
      border-radius: 6px;
      margin-bottom: 15px;
      text-align: center;
    }
  </style>
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

  <script>
    function togglePassword() {
      const pass = document.getElementById("pass");
      pass.type = pass.type === "password" ? "text" : "password";
    }
  </script>

</body>
</html>
