<?php
session_start();

header('Content-Type: application/json');

if(!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$carpetaUploads = __DIR__ . '/../uploads/';

if (!is_dir($carpetaUploads)) {
    if (!mkdir($carpetaUploads, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Error: No se pudo crear el directorio de subida']);
        exit();
    }
}

$extensionesPermitidasIMG = ['jpg', 'jpeg', 'png', 'gif'];
$extensionesPermitidasVID = ['mp4', 'mov', 'webm', 'avi'];
$maxTamanoImagen = 10 * 1024 * 1024;
$maxTamanoVideo = 500 * 1024 * 1024;

$videoSubido = null;
$imagen1Subida = null;
$imagen2Subida = null;
$imagen3Subida = null;

$errores = [];

function procesarArchivo($nombreInput, $extensionesPermitidas, $carpetaDestino, $tamanoMaximo, $userId) {
    if (isset($_FILES[$nombreInput]) && $_FILES[$nombreInput]['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES[$nombreInput];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $extensionesPermitidas)) {
            return ['error' => "La extensión .$extension no es permitida"];
        }

        if ($archivo['size'] > $tamanoMaximo) {
            return ['error' => "El archivo excede el tamaño máximo de " . ($tamanoMaximo / (1024 * 1024)) . "MB"];
        }

        $nuevoNombre = $userId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $rutaFinal = $carpetaDestino . $nuevoNombre;

        if (move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
            return ['nombre' => $nuevoNombre];
        } else {
            return ['error' => "Error al mover el archivo"];
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $autor = trim($_POST['autor'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    if (empty($titulo)) $errores[] = "El título es obligatorio";
    if (empty($autor)) $errores[] = "El autor es obligatorio";
    if (empty($descripcion)) $errores[] = "La descripción es obligatoria";

    $resImagen1 = procesarArchivo('uploadImagen1', $extensionesPermitidasIMG, $carpetaUploads, $maxTamanoImagen, $userId);
    if ($resImagen1 && isset($resImagen1['error'])) $errores[] = $resImagen1['error'];
    elseif ($resImagen1) $imagen1Subida = $resImagen1['nombre'];
    else $errores[] = "La imagen de portada es obligatoria";

    $resImagen2 = procesarArchivo('uploadImagen2', $extensionesPermitidasIMG, $carpetaUploads, $maxTamanoImagen, $userId);
    if ($resImagen2 && isset($resImagen2['error'])) $errores[] = $resImagen2['error'];
    elseif ($resImagen2) $imagen2Subida = $resImagen2['nombre'];

    $resImagen3 = procesarArchivo('uploadImagen3', $extensionesPermitidasIMG, $carpetaUploads, $maxTamanoImagen, $userId);
    if ($resImagen3 && isset($resImagen3['error'])) $errores[] = $resImagen3['error'];
    elseif ($resImagen3) $imagen3Subida = $resImagen3['nombre'];

    $resVideo = procesarArchivo('uploadVideo', $extensionesPermitidasVID, $carpetaUploads, $maxTamanoVideo, $userId);
    if ($resVideo && isset($resVideo['error'])) $errores[] = $resVideo['error'];
    elseif ($resVideo) $videoSubido = $resVideo['nombre'];

    if (empty($errores)) {
        try {
            $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
            $conn->begin_transaction();

            $insertLibro = $conn->prepare("
                INSERT INTO Libros (
                    titulo, autor, descripcion, editorial, edicion, categoria, tipoPublico, 
                    base, altura, paginas, fechaPublicacion, linkVideo, linkImagen1, linkImagen2, linkImagen3
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $editorial = substr($_POST['editorial'] ?? '', 0, 50);
            $edicion = substr($_POST['edicion'] ?? '', 0, 20);
            $categoria = substr($_POST['categoria'] ?? 'General', 0, 20);
            $tipoPublico = substr($_POST['tipoPublico'] ?? 'General', 0, 20);
            $base = !empty($_POST['base']) ? (float)$_POST['base'] : null;
            $altura = !empty($_POST['altura']) ? (float)$_POST['altura'] : null;
            $paginas = !empty($_POST['paginas']) ? (int)$_POST['paginas'] : null;
            $fechaPublicacion = isset($_POST['fechaPublicacion']) && !empty($_POST['fechaPublicacion']) ? $_POST['fechaPublicacion'] : NULL;
            $precio = !empty($_POST['precio']) ? (float)$_POST['precio'] : null;

            $insertLibro->bind_param("sssssssddiissss",
                $titulo, $autor, $descripcion, $editorial, $edicion, $categoria, $tipoPublico,
                $base, $altura, $paginas, $fechaPublicacion, $videoSubido, 
                $imagen1Subida, $imagen2Subida, $imagen3Subida
            );

            if (!$insertLibro->execute()) {
                throw new Exception("Error al insertar libro: " . $insertLibro->error);
            }

            $libroId = $conn->insert_id;
            $insertLibro->close();

            $hashtagIds = [];
            if (!empty($_POST['etiquetas'])) {
                $etiquetas = array_filter(array_map('trim', explode(',', $_POST['etiquetas'])));
                $etiquetas = array_unique($etiquetas);
                
                foreach ($etiquetas as $etiqueta) {
                    if (!empty($etiqueta) && strlen($etiqueta) <= 50) {
                        $etiqueta = htmlspecialchars($etiqueta);
                        
                        $checkHashtag = $conn->prepare("SELECT idHashtag FROM Hashtags WHERE texto = ?");
                        $checkHashtag->bind_param("s", $etiqueta);
                        $checkHashtag->execute();
                        $result = $checkHashtag->get_result();
                        
                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $hashtagIds[] = $row['idHashtag'];
                        } else {
                            $insertHashtag = $conn->prepare("INSERT INTO Hashtags (texto, fechaCreacion) VALUES (?, NOW())");
                            $insertHashtag->bind_param("s", $etiqueta);
                            if ($insertHashtag->execute()) {
                                $hashtagIds[] = $conn->insert_id;
                            }
                            $insertHashtag->close();
                        }
                        $checkHashtag->close();
                    }
                }
            }

            if (!empty($hashtagIds)) {
                $insertRelacion = $conn->prepare("INSERT INTO LibroHashtags (idLibro, idHashtag) VALUES (?, ?)");
                foreach ($hashtagIds as $hashtagId) {
                    $insertRelacion->bind_param("ii", $libroId, $hashtagId);
                    if (!$insertRelacion->execute()) {
                        throw new Exception("Error al insertar relación libro-hashtag");
                    }
                }
                $insertRelacion->close();
            }

            $insertPublicacion = $conn->prepare("
                INSERT INTO Publicaciones (idUsuario, idLibro, precio, fechaCreacion)
                VALUES (?, ?, ?, NOW())
            ");
            $insertPublicacion->bind_param("iid", $userId, $libroId, $precio);

            if (!$insertPublicacion->execute()) {
                throw new Exception("Error al insertar publicación: " . $insertPublicacion->error);
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => '¡Publicación creada exitosamente!']);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => "Error al guardar: " . $e->getMessage()]);
        } finally {
            if (isset($conn)) $conn->close();
        }
    } else {
        echo json_encode(['success' => false, 'message' => implode(', ', $errores)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
