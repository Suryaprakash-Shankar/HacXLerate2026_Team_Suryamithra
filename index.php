<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) redirect(current_user()['role'] === 'student' ? 'student.php' : 'dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('SELECT * FROM users WHERE email=? AND is_active=1');
    $st->execute([trim($_POST['email'] ?? '')]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'], 'department' => $u['department'] ?? ''];
        db()->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$u['id']]);
        audit('login', $u['email']);
        redirect($u['role'] === 'student' ? 'student.php' : 'dashboard.php');
    }
    $error = 'Email or password is incorrect.';
}

$demo = [
  ['HOD CSE', 'hod_cse@suryamithra.local', 'cse@2026'],
  ['Teacher / Faculty', 'faculty@suryamithra.local', 'password'],
  ['Student Profile', 'student@suryamithra.local', 'password'],
  ['Placement Officer', 'placement@suryamithra.local', 'password'],
  ['Admin', 'admin@suryamithra.local', 'password']
];

$fbConfig = [
  'apiKey' => FIREBASE_API_KEY,
  'authDomain' => FIREBASE_AUTH_DOMAIN,
  'projectId' => FIREBASE_PROJECT_ID,
  'storageBucket' => FIREBASE_STORAGE_BUCKET,
  'messagingSenderId' => FIREBASE_MESSAGING_SENDER_ID,
  'appId' => FIREBASE_APP_ID
];
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · SURYAMITHRA</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
<!-- Firebase Web SDK Compat -->
<script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-auth-compat.js"></script>
<script>window.BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= url('assets/js/firebase-auth.js') ?>?v=<?= filemtime(__DIR__ . '/assets/js/firebase-auth.js') ?>"></script>
</head>
<body class="login-body">
<div class="login">
  <section class="login-hero">
    <a class="brand" href="#"><span>SURYA<b>MITHRA</b></span></a>
    <div class="hero-copy">
      <h1>Know which students need help before the results do.</h1>
      <p>SURYAMITHRA unifies academic records, attendance, LMS activity, placement aptitude & coding scores, technical/soft skill assessments, and faculty feedback into an explainable student success intelligence platform.</p>
    </div>
    <div class="peek">
      <div class="ring" style="--p:43;--c:var(--red)"><b>43</b><small>/100</small></div>
      <div class="peek-body">
        <strong>Arun Kumar · ST1001</strong>
        <span class="pill red">High risk</span>
        <div class="mini"><i style="width:34%"></i><em>Attendance 34% of risk</em></div>
        <div class="mini"><i style="width:26%"></i><em>Academic 26%</em></div>
        <div class="mini"><i style="width:19%"></i><em>Placement 19%</em></div>
      </div>
    </div>
  </section>
  <section class="login-form">
    <form method="post" class="lf">
      <?= csrf_field() ?>
      <h2>Sign in to SURYAMITHRA</h2>
      <p class="muted">Use your institutional account. Each role accesses a custom workspace.</p>
      <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
      <label>Email<input type="email" name="email" id="email" required autofocus placeholder="you@suryamithra.local"></label>
      <label>Password<input type="password" name="password" id="password" required placeholder="Your password"></label>
      <button class="btn primary wide">Sign in</button>

      <div class="divider">or continue with</div>

      <div class="oauth-group">
        <button type="button" onclick="suryaFirebaseAuth('google', { csrf: '<?= csrf_token() ?>', mode: 'login' }, event)" class="oauth-btn google-btn">
          <svg viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
          <span>Firebase Sign in with Google</span>
        </button>
        <button type="button" onclick="suryaFirebaseAuth('microsoft', { csrf: '<?= csrf_token() ?>', mode: 'login' }, event)" class="oauth-btn microsoft-btn">
          <svg viewBox="0 0 23 23"><path fill="#f35325" d="M1 1h10v10H1z"/><path fill="#81bc06" d="M12 1h10v10H12z"/><path fill="#05a6f0" d="M1 12h10v10H1z"/><path fill="#ffba08" d="M12 12h10v10H12z"/></svg>
          <span>Firebase Sign in with Microsoft</span>
        </button>
      </div>

      <p class="muted" style="text-align: center; margin: 12px 0 0;">
        Don't have an account? <a href="<?= url('signup.php') ?>" style="font-weight: 700; color: var(--primary)">Sign up here</a>
      </p>
      <div class="demo">
        <small>Demo accounts (password is <code>password</code>)</small>
        <div class="chips">
          <?php foreach ($demo as [$l, $m, $p]): ?><button type="button" class="chip" data-email="<?= e($m) ?>" data-pass="<?= e($p) ?>"><?= e($l) ?></button><?php endforeach; ?>
        </div>
      </div>
    </form>
  </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  window.SURYA_FB_CONFIG = <?= json_encode($fbConfig) ?>;
  initSuryaFirebase(window.SURYA_FB_CONFIG);
});
document.querySelectorAll('.chip').forEach(function (b) {
  b.addEventListener('click', function () {
    document.getElementById('email').value = b.dataset.email;
    document.getElementById('password').value = b.dataset.pass || 'password';
  });
});
</script>
</body></html>
