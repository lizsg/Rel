<?php
// diagnostico_upload.php
// Ejecuta este archivo visitando: http://tu-dominio/pages/auth/diagnostico_upload.php

echo "<h1>🔍 Diagnóstico del Sistema de Upload</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .check { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .section { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    pre { background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>";

// 1. Verificar directorio uploads
echo "<div class='section'>";
echo "<h2>📁 Directorio Uploads</h2>";

$uploadDir = __DIR__ . '/../../uploads/';
$uploadDirExists = is_dir($uploadDir);
$uploadDirWritable = is_writable($uploadDir);

echo "<p><strong>Ruta:</strong> " . realpath($uploadDir) . "</p>";
echo "<p><strong>¿Existe?</strong> " . ($uploadDirExists ? "<span class='check'>✓ SÍ</span>" : "<span class='error'>✗ NO</span>") . "</p>";
echo "<p><strong>¿Escribible?</strong> " . ($uploadDirWritable ? "<span class='check'>✓ SÍ</span>" : "<span class='error'>✗ NO</span>") . "</p>";

if ($uploadDirExists) {
    $perms = substr(sprintf('%o', fileperms($uploadDir)), -4);
    echo "<p><strong>Permisos:</strong> " . $perms . "</p>";
    
    if ($perms < '0755') {
        echo "<p class='error'>⚠️ Los permisos son muy restrictivos. Necesitas al menos 0755</p>";
        echo "<p><strong>Solución:</strong> Ejecuta: <code>chmod 755 " . realpath($uploadDir) . "</code></p>";
    }
}

if (!$uploadDirExists) {
    echo "<p class='error'>❌ El directorio no existe. Intentando crearlo...</p>";
    if (mkdir($uploadDir, 0755, true)) {
        echo "<p class='check'>✓ Directorio creado exitosamente</p>";
    } else {
        echo "<p class='error'>✗ No se pudo crear el directorio</p>";
        echo "<p><strong>Solución:</strong> Créalo manualmente y dale permisos 755</p>";
    }
}
echo "</div>";

// 2. Verificar configuración PHP
echo "<div class='section'>";
echo "<h2>⚙️ Configuración PHP</h2>";

$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');
$memoryLimit = ini_get('memory_limit');
$maxExecutionTime = ini_get('max_execution_time');

echo "<p><strong>upload_max_filesize:</strong> " . $uploadMaxFilesize . "</p>";
echo "<p><strong>post_max_size:</strong> " . $postMaxSize . "</p>";
echo "<p><strong>memory_limit:</strong> " . $memoryLimit . "</p>";
echo "<p><strong>max_execution_time:</strong> " . $maxExecutionTime . " segundos</p>";

$uploadMaxBytes = return_bytes($uploadMaxFilesize);
$postMaxBytes = return_bytes($postMaxSize);

if ($uploadMaxBytes < 5 * 1024 * 1024) {
    echo "<p class='warning'>⚠️ upload_max_filesize es menor a 5MB</p>";
}

if ($postMaxBytes < 5 * 1024 * 1024) {
    echo "<p class='warning'>⚠️ post_max_size es menor a 5MB</p>";
}

echo "</div>";

// 3. Verificar extensiones PHP
echo "<div class='section'>";
echo "<h2>🔧 Extensiones PHP</h2>";

$requiredExtensions = ['gd', 'mysqli', 'fileinfo'];
foreach ($requiredExtensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "<p><strong>$ext:</strong> " . ($loaded ? "<span class='check'>✓ Cargada</span>" : "<span class='error'>✗ No cargada</span>") . "</p>";
}
echo "</div>";

// 4. Verificar conexión a base de datos
echo "<div class='section'>";
echo "<h2>🗄️ Base de Datos</h2>";

