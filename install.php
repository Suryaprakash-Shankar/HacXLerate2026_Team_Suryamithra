<?php
// install.php is replaced by automatic default DB setup in functions.php
require_once __DIR__ . '/includes/functions.php';
db(); // triggers auto-installation if needed
redirect('index.php');
