<?php
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/layout.php';

session_boot();

function refresh_session_user_from_db(): void {
  if (empty($_SESSION['user'])) return;
  $pdo = db();

  $id = $_SESSION['user']['id'] ?? null;
  $email = $_SESSION['user']['email'] ?? null;

  if ($id) {
    $st = $pdo->prepare("SELECT id, name, email, role, phone, courier_class, start_date, first_login_done, accounting_fee, motor_default_type, motor_monthly_rent FROM users WHERE id=? LIMIT 1");
    $st->execute([(int)$id]);
  } elseif ($email) {
    $st = $pdo->prepare("SELECT id, name, email, role, phone, courier_class, start_date, first_login_done, accounting_fee, motor_default_type, motor_monthly_rent FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]);
  } else {
    return;
  }
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    // session user'ı güncelle (mevcut anahtarları koruyup üstüne yaz)
    $_SESSION['user'] = array_merge($_SESSION['user'], $row);
  }
}


if (!empty($_SESSION['user'])) {
  refresh_session_user_from_db();
  if ((int)($_SESSION['user']['first_login_done'] ?? 1) === 0) {
    redirect('/onboarding.php');
  }
  redirect('/dashboard.php');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $email = trim($_POST['email'] ?? '');
  $pass = $_POST['password'] ?? '';
  if (auth_login($email, $pass)) {
    // auth_login session'a temel kullanıcıyı yazar, DB'den tüm alanları tazele
    refresh_session_user_from_db();
    flash_set('ok', 'Giriş başarılı.');
    if (!empty($_SESSION['user']) && (int)($_SESSION['user']['first_login_done'] ?? 1) === 0) {
      redirect('/onboarding.php');
    }
    redirect('/dashboard.php');
  } else {
    flash_set('err', 'E-posta veya şifre hatalı.');
  }
}

render_header('Giriş • Kurye Kazanç');
?>
<div class="max-w-md mx-auto">
  <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-6">
    <h1 class="text-xl font-extrabold">Giriş</h1>
    <p class="mt-1 text-sm text-slate-600">Hesabına giriş yap, paketlerini gir, kazancın otomatik hesaplanır.</p>

    <form method="post" class="mt-6 space-y-4">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div>
        <label class="text-sm font-semibold">E-posta</label>
        <input name="email" type="email" required class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="ornek@mail.com">
      </div>
      <div>
        <label class="text-sm font-semibold">Şifre</label>
        <input name="password" type="password" required class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="••••••••">
      </div>
      <button class="w-full rounded-2xl bg-slate-900 px-4 py-3 font-bold text-white hover:bg-slate-800">Giriş Yap</button>
    </form>

    <div class="mt-4 text-xs text-slate-500">
    </div>
  </div>
</div>
<?php render_footer(); ?>
