<?php
$pageTitle = 'Staff hierarchy & student management'; 
$pageSub = 'HOD adds teachers, teachers manage and add students, update academic & placement scores';
require_once __DIR__ . '/includes/functions.php';
$u = require_role(['admin', 'hod', 'faculty']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Admin adds a New Department
    if ($action === 'add_department' && $u['role'] === 'admin') {
        $deptName = trim($_POST['department_name'] ?? '');
        $deptCode = trim($_POST['department_code'] ?? '');

        if ($deptName) {
            if (add_department($deptName, $deptCode)) {
                audit('add_department', "Added department $deptName ($deptCode)");
                flash("New department '$deptName' added successfully!");
            } else {
                flash("Could not add department '$deptName'.", 'warn');
            }
        }
        redirect('hierarchy.php');
    }

    // Admin adds a Department HOD
    if ($action === 'add_hod' && $u['role'] === 'admin') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $dept = trim($_POST['department'] ?? 'CSE');
        $pass = $_POST['password'] ?? 'password';

        if ($name && $email && $dept) {
            try {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                db()->prepare('INSERT INTO users (name, email, password_hash, role, department, added_by) VALUES (?,?,?,?,?,?)')
                    ->execute([$name, $email, $hash, 'hod', $dept, $u['id']]);
                audit('add_hod', "HOD $name ($email) added for $dept");
                flash("HOD $name added successfully for $dept department.");
            } catch (PDOException $ex) {
                flash("Could not add HOD: Email $email may already exist.", 'warn');
            }
        }
        redirect('hierarchy.php');
    }

    // HOD or Admin adds a Teacher/Faculty
    if ($action === 'add_teacher' && in_array($u['role'], ['admin', 'hod'])) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $dept = trim($_POST['department'] ?? ($u['department'] ?: 'CSE'));
        $pass = $_POST['password'] ?? 'password';

        if ($name && $email) {
            try {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                db()->prepare('INSERT INTO users (name, email, password_hash, role, department, added_by) VALUES (?,?,?,?,?,?)')
                    ->execute([$name, $email, $hash, 'faculty', $dept, $u['id']]);
                audit('add_teacher', "$name ($email) under $dept");
                flash("Teacher $name added successfully to $dept department.");
            } catch (PDOException $ex) {
                flash("Could not add teacher: Email $email may already exist.", 'warn');
            }
        }
        redirect('hierarchy.php');
    }

    // Class Tutor, HOD or Admin adds a Student
    if ($action === 'add_student' && in_array($u['role'], ['admin', 'hod', 'faculty'])) {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['student_code'] ?? '');
        $email = trim($_POST['email'] ?? strtolower($code) . '@suryamithra.local');
        $dept = trim($_POST['department'] ?? ($u['department'] ?: 'CSE'));
        $className = trim($_POST['class_name'] ?? 'Class A');
        $sem = (int)($_POST['semester'] ?? 5);
        $teacherId = (int)($_POST['teacher_id'] ?? ($u['role'] === 'faculty' ? $u['id'] : 0));
        $roleId = (int)($_POST['role_id'] ?? 1);
        $pass = trim($_POST['password'] ?? 'password');

        if ($name && $code) {
            try {
                $pdo = db();
                $hash = password_hash($pass ?: 'password', PASSWORD_DEFAULT);
                
                // Create user login for student
                $pdo->prepare('INSERT INTO users (name, email, password_hash, role, department, added_by) VALUES (?,?,?,?,?,?)')
                    ->execute([$name, $email, $hash, 'student', $dept, $teacherId ?: $u['id']]);
                $uid = (int)$pdo->lastInsertId();

                // Create student record
                $pdo->prepare('INSERT INTO students (user_id, teacher_id, student_code, name, email, department, class_name, semester, bio, interests, projects) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$uid, $teacherId ?: null, $code, $name, $email, $dept, $className, $sem, '', '', '']);
                $sid = (int)$pdo->lastInsertId();

                // Clean empty initial scores
                $cgpa = (float)($_POST['cgpa'] ?? 0.0);
                $backlogs = (int)($_POST['backlogs'] ?? 0);
                $internal = (float)($_POST['internal_avg'] ?? 0.0);
                $att = (float)($_POST['attendance'] ?? 0.0);
                $apt = (float)($_POST['aptitude'] ?? 0.0);
                $coding = (float)($_POST['coding'] ?? 0.0);
                $mock = (float)($_POST['mock_interview'] ?? 0.0);

                $pdo->prepare('INSERT INTO academic_records VALUES (?,?,?,?)')->execute([$sid, $cgpa, $backlogs, $internal]);
                $pdo->prepare('INSERT INTO attendance VALUES (?,?)')->execute([$sid, $att]);
                $pdo->prepare('INSERT INTO lms_activity VALUES (?,?,?)')->execute([$sid, 0, 0.0]);
                $pdo->prepare('INSERT INTO engagement VALUES (?,?,?,?,?)')->execute([$sid, 0, 0, 0, 0]);
                $pdo->prepare('INSERT INTO placement VALUES (?,?,?,?,?,?,?)')->execute([$sid, $apt, $coding, $mock, 0.0, 0, $roleId]);
                $pdo->prepare('INSERT INTO feedback VALUES (?,?,?,?)')->execute([$sid, 0.0, 0.0, 'Student profile created']);

                audit('add_student', "$name ($code) added");
                flash("Student $name ($code) added successfully.");
            } catch (PDOException $ex) {
                flash("Could not add student: Code or Email already exists.", 'warn');
            }
        }
        redirect('hierarchy.php');
    }

    // HOD or Admin assigns Class Teacher / Tutor for Class & Department
    if ($action === 'assign_class_teacher' && in_array($u['role'], ['admin', 'hod'])) {
        $dept = trim($_POST['department'] ?? ($u['department'] ?: 'CSE'));
        $className = trim($_POST['class_name'] ?? 'Class A');
        $teacherId = (int)($_POST['teacher_id'] ?? 0);

        if ($dept && $className && $teacherId) {
            assign_class_teacher($dept, $className, $teacherId);
            audit('assign_class_teacher', "$className in $dept assigned to teacher #$teacherId");
            flash("Assigned Teacher as Class Tutor for $dept - $className.");
        }
        redirect('hierarchy.php');
    }

    // Class Tutor / HOD / Admin updates Overall Class Attendance
    if ($action === 'update_class_attendance' && in_array($u['role'], ['admin', 'hod', 'faculty'])) {
        $sid = (int)($_POST['student_id'] ?? 0);
        $att = (float)($_POST['attendance'] ?? 0);
        if ($sid && can_update_class_attendance($u, $sid)) {
            db()->prepare('UPDATE attendance SET overall_pct=? WHERE student_id=?')->execute([$att, $sid]);
            audit('update_class_attendance', "Updated overall attendance for student #$sid to $att%");
            flash("Class attendance updated to {$att}%.");
        } else {
            flash("Permission denied: Only Class Tutor, HOD, or Admin can update class attendance.", 'warn');
        }
        redirect('hierarchy.php');
    }

    // Subject Staff logs Period Attendance
    if ($action === 'log_period_attendance' && in_array($u['role'], ['admin', 'hod', 'faculty'])) {
        $sid = (int)($_POST['student_id'] ?? 0);
        $code = trim($_POST['subject_code'] ?? '');
        $date = trim($_POST['date'] ?? date('Y-m-d'));
        $period = (int)($_POST['period_number'] ?? 1);
        $status = trim($_POST['status'] ?? 'Present');

        if ($sid && $code) {
            log_period_attendance($sid, $code, $u['id'], $date, $period, $status);
            audit('log_period_attendance', "Logged period attendance ($status) for student #$sid in $code");
            flash("Period attendance ($status) recorded for $code.");
        }
        redirect('hierarchy.php');
    }

    // Subject Staff enters Subject Marks for Student
    if ($action === 'save_subject_mark' && in_array($u['role'], ['admin', 'hod', 'faculty'])) {
        $sid = (int)($_POST['student_id'] ?? 0);
        $code = trim($_POST['subject_code'] ?? '');
        $name = trim($_POST['subject_name'] ?? '');
        $intM = (float)($_POST['internal_mark'] ?? 0);
        $examM = (float)($_POST['exam_mark'] ?? 0);
        $sem = (int)($_POST['semester'] ?? 5);

        // Check subject staff permission
        $existingStaff = db()->prepare('SELECT staff_id FROM subject_marks WHERE student_id=? AND subject_code=?');
        $existingStaff->execute([$sid, $code]);
        $existingStaffId = $existingStaff->fetchColumn();

        if ($existingStaffId !== false && !can_edit_subject_mark($u, $sid, $existingStaffId ?: null)) {
            flash("Permission denied: You can only edit marks for your own subjects unless you are HOD/Admin.", 'warn');
        } elseif ($sid && $code && $name) {
            save_subject_mark($sid, $code, $name, $intM, $examM, $u['id'], $sem);
            audit('save_subject_mark', "Subject $code for Student #$sid updated by " . $u['name']);
            flash("Subject marks for $code ($name) saved successfully!");
        }
        redirect('hierarchy.php');
    }

    // Class Tutor / HOD / Admin updates Student Academic & Placement Scores
    if ($action === 'update_student_scores' && in_array($u['role'], ['admin', 'hod', 'faculty'])) {
        $sid = (int)($_POST['student_id'] ?? 0);
        if ($sid && can_update_class_attendance($u, $sid)) {
            $cgpa = (float)$_POST['cgpa'];
            $backlogs = (int)$_POST['backlogs'];
            $internal = (float)$_POST['internal_avg'];
            $att = (float)$_POST['attendance'];
            $apt = (float)$_POST['aptitude'];
            $coding = (float)$_POST['coding'];
            $mock = (float)$_POST['mock_interview'];
            $facRating = (float)$_POST['faculty_rating'];

            $pdo = db();
            $pdo->prepare('UPDATE academic_records SET cgpa=?, backlogs=?, internal_avg=? WHERE student_id=?')->execute([$cgpa, $backlogs, $internal, $sid]);
            $pdo->prepare('UPDATE attendance SET overall_pct=? WHERE student_id=?')->execute([$att, $sid]);
            
            $roleId = (int)($_POST['role_id'] ?? 0);
            if ($roleId > 0) {
                $pdo->prepare('UPDATE placement SET aptitude=?, coding=?, mock_interview=?, role_id=? WHERE student_id=?')->execute([$apt, $coding, $mock, $roleId, $sid]);
            } else {
                $pdo->prepare('UPDATE placement SET aptitude=?, coding=?, mock_interview=? WHERE student_id=?')->execute([$apt, $coding, $mock, $sid]);
            }

            $pdo->prepare('UPDATE feedback SET faculty_rating=? WHERE student_id=?')->execute([$facRating, $sid]);

            audit('update_scores', "Student #$sid updated by " . $u['name']);
            flash("Academic & Placement metrics updated for student.");
        } else {
            flash("Permission denied: Only Class Tutor, HOD, or Admin can update these metrics.", 'warn');
        }
        redirect('hierarchy.php');
    }
}

