<?php
    session_start();

    // Si no se esta logeado manda a inicio de sesión
    if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
        header("Location: ../auth/login.php");
        exit();
    }

    // Usamos un require con los datos para ingresar a la base de datos
    require_once __DIR__ . '/../../config/database.php';

    // Obtenemos el id del usuario, en caso de que sea null, mandamos regreso a login
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
<style>
        /* Estilos para la barra superior */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background-color: #ffffff;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .topbar-logo {
            height: 40px;
            width: auto;
            border-radius: 8px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #4CAF50;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .topbar-icon:hover {
            background-color: #45a049;
        }

        .topbar-icon svg {
            width: 20px;
            height: 20px;
        }

        .logout-btn {
            background-color: #8B4513;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .logout-btn:hover {
            background-color: #7A3D0F;
        }

        /* Ajustar el contenido para la barra superior */
        body {
            padding-top: 60px;
        }

        .chat-app {
            height: calc(100vh - 60px);
        }

        /* Media query para dispositivos móviles */
        @media (max-width: 768px) {
            .topbar {
                padding: 0 15px;
            }
            
            .topbar-logo {
                height: 35px;
            }
            
            .topbar-icon {
                width: 35px;
                height: 35px;
            }
            
            .topbar-icon svg {
                width: 18px;
                height: 18px;
            }
            
            .logout-btn {
                padding: 6px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Barra superior -->
    <div class="topbar">
        <div class="topbar-left">
            <img src="../../assets/images/REELEE.jpeg" alt="RELEE Logo" class="topbar-logo">
        </div>
        <div class="topbar-right">
            <!-- Icono de notificaciones -->
            <a href="#" class="topbar-icon" title="Notificaciones">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
                </svg>
            </a>
            
            <!-- Icono de configuración -->
            <a href="#" class="topbar-icon" title="Configuración">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.82,11.69,4.82,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                </svg>
            </a>
            
            <!-- Icono de perfil -->
            <a href="#" class="topbar-icon" title="Perfil">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </a>
            
            <!-- Botón de cerrar sesión -->
            <button class="logout-btn">Cerrar sesión</button>
        </div>
    </div>

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
                    <!-- Los mensajes van a cargar en esta partecita -->
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
    </div>

    <script>
        // Pasamos el user name al script
        const currentUserId = <?php echo json_encode($userId); ?>;
    </script>
    <script src="../../assets/js/chatUsuarios-script.js"></script>

    <script>
    // Cargar la conversación si hay un parámetro en la URL
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const conversationId = urlParams.get('conversacion');
        
        if (conversationId) {
            const otherUserId = urlParams.get('other_user_id');
            const otherUserName = urlParams.get('other_user_name');
            
            if (otherUserId && otherUserName) {
                // Simular click en la conversación
                openChat(conversationId, otherUserId, otherUserName);
            } else {
                // Buscar la conversación en la lista
                const conversationItem = document.querySelector(`.conversation-item[data-conversation-id="${conversationId}"]`);
                if (conversationItem) {
                    const otherUserId = conversationItem.dataset.otherUserId;
                    const otherUserName = conversationItem.dataset.otherUserName;
                    openChat(conversationId, otherUserId, otherUserName);
                }
            }
        }
    });

    function openChat(conversationId, otherUserId, otherUserName) {
        window.currentConversationId = conversationId; // Guardar ID globalmente
        
        // Mostrar el área de chat activo
        document.getElementById('chatPlaceholder').style.display = 'none';
        const activeChat = document.getElementById('activeChat');
        activeChat.style.display = 'block';
        
        // Establecer información del usuario
        document.getElementById('chatUserName').textContent = otherUserName;
        document.getElementById('chatUserAvatar').textContent = otherUserName.charAt(0).toUpperCase();
        
        // Cargar los mensajes
        loadMessages(conversationId);
    }
    
    function loadMessages(conversationId) {
        // Implementa esta función para cargar los mensajes usando AJAX
        console.log("Cargando mensajes para la conversación: " + conversationId);
        // Aquí deberías hacer una petición a get_messages.php
    }

    function loadMessages(conversationId) {
        fetch('../../api/get_messages.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'conversacion_id=' + conversationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMessages(data.messages);
            } else {
                console.error('Error al cargar mensajes:', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    document.addEventListener('click', function(e) {
    if (e.target.closest('.user-result')) {
        const userResult = e.target.closest('.user-result');
        const userId = userResult.dataset.userid;
        const userName = userResult.dataset.username;
        
        if (userId && userName) {
            abrirChat(parseInt(userId), userName);
        }
    }
});

    function displayMessages(messages) {
        const container = document.getElementById('messagesContainer');
        container.innerHTML = '';
        
        messages.forEach(message => {
            const messageDiv = document.createElement('div');
            messageDiv.className = message.idRemitente == currentUserId ? 'message-sent' : 'message-received';
            messageDiv.innerHTML = `
                <div class="message-content">${message.contenido}</div>
                <div class="message-time">${new Date(message.fechaEnvio).toLocaleTimeString()}</div>
            `;
            container.appendChild(messageDiv);
        });
        
        container.scrollTop = container.scrollHeight;
    }

    // Agregar event listener para enviar mensajes
    document.getElementById('sendButton').addEventListener('click', function() {
        const messageText = document.getElementById('messageText');
        const content = messageText.value.trim();
        
        if (content && window.currentConversationId) {
            fetch('../../api/send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `conversacion_id=${window.currentConversationId}&contenido=${encodeURIComponent(content)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageText.value = '';
                    loadMessages(window.currentConversationId);
                } else {
                    alert('Error al enviar mensaje: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    });

document.addEventListener('DOMContentLoaded', function() {
    // Si hay una conversación en la URL, seleccionarla automáticamente
    const urlParams = new URLSearchParams(window.location.search);
    const conversacionId = urlParams.get('conversacion');
    
    if (conversacionId) {
        const conversationItem = document.querySelector(`[data-conversation-id="${conversacionId}"]`);
        if (conversationItem) {
            conversationItem.click();
        }
    }
});

let lastClickTime = 0;
        document.addEventListener('click', function(e) {
            const now = Date.now();
            if (now - lastClickTime < 300) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            lastClickTime = now;
        }, true);
</script>
</body>
</html>