if (file_exists(__DIR__ . '/../../config/database.php')) {
    require_once __DIR__ . '/../../config/database.php';
    
    try {
        $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            echo "<p class='error'>✗ Error de conexión: " . $conn->connect_error . "</p>";
        } else {
            echo "<p class='check'>✓ Conexión exitosa</p>";
            
            // Verificar estructura de tabla
            $result = $conn->query("SHOW COLUMNS FROM Usuarios LIKE 'fotoPerfil'");
            if ($result && $result->num_rows > 0) {
                echo "<p class='check'>✓ Campo 'fotoPerfil' existe</p>";
            } else {
                echo "<p class='error'>✗ Campo 'fotoPerfil' NO existe</p>";
                echo "<p>Ejecuta: <code>ALTER TABLE Usuarios ADD COLUMN fotoPerfil VARCHAR(255) DEFAULT NULL;</code></p>";
            }
            
            $result = $conn->query("SHOW COLUMNS FROM Usuarios LIKE 'fotoPortada'");
            if ($result && $result->num_rows > 0) {
                echo "<p class='check'>✓ Campo 'fotoPortada' existe</p>";
            } else {
                echo "<p class='error'>✗ Campo 'fotoPortada' NO existe</p>";
                echo "<p>Ejecuta: <code>ALTER TABLE Usuarios ADD COLUMN fotoPortada VARCHAR(255) DEFAULT NULL;</code></p>";
            }
            
            $conn->close();
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>✗ Archivo database.php no encontrado</p>";
}
echo "</div>";

// 5. Test de escritura
echo "<div class='section'>";
echo "<h2>📝 Test de Escritura</h2>";

if ($uploadDirExists && $uploadDirWritable) {
    $testFile = $uploadDir . 'test_' . time() . '.txt';
    $testContent = 'Test de escritura - ' . date('Y-m-d H:i:s');
    
    if (file_put_contents($testFile, $testContent)) {
        echo "<p class='check'>✓ Escritura exitosa</p>";
        
        if (file_exists($testFile)) {
            echo "<p class='check'>✓ Archivo creado correctamente</p>";
            
            // Leer archivo
            $content = file_get_contents($testFile);
            if ($content === $testContent) {
                echo "<p class='check'>✓ Lectura exitosa</p>";
            }
            
            // Eliminar archivo de prueba
            @unlink($testFile);
            echo "<p class='check'>✓ Archivo de prueba eliminado</p>";
        }
    } else {
        echo "<p class='error'>✗ No se pudo escribir en el directorio</p>";
    }
}
echo "</div>";

// 6. Archivos en uploads
echo "<div class='section'>";
echo "<h2>📂 Archivos en Uploads</h2>";

if ($uploadDirExists) {
    $files = scandir($uploadDir);
    $imageFiles = array_filter($files, function($file) use ($uploadDir) {
        return is_file($uploadDir . $file) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    });
    
    echo "<p><strong>Total de imágenes:</strong> " . count($imageFiles) . "</p>";
    
    if (count($imageFiles) > 0) {
        echo "<ul>";
        foreach (array_slice($imageFiles, 0, 10) as $file) {
            $size = filesize($uploadDir . $file);
            echo "<li>$file (" . formatBytes($size) . ")</li>";
        }
        echo "</ul>";
    }
}
echo "</div>";

// 7. Recomendaciones
echo "<div class='section'>";
echo "<h2>💡 Recomendaciones</h2>";

$recommendations = [];

if (!$uploadDirExists || !$uploadDirWritable) {
    $recommendations[] = "Crea el directorio /uploads/ y dale permisos 755 o 777";
}

if ($uploadMaxBytes < 5 * 1024 * 1024) {
    $recommendations[] = "Aumenta upload_max_filesize a 10M en php.ini";
}

if ($postMaxBytes < 5 * 1024 * 1024) {
    $recommendations[] = "Aumenta post_max_size a 10M en php.ini";
}

if (count($recommendations) > 0) {
    echo "<ol>";
    foreach ($recommendations as $rec) {
        echo "<li>$rec</li>";
    }
    echo "</ol>";
} else {
    echo "<p class='check'>✓ Todo parece estar configurado correctamente</p>";
}

echo "</div>";

// Funciones auxiliares
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = intval($val);
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

echo "<div class='section'>";
echo "<h2>✅ Siguiente Paso</h2>";
echo "<p>Si todo está en verde, elimina este archivo por seguridad:</p>";
echo "<code>rm " . __FILE__ . "</code>";
echo "</div>";
?>