<?php
require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/db.php';

require_admin();
$pdo  = db();
$user = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';
  $phone = trim($_POST['phone'] ?? '');
  if ($phone === '') $phone = null;

  $courier_class = trim($_POST['courier_class'] ?? '');
  if ($courier_class === '') $courier_class = null;

  $role = $_POST['role'] ?? 'user';
  if (!in_array($role, ['user','chef','admin'], true)) $role = 'user';

  $seniority_start_date = trim($_POST['seniority_start_date'] ?? '');
  if ($seniority_start_date === '') $seniority_start_date = null;

  if (!$name || !$email || !$pass) {
    flash_set('err', 'Ad, e-posta ve şifre zorunlu.');
    redirect('/admin/user_add.php');
  }

  // email benzersiz
  $st = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
  $st->execute([$email]);
  if ($st->fetch()) {
    flash_set('err', 'Bu e-posta zaten kayıtlı.');
    redirect('/admin/user_add.php');
  }

  $hash = password_hash($pass, PASSWORD_BCRYPT);

  try {
    $st = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, role, courier_class, seniority_start_date) VALUES (?,?,?,?,?,?,?)");
    $st->execute([$name, $email, $phone, $hash, $role, $courier_class, $seniority_start_date]);

    // first_login_done varsa 0 yap (onboarding için)
    try {
      $uid = (int)$pdo->lastInsertId();
      $pdo->prepare("UPDATE users SET first_login_done = 0 WHERE id = ?")->execute([$uid]);
    } catch (Throwable $e) {}

    flash_set('ok', 'Kullanıcı eklendi.');
    redirect('/admin/users.php');
  } catch (Throwable $e) {
    flash_set('err', 'Hata: ' . $e->getMessage());
    redirect('/admin/user_add.php');
  }
}

render_header('Yönetici • Kullanıcı Ekle', ['user'=>$user, 'is_admin'=>true]);
?>
<div class="max-w-6xl mb-4 flex flex-wrap items-center gap-2">
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/users.php">Kullanıcılar</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/tiers.php">Prim/Bonus</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/contacts.php">Mesajlar</a>
</div>

<div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-6 max-w-3xl mx-auto">
  <div class="flex items-center justify-between">
    <h1 class="text-lg font-extrabold">Kullanıcı Ekle</h1>
    <a class="text-sm font-semibold text-slate-600 hover:text-slate-800" href="<?= e(base_url()) ?>/admin/users.php">← Geri</a>
  </div>

  <form method="post" class="mt-4 space-y-3">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <div>
      <label class="text-sm font-semibold">Ad Soyad</label>
      <input name="name" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" placeholder="Örn: Alper" required>
    </div>

    <div>
      <label class="text-sm font-semibold">E-posta</label>
      <input name="email" type="email" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" placeholder="ornek@mail.com" required>
    </div>

    <div>
      <label class="text-sm font-semibold">Telefon</label>
      <input name="phone" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" placeholder="05xx...">
    </div>

    <div>
      <label class="text-sm font-semibold">Kurye Sınıfı</label>
      <select name="courier_class" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
        <option value="">Seçiniz</option>
        <option value="Hemen Kuryesi">Hemen Kuryesi</option>
        <option value="Hızlı Kuryesi">Hızlı Kuryesi</option>
        <option value="Yemek Kuryesi">Yemek Kuryesi</option>
      </select>
    </div>

    <div>
      <label class="text-sm font-semibold">Şifre</label>
      <input name="password" type="password" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" placeholder="••••••••" required>
    </div>

    <div>
      <label class="text-sm font-semibold">Rol</label>
      <select name="role" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
        <option value="user">Kullanıcı</option>
        <option value="chef">Şef</option>
        <option value="admin">Admin</option>
      </select>
    </div>

    <div>
      <label class="text-sm font-semibold">Kıdem Başlangıç Tarihi</label>
      <input name="seniority_start_date" type="date" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
      <div class="mt-1 text-xs text-slate-500">Kıdem yılı otomatik hesaplanır.</div>
    </div>

    <button class="w-full rounded-2xl bg-indigo-600 px-4 py-3 font-bold text-white hover:bg-indigo-500">Kaydet</button>
  </form>
</div>

<?php render_footer(); ?>
