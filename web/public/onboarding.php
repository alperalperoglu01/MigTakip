<?php
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/db.php';

require_login();
$user = $_SESSION['user'];
$pdo = db();

// Admin istemezse de doldurabilir ama zorunlu değil.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $phone = trim($_POST['phone'] ?? '');
  $accounting_fee = floatval(str_replace(',', '.', $_POST['accounting_fee'] ?? '0'));
  $motor_default_type = ($_POST['motor_default_type'] ?? 'own') === 'rental' ? 'rental' : 'own';
  $motor_monthly_rent = floatval(str_replace(',', '.', $_POST['motor_monthly_rent'] ?? '0'));
  $motor_plate = trim($_POST['motor_plate'] ?? '');

  $st = $pdo->prepare("UPDATE users SET phone=?, accounting_fee=?, motor_default_type=?, motor_monthly_rent=?, motor_plate=?, first_login_done=1 WHERE id=?");
  $st->execute([$phone ?: null, $accounting_fee, $motor_default_type, $motor_monthly_rent, ($motor_plate !== '' ? $motor_plate : null), $user['id']]);

  // session güncelle
  $_SESSION['user']['phone'] = $phone ?: null;
  $_SESSION['user']['accounting_fee'] = $accounting_fee;
  $_SESSION['user']['motor_default_type'] = $motor_default_type;
  $_SESSION['user']['motor_monthly_rent'] = $motor_monthly_rent;
  $_SESSION['user']['motor_plate'] = ($motor_plate !== '' ? $motor_plate : null);
  $_SESSION['user']['first_login_done'] = 1;

  flash_set('ok', 'Bilgiler kaydedildi.');
  redirect('/dashboard.php');
}

render_header('İlk Kurulum • Kurye Kazanç', ['user'=>$user, 'is_admin'=>(($user['role']??'')==='admin')]);
?>
<div class="max-w-xl mx-auto">
  <div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-6">
    <h1 class="text-xl font-extrabold">İlk Kurulum</h1>
    <p class="mt-1 text-sm text-slate-600">
      Bu bilgileri bir kere girmen yeterli. Sonradan <b>Hesap</b> sayfasından değiştirebilirsin.
    </p>

    <form method="post" class="mt-6 space-y-4">
      <?= csrf_field() ?>
      <div>
        <label class="text-sm font-semibold">Telefon</label>
        <input name="phone" value="<?= e($user['phone'] ?? '') ?>" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" placeholder="05xx..." />
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="text-sm font-semibold">Muhasebe Ücreti (TL / Ay)</label>
          <input name="accounting_fee" inputmode="decimal" value="<?= e((string)($user['accounting_fee'] ?? '0')) ?>" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
        </div>
        <div>
          <label class="text-sm font-semibold">Motor</label>
          <select name="motor_default_type" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3">
            <option value="own" <?= (($user['motor_default_type'] ?? 'own')==='own')?'selected':'' ?>>Kendi Motorum</option>
            <option value="rental" <?= (($user['motor_default_type'] ?? '')==='rental')?'selected':'' ?>>Kiralık Motor</option>
          </select>
          <div class="mt-1 text-xs text-slate-500">Kiralık ise giderlerde motor kira hesaplanır.</div>
        </div>
      </div>

      <div id="rentalFields" class="space-y-4">
      <div>
        <label class="text-sm font-semibold">Kiralık Motor Ücreti (TL / Ay)</label>
        <input name="motor_monthly_rent" inputmode="decimal" value="<?= e((string)($user['motor_monthly_rent'] ?? '0')) ?>" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" />
      </div>
      </div>
	  
	  <div>
        <label class="text-sm font-semibold">Motor Plakası</label>
        <input name="motor_plate" value="<?= e($user['motor_plate'] ?? '') ?>" class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3" placeholder="34 ABC 123" />
      </div>
</div>

      <button class="w-full rounded-2xl bg-slate-900 px-4 py-3 font-bold text-white hover:bg-slate-800">Kaydet ve Devam Et</button>


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

    </form>
  </div>
</div>
<?php render_footer(); ?>
