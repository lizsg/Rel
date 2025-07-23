<?php

?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Publicaciones | RELEE</title>
  <link rel="stylesheet" href="../../assets/css/publicaciones-styles.css">
  <link rel="stylesheet" href="../../assets/css/chat-styles.css">
  <script src="../../assets/js/chat-script.js"></script>
  <?php include '../../includes/chat-component.php'; ?>
</head>
<body>


  <div class="topbar">
    <div class="topbar-icon" title="Chat">
      <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
        <path d="M12 2c.55 0 1 .45 1 1v1h4a2 2 0 0 1 2 2v2h1a1 1 0 1 1 0 2h-1v6a3 3 0 0 1-3 3h-1v1a1 1 0 1 1-2 0v-1H9v1a1 1 0 1 1-2 0v-1H6a3 3 0 0 1-3-3v-6H2a1 1 0 1 1 0-2h1V6a2 2 0 0 1 2-2h4V3c0-.55.45-1 1-1zm-5 9a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm10 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
      </svg>
    </div>

    <div class="topbar-icon" title="Chat 2">
      <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
        <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
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

  <header>
    <div class="logo">RELEE</div>
    <div class="search-bar">
      <input type="text" placeholder="Buscar libros, autores, géneros...">
      <button>
        <svg width="18" height="18" fill="white" viewBox="0 0 24 24">
          <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
        </svg>
      </button>
    </div>
    <button class="user-button">Búsqueda Avanzada</button>
  </header>

  <main class="gallery">
    <div class="card">
      <div class="card-image">
        <svg width="60" height="60" fill="rgba(0,0,0,0.2)" viewBox="0 0 24 24">
          <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
        </svg>
      </div>
      <div class="card-content">
        <div class="card-title">Libro sin título</div>
        <div class="card-description">Autor desconocido</div>
        <div class="card-actions">
          <button class="card-button view-button">Ver</button>
          <button class="card-button edit-button">Editar</button>
          <button class="card-button delete-button">Eliminar</button>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-image">
        <svg width="60" height="60" fill="rgba(0,0,0,0.2)" viewBox="0 0 24 24">
          <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
        </svg>
      </div>
      <div class="card-content">
        <div class="card-title">Libro sin título</div>
        <div class="card-description">Autor desconocido</div>
        <div class="card-actions">
          <button class="card-button view-button">Ver</button>
          <button class="card-button edit-button">Editar</button>
          <button class="card-button delete-button">Eliminar</button>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-image">
        <svg width="60" height="60" fill="rgba(0,0,0,0.2)" viewBox="0 0 24 24">
          <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
        </svg>
      </div>
      <div class="card-content">
        <div class="card-title">Libro sin título</div>
        <div class="card-description">Autor desconocido</div>
        <div class="card-actions">
          <button class="card-button view-button">Ver</button>
          <button class="card-button edit-button">Editar</button>
          <button class="card-button delete-button">Eliminar</button>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-image">
        <svg width="60" height="60" fill="rgba(0,0,0,0.2)" viewBox="0 0 24 24">
          <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
        </svg>
      </div>
      <div class="card-content">
        <div class="card-title">Libro sin título</div>
        <div class="card-description">Autor desconocido</div>
        <div class="card-actions">
          <button class="card-button view-button">Ver</button>
          <button class="card-button edit-button">Editar</button>
          <button class="card-button delete-button">Eliminar</button>
        </div>
      </div>
    </div>
  </main>

  <div class="bottombar">
    <a href="../home.php" class="bottom-button" title="Inicio">
      <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
        <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
      </svg>
      <span>Inicio</span>
    </a>
    <a href="publicaciones.php" class="bottom-button bottom-button-wide" title="Mis Publicaciones">
      <span>Mis Publicaciones</span>
    </a>
    <button class="bottom-button" title="Menú">
      <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
        <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
      </svg>
      <span>Menú</span>
    </button>
  </div>

   <script src="../../assets/js/publicaciones-script.js"></script>
  <script src="../../assets/js/chat-script.js"></script>
</body>
</html>