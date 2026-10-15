CREATE TABLE IF NOT EXISTS {PREFIX}turnstile_checks (
    day DATE NOT NULL,
    form VARCHAR(40) NOT NULL,
    outcome VARCHAR(20) NOT NULL COMMENT 'passed, failed, missing, unreachable',
    total INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (day, form, outcome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
