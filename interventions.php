<?php
$pageTitle = 'Interventions'; $pageSub = 'Track every action from prediction to outcome';
require_once __DIR__ . '/includes/functions.php';
$u = require_role(['admin', 'hod', 'faculty']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'create') {
        db()->prepare('INSERT INTO interventions (student_id,type,mentor,priority,status,notes,created_by) VALUES (?,?,?,?,?,?,?)')
            ->execute([(int)$_POST['student_id'], $_POST['type'], $_POST['mentor'] ?: $u['name'], $_POST['priority'], 'Planned', $_POST['notes'], $u['id']]);
        $uid = db()->prepare('SELECT user_id FROM students WHERE id=?'); $uid->execute([(int)$_POST['student_id']]); $uid = $uid->fetchColumn();
        if ($uid) notify((int)$uid, 'New support plan: ' . $_POST['type'], 'A support plan has been created for you. Your mentor will reach out soon.', 'info');
        audit('intervention_create', $_POST['type']); flash('Intervention created.');
    } elseif (($_POST['action'] ?? '') === 'update') {
        $id = (int)$_POST['id'];
        db()->prepare('UPDATE interventions SET status=?, outcome=? WHERE id=?')->execute([$_POST['status'], $_POST['outcome'] ?: null, $id]);
        if ($_POST['status'] === 'Completed') {
            $q = db()->prepare('SELECT s.user_id, i.type FROM interventions i JOIN students s ON s.id=i.student_id WHERE i.id=?'); $q->execute([$id]); $r = $q->fetch();
            if ($r && $r['user_id']) notify((int)$r['user_id'], 'Support plan completed', '"' . $r['type'] . '" is marked complete. Keep up the progress.', 'good');
        }
        audit('intervention_update', "#$id " . $_POST['status']); flash('Intervention updated.');
    }
    redirect('interventions.php');
}
$rows = db()->query('SELECT i.*, s.name, s.student_code FROM interventions i JOIN students s ON s.id=i.student_id ORDER BY FIELD(i.status,"In Progress","Planned","Completed"), i.created_at DESC')->fetchAll();
$stu = db()->query('SELECT id,name,student_code FROM students ORDER BY student_code')->fetchAll();
$counts = ['Planned' => 0, 'In Progress' => 0, 'Completed' => 0]; foreach ($rows as $r) $counts[$r['status']]++;
include __DIR__ . '/includes/header.php';
?>
<div class="grid g3">
  <?php foreach ($counts as $k => $v): ?><div class="card kpi"><small><?= e($k) ?></small><strong><?= $v ?></strong><span>interventions</span></div><?php endforeach; ?>
</div>
<div class="card"><h3>New intervention</h3><p class="sub">The student is notified when you create it</p>
  <form method="post" class="formgrid"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label>Student<select name="student_id"><?php foreach ($stu as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['student_code'] . ' · ' . $s['name']) ?></option><?php endforeach; ?></select></label>
    <label>Type<select name="type"><?php foreach (['Academic mentoring', 'Attendance improvement plan', 'Coding & placement training', 'Weekly LMS targets', 'Skill-building track', 'Faculty counselling'] as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></label>
    <label>Mentor<input name="mentor" placeholder="<?= e($u['name']) ?>"></label>
    <label>Priority<select name="priority"><option>High</option><option selected>Medium</option><option>Low</option></select></label>
    <label style="grid-column:1/-1">Notes<input name="notes" placeholder="What should the mentor do?"></label>
    <button class="btn primary">Create intervention</button></form></div>
<div class="card flat"><div class="scroll"><table class="tbl"><thead><tr><th>Student</th><th>Intervention</th><th>Mentor</th><th>Priority</th><th>Status</th><th>Outcome</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr>
    <td><form id="f<?= $r['id'] ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $r['id'] ?>"></form><div class="who2"><strong><?= e($r['name']) ?></strong><small><?= e($r['student_code']) ?></small></div></td>
    <td><?= e($r['type']) ?><br><small class="muted"><?= e($r['notes']) ?></small></td><td><?= e($r['mentor']) ?></td>
    <td><span class="pill <?= $r['priority'] === 'High' ? 'red' : ($r['priority'] === 'Medium' ? 'amber' : 'slate') ?>"><?= e($r['priority']) ?></span></td>
    <td><select name="status" form="f<?= $r['id'] ?>"><?php foreach (['Planned', 'In Progress', 'Completed'] as $st): ?><option <?= $r['status'] === $st ? 'selected' : '' ?>><?= $st ?></option><?php endforeach; ?></select></td>
    <td><input name="outcome" form="f<?= $r['id'] ?>" value="<?= e($r['outcome']) ?>" placeholder="Result"></td><td><button class="btn sm" form="f<?= $r['id'] ?>">Save</button></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted">No interventions yet. Create the first one above.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
