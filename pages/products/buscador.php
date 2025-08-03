<?php
    session_start();

    // Redireccionar si el usuario no ha iniciado sesión o no tiene un user_id
    if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }

    require_once __DIR__ . '/../../config/database.php';

    $userId = $_SESSION['user_id']; 

    $errores = [];
    $mensaje_exito = '';
    $resultados = [];
    $hashtags = [];

    // Obtener valores del formulario
    $titulo = htmlspecialchars($_POST['titulo'] ?? '');
    $autor = htmlspecialchars($_POST['autor'] ?? '');
    $descripcion = htmlspecialchars($_POST['descripcion'] ?? '');
    $precioMin = htmlspecialchars($_POST['precioMin'] ?? '');
    $precioMax = htmlspecialchars($_POST['precioMax'] ?? '');
    $etiquetas = htmlspecialchars($_POST['etiquetas'] ?? '');
    $editorial = htmlspecialchars($_POST['editorial'] ?? '');
    $edicion = htmlspecialchars($_POST['edicion'] ?? '');
    $categoria = htmlspecialchars($_POST['categoria'] ?? '');
    $tipoPublico = htmlspecialchars($_POST['tipoPublico'] ?? '');
    $baseMin = htmlspecialchars($_POST['baseMin'] ?? '');
    $baseMax = htmlspecialchars($_POST['baseMax'] ?? '');
    $alturaMin = htmlspecialchars($_POST['alturaMin'] ?? '');
    $alturaMax = htmlspecialchars($_POST['alturaMax'] ?? '');
    $paginasMin = htmlspecialchars($_POST['paginasMin'] ?? '');
    $paginasMax = htmlspecialchars($_POST['paginasMax'] ?? '');

    try {
        $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset("utf8mb4");
        
        if ($conn->connect_error) {
            throw new Exception("Conexión fallida: " . $conn->connect_error);
        }

        // Procesar el formulario solo si se ha enviado por POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Construir la consulta SQL dinámicamente
            $sql = "SELECT 
                        p.idPublicacion,
                        p.idLibro,
                        p.precio,
                        p.fechaCreacion,
                        l.titulo,
                        l.autor,
                        l.descripcion,
                        l.editorial,
                        l.edicion,
                        l.categoria,
                        l.tipoPublico,
                        l.base,
                        l.altura,
                        l.paginas,
                        l.linkVideo,
                        l.linkImagen1,
                        l.linkImagen2,
                        l.linkImagen3,
                        l.fechaPublicacion,
                        u.username as nombreUsuario
                    FROM Publicaciones p
                    JOIN Libros l ON p.idLibro = l.idLibro
                    LEFT JOIN Usuarios u ON p.idUsuario = u.idUsuario
                    WHERE 1=1";

            $params = [];
            $types = "";

            // Agregar filtros dinámicamente
            if (!empty(trim($titulo))) {
                $sql .= " AND l.titulo LIKE ?";
                $params[] = "%" . trim($titulo) . "%";
                $types .= "s";
            }

            if (!empty(trim($autor))) {
                $sql .= " AND l.autor LIKE ?";
                $params[] = "%" . trim($autor) . "%";
                $types .= "s";
            }

            if (!empty(trim($descripcion))) {
                $sql .= " AND l.descripcion LIKE ?";
                $params[] = "%" . trim($descripcion) . "%";
                $types .= "s";
            }

            if (!empty($precioMin)) {
                $sql .= " AND p.precio >= ?";
                $params[] = floatval($precioMin);
                $types .= "d";
            }

            if (!empty($precioMax)) {
                $sql .= " AND p.precio <= ?";
                $params[] = floatval($precioMax);
                $types .= "d";
            }

            if (!empty(trim($editorial))) {
                $sql .= " AND l.editorial LIKE ?";
                $params[] = "%" . trim($editorial) . "%";
                $types .= "s";
            }

            if (!empty(trim($edicion))) {
                $sql .= " AND l.edicion LIKE ?";
                $params[] = "%" . trim($edicion) . "%";
                $types .= "s";
            }

            if (!empty(trim($categoria))) {
                $sql .= " AND l.categoria LIKE ?";
                $params[] = "%" . trim($categoria) . "%";
                $types .= "s";
            }

            if (!empty($tipoPublico)) {
                $sql .= " AND l.tipoPublico = ?";
                $params[] = $tipoPublico;
                $types .= "s";
            }

            if (!empty($baseMin)) {
                $sql .= " AND l.base >= ?";
                $params[] = floatval($baseMin);
                $types .= "d";
            }

            if (!empty($baseMax)) {
                $sql .= " AND l.base <= ?";
                $params[] = floatval($baseMax);
                $types .= "d";
            }

            if (!empty($alturaMin)) {
                $sql .= " AND l.altura >= ?";
                $params[] = floatval($alturaMin);
                $types .= "d";
            }

            if (!empty($alturaMax)) {
                $sql .= " AND l.altura <= ?";
                $params[] = floatval($alturaMax);
                $types .= "d";
            }

            if (!empty($paginasMin)) {
                $sql .= " AND l.paginas >= ?";
                $params[] = intval($paginasMin);
                $types .= "i";
            }

            if (!empty($paginasMax)) {
                $sql .= " AND l.paginas <= ?";
                $params[] = intval($paginasMax);
                $types .= "i";
            }

            // Búsqueda por hashtags
            if (!empty(trim($etiquetas))) {
                $etiquetasArray = array_map('trim', explode(',', $etiquetas));
                $hashtagConditions = [];
                
                foreach ($etiquetasArray as $etiqueta) {
                    if (!empty($etiqueta)) {
                        $hashtagConditions[] = "h.texto LIKE ?";
                        $params[] = "%" . $etiqueta . "%";
                        $types .= "s";
                    }
                }
                
                if (!empty($hashtagConditions)) {
                    $sql .= " AND l.idLibro IN (
                        SELECT lh.idLibro 
                        FROM LibroHashtags lh 
                        JOIN Hashtags h ON lh.idHashtag = h.idHashtag 
                        WHERE " . implode(' OR ', $hashtagConditions) . "
                    )";
                }
            }

            $sql .= " ORDER BY p.fechaCreacion DESC";

            // Ejecutar consulta
            $stmt = $conn->prepare($sql);
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $resultados[] = $row;
            }

            // Obtener hashtags para cada libro encontrado
            if (!empty($resultados)) {
                $libroIds = array_column($resultados, 'idLibro');
                $placeholders = implode(',', array_fill(0, count($libroIds), '?'));
                
                $hashtagStmt = $conn->prepare("
                    SELECT lh.idLibro, h.texto as hashtag
                    FROM LibroHashtags lh
                    INNER JOIN Hashtags h ON lh.idHashtag = h.idHashtag
                    WHERE lh.idLibro IN ($placeholders)
                ");
                
                if ($hashtagStmt) {
                    $hashtagTypes = str_repeat('i', count($libroIds));
                    $hashtagStmt->bind_param($hashtagTypes, ...$libroIds);
                    $hashtagStmt->execute();
                    $hashtagResult = $hashtagStmt->get_result();
                    
                    while ($hashtagRow = $hashtagResult->fetch_assoc()) {
                        $hashtags[$hashtagRow['idLibro']][] = $hashtagRow['hashtag'];
                    }
                    $hashtagStmt->close();
                }
            }

            if (empty($resultados)) {
                $mensaje_exito = "No se encontraron libros que coincidan con los criterios de búsqueda.";
            } else {
                $mensaje_exito = "Se encontraron " . count($resultados) . " libro(s) que coinciden con tu búsqueda.";
            }

            $stmt->close();
        }
    } catch (Exception $e) {
        $errores[] = "Error en el servidor: " . $e->getMessage();
        error_log("Error en buscador.php: " . $e->getMessage());
    } finally {
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close();
        }
    }

    // Funciones helper
    function formatearPrecio($precio) {
        return ($precio == 0 || $precio === null) ? 'Gratis' : '$' . number_format($precio, 2);
    }

    function formatearFecha($fecha) {
        return empty($fecha) ? 'No especificada' : date('d/m/Y', strtotime($fecha));
    }

    function formatearDimensiones($base, $altura) {
        if (empty($base) && empty($altura)) return 'No especificadas';
        if (empty($base)) return "Altura: {$altura} cm";
        if (empty($altura)) return "Base: {$base} cm";
        return "{$base} x {$altura} cm";
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Buscador Avanzado | RELEE</title>
    
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

        .search-container {
            background: rgba(255, 253, 252, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            width: 90%;
            max-width: 800px;
            margin: 40px auto;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
        }

        .search-container::before {
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

        .search-container h1 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 2.2em;
            font-weight: 800;
            background: linear-gradient(135deg, #6b4226 0%, #8b5a3c 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .search-description {
            text-align: center;
            margin-bottom: 30px;
            color: #6f5c4d;
            font-size: 1.1em;
            line-height: 1.6;
        }

        .search-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        @media (min-width: 768px) {
            .search-form {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-group.full-width {
                grid-column: 1 / -1;
            }
        }

        .form-group {
            margin-bottom: 10px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c2016;
            font-size: 0.95em;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid transparent;
            border-radius: 15px;
            font-size: 1em;
            color: #2c2016;
            background: linear-gradient(white, white) padding-box,
                                    linear-gradient(135deg, #a3b18a, #588157) border-box;
            transition: all 0.3s ease;
            box-sizing: border-box;
            box-shadow: 0 4px 15px rgba(163, 177, 138, 0.1);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(163, 177, 138, 0.2);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .dimensions-group .dimension-inputs {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .dimensions-group .dimension-inputs label {
            margin-bottom: 0;
            white-space: nowrap;
            font-weight: 500;
            min-width: fit-content;
        }

        .dimensions-group .dimension-inputs input {
            flex: 1;
            min-width: 80px;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6f5c4d;
            font-size: 0.85em;
            opacity: 0.8;
        }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }

        .submit-button, .cancel-button {
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .submit-button {
            background: linear-gradient(135deg, #588157 0%, #3a5a40 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(88, 129, 87, 0.3);
        }

        .submit-button:hover {
            background: linear-gradient(135deg, #3a5a40 0%, #2d4732 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(88, 129, 87, 0.4);
        }

        .cancel-button {
            background: linear-gradient(135deg, #6c584c 0%, #5b4a3e 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(108, 88, 76, 0.3);
        }

        .cancel-button:hover {
            background: linear-gradient(135deg, #5b4a3e 0%, #4a3d32 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(108, 88, 76, 0.4);
        }

        /* Estilos para los resultados */
        .results-section {
            margin-top: 40px;
        }

        .results-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .results-header h2 {
            font-size: 1.8em;
            font-weight: 700;
            color: #2c2016;
            margin-bottom: 10px;
        }

        .results-count {
            background: linear-gradient(135deg, #588157 0%, #3a5a40 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            padding: 0 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .book-card {
            background: rgba(255, 253, 252, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .book-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .book-image {
            position: relative;
            height: 250px;
            overflow: hidden;
            background: linear-gradient(135deg, #f8f6f3 0%, #f0ede8 100%);
        }

        .book-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .book-card:hover .book-image img {
            transform: scale(1.05);
        }

        .image-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: rgba(44, 32, 22, 0.3);
        }

        .video-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: linear-gradient(135deg, #588157 0%, #3a5a40 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .price-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #6b4226 0%, #8b5a3c 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 700;
            font-size: 0.85em;
        }

        .price-badge.free {
            background: linear-gradient(135deg, #588157 0%, #3a5a40 100%);
        }

        .book-content {
            padding: 25px;
        }

        .book-title {
            font-size: 1.3em;
            font-weight: 700;
            color: #2c2016;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .book-author {
            color: #6f5c4d;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 1em;
        }

        .book-description {
            color: #8a7a6a;
            line-height: 1.5;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: 0.95em;
        }

        .book-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .detail-item {
            background: rgba(163, 177, 138, 0.1);
            padding: 8px 12px;
            border-radius: 10px;
            border-left: 3px solid #a3b18a;
        }

        .detail-label {
            font-weight: 600;
            color: #588157;
            display: block;
            margin-bottom: 3px;
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            color: #2c2016;
            font-weight: 500;
            font-size: 0.85em;
        }

        .book-hashtags {
            margin-bottom: 15px;
        }

        .hashtag {
            display: inline-block;
            background: rgba(88, 129, 87, 0.1);
            color: #3a5a40;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            margin: 2px 4px 2px 0;
            border: 1px solid rgba(88, 129, 87, 0.2);
        }

        .book-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid rgba(163, 177, 138, 0.2);
        }

        .publication-date {
            color: #8a7a6a;
            font-size: 0.85em;
            font-weight: 500;
        }

        .view-button {
            background: linear-gradient(135deg, #588157 0%, #3a5a40 100%);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.85em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .view-button:hover {
            background: linear-gradient(135deg, #3a5a40 0%, #2d4732 100%);
            transform: translateY(-1px);
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #8a7a6a;
        }

        .no-results-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .errores, .mensaje-exito {
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
        }

        .errores {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            border: 1px solid #ef9a9a;
            color: #c62828;
        }

        .mensaje-exito {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            border: 1px solid #81c784;
            color: #2e7d32;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .search-container {
                padding: 30px 20px;
                margin: 20px 15px;
            }
            
            .search-container h1 {
                font-size: 1.8em;
            }
            
            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .submit-button, .cancel-button {
                width: 100%;
                padding: 12px 20px;
                justify-content: center;
            }

            .results-grid {
                grid-template-columns: 1fr;
                padding: 0 15px;
            }

            .dimensions-group .dimension-inputs {
                flex-direction: column;
                gap: 10px;
            }

            .dimensions-group .dimension-inputs input {
                width: 100%;
            }
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

        .search-container, .book-card {
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

    <main class="search-container">
        <h1>🔍 Buscador Avanzado</h1>
        <p class="search-description">Encuentra libros usando múltiples criterios. Todos los campos son opcionales, pero más detalles te darán mejores resultados.</p>
        
        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensaje_exito) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="mensaje-exito">
                <p><?php echo htmlspecialchars($mensaje_exito); ?></p>
            </div>
        <?php endif; ?>
        
        <form action="" method="POST" class="search-form">
            
            <div class="form-group">
                <label for="titulo">Título:</label>
                <input type="text" id="titulo" name="titulo" value="<?php echo $titulo; ?>" placeholder="Ej: Cien años de soledad">
            </div>

            <div class="form-group">
                <label for="autor">Autor:</label>
                <input type="text" id="autor" name="autor" value="<?php echo $autor; ?>" placeholder="Ej: Gabriel García Márquez">
            </div>

            <div class="form-group full-width">
                <label for="descripcion">Descripción (palabras clave):</label>
                <textarea id="descripcion" name="descripcion" rows="3" placeholder="Palabras clave de la descripción del libro"><?php echo $descripcion; ?></textarea>
                <small>Busca palabras clave en la descripción del libro</small>
            </div>

            <div class="form-group">
                <label for="precioMin">Precio Mínimo:</label>
                <input type="number" id="precioMin" name="precioMin" step="0.01" min="0" value="<?php echo $precioMin; ?>" placeholder="0.00">
            </div>

            <div class="form-group">
                <label for="precioMax">Precio Máximo:</label>
                <input type="number" id="precioMax" name="precioMax" step="0.01" min="0" value="<?php echo $precioMax; ?>" placeholder="100.00">
            </div>

            <div class="form-group">
                <label for="etiquetas">Etiquetas (separadas por comas):</label>
                <input type="text" id="etiquetas" name="etiquetas" placeholder="ficción, fantasía, aventura" value="<?php echo $etiquetas; ?>">
                <small>Busca por hashtags del libro</small>
            </div>

            <div class="form-group">
                <label for="editorial">Editorial:</label>
                <input type="text" id="editorial" name="editorial" value="<?php echo $editorial; ?>" placeholder="Ej: Penguin Random House">
            </div>

            <div class="form-group">
                <label for="edicion">Edición:</label>
                <input type="text" id="edicion" name="edicion" value="<?php echo $edicion; ?>" placeholder="Ej: Primera, Segunda">
            </div>

            <div class="form-group">
                <label for="categoria">Categoría:</label>
                <input type="text" id="categoria" name="categoria" value="<?php echo $categoria; ?>" placeholder="Ej: Novela, Ensayo, Poesía">
            </div>

            <div class="form-group">
                <label for="tipoPublico">Tipo de Público:</label>
                <select id="tipoPublico" name="tipoPublico">
                    <option value="">Todos los públicos</option>
                    <option value="General" <?php echo ($tipoPublico === 'General' ? 'selected' : ''); ?>>General</option>
                    <option value="Infantil" <?php echo ($tipoPublico === 'Infantil' ? 'selected' : ''); ?>>Infantil</option>
                    <option value="Juvenil" <?php echo ($tipoPublico === 'Juvenil' ? 'selected' : ''); ?>>Juvenil</option>
                    <option value="Adultos" <?php echo ($tipoPublico === 'Adultos' ? 'selected' : ''); ?>>Adultos</option>
                </select>
            </div>

            <div class="form-group dimensions-group full-width">
                <label>Dimensiones (cm):</label>
                <div class="dimension-inputs">
                    <label for="baseMin">Base Mín:</label>
                    <input type="number" id="baseMin" name="baseMin" step="0.1" min="0" value="<?php echo $baseMin; ?>" placeholder="10.0">
                    <label for="baseMax">Base Máx:</label>
                    <input type="number" id="baseMax" name="baseMax" step="0.1" min="0" value="<?php echo $baseMax; ?>" placeholder="30.0">
                    <label for="alturaMin">Altura Mín:</label>
                    <input type="number" id="alturaMin" name="alturaMin" step="0.1" min="0" value="<?php echo $alturaMin; ?>" placeholder="15.0">
                    <label for="alturaMax">Altura Máx:</label>
                    <input type="number" id="alturaMax" name="alturaMax" step="0.1" min="0" value="<?php echo $alturaMax; ?>" placeholder="25.0">
                </div>
            </div>

            <div class="form-group">
                <label for="paginasMin">Páginas Mínimas:</label>
                <input type="number" id="paginasMin" name="paginasMin" min="1" value="<?php echo $paginasMin; ?>" placeholder="50">
            </div>

            <div class="form-group">
                <label for="paginasMax">Páginas Máximas:</label>
                <input type="number" id="paginasMax" name="paginasMax" min="1" value="<?php echo $paginasMax; ?>" placeholder="500">
            </div>

            <div class="form-actions">
                <a href="../home.php" class="cancel-button">
                    <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                    Cancelar
                </a>
                <button type="submit" class="submit-button">
                    <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                        <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                    </svg>
                    Buscar Libros
                </button>
            </div>
        </form>
    </main>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <section class="results-section">
            <div class="results-header">
                <h2>📚 Resultados de Búsqueda</h2>
                <?php if (!empty($resultados)): ?>
                    <span class="results-count"><?php echo count($resultados); ?> libro(s) encontrado(s)</span>
                <?php endif; ?>
            </div>

            <div class="results-grid">
                <?php if (empty($resultados)): ?>
                    <div class="no-results">
                        <div class="no-results-icon">📖</div>
                        <h3>No se encontraron resultados</h3>
                        <p>No hay libros que coincidan con los criterios de búsqueda especificados.</p>
                        <p>Intenta modificar los filtros o usar menos criterios específicos.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($resultados as $libro): ?>
                        <div class="book-card" onclick="window.location.href='ver_libro.php?id=<?php echo $libro['idPublicacion']; ?>'">
                            <div class="book-image">
                                <?php if (!empty($libro['linkImagen1'])): ?>
                                    <img src="../../uploads/<?php echo htmlspecialchars($libro['linkImagen1']); ?>" 
                                         alt="<?php echo htmlspecialchars($libro['titulo']); ?>" 
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="image-placeholder">
                                        <svg width="80" height="80" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($libro['linkVideo'])): ?>
                                    <div class="video-badge">
                                        <svg width="12" height="12" fill="white" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z"/>
                                        </svg>
                                        Video
                                    </div>
                                <?php endif; ?>
                                
                                <div class="price-badge <?php echo ($libro['precio'] == 0) ? 'free' : ''; ?>">
                                    <?php echo formatearPrecio($libro['precio']); ?>
                                </div>
                            </div>
                            
                            <div class="book-content">
                                <h3 class="book-title"><?php echo htmlspecialchars($libro['titulo']); ?></h3>
                                <div class="book-author">✍️ <?php echo htmlspecialchars($libro['autor']); ?></div>
                                
                                <?php if (!empty($libro['descripcion'])): ?>
                                    <div class="book-description">
                                        <?php echo htmlspecialchars($libro['descripcion']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="book-details">
                                    <?php if (!empty($libro['editorial'])): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Editorial</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($libro['editorial']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($libro['categoria'])): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Categoría</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($libro['categoria']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($libro['tipoPublico'])): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Público</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($libro['tipoPublico']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($libro['paginas'])): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Páginas</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($libro['paginas']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($hashtags[$libro['idLibro']])): ?>
                                    <div class="book-hashtags">
                                        <?php foreach ($hashtags[$libro['idLibro']] as $hashtag): ?>
                                            <span class="hashtag">#<?php echo htmlspecialchars($hashtag); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="book-footer">
                                    <span class="publication-date">📅 <?php echo formatearFecha($libro['fechaCreacion']); ?></span>
                                    <a href="ver_libro.php?id=<?php echo $libro['idPublicacion']; ?>" class="view-button" onclick="event.stopPropagation();">
                                        <svg width="14" height="14" fill="white" viewBox="0 0 24 24">
                                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                        </svg>
                                        Ver
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Efectos visuales de foco para inputs
            document.querySelectorAll('input, select, textarea').forEach(element => {
                element.addEventListener('focus', function() {
                    this.style.transform = 'translateY(-1px)';
                });
                
                element.addEventListener('blur', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Animación de las tarjetas de resultados
            const cards = document.querySelectorAll('.book-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });

            // Validación del formulario
            const form = document.querySelector('.search-form');
            const submitButton = document.querySelector('.submit-button');
            
            form.addEventListener('submit', function(e) {
                const formData = new FormData(form);
                let hasValue = false;
                
                for (let [key, value] of formData.entries()) {
                    if (value.trim() !== '') {
                        hasValue = true;
                        break;
                    }
                }
                
                if (!hasValue) {
                    e.preventDefault();
                    alert('⚠️ Por favor, completa al menos un campo para realizar la búsqueda.');
                    return;
                }
                
                // Animación del botón
                submitButton.innerHTML = `
                    <svg width="16" height="16" fill="white" viewBox="0 0 24 24" class="spinning">
                        <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8z"/>
                        <path d="m4 12c0-1.01.25-1.97.7-2.8L3.24 7.74C2.46 8.97 2 10.43 2 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3c-3.31 0-6-2.69-6-6z"/>
                    </svg>
                    Buscando...
                `;
                submitButton.disabled = true;
            });

            // Validación de rangos numéricos
            function validateRange(minId, maxId) {
                const minInput = document.getElementById(minId);
                const maxInput = document.getElementById(maxId);
                
                function validate() {
                    const minVal = parseFloat(minInput.value);
                    const maxVal = parseFloat(maxInput.value);
                    
                    if (minVal && maxVal && minVal > maxVal) {
                        maxInput.setCustomValidity(`El valor máximo debe ser mayor que ${minVal}`);
                    } else {
                        maxInput.setCustomValidity('');
                    }
                }
                
                minInput.addEventListener('input', validate);
                maxInput.addEventListener('input', validate);
            }
            
            validateRange('precioMin', 'precioMax');
            validateRange('baseMin', 'baseMax');
            validateRange('alturaMin', 'alturaMax');
            validateRange('paginasMin', 'paginasMax');

            // Scroll suave a resultados después de buscar
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($resultados)): ?>
                setTimeout(() => {
                    document.querySelector('.results-section')?.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'start' 
                    });
                }, 100);
            <?php endif; ?>
        });

        // CSS para animaciones
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spinning {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .spinning { animation: spinning 1s linear infinite; }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>