<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$currentUserId = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'amigos';
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : $currentUserId;

$title = '';
$users = [];

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");

    if ($type === 'amigos') {
        $title = 'Amigos';
        $stmt = $conn->prepare("
            SELECT u.idUsuario, u.nombre, u.userName, u.fotoPerfil 
            FROM Amistades a
            JOIN Usuarios u ON (a.idUsuarioSolicitante = u.idUsuario OR a.idUsuarioReceptor = u.idUsuario)
            WHERE (a.idUsuarioSolicitante = ? OR a.idUsuarioReceptor = ?)
            AND a.estado = 'aceptada'
            AND u.idUsuario != ?
        ");
        $stmt->bind_param("iii", $profileId, $profileId, $profileId);
        
    } elseif ($type === 'seguidores') {
        $title = 'Seguidores';
        $stmt = $conn->prepare("
            SELECT u.idUsuario, u.nombre, u.userName, u.fotoPerfil 
            FROM Seguidores s
            JOIN Usuarios u ON s.idUsuarioSeguidor = u.idUsuario
            WHERE s.idUsuarioSeguido = ?
        ");
        $stmt->bind_param("i", $profileId);
        
    } elseif ($type === 'siguiendo') {
        $title = 'Siguiendo';
        $stmt = $conn->prepare("
            SELECT u.idUsuario, u.nombre, u.userName, u.fotoPerfil 
            FROM Seguidores s
            JOIN Usuarios u ON s.idUsuarioSeguido = u.idUsuario
            WHERE s.idUsuarioSeguidor = ?
        ");
        $stmt->bind_param("i", $profileId);
    }

    if (isset($stmt)) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> | RELEE</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f6f3;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        .back-btn {
            text-decoration: none;
            color: #6b4226;
            font-weight: bold;
            margin-right: 15px;
        }
        .user-list {
            display: grid;
            gap: 15px;
        }
        .user-card {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            text-decoration: none;
            color: inherit;
            transition: background 0.2s;
        }
        .user-card:hover {
            background: #f9f9f9;
        }
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }
        .user-info div:first-child {
            font-weight: bold;
            font-size: 1.1em;
        }
        .user-info div:last-child {
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="javascript:history.back()" class="back-btn">← Volver</a>
            <h2><?php echo $title; ?></h2>
        </div>

        <div class="user-list">
            <?php if (empty($users)): ?>
                <p style="text-align: center; color: #666;">No hay usuarios en esta lista.</p>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <?php 
                        $avatar = $user['fotoPerfil'];
                        if (empty($avatar) || $avatar === 'default-avatar.png') {
                            $avatar = 'assets/images/default-avatar.png';
                        } elseif (!str_starts_with($avatar, 'http') && !str_starts_with($avatar, '../')) {
                            $avatar = 'uploads/' . $avatar;
                        }
                    ?>
                    <a href="pages/perfil_usuario.php?id=<?php echo $user['idUsuario']; ?>" class="user-card">
                        <img src="<?php echo $avatar; ?>" class="user-avatar" onerror="this.src='assets/images/default-avatar.png'">
                        <div class="user-info">
                            <div><?php echo htmlspecialchars($user['nombre']); ?></div>
                            <div>@<?php echo htmlspecialchars($user['userName']); ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>