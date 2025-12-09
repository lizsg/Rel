<?php
session_start();

if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';
$currentUserId = $_SESSION['user_id'];
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($profileId === 0) {
    header("Location: home.php");
    exit();
}

// Si el usuario intenta ver su propio perfil, lo redirigimos a su perfil editable
// Opcional: Podríamos permitirle ver su perfil "como visitante" comentando esto.
/*
if ($profileId === $currentUserId) {
    header("Location: auth/perfil.php");
    exit();
}
*/

$usuario = [];
$publicaciones = [];
$amistad = null;
$siguiendo = false;
$errorMessage = '';

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión");
    }

    // 1. Obtener datos del usuario
    $userStmt = $conn->prepare("SELECT * FROM Usuarios WHERE idUsuario = ?");
    $userStmt->bind_param("i", $profileId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if ($userResult->num_rows === 0) {
        throw new Exception("Usuario no encontrado");
    }
    
    $usuario = $userResult->fetch_assoc();

    // 2. Verificar amistad
    $amistadStmt = $conn->prepare("
        SELECT * FROM Amistades 
        WHERE (idUsuarioSolicitante = ? AND idUsuarioReceptor = ?) 
           OR (idUsuarioSolicitante = ? AND idUsuarioReceptor = ?)
    ");
    $amistadStmt->bind_param("iiii", $currentUserId, $profileId, $profileId, $currentUserId);
    $amistadStmt->execute();
    $amistadResult = $amistadStmt->get_result();
    if ($amistadResult->num_rows > 0) {
        $amistad = $amistadResult->fetch_assoc();
    }

    // 3. Verificar seguimiento
    $segStmt = $conn->prepare("SELECT 1 FROM Seguidores WHERE idUsuarioSeguidor = ? AND idUsuarioSeguido = ?");
    $segStmt->bind_param("ii", $currentUserId, $profileId);
    $segStmt->execute();
    $siguiendo = $segStmt->get_result()->num_rows > 0;

    // 3.5 Obtener contadores
    $countSeguidores = $conn->query("SELECT COUNT(*) as c FROM Seguidores WHERE idUsuarioSeguido = $profileId")->fetch_assoc()['c'];
    $countSiguiendo = $conn->query("SELECT COUNT(*) as c FROM Seguidores WHERE idUsuarioSeguidor = $profileId")->fetch_assoc()['c'];
    $countAmigos = $conn->query("SELECT COUNT(*) as c FROM Amistades WHERE (idUsuarioSolicitante = $profileId OR idUsuarioReceptor = $profileId) AND estado = 'aceptada'")->fetch_assoc()['c'];

    // 4. Obtener publicaciones (estados)
    $pubStmt = $conn->prepare("
        SELECT 
            ps.idPublicacionSocial as id,
            ps.contenido,
            ps.imagen,
            ps.fechaCreacion,
            (SELECT COUNT(*) FROM LikesSocial ls WHERE ls.idPublicacionSocial = ps.idPublicacionSocial) as totalLikes,
            (SELECT COUNT(*) FROM LikesSocial ls WHERE ls.idPublicacionSocial = ps.idPublicacionSocial AND ls.idUsuario = ?) as likedByMe,
            (SELECT COUNT(*) FROM ComentariosSocial cs WHERE cs.idPublicacionSocial = ps.idPublicacionSocial) as totalComentarios
        FROM PublicacionesSocial ps
        WHERE ps.idUsuario = ?
        ORDER BY ps.fechaCreacion DESC
    ");
    $pubStmt->bind_param("ii", $currentUserId, $profileId);
    $pubStmt->execute();
    $resultPubs = $pubStmt->get_result();
    while ($row = $resultPubs->fetch_assoc()) {
        $publicaciones[] = $row;
    }

    $conn->close();

} catch (Exception $e) {
    $errorMessage = "Error: " . $e->getMessage();
}

function tiempoTranscurrido($fecha) {
    $timestamp = strtotime($fecha);
    $diferencia = time() - $timestamp;
    
    if ($diferencia < 60) return "Hace " . $diferencia . " segundos";
    if ($diferencia < 3600) return "Hace " . floor($diferencia / 60) . " minutos";
    if ($diferencia < 86400) return "Hace " . floor($diferencia / 3600) . " horas";
    if ($diferencia < 604800) return "Hace " . floor($diferencia / 86400) . " días";
    return date("d/m/Y", $timestamp);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de <?php echo htmlspecialchars($usuario['nombre']); ?> | RELEE</title>
    <style>
        :root {
            --primary-brown: #6b4226;
            --secondary-brown: #8b5a3c;
            --light-brown: #d6c1b2;
            --cream-bg: #f8f6f3;
            --green-primary: #a3b18a;
            --green-secondary: #588157;
            --text-primary: #2c2016;
            --text-secondary: #6f5c4d;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--cream-bg);
            color: var(--text-primary);
            margin: 0;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }

        .cover-photo {
            height: 300px;
            background: linear-gradient(135deg, var(--light-brown) 0%, #c4a68a 100%);
            position: relative;
        }
        .cover-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-header {
            padding: 0 30px 30px;
            position: relative;
        }

        .profile-avatar-container {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            border: 5px solid white;
            overflow: hidden;
            margin-top: -80px;
            position: relative;
            background: linear-gradient(135deg, var(--green-primary) 0%, var(--green-secondary) 100%);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .profile-avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            margin-top: 15px;
        }
        .profile-name {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .profile-username {
            color: var(--text-secondary);
            font-size: 1.1em;
        }
        .profile-bio {
            margin-top: 15px;
            max-width: 600px;
            line-height: 1.5;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 0.95em;
        }
        .btn-primary {
            background: var(--primary-brown);
            color: white;
        }
        .btn-secondary {
            background: #e4e6eb;
            color: #050505;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .feed-section {
            padding: 30px;
            background: #f0f2f5;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .card-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        .card-meta div:first-child {
            font-weight: 600;
        }
        .card-meta div:last-child {
            font-size: 0.85em;
            color: #65676b;
        }
        .card-content {
            font-size: 1.1em;
            margin-bottom: 15px;
        }
        .card-image img {
            width: 100%;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .top-nav {
            padding: 15px;
            background: white;
            border-bottom: 1px solid #eee;
        }
        .back-link {
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="top-nav">
        <a href="home.php" class="back-link">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            Volver al Inicio
        </a>
    </div>

    <?php if (!empty($errorMessage)): ?>
        <div style="padding: 20px; color: red; text-align: center;"><?php echo $errorMessage; ?></div>
    <?php else: ?>
        <div class="cover-photo">
            <img src="<?php echo !empty($usuario['fotoPortada']) && $usuario['fotoPortada'] !== 'default-cover.png' ? '../uploads/' . $usuario['fotoPortada'] : '../assets/images/default-cover.png'; ?>" 
                 onerror="this.src='../assets/images/default-cover.png'">
        </div>

        <div class="profile-header">
            <div class="profile-avatar-container">
                <img src="<?php echo !empty($usuario['fotoPerfil']) && $usuario['fotoPerfil'] !== 'default-avatar.png' ? '../uploads/' . $usuario['fotoPerfil'] : '../assets/images/default-avatar.png'; ?>" 
                     class="profile-avatar"
                     onerror="this.src='../assets/images/default-avatar.png'">
            </div>

            <div class="profile-info">
                <div class="profile-name"><?php echo htmlspecialchars($usuario['nombre']); ?></div>
                <div class="profile-username">@<?php echo htmlspecialchars($usuario['userName']); ?></div>
                
                <div class="profile-stats" style="display: flex; gap: 20px; margin: 15px 0;">
                    <div style="text-align: center; cursor: pointer;" onclick="window.location.href='lista_usuarios.php?type=seguidores&id=<?php echo $profileId; ?>'">
                        <span style="display: block; font-weight: bold; font-size: 1.2em; color: var(--green-secondary);"><?php echo $countSeguidores; ?></span>
                        <span style="font-size: 0.9em; color: #666;">Seguidores</span>
                    </div>
                    <div style="text-align: center; cursor: pointer;" onclick="window.location.href='lista_usuarios.php?type=siguiendo&id=<?php echo $profileId; ?>'">
                        <span style="display: block; font-weight: bold; font-size: 1.2em; color: var(--green-secondary);"><?php echo $countSiguiendo; ?></span>
                        <span style="font-size: 0.9em; color: #666;">Siguiendo</span>
                    </div>
                    <div style="text-align: center; cursor: pointer;" onclick="window.location.href='lista_usuarios.php?type=amigos&id=<?php echo $profileId; ?>'">
                        <span style="display: block; font-weight: bold; font-size: 1.2em; color: var(--green-secondary);"><?php echo $countAmigos; ?></span>
                        <span style="font-size: 0.9em; color: #666;">Amigos</span>
                    </div>
                </div>

                <?php if (!empty($usuario['biografia'])): ?>
                    <div class="profile-bio"><?php echo nl2br(htmlspecialchars($usuario['biografia'])); ?></div>
                <?php endif; ?>

                <div class="action-buttons">
                    <!-- Botón de Amistad -->
                    <?php if (!$amistad): ?>
                        <button class="btn btn-primary" onclick="sendFriendRequest(<?php echo $profileId; ?>)">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            Agregar amigo
                        </button>
                    <?php elseif ($amistad['estado'] === 'pendiente' && $amistad['idUsuarioSolicitante'] == $currentUserId): ?>
                        <button class="btn btn-secondary" disabled>Solicitud enviada</button>
                    <?php elseif ($amistad['estado'] === 'pendiente' && $amistad['idUsuarioReceptor'] == $currentUserId): ?>
                        <button class="btn btn-primary" onclick="acceptFriendRequest(<?php echo $profileId; ?>)">Aceptar solicitud</button>
                    <?php elseif ($amistad['estado'] === 'aceptada'): ?>
                        <button class="btn btn-secondary">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                            Amigos
                        </button>
                    <?php endif; ?>

                    <!-- Botón de Seguir -->
                    <button class="btn <?php echo $siguiendo ? 'btn-secondary' : 'btn-primary'; ?>" onclick="toggleFollow(<?php echo $profileId; ?>)">
                        <?php echo $siguiendo ? 'Siguiendo' : 'Seguir'; ?>
                    </button>

                    <!-- Botón de Mensaje -->
                    <a href="chat/chat.php?user_id=<?php echo $profileId; ?>" class="btn btn-secondary">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
                        Mensaje
                    </a>
                </div>
            </div>
        </div>

        <div class="feed-section">
            <h3>Publicaciones</h3>
            <?php if (empty($publicaciones)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">Este usuario aún no ha publicado nada.</p>
            <?php else: ?>
                <?php foreach ($publicaciones as $pub): ?>
                    <div class="card">
                        <div class="card-header">
                            <img src="<?php echo !empty($usuario['fotoPerfil']) && $usuario['fotoPerfil'] !== 'default-avatar.png' ? '../uploads/' . $usuario['fotoPerfil'] : '../assets/images/default-avatar.png'; ?>" 
                                 class="card-avatar"
                                 onerror="this.src='../assets/images/default-avatar.png'">
                            <div class="card-meta">
                                <div><?php echo htmlspecialchars($usuario['nombre']); ?></div>
                                <div><?php echo tiempoTranscurrido($pub['fechaCreacion']); ?></div>
                            </div>
                        </div>
                        <div class="card-content">
                            <?php echo nl2br(htmlspecialchars($pub['contenido'])); ?>
                        </div>
                        <?php if (!empty($pub['imagen'])): ?>
                            <div class="card-image">
                                <img src="../<?php echo htmlspecialchars($pub['imagen']); ?>" loading="lazy">
                            </div>
                        <?php endif; ?>
                        
                        <!-- Interactive Footer -->
                        <div class="card-actions" style="display: flex; gap: 15px; padding-top: 15px; border-top: 1px solid #eee; margin-top: 15px;">
                            <button class="action-btn like-btn <?php echo $pub['likedByMe'] ? 'active' : ''; ?>" 
                                    onclick="toggleLike(<?php echo $pub['id']; ?>, this)"
                                    style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; color: <?php echo $pub['likedByMe'] ? '#e74c3c' : 'var(--text-secondary)'; ?>; font-size: 0.95em;">
                                <svg width="20" height="20" fill="<?php echo $pub['likedByMe'] ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                </svg>
                                <span class="like-count"><?php echo $pub['totalLikes']; ?></span>
                            </button>

                            <button class="action-btn comment-btn" 
                                    onclick="toggleComments(<?php echo $pub['id']; ?>)"
                                    style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; color: var(--text-secondary); font-size: 0.95em;">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                </svg>
                                <span class="comment-count"><?php echo $pub['totalComentarios']; ?></span>
                            </button>
                        </div>

                        <!-- Comments Section -->
                        <div id="comments-<?php echo $pub['id']; ?>" class="comments-section" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                            <div class="comments-list" id="comments-list-<?php echo $pub['id']; ?>"></div>
                            <div class="comment-input-wrapper" style="display: flex; gap: 10px; margin-top: 10px;">
                                <input type="text" id="comment-input-<?php echo $pub['id']; ?>" 
                                       placeholder="Escribe un comentario..." 
                                       style="flex: 1; padding: 8px 12px; border: 1px solid #ddd; border-radius: 20px; outline: none;">
                                <button onclick="postComment(<?php echo $pub['id']; ?>)"
                                        style="background: #4a90e2; color: white; border: none; padding: 8px 15px; border-radius: 20px; cursor: pointer; font-weight: 600;">
                                    Enviar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function sendFriendRequest(userId) {
    fetch('../api/user_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=add_friend&id=${userId}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function acceptFriendRequest(userId) {
    fetch('../api/user_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=accept_friend&id=${userId}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            location.reload();
        }
    });
}

function toggleFollow(userId) {
    const btn = document.querySelector('button[onclick^="toggleFollow"]');
    const isFollowing = btn.classList.contains('btn-secondary');
    const action = isFollowing ? 'unfollow' : 'follow';
    
    fetch('../api/user_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=${action}&id=${userId}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            location.reload();
        }
    });
}

// Social Interactions
function toggleLike(postId, btn) {
    const isLiked = btn.classList.contains('active');
    const action = isLiked ? 'unlike' : 'like';
    
    fetch('../api/social_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=${action}&id=${postId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            btn.classList.toggle('active');
            btn.querySelector('.like-count').textContent = data.likes;
            const icon = btn.querySelector('svg');
            if (data.liked) {
                btn.style.color = '#e74c3c';
                icon.setAttribute('fill', 'currentColor');
            } else {
                btn.style.color = 'var(--text-secondary)';
                icon.setAttribute('fill', 'none');
            }
        }
    })
    .catch(console.error);
}

function toggleComments(postId) {
    const section = document.getElementById(`comments-${postId}`);
    const list = document.getElementById(`comments-list-${postId}`);
    
    if (section.style.display === 'none') {
        section.style.display = 'block';
        
        // Mostrar indicador de carga
        list.innerHTML = '<div style="padding:10px; text-align:center; color:#666;">Cargando comentarios...</div>';
        
        fetch('../api/social_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_comments&id=${postId}`
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                list.innerHTML = '';
                if(data.comments.length === 0) {
                    list.innerHTML = '<div style="padding:10px; text-align:center; color:#999; font-style:italic;">Sé el primero en comentar</div>';
                }
                data.comments.forEach(c => {
                    const html = `
                        <div class="comment" style="margin-bottom: 10px; padding: 10px; background: #f0f2f5; border-radius: 12px;">
                            <div style="font-weight: 700; font-size: 0.9em; color: #333; margin-bottom: 2px;">${c.author}</div>
                            <div style="font-size: 0.95em; color: #1c1e21; line-height: 1.4;">${c.content}</div>
                        </div>
                    `;
                    list.insertAdjacentHTML('beforeend', html);
                });
            }
        })
        .catch(err => {
            console.error(err);
            list.innerHTML = '<div style="color:red; padding:10px;">Error al cargar comentarios</div>';
        });
    } else {
        section.style.display = 'none';
    }
}

function postComment(postId) {
    const input = document.getElementById(`comment-input-${postId}`);
    const content = input.value.trim();
    
    if (!content) return;
    
    fetch('../api/social_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=comment&id=${postId}&content=${encodeURIComponent(content)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            const list = document.getElementById(`comments-list-${postId}`);
            const commentHtml = `
                <div class="comment" style="margin-bottom: 10px; padding: 8px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-weight: 600; font-size: 0.9em;">${data.comment.author}</div>
                    <div style="font-size: 0.95em;">${data.comment.content}</div>
                </div>
            `;
            list.insertAdjacentHTML('beforeend', commentHtml);
            
            // Update count
            const countSpan = document.querySelector(`button[onclick="toggleComments(${postId})"] .comment-count`);
            countSpan.textContent = parseInt(countSpan.textContent) + 1;
        }
    })
    .catch(console.error);
}
</script>

</body>
</html>