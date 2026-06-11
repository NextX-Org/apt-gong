<?php

namespace NextX\AptGong;

require_once __DIR__ . '/../config/config.php';

class Database
{
  private static ?\PDO $instance = null;

  public static function getInstance(): \PDO
  {
    if (self::$instance === null) {
      self::$instance = self::connect();
    }
    return self::$instance;
  }

  private static function connect(): \PDO
  {
    $dsn = sprintf(
      'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
      DB_HOST,
      DB_PORT,
      DB_NAME
    );
    try {
      return new \PDO($dsn, DB_USER, DB_PASS, [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES   => false,
      ]);
    } catch (\PDOException $e) {
      throw new \RuntimeException('DB 연결 실패: ' . $e->getMessage());
    }
  }

  public static function test(): array
  {
    try {
      $stmt = self::getInstance()->query('SELECT VERSION() AS v, NOW() AS t');
      $row  = $stmt->fetch();
      return ['success' => true, 'version' => $row['v'], 'now' => $row['t']];
    } catch (\Exception $e) {
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  private function __construct() {}
  private function __clone() {}
}
