<?php
session_start();

// Redireccionar si el usuario no ha iniciado sesión o no tiene un user_id
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php'; // Asegúrate de que esta ruta sea correcta

$userId = $_SESSION['user_id']; // Obtener el ID del usuario de la sesión. Ya validamos que existe.

$publicaciones = []; // Array para almacenar las publicaciones
$errorMessage = ''; // Variable para mensajes de error al usuario

try {
    // Establecer la conexión a la base de datos
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        throw new Exception("Error de conexión a la base de datos: " . $conn->connect_error);
    }

    // Consulta para obtener las publicaciones del usuario actual
    // Seleccionamos también el ID de la publicación para los botones de acción
    $stmt = $conn->prepare("SELECT id, titulo, autor, linkImagen1 FROM Publicaciones WHERE user_id = ? ORDER BY id DESC");
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $publicaciones[] = $row;
        }
    } else {
        throw new Exception("Error al obtener resultados de la consulta: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    // Manejo de errores de conexión o consulta
    error_log("Error al cargar publicaciones en publicaciones.php: " . $e->getMessage());
    $errorMessage = "No se pudieron cargar tus publicaciones en este momento. Por favor, inténtalo de nuevo más tarde.";
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mis Publicaciones | RELEE</title>
    <link rel="stylesheet" href="../../assets/css/home-styles.css"> <link rel="stylesheet" href="../../assets/css/chat-styles.css">
    
    <style>
        /* CSS adicional o sobrescrito para publicaciones.php */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8f6f3 0%, #f0ede8 100%);
            margin: 0;
            padding-bottom: 65px; /* Espacio para el bottombar */
            min-height: 100vh;
            color: #2c2016;
        }

        .new-button-container {
            display: flex;
            justify-content: center; /* Centrado horizontal */
            align-items: center;
            padding: 25px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .new-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px; /* Más espacio entre ícono y texto */
            background: linear-gradient(135deg, #6b4226 0%, #8b5a3c 100%); /* Colores café */
            color: white;
            padding: 16px 28px; /* Botón más grande */
            border: none;
            border-radius: 15px; /* Bordes más redondeados */
            font-size: 16px; /* Texto más grande */
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 6px 20px rgba(107, 66, 38, 0.3); /* Sombra café */
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
            min-width: 200px; /* Ancho mínimo para que se vea más grande */
        }

        /* Efecto de brillo */
        .new-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .new-button svg {
            fill: white;
            transition: transform 0.3s ease;
        }

        .new-button:hover {
            transform: translateY(-3px); /* Más elevación */
            box-shadow: 0 12px 35px rgba(107, 66, 38, 0.4); /* Sombra más pronunciada */
            background: linear-gradient(135deg, #8b5a3c 0%, #6b4226 100%); /* Gradiente invertido */
        }

        .new-button:hover::before {
            left: 100%;
        }

        .new-button:hover svg {
            transform: rotate(90deg); /* Rotación del ícono + */
        }

        .new-button:active {
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(107, 66, 38, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .new-button-container {
                padding: 20px;
            }
            
            .new-button {
                padding: 14px 24px;
                font-size: 14px;
                min-width: 180px;
            }
            
            .new-button svg {
                width: 20px;
                height: 20px;
            }
        }

        @media (max-width: 480px) {
            .new-button {
                padding: 12px 20px;
                font-size: 13px;
                min-width: 160px;
            }
        }

        /* Estilos para las tarjetas de publicaciones dinámicas */
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
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative; /* Para el mensaje de éxito */
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }

        .card-image {
            width: 100%;
            height: 200px; /* Altura fija para las imágenes */
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f0ede8; /* Fondo para cuando no hay imagen */
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* Asegura que la imagen cubra el espacio */
            display: block;
        }

        .card-image svg {
            width: 80px;
            height: 80px;
            fill: rgba(0,0,0,0.1);
        }

        .card-content {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .card-title {
            font-size: 1.5em;
            font-weight: 700;
            color: #3e2723;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .card-author { /* Cambiado de card-description a card-author para mayor claridad */
            font-size: 0.95em;
            color: #6d4c41;
            margin-bottom: 15px;
            flex-grow: 1;
        }

        .card-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 15px;
        }

        .card-button {
            flex-grow: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            font-size: 0.9em;
            text-decoration: none; /* Asegura que los enlaces se vean como botones */
            text-align: center;
        }

        .view-button {
            background-color: #8b5a3c;
            color: white;
        }

        .view-button:hover {
            background-color: #6b4226;
        }

        .edit-button {
            background-color: #f0ede8;
            color: #6b4226;
            border: 1px solid #6b4226;
        }

        .edit-button:hover {
            background-color: #6b4226;
            color: white;
        }

        .delete-button {
            background-color: #e57373;
            color: white;
        }

        .delete-button:hover {
            background-color: #c62828;
        }

        @media (max-width: 600px) {
            .gallery {
                grid-template-columns: 1fr;
                padding: 20px;
            }
        }

        /* Estilo para el mensaje de éxito */
        .success-message {
            background-color: #e8f5e9; /* Verde claro */
            border: 1px solid #a5d6a7; /* Verde más oscuro */
            color: #2e7d32; /* Verde texto */
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px auto;
            max-width: 1000px;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            animation: fadeInOut 5s forwards; /* Animación para desaparecer */
        }

        /* Animación para el mensaje de éxito */
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-20px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-20px); }
        }

        .error-display {
            background-color: #ffebee;
            border: 1px solid #ef9a9a;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin: 20px auto;
            max-width: 1000px;
            text-align: center;
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="topbar">
        <div class="topbar-icon" title="Chat" onclick="openChatModal()">
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

    <div class="new-button-container">
        <a href="NuevaPublicacion.php" class="new-button" title="Agregar Publicación">
            <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                <path d="M19 13H13V19H11V13H5V11H11V5H13V11H19V13Z" />
            </svg>
            <span>Nueva Publicación</span>
        </a>
    </div>

    <?php 
    // Mostrar mensaje de éxito si existe en la sesión
    if (isset($_SESSION['mensaje_exito']) && !empty($_SESSION['mensaje_exito'])) {
        echo '<div class="success-message">' . htmlspecialchars($_SESSION['mensaje_exito']) . '</div>';
        unset($_SESSION['mensaje_exito']); // Limpiar el mensaje después de mostrarlo
    }
    // Mostrar mensaje de error si existe
    if (!empty($errorMessage)) {
        echo '<div class="error-display">' . htmlspecialchars($errorMessage) . '</div>';
    }
    ?>

    <main class="gallery">
        <?php if (empty($publicaciones)): ?>
            <p style="grid-column: 1 / -1; text-align: center; color: #6d4c41; font-size: 1.2em; margin-top: 50px;">
                Aún no tienes publicaciones. ¡Anímate a crear una!
            </p>
        <?php else: ?>
            <?php foreach ($publicaciones as $publicacion): ?>
                <div class="card">
                    <div class="card-image">
                        <?php if (!empty($publicacion['linkImagen1'])): ?>
                            <img src="../../uploads/<?php echo htmlspecialchars($publicacion['linkImagen1']); ?>" alt="Portada de <?php echo htmlspecialchars($publicacion['titulo'] ?? 'Publicación'); ?>">
                        <?php else: ?>
                            <svg width="60" height="60" fill="rgba(0,0,0,0.2)" viewBox="0 0 24 24">
                                <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="card-content">
                        <div class="card-title"><?php echo htmlspecialchars($publicacion['titulo'] ?? 'Título Desconocido'); ?></div>
                        <div class="card-author"><?php echo htmlspecialchars($publicacion['autor'] ?? 'Autor Desconocido'); ?></div>
                        <div class="card-actions">
                            <a href="ver_publicacion.php?id=<?php echo htmlspecialchars($publicacion['id']); ?>" class="card-button view-button">Ver</a>
                            <a href="editar_publicacion.php?id=<?php echo htmlspecialchars($publicacion['id']); ?>" class="card-button edit-button">Editar</a>
                            <form action="eliminar_publicacion.php" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta publicación? Esta acción no se puede deshacer.');" style="display:contents;">
                                <input type="hidden" name="publicacion_id" value="<?php echo htmlspecialchars($publicacion['id']); ?>">
                                <button type="submit" class="card-button delete-button">Eliminar</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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

    <script src="../../assets/js/home-script.js"></script> <script src="../../assets/js/chat-script.js"></script>
    <script>
        // Funciones para el chat (asegúrate de que estén definidas en chat-script.js o aquí)
        function openChatModal() {
            console.log('Abriendo chat principal...');
            // Lógica para abrir el modal del chat (si existe)
        }

        // Si tienes más lógica JS para publicaciones, agrégala aquí o en publicaciones-script.js
        // Por ejemplo, manejo de clics para los botones de acción si no son enlaces directos.
    </script>
</body>
</html>