<?php
require_once __DIR__ . '/includes/functions.php';
$u = require_login();
$isStaff = in_array($u['role'], ['admin', 'hod', 'faculty', 'placement']);

if ($u['role'] === 'student') {
    $s = student_by_user($u['id']);
} else {
    $id = (int)($_GET['id'] ?? 0);
    if (!can_teacher_access_student($u, $id)) {
        flash('Access denied: You can only view students in your department or assigned subject classes.', 'warn');
        redirect('students.php');
    }
    $r = $id ? load_students($id) : [];
    $s = $r[0] ?? null;
    if (!$s) redirect('students.php');
}

if (!$s) { 
    $pageTitle = 'My profile'; 
    include __DIR__ . '/includes/header.php'; 
    echo '<div class="card">No student record is linked to this account.</div>'; 
    include __DIR__ . '/includes/footer.php'; 
    exit; 
}

// Handle Form Submissions (Student Profile Edit or Staff Actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';

    // Student updates their own Profile (Projects, Bio, Interests)
    if ($act === 'update_profile' && ($u['role'] === 'student' || $isStaff)) {
        $bio = trim($_POST['bio'] ?? '');
        $interests = trim($_POST['interests'] ?? '');
        $projects = trim($_POST['projects'] ?? '');
        $satisfaction = (float)($_POST['satisfaction'] ?? 4.0);

        $pdo = db();
        $pdo->prepare('UPDATE students SET bio=?, interests=?, projects=? WHERE id=?')->execute([$bio, $interests, $projects, $s['id']]);
        $pdo->prepare('UPDATE feedback SET satisfaction=? WHERE student_id=?')->execute([$satisfaction, $s['id']]);

        // Target Job Role update
        if (!empty($_POST['role_id'])) {
            $roleId = (int)$_POST['role_id'];
            $pdo->prepare('UPDATE placement SET role_id=? WHERE student_id=?')->execute([$roleId, $s['id']]);
        }

        // Skill updates allowed ONLY for authorized Staff (Teachers / HOD / Admin)
        if ($isStaff && !empty($_POST['skill_level']) && is_array($_POST['skill_level'])) {
            $skStmt = $pdo->prepare('INSERT INTO student_skills (student_id, skill, level, category) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE level=?');
            foreach ($_POST['skill_level'] as $skName => $levelVal) {
                $lv = max(0, min(100, (int)$levelVal));
                $cat = in_array($skName, ['Communication', 'Problem Solving', 'Teamwork', 'Leadership', 'Time Management', 'Critical Thinking']) ? 'soft' : 'technical';
                $skStmt->execute([$s['id'], $skName, $lv, $cat, $lv]);
            }
        }

        audit('update_profile', $s['student_code'] . ' profile updated');
        flash('Profile updated successfully.');
        redirect($u['role'] === 'student' ? 'student.php' : 'student.php?id=' . $s['id']);
    }

    // Staff actions: Notifications, Interventions, Score updates
    if ($isStaff) {
        if ($act === 'notify' && $s['user_id']) {
            $title = trim($_POST['title'] ?? ''); $body = trim($_POST['body'] ?? '');
            if ($title && $body) { 
                notify((int)$s['user_id'], $title, $body, $_POST['type'] ?? 'info'); 
                audit('notify', $s['student_code'] . ': ' . $title); 
                flash('Notification sent to ' . $s['name'] . '.'); 
            }
        } elseif ($act === 'intervene' && in_array($u['role'], ['admin', 'hod', 'faculty'])) {
            db()->prepare('INSERT INTO interventions (student_id,type,mentor,priority,status,notes,created_by) VALUES (?,?,?,?,?,?,?)')
                ->execute([$s['id'], $_POST['type'], $u['name'], $_POST['priority'] ?? 'Medium', 'Planned', $_POST['notes'] ?? '', $u['id']]);
            if ($s['user_id']) notify((int)$s['user_id'], 'New support plan: ' . $_POST['type'], 'Your mentor ' . $u['name'] . ' has planned "' . $_POST['type'] . '" for you. Check with them for the next step.', 'info');
            audit('intervention', $s['student_code'] . ': ' . $_POST['type']); 
            flash('Intervention planned and student notified.');
        } elseif ($act === 'gap_notify' && $s['user_id']) {
            $pri = array_filter($s['gaps'], fn($g) => $g['gap'] > 0);
            $lines = array_map(fn($g) => $g['skill'] . ' (' . $g['current'] . ' → ' . $g['required'] . ')', array_slice($pri, 0, 4));
            notify((int)$s['user_id'], 'Skill gaps for ' . ($s['role_name'] ?: 'your target role'), 'Focus on: ' . implode(', ', $lines) . '.', 'warn');
            audit('notify_gaps', $s['student_code']); 
            flash('Skill gap notification sent.');
        } elseif ($act === 'staff_update_scores') {
            $cgpa = (float)$_POST['cgpa'];
            $backlogs = (int)$_POST['backlogs'];
            $internal = (float)$_POST['internal_avg'];
            $att = (float)$_POST['attendance'];
            $apt = (float)$_POST['aptitude'];
            $coding = (float)$_POST['coding'];
            $mock = (float)$_POST['mock_interview'];
            $facRating = (float)$_POST['faculty_rating'];

            $pdo = db();
            $pdo->prepare('UPDATE academic_records SET cgpa=?, backlogs=?, internal_avg=? WHERE student_id=?')->execute([$cgpa, $backlogs, $internal, $s['id']]);
            $pdo->prepare('UPDATE attendance SET overall_pct=? WHERE student_id=?')->execute([$att, $s['id']]);
            $pdo->prepare('UPDATE placement SET aptitude=?, coding=?, mock_interview=? WHERE student_id=?')->execute([$apt, $coding, $mock, $s['id']]);
            $pdo->prepare('UPDATE feedback SET faculty_rating=? WHERE student_id=?')->execute([$facRating, $s['id']]);

            audit('staff_update_scores', "Updated scores for " . $s['student_code']);
            flash("Academic & Placement metrics updated for " . $s['name'] . ".");
        }
        redirect('student.php?id=' . $s['id']);
    }
}

