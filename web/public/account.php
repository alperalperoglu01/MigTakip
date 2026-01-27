<?php
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/db.php';

require_login();
$pdo = db();
$me = $_SESSION['user'];

// kullanıcıyı güncel çek
$st = $pdo->prepare("SELECT id,name,email,phone,role,courier_class,seniority_start_date,accounting_fee,motor_default_type,motor_monthly_rent,motor_plate,first_login_done,created_at FROM users WHERE id=? LIMIT 1");
$st->execute([$me['id']]);
$u = $st->fetch();
if (!$u) redirect('/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

    if (isset($_POST['save_account'])) {
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // basit doğrulama
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      flash_set('err', 'E-posta formatı geçersiz.');
      redirect('/account.php');
    }

    $up = $pdo->prepare("UPDATE users SET phone=?, email=? WHERE id=?");
    $up->execute([$phone, $email, $u['id']]);

    // session güncelle
    $_SESSION['user']['phone'] = $phone;
    $_SESSION['user']['email'] = $email;

    flash_set('ok', 'Hesap bilgileri güncellendi.');
    redirect('/account.php');
  }

  if (isset($_POST['save_work_settings'])) {
    $accounting_fee = floatval(str_replace(',', '.', $_POST['accounting_fee'] ?? ($u['accounting_fee'] ?? 0)));
    if ($accounting_fee < 0) $accounting_fee = 0;

    $motor_default_type = ($_POST['motor_default_type'] ?? ($u['motor_default_type'] ?? 'own')) === 'rental' ? 'rental' : 'own';
    $motor_monthly_rent = floatval(str_replace(',', '.', $_POST['motor_monthly_rent'] ?? ($u['motor_monthly_rent'] ?? 0)));
    if ($motor_monthly_rent < 0) $motor_monthly_rent = 0;

    $motor_plate = trim($_POST['motor_plate'] ?? '');
    if (strlen($motor_plate) > 20) $motor_plate = substr($motor_plate, 0, 20);

    $up = $pdo->prepare("UPDATE users SET accounting_fee=?, motor_default_type=?, motor_monthly_rent=?, motor_plate=? WHERE id=?");
    $up->execute([$accounting_fee, $motor_default_type, $motor_monthly_rent, $motor_plate, $u['id']]);
    // session güncelle
    $_SESSION['user']['accounting_fee'] = $accounting_fee;
    $_SESSION['user']['motor_default_type'] = $motor_default_type;
    $_SESSION['user']['motor_monthly_rent'] = $motor_monthly_rent;
    flash_set('ok', 'Çalışma ayarları güncellendi.');
    redirect('/account.php');
  }

if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new1 = $_POST['new_password'] ?? '';
    $new2 = $_POST['new_password_confirm'] ?? '';

    if (!$current || !$new1 || !$new2) {
      flash_set('err', 'Tüm alanları doldur.');
      redirect('/account.php#password');
    }
    if ($new1 !== $new2) {
      flash_set('err', 'Yeni şifreler eşleşmiyor.');
      redirect('/account.php#password');
    }
    if (strlen($new1) < 6) {
      flash_set('err', 'Şifre en az 6 karakter olmalı.');
      redirect('/account.php#password');
    }

    $st2 = $pdo->prepare("SELECT password_hash FROM users WHERE id=? LIMIT 1");
    $st2->execute([$u['id']]);
    $row = $st2->fetch();
    if (!$row || !password_verify($current, $row['password_hash'])) {
      flash_set('err', 'Mevcut şifre yanlış.');
      redirect('/account.php#password');
    }

    $hash = password_hash($new1, PASSWORD_BCRYPT);
    $up = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
    $up->execute([$hash, $u['id']]);

    flash_set('ok', 'Şifre güncellendi.');
    redirect('/account.php#password');
  }
}

render_header('Hesap Bilgileri', ['user'=>$me, 'is_admin'=>(($me['role']??'')==='admin')]);

