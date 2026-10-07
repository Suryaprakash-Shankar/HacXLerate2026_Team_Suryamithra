<?php
$pageTitle = 'Dashboard'; $pageSub = 'Campus-wide view of student success, risk and placement readiness';
require_once __DIR__ . '/includes/functions.php';
$u = require_role(['admin', 'hod', 'faculty', 'placement']);
if ($u['role'] === 'student') redirect('student.php');
$S = load_students(null, null, $u);
$n = count($S);
$riskCount = ['HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
$dept = []; $seg = []; $hist = array_fill(0, 10, 0); $comp = array_fill_keys(array_keys(WEIGHTS), []);
foreach ($S as $s) {
    $riskCount[$s['risk']['overall']]++;
    $dept[$s['department']][] = $s['score'];
    $seg[$s['segment']] = ($seg[$s['segment']] ?? 0) + 1;
    $hist[min(9, (int)floor($s['score'] / 10))]++;
    foreach ($s['c'] as $k => $v) $comp[$k][] = $v;
}
$avgScore = avg(array_column($S, 'score'));
$avgPlace = avg(array_map(fn($s) => $s['c']['placement'], $S));
$deptAvg = array_map('avg', $dept);
$compAvg = array_map('avg', $comp);
$atRisk = array_values(array_filter($S, fn($s) => $s['risk']['overall'] === 'HIGH'));
usort($atRisk, fn($a, $b) => $a['score'] <=> $b['score']);
$gapStudents = count(array_filter($S, fn($s) => $s['segment'] === 'Placement Gap'));
$openIv = (int)db()->query("SELECT COUNT(*) FROM interventions WHERE status<>'Completed'")->fetchColumn();
include __DIR__ . '/includes/header.php';
?>
<div class="grid g4">
  <div class="card kpi dark"><small>Students tracked</small><strong><?= $n ?></strong><span>Unified across 7 data sources</span></div>
  <div class="card kpi"><i class="dot" style="background:var(--red)"></i><small>High risk</small><strong><?= $riskCount['HIGH'] ?></strong><span><?= $riskCount['MEDIUM'] ?> medium · <?= $riskCount['LOW'] ?> low</span></div>
  <div class="card kpi"><small>Average success score</small><strong><?= f1($avgScore) ?></strong><span>Weighted across 7 indicators</span></div>
  <div class="card kpi"><small>Placement readiness</small><strong><?= f1($avgPlace) ?></strong><span><?= $gapStudents ?> students with a placement gap</span></div>
</div>

<div class="grid g-main">
  <div class="card"><h3>Success score distribution</h3><p class="sub">How many students fall in each 10-point band</p>
    <div class="chartbox"><canvas id="cHist"></canvas></div></div>
  <div class="card"><h3>Risk split</h3><p class="sub">Overall risk flag per student</p>
    <div class="chartbox"><canvas id="cRisk"></canvas></div></div>
</div>

<div class="grid g3">
  <div class="card"><h3>Department comparison</h3><p class="sub">Average success score</p><div class="chartbox sm"><canvas id="cDept"></canvas></div></div>
  <div class="card"><h3>Campus strengths and weak spots</h3><p class="sub">Average of each indicator (0–100)</p><div class="chartbox sm"><canvas id="cComp"></canvas></div></div>
  <div class="card"><h3>Student segments</h3><p class="sub">Groups that need different actions</p><div class="chartbox sm"><canvas id="cSeg"></canvas></div></div>
</div>

<div class="card flat">
  <div class="pad row spread"><div><h3>Early warning list</h3><p class="sub">Lowest success scores first. The main driver explains the flag.</p></div>
    <?php if (in_array($u['role'], ['admin', 'faculty'])): ?><a class="btn sm" href="<?= url('risk.php') ?>">Open risk center</a><?php endif; ?></div>
  <div class="scroll"><table class="tbl"><thead><tr><th>Student</th><th>Department</th><th>Success score</th><th>Biggest driver</th><th>Segment</th><th></th></tr></thead><tbody>
  <?php foreach (array_slice($atRisk, 0, 8) as $s): ?>
    <tr><td><div class="who2"><strong><?= e($s['name']) ?></strong><small><?= e($s['student_code']) ?></small></div></td>
      <td><?= e($s['department']) ?></td>
      <td><div class="scorebar"><span><?= f0($s['score']) ?></span><i><b style="width:<?= $s['score'] ?>%;background:<?= score_color($s['score']) ?>"></b></i></div></td>
      <td><?= e($s['explain'][0]['label']) ?> <small class="muted">(<?= f0($s['explain'][0]['risk_share']) ?>%)</small></td>
      <td><?= seg_pill($s) ?></td>
      <td><a class="btn sm" href="<?= url('student.php?id=' . $s['id']) ?>">View</a></td></tr>
  <?php endforeach; if (!$atRisk): ?><tr><td colspan="6" class="muted">No high-risk students right now.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>

<div class="grid g3">
  <div class="card"><h3>Prediction → intervention → outcome</h3><p class="sub">Decision intelligence loop</p>
    <p><strong><?= $riskCount['HIGH'] ?></strong> students flagged · <strong><?= $openIv ?></strong> open interventions. Every flag links to its causes, a suggested action and a tracked outcome.</p></div>
  <div class="card"><h3>Explainable by design</h3><p class="sub">No black box</p>
    <p>Each score splits into 7 weighted indicators. Each risk flag shows which indicator contributes the most, so faculty can act on the cause.</p></div>
  <div class="card"><h3>Skill gaps feed placement</h3><p class="sub">Student → skills → career</p>
    <p>Compare every student with the skills their target role needs, then notify them with the gaps to close.</p>
    <a class="btn sm" href="<?= url('skills.php') ?>">See skill gaps</a></div>
</div>

<script>
var C = CIQ.c;
CIQ.chart('cHist', {type: 'bar', data: {labels: ['0-9','10-19','20-29','30-39','40-49','50-59','60-69','70-79','80-89','90+'],
  datasets: [{data: <?= json_encode(array_values($hist)) ?>, borderRadius: 8,
    backgroundColor: ['#e5484d','#e5484d','#e5484d','#e5484d','#e5484d','#f0a020','#f0a020','#14a96b','#14a96b','#14a96b'].map(function (x, i) { return i < 4 ? C.red : (i < 6 ? C.amber : C.green); })}]},
  options: {plugins: {legend: {display: false}}, scales: {y: {grid: CIQ.grid, ticks: {precision: 0}}, x: {grid: {display: false}}}}});
CIQ.chart('cRisk', {type: 'doughnut', data: {labels: ['High', 'Medium', 'Low'], datasets: [{data: <?= json_encode(array_values($riskCount)) ?>, backgroundColor: [C.red, C.amber, C.green], borderWidth: 0}]},
  options: {cutout: '68%', plugins: {legend: {position: 'bottom'}}}});
CIQ.chart('cDept', {type: 'bar', data: {labels: <?= json_encode(array_keys($deptAvg)) ?>, datasets: [{data: <?= json_encode(array_map(fn($v) => round($v, 1), array_values($deptAvg))) ?>, backgroundColor: C.primary, borderRadius: 8}]},
  options: {indexAxis: 'y', plugins: {legend: {display: false}}, scales: {x: {min: 0, max: 100, grid: CIQ.grid}, y: {grid: {display: false}}}}});
CIQ.chart('cComp', {type: 'radar', data: {labels: <?= json_encode(array_values(LABELS)) ?>, datasets: [{data: <?= json_encode(array_map(fn($v) => round($v, 1), array_values($compAvg))) ?>, backgroundColor: 'rgba(91,75,255,.16)', borderColor: C.primary, pointBackgroundColor: C.primary}]},
  options: {plugins: {legend: {display: false}}, scales: {r: {min: 0, max: 100, ticks: {display: false}, grid: {color: C.line}, pointLabels: {font: {size: 11}}}}}});
var segL = <?= json_encode(array_keys($seg)) ?>;
CIQ.chart('cSeg', {type: 'doughnut', data: {labels: segL, datasets: [{data: <?= json_encode(array_values($seg)) ?>, backgroundColor: segL.map(function (l) { return CIQ.segColors[l]; }), borderWidth: 0}]},
  options: {cutout: '60%', plugins: {legend: {position: 'right', labels: {boxWidth: 10, font: {size: 11}}}}}});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
