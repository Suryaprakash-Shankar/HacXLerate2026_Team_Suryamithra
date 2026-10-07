<?php
$pageTitle = 'Students'; $pageSub = 'Unified student profiles with scores, risk flags and segments';
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'hod', 'faculty', 'placement']);
$u = current_user();
$S = load_students(null, null, $u);
$depts = load_departments();
$fd = $_GET['dept'] ?? ''; $fr = $_GET['risk'] ?? '';
$S = array_filter($S, fn($s) => ($fd === '' || $s['department'] === $fd) && ($fr === '' || $s['risk']['overall'] === $fr));
usort($S, fn($a, $b) => $a['score'] <=> $b['score']);
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <form class="filters" method="get">
    <label>Search<input type="search" placeholder="Name or ID" data-filter="stbl"></label>
    <label>Department<select name="dept"><option value="">All</option><?php foreach ($depts as $d): ?><option <?= $fd === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?></select></label>
    <label>Risk<select name="risk"><option value="">All</option><?php foreach (['HIGH', 'MEDIUM', 'LOW'] as $r): ?><option value="<?= $r ?>" <?= $fr === $r ? 'selected' : '' ?>><?= ucfirst(strtolower($r)) ?></option><?php endforeach; ?></select></label>
    <button class="btn primary">Apply filters</button>
  </form>
</div>
<div class="card flat"><div class="scroll"><table class="tbl" id="stbl"><thead><tr>
  <th>Student</th><th>Dept</th><th>CGPA</th><th>Attendance</th><th>Placement</th><th>Success score</th><th>Risk</th><th>Segment</th><th></th></tr></thead><tbody>
<?php foreach ($S as $s): ?>
  <tr><td><div class="who2"><strong><?= e($s['name']) ?></strong><small><?= e($s['student_code']) ?></small></div></td>
    <td><?= e($s['department']) ?></td><td><?= number_format($s['cgpa'], 1) ?></td><td><?= f0($s['attendance']) ?>%</td><td><?= f0($s['c']['placement']) ?></td>
    <td><div class="scorebar"><span><?= f0($s['score']) ?></span><i><b style="width:<?= $s['score'] ?>%;background:<?= score_color($s['score']) ?>"></b></i></div></td>
    <td><?= risk_pill($s['risk']['overall']) ?></td><td><?= seg_pill($s) ?></td>
    <td><a class="btn sm" href="<?= url('student.php?id=' . $s['id']) ?>">Open</a></td></tr>
<?php endforeach; if (!$S): ?><tr><td colspan="9" class="muted">No students match these filters.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
