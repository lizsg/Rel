<?php
session_start();
header('Content-Type: application/json');

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    
    $conn->begin_transaction();
    $conversacionesLimpiadas = 0;
    
    // Encontrar conversaciones duplicadas
    $query = "
        SELECT 
            LEAST(idUsuario1, idUsuario2) as user1,
            GREATEST(idUsuario1, idUsuario2) as user2,
            GROUP_CONCAT(idConversacion ORDER BY fechaCreacion ASC) as conversacion_ids,
            COUNT(*) as total
        FROM Conversaciones
        GROUP BY LEAST(idUsuario1, idUsuario2), GREATEST(idUsuario1, idUsuario2)
        HAVING COUNT(*) > 1
    ";
    
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $conversacionIds = explode(',', $row['conversacion_ids']);
        $conversacionPrincipal = array_shift($conversacionIds); // Mantener la primera (más antigua)
        
        foreach ($conversacionIds as $conversacionDuplicada) {
            // Mover mensajes de la conversación duplicada a la principal
            $moveMessages = $conn->prepare("
                UPDATE Mensajes 
                SET idConversacion = ? 
                WHERE idConversacion = ?
            ");
            $moveMessages->bind_param("ii", $conversacionPrincipal, $conversacionDuplicada);
            $moveMessages->execute();
            
            // Actualizar último mensaje de la conversación principal
            $updateLastMessage = $conn->prepare("
                UPDATE Conversaciones 
                SET ultimoMensaje = (
                    SELECT MAX(fechaEnvio) 
                    FROM Mensajes 
                    WHERE idConversacion = ?
                )
                WHERE idConversacion = ?
            ");
            $updateLastMessage->bind_param("ii", $conversacionPrincipal, $conversacionPrincipal);
            $updateLastMessage->execute();
            
            // Borrar la conversación duplicada
            $deleteConversation = $conn->prepare("DELETE FROM Conversaciones WHERE idConversacion = ?");
            $deleteConversation->bind_param("i", $conversacionDuplicada);
            $deleteConversation->execute();
            
            $conversacionesLimpiadas++;
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'cleaned' => $conversacionesLimpiadas,
        'message' => $conversacionesLimpiadas > 0 ? 
            "Se limpiaron $conversacionesLimpiadas conversaciones duplicadas" : 
            "No se encontraron conversaciones duplicadas"
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>