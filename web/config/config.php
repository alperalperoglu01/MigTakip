<?php
// Kurye Kazanç - Config
// 1) Bu dosyayı düzenleyip DB bilgilerini girin.
// 2) public/install.php ile kurulum yapın, sonra install.php'yi SİLİN.

return [
  'db' => [
    'host' => 'localhost',
    'name' => 'sesmotors_kurye',
    'user' => 'sesmotors_kurye',
    'pass' => 'nuFeKp5EWXnQ558embmK',
    'charset' => 'utf8mb4',
  ],
  'app' => [
    'base_url' => 'https://www.sesmotors.com/kurye/public', // örn: https://domain.com/kurye/public  (boş bırakılırsa otomatik)
    'session_name' => 'KURYESESS',
  ],
];

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../storage/php_errors.log');
