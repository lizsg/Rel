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
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
      background: linear-gradient(135deg, #f8f6f3 0%, #f0ede8 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow: hidden;
    }

    /* Elementos decorativos de fondo */
    body::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle at 30% 70%, rgba(163, 177, 138, 0.1) 0%, transparent 50%),
                  radial-gradient(circle at 70% 30%, rgba(107, 66, 38, 0.05) 0%, transparent 50%);
      animation: float 20s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-20px) rotate(1deg); }
    }

    .login-container {
      background: rgba(255, 253, 252, 0.95);
      backdrop-filter: blur(20px);
      border-radius: 24px;
      padding: 50px 45px;
      width: 100%;
      max-width: 440px;
      box-shadow: 
        0 20px 60px rgba(0, 0, 0, 0.1),
        0 8px 32px rgba(163, 177, 138, 0.15),
        inset 0 1px 0 rgba(255, 255, 255, 0.8);
      border: 1px solid rgba(163, 177, 138, 0.2);
      position: relative;
      animation: slideInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(40px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .login-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(163, 177, 138, 0.03) 0%, rgba(88, 129, 87, 0.03) 100%);
      border-radius: 24px;
      z-index: -1;
    }

    /* Logo container */
    .logo-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 40px;
    }

    .logo-icon {
      width: 140px;
      height: 140px;
      margin-bottom: 20px;
      position: relative;
      animation: logoFloat 3s ease-in-out infinite;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(107, 66, 38, 0.2);
    }

    @keyframes logoFloat {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-5px); }
    }

    /* Estilo para la imagen del logo */
    .logo-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      transition: transform 0.3s ease;
    }

    .logo-icon:hover .logo-image {
      transform: scale(1.05);
    }

    .login-container h2 {
      text-align: center;
      font-size: 2.2em;
      font-weight: 800;
      background: linear-gradient(135deg, #6b4226 0%, #8b5a3c 100%);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 12px;
      letter-spacing: -1px;
    }

    .subtitle {
      text-align: center;
      color: #6f5c4d;
      font-size: 0.95em;
      margin-bottom: 35px;
      opacity: 0.8;
      font-weight: 400;
    }

    .error {
      background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
      color: #c62828;
      padding: 14px 18px;
      border-radius: 12px;
      margin-bottom: 25px;
      font-size: 0.9em;
      font-weight: 500;
      border: 1px solid rgba(198, 40, 40, 0.2);
      animation: errorShake 0.5s ease-in-out;
    }

    @keyframes errorShake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-5px); }
      75% { transform: translateX(5px); }
    }

    .input-group {
      position: relative;
      margin-bottom: 25px;
    }

    .input-group input {
      width: 100%;
      padding: 18px 24px;
      border: 2px solid transparent;
      border-radius: 16px;
      font-size: 16px;
      color: #2c2016;
      background: linear-gradient(white, white) padding-box,
                  linear-gradient(135deg, #a3b18a, #588157) border-box;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      font-weight: 500;
      box-shadow: 0 4px 15px rgba(163, 177, 138, 0.1);
    }

    .input-group input:focus {
      outline: none;
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(163, 177, 138, 0.2);
      background: linear-gradient(white, white) padding-box,
                  linear-gradient(135deg, #588157, #3a5a40) border-box;
    }

    .input-group input::placeholder {
      color: #888;
      font-weight: 400;
    }

    /* Toggle password personalizado */
    .password-group {
      position: relative;
    }

    .toggle-pass {
      position: absolute;
      right: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: #6f5c4d;
      cursor: pointer;
      padding: 8px;
      border-radius: 6px;
      transition: all 0.3s ease;
      user-select: none;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .toggle-pass svg {
      transition: all 0.3s ease;
    }

    .toggle-pass:hover {
      color: #588157;
      background: rgba(163, 177, 138, 0.1);
    }

    /* Botones */
    .btn-login {
      width: 100%;
      padding: 18px;
      border: none;
      border-radius: 16px;
      font-size: 16px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
      text-decoration: none;
      display: block;
      text-align: center;
      margin-bottom: 15px;
    }

    .btn-login.primary {
      background: linear-gradient(135deg, #588157 0%, #3a5a40 100%);
      color: white;
      box-shadow: 0 8px 25px rgba(88, 129, 87, 0.3);
    }

    .btn-login.primary::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }

    .btn-login.primary:hover {
      background: linear-gradient(135deg, #3a5a40 0%, #2d4732 100%);
      transform: translateY(-3px);
      box-shadow: 0 15px 35px rgba(88, 129, 87, 0.4);
    }

    .btn-login.primary:hover::before {
      left: 100%;
    }

    .btn-login.secondary {
      background: linear-gradient(135deg, #6b4226 0%, #8b5a3c 100%);
      color: white;
      box-shadow: 0 6px 20px rgba(107, 66, 38, 0.3);
    }

    .btn-login.secondary:hover {
      background: linear-gradient(135deg, #8b5a3c 0%, #6b4226 100%);
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(107, 66, 38, 0.4);
    }

    .btn-login:active {
      transform: translateY(0);
    }

    /* Separador */
    .divider {
      text-align: center;
      margin: 30px 0 20px 0;
      position: relative;
      color: #6f5c4d;
      font-size: 0.9em;
      font-weight: 500;
    }

    .divider::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 0;
      right: 0;
      height: 1px;
      background: linear-gradient(to right, transparent, rgba(163, 177, 138, 0.3), transparent);
    }

    .divider span {
      background: rgba(255, 253, 252, 0.95);
      padding: 0 20px;
      position: relative;
    }

    /* Efectos responsive */
    @media (max-width: 480px) {
      .login-container {
        padding: 40px 30px;
        margin: 10px;
        border-radius: 20px;
      }

      .login-container h2 {
        font-size: 1.8em;
      }

      .logo-icon {
        width: 110px;
        height: 110px;
      }

      .input-group input,
      .btn-login {
        padding: 16px 20px;
        font-size: 15px;
      }
    }

    /* Micro-animaciones */
    .input-group {
      animation: fadeInLeft 0.6s ease forwards;
    }

    .input-group:nth-child(2) { animation-delay: 0.1s; }
    .input-group:nth-child(3) { animation-delay: 0.2s; }

    @keyframes fadeInLeft {
      from {
        opacity: 0;
        transform: translateX(-20px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
  </style>
</head>
<body>

  <form class="login-container" method="POST" action="">
    <div class="logo-container">
      <div class="logo-icon">
        <img src="../../assets/images/REELEE.jpeg" alt="RELEE Logo" class="logo-image" />
      </div>
      <p class="subtitle">Bienvenido</p>
    </div>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="input-group">
      <input type="text" name="usuario" placeholder="Usuario" required value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" />
    </div>

    <div class="input-group password-group">
      <input type="password" id="pass" name="contrasena" placeholder="Contraseña" required />
      <div class="toggle-pass" onclick="togglePassword()">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
        </svg>
      </div>
    </div>

    <button class="btn-login primary" type="submit">Ingresar</button>

    <div class="divider">
      <span>¿No tienes cuenta?</span>
    </div>

    <a href="signUp.php" class="btn-login secondary">
      Registrarse
    </a>
  </form>

  <script src="../../assets/js/auth-script.js"></script>
  <script>
    function togglePassword() {
      const passwordInput = document.getElementById('pass');
      const toggleBtn = document.querySelector('.toggle-pass');
      const eyeIcon = toggleBtn.querySelector('svg');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerHTML = '<path d="M9.34 16.12L12 13.41l2.66 2.71c-.8.45-1.7.68-2.66.68s-1.86-.23-2.66-.68zM12 6c3.79 0 7.17 2.13 8.82 5.5-.59 1.22-1.42 2.27-2.41 3.12l1.41 1.41c1.39-1.23 2.49-2.77 3.18-4.53C21.27 7.11 17 4 12 4c-1.27 0-2.49.2-3.64.57l1.65 1.65C10.66 6.09 11.32 6 12 6zM2.01 3.87l2.68 2.68A11.738 11.738 0 0 0 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l2.51 2.51 1.41-1.41L3.42 2.45 2.01 3.87zm7.5 7.5l2.61 2.61c-.04.01-.08.02-.12.02-1.38 0-2.5-1.12-2.5-2.5 0-.05.01-.08.01-.13z"/>';
      } else {
        passwordInput.type = 'password';
        eyeIcon.innerHTML = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
      }
    }

    // Animación de entrada de los elementos
    document.addEventListener('DOMContentLoaded', function() {
      const inputs = document.querySelectorAll('.input-group');
      inputs.forEach((input, index) => {
        input.style.animationDelay = `${index * 0.1}s`;
      });
    });

    // Efecto de ripple en los botones
    document.querySelectorAll('.btn-login').forEach(button => {
      button.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
          position: absolute;
          width: ${size}px;
          height: ${size}px;
          left: ${x}px;
          top: ${y}px;
          background: rgba(255, 255, 255, 0.3);
          border-radius: 50%;
          transform: scale(0);
          animation: ripple 0.6s ease-out;
          pointer-events: none;
        `;
        
        this.appendChild(ripple);
        
        setTimeout(() => {
          ripple.remove();
        }, 600);
      });
    });

    // CSS para el efecto ripple
    const style = document.createElement('style');
    style.textContent = `
      @keyframes ripple {
        to {
          transform: scale(2);
          opacity: 0;
        }
      }
    `;
    document.head.appendChild(style);
  </script>
</body>
</html>