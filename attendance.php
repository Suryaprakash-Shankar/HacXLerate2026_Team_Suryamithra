<?php
$pageTitle = '7-Period Attendance, OD & Assignments';
$pageSub = 'Class Tutors & Subject Staff manage 2nd, 3rd, 4th Year student attendance, OD approvals & assignments.';
require_once __DIR__ . '/includes/functions.php';
$u = require_role(['admin', 'hod', 'faculty']);

$departments = load_departments();
$dept = trim($_GET['dept'] ?? ($u['department'] ?: 'CSE'));
$className = trim($_GET['class_name'] ?? 'Class A');
$year = (int)($_GET['year'] ?? 0);
$sem = (int)($_GET['sem'] ?? 0);
$date = trim($_GET['date'] ?? date('Y-m-d'));
$mode = trim($_GET['mode'] ?? 'tutor');

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Class Tutor / Admin / HOD saves full 7-Period Matrix
    if ($action === 'save_matrix') {
        $postDept = trim($_POST['department'] ?? $dept);
        $postClass = trim($_POST['class_name'] ?? $className);
        $postDate = trim($_POST['date'] ?? $date);
        $postYear = (int)($_POST['year'] ?? $year);
        $postSem = (int)($_POST['sem'] ?? $sem);
        $matrix = $_POST['matrix'] ?? [];

        $savedCount = save_class_period_matrix($matrix, $postDate, $u['id']);
        audit('save_period_attendance_matrix', "Saved 7-period attendance for $postDept $postClass on $postDate ($savedCount records updated)");
        flash("Successfully saved 7-period attendance for $postDept - $postClass on $postDate! Attendance percentages updated.");
        redirect("attendance.php?dept=" . urlencode($postDept) . "&class_name=" . urlencode($postClass) . "&date=" . urlencode($postDate) . "&year=$postYear&sem=$postSem&mode=tutor");
    }

    // Subject Staff / Tutor logs period attendance for a subject and period
    if ($action === 'log_subject_period') {
        $postDept = trim($_POST['department'] ?? $dept);
        $postClass = trim($_POST['class_name'] ?? $className);
        $postDate = trim($_POST['date'] ?? $date);
        $postYear = (int)($_POST['year'] ?? $year);
        $postSem = (int)($_POST['sem'] ?? $sem);
        $subCode = trim($_POST['subject_code'] ?? 'GEN');
        $periodNum = (int)($_POST['period_number'] ?? 1);
        $statusMap = $_POST['status_map'] ?? [];

        $count = 0;
        foreach ($statusMap as $sid => $st) {
            $sid = (int)$sid;
            if ($sid > 0) {
                log_period_attendance($sid, $subCode, $u['id'], $postDate, $periodNum, $st);
                $count++;
            }
        }

        audit('log_subject_period', "Subject $subCode Period $periodNum attendance logged for $count students on $postDate");
        flash("Recorded Period $periodNum ($subCode) attendance for $count students on $postDate! Automatically stored in Tutor sheet.");
        redirect("attendance.php?dept=" . urlencode($postDept) . "&class_name=" . urlencode($postClass) . "&date=" . urlencode($postDate) . "&year=$postYear&sem=$postSem&mode=subject");
    }

    // Quick Action: Class Tutor converts student's Period Absent to OD
    if ($action === 'approve_od') {
        $sid = (int)($_POST['student_id'] ?? 0);
        $pNum = (int)($_POST['period_number'] ?? 1);
        $subCode = trim($_POST['subject_code'] ?? 'GEN');
        $pDate = trim($_POST['date'] ?? $date);

        if ($sid > 0 && $pNum >= 1 && $pNum <= 7) {
            log_period_attendance($sid, $subCode, $u['id'], $pDate, $pNum, 'OD');
            audit('approve_od', "Converted Period $pNum to OD for student #$sid on $pDate");
            flash("Period $pNum marked as OD (On Duty) for student. OD does not reduce attendance!");
        }
        redirect("attendance.php?dept=" . urlencode($dept) . "&class_name=" . urlencode($className) . "&date=" . urlencode($pDate) . "&year=$year&sem=$sem&mode=tutor");
    }

    // Post New Common Assignment for 2nd, 3rd, 4th Year Students
    if ($action === 'create_assignment') {
        $asgTitle = trim($_POST['title'] ?? '');
        $asgDesc = trim($_POST['description'] ?? '');
        $asgDept = trim($_POST['department'] ?? $dept);
        $asgClass = trim($_POST['class_name'] ?? $className);
        $asgYear = (int)($_POST['year'] ?? 3);
        $asgSem = (int)($_POST['semester'] ?? 5);
        $asgSubCode = trim($_POST['subject_code'] ?? 'CS501');
        $dueDate = trim($_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days')));

        if ($asgTitle && $asgDept) {
            create_assignment($asgTitle, $asgDesc, $asgDept, $asgClass, $asgYear, $asgSem, $asgSubCode, $u['id'], $dueDate);
            audit('create_assignment', "Created assignment '$asgTitle' for Year $asgYear ($asgDept $asgClass)");
            flash("Assignment '$asgTitle' posted successfully for Year $asgYear ($asgDept - $asgClass)!");
        }
        redirect("attendance.php?dept=" . urlencode($asgDept) . "&class_name=" . urlencode($asgClass) . "&year=$asgYear&mode=assignments");
    }
}

