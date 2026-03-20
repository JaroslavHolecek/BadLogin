<?php

declare(strict_types=1);


/**
 * Normalizes an email address. Support function.
 *
 * Converts the email to a standard format by trimming whitespace,
 * converting to lowercase, and removing any special characters.
 *
 * @param string $email The email address to normalize.
 *
 * @return string The normalized email address.
 */
function bl_auth_normalize_email(string $email): string
{
    return mb_strtolower(trim($email));
}

/**
 * Checks whether the given string is a valid email address.
 *
 * @param string $email Email address to validate.
 *
 * @return bool True when the email format is valid.
 */
function bl_auth_is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Normalizes and validates an email address.
 *
 * @param string $email Raw email address.
 *
 * @return string Normalized email address.
 *
 * @throws Bl_Exception When the email is invalid.
 */
function bl_auth_refine_email(string $email): string
{
    $email = bl_auth_normalize_email($email);

    if (!bl_auth_is_valid_email($email)) {
        throw new Bl_Exception(Bl_Exception::USER_ERROR, 'Neplatný e-mail.');
    }

    return $email;
}

/**
 * Creates a secure hash for a plaintext password.
 *
 * @param string $password Plaintext password.
 *
 * @return string Password hash.
 *
 * @throws Bl_Exception When hashing fails.
 */
function bl_auth_hash_password(string $password): string
{
    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($hash === false) {
        throw new Bl_Exception(Bl_Exception::SYSTEM_ERROR, 'Nepodařilo se vytvořit hash hesla.');
    }

    return $hash;
}

/**
 * Registers a new user account.
 *
 * @param string      $email     User email address.
 * @param string|null $password  Optional plaintext password.
 * @param PDO         $pdo       Database connection.
 * @param array       $bl_config BadLogin configuration.
 *
 * @return int Newly created user ID.
 *
 * @throws Bl_Exception When validation fails or the user already exists.
 */
function bl_auth_register(string $email, ?string $password, PDO $pdo, array $bl_config): int
{
    $email = bl_auth_refine_email($email);

    $existing_user = bl_db_find_user_by_email($email, $pdo, $bl_config);
    if ($existing_user !== null) {
        throw new Bl_Exception(Bl_Exception::USER_ERROR, 'Účet s tímto e-mailem již existuje, pokud si nepamatujete heslo, můžete využít přihlášení skrze odkaz zaslaný do emailu (tuto metodu si lze zvolit při přihlášení).');
    }
    
    $password_hash = $password === null ? null : bl_auth_hash_password($password);

    return bl_db_create_user($email, $password_hash, $pdo, $bl_config);
}

/**
 * Logs a user in using email and password credentials.
 *
 * @param string $email     User email address.
 * @param string $password  Plaintext password.
 * @param PDO    $pdo       Database connection.
 * @param array  $bl_config BadLogin configuration.
 *
 * @return array|null User database row after successful login.
 *
 * @throws Bl_Exception When the account does not exist or credentials are invalid.
 */
function bl_auth_login_with_password(string $email, string $password, PDO $pdo, array $bl_config): ?array
{
    $email = bl_auth_refine_email($email);

    $user = bl_db_find_user_by_email($email, $pdo, $bl_config);
    if ($user === null) {
        throw new Bl_Exception(Bl_Exception::USER_ERROR, 'Tento účet neexistuje.');
    }

    if ($user['password_hash'] === null || $user['password_hash'] === '') {
        throw new Bl_Exception(Bl_Exception::USER_ERROR, 'Heslo není nastaveno. Použijte přihlášení pomocí odkazu do emailu.');
    }

    if (!password_verify($password, $user['password_hash'])) {
        throw new Bl_Exception(Bl_Exception::USER_ERROR, 'Neplatné přihlašovací údaje.');
    }

    bl_session_login((int) $user['id'], $bl_config);

    return $user;
}

/**
 * Builds the email body for a link-token login message.
 *
 * @param string $linktoken_login_url Login URL containing the token.
 * @param array  $bl_config           BadLogin configuration.
 *
 * @return string Rendered email body.
 */
function bl_auth_build_body_linktoken_login(string $linktoken_login_url, array $bl_config): string
{
    return str_replace(
        ['{linktoken_login_url}', '{app_name}'],
        [$linktoken_login_url, bl_config_get('app_name', $bl_config)],
        bl_config_get('mail_body_linktoken_login', $bl_config)
    );
}

/**
 * Generates and emails a login token for the given user.
 *
 * @param string $email     User email address.
 * @param PDO    $pdo       Database connection.
 * @param array  $bl_config BadLogin configuration.
 *
 * @return bool True when the email was handed off successfully.
 *
 * @throws Bl_Exception When the account is missing and auto-creation is disabled or token storage fails.
 */
