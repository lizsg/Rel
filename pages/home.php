<?php
    session_start();

    if(!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
        header("Location: auth/login.php");
        exit(); 
    }

    require_once __DIR__ . '/../config/database.php';

    $publicaciones = [];
    $errorMessage = '';

    try {
        $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Error de conexión: " . $conn->connect_error);
        }

        $query = "
            SELECT 
                p.idPublicacion,
                p.idLibro,
                p.idUsuario,
                p.precio,
                p.fechaCreacion,
                l.titulo,
                l.autor,
                l.descripcion,
                l.linkImagen1,
                u.usuario as nombreUsuario
            FROM Publicaciones p
            JOIN Libros l ON p.idLibro = l.idLibro
            JOIN Usuarios u ON p.idUsuario = u.idUsuario
            ORDER BY p.fechaCreacion DESC;
        ";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception("Error en la consulta: " . $conn->error);
        }

        while ($row = $result->fetch_assoc()) {
            $publicaciones[] = $row;
        }

        $conn->close();

    } catch (Exception $e) {
        $errorMessage = "Error al cargar publicaciones: " . $e->getMessage();
        error_log($errorMessage);
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Inicio | RELEE</title>
    <link rel="stylesheet" href="../assets/css/home-styles.css">
    <link rel="stylesheet" href="../assets/css/chat-styles.css">
    <style>
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            padding: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }
        .card-image {
            width: 100%;
            height: 200px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f0ede8;
        }
        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .card-content {
            padding: 20px;
        }
        .card-title {
            font-size: 1.5em;
            font-weight: 700;
            color: #3e2723;
            margin-bottom: 8px;
        }
        .card-author {
            font-size: 0.95em;
            color: #6d4c41;
            margin-bottom: 15px;
        }
        .card-price {
            font-weight: bold;
            color: #8b5a3c;
            margin-bottom: 10px;
        }
        .error-message {
            background-color: #ffebee;
            border: 1px solid #ef9a9a;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin: 20px auto;
            max-width: 1000px;
            text-align: center;
        }
        @media (max-width: 768px) {
            .gallery {
                grid-template-columns: 1fr;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-icon" title="Chat">
            <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                <path d="M12 2c.55 0 1 .45 1 1v1h4a2 2 0 0 1 2 2v2h1a1 1 0 1 1 0 2h-1v6a3 3 0 0 1-3 3h-1v1a1 1 0 1 1-2 0v-1H9v1a1 1 0 1 1-2 0v-1H6a3 3 0 0 1-3-3v-6H2a1 1 0 1 1 0-2h1V6a2 2 0 0 1 2-2h4V3c0-.55.45-1 1-1zm-5 9a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm10 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
            </svg>
        </div>

        <div class="topbar-icon" title="Chat 2">
            <a href="chat/chat.php" class="bottom-button" title="Chat">
                <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                </svg>
            </a>
        </div>

        <div class="topbar-icon" title="Perfil">
            <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
        </div>

        <form action="auth/logout.php" method="post" class="logout-form">
            <button type="submit" class="logout-button">
                <svg width="14" height="14" fill="white" viewBox="0 0 24 24">
                    <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.59L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                </svg>
                Cerrar sesión
            </button>
        </form>
    </div>

    <?php include '../includes/chat-component.php'; ?>

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

    <?php if (!empty($errorMessage)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <main class="gallery">
        <?php if (empty($publicaciones)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                <svg width="60" height="60" fill="#8d6e63" viewBox="0 0 24 24">
                    <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                </svg>
                <p style="color: #5f4b3a; font-size: 1.2em;">No hay publicaciones recientes</p>
            </div>
        <?php else: ?>
            <?php foreach ($publicaciones as $publicacion): ?>
                <div class="card">
                    <div class="card-image">
                        <?php if (!empty($publicacion['linkImagen1'])): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($publicacion['linkImagen1']); ?>" alt="<?php echo htmlspecialchars($publicacion['titulo']); ?>">
                        <?php else: ?>
                            <svg width="60" height="60" fill="rgba(0,0,0,0.1)" viewBox="0 0 24 24">
                                <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="card-content">
                        <div class="card-title"><?php echo htmlspecialchars($publicacion['titulo']); ?></div>
                        <div class="card-author"><?php echo htmlspecialchars($publicacion['autor']); ?></div>
                        <div class="card-price"><?php echo '$' . number_format($publicacion['precio'], 2); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <div class="bottombar">
        <a href="home.php" class="bottom-button" title="Inicio">
            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
            </svg>
            <span>Inicio</span>
        </a>
        <a href="products/publicaciones.php" class="bottom-button bottom-button-wide" title="Mis Publicaciones">
            <span>Mis Publicaciones</span>
        </a>
        <button class="bottom-button" title="Menú">
            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
            </svg>
            <span>Menú</span>
        </button>
    </div>

    <script src="../assets/js/home-script.js"></script>
    <script src="../assets/js/chat-script.js"></script>
</body>
</html>