$roleText = ($u['role']==='admin') ? 'Admin' : (($u['role']==='chef') ? 'Şef' : 'Kurye');
?>
<div class="max-w-3xl space-y-4 mx-auto">
  <div class="rounded-2xl bg-white border border-slate-200 p-4">
    <div class="text-lg font-extrabold">Hesap Bilgileri</div>
    <form method="post" class="mt-3 grid gap-3 sm:grid-cols-2">
      <?= csrf_field() ?>
      <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
        <div class="text-xs text-slate-500">İsim Soyisim</div>
        <div class="font-bold"><?= e($u['name']) ?></div>
      </div>
	  
	  <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
        <div class="text-xs text-slate-500">Başlangıç Tarihi</div>
        <div class="font-bold"><?= e($u['seniority_start_date'] ?? '-') ?></div>
      </div>

      <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
        <label class="text-xs text-slate-500">E-posta</label>
        <input name="email" value="<?= e($u['email'] ?? '') ?>"
               class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2"
               placeholder="ornek@mail.com" />
      </div>

      <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
        <label class="text-xs text-slate-500">Telefon</label>
        <input name="phone" value="<?= e($u['phone'] ?? '') ?>" placeholder="+90 5xx xxx xx xx"
               class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2" />
      </div>
	  
	  <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
        <div class="text-xs text-slate-500">Rol</div>
        <div class="font-bold"><?= e($roleText) ?></div>
      </div>

      <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
        <div class="text-xs text-slate-500">Kurye Sınıfı</div>
        <div class="font-bold"><?= e($u['courier_class'] ?? '-') ?></div>
      </div>
		
		<div></div>
		
      <div class="flex items-end justify-end">
        <button class="rounded-xl px-4 py-2 font-extrabold bg-indigo-600 text-white hover:bg-indigo-500"
                type="submit" name="save_account" value="1">Kaydet</button>
      </div>
    </form>    
	</div>

<div class="rounded-2xl bg-white border border-slate-200 p-4">
    <div class="text-lg font-extrabold">Çalışma Ayarları</div>
    <p class="mt-1 text-sm text-slate-600">Muhasebe ücreti ve motor bilgisi giderlere otomatik eklenir.</p>
    <form class="mt-3 grid gap-3 sm:grid-cols-2" method="post">
      <?= csrf_field() ?>
<div>
        <label class="text-xs text-slate-600">Muhasebe Ücreti (TL / Ay)</label>
        <input name="accounting_fee" inputmode="decimal" value="<?= e((string)($u['accounting_fee'] ?? 0)) ?>" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2" placeholder="0">
      </div>
      <div>
        <label class="text-xs text-slate-600">Motor (Varsayılan)</label>
        <select name="motor_default_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2">
          <option value="own" <?= (($u['motor_default_type'] ?? 'own')==='own')?'selected':'' ?>>Kendi</option>
          <option value="rental" <?= (($u['motor_default_type'] ?? '')==='rental')?'selected':'' ?>>Kiralık</option>
        </select>
      </div>
	  
	  <div id="rentalFields" class="space-y-4"><div>
        <label class="text-xs text-slate-600">Kiralık Motor Ücreti (TL / Ay)</label>
        <input name="motor_monthly_rent" inputmode="decimal" value="<?= e((string)($u['motor_monthly_rent'] ?? 0)) ?>" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2" placeholder="0">
      </div></div>

      <div>
        <label class="text-xs text-slate-600">Motor Plakası</label>
        <input name="motor_plate" value="<?= e((string)($u['motor_plate'] ?? '')) ?>" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2" placeholder="34ABC123">
      </div>

      <div class="sm:col-span-2 flex items-center justify-end">
        <button class="rounded-xl px-4 py-2 font-extrabold bg-indigo-600 text-white hover:bg-indigo-500"
                type="submit" name="save_work_settings" value="1">Kaydet</button>
      </div>
    </form>
  </div>

  <div id="password" class="rounded-2xl bg-white border border-slate-200 p-4">
    <div class="text-lg font-extrabold">Şifre Değiştir</div>
    <form class="mt-3 grid gap-3 sm:grid-cols-2" method="post">
      <?= csrf_field() ?>
      <div class="sm:col-span-2">
        <label class="text-xs text-slate-600">Mevcut Şifre</label>
        <input name="current_password" type="password" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2" placeholder="••••••••">
      </div>
      <div>
        <label class="text-xs text-slate-600">Yeni Şifre</label>
        <input name="new_password" type="password" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2" placeholder="••••••••">
      </div>
      <div>
        <label class="text-xs text-slate-600">Yeni Şifre (Tekrar)</label>
        <input name="new_password_confirm" type="password" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2" placeholder="••••••••">
      </div>
      <div class="sm:col-span-2 flex items-center justify-end">
        <button class="rounded-xl px-4 py-2 font-extrabold bg-slate-900 text-white hover:bg-slate-800"
                type="submit" name="change_password" value="1">Şifreyi Güncelle</button>
      </div>
    </form>
  </div>
</div>
</div>

<script>
      (function(){
        const sel = document.querySelector('select[name="motor_default_type"]');
        const box = document.getElementById('rentalFields');
        function sync(){
          const isRental = sel && sel.value === 'rental';
          if (box) box.style.display = isRental ? '' : 'none';
        }
        if (sel) sel.addEventListener('change', sync);
        sync();
      })();
    </script>
<?php render_footer(); ?>
