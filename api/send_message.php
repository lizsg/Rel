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

    $conversacionId = isset($_POST['conversacion_id']) ? intval($_POST['conversacion_id']) : 0;
    $contenido = isset($_POST['contenido']) ? trim($_POST['contenido']) : '';
    
    if ($conversacionId <= 0) {
        throw new Exception('ID de conversación inválido');
    }
    
    if (empty($contenido)) {
        throw new Exception('El mensaje no puede estar vacío');
    }
    
    // Validar longitud del mensaje
    if (strlen($contenido) > 1000) {
        throw new Exception('El mensaje es demasiado largo (máximo 1000 caracteres)');
    }
    
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida: " . $conn->connect_error);
    }
    
    // Comenzar transacción para prevenir duplicados
    $conn->begin_transaction();
    
    // Verificar acceso a la conversación
    $stmt = $conn->prepare("SELECT * FROM Conversaciones WHERE idConversacion = ? 
        AND (idUsuario1 = ? OR idUsuario2 = ?)");
    $stmt->bind_param("iii", $conversacionId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->rollback();
        throw new Exception('No tienes acceso a esta conversación');
    }
    $stmt->close();
    
    // Verificar si existe un mensaje muy similar reciente (últimos 10 segundos)
    // Esto previene duplicados por double-click/tap
    $checkDuplicateStmt = $conn->prepare("
        SELECT COUNT(*) as count FROM Mensajes 
        WHERE idConversacion = ? 
        AND idRemitente = ? 
        AND contenido = ? 
        AND fechaEnvio >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
    ");
    $checkDuplicateStmt->bind_param("iis", $conversacionId, $userId, $contenido);
    $checkDuplicateStmt->execute();
    $duplicateResult = $checkDuplicateStmt->get_result();
    $duplicateRow = $duplicateResult->fetch_assoc();
    $checkDuplicateStmt->close();
    
    if ($duplicateRow['count'] > 0) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Mensaje duplicado detectado. Por favor espera antes de enviar otro mensaje igual.'
        ]);
        exit();
    }
    
    // Insertar el mensaje
    $stmt = $conn->prepare("INSERT INTO Mensajes (idConversacion, idRemitente, contenido, fechaEnvio, leido) 
        VALUES (?, ?, ?, NOW(), 0)");
    $stmt->bind_param("iis", $conversacionId, $userId, $contenido);
    
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();
        throw new Exception('Error al enviar mensaje: ' . $stmt->error);
    }
    
    $messageId = $conn->insert_id;
    $stmt->close();
    
    // Actualizar el último mensaje de la conversación
    $updateStmt = $conn->prepare("UPDATE Conversaciones SET ultimoMensaje = NOW() WHERE idConversacion = ?");
    $updateStmt->bind_param("i", $conversacionId);
    
    if (!$updateStmt->execute()) {
        $updateStmt->close();
        $conn->rollback();
        throw new Exception('Error al actualizar conversación');
    }
    $updateStmt->close();
    
    // Confirmar transacción
    $conn->commit();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Mensaje enviado exitosamente',
        'messageId' => $messageId
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->close();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>