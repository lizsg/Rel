<?php
define('SERVER_NAME', 'localhost');  // Cambiado a localhost
define('DB_USER', 'admin');  // Nombre de usuario de la base de datos
define('DB_PASS', '1a775d52ecd11bcecf034cbf6a70bac23f874bdf99605178');  // Contraseña de acceso
define('DB_NAME', 'ReLee');  // Nombre de la base de datos

// Crear conexión
$conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);

// Comprobar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>