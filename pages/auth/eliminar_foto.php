<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$userId = $_SESSION['user_id'];
$tipo = $_POST['tipo'] ?? ''; // 'perfil' or 'portada'

if (!in_array($tipo, ['perfil', 'portada'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
    exit();
}

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        throw new Exception("Error de conexión");
    }

    $field = ($tipo === 'perfil') ? 'fotoPerfil' : 'fotoPortada';
    $default = ($tipo === 'perfil') ? 'default-avatar.png' : 'default-cover.png';

    // 1. Update Usuarios table
    $stmt = $conn->prepare("UPDATE Usuarios SET $field = ? WHERE idUsuario = ?");
    $stmt->bind_param("si", $default, $userId);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar usuario");
    }

    // 2. Update HistorialFotos (set all to not actual)
    $histStmt = $conn->prepare("UPDATE HistorialFotos SET esActual = 0 WHERE idUsuario = ? AND tipoFoto = ?");
    $histStmt->bind_param("is", $userId, $tipo);
    $histStmt->execute();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
