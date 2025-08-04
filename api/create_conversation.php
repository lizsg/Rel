<?php
// api/create_conversation.php - Versión mejorada
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if (!isset($_POST['other_user_id']) || empty($_POST['other_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario requerido']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$otherUserId = intval($_POST['other_user_id']);

// Validar que no se trate de crear conversación consigo mismo
if ($userId == $otherUserId) {
    echo json_encode(['success' => false, 'message' => 'No puedes crear una conversación contigo mismo']);
    exit();
}

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    
    // PASO 1: Verificar que el otro usuario existe
    $checkUser = $conn->prepare("SELECT idUsuario FROM Usuarios WHERE idUsuario = ?");
    $checkUser->bind_param("i", $otherUserId);
    $checkUser->execute();
    $userResult = $checkUser->get_result();
    
    if ($userResult->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit();
    }
    
    // PASO 2: Normalizar IDs (menor siempre primero para evitar duplicados)
    $usuario1 = min($userId, $otherUserId);
    $usuario2 = max($userId, $otherUserId);
    
    // PASO 3: Buscar conversación existente (en cualquier orden)
    $stmt = $conn->prepare("
        SELECT idConversacion 
        FROM Conversaciones 
        WHERE (idUsuario1 = ? AND idUsuario2 = ?) 
           OR (idUsuario1 = ? AND idUsuario2 = ?)
        LIMIT 1
    ");
    
    $stmt->bind_param("iiii", $usuario1, $usuario2, $usuario2, $usuario1);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Ya existe una conversación
        echo json_encode([
            'success' => true,
            'conversationId' => $row['idConversacion'],
            'message' => 'Conversación existente',
            'isNew' => false
        ]);
        exit();
    }
    
    // PASO 4: Crear nueva conversación con IDs normalizados
    $createStmt = $conn->prepare("
        INSERT INTO Conversaciones (idUsuario1, idUsuario2, fechaCreacion, ultimoMensaje) 
        VALUES (?, ?, NOW(), NOW())
    ");
    
    $createStmt->bind_param("ii", $usuario1, $usuario2);
    
    if ($createStmt->execute()) {
        $newConversationId = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'conversationId' => $newConversationId,
            'message' => 'Nueva conversación creada',
            'isNew' => true
        ]);
    } else {
        // Si falla, podría ser por un duplicado concurrente
        // Intentar buscar de nuevo la conversación
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                'success' => true,
                'conversationId' => $row['idConversacion'],
                'message' => 'Conversación encontrada tras intento de creación',
                'isNew' => false
            ]);
        } else {
            throw new Exception("Error al crear conversación: " . $conn->error);
        }
    }
    
} catch (Exception $e) {
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