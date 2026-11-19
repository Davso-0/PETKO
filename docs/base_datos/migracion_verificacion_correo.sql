-- Ejecutar una sola vez sobre una instalación existente de kion.
-- Respaldar la base de datos antes de aplicar cambios estructurales.
INSERT INTO roles (nombre)
SELECT 'Usuario'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'Usuario');

ALTER TABLE usuarios
    ADD COLUMN recovery_sent_at datetime DEFAULT NULL AFTER recovery_expires_at,
    ADD COLUMN recovery_window_started_at datetime DEFAULT NULL AFTER recovery_sent_at,
    ADD COLUMN recovery_send_count tinyint(3) UNSIGNED NOT NULL DEFAULT 0 AFTER recovery_window_started_at;

CREATE TABLE registro_verificaciones (
    id_usuario int(10) UNSIGNED NOT NULL,
    codigo_hash varchar(255) NOT NULL,
    expira_en datetime NOT NULL,
    intentos tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
    enviado_en datetime NOT NULL,
    ventana_envios_inicio datetime NOT NULL,
    envios_en_ventana tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id_usuario),
    CONSTRAINT fk_registro_verificaciones_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
