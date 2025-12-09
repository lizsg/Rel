<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$userId = $_SESSION['user_id'];
$idHistorial = $_POST['idHistorial'] ?? 0;

$response = ['success' => false, 'message' => ''];

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión");
    }

    // Verificar que la foto pertenezca al usuario
    $checkStmt = $conn->prepare("
        SELECT tipoFoto, rutaArchivo 
        FROM HistorialFotos 
        WHERE idHistorial = ? AND idUsuario = ?
    ");
    $checkStmt->bind_param("ii", $idHistorial, $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Foto no encontrada");
    }
    
    $foto = $result->fetch_assoc();
    $tipoFoto = $foto['tipoFoto'];
    $rutaArchivo = $foto['rutaArchivo'];
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Desmarcar todas las fotos actuales de ese tipo
    $updateStmt = $conn->prepare("
        UPDATE HistorialFotos 
        SET esActual = 0 
        WHERE idUsuario = ? AND tipoFoto = ?
    ");
    $updateStmt->bind_param("is", $userId, $tipoFoto);
    $updateStmt->execute();
    
    // Marcar esta foto como actual
    $setActualStmt = $conn->prepare("
        UPDATE HistorialFotos 
        SET esActual = 1 
        WHERE idHistorial = ?
    ");
    $setActualStmt->bind_param("i", $idHistorial);
    $setActualStmt->execute();
    
    // Actualizar tabla Usuarios
    $campoFoto = $tipoFoto === 'perfil' ? 'fotoPerfil' : 'fotoPortada';
    $updateUserStmt = $conn->prepare("
        UPDATE Usuarios 
        SET $campoFoto = ? 
        WHERE idUsuario = ?
    ");
    $updateUserStmt->bind_param("si", $rutaArchivo, $userId);
    $updateUserStmt->execute();
    
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = 'Foto restaurada correctamente';
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    $response['message'] = $e->getMessage();
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>