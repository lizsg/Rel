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
$targetId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$targetId || !$action) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    
    if ($action === 'follow') {
        $stmt = $conn->prepare("INSERT IGNORE INTO Seguidores (idUsuarioSeguidor, idUsuarioSeguido) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $targetId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            createNotification($conn, $targetId, $userId, 'amistad', $userId, 'te ha comenzado a seguir');
        }
        echo json_encode(['success' => true, 'status' => 'following']);
        
    } elseif ($action === 'unfollow') {
        $stmt = $conn->prepare("DELETE FROM Seguidores WHERE idUsuarioSeguidor = ? AND idUsuarioSeguido = ?");
        $stmt->bind_param("ii", $userId, $targetId);
        $stmt->execute();
        echo json_encode(['success' => true, 'status' => 'not_following']);
        
    } elseif ($action === 'add_friend') {
        $stmt = $conn->prepare("INSERT INTO Amistades (idUsuarioSolicitante, idUsuarioReceptor, estado) VALUES (?, ?, 'pendiente')");
        $stmt->bind_param("ii", $userId, $targetId);
        if ($stmt->execute()) {
            createNotification($conn, $targetId, $userId, 'amistad', $userId, 'te ha enviado una solicitud de amistad');
            echo json_encode(['success' => true, 'status' => 'pending']);
        } else {
            throw new Exception("Error al enviar solicitud");
        }
        
    } elseif ($action === 'accept_friend') {
        $stmt = $conn->prepare("UPDATE Amistades SET estado = 'aceptada' WHERE idUsuarioSolicitante = ? AND idUsuarioReceptor = ?");
        $stmt->bind_param("ii", $targetId, $userId);
        if ($stmt->execute()) {
            createNotification($conn, $targetId, $userId, 'amistad', $userId, 'ha aceptado tu solicitud de amistad');
            echo json_encode(['success' => true, 'status' => 'accepted']);
        }
    }
    
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>