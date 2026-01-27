<?php
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/calc.php';

require_roles(['admin','chef']);
$pdo = db();
$me = $_SESSION['user'];
$is_admin = ($me['role'] ?? '') === 'admin';

$uid = intval($_GET['uid'] ?? 0);
$ym = $_GET['ym'] ?? date('Y-m');
if ($uid <= 0) redirect('/reports.php');
if (!preg_match('/^(\d{4})-(\d{2})$/', $ym)) $ym = date('Y-m');

$u = $pdo->prepare("SELECT id,name,email,role,seniority_start_date FROM users WHERE id=? LIMIT 1");
$u->execute([$uid]);
$usr = $u->fetch();
if (!$usr) {
  flash_set('err', 'Kullanıcı bulunamadı.');
  redirect('/reports.php?ym=' . urlencode($ym));
}

$company = company_settings();
$overtime_hourly_rate = floatval($company['overtime_hourly_rate'] ?? 0);

$m = $pdo->prepare("SELECT id,daily_hours,hourly_rate,target_packages FROM months WHERE user_id=? AND ym=? LIMIT 1");
$m->execute([$uid, $ym]);
$month = $m->fetch();
$daily_hours = $month ? floatval($month['daily_hours']) : floatval($company['default_daily_hours']);
$hourly_rate = $month ? floatval($month['hourly_rate']) : floatval($company['hourly_rate']);
$target_packages = $month ? intval($month['target_packages'] ?? 0) : 0;
$monthId = $month ? intval($month['id']) : 0;

$byDay = [];
if ($monthId > 0) {
  $st = $pdo->prepare("SELECT day,status,packages,overtime_hours,note FROM day_entries WHERE month_id=?");
  $st->execute([$monthId]);
  foreach ($st->fetchAll() as $r) $byDay[(int)$r['day']] = $r;
}

$totalPackages = 0;
$totalPrime = 0.0;
$totalFixed = 0.0;
$totalOvertime = 0.0;
$totalDaily = 0.0;
$workDays = 0;
$leaveDays = 0;
$days = [];
for ($d=1; $d<=31; $d++) {
  $status = $byDay[$d]['status'] ?? 'OFF';
  $pkg = intval($byDay[$d]['packages'] ?? 0);
  $ot_h = floatval($byDay[$d]['overtime_hours'] ?? 0);
  $note = $byDay[$d]['note'] ?? '';
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

  $days[] = ['day'=>$d,'status'=>$status,'packages'=>$pkg,'overtime_hours'=>$ot_h,'prime'=>$prime,'fixed'=>$fixed,'daily'=>$daily,'note'=>$note];
}

$bonus = bonus_for_month_packages($totalPackages);
$roleExtra = role_extra_pay($usr['role'] ?? 'user');
$seniorityBonus = seniority_bonus_for_month($usr['seniority_start_date'] ?? null, $ym);
$grand = $totalDaily + $bonus + $roleExtra + $seniorityBonus;

