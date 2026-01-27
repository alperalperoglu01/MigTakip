<?php
require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/db.php';

require_admin();
$pdo = db();
$user = $_SESSION['user'];

function fetch_tiers(PDO $pdo, string $table): array {
  return $pdo->query("SELECT id,min_value,amount,label,active FROM {$table} ORDER BY min_value ASC")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  if (isset($_POST['save_company'])) {
    $hourly = floatval($_POST['hourly_rate'] ?? 177);
    $hours  = floatval($_POST['default_daily_hours'] ?? 12);
    $otrate = floatval($_POST['overtime_hourly_rate'] ?? 0);
    if ($hourly <= 0) $hourly = 177;
    if ($hours <= 0) $hours = 12;
    if ($otrate < 0) $otrate = 0;

    $st = $pdo->prepare("UPDATE company_settings SET hourly_rate=?, default_daily_hours=?, overtime_hourly_rate=? WHERE id=1");
    $st->execute([$hourly, $hours, $otrate]);
    flash_set('ok', 'Şirket ayarları güncellendi.');
    redirect('/admin/tiers.php');
  }

  if (isset($_POST['save_tiers'])) {
    foreach (['prime_tiers','bonus_tiers'] as $table) {
      $mins = $_POST[$table]['min_value'] ?? [];
      $amts = $_POST[$table]['amount'] ?? [];
      $labs = $_POST[$table]['label'] ?? [];
      $acts = $_POST[$table]['active'] ?? [];

      foreach ($mins as $id => $minv) {
        $minv = intval($minv);
        $amt  = floatval($amts[$id] ?? 0);
        $lab  = trim($labs[$id] ?? '');
        $act  = isset($acts[$id]) ? 1 : 0;

        $u = $pdo->prepare("UPDATE {$table} SET min_value=?, amount=?, label=?, active=? WHERE id=?");
        $u->execute([$minv, $amt, $lab, $act, intval($id)]);
      }
    }
    flash_set('ok', 'Kademeler güncellendi.');
    redirect('/admin/tiers.php');
  }

  if (isset($_POST['add_tier'])) {
    $table = ($_POST['table'] ?? '') === 'bonus_tiers' ? 'bonus_tiers' : 'prime_tiers';
    $minv = intval($_POST['min_value'] ?? 0);
    $amt  = floatval($_POST['amount'] ?? 0);
    $lab  = trim($_POST['label'] ?? '');
    $pdo->prepare("INSERT INTO {$table} (min_value, amount, label, active) VALUES (?,?,?,1)")
        ->execute([$minv, $amt, $lab]);
    flash_set('ok', 'Yeni kademe eklendi.');
    redirect('/admin/tiers.php');
  }
}

$company = $pdo->query("SELECT hourly_rate, default_daily_hours, overtime_hourly_rate FROM company_settings WHERE id=1")->fetch();
$prime = fetch_tiers($pdo,'prime_tiers');
$bonus = fetch_tiers($pdo,'bonus_tiers');

render_header('Yönetici • Ayarlar', ['user'=>$user, 'is_admin'=>true]);
?>
<div class="max-w-6xl mb-4 flex flex-wrap items-center gap-2">
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/users.php">Kullanıcılar</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/tiers.php">Prim/Bonus</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/contacts.php">Mesajlar</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/reports.php">Raporlar</a>
</div>

<div class="grid gap-4 lg:grid-cols-2">
  <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-6">
    <h1 class="text-lg font-extrabold">Şirket Ayarları</h1>
    <form method="post" class="mt-4 space-y-3">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="save_company" value="1">
      <div>
        <label class="text-sm font-semibold">Saatlik Ücret (TL)</label>
        <input name="hourly_rate" value="<?= e($company['hourly_rate']) ?>" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
      </div>
      <div>
        <label class="text-sm font-semibold">Varsayılan Günlük Saat</label>
        <input name="default_daily_hours" value="<?= e($company['default_daily_hours']) ?>" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
      </div>
      <div>
        <label class="text-sm font-semibold">Ek Mesai Saat Ücreti (TL)</label>
        <input name="overtime_hourly_rate" value="<?= e($company['overtime_hourly_rate']) ?>" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" placeholder="Örn: 200">
      </div>
      <button class="w-full rounded-2xl bg-indigo-600 px-4 py-3 font-bold text-white hover:bg-indigo-500">Kaydet</button>
    </form>
    <div class="mt-4 text-sm">
      <a class="underline text-indigo-700" href="<?= e(base_url()) ?>/admin/users.php">← Kullanıcılar</a>
    </div>
  </div>

  <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-6">
    <h2 class="text-lg font-extrabold">Yeni Kademe Ekle</h2>
    <form method="post" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="add_tier" value="1">
      <div class="sm:col-span-2">
        <label class="text-sm font-semibold">Tablo</label>
        <select name="table" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
          <option value="prime_tiers">Günlük Prim</option>
          <option value="bonus_tiers">Aylık Bonus</option>
        </select>
      </div>
      <div>
        <label class="text-sm font-semibold">Min Paket</label>
        <input name="min_value" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" placeholder="Örn: 1500">
      </div>
      <div>
        <label class="text-sm font-semibold">Tutar (TL)</label>
        <input name="amount" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" placeholder="Örn: 52000">
      </div>
      <div class="sm:col-span-2">
        <label class="text-sm font-semibold">Etiket</label>
        <input name="label" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" placeholder="Örn: 1500+">
      </div>
      <button class="sm:col-span-2 w-full rounded-2xl bg-slate-900 px-4 py-3 font-bold text-white hover:bg-slate-800">Ekle</button>
    </form>
  </div>
