<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }

    if (empty($_FILES) && empty($_POST) && isset($_SERVER['REQUEST_METHOD']) && strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
        throw new Exception("El archivo es demasiado grande (excede el límite del servidor)");
    }

    if (!isset($_FILES['profileImage']) && !isset($_FILES['coverImage'])) {
        throw new Exception("No se recibió ninguna imagen");
    }

    // Determinar tipo de imagen
    if (isset($_POST['imageType'])) {
        $imageType = $_POST['imageType'];
    } else {
        $imageType = isset($_FILES['profileImage']) ? 'profile' : 'cover';
    }

    // Validar tipo de imagen
    if ($imageType !== 'profile' && $imageType !== 'cover') {
        throw new Exception("Tipo de imagen no válido");
    }

    $fileField = $imageType === 'profile' ? 'fotoPerfil' : 'fotoPortada';
    $fileInputName = $imageType === 'profile' ? 'profileImage' : 'coverImage';
    $tipoFoto = $imageType === 'profile' ? 'perfil' : 'portada';

    // Verificar si el archivo existe en $_FILES
    if (!isset($_FILES[$fileInputName])) {
        // Intentar buscar en el otro campo por si acaso
        $otherInput = $imageType === 'profile' ? 'coverImage' : 'profileImage';
        if (isset($_FILES[$otherInput])) {
            $fileInputName = $otherInput;
        } else {
            throw new Exception("No se encontró el archivo de imagen esperado");
        }
    }

    $file = $_FILES[$fileInputName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error al subir el archivo");
    }

    // Validar tipo de archivo
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        throw new Exception("Formato no permitido");
    }

    // Validar que sea imagen
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception("El archivo no es una imagen válida");
    }

    // Validar tamaño (5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("La imagen es demasiado grande. Máximo 5MB");
    }

    // Crear directorio si no existe
    $uploadDir = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!is_writable($uploadDir)) {
        throw new Exception("El directorio de subidas no tiene permisos de escritura");
    }

    // Obtener imagen anterior
    $stmt = $conn->prepare("SELECT $fileField FROM Usuarios WHERE idUsuario = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldImage = $result->fetch_assoc()[$fileField] ?? null;
    $stmt->close();

    // Generar nombre único
    $prefix = $imageType === 'profile' ? 'perfil' : 'portada';
    $newFilename = $userId . '_' . time() . '_' . $prefix . '.' . $ext;
    $uploadPath = $uploadDir . $newFilename;

    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception("Error al mover el archivo");
    }

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Desmarcar fotos anteriores como actuales
        $updateHistStmt = $conn->prepare("
            UPDATE HistorialFotos 
            SET esActual = 0 
            WHERE idUsuario = ? AND tipoFoto = ?
        ");
        $updateHistStmt->bind_param("is", $userId, $tipoFoto);
        $updateHistStmt->execute();

        // Insertar nueva foto en historial
        $insertHistStmt = $conn->prepare("
            INSERT INTO HistorialFotos (idUsuario, tipoFoto, rutaArchivo, esActual)
            VALUES (?, ?, ?, 1)
        ");
        $insertHistStmt->bind_param("iss", $userId, $tipoFoto, $newFilename);
        $insertHistStmt->execute();

        // Actualizar tabla Usuarios
        $updateUserStmt = $conn->prepare("UPDATE Usuarios SET $fileField = ? WHERE idUsuario = ?");
        $updateUserStmt->bind_param("si", $newFilename, $userId);
        
        if (!$updateUserStmt->execute()) {
            throw new Exception("Error al actualizar usuario");
        }

        // Insertar en el feed social (PublicacionesSocial)
        if ($imageType === 'profile') {
            try {
                $socialContent = "Actualizó su foto de perfil";
                $socialImage = 'uploads/' . $newFilename;
                $socialType = 'foto_perfil';
                
                $socialStmt = $conn->prepare("INSERT INTO PublicacionesSocial (idUsuario, contenido, imagen, tipo) VALUES (?, ?, ?, ?)");
                $socialStmt->bind_param("isss", $userId, $socialContent, $socialImage, $socialType);
                $socialStmt->execute();
            } catch (Exception $e) {
                // Ignoramos error en feed para no bloquear la actualización de perfil
                error_log("Error creando post social de perfil: " . $e->getMessage());
            }
        }

        $conn->commit();

        // Eliminar imagen anterior si existe
        if ($oldImage && file_exists($uploadDir . $oldImage)) {
            @unlink($uploadDir . $oldImage);
        }

        $response['success'] = true;
        $response['message'] = 'Imagen actualizada correctamente';
        $response['filename'] = $newFilename;
        $response['path'] = '../../uploads/' . $newFilename;

    } catch (Exception $e) {
        $conn->rollback();
        @unlink($uploadPath);
        throw $e;
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Error subiendo imagen: " . $e->getMessage());
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>