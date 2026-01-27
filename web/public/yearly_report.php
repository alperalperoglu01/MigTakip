<?php
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/calc.php';

require_login();
$pdo = db();
$me = $_SESSION['user'];
$role = $me['role'] ?? 'user';
$is_admin = ($role === 'admin');
$is_manager = in_array($role, ['admin','chef'], true);

// Kullanıcı seçimi (admin/şef)
$userId = (int)($me['id']);
if ($is_manager && isset($_GET['user_id'])) {
  $userId = (int)$_GET['user_id'];
}

$fromYm = preg_match('/^\d{4}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y') . '-01';
$toYm   = preg_match('/^\d{4}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y') . '-12';

if ($fromYm > $toYm) { $tmp=$fromYm; $fromYm=$toYm; $toYm=$tmp; }

$u = $pdo->prepare("SELECT id,name,email,role,accounting_fee,motor_default_type,motor_monthly_rent,seniority_start_date,motor_plate FROM users WHERE id=? LIMIT 1");
$u->execute([$userId]);
$user = $u->fetch();
if (!$user) redirect('/dashboard.php');

// Yetki: admin/şef başkasını görebilir; normal kullanıcı sadece kendini.
if (!$is_manager && (int)$user['id'] !== (int)$me['id']) { http_response_code(403); echo "Yetkisiz."; exit; }

$company = company_settings();
$overtime_hourly_rate = floatval($company['overtime_hourly_rate'] ?? 0);

$monthsSt = $pdo->prepare("SELECT * FROM months WHERE user_id=? AND ym BETWEEN ? AND ? ORDER BY ym ASC");
$monthsSt->execute([$userId, $fromYm, $toYm]);
$months = $monthsSt->fetchAll();

$rowsOut = [];
$sum = ['packages'=>0,'gross'=>0.0,'expenses'=>0.0,'net'=>0.0];

foreach ($months as $m) {
  $monthId = (int)$m['id'];
  $daily_hours = floatval($m['locked_daily_hours'] ?? $m['daily_hours']);
  $hourly_rate = floatval($m['locked_hourly_rate'] ?? $m['hourly_rate']);

  $st = $pdo->prepare("SELECT status, packages, overtime_hours FROM day_entries WHERE month_id=?");
  $st->execute([$monthId]);
  $days = $st->fetchAll();

  $totalPackages = 0;
  $totalDaily = 0.0;

  foreach ($days as $d) {
    if (($d['status'] ?? '') !== 'WORK') continue;
    $pkg = intval($d['packages'] ?? 0);
    $ot = floatval($d['overtime_hours'] ?? 0);
    $prime = prime_for_packages($pkg);
    $fixed = daily_fixed_pay($pkg, $daily_hours, $hourly_rate);
    $otPay = overtime_pay($ot, $overtime_hourly_rate);
    $totalDaily += ($prime + $fixed + $otPay);
    $totalPackages += $pkg;
  }

  $bonus = bonus_for_month_packages($totalPackages);
  $roleExtra = role_extra_pay($user['role'] ?? 'user');
  $seniorityBonus = seniority_bonus_for_month($user['seniority_start_date'] ?? null, $m['ym']);

  $gross = $totalDaily + $bonus + $roleExtra + $seniorityBonus;

  $aidFund = floatval($m['locked_help_fund'] ?? 250);
  $franchiseFee = floatval($m['locked_franchise_fee'] ?? 1000);
  $tevRate = floatval($m['locked_tevkifat_rate'] ?? 5.0);
  $withholding = $gross * ($tevRate/100.0);

  $fuel = floatval($m['fuel_cost'] ?? 0);
  $penalty = floatval($m['penalty_cost'] ?? 0);
  $other = floatval($m['other_cost'] ?? 0);
  $advance = floatval($m['advance_amount'] ?? 0);
  $accounting = floatval($user['accounting_fee'] ?? 0);

  $motorRentExpense = 0.0;
    // Motor kira gideri (aylık kira / çalışma günü * kullanılan gün)
    $motorType = ($m['locked_motor_default_type'] ?? $user['motor_default_type'] ?? 'own');
    $motorMonthly = floatval($m['locked_motor_monthly_rent'] ?? $user['motor_monthly_rent'] ?? 0);
    if ($motorType === 'rental' && $motorMonthly > 0) {
      $daysInMonth = (int)date('t', strtotime($m['ym'].'-01'));
      $workDaysTarget = max($daysInMonth - 4, 1); // haftada 1 gün izin
      $full = (int)($m['motor_full_month'] ?? 1) === 1;
      if ($full) {
        $motorRentExpense = $motorMonthly;
      } else {
        $used = max((int)($m['motor_rental_days'] ?? 0), 0);
        $motorRentExpense = ($motorMonthly / $workDaysTarget) * $used;
      }
    }
  
  $expenses = $aidFund + $franchiseFee + $withholding + $fuel + $penalty + $other + $advance + $accounting + $motorRentExpense;
  $net = max($gross - $expenses, 0);

  $rowsOut[] = [
    'ym'=>$m['ym'],
    'label'=>month_label($m['ym']),
    'packages'=>$totalPackages,
    'gross'=>$gross,
    'expenses'=>$expenses,
    'net'=>$net,
    'closed'=>(int)($m['is_closed'] ?? 0)===1
  ];

  $sum['packages'] += $totalPackages;
  $sum['gross'] += $gross;
  $sum['expenses'] += $expenses;
  $sum['net'] += $net;
}