</div>

<div class="mt-6 rounded-3xl bg-white border border-slate-200 shadow-sm p-6">
  <h2 class="text-lg font-extrabold">Kademeler</h2>
  <p class="mt-1 text-sm text-slate-600">Prim/bonus değerlerini buradan güncelle. (Alt bonus kısımlarını tablodan gir.)</p>

  <form method="post" class="mt-4">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="save_tiers" value="1">

    <div class="grid gap-4 lg:grid-cols-2">
      <div class="overflow-x-auto rounded-2xl border border-slate-200">
        <div class="bg-slate-100 px-4 py-3 font-bold">Günlük Prim</div>
        <table class="min-w-[520px] w-full text-sm">
          <thead>
            <tr class="text-left">
              <th class="p-3">Min</th>
              <th class="p-3">Tutar</th>
              <th class="p-3">Etiket</th>
              <th class="p-3">Aktif</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($prime as $t): ?>
            <tr class="border-t border-slate-200">
              <td class="p-3"><input name="prime_tiers[min_value][<?= e($t['id']) ?>]" value="<?= e($t['min_value']) ?>" class="w-24 rounded-xl border border-slate-200 px-2 py-2"></td>
              <td class="p-3"><input name="prime_tiers[amount][<?= e($t['id']) ?>]" value="<?= e($t['amount']) ?>" class="w-28 rounded-xl border border-slate-200 px-2 py-2"></td>
              <td class="p-3"><input name="prime_tiers[label][<?= e($t['id']) ?>]" value="<?= e($t['label']) ?>" class="w-40 rounded-xl border border-slate-200 px-2 py-2"></td>
              <td class="p-3"><input type="checkbox" name="prime_tiers[active][<?= e($t['id']) ?>]" <?= $t['active']?'checked':'' ?>></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="overflow-x-auto rounded-2xl border border-slate-200">
        <div class="bg-slate-100 px-4 py-3 font-bold">Aylık Bonus</div>
        <table class="min-w-[520px] w-full text-sm">
          <thead>
            <tr class="text-left">
              <th class="p-3">Min</th>
              <th class="p-3">Tutar</th>
              <th class="p-3">Etiket</th>
              <th class="p-3">Aktif</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($bonus as $t): ?>
            <tr class="border-t border-slate-200">
              <td class="p-3"><input name="bonus_tiers[min_value][<?= e($t['id']) ?>]" value="<?= e($t['min_value']) ?>" class="w-24 rounded-xl border border-slate-200 px-2 py-2"></td>
              <td class="p-3"><input name="bonus_tiers[amount][<?= e($t['id']) ?>]" value="<?= e($t['amount']) ?>" class="w-28 rounded-xl border border-slate-200 px-2 py-2"></td>
              <td class="p-3"><input name="bonus_tiers[label][<?= e($t['id']) ?>]" value="<?= e($t['label']) ?>" class="w-40 rounded-xl border border-slate-200 px-2 py-2"></td>
              <td class="p-3"><input type="checkbox" name="bonus_tiers[active][<?= e($t['id']) ?>]" <?= $t['active']?'checked':'' ?>></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <button class="mt-4 w-full rounded-2xl bg-indigo-600 px-4 py-3 font-bold text-white hover:bg-indigo-500">Kademeleri Kaydet</button>
  </form>
</div>

<?php render_footer(); ?>
