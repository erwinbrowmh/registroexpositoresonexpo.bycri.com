-- ==============================================================================
-- SCRIPT DE OPTIMIZACIÓN Y MEJORA: crivirtual_registro_onexpo2026
-- ==============================================================================

-- 1. UNIFICAR MOTORES DE ALMACENAMIENTO (Cambiar MyISAM a InnoDB)
-- InnoDB soporta transacciones, llaves foráneas y es más rápido/seguro para concurrencia.
ALTER TABLE `admexpositor` ENGINE=InnoDB;
ALTER TABLE `city` ENGINE=InnoDB;
ALTER TABLE `country` ENGINE=InnoDB;
ALTER TABLE `expasistentes` ENGINE=InnoDB;
ALTER TABLE `experiencias` ENGINE=InnoDB;
ALTER TABLE `premios_generados` ENGINE=InnoDB;
ALTER TABLE `province` ENGINE=InnoDB;
ALTER TABLE `sesiones_persistentes` ENGINE=InnoDB;

-- 2. UNIFICAR CHARSET A UTF8MB4 (Para soportar todos los caracteres y emojis, eliminando latin1)
ALTER TABLE `city` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `country` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `payment_logs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `productos_tickets` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `province` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `factura_tickets` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 3. CREAR ÍNDICES CRÍTICOS FALTANTES (Mejora radicalmente la velocidad de consultas)
-- La tabla expositores carece de índices en campos por los que se busca todo el tiempo.
ALTER TABLE `expositores`
  ADD INDEX `idx_id_empresa` (`id_empresa`),
  ADD INDEX `idx_correo` (`correo`),
  ADD INDEX `idx_usuario` (`usuario`);

-- 4. AGREGAR LLAVES FORÁNEAS FALTANTES (Garantiza la integridad de los datos)
-- Evita que se asigne un id_empresa que no existe, y si se borra la empresa, el campo queda en NULL.
ALTER TABLE `expositores`
  ADD CONSTRAINT `fk_expositores_empresa` 
  FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- (Opcional) Limpiar tablas huérfanas/duplicadas 
-- CUIDADO: Ejecutar esto solo después de asegurar que no tienen datos útiles.
-- DROP TABLE IF EXISTS `expositor_imagenes_galeria`; -- La buena es expositores_imagenes_galeria
-- DROP TABLE IF EXISTS `expositor_videos_galeria`;   -- La buena es expositores_videos_galeria