// Load students using 1-click Year & Semester filter
$students = load_class_period_matrix($dept, $className, $date, $year, $sem);
$classAssignments = load_class_assignments($dept);
$assignmentsList = load_assignments($dept, $year, $sem);

// Find assigned tutor for current section
$assignedTutorName = 'Unassigned';
foreach ($classAssignments as $ca) {
    if ($ca['class_name'] === $className) {
        $assignedTutorName = $ca['teacher_name'] ?: 'Unassigned';
        break;
    }
}

include __DIR__ . '/includes/header.php';
?>

<!-- Filter Control Bar -->
<div class="card" style="margin-bottom: 20px;">
  <form method="get" class="formgrid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); align-items: end;">
    <label>Department
      <select name="dept" onchange="this.form.submit()">
        <?php foreach ($departments as $d): ?>
          <option value="<?= e($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Class / Section
      <select name="class_name" onchange="this.form.submit()">
        <option value="Class A" <?= $className === 'Class A' ? 'selected' : '' ?>>Class A</option>
        <option value="Class B" <?= $className === 'Class B' ? 'selected' : '' ?>>Class B</option>
        <option value="Class C" <?= $className === 'Class C' ? 'selected' : '' ?>>Class C</option>
      </select>
    </label>

    <label>Year Group
      <select name="year" onchange="this.form.submit()">
        <option value="0" <?= $year === 0 ? 'selected' : '' ?>>All Years (2nd, 3rd, 4th)</option>
        <option value="2" <?= $year === 2 ? 'selected' : '' ?>>2nd Year (Sem 3 & 4)</option>
        <option value="3" <?= $year === 3 ? 'selected' : '' ?>>3rd Year (Sem 5 & 6)</option>
        <option value="4" <?= $year === 4 ? 'selected' : '' ?>>4th Year (Sem 7 & 8)</option>
      </select>
    </label>

    <label>Semester
      <select name="sem" onchange="this.form.submit()">
        <option value="0" <?= $sem === 0 ? 'selected' : '' ?>>All Semesters</option>
        <?php for($i=1; $i<=8; $i++): ?>
          <option value="<?= $i ?>" <?= $sem === $i ? 'selected' : '' ?>>Semester <?= $i ?></option>
        <?php endfor; ?>
      </select>
    </label>

    <label>Date
      <input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
    </label>

    <input type="hidden" name="mode" value="<?= e($mode) ?>">

    <div>
      <button class="btn primary" style="width: 100%;">Filter Students</button>
    </div>
  </form>

  <!-- 1-Click Quick Year Filter Pills -->
  <div style="margin-top: 14px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
    <span style="font-size:12px; font-weight:700; color:var(--muted)">1-Click Year Filter:</span>
    <a href="attendance.php?dept=<?= urlencode($dept) ?>&class_name=<?= urlencode($className) ?>&year=0&date=<?= urlencode($date) ?>&mode=<?= e($mode) ?>" class="pill <?= $year === 0 ? 'blue' : 'slate' ?>">All Years</a>
    <a href="attendance.php?dept=<?= urlencode($dept) ?>&class_name=<?= urlencode($className) ?>&year=2&date=<?= urlencode($date) ?>&mode=<?= e($mode) ?>" class="pill <?= $year === 2 ? 'blue' : 'slate' ?>">2nd Year (Sem 3,4)</a>
    <a href="attendance.php?dept=<?= urlencode($dept) ?>&class_name=<?= urlencode($className) ?>&year=3&date=<?= urlencode($date) ?>&mode=<?= e($mode) ?>" class="pill <?= $year === 3 ? 'blue' : 'slate' ?>">3rd Year (Sem 5,6)</a>
    <a href="attendance.php?dept=<?= urlencode($dept) ?>&class_name=<?= urlencode($className) ?>&year=4&date=<?= urlencode($date) ?>&mode=<?= e($mode) ?>" class="pill <?= $year === 4 ? 'blue' : 'slate' ?>">4th Year (Sem 7,8)</a>
  </div>
