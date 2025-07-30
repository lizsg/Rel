<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

try {
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        throw new Exception('Usuario no válido en sesión');
    }

    $otherUserId = isset($_POST['other_user_id']) ? intval($_POST['other_user_id']) : 0;
    
    if ($otherUserId <= 0) {
        throw new Exception('ID de usuario inválido');
    }

    if ($userId == $otherUserId) {
        throw new Exception('No puedes crear una conversación contigo mismo');
    }
    
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }
    
    $stmt = $conn->prepare("SELECT idUsuario FROM Usuarios WHERE idUsuario = ?");
    $stmt->bind_param("i", $otherUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('El usuario no existe');
    }
    
    $stmt = $conn->prepare("SELECT idConversacion FROM Conversaciones 
                           WHERE (idUsuario1 = ? AND idUsuario2 = ?) 
                           OR (idUsuario1 = ? AND idUsuario2 = ?)");
    $stmt->bind_param("iiii", $userId, $otherUserId, $otherUserId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $conversacion = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'conversationId' => $conversacion['idConversacion'],
            'message' => 'Conversación encontrada'
        ]);
    } else {
        $stmt = $conn->prepare("INSERT INTO Conversaciones (idUsuario1, idUsuario2, fechaCreacion, ultimoMensaje) 
                               VALUES (?, ?, NOW(), NOW())");
        $stmt->bind_param("ii", $userId, $otherUserId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'conversationId' => $conn->insert_id,
                'message' => 'Conversación creada exitosamente'
            ]);
        } else {
            throw new Exception('Error al crear la conversación: ' . $stmt->error);
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}