<?php
$pageTitle = 'Skill gap analyzer'; $pageSub = 'Every student compared with the skills their target role needs';
require_once __DIR__ . '/includes/functions.php';
$u = require_role(['admin', 'hod', 'faculty', 'placement']);
$S = load_students();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $sent = 0;
    foreach ($S as $s) {
        if (!$s['user_id']) continue;
        $one = ($_POST['action'] ?? '') === 'notify_one' && (int)($_POST['sid'] ?? 0) === (int)$s['id'];
        $all = ($_POST['action'] ?? '') === 'notify_all';
        if (!$one && !$all) continue;
        $gaps = array_values(array_filter($s['gaps'], fn($g) => $g['gap'] > 0 && ($one || $g['priority'] === 'Priority')));
        if (!$gaps) continue;
        $lines = array_map(fn($g) => $g['skill'] . ' (' . $g['current'] . ' → ' . $g['required'] . ')', array_slice($gaps, 0, 4));
        notify((int)$s['user_id'], 'Skill gaps for ' . $s['role_name'], 'To be ready for ' . $s['role_name'] . ', focus on: ' . implode(', ', $lines) . '.', 'warn');
        $sent++;
    }
    audit('notify_skill_gaps', $sent . ' students');
    flash($sent ? "Skill gap notifications sent to $sent student(s)." : 'No students had skill gaps to notify.', $sent ? 'ok' : 'warn');
    redirect('skills.php');
}

$fr = $_GET['role'] ?? '';
$roleNames = array_values(array_unique(array_filter(array_column($S, 'role_name')))); sort($roleNames);
$agg = []; $allSk = [];
foreach ($S as $s) foreach ($s['gaps'] as $g) {
    $agg[$g['skill']]['sum'] = ($agg[$g['skill']]['sum'] ?? 0) + $g['gap'];
    $agg[$g['skill']]['n'] = ($agg[$g['skill']]['n'] ?? 0) + 1;
    $agg[$g['skill']]['pri'] = ($agg[$g['skill']]['pri'] ?? 0) + ($g['priority'] === 'Priority' ? 1 : 0);
}
$avgGap = []; foreach ($agg as $k => $v) $avgGap[$k] = round($v['sum'] / $v['n'], 1);
arsort($avgGap);
$priCount = []; foreach ($agg as $k => $v) $priCount[$k] = $v['pri'];
arsort($priCount);
$list = array_values(array_filter($S, fn($s) => $fr === '' || $s['role_name'] === $fr));
usort($list, fn($a, $b) => array_sum(array_column($b['gaps'], 'gap')) <=> array_sum(array_column($a['gaps'], 'gap')));
$skillCols = array_keys($agg); sort($skillCols);
$withPri = count(array_filter($S, fn($s) => array_filter($s['gaps'], fn($g) => $g['priority'] === 'Priority')));
include __DIR__ . '/includes/header.php';
?>
<div class="grid g4">
  <div class="card kpi dark"><small>Students with a priority gap</small><strong><?= $withPri ?></strong><span>Gap of 25+ points on a required skill</span></div>
  <div class="card kpi"><small>Biggest campus-wide gap</small><strong style="font-size:24px"><?= e(array_key_first($avgGap) ?? '-') ?></strong><span>Average shortfall <?= f0(reset($avgGap) ?: 0) ?> points</span></div>
  <div class="card kpi"><small>Most students in priority</small><strong style="font-size:24px"><?= e(array_key_first($priCount) ?? '-') ?></strong><span><?= (int)(reset($priCount) ?: 0) ?> students</span></div>
  <div class="card kpi"><small>Notify everyone</small>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="notify_all"><button class="btn primary sm" style="margin-top:8px" onclick="return confirm('Send skill gap notifications to every student with a priority gap?')">Send to <?= $withPri ?> students</button></form>
    <span>Each student gets their own top gaps</span></div>
</div>

<div class="grid g2">
  <div class="card"><h3>Average gap by skill</h3><p class="sub">Points below the level the target roles require</p><div class="chartbox"><canvas id="cGap"></canvas></div></div>
  <div class="card"><h3>Students with a priority gap, by skill</h3><p class="sub">Where training programmes will help the most students</p><div class="chartbox"><canvas id="cPri"></canvas></div></div>
