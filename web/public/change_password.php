<?php
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

require_login();
// Şifre değişimi artık Hesap sayfasında
redirect('/account.php#password');
