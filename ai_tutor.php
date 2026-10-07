<?php
$pageTitle = 'AI Learning Assistant';
$pageSub = 'Personalized AI Content Tutor, Concept Explainer, Quiz Generator & Interview Drill';
require_once __DIR__ . '/includes/functions.php';
$u = require_login();

// Load Student Context
$s = null;
$lowMarks = [];
$topGaps = [];

if ($u['role'] === 'student') {
    $s = student_by_user((int)$u['id']);
} elseif (!empty($_GET['student_id'])) {
    $r = load_students((int)$_GET['student_id']);
    $s = $r[0] ?? null;
}

if ($s) {
    // Subject marks
    $sm = load_subject_marks($s['id']);
    foreach ($sm as $m) {
        if ((float)$m['total_pct'] < 60) {
            $lowMarks[] = $m;
        }
    }
    // Skill gaps
    if (!empty($s['gaps'])) {
        $topGaps = array_filter($s['gaps'], fn($g) => $g['gap'] > 0);
        usort($topGaps, fn($a, $b) => $b['gap'] <=> $a['gap']);
    }
}

include __DIR__ . '/includes/header.php';
?>

<style>
.tutor-container { display: flex; flex-direction: column; gap: 20px; }
.tutor-nav { display: flex; gap: 10px; border-bottom: 2px solid var(--line); padding-bottom: 12px; margin-bottom: 8px; flex-wrap: wrap; }
.tutor-tab { padding: 9px 18px; border-radius: 12px; font-weight: 700; cursor: pointer; border: 1px solid var(--line); background: var(--card); color: var(--text); transition: all .15s; }
.tutor-tab:hover { background: var(--soft); color: var(--primary); }
.tutor-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 4px 12px rgba(91,75,255,.25); }
.tutor-card { background: var(--card); border-radius: 20px; padding: 24px; box-shadow: var(--shadow); border: 1px solid var(--line); }
.quiz-opt { padding: 12px 16px; border: 1px solid var(--line); border-radius: 12px; margin-bottom: 8px; cursor: pointer; font-weight: 600; transition: background .15s, border-color .15s; }
.quiz-opt:hover { background: var(--soft); border-color: var(--primary); }
.quiz-opt.selected { background: var(--soft); border-color: var(--primary); color: var(--primary); font-weight: 700; }
.quiz-opt.correct { background: #dcf6ea; border-color: var(--green); color: #0b7a4b; }
.quiz-opt.wrong { background: #ffe9ea; border-color: var(--red); color: #c4262c; }
.chip-btn { border: 1px solid var(--line); background: #fff; border-radius: 99px; padding: 6px 14px; font: 700 12.5px Manrope,sans-serif; cursor: pointer; transition: all .15s; }
.chip-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--soft); }
</style>

<div class="tutor-container">

  <!-- Personalized AI Recommendation Banner -->
  <div class="card" style="background: linear-gradient(135deg, #0d1130 0%, #161b45 100%); color: #fff; border: 0;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
      <div>
        <h2 style="font-size:22px; margin-bottom:6px; color:#fff;">🤖 AI Learning Copilot for <?= e($s ? $s['name'] : $user['name']) ?></h2>
        <p style="color:#aab0e0; margin:0;">Targeted content learning, interactive quizzes, and placement prep powered by explainable AI.</p>
      </div>
      <?php if ($s && (!empty($lowMarks) || !empty($topGaps))): ?>
        <div style="background: rgba(255,255,255,0.08); padding:10px 16px; border-radius:14px; border:1px solid rgba(255,255,255,0.12);">
          <small style="color:#aab0e0; font-weight:700; display:block; margin-bottom:4px;">🎯 Recommended Focus Topics:</small>
          <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <?php foreach (array_slice($lowMarks, 0, 2) as $lm): ?>
              <span class="pill red"><?= e($lm['subject_code']) ?> (<?= f0($lm['total_pct']) ?>%)</span>
            <?php endforeach; ?>
            <?php foreach (array_slice($topGaps, 0, 2) as $tg): ?>
              <span class="pill amber"><?= e($tg['skill']) ?> (Gap: <?= $tg['gap'] ?>)</span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Mode Switcher -->
  <div class="tutor-nav">
    <button class="tutor-tab active" data-mode="explain">🧠 Concept Explainer</button>
    <button class="tutor-tab" data-mode="quiz">📝 AI Quiz Generator</button>
    <button class="tutor-tab" data-mode="prep">⚡ Placement Drill</button>
    <button class="tutor-tab" data-mode="chat">💬 Ask AI Tutor</button>
  </div>

  <!-- Topic & Quick Suggestion Bar -->
  <div class="tutor-card">
    <label style="margin-bottom:8px;">Choose Topic or Subject to Learn:</label>
    <div class="row" style="flex-wrap:nowrap; gap:10px; margin-bottom:12px;">
      <input type="text" id="topicInput" placeholder="e.g. Binary Search Trees, SQL Joins, Speed & Distance, Java OOP..." value="Binary Search Tree">
      <button type="button" class="btn primary" id="btnLearn" style="white-space:nowrap;">Generate AI Content</button>
    </div>

    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
      <small class="muted" style="font-weight:700;">Popular Topics:</small>
      <button class="chip-btn" data-t="Binary Search Tree">Binary Search Trees</button>
      <button class="chip-btn" data-t="SQL Joins & DBMS">SQL Joins & Queries</button>
      <button class="chip-btn" data-t="Java Multithreading & OOP">Java Multithreading</button>
      <button class="chip-btn" data-t="Speed, Distance & Time Aptitude">Speed & Distance</button>
      <button class="chip-btn" data-t="HR Interview STAR Technique">Interview Prep</button>
    </div>
  </div>

  <!-- Output Display Area -->
  <div class="tutor-card" id="outputCard">
    <div id="outputContent">
      <div style="text-align:center; padding:40px; color:var(--muted);">
        <p style="font-size:16px; font-weight:700;">Click "Generate AI Content" or select a topic above to begin learning!</p>
      </div>
    </div>
  </div>

</div>

<script>
var csrf = <?= json_encode(csrf_token()) ?>;
var currentMode = 'explain';

// Mode Switching
document.querySelectorAll('.tutor-tab').forEach(function (tab) {
  tab.addEventListener('click', function () {
    document.querySelectorAll('.tutor-tab').forEach(t => t.classList.remove('active'));
    this.classList.add('active');
    currentMode = this.dataset.mode;
    loadContent();
  });
});

// Quick Topic Chips
document.querySelectorAll('.chip-btn').forEach(function (chip) {
  chip.addEventListener('click', function () {
    document.getElementById('topicInput').value = this.dataset.t;
    loadContent();
  });
});

document.getElementById('btnLearn').addEventListener('click', function () {
  loadContent();
});

function loadContent() {
  var topic = document.getElementById('topicInput').value.trim();
  if (!topic) topic = 'Binary Search Tree';

  var out = document.getElementById('outputContent');
  out.innerHTML = '<div style="text-align:center; padding:30px;"><div class="muted">🤖 AI Tutor is generating personalized content for "' + topic.replace(/</g, '&lt;') + '"…</div></div>';

  var fd = new FormData();
  fd.append('action', currentMode);
  fd.append('topic', topic);
  fd.append('q', topic);
  fd.append('csrf', csrf);

  fetch(<?= json_encode(url('api/ai_tutor.php')) ?>, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (currentMode === 'quiz') {
        renderQuiz(data, topic);
      } else {
        out.innerHTML = data.answer || '<p>Unable to generate content. Please try again.</p>';
      }
    })
    .catch(err => {
      out.innerHTML = '<div class="flash err">Failed to load content. Please check connection.</div>';
    });
}

