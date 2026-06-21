-- henax-ai: audit log query AI (tecnica). Da architect llx_henax-architect_ai_log, nome sanato.
CREATE TABLE llx_henaxai_log(
    rowid              INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_user            INTEGER,
    datec              DATETIME,
    question           TEXT,
    provider           VARCHAR(32),
    model              VARCHAR(64),
    tokens_input       INTEGER DEFAULT 0,
    tokens_output      INTEGER DEFAULT 0,
    latency_ms         INTEGER DEFAULT 0,
    response_truncated VARCHAR(500),
    cache_hit          TINYINT DEFAULT 0,
    status             VARCHAR(16) DEFAULT 'ok',
    error_msg          VARCHAR(500)
) ENGINE=InnoDB;