function bl_auth_request_linktoken_login(string $email, PDO $pdo, array $bl_config): bool
{
    $email = bl_auth_refine_email($email);

    $user = bl_db_find_user_by_email($email, $pdo, $bl_config);

    if ($user === null) {
        $create_if_missing = bl_config_get('_linktoken_create_user_if_missing', $bl_config);

        if (!$create_if_missing) {
            throw new Bl_Exception(Bl_Exception::USER_ERROR, 'Tento účet neexistuje.');
        }

        $user_id = bl_db_create_user($email, null, $pdo, $bl_config);
    }else {
        $user_id = (int) $user['id'];
    }

    $ttl_minutes = (int) bl_config_get('_linktoken_ttl_minutes', $bl_config);
    $token_data = bl_linktoken_generate_data($bl_config);

    $saved = bl_db_set_linktoken(
        $user_id,
        $token_data['token_hash'],
        $token_data['expires_at'],
        $pdo,
        $bl_config);

    if (!$saved) {
        throw new Bl_Exception(Bl_Exception::SYSTEM_ERROR, 'Nepodařilo se uložit token pro přihlášení pomocí emailu.');
    }

    $linktoken_login_url = bl_linktoken_build_login_url($token_data['token'], $bl_config);

    return bl_mail_send(
        $email,
        bl_config_get('mail_subject_linktoken_login', $bl_config),
        bl_auth_build_body_linktoken_login($linktoken_login_url, $bl_config), $bl_config);
}

/**
 * Logs a user in using a previously issued login token.
 *
 * @param string $token     Raw login token.
 * @param PDO    $pdo       Database connection.
 * @param array  $bl_config BadLogin configuration.
 *
 * @return array|null Current user database row, or null when the token is invalid.
 */
function bl_auth_login_with_linktoken(string $token, PDO $pdo, array $bl_config): ?array
{
    $token = trim($token);

    if ($token === '') {
        return null;
    }

    $token_hash = bl_linktoken_hash_token($token, $bl_config);
    $user = bl_db_find_user_by_linktoken_hash($token_hash, $pdo, $bl_config);

    if ($user === null) {
        return null;
    }

    if (bl_linktoken_is_expired($user['linktoken_expires_at'])) {
        bl_db_clear_linktoken((int) $user['id'], $pdo, $bl_config);
        return null;
    }

    bl_session_login((int) $user['id'], $bl_config);

    bl_db_clear_linktoken((int) $user['id'], $pdo, $bl_config);

    bl_db_set_email_verified((int) $user['id'], true, $pdo, $bl_config);

    return bl_auth_current_user_db_data($pdo, $bl_config);
}

/**
 * Loads the currently logged-in user's database record.
 *
 * @param PDO   $pdo       Database connection.
 * @param array $bl_config BadLogin configuration.
 *
 * @return array|null Current user row, or null when nobody is logged in.
 */
function bl_auth_current_user_db_data(PDO $pdo, array $bl_config): ?array
{
    $user_id = bl_session_get_user_id($bl_config);

    if ($user_id === null) {
        return null;
    }

    return bl_db_find_user_by_id($user_id, $pdo, $bl_config);
}

/**
 * Checks whether there is an authenticated user in the current session.
 *
 * @param array $bl_config BadLogin configuration.
 *
 * @return bool True when a user is logged in.
 */
function bl_auth_is_logged_in(array $bl_config): bool
{
    return bl_session_is_logged_in($bl_config);
}

/**
 * Logs out the current user.
 *
 * @return bool True when the session was destroyed.
 */
function bl_auth_logout(): bool
{
    return bl_session_logout();
}

/**
 * Deletes the currently logged-in user account and logs the user out.
 *
 * @param PDO   $pdo       Database connection.
 * @param array $bl_config BadLogin configuration.
 *
 * @return bool True when the user was deleted and the session was closed.
 */
function bl_auth_delete_current_user(PDO $pdo, array $bl_config): bool
{
    $user_id = bl_session_get_user_id($bl_config);

    if ($user_id === null) {
        return false;
    }

    $deleted = bl_auth_delete_user_by_id($user_id, $pdo, $bl_config);

    if (!$deleted) {
        return false;
    }

    return bl_session_logout();
}

/**
 * Deletes a user account by ID.
 *
 * @param int   $user_id   User ID to delete.
 * @param PDO   $pdo       Database connection.
 * @param array $bl_config BadLogin configuration.
 *
 * @return bool True when the user was deleted.
 */
function bl_auth_delete_user_by_id(int $user_id, PDO $pdo, array $bl_config): bool
{
    if ($user_id === null) {
        throw new Bl_Exception(Bl_Exception::SYSTEM_ERROR, 'Neplatný uživatelský ID.');
    }

    return bl_db_delete_user($user_id, $pdo, $bl_config);;
}