// Load hierarchy data
$hods = load_hods();
$teachers = load_teachers($u['role'] === 'hod' ? $u['department'] : null);
$allStudents = load_students(null, null, $u);
$classAssignments = load_class_assignments($u['role'] === 'hod' ? $u['department'] : null);
$departments = load_departments();
$jobRoles = db()->query('SELECT id, name FROM job_roles ORDER BY name')->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="grid g3">
  <div class="card kpi dark">
    <small>HOD Heads</small>
    <strong><?= count($hods) ?></strong>
    <span>Managing Departmental Staff & Quality</span>
  </div>
  <div class="card kpi">
    <small>Faculty / Teachers</small>
    <strong><?= count($teachers) ?></strong>
    <span>Active Mentors & Evaluators</span>
  </div>
  <div class="card kpi">
    <small>Students Managed</small>
    <strong><?= count($allStudents) ?></strong>
    <span>Unified Profiles Tracked</span>
  </div>
</div>

<!-- Admin Action: Add New Department & HOD -->
<?php if ($u['role'] === 'admin'): ?>
<div class="card" style="border-left: 4px solid var(--primary);">
  <h3>Admin Action: Add New Department</h3>
  <p class="sub">Add a new academic department to SURYAMITHRA (e.g. Cyber Security, Robotics, Chemical Engineering).</p>
  <form method="post" class="formgrid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_department">
    <label>Department Full Name<input name="department_name" required placeholder="e.g. Cyber Security & Forensics"></label>
    <label>Department Code / Abbreviation<input name="department_code" placeholder="e.g. CYBER (optional)"></label>
    <button class="btn primary">Add Department</button>
  </form>
  
  <div style="margin-top: 14px;">
    <strong>Available Institutional Departments (<?= count($departments) ?>):</strong>
    <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">
      <?php foreach ($departments as $d): ?>
        <span class="pill blue" style="font-size:13px"><?= e($d) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card" style="border-left: 4px solid var(--violet);">
  <h3>Admin Action: Add Department HOD</h3>
  <p class="sub">Admin adds a Department HOD (Head of Department) who manages faculty and class tutors for their department.</p>
  <form method="post" class="formgrid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_hod">
    <label>HOD Full Name<input name="name" required placeholder="Dr. S. Meenakshi"></label>
    <label>Email Address<input type="email" name="email" required placeholder="hod_cse@suryamithra.local"></label>
    <label>Department
      <select name="department">
        <?php foreach ($departments as $d): ?>
          <option value="<?= e($d) ?>"><?= e($d) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Default Password<input type="text" name="password" value="password" required></label>
    <button class="btn primary">Add Department HOD</button>
  </form>
