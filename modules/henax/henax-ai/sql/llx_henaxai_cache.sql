-- henax-ai: cache risposte LLM (tecnica). Da architect llx_henax-architect_ai_cache, nome sanato.
CREATE TABLE llx_henaxai_cache(
    cache_key      VARCHAR(64) NOT NULL PRIMARY KEY,
    response       MEDIUMTEXT,
    provider       VARCHAR(32),
    model          VARCHAR(64),
    tokens_input   INTEGER DEFAULT 0,
    tokens_output  INTEGER DEFAULT 0,
    hit_count      INTEGER DEFAULT 0,
    created_at     DATETIME,
    last_hit_at    DATETIME
) ENGINE=InnoDB;
