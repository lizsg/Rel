<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }

    $contenido = isset($_POST['contenido']) ? trim($_POST['contenido']) : '';
    $hasImage = isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK;

    if (empty($contenido) && !$hasImage) {
        throw new Exception("La publicación no puede estar vacía");
    }

    $imagePath = null;
    $tipo = 'estado';

    if ($hasImage) {
        $file = $_FILES['imagen'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            throw new Exception("Formato de imagen no permitido");
        }

        $uploadDir = __DIR__ . '/../uploads/posts/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid() . '_' . time() . '.' . $ext;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $imagePath = 'uploads/posts/' . $fileName;
            $tipo = 'foto';
        } else {
            throw new Exception("Error al guardar la imagen");
        }
    }

    $stmt = $conn->prepare("INSERT INTO PublicacionesSocial (idUsuario, contenido, imagen, tipo) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $contenido, $imagePath, $tipo);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Publicación creada exitosamente';
    } else {
        throw new Exception("Error al guardar en base de datos");
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
