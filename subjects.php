<?php
$pageTitle = 'Subjects & Staff Allocations';
$pageSub = 'Class Tutors update department subjects and allocate subject staff to classes';
require_once __DIR__ . '/includes/functions.php';
$u = require_role(['admin', 'hod', 'faculty']);

$dept = $_GET['dept'] ?? ($u['department'] ?? 'CSE');
if (!$dept || $dept === 'Management' || $dept === 'Placement Cell') $dept = 'CSE';
$depts = load_departments();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';

    if ($act === 'add_subject') {
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $d = trim($_POST['department'] ?? $dept);
        $yr = (int)($_POST['year'] ?? 1);
        $sem = (int)($_POST['semester'] ?? 1);

        if ($code && $name) {
            add_department_subject($code, $name, $d, $yr, $sem, $u['id']);
            audit('add_subject', "Subject $code added for $d");
            flash("Subject $code ($name) added successfully.");
            redirect("subjects.php?dept=" . urlencode($d));
        } else {
            flash('Subject code and name are required.', 'err');
        }
    } elseif ($act === 'allocate_staff') {
        $subCode = trim($_POST['subject_code'] ?? '');
        $d = trim($_POST['department'] ?? $dept);
        $className = trim($_POST['class_name'] ?? 'Class A');
        $staffId = (int)($_POST['staff_id'] ?? 0);

        if ($subCode && $staffId > 0) {
            allocate_subject_staff($subCode, $d, $className, $staffId, $u['id']);
            audit('allocate_staff', "Allocated staff #$staffId to $subCode ($d - $className)");
            flash("Subject staff allocated successfully to $subCode ($className).");
            redirect("subjects.php?dept=" . urlencode($d));
        } else {
            flash('Please select a subject and a staff member.', 'err');
        }
    }
}

$subjects = load_department_subjects($dept);
$teachers = load_teachers($dept);
$allocations = load_subject_staff_allocations($dept);

include __DIR__ . '/includes/header.php';
?>

<div class="row spread" style="margin-bottom:18px;">
  <div>
    <h2 style="margin:0;">Department Subjects & Staff Allocation</h2>
    <p class="sub" style="margin:0;">Manage course subjects and assign faculty to subject classes.</p>
  </div>
  <form method="get" class="row" style="gap:8px;">
    <select name="dept" onchange="this.form.submit()" style="width:auto; font-weight:700;">
      <?php foreach ($depts as $d): ?>
        <option value="<?= e($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div class="grid g2" style="margin-bottom:24px;">

  <!-- Form 1: Add New Subject (Class Tutor) -->
  <div class="card">
    <h3>1. Add Department Subject</h3>
    <p class="sub">Class tutors add course subjects for their department</p>
    <form method="post" style="display:flex; flex-direction:column; gap:12px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_subject">
      <input type="hidden" name="department" value="<?= e($dept) ?>">

      <div class="row" style="gap:12px;">
        <label style="flex:1;">Subject Code
          <input type="text" name="code" placeholder="e.g. CS501" required>
        </label>
        <label style="flex:1.5;">Subject Name
          <input type="text" name="name" placeholder="e.g. Operating Systems" required>
        </label>
      </div>

      <div class="row" style="gap:12px;">
        <label style="flex:1;">Year
          <select name="year">
            <option value="1">1st Year</option>
            <option value="2">2nd Year</option>
            <option value="3" selected>3rd Year</option>
            <option value="4">4th Year</option>
          </select>
        </label>
        <label style="flex:1;">Semester
          <select name="semester">
            <?php for ($i=1; $i<=8; $i++): ?>
              <option value="<?= $i ?>" <?= $i === 5 ? 'selected' : '' ?>>Sem <?= $i ?></option>
            <?php endfor; ?>
          </select>
        </label>
      </div>

      <button class="btn primary" style="margin-top:6px;">Add Subject</button>
    </form>
  </div>

  <!-- Form 2: Allocate Subject Staff to Class (Class Tutor) -->
  <div class="card">
    <h3>2. Allocate Subject Staff to Class</h3>
    <p class="sub">Class tutors assign specific subject teachers to classes</p>
    <form method="post" style="display:flex; flex-direction:column; gap:12px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="allocate_staff">
      <input type="hidden" name="department" value="<?= e($dept) ?>">

      <label>Select Subject
        <select name="subject_code" required>
          <option value="">-- Choose Subject --</option>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= e($s['code']) ?>"><?= e($s['code']) ?> - <?= e($s['name']) ?> (Sem <?= $s['semester'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="row" style="gap:12px;">
        <label style="flex:1;">Class
          <select name="class_name">
            <option value="Class A">Class A</option>
            <option value="Class B">Class B</option>
            <option value="Class C">Class C</option>
          </select>
        </label>
        <label style="flex:2;">Subject Faculty / Staff
          <select name="staff_id" required>
            <option value="">-- Choose Faculty --</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= e($t['name']) ?> (<?= e($t['email']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <button class="btn primary" style="margin-top:6px;">Allocate Subject Staff</button>
    </form>
  </div>

</div>

<!-- Current Subjects & Staff Allocations Table -->
<div class="card flat">
  <div class="pad row spread">
    <div>
      <h3>Current Subjects & Allocated Staff (<?= e($dept) ?>)</h3>
      <p class="sub">Allocated subject staff have permission to record period attendance, enter internal/semester marks, and conduct daily quizzes.</p>
    </div>
  </div>
  <div class="scroll">
    <table class="tbl">
      <thead>
        <tr>
          <th>Subject Code</th>
          <th>Subject Name</th>
          <th>Year / Sem</th>
          <th>Allocated Class</th>
          <th>Allocated Subject Staff</th>
          <th>Created By</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subjects as $s): 
          $alloc = array_values(array_filter($allocations, fn($a) => $a['subject_code'] === $s['code']));
          $allocStaff = $alloc ? $alloc[0]['staff_name'] : '<em>Unallocated</em>';
          $allocClass = $alloc ? $alloc[0]['class_name'] : 'Class A';
        ?>
          <tr>
            <td><strong><?= e($s['code']) ?></strong></td>
            <td><?= e($s['name']) ?></td>
            <td>Year <?= $s['year'] ?> · Sem <?= $s['semester'] ?></td>
            <td><span class="pill blue"><?= e($allocClass) ?></span></td>
            <td><?= $alloc ? '<strong>' . e($allocStaff) . '</strong>' : '<span class="muted">Not allocated yet</span>' ?></td>
            <td><small><?= e($s['creator_name'] ?: 'System') ?></small></td>
          </tr>
        <?php endforeach; if (!$subjects): ?>
          <tr><td colspan="6" class="muted" style="text-align:center; padding:20px;">No subjects created for <?= e($dept) ?> yet. Use the form above to add subjects.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
