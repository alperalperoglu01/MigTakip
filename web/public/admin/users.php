<?php
require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/db.php';

require_admin();
$pdo  = db();
$user = $_SESSION['user'];

// Silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
  csrf_check();
  $id = (int)($_POST['id'] ?? 0);

  if ($id <= 0) {
    flash_set('err', 'Geçersiz kullanıcı.');
    redirect('/admin/users.php');
  }

  // Admin kendini silmesin
  if ($id === (int)($user['id'] ?? 0)) {
    flash_set('err', 'Kendi hesabını silemezsin.');
    redirect('/admin/users.php');
  }

  try {
    $pdo->beginTransaction();

    // İlişkili kayıtları temizle (FK yoksa şart)
    $safeDeletes = [
      "DELETE FROM contact_replies WHERE sender_user_id = ?",
      "DELETE FROM contact_messages WHERE user_id = ?",
      "DELETE FROM day_entries WHERE user_id = ?",
      "DELETE FROM months WHERE user_id = ?",
    ];

    foreach ($safeDeletes as $sql) {
      try {
        $st = $pdo->prepare($sql);
        $st->execute([$id]);
      } catch (Throwable $e) {
        // tablo yoksa vs. görmezden gel
      }
    }

    $st = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $st->execute([$id]);

    $pdo->commit();
    flash_set('ok', 'Kullanıcı silindi.');
  } catch (Throwable $e) {
    try { $pdo->rollBack(); } catch (Throwable $e2) {}
    flash_set('err', 'Silme hatası: ' . $e->getMessage());
  }

  redirect('/admin/users.php');
}

$users = $pdo->query("SELECT id,name,email,phone,role,courier_class,seniority_start_date,created_at FROM users ORDER BY id DESC")->fetchAll();

render_header('Yönetici • Kullanıcılar', ['user'=>$user, 'is_admin'=>true]);
?>
<div class="max-w-6xl mb-4 flex flex-wrap items-center gap-2">
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/users.php">Kullanıcılar</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/tiers.php">Prim/Bonus</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/contacts.php">Mesajlar</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/reports.php">Raporlar</a>
</div>

<div class="rounded-3xl bg-white border border-slate-200 shadow-sm p-6 overflow-x-auto">
  <div class="flex items-center justify-between gap-3">
    <h2 class="text-lg font-extrabold">Kayıtlı Kullanıcılar</h2>
    <a class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-500"
       href="<?= e(base_url()) ?>/admin/user_add.php">+ Kullanıcı Ekle</a>
  </div>

  <table class="mt-4 w-full text-sm min-w-[760px]">
    <thead class="bg-slate-100">
      <tr>
        <th class="text-left p-3">Ad</th>
        <th class="text-left p-3">E-posta</th>
        <th class="text-left p-3">Rol</th>
        <th class="text-left p-3">Kurye Sınıfı</th>
        <th class="text-left p-3">Kıdem</th>
        <th class="text-left p-3">Tarih</th>
        <th class="text-left p-3">İşlem</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr class="border-t border-slate-200">
          <td class="p-3 font-semibold"><?= e($u['name']) ?></td>
          <td class="p-3"><?= e($u['email']) ?></td>
          <td class="p-3"><?= e($u['role']) ?></td>
          <td class="p-3"><?= e($u['courier_class'] ?: '-') ?></td>
          <td class="p-3 text-slate-600"><?php
            $yrs = $u['seniority_start_date'] ? (new DateTime($u['seniority_start_date']))->diff(new DateTime())->y : 0;
            echo $u['seniority_start_date'] ? e($yrs . ' yıl') : '-';
          ?></td>
          <td class="p-3 text-slate-500"><?= e($u['created_at']) ?></td>
          <td class="p-3">
            <div class="flex items-center gap-3">
              <a class="underline text-indigo-700" href="<?= e(base_url()) ?>/admin/user_edit.php?id=<?= e($u['id']) ?>">Düzenle</a>

              <form method="post" onsubmit="return confirm('Bu kullanıcı silinsin mi? Bu işlem geri alınamaz.');">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                <button class="underline text-rose-700 hover:text-rose-800" type="submit">Sil</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php render_footer(); ?>
