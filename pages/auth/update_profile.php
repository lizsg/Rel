<?php
session_start();

// Habilitar reporte de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

    // Verificar que se recibió el tipo de imagen
    $imageType = $_POST['imageType'] ?? '';
    
    if (empty($imageType)) {
        throw new Exception("No se especificó el tipo de imagen");
    }

    $fileField = $imageType === 'profile' ? 'fotoPerfil' : 'fotoPortada';
    $fileInputName = $imageType === 'profile' ? 'profileImage' : 'coverImage';

    // Verificar que se recibió el archivo
    if (!isset($_FILES[$fileInputName])) {
        throw new Exception("No se recibió ninguna imagen. Campo esperado: " . $fileInputName);
    }

    $file = $_FILES[$fileInputName];

    // Verificar errores de upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida'
        ];
        
        $errorMsg = $errorMessages[$file['error']] ?? 'Error desconocido al subir';
        throw new Exception($errorMsg . " (Código: " . $file['error'] . ")");
    }

    // Validar tipo de archivo
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        throw new Exception("Formato no permitido. Solo se aceptan: " . implode(', ', $allowed));
    }

    // Validar que sea realmente una imagen
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception("El archivo no es una imagen válida");
    }

    // Validar tamaño (máximo 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("La imagen es demasiado grande. Máximo 5MB. Tu imagen: " . round($file['size'] / 1024 / 1024, 2) . "MB");
    }

    // Verificar y crear directorio de uploads
    $uploadDir = __DIR__ . '/../../uploads/';
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("No se pudo crear el directorio de uploads. Verifica los permisos.");
        }
    }

    // Verificar permisos de escritura
    if (!is_writable($uploadDir)) {
        throw new Exception("El directorio uploads no tiene permisos de escritura. Cambia los permisos a 755 o 777");
    }

    // Obtener la imagen anterior para eliminarla
    $stmt = $conn->prepare("SELECT $fileField FROM Usuarios WHERE idUsuario = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldImage = $result->fetch_assoc()[$fileField] ?? null;
    $stmt->close();

    // Generar nombre único para la imagen
    $prefix = $imageType === 'profile' ? 'perfil' : 'portada';
    $newFilename = $userId . '_' . time() . '_' . $prefix . '.' . $ext;
    $uploadPath = $uploadDir . $newFilename;

    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception("Error al mover el archivo. Verifica permisos del directorio uploads");
    }

    // Verificar que el archivo se guardó correctamente
    if (!file_exists($uploadPath)) {
        throw new Exception("El archivo no se guardó correctamente");
    }

    // Actualizar base de datos
    $stmt = $conn->prepare("UPDATE Usuarios SET $fileField = ? WHERE idUsuario = ?");
    $stmt->bind_param("si", $newFilename, $userId);
    
    if (!$stmt->execute()) {
        // Si falla la BD, eliminar la imagen subida
        @unlink($uploadPath);
        throw new Exception("Error al actualizar la base de datos: " . $stmt->error);
    }

    $stmt->close();

    // Eliminar imagen anterior si existe
    if ($oldImage && file_exists($uploadDir . $oldImage)) {
        @unlink($uploadDir . $oldImage);
    }

    $response['success'] = true;
    $response['message'] = 'Imagen actualizada correctamente';
    $response['filename'] = $newFilename;
    $response['path'] = '../../uploads/' . $newFilename;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    
    // Log del error para debugging
    error_log("Error subiendo imagen: " . $e->getMessage());
    
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);