<?php

declare(strict_types=1);

/**
 * Starts the PHP session when it is not already active.
 *
 * @return void
 *
 * @throws Bl_Exception When the session cannot be started.
 */
function bl_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $s = session_start();
    if (!$s) {
        throw new Bl_Exception(Bl_Exception::SYSTEM_ERROR, 'Nepodařilo se spustit session.');
    }
}

/**
 * Logs a user into the current session.
 *
 * @param int   $user_id   User ID to store in the session.
 * @param array $bl_config BadLogin configuration.
 *
 * @return void
 */
function bl_session_login(int $user_id, array $bl_config): void
{
    bl_session_start();

    $session_key = bl_config_get('_session_key_user_id', $bl_config);

    session_regenerate_id(true);
    $_SESSION[$session_key] = $user_id;
}

/**
 * Returns the logged-in user ID from the current session.
 *
 * @param array $bl_config BadLogin configuration.
 *
 * @return int|null Stored user ID, or null when not logged in.
 */
function bl_session_get_user_id(array $bl_config): ?int
{
    bl_session_start();

    $session_key = bl_config_get('_session_key_user_id', $bl_config);

    if (!isset($_SESSION[$session_key])) {
        return null;
    }

    return (int) $_SESSION[$session_key];
}

/**
 * Checks whether a user ID is present in the current session.
 *
 * @param array $bl_config BadLogin configuration.
 *
 * @return bool True when the session contains a logged-in user.
 */
function bl_session_is_logged_in(array $bl_config): bool
{
    return bl_session_get_user_id($bl_config) !== null;
}

/**
 * Clears the current session and destroys the session cookie.
 *
 * @return bool True when the session was destroyed.
 */
function bl_session_logout(): bool
{
    bl_session_start();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 3600,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool) ($params['secure'] ?? false),
            (bool) ($params['httponly'] ?? true)
        );
    }

    return session_destroy();
}