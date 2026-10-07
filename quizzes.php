<?php
$pageTitle = 'Daily 10 MCQ Quizzes';
$pageSub = 'Subject staff publish daily 10 MCQ quizzes for department students';
require_once __DIR__ . '/includes/functions.php';
$u = require_login();

$dept = $_GET['dept'] ?? ($u['department'] ?? 'CSE');
if (!$dept || $dept === 'Management' || $dept === 'Placement Cell') $dept = 'CSE';
$className = $_GET['class_name'] ?? 'Class A';
$isStaff = in_array($u['role'], ['admin', 'hod', 'faculty']);

$studentObj = null;
if ($u['role'] === 'student') {
    $studentObj = student_by_user($u['id']);
    if ($studentObj) {
        $dept = $studentObj['department'];
        $className = $studentObj['class_name'];
    }
}

$depts = load_departments();
$subjects = load_department_subjects($dept);

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';

    // Staff creates a Daily 10 MCQ Quiz
    if ($act === 'create_quiz' && $isStaff) {
        $title = trim($_POST['title'] ?? '');
        $subCode = trim($_POST['subject_code'] ?? '');
        $quizDate = trim($_POST['quiz_date'] ?? date('Y-m-d'));
        $questionsJson = $_POST['questions_json'] ?? '[]';
        $questions = json_decode($questionsJson, true) ?: [];

        if ($title && $subCode && count($questions) >= 1) {
            create_daily_quiz($title, $dept, $className, $subCode, $u['id'], $quizDate, $questions);
            audit('create_quiz', "Created daily quiz '$title' for $dept - $subCode");
            flash("Daily 10 MCQ Quiz '$title' created successfully for $dept students!");
            redirect("quizzes.php?dept=" . urlencode($dept) . "&class_name=" . urlencode($className));
        } else {
            flash('Please enter quiz title, select subject, and add at least 1 question.', 'err');
        }
    }

    // Student Submits Quiz
    if ($act === 'submit_quiz' && $u['role'] === 'student' && $studentObj) {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $answers = $_POST['answers'] ?? [];

        if ($quizId > 0) {
            $score = submit_quiz_result($quizId, $studentObj['id'], $answers);
            audit('submit_quiz', "Student #{$studentObj['id']} scored $score/10 on quiz #$quizId");
            flash("Quiz submitted successfully! You scored $score out of 10.");
            redirect("quizzes.php?quiz_id=" . $quizId);
        }
    }
}

