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
    // CORRECCIÓN: Usar user_id que es como se guarda en login.php
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        throw new Exception('Usuario no válido en sesión');
    }

    $conversacionId = isset($_POST['conversacion_id']) ? intval($_POST['conversacion_id']) : 0;
    $contenido = isset($_POST['contenido']) ? trim($_POST['contenido']) : '';
    
    if ($conversacionId <= 0) {
        throw new Exception('ID de conversación inválido');
    }
    
    if (empty($contenido)) {
        throw new Exception('El mensaje no puede estar vacío');
    }
    
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }
    
    // Verificar que el usuario tiene acceso a esta conversación
    $stmt = $conn->prepare("SELECT * FROM Conversaciones 
                           WHERE idConversacion = ? 
                           AND (idUsuario1 = ? OR idUsuario2 = ?)");
    $stmt->bind_param("iii", $conversacionId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('No tienes acceso a esta conversación');
    }
    
    // Insertar el mensaje
    $stmt = $conn->prepare("INSERT INTO Mensajes (idConversacion, idRemitente, contenido, fechaEnvio, leido) 
                           VALUES (?, ?, ?, NOW(), 0)");
    $stmt->bind_param("iis", $conversacionId, $userId, $contenido);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al enviar mensaje: ' . $stmt->error);
    }
    
    // Actualizar el último mensaje de la conversación
    $stmt = $conn->prepare("UPDATE Conversaciones SET ultimoMensaje = NOW() WHERE idConversacion = ?");
    $stmt->bind_param("i", $conversacionId);
    $stmt->execute();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Mensaje enviado exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}