</div>

<!-- Tab Navigation Bar -->
<div style="display:flex; gap:10px; margin-bottom:20px;">
  <a href="attendance.php?dept=<?= urlencode($dept) ?>&class_name=<?= urlencode($className) ?>&year=<?= $year ?>&sem=<?= $sem ?>&date=<?= urlencode($date) ?>&mode=tutor" class="btn <?= $mode === 'tutor' ? 'primary' : 'secondary' ?>">
    Class Tutor 7-Period Sheet & OD
  </a>
  <a href="attendance.php?dept=<?= urlencode($dept) ?>&class_name=<?= urlencode($className) ?>&year=<?= $year ?>&sem=<?= $sem ?>&date=<?= urlencode($date) ?>&mode=subject" class="btn <?= $mode === 'subject' ? 'primary' : 'secondary' ?>">
    Subject Faculty 1-Click Upload
  </a>
  <a href="attendance.php?dept=<?= urlencode($dept) ?>&class_name=<?= urlencode($className) ?>&year=<?= $year ?>&sem=<?= $sem ?>&date=<?= urlencode($date) ?>&mode=assignments" class="btn <?= $mode === 'assignments' ? 'primary' : 'secondary' ?>">
    Assignments Manager
  </a>
</div>

<!-- Info Cards & OD Policy Overview -->
<div class="grid g4" style="margin-bottom: 20px;">
  <div class="card kpi dark">
    <small>Selected Section</small>
    <strong><?= e($dept) ?> - <?= e($className) ?></strong>
    <span>Class Tutor: <b><?= e($assignedTutorName) ?></b></span>
  </div>
  <div class="card kpi">
    <small>Students Loaded</small>
    <strong><?= count($students) ?></strong>
    <span>Year Group: <?= $year > 0 ? "Year $year" : "All Years" ?></span>
  </div>
  <div class="card kpi">
    <small>Semester Baseline</small>
    <strong style="color: var(--green);">100% Initial</strong>
    <span>5 Months / 110 Days (770 Periods)</span>
  </div>
  <div class="card kpi">
    <small>OD (On Duty) Policy</small>
    <strong style="color: var(--violet);">0% Reduction</strong>
    <span>OD maintains 100% attendance score</span>
  </div>
</div>

