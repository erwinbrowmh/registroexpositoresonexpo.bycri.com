-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 24-02-2026 a las 11:38:02
-- Versión del servidor: 10.6.25-MariaDB
-- Versión de PHP: 8.4.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `crivirtual_registro_onexpo2026`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administradores`
--

CREATE TABLE `administradores` (
  `id_admin` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asientos`
--

CREATE TABLE `asientos` (
  `id_asiento` int(11) NOT NULL,
  `id_mesa` int(11) DEFAULT NULL,
  `numero_asiento` int(11) NOT NULL,
  `estatus` enum('disponible','reservado','bloqueado') DEFAULT 'disponible',
  `reservation_expires_at` datetime DEFAULT NULL,
  `temp_session_id` varchar(100) DEFAULT NULL,
  `reserva_corporativa` varchar(200) DEFAULT NULL COMMENT 'Nombre de organización si es reserva corporativa',
  `id_usuario_reservado` int(11) DEFAULT NULL,
  `id_reserva_corporativa` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `capturistas`
--

CREATE TABLE `capturistas` (
  `id_capturista` int(11) NOT NULL,
  `nombre_capturista` varchar(150) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `city`
--

CREATE TABLE `city` (
  `Name` varchar(255) DEFAULT NULL,
  `Country` varchar(10) DEFAULT NULL,
  `Province` varchar(255) DEFAULT NULL,
  `Population` int(11) DEFAULT NULL,
  `Longitude` decimal(11,8) DEFAULT NULL,
  `Latitude` decimal(10,8) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `country`
--

CREATE TABLE `country` (
  `Name` varchar(50) NOT NULL,
  `Code` varchar(10) DEFAULT NULL,
  `Capital` varchar(50) DEFAULT NULL,
  `Province` varchar(50) DEFAULT NULL,
  `Area` int(11) DEFAULT NULL,
  `Population` bigint(20) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_tickets`
--

CREATE TABLE `detalle_tickets` (
  `id_ticket` int(11) NOT NULL,
  `id_venta` int(11) DEFAULT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `codigo_qr` varchar(100) DEFAULT NULL,
  `asistente_nombre` varchar(150) DEFAULT NULL,
  `id_asiento` int(11) DEFAULT NULL,
  `checkin_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas`
--

CREATE TABLE `empresas` (
  `id` int(11) NOT NULL,
  `nombre_empresa` varchar(255) NOT NULL,
  `limite_participantes` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expositores`
--

CREATE TABLE `expositores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `apellido` varchar(255) DEFAULT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `correo` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `giro` varchar(255) DEFAULT NULL,
  `requiere_mampara` tinyint(1) DEFAULT 0,
  `razon_social` varchar(255) DEFAULT NULL,
  `cargo_contacto` varchar(255) DEFAULT NULL,
  `giro_empresa` varchar(255) DEFAULT NULL,
  `logo_ruta` varchar(255) DEFAULT NULL,
  `banner_ruta` varchar(255) DEFAULT NULL,
  `video_promocional_ruta` varchar(255) DEFAULT NULL,
  `descripcion_breve` TEXT DEFAULT NULL,
  `responsiva_ruta` varchar(255) DEFAULT NULL,
  `mampara` tinyint(1) DEFAULT 0,
  `rotulo_antepecho` varchar(255) DEFAULT NULL,
  `hoja_responsiva_ruta` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_empresa` int(11) DEFAULT NULL,
  `stand` varchar(50) DEFAULT NULL,
  `tipo_stand` varchar(50) DEFAULT NULL,
  `acceso` varchar(255) DEFAULT NULL,
  `reset_code` varchar(10) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `whatsapp` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `facturas`
--

CREATE TABLE `facturas` (
  `id_factura` int(11) NOT NULL,
  `purchase_group` varchar(100) NOT NULL,
  `rfc` varchar(20) NOT NULL,
  `regimen_fiscal` varchar(100) DEFAULT NULL,
  `razon_social` varchar(255) NOT NULL,
  `uso_cfdi` varchar(10) NOT NULL,
  `codigo_postal` varchar(10) DEFAULT NULL,
  `direccion` mediumtext DEFAULT NULL,
  `monto_total` decimal(10,2) DEFAULT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `estatus` enum('pendiente','completada','rechazada') DEFAULT 'pendiente',
  `folio_factura` varchar(50) DEFAULT NULL,
  `codigo_qr_venta` varchar(100) NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `factura_tickets`
--

CREATE TABLE `factura_tickets` (
  `id_factura` int(11) NOT NULL,
  `id_ticket` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `folios_consecutivos`
--

CREATE TABLE `folios_consecutivos` (
  `id_folio` int(11) NOT NULL,
  `folio` varchar(20) NOT NULL,
  `purchase_group` varchar(100) NOT NULL,
  `id_ticket` int(11) DEFAULT NULL,
  `tipo_pago` varchar(50) DEFAULT NULL,
  `monto_total` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `folio_counter`
--

CREATE TABLE `folio_counter` (
  `prefix` varchar(10) NOT NULL,
  `current_number` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mesas`
--

CREATE TABLE `mesas` (
  `id_mesa` int(11) NOT NULL,
  `numero_mesa` int(11) NOT NULL,
  `capacidad` int(11) DEFAULT 10,
  `zona` varchar(50) DEFAULT NULL,
  `pos_x` float DEFAULT 0,
  `pos_y` float DEFAULT 0,
  `color` varchar(20) DEFAULT 'blue'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `participantes`
--

CREATE TABLE `participantes` (
  `id` int(11) NOT NULL,
  `expositor_id` int(11) NOT NULL,
  `nombre_completo` varchar(255) NOT NULL,
  `cargo_puesto` varchar(255) DEFAULT NULL,
  `empresa` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `correo` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `payment_logs`
--

CREATE TABLE `payment_logs` (
  `id` int(11) NOT NULL,
  `payment_id` varchar(100) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_tickets`
--

CREATE TABLE `productos_tickets` (
  `id_producto` int(11) NOT NULL,
  `nombre_ticket` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `stock_disponible` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `province`
--

CREATE TABLE `province` (
  `Name` varchar(255) DEFAULT NULL,
  `Country` varchar(10) DEFAULT NULL,
  `Population` int(11) DEFAULT NULL,
  `Area` int(11) DEFAULT NULL,
  `Capital` varchar(255) DEFAULT NULL,
  `CapProv` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservas_corporativas`
--

CREATE TABLE `reservas_corporativas` (
  `id_reserva` int(11) NOT NULL,
  `nombre_organizacion` varchar(200) NOT NULL,
  `cantidad_lugares` int(11) NOT NULL,
  `mesas_asignadas` varchar(500) DEFAULT NULL,
  `asientos_ids` mediumtext DEFAULT NULL COMMENT 'IDs de asientos reservados (JSON array)',
  `contacto_nombre` varchar(200) DEFAULT NULL,
  `contacto_email` varchar(200) DEFAULT NULL,
  `contacto_telefono` varchar(50) DEFAULT NULL,
  `notas` mediumtext DEFAULT NULL,
  `estatus_pago` enum('pendiente','pagado') DEFAULT 'pendiente',
  `monto_total` decimal(10,2) DEFAULT 0.00,
  `estatus` enum('activa','completada','cancelada') DEFAULT 'activa',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `staff`
--

CREATE TABLE `staff` (
  `id_staff` int(11) NOT NULL,
  `nombre_staff` varchar(150) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tickets`
--

CREATE TABLE `tickets` (
  `id_ticket` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_comprador` int(11) DEFAULT NULL,
  `id_producto` int(11) NOT NULL,
  `id_asiento` int(11) DEFAULT NULL,
  `checkin_time` datetime DEFAULT NULL,
  `codigo_qr` varchar(50) NOT NULL,
  `estatus_pago` enum('pendiente','pagado','cancelado','pendiente_validacion','cortesia') DEFAULT 'pendiente',
  `folio_pago` varchar(100) DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `monto_total` decimal(10,2) DEFAULT 0.00,
  `stripe_session_id` varchar(255) DEFAULT NULL,
  `comprobante_pago` varchar(255) DEFAULT NULL,
  `es_cortesia` tinyint(1) DEFAULT 0,
  `comentario_cortesia` varchar(255) DEFAULT NULL,
  `tipo_gafete` enum('normal','expositor','prensa') DEFAULT 'normal' COMMENT 'Tipo de gafete (sin QR para expositor/prensa)',
  `purchase_group` varchar(100) DEFAULT NULL,
  `id_reserva_corporativa` int(11) DEFAULT NULL COMMENT 'ID de reserva corporativa si aplica',
  `participa_rifa` tinyint(1) DEFAULT 0,
  `entrega_obsequio` tinyint(1) DEFAULT 0,
  `participa_dinamica` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultimo_envio_email` datetime DEFAULT NULL,
  `clip_payment_id` varchar(255) DEFAULT NULL,
  `clip_transaction_id` varchar(255) DEFAULT NULL,
  `clip_payment_url` mediumtext DEFAULT NULL COMMENT 'URL de pago generada por Clip',
  `clip_payment_status` varchar(50) DEFAULT NULL COMMENT 'Estado del pago en Clip',
  `clip_payment_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `clip_webhook_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `payment_session_id` varchar(255) DEFAULT NULL COMMENT 'Session ID del pago en Clip',
  `clip_payment_reference` varchar(150) DEFAULT NULL,
  `clip_receipt_no` varchar(50) DEFAULT NULL,
  `clip_auth_code` varchar(50) DEFAULT NULL,
  `clip_last4` varchar(10) DEFAULT NULL,
  `clip_issuer` varchar(50) DEFAULT NULL,
  `clip_payment_date` varchar(50) DEFAULT NULL,
  `clip_amount` decimal(10,2) DEFAULT NULL,
  `clip_email` varchar(150) DEFAULT NULL,
  `clip_status_msg` varchar(100) DEFAULT NULL,
  `clip_card_type` varchar(20) DEFAULT NULL,
  `clip_card_brand` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expositor_imagenes_galeria`
--

CREATE TABLE `expositor_imagenes_galeria` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `expositor_id` INT(11) NOT NULL,
  `imagen_ruta` VARCHAR(255) NOT NULL,
  `orden` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `fk_expositor_imagenes_galeria_expositor_id` (`expositor_id`),
  CONSTRAINT `fk_expositor_imagenes_galeria_expositor_id` FOREIGN KEY (`expositor_id`) REFERENCES `expositores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expositor_videos_galeria`
--

CREATE TABLE `expositor_videos_galeria` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `expositor_id` INT(11) NOT NULL,
  `video_ruta` VARCHAR(255) NOT NULL,
  `orden` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `fk_expositor_videos_galeria_expositor_id` (`expositor_id`),
  CONSTRAINT `fk_expositor_videos_galeria_expositor_id` FOREIGN KEY (`expositor_id`) REFERENCES `expositores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_registro`
--

CREATE TABLE `tipos_registro` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transplano`
--

CREATE TABLE `transplano` (
  `id_transplano` int(11) NOT NULL,
  `tipo_usuario` enum('administrador','capturista','staff','expositor') NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `password_plain` varchar(255) NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabla de contraseñas en texto plano - ACCESO RESTRINGIDO SOLO SUPER ADMIN';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `email` varchar(100) NOT NULL,
  `folio` varchar(20) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT current_timestamp(),
  `puesto` varchar(100) DEFAULT NULL,
  `tipo_negocio` varchar(50) DEFAULT NULL,
  `tipo_negocio_otro` varchar(100) DEFAULT NULL,
  `empresa` varchar(150) DEFAULT NULL,
  `asociacion` varchar(150) DEFAULT NULL,
  `genero` varchar(20) DEFAULT NULL,
  `pais` varchar(100) DEFAULT NULL,
  `estado_provincia` varchar(100) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `tipo_registro` int(11) DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id_venta` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha_compra` datetime DEFAULT current_timestamp(),
  `total_pago` decimal(10,2) DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `estatus` enum('pendiente','pagado','cancelado') DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `webhooks_clip`
--

CREATE TABLE `webhooks_clip` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `payload_completo` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `fecha_recepcion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `administradores`
--
ALTER TABLE `administradores`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Indices de la tabla `asientos`
--
ALTER TABLE `asientos`
  ADD PRIMARY KEY (`id_asiento`),
  ADD KEY `id_mesa` (`id_mesa`),
  ADD KEY `idx_estatus` (`estatus`),
  ADD KEY `idx_reserva` (`id_reserva_corporativa`),
  ADD KEY `idx_asientos_estatus` (`estatus`),
  ADD KEY `idx_asientos_id_mesa` (`id_mesa`);

--
-- Indices de la tabla `capturistas`
--
ALTER TABLE `capturistas`
  ADD PRIMARY KEY (`id_capturista`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Indices de la tabla `detalle_tickets`
--
ALTER TABLE `detalle_tickets`
  ADD PRIMARY KEY (`id_ticket`),
  ADD UNIQUE KEY `codigo_qr` (`codigo_qr`),
  ADD KEY `id_venta` (`id_venta`),
  ADD KEY `id_producto` (`id_producto`),
  ADD KEY `id_asiento` (`id_asiento`),
  ADD KEY `idx_codigo_qr` (`codigo_qr`),
  ADD KEY `idx_checkin_time` (`checkin_time`);

--
-- Indices de la tabla `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `expositores`
--
ALTER TABLE `expositores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD UNIQUE KEY `unique_usuario` (`usuario`),
  ADD KEY `fk_expositores_empresa` (`id_empresa`);

--
-- Indices de la tabla `facturas`
--
ALTER TABLE `facturas`
  ADD PRIMARY KEY (`id_factura`),
  ADD KEY `codigo_qr_venta` (`codigo_qr_venta`),
  ADD KEY `idx_purchase_group` (`purchase_group`),
  ADD KEY `idx_facturas_purchase_group` (`purchase_group`);

--
-- Indices de la tabla `factura_tickets`
--
ALTER TABLE `factura_tickets`
  ADD PRIMARY KEY (`id_factura`,`id_ticket`),
  ADD KEY `id_ticket` (`id_ticket`),
  ADD KEY `idx_factura_tickets_id_ticket` (`id_ticket`),
  ADD KEY `idx_factura_tickets_id_factura` (`id_factura`);

--
-- Indices de la tabla `folios_consecutivos`
--
ALTER TABLE `folios_consecutivos`
  ADD PRIMARY KEY (`id_folio`),
  ADD UNIQUE KEY `folio` (`folio`),
  ADD KEY `idx_purchase_group` (`purchase_group`),
  ADD KEY `idx_folio` (`folio`),
  ADD KEY `idx_ticket` (`id_ticket`);

--
-- Indices de la tabla `folio_counter`
--
ALTER TABLE `folio_counter`
  ADD PRIMARY KEY (`prefix`);

--
-- Indices de la tabla `mesas`
--
ALTER TABLE `mesas`
  ADD PRIMARY KEY (`id_mesa`),
  ADD KEY `idx_numero_mesa` (`numero_mesa`),
  ADD KEY `idx_mesas_numero_mesa` (`numero_mesa`);

--
-- Indices de la tabla `participantes`
--
ALTER TABLE `participantes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expositor_id` (`expositor_id`);

--
-- Indices de la tabla `payment_logs`
--
ALTER TABLE `payment_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `productos_tickets`
--
ALTER TABLE `productos_tickets`
  ADD PRIMARY KEY (`id_producto`);

--
-- Indices de la tabla `reservas_corporativas`
--
ALTER TABLE `reservas_corporativas`
  ADD PRIMARY KEY (`id_reserva`),
  ADD KEY `idx_organizacion` (`nombre_organizacion`),
  ADD KEY `idx_estatus` (`estatus`);

--
-- Indices de la tabla `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id_staff`),
  ADD UNIQUE KEY `usuario_unique` (`usuario`);

--
-- Indices de la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id_ticket`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_asiento` (`id_asiento`),
  ADD KEY `idx_estatus_pago` (`estatus_pago`),
  ADD KEY `idx_checkin_time` (`checkin_time`),
  ADD KEY `idx_id_producto` (`id_producto`),
  ADD KEY `idx_id_comprador` (`id_comprador`),
  ADD KEY `idx_purchase_group` (`purchase_group`),
  ADD KEY `idx_codigo_qr_tickets` (`codigo_qr`),
  ADD KEY `idx_stripe_session` (`stripe_session_id`),
  ADD KEY `idx_clip_payment_id` (`clip_payment_id`),
  ADD KEY `idx_payment_session_id` (`payment_session_id`),
  ADD KEY `idx_reserva_corp` (`id_reserva_corporativa`),
  ADD KEY `idx_clip_ref_prod` (`clip_payment_reference`),
  ADD KEY `idx_clip_auth` (`clip_auth_code`),
  ADD KEY `idx_tickets_estatus_pago` (`estatus_pago`),
  ADD KEY `idx_tickets_purchase_group` (`purchase_group`),
  ADD KEY `idx_tickets_codigo_qr` (`codigo_qr`),
  ADD KEY `idx_tickets_created_at` (`created_at`),
  ADD KEY `idx_tickets_id_usuario` (`id_usuario`),
  ADD KEY `idx_tickets_id_asiento` (`id_asiento`),
  ADD KEY `clip_transaction_id` (`clip_transaction_id`);

--
-- Indices de la tabla `tipos_registro`
--
ALTER TABLE `tipos_registro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `transplano`
--
ALTER TABLE `transplano`
  ADD PRIMARY KEY (`id_transplano`),
  ADD UNIQUE KEY `unique_user` (`tipo_usuario`,`id_usuario`),
  ADD KEY `idx_tipo_id` (`tipo_usuario`,`id_usuario`),
  ADD KEY `idx_usuario` (`usuario`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD KEY `idx_nombre_completo` (`nombre_completo`),
  ADD KEY `idx_asociacion` (`asociacion`),
  ADD KEY `idx_empresa` (`empresa`),
  ADD KEY `idx_email_multi` (`email`),
  ADD KEY `idx_usuarios_nombre_completo` (`nombre_completo`),
  ADD KEY `idx_usuarios_email` (`email`),
  ADD KEY `idx_usuarios_asociacion` (`asociacion`),
  ADD KEY `idx_usuarios_empresa` (`empresa`),
  ADD KEY `fk_tipo_registro` (`tipo_registro`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id_venta`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `webhooks_clip`
--
ALTER TABLE `webhooks_clip`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `reference` (`reference`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `administradores`
--
ALTER TABLE `administradores`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asientos`
--
ALTER TABLE `asientos`
  MODIFY `id_asiento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `capturistas`
--
ALTER TABLE `capturistas`
  MODIFY `id_capturista` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_tickets`
--
ALTER TABLE `detalle_tickets`
  MODIFY `id_ticket` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `expositores`
--
ALTER TABLE `expositores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `facturas`
--
ALTER TABLE `facturas`
  MODIFY `id_factura` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `folios_consecutivos`
--
ALTER TABLE `folios_consecutivos`
  MODIFY `id_folio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mesas`
--
ALTER TABLE `mesas`
  MODIFY `id_mesa` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `participantes`
--
ALTER TABLE `participantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `payment_logs`
--
ALTER TABLE `payment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos_tickets`
--
ALTER TABLE `productos_tickets`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reservas_corporativas`
--
ALTER TABLE `reservas_corporativas`
  MODIFY `id_reserva` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `staff`
--
ALTER TABLE `staff`
  MODIFY `id_staff` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id_ticket` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipos_registro`
--
ALTER TABLE `tipos_registro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `transplano`
--
ALTER TABLE `transplano`
  MODIFY `id_transplano` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_venta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `webhooks_clip`
--
ALTER TABLE `webhooks_clip`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asientos`
--
ALTER TABLE `asientos`
  ADD CONSTRAINT `asientos_ibfk_1` FOREIGN KEY (`id_mesa`) REFERENCES `mesas` (`id_mesa`);

--
-- Filtros para la tabla `detalle_tickets`
--
ALTER TABLE `detalle_tickets`
  ADD CONSTRAINT `detalle_tickets_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`),
  ADD CONSTRAINT `detalle_tickets_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos_tickets` (`id_producto`),
  ADD CONSTRAINT `detalle_tickets_ibfk_3` FOREIGN KEY (`id_asiento`) REFERENCES `asientos` (`id_asiento`);

--
-- Filtros para la tabla `expositores`
--
ALTER TABLE `expositores`
  ADD CONSTRAINT `fk_expositores_empresa` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `participantes`
--
ALTER TABLE `participantes`
  ADD CONSTRAINT `participantes_ibfk_1` FOREIGN KEY (`expositor_id`) REFERENCES `expositores` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`id_asiento`) REFERENCES `asientos` (`id_asiento`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_tipo_registro` FOREIGN KEY (`tipo_registro`) REFERENCES `tipos_registro` (`id`);

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
