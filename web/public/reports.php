<?php
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/calc.php';

require_roles(['admin','chef']);
$pdo = db();
$user = $_SESSION['user'];
$is_admin = ($user['role'] ?? '') === 'admin';

$ym = $_GET['ym'] ?? date('Y-m');
if (!preg_match('/^(\d{4})-(\d{2})$/', $ym)) $ym = date('Y-m');

$company = company_settings();
$overtime_hourly_rate = floatval($company['overtime_hourly_rate'] ?? 0);

// son 12 ay
$ym_options = [];
$base = new DateTime(date('Y-m-01'));
for ($i=0; $i<12; $i++) {
  $opt = (clone $base)->modify("-{$i} months")->format('Y-m');
  $ym_options[] = $opt;
}

$users = $pdo->query("SELECT id,name,email,role,seniority_start_date FROM users ORDER BY role DESC, name ASC")->fetchAll();

$rows = [];
foreach ($users as $u) {
  // ay ayarları varsa kullan
  $m = $pdo->prepare("SELECT id,daily_hours,hourly_rate FROM months WHERE user_id=? AND ym=? LIMIT 1");
  $m->execute([$u['id'], $ym]);
  $month = $m->fetch();

  $daily_hours = $month ? floatval($month['daily_hours']) : floatval($company['default_daily_hours']);
  $hourly_rate = $month ? floatval($month['hourly_rate']) : floatval($company['hourly_rate']);
  $monthId = $month ? intval($month['id']) : 0;

  $totalPackages = 0;
  $workDays = 0;
  $leaveDays = 0;
  $totalPrime = 0.0;
  $totalFixed = 0.0;
  $totalOvertime = 0.0;
  $totalDaily = 0.0;

  if ($monthId > 0) {
    $st = $pdo->prepare("SELECT day,status,packages,overtime_hours FROM day_entries WHERE month_id=?");
    $st->execute([$monthId]);
    $des = $st->fetchAll();
    foreach ($des as $d) {
      $status = $d['status'] ?? 'OFF';
      $pkg = intval($d['packages'] ?? 0);
      $ot_h = floatval($d['overtime_hours'] ?? 0);
      if ($status === 'LEAVE') $leaveDays++;
      if ($status === 'WORK') $workDays++;

      $prime = ($status==='WORK' && $pkg>0) ? prime_for_packages($pkg) : 0.0;
      $fixed = ($status==='WORK') ? daily_fixed_pay($pkg, $daily_hours, $hourly_rate) : 0.0;
      if ($status !== 'WORK') $ot_h = 0;
      $ot_pay = ($status==='WORK') ? overtime_pay($ot_h, $overtime_hourly_rate) : 0.0;
      $daily = $prime + $fixed + $ot_pay;

      if ($status==='WORK') $totalPackages += $pkg;
      $totalPrime += $prime;
      $totalFixed += $fixed;
      $totalOvertime += $ot_pay;
      $totalDaily += $daily;
    }
  }

  $bonus = bonus_for_month_packages($totalPackages);
  $roleExtra = role_extra_pay($u['role'] ?? 'user');
  $senBonus = seniority_bonus_for_month($u['seniority_start_date'] ?? null, $ym);
  $grand = $totalDaily + $bonus + $roleExtra + $senBonus;

  $rows[] = [
    'id'=>$u['id'],
    'name'=>$u['name'],
    'role'=>$u['role'],
    'workDays'=>$workDays,
    'leaveDays'=>$leaveDays,
    'packages'=>$totalPackages,
    'total'=>$grand,
    'monthId'=>$monthId
  ];
}

render_header('Raporlar • ' . month_label($ym), ['user'=>$user, 'is_admin'=>$is_admin]);
?>

<div class="max-w-6xl mb-4 flex flex-wrap items-center gap-2">
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/users.php">Kullanıcılar</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/tiers.php">Prim/Bonus</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/contacts.php">Mesajlar</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/reports.php">Raporlar</a>
</div>

<div class="flex flex-col gap-4">
  <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-5">
    <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
      <div>
        <div class="text-sm text-slate-500">Rapor</div>
        <h1 class="text-2xl font-extrabold"><?= e(month_label($ym)) ?></h1>
        <div class="mt-1 text-xs text-slate-500">Admin ve Şef kullanıcılar tüm kullanıcıların paket/ay/kazanç bilgilerini görebilir.</div>
      </div>
      <form method="get" class="flex items-center gap-2">
        <select name="ym" class="rounded-2xl border border-slate-200 bg-white px-4 py-2">
          <?php foreach ($ym_options as $opt): ?>
            <option value="<?= e($opt) ?>" <?= $opt===$ym?'selected':'' ?>><?= e(month_label($opt)) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="rounded-2xl bg-slate-900 px-4 py-2 font-bold text-white hover:bg-slate-800">Göster</button>
      </form>
    </div>
  </div>

  <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-5 overflow-x-auto">
    <table class="min-w-[900px] w-full text-sm">
      <thead class="bg-slate-100">
        <tr>
          <th class="text-left p-3">Kullanıcı</th>
          <th class="text-left p-3">Rol</th>
          <th class="text-left p-3">Çalışılan</th>
          <th class="text-left p-3">İzin</th>
          <th class="text-left p-3">Toplam Paket</th>
          <th class="text-left p-3">Kazanç (Genel)</th>
          <th class="text-left p-3">Detay</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="border-t border-slate-200">
            <td class="p-3 font-semibold"><?= e($r['name']) ?></td>
            <td class="p-3"><?= e($r['role']) ?></td>
            <td class="p-3"><?= e((string)$r['workDays']) ?></td>
            <td class="p-3"><?= e((string)$r['leaveDays']) ?></td>
            <td class="p-3 font-semibold"><?= e((string)$r['packages']) ?></td>
            <td class="p-3 font-extrabold"><?= number_format($r['total'], 0, ',', '.') ?> TL</td>
            <td class="p-3">
              <a class="underline text-indigo-700" href="<?= e(base_url()) ?>/reports_user.php?uid=<?= e((string)$r['id']) ?>&ym=<?= e($ym) ?>">Görüntüle</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php render_footer(); ?>
