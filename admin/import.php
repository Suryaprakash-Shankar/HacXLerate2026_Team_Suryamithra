<?php
$pageTitle = 'Import student data'; $pageSub = 'Upload one CSV to add or update students across all 7 data sources';
require_once __DIR__ . '/../includes/functions.php';
$u = require_role(['admin']);
$report = null;
$num = fn($v, $d = 0) => is_numeric($v) ? (float)$v : $d;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    csrf_check();
    $pdo = db(); $added = 0; $updated = 0; $errors = [];
    if ($_FILES['csv']['error'] !== UPLOAD_ERR_OK) { flash('Upload failed. Choose a .csv file and try again.', 'warn'); redirect('admin/import.php'); }
    $fh = fopen($_FILES['csv']['tmp_name'], 'r');
    $head = fgetcsv($fh);
    if ($head) $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
    $head = array_map('trim', $head ?: []);
    $roles = []; foreach ($pdo->query('SELECT id,name FROM job_roles')->fetchAll() as $r) $roles[strtolower($r['name'])] = $r['id'];
    $hash = password_hash('password', PASSWORD_DEFAULT);
    $pdo->beginTransaction();
    try {
        $line = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            if (count($row) < 2) continue;
            $d = array_combine($head, array_pad(array_slice($row, 0, count($head)), count($head), ''));
            $code = trim($d['student_code'] ?? ''); $name = trim($d['name'] ?? '');
            if ($code === '' || $name === '') { $errors[] = "Row $line: student_code and name are required."; continue; }
            $q = $pdo->prepare('SELECT id,user_id FROM students WHERE student_code=?'); $q->execute([$code]); $ex = $q->fetch();
            $email = trim($d['email'] ?? '');
            $uid = $ex['user_id'] ?? null;
            if (!$uid && $email !== '') {
                $f = $pdo->prepare('SELECT id FROM users WHERE email=?'); $f->execute([$email]); $uid = $f->fetchColumn() ?: null;
                if (!$uid) { $pdo->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)')->execute([$name, $email, $hash, 'student']); $uid = (int)$pdo->lastInsertId(); }
            }
            if ($ex) {
                $sid = (int)$ex['id'];
                $pdo->prepare('UPDATE students SET user_id=?, name=?, email=?, department=?, semester=? WHERE id=?')->execute([$uid, $name, $email ?: null, $d['department'] ?: 'General', (int)$num($d['semester'] ?? 5, 5), $sid]);
                $updated++;
            } else {
                $pdo->prepare('INSERT INTO students (user_id,student_code,name,email,department,semester) VALUES (?,?,?,?,?,?)')->execute([$uid, $code, $name, $email ?: null, $d['department'] ?: 'General', (int)$num($d['semester'] ?? 5, 5)]);
                $sid = (int)$pdo->lastInsertId(); $added++;
            }
            $pdo->prepare('REPLACE INTO academic_records VALUES (?,?,?,?)')->execute([$sid, min(10, $num($d['cgpa'] ?? 0)), (int)$num($d['backlogs'] ?? 0), min(100, $num($d['internal_avg'] ?? 0))]);
            $pdo->prepare('REPLACE INTO attendance VALUES (?,?)')->execute([$sid, min(100, $num($d['attendance'] ?? 0))]);
            $pdo->prepare('REPLACE INTO lms_activity VALUES (?,?,?)')->execute([$sid, (int)$num($d['login_freq'] ?? 0), min(100, $num($d['assignment_completion'] ?? 0))]);
            $pdo->prepare('REPLACE INTO engagement VALUES (?,?,?,?,?)')->execute([$sid, (int)$num($d['events'] ?? 0), (int)$num($d['clubs'] ?? 0), (int)$num($d['hackathons'] ?? 0), (int)$num($d['certifications'] ?? 0)]);
            $rid = $roles[strtolower(trim($d['target_role'] ?? ''))] ?? null;
            $pdo->prepare('REPLACE INTO placement VALUES (?,?,?,?,?,?,?)')->execute([$sid, min(100, $num($d['aptitude'] ?? 0)), min(100, $num($d['coding'] ?? 0)), min(100, $num($d['mock_interview'] ?? 0)), min(100, $num($d['resume'] ?? 0)), (int)$num($d['applications'] ?? 0), $rid]);
            $pdo->prepare('REPLACE INTO feedback VALUES (?,?,?)')->execute([$sid, min(5, $num($d['satisfaction'] ?? 3, 3)), min(5, $num($d['faculty_rating'] ?? 3, 3))]);
            foreach ($d as $col => $val) {
                if (strpos($col, 'skill_') === 0 && is_numeric($val)) $pdo->prepare('REPLACE INTO student_skills VALUES (?,?,?)')->execute([$sid, substr($col, 6), max(0, min(100, (int)$val))]);
            }
        }
        $pdo->commit();
    } catch (Throwable $ex) { $pdo->rollBack(); $errors[] = 'Import stopped: ' . $ex->getMessage(); }
    fclose($fh);
    audit('import', "added $added, updated $updated");
    $report = [$added, $updated, $errors];
}
include __DIR__ . '/../includes/header.php';
?>
<?php if ($report): ?><div class="card"><h3>Import finished</h3><p><strong><?= $report[0] ?></strong> students added · <strong><?= $report[1] ?></strong> updated · <strong><?= count($report[2]) ?></strong> rows skipped.</p>
  <?php if ($report[2]): ?><ul><?php foreach (array_slice($report[2], 0, 15) as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <a class="btn primary sm" href="<?= url('dashboard.php') ?>">See dashboard</a></div><?php endif; ?>
<div class="grid g2">
  <div class="card"><h3>Upload CSV</h3><p class="sub">New students get a login (password: <code>password</code>). Existing student IDs are updated.</p>
    <form method="post" enctype="multipart/form-data" class="lf" style="max-width:none"><?= csrf_field() ?>
      <label>CSV file<input type="file" name="csv" accept=".csv" required></label><button class="btn primary">Import students</button></form>
    <p style="margin-top:16px"><a class="btn sm" href="<?= url('uploads/student_template.csv') ?>" download>Download template</a></p></div>
  <div class="card"><h3>Columns</h3><p class="sub">One row per student</p>
    <p><strong>Student:</strong> student_code, name, email, department, semester<br><strong>Academic:</strong> cgpa, backlogs, internal_avg<br><strong>Attendance:</strong> attendance<br><strong>LMS:</strong> login_freq, assignment_completion<br>
    <strong>Engagement:</strong> events, clubs, hackathons, certifications<br><strong>Placement:</strong> aptitude, coding, mock_interview, resume, applications, target_role<br><strong>Feedback:</strong> satisfaction, faculty_rating (1–5)<br>
    <strong>Skills:</strong> any column named skill_&lt;name&gt;, such as skill_Java or skill_Spring Boot</p></div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
