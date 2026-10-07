<?php
$pageTitle = 'Jobs & placement'; $pageSub = 'Openings matched to student readiness and skills';
require_once __DIR__ . '/../includes/functions.php';
$u = require_role(['admin', 'placement', 'student']);
$manage = in_array($u['role'], ['admin', 'placement']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $manage) {
    csrf_check();
    if (($_POST['action'] ?? '') === 'add') {
        db()->prepare('INSERT INTO jobs (company,title,role_id,min_readiness,package_lpa,deadline) VALUES (?,?,?,?,?,?)')
            ->execute([trim($_POST['company']), trim($_POST['title']), (int)$_POST['role_id'] ?: null, (int)$_POST['min_readiness'], $_POST['package'] ?: null, $_POST['deadline'] ?: null]);
        audit('job_add', $_POST['company']); flash('Job posted.');
    } elseif (($_POST['action'] ?? '') === 'notify') {
        $jid = (int)$_POST['job_id'];
        $j = db()->prepare('SELECT j.*, r.name rname FROM jobs j LEFT JOIN job_roles r ON r.id=j.role_id WHERE j.id=?'); $j->execute([$jid]); $job = $j->fetch();
        $sent = 0;
        if ($job) foreach (load_students() as $s) {
            if (!$s['user_id'] || $s['c']['placement'] < $job['min_readiness']) continue;
            if ($job['role_id'] && $s['role_id'] != $job['role_id']) continue;
            notify((int)$s['user_id'], 'New opening: ' . $job['company'], $job['title'] . ' at ' . $job['company'] . ' matches your profile. Apply before ' . ($job['deadline'] ?: 'the deadline') . '.', 'good'); $sent++;
        }
        audit('job_notify', "job #$jid, $sent students"); flash("Notified $sent eligible student(s).");
    }
    redirect('placement/jobs.php');
}
$jobs = db()->query('SELECT j.*, r.name rname FROM jobs j LEFT JOIN job_roles r ON r.id=j.role_id ORDER BY j.deadline')->fetchAll();
seed_default_job_roles();
$roles = db()->query('SELECT * FROM job_roles ORDER BY name')->fetchAll();
$S = load_students();
$me = $u['role'] === 'student' ? student_by_user($u['id']) : null;
include __DIR__ . '/../includes/header.php';
$readyAll = array_map(fn($s) => $s['c']['placement'], $S);
?>
<?php if ($manage): ?>
<div class="grid g3">
  <div class="card kpi dark"><small>Open positions</small><strong><?= count($jobs) ?></strong><span>Active postings</span></div>
  <div class="card kpi"><small>Placement-ready (65+)</small><strong><?= count(array_filter($readyAll, fn($v) => $v >= 65)) ?></strong><span>of <?= count($S) ?> students</span></div>
  <div class="card kpi"><small>Need training (&lt;50)</small><strong><?= count(array_filter($readyAll, fn($v) => $v < 50)) ?></strong><span>Below the placement threshold</span></div>
</div>
<div class="card"><h3>Post a job</h3><p class="sub">Students whose readiness and target role fit can be notified in one click</p>
  <form method="post" class="formgrid"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <label>Company<input name="company" required></label><label>Job title<input name="title" required></label>
    <label>Target role<select name="role_id"><option value="0">Any role</option><?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></label>
    <label>Minimum readiness<input type="number" name="min_readiness" value="60" min="0" max="100"></label>
    <label>Package (LPA)<input type="number" step="0.1" name="package"></label><label>Deadline<input type="date" name="deadline"></label>
    <button class="btn primary">Post job</button></form></div>
<?php endif; ?>
<div class="card flat"><div class="pad"><h3>Openings</h3><p class="sub"><?= $me ? 'Your match is based on your placement readiness and target role.' : 'Eligible students are those who meet the readiness bar for the role.' ?></p></div>
<div class="scroll"><table class="tbl"><thead><tr><th>Company</th><th>Role</th><th>Package</th><th>Min readiness</th><th>Deadline</th><th><?= $me ? 'Your match' : 'Eligible students' ?></th><?php if ($manage): ?><th></th><?php endif; ?></tr></thead><tbody>
<?php foreach ($jobs as $j):
  $elig = 0; foreach ($S as $s) if ($s['c']['placement'] >= $j['min_readiness'] && (!$j['role_id'] || $s['role_id'] == $j['role_id'])) $elig++; ?>
  <tr><td><div class="who2"><strong><?= e($j['company']) ?></strong><small><?= e($j['title']) ?></small></div></td><td><?= e($j['rname'] ?: 'Any') ?></td>
    <td><?= $j['package_lpa'] ? e($j['package_lpa']) . ' LPA' : '-' ?></td><td><?= $j['min_readiness'] ?></td><td><?= e($j['deadline'] ?: '-') ?></td>
    <td><?php if ($me):
        $ok = $me['c']['placement'] >= $j['min_readiness']; $roleOk = !$j['role_id'] || $me['role_id'] == $j['role_id'];
        echo $ok && $roleOk ? '<span class="pill green">Eligible</span>' : '<span class="pill amber">' . (!$ok ? 'Readiness ' . f0($me['c']['placement']) . ' of ' . $j['min_readiness'] : 'Different target role') . '</span>';
      else: echo $elig; endif; ?></td>
    <?php if ($manage): ?><td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="notify"><input type="hidden" name="job_id" value="<?= $j['id'] ?>"><button class="btn sm">Notify eligible</button></form></td><?php endif; ?></tr>
<?php endforeach; if (!$jobs): ?><tr><td colspan="7" class="muted">No jobs posted yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php if ($manage): ?><div class="card"><h3>Placement readiness by student</h3><p class="sub">Lowest 12 students, who need training before drives</p><div class="chartbox sm"><canvas id="cPl"></canvas></div></div>
<?php usort($S, fn($a, $b) => $a['c']['placement'] <=> $b['c']['placement']); $low = array_slice($S, 0, 12); ?>
<script>
CIQ.chart('cPl', {type: 'bar', data: {labels: <?= json_encode(array_column($low, 'name')) ?>, datasets: [{data: <?= json_encode(array_map(fn($s) => $s['c']['placement'], $low)) ?>, backgroundColor: CIQ.c.amber, borderRadius: 6}]},
  options: {plugins: {legend: {display: false}}, scales: {y: {min: 0, max: 100, grid: CIQ.grid}, x: {grid: {display: false}, ticks: {font: {size: 10}}}}}});
</script><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
