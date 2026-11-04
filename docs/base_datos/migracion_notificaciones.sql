-- Ejecutar una sola vez sobre una instalación existente de kion.
-- Respaldar la base de datos antes de aplicar cambios estructurales.
ALTER TABLE usuarios
    MODIFY password_hash varchar(255) DEFAULT NULL,
    ADD COLUMN google_id varchar(255) DEFAULT NULL AFTER password_hash,
    ADD COLUMN reset_token char(64) DEFAULT NULL AFTER recovery_expires_at,
    ADD COLUMN token_expira datetime DEFAULT NULL AFTER reset_token,
    MODIFY estado varchar(20) NOT NULL DEFAULT 'activo';

UPDATE usuarios
SET estado = CASE
    WHEN estado = 'ACTIVO' THEN 'activo'
    WHEN estado = 'INACTIVO' THEN 'inactivo'
    ELSE LOWER(estado)
END;

ALTER TABLE usuarios
    MODIFY estado enum('pendiente','activo','inactivo') NOT NULL DEFAULT 'activo';
ALTER TABLE usuarios
    ADD UNIQUE KEY uq_usuarios_google_id (google_id),
    ADD KEY idx_usuarios_reset_token (reset_token);
