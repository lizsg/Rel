<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Inicio | RELEE</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      margin: 0;
      background: linear-gradient(135deg, #f8f6f3 0%, #f0ede8 100%);
      color: #2c2016;
      position: relative;
      padding-bottom: 65px;
      min-height: 100vh;
    }

    .topbar {
      background: linear-gradient(135deg, #f5f0ea 0%, #ede6dd 100%);
      backdrop-filter: blur(10px);
      padding: 8px 25px;
      display: flex;
      justify-content: flex-end;
      align-items: center;
      gap: 15px;
      border-bottom: 1px solid rgba(211, 197, 184, 0.3);
      box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
      position: relative;
    }

    .topbar-icon {
      background: linear-gradient(135deg, #a3b18a 0%, #8fa377 100%);
      width: 35px;
      height: 35px;
      border-radius: 10px;
      color: white;
      display: flex;
      justify-content: center;
      align-items: center;
      cursor: pointer;
      font-size: 18px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 3px 10px rgba(163, 177, 138, 0.3);
      position: relative;
      overflow: hidden;
    }

    .topbar-icon::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }

    .topbar-icon:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(163, 177, 138, 0.4);
    }

    .topbar-icon:hover::before {
      left: 100%;
    }

    header {
      background: rgba(255, 253, 251, 0.9);
      backdrop-filter: blur(20px);
      padding: 25px 40px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid rgba(224, 214, 207, 0.5);
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .logo {
      font-size: 28px;
      font-weight: 800;
      background: linear-gradient(135deg, #6b4226 0%, #8b5a3c 100%);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      letter-spacing: -0.5px;
    }

    .search-bar {
      flex: 1;
      margin: 0 30px;
      display: flex;
      border: 2px solid transparent;
      border-radius: 50px;
      overflow: hidden;
      background: linear-gradient(white, white) padding-box,
                  linear-gradient(135deg, #a3b18a, #588157) border-box;
      box-shadow: 0 8px 32px rgba(163, 177, 138, 0.15);
      transition: all 0.3s ease;
    }

    .search-bar:focus-within {
      transform: translateY(-1px);
      box-shadow: 0 12px 40px rgba(163, 177, 138, 0.25);
    }

    .search-bar input {
      flex: 1;
      padding: 15px 25px;
      border: none;
      outline: none;
      font-size: 16px;
      background-color: transparent;
      color: #2c2016;
    }

    .search-bar input::placeholder {
      color: #888;
    }

    .search-bar button {
      background: linear-gradient(135deg, #588157 0%, #3a5a40 100%);
      color: white;
      padding: 0 25px;
      border: none;
      cursor: pointer;
      font-size: 18px;
      transition: all 0.3s ease;
    }

    .search-bar button:hover {
      background: linear-gradient(135deg, #3a5a40 0%, #2d4732 100%);
    }

    .user-button {
      background: linear-gradient(135deg, #6c584c 0%, #5b4a3e 100%);
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 600;
      font-size: 14px;
      letter-spacing: 0.5px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 6px 20px rgba(108, 88, 76, 0.3);
      text-transform: uppercase;
    }

    .user-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(108, 88, 76, 0.4);
    }

    .gallery {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 30px;
      padding: 50px 40px;
      max-width: 1400px;
      margin: 0 auto;
    }

    .card {
      background: rgba(255, 253, 252, 0.9);
      backdrop-filter: blur(20px);
      border-radius: 20px;
      overflow: hidden;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.2);
      position: relative;
    }

    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(163, 177, 138, 0.1) 0%, rgba(88, 129, 87, 0.1) 100%);
      opacity: 0;
      transition: opacity 0.3s ease;
      border-radius: 20px;
    }

    .card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    }

    .card:hover::before {
      opacity: 1;
    }

    .card-image {
      height: 220px;
      background: linear-gradient(135deg, #d6c1b2 0%, #c4a68a 100%);
      position: relative;
      overflow: hidden;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .card-content {
      padding: 25px;
      text-align: center;
      position: relative;
      z-index: 2;
    }

    .card-title {
      font-weight: 700;
      margin-bottom: 12px;
      font-size: 20px;
      color: #2c2016;
      line-height: 1.3;
    }

    .card-description {
      color: #6f5c4d;
      font-size: 15px;
      line-height: 1.5;
      opacity: 0.8;
      margin-bottom: 15px;
    }

    .card-actions {
      display: flex;
      justify-content: center;
      gap: 10px;
    }

    .card-button {
      padding: 8px 15px;
      border: none;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .view-button {
      background: linear-gradient(135deg, #588157 0%, #3a5a40 100%);
      color: white;
    }

    .edit-button {
      background: linear-gradient(135deg, #6c584c 0%, #5b4a3e 100%);
      color: white;
    }

    .delete-button {
      background: linear-gradient(135deg, #d4a59a 0%, #bc6c25 100%);
      color: white;
    }

    .card-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .bottombar {
      position: fixed;
      bottom: 0;
      width: 100%;
      height: 55px;
      background: linear-gradient(135deg, rgba(216, 226, 220, 0.95) 0%, rgba(196, 188, 178, 0.95) 100%);
      backdrop-filter: blur(20px);
      display: flex;
      justify-content: space-around;
      align-items: center;
      border-top: 1px solid rgba(196, 188, 178, 0.3);
      box-shadow: 0 -6px 25px rgba(0, 0, 0, 0.1);
      z-index: 1000;
    }

    .bottom-button {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      width: 45px;
      height: 45px;
      background: linear-gradient(135deg, #a3b18a 0%, #8fa377 100%);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 18px;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 4px 15px rgba(163, 177, 138, 0.3);
      position: relative;
      overflow: hidden;
      text-decoration: none;
    }

    .bottom-button::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }

    .bottom-button:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 30px rgba(163, 177, 138, 0.4);
    }

    .bottom-button:hover::before {
      left: 100%;
    }

    .bottom-button span {
      font-size: 9px;
      margin-top: 2px;
      color: rgba(255, 255, 255, 0.9);
      font-weight: 500;
      letter-spacing: 0.3px;
    }

    .bottom-button-wide {
      width: 100px;
      height: 45px;
      font-size: 11px;
      padding: 5px;
    }

    .bottom-button-wide span {
      font-size: 11px;
      margin-top: 0;
      text-align: center;
      line-height: 1.1;
    }

    .logout-button {
      background: linear-gradient(135deg, #6c584c 0%, #5b4a3e 100%);
      color: white;
      border: none;
      padding: 6px 12px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 2px 8px rgba(108, 88, 76, 0.3);
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .logout-button:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 25px rgba(108, 88, 76, 0.4);
    }

    form.logout-form {
      margin: 0;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .card {
      animation: fadeInUp 0.6s ease forwards;
    }

    .card:nth-child(2) { animation-delay: 0.1s; }
    .card:nth-child(3) { animation-delay: 0.2s; }
    .card:nth-child(4) { animation-delay: 0.3s; }

    @media (max-width: 768px) {
      header {
        flex-direction: column;
        gap: 20px;
        padding: 20px;
      }
      
      .search-bar {
        width: 100%;
        margin: 0;
      }

      .gallery {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        padding: 30px 20px;
      }

      .topbar {
        padding: 8px 20px;
        gap: 12px;
      }

      .logo {
        font-size: 24px;
      }

      .bottom-button-wide {
        width: 90px;
        font-size: 10px;
      }

      .bottom-button-wide span {
        font-size: 10px;
      }
    }

    .white-icon {
      filter: brightness(0) invert(1);
      color: white !important;
    }

    .topbar-icon, .bottom-button, .logout-button, .search-bar button {
      filter: none;
    }

    .topbar-icon {
      font-family: 'Segoe UI Symbol', sans-serif;
    }

    .bottom-button {
      font-family: 'Segoe UI Symbol', sans-serif;
    }

    .logout-button {
      font-family: 'Segoe UI Symbol', sans-serif;
    }

    .search-bar button {
      font-family: 'Segoe UI Symbol', sans-serif;
    }
  </style>
  
  <!-- Incluir estilos del chat -->
  <link rel="stylesheet" href="chat-styles.css">
</head>
<body>

 <!-- Barra superior -->
<div class="topbar">
  <!-- Botón Chat con ícono de robot  -->
  <div class="topbar-icon" title="Chat">
    <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
      <path d="M12 2c.55 0 1 .45 1 1v1h4a2 2 0 0 1 2 2v2h1a1 1 0 1 1 0 2h-1v6a3 3 0 0 1-3 3h-1v1a1 1 0 1 1-2 0v-1H9v1a1 1 0 1 1-2 0v-1H6a3 3 0 0 1-3-3v-6H2a1 1 0 1 1 0-2h1V6a2 2 0 0 1 2-2h4V3c0-.55.45-1 1-1zm-5 9a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm10 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
    </svg>
  </div>

  <!-- Botón Chat 2 con ícono original -->
  <div class="topbar-icon" title="Chat 2">
    <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
      <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
    </svg>
  </div>

  <!-- Botón Perfil -->
  <div class="topbar-icon" title="Perfil">
    <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
      <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
    </svg>
  </div>

  <!-- Botón Cerrar sesión -->
  <form action="logout.php" method="post" class="logout-form">
    <button type="submit" class="logout-button">
      <svg width="14" height="14" fill="white" viewBox="0 0 24 24">
        <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.59L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
      </svg>
      Cerrar sesión
    </button>
  </form>
</div>


  <!-- Incluir componente del chat -->
  <?php include 'chat-component.php'; ?>

  <!-- Encabezado -->
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

  <!-- Galería -->
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

 <!-- Barra inferior -->
  <div class="bottombar">
    </button>
        <a href="main.php" class="bottom-button" title="Inicio">
      <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
        <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
      </svg>
      <span>Inicio</span>
    </button>
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

</body>
</html>