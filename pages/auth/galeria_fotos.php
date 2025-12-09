<?php
session_start();

if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']) || !isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../../config/database.php';
$userId = $_SESSION['user_id'];

$usuario = [];
$fotosHistorial = [];
$errorMessage = '';

try {
    $conn = new mysqli(SERVER_NAME, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión");
    }

    // Obtener datos del usuario
    $userStmt = $conn->prepare("SELECT * FROM Usuarios WHERE idUsuario = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if ($userResult->num_rows === 0) {
        throw new Exception("Usuario no encontrado");
    }
    
    $usuario = $userResult->fetch_assoc();

    // Obtener historial de fotos de perfil y portada
    $fotosStmt = $conn->prepare("
        SELECT 
            idHistorial,
            tipoFoto,
            rutaArchivo,
            fechaCambio,
            esActual
        FROM HistorialFotos 
        WHERE idUsuario = ? 
        ORDER BY fechaCambio DESC
    ");
    $fotosStmt->bind_param("i", $userId);
    $fotosStmt->execute();
    $fotosResult = $fotosStmt->get_result();
    
    while ($row = $fotosResult->fetch_assoc()) {
        $fotosHistorial[] = $row;
    }

    $conn->close();

} catch (Exception $e) {
    $errorMessage = "Error al cargar datos: " . $e->getMessage();
}

function tiempoTranscurrido($fecha) {
    $tiempo = time() - strtotime($fecha);
    
    if ($tiempo < 60) return 'Hace un momento';
    if ($tiempo < 3600) return 'Hace ' . floor($tiempo/60) . ' minutos';
    if ($tiempo < 86400) return 'Hace ' . floor($tiempo/3600) . ' horas';
    if ($tiempo < 604800) return 'Hace ' . floor($tiempo/86400) . ' días';
    if ($tiempo < 2592000) return 'Hace ' . floor($tiempo/604800) . ' semanas';
    return 'Hace ' . floor($tiempo/2592000) . ' meses';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Fotos | RELEE</title>
    <style>
        :root {
            --primary-brown: #6b4226;
            --secondary-brown: #8b5a3c;
            --light-brown: #d6c1b2;
            --cream-bg: #f8f6f3;
            --green-primary: #a3b18a;
            --green-secondary: #588157;
            --text-primary: #2c2016;
            --text-secondary: #6f5c4d;
            --text-muted: #888;
            --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--cream-bg);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .top-header {
            padding: 15px 20px;
            background: white;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: rgba(163, 177, 138, 0.1);
            color: var(--green-secondary);
        }

        .page-title {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 30px;
            color: var(--primary-brown);
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 12px 24px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            background: white;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-primary) 100%);
            color: white;
            border-color: var(--green-secondary);
        }

        .filter-tab:hover {
            border-color: var(--green-secondary);
        }

        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .photo-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .photo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .photo-container {
            width: 100%;
            height: 280px;
            overflow: hidden;
            position: relative;
            background: var(--light-brown);
        }

        .photo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .photo-card:hover .photo-container img {
            transform: scale(1.1);
        }

        .current-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-primary) 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .photo-type-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .photo-info {
            padding: 15px;
        }

        .photo-date {
            color: var(--text-muted);
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .photo-actions {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }

        .btn-view {
            background: linear-gradient(135deg, var(--green-secondary) 0%, var(--green-primary) 100%);
            color: white;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-restore {
            background: linear-gradient(135deg, var(--primary-brown) 0%, var(--secondary-brown) 100%);
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .empty-state-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            max-width: 90%;
            max-height: 90%;
            position: relative;
        }

        .modal-content img {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 8px;
        }

        .close-modal {
            position: absolute;
            top: -40px;
            right: 0;
            background: white;
            color: var(--text-primary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
        }

        @media (max-width: 768px) {
            .photos-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }

            .photo-container {
                height: 150px;
            }

            .page-title {
                font-size: 1.5em;
            }

            .filter-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 10px;
            }

            .filter-tab {
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <div class="top-header">
        <a href="perfil.php" class="back-button">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
            </svg>
            Volver al perfil
        </a>
    </div>

    <div class="container">
        <h1 class="page-title">Mis Fotos</h1>

        <?php if ($errorMessage): ?>
            <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="filter-tabs">
            <button class="filter-tab active" data-filter="all">Todas</button>
            <button class="filter-tab" data-filter="perfil">Fotos de Perfil</button>
            <button class="filter-tab" data-filter="portada">Fotos de Portada</button>
            <button class="filter-tab" data-filter="actual">Actuales</button>
        </div>

        <div class="photos-grid">
            <?php if (empty($fotosHistorial)): ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <h3>No tienes fotos aún</h3>
                    <p>Sube tu primera foto de perfil o portada para comenzar tu galería</p>
                </div>
            <?php else: ?>
                <?php foreach ($fotosHistorial as $foto): ?>
                    <div class="photo-card" data-type="<?php echo $foto['tipoFoto']; ?>" data-current="<?php echo $foto['esActual']; ?>">
                        <div class="photo-container">
                            <img src="../../uploads/<?php echo htmlspecialchars($foto['rutaArchivo']); ?>" 
                                 alt="Foto de <?php echo $foto['tipoFoto']; ?>"
                                 onclick="openModal(this.src)">
                            
                            <?php if ($foto['esActual'] == 1): ?>
                                <div class="current-badge">✓ Actual</div>
                            <?php endif; ?>
                            
                            <div class="photo-type-badge">
                                <?php echo $foto['tipoFoto'] == 'perfil' ? 'Perfil' : ' Portada'; ?>
                            </div>
                        </div>
                        
                        <div class="photo-info">
                            <div class="photo-date">
                                <?php echo tiempoTranscurrido($foto['fechaCambio']); ?>
                            </div>
                            <div class="photo-actions">
                                <button class="btn-action btn-view" onclick="openModal('../../uploads/<?php echo htmlspecialchars($foto['rutaArchivo']); ?>')">
                                    Ver
                                </button>
                                <?php if ($foto['esActual'] == 0): ?>
                                    <button class="btn-action btn-restore" onclick="restaurarFoto(<?php echo $foto['idHistorial']; ?>)">
                                        Restaurar
                                    </button>
                                    <button class="btn-action btn-delete" onclick="eliminarFoto(<?php echo $foto['idHistorial']; ?>)">
                                        Eliminar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para ver imagen en grande -->
    <div id="imageModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal()">×</button>
            <img id="modalImage" src="" alt="Vista previa">
        </div>
    </div>

    <script>
        // Filtros
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                const cards = document.querySelectorAll('.photo-card');
                
                cards.forEach(card => {
                    if (filter === 'all') {
                        card.style.display = 'block';
                    } else if (filter === 'actual') {
                        card.style.display = card.dataset.current === '1' ? 'block' : 'none';
                    } else {
                        card.style.display = card.dataset.type === filter ? 'block' : 'none';
                    }
                });
            });
        });

        // Modal
        function openModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('imageModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Restaurar foto
        function restaurarFoto(idHistorial) {
            if (confirm('¿Restaurar esta foto como actual?')) {
                fetch('restaurar_foto.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'idHistorial=' + idHistorial
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        // Eliminar foto
        function eliminarFoto(idHistorial) {
            if (confirm('¿Eliminar esta foto permanentemente?')) {
                fetch('eliminar_foto.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'idHistorial=' + idHistorial
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
    </script>
</body>
</html>