<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Check CSRF
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please refresh the page.']);
    exit;
}

$idToken     = trim($_POST['id_token'] ?? '');
$provider    = trim($_POST['provider'] ?? 'firebase');
$name        = trim($_POST['name'] ?? '');
$reqRole     = trim($_POST['role'] ?? 'student');
$reqDept     = trim($_POST['department'] ?? 'CSE');
$reqClassName= trim($_POST['class_name'] ?? 'Class A');
$reqSem      = (int)($_POST['semester'] ?? 5);
$reqRoleId   = (int)($_POST['role_id'] ?? 1);
$reqCode     = trim($_POST['student_code'] ?? '');

$mode        = trim($_POST['mode'] ?? 'login');

if (!$idToken) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing Firebase ID token.']);
    exit;
}

// Verify the token with Firebase, so email/uid can never be forged by the client
$ch = curl_init('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode(FIREBASE_API_KEY));
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['idToken' => $idToken]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$raw  = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

if ($raw === false) {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'Server could not reach Firebase to verify sign-in (' . $cerr . '). Your host may block outgoing connections.']);
    exit;
}
$info = json_decode($raw, true);
$fu   = $info['users'][0] ?? null;
if ($code !== 200 || !$fu || empty($fu['email'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Firebase sign-in could not be verified. Please try again.']);
    exit;
}

$email = strtolower(trim($fu['email']));
$uid   = $fu['localId'];
if (!$name) $name = trim($fu['displayName'] ?? '');


if (!$email) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email address is required from Firebase Auth provider.']);
    exit;
}

if (!$name) {
    $parts = explode('@', $email);
    $name = ucfirst($parts[0]);
}

$pdo = db();

// Search for user by email or firebase_uid
$stmt = $pdo->prepare('SELECT * FROM users WHERE email=? OR (firebase_uid IS NOT NULL AND firebase_uid=?)');
$stmt->execute([$email, $uid]);
$user = $stmt->fetch();

if (!$user) {
    if ($mode === 'login') {
        echo json_encode([
            'status' => 'not_found', 
            'message' => 'No SURYAMITHRA account found for ' . $email . '. Redirecting to complete registration...',
            'redirect' => url('signup.php?email=' . urlencode($email) . '&name=' . urlencode($name))
        ]);
        exit;
    }

    // Register new user from Firebase Auth (SIGNUP MODE)
    try {
        $hash = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
        $role = in_array($reqRole, ['student', 'faculty', 'hod', 'placement']) ? $reqRole : 'student';

        $uStmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, department, firebase_uid) VALUES (?,?,?,?,?,?)');
        $uStmt->execute([$name, $email, $hash, $role, $reqDept, $uid ?: null]);
        $userId = (int)$pdo->lastInsertId();

        if ($role === 'student') {
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
                ->execute([$userId, $assignedTeacherId, $code, $name, $email, $reqDept, $reqClassName, $reqSem, "", "", ""]);
            $sid = (int)$pdo->lastInsertId();

            // Populate baseline empty academic & placement data
            $pdo->prepare('INSERT INTO academic_records VALUES (?,?,?,?)')->execute([$sid, 0.0, 0, 0.0]);
            $pdo->prepare('INSERT INTO attendance VALUES (?,?)')->execute([$sid, 0.0]);
            $pdo->prepare('INSERT INTO lms_activity VALUES (?,?,?)')->execute([$sid, 0, 0.0]);
            $pdo->prepare('INSERT INTO engagement VALUES (?,?,?,?,?)')->execute([$sid, 0, 0, 0, 0]);
            $pdo->prepare('INSERT INTO placement VALUES (?,?,?,?,?,?,?)')->execute([$sid, 0.0, 0.0, 0.0, 0.0, 0, $reqRoleId]);
            $pdo->prepare('INSERT INTO feedback VALUES (?,?,?,?)')->execute([$sid, 0.0, 0.0, "Authenticated via Firebase Auth ($provider)"]);

            notify($userId, "Welcome to SURYAMITHRA (Firebase Auth)", "Your account was successfully registered as a Student ($reqDept - $reqClassName) using Firebase Authentication.", "good");
        } else {
            notify($userId, "Welcome to SURYAMITHRA", "Your staff account ($role - $reqDept) was created via Firebase Auth.", "info");
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        audit('firebase_signup', "$email ($provider)");
    } catch (PDOException $ex) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create user account: ' . $ex->getMessage()]);
        exit;
    }
} else {
    // Update firebase_uid if missing
    if ($uid && empty($user['firebase_uid'])) {
        $pdo->prepare('UPDATE users SET firebase_uid=? WHERE id=?')->execute([$uid, $user['id']]);
    }
    audit('firebase_login', "$email ($provider)");
}

// Log user in
session_regenerate_id(true);
$_SESSION['user'] = [
    'id' => (int)$user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'],
    'department' => $user['department'] ?? 'CSE'
];

$pdo->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$user['id']]);

$redirectUrl = url($user['role'] === 'student' ? 'student.php' : 'dashboard.php');

flash("Signed in successfully via Firebase Authentication (" . e(ucfirst($provider)) . ")!");

echo json_encode([
    'status' => 'success',
    'message' => 'Firebase Auth successful',
    'redirect' => $redirectUrl,
    'user' => $_SESSION['user']
]);
