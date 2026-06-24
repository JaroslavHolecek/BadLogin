CREATE TABLE bl_login (
    id SERIAL PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NULL,
    is_email_verified BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    linktoken_hash TEXT NULL,
    linktoken_expires_at TIMESTAMPTZ NULL,
    deleted_at TIMESTAMPTZ NULL

);

-- FUNCTION soft_delete_bl_login(p_id INT)
--  Anonymize row with NULL or FALSE value where possible; new email value will be deleted_email_[id] where id is id of anonymized row; fill in deleted_at timestamp 
-- ::params::
--  p_id INT id of row to be anonymized
-- ::returns::
-- VOID

CREATE OR REPLACE FUNCTION soft_delete_bl_login(p_id INT)
RETURNS VOID AS $$
BEGIN
    UPDATE bl_login
    SET
        email = 'deleted_email_' || p_id,
        password_hash = NULL,
        is_email_verified = FALSE,
        linktoken_hash = NULL,
        linktoken_expires_at = NULL,
        deleted_at = CURRENT_TIMESTAMP
    WHERE id = p_id;
END;
$$ LANGUAGE plpgsql;