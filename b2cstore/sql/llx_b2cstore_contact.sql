-- Copyright (C) 2025 Henaxis
-- Contact messages submitted via B2C Store portal

CREATE TABLE IF NOT EXISTS llx_b2cstore_contact (
    rowid       INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    entity      INTEGER DEFAULT 1 NOT NULL,
    datec       DATETIME NOT NULL,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    phone       VARCHAR(50),
    subject     VARCHAR(255),
    message     TEXT NOT NULL,
    ip          VARCHAR(45),
    status      INTEGER DEFAULT 0 NOT NULL COMMENT '0=new, 1=read, 2=replied',
    fk_soc      INTEGER DEFAULT 0 COMMENT 'Link to thirdparty if registered customer',
    import_key  VARCHAR(14)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
