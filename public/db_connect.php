<?php

declare(strict_types=1);

/**
 * Creates a PDO database connection from showroom configuration.
 *
 * @param array $db_config Database connection settings.
 * Expected keys: 'engine', 'host', 'port', 'dbname', 'user', 'password'.
 *
 * @return PDO Connected PDO instance.
 *
 * @throws Bl_Exception When the connection cannot be established.
 */
function getDBConnection(array $db_config): PDO
{
    try {
        $dsn = $db_config['engine'] . ":host=" . $db_config['host'] . ";port=" . $db_config['port'] . ";dbname=" . $db_config['dbname'] . ";";

        if ($db_config['engine'] === 'pgsql') {
            $dsn .= "options='--client_encoding=UTF8'";
        } else {
            $dsn .= "charset=utf8";
        }
        
        $pdo = new PDO(
            $dsn,
            $db_config['user'],
            $db_config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        
        return $pdo;
    } catch (PDOException $e) {
        throw new Bl_Exception(Bl_Exception::SYSTEM_ERROR, 'Nepodařilo se připojit k databázi: ' . $e->getMessage(), $e);
    }
}