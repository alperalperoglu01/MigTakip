<?php
// MigPack -> MigTakip otomatik paket kaydı (session ile)
// POST JSON: {"date":"YYYY-MM-DD","packages":46,"overtime_hours":0}
// veya x-www-form-urlencoded: date=...&packages=...

require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/calc.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$user = $_SESSION['user'] ?? null;
if (!$user) { http_response_code(401); echo json_encode(['ok'=>false,'err'=>'unauthorized']); exit; }

$raw = file_get_contents('php://input');
$data = [];
if ($raw) {
  $j = json_decode($raw, true);
  if (is_array($j)) $data = $j;
}
if (!$data) $data = $_POST;

$date = trim((string)($data['date'] ?? ''));
$packages = intval($data['packages'] ?? 0);
$overtime_hours = floatval($data['overtime_hours'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'err'=>'invalid_date']);
  exit;
}
if ($packages < 0) $packages = 0;
if ($overtime_hours < 0) $overtime_hours = 0;

$ym = substr($date, 0, 7);
$day = intval(substr($date, 8, 2));
if ($day < 1 || $day > 31) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'err'=>'invalid_day']);
  exit;
}

// month var mı? yoksa oluştur
$st = $pdo->prepare("SELECT * FROM months WHERE user_id=? AND ym=? LIMIT 1");
$st->execute([$user['id'], $ym]);
$month = $st->fetch();
if (!$month) {
  $s = company_settings();
  $ins = $pdo->prepare("INSERT INTO months (user_id, ym, daily_hours, hourly_rate) VALUES (?,?,?,?)");
  $ins->execute([$user['id'], $ym, $s['default_daily_hours'], $s['hourly_rate']]);
  $st->execute([$user['id'], $ym]);
  $month = $st->fetch();
}
$monthId = intval($month['id'] ?? 0);
if ($monthId <= 0) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'err'=>'month_create_failed']);
  exit;
}

// upsert
function upsert_day(PDO $pdo, int $monthId, int $day, string $status, int $packages, float $overtime_hours, string $note): void {
  $sql = "INSERT INTO day_entries (month_id, day, status, packages, overtime_hours, note)
          VALUES (?,?,?,?,?,?)
          ON DUPLICATE KEY UPDATE
            status=VALUES(status),
            packages=VALUES(packages),
            overtime_hours=VALUES(overtime_hours),
            note=VALUES(note)";
  $st = $pdo->prepare($sql);
  if ($overtime_hours < 0) $overtime_hours = 0;
  if ($status !== 'WORK') $overtime_hours = 0;
  if ($packages < 0) $packages = 0;
  if ($status !== 'WORK') $packages = 0;
  $st->execute([$monthId, $day, $status, $packages, $overtime_hours, $note]);
}

// Paket girildiyse WORK
$status = ($packages > 0) ? 'WORK' : 'OFF';
$note = 'MigPack Otomatik';

upsert_day($pdo, $monthId, $day, $status, $packages, $overtime_hours, $note);

echo json_encode(['ok'=>true,'date'=>$date,'packages'=>$packages,'overtime_hours'=>$overtime_hours]);
