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
    $conversacionId = isset($_POST['conversacion_id']) ? intval($_POST['conversacion_id']) : 0;
    
    if ($conversacionId <= 0) {
        throw new Exception('ID de conversación inválido');
    }
    
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }
    
    // Verificar que el usuario tiene acceso a esta conversación
    $userId = $_SESSION['usuario']['idUsuario'];
    $stmt = $conn->prepare("SELECT * FROM Conversaciones 
                           WHERE idConversacion = ? 
                           AND (idUsuario1 = ? OR idUsuario2 = ?)");
    $stmt->bind_param("iii", $conversacionId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('No tienes acceso a esta conversación');
    }
    
    // Obtener mensajes
    $stmt = $conn->prepare("SELECT idMensaje, idRemitente, contenido, fechaEnvio, leido 
                           FROM Mensajes 
                           WHERE idConversacion = ? 
                           ORDER BY fechaEnvio ASC");
    $stmt->bind_param("i", $conversacionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    // Marcar mensajes como leídos
    $stmt = $conn->prepare("UPDATE Mensajes 
                           SET leido = 1 
                           WHERE idConversacion = ? 
                           AND idRemitente != ? 
                           AND leido = 0");
    $stmt->bind_param("ii", $conversacionId, $userId);
    $stmt->execute();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}