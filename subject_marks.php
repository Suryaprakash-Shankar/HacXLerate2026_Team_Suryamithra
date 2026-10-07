<?php
$pageTitle = 'Subject Marks Entry';
$pageSub = 'Subject staff enter internal marks and semester exam marks for department students';
require_once __DIR__ . '/includes/functions.php';
$u = require_role(['admin', 'hod', 'faculty']);

$dept = $_GET['dept'] ?? ($u['department'] ?? 'CSE');
if (!$dept || $dept === 'Management' || $dept === 'Placement Cell') $dept = 'CSE';
$className = $_GET['class_name'] ?? 'Class A';
$subCode = $_GET['subject_code'] ?? 'CS501';
$sem = (int)($_GET['semester'] ?? 5);

$depts = load_departments();
$subjects = load_department_subjects($dept);
if (!$subCode && !empty($subjects)) {
    $subCode = $subjects[0]['code'];
}

// Handle Bulk Mark Submission by Subject Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    csrf_check();
    $subCodePost = trim($_POST['subject_code'] ?? $subCode);
    $subNamePost = trim($_POST['subject_name'] ?? 'Subject');
    $marksData = $_POST['marks'] ?? [];

    $count = 0;
    foreach ($marksData as $sid => $mRow) {
        $sid = (int)$sid;
        $internal = max(0, min(100, (float)($mRow['internal'] ?? 0)));
        $exam = max(0, min(100, (float)($mRow['exam'] ?? 0)));

        save_subject_mark($sid, $subCodePost, $subNamePost, $internal, $exam, $u['id'], $sem);
        $count++;
    }

    audit('save_marks', "Saved internal & semester marks for $subCodePost ($count students)");
    flash("Successfully updated internal & semester marks for $count students in $subCodePost.");
    redirect("subject_marks.php?dept=" . urlencode($dept) . "&class_name=" . urlencode($className) . "&subject_code=" . urlencode($subCodePost) . "&semester=" . $sem);
}

// Load Students for selected department, class & semester
$students = load_class_period_matrix($dept, $className, date('Y-m-d'), 0, $sem);

// Load existing subject marks for these students
$pdo = db();
$existingMarks = [];
if (!empty($students)) {
    $sids = array_column($students, 'id');
    $in = implode(',', array_map('intval', $sids));
    $stM = $pdo->query("SELECT * FROM subject_marks WHERE subject_code=" . $pdo->quote($subCode) . " AND student_id IN ($in)");
    foreach ($stM->fetchAll() as $r) {
        $existingMarks[$r['student_id']] = $r;
    }
}

