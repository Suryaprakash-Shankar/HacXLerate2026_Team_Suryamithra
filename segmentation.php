<?php
$pageTitle = 'Student segments'; $pageSub = 'Groups of students that need different actions';
require_once __DIR__ . '/includes/functions.php';
$u = require_role(['admin', 'hod', 'faculty', 'placement']);
$S = load_students();
$info = [
  'High Performer' => 'Score 80+ and strong placement readiness. Offer stretch projects and peer-mentor roles.',
  'Critical Intervention' => 'Score below 45. Start mentoring, counselling and a recovery plan now.',
  'Placement Gap' => 'Strong academics but placement readiness under 60. Coach on coding, aptitude and interviews.',
  'Attendance Risk' => 'Attendance under 70%. Set a weekly target and alert guardians.',
  'Skill Gap' => 'Average skill level under 55. Assign a focused skill-building track.',
  'Potential Improver' => 'Mid score but active on LMS or in campus life. Small nudges can lift them.',
  'Balanced' => 'No major weakness. Keep monitoring.',
];
$by = []; foreach ($S as $s) $by[$s['segment']][] = $s;
$order = array_keys($info);
$pts = [];
foreach ($S as $s) $pts[$s['segment']][] = ['x' => $s['c']['academic'], 'y' => $s['c']['placement'], 'n' => $s['name']];
include __DIR__ . '/includes/header.php';
?>
<div class="card"><h3>Academic strength vs placement readiness</h3><p class="sub">Each dot is a student. High academic and low placement (bottom right) is the placement-gap group.</p><div class="chartbox" style="height:380px"><canvas id="cScatter"></canvas></div></div>
<div class="grid g3">
<?php foreach ($order as $seg): $list = $by[$seg] ?? []; $col = $list ? $list[0]['segment_color'] : 'slate'; ?>
  <div class="card"><div class="row spread"><h3><?= e($seg) ?></h3><span class="pill <?= $col ?>"><?= count($list) ?></span></div>
    <p class="sub"><?= e($info[$seg]) ?></p>
    <?php foreach (array_slice($list, 0, 6) as $s): ?>
      <div class="row spread" style="padding:6px 0;border-bottom:1px dashed var(--line)"><a href="<?= url('student.php?id=' . $s['id']) ?>"><?= e($s['name']) ?></a><strong><?= f0($s['score']) ?></strong></div>
    <?php endforeach; if (count($list) > 6): ?><small class="muted">+ <?= count($list) - 6 ?> more</small><?php endif; if (!$list): ?><small class="muted">No students in this group.</small><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<script>
var P = <?= json_encode($pts) ?>;
CIQ.chart('cScatter', {type: 'scatter', data: {datasets: Object.keys(P).map(function (k) {
  return {label: k, data: P[k], backgroundColor: CIQ.segColors[k], pointRadius: 6, pointHoverRadius: 8}; })},
  options: {plugins: {legend: {position: 'bottom'}, tooltip: {callbacks: {label: function (c) { return c.raw.n + ': academic ' + c.raw.x + ', placement ' + c.raw.y; }}}},
    scales: {x: {min: 0, max: 100, title: {display: true, text: 'Academic score'}, grid: CIQ.grid}, y: {min: 0, max: 100, title: {display: true, text: 'Placement readiness'}, grid: CIQ.grid}}}});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
