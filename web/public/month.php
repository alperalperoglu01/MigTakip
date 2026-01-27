<?php
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/calc.php';

require_login();
$pdo = db();
$user = $_SESSION['user'];
$is_admin = ($user['role'] ?? '') === 'admin';

$ym = trim($_GET['ym'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) redirect('/dashboard.php');

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

$monthId = (int)$month['id'];
$isClosed = (int)($month['is_closed'] ?? 0) === 1;
$readonly = ($isClosed && !$is_admin);


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
  $st->execute([$monthId, $day, $status, $packages, $overtime_hours, $note]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  // Ay kapalıysa kullanıcı düzenleyemez (admin hariç)
  $isClosed = (int)($month['is_closed'] ?? 0) === 1;
  if ($isClosed && !$is_admin) {
    flash_set('err', 'Bu ay kapatılmış. Düzenleme için yöneticiden açmasını isteyin.');
    redirect('/month.php?ym=' . urlencode($ym));
  }

  if (isset($_POST['toggle_close'])) {
    // Ay kapat/aç: kullanıcı kendi ayını kapatabilir; admin/şef açabilir.
    $want = ($_POST['toggle_close'] === '1') ? 1 : 0;
    $role = $user['role'] ?? 'user';
    $can = ($user['id'] == $month['user_id']) || in_array($role, ['admin','chef'], true);
    if (!$can) { http_response_code(403); echo 'Yetkisiz.'; exit; }

    // Ay kapatılırken kilitli ayarları güncelleme (sadece ilk oluşturulduğu haliyle kalsın)
    if ($want === 1) {
      $fee = floatval($user['accounting_fee'] ?? 0);
      $mt = ($user['motor_default_type'] ?? 'own') === 'rental' ? 'rental' : 'own';
      $mr = floatval($user['motor_monthly_rent'] ?? 0);
      $u = $pdo->prepare("UPDATE months
        SET is_closed=1,
            locked_accounting_fee = COALESCE(locked_accounting_fee, ?),
            locked_motor_default_type = COALESCE(locked_motor_default_type, ?),
            locked_motor_monthly_rent = COALESCE(locked_motor_monthly_rent, ?)
        WHERE id=? AND user_id=?");
      $u->execute([$fee, $mt, $mr, $monthId, $user['id']]);
    } else {
      $u = $pdo->prepare("UPDATE months SET is_closed=0 WHERE id=? AND user_id=?");
      $u->execute([$monthId, $user['id']]);
    }
    flash_set('ok', $want ? 'Ay kapatıldı.' : 'Ay tekrar açıldı.');
    redirect('/month.php?ym=' . urlencode($ym));
  }

  if (isset($_POST['save_settings'])) {
    if (!$is_admin) { http_response_code(403); echo 'Yetkisiz.'; exit; }
    $daily_hours = floatval($_POST['daily_hours'] ?? $month['daily_hours']);
    $hourly_rate = floatval($_POST['hourly_rate'] ?? $month['hourly_rate']);
    if ($daily_hours <= 0) $daily_hours = $month['daily_hours'];
    if ($hourly_rate <= 0) $hourly_rate = $month['hourly_rate'];

    $u = $pdo->prepare("UPDATE months SET daily_hours=?, hourly_rate=? WHERE id=? AND user_id=?");
    $u->execute([$daily_hours, $hourly_rate, $monthId, $user['id']]);
    flash_set('ok', 'Ay ayarları kaydedildi.');
    redirect('/month.php?ym=' . urlencode($ym));
  }

  if (isset($_POST['save_goal'])) {
    $target_packages = intval($_POST['target_packages'] ?? 0);
    if ($target_packages < 0) $target_packages = 0;
    $u = $pdo->prepare("UPDATE months SET target_packages=? WHERE id=? AND user_id=?");
    $u->execute([$target_packages ?: null, $monthId, $user['id']]);
    flash_set('ok', 'Hedef kaydedildi.');
    redirect('/month.php?ym=' . urlencode($ym));
  }


  if (isset($_POST['save_expenses'])) {
    $fuel_cost = floatval(str_replace(',', '.', $_POST['fuel_cost'] ?? $month['fuel_cost'] ?? 0));
    $penalty_cost = floatval(str_replace(',', '.', $_POST['penalty_cost'] ?? $month['penalty_cost'] ?? 0));
    $other_cost = floatval(str_replace(',', '.', $_POST['other_cost'] ?? $month['other_cost'] ?? 0));
    if ($fuel_cost < 0) $fuel_cost = 0;
    if ($penalty_cost < 0) $penalty_cost = 0;
    if ($other_cost < 0) $other_cost = 0;
    $u = $pdo->prepare("UPDATE months SET fuel_cost=?, penalty_cost=?, other_cost=?, advance_amount=?, motor_full_month=?, motor_rental_days=? WHERE id=? AND user_id=?");
    $advance_amount = floatval(str_replace(',', '.', $_POST['advance_amount'] ?? $month['advance_amount'] ?? 0));
    if ($advance_amount < 0) $advance_amount = 0;
    $motor_full_month = isset($_POST['motor_full_month']) ? 1 : 0;
    $motor_rental_days = intval($_POST['motor_rental_days'] ?? 0);
    if ($motor_rental_days < 0) $motor_rental_days = 0;

    // Eğer kullanıcı motoru kiralık değilse bu alanları sıfırla
    $defMotor = ($user['motor_default_type'] ?? 'own');
    if ($defMotor !== 'rental') {
      $motor_full_month = 1;
      $motor_rental_days = 0;
    } else {
      // Kiralıksa: tam ay seçiliyse gün sayısı gerekmez
      if ($motor_full_month === 1) $motor_rental_days = 0;
    }
    $u->execute([$fuel_cost, $penalty_cost, $other_cost, $advance_amount, $motor_full_month, $motor_rental_days, $monthId, $user['id']]);
    flash_set('ok', 'Giderler kaydedildi.');
    redirect('/month.php?ym=' . urlencode($ym));
  }

if (isset($_POST['save_days'])) {

    $statuses = $_POST['status'] ?? [];
    $packages = $_POST['packages'] ?? [];
    $overtimes = $_POST['overtime_hours'] ?? [];
    $notes = $_POST['note'] ?? [];
    for ($d=1; $d<=31; $d++) {
      $stt = $statuses[$d] ?? 'OFF';
      if (!in_array($stt, ['WORK','LEAVE','SICK','ANNUAL','OFF'], true)) $stt = 'OFF';

      $pkg = intval($packages[$d] ?? 0);
      if ($pkg < 0) $pkg = 0;

      // Paket girildiyse durum otomatik ÇALIŞTI olsun (İZİN seçili değilse)
      if ($pkg > 0 && !in_array($stt, ['LEAVE','SICK','ANNUAL'], true)) {
        $stt = 'WORK';
      }

      if ($stt !== 'WORK') $pkg = 0;

      $note = trim($notes[$d] ?? '');
      if (strlen($note) > 255) $note = substr($note, 0, 255);
      $ot = floatval($overtimes[$d] ?? 0);
      if ($ot < 0) $ot = 0;
      upsert_day($pdo, $monthId, $d, $stt, $pkg, $ot, $note);
    }
    flash_set('ok', 'Günlük kayıtlar kaydedildi.');
    redirect('/month.php?ym=' . urlencode($ym));
  }
}

$st = $pdo->prepare("SELECT day, status, packages, overtime_hours, note FROM day_entries WHERE month_id=?");
$st->execute([$monthId]);
$rows = $st->fetchAll();

$byDay = [];
foreach ($rows as $r) $byDay[(int)$r['day']] = $r;

$company = company_settings();
$overtime_hourly_rate = floatval($company['overtime_hourly_rate'] ?? 0);

// Kullanıcı ayarlarını DB'den güncel çek (session güncel değilse bile giderler doğru hesaplanır)
$usr = $pdo->prepare("SELECT seniority_start_date, accounting_fee, motor_default_type, motor_monthly_rent FROM users WHERE id=? LIMIT 1");
$usr->execute([$user['id']]);
$usrRow = $usr->fetch() ?: [];
$seniority_start_date = $usrRow['seniority_start_date'] ?? ($user['seniority_start_date'] ?? null);
$user_accounting_fee = floatval($usrRow['accounting_fee'] ?? ($user['accounting_fee'] ?? 0));
$user_motor_type = ($usrRow['motor_default_type'] ?? ($user['motor_default_type'] ?? 'own'));
$user_motor_monthly = floatval($usrRow['motor_monthly_rent'] ?? ($user['motor_monthly_rent'] ?? 0));

$daily_hours = floatval($month['daily_hours']);
$hourly_rate = floatval($month['hourly_rate']);

$target_packages = isset($month['target_packages']) ? intval($month['target_packages']) : 0;


$totalPackages = 0;
$totalPrime = 0.0;
$totalFixed = 0.0;
$totalOvertime = 0.0;
$totalDaily = 0.0;
$workDays = 0;
$leaveDays = 0;
$sickDays = 0;
$annualDays = 0;

$days = [];
for ($d=1; $d<=31; $d++) {
  $status = $byDay[$d]['status'] ?? 'OFF';
  $pkg = intval($byDay[$d]['packages'] ?? 0);
  $ot_h = floatval($byDay[$d]['overtime_hours'] ?? 0);
  $note = $byDay[$d]['note'] ?? '';
  if ($status === 'LEAVE') $leaveDays++;
  if ($status === 'SICK') $sickDays++;
  if ($status === 'ANNUAL') $annualDays++;
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

  $days[] = ['day'=>$d,'status'=>$status,'packages'=>$pkg,'overtime_hours'=>$ot_h,'overtime_pay'=>$ot_pay,'prime'=>$prime,'fixed'=>$fixed,'daily'=>$daily,'note'=>$note,];
}

$bonus = bonus_for_month_packages($totalPackages);

$roleExtra = role_extra_pay($user['role'] ?? 'user');
$seniorityBonus = seniority_bonus_for_month($seniority_start_date, $ym);

// Kalan gün otomatik: ay_günü - 4 (haftada 1 gün izin). 31->27, 30->26.
$stdWorkdays = standard_workdays_in_month($ym);
$remaining_days = max($stdWorkdays - $workDays, 0);

$grand = $totalDaily + $bonus + $roleExtra + $seniorityBonus;

// Giderler ve kesintiler
$aidFund = floatval($month['locked_help_fund'] ?? 250);
$franchiseFee = floatval($month['locked_franchise_fee'] ?? 1000);
$fuelCost = floatval($month['fuel_cost'] ?? 0);
$penaltyCost = floatval($month['penalty_cost'] ?? 0);
$otherCost = floatval($month['other_cost'] ?? 0);
$tevRate = floatval($month['locked_tevkifat_rate'] ?? 5.0);
$withholding = $grand * ($tevRate/100.0); // Tevkifat
$advanceAmount = floatval($month['advance_amount'] ?? 0);
$accountingFee = floatval($month['locked_accounting_fee'] ?? $user_accounting_fee);
$motorRentExpense = 0.0;
$motorType = ($month['locked_motor_default_type'] ?? $user_motor_type);
$motorMonthly = floatval($month['locked_motor_monthly_rent'] ?? $user_motor_monthly);
if ($motorType === 'rental' && $motorMonthly > 0) {
  $daysInMonth = (int)date('t', strtotime($ym.'-01'));
  $full = (int)($month['motor_full_month'] ?? 1) === 1;
  if ($full) {
    $motorRentExpense = $motorMonthly;
  } else {
    $used = max((int)($month['motor_rental_days'] ?? 0), 0);
    // İstenen mantık: (Aylık kira / ayın gün sayısı) * kullanıldığı gün sayısı
    $motorRentExpense = ($motorMonthly / max($daysInMonth, 1)) * $used;
  }
}
$netPay = $grand - $aidFund - $franchiseFee - $fuelCost - $penaltyCost - $otherCost - $withholding - $advanceAmount - $accountingFee - $motorRentExpense;
if ($netPay < 0) $netPay = 0;

$kalanPaket = 0;
if ($target_packages > 0) { $kalanPaket = max($target_packages - $totalPackages, 0); }
$gunlukGerekli = 0;
if ($kalanPaket > 0 && $remaining_days > 0) { $gunlukGerekli = $kalanPaket / $remaining_days; }


render_header(month_label($ym) . ' • Kurye Kazanç', ['user'=>$user, 'is_admin'=>$is_admin]);
?>
<div class="flex flex-col gap-4">
  <div class="flex items-start justify-between gap-3 flex-wrap">
    <div>
      <div class="text-sm text-slate-500">Ay</div>
      <div class="text-2xl font-extrabold"><?= e(month_label($ym)) ?></div>
      <div class="text-xs text-slate-500"><?= e($ym) ?></div>
    </div>
    <a href="<?= e(base_url()) ?>/dashboard.php" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 font-semibold hover:bg-slate-50">← Panel</a>
  </div>

  <div class="grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-3xl bg-white border border-slate-200 shadow-sm p-5">
      <div class="flex items-center justify-between gap-2 flex-wrap">
        <h2 class="text-lg font-extrabold">Günlük Giriş</h2>
        <div class="text-xs text-slate-500">Durum: Çalıştı / İzin / Boş</div>
      </div>

      <form method="post" class="mt-4 pb-24 lg:pb-0">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<!-- Mobil: açılır/kapanır kartlar -->
        <div class="space-y-2">
          <?php foreach ($days as $row): ?>
            <?php
              if ($row['status'] === 'WORK') {
                $badge = 'bg-emerald-50 text-emerald-700 border-emerald-200';
              } elseif (in_array($row['status'], ['LEAVE','SICK','ANNUAL'], true)) {
                $badge = 'bg-amber-50 text-amber-700 border-amber-200';
              } else {
                $badge = 'bg-slate-50 text-slate-600 border-slate-200';
              }
              $statusText = $row['status']==='WORK'?'Çalıştı':($row['status']==='LEAVE'?'İzin':($row['status']==='SICK'?'Raporlu':($row['status']==='ANNUAL'?'Yıllık İzin':'Boş')));
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
                  <div class="mt-1 text-xs text-emerald-700 font-extrabold">
                    <?= number_format((float)$row['daily'], 0, ',', '.') ?> TL
                  </div>
                </div>
              </summary>
              <div class="px-4 pb-4 pt-1 grid gap-3 md:grid-cols-2">
                <div>
                  <label class="text-xs font-semibold text-slate-600">Durum</label>
                  <select name="status[<?= e($row['day']) ?>]" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2" <?= $readonly?'disabled':'' ?>>
                    <option value="WORK" <?= $row['status']==='WORK'?'selected':'' ?>>Çalıştı</option>
                    <option value="LEAVE" <?= $row['status']==='LEAVE'?'selected':'' ?>>İzin</option>
                    <option value="SICK" <?= $row['status']==='SICK'?'selected':'' ?>>Raporlu</option>
                    <option value="ANNUAL" <?= $row['status']==='ANNUAL'?'selected':'' ?>>Yıllık İzin</option>
                    <option value="OFF" <?= $row['status']==='OFF'?'selected':'' ?>>Boş</option>
                  </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="text-xs font-semibold text-slate-600">Paket</label>
                    <input name="packages[<?= e($row['day']) ?>]" inputmode="numeric" pattern="\d*" value="<?= e($row['packages']) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-base" <?= $readonly?'readonly':'' ?>>
                  </div>
                  <div>
                    <label class="text-xs font-semibold text-slate-600">Ek Mesai Saati</label>
                    <input name="overtime_hours[<?= e($row['day']) ?>]" inputmode="decimal" value="<?= e($row['overtime_hours']) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-base" placeholder="0" <?= $readonly?'readonly':'' ?>>
                  </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-sm md:col-span-2">
                  <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                    <div class="text-xs text-slate-500">Prim</div>
                    <div class="font-extrabold"><?= number_format($row['prime'], 0, ',', '.') ?> TL</div>
                  </div>
                  <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                    <div class="text-xs text-slate-500">Sabit</div>
                    <div class="font-extrabold"><?= number_format($row['fixed'], 0, ',', '.') ?> TL</div>
                  </div>

                  <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                    <div class="text-xs text-slate-500">Ek Mesai</div>
                    <div class="font-extrabold"><?= number_format($row['overtime_pay'], 0, ',', '.') ?> TL</div>
                  </div>
                  <div class="rounded-xl bg-slate-900 text-white p-3">
                    <div class="text-xs opacity-80">Günlük</div>
                    <div class="font-extrabold"><?= number_format($row['daily'], 0, ',', '.') ?> TL</div>
                  </div>
                </div>
                <div class="md:col-span-2">
                  <label class="text-xs font-semibold text-slate-600">Not</label>
                  <input name="note[<?= e($row['day']) ?>]" value="<?= e($row['note']) ?>" maxlength="255" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" placeholder="İstersen not yaz...">
                </div>
              </div>
            </details>
          <?php endforeach; ?>
        </div>

        

<div class="mt-4 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
          <button type="submit" name="save_days" value="1" class="w-full sm:w-auto rounded-2xl bg-indigo-600 px-5 py-3 font-bold text-white hover:bg-indigo-500">Kaydet</button>
          <div class="text-xs text-slate-500">Kaydet deyince tüm günler güncellenir.</div>
        </div>

        <!-- Mobilde sabit kayıt butonu -->
        <div class="lg:hidden fixed bottom-0 left-0 right-0 z-40 border-t border-slate-200 bg-white/95 backdrop-blur px-4 py-3">
          <button type="submit" name="save_days" value="1" class="w-full rounded-2xl bg-indigo-600 px-5 py-3 font-bold text-white hover:bg-indigo-500">Kaydet</button>
          <div class="mt-1 text-center text-xs text-slate-500">Formun neresinde olursan ol kaydedebilirsin.</div>
        </div>
        <div class="lg:hidden h-20"></div>
      </form>
    </div>

    <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-5">
      <h2 class="text-lg font-extrabold">Özet</h2>
      <div class="mt-3 grid gap-3">
        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
          <div class="text-xs text-slate-500">Toplam Paket</div>
          <div class="text-2xl font-extrabold"><?= e($totalPackages) ?></div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4">
            <div class="text-xs text-emerald-700">Çalışılan Gün</div>
            <div class="text-xl font-extrabold text-emerald-900"><?= e($workDays) ?></div>
          </div>
          <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4">
            <div class="text-xs text-amber-700">İzin Gün</div>
            <div class="text-xl font-extrabold text-amber-900"><?= e($leaveDays) ?></div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 p-4">
          <div class="flex items-center justify-between text-sm">
            <span class="text-slate-600">Toplam Sabit</span>
            <b><?= number_format($totalFixed, 0, ',', '.') ?> TL</b>
          </div>
          <div class="mt-2 flex items-center justify-between text-sm">
            <span class="text-slate-600">Toplam Prim</span>
            <b><?= number_format($totalPrime, 0, ',', '.') ?> TL</b>
          </div>
          <div class="mt-2 flex items-center justify-between text-sm">
            <span class="text-slate-600">Toplam Mesai</span>
            <b><?= number_format($totalOvertime, 0, ',', '.') ?> TL</b>
          </div>
          <div class="mt-2 flex items-center justify-between text-sm">
            <span class="text-slate-600">Günlük Toplam</span>
            <b><?= number_format($totalDaily, 0, ',', '.') ?> TL</b>
          </div>
          <div class="mt-2 flex items-center justify-between text-sm">
            <span class="text-slate-600">Aylık Bonus</span>
            <b><?= number_format($bonus, 0, ',', '.') ?> TL</b>
          </div>
          <?php if ($roleExtra > 0): ?>
          <div class="mt-2 flex items-center justify-between text-sm">
            <span class="text-slate-600">Şef Ek Ödeme</span>
            <b><?= number_format($roleExtra, 0, ',', '.') ?> TL</b>
          </div>
          <?php endif; ?>
          <?php if ($seniorityBonus > 0): ?>
          <div class="mt-2 flex items-center justify-between text-sm">
            <span class="text-slate-600">Kıdem Bonusu (yıl dönümü)</span>
            <b><?= number_format($seniorityBonus, 0, ',', '.') ?> TL</b>
          </div>
          <?php endif; ?>
          <div class="mt-3 flex items-center justify-between text-sm">
            <span class="text-slate-700 font-extrabold">Genel Toplam</span>
            <b class="text-slate-900 text-lg"><?= number_format($grand, 0, ',', '.') ?> TL</b>
          </div>

          <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-3">
            <div class="text-sm font-extrabold">Giderler & Kesintiler</div>

            <div class="mt-2 space-y-2 text-sm">
              <div class="flex items-center justify-between">
                <span class="text-slate-600">Yardım Fonu</span>
                <b><?= number_format($aidFund, 0, ',', '.') ?> TL</b>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-600">Franchise Bedeli</span>
                <b><?= number_format($franchiseFee, 0, ',', '.') ?> TL</b>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-600"><?= number_format($tevRate, 0, ',', '.') ?>% Tevkifat</span>
                <b><?= number_format($withholding, 0, ',', '.') ?> TL</b>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-600">Muhasebe Ücreti</span>
                <b><?= number_format($accountingFee, 0, ',', '.') ?> TL</b>
              </div>
              <?php if ($motorType === 'rental' && $motorMonthly > 0): ?>
              <div class="flex items-center justify-between">
                <span class="text-slate-600">Kiralık Motor Kira Gideri</span>
                <b><?= number_format($motorRentExpense, 0, ',', '.') ?> TL</b>
              </div>
              <?php endif; ?>
              <div class="flex items-center justify-between">
                <span class="text-slate-600">Avans</span>
                <b><?= number_format($advanceAmount, 0, ',', '.') ?> TL</b>
              </div>
            </div>

            <form class="mt-3 grid gap-2 md:grid-cols-3" method="post">
              <?= csrf_field() ?>
              <div>
                <label class="text-xs text-slate-600">Yakıt Bedeli</label>
                <input name="fuel_cost" value="<?= e((string)$fuelCost) ?>" inputmode="decimal"
                  class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 bg-white" placeholder="0" <?= $readonly?'readonly':'' ?>>
              </div>
              <div>
                <label class="text-xs text-slate-600">Ceza (manuel)</label>
                <input name="penalty_cost" value="<?= e((string)$penaltyCost) ?>" inputmode="decimal"
                  class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 bg-white" placeholder="0" <?= $readonly?'readonly':'' ?>>
              </div>
              <div>
                <label class="text-xs text-slate-600">Diğer</label>
                <input name="other_cost" value="<?= e((string)$otherCost) ?>" inputmode="decimal"
                  class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 bg-white" placeholder="0" <?= $readonly?'readonly':'' ?>>
              </div>
              <div>
                <label class="text-xs text-slate-600">Avans (manuel)</label>
                <input name="advance_amount" value="<?= e((string)$advanceAmount) ?>" inputmode="decimal"
                  class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 bg-white" placeholder="0" <?= $readonly?'readonly':'' ?>>
              </div>

              <?php if (($motorType ?? 'own') === 'rental' && $motorMonthly > 0): ?>
              <div class="md:col-span-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="font-extrabold">Motor Kira Hesabı</div>
                <p class="text-xs text-slate-600 mt-1">Kira aylık hesaplanır. Tam ay kullanılmadıysa kaç gün kullandığını yaz (ay gününe bölünür).</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-3 items-end">
                  <label class="sm:col-span-1 inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="motor_full_month" value="1" <?= ((int)($month['motor_full_month'] ?? 1) === 1) ? 'checked' : '' ?> <?= $readonly?'disabled':'' ?>>
                    <span>Tam ay kiralık kullanıldı mı?</span>
                  </label>
                  <div class="sm:col-span-2">
                    <label class="text-xs text-slate-600">Tam ay kullanılmadıysa kiralık gün sayısı</label>
                    <input name="motor_rental_days" value="<?= e((string)($month['motor_rental_days'] ?? 0)) ?>" inputmode="numeric"
                      class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 bg-white" placeholder="0" <?= $readonly?'readonly':'' ?>>
                    <div class="text-[11px] text-slate-500 mt-1">Hesap: (Aylık kira / ay gün sayısı) × gün</div>
                  </div>
                </div>
              </div>
              <?php endif; ?>

              <div class="md:col-span-3 flex items-center justify-between gap-3 pt-1">
                <button class="rounded-xl px-4 py-2 font-extrabold bg-slate-900 text-white hover:bg-slate-800"
                        type="submit" name="save_expenses" value="1">Giderleri Kaydet</button>
                <div class="text-right">
                  <div class="text-xs text-slate-500">Kalan Toplam</div>
                  <div class="text-2xl font-black text-emerald-700"><?= number_format($netPay, 0, ',', '.') ?> TL</div>
                </div>
              </div>
            </form>
          </div>


        <div class="rounded-2xl bg-white border border-slate-200 p-4">
  <div class="text-sm font-extrabold">Hedef</div>
  <p class="mt-1 text-xs text-slate-500">Ay hedefini yaz. Kalan gün otomatik: ay_günü - 4 (haftada 1 gün izin) ve girilen çalışılan/izin günlerinden düşer.</p>

  <form method="post" class="mt-3 grid grid-cols-1 gap-3">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="save_goal" value="1">

    <div class="grid grid-cols-2 gap-3">
      <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
        <div class="text-xs text-slate-500">Atılacak Paket</div>
        <div class="text-lg font-extrabold"><?= e($target_packages ?: 0) ?></div>
      </div>
      <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
        <div class="text-xs text-slate-500">Kalan Paket</div>
        <div class="text-lg font-extrabold"><?= e($kalanPaket) ?></div>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
        <div class="text-xs text-slate-500">Çalışılan Gün Sayısı</div>
        <div class="text-lg font-extrabold"><?= e($workDays) ?></div>
      </div>
      <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
        <div class="text-xs text-slate-500">Kalan Gün Sayısı</div>
        <div class="text-lg font-extrabold"><?= e($remaining_days) ?></div>
      </div>
    </div>

    <div class="rounded-xl bg-indigo-50 border border-indigo-200 p-3">
      <div class="text-xs text-indigo-700">Günlük Atılması Gereken Paket</div>
      <div class="text-2xl font-extrabold text-indigo-900">
        <?= $gunlukGerekli ? number_format($gunlukGerekli, 1, ',', '.') : '0,0' ?>
      </div>
    </div>

    <div>
      <label class="block text-xs font-semibold text-slate-600">Atılacak Paket (manuel giriş)</label>
      <input name="target_packages" inputmode="numeric" pattern="\d*" value="<?= e($target_packages ?: '') ?>"
             class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" placeholder="Örn: 1000">
    </div>

    <button class="w-full rounded-2xl bg-indigo-600 px-4 py-2.5 font-bold text-white hover:bg-indigo-500">Hedefi Kaydet</button>
  </form>
</div>

<?php
  $role = $user['role'] ?? 'user';
  $is_manager = in_array($role, ['admin','chef'], true);
?>
<div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
  <div class="text-sm font-bold">Ay Durumu</div>
  <div class="mt-2 flex items-center justify-between gap-2">
    <div class="text-sm text-slate-700">
      <?php if ($isClosed): ?>
        <span class="font-extrabold text-slate-900">Kapalı</span> — Düzenleme kilitli.
      <?php else: ?>
        <span class="font-extrabold text-emerald-700">Açık</span> — Düzenleyebilirsin.
      <?php endif; ?>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <?php if (!$isClosed): ?>
        <button  type="submit" name="toggle_close" value="1" class="rounded-2xl bg-slate-900 px-4 py-2 text-white font-bold" onclick="return confirm('Ayı kapattıktan sonra bir daha düzenleme yapamazsınız.\n\nOnaylıyor musunuz?');">Ayı Kapat</button>
      <?php elseif ($is_manager): ?>
        <button name="toggle_close" value="0" class="rounded-2xl bg-indigo-600 px-4 py-2 text-white font-bold">Aç</button>
      <?php endif; ?>
    </form>
  </div>
  <div class="mt-2 text-xs text-slate-500">
    Ay kapatılırsa geçmiş aylar yeni ayarlardan etkilenmez.
  </div>
</div>

<?php if ($is_admin): ?>
  <div class="mt-3 rounded-2xl bg-white border border-slate-200 p-4">
    <div class="text-sm font-bold">Ay Ayarları (Sadece Admin)</div>
    <form method="post" class="mt-3 space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="save_settings" value="1">
      <label class="block text-xs font-semibold text-slate-600">Günlük Saat</label>
      <input name="daily_hours" value="<?= e($daily_hours) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2">
      <label class="block text-xs font-semibold text-slate-600">Saatlik Ücret (TL)</label>
      <input name="hourly_rate" value="<?= e($hourly_rate) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2">
      <button class="w-full rounded-2xl bg-slate-900 px-4 py-2.5 font-bold text-white hover:bg-slate-800">Ayarları Kaydet</button>
      <div class="text-xs text-slate-500">Bu ay için değişiklik yapar, diğer ayları etkilemez.</div>
    </form>
  </div>
<?php endif; ?>

<?php render_footer(); ?>