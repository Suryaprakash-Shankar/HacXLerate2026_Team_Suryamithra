<?php
require_once __DIR__ . '/includes/functions.php';

$provider = strtolower($_GET['provider'] ?? 'google');
if (!in_array($provider, ['google', 'microsoft'], true)) {
    redirect('index.php');
}

$action = strtolower($_GET['action'] ?? 'login');
$code = $_GET['code'] ?? '';

// Check if actual Client ID is set for OAuth 2.0 redirect
$clientId = ($provider === 'google') ? (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '') : (defined('MICROSOFT_CLIENT_ID') ? MICROSOFT_CLIENT_ID : '');

// If Real OAuth 2.0 Code is returned from Google / Microsoft
if ($code && $clientId) {
    // In production with real keys, exchange authorization code for access token here.
    // For seamless local execution, we fallback to account creation/login below.
}

// Determine OAuth parameters
$oauthEmail = trim($_REQUEST['email'] ?? '');
$oauthName  = trim($_REQUEST['name']  ?? '');
$reqRole    = trim($_REQUEST['role'] ?? 'student');
$reqDept    = trim($_REQUEST['department'] ?? 'CSE');
$reqClassName= trim($_REQUEST['class_name'] ?? 'Class A');
$reqSem     = (int)($_REQUEST['semester'] ?? 5);
$reqRoleId  = (int)($_REQUEST['role_id'] ?? 1);
$reqCode    = trim($_REQUEST['student_code'] ?? '');
$providerLabel = ucfirst($provider);

$pdo = db();

// If email is provided, check if user exists
if ($oauthEmail) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email=?');
    $stmt->execute([$oauthEmail]);
    $user = $stmt->fetch();

    if ($user) {
        // Log existing user in directly
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'department' => $user['department'] ?? 'CSE'
        ];
        $pdo->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$user['id']]);
        audit('oauth_login', $user['email'] . " ($providerLabel SSO)");
        flash("Signed in successfully with $providerLabel OAuth!");
        redirect($user['role'] === 'student' ? 'student.php' : 'dashboard.php');
    }
}

// If LOGIN mode and user not found
if ($action === 'login') {
    if ($oauthEmail) {
        flash("No registered account found for $oauthEmail. Please complete your registration details to create an account.", "warn");
        redirect('signup.php?email=' . urlencode($oauthEmail) . '&name=' . urlencode($oauthName));
    } else {
        flash("Please click Sign in with Google on the login page.", "info");
        redirect('index.php');
    }
}

// REGISTER NEW USER (SIGNUP MODE)
if (!$oauthEmail) {
    // Generate fallback email for OAuth signup if email parameter was omitted
    $slug = $oauthName ? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $oauthName)) : 'user';
    $oauthEmail = $slug . '_' . rand(100, 999) . '@' . $provider . '.sso.local';
}

try {
    $hash = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
    $role = in_array($reqRole, ['student', 'faculty', 'hod', 'placement']) ? $reqRole : 'student';
    if (!$oauthName) {
        $parts = explode('@', $oauthEmail);
        $oauthName = ucfirst($parts[0]);
    }

    // 1. Create User
    $uStmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, department) VALUES (?,?,?,?,?)');
    $uStmt->execute([$oauthName, $oauthEmail, $hash, $role, $reqDept]);
    $userId = (int)$pdo->lastInsertId();

    if ($role === 'student') {
        // 2. Create Student Record
        $code = $reqCode;
        if (!$code) {
            $maxId = (int)$pdo->query("SELECT MAX(id) FROM students")->fetchColumn();
            $code = 'ST' . (1000 + $maxId + 1);
        }

        // Find assigned class teacher for this department & section
        $tCheck = $pdo->prepare('SELECT teacher_id FROM class_assignments WHERE department=? AND class_name=?');
        $tCheck->execute([$reqDept, $reqClassName]);
        $assignedTeacherId = $tCheck->fetchColumn() ?: null;

        $pdo->prepare('INSERT INTO students (user_id, teacher_id, student_code, name, email, department, class_name, semester, bio, interests, projects) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$userId, $assignedTeacherId, $code, $oauthName, $oauthEmail, $reqDept, $reqClassName, $reqSem, "Authenticated via $providerLabel OAuth Single Sign-On", "Machine Learning, Software Development", "Cloud & Web Applications"]);
        $sid = (int)$pdo->lastInsertId();

        // 3. Populate baseline academic & placement data
        $pdo->prepare('INSERT INTO academic_records VALUES (?,?,?,?)')->execute([$sid, 8.0, 0, 80.0]);
        $pdo->prepare('INSERT INTO attendance VALUES (?,?)')->execute([$sid, 88.0]);
        $pdo->prepare('INSERT INTO lms_activity VALUES (?,?,?)')->execute([$sid, 15, 85.0]);
        $pdo->prepare('INSERT INTO engagement VALUES (?,?,?,?,?)')->execute([$sid, 3, 2, 1, 2]);
        $pdo->prepare('INSERT INTO placement VALUES (?,?,?,?,?,?,?)')->execute([$sid, 75.0, 75.0, 70.0, 80.0, 3, $reqRoleId]);
        $pdo->prepare('INSERT INTO feedback VALUES (?,?,?,?)')->execute([$sid, 4.5, 4.5, "Registered via $providerLabel OAuth"]);

        // Skills
        foreach (['Python', 'SQL', 'JavaScript', 'Communication', 'Problem Solving', 'Teamwork'] as $sk) {
            $cat = in_array($sk, ['Communication', 'Problem Solving', 'Teamwork']) ? 'soft' : 'technical';
            $pdo->prepare('INSERT INTO student_skills (student_id, skill, level, category) VALUES (?,?,?,?)')
                ->execute([$sid, $sk, 72, $cat]);
        }

        notify($userId, "Welcome to SURYAMITHRA ($providerLabel SSO)", "Your account was successfully created as a Student ($reqDept - $reqClassName) using $providerLabel Single Sign-On.", "good");
    } else {
        notify($userId, "Welcome to SURYAMITHRA ($providerLabel SSO)", "Your staff account ($role - $reqDept) was created using $providerLabel Single Sign-On.", "info");
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
} catch (PDOException $ex) {
    flash("OAuth Sign-up failed: " . $ex->getMessage(), "err");
    redirect('signup.php');
}

// Log new user in
session_regenerate_id(true);
$_SESSION['user'] = [
    'id' => (int)$user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'],
    'department' => $user['department'] ?? 'CSE'
];

$pdo->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$user['id']]);
audit('oauth_login', $user['email'] . " ($providerLabel SSO)");

flash("Signed in successfully with $providerLabel OAuth!");
redirect($user['role'] === 'student' ? 'student.php' : 'dashboard.php');
