<?php

declare(strict_types=1);

/**
 * Generates a random login token and returns it as hexadecimal text.
 *
 * @param int $bytes Number of random bytes to generate.
 *
 * @return string Generated token.
 */
function bl_linktoken_generate_token(int $bytes): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Hashes a login token using the configured algorithm.
 *
 * @param string $token     Raw login token.
 * @param array  $bl_config BadLogin configuration.
 *
 * @return string Token hash.
 */
function bl_linktoken_hash_token(string $token, array $bl_config): string
{
    return hash(bl_config_get('_linktoken_hash_algo', $bl_config), $token);
}

/**
 * Computes an ISO 8601 expiration timestamp from a TTL value.
 *
 * @param int $ttl_minutes Time to live in minutes.
 *
 * @return string Expiration timestamp in UTC.
 */
function bl_linktoken_expires_at_iso(int $ttl_minutes): string
{
    $expires_at_unix = time() + ($ttl_minutes * 60);
    return gmdate('c', $expires_at_unix);
}

/**
 * Generates all data required for a login token.
 *
 * @param array $bl_config BadLogin configuration.
 *
 * @return array Generated token, token hash, and expiration timestamp.
 */
function bl_linktoken_generate_data(array $bl_config): array
{
    $token = bl_linktoken_generate_token((int) bl_config_get('_linktoken_bytes', $bl_config));
    $token_hash = bl_linktoken_hash_token($token, $bl_config);
    $expires_at = bl_linktoken_expires_at_iso((int) bl_config_get('_linktoken_ttl_minutes', $bl_config));

    return [
        'token' => $token,
        'token_hash' => $token_hash,
        'expires_at' => $expires_at,
    ];
}

/**
 * Determines whether a stored token expiration time has already passed.
 *
 * @param string|null $expires_at Expiration timestamp in string form.
 *
 * @return bool True when the timestamp is missing, invalid, or expired.
 */
function bl_linktoken_is_expired(?string $expires_at): bool
{
    if ($expires_at === null || $expires_at === '') {
        return true;
    }

    $expires_at_unix = strtotime($expires_at);

    if ($expires_at_unix === false) {
        return true;
    }

    return $expires_at_unix < time();
}

/**
 * Builds the public login URL for a token-based login request.
 *
 * @param string $token     Raw login token.
 * @param array  $bl_config BadLogin configuration.
 *
 * @return string Absolute login URL.
 */
function bl_linktoken_build_login_url(string $token, array $bl_config): string
{
    $base_url = rtrim(bl_config_get('base_url', $bl_config), '/');
    
    $path = bl_config_get('_linktoken_login_filepath', $bl_config);
    
    $get_key = bl_config_get('_linktoken_get_key', $bl_config);

    return $base_url . $path . '?' . rawurlencode($get_key) . '=' . rawurlencode($token);
}