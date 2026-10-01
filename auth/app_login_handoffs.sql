-- Optional: the page creates this table automatically. Run this only if your DB user cannot CREATE tables.
CREATE TABLE IF NOT EXISTS app_login_handoffs (
    token_hash   CHAR(64)     NOT NULL PRIMARY KEY,
    session_data MEDIUMTEXT   NOT NULL,
    redirect     VARCHAR(500) NOT NULL,
    expires_at   INT UNSIGNED NOT NULL,
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
