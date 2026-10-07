<?php
$pageTitle = 'AI Copilot'; $pageSub = 'Ask questions about your students in plain English';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'hod', 'faculty', 'placement']);
include __DIR__ . '/includes/header.php';
$qs = ['Which students need immediate intervention?', 'Which students have placement gaps?', 'Which students have low attendance?', 'What is the biggest risk factor?', 'Which skills are weakest?', 'Tell me about ST1001'];
?>
<div class="card">
  <div class="chat" id="chat"><div class="msg a">Hi, I'm your campus analytics copilot. I answer from live student data. Pick a question or type your own.</div></div>
  <div class="suggest" style="margin:16px 0"><?php foreach ($qs as $q): ?><button class="chip" type="button" data-q="<?= e($q) ?>"><?= e($q) ?></button><?php endforeach; ?></div>
  <form id="f" class="row" style="flex-wrap:nowrap"><input id="q" placeholder="Ask about risk, attendance, placement, skills…" autocomplete="off"><button class="btn primary">Ask</button></form>
</div>
<script>
var chat = document.getElementById('chat'), csrf = <?= json_encode(csrf_token()) ?>;
function add(cls, html) { var d = document.createElement('div'); d.className = 'msg ' + cls; d.innerHTML = html; chat.appendChild(d); chat.scrollTop = chat.scrollHeight; return d; }
function ask(q) {
  if (!q) return; add('u', q.replace(/</g, '&lt;')); var w = add('a', 'Thinking…');
  var fd = new FormData(); fd.append('q', q); fd.append('csrf', csrf);
  fetch(<?= json_encode(url('api/copilot.php')) ?>, {method: 'POST', body: fd}).then(function (r) { return r.json(); })
    .then(function (j) { w.innerHTML = j.answer; chat.scrollTop = chat.scrollHeight; }).catch(function () { w.textContent = 'Something went wrong. Try again.'; });
}
document.getElementById('f').addEventListener('submit', function (e) { e.preventDefault(); var i = document.getElementById('q'); ask(i.value.trim()); i.value = ''; });
document.querySelectorAll('.chip').forEach(function (c) { c.addEventListener('click', function () { ask(c.dataset.q); }); });
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
