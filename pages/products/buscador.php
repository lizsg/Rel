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
    $publicaciones = [];

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
        
        if ($conn->connect_error) {
            throw new Exception("Conexión fallida: " . $conn->connect_error);
        }

        // Procesar el formulario solo si se ha enviado por POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar que al menos un campo esté lleno
            $camposLlenos = !empty(trim($titulo)) || !empty(trim($autor)) || !empty(trim($descripcion)) || 
                           !empty($precioMin) || !empty($precioMax) || !empty($paginasMin) || !empty($paginasMax) ||
                           !empty($baseMin) || !empty($baseMax) || !empty($alturaMin) || !empty($alturaMax) || 
                           !empty(trim($categoria)) || !empty(trim($tipoPublico)) || !empty(trim($editorial)) || 
                           !empty(trim($edicion)) || !empty(trim($etiquetas));

            if (!$camposLlenos) {
                $errores[] = "Debe llenar al menos un campo para realizar la búsqueda.";
            } else {
                // Construir la consulta dinámicamente
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
                        l.editorial,
                        l.edicion,
                        l.categoria,
                        l.tipoPublico,
                        l.base,
                        l.altura,
                        l.paginas,
                        u.userName as nombreUsuario,
                        u.nombre
                    FROM Publicaciones p
                    JOIN Libros l ON p.idLibro = l.idLibro
                    JOIN Usuarios u ON p.idUsuario = u.idUsuario
                    WHERE p.idUsuario != ?
                ";

                $conditions = [];
                $params = [$userId];
                $types = "i";

                // Agregar condiciones según los campos llenos
                if (!empty(trim($titulo))) {
                    $conditions[] = "l.titulo LIKE ?";
                    $params[] = "%" . $titulo . "%";
                    $types .= "s";
                }

                if (!empty(trim($autor))) {
                    $conditions[] = "l.autor LIKE ?";
                    $params[] = "%" . $autor . "%";
                    $types .= "s";
                }

                if (!empty(trim($descripcion))) {
                    $conditions[] = "l.descripcion LIKE ?";
                    $params[] = "%" . $descripcion . "%";
                    $types .= "s";
                }

                if (!empty($precioMin)) {
                    $conditions[] = "p.precio >= ?";
                    $params[] = floatval($precioMin);
                    $types .= "d";
                }

                if (!empty($precioMax)) {
                    $conditions[] = "p.precio <= ?";
                    $params[] = floatval($precioMax);
                    $types .= "d";
                }

                if (!empty(trim($categoria))) {
                    $conditions[] = "l.categoria LIKE ?";
                    $params[] = "%" . $categoria . "%";
                    $types .= "s";
                }

                if (!empty(trim($tipoPublico))) {
                    $conditions[] = "l.tipoPublico = ?";
                    $params[] = $tipoPublico;
                    $types .= "s";
                }

                if (!empty(trim($editorial))) {
                    $conditions[] = "l.editorial LIKE ?";
                    $params[] = "%" . $editorial . "%";
                    $types .= "s";
                }

                if (!empty(trim($edicion))) {
                    $conditions[] = "l.edicion LIKE ?";
                    $params[] = "%" . $edicion . "%";
                    $types .= "s";
                }

                if (!empty($baseMin)) {
                    $conditions[] = "l.base >= ?";
                    $params[] = floatval($baseMin);
                    $types .= "d";
                }

                if (!empty($baseMax)) {
                    $conditions[] = "l.base <= ?";
                    $params[] = floatval($baseMax);
                    $types .= "d";
                }

                if (!empty($alturaMin)) {
                    $conditions[] = "l.altura >= ?";
                    $params[] = floatval($alturaMin);
                    $types .= "d";
                }

                if (!empty($alturaMax)) {
                    $conditions[] = "l.altura <= ?";
                    $params[] = floatval($alturaMax);
                    $types .= "d";
                }

                if (!empty($paginasMin)) {
                    $conditions[] = "l.paginas >= ?";
                    $params[] = intval($paginasMin);
                    $types .= "i";
                }

                if (!empty($paginasMax)) {
                    $conditions[] = "l.paginas <= ?";
                    $params[] = intval($paginasMax);
                    $types .= "i";
                }

                // Agregar las condiciones a la consulta
                if (!empty($conditions)) {
                    $query .= " AND " . implode(" AND ", $conditions);
                }

                $query .= " ORDER BY p.fechaCreacion DESC";

                $stmt = $conn->prepare($query);
                
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $publicaciones[] = $row;
                }

                $stmt->close();

                if (empty($publicaciones)) {
                    $mensaje_exito = "No se encontraron publicaciones que coincidan con los criterios de búsqueda.";
                }
            }
        }
    } catch (Exception $e) {
        $errores[] = "Error en el servidor: " . $e->getMessage();
        error_log("Error en buscador.php: " . $e->getMessage());
    } finally {
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close();
        }
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
            margin-bottom: 30px;
            font-size: 2.2em;
            font-weight: 800;
            background: linear-gradient(135deg, #6b4226 0%, #8b5a3c 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        /* Formulario en grid */
        .publication-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        @media (min-width: 768px) {
            .publication-form {
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
        .form-group input[type="date"],
        .form-group input[type="url"],
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

        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group input[type="date"]:focus,
        .form-group input[type="url"]:focus,
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

        /* Estilos específicos para el grupo de dimensiones */
        .dimensions-group .dimension-inputs {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .dimensions-group .dimension-inputs label {
            margin-bottom: 0;
            white-space: nowrap;
            font-weight: 500;
        }

        .dimensions-group .dimension-inputs input {
            flex-grow: 1;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6f5c4d;
            font-size: 0.85em;
            opacity: 0.8;
        }

        /* Botones del formulario */
        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
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

        .results-title {
            text-align: center;
            font-size: 1.8em;
            font-weight: 700;
            color: #6b4226;
            margin-bottom: 30px;
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            padding: 20px 0;
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
            font-size: 1.3em;
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
            margin-bottom: 15px;
            font-size: 1.1em;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-details, .btn-contact {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            font-size: 0.9em;
        }

        .btn-details {
            background: linear-gradient(135deg, #588157 0%, #3a5a40 100%);
            color: white;
        }

        .btn-details:hover {
            background: linear-gradient(135deg, #3a5a40 0%, #2d4732 100%);
            transform: translateY(-1px);
        }

        .btn-contact {
            background: linear-gradient(135deg, #8b5a3c 0%, #6b4226 100%);
            color: white;
        }

        .btn-contact:hover {
            background: linear-gradient(135deg, #6b4226 0%, #5d3a22 100%);
            transform: translateY(-1px);
        }

        .errores {
            background-color: #ffebee;
            border: 1px solid #ef9a9a;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .errores p {
            margin: 5px 0;
            font-weight: 500;
        }

        .mensaje-exito {
            background-color: #e8f5e8;
            border: 1px solid #4caf50;
            color: #2e7d32;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #6d4c41;
        }

        .no-results svg {
            margin-bottom: 20px;
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
            }

            .gallery {
                grid-template-columns: 1fr;
                padding: 20px;
            }

            .card-actions {
                flex-direction: column;
            }
        }

        /* Animación de entrada */
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

        .search-container {
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
        <h1>Buscador Avanzado</h1>
        <p>Llene los campos con las características del libro que busque. Todos los campos son opcionales, pero con más datos funcionará mejor el filtro.</p>
        
        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensaje_exito)): ?>
            <div class="mensaje-exito">
                <p><?php echo htmlspecialchars($mensaje_exito); ?></p>
            </div>
        <?php endif; ?>
        
        <form action="" method="POST" class="publication-form">
            
            <div class="form-group">
                <label for="titulo">Título:</label>
                <input type="text" id="titulo" name="titulo" value="<?php echo $titulo; ?>">
            </div>

            <div class="form-group">
                <label for="autor">Autor:</label>
                <input type="text" id="autor" name="autor" value="<?php echo $autor; ?>">
            </div>

            <div class="form-group full-width">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion" rows="4"><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>
                <small>Descripción detallada del libro</small>
            </div>

            <div class="form-group">
                <label for="precioMin">Precio Mínimo:</label>
                <input type="number" id="precioMin" name="precioMin" step="0.01" min="0" value="<?php echo $precioMin; ?>">
            </div>

            <div class="form-group">
                <label for="precioMax">Precio Máximo:</label>
                <input type="number" id="precioMax" name="precioMax" step="0.01" min="0" value="<?php echo $precioMax; ?>">
            </div>

            <div class="form-group">
                <label for="etiquetas">Etiquetas (separadas por comas):</label>
                <input type="text" id="etiquetas" name="etiquetas" placeholder="ej: ficción, fantasía, aventura" value="<?php echo $etiquetas; ?>">
            </div>

            <div class="form-group">
                <label for="editorial">Editorial:</label>
                <input type="text" id="editorial" name="editorial" value="<?php echo $editorial; ?>">
            </div>

            <div class="form-group">
                <label for="edicion">Edición:</label>
                <input type="text" id="edicion" name="edicion" value="<?php echo $edicion; ?>">
            </div>

            <div class="form-group">
                <label for="categoria">Categoría:</label>
                <input type="text" id="categoria" name="categoria" value="<?php echo $categoria; ?>">
            </div>

            <div class="form-group">
                <label for="tipoPublico">Tipo de Público:</label>
                <select id="tipoPublico" name="tipoPublico">
                    <option value="">Seleccione...</option>
                    <option value="General" <?php echo ($tipoPublico === 'General' ? 'selected' : ''); ?>>General</option>
                    <option value="Infantil" <?php echo ($tipoPublico === 'Infantil' ? 'selected' : ''); ?>>Infantil</option>
                    <option value="Juvenil" <?php echo ($tipoPublico === 'Juvenil' ? 'selected' : ''); ?>>Juvenil</option>
                    <option value="Adultos" <?php echo ($tipoPublico === 'Adultos' ? 'selected' : ''); ?>>Adultos</option>
                </select>
            </div>

            <div class="form-group dimensions-group">
                <label>Dimensiones (cm):</label>
                <div class="dimension-inputs">
                    <label for="baseMin">Base Mín:</label>
                    <input type="number" id="baseMin" name="baseMin" step="0.1" min="0" value="<?php echo $baseMin; ?>">
                    <label for="baseMax">Base Máx:</label>
                    <input type="number" id="baseMax" name="baseMax" step="0.1" min="0" value="<?php echo $baseMax; ?>">
                    <label for="alturaMin">Altura Mín:</label>
                    <input type="number" id="alturaMin" name="alturaMin" step="0.1" min="0" value="<?php echo $alturaMin; ?>">
                    <label for="alturaMax">Altura Máx:</label>
                    <input type="number" id="alturaMax" name="alturaMax" step="0.1" min="0" value="<?php echo $alturaMax; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="paginasMin">Número de Páginas Mínimo:</label>
                <input type="number" id="paginasMin" name="paginasMin" min="1" value="<?php echo $paginasMin; ?>">
            </div>

            <div class="form-group">
                <label for="paginasMax">Número de Páginas Máximo:</label>
                <input type="number" id="paginasMax" name="paginasMax" min="1" value="<?php echo $paginasMax; ?>">
            </div>

            <div class="form-actions">
                <a href="../home.php" class="cancel-button">Cancelar</a>
                <button type="submit" class="submit-button">Buscar</button>
            </div>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($publicaciones)): ?>
            <div class="results-section">
                <h2 class="results-title">Resultados de la búsqueda (<?php echo count($publicaciones); ?> encontrados)</h2>
                <div class="gallery">
                    <?php foreach ($publicaciones as $publicacion): ?>
                        <div class="card">
                            <div class="card-image">
                                <?php if (!empty($publicacion['linkImagen1'])): ?>
                                    <img src="../../uploads/<?php echo htmlspecialchars($publicacion['linkImagen1']); ?>" alt="<?php echo htmlspecialchars($publicacion['titulo']); ?>">
                                <?php else: ?>
                                    <svg width="60" height="60" fill="rgba(0,0,0,0.1)" viewBox="0 0 24 24">
                                        <path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="card-content">
                                <div class="card-title"><?php echo htmlspecialchars($publicacion['titulo']); ?></div>
                                <div class="card-author">Por: <?php echo htmlspecialchars($publicacion['autor']); ?></div>
                                <div class="card-price">$<?php echo number_format($publicacion['precio'], 2); ?></div>
                                <div class="card-actions">
                                    <a href="detalle_publicacion.php?id=<?php echo $publicacion['idPublicacion']; ?>" class="btn-details">Ver Detalles</a>
                                    <button class="btn-contact" onclick="abrirChat(<?php echo $publicacion['idUsuario']; ?>, '<?php echo htmlspecialchars($publicacion['userName']); ?>')">Contactar</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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
        // Efectos visuales de foco para inputs
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('focus', function() {
                this.style.transform = 'translateY(-1px)';
            });
            
            element.addEventListener('blur', function() {
                this.style.transform = 'translateY(0)';
            });
        });

    </script>
</body>
</html>