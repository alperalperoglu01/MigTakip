<?php
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function base_url(): string {
  $cfg = require __DIR__ . '/../config/config.php';
  if (!empty($cfg['app']['base_url'])) return rtrim($cfg['app']['base_url'], '/');

  // Bazı hosting/proxy (Cloudflare vb.) ortamlarında HTTPS bilgisi $_SERVER['HTTPS']'e doğru yansımayabilir.
  // Bu durumda uygulama http<->https arasında gidip gelerek "ERR_TOO_MANY_REDIRECTS" üretebilir.
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  if (!$https) {
    $xfp = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    if ($xfp === 'https') $https = true;
  }
  if (!$https) {
    // Cloudflare örneği: {"scheme":"https"}
    $cfv = $_SERVER['HTTP_CF_VISITOR'] ?? '';
    if (stripos($cfv, 'https') !== false) $https = true;
  }
  $scheme = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
  $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
  return $scheme . '://' . $host . $dir;
}

function redirect(string $path): void {
  header('Location: ' . base_url() . $path);
  exit;
}

function session_boot(): void {
  $cfg = require __DIR__ . '/../config/config.php';
  if (session_status() === PHP_SESSION_NONE) {
    session_name($cfg['app']['session_name'] ?? 'KURYESESS');
    // Cookie ayarları: bazı ortamlarda SameSite/secure uyumsuzluğu session'ın tutmamasına
    // ve login->login redirect döngüsüne sebep olabilir.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (!$https) {
      $xfp = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
      if ($xfp === 'https') $https = true;
    }
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $cookiePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/') . '/';
    if ($cookiePath === '//') $cookiePath = '/';
    session_set_cookie_params([
      'lifetime' => 0,
      'path' => $cookiePath,
      'secure' => $https,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    session_start();
  }
}

function csrf_token(): string {
  session_boot();
  if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['_csrf'];
}

function csrf_field(): string {
  return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
  session_boot();
  $t = $_POST['_csrf'] ?? '';
  if (!$t || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $t)) {
    http_response_code(403);
    echo "CSRF doğrulaması başarısız.";
    exit;
  }
}

function require_login(): void {
  session_boot();
  if (empty($_SESSION['user'])) redirect('/login.php');
}

function require_admin(): void {
  require_login();
  if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo "Bu sayfaya erişim yetkiniz yok.";
    exit;
  }
}

function require_roles(array $roles): void {
  require_login();
  $r = $_SESSION['user']['role'] ?? '';
  if (!in_array($r, $roles, true)) {
    http_response_code(403);
    echo "Bu sayfaya erişim yetkiniz yok.";
    exit;
  }
}

function day_label(string $ym, int $day): string {
  // "09.09.2026 - Perşembe" formatı
  if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) return (string)$day;
  $y = intval($m[1]);
  $mo = intval($m[2]);
  $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $mo, $y);
  if ($day < 1) $day = 1;
  if ($day > $daysInMonth) $day = $daysInMonth;
  $dt = new DateTime(sprintf('%04d-%02d-%02d', $y, $mo, $day));
  $wd = ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
  $w = $wd[(int)$dt->format('w')] ?? '';
  return $dt->format('d.m.Y') . ' - ' . $w;
}

function standard_workdays_in_month(string $ym): int {
  // Haftalık 1 gün izin varsayımı: 31->27, 30->26; genel kural: ay_günü - 4
  if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) return 0;
  $y = intval($m[1]);
  $mo = intval($m[2]);
  $dim = (int)cal_days_in_month(CAL_GREGORIAN, $mo, $y);
  return max($dim - 4, 0);
}

function flash_set(string $type, string $msg): void {
  session_boot();
  $_SESSION['_flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array {
  session_boot();
  if (empty($_SESSION['_flash'])) return null;
  $f = $_SESSION['_flash'];
  unset($_SESSION['_flash']);
  return $f;
}

function month_label(string $ym): string {
  $map = ['01'=>'Ocak','02'=>'Şubat','03'=>'Mart','04'=>'Nisan','05'=>'Mayıs','06'=>'Haziran','07'=>'Temmuz','08'=>'Ağustos','09'=>'Eylül','10'=>'Ekim','11'=>'Kasım','12'=>'Aralık'];
  if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) return $ym;
  return ($map[$m[2]] ?? $m[2]) . ' ' . $m[1];
}