function renderQuiz(data, topic) {
  var out = document.getElementById('outputContent');
  var questions = [];

  try {
    var parsed = (typeof data === 'string') ? JSON.parse(data) : data;
    if (parsed.questions) questions = parsed.questions;
  } catch (e) {}

  if (!questions || !questions.length) {
    questions = [
      { q: "What is the time complexity of searching an element in a balanced BST?", opts: ["O(1)", "O(log N)", "O(N)", "O(N^2)"], ans: 1, exp: "In a balanced BST, search space is halved at each step." },
      { q: "Which SQL clause is used to filter aggregated data?", opts: ["WHERE", "HAVING", "GROUP BY", "WHERE GROUP"], ans: 1, exp: "HAVING filters aggregate groups." }
    ];
  }

  var html = '<h3>📝 AI Practice Quiz: ' + (topic.replace(/</g, '&lt;')) + '</h3>';
  html += '<p class="muted">Select your answer for each question to see instant feedback and AI explanations.</p><hr style="border:0; border-top:1px solid var(--line); margin:16px 0;">';

  questions.forEach(function (qObj, qIdx) {
    html += '<div class="card" style="margin-bottom:18px; border:1px solid var(--line);" id="qbox_' + qIdx + '">';
    html += '<strong style="font-size:15px; display:block; margin-bottom:12px;">Q' + (qIdx + 1) + '. ' + qObj.q + '</strong>';
    qObj.opts.forEach(function (opt, oIdx) {
      html += '<div class="quiz-opt" onclick="checkAnswer(' + qIdx + ',' + oIdx + ',' + qObj.ans + ',\'' + qObj.exp.replace(/'/g, "\\'") + '\')">' + opt + '</div>';
    });
    html += '<div id="qexp_' + qIdx + '" style="margin-top:10px; display:none;"></div>';
    html += '</div>';
  });

  out.innerHTML = html;
}

function checkAnswer(qIdx, selectedIdx, correctIdx, expText) {
  var qbox = document.getElementById('qbox_' + qIdx);
  var opts = qbox.querySelectorAll('.quiz-opt');

  opts.forEach(function (optEl, idx) {
    optEl.onclick = null; // disable further clicks
    if (idx === correctIdx) {
      optEl.classList.add('correct');
    } else if (idx === selectedIdx) {
      optEl.classList.add('wrong');
    }
  });

  var expDiv = document.getElementById('qexp_' + qIdx);
  expDiv.style.display = 'block';
  if (selectedIdx === correctIdx) {
    expDiv.innerHTML = '<div class="flash ok" style="margin:0;">✅ <strong>Correct!</strong> ' + expText + '</div>';
  } else {
    expDiv.innerHTML = '<div class="flash err" style="margin:0;">❌ <strong>Incorrect.</strong> ' + expText + '</div>';
  }
}

// Automatically load default concept on page load
window.addEventListener('DOMContentLoaded', function () {
  loadContent();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
