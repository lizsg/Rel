<?php
session_start();

if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$usuariosEncontrados = [];
$error = null;

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (!isset($_POST["buscador"]) || empty(trim($_POST["buscador"]))) {
            throw new Exception("No escribiste nada en el buscador");
        }

        $buscador = trim($_POST["buscador"]);
        $parametroBusqueda = "%" . $buscador . "%";

        $stmt = $conn->prepare("SELECT idUsuario, userName FROM Usuarios WHERE userName LIKE ? AND userName != ?");
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }

        $stmt->bind_param("ss", $parametroBusqueda, $_SESSION['usuario']);

        if (!$stmt->execute()) {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $usuariosEncontrados[] = $row;
            }
        } else {
            $error = "No se encontró ningún usuario con ese nombre";
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Inicio | RELEE</title>
  <link rel="stylesheet" href="../../assets/css/chatInicio-styles.css">
  <link rel="stylesheet" href="../../assets/css/chat-styles.css">
</head>
<body>

  <div class="topbar">
    <div class="topbar-icon" title="Chat">
      <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
        <path d="M12 2c.55 0 1 .45 1 1v1h4a2 2 0 0 1 2 2v2h1a1 1 0 1 1 0 2h-1v6a3 3 0 0 1-3 3h-1v1a1 1 0 1 1-2 0v-1H9v1a1 1 0 1 1-2 0v-1H6a3 3 0 0 1-3-3v-6H2a1 1 0 1 1 0-2h1V6a2 2 0 0 1 2-2h4V3c0-.55.45-1 1-1zm-5 9a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm10 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
      </svg>
    </div>

    <div class="topbar-icon" title="Perfil">
      <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
      </svg>
    </div>

    <form action="../auth/logout.php" method="post" class="logout-form">
      <button type="submit" class="logout-button">
        <svg width="14" height="14" fill="white" viewBox="0 0 24 24">
          <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.59L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
        </svg>
        Cerrar sesión
      </button>
    </form>
  </div>

  <?php include '../../includes/chat-component.php'; ?>

  <header>
    <div class="logo">RELEE</div>
    <div class="search-bar">
      <form method="POST" action="">
        <input type="text" placeholder="Buscar usuario" name="buscador">
      
        <button type ="submit">
          <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
          </svg>
        </button>
      </form>
    </div>
  </header>

  <main class="Usuarios">
  <?php if(!empty($usuariosEncontrados)): ?>
    <div class="lista-usuarios">
      <h2>Usuarios encontrados</h2>
      <ul>
        <?php foreach($usuariosEncontrados as $usuario): ?>
          <li class="usuario-item">
            <div class="usuario-info">
              <span><?php echo htmlspecialchars($usuario['userName']); ?></span>
            </div>
            <button class="enviar-mensaje" data-userid="<?php echo $usuario['idUsuario']; ?>" 
                    data-username="<?php echo htmlspecialchars($usuario['userName']); ?>">
              <svg width="16" height="16" viewBox="0 0 24 24">
                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
              </svg>
              Mensaje
            </button>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php elseif(isset($error)): ?>
    <div class="mensaje-error"><?php echo $error; ?></div>
  <?php else: ?>
    <div class="mensaje-info">
      <p>Usa el buscador para encontrar usuarios y comenzar una conversación.</p>
    </div>
  <?php endif; ?>
</main>

  <div class="bottombar">
    <a href="../home.php" class="bottom-button" title="Inicio">
      <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
        <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
      </svg>
      <span>Inicio</span>
    </a>
    <a href="../products/publicaciones.php" class="bottom-button bottom-button-wide" title="Mis Publicaciones">
      <span>Mis Publicaciones</span>
    </a>
    <button class="bottom-button" title="Menú">
      <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
        <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
      </svg>
      <span>Menú</span>
    </button>
  </div>

  <script src="../../assets/js/chatInicio-script.js"></script>
  <script src="../../assets/js/chat-script.js"></script>
</body>
</html>