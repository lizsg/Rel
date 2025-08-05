<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

header('Content-Type: application/json');

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    
    $conn->begin_transaction();
    
    // 1. Encontrar conversaciones duplicadas
    $duplicatesQuery = "
        SELECT 
            MIN(idConversacion) as keep_id,
            GROUP_CONCAT(idConversacion) as all_ids,
            COUNT(*) as count,
            LEAST(idUsuario1, idUsuario2) as user1,
            GREATEST(idUsuario1, idUsuario2) as user2
        FROM Conversaciones 
        GROUP BY LEAST(idUsuario1, idUsuario2), GREATEST(idUsuario1, idUsuario2)
        HAVING COUNT(*) > 1
    ";
    
    $result = $conn->query($duplicatesQuery);
    $cleanedConversations = 0;
    $mergedMessages = 0;
    
    while ($row = $result->fetch_assoc()) {
        $keepId = $row['keep_id'];
        $allIds = explode(',', $row['all_ids']);
        $duplicateIds = array_filter($allIds, function($id) use ($keepId) {
            return $id != $keepId;
        });
        
        if (!empty($duplicateIds)) {
            $duplicateIdsStr = implode(',', $duplicateIds);
            
            // 2. Mover todos los mensajes de las conversaciones duplicadas a la conversación principal
            $moveMessagesQuery = "
                UPDATE Mensajes 
                SET idConversacion = ? 
                WHERE idConversacion IN ($duplicateIdsStr)
            ";
            $stmt = $conn->prepare($moveMessagesQuery);
            $stmt->bind_param("i", $keepId);
            $stmt->execute();
            $mergedMessages += $stmt->affected_rows;
            $stmt->close();
            
            // 3. Actualizar el último mensaje de la conversación principal
            $updateLastMessageQuery = "
                UPDATE Conversaciones 
                SET ultimoMensaje = (
                    SELECT fechaEnvio 
                    FROM Mensajes 
                    WHERE idConversacion = ? 
                    ORDER BY fechaEnvio DESC 
                    LIMIT 1
                )
                WHERE idConversacion = ?
            ";
            $stmt = $conn->prepare($updateLastMessageQuery);
            $stmt->bind_param("ii", $keepId, $keepId);
            $stmt->execute();
            $stmt->close();
            
            // 4. Eliminar las conversaciones duplicadas
            $deleteQuery = "DELETE FROM Conversaciones WHERE idConversacion IN ($duplicateIdsStr)";
            $conn->query($deleteQuery);
            $cleanedConversations += count($duplicateIds);
        }
    }
    
    // 5. Limpiar mensajes duplicados (mismo contenido, mismo remitente, misma conversación, tiempo similar)
    $cleanDuplicateMessagesQuery = "
        DELETE m1 FROM Mensajes m1
        INNER JOIN Mensajes m2 
        WHERE m1.idMensaje > m2.idMensaje
        AND m1.idConversacion = m2.idConversacion
        AND m1.idRemitente = m2.idRemitente
        AND m1.contenido = m2.contenido
        AND ABS(TIMESTAMPDIFF(SECOND, m1.fechaEnvio, m2.fechaEnvio)) <= 5
    ";
    $result = $conn->query($cleanDuplicateMessagesQuery);
    $deletedMessages = $conn->affected_rows;
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'cleaned' => $cleanedConversations,
        'merged_messages' => $mergedMessages,
        'deleted_duplicate_messages' => $deletedMessages,
        'message' => "Limpieza completada: $cleanedConversations conversaciones duplicadas eliminadas, $mergedMessages mensajes movidos, $deletedMessages mensajes duplicados eliminados"
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error en la limpieza: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>