<?php
/* =========================================================
   db.php — one connection to MySQL through PDO.
   ERRMODE_EXCEPTION: every SQL error becomes an exception,
   EMULATE_PREPARES = false: real prepared statements
   (the query and the data are sent to MySQL separately,
   so SQL injection is impossible).
   ========================================================= */

function db(): PDO
{
    static $pdo = null;                      // created only once per request
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// Short helpers: run a prepared statement and get the result
function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_one(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();                   // array or false
}

function db_value(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();                // number of changed rows
}

// true if the error is "duplicate key" or "foreign key" (SQLSTATE 23000)
function is_constraint_error(PDOException $e): bool
{
    return $e->getCode() === '23000';
}
