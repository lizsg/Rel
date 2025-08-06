<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

require_once '../config/database.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }

    switch ($action) {
        case 'can_rate':
            canRate($conn, $userId);
            break;
        case 'submit_rating':
            submitRating($conn, $userId);
            break;
        case 'get_user_stats':
            getUserStats($conn);
            break;
        case 'get_user_ratings':
            getUserRatings($conn);
            break;
        default:
            throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

function canRate($conn, $userId) {
    $targetUserId = intval($_GET['target_user_id'] ?? 0);
    
    if ($targetUserId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    if ($targetUserId == $userId) {
        echo json_encode([
            'success' => true,
            'can_rate' => false,
            'reason' => 'No puedes calificarte a ti mismo'
        ]);
        return;
    }
    
    // Verificar si ya calificó a este usuario
    $query = "SELECT COUNT(*) as count FROM Calificaciones WHERE idCalificador = ? AND idCalificado = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $userId, $targetUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $canRate = $row['count'] == 0;
    $reason = $canRate ? '' : 'Ya has calificado a este usuario';
    
    // Obtener calificación existente si ya calificó
    $existingRating = null;
    if (!$canRate) {
        $query = "SELECT puntuacion, comentario, fechaCalificacion FROM Calificaciones WHERE idCalificador = ? AND idCalificado = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $userId, $targetUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        $existingRating = $result->fetch_assoc();
    }
    
    echo json_encode([
        'success' => true,
        'can_rate' => $canRate,
        'reason' => $reason,
        'existing_rating' => $existingRating
    ]);
}

function submitRating($conn, $userId) {
    $targetUserId = intval($_POST['target_user_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    
    // Validaciones
    if ($targetUserId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    if ($targetUserId == $userId) {
        throw new Exception('No puedes calificarte a ti mismo');
    }
    
    if ($rating < 1 || $rating > 5) {
        throw new Exception('La calificación debe estar entre 1 y 5');
    }
    
    // Verificar que el usuario a calificar existe
    $query = "SELECT idUsuario FROM Usuarios WHERE idUsuario = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $targetUserId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        throw new Exception('El usuario a calificar no existe');
    }
    
    // Verificar si ya calificó (seguridad adicional)
    $query = "SELECT COUNT(*) as count FROM Calificaciones WHERE idCalificador = ? AND idCalificado = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $userId, $targetUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        throw new Exception('Ya has calificado a este usuario');
    }
    
    // Insertar calificación
    $query = "INSERT INTO Calificaciones (idCalificador, idCalificado, puntuacion, comentario) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiis", $userId, $targetUserId, $rating, $comment);
    
    if ($stmt->execute()) {
        // Las estadísticas se actualizan automáticamente con el trigger
        echo json_encode([
            'success' => true,
            'message' => 'Calificación enviada exitosamente'
        ]);
    } else {
        throw new Exception('Error al guardar la calificación');
    }
}

function getUserStats($conn) {
    $targetUserId = intval($_GET['user_id'] ?? 0);
    
    if ($targetUserId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    $query = "SELECT * FROM VistaEstadisticasUsuario WHERE idUsuario = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $targetUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stats = $result->fetch_assoc();
        echo json_encode(['success' => true, 'stats' => $stats]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    }
}

function getUserRatings($conn) {
    $targetUserId = intval($_GET['user_id'] ?? 0);
    $limit = intval($_GET['limit'] ?? 10);
    $offset = intval($_GET['offset'] ?? 0);
    
    if ($targetUserId <= 0) {
        throw new Exception('ID de usuario inválido');
    }
    
    $query = "
        SELECT 
            c.puntuacion,
            c.comentario,
            c.fechaCalificacion,
            u.userName as calificadorNombre,
            u.nombre as calificadorNombreCompleto
        FROM Calificaciones c
        JOIN Usuarios u ON c.idCalificador = u.idUsuario
        WHERE c.idCalificado = ?
        ORDER BY c.fechaCalificacion DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $targetUserId, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ratings = [];
    while ($row = $result->fetch_assoc()) {
        $ratings[] = $row;
    }
    
    // Obtener total de calificaciones
    $countQuery = "SELECT COUNT(*) as total FROM Calificaciones WHERE idCalificado = ?";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $targetUserId);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    
    echo json_encode([
        'success' => true,
        'ratings' => $ratings,
        'total' => $total,
        'has_more' => ($offset + $limit) < $total
    ]);
}
?>