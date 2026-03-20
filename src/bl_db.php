<?php

declare(strict_types=1);

/**
 * Finds a single user row by a custom WHERE clause.
 *
 * @param string $where_clause SQL WHERE clause using named parameters.
 * @param array  $params       Parameters bound to the query.
 * @param PDO    $pdo          Database connection.
 * @param array  $bl_config    BadLogin configuration.
 *
 * @return array|null Matching user row, or null when nothing is found.
 */
function bl_db_find_user_by(string $where_clause, array $params, PDO $pdo, array $bl_config): ?array
{
    $table = bl_config_get('_table_login', $bl_config);

    $stmt = $pdo->prepare("
        SELECT id, email, password_hash, is_email_verified, created_at,
               linktoken_hash, linktoken_expires_at
        FROM {$table}
        WHERE {$where_clause}
    ");

    $stmt->execute($params);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Finds a user by database ID.
 *
 * @param int   $user_id   User ID.
 * @param PDO   $pdo       Database connection.
 * @param array $bl_config BadLogin configuration.
 *
 * @return array|null Matching user row, or null when not found.
 */
function bl_db_find_user_by_id(int $user_id, PDO $pdo, array $bl_config): ?array
{
    return bl_db_find_user_by(
        'id = :id', ['id' => $user_id],
        $pdo, $bl_config);
}

    /**
     * Finds a user by email address.
     *
     * @param string $email     Email address.
     * @param PDO    $pdo       Database connection.
     * @param array  $bl_config BadLogin configuration.
     *
     * @return array|null Matching user row, or null when not found.
     */
function bl_db_find_user_by_email(string $email, PDO $pdo, array $bl_config): ?array
{
    return bl_db_find_user_by(
        'email = :email', ['email' => $email],
        $pdo, $bl_config);
}

    /**
     * Finds a user by the stored hash of a login token.
     *
     * @param string $token_hash Token hash.
     * @param PDO    $pdo        Database connection.
     * @param array  $bl_config  BadLogin configuration.
     *
     * @return array|null Matching user row, or null when not found.
     */
function bl_db_find_user_by_linktoken_hash(string $token_hash, PDO $pdo, array $bl_config): ?array
{
    return bl_db_find_user_by(
        'linktoken_hash = :token_hash', ['token_hash' => $token_hash],
        $pdo, $bl_config);
}

    /**
     * Creates a new user row.
     *
     * @param string      $email         User email address.
     * @param string|null $password_hash Optional password hash.
     * @param PDO         $pdo           Database connection.
     * @param array       $bl_config     BadLogin configuration.
     *
     * @return int ID of the newly created user.
     */
function bl_db_create_user(string $email, ?string $password_hash, PDO $pdo, array $bl_config): int
{
    $table = bl_config_get('_table_login', $bl_config);

    $stmt = $pdo->prepare("
        INSERT INTO {$table} (email, password_hash)
        VALUES (:email, :password_hash)
        RETURNING id
    ");

    $stmt->execute([
        'email' => $email,
        'password_hash' => $password_hash,
    ]);

    return (int) $stmt->fetchColumn();
}

/**
 * Stores or clears the login token values for a user.
 *
 * @param int         $user_id    User ID.
 * @param string|null $token_hash Token hash to store.
 * @param string|null $expires_at Token expiration in ISO format.
 * @param PDO         $pdo        Database connection.
 * @param array       $bl_config  BadLogin configuration.
 *
 * @return bool True when the update succeeds.
 */
function bl_db_set_linktoken(
    int $user_id,
    ?string $token_hash,
    ?string $expires_at,
    PDO $pdo,
    array $bl_config
): bool
{
    $table = bl_config_get('_table_login', $bl_config);

    $stmt = $pdo->prepare("
        UPDATE {$table}
        SET linktoken_hash = :token_hash,
            linktoken_expires_at = :expires_at
        WHERE id = :id
    ");

    return $stmt->execute([
        'id' => $user_id,
        'token_hash' => $token_hash,
        'expires_at' => $expires_at,
    ]);
}

/**
 * Removes the stored login token for a user.
 *
 * @param int   $user_id   User ID.
 * @param PDO   $pdo       Database connection.
 * @param array $bl_config BadLogin configuration.
 *
 * @return bool True when the update succeeds.
 */
function bl_db_clear_linktoken(int $user_id, PDO $pdo, array $bl_config): bool
{
    return bl_db_set_linktoken($user_id, null, null, $pdo, $bl_config);
}

/**
 * Updates the email verification flag for a user.
 *
 * @param int   $user_id     User ID.
 * @param bool  $is_verified New verification state.
 * @param PDO   $pdo         Database connection.
 * @param array $bl_config   BadLogin configuration.
 *
 * @return bool True when the update succeeds.
 */
function bl_db_set_email_verified(int $user_id, bool $is_verified, PDO $pdo, array $bl_config): bool
{
    $table = bl_config_get('_table_login', $bl_config);

    $stmt = $pdo->prepare("
        UPDATE {$table}
        SET is_email_verified = :is_email_verified
        WHERE id = :id
    ");

    return $stmt->execute([
        'id' => $user_id,
        'is_email_verified' => $is_verified,
    ]);
}

/**
 * Updates the password hash for a user.
 *
 * @param int    $user_id       User ID.
 * @param string $password_hash New password hash.
 * @param PDO    $pdo           Database connection.
 * @param array  $bl_config     BadLogin configuration.
 *
 * @return bool True when the update succeeds.
 */
function bl_db_set_password_hash(int $user_id, string $password_hash, PDO $pdo, array $bl_config): bool
{
    $table = bl_config_get('_table_login', $bl_config);

    $stmt = $pdo->prepare("
        UPDATE {$table}
        SET password_hash = :password_hash
        WHERE id = :id
    ");

    return $stmt->execute([
        'id' => $user_id,
        'password_hash' => $password_hash,
    ]);
}

/**
 * Deletes a user row.
 *
 * @param int   $user_id   User ID.
 * @param PDO   $pdo       Database connection.
 * @param array $bl_config BadLogin configuration.
 *
 * @return bool True when the delete succeeds.
 */
function bl_db_delete_user(int $user_id, PDO $pdo, array $bl_config): bool
{
    $table = bl_config_get('_table_login', $bl_config);

    $stmt = $pdo->prepare("
        DELETE FROM {$table}
        WHERE id = :id
    ");

    return $stmt->execute([
        'id' => $user_id,
    ]);
}