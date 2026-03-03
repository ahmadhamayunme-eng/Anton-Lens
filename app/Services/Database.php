<?php
namespace App\Services;

use PDO;

class Database {
    private static ?PDO $pdo = null;

    public static function pdo(array $config): PDO {
        if (self::$pdo) {
            return self::$pdo;
        }
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['name'], $config['charset']);
        self::$pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return self::$pdo;
    }
}
