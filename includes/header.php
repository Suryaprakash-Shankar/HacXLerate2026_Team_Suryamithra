<?php
require_once __DIR__ . '/functions.php';
$user = require_login();
$role = $user['role'];
$nav = [
  ['dashboard.php', 'Dashboard', 'grid', ['admin', 'hod', 'faculty', 'placement']],
  ['attendance.php', '7-Period Attendance', 'calendar', ['admin', 'hod', 'faculty']],
  ['hierarchy.php', 'Staff hierarchy', 'hierarchy', ['admin', 'hod', 'faculty']],
  ['student.php', 'My success profile', 'user', ['student']],
  ['students.php', 'Students', 'users', ['admin', 'hod', 'faculty', 'placement']],
  ['risk.php', 'Risk center', 'alert', ['admin', 'hod', 'faculty']],
  ['segmentation.php', 'Segments', 'pie', ['admin', 'hod', 'faculty', 'placement']],
  ['skills.php', 'Skill gaps', 'target', ['admin', 'hod', 'faculty', 'placement']],
  ['interventions.php', 'Interventions', 'heart', ['admin', 'hod', 'faculty']],
  ['whatif.php', 'What-if simulator', 'sliders', ['admin', 'hod', 'faculty', 'student']],
  ['subjects.php', 'Subjects & Staff', 'sliders', ['admin', 'hod', 'faculty']],
  ['subject_marks.php', 'Internal & Sem Marks', 'target', ['admin', 'hod', 'faculty']],
  ['quizzes.php', 'Daily 10 MCQ Quizzes', 'spark', ['admin', 'hod', 'faculty', 'student']],
  ['placement/jobs.php', 'Jobs & placement', 'briefcase', ['admin', 'placement', 'student']],
  ['ai_tutor.php', 'AI Student Tutor', 'spark', ['admin', 'hod', 'faculty', 'placement', 'student']],
  ['notifications.php', 'Notifications', 'bell', ['admin', 'hod', 'faculty', 'placement', 'student']],
  ['profile.php', 'My Account', 'user', ['admin', 'hod', 'faculty', 'placement', 'student']],
  ['admin/import.php', 'Import data', 'upload', ['admin']],
  ['admin/users.php', 'Users & audit', 'shield', ['admin']],
];
$icons = [
  'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
  'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
 'hierarchy' => '<path d="M12 3v6M6 12v6M18 12v6M3 18h6M15 18h6M6 12h12"/><circle cx="12" cy="3" r="2"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
 'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
 'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c.8-3.5 3.4-5 7-5s6.2 1.5 7 5"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14c2.6.2 4.4 1.6 5 4"/>',
 'alert' => '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.5"/>',
 'pie' => '<path d="M12 3a9 9 0 1 0 9 9h-9z"/><path d="M15 3.5A9 9 0 0 1 20.5 9H15z"/>',
 'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
 'heart' => '<path d="M12 20s-8-5-8-11a4.5 4.5 0 0 1 8-2.5A4.5 4.5 0 0 1 20 9c0 6-8 11-8 11z"/>',
 'sliders' => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
 'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/>',
 'spark' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 17l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z"/>',
 'bell' => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 21h4"/>',
 'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
 'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
];
$cur = ltrim(str_replace(BASE_URL, '', $_SERVER['SCRIPT_NAME']), '/');
$unread = unread_count();
$roleLabel = [
  'admin' => 'Administrator',
  'hod' => 'HOD (Head of Dept)',
  'faculty' => 'Teacher / Faculty',
  'placement' => 'Placement Officer',
  'student' => 'Student'
][$role] ?? $role;
$fl = take_flash();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Dashboard') ?> · <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
</head>
<body>
<div class="shell">
  <aside class="side" id="side">
    <a class="brand" href="<?= url('dashboard.php') ?>"><span>SURYA<b>MITHRA</b></span></a>
    <nav>
      <?php foreach ($nav as [$href, $label, $ic, $roles]): if (!in_array($role, $roles, true)) continue; ?>
        <a href="<?= url($href) ?>" class="<?= $cur === $href ? 'on' : '' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $icons[$ic] ?></svg>
          <span><?= e($label) ?></span>
          <?php if ($href === 'notifications.php' && $unread): ?><em><?= $unread ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a class="who" href="<?= url('profile.php') ?>" style="text-decoration:none; color:inherit; display:flex; align-items:center; gap:10px;">
        <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/../' . $user['avatar'])): ?>
          <img src="<?= url(e($user['avatar'])) ?>" alt="Avatar" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid var(--primary);">
        <?php else: ?>
          <span class="av"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span>
        <?php endif; ?>
        <div><strong><?= e($user['name']) ?></strong><small><?= e($roleLabel) ?></small></div>
      </a>
      <a class="out" href="<?= url('logout.php') ?>">Sign out</a>
    </div>
  </aside>
  <main class="main">
    <header class="top">
      <button class="burger" onclick="document.getElementById('side').classList.toggle('open')" aria-label="Menu">☰</button>
      <div><h1><?= e($pageTitle ?? 'Dashboard') ?></h1><?php if (!empty($pageSub)): ?><p><?= e($pageSub) ?></p><?php endif; ?></div>
      <a class="bell" href="<?= url('notifications.php') ?>" aria-label="Notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $icons['bell'] ?></svg>
        <?php if ($unread): ?><i><?= $unread ?></i><?php endif; ?>
      </a>
    </header>
    <?php if ($fl): ?><div class="flash <?= e($fl[1]) ?>"><?= e($fl[0]) ?></div><?php endif; ?>
    <div class="content">
