<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar que se haya enviado el ID del otro usuario
if (!isset($_POST['other_user_id']) || empty($_POST['other_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario requerido']);
    exit();
}

$currentUserId = $_SESSION['user_id'];
$otherUserId = intval($_POST['other_user_id']);

// Validar que no sea el mismo usuario
if ($currentUserId == $otherUserId) {
    echo json_encode(['success' => false, 'message' => 'No puedes crear una conversación contigo mismo']);
    exit();
}

header('Content-Type: application/json');

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    
    // Comenzar transacción para consistencia
    $conn->begin_transaction();
    
    // Verificar que el otro usuario existe
    $userCheckQuery = "SELECT idUsuario FROM Usuarios WHERE idUsuario = ?";
    $userStmt = $conn->prepare($userCheckQuery);
    $userStmt->bind_param("i", $otherUserId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if ($userResult->num_rows == 0) {
        $userStmt->close();
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit();
    }
    $userStmt->close();
    
    // Buscar conversación existente (en ambas direcciones)
    $checkQuery = "
        SELECT idConversacion 
        FROM Conversaciones 
        WHERE (idUsuario1 = ? AND idUsuario2 = ?) 
           OR (idUsuario1 = ? AND idUsuario2 = ?)
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("iiii", $currentUserId, $otherUserId, $otherUserId, $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // La conversación ya existe
        $row = $result->fetch_assoc();
        $conversationId = $row['idConversacion'];
        $stmt->close();
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'conversationId' => $conversationId,
            'message' => 'Conversación existente encontrada'
        ]);
    } else {
        // Crear nueva conversación
        $stmt->close();
        
        // Crear la conversación con los usuarios ordenados consistentemente
        // Esto previene duplicados adicionales
        $user1 = min($currentUserId, $otherUserId);
        $user2 = max($currentUserId, $otherUserId);
        
        $insertQuery = "
            INSERT INTO Conversaciones (idUsuario1, idUsuario2, fechaCreacion) 
            VALUES (?, ?, NOW())
        ";
        
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("ii", $user1, $user2);
        
        if ($insertStmt->execute()) {
            $conversationId = $conn->insert_id;
            $insertStmt->close();
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'conversationId' => $conversationId,
                'message' => 'Nueva conversación creada'
            ]);
        } else {
            $insertStmt->close();
            $conn->rollback();
            
            // Verificar si fue error por duplicado
            if ($conn->errno == 1062) { // Duplicate entry error
                // Intentar buscar la conversación que se creó entre tanto
                $retryStmt = $conn->prepare($checkQuery);
                $retryStmt->bind_param("iiii", $currentUserId, $otherUserId, $otherUserId, $currentUserId);
                $retryStmt->execute();
                $retryResult = $retryStmt->get_result();
                
                if ($retryResult->num_rows > 0) {
                    $row = $retryResult->fetch_assoc();
                    $conversationId = $row['idConversacion'];
                    $retryStmt->close();
                    
                    echo json_encode([
                        'success' => true, 
                        'conversationId' => $conversationId,
                        'message' => 'Conversación encontrada después del conflicto'
                    ]);
                } else {
                    $retryStmt->close();
                    throw new Exception("Error de duplicado sin resolver");
                }
            } else {
                throw new Exception("Error al crear la conversación: " . $conn->error);
            }
        }
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->close();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>