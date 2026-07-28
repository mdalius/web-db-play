CREATE TABLE request_clients (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    client_key TEXT NOT NULL UNIQUE,
    first_request_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_request_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    request_count BIGINT NOT NULL DEFAULT 1
);

CREATE TABLE request_log (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    client_id BIGINT NOT NULL REFERENCES request_clients(id),
    requested_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    request_method TEXT NOT NULL,
    request_path TEXT NOT NULL,
    remote_address INET,
    web_server TEXT NOT NULL
);

CREATE INDEX request_log_requested_at_idx ON request_log (requested_at DESC);
