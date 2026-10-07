<?php
$pageTitle = 'Risk center'; $pageSub = 'Where each student is at risk, and which indicator drives it';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'hod', 'faculty']);
$S = load_students();
$areas = ['academic' => 'Academic', 'attendance' => 'Attendance', 'placement' => 'Placement', 'lms' => 'LMS', 'skills' => 'Skills'];
$cnt = [];
foreach ($areas as $k => $l) { $cnt[$k] = ['HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0]; foreach ($S as $s) $cnt[$k][$s['risk'][$k]]++; }
usort($S, fn($a, $b) => $a['score'] <=> $b['score']);
$S = array_filter($S, fn($s) => $s['risk']['overall'] !== 'LOW');
include __DIR__ . '/includes/header.php';
?>
<div class="card"><h3>Risk by area</h3><p class="sub">Number of students at each level</p><div class="chartbox"><canvas id="cAreas"></canvas></div></div>
<div class="card flat"><div class="pad"><h3>Students needing attention</h3><p class="sub">High and medium overall risk, lowest score first</p></div>
<div class="scroll"><table class="tbl" id="rtbl"><thead><tr><th>Student</th><?php foreach ($areas as $l): ?><th><?= $l ?></th><?php endforeach; ?><th>Overall</th><th>Top driver</th><th></th></tr></thead><tbody>
<?php foreach ($S as $s): ?><tr>
  <td><div class="who2"><strong><?= e($s['name']) ?></strong><small><?= e($s['student_code']) ?> · score <?= f0($s['score']) ?></small></div></td>
  <?php foreach ($areas as $k => $l): ?><td><?= risk_pill($s['risk'][$k]) ?></td><?php endforeach; ?>
  <td><?= risk_pill($s['risk']['overall']) ?></td><td><?= e($s['explain'][0]['label']) ?> <small class="muted"><?= f0($s['explain'][0]['risk_share']) ?>%</small></td>
  <td><a class="btn sm" href="<?= url('student.php?id=' . $s['id']) ?>">Plan</a></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<script>
var A = <?= json_encode($cnt) ?>, L = <?= json_encode(array_values($areas)) ?>, K = <?= json_encode(array_keys($areas)) ?>;
CIQ.chart('cAreas', {type: 'bar', data: {labels: L, datasets: [
  {label: 'High', data: K.map(function (k) { return A[k].HIGH; }), backgroundColor: CIQ.c.red, borderRadius: 6},
  {label: 'Medium', data: K.map(function (k) { return A[k].MEDIUM; }), backgroundColor: CIQ.c.amber, borderRadius: 6},
  {label: 'Low', data: K.map(function (k) { return A[k].LOW; }), backgroundColor: CIQ.c.green, borderRadius: 6}]},
  options: {plugins: {legend: {position: 'bottom'}}, scales: {x: {stacked: true, grid: {display: false}}, y: {stacked: true, grid: CIQ.grid, ticks: {precision: 0}}}}});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
