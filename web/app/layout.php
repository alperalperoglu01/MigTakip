<?php
require_once __DIR__ . '/helpers.php';

function render_header(string $title, array $opts = []): void {
  $user = $opts['user'] ?? null;
  $is_admin = $opts['is_admin'] ?? false;
  $role = $user['role'] ?? '';
  $is_manager = in_array($role, ['admin','chef'], true);
  $flash = flash_get();
  ?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($title) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900">
  <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-2xl text-white flex items-center justify-center font-black" style="background-color: rgb(251 146 60);">Mig</div>
        <div class="leading-tight">
          <div class="font-extrabold">Kurye Kazanç</div>
          <div class="text-xs text-slate-500">prim/bonus hesaplayıcı</div>
        </div>
      </div>

      <!-- Desktop nav (sm+) -->
      <nav class="hidden sm:flex items-center gap-2">
        <?php if ($user): ?>
          <span class="hidden md:inline text-sm text-slate-600">Merhaba, <b><?= e($user['name']) ?></b></span>
          <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50"
             href="<?= e(base_url()) ?>/dashboard.php">Panel</a>
          <?php if ($is_admin): ?>
            <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50"
               href="<?= e(base_url()) ?>/admin/users.php">Yönetici</a>
          <?php endif; ?>
          <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50"
             href="<?= e(base_url()) ?>/account.php">Hesap</a>
          <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50"
             href="<?= e(base_url()) ?>/contact.php">İletişim</a>
          <a class="rounded-xl px-3 py-2 text-sm font-semibold bg-slate-900 text-white hover:bg-slate-800"
             href="<?= e(base_url()) ?>/logout.php">Çıkış</a>
        <?php else: ?>
          <a class="rounded-xl px-3 py-2 text-sm font-semibold bg-slate-900 text-white hover:bg-slate-800"
             href="<?= e(base_url()) ?>/login.php">Giriş</a>
        <?php endif; ?>
      </nav>

      <!-- Mobile menu button (xs) -->
      <button type="button"
              class="sm:hidden inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2"
              aria-label="Menüyü Aç/Kapat"
              onclick="toggleMobileMenu()">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
    </div>

    <!-- Mobile dropdown (xs) -->
    <div id="mobileMenu" class="sm:hidden hidden border-t border-slate-200 bg-white">
      <div class="max-w-6xl mx-auto px-4 py-3 flex flex-col gap-2">
        <?php if ($user): ?>
          <div class="text-sm text-slate-600">Merhaba, <b><?= e($user['name']) ?></b></div>
          <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50"
             href="<?= e(base_url()) ?>/dashboard.php">Panel</a>
          <?php if ($is_admin): ?>
            <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50"
               href="<?= e(base_url()) ?>/admin/users.php">Yönetici</a>
          <?php endif; ?>
          <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50"
             href="<?= e(base_url()) ?>/account.php">Hesap</a>
          <a class="rounded-xl px-3 py-2 text-sm font-semibold border border-slate-200 bg-white hover:bg-slate-50"
             href="<?= e(base_url()) ?>/contact.php">İletişim</a>
          <a class="rounded-xl px-3 py-2 text-sm font-semibold bg-slate-900 text-white hover:bg-slate-800"
             href="<?= e(base_url()) ?>/logout.php">Çıkış</a>
        <?php else: ?>
          <a class="rounded-xl px-3 py-2 text-sm font-semibold bg-slate-900 text-white hover:bg-slate-800"
             href="<?= e(base_url()) ?>/login.php">Giriş</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <script>
    function toggleMobileMenu() {
      var el = document.getElementById('mobileMenu');
      if (!el) return;
      el.classList.toggle('hidden');
    }
  </script>

  <main class="max-w-6xl mx-auto px-4 py-6">
    <?php if ($flash): ?>
      <div class="mb-4 rounded-2xl border px-4 py-3 text-sm <?= $flash['type']==='ok'?'border-emerald-200 bg-emerald-50 text-emerald-900':'border-rose-200 bg-rose-50 text-rose-900' ?>">
        <?= e($flash['msg']) ?>
      </div>
    <?php endif; ?>
<?php
}

function render_footer(): void { ?>
  </main>
  <footer class="border-t border-slate-200 bg-white">
    <div class="max-w-6xl mx-auto px-4 py-5 text-xs text-slate-500 flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between">
      <div>© <?= date('Y') ?> Kurye Kazanç | MigTakip</div>
      <div>Created By Alper ALPEROĞLU</div>
    </div>
  </footer>
</body>
</html>
<?php } ?>