// Find subject name
$subName = 'Subject';
foreach ($subjects as $sItem) {
    if ($sItem['code'] === $subCode) {
        $subName = $sItem['name'];
        break;
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="row spread" style="margin-bottom:18px;">
  <div>
    <h2 style="margin:0;">Internal Marks & Semester Exam Entry</h2>
    <p class="sub" style="margin:0;">Subject faculty enter internal marks and semester exam marks. CGPA and internal averages update automatically.</p>
  </div>
</div>

<!-- Filters Bar -->
<div class="card" style="margin-bottom:20px;">
  <form method="get" class="filters">
    <label>Department
      <select name="dept" onchange="this.form.submit()">
        <?php foreach ($depts as $d): ?>
          <option value="<?= e($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Class
      <select name="class_name" onchange="this.form.submit()">
        <option value="Class A" <?= $className === 'Class A' ? 'selected' : '' ?>>Class A</option>
        <option value="Class B" <?= $className === 'Class B' ? 'selected' : '' ?>>Class B</option>
        <option value="Class C" <?= $className === 'Class C' ? 'selected' : '' ?>>Class C</option>
      </select>
    </label>
    <label>Subject
      <select name="subject_code" onchange="this.form.submit()">
        <?php foreach ($subjects as $sb): ?>
          <option value="<?= e($sb['code']) ?>" <?= $subCode === $sb['code'] ? 'selected' : '' ?>><?= e($sb['code']) ?> - <?= e($sb['name']) ?></option>
        <?php endforeach; if (!$subjects): ?>
          <option value="">No subjects found</option>
        <?php endif; ?>
      </select>
    </label>
    <label>Semester
      <select name="semester" onchange="this.form.submit()">
        <?php for ($i=1; $i<=8; $i++): ?>
          <option value="<?= $i ?>" <?= $sem === $i ? 'selected' : '' ?>>Sem <?= $i ?></option>
        <?php endfor; ?>
      </select>
    </label>
    <button class="btn primary" style="align-self:flex-end;">Filter</button>
  </form>
</div>

<!-- Marks Table Form -->
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="save_marks" value="1">
  <input type="hidden" name="subject_code" value="<?= e($subCode) ?>">
  <input type="hidden" name="subject_name" value="<?= e($subName) ?>">

  <div class="card flat">
    <div class="pad row spread">
      <div>
        <h3>Students Marks: <?= e($subCode) ?> - <?= e($subName) ?> (<?= e($dept) ?> - <?= e($className) ?>, Sem <?= $sem ?>)</h3>
        <p class="sub">Total % Formula: <code>40% Internal Mark + 60% Semester Exam Mark</code></p>
      </div>
      <button class="btn primary">Save All Marks</button>
    </div>

    <div class="scroll">
      <table class="tbl">
        <thead>
          <tr>
            <th>Student</th>
            <th>Roll / Code</th>
            <th>Internal Mark (Out of 100 / 40)</th>
            <th>Semester Exam Mark (Out of 100 / 60)</th>
            <th>Calculated Total %</th>
            <th>Grade</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $st): 
            $sid = $st['id'];
            $mRow = $existingMarks[$sid] ?? null;
            $internalVal = $mRow ? (float)$mRow['internal_mark'] : 75.0;
            $examVal = $mRow ? (float)$mRow['exam_mark'] : 80.0;
            $totalVal = $mRow ? (float)$mRow['total_pct'] : round(($internalVal * 0.4) + ($examVal * 0.6), 1);
            $gradeVal = $mRow ? $mRow['grade'] : ($totalVal >= 90 ? 'A+' : ($totalVal >= 80 ? 'A' : ($totalVal >= 70 ? 'B' : ($totalVal >= 60 ? 'C' : 'F'))));
          ?>
            <tr>
              <td><strong><?= e($st['name']) ?></strong></td>
              <td><code><?= e($st['student_code']) ?></code></td>
              <td style="width:200px;">
                <input type="number" step="0.5" min="0" max="100" name="marks[<?= $sid ?>][internal]" value="<?= $internalVal ?>" class="int-input" data-sid="<?= $sid ?>" required>
              </td>
              <td style="width:200px;">
                <input type="number" step="0.5" min="0" max="100" name="marks[<?= $sid ?>][exam]" value="<?= $examVal ?>" class="exam-input" data-sid="<?= $sid ?>" required>
              </td>
              <td>
                <strong id="tot_<?= $sid ?>"><?= f1($totalVal) ?>%</strong>
              </td>
              <td>
                <span class="pill <?= $gradeVal === 'F' ? 'red' : 'green' ?>" id="grd_<?= $sid ?>"><?= e($gradeVal) ?></span>
              </td>
            </tr>
          <?php endforeach; if (!$students): ?>
            <tr><td colspan="6" class="muted" style="text-align:center; padding:20px;">No students found in <?= e($dept) ?> - <?= e($className) ?> for Semester <?= $sem ?>.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($students): ?>
      <div class="pad row" style="justify-content:flex-end;">
        <button class="btn primary wide" style="max-width:250px;">Save All Marks</button>
      </div>
    <?php endif; ?>
  </div>
</form>

<script>
// Dynamic On-the-fly Mark Calculation
function updateRow(sid) {
  var intInput = document.querySelector('.int-input[data-sid="' + sid + '"]');
  var examInput = document.querySelector('.exam-input[data-sid="' + sid + '"]');

  var iVal = parseFloat(intInput.value) || 0;
  var eVal = parseFloat(examInput.value) || 0;

  var tot = (iVal * 0.4) + (eVal * 0.6);
  tot = Math.min(100, Math.max(0, tot)).toFixed(1);

  var grade = tot >= 90 ? 'A+' : (tot >= 80 ? 'A' : (tot >= 70 ? 'B' : (tot >= 60 ? 'C' : (tot >= 50 ? 'D' : 'F'))));

  document.getElementById('tot_' + sid).textContent = tot + '%';
  var grdEl = document.getElementById('grd_' + sid);
  grdEl.textContent = grade;
  grdEl.className = 'pill ' + (grade === 'F' ? 'red' : 'green');
}

document.querySelectorAll('.int-input, .exam-input').forEach(function (inp) {
  inp.addEventListener('input', function () {
    updateRow(this.dataset.sid);
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
