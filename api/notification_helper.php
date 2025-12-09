<?php
// api/notification_helper.php

function createNotification($conn, $receiverId, $senderId, $type, $referenceId, $content = '') {
    if ($receiverId == $senderId) return; // Don't notify self

    $stmt = $conn->prepare("
        INSERT INTO Notificaciones (idUsuario, idUsuarioEmisor, tipo, idReferencia, contenido, leida, fechaCreacion)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    $stmt->bind_param("iisis", $receiverId, $senderId, $type, $referenceId, $content);
    $stmt->execute();
    $stmt->close();
}
?>