<?php if ($mode === 'tutor'): ?>

  <!-- CLASS TUTOR 7-PERIOD ATTENDANCE MATRIX & OD CONVERSION -->
  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom: 16px;">
      <div>
        <h3>Class Tutor 7-Period Attendance Sheet & OD Approval</h3>
        <p class="sub">View and update attendance for all 7 periods. If a student went for <b>OD (On Duty)</b>, select <b>OD</b> or convert Subject Staff's <b>Absent</b> mark to <b>OD</b> to prevent score reduction.</p>
      </div>
      <div style="display:flex; gap:8px;">
        <button type="button" class="btn secondary sm" onclick="markAllPresent()">Set All Present</button>
        <button type="button" class="btn secondary sm" onclick="convertAbsentsToOD()">Convert Absents to OD</button>
      </div>
    </div>

    <?php if (empty($students)): ?>
      <div style="text-align: center; padding: 40px;" class="muted">
        <p>No students found for <?= e($dept) ?> - <?= e($className) ?> (<?= $year > 0 ? "Year $year" : "All Years" ?>).</p>
      </div>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_matrix">
        <input type="hidden" name="department" value="<?= e($dept) ?>">
        <input type="hidden" name="class_name" value="<?= e($className) ?>">
        <input type="hidden" name="date" value="<?= e($date) ?>">
        <input type="hidden" name="year" value="<?= $year ?>">
        <input type="hidden" name="sem" value="<?= $sem ?>">

        <div style="overflow-x: auto;">
          <table class="table" style="font-size: 13px;">
            <thead>
              <tr>
                <th style="min-width:140px;">Student</th>
                <th style="min-width:70px; text-align:center;">Year/Sem</th>
                <th style="min-width:80px; text-align:center;">Attd %</th>
                <?php for ($p = 1; $p <= 7; $p++): ?>
                  <th style="min-width:115px; text-align:center;">Period <?= $p ?></th>
                <?php endfor; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $s): ?>
                <tr>
                  <td>
                    <strong><?= e($s['name']) ?></strong><br>
                    <small class="muted"><?= e($s['student_code']) ?></small>
                  </td>
                  <td style="text-align:center;">
                    <span class="pill slate" style="font-size:11px;">Y<?= e($s['year']) ?> · S<?= e($s['semester']) ?></span>
                  </td>
                  <td style="text-align:center;">
                    <?php 
                      $attVal = (float)$s['overall_pct'];
                      $pillCls = $attVal >= 85 ? 'green' : ($attVal >= 75 ? 'amber' : 'red');
                    ?>
                    <span class="pill <?= $pillCls ?>" style="font-weight:700;"><?= f1($attVal) ?>%</span>
                  </td>

                  <?php for ($p = 1; $p <= 7; $p++): 
                    $pData = $s['periods'][$p] ?? ['status' => 'Present', 'subject_code' => 'GEN'];
                    $curStatus = $pData['status'];
                    $subCode = $pData['subject_code'] ?: 'GEN';
                    $staffName = $pData['staff_name'] ?? '';
                  ?>
                    <td style="text-align:center; background: <?= $curStatus === 'Absent' ? '#fff5f5' : ($curStatus === 'OD' ? '#f3f0ff' : 'transparent') ?>;">
                      <div style="margin-bottom: 4px;">
                        <select name="matrix[<?= $s['id'] ?>][<?= $p ?>][status]" class="status-select p-sel-<?= $p ?>" style="padding: 3px 6px; font-size:12px; border-radius:6px; font-weight:600; background: <?= $curStatus === 'Absent' ? '#fee2e2; color:#991b1b' : ($curStatus === 'OD' ? '#e0e7ff; color:#3730a3' : ($curStatus === 'Late' ? '#fef3c7; color:#92400e' : '#dcfce7; color:#166534')) ?>;">
                          <option value="Present" <?= $curStatus === 'Present' ? 'selected' : '' ?>>Present</option>
                          <option value="Absent" <?= $curStatus === 'Absent' ? 'selected' : '' ?>>Absent</option>
                          <option value="Late" <?= $curStatus === 'Late' ? 'selected' : '' ?>>Late</option>
                          <option value="OD" <?= $curStatus === 'OD' ? 'selected' : '' ?>>OD (On Duty)</option>
                        </select>
                        <input type="hidden" name="matrix[<?= $s['id'] ?>][<?= $p ?>][subject_code]" value="<?= e($subCode) ?>">
                      </div>
                      <?php if ($staffName): ?>
                        <small style="font-size:10px; color:#64748b;" title="Logged by <?= e($staffName) ?>">By: <?= e(explode(' ', $staffName)[0]) ?></small>
                      <?php endif; ?>
                    </td>
                  <?php endfor; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div style="margin-top: 20px; display:flex; justify-content: flex-end;">
          <button class="btn primary wide" style="max-width: 320px;">Save 7-Period Attendance & Update %</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

