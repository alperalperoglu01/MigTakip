<?php
require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/db.php';

require_admin();
$pdo = db();
$me = $_SESSION['user'];

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) redirect('/admin/users.php');

$st = $pdo->prepare("SELECT id,name,email,phone,role,courier_class,seniority_start_date,created_at FROM users WHERE id=? LIMIT 1");
$st->execute([$id]);
$u = $st->fetch();
if (!$u) {
  flash_set('err', 'Kullanıcı bulunamadı.');
  redirect('/admin/users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  if ($phone === '') $phone = null;
  $courier_class = trim($_POST['courier_class'] ?? '');
  if ($courier_class === '') $courier_class = null;
  $role = $_POST['role'] ?? 'user';
  if (!in_array($role, ['user','chef','admin'], true)) $role = 'user';
  $seniority_start_date = trim($_POST['seniority_start_date'] ?? '');
  if ($seniority_start_date === '') $seniority_start_date = null;

  if (!$name || !$email) {
    flash_set('err', 'Ad ve e-posta zorunlu.');
    redirect('/admin/user_edit.php?id=' . urlencode((string)$id));
  }

  // Şifre opsiyonel
  $pass = trim($_POST['password'] ?? '');
  try {
    if ($pass !== '') {
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      $up = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, role=?, courier_class=?, seniority_start_date=?, password_hash=? WHERE id=?");
      $up->execute([$name, $email, $phone, $role, $courier_class, $seniority_start_date, $hash, $id]);
    } else {
      $up = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, role=?, courier_class=?, seniority_start_date=? WHERE id=?");
      $up->execute([$name, $email, $phone, $role, $courier_class, $seniority_start_date, $id]);
    }
    flash_set('ok', 'Kullanıcı güncellendi.');
  } catch (Throwable $e) {
    flash_set('err', 'Hata: ' . $e->getMessage());
  }

  // Eğer admin kendi rolünü/şifresini değiştirdiyse oturumu tazele
  if ($id === intval($me['id'])) {
    $st = $pdo->prepare("SELECT id,name,email,phone,courier_class,role,seniority_start_date FROM users WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $fresh = $st->fetch();
    if ($fresh) $_SESSION['user'] = $fresh;
  }

  redirect('/admin/user_edit.php?id=' . urlencode((string)$id));
}

render_header('Yönetici • Kullanıcı Düzenle', ['user'=>$me, 'is_admin'=>true]);
?>
<div class="max-w-2xl">
  <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-6">
    <div class="flex items-start justify-between gap-3">
      <div>
        <div class="text-sm text-slate-500">Kullanıcı</div>
        <div class="text-2xl font-extrabold"><?= e($u['name']) ?></div>
        <div class="text-xs text-slate-500"><?= e($u['email']) ?> • ID: <?= e((string)$u['id']) ?></div>
      </div>
      <a href="<?= e(base_url()) ?>/admin/users.php" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 font-semibold hover:bg-slate-50">← Liste</a>
    </div>

    <form method="post" class="mt-6 space-y-4">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div>
        <label class="text-sm font-semibold">Ad Soyad</label>
        <input name="name" value="<?= e($u['name']) ?>" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
      </div>
      <div>
        <label class="text-sm font-semibold">E-posta</label>
        <input name="email" type="email" value="<?= e($u['email']) ?>" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
      </div>
      <div>
        <label class="text-sm font-semibold">Rol</label>
        <select name="role" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
          <option value="user" <?= $u['role']==='user'?'selected':'' ?>>Kullanıcı</option>
          <option value="chef" <?= $u['role']==='chef'?'selected':'' ?>>Şef</option>
          <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
        </select>
      </div>
      <div>
        <label class="text-sm font-semibold">Kıdem Başlangıç Tarihi</label>
        <input name="seniority_start_date" type="date" value="<?= e($u['seniority_start_date'] ?? '') ?>" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
        <div class="mt-1 text-xs text-slate-500">Sistem kıdem yılını otomatik hesaplar ve kıdem bonusunu yıl dönümü ayında uygular.</div>
      </div>
      <div>
        <label class="text-sm font-semibold">Şifre (opsiyonel)</label>
        <input name="password" type="password" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" placeholder="Değiştirmek için yeni şifre yaz">
      </div>

      <button class="w-full rounded-2xl bg-indigo-600 px-4 py-3 font-bold text-white hover:bg-indigo-500">Kaydet</button>

      <div class="text-xs text-slate-500">Not: Şifre boş bırakılırsa değişmez.</div>
    </form>
  </div>
</div>
<?php render_footer(); ?>