render_header('Rapor • ' . e($usr['name']) . ' • ' . month_label($ym), ['user'=>$me, 'is_admin'=>$is_admin]);
?>
<div class="flex flex-col gap-4">
  <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-5">
    <div class="flex items-start justify-between gap-3 flex-wrap">
      <div>
        <div class="text-sm text-slate-500">Kullanıcı</div>
        <h1 class="text-2xl font-extrabold"><?= e($usr['name']) ?></h1>
        <div class="mt-1 text-xs text-slate-500"><?= e($usr['role']) ?> • <?= e(month_label($ym)) ?></div>
      </div>
      <a class="rounded-2xl border border-slate-200 bg-white px-4 py-2 font-semibold hover:bg-slate-50" href="<?= e(base_url()) ?>/reports.php?ym=<?= e($ym) ?>">← Raporlar</a>
    </div>
  </div>

  <div class="grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-3xl bg-white border border-slate-200 shadow-sm p-5">
      <h2 class="text-lg font-extrabold">Günlük Detay</h2>
      <div class="mt-4 space-y-2">
        <?php foreach ($days as $row): ?>
          <?php
            $badge = $row['status']==='WORK' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                     ($row['status']==='LEAVE' ? 'bg-amber-50 text-amber-700 border-amber-200' :
                     'bg-slate-50 text-slate-600 border-slate-200');
            $statusText = $row['status']==='WORK'?'Çalıştı':($row['status']==='LEAVE'?'İzin':'Boş');
          ?>
          <details class="rounded-2xl border border-slate-200 bg-white">
            <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-3">
              <div>
                <div class="font-semibold"><?= e(day_label($ym, (int)$row['day'])) ?></div>
                <div class="mt-1 inline-flex items-center rounded-full border px-2 py-0.5 text-xs <?= $badge ?>"><?= e($statusText) ?></div>
              </div>
              <div class="text-right">
                <div class="text-xs text-slate-500">Paket</div>
                <div class="text-lg font-extrabold"><?= e((string)$row['packages']) ?></div>
                <div class="mt-1 text-xs text-emerald-700 font-extrabold"><?= number_format($row['daily'], 0, ',', '.') ?> TL</div>
              </div>
            </summary>
            <div class="px-4 pb-4 pt-1 grid gap-3 md:grid-cols-3 text-sm">
              <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                <div class="text-xs text-slate-500">Mesai (saat)</div>
                <div class="font-extrabold"><?= number_format($row['overtime_hours'], 1, ',', '.') ?></div>
              </div>
              <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                <div class="text-xs text-slate-500">Mesai Ücreti</div>
                <div class="font-extrabold"><?= number_format($row['overtime_pay'], 0, ',', '.') ?> TL</div>
              </div>
              <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                <div class="text-xs text-slate-500">Prim</div>
                <div class="font-extrabold"><?= number_format($row['prime'], 0, ',', '.') ?> TL</div>
              </div>
              <div class="md:col-span-3 rounded-xl bg-slate-50 border border-slate-200 p-3">
                <div class="text-xs text-slate-500">Not</div>
                <div class="font-semibold"><?= e($row['note'] ?: '-') ?></div>
              </div>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-5">
      <h2 class="text-lg font-extrabold">Özet</h2>
      <div class="mt-3 grid gap-3">
        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
          <div class="text-xs text-slate-500">Toplam Paket</div>
          <div class="text-2xl font-extrabold"><?= e((string)$totalPackages) ?></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4">
            <div class="text-xs text-emerald-700">Çalışılan Gün</div>
            <div class="text-xl font-extrabold text-emerald-900"><?= e((string)$workDays) ?></div>
          </div>
          <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4">
            <div class="text-xs text-amber-700">İzin Gün</div>
            <div class="text-xl font-extrabold text-amber-900"><?= e((string)$leaveDays) ?></div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 p-4">
          <div class="flex items-center justify-between text-sm"><span class="text-slate-600">Toplam Sabit</span><b><?= number_format($totalFixed, 0, ',', '.') ?> TL</b></div>
          <div class="mt-2 flex items-center justify-between text-sm"><span class="text-slate-600">Toplam Prim</span><b><?= number_format($totalPrime, 0, ',', '.') ?> TL</b></div>
          <div class="mt-2 flex items-center justify-between text-sm"><span class="text-slate-600">Toplam Mesai</span><b><?= number_format($totalOvertime, 0, ',', '.') ?> TL</b></div>
          <div class="mt-2 flex items-center justify-between text-sm"><span class="text-slate-600">Günlük Toplam</span><b><?= number_format($totalDaily, 0, ',', '.') ?> TL</b></div>
          <div class="mt-2 flex items-center justify-between text-sm"><span class="text-slate-600">Aylık Bonus</span><b><?= number_format($bonus, 0, ',', '.') ?> TL</b></div>
          <?php if ($roleExtra > 0): ?>
            <div class="mt-2 flex items-center justify-between text-sm"><span class="text-slate-600">Şef Ek Ödeme</span><b><?= number_format($roleExtra, 0, ',', '.') ?> TL</b></div>
          <?php endif; ?>
          <?php if ($seniorityBonus > 0): ?>
            <div class="mt-2 flex items-center justify-between text-sm"><span class="text-slate-600">Kıdem Bonusu (yıl dönümü)</span><b><?= number_format($seniorityBonus, 0, ',', '.') ?> TL</b></div>
          <?php endif; ?>
          <div class="mt-3 flex items-center justify-between rounded-xl bg-slate-900 text-white px-3 py-2">
            <span class="font-bold">Genel Toplam</span>
            <span class="font-extrabold"><?= number_format($grand, 0, ',', '.') ?> TL</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php render_footer(); ?>
