<?php
session_start();

// Si no se esta logeado manda a inicio de sesión
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Usamos un require con los datos para ingresar a la base de datos
require_once __DIR__ . '/../../config/database.php';

// Obtenemos el id del usuario, en caso de que sea null, mandar a login
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header("Location: ../auth/login.php");
    exit();
}

// Declaraamos las variables para la búsqueda de usuarios
$usuariosEncontrados = [];
$error = null;
$searchTerm = '';

try {
    // Establecemos la conexion a la base de datos
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }

    // Procesamos la búsqueda de usuarios en caso de que se busque
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["buscador"])) {
        $searchTerm = trim($_POST["buscador"]);
        if (!empty($searchTerm)) {
            //preparamos la busquedo con los % para que no marque error la consulta
            $parametroBusqueda = "%" . $searchTerm . "%";
            
            // Buscamos id y userName de los usuarios que tengan un user parecido a lo que ingresamos
            // pero que mo tenga nuestra misma id, para evitar chats a uno mismo y que marque error
            $stmt = $conn->prepare("SELECT idUsuario, userName FROM Usuarios WHERE userName LIKE ? AND idUsuario != ?");
            
            // Preparamos la consulta especifcando que vamos a meter un s -> String y un i -> int
            $stmt->bind_param("si", $parametroBusqueda, $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            // Verificamos si hay resultado, en caso de que haya, se despliega una lista
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $usuariosEncontrados[] = $row;
                }
            } else {
                $error = "No se encontró ningún usuario con ese nombre";
            }

            // cerramos conexión
            $stmt->close();
        } else { // si no se ha mandado nada
            $error = "Ingresa un nombre para buscar";
        }
    } // Fin buscar usuarios

    // Declaramos la variable para almacenar las conversaciones existentes
    $conversaciones = [];

    //preparamos la sentencia que mas o menos es:
    /*
        Aqui seleccionamos informacion de de la tabla de Conversación con el
        alias de c y datos relacionados de otras tablas.
        El left Join con las tablas de usuarios con el alias de u1 se hace para
        obtner el nombre del primer usuario en la conversación.
        El otro left Join con alias u2 es para sacar el el segundo nombre del 
        segundo usuario.

        Y por ultimo el Left Join de mensajes une con la tabla de mensajes con el
        alias m para obtener la información del ultimo mensaje, con la condición
        de que el id de la conversacion de parte de mensaje sea el mismo que por
        parte de las conversaciones, esto asegura que el mensaje si sea de la
        conersacion, con select max sacamos el mesaje mas reciente y asi se unen
        todos los mensajes de la conversacion.

        Ahora esto se hara con el filtrado donde el id corresponda al usuario
        ya sea como el primero o el segundo y finalmeente se ordenan por fecha
        del ultimo mensqaje, si no hay mensajes por la fecha de cuando se creo la 
        conversacon y con DESC tenemos los mas recientes primero.
    */
    $stmt = $conn->prepare("
        SELECT c.idConversacion, c.idUsuario1, c.idUsuario2, c.ultimoMensaje,
               u1.userName as userName1, u2.userName as userName2,
               m.contenido as ultimoMensajeTexto, m.fechaEnvio as fechaUltimoMensaje
        FROM Conversaciones c
        LEFT JOIN Usuarios u1 ON c.idUsuario1 = u1.idUsuario
        LEFT JOIN Usuarios u2 ON c.idUsuario2 = u2.idUsuario
        LEFT JOIN Mensajes m ON m.idConversacion = c.idConversacion 
            AND m.fechaEnvio = (
                SELECT MAX(fechaEnvio) 
                FROM Mensajes 
                WHERE idConversacion = c.idConversacion
            )
        WHERE c.idUsuario1 = ? OR c.idUsuario2 = ?
        ORDER BY COALESCE(m.fechaEnvio, c.fechaCreacion) DESC
    ");

    //Finalmente aqui ponemos los id del usuario, ejecutamos y tenemos resultados
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Ahora, si hay un resultado con fetch_assoc recorremos cada uno de los registros
    while ($row = $result->fetch_assoc()) {
        // Si el id del usuario es el mismo que el uno, entonces el otro usuario es el dos
        $otherUserName = ($row['idUsuario1'] == $userId) ? $row['userName2'] : $row['userName1'];
        // Con la misma logica se saca el id del otro usuario
        $otherUserId = ($row['idUsuario1'] == $userId) ? $row['idUsuario2'] : $row['idUsuario1'];
        
        // Guardamos las conversaciones en esta matriz que guarda todos los datos recabados
        $conversaciones[] = [
            'idConversacion' => $row['idConversacion'],
            'otherUserId' => $otherUserId,
            'otherUserName' => $otherUserName,
            'ultimoMensaje' => $row['ultimoMensajeTexto'] ?? 'Nueva conversación', // Si no hay mensaje manda 'nueva conversacion'
            'fechaUltimoMensaje' => $row['fechaUltimoMensaje'] ?? $row['ultimoMensaje']
        ];
    }
    
    $conn->close();
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Chat | RELEE</title>
    <link rel="stylesheet" href="../../assets/css/chatUsuarios-styles.css">
</head>
<body>
    <div class="chat-app">
        <!-- Sidebar con conversaciones -->
        <div class="chat-sidebar">
            <!-- Header del sidebar -->
            <div class="sidebar-header">
                <div class="logo">RELEE Chat</div>
                <button class="new-chat-btn" id="newChatBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                </button>
            </div>

            <!-- Buscador (inicialmente oculto hasta que se da click) -->
            <div class="search-section" id="searchSection" style="display: none;">
                <form method="POST" action="" id="searchForm">
                    <div class="search-bar">
                        <input type="text" name="buscador" placeholder="Buscar usuario..." 
                               value="<?php echo htmlspecialchars($searchTerm); ?>" id="searchInput">
                        <button type="submit">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                            </svg>
                        </button>
                    </div>
                    <button type="button" class="cancel-search" id="cancelSearch">Cancelar</button>
                </form>

                <!-- Resultados de búsqueda -->
                <?php if (!empty($usuariosEncontrados)): ?>
                    <div class="search-results">
                        <h3>Usuarios encontrados</h3>
                        <?php foreach($usuariosEncontrados as $usuario): ?>
                            <div class="user-result" data-userid="<?php echo $usuario['idUsuario']; ?>" 
                                 data-username="<?php echo htmlspecialchars($usuario['userName']); ?>">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($usuario['userName'], 0, 1)); ?>
                                </div>
                                <div class="user-info">
                                    <span class="user-name"><?php echo htmlspecialchars($usuario['userName']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif(isset($error) && !empty($searchTerm)): ?>
                    <div class="search-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
            </div>

            <!-- Lista de conversaciones -->
            <div class="conversations-list" id="conversationsList">
                <?php if (empty($conversaciones)): ?>
                    <div class="no-conversations">
                        <p>No tienes conversaciones aún</p>
                        <button class="start-chat-btn" id="startChatBtn">Iniciar nueva conversación</button>
                    </div>
                <?php else: ?>
                    <?php foreach($conversaciones as $conv): ?>
                        <div class="conversation-item" 
                             data-conversation-id="<?php echo $conv['idConversacion']; ?>"
                             data-other-user-id="<?php echo $conv['otherUserId']; ?>"
                             data-other-user-name="<?php echo htmlspecialchars($conv['otherUserName']); ?>">
                            <div class="conversation-avatar">
                                <?php echo strtoupper(substr($conv['otherUserName'], 0, 1)); ?>
                            </div>
                            <div class="conversation-info">
                                <div class="conversation-name"><?php echo htmlspecialchars($conv['otherUserName']); ?></div>
                                <div class="conversation-preview">
                                    <?php echo htmlspecialchars(substr($conv['ultimoMensaje'], 0, 30)); ?>
                                    <?php if (strlen($conv['ultimoMensaje']) > 30) echo '...'; ?>
                                </div>
                            </div>
                            <div class="conversation-time">
                                <?php 
                                if ($conv['fechaUltimoMensaje']) {
                                    $date = new DateTime($conv['fechaUltimoMensaje']);
                                    echo $date->format('H:i');
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Área de chat -->
        <div class="chat-area">
            <div class="chat-placeholder" id="chatPlaceholder">
                <div class="placeholder-content">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="currentColor" opacity="0.3">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <h3>Selecciona una conversación</h3>
                    <p>Elige una conversación existente o inicia una nueva</p>
                </div>
            </div>

            <!-- Chat activo (inicialmente oculto hasta que se da click como el buscador) -->
            <div class="active-chat" id="activeChat" style="display: none;">
                <div class="chat-header">
                    <button class="back-button" id="backButton">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                        </svg>
                    </button>
                    <div class="chat-user-info">
                        <div class="chat-user-avatar" id="chatUserAvatar"></div>
                        <div>
                            <div class="chat-user-name" id="chatUserName"></div>
                            <div class="chat-user-status">En línea</div>
                        </div>
                    </div>
                </div>

                <div class="messages-container" id="messagesContainer">
                    <!-- Los mensajes se cargarán aquí -->
                </div>

                <div class="message-input-container">
                    <div class="message-input">
                        <textarea id="messageText" placeholder="Escribe tu mensaje..." rows="1"></textarea>
                        <button id="sendButton" type="button">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bottombar">
        <a href="../home.php" class="bottom-button">
            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
            </svg>
            <span>Inicio</span>
        </a>
        <a href="../products/publicaciones.php" class="bottom-button bottom-button-wide">
            <span>Mis Publicaciones</span>
        </a>
        <button class="bottom-button">
            <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
                <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
            </svg>
            <span>Menú</span>
        </button>
    </div>

    <script>
        // Pasamos el user name al script
        const currentUserId = <?php echo json_encode($userId); ?>;
    </script>
    <script src="../../assets/js/chatUsuarios-script.js"></script>
</body>
</html>