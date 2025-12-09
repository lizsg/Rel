# REPORTE TÉCNICO - PROYECTO ReL

## 1. Descripción General
ReL es una plataforma web social diseñada para la interacción entre usuarios, combinando elementos de redes sociales tradicionales (perfiles, publicaciones, chat) con un asistente virtual integrado.

El sistema permite a los usuarios registrarse, personalizar sus perfiles, compartir actualizaciones de estado y fotos, comunicarse en tiempo real mediante un chat privado, y recibir asistencia automatizada.

## 2. Arquitectura del Sistema

### 2.1 Stack Tecnológico
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla).
- **Backend**: PHP 8.x (Estructurado/Procedural).
- **Base de Datos**: MySQL 8.0.
- **Servidor Web**: Apache.

### 2.2 Estructura de Directorios
El proyecto sigue una estructura organizada por funcionalidad:
- `/api`: Endpoints para peticiones AJAX (chat, publicaciones, limpieza de datos).
- `/assets`: Recursos estáticos (CSS, JS, Imágenes).
- `/bd`: Scripts SQL para la creación y estructura de la base de datos.
- `/config`: Archivos de configuración (conexión a BD, variables globales).
- `/includes`: Componentes reutilizables (ej. componente de chat).
- `/pages`: Vistas principales de la aplicación.
  - `/auth`: Gestión de usuarios (login, registro, perfil).
  - `/chat`: Interfaz de mensajería.
- `/uploads`: Almacenamiento de archivos subidos por usuarios.

## 3. Módulos Principales

### 3.1 Autenticación y Usuarios
- **Registro/Login**: Validación de credenciales y creación de sesiones seguras.
- **Perfil de Usuario**: 
  - Edición de información personal.
  - Carga de foto de perfil y foto de portada (con previsualización y validación).
  - Visualización de estadísticas (seguidores, publicaciones).

### 3.2 Sistema Social
- **Publicaciones**: Los usuarios pueden crear posts con texto e imágenes.
- **Feed**: Visualización cronológica de actividades.
- **Interacciones**: Sistema de notificaciones para eventos relevantes.

### 3.3 Comunicación (Chat)
- **Mensajería Privada**: Chat en tiempo real entre usuarios.
- **Asistente Virtual (ReL Bot)**: Chatbot integrado capaz de responder preguntas sobre la plataforma y brindar asistencia general, con personalidad adaptada a una red social.

### 3.4 Base de Datos
El modelo de datos (`ReLee.sql` y `social_schema.sql`) incluye tablas para:
- `Usuarios`: Información de cuenta y perfil.
- `PublicacionesSocial`: Posts de los usuarios.
- `Conversaciones` y `Mensajes`: Estructura para el chat privado.
- `HistorialFotos`: Registro de cambios de fotos de perfil/portada.
- `Notificaciones`: Sistema de alertas para el usuario.

## 4. Seguridad
- **Consultas Preparadas**: Uso de `mysqli->prepare` para prevenir inyección SQL.
- **Validación de Archivos**: Verificación estricta de tipos MIME y extensiones para subidas de imágenes.
- **Control de Sesiones**: Verificación de autenticación en páginas protegidas.

## 5. Estado Actual y Mejoras Recientes
- **Interfaz de Usuario**: Se han corregido problemas de superposición (z-index) en menús desplegables y notificaciones.
- **Funcionalidad de Perfil**: Implementación robusta de carga de imágenes de portada con validación en backend.
- **Chatbot**: Rebranding del asistente virtual para alinearse con la identidad "ReL".


