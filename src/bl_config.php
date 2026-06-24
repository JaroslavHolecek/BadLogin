<?php

declare(strict_types=1);

/**
 * Returns a configuration value and validates selected internal options.
 *
 * @param string $key    Configuration key. Keys starting with an underscore are considered internal and are validated for type and content.
 * @param array  $config Configuration array.
 *
 * @return mixed Configuration value.
 *
 * @throws Bl_Exception When the key is missing or contains an invalid value.
 */
function bl_config_get(string $key, array $config): mixed
{
    if (!isset($config[$key])) {
        throw new Bl_Exception(Bl_Exception::CONFIG_ERROR, "Chybí konfigurace {$key} v bl_config.");
    }

    $value = $config[$key];

    if ('_' === $key[0]) {
        $err_msg = "Neplatná konfigurace {$key} v bl_config.";

        switch ($key) {
            case '_linktoken_login_filepath':
                if (!is_string($value) || trim($value) === '' || $value[0] !== '/' || strpos($value, '..') !== false) {
                    throw new Bl_Exception(Bl_Exception::CONFIG_ERROR, $err_msg);
                }
                break;
            case '_table_login':
                if (!is_string($value) || trim($value) === '') {
                    throw new Bl_Exception(Bl_Exception::CONFIG_ERROR, $err_msg);
                }
                break;
            case '_session_key_user_id':
                if (!is_string($value) || trim($value) === '') {
                    throw new Bl_Exception(Bl_Exception::CONFIG_ERROR, $err_msg);
                }
                break;
            case '_linktoken_hash_algo':
                if (!is_string($value) || trim($value) === '' || !in_array($value, hash_algos(), true)) {
                    throw new Bl_Exception(Bl_Exception::CONFIG_ERROR, $err_msg);
                }
                break;
            case '_linktoken_bytes':
                if (!is_int($value) || $value <= 0) {
                    throw new Bl_Exception(Bl_Exception::CONFIG_ERROR, $err_msg);
                }
                break;
            case '_linktoken_ttl_minutes':
                if (!is_int($value) || $value <= 0) {
                    throw new Bl_Exception(Bl_Exception::CONFIG_ERROR, $err_msg);
                }
                break;
            case '_linktoken_create_user_if_missing':
                if (!is_bool($value)) {
                    throw new Bl_Exception(Bl_Exception::CONFIG_ERROR, $err_msg);
                }
                break;
            case '_linktoken_get_key':
                if (!is_string($value) || trim($value) === '') {
                    throw new Bl_Exception(Bl_Exception::CONFIG_ERROR, $err_msg);
                }
                break;
        }
    }

    return $config[$key];
}