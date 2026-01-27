<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function auth_user_by_email(string $email): ?array {
  $pdo = db();
  $st = $pdo->prepare("SELECT id, name, email, phone, courier_class, seniority_start_date, accounting_fee, motor_default_type, motor_monthly_rent, first_login_done, password_hash, role FROM users WHERE email = ? LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch();
  return $u ?: null;
}

function auth_login(string $email, string $password): bool {
  session_boot();
  $u = auth_user_by_email($email);
  if (!$u) return false;
  if (!password_verify($password, $u['password_hash'])) return false;
  // Session'a ayarları da koyuyoruz ki month hesapları DB'ye ekstra sorgu atmasa bile doğru çalışsın.
  $_SESSION['user'] = [
    'id' => $u['id'],
    'name' => $u['name'],
    'email' => $u['email'],
    'phone' => $u['phone'] ?? null,
    'courier_class' => $u['courier_class'] ?? null,
    'role' => $u['role'],
    'seniority_start_date' => $u['seniority_start_date'] ?? null,
    'accounting_fee' => $u['accounting_fee'] ?? 0,
    'motor_default_type' => $u['motor_default_type'] ?? 'own',
    'motor_monthly_rent' => $u['motor_monthly_rent'] ?? 0,
  ];
  return true;
}

function auth_logout(): void {
  session_boot();
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params["path"], $params["domain"],
      $params["secure"], $params["httponly"]
    );
  }
  session_destroy();
}
