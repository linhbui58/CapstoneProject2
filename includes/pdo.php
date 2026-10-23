<?php

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host     = getenv('DB_HOST')     ?: (defined('DB_HOST') ? DB_HOST : '');
    $database = getenv('DB_NAME')     ?: (defined('DB_NAME') ? DB_NAME : '');
    $username = getenv('DB_USER')     ?: (defined('DB_USER') ? DB_USER : '');
    $password = getenv('DB_PASSWORD') ?: (defined('DB_PASS') ? DB_PASS : '');

    if ($host === '' || $database === '' || $username === false) {
        throw new RuntimeException('Database environment variables are not configured.');
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $database);
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}