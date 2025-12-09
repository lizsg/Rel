CREATE TABLE IF NOT EXISTS `PublicacionesSocial` (
  `idPublicacion` int NOT NULL AUTO_INCREMENT,
  `idUsuario` int NOT NULL,
  `contenido` text,
  `imagen` varchar(255) DEFAULT NULL,
  `tipo` enum('estado', 'foto', 'foto_perfil') NOT NULL DEFAULT 'estado',
  `fechaCreacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPublicacion`),
  KEY `idUsuario` (`idUsuario`),
  CONSTRAINT `PublicacionesSocial_ibfk_1` FOREIGN KEY (`idUsuario`) REFERENCES `Usuarios` (`idUsuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
