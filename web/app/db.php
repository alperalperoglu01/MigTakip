<?php
require_once __DIR__ . '/schema.php';
function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $cfg = require __DIR__ . '/../config/config.php';
  $db = $cfg['db'];

  $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
  $opts = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];
  $pdo = new PDO($dsn, $db['user'], $db['pass'], $opts);
  // Otomatik şema kontrolü (eksik kolon/tablo varsa eklemeyi dener)
  ensure_schema($pdo);
  return $pdo;
}
