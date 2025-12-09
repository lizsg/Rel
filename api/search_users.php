<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'users' => []]);
    exit();
}

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        throw new Exception("Error de conexión");
    }

    $searchTerm = "%{$query}%";
    $stmt = $conn->prepare("
        SELECT idUsuario, nombre, userName, fotoPerfil 
        FROM Usuarios 
        WHERE (nombre LIKE ? OR userName LIKE ?) 
        AND idUsuario != ?
        LIMIT 10
    ");
    
    $userId = $_SESSION['user_id'];
    $stmt->bind_param("ssi", $searchTerm, $searchTerm, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure photo path is correct
        $photo = $row['fotoPerfil'];
        if (empty($photo) || $photo === 'default-avatar.png') {
            $photo = 'assets/images/default-avatar.png'; // Adjust path as needed
        } elseif (!str_starts_with($photo, 'uploads/') && !str_starts_with($photo, 'http')) {
             // Assuming photos are in uploads/users/ or similar, but based on DB dump they seem to be filenames
             // Let's assume they are in uploads/ or pages/auth/uploads/
             // Based on home.php, it seems complex. Let's just pass the value and handle in frontend.
        }
        
        $users[] = [
            'id' => (int)$row['idUsuario'],
            'name' => $row['nombre'],
            'username' => $row['userName'],
            'avatar' => $row['fotoPerfil']
        ];
    }
    
    echo json_encode(['success' => true, 'users' => $users]);
    
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>