<?php
require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/layout.php';
require_once __DIR__ . '/../../app/db.php';

require_admin();
$pdo = db();
$me = $_SESSION['user'];

function status_badge($s): array {
  $is_open = (int)$s === 1;
  return $is_open
    ? ['Açık', 'border-emerald-200 bg-emerald-50 text-emerald-900']
    : ['Kapalı', 'border-slate-200 bg-white text-slate-700'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  // Close/Open ticket
  if (!empty($_POST['action'])) {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id > 0 && in_array($action, ['close','open'], true)) {
      $status = $action === 'close' ? 0 : 1; // 1=Açık, 0=Kapalı
      $st = $pdo->prepare("UPDATE contact_messages SET status=? WHERE id=?");
      $st->execute([$status, $id]);
      flash_set('ok', 'Güncellendi.');
    }
    redirect('/admin/contacts.php#c' . $id);
  }

  // Admin reply
  if (!empty($_POST['reply_contact_id'])) {
    $cid = (int)($_POST['reply_contact_id'] ?? 0);
    $msg = trim($_POST['reply_message'] ?? '');
    if ($cid <= 0 || $msg === '') {
      flash_set('err', 'Cevap boş olamaz.');
      redirect('/admin/contacts.php');
    }

    $st = $pdo->prepare("INSERT INTO contact_replies (contact_id, sender_type, sender_user_id, message) VALUES (?,?,?,?)");
    $st->execute([$cid, 'admin', $me['id'], $msg]);

    // reply keeps ticket open
    $pdo->prepare("UPDATE contact_messages SET status=1 WHERE id=?")->execute([$cid]);

    flash_set('ok', 'Yanıt gönderildi.');
    redirect('/admin/contacts.php#c' . $cid);
  }

  redirect('/admin/contacts.php');
}

$rows = $pdo->query("SELECT cm.id, cm.user_id, cm.category, cm.subject, cm.message, cm.status, cm.created_at,
                            u.name, u.email
                     FROM contact_messages cm
                     JOIN users u ON u.id = cm.user_id
                     ORDER BY cm.id DESC
                     LIMIT 300")->fetchAll();

$ids = array_map(fn($r)=> (int)$r['id'], $rows);
$repliesBy = [];
if ($ids) {
  $in = implode(',', array_fill(0, count($ids), '?'));
  $st = $pdo->prepare("SELECT id, contact_id, sender_type, sender_user_id, message, created_at
                       FROM contact_replies
                       WHERE contact_id IN ($in)
                       ORDER BY id ASC");
  $st->execute($ids);
  foreach ($st->fetchAll() as $rep) {
    $cid = (int)$rep['contact_id'];
    if (!isset($repliesBy[$cid])) $repliesBy[$cid] = [];
    $repliesBy[$cid][] = $rep;
  }
}

render_header('Yönetici • Mesajlar', ['user'=>$me, 'is_admin'=>true]);
?>

<div class="max-w-6xl mb-4 flex flex-wrap items-center gap-2">
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/users.php">Kullanıcılar</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/tiers.php">Prim/Bonus</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/admin/contacts.php">Mesajlar</a>
  <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50" href="<?= e(base_url()) ?>/reports.php">Raporlar</a>
</div>

  <div class="rounded-3xl bg-white border border-slate-200 p-4">
   
    <?php if (!$rows): ?>
      <div class="mt-3 text-sm text-slate-500">Mesaj yok.</div>
    <?php else: ?>
      <div class="mt-3 space-y-2">
        <?php foreach ($rows as $r): ?>
          <?php [$st_text, $st_cls] = status_badge($r['status']); ?>
          <details id="c<?= e((string)$r['id']) ?>" class="rounded-2xl border border-slate-200 bg-slate-50">
            <summary class="cursor-pointer list-none px-4 py-3 flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="font-extrabold truncate"><?= e($r['name']) ?> <span class="text-xs text-slate-500 font-normal">(<?= e($r['email']) ?>)</span></div>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                  <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2 py-0.5 text-xs font-bold"><?= e($r['category']) ?></span>
                  <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-extrabold <?= e($st_cls) ?>"><?= e($st_text) ?></span>
                  <span class="text-xs text-slate-500"><?= e($r['created_at']) ?></span>
                </div>
                <div class="mt-1 font-extrabold truncate"><?= e($r['subject'] ?? '') ?></div>
              </div>
              <div class="text-xs text-slate-500 whitespace-nowrap">#<?= e((string)$r['id']) ?></div>
            </summary>

            <div class="px-4 pb-4">
              <div class="mt-2 rounded-xl border border-slate-200 bg-white p-3">
                <div class="text-xs text-slate-500"><?= e($r['name']) ?> • <?= e($r['created_at']) ?></div>
                <div class="mt-1 text-sm whitespace-pre-wrap"><?= e($r['message']) ?></div>
              </div>

              <?php $reps = $repliesBy[(int)$r['id']] ?? []; ?>
              <?php if ($reps): ?>
                <div class="mt-3 space-y-2">
                  <?php foreach ($reps as $rep): ?>
                    <?php $is_admin = ($rep['sender_type'] === 'admin'); ?>
                    <div class="rounded-xl border border-slate-200 <?= $is_admin ? 'bg-indigo-50' : 'bg-white' ?> p-3">
                      <div class="text-xs text-slate-500">
                        <?= $is_admin ? 'Admin' : 'Kurye' ?> • <?= e($rep['created_at']) ?>
                      </div>
                      <div class="mt-1 text-sm whitespace-pre-wrap"><?= e($rep['message']) ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <div class="mt-3 grid gap-2">
                <form class="grid gap-2" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="reply_contact_id" value="<?= e((string)$r['id']) ?>">
                  <label class="text-xs text-slate-600">Yanıt yaz</label>
                  <textarea name="reply_message" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2" placeholder="Kuryeye yanıt..."></textarea>
                  <div class="flex items-center justify-end">
                    <button class="rounded-xl px-4 py-2 font-extrabold bg-slate-900 text-white hover:bg-slate-800"
                            type="submit">Gönder</button>
                  </div>
                </form>

                <form class="flex items-center justify-end gap-2" method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
                  <?php if ((int)$r['status'] === 1): ?>
                    <button class="rounded-xl px-3 py-2 text-sm font-extrabold bg-white border border-slate-200 hover:bg-slate-50"
                            name="action" value="close" type="submit">Kapat</button>
                  <?php else: ?>
                    <button class="rounded-xl px-3 py-2 text-sm font-extrabold bg-white border border-slate-200 hover:bg-slate-50"
                            name="action" value="open" type="submit">Tekrar Aç</button>
                  <?php endif; ?>
                </form>
              </div>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php render_footer(); ?>