</div>
<?php endif; ?>

<!-- HOD / Admin Action: Add Teacher -->
<?php if (in_array($u['role'], ['admin', 'hod'])): ?>
<div class="card">
  <h3>Add New Teacher / Faculty</h3>
  <p class="sub">HOD adds faculty members to their department to mentor students and manage academic metrics.</p>
  <form method="post" class="formgrid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_teacher">
    <label>Teacher Name<input name="name" required placeholder="Prof. S. Ramanathan"></label>
    <label>Email Address<input type="email" name="email" required placeholder="teacher@suryamithra.local"></label>
    <label>Department
      <select name="department">
        <?php foreach ($departments as $d): ?>
          <option value="<?= $d ?>" <?= ($u['department'] === $d) ? 'selected' : '' ?>><?= e($d) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Default Password<input type="text" name="password" value="password" required></label>
    <button class="btn primary">Add Teacher</button>
  </form>
</div>

<!-- HOD / Admin Action: Assign Class Teacher / Class Tutor -->
<div class="card">
  <h3>HOD Action: Assign Class Teacher / Class Tutor</h3>
  <p class="sub">HOD designates a faculty member as Class Tutor responsible for monitoring students in a specific section.</p>
  <form method="post" class="formgrid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="assign_class_teacher">
    <label>Department
      <select name="department">
        <?php foreach ($departments as $d): ?>
          <option value="<?= $d ?>" <?= ($u['department'] === $d) ? 'selected' : '' ?>><?= e($d) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Class / Section
      <select name="class_name">
        <option value="Class A">Class A</option>
        <option value="Class B">Class B</option>
        <option value="Class C">Class C</option>
      </select>
    </label>
    <label>Assign Class Tutor / Teacher
      <select name="teacher_id" required>
        <?php foreach ($teachers as $t): ?>
          <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> (<?= e($t['department']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn primary">Assign Class Tutor</button>
  </form>

  <?php if ($classAssignments): ?>
    <h4 style="margin-top: 18px; margin-bottom: 8px;">Active Class Tutor Assignments:</h4>
    <div class="scroll">
      <table class="tbl">
        <thead>
          <tr>
            <th>Department</th>
            <th>Class / Section</th>
            <th>Class Tutor</th>
            <th>Email</th>
            <th>Students Enrolled</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($classAssignments as $ca): ?>
            <tr>
              <td><strong><?= e($ca['department']) ?></strong></td>
              <td><span class="pill blue"><?= e($ca['class_name']) ?></span></td>
              <td><strong><?= e($ca['teacher_name'] ?: 'Unassigned') ?></strong></td>
              <td><?= e($ca['teacher_email'] ?: '-') ?></td>
              <td><?= (int)$ca['student_count'] ?> students</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Teacher / HOD / Admin Action: Add Student -->
<?php if (in_array($u['role'], ['admin', 'hod', 'faculty'])): ?>
<div class="card">
  <h3>Add New Student under Teacher</h3>
  <p class="sub">Teachers add students under their guidance and set initial academic & placement baselines.</p>
  <form method="post" class="formgrid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_student">
    <label>Student Full Name<input name="name" required placeholder="Anusha Sundaram"></label>
    <label>Student Code / Roll No<input name="student_code" required placeholder="ST1061"></label>
    <label>Email Address<input type="email" name="email" placeholder="anusha@suryamithra.local"></label>
    <label>Student Login Password<input type="text" name="password" value="password" required placeholder="Set password for student login"></label>
    <label>Department
      <select name="department">
        <?php foreach ($departments as $d): ?>
          <option value="<?= e($d) ?>" <?= ($u['department'] === $d) ? 'selected' : '' ?>><?= e($d) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Semester
      <select name="semester">
        <?php for($i=1;$i<=8;$i++): ?><option value="<?= $i ?>" <?= $i==5 ? 'selected':'' ?>>Semester <?= $i ?></option><?php endfor; ?>
      </select>
    </label>
    <label>Assign Teacher / Mentor
      <select name="teacher_id">
        <?php foreach ($teachers as $t): ?>
          <option value="<?= $t['id'] ?>" <?= ($u['id'] == $t['id']) ? 'selected' : '' ?>><?= e($t['name']) ?> (<?= e($t['department']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Target Job Role
      <select name="role_id">
        <?php foreach ($jobRoles as $jr): ?>
          <option value="<?= $jr['id'] ?>"><?= e($jr['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Initial CGPA<input type="number" step="0.1" name="cgpa" value="0.0" min="0" max="10" required></label>
    <label>Initial Coding Score (0-100)<input type="number" name="coding" value="0" min="0" max="100" required></label>
    <button class="btn primary" style="grid-column: 1/-1">Register Student under Class Tutor</button>
  </form>
</div>
<?php endif; ?>

<!-- Hierarchy Tree Visualization -->
<div class="card">
  <h3>Institutional Hierarchy Overview & Class Tutor Monitoring</h3>
  <p class="sub">HOD → Faculty/Class Tutors → Class Students & Subject Marks</p>

  <?php if (!$teachers): ?>
    <p class="muted">No teachers registered in this view yet. Add your first faculty member above.</p>
  <?php else: ?>
    <?php foreach ($teachers as $t): 
        $tStudents = array_filter($allStudents, fn($s) => $s['teacher_id'] == $t['id']);
    ?>
    <div style="border: 1px solid var(--line); border-radius: 16px; padding: 18px; margin-bottom: 16px; background: #fafaff">
      <div class="row spread" style="border-bottom: 1px solid var(--line); padding-bottom: 12px; margin-bottom: 12px;">
        <div>
          <span class="pill violet" style="margin-bottom:4px">Class Tutor / Faculty</span>
          <h3 style="margin: 2px 0"><?= e($t['name']) ?> <small class="muted">(<?= e($t['email']) ?>)</small></h3>
          <span class="muted">Department: <strong><?= e($t['department']) ?></strong> · Mentor HOD: <strong><?= e($t['hod_name'] ?: 'HOD') ?></strong></span>
        </div>
        <div>
          <span class="pill blue"><?= count($tStudents) ?> Students Assigned</span>
        </div>
      </div>

      <!-- Assigned Students Table for this Teacher -->
      <?php if (!$tStudents): ?>
        <p class="muted" style="margin: 8px 0">No students assigned to this teacher yet.</p>
      <?php else: ?>
        <div class="scroll">
          <table class="tbl" style="background:#fff; border-radius:12px; overflow:hidden">
            <thead>
              <tr>
                <th>Student</th>
                <th>Class</th>
                <th>CGPA</th>
                <th>Attendance</th>
                <th>Aptitude</th>
                <th>Coding</th>
                <th>Success Score</th>
                <th>Risk</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tStudents as $st): ?>
              <tr>
                <td>
                  <div class="who2">
                    <strong><a href="<?= url('student.php?id=' . $st['id']) ?>"><?= e($st['name']) ?></a></strong>
                    <small><?= e($st['student_code']) ?></small>
                  </div>
                </td>
                <td><span class="pill slate"><?= e($st['class_name'] ?: 'Class A') ?></span></td>
                <td><strong><?= number_format($st['cgpa'], 1) ?></strong> <small class="muted">(Backlogs: <?= $st['backlogs'] ?>)</small></td>
                <td><?= f0($st['attendance']) ?>%</td>
                <td><?= f0($st['aptitude']) ?></td>
                <td><?= f0($st['coding']) ?></td>
                <td>
                  <div class="scorebar">
                    <span><?= f0($st['score']) ?></span>
                    <i><b style="width:<?= $st['score'] ?>%;background:<?= score_color($st['score']) ?>"></b></i>
                  </div>
                </td>
                <td><?= risk_pill($st['risk']['overall']) ?></td>
                <td>
                  <button type="button" class="btn sm" onclick="toggleEdit(<?= $st['id'] ?>)">Class Tutor Edit</button>
                  <button type="button" class="btn sm primary" onclick="toggleMarks(<?= $st['id'] ?>)" style="margin-left: 4px;">Subject Mark</button>
                  <button type="button" class="btn sm green" onclick="togglePeriod(<?= $st['id'] ?>)" style="margin-left: 4px;">Period Attd</button>
                </td>
              </tr>
              <!-- Inline Update Form Row for Class Tutor -->
              <tr id="editRow-<?= $st['id'] ?>" style="display:none; background:#f0f2ff">
                <td colspan="9">
                  <form method="post" style="padding:10px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_student_scores">
                    <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                    <div style="font-weight:700; margin-bottom:8px">Class Tutor: Update Attendance & Metrics for <?= e($st['name']) ?>:</div>
                    <div class="formgrid">
                      <label>CGPA<input type="number" step="0.01" name="cgpa" value="<?= $st['cgpa'] ?>" required></label>
                      <label>Internal Avg<input type="number" step="0.1" name="internal_avg" value="<?= $st['internal_avg'] ?>" required></label>
                      <label>Backlogs<input type="number" name="backlogs" value="<?= $st['backlogs'] ?>" required></label>
                      <label>Class Attendance %<input type="number" step="0.1" name="attendance" value="<?= $st['attendance'] ?>" required></label>
                      <label>Target Job Role
                        <select name="role_id">
                          <?php foreach ($jobRoles as $jr): ?>
                            <option value="<?= $jr['id'] ?>" <?= ($st['role_id'] == $jr['id']) ? 'selected' : '' ?>><?= e($jr['name']) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <label>Aptitude Score<input type="number" step="0.1" name="aptitude" value="<?= $st['aptitude'] ?>" required></label>
                      <label>Coding Score<input type="number" step="0.1" name="coding" value="<?= $st['coding'] ?>" required></label>
                      <label>Mock Interview<input type="number" step="0.1" name="mock_interview" value="<?= $st['mock_interview'] ?>" required></label>
                      <label>Faculty Rating (1-5)<input type="number" step="0.1" name="faculty_rating" value="<?= $st['faculty_rating'] ?>" required></label>
                      <button class="btn primary sm" style="grid-column: 1/-1">Save Class Tutor Updates</button>
                    </div>
                  </form>
                </td>
              </tr>
              <!-- Inline Subject Marks Form Row for Subject Staff -->
              <tr id="marksRow-<?= $st['id'] ?>" style="display:none; background:#e8f4ff">
                <td colspan="9">
                  <form method="post" style="padding:12px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_subject_mark">
                    <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                    <div style="font-weight:700; margin-bottom:8px; color:var(--primary)">Subject Staff: Enter Mark for <?= e($st['name']) ?>:</div>
                    <div class="formgrid">
                      <label>Subject Code<input name="subject_code" required placeholder="e.g. CS501"></label>
                      <label>Subject Name<input name="subject_name" required placeholder="e.g. Data Structures"></label>
                      <label>Internal Mark (0-50)<input type="number" step="0.5" name="internal_mark" min="0" max="50" required placeholder="out of 50"></label>
                      <label>Exam Mark (0-100)<input type="number" step="0.5" name="exam_mark" min="0" max="100" required placeholder="out of 100"></label>
                      <label>Semester
                        <select name="semester">
                          <?php for($semIdx=1;$semIdx<=8;$semIdx++): ?>
                            <option value="<?= $semIdx ?>" <?= $semIdx == $st['semester'] ? 'selected' : '' ?>>Sem <?= $semIdx ?></option>
                          <?php endfor; ?>
                        </select>
                      </label>
                      <button class="btn primary sm" style="grid-column: 1/-1">Save Subject Mark</button>
                    </div>
                  </form>
                </td>
              </tr>
              <!-- Inline Period Attendance Form Row for Subject Staff -->
              <tr id="periodRow-<?= $st['id'] ?>" style="display:none; background:#eefbf3">
                <td colspan="9">
                  <form method="post" style="padding:12px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="log_period_attendance">
                    <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                    <div style="font-weight:700; margin-bottom:8px; color:var(--green)">Subject Staff: Upload Period Attendance for <?= e($st['name']) ?>:</div>
                    <div class="formgrid">
                      <label>Subject Code<input name="subject_code" required placeholder="e.g. CS501"></label>
                      <label>Date<input type="date" name="date" value="<?= date('Y-m-d') ?>" required></label>
                      <label>Period Number
                        <select name="period_number">
                          <?php for($p=1;$p<=8;$p++): ?>
                            <option value="<?= $p ?>">Period <?= $p ?></option>
                          <?php endfor; ?>
                        </select>
                      </label>
                      <label>Status
                        <select name="status">
                          <option value="Present">Present</option>
                          <option value="Absent">Absent</option>
                          <option value="Late">Late</option>
                        </select>
                      </label>
                      <button class="btn primary sm" style="grid-column: 1/-1">Record Period Attendance</button>
                    </div>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
function toggleEdit(id) {
  var row = document.getElementById('editRow-' + id);
  if (row) {
    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
  }
}
function toggleMarks(id) {
  var row = document.getElementById('marksRow-' + id);
  if (row) {
    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
  }
}
function togglePeriod(id) {
  var row = document.getElementById('periodRow-' + id);
  if (row) {
    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
  }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
