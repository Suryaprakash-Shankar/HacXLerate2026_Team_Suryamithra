<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) redirect(current_user()['role'] === 'student' ? 'student.php' : 'dashboard.php');

$error = '';
$jobRoles = db()->query('SELECT id, name FROM job_roles ORDER BY name')->fetchAll();
$departments = load_departments();

$prefillEmail = trim($_GET['email'] ?? $_POST['email'] ?? '');
$prefillName  = trim($_GET['name']  ?? $_POST['name']  ?? '');
$infoNotice   = '';
if (!empty($_GET['email']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $infoNotice = 'No SURYAMITHRA account found for ' . e($_GET['email']) . '. Please complete the basic details below to create your account.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $dept = trim($_POST['department'] ?? 'CSE');

    // Validation
    if (!$name || !$email || !$password) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if email already exists
        $check = db()->prepare('SELECT id FROM users WHERE email=?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            try {
                $pdo = db();
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // 1. Create User Login
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, department) VALUES (?,?,?,?,?)');
                $stmt->execute([$name, $email, $hash, $role, $dept]);
                $userId = (int)$pdo->lastInsertId();

                // 2. If Student role, create full Student profile and initial baseline data
                if ($role === 'student') {
                    $code = trim($_POST['student_code'] ?? '');
                    if (!$code) {
                        $maxId = (int)$pdo->query("SELECT MAX(id) FROM students")->fetchColumn();
                        $code = 'ST' . (1000 + $maxId + 1);
                    }
                    $sem = (int)($_POST['semester'] ?? 5);
                    $className = trim($_POST['class_name'] ?? 'Class A');
                    $roleId = (int)($_POST['role_id'] ?? ($jobRoles[0]['id'] ?? 1));

                    // Check code uniqueness
                    $codeCheck = $pdo->prepare('SELECT id FROM students WHERE student_code=?');
                    $codeCheck->execute([$code]);
                    if ($codeCheck->fetch()) {
                        $code = 'ST' . (1000 + $userId + rand(100, 999));
                    }

                    // Find class teacher if assigned
                    $tCheck = $pdo->prepare('SELECT teacher_id FROM class_assignments WHERE department=? AND class_name=?');
                    $tCheck->execute([$dept, $className]);
                    $assignedTeacherId = $tCheck->fetchColumn() ?: null;

                    // Insert student with clean empty profile
                    $pdo->prepare('INSERT INTO students (user_id, teacher_id, student_code, name, email, department, class_name, semester, bio, interests, projects) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$userId, $assignedTeacherId, $code, $name, $email, $dept, $className, $sem, '', '', '']);
                    $sid = (int)$pdo->lastInsertId();

                    // Baseline empty scores
                    $pdo->prepare('INSERT INTO academic_records VALUES (?,?,?,?)')->execute([$sid, 0.0, 0, 0.0]);
                    $pdo->prepare('INSERT INTO attendance VALUES (?,?)')->execute([$sid, 0.0]);
                    $pdo->prepare('INSERT INTO lms_activity VALUES (?,?,?)')->execute([$sid, 0, 0.0]);
                    $pdo->prepare('INSERT INTO engagement VALUES (?,?,?,?,?)')->execute([$sid, 0, 0, 0, 0]);
                    $pdo->prepare('INSERT INTO placement VALUES (?,?,?,?,?,?,?)')->execute([$sid, 0.0, 0.0, 0.0, 0.0, 0, $roleId]);
                    $pdo->prepare('INSERT INTO feedback VALUES (?,?,?,?)')->execute([$sid, 0.0, 0.0, 'New registration']);

                    // Welcome notification
                    notify($userId, 'Welcome to SURYAMITHRA', 'Your student account has been created. Your profile will populate as your class tutor and subject teachers enter attendance and marks.', 'good');
                } else {
                    notify($userId, 'Welcome to SURYAMITHRA', 'Your staff account is ready. Access the dashboard to view institution analytics.', 'info');
                }

                // Log user in automatically
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'department' => $dept
                ];
                
                db()->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$userId]);
                audit('signup', "$email ($role)");
                flash("Welcome to SURYAMITHRA, $name! Your account is active.");

                redirect($role === 'student' ? 'student.php' : 'dashboard.php');
            } catch (PDOException $ex) {
                $error = 'Failed to create account: ' . $ex->getMessage();
            }
        }
    }
}

