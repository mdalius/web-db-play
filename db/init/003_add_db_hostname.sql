ALTER TABLE request_log
    ADD COLUMN IF NOT EXISTS db_hostname TEXT NOT NULL DEFAULT 'unknown';
