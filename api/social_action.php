<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/notification_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$postId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$postId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

// Re-establish connection if needed
if (!isset($conn) || $conn->connect_error) {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
}

try {
    if ($action === 'like') {
        $stmt = $conn->prepare("INSERT IGNORE INTO LikesSocial (idPublicacionSocial, idUsuario) VALUES (?, ?)");
        $stmt->bind_param("ii", $postId, $userId);
        $stmt->execute();
        $liked = $stmt->affected_rows > 0;
        
        if ($liked) {
            // Get post owner
            $ownerStmt = $conn->prepare("SELECT idUsuario FROM PublicacionesSocial WHERE idPublicacionSocial = ?");
            $ownerStmt->bind_param("i", $postId);
            $ownerStmt->execute();
            $ownerRes = $ownerStmt->get_result()->fetch_assoc();
            if ($ownerRes) {
                createNotification($conn, $ownerRes['idUsuario'], $userId, 'like', $postId, 'le gustó tu publicación');
            }
        }
        
        // Get new count
        $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM LikesSocial WHERE idPublicacionSocial = ?");
        $countStmt->bind_param("i", $postId);
        $countStmt->execute();
        $result = $countStmt->get_result()->fetch_assoc();
        
        echo json_encode(['success' => true, 'likes' => $result['total'], 'liked' => true]);
        
    } elseif ($action === 'unlike') {
        $stmt = $conn->prepare("DELETE FROM LikesSocial WHERE idPublicacionSocial = ? AND idUsuario = ?");
        $stmt->bind_param("ii", $postId, $userId);
        $stmt->execute();
        
        // Get new count
        $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM LikesSocial WHERE idPublicacionSocial = ?");
        $countStmt->bind_param("i", $postId);
        $countStmt->execute();
        $result = $countStmt->get_result()->fetch_assoc();
        
        echo json_encode(['success' => true, 'likes' => $result['total'], 'liked' => false]);
        
    } elseif ($action === 'comment') {
        $content = trim($_POST['content'] ?? '');
        if (empty($content)) {
            throw new Exception("El comentario no puede estar vacío");
        }
        
        $stmt = $conn->prepare("INSERT INTO ComentariosSocial (idPublicacionSocial, idUsuario, contenido) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $postId, $userId, $content);
        
        if ($stmt->execute()) {
            // Notify post owner
            $ownerStmt = $conn->prepare("SELECT idUsuario FROM PublicacionesSocial WHERE idPublicacionSocial = ?");
            $ownerStmt->bind_param("i", $postId);
            $ownerStmt->execute();
            $ownerRes = $ownerStmt->get_result()->fetch_assoc();
            if ($ownerRes) {
                createNotification($conn, $ownerRes['idUsuario'], $userId, 'comentario', $postId, 'comentó tu publicación');
            }

            // Return the new comment data for UI update
            $commentId = $conn->insert_id;
            $userStmt = $conn->prepare("SELECT nombre FROM Usuarios WHERE idUsuario = ?");
            $userStmt->bind_param("i", $userId);
            $userStmt->execute();
            $userRes = $userStmt->get_result()->fetch_assoc();
            $userName = $userRes['nombre'] ?? 'Usuario';
            
            echo json_encode([
                'success' => true, 
                'comment' => [
                    'id' => $commentId,
                    'author' => $userName,
                    'content' => htmlspecialchars($content),
                    'date' => 'Justo ahora'
                ]
            ]);
        } else {
            throw new Exception("Error al guardar comentario");
        }
    } elseif ($action === 'delete') {
        // Verify ownership
        $checkStmt = $conn->prepare("SELECT idUsuario FROM PublicacionesSocial WHERE idPublicacionSocial = ?");
        $checkStmt->bind_param("i", $postId);
        $checkStmt->execute();
        $res = $checkStmt->get_result();
        
        if ($res->num_rows === 0) {
            throw new Exception("Publicación no encontrada");
        }
        
        $post = $res->fetch_assoc();
        if ($post['idUsuario'] != $userId) {
            throw new Exception("No tienes permiso para eliminar esta publicación");
        }
        
        // Delete the post
        $delStmt = $conn->prepare("DELETE FROM PublicacionesSocial WHERE idPublicacionSocial = ?");
        $delStmt->bind_param("i", $postId);
        
        if ($delStmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Error al eliminar la publicación");
        }
    } elseif ($action === 'get_comments') {
        $stmt = $conn->prepare("
            SELECT c.idComentario, c.contenido, c.fechaCreacion, 
                   COALESCE(u.nombre, 'Usuario') as author 
            FROM ComentariosSocial c
            LEFT JOIN Usuarios u ON c.idUsuario = u.idUsuario
            WHERE c.idPublicacionSocial = ?
            ORDER BY c.fechaCreacion ASC
        ");
        $stmt->bind_param("i", $postId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $comments = [];
        while ($row = $result->fetch_assoc()) {
            $comments[] = [
                'id' => $row['idComentario'],
                'author' => $row['author'],
                'content' => htmlspecialchars($row['contenido']),
                'date' => $row['fechaCreacion']
            ];
        }
        echo json_encode(['success' => true, 'comments' => $comments]);
    } else {
        throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>