$pageTitle = $u['role'] === 'student' ? 'My success profile' : $s['name'];
$pageSub = $s['student_code'] . ' · ' . $s['department'] . ' (' . ($s['class_name'] ?: 'Class A') . ') · Semester ' . $s['semester'] . ($s['teacher_name'] ? ' · Class Tutor: ' . $s['teacher_name'] : '');
$iv = db()->prepare('SELECT * FROM interventions WHERE student_id=? ORDER BY created_at DESC'); 
$iv->execute([$s['id']]); 
$ivs = $iv->fetchAll();
$recs = recommendations($s, $s['c'], $s['risk']);
$subjectMarks = load_subject_marks($s['id']);
$periodAttendance = load_period_attendance($s['id']);
$jobRoles = db()->query("SELECT id, name FROM job_roles ORDER BY name")->fetchAll();

include __DIR__ . '/includes/header.php';
$riskLabels = ['academic' => 'Academic', 'attendance' => 'Attendance', 'placement' => 'Placement', 'lms' => 'LMS', 'skills' => 'Skills', 'overall' => 'Overall'];
?>

<div class="card hero-card">
  <div class="ring" style="--p:<?= $s['score'] ?>;--c:<?= score_color($s['score']) ?>"><b><?= f0($s['score']) ?></b><small>/100</small></div>
  <div>
    <div class="row"><h2><?= e($s['name']) ?></h2><?= risk_pill($s['risk']['overall']) ?> <?= seg_pill($s) ?></div>
    <p class="muted" style="margin:4px 0 0">Success rating: <strong style="color:var(--text)"><?= e($s['rating']) ?></strong> · Class: <strong style="color:var(--text)"><?= e($s['class_name'] ?: 'Class A') ?></strong> · Target role: <strong style="color:var(--text)"><?= e($s['role_name'] ?: 'Not set') ?></strong></p>
    <div class="facts">
      <div><small>CGPA</small><strong><?= number_format($s['cgpa'], 1) ?></strong></div>
      <div><small>Backlogs</small><strong><?= $s['backlogs'] ?></strong></div>
      <div><small>Attendance</small><strong><?= f0($s['attendance']) ?>%</strong></div>
      <div><small>Coding score</small><strong><?= f0($s['coding']) ?></strong></div>
      <div><small>Aptitude score</small><strong><?= f0($s['aptitude']) ?></strong></div>
      <div><small>Mock interview</small><strong><?= f0($s['mock_interview']) ?></strong></div>
      <div><small>Placement readiness</small><strong><?= f0($s['c']['placement']) ?></strong></div>
    </div>
  </div>
