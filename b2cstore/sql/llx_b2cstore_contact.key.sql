-- Copyright (C) 2025 Henaxis
-- Indexes for llx_b2cstore_contact

ALTER TABLE llx_b2cstore_contact ADD INDEX idx_b2cstore_contact_entity (entity);
ALTER TABLE llx_b2cstore_contact ADD INDEX idx_b2cstore_contact_email  (email);
ALTER TABLE llx_b2cstore_contact ADD INDEX idx_b2cstore_contact_status (status);
ALTER TABLE llx_b2cstore_contact ADD INDEX idx_b2cstore_contact_datec  (datec);
