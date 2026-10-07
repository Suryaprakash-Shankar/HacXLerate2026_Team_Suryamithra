<?php
$pageTitle = 'Notifications'; $pageSub = 'Alerts and updates sent to you';
require_once __DIR__ . '/includes/functions.php';
$u = require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'read_all') db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$u['id']]);
    redirect('notifications.php');
}
$st = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC, id DESC LIMIT 100'); $st->execute([$u['id']]); $rows = $st->fetchAll();
include __DIR__ . '/includes/header.php';
$ic = ['info' => 'i', 'warn' => '!', 'alert' => '!!', 'good' => '✓'];
?>
<div class="card flat"><div class="pad row spread"><div><h3>Your notifications</h3><p class="sub"><?= count($rows) ?> total</p></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn sm">Mark all as read</button></form></div>
  <?php foreach ($rows as $n): ?>
    <div class="note <?= $n['is_read'] ? '' : 'unread' ?>"><div class="ic <?= e($n['type']) ?>"><?= $ic[$n['type']] ?? 'i' ?></div>
      <div><strong><?= e($n['title']) ?></strong><div><?= e($n['body']) ?></div><small><?= e(date('d M Y, H:i', strtotime($n['created_at']))) ?></small></div></div>
  <?php endforeach; if (!$rows): ?><div class="note"><span class="muted">Nothing here yet. Alerts about attendance, skill gaps and support plans will appear here.</span></div><?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