$fbConfig = [
  'apiKey' => FIREBASE_API_KEY,
  'authDomain' => FIREBASE_AUTH_DOMAIN,
  'projectId' => FIREBASE_PROJECT_ID,
  'storageBucket' => FIREBASE_STORAGE_BUCKET,
  'messagingSenderId' => FIREBASE_MESSAGING_SENDER_ID,
  'appId' => FIREBASE_APP_ID
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign up · SURYAMITHRA</title>
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
    <a class="brand" href="<?= url('index.php') ?>"><span>SURYA<b>MITHRA</b></span></a>
    <div class="hero-copy">
      <h1>Empowering Every Student to Reach Their Full Potential.</h1>
      <p>Join SURYAMITHRA to access unified student decision intelligence, explainable success scores, real-time skill gap heatmaps, and placement matching.</p>
    </div>
    <div class="peek">
      <div class="ring" style="--p:82;--c:var(--green)"><b>82</b><small>/100</small></div>
      <div class="peek-body">
        <strong>Create Your SURYAMITHRA Account</strong>
        <span class="pill green">Multi-role support</span>
        <div class="mini"><i style="width:100%;background:var(--primary)"></i><em>Students · Teachers · HODs · Placement</em></div>
      </div>
    </div>
  </section>
  <section class="login-form" style="padding: 30px 40px;">
    <form method="post" class="lf" style="max-width: 440px;">
      <?= csrf_field() ?>
      <h2>Create an account</h2>
      <p class="muted">Select your role to get started with SURYAMITHRA.</p>
      
      <?php if ($infoNotice): ?>
        <div class="flash info" style="margin-bottom: 15px; border-left: 4px solid var(--primary); background: rgba(59, 130, 246, 0.1); padding: 12px 16px; border-radius: 8px;">
          <strong>Account Setup Required:</strong> <?= $infoNotice ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="flash err"><?= e($error) ?></div>
      <?php endif; ?>

      <label>Full Name
        <input name="name" required placeholder="e.g. Ananya Sundaram" value="<?= e($prefillName) ?>">
      </label>

      <label>Email Address
        <input type="email" name="email" required placeholder="you@suryamithra.local" value="<?= e($prefillEmail) ?>">
      </label>

      <label>Role
        <select name="role" id="roleSelect" onchange="toggleStudentFields()">
          <option value="student" <?= ($_POST['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
          <option value="faculty" <?= ($_POST['role'] ?? '') === 'faculty' ? 'selected' : '' ?>>Teacher / Faculty</option>
          <option value="hod" <?= ($_POST['role'] ?? '') === 'hod' ? 'selected' : '' ?>>HOD (Head of Dept)</option>
          <option value="placement" <?= ($_POST['role'] ?? '') === 'placement' ? 'selected' : '' ?>>Placement Officer</option>
        </select>
      </label>

      <label>Department
        <select name="department" id="deptSelect">
          <?php foreach ($departments as $d): ?>
            <option value="<?= e($d) ?>" <?= ($_POST['department'] ?? '') === $d ? 'selected' : '' ?>><?= e($d) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <!-- Student Specific Fields -->
      <div id="studentFields">
        <label style="margin-top: 10px;">Class / Section
          <select name="class_name">
            <option value="Class A" <?= ($_POST['class_name'] ?? '') === 'Class A' ? 'selected' : '' ?>>Class A</option>
            <option value="Class B" <?= ($_POST['class_name'] ?? '') === 'Class B' ? 'selected' : '' ?>>Class B</option>
            <option value="Class C" <?= ($_POST['class_name'] ?? '') === 'Class C' ? 'selected' : '' ?>>Class C</option>
          </select>
        </label>
        <label style="margin-top: 10px;">Student Roll / Code (Optional)
          <input name="student_code" placeholder="e.g. ST1065 (auto-generated if empty)" value="<?= e($_POST['student_code'] ?? '') ?>">
        </label>
        <label style="margin-top: 10px;">Current Semester
          <select name="semester">
            <?php for($i=1;$i<=8;$i++): ?>
              <option value="<?= $i ?>" <?= $i==5 ? 'selected':'' ?>>Semester <?= $i ?></option>
            <?php endfor; ?>
          </select>
        </label>
        <label style="margin-top: 10px;">Target Job Role
          <select name="role_id">
            <?php foreach ($jobRoles as $jr): ?>
              <option value="<?= $jr['id'] ?>"><?= e($jr['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <label>Password
        <input type="password" name="password" required minlength="6" placeholder="At least 6 characters">
      </label>

      <label>Confirm Password
        <input type="password" name="confirm_password" required minlength="6" placeholder="Repeat password">
      </label>

      <button class="btn primary wide" style="margin-top: 14px">Create Account & Sign In</button>

      <div class="divider">or sign up with</div>

      <div class="oauth-group">
        <button type="button" onclick="suryaFirebaseSignUp('google', event)" class="oauth-btn google-btn">
          <svg viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
          <span>Firebase Sign up with Google</span>
        </button>
        <button type="button" onclick="suryaFirebaseSignUp('microsoft', event)" class="oauth-btn microsoft-btn">
          <svg viewBox="0 0 23 23"><path fill="#f35325" d="M1 1h10v10H1z"/><path fill="#81bc06" d="M12 1h10v10H1z"/><path fill="#05a6f0" d="M1 12h10v10H1z"/><path fill="#ffba08" d="M12 12h10v10H12z"/></svg>
          <span>Firebase Sign up with Microsoft</span>
        </button>
      </div>

      <p class="muted" style="text-align: center; margin-top: 16px;">
        Already have an account? <a href="<?= url('index.php') ?>" style="font-weight: 700;">Sign in here</a>
      </p>
    </form>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  window.SURYA_FB_CONFIG = <?= json_encode($fbConfig) ?>;
  initSuryaFirebase(window.SURYA_FB_CONFIG);
});

function toggleStudentFields() {
  var role = document.getElementById('roleSelect').value;
  var sf = document.getElementById('studentFields');
  sf.style.display = role === 'student' ? 'block' : 'none';
}
toggleStudentFields();

function suryaFirebaseSignUp(provider, evt) {
  var nameEl = document.querySelector('input[name="name"]');
  var name = nameEl ? nameEl.value.trim() : '';
  var role = document.getElementById('roleSelect').value;
  var dept = document.getElementById('deptSelect').value;
  var classEl = document.querySelector('select[name="class_name"]');
  var className = classEl ? classEl.value : 'Class A';
  var roleIdEl = document.querySelector('select[name="role_id"]');
  var roleId = roleIdEl ? roleIdEl.value : 1;
  var semEl = document.querySelector('select[name="semester"]');
  var sem = semEl ? semEl.value : 5;
  var codeEl = document.querySelector('input[name="student_code"]');
  var code = codeEl ? codeEl.value.trim() : '';
  var csrf = '<?= csrf_token() ?>';

  suryaFirebaseAuth(provider, { 
    mode: 'signup',
    name: name,
    role: role, 
    department: dept, 
    class_name: className, 
    semester: sem, 
    role_id: roleId, 
    student_code: code, 
    csrf: csrf 
  }, evt);
}
</script>
</body>
</html>