render_header('Yıllık / Tarih Aralığı Analizi', ['user'=>$me, 'is_admin'=>$is_admin]);
?>
<div class="flex flex-col gap-4">
  <div class="flex items-start justify-between flex-wrap gap-3">
    <div>
      <div class="text-sm text-slate-500">Analiz</div>
      <div class="text-2xl font-extrabold">Yıllık / Tarih Aralığı</div>
      <div class="text-sm text-slate-600"><?= e($user['name']) ?> — <?= e($fromYm) ?> → <?= e($toYm) ?></div>
    </div>
    <a href="<?= e(base_url()) ?>/dashboard.php" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 font-semibold hover:bg-slate-50">← Panel</a>
  </div>

  <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-6">
    <form method="get" class="grid gap-3 sm:grid-cols-4 items-end">
      <?php if ($is_manager): ?>
        <div class="sm:col-span-2">
          <label class="text-xs text-slate-600">Kullanıcı</label>
          <select name="user_id" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
            <?php
              $us = $pdo->query("SELECT id,name,role FROM users ORDER BY name ASC")->fetchAll();
              foreach ($us as $uu):
            ?>
              <option value="<?= e((string)$uu['id']) ?>" <?= ((int)$uu['id']===$userId)?'selected':'' ?>>
                <?= e($uu['name']) ?><?= ($uu['role']==='chef')?' (Şef)':(($uu['role']==='admin')?' (Admin)':'') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div>
        <label class="text-xs text-slate-600">Başlangıç (Ay)</label>
        <input type="month" name="from" value="<?= e($fromYm) ?>" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
      </div>
      <div>
        <label class="text-xs text-slate-600">Bitiş (Ay)</label>
        <input type="month" name="to" value="<?= e($toYm) ?>" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
      </div>
      <div class="sm:col-span-4 flex justify-end">
        <button class="rounded-2xl bg-slate-900 px-5 py-3 font-bold text-white hover:bg-slate-800">Filtrele</button>
      </div>
    </form>
  </div>

  <div class="grid gap-3 sm:grid-cols-4">
    <div class="rounded-2xl bg-white border border-slate-200 p-4">
      <div class="text-xs text-slate-500">Toplam Paket</div>
      <div class="text-2xl font-extrabold"><?= number_format($sum['packages'], 0, ',', '.') ?></div>
    </div>
    <div class="rounded-2xl bg-white border border-slate-200 p-4">
      <div class="text-xs text-slate-500">Brüt Toplam</div>
      <div class="text-2xl font-extrabold"><?= number_format($sum['gross'], 0, ',', '.') ?> TL</div>
    </div>
    <div class="rounded-2xl bg-white border border-slate-200 p-4">
      <div class="text-xs text-slate-500">Gider Toplam</div>
      <div class="text-2xl font-extrabold"><?= number_format($sum['expenses'], 0, ',', '.') ?> TL</div>
    </div>
    <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4">
      <div class="text-xs text-emerald-700">Net Toplam</div>
      <div class="text-2xl font-extrabold text-emerald-800"><?= number_format($sum['net'], 0, ',', '.') ?> TL</div>
    </div>
  </div>

  <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-6 overflow-x-auto">
    <div class="text-lg font-extrabold">Aylık Özet</div>
    <table class="mt-4 w-full text-sm">
      <thead>
        <tr class="text-left text-slate-500">
          <th class="py-2 pr-3">Ay</th>
          <th class="py-2 pr-3">Paket</th>
          <th class="py-2 pr-3">Brüt</th>
          <th class="py-2 pr-3">Gider</th>
          <th class="py-2 pr-3">Net</th>
          <th class="py-2 pr-3">Durum</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rowsOut as $r): ?>
          <tr class="border-t border-slate-100">
            <td class="py-2 pr-3 font-bold">
              <?php
                $href = ($is_manager) ? (base_url().'/reports_user.php?uid='.(int)$user['id'].'&ym='.urlencode($r['ym'])) : (base_url().'/month.php?ym='.urlencode($r['ym']));
              ?>
              <a href="<?= e($href) ?>" class="hover:underline text-slate-900"><?= e($r['label']) ?></a>
            </td>
            <td class="py-2 pr-3"><?= number_format($r['packages'], 0, ',', '.') ?></td>
            <td class="py-2 pr-3"><?= number_format($r['gross'], 0, ',', '.') ?> TL</td>
            <td class="py-2 pr-3"><?= number_format($r['expenses'], 0, ',', '.') ?> TL</td>
            <td class="py-2 pr-3 font-extrabold text-emerald-700"><?= number_format($r['net'], 0, ',', '.') ?> TL</td>
            <td class="py-2 pr-3">
              <?php if ($r['closed']): ?>
                <span class="text-[11px] rounded-full bg-slate-900 text-white px-2 py-0.5">Kapalı</span>
              <?php else: ?>
                <span class="text-[11px] rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5">Açık</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rowsOut): ?>
          <tr><td colspan="6" class="py-4 text-slate-500">Bu aralıkta veri yok.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php render_footer(); ?>
