<?php
  session_start();

  if(!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
      header("Location: ../auth/login.php");
      exit(); 
  }

  
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Inicio | RELEE</title>
  <link rel="stylesheet" href="../../assets/css/chatUsuarios-styles.css">
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
  </header>

  <main class="chat">
    
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

  <script src="../../assets/js/chatUsuarios-script.js"></script>
  <script src="../../assets/js/chat-script.js"></script>
</body>
</html>