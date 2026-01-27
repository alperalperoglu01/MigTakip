<?php
require_once __DIR__ . '/db.php';

function company_settings(): array {
  static $cache = null;
  if ($cache) return $cache;
  $pdo = db();
  $row = $pdo->query("SELECT hourly_rate, default_daily_hours, overtime_hourly_rate FROM company_settings WHERE id=1")->fetch();
  if (!$row) $row = ['hourly_rate'=>177, 'default_daily_hours'=>12, 'overtime_hourly_rate'=>0];
  $cache = $row;
  return $cache;
}

function overtime_pay(float $overtime_hours, float $overtime_hourly_rate): float {
  if ($overtime_hours <= 0) return 0.0;
  return $overtime_hours * $overtime_hourly_rate;
}

function role_extra_pay(string $role): float {
  // Şef rolü ek ödeme
  return $role === 'chef' ? 7600.0 : 0.0;
}

function seniority_years(?string $startDate, ?string $asOfDate): int {
  if (!$startDate || !$asOfDate) return 0;
  try {
    $s = new DateTime($startDate);
    $a = new DateTime($asOfDate);
    if ($a < $s) return 0;
    $diff = $s->diff($a);
    return (int)$diff->y;
  } catch (Throwable $e) {
    return 0;
  }
}

function seniority_bonus_for_month(?string $startDate, string $ym): float {
  // Yıllık kıdem bonusu: 2 yıl -> 2700, 3+ -> 3600.
  // Bonus, çalışanın yıl dönümünün bulunduğu ayda 1 kez eklenir.
  if (!$startDate || !preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) return 0.0;
  try {
    $year = intval($m[1]);
    $month = intval($m[2]);
    $start = new DateTime($startDate);
    // Bu ay içerisindeki yıl dönümü tarihi
    $ann = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, (int)$start->format('d')));
    if (!$ann) return 0.0;

    // Eğer ay, başlangıç ayı değilse veya gün yoksa (örn 31 Şubat), bir önceki geçerli güne çek.
    // createFromFormat geçersiz günlerde false dönebileceği için güvenli şekilde düzeltelim.
  } catch (Throwable $e) {
    return 0.0;
  }

  // Geçerli gün ayarlama
  $day = (int)(new DateTime($startDate))->format('d');
  $daysInMonth = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
  if ($day > $daysInMonth) $day = $daysInMonth;
  $ann = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));

  // Bu ayda yıl dönümü var sayıyoruz; şimdi kıdem yılına bakalım.
  $years = seniority_years($startDate, $ann->format('Y-m-d'));
  if ($years < 2) return 0.0;
  if ($years === 2) return 2700.0;
  return 3600.0;
}

function daily_fixed_pay(int $packages, float $hours, float $hourly): float {
  if ($packages <= 0) return 0.0;
  return $hours * $hourly;
}

function tier_lookup(string $table, int $value): float {
  $pdo = db();
  $st = $pdo->prepare("SELECT amount FROM {$table} WHERE active=1 AND min_value <= ? ORDER BY min_value DESC LIMIT 1");
  $st->execute([$value]);
  $r = $st->fetch();
  return $r ? floatval($r['amount']) : 0.0;
}

function prime_for_packages(int $packages): float {
  if ($packages <= 0) return 0.0;
  return tier_lookup('prime_tiers', $packages);
}

function bonus_for_month_packages(int $totalPackages): float {
  if ($totalPackages <= 0) return 0.0;
  return tier_lookup('bonus_tiers', $totalPackages);
}
