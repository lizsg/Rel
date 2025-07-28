-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 27, 2025 at 11:08 PM
-- Server version: 8.0.42-0ubuntu0.22.04.1
-- PHP Version: 8.1.2-1ubuntu2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ReLee`
--

-- --------------------------------------------------------

--
-- Table structure for table `Conversaciones`
--

CREATE TABLE `Conversaciones` (
  `idConversacion` int NOT NULL,
  `idUsuario1` int NOT NULL,
  `idUsuario2` int NOT NULL,
  `fechaCreacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `ultimoMensaje` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Hashtags`
--

CREATE TABLE `Hashtags` (
  `idHashtag` int NOT NULL,
  `texto` varchar(50) NOT NULL,
  `fechaCreacion` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `LibroHashtags`
--

CREATE TABLE `LibroHashtags` (
  `idLibro` int NOT NULL,
  `idHashtag` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Libros`
--

CREATE TABLE `Libros` (
  `idLibro` int NOT NULL,
  `titulo` varchar(70) NOT NULL,
  `autor` varchar(70) DEFAULT NULL,
  `descripcion` text NOT NULL,
  `editorial` varchar(20) DEFAULT NULL,
  `edicion` varchar(20) DEFAULT NULL,
  `categoria` varchar(20) NOT NULL,
  `tipoPublico` varchar(20) NOT NULL,
  `base` int DEFAULT NULL,
  `altura` int DEFAULT NULL,
  `paginas` int DEFAULT NULL,
  `fechaPublicacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `linkVideo` varchar(100) NOT NULL,
  `linkImagen1` varchar(100) NOT NULL,
  `linkImagen2` varchar(100) NOT NULL,
  `linkImagen3` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Mensajes`
--

CREATE TABLE `Mensajes` (
  `idMensaje` int NOT NULL,
  `idConversacion` int NOT NULL,
  `idRemitente` int NOT NULL,
  `contenido` text NOT NULL,
  `fechaEnvio` datetime DEFAULT CURRENT_TIMESTAMP,
  `leido` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `MensajesLeidos`
--

CREATE TABLE `MensajesLeidos` (
  `idMensaje` int NOT NULL,
  `idUsuario` int NOT NULL,
  `fechaLeido` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Publicaciones`
--

CREATE TABLE `Publicaciones` (
  `idPublicacion` int NOT NULL,
  `idUsuario` int NOT NULL,
  `idLibro` int NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `fechaCreacion` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Usuarios`
--

CREATE TABLE `Usuarios` (
  `idUsuario` int NOT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `userName` varchar(50) NOT NULL,
  `correo` varchar(70) NOT NULL,
  `contraseña` varchar(255) NOT NULL,
  `fechaNacimiento` date NOT NULL,
  `telefono` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Usuarios`
--

INSERT INTO `Usuarios` (`idUsuario`, `nombre`, `userName`, `correo`, `contraseña`, `fechaNacimiento`, `telefono`) VALUES
(1, 'Lizbeth', 'Lizbeth', 'lizbeth@gmail.com', '1234', '2000-07-26', '8341659305'),
(2, 'Georgina Reta', 'Gina', 'gina@gmail.com', '1234', '2006-02-24', '8341585398');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Conversaciones`
--
ALTER TABLE `Conversaciones`
  ADD PRIMARY KEY (`idConversacion`),
  ADD UNIQUE KEY `uk_usuarios_conversacion` (`idUsuario1`,`idUsuario2`),
  ADD KEY `idx_usuario1` (`idUsuario1`),
  ADD KEY `idx_usuario2` (`idUsuario2`);

--
-- Indexes for table `Hashtags`
--
ALTER TABLE `Hashtags`
  ADD PRIMARY KEY (`idHashtag`),
  ADD UNIQUE KEY `texto` (`texto`);

--
-- Indexes for table `LibroHashtags`
--
ALTER TABLE `LibroHashtags`
  ADD PRIMARY KEY (`idLibro`,`idHashtag`),
  ADD KEY `idHashtag` (`idHashtag`);

--
-- Indexes for table `Libros`
--
ALTER TABLE `Libros`
  ADD PRIMARY KEY (`idLibro`);

--
-- Indexes for table `Mensajes`
--
ALTER TABLE `Mensajes`
  ADD PRIMARY KEY (`idMensaje`),
  ADD KEY `idx_conversacion` (`idConversacion`),
  ADD KEY `idx_remitente` (`idRemitente`),
  ADD KEY `idx_fecha_envio` (`fechaEnvio`);

--
-- Indexes for table `MensajesLeidos`
--
ALTER TABLE `MensajesLeidos`
  ADD PRIMARY KEY (`idMensaje`,`idUsuario`),
  ADD KEY `idUsuario` (`idUsuario`);

--
-- Indexes for table `Publicaciones`
--
ALTER TABLE `Publicaciones`
  ADD PRIMARY KEY (`idPublicacion`),
  ADD KEY `idUsuario` (`idUsuario`),
  ADD KEY `idLibro` (`idLibro`);

--
-- Indexes for table `Usuarios`
--
ALTER TABLE `Usuarios`
  ADD PRIMARY KEY (`idUsuario`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD UNIQUE KEY `nombre_2` (`nombre`),
  ADD UNIQUE KEY `userName` (`userName`,`correo`,`telefono`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Conversaciones`
--
ALTER TABLE `Conversaciones`
  MODIFY `idConversacion` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Hashtags`
--
ALTER TABLE `Hashtags`
  MODIFY `idHashtag` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Libros`
--
ALTER TABLE `Libros`
  MODIFY `idLibro` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Mensajes`
--
ALTER TABLE `Mensajes`
  MODIFY `idMensaje` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Publicaciones`
--
ALTER TABLE `Publicaciones`
  MODIFY `idPublicacion` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Usuarios`
--
ALTER TABLE `Usuarios`
  MODIFY `idUsuario` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Conversaciones`
--
ALTER TABLE `Conversaciones`
  ADD CONSTRAINT `Conversaciones_ibfk_1` FOREIGN KEY (`idUsuario1`) REFERENCES `Usuarios` (`idUsuario`),
  ADD CONSTRAINT `Conversaciones_ibfk_2` FOREIGN KEY (`idUsuario2`) REFERENCES `Usuarios` (`idUsuario`);

--
-- Constraints for table `LibroHashtags`
--
ALTER TABLE `LibroHashtags`
  ADD CONSTRAINT `LibroHashtags_ibfk_1` FOREIGN KEY (`idLibro`) REFERENCES `Libros` (`idLibro`) ON DELETE CASCADE,
  ADD CONSTRAINT `LibroHashtags_ibfk_2` FOREIGN KEY (`idHashtag`) REFERENCES `Hashtags` (`idHashtag`) ON DELETE CASCADE;

--
-- Constraints for table `Mensajes`
--
ALTER TABLE `Mensajes`
  ADD CONSTRAINT `Mensajes_ibfk_1` FOREIGN KEY (`idConversacion`) REFERENCES `Conversaciones` (`idConversacion`),
  ADD CONSTRAINT `Mensajes_ibfk_2` FOREIGN KEY (`idRemitente`) REFERENCES `Usuarios` (`idUsuario`);

--
-- Constraints for table `MensajesLeidos`
--
ALTER TABLE `MensajesLeidos`
  ADD CONSTRAINT `MensajesLeidos_ibfk_1` FOREIGN KEY (`idMensaje`) REFERENCES `Mensajes` (`idMensaje`),
  ADD CONSTRAINT `MensajesLeidos_ibfk_2` FOREIGN KEY (`idUsuario`) REFERENCES `Usuarios` (`idUsuario`);

--
-- Constraints for table `Publicaciones`
--
ALTER TABLE `Publicaciones`
  ADD CONSTRAINT `Publicaciones_ibfk_1` FOREIGN KEY (`idUsuario`) REFERENCES `Usuarios` (`idUsuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `Publicaciones_ibfk_2` FOREIGN KEY (`idLibro`) REFERENCES `Libros` (`idLibro`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
