<?php
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/db.php';

require_login();
$pdo = db();
$me = $_SESSION['user'];
$is_admin = ($me['role'] ?? '') === 'admin';

function status_badge($s): array {
  $is_open = (int)$s === 1;
  return $is_open
    ? ['Açık', 'border-emerald-200 bg-emerald-50 text-emerald-900']
    : ['Kapalı', 'border-slate-200 bg-white text-slate-700'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  // Reply
  if (!empty($_POST['reply_contact_id'])) {
    $cid = (int)($_POST['reply_contact_id'] ?? 0);
    $msg = trim($_POST['reply_message'] ?? '');
    if ($cid <= 0) {
      flash_set('err', 'Geçersiz mesaj.');
      redirect('/contact.php');
    }
    if ($msg === '') {
      flash_set('err', 'Cevap boş olamaz.');
      redirect('/contact.php#c' . $cid);
    }

    // ensure ownership
    $chk = $pdo->prepare("SELECT id, status FROM contact_messages WHERE id=? AND user_id=? LIMIT 1");
    $chk->execute([$cid, $me['id']]);
    $t = $chk->fetch();
    if (!$t) {
      flash_set('err', 'Bu mesaja erişimin yok.');
      redirect('/contact.php');
    }

    $st = $pdo->prepare("INSERT INTO contact_replies (contact_id, sender_type, sender_user_id, message) VALUES (?,?,?,?)");
    $st->execute([$cid, 'user', $me['id'], $msg]);

    // user replies reopen ticket
    $pdo->prepare("UPDATE contact_messages SET status=1 WHERE id=?")->execute([$cid]);

    flash_set('ok', 'Cevabın gönderildi.');
    redirect('/contact.php#c' . $cid);
  }

  // New ticket
  $category = trim($_POST['category'] ?? 'Bug');
  $allowed = ['Öneri','Şikayet','Talep','Bug'];
  if (!in_array($category, $allowed, true)) $category = 'Bug';
  $subject = trim($_POST['subject'] ?? '');
  $message = trim($_POST['message'] ?? '');
  if ($subject === '') {
    flash_set('err', 'Konu başlığı boş olamaz.');
    redirect('/contact.php');
  }
  if ($message === '') {
    flash_set('err', 'Mesaj boş olamaz.');
    redirect('/contact.php');
  }
  $st = $pdo->prepare("INSERT INTO contact_messages (user_id, category, subject, message, status) VALUES (?,?,?,?,1)");
  $st->execute([$me['id'], $category, $subject, $message]);
  $new_id = (int)$pdo->lastInsertId();

  flash_set('ok', 'Mesajın iletildi.');
  redirect('/contact.php#c' . $new_id);
}

$my = $pdo->prepare("SELECT id, category, subject, message, status, created_at FROM contact_messages WHERE user_id=? ORDER BY id DESC LIMIT 30");
$my->execute([$me['id']]);
$rows = $my->fetchAll();

$ids = array_map(fn($r)=> (int)$r['id'], $rows);
$repliesBy = [];
if ($ids) {
  $in = implode(',', array_fill(0, count($ids), '?'));
  $st = $pdo->prepare("SELECT id, contact_id, sender_type, sender_user_id, message, created_at FROM contact_replies WHERE contact_id IN ($in) ORDER BY id ASC");
  $st->execute($ids);
  foreach ($st->fetchAll() as $rep) {
    $cid = (int)$rep['contact_id'];
    if (!isset($repliesBy[$cid])) $repliesBy[$cid] = [];
    $repliesBy[$cid][] = $rep;
  }
}

render_header('İletişim', ['user'=>$me, 'is_admin'=>$is_admin]);
?>
<div class="max-w-4xl space-y-4">
  <div class="rounded-2xl bg-white border border-slate-200 p-4">
    <div class="text-lg font-extrabold">Admine Bildir</div>
    <p class="mt-1 text-sm text-slate-600">Şikayet, öneri, talep veya sorunlarını buradan iletebilirsin.</p>

    <form class="mt-3 grid gap-3" method="post">
      <?= csrf_field() ?>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="text-xs text-slate-600">Konu Tipi</label>
          <select name="category" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2">
            <option value="Öneri">Öneri</option>
            <option value="Şikayet">Şikayet</option>
            <option value="Talep">Talep</option>
            <option value="Bug">Bug</option>
          </select>
        </div>
        <div class="sm:col-span-2">
          <label class="text-xs text-slate-600">Konu Başlığı</label>
          <input name="subject" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2" placeholder="Kısa başlık...">
        </div>
      </div>

      <div>
        <label class="text-xs text-slate-600">Mesaj</label>
        <textarea name="message" rows="5" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2" placeholder="Detayları yaz..."></textarea>
      </div>

      <div class="flex items-center justify-end">
        <button class="rounded-xl px-4 py-2 font-extrabold bg-slate-900 text-white hover:bg-slate-800"
                type="submit">Gönder</button>
      </div>
    </form>
  </div>

  <div class="rounded-2xl bg-white border border-slate-200 p-4">
    <div class="text-sm font-extrabold">Mesajların</div>

    <?php if (!$rows): ?>
      <div class="mt-2 text-sm text-slate-500">Henüz mesaj yok.</div>
    <?php else: ?>
      <div class="mt-3 space-y-2">
        <?php foreach ($rows as $r): ?>
          <?php [$st_text, $st_cls] = status_badge($r['status']); ?>
          <details id="c<?= e((string)$r['id']) ?>" class="rounded-2xl border border-slate-200 bg-slate-50">
            <summary class="cursor-pointer list-none px-4 py-3 flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2 py-0.5 text-xs font-bold"><?= e($r['category']) ?></span>
                  <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-extrabold <?= e($st_cls) ?>"><?= e($st_text) ?></span>
                </div>
                <div class="mt-1 font-extrabold truncate"><?= e($r['subject']) ?></div>
              </div>
              <div class="text-xs text-slate-500 whitespace-nowrap"><?= e($r['created_at']) ?></div>
            </summary>

            <div class="px-4 pb-4">
              <div class="mt-2 rounded-xl border border-slate-200 bg-white p-3">
                <div class="text-xs text-slate-500">Sen • <?= e($r['created_at']) ?></div>
                <div class="mt-1 text-sm whitespace-pre-wrap"><?= e($r['message']) ?></div>
              </div>

              <?php $reps = $repliesBy[(int)$r['id']] ?? []; ?>
              <?php if ($reps): ?>
                <div class="mt-3 space-y-2">
                  <?php foreach ($reps as $rep): ?>
                    <?php $is_me = ($rep['sender_type'] === 'user'); ?>
                    <div class="rounded-xl border border-slate-200 <?= $is_me ? 'bg-white' : 'bg-indigo-50' ?> p-3">
                      <div class="text-xs text-slate-500">
                        <?= $is_me ? 'Sen' : 'Admin' ?> • <?= e($rep['created_at']) ?>
                      </div>
                      <div class="mt-1 text-sm whitespace-pre-wrap"><?= e($rep['message']) ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <form class="mt-3 grid gap-2" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="reply_contact_id" value="<?= e((string)$r['id']) ?>">
                <label class="text-xs text-slate-600">Cevap yaz</label>
                <textarea name="reply_message" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2" placeholder="Yanıtını yaz..."></textarea>
                <div class="flex items-center justify-end">
                  <button class="rounded-xl px-4 py-2 font-extrabold bg-slate-900 text-white hover:bg-slate-800"
                          type="submit">Gönder</button>
                </div>
              </form>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php render_footer(); ?>
