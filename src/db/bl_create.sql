CREATE TABLE bl_login (
    id SERIAL PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NULL,
    is_email_verified BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    linktoken_hash TEXT NULL,
    linktoken_expires_at TIMESTAMPTZ NULL
);