<?php
$pageTitle = 'Users & audit log'; $pageSub = 'Manage logins for admin, faculty, placement officers and students';
require_once __DIR__ . '/../includes/functions.php';
$u = require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? '';
    try {
        if ($a === 'add') {
            db()->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)')->execute([trim($_POST['name']), trim($_POST['email']), password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['role']]);
            audit('user_add', $_POST['email']); flash('User created.');
        } elseif ($a === 'toggle' && (int)$_POST['id'] !== $u['id']) {
            db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id=?')->execute([(int)$_POST['id']]); audit('user_toggle', '#' . (int)$_POST['id']); flash('User updated.');
        } elseif ($a === 'reset' && strlen($_POST['password']) >= 6) {
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), (int)$_POST['id']]); audit('password_reset', '#' . (int)$_POST['id']); flash('Password reset.');
        }
    } catch (PDOException $ex) { flash('Could not save: that email may already exist.', 'warn'); }
    redirect('admin/users.php');
}
$users = db()->query("SELECT * FROM users WHERE role<>'student' ORDER BY FIELD(role,'admin','hod','faculty','placement'), name")->fetchAll();
$studentCount = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$logs = db()->query('SELECT l.*, u.email FROM audit_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 25')->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="card"><h3>Add a user</h3><p class="sub">Student logins are created automatically when you import students (<?= $studentCount ?> exist).</p>
  <form method="post" class="formgrid"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <label>Name<input name="name" required></label><label>Email<input type="email" name="email" required></label>
    <label>Role<select name="role"><option value="hod">HOD</option><option value="faculty">Teacher / Faculty</option><option value="placement">Placement officer</option><option value="admin">Admin</option><option value="student">Student</option></select></label>
    <label>Password<input type="text" name="password" minlength="6" required></label><button class="btn primary">Create user</button></form></div>
<div class="card flat"><div class="scroll"><table class="tbl"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last login</th><th>Status</th><th>Reset password</th></tr></thead><tbody>
<?php foreach ($users as $x): ?><tr><td><strong><?= e($x['name']) ?></strong></td><td><?= e($x['email']) ?></td><td><span class="pill violet"><?= e($x['role']) ?></span></td><td><?= e($x['last_login'] ?: 'Never') ?></td>
  <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="btn sm" <?= $x['id'] == $u['id'] ? 'disabled' : '' ?>><?= $x['is_active'] ? 'Active · Disable' : 'Disabled · Enable' ?></button></form></td>
  <td><form method="post" class="row" style="flex-wrap:nowrap"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $x['id'] ?>"><input name="password" placeholder="New password" minlength="6" required style="min-width:130px"><button class="btn sm">Reset</button></form></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<div class="card flat"><div class="pad"><h3>Audit log</h3><p class="sub">Latest 25 actions</p></div><div class="scroll"><table class="tbl"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Detail</th></tr></thead><tbody>
<?php foreach ($logs as $l): ?><tr><td><?= e($l['created_at']) ?></td><td><?= e($l['email'] ?: '-') ?></td><td><?= e($l['action']) ?></td><td><?= e($l['detail']) ?></td></tr><?php endforeach; ?>
<?php if (!$logs): ?><tr><td colspan="4" class="muted">No activity yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