</div>

<div class="card flat"><div class="pad">
  <form class="filters" method="get"><label>Target role<select name="role"><option value="">All roles</option><?php foreach ($roleNames as $r): ?><option <?= $fr === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select></label>
  <label>Search<input type="search" data-filter="gtbl" placeholder="Name or ID"></label><button class="btn primary">Apply</button></form></div>
  <div class="scroll"><table class="tbl" id="gtbl"><thead><tr><th>Student</th><th>Target role</th><th>Readiness</th><th>Top gaps</th><th>Total gap</th><th></th></tr></thead><tbody>
  <?php foreach ($list as $s): $open = array_filter($s['gaps'], fn($g) => $g['gap'] > 0); ?>
    <tr><td><div class="who2"><strong><a href="<?= url('student.php?id=' . $s['id']) ?>"><?= e($s['name']) ?></a></strong><small><?= e($s['student_code']) ?></small></div></td>
      <td><?= e($s['role_name']) ?></td><td><?= f0($s['c']['placement']) ?></td>
      <td><?php foreach (array_slice($open, 0, 3) as $g): ?><span class="pill <?= $g['priority'] === 'Priority' ? 'red' : ($g['priority'] === 'Moderate' ? 'amber' : 'blue') ?>" style="margin:2px"><?= e($g['skill']) ?> −<?= $g['gap'] ?></span><?php endforeach; if (!$open): ?><span class="pill green">All met</span><?php endif; ?></td>
      <td><strong><?= array_sum(array_column($s['gaps'], 'gap')) ?></strong></td>
      <td><?php if ($open && $s['user_id']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="notify_one"><input type="hidden" name="sid" value="<?= $s['id'] ?>"><button class="btn sm">Notify</button></form><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div></div>

<div class="card flat"><div class="pad"><h3>Gap heatmap, all students</h3><p class="sub">Shortfall against the target role. Blank means the role does not need that skill. Green means met.</p></div>
  <div class="scroll"><table class="tbl heat"><thead><tr><th>Student</th><?php foreach ($skillCols as $k): ?><th class="v"><?= e($k) ?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach ($list as $s): $m = []; foreach ($s['gaps'] as $g) $m[$g['skill']] = $g; ?>
    <tr><td style="white-space:nowrap"><strong><?= e($s['name']) ?></strong> <small class="muted"><?= e($s['student_code']) ?></small></td>
    <?php foreach ($skillCols as $k): $g = $m[$k] ?? null;
        if (!$g) { echo '<td class="h"></td>'; continue; }
        $bg = $g['gap'] == 0 ? '#dcf6ea' : ($g['gap'] >= 25 ? '#ffd2d4' : ($g['gap'] >= 10 ? '#ffe9bd' : '#e3eeff'));
        echo '<td class="h" style="background:' . $bg . '">' . ($g['gap'] ? '−' . $g['gap'] : '✓') . '</td>';
    endforeach; ?></tr>
  <?php endforeach; ?></tbody></table></div></div>
<script>
CIQ.chart('cGap', {type: 'bar', data: {labels: <?= json_encode(array_keys($avgGap)) ?>, datasets: [{data: <?= json_encode(array_values($avgGap)) ?>, backgroundColor: CIQ.c.primary, borderRadius: 6}]},
  options: {indexAxis: 'y', plugins: {legend: {display: false}}, scales: {x: {grid: CIQ.grid}, y: {grid: {display: false}}}}});
CIQ.chart('cPri', {type: 'bar', data: {labels: <?= json_encode(array_keys($priCount)) ?>, datasets: [{data: <?= json_encode(array_values($priCount)) ?>, backgroundColor: CIQ.c.red, borderRadius: 6}]},
  options: {indexAxis: 'y', plugins: {legend: {display: false}}, scales: {x: {grid: CIQ.grid, ticks: {precision: 0}}, y: {grid: {display: false}}}}});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
