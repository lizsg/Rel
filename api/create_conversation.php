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
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    
    // Prevenir condiciones de carrera con bloqueo
    $conn->query("LOCK TABLES Conversaciones WRITE, Usuarios READ");
    
    // Verificar si ya existe una conversación entre estos usuarios
    // Buscar en ambas direcciones (user1->user2 y user2->user1)
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
        $conn->query("UNLOCK TABLES");
        
        echo json_encode([
            'success' => true, 
            'conversationId' => $conversationId,
            'message' => 'Conversación existente encontrada'
        ]);
    } else {
        // Crear nueva conversación
        $stmt->close();
        
        // Verificar que el otro usuario existe
        $userCheckQuery = "SELECT idUsuario FROM Usuarios WHERE idUsuario = ?";
        $userStmt = $conn->prepare($userCheckQuery);
        $userStmt->bind_param("i", $otherUserId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userResult->num_rows == 0) {
            $userStmt->close();
            $conn->query("UNLOCK TABLES");
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit();
        }
        $userStmt->close();
        
        // Crear la conversación con los usuarios ordenados consistentemente
        // Esto ayuda a prevenir duplicados adicionales
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
            $conn->query("UNLOCK TABLES");
            
            echo json_encode([
                'success' => true, 
                'conversationId' => $conversationId,
                'message' => 'Nueva conversación creada'
            ]);
        } else {
            $insertStmt->close();
            $conn->query("UNLOCK TABLES");
            throw new Exception("Error al crear la conversación: " . $conn->error);
        }
    }
    
} catch (Exception $e) {
    // Asegurar que las tablas se desbloqueen en caso de error
    $conn->query("UNLOCK TABLES");
    
    echo json_encode([
        'success' => false, 
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>