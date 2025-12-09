<?php
session_start();

if(!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';

// Verificar que se recibió el ID de la publicación
if (!isset($_POST['publicacion_id']) || empty($_POST['publicacion_id'])) {
    $_SESSION['mensaje_error'] = "ID de publicación no válido";
    header("Location: publicaciones.php");
    exit();
}

$publicacion_id = (int)$_POST['publicacion_id'];
$errorMessage = '';
$titulo_libro = '';

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }

    // Iniciar transacción para asegurar integridad de datos
    $conn->begin_transaction();

    // Verificar que la publicación pertenece al usuario y obtener información
    $checkStmt = $conn->prepare("
        SELECT p.idLibro, l.titulo, l.linkImagen1, l.linkImagen2, l.linkImagen3
        FROM Publicaciones p
        JOIN Libros l ON p.idLibro = l.idLibro
        WHERE p.idPublicacion = ? AND p.idUsuario = ?
    ");
    $checkStmt->bind_param("ii", $publicacion_id, $_SESSION['user_id']);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Publicación no encontrada o no tienes permisos para eliminarla");
    }
    
    $libro_data = $result->fetch_assoc();
    $libro_id = $libro_data['idLibro'];
    $titulo_libro = $libro_data['titulo'];
    
    // Obtener rutas de imágenes para eliminar archivos
    $imagenes_a_eliminar = array_filter([
        $libro_data['linkImagen1'],
        $libro_data['linkImagen2'],
        $libro_data['linkImagen3']
    ]);

    // 1. Eliminar asociaciones de hashtags del libro
    $deleteHashtagsStmt = $conn->prepare("DELETE FROM LibroHashtags WHERE idLibro = ?");
    $deleteHashtagsStmt->bind_param("i", $libro_id);
    $deleteHashtagsStmt->execute();

    // 2. Eliminar la publicación
    $deletePublicacionStmt = $conn->prepare("DELETE FROM Publicaciones WHERE idPublicacion = ?");
    $deletePublicacionStmt->bind_param("i", $publicacion_id);
    $deletePublicacionStmt->execute();

    // 3. Verificar si el libro está siendo usado en otras publicaciones
    $checkOtrasPublicacionesStmt = $conn->prepare("SELECT COUNT(*) as count FROM Publicaciones WHERE idLibro = ?");
    $checkOtrasPublicacionesStmt->bind_param("i", $libro_id);
    $checkOtrasPublicacionesStmt->execute();
    $count_result = $checkOtrasPublicacionesStmt->get_result();
    $otras_publicaciones = $count_result->fetch_assoc()['count'];

    // 4. Si no hay otras publicaciones usando este libro, eliminarlo también
    if ($otras_publicaciones == 0) {
        $deleteLibroStmt = $conn->prepare("DELETE FROM Libros WHERE idLibro = ?");
        $deleteLibroStmt->bind_param("i", $libro_id);
        $deleteLibroStmt->execute();
        
        // Eliminar archivos de imágenes del servidor
        foreach ($imagenes_a_eliminar as $imagen) {
            if (!empty($imagen)) {
                $ruta_imagen = __DIR__ . '/../../uploads/' . $imagen;
                if (file_exists($ruta_imagen)) {
                    unlink($ruta_imagen);
                }
            }
        }
    }

    // 5. Limpiar hashtags huérfanos (que no están asociados a ningún libro)
    $cleanHashtagsStmt = $conn->prepare("
        DELETE FROM Hashtags 
        WHERE idHashtag NOT IN (SELECT DISTINCT idHashtag FROM LibroHashtags)
    ");
    $cleanHashtagsStmt->execute();

    // Confirmar transacción
    $conn->commit();
    
    // Cerrar statements
    $checkStmt->close();
    $deleteHashtagsStmt->close();
    $deletePublicacionStmt->close();
    $checkOtrasPublicacionesStmt->close();
    if (isset($deleteLibroStmt)) $deleteLibroStmt->close();
    $cleanHashtagsStmt->close();
    $conn->close();

    // Mensaje de éxito
    $_SESSION['mensaje_exito'] = "La publicación \"$titulo_libro\" ha sido eliminada correctamente";
    
    // Registrar en log para auditoría
    error_log("Publicación eliminada - Usuario: {$_SESSION['user_id']}, Publicación ID: $publicacion_id, Título: $titulo_libro");

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($conn) && $conn) {
        $conn->rollback();
        $conn->close();
    }
    
    $errorMessage = "Error al eliminar la publicación: " . $e->getMessage();
    $_SESSION['mensaje_error'] = $errorMessage;
    error_log($errorMessage);
}

// Redirigir de vuelta a publicaciones
header("Location: publicaciones.php");
exit();
?>