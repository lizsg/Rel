<?php
session_start();

if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    // Corregido: "Location" en lugar de "Ubicación" y "exit()" en lugar de "salida()"
    header("Location: ../auth/login.php");
    exit(); // Cambiado de salida() a exit() para una función estándar de PHP
}

// Puedes añadir lógica PHP aquí para procesar el formulario cuando se envíe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Recoger los datos del formulario
    $titulo = $_POST['titulo'] ?? '';
    $autor = $_POST['autor'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $editorial = $_POST['editorial'] ?? '';
    $edicion = $_POST['edicion'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $tipoPublico = $_POST['tipoPublico'] ?? '';
    $base = $_POST['base'] ?? '';
    $altura = $_POST['altura'] ?? '';
    $paginas = $_POST['paginas'] ?? '';
    $fechaPublicacion = $_POST['fechaPublicacion'] ?? '';
    $linkVideo = $_POST['linkVideo'] ?? '';
    $linkImagen1 = $_POST['linkImagen1'] ?? '';
    $linkImagen2 = $_POST['linkImagen2'] ?? '';
    $linkImagen3 = $_POST['linkImagen3'] ?? '';

    // Manejo de subida de archivos (ejemplo básico, necesitarás mejorar esto para producción)
    $uploadedImagePath1 = '';
    if (isset($_FILES['uploadImagen1']) && $_FILES['uploadImagen1']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/'; // Asegúrate de que este directorio exista y tenga permisos de escritura
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $uploadedFile = $uploadDir . basename($_FILES['uploadImagen1']['name']);
        if (move_uploaded_file($_FILES['uploadImagen1']['tmp_name'], $uploadedFile)) {
            $uploadedImagePath1 = $uploadedFile;
        }
    }
    // Repetir para uploadImagen2 y uploadImagen3

    // 2. Validar los datos
    $errores = [];
    if (empty($titulo)) {
        $errores[] = "El título es obligatorio.";
    }
    if (empty($autor)) {
        $errores[] = "El autor es obligatorio.";
    }
    // 3. Si no hay errores, guardar en la base de datos
    if (empty($errores)) {
        // Lógica para guardar en la base de datos
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Nueva Publicación | RELEE</title>
    
    <link rel="stylesheet" href="../../activos/css/publicaciones-styles.css">
    <link rel="stylesheet" href="../../activos/css/chat-styles.css">
    
    <style>
        body {
            margin: 0;
            background-color: #ffffff; /* Ajustado para el color de fondo general */
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            flex-direction: column; 
            min-height: 100vh; /* Para que ocupe al menos el 100% del alto de la ventana */
            background: linear-gradient(135deg, #f8f6f3 0%, #f0ede8 100%);
        }

        /* Estilos de los botones del topbar rescatados */
        .topbar-icon {
            background-color: #79946F; /* El color verdoso de fondo de los botones */
            border-radius: 8px; /* Esquinas redondeadas */
            width: 40px; /* Tamaño ajustado */
            height: 40px; /* Tamaño ajustado */
            display: flex;
            justify-content: center;
            align-items: center;
            margin-left: 10px; /* Espaciado entre botones */
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease; /* Transición para hover */
        }

        .topbar-icon:hover {
            background-color: #6a8260; /* Verde ligeramente más oscuro al pasar el ratón */
            transform: translateY(-2px); /* Pequeño efecto de elevación */
        }

        .topbar-icon a {
            display: flex; /* Para que el SVG y cualquier texto dentro del 'a' se centren */
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            text-decoration: none; /* Eliminar subrayado del enlace */
        }

        .topbar-icon svg {
            fill: white; /* Color de los iconos SVG */
            width: 24px; /* Tamaño del icono */
            height: 24px; /* Tamaño del icono */
        }
      
        .add-publication-container {
            background-color: #fdf6f0; /* Color de fondo del login-container */
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 90%;
            max-width: 800px; 
            margin: 40px auto; /* Centrar el contenedor */
            border: 1px solid rgba(163, 177, 138, 0.3);
            box-sizing: border-box; /* Para incluir padding y border en el ancho total */
        }

        .add-publication-container h1 {
            text-align: center;
            color: #6b4226; 
            margin-bottom: 30px;
            font-size: 2.2em;
            font-weight: 600;
            background: linear-gradient(135deg, #6b4226 0%, #8b5a3c 100%); 
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Estilos para el formulario */
        .publication-form {
            display: grid;
            grid-template-columns: 1fr; /* Una columna por defecto */
            gap: 20px; /* Espacio entre los grupos de formulario */
        }

        /* Para pantallas más grandes, dos columnas */
        @media (min-width: 768px) {
            .publication-form {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-group.full-width { /* Para elementos que necesitan ocupar el ancho completo */
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
            color: #4e3b2b; /* Color similar al texto de los inputs del login */
            font-size: 0.95em;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group input[type="url"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px; /* Adaptado del padding de los inputs del login */
            margin: 0; 
            border: 2px solid #a3b18a; /* Borde de los inputs del login */
            border-radius: 8px; 
            font-size: 1em;
            color: #4e3b2b; 
            background-color: #fffdfc; /* Color de fondo de los inputs del login */
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group input[type="date"]:focus,
        .form-group input[type="url"]:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #588157; /* Color de borde al enfocar del login */
            box-shadow: 0 0 0 3px rgba(88, 129, 87, 0.1); 
            background-color: #fff;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
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
        }

        .dimensions-group .dimension-inputs input {
            flex-grow: 1;
        }

        /* Estilos para el input de tipo file */
        .form-group input[type="file"] {
            border: 2px dashed #a3b18a; /* Borde punteado adaptado del login */
            padding: 10px;
            background-color: #f0f0f0; /* Se mantiene un color de fondo claro */
            cursor: pointer;
            margin-top: 5px;
            border-radius: 8px; /* Borde redondeado */
        }

        .form-group input[type="file"]:hover {
            background-color: #e5e5e5;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #6a6a6a; /* Color de texto para información pequeña */
            font-size: 0.85em;
        }

        /* Acciones del formulario (botones) */
        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }

        .submit-button, .cancel-button {
            padding: 12px 25px;
            border: none;
            border-radius: 10px; /* Radio del botón del login */
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(88, 129, 87, 0.3); /* Sombra de los botones del login */
        }

        .submit-button {
            background: linear-gradient(135deg, #588157 0%, #3a5a40 100%); /* Degradado del botón del login */
            color: white;
        }

        .submit-button:hover {
            background: linear-gradient(135deg, #3a5a40 0%, #2d4732 100%); /* Degradado hover del login */
            transform: translateY(-1px); /* Transformación hover del login */
            box-shadow: 0 8px 25px rgba(88, 129, 87, 0.4); /* Sombra hover del login */
        }

        .cancel-button {
            background-color: #e0e0e0; /* Color gris suave */
            color: #555;
            box-shadow: none; /* Sin sombra tan pronunciada como el submit */
        }

        .cancel-button:hover {
            background-color: #d0d0d0;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        /* Responsive adjustments for the form */
        @media (max-width: 480px) {
            .add-publication-container {
                padding: 30px 20px;
                margin: 20px 15px;
            }
            h1 {
                font-size: 1.8em;
            }
            .submit-button, .cancel-button {
                padding: 10px 20px;
                font-size: 0.9em;
            }
            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .submit-button, .cancel-button {
                width: 100%;
            }
        }

        /* Estilos para el topbar y bottombar (si necesitas adaptar su paleta de colores) */
        .topbar {
            background: linear-gradient(135deg, #6b4226 0%, #8b5a3c 100%); /* Color de tu marca o similar al degradado del título */
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .topbar-icon svg {
            width: 20px;
            height: 20px;
            fill: white;
            transition: transform 0.2s ease-in-out;
        }

        .topbar-icon:hover svg {
            transform: scale(1.1);
        }

        .logout-form button {
            background: none;
            border: 1px solid white;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .logout-form button:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        header {
            background-color: #4e3b2b; /* Un color más oscuro de la paleta */
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Permite que los elementos se envuelvan en pantallas pequeñas */
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        header .logo {
            font-size: 1.8em;
            font-weight: bold;
            color: #fdf6f0; /* Un color claro para el logo */
        }

        .barra-busqueda {
            display: flex;
            flex-grow: 1;
            max-width: 500px;
            background-color: #fffdfc; /* Fondo de la barra de búsqueda similar a inputs */
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #a3b18a;
        }

        .barra-busqueda input {
            border: none;
            padding: 10px 15px;
            flex-grow: 1;
            font-size: 1em;
            color: #4e3b2b;
            background-color: transparent;
            outline: none;
        }

        .barra-busqueda button {
            background-color: #588157; /* Color de acción para el botón de búsqueda */
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .barra-busqueda button:hover {
            background-color: #3a5a40;
        }

        .user-button {
            background-color: #6b584c; /* Otro color de la paleta */
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .user-button:hover {
            background-color: #4e3b2b;
            transform: translateY(-1px);
        }

        /* Bottom bar */
        .bottombar {
            background-color: #4e3b2b; /* Color similar al header */
            color: white;
            padding: 10px 0;
            position: sticky;
            bottom: 0;
            width: 100%;
            display: flex;
            justify-content: space-around;
            align-items: center;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .bottom-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: white;
            font-size: 0.8em;
            font-weight: 500;
            padding: 8px 10px;
            border-radius: 5px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .bottom-button svg {
            margin-bottom: 5px;
        }

        .bottom-button:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-1px);
        }

        .bottom-button-wide {
            flex-grow: 1;
        }

        /* Ensure main content pushes footer down */
        main {
            flex-grow: 1;
        }
    </style>
    
    <script src="../../assets/js/chat-script.js"></script>
    
    <?php include '../../incluye/chat-component.php'; ?>
</head>
<body>

    <div class="topbar">
        <div class="topbar-icon" title="Chat">
            <a href="" onclick="openChatModal()"> 
                <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                    <path d="M12 2c.55 0 1 .45 1 1v1h4a2 2 0 0 1 2 2v2h1a1 1 0 1 1 0 2h-1v6a3 3 0 0 1-3 3h-1v1a1 1 0 1 1-2 0v-1H9v1a1 1 0 1 1-2 0v-1H6a3 3 0 0 1-3-3v-6H2a1 1 0 1 1 0-2h1V6a2 2 0 0 1 2-2h4V3c0-.55.45-1 1-1zm-5 9a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm10 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                </svg>
            </a>
        </div>

        <div class="topbar-icon" title="Chat 2">
            <a href="../chat/chatInicio.php" onclick="openChatModal2()"> 
                <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                </svg>
            </a>
        </div>

        <div class="topbar-icon" title="Perfil">
            <a href="../perfil/mi-perfil.php"> 
                <svg width="16" height="16" fill="white" viewBox="0 0 24 24">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </a>
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
        <div class="barra-busqueda">
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
        <form action="" method="POST" class="publication-form" enctype="multipart/form-data">
            
            <div class="form-group">
                <label for="titulo">Título:</label>
                <input type="text" id="titulo" name="titulo" required>
            </div>

            <div class="form-group">
                <label for="autor">Autor:</label>
                <input type="text" id="autor" name="autor" required>
            </div>

            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion" rows="5"></textarea>
            </div>

            <div class="form-group">
                <label for="editorial">Editorial:</label>
                <input type="text" id="editorial" name="editorial">
            </div>

            <div class="form-group">
                <label for="edicion">Edición:</label>
                <input type="text" id="edicion" name="edicion">
            </div>

            <div class="form-group">
                <label for="categoria">Categoría:</label>
                <input type="text" id="categoria" name="categoria">
            </div>

            <div class="form-group">
                <label for="tipoPublico">Tipo de Público:</label>
                <select id="tipoPublico" name="tipoPublico">
                    <option value="">Seleccione...</option>
                    <option value="General">General</option>
                    <option value="Infantil">Infantil</option>
                    <option value="Juvenil">Juvenil</option>
                    <option value="Adultos">Adultos</option>
                    </select>
            </div>

            <div class="form-group dimensions-group">
                <label>Dimensiones (cm):</label>
                <div class="dimension-inputs">
                    <label for="base">Base:</label>
                    <input type="number" id="base" name="base" step="0.1" min="0">
                    <label for="altura">Altura:</label>
                    <input type="number" id="altura" name="altura" step="0.1" min="0">
                </div>
            </div>

            <div class="form-group">
                <label for="paginas">Número de Páginas:</label>
                <input type="number" id="paginas" name="paginas" min="1">
            </div>

            <div class="form-group">
                <label for="fechaPublicacion">Fecha de Publicación:</label>
                <input type="date" id="fechaPublicacion" name="fechaPublicacion">
            </div>

            <div class="form-group">
                <label for="linkVideo">Enlace de Video (URL):</label>
                <input type="url" id="linkVideo" name="linkVideo" placeholder="https://ejemplo.com/video">
            </div>

            <div class="form-group">
                <label for="linkImagen1">Imagen de Portada (URL o Subir):</label>
                <input type="url" id="linkImagen1" name="linkImagen1" placeholder="https://ejemplo.com/imagen.jpg">
                <input type="file" id="uploadImagen1" name="uploadImagen1" accept="image/*">
                <small>Puedes usar un enlace externo o subir una imagen.</small>
            </div>

            <div class="form-group">
                <label for="linkImagen2">Imagen Adicional 1 (URL o Subir):</label>
                <input type="url" id="linkImagen2" name="linkImagen2" placeholder="https://ejemplo.com/imagen2.jpg">
                <input type="file" id="uploadImagen2" name="uploadImagen2" accept="image/*">
            </div>

            <div class="form-group">
                <label for="linkImagen3">Imagen Adicional 2 (URL o Subir):</label>
                <input type="url" id="linkImagen3" name="linkImagen3" placeholder="https://ejemplo.com/imagen3.jpg">
                <input type="file" id="uploadImagen3" name="uploadImagen3" accept="image/*">
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

    <script src="../../assets/js/publicaciones-script.js"></script>
    <script src="../../assets/js/chat-script.js"></script>
    <script>
        
        function openChatModal() {
            alert('Abriendo chat principal...');
           
        }

        function openChatModal2() {
            alert('Abriendo :D...');
          
        }

      
    </script>
</body>
</html>