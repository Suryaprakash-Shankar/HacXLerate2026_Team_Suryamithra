<?php
$pageTitle = 'What-if simulator'; $pageSub = 'See how the success score and risk change if a student improves';
require_once __DIR__ . '/includes/functions.php';
$u = require_role(['admin', 'hod', 'faculty', 'student']);
$list = [];
if ($u['role'] === 'student') { $s = student_by_user($u['id']); }
else {
    $list = db()->query('SELECT id,name,student_code FROM students ORDER BY student_code')->fetchAll();
    $id = (int)($_GET['id'] ?? ($list[0]['id'] ?? 0));
    $r = $id ? load_students($id) : []; $s = $r[0] ?? null;
}
include __DIR__ . '/includes/header.php';
if (!$s) { echo '<div class="card">No student record found.</div>'; include __DIR__ . '/includes/footer.php'; exit; }
$data = ['cgpa' => $s['cgpa'], 'internal' => $s['internal_avg'], 'backlogs' => $s['backlogs'], 'attendance' => $s['attendance'], 'login' => $s['login_freq'],
  'assign' => $s['assignment_completion'], 'events' => $s['events'], 'clubs' => $s['clubs'], 'hack' => $s['hackathons'], 'certs' => $s['certifications'],
  'apt' => $s['aptitude'], 'coding' => $s['coding'], 'mock' => $s['mock_interview'], 'resume' => $s['resume'], 'apps' => $s['applications'],
  'sat' => $s['satisfaction'], 'fac' => $s['faculty_rating'], 'skills' => $s['c']['skills']];
?>
<?php if ($list): ?><div class="card"><form class="filters" method="get"><label>Student<select name="id" onchange="this.form.submit()"><?php foreach ($list as $x): ?><option value="<?= $x['id'] ?>" <?= $x['id'] == $s['id'] ? 'selected' : '' ?>><?= e($x['student_code'] . ' · ' . $x['name']) ?></option><?php endforeach; ?></select></label></form></div><?php endif; ?>
<div class="sim">
  <div class="card"><h3><?= e($s['name']) ?></h3><p class="sub">Move the sliders to try an improvement plan</p>
    <?php foreach ([['attendance', 'Attendance %', 0, 100, 1], ['cgpa', 'CGPA', 0, 10, 0.1], ['backlogs', 'Backlogs', 0, 6, 1], ['coding', 'Coding score', 0, 100, 1], ['assign', 'Assignment completion %', 0, 100, 1], ['skills', 'Average skill level', 0, 100, 1]] as [$k, $l, $mn, $mx, $st]): ?>
      <div class="slider"><div class="t"><span><?= $l ?></span><span id="v-<?= $k ?>"></span></div><input type="range" id="s-<?= $k ?>" min="<?= $mn ?>" max="<?= $mx ?>" step="<?= $st ?>" value="<?= $data[$k] ?>"></div>
    <?php endforeach; ?>
    <button class="btn" style="margin-top:14px" id="reset" type="button">Reset to current values</button>
  </div>
  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card"><h3>Result</h3><p class="sub">Success score now vs after the change</p>
      <div class="row" style="gap:28px"><div><small class="muted">Now</small><div class="delta" id="now"></div><span id="nowRisk"></span></div>
      <div style="font-size:30px;color:var(--muted)">→</div>
      <div><small class="muted">Simulated</small><div class="delta" id="sim"></div><span id="simRisk"></span></div>
      <div><small class="muted">Change</small><div class="delta" id="chg"></div></div></div></div>
    <div class="card"><h3>Indicators</h3><p class="sub">Current vs simulated</p><div class="chartbox sm"><canvas id="cSim"></canvas></div></div>
  </div>
</div>
<script>
var D = <?= json_encode($data) ?>;
var W = {academic: .30, attendance: .15, lms: .10, engagement: .10, placement: .20, skills: .10, feedback: .05};
var L = <?= json_encode(array_values(LABELS)) ?>;
function clamp(v) { return Math.max(0, Math.min(100, v)); }
function calc(d) {
  var c = {};
  c.academic = clamp(0.7 * d.cgpa * 10 + 0.3 * d.internal - 5 * d.backlogs);
  c.attendance = clamp(d.attendance);
  c.lms = clamp(0.5 * Math.min(100, d.login / 20 * 100) + 0.5 * d.assign);
  c.engagement = clamp(d.events * 8 + d.clubs * 10 + d.hack * 15 + d.certs * 12);
  c.placement = clamp(0.25 * d.apt + 0.35 * d.coding + 0.20 * d.mock + 0.10 * d.resume + 0.10 * Math.min(100, d.apps * 20));
  c.skills = clamp(d.skills);
  c.feedback = clamp((d.sat + d.fac) / 10 * 100);
  var t = 0; for (var k in W) t += c[k] * W[k];
  return {c: c, total: Math.round(t * 10) / 10};
}
function risk(s) { return s < 45 ? ['High risk', 'red'] : (s < 65 ? ['Medium risk', 'amber'] : ['Low risk', 'green']); }
var base = calc(D), chart;
function cur() {
  var d = Object.assign({}, D);
  ['attendance', 'cgpa', 'backlogs', 'coding', 'assign', 'skills'].forEach(function (k) { d[k] = parseFloat(document.getElementById('s-' + k).value); document.getElementById('v-' + k).textContent = d[k]; });
  return d;
}
function pill(r) { return '<span class="pill ' + r[1] + '">' + r[0] + '</span>'; }
function update() {
  var r = calc(cur()), diff = Math.round((r.total - base.total) * 10) / 10;
  document.getElementById('now').textContent = base.total;
  document.getElementById('nowRisk').innerHTML = pill(risk(base.total));
  document.getElementById('sim').textContent = r.total;
  document.getElementById('simRisk').innerHTML = pill(risk(r.total));
  var ch = document.getElementById('chg'); ch.textContent = (diff > 0 ? '+' : '') + diff; ch.className = 'delta ' + (diff > 0 ? 'up' : (diff < 0 ? 'down' : ''));
  var keys = Object.keys(W);
  chart.data.datasets[1].data = keys.map(function (k) { return Math.round(r.c[k] * 10) / 10; });
  chart.update();
}
document.addEventListener('DOMContentLoaded', function () {
  var keys = Object.keys(W);
  chart = CIQ.chart('cSim', {type: 'bar', data: {labels: L, datasets: [
    {label: 'Current', data: keys.map(function (k) { return Math.round(base.c[k] * 10) / 10; }), backgroundColor: '#dfe1f3', borderRadius: 6},
    {label: 'Simulated', data: [], backgroundColor: CIQ.c.primary, borderRadius: 6}]},
    options: {plugins: {legend: {position: 'bottom'}}, scales: {y: {min: 0, max: 100, grid: CIQ.grid}, x: {grid: {display: false}, ticks: {font: {size: 10}}}}}});
  document.querySelectorAll('input[type=range]').forEach(function (i) { i.addEventListener('input', update); });
  document.getElementById('reset').addEventListener('click', function () {
    ['attendance', 'cgpa', 'backlogs', 'coding', 'assign', 'skills'].forEach(function (k) { document.getElementById('s-' + k).value = D[k]; });
    update();
  });
  update();
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
