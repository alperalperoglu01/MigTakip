<?php
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/calc.php';

require_login();
$pdo = db();
$user = $_SESSION['user'];
$role = $user['role'] ?? '';
$is_admin = ($role === 'admin');
$is_manager = in_array($role, ['admin','chef'], true);

$year = intval($_GET['year'] ?? date('Y'));
if ($year < 2020 || $year > 2100) $year = intval(date('Y'));

$settings = company_settings();

// Ayları otomatik oluştur (yıl bazında)
function ensure_months_for_year(PDO $pdo, int $userId, int $year, array $settings): void {
  for ($m=1; $m<=12; $m++) {
    $ym = sprintf('%04d-%02d', $year, $m);
    $st = $pdo->prepare("SELECT id FROM months WHERE user_id=? AND ym=?");
    $st->execute([$userId, $ym]);
    if ($st->fetch()) continue;

    // Ay oluştururken o anki ayarları kilitle (geçmişe yansımasın)
    $ins = $pdo->prepare("INSERT INTO months (user_id, ym, daily_hours, hourly_rate, locked_daily_hours, locked_hourly_rate, locked_overtime_hourly_rate, locked_help_fund, locked_franchise_fee, locked_tevkifat_rate) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $ins->execute([
      $userId, $ym,
      $settings['default_daily_hours'], $settings['hourly_rate'],
      $settings['default_daily_hours'], $settings['hourly_rate'],
      $settings['overtime_hourly_rate'] ?? 0,
      250, // yardım fonu sabit
      1000, // franchise sabit
      5.00 // tevkifat
    ]);
  }
}
ensure_months_for_year($pdo, (int)$user['id'], $year, $settings);

// Yıl listesi
$years = $pdo->prepare("SELECT DISTINCT LEFT(ym,4) AS y FROM months WHERE user_id=? ORDER BY y DESC");
$years->execute([$user['id']]);
$years = array_map(fn($r)=>intval($r['y']), $years->fetchAll() ?: []);
if (!$years) $years = [intval(date('Y'))];

$months = $pdo->prepare("SELECT ym, is_closed FROM months WHERE user_id=? AND ym LIKE ? ORDER BY ym ASC");
$months->execute([$user['id'], sprintf('%04d%%', $year)]);
$months = $months->fetchAll();

render_header('Panel • Kurye Kazanç', ['user'=>$user, 'is_admin'=>$is_admin]);
?>
<div class="flex items-center justify-between gap-3">
  <div>
    <h1 class="text-xl font-extrabold">Aylarım</h1>
    <p class="mt-1 text-sm text-slate-600">Aylar otomatik listelenir. İstersen yıla göre filtrele.</p>
  </div>
  <form method="get" class="flex items-center gap-2">
    <select name="year" class="rounded-2xl border border-slate-200 px-4 py-2" onchange="this.form.submit()">
      <?php foreach ($years as $y): ?>
        <option value="<?= e((string)$y) ?>" <?= $y===$year?'selected':'' ?>><?= e((string)$y) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="rounded-2xl bg-slate-900 px-4 py-2 font-bold text-white">Filtrele</button></noscript>
  </form>
</div>

<div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
  <?php foreach ($months as $m): ?>
    <a class="rounded-2xl border border-slate-200 bg-white hover:bg-slate-50 p-4 flex items-center justify-between"
       href="<?= e(base_url()) ?>/month.php?ym=<?= e($m['ym']) ?>">
      <div>
        <div class="font-extrabold flex items-center gap-2">
          <span><?= e(month_label($m['ym'])) ?></span>
          <?php if ((int)($m['is_closed'] ?? 0) === 1): ?>
            <span class="text-[11px] rounded-full bg-slate-900 text-white px-2 py-0.5">Kapalı</span>
          <?php endif; ?>
        </div>
        <div class="text-xs text-slate-500"><?= e($m['ym']) ?></div>
      </div>
      <div class="text-slate-400">›</div>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($is_manager): ?>
  <div class="mt-6 rounded-3xl bg-white border border-slate-200 shadow-sm p-6">
    <h2 class="text-lg font-extrabold">Raporlar</h2>
    <div class="mt-3 flex flex-wrap gap-2">
      <a class="rounded-2xl bg-indigo-600 text-white px-4 py-2 font-bold" href="<?= e(base_url()) ?>/reports.php">Kullanıcı Raporları</a>
      <a class="rounded-2xl bg-slate-900 text-white px-4 py-2 font-bold" href="<?= e(base_url()) ?>/yearly_report.php">Yıllık / Tarih Aralığı Analizi</a>
    </div>
  </div>
<?php else: ?>
  <div class="mt-6 rounded-3xl bg-white border border-slate-200 shadow-sm p-6">
    <h2 class="text-lg font-extrabold">Analiz</h2>
    <p class="mt-1 text-sm text-slate-600">Tarih aralığına göre kazançlarını detaylı inceleyebilirsin.</p>
    <a class="mt-3 inline-flex rounded-2xl bg-slate-900 text-white px-4 py-2 font-bold" href="<?= e(base_url()) ?>/yearly_report.php">Yıllık / Tarih Aralığı</a>
  </div>
<?php endif; ?>

<?php render_footer(); ?>
