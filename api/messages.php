<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

session_start();
if(!isset($_SESSION['usuario'])) {
    die(json_encode(['error' => 'No autenticado']));
}

$userId = $_SESSION['usuario']['idUsuario'];
$db = new Database();
$conn = $db->getConnection();

// Obtener mensajes de una conversación
if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['conversation'])) {
    $conversationId = $_GET['conversation'];
    
    // Verificar que el usuario pertenece a la conversación
    $stmt = $conn->prepare("SELECT * FROM ReLee_Conversaciones WHERE idConversacion = ? AND (idUsuario1 = ? OR idUsuario2 = ?)");
    $stmt->execute([$conversationId, $userId, $userId]);
    
    if($stmt->rowCount() === 0) {
        die(json_encode(['error' => 'Conversación no encontrada']));
    }
    
    // Obtener mensajes
    $stmt = $conn->prepare("SELECT * FROM ReLee_Mensajes WHERE idConversacion = ? ORDER BY fechaEnvio ASC");
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($messages);
}

// Crear nuevo mensaje
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar datos
    if(empty($data['conversationId']) || empty($data['content'])) {
        die(json_encode(['error' => 'Datos inválidos']));
    }
    
    // Insertar mensaje en la base de datos
    $stmt = $conn->prepare("INSERT INTO ReLee_Mensajes (idConversacion, idRemitente, contenido, fechaEnvio, leido) VALUES (?, ?, ?, NOW(), 0)");
    $stmt->execute([
        $data['conversationId'],
        $userId,
        $data['content']
    ]);
    
    // Actualizar último mensaje en la conversación
    $stmt = $conn->prepare("UPDATE ReLee_Conversaciones SET ultimoMensaje = NOW() WHERE idConversacion = ?");
    $stmt->execute([$data['conversationId']]);
    
    echo json_encode(['success' => true, 'messageId' => $conn->lastInsertId()]);
}