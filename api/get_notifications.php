<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        throw new Exception("Error de conexión");
    }

    // Mark as read if requested
    if (isset($_POST['action']) && $_POST['action'] === 'mark_read') {
        $stmt = $conn->prepare("UPDATE Notificaciones SET leida = 1 WHERE idUsuario = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit();
    }

    // Get unread notifications
    $stmt = $conn->prepare("
        SELECT n.*, u.nombre as emisorNombre, u.fotoPerfil as emisorFoto
        FROM Notificaciones n
        JOIN Usuarios u ON n.idUsuarioEmisor = u.idUsuario
        WHERE n.idUsuario = ?
        ORDER BY n.fechaCreacion DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    $unreadCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        if ($row['leida'] == 0) $unreadCount++;
        
        // Fix photo path
        $photo = $row['emisorFoto'];
        if (empty($photo) || $photo === 'default-avatar.png') {
            $photo = '../assets/images/default-avatar.png';
        } elseif (!str_starts_with($photo, 'http') && !str_starts_with($photo, '../')) {
             $photo = '../uploads/' . $photo;
        }

        $notifications[] = [
            'id' => $row['idNotificacion'],
            'type' => $row['tipo'],
            'content' => $row['contenido'],
            'senderName' => $row['emisorNombre'],
            'senderPhoto' => $photo,
            'read' => (bool)$row['leida'],
            'date' => $row['fechaCreacion'],
            'link' => getNotificationLink($row['tipo'], $row['idReferencia'], $row['idUsuarioEmisor'])
        ];
    }

    // Count unread messages
    $msgStmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM Mensajes m
        JOIN Conversaciones c ON m.idConversacion = c.idConversacion
        WHERE (c.idUsuario1 = ? OR c.idUsuario2 = ?)
          AND m.idRemitente != ?
          AND m.leido = 0
    ");
    $msgStmt->bind_param("iii", $userId, $userId, $userId);
    $msgStmt->execute();
    $unreadMessages = $msgStmt->get_result()->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true, 
        'notifications' => $notifications,
        'unreadCount' => $unreadCount,
        'unreadMessages' => $unreadMessages
    ]);
    
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getNotificationLink($type, $refId, $senderId) {
    switch ($type) {
        case 'amistad':
            return "perfil_usuario.php?id=" . $senderId;
        case 'like':
        case 'comentario':
            return "home.php?post=" . $refId; // Ideally anchor to post
        case 'mensaje':
            return "chat/chat.php?user_id=" . $senderId;
        default:
            return "#";
    }
}
?>