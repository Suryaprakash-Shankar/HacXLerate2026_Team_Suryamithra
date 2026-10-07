<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) audit('logout', current_user()['email']);
$_SESSION = [];
session_destroy();
header('Location: ' . url('index.php'));
