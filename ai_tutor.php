<?php
$pageTitle = 'AI Learning Copilot (GPT-4 Tutor)';
$pageSub = 'ChatGPT-powered interactive AI learning studio, concept explainer, quiz generator & mock interviewer';
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
    // Low subject marks
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

<!-- Marked.js for ChatGPT-style Markdown rendering -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<style>
.gpt-wrapper { display: flex; flex-direction: column; gap: 20px; }
.gpt-banner { background: linear-gradient(135deg, #0d1130 0%, #161b45 100%); color: #fff; border-radius: 20px; padding: 24px; box-shadow: var(--shadow); border: 1px solid var(--line); }
.gpt-mode-bar { display: flex; gap: 10px; border-bottom: 2px solid var(--line); padding-bottom: 12px; flex-wrap: wrap; }
.gpt-tab { padding: 10px 20px; border-radius: 12px; font-weight: 700; cursor: pointer; border: 1px solid var(--line); background: var(--card); color: var(--text); transition: all .15s; }
.gpt-tab:hover { background: var(--soft); color: var(--primary); }
.gpt-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 4px 14px rgba(91,75,255,.3); }

/* Chat Box Styling */
.chat-window { background: var(--card); border-radius: 20px; border: 1px solid var(--line); box-shadow: var(--shadow); display: flex; flex-direction: column; height: 580px; overflow: hidden; }
.chat-messages { flex: 1; padding: 24px; overflow-y: auto; display: flex; flex-direction: column; gap: 18px; }
.msg-bubble { display: flex; gap: 14px; max-width: 88%; align-items: flex-start; }
.msg-bubble.user { margin-left: auto; flex-direction: row-reverse; }
.msg-avatar { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; font-size: 14px; flex-shrink: 0; }
.msg-bubble.ai .msg-avatar { background: linear-gradient(135deg, var(--primary), #7f72ff); color: #fff; }
.msg-bubble.user .msg-avatar { background: var(--ink); color: #fff; }
.msg-content { background: #f8f9fe; border: 1px solid var(--line); padding: 16px 20px; border-radius: 18px; color: var(--text); font-size: 14.5px; line-height: 1.6; }
.msg-bubble.user .msg-content { background: var(--primary); color: #fff; border-color: var(--primary); border-top-right-radius: 4px; }
.msg-bubble.ai .msg-content { border-top-left-radius: 4px; background: #ffffff; }

/* Markdown & Code Blocks */
.msg-content h1, .msg-content h2, .msg-content h3 { margin-top: 10px; margin-bottom: 8px; color: inherit; }
.msg-content p { margin-bottom: 10px; }
.msg-content ul, .msg-content ol { margin-left: 20px; margin-bottom: 10px; }
.msg-content pre { background: #0d1130; color: #f1f2f8; padding: 14px; border-radius: 12px; overflow-x: auto; font-family: monospace; font-size: 13.5px; margin: 10px 0; position: relative; }
.msg-content code { background: rgba(91,75,255,0.08); color: var(--primary); padding: 2px 6px; border-radius: 6px; font-family: monospace; font-size: 0.9em; }
.msg-bubble.user .msg-content code { background: rgba(255,255,255,0.2); color: #fff; }

.chat-input-bar { border-top: 1px solid var(--line); padding: 16px 20px; background: #fafaff; display: flex; gap: 12px; align-items: center; }
.chat-input-bar input { flex: 1; border-radius: 14px; padding: 12px 18px; border: 1px solid var(--line); background: #fff; font-size: 14.5px; }

/* Preset Chips */
.preset-chip { border: 1px solid var(--line); background: #fff; border-radius: 99px; padding: 6px 14px; font: 700 12.5px Manrope,sans-serif; cursor: pointer; transition: all .15s; }
.preset-chip:hover { border-color: var(--primary); color: var(--primary); background: var(--soft); transform: translateY(-1px); }

/* Quiz Styling */
.quiz-option { padding: 14px 18px; border: 1px solid var(--line); border-radius: 14px; margin-bottom: 10px; cursor: pointer; font-weight: 600; background: #fff; transition: all .15s; }
.quiz-option:hover { border-color: var(--primary); background: var(--soft); }
.quiz-option.correct { background: #dcf6ea; border-color: var(--green); color: #0b7a4b; font-weight: 700; }
.quiz-option.wrong { background: #ffe9ea; border-color: var(--red); color: #c4262c; }
</style>

<div class="gpt-wrapper">

  <!-- Header Banner -->
  <div class="gpt-banner">
    <div class="row spread">
      <div>
        <h2 style="font-size:24px; margin-bottom:6px; color:#fff;">🤖 SURYAMITHRA GPT — AI Student Tutor</h2>
        <p style="color:#aab0e0; margin:0;">ChatGPT-level interactive learning assistant, concept explainer, quiz engine & mock interviewer.</p>
      </div>
      <?php if ($s && (!empty($lowMarks) || !empty($topGaps))): ?>
        <div style="background: rgba(255,255,255,0.08); padding:10px 16px; border-radius:14px; border:1px solid rgba(255,255,255,0.14);">
          <small style="color:#aab0e0; font-weight:700; display:block; margin-bottom:4px;">🎯 Recommended Focus Areas:</small>
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

  <!-- Mode Selector Tabs -->
  <div class="gpt-mode-bar">
    <button class="gpt-tab active" data-mode="chat">💬 GPT Learning Assistant</button>
    <button class="gpt-tab" data-mode="explain">🧠 Deep Concept Explainer</button>
    <button class="gpt-tab" data-mode="quiz">📝 AI Quiz Studio</button>
    <button class="gpt-tab" data-mode="prep">⚡ Mock Interviewer</button>
  </div>

  <!-- Quick Suggestion Prompts -->
  <div class="card" style="padding:14px 20px;">
    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
      <small class="muted" style="font-weight:700;">💡 Recommended Prompts:</small>
      <button class="preset-chip" data-p="Explain Binary Search Trees in Java with code example">Binary Search Trees in Java</button>
      <button class="preset-chip" data-p="Explain SQL INNER JOIN vs LEFT JOIN with examples">SQL Joins Explained</button>
      <button class="preset-chip" data-p="Solve Speed, Distance & Time Aptitude problem step-by-step">Speed & Distance Formulas</button>
      <button class="preset-chip" data-p="Generate 5 SQL Quiz questions with answers">5-Question SQL Quiz</button>
      <button class="preset-chip" data-p="Mock Interview: Software Developer role">Mock Technical Interview</button>
    </div>
  </div>

  <!-- Chat Window -->
  <div class="chat-window" id="chatWindow">
    <div class="chat-messages" id="chatMessages">
      <!-- Welcome AI Message -->
      <div class="msg-bubble ai">
        <div class="msg-avatar">GPT</div>
        <div class="msg-content">
          <p><strong>Hello <?= e($s ? $s['name'] : $user['name']) ?>! 👋 I am SURYAMITHRA GPT, your personal AI tutor.</strong></p>
          <p>You can ask me to explain any topic, debug your code, generate practice quizzes, or conduct a mock interview for your target role. How can I help you today?</p>
        </div>
      </div>
    </div>

    <!-- Chat Input Form -->
    <form class="chat-input-bar" id="chatForm">
      <input type="text" id="userInput" placeholder="Ask SURYAMITHRA GPT anything (e.g. 'Explain BST', '5 SQL Quiz Questions', 'How to answer tell me about yourself?')..." autocomplete="off">
      <button type="submit" class="btn primary" style="padding:12px 24px; border-radius:12px;">Send GPT</button>
    </form>
  </div>

</div>

<script>
var csrf = <?= json_encode(csrf_token()) ?>;
var currentMode = 'chat';
var conversationHistory = [];

// Initialize Marked Parser if available
if (typeof marked !== 'undefined') {
  marked.setOptions({ gfm: true, breaks: true });
}

// Mode Selection
document.querySelectorAll('.gpt-tab').forEach(function (tab) {
  tab.addEventListener('click', function () {
    document.querySelectorAll('.gpt-tab').forEach(t => t.classList.remove('active'));
    this.classList.add('active');
    currentMode = this.dataset.mode;
  });
});

// Preset Chips
document.querySelectorAll('.preset-chip').forEach(function (chip) {
  chip.addEventListener('click', function () {
    document.getElementById('userInput').value = this.dataset.p;
    sendPrompt(this.dataset.p);
  });
});

// Submit Form
document.getElementById('chatForm').addEventListener('submit', function (e) {
  e.preventDefault();
  var input = document.getElementById('userInput');
  var val = input.value.trim();
  if (!val) return;
  sendPrompt(val);
  input.value = '';
});

function appendMessage(role, rawContent) {
  var container = document.getElementById('chatMessages');
  var bubble = document.createElement('div');
  bubble.className = 'msg-bubble ' + (role === 'user' ? 'user' : 'ai');

  var avatar = document.createElement('div');
  avatar.className = 'msg-avatar';
  avatar.textContent = role === 'user' ? 'YOU' : 'GPT';

  var content = document.createElement('div');
  content.className = 'msg-content';

  if (role === 'user') {
    content.textContent = rawContent;
  } else {
    if (typeof marked !== 'undefined') {
      content.innerHTML = marked.parse(rawContent);
    } else {
      content.innerHTML = rawContent;
    }
  }

  bubble.appendChild(avatar);
  bubble.appendChild(content);
  container.appendChild(bubble);

  container.scrollTop = container.scrollHeight;
  return content;
}

function sendPrompt(promptText) {
  appendMessage('user', promptText);

  // Push to local conversation history
  conversationHistory.push({ role: 'user', content: promptText });

  var aiContentEl = appendMessage('ai', 'Thinking… 🤖');

  var fd = new FormData();
  fd.append('action', currentMode);
  fd.append('topic', promptText);
  fd.append('q', promptText);
  fd.append('messages', JSON.stringify(conversationHistory.slice(-8))); // send last 8 turns
  fd.append('csrf', csrf);

  fetch(<?= json_encode(url('api/ai_tutor.php')) ?>, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      var ans = data.answer || 'No response generated.';
      if (typeof marked !== 'undefined') {
        aiContentEl.innerHTML = marked.parse(ans);
      } else {
        aiContentEl.innerHTML = ans;
      }

      // Add to conversation history
      conversationHistory.push({ role: 'assistant', content: ans });
      var container = document.getElementById('chatMessages');
      container.scrollTop = container.scrollHeight;
    })
    .catch(err => {
      aiContentEl.innerHTML = '<div class="flash err" style="margin:0;">Connection error. Please try again.</div>';
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