<?php elseif ($mode === 'subject'): ?>

  <!-- SUBJECT FACULTY SINGLE PERIOD WORKSPACE -->
  <div class="card">
    <h3>Subject Faculty Period Attendance Upload</h3>
    <p class="sub">Select Period Number (1–7) & Subject Code. One click loads all students for the selected Year & Section. Mark students <b>Present</b>, <b>Absent</b>, or <b>Late</b>. Absences automatically sync to the Class Tutor sheet for OD conversion.</p>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="log_subject_period">
      <input type="hidden" name="department" value="<?= e($dept) ?>">
      <input type="hidden" name="class_name" value="<?= e($className) ?>">
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <input type="hidden" name="year" value="<?= $year ?>">
      <input type="hidden" name="sem" value="<?= $sem ?>">

      <div class="formgrid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 20px;">
        <label>Select Hour / Period Number
          <select name="period_number">
            <?php for ($p = 1; $p <= 7; $p++): ?>
              <option value="<?= $p ?>">Period <?= $p ?></option>
            <?php endfor; ?>
          </select>
        </label>

        <label>Subject Code / Name
          <input name="subject_code" required placeholder="e.g. CS501 / MA301 / CS701" value="CS501">
        </label>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <strong>Student List (<?= count($students) ?> Loaded)</strong>
        <button type="button" class="btn secondary sm" onclick="markSubjectAllPresent()">Set All Present</button>
      </div>

      <?php if (empty($students)): ?>
        <p class="muted">No students found for this selection.</p>
      <?php else: ?>
        <table class="table" style="font-size: 14px;">
          <thead>
            <tr>
              <th>Student Roll</th>
              <th>Student Name</th>
              <th>Year / Sem</th>
              <th>Attendance Status for Period</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $s): ?>
              <tr>
                <td><strong><?= e($s['student_code']) ?></strong></td>
                <td><?= e($s['name']) ?></td>
                <td><span class="pill slate" style="font-size:11px;">Year <?= e($s['year']) ?> (Sem <?= e($s['semester']) ?>)</span></td>
                <td>
                  <div style="display:flex; gap: 16px;">
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:600; color:var(--green);">
                      <input type="radio" class="sub-radio-pres" name="status_map[<?= $s['id'] ?>]" value="Present" checked> Present
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:600; color:var(--red);">
                      <input type="radio" name="status_map[<?= $s['id'] ?>]" value="Absent"> Absent
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:600; color:var(--amber);">
                      <input type="radio" name="status_map[<?= $s['id'] ?>]" value="Late"> Late
                    </label>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div style="margin-top: 20px; text-align: right;">
          <button class="btn primary">Submit Period Attendance to Tutor Sheet</button>
        </div>
      <?php endif; ?>
    </form>
  </div>

