<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Connexion unique à PostgreSQL, partagée par tous les Models.
 */
class Database
{
    private static ?PDO $connection = null;

    public static function get(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::connect();
        }
        return self::$connection;
    }

    private static function connect(): PDO
    {
        $config = require APP_ROOT . '/config/config.php';
        $db = $config['db'];

        $dsn = "pgsql:host={$db['host']};port={$db['port']};dbname={$db['name']}";

        return new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
