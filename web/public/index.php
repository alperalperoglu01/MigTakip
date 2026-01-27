<?php
require_once __DIR__ . '/../app/helpers.php';
session_boot();
if (!empty($_SESSION['user'])) redirect('/dashboard.php');
redirect('/login.php');