<?php else: ?>

  <!-- ASSIGNMENTS MANAGER FOR 2ND, 3RD, 4TH YEAR STUDENTS -->
  <div class="card">
    <h3>Assignments Manager for 2nd, 3rd, 4th Year Students</h3>
    <p class="sub">Post common assignments to students across Year 2, Year 3, and Year 4. Specify subject code, target year group, and due date.</p>

    <form method="post" class="formgrid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_assignment">

      <label style="grid-column: 1 / -1;">Assignment Title
        <input name="title" required placeholder="e.g. Mini Project 1: Microservices Architecture & SQL Schema">
      </label>

      <label>Department
        <select name="department">
          <?php foreach ($departments as $d): ?>
            <option value="<?= e($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
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

      <label>Target Year
        <select name="year">
          <option value="2">2nd Year (Sem 3 & 4)</option>
          <option value="3" selected>3rd Year (Sem 5 & 6)</option>
          <option value="4">4th Year (Sem 7 & 8)</option>
        </select>
      </label>

      <label>Semester
        <select name="semester">
          <option value="3">Semester 3</option>
          <option value="4">Semester 4</option>
          <option value="5" selected>Semester 5</option>
          <option value="6">Semester 6</option>
          <option value="7">Semester 7</option>
          <option value="8">Semester 8</option>
        </select>
      </label>

      <label>Subject Code
        <input name="subject_code" required placeholder="e.g. CS501" value="CS501">
      </label>

      <label>Due Date
        <input type="date" name="due_date" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
      </label>

      <label style="grid-column: 1 / -1;">Description / Instructions
        <textarea name="description" rows="3" placeholder="Provide assignment requirements, submission link, and evaluation criteria..."></textarea>
      </label>

      <div style="grid-column: 1 / -1; text-align: right;">
        <button class="btn primary">Post Assignment to Students</button>
      </div>
    </form>

    <h4>Posted Assignments (<?= count($assignmentsList) ?>)</h4>
    <?php if (empty($assignmentsList)): ?>
      <p class="muted">No assignments posted for this section yet.</p>
    <?php else: ?>
      <div class="scroll">
        <table class="table" style="font-size: 13px;">
          <thead>
            <tr>
              <th>Title & Subject</th>
              <th>Target Year/Sem</th>
              <th>Section</th>
              <th>Posted By</th>
              <th>Due Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($assignmentsList as $asg): ?>
              <tr>
                <td>
                  <strong><?= e($asg['title']) ?></strong><br>
                  <small class="muted">Subject: <b><?= e($asg['subject_code']) ?></b></small>
                </td>
                <td><span class="pill blue">Year <?= e($asg['year']) ?> (Sem <?= e($asg['semester']) ?>)</span></td>
                <td><?= e($asg['department']) ?> - <?= e($asg['class_name']) ?></td>
                <td><?= e($asg['creator_name']) ?></td>
                <td><strong style="color:var(--primary);"><?= e($asg['due_date']) ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php endif; ?>

<script>
function markAllPresent() {
  document.querySelectorAll('.status-select').forEach(function(sel) {
    sel.value = 'Present';
    sel.style.background = '#dcfce7';
    sel.style.color = '#166534';
  });
}

function convertAbsentsToOD() {
  document.querySelectorAll('.status-select').forEach(function(sel) {
    if (sel.value === 'Absent') {
      sel.value = 'OD';
      sel.style.background = '#e0e7ff';
      sel.style.color = '#3730a3';
    }
  });
}

function markSubjectAllPresent() {
  document.querySelectorAll('.sub-radio-pres').forEach(function(r) {
    r.checked = true;
  });
}

// Update select style on change dynamically
document.querySelectorAll('.status-select').forEach(function(sel) {
  sel.addEventListener('change', function() {
    if (this.value === 'Absent') {
      this.style.background = '#fee2e2';
      this.style.color = '#991b1b';
    } else if (this.value === 'OD') {
      this.style.background = '#e0e7ff';
      this.style.color = '#3730a3';
    } else if (this.value === 'Late') {
      this.style.background = '#fef3c7';
      this.style.color = '#92400e';
    } else {
      this.style.background = '#dcfce7';
      this.style.color = '#166534';
    }
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
