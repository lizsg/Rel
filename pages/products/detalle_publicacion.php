<?php
    session_start();

    // Redireccionar si el usuario no ha iniciado sesión
    if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }

    require_once __DIR__ . '/../../config/database.php';

    $userId = $_SESSION['user_id']; 
    $publicacionId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $publicacion = null;
    $error = '';

    if ($publicacionId <= 0) {
        header("Location: buscador.php");
        exit();
    }

    try {
        $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Conexión fallida: " . $conn->connect_error);
        }

        // Obtener los detalles completos de la publicación
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
            l.linkImagen2,
            l.linkImagen3,
            l.linkVideo,
            l.editorial,
            l.edicion,
            l.categoria,
            l.tipoPublico,
            l.base,
            l.altura,
            l.paginas,
            l.fechaPublicacion,
            u.userName as nombreUsuario,
            u.nombre
        FROM Publicaciones p
        JOIN Libros l ON p.idLibro = l.idLibro
        JOIN Usuarios u ON p.idUsuario = u.idUsuario
        WHERE p.idPublicacion = ?
    ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $publicacionId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $publicacion = $result->fetch_assoc();
            
            // Verificar si es su propia publicación DESPUÉS de obtener los datos
            if ($publicacion['idUsuario'] == $userId) {
                $error = "No puedes ver los detalles de tus propias publicaciones aquí.";
                $publicacion = null;
            }
        } else {
            $error = "Publicación no encontrada. ID: " . $publicacionId;
            error_log("Publicación no encontrada. ID: " . $publicacionId . " | Usuario: " . $userId);
        }

        $stmt->close();
        $conn->close();

    } catch (Exception $e) {
        $error = "Error al cargar la publicación: " . $e->getMessage();
        error_log("Error en detalle_publicacion.php: " . $e->getMessage());
    }

    if (!$publicacion) {
        header("Location: buscador.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($publicacion['titulo']); ?> | RELEE</title>
    
    <link rel="stylesheet" href="../../assets/css/home-styles.css">
    <link rel="stylesheet" href="../../assets/css/chat-styles.css">
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            background: linear-gradient(135deg, #f8f6f3 0%, #f0ede8 100%);
            color: #2c2016;
            position: relative;
            padding-bottom: 65px;
            min-height: 100vh;
        }

        .detail-container {
            background: rgba(255, 253, 252, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            width: 90%;
            max-width: 1200px;
            margin: 40px auto;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
        }

        .detail-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(163, 177, 138, 0.05) 0%, rgba(88, 129, 87, 0.05) 100%);
            border-radius: 20px;
            z-index: -1;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #588157;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            color: #3a5a40;
            transform: translateX(-5px);
        }

        .book-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 30px;
        }

        .book-media {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .main-image-container {
            position: relative;
            border-radius: 15px;
            overflow: hidden;
            background: #f0ede8;
            aspect-ratio: 3/4;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .main-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-gallery {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 10px 0;
        }

        .gallery-thumb {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            object-fit: cover;
            border: 2px solid transparent;
        }

        .gallery-thumb:hover,
        .gallery-thumb.active {
            border-color: #588157;
            transform: scale(1.05);
        }

        .video-container {
            margin-top: 20px;
            border-radius: 15px;
            overflow: hidden;
        }

        .video-container video {
            width: 100%;
            border-radius: 15px;
        }

        .book-info {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .book-title {
            font-size: 2.5em;
            font-weight: 800;
            color: #6b4226;
            line-height: 1.2;
            margin-bottom: 10px;
        }

        .book-author {
            font-size: 1.3em;
            color: #8b5a3c;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .book-price {
            font-size: 2em;
            font-weight: 800;
            color: #588157;
            margin-bottom: 20px;
        }

        .seller-info {
            background: rgba(163, 177, 138, 0.1);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .seller-info h3 {
            color: #6b4226;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .seller-name {
            font-weight: 600;
            color: #2c2016;
        }

        .contact-button {
            background: linear-gradient(135deg, #588157 0%, #3a5a40 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 20px;
        }

        .contact-button:hover {
            background: linear-gradient(135deg, #3a5a40 0%, #2d4732 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(88, 129, 87, 0.4);
        }

        .book-specs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .spec-card {
            background: rgba(255, 255, 255, 0.7);
            padding: 20px;
            border-radius: 15px;
            border: 1px solid rgba(163, 177, 138, 0.2);
        }

        .spec-card h4 {
            color: #6b4226;
            margin-bottom: 10px;
            font-size: 1em;
            font-weight: 600;
        }

        .spec-card p {
            color: #2c2016;
            margin: 0;
            font-weight: 500;
        }

        .description-section {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid rgba(163, 177, 138, 0.2);
        }

        .description-section h3 {
            color: #6b4226;
            font-size: 1.5em;
            margin-bottom: 15px;
        }

        .description-text {
            color: #2c2016;
            line-height: 1.6;
            font-size: 1.1em;
        }

        .error-message {
            background-color: #ffebee;
            border: 1px solid #ef9a9a;
            color: #c62828;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin: 40px auto;
            max-width: 600px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .detail-container {
                padding: 20px;
                margin: 20px 15px;
            }

            .book-detail {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .book-title {
                font-size: 2em;
            }

            .book-specs {
                grid-template-columns: 1fr;
            }

            .image-gallery {
                justify-content: center;
            }
        }

        .placeholder-image {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0ede8;
            color: rgba(0,0,0,0.3);
        }

        /* Animaciones */
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

        .detail-container {
            animation: fadeInUp 0.6s ease forwards;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="logo">RELEE</div>
        <div class="topbar-icon" title="Chat">
            <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                <path d="M12 2c.55 0 1 .45 1 1v1h4a2 2 0 0 1 2 2v2h1a1 1 0 1 1 0 2h-1v6a3 3 0 0 1-3 3h-1v1a1 1 0 1 1-2 0v-1H9v1a1 1 0 1 1-2 0v-1H6a3 3 0 0 1-3-3v-6H2a1 1 0 1 1 0-2h1V6a2 2 0 0 1 2-2h4V3c0-.55.45-1 1-1zm-5 9a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm10 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
            </svg>
        </div>

        <div class="topbar-icon" title="Chat 2">
            <a href="../chat/chat.php" class="bottom-button" title="Chat">
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

    <?php if (!empty($error)): ?>
        <div class="error-message">
            <h3>Error</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
            <a href="buscador.php" style="color: #588157; text-decoration: none; font-weight: 600;">← Volver al buscador</a>
        </div>
    <?php else: ?>
        <main class="detail-container">
            <a href="buscador.php" class="back-button">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                </svg>
                Volver al buscador
            </a>

            <div class="book-detail">
                <div class="book-media">
                    <div class="main-image-container">
                        <?php if (!empty($publicacion['linkImagen1'])): ?>
                            <img src="../../uploads/<?php echo htmlspecialchars($publicacion['linkImagen1']); ?>" 
                                 alt="<?php echo htmlspecialchars($publicacion['titulo']); ?>" 
                                 class="main-image" id="mainImage">
                        <?php else: ?>
                            <div class="placeholder-image">
                                <svg width="100" height="100" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php 
                    $imagenes = array_filter([
                        $publicacion['linkImagen1'], 
                        $publicacion['linkImagen2'], 
                        $publicacion['linkImagen3']
                    ]);
                    if (count($imagenes) > 1): 
                    ?>
                        <div class="image-gallery">
                            <?php foreach ($imagenes as $index => $imagen): ?>
                                <img src="../../uploads/<?php echo htmlspecialchars($imagen); ?>" 
                                     alt="Imagen <?php echo $index + 1; ?>" 
                                     class="gallery-thumb <?php echo $index === 0 ? 'active' : ''; ?>"
                                     onclick="cambiarImagenPrincipal('../../uploads/<?php echo htmlspecialchars($imagen); ?>', this)">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($publicacion['linkVideo'])): ?>
                        <div class="video-container">
                            <video controls>
                                <source src="../../uploads/<?php echo htmlspecialchars($publicacion['linkVideo']); ?>" type="video/mp4">
                                Tu navegador no soporta videos HTML5.
                            </video>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="book-info">
                    <h1 class="book-title"><?php echo htmlspecialchars($publicacion['titulo']); ?></h1>
                    <p class="book-author">por <?php echo htmlspecialchars($publicacion['autor']); ?></p>
                    <div class="book-price">$<?php echo number_format($publicacion['precio'], 2); ?></div>

                    <div class="seller-info">
                        <h3>Vendido por:</h3>
                        <p class="seller-name"><?php echo htmlspecialchars($publicacion['nombreUsuario']); ?></p>
                    </div>

                    <button class="contact-button" onclick="abrirChat(<?php echo $publicacion['idUsuario']; ?>, '<?php echo htmlspecialchars($publicacion['userName']); ?>')">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" style="margin-right: 8px;">
                            <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                        </svg>
                        Contactar Vendedor
                    </button>
                </div>
            </div>

            <div class="book-specs">
                <?php if (!empty($publicacion['editorial'])): ?>
                    <div class="spec-card">
                        <h4>Editorial</h4>
                        <p><?php echo htmlspecialchars($publicacion['editorial']); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($publicacion['edicion'])): ?>
                    <div class="spec-card">
                        <h4>Edición</h4>
                        <p><?php echo htmlspecialchars($publicacion['edicion']); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($publicacion['categoria'])): ?>
                    <div class="spec-card">
                        <h4>Categoría</h4>
                        <p><?php echo htmlspecialchars($publicacion['categoria']); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($publicacion['tipoPublico'])): ?>
                    <div class="spec-card">
                        <h4>Tipo de Público</h4>
                        <p><?php echo htmlspecialchars($publicacion['tipoPublico']); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($publicacion['paginas'])): ?>
                    <div class="spec-card">
                        <h4>Número de Páginas</h4>
                        <p><?php echo htmlspecialchars($publicacion['paginas']); ?> páginas</p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($publicacion['base']) && !empty($publicacion['altura'])): ?>
                    <div class="spec-card">
                        <h4>Dimensiones</h4>
                        <p><?php echo htmlspecialchars($publicacion['base']); ?> × <?php echo htmlspecialchars($publicacion['altura']); ?> cm</p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($publicacion['fechaPublicacion'])): ?>
                    <div class="spec-card">
                        <h4>Fecha de Publicación</h4>
                        <p><?php echo date('d/m/Y', strtotime($publicacion['fechaPublicacion'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($publicacion['descripcion'])): ?>
                <div class="description-section">
                    <h3>Descripción</h3>
                    <div class="description-text">
                        <?php echo nl2br(htmlspecialchars($publicacion['descripcion'])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    <?php endif; ?>

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

    <script src="../../assets/js/home-script.js"></script>
    <script src="../../assets/js/chat-script.js"></script>
    <script>
        // Función para cambiar la imagen principal
        function cambiarImagenPrincipal(imagenSrc, thumbnail) {
            const mainImage = document.getElementById('mainImage');
            if (mainImage) {
                mainImage.src = imagenSrc;
            }

            // Remover clase active de todas las miniaturas
            document.querySelectorAll('.gallery-thumb').forEach(thumb => {
                thumb.classList.remove('active');
            });

            // Agregar clase active a la miniatura clickeada
            thumbnail.classList.add('active');
        }

        function abrirChat(userId, userName) {
            fetch('../../api/create_conversation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'other_user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '../chat/chat.php?conversacion=' + data.conversationId + '&other_user_id=' + userId + '&other_user_name=' + encodeURIComponent(userName);
                } else {
                    alert('Error al abrir el chat: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al conectar con el servidor');
            });
        }
    </script>
</body>
</html>