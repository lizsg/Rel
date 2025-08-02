<?php
    session_start();

    // Redireccionar si el usuario no ha iniciado sesión o no tiene un user_id
    if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }

    require_once __DIR__ . '/../../config/database.php'; // Asegúrate de que esta ruta sea correcta

    $userId = $_SESSION['user_id']; // Obtener el ID del usuario de la sesión

    $errores = []; // Array para almacenar mensajes de error de PHP
    $mensaje_exito = ''; // Variable para mensaje de éxito

    // Inicializar variables para mantener los valores en el formulario si hay errores
    // Se usa htmlspecialchars para prevenir XSS al mostrar los valores en el HTML
    $titulo = htmlspecialchars($_POST['titulo'] ?? '');
    $autor = htmlspecialchars($_POST['autor'] ?? '');
    $descripcion = htmlspecialchars($_POST['descripcion'] ?? '');
    $precio = htmlspecialchars($_POST['precio'] ?? '');
    $etiquetas = htmlspecialchars($_POST['etiquetas'] ?? '');
    $editorial = htmlspecialchars($_POST['editorial'] ?? '');
    $edicion = htmlspecialchars($_POST['edicion'] ?? '');
    $categoria = htmlspecialchars($_POST['categoria'] ?? '');
    $tipoPublico = htmlspecialchars($_POST['tipoPublico'] ?? '');
    $base = htmlspecialchars($_POST['base'] ?? '');
    $altura = htmlspecialchars($_POST['altura'] ?? '');
    $paginas = htmlspecialchars($_POST['paginas'] ?? '');

    try {
        // Establecemos la conexión a la base de datos
        $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Conexión fallida: " . $conn->connect_error);
        }

        // Procesar el formulario solo si se ha enviado por POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validaciones básicas de campos de texto (lado del servidor)
            if (empty(trim($titulo))) {
                $errores[] = "El título es obligatorio.";
            }
            if (empty(trim($autor))) {
                $errores[] = "El autor es obligatorio.";
            }
            if (empty(trim($descripcion))) {
                $errores[] = "La descripción es obligatoria.";
            }
            // Validar precio si se proporciona
            if (!empty($precio)) {
                if (!is_numeric($precio) || (float)$precio < 0) {
                    $errores[] = "El precio debe ser un número válido y no negativo.";
                }
            }
            // Validar páginas si se proporciona
            if (!empty($paginas)) {
                if (!filter_var($paginas, FILTER_VALIDATE_INT, array("options" => array("min_range"=>1)))) {
                    $errores[] = "El número de páginas debe ser un número entero positivo.";
                }
            }
            // Validar dimensiones si se proporcionan
            if (!empty($base)) {
                if (!is_numeric($base) || (float)$base < 0) {
                    $errores[] = "La base debe ser un número válido y no negativo.";
                }
            }
            if (!empty($altura)) {
                if (!is_numeric($altura) || (float)$altura < 0) {
                    $errores[] = "La altura debe ser un número válido y no negativo.";
                }
            }
            if (empty(trim($categoria))) {
                $errores[] = "La categoría es obligatoria.";
            }
            if (empty(trim($tipoPublico))) {
                $errores[] = "El tipo de público es obligatorio.";
            }

        }
    } catch (Exception $e) {
        // Captura cualquier excepción (ej. error de conexión a DB)
        $errores[] = "Error en el servidor: " . $e->getMessage();
        error_log("Error en NuevaPublicacion.php: " . $e->getMessage()); // Para depuración en el log del servidor
    } finally {
        // Asegura que la conexión a la base de datos se cierre
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
    <title>Nueva Publicación | RELEE</title>
    
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

        .add-publication-container {
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

        .add-publication-container::before {
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

        .add-publication-container h1 {
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

        /* Input file personalizado */
        .form-group input[type="file"] {
            border: 2px dashed #a3b18a;
            padding: 15px;
            background: rgba(163, 177, 138, 0.05);
            cursor: pointer;
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .form-group input[type="file"]:hover {
            background: rgba(163, 177, 138, 0.1);
            transform: translateY(-1px);
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

        /* Responsive */
        @media (max-width: 768px) {
            .add-publication-container {
                padding: 30px 20px;
                margin: 20px 15px;
            }
            
            .add-publication-container h1 {
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

        .add-publication-container {
            animation: fadeInUp 0.6s ease forwards;
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

    <main class="add-publication-container">
        <h1>Agregar Nueva Publicación</h1>
        
        <?php if (!empty($errores)): ?>
            <div class="errores">
                <?php foreach ($errores as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form action="" method="POST" class="publication-form" enctype="multipart/form-data">
            
            <div class="form-group">
                <label for="titulo">Título:</label>
                <input type="text" id="titulo" name="titulo" required value="<?php echo $titulo; ?>">
            </div>

            <div class="form-group">
                <label for="autor">Autor:</label>
                <input type="text" id="autor" name="autor" required value="<?php echo $autor; ?>">
            </div>

            <div class="form-group full-width">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion" rows="4" required><?php echo $descripcion; ?></textarea>
            </div>

            <div class="form-group">
                <label for="precio">Precio:</label>
                <input type="number" id="precio" name="precio" step="0.01" min="0" value="<?php echo $precio; ?>">
                <small>Opcional</small>
            </div>

            <div class="form-group">
                <label for="etiquetas">Etiquetas (separadas por comas):</label>
                <input type="text" id="etiquetas" name="etiquetas" placeholder="ej: ficción, fantasía, aventura" value="<?php echo $etiquetas; ?>">
                <small>Opcional</small>
            </div>

            <div class="form-group">
                <label for="editorial">Editorial:</label>
                <input type="text" id="editorial" name="editorial" value="<?php echo $editorial; ?>">
                <small>Opcional</small>
            </div>

            <div class="form-group">
                <label for="edicion">Edición:</label>
                <input type="text" id="edicion" name="edicion" value="<?php echo $edicion; ?>">
                <small>Opcional</small>
            </div>

            <div class="form-group">
                <label for="categoria">Categoría:</label>
                <input type="text" id="categoria" name="categoria" required value="<?php echo $categoria; ?>">
            </div>

            <div class="form-group">
                <label for="tipoPublico">Tipo de Público:</label>
                <select id="tipoPublico" name="tipoPublico" required>
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
                    <label for="base">Base:</label>
                    <input type="number" id="base" name="base" step="0.1" min="0" value="<?php echo $base; ?>">
                    <label for="altura">Altura:</label>
                    <input type="number" id="altura" name="altura" step="0.1" min="0" value="<?php echo $altura; ?>">
                </div>
                <small>Opcional</small>
            </div>

            <div class="form-group">
                <label for="paginas">Número de Páginas:</label>
                <input type="number" id="paginas" name="paginas" min="1" value="<?php echo $paginas; ?>">
                <small>Opcional</small>
            </div>

            <div class="form-group full-width">
                <label for="uploadvideo">Video del libro (Subir):</label>
                <input type="file" id="uploadvideo" name="uploadvideo" accept="video/*">
                <small>Trata de subir un video donde se pueda ver claramente el estado del libro (Max 10MB)</small>
            </div>

            <div class="form-group full-width">
                <label for="uploadImagen1">Imagen de Portada (Subir):</label>
                <input type="file" id="uploadImagen1" name="uploadImagen1" accept="image/*" required>
                <small>Imagen obligatoria (Max 10MB)</small>
            </div>

            <div class="form-group full-width">
                <label for="uploadImagen2">Imagen Adicional 1 (Subir):</label>
                <input type="file" id="uploadImagen2" name="uploadImagen2" accept="image/*">
                <small>Si no sabes qué subir aquí, recomendamos una imagen de la contraportada (Max 10MB)</small>
            </div>

            <div class="form-group full-width">
                <label for="uploadImagen3">Imagen Adicional 2 (Subir):</label>
                <input type="file" id="uploadImagen3" name="uploadImagen3" accept="image/*">
                <small>Si no sabes qué subir aquí, recomendamos una foto que muestre el estado del libro (Max 10MB)</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="submit-button">Guardar Publicación</button>
                <button type="button" class="cancel-button" onclick="window.history.back()">Cancelar</button>
            </div>
        </form>
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
        // Tus funciones JS actuales
        function openChatModal() {
            console.log('Abriendo chat principal...');
            // Implementa la lógica para tu modal de chat aquí
        }

        function openChatModal2() {
            console.log('Abriendo chat secundario...');
            // Implementa la lógica para tu segundo modal de chat aquí
        }

        document.querySelector('.publication-form').addEventListener('submit', function(e) {
            // Validaciones del lado del cliente (JS) para mejorar la UX
            const titulo = document.getElementById('titulo').value.trim();
            const autor = document.getElementById('autor').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            const categoria = document.getElementById('categoria').value.trim();
            const tipoPublico = document.getElementById('tipoPublico').value;
            const imagen1 = document.getElementById('uploadImagen1');
            
            let erroresJS = [];

            if (!titulo) {
                erroresJS.push('El título es obligatorio.');
            }
            if (!autor) {
                erroresJS.push('El autor es obligatorio.');
            }
            if (!descripcion) {
                erroresJS.push('La descripción es obligatoria.');
            }
            if (!categoria) {
                erroresJS.push('La categoría es obligatoria.');
            }
            if (!tipoPublico) {
                erroresJS.push('El tipo de público es obligatorio.');
            }
            if (imagen1.files.length === 0) {
                erroresJS.push('La imagen de portada es obligatoria.');
            }

            const precio = document.getElementById('precio').value.trim();
            if (precio !== '' && (isNaN(precio) || parseFloat(precio) < 0)) {
                erroresJS.push('El precio debe ser un número válido y no negativo.');
            }

            const paginas = document.getElementById('paginas').value.trim();
            if (paginas !== '' && (isNaN(paginas) || parseInt(paginas) < 1)) {
                erroresJS.push('El número de páginas debe ser un número entero positivo.');
            }

            const base = document.getElementById('base').value.trim();
            if (base !== '' && (isNaN(base) || parseFloat(base) < 0)) {
                erroresJS.push('La base debe ser un número válido y no negativo.');
            }

            const altura = document.getElementById('altura').value.trim();
            if (altura !== '' && (isNaN(altura) || parseFloat(altura) < 0)) {
                erroresJS.push('La altura debe ser un número válido y no negativo.');
            }

            if (erroresJS.length > 0) {
                e.preventDefault(); // Detener el envío del formulario
                alert('Por favor corrige los siguientes errores:\n\n' + erroresJS.join('\n'));
                return false;
            }
        });

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