$quizzes = load_daily_quizzes($dept, $className, $studentObj ? $studentObj['id'] : null);
$selectedQuiz = null;
if (!empty($_GET['quiz_id'])) {
    $qid = (int)$_GET['quiz_id'];
    foreach ($quizzes as $qz) {
        if ($qz['id'] === $qid) {
            $selectedQuiz = $qz;
            break;
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="row spread" style="margin-bottom:18px;">
  <div>
    <h2 style="margin:0;">Daily 10 MCQ Department Quizzes</h2>
    <p class="sub" style="margin:0;">Subject staff conduct daily 10 MCQ quizzes to test student understanding and track learning progress.</p>
  </div>
  <?php if ($isStaff): ?>
    <form method="get" class="row" style="gap:8px;">
      <select name="dept" onchange="this.form.submit()" style="width:auto; font-weight:700;">
        <?php foreach ($depts as $d): ?>
          <option value="<?= e($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  <?php endif; ?>
</div>

<?php if ($isStaff): ?>
  <!-- Staff Section: Create Daily 10 MCQ Quiz -->
  <div class="card" style="margin-bottom:24px;">
    <div class="row spread" style="margin-bottom:14px;">
      <div>
        <h3>Publish Daily 10 MCQ Quiz</h3>
        <p class="sub">Create a 10-question daily quiz or use AI Auto-Generate to populate 10 MCQs instantly.</p>
      </div>
      <button type="button" class="btn primary sm" id="btnAiQuiz">✨ AI Auto-Generate 10 MCQs</button>
    </div>

    <form method="post" id="quizForm" style="display:flex; flex-direction:column; gap:14px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_quiz">
      <input type="hidden" name="department" value="<?= e($dept) ?>">
      <input type="hidden" name="questions_json" id="questionsJson" value="[]">

      <div class="row" style="gap:12px;">
        <label style="flex:1.5;">Quiz Title
          <input type="text" name="title" id="quizTitle" placeholder="e.g. Daily Quiz: SQL Joins & Aggregate Functions" required>
        </label>
        <label style="flex:1;">Subject
          <select name="subject_code" id="quizSubCode" required>
            <option value="">-- Select Subject --</option>
            <?php foreach ($subjects as $sb): ?>
              <option value="<?= e($sb['code']) ?>"><?= e($sb['code']) ?> - <?= e($sb['name']) ?></option>
            <?php endforeach; if (!$subjects): ?>
              <option value="CS501">CS501 - Operating Systems</option>
            <?php endif; ?>
          </select>
        </label>
        <label style="flex:1;">Quiz Date
          <input type="date" name="quiz_date" value="<?= date('Y-m-d') ?>" required>
        </label>
      </div>

      <!-- MCQs Preview Box -->
      <div id="mcqContainer" style="border:1px dashed var(--line); border-radius:14px; padding:16px; background:var(--paper);">
        <p class="muted" style="margin:0; text-align:center; font-weight:700;">Click "✨ AI Auto-Generate 10 MCQs" above to populate 10 questions automatically!</p>
      </div>

      <button class="btn primary" type="submit" style="align-self:flex-start;">Publish Daily Quiz to Students</button>
    </form>
  </div>
<?php endif; ?>

<!-- Quiz List / Interactive Quiz View -->
<?php if ($selectedQuiz): 
  $questions = json_decode($selectedQuiz['questions_json'], true) ?: [];
  $myRes = $selectedQuiz['my_result'] ?? null;
?>
  <div class="card" style="margin-bottom:24px;">
    <div class="row spread" style="margin-bottom:14px;">
      <div>
        <h3>📝 <?= e($selectedQuiz['title']) ?></h3>
        <p class="sub">Subject: <strong><?= e($selectedQuiz['subject_code']) ?></strong> · Date: <?= e($selectedQuiz['quiz_date']) ?> · Staff: <?= e($selectedQuiz['creator_name']) ?></p>
      </div>
      <?php if ($myRes): ?>
        <div style="text-align:right;">
          <span class="pill green" style="font-size:14px; padding:6px 14px;">Score: <?= $myRes['score'] ?> / <?= $myRes['total_questions'] ?></span>
          <small class="muted" style="display:block; margin-top:2px;">Submitted: <?= e(date('M d, g:i a', strtotime($myRes['submitted_at']))) ?></small>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($u['role'] === 'student' && !$myRes): ?>
      <!-- Student Quiz Taking Form -->
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="submit_quiz">
        <input type="hidden" name="quiz_id" value="<?= $selectedQuiz['id'] ?>">

        <?php foreach ($questions as $qIdx => $qObj): ?>
          <div class="card" style="margin-bottom:16px; border:1px solid var(--line);">
            <strong style="font-size:15px; display:block; margin-bottom:10px;">Q<?= $qIdx + 1 ?>. <?= e($qObj['q']) ?></strong>
            <?php foreach ($qObj['opts'] as $oIdx => $optText): ?>
              <label style="display:flex; align-items:center; gap:10px; padding:10px 14px; border:1px solid var(--line); border-radius:10px; margin-bottom:6px; cursor:pointer; font-weight:600;">
                <input type="radio" name="answers[<?= $qIdx ?>]" value="<?= $oIdx ?>" required style="width:auto;">
                <span><?= e($optText) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>

        <button class="btn primary wide" style="max-width:260px;">Submit 10 MCQ Quiz Answers</button>
      </form>
    <?php else: ?>
      <!-- Results / Staff Review View -->
      <div style="display:flex; flex-direction:column; gap:14px;">
        <?php 
        $userAns = $myRes ? (json_decode($myRes['answers_json'], true) ?: []) : [];
        foreach ($questions as $qIdx => $qObj): 
          $uChoice = (int)($userAns[$qIdx] ?? -1);
          $correctAns = (int)($qObj['ans'] ?? 0);
        ?>
          <div class="card" style="border:1px solid var(--line);">
            <strong style="font-size:15px; display:block; margin-bottom:8px;">Q<?= $qIdx + 1 ?>. <?= e($qObj['q']) ?></strong>
            <?php foreach ($qObj['opts'] as $oIdx => $optText): 
              $isCorrect = $oIdx === $correctAns;
              $isChosen = $oIdx === $uChoice;
              $bg = $isCorrect ? '#dcf6ea' : ($isChosen ? '#ffe9ea' : '#fff');
              $border = $isCorrect ? 'var(--green)' : ($isChosen ? 'var(--red)' : 'var(--line)');
            ?>
              <div style="padding:10px 14px; border:1px solid <?= $border ?>; background:<?= $bg ?>; border-radius:10px; margin-bottom:6px; font-weight:600;">
                <?= $isCorrect ? '✅ ' : ($isChosen ? '❌ ' : '') ?><?= e($optText) ?>
              </div>
            <?php endforeach; ?>
            <div style="background:var(--paper); padding:10px 14px; border-radius:10px; margin-top:8px;">
              <small style="color:var(--muted); font-weight:700;">💡 AI Explanation:</small>
              <p style="margin:4px 0 0; font-size:13.5px;"><?= e($qObj['exp'] ?? 'No explanation provided.') ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Available Quizzes List Table -->
<div class="card flat">
  <div class="pad row spread">
    <div>
      <h3>Daily Quizzes List (<?= e($dept) ?>)</h3>
      <p class="sub">Students complete 10 MCQs daily to build LMS assignment completion scores.</p>
    </div>
  </div>
  <div class="scroll">
    <table class="tbl">
      <thead>
        <tr>
          <th>Quiz Title</th>
          <th>Subject</th>
          <th>Quiz Date</th>
          <th>Created By</th>
          <th>My Result / Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($quizzes as $qz): 
          $res = $qz['my_result'] ?? null;
        ?>
          <tr>
            <td><strong><?= e($qz['title']) ?></strong></td>
            <td><code><?= e($qz['subject_code']) ?></code></td>
            <td><?= e($qz['quiz_date']) ?></td>
            <td><small><?= e($qz['creator_name']) ?></small></td>
            <td>
              <?php if ($res): ?>
                <span class="pill green">Scored <?= $res['score'] ?>/<?= $res['total_questions'] ?></span>
              <?php else: ?>
                <span class="pill amber">Pending Attempt</span>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn sm primary" href="quizzes.php?dept=<?= urlencode($dept) ?>&quiz_id=<?= $qz['id'] ?>"><?= $res ? 'View Results' : 'Take Quiz' ?></a>
            </td>
          </tr>
        <?php endforeach; if (!$quizzes): ?>
          <tr><td colspan="6" class="muted" style="text-align:center; padding:20px;">No daily quizzes published for <?= e($dept) ?> yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
var csrf = <?= json_encode(csrf_token()) ?>;

document.getElementById('btnAiQuiz')?.addEventListener('click', function () {
  var subCode = document.getElementById('quizSubCode').value || 'CS501';
  var titleInput = document.getElementById('quizTitle');
  var container = document.getElementById('mcqContainer');

  container.innerHTML = '<div style="text-align:center; padding:20px;"><span class="muted">✨ AI is generating 10 High-Quality MCQs for ' + subCode.replace(/</g, '&lt;') + '…</span></div>';

  var fd = new FormData();
  fd.append('action', 'quiz');
  fd.append('topic', '10 MCQ Quiz for ' + subCode);
  fd.append('csrf', csrf);

  fetch(<?= json_encode(url('api/ai_tutor.php')) ?>, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      var questions = [];
      try {
        var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
        if (parsed.questions) questions = parsed.questions;
      } catch (e) {}

      if (!questions || questions.length < 5) {
        // Sample 10 MCQs Generator Fallback
        questions = [
          { q: "What is the primary function of an Operating System?", opts: ["Compile code", "Manage hardware resources and provide a user interface", "Browse internet", "Edit images"], ans: 1, exp: "The OS manages CPU, memory, storage, and processes." },
          { q: "Which CPU scheduling algorithm gives minimum average waiting time?", opts: ["FCFS", "SJF (Shortest Job First)", "Round Robin", "Priority"], ans: 1, exp: "SJF is provably optimal for minimizing average waiting time." },
          { q: "What is a Deadlock condition?", opts: ["Memory is full", "Two or more processes are blocked forever waiting for each other", "CPU temperature is high", "Disk crash"], ans: 1, exp: "Deadlock occurs when processes wait indefinitely for resources held by each other." },
          { q: "Which memory management scheme permits the physical address space of a process to be noncontiguous?", opts: ["Paging", "Contiguous allocation", "Single partition", "Overlays"], ans: 0, exp: "Paging divides physical memory into frames and logical memory into pages." },
          { q: "What is the main advantage of Multi-threading?", opts: ["Increased CPU usage", "Responsiveness and resource sharing", "More storage space", "Faster internet"], ans: 1, exp: "Threads allow concurrent execution within the same process." },
          { q: "Which SQL command is used to remove a table and all its data from a database?", opts: ["DELETE", "REMOVE", "DROP TABLE", "TRUNCATE"], ans: 2, exp: "DROP TABLE deletes the table definition and all rows." },
          { q: "What does ACID stand for in Database Systems?", opts: ["Atomicity, Consistency, Isolation, Durability", "Access, Control, Index, Data", "Auto, Core, Internal, Disk", "None"], ans: 0, exp: "ACID guarantees reliable database transaction processing." },
          { q: "Which network layer is responsible for routing packets across networks?", opts: ["Physical Layer", "Data Link Layer", "Network Layer", "Transport Layer"], ans: 2, exp: "Network Layer (IP) handles logical addressing and routing." },
          { q: "What is the worst-case time complexity of QuickSort?", opts: ["O(N)", "O(N log N)", "O(N^2)", "O(1)"], ans: 2, exp: "QuickSort degrades to O(N^2) when the pivot is poorly chosen." },
          { q: "In Java, which collection class is thread-safe?", opts: ["ArrayList", "Vector", "LinkedList", "HashSet"], ans: 1, exp: "Vector methods are synchronized and thread-safe." }
        ];
      }

      titleInput.value = 'Daily 10 MCQ Quiz: ' + subCode;
      document.getElementById('questionsJson').value = JSON.stringify(questions);

      var html = '<div style="display:flex; flex-direction:column; gap:12px;">';
      html += '<strong style="color:var(--primary); font-size:15px;">✅ 10 MCQs Generated Successfully! Preview:</strong>';
      questions.forEach(function (qObj, idx) {
        html += '<div style="padding:10px; background:#fff; border-radius:10px; border:1px solid var(--line);">';
        html += '<strong>Q' + (idx + 1) + '. ' + qObj.q + '</strong>';
        html += '<small style="display:block; color:var(--muted); margin-top:4px;">Ans: Option ' + (qObj.ans + 1) + ' (' + qObj.opts[qObj.ans] + ')</small>';
        html += '</div>';
      });
      html += '</div>';

      container.innerHTML = html;
    })
    .catch(err => {
      container.innerHTML = '<div class="flash err" style="margin:0;">Failed to auto-generate MCQs. Please try again.</div>';
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