</div>

<!-- Subject-wise Academic Marks & Progress Tracking (Entered by Class Tutor & Subject Staff) -->
<div class="card">
  <div class="row spread">
    <div>
      <h3>Subject Marks & Academic Progress</h3>
      <p class="sub">Detailed internal and semester examination marks entered by Class Tutor & Subject Staff.</p>
    </div>
    <div>
      <span class="pill blue">Class: <?= e($s['class_name'] ?: 'Class A') ?></span>
      <span class="pill green" style="margin-left:4px">Internal Avg: <?= f1($s['internal_avg']) ?>%</span>
    </div>
  </div>

  <div class="scroll" style="margin-top: 12px;">
    <table class="tbl">
      <thead>
        <tr>
          <th>Subject Code</th>
          <th>Subject Name</th>
          <th>Class Tutor / Staff</th>
          <th>Internal Mark (50)</th>
          <th>Exam Mark (100)</th>
          <th>Total (40% Int + 60% Exam)</th>
          <th>Grade</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subjectMarks as $m): 
          $gColor = in_array($m['grade'], ['A+', 'A']) ? 'green' : (in_array($m['grade'], ['B', 'C']) ? 'blue' : ($m['grade'] === 'D' ? 'amber' : 'red'));
        ?>
          <tr>
            <td><code><?= e($m['subject_code']) ?></code></td>
            <td><strong><?= e($m['subject_name']) ?></strong></td>
            <td><?= e($m['staff_name'] ?: 'Class Tutor / Faculty') ?></td>
            <td><strong><?= number_format($m['internal_mark'], 1) ?></strong> / 50</td>
            <td><strong><?= number_format($m['exam_mark'], 1) ?></strong> / 100</td>
            <td>
              <div class="scorebar">
                <span><?= number_format($m['total_pct'], 1) ?>%</span>
                <i><b style="width:<?= $m['total_pct'] ?>%;background:<?= score_color($m['total_pct']) ?>"></b></i>
              </div>
            </td>
            <td><span class="pill <?= $gColor ?>"><?= e($m['grade']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Period-wise Attendance Log (Uploaded by Subject Staff) -->
<div class="card">
  <h3>Subject Period Attendance Log</h3>
  <p class="sub">Period-by-period attendance uploaded by subject teachers.</p>
  <div class="scroll" style="margin-top: 10px;">
    <table class="tbl">
      <thead>
        <tr>
          <th>Date</th>
          <th>Subject Code</th>
          <th>Period Number</th>
          <th>Staff / Teacher</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($periodAttendance as $pa): 
          $pColor = $pa['status'] === 'Present' ? 'green' : ($pa['status'] === 'Late' ? 'amber' : 'red');
        ?>
          <tr>
            <td><?= e($pa['date']) ?></td>
            <td><code><?= e($pa['subject_code']) ?></code></td>
            <td>Period <?= (int)$pa['period_number'] ?></td>
            <td><?= e($pa['staff_name'] ?: 'Subject Staff') ?></td>
            <td><span class="pill <?= $pColor ?>"><?= e($pa['status']) ?></span></td>
          </tr>
        <?php endforeach; if (!$periodAttendance): ?>
          <tr><td colspan="5" class="muted" style="text-align:center">No period attendance recorded yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Bio & Overview -->
<div class="card">
  <h3>Student Bio & Project Portfolio</h3>
  <p class="sub">Self-reported profile details, interests and project highlights</p>
  <div class="grid g3">
    <div><strong>Bio:</strong> <p class="muted"><?= e($s['bio'] ?: 'No bio added yet.') ?></p></div>
    <div><strong>Interests:</strong> <p class="muted"><?= e($s['interests'] ?: 'No interests specified.') ?></p></div>
    <div><strong>Projects:</strong> <p class="muted"><?= e($s['projects'] ?: 'No projects listed.') ?></p></div>
  </div>
</div>

<div class="grid g2">
  <div class="card">
    <h3>Why this score? (Explainable Score Breakdown)</h3>
    <p class="sub">Share of this student's risk coming from each indicator</p>
    <div class="why">
    <?php foreach ($s['explain'] as $x): ?>
      <div class="r"><span><?= e($x['label']) ?></span><i><b style="width:<?= $x['risk_share'] ?>%"></b></i><em><?= f0($x['risk_share']) ?>%</em></div>
    <?php endforeach; ?></div>
    <p class="sub" style="margin:14px 0 0">Score = academic 30% + attendance 15% + LMS 10% + engagement 10% + placement 20% + skills 10% + feedback 5%.</p>
  </div>
  <div class="card">
    <h3>Indicator profile</h3>
    <p class="sub">Each indicator on a 0–100 scale</p>
    <div class="chartbox"><canvas id="cRadar"></canvas></div>
  </div>
</div>

<div class="grid g2">
  <div class="card">
    <h3>Risk flags</h3>
    <p class="sub">Per-area risk level</p>
    <div class="riskgrid"><?php foreach ($riskLabels as $k => $l): ?><div><span><?= $l ?></span><?= risk_pill($s['risk'][$k]) ?></div><?php endforeach; ?></div>
  </div>
  <div class="card">
    <h3>Score contribution</h3>
    <p class="sub">Points earned out of the maximum for each indicator</p>
    <div class="chartbox sm"><canvas id="cPts"></canvas></div>
  </div>
</div>

<!-- AI Learning Copilot Card -->
<div class="card" style="background: linear-gradient(135deg, #0d1130 0%, #161b45 100%); color:#fff; border:0; margin-bottom:20px;">
  <div class="row spread">
    <div>
      <h3 style="color:#fff; font-size:18px; margin-bottom:4px;">🤖 AI Content Tutor & Practice Quiz Generator</h3>
      <p style="color:#aab0e0; margin:0; font-size:13.5px;">Master weak academic topics, solve interactive practice quizzes, and prepare for technical & HR interviews.</p>
    </div>
    <a class="btn primary" href="<?= url('ai_tutor.php' . ($u['role'] !== 'student' ? '?student_id=' . $s['id'] : '')) ?>" style="white-space:nowrap;">Open AI Student Tutor</a>
  </div>
</div>

<!-- Skill Gaps against Target Role -->
<div class="card">
  <div class="row spread">
    <div>
      <h3>Skill gap: <?= e($s['role_name'] ?: 'no target role') ?></h3>
      <p class="sub">Current level against the level the target job role requires. Dark marker represents job requirement.</p>
    </div>
    <?php if ($isStaff && $s['user_id']): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="gap_notify"><button class="btn sm primary">Notify student of gaps</button></form>
    <?php endif; ?>
  </div>
  <?php foreach ($s['gaps'] as $g): 
      $col = $g['priority'] === 'Priority' ? 'var(--red)' : ($g['priority'] === 'Moderate' ? 'var(--amber)' : ($g['priority'] === 'Minor' ? 'var(--primary2)' : 'var(--green)')); ?>
    <div class="gap">
      <div><strong><?= e($g['skill']) ?></strong><small>Need <?= $g['required'] ?> · Have <?= $g['current'] ?></small></div>
      <div class="bar"><b style="width:<?= $g['current'] ?>%;background:<?= $col ?>"></b><u style="left:<?= $g['required'] ?>%"></u></div>
      <div><?= $g['gap'] > 0 ? '<span class="pill ' . ($g['priority'] === 'Priority' ? 'red' : ($g['priority'] === 'Moderate' ? 'amber' : 'blue')) . '">Gap ' . $g['gap'] . ' · ' . $g['priority'] . '</span>' : '<span class="pill green">Met</span>' ?></div>
    </div>
  <?php endforeach; if (!$s['gaps']): ?><p class="muted">Set a target role to see skill gaps.</p><?php endif; ?>
</div>

<!-- Student Profile Editor -->
<div class="card">
  <h3>Update Profile Details</h3>
  <p class="sub">Students can update personal bio summary, areas of interest, and project highlights.</p>
  <form method="post" class="formgrid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_profile">
    <label style="grid-column: 1/-1">Bio & Summary<textarea name="bio" rows="2" placeholder="Describe your technical background and career aspiration"><?= e($s['bio']) ?></textarea></label>
    <label style="grid-column: 1/-1">Areas of Interest<input name="interests" value="<?= e($s['interests']) ?>" placeholder="e.g. Machine Learning, Cloud Architecture, Full Stack"></label>
    <label style="grid-column: 1/-1">Projects & Certifications<textarea name="projects" rows="2" placeholder="e.g. Built E-commerce REST API, AWS Cloud Practitioner certified"><?= e($s['projects']) ?></textarea></label>
    <label>Target Job Role
      <select name="role_id">
        <?php foreach ($jobRoles as $jr): ?>
          <option value="<?= $jr['id'] ?>" <?= ($s['role_id'] == $jr['id']) ? 'selected' : '' ?>><?= e($jr['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Student Satisfaction Rating (1-5)<input type="number" step="0.1" min="1" max="5" name="satisfaction" value="<?= e($s['satisfaction']) ?>" required></label>
    
    <?php if ($isStaff): ?>
    <div style="grid-column: 1/-1; margin-top: 10px;">
      <h4 style="margin-bottom: 8px">Faculty / Staff Control: Technical & Soft Skill Levels (0–100)</h4>
      <div class="formgrid">
        <?php foreach ($s['skillmap'] as $skName => $skLvl): ?>
          <label><?= e($skName) ?>
            <input type="number" name="skill_level[<?= e($skName) ?>]" min="0" max="100" value="<?= (int)$skLvl ?>">
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    
    <button class="btn primary" style="grid-column: 1/-1; margin-top: 14px">Save Profile Details</button>
  </form>
</div>

<!-- Teacher/Staff Academic & Placement Metric Editor -->
<?php if ($isStaff): ?>
<div class="card">
  <h3>Teacher / Faculty Controls: Update GPA, CGPA & Placement Scores</h3>
  <p class="sub">Authorized staff can directly update student academic standing and placement assessment scores.</p>
  <form method="post" class="formgrid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="staff_update_scores">
    <label>CGPA<input type="number" step="0.01" min="0" max="10" name="cgpa" value="<?= $s['cgpa'] ?>" required></label>
    <label>Internal Avg<input type="number" step="0.1" min="0" max="100" name="internal_avg" value="<?= $s['internal_avg'] ?>" required></label>
    <label>Backlogs<input type="number" min="0" max="10" name="backlogs" value="<?= $s['backlogs'] ?>" required></label>
    <label>Attendance %<input type="number" step="0.1" min="0" max="100" name="attendance" value="<?= $s['attendance'] ?>" required></label>
    <label>Aptitude Score<input type="number" step="0.1" min="0" max="100" name="aptitude" value="<?= $s['aptitude'] ?>" required></label>
    <label>Coding Score<input type="number" step="0.1" min="0" max="100" name="coding" value="<?= $s['coding'] ?>" required></label>
    <label>Mock Interview Score<input type="number" step="0.1" min="0" max="100" name="mock_interview" value="<?= $s['mock_interview'] ?>" required></label>
    <label>Faculty Rating (1-5)<input type="number" step="0.1" min="1" max="5" name="faculty_rating" value="<?= $s['faculty_rating'] ?>" required></label>
    <button class="btn primary" style="grid-column: 1/-1">Save Staff Evaluation Scores</button>
  </form>
</div>
<?php endif; ?>

<div class="grid g2">
  <div class="card">
    <h3>Recommended actions</h3>
    <p class="sub">Generated from this student's risk flags</p>
    <?php foreach ($recs as $i => [$t, $d]): ?>
      <div class="rec"><span class="n"><?= $i + 1 ?></span><div><strong><?= e($t) ?></strong><span><?= e($d) ?></span></div></div>
    <?php endforeach; ?>
  </div>
  <div class="card">
    <h3>Intervention tracking</h3>
    <p class="sub">Prediction → intervention → outcome</p>
    <?php foreach ($ivs as $x): ?>
      <div class="rec"><span class="n">•</span><div><strong><?= e($x['type']) ?> <span class="pill <?= $x['status'] === 'Completed' ? 'green' : ($x['status'] === 'In Progress' ? 'blue' : 'slate') ?>"><?= e($x['status']) ?></span></strong>
      <span>Mentor <?= e($x['mentor']) ?> · <?= e($x['priority']) ?> priority<?= $x['outcome'] ? ' · Outcome: ' . e($x['outcome']) : '' ?></span></div></div>
    <?php endforeach; if (!$ivs): ?><p class="muted">No interventions yet.</p><?php endif; ?>
    
    <?php if (in_array($u['role'], ['admin', 'hod', 'faculty'])): ?>
    <form method="post" class="formgrid" style="margin-top:16px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="intervene">
      <label>Intervention<select name="type"><?php foreach ($recs as [$t, $d]): ?><option><?= e($t) ?></option><?php endforeach; ?></select></label>
      <label>Priority<select name="priority"><option>High</option><option selected>Medium</option><option>Low</option></select></label>
      <label style="grid-column:1/-1">Notes<input name="notes" placeholder="Optional note for the mentor"></label>
      <button class="btn primary">Plan intervention</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($isStaff && $s['user_id']): ?>
<div class="card">
  <h3>Send a notification</h3>
  <p class="sub">The student sees it in their notification centre</p>
  <form method="post" class="formgrid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="notify">
    <label>Title<input name="title" required placeholder="e.g. Meet your mentor on Monday"></label>
    <label>Type<select name="type"><option value="info">Info</option><option value="warn">Warning</option><option value="alert">Urgent</option><option value="good">Good news</option></select></label>
    <label style="grid-column:1/-1">Message<textarea name="body" rows="2" required></textarea></label>
    <button class="btn primary">Send notification</button>
  </form>
</div>
<?php endif; ?>

<script>
CIQ.chart('cRadar', {type: 'radar', data: {labels: <?= json_encode(array_values(LABELS)) ?>, datasets: [{data: <?= json_encode(array_values($s['c'])) ?>, backgroundColor: 'rgba(91,75,255,.18)', borderColor: CIQ.c.primary, pointBackgroundColor: CIQ.c.primary}]},
  options: {plugins: {legend: {display: false}}, scales: {r: {min: 0, max: 100, ticks: {display: false}, grid: {color: CIQ.c.line}}}}});
<?php $pts = $s['explain']; ?>
CIQ.chart('cPts', {type: 'bar', data: {labels: <?= json_encode(array_column($pts, 'label')) ?>, datasets: [
  {label: 'Earned', data: <?= json_encode(array_column($pts, 'points')) ?>, backgroundColor: CIQ.c.primary, borderRadius: 6},
  {label: 'Maximum', data: <?= json_encode(array_column($pts, 'max')) ?>, backgroundColor: '#dfe1f3', borderRadius: 6}]},
  options: {plugins: {legend: {position: 'bottom'}}, scales: {y: {grid: CIQ.grid}, x: {grid: {display: false}, ticks: {font: {size: 10}}}}}});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
