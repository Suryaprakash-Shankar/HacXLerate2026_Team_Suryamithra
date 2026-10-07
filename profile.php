<?php
$pageTitle = 'My account & profile';
$pageSub = 'Manage your account details, upload profile picture, and change password';
require_once __DIR__ . '/includes/functions.php';
$u = require_login();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Action 1: Upload Profile Picture / Avatar
    if ($action === 'update_avatar') {
        if (!empty($_FILES['avatar_file']['name'])) {
            $newAvatar = upload_avatar($_FILES['avatar_file'], $u['id']);
            if ($newAvatar) {
                audit('update_avatar', "Updated profile picture for {$u['email']}");
                flash('Profile picture updated successfully!');
                redirect('profile.php');
            } else {
                $error = 'Invalid image file. Please upload a JPEG, PNG, GIF, WebP, or SVG file.';
            }
        } else {
            $error = 'Please select an image file to upload.';
        }
    }

    // Action 2: Update Personal Info & Role-specific Bio
    if ($action === 'update_info') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $dept = trim($_POST['department'] ?? $u['department']);

        if (!$name || !$email) {
            $error = 'Name and email address are required.';
        } else {
            try {
                $pdo = db();
                // Check email uniqueness if changed
                $check = $pdo->prepare('SELECT id FROM users WHERE email=? AND id<>?');
                $check->execute([$email, $u['id']]);
                if ($check->fetch()) {
                    $error = 'That email address is already in use by another account.';
                } else {
                    $pdo->prepare('UPDATE users SET name=?, email=?, department=? WHERE id=?')
                        ->execute([$name, $email, $dept, $u['id']]);

                    // If user is a student, update student table & target job role as well
                    if ($u['role'] === 'student') {
                        $bio = trim($_POST['bio'] ?? '');
                        $interests = trim($_POST['interests'] ?? '');
                        $projects = trim($_POST['projects'] ?? '');
                        $pdo->prepare('UPDATE students SET name=?, email=?, department=?, bio=?, interests=?, projects=? WHERE user_id=?')
                            ->execute([$name, $email, $dept, $bio, $interests, $projects, $u['id']]);
                        
                        if (!empty($_POST['role_id']) && !empty($studentData['id'])) {
                            $roleId = (int)$_POST['role_id'];
                            $pdo->prepare('UPDATE placement SET role_id=? WHERE student_id=?')->execute([$roleId, $studentData['id']]);
                        }
                    }

                    audit('update_profile_info', "Updated name/email for {$u['email']}");
                    flash('Personal information updated successfully!');
                    redirect('profile.php');
                }
            } catch (PDOException $ex) {
                $error = 'Failed to update details: ' . $ex->getMessage();
            }
        }
    }

    // Action 3: Change Password
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!$currentPass || !$newPass || !$confirmPass) {
            $error = 'Please fill in all password fields.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPass) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } else {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id=?');
            $stmt->execute([$u['id']]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($currentPass, $hash)) {
                $error = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$newHash, $u['id']]);
                audit('change_password', "Password updated for {$u['email']}");
                flash('Password changed successfully!');
                redirect('profile.php');
            }
        }
    }
}

// Reload current student record if student
$studentData = null;
if ($u['role'] === 'student') {
    $studentData = student_by_user($u['id']);
}
$departments = load_departments();
$jobRoles = db()->query('SELECT id, name FROM job_roles ORDER BY name')->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php if ($error): ?>
  <div class="flash err" style="margin-bottom:20px"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid g2">
  <!-- Profile Picture & Overview Card -->
  <div class="card" style="text-align:center;">
    <div style="margin: 0 auto 16px; width: 120px; height: 120px; position: relative;">
      <?php if (!empty($u['avatar']) && file_exists(__DIR__ . '/' . $u['avatar'])): ?>
        <img src="<?= url(e($u['avatar'])) ?>" alt="<?= e($u['name']) ?>" style="width:120px; height:120px; border-radius:50%; object-fit:cover; border: 4px solid var(--primary); box-shadow:0 8px 24px rgba(0,0,0,0.12);">
      <?php else: ?>
        <div style="width:120px; height:120px; border-radius:50%; background:linear-gradient(135deg, var(--primary), #818cf8); color:#fff; display:flex; align-items:center; justify-content:center; font-size:42px; font-weight:800; border:4px solid #fff; box-shadow:0 8px 24px rgba(0,0,0,0.12);">
          <?= e(strtoupper(substr($u['name'], 0, 1))) ?>
        </div>
      <?php endif; ?>
    </div>

    <h2 style="margin-bottom:4px"><?= e($u['name']) ?></h2>
    <p class="muted" style="margin-top:0"><?= e($u['email']) ?></p>
    <div style="margin-bottom:16px">
      <span class="pill blue" style="font-size:13px"><?= e(ucfirst($u['role'])) ?></span>
      <span class="pill slate" style="font-size:13px; margin-left:4px"><?= e($u['department'] ?: 'General') ?></span>
    </div>

    <hr style="border:0; border-top:1px solid var(--line); margin:20px 0;">

    <form method="post" enctype="multipart/form-data" style="text-align:left">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_avatar">
      <label><strong>Upload Profile Picture</strong>
        <input type="file" name="avatar_file" accept="image/*" required style="margin-top:6px">
      </label>
      <small class="muted" style="display:block; margin-bottom:12px">Supports JPG, PNG, GIF, WebP, SVG (Max 5MB)</small>
      <button class="btn primary wide">Upload New Picture</button>
    </form>
  </div>

  <!-- Personal Details & Profile Info -->
  <div class="card">
    <h3>Personal Information</h3>
    <p class="sub">Update your account display name, email, and departmental settings.</p>

    <form method="post" class="formgrid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_info">
      
      <label>Full Name
        <input name="name" value="<?= e($_POST['name'] ?? $u['name']) ?>" required placeholder="Your full name">
      </label>

      <label>Email Address
        <input type="email" name="email" value="<?= e($_POST['email'] ?? $u['email']) ?>" required placeholder="you@suryamithra.local">
      </label>

      <label style="grid-column: 1/-1">Department
        <select name="department">
          <?php foreach ($departments as $d): ?>
            <option value="<?= e($d) ?>" <?= ($u['department'] === $d) ? 'selected' : '' ?>><?= e($d) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <?php if ($u['role'] === 'student' && $studentData): ?>
        <label style="grid-column: 1/-1">Target Job Role
          <select name="role_id">
            <?php foreach ($jobRoles as $jr): ?>
              <option value="<?= $jr['id'] ?>" <?= (($studentData['role_id'] ?? 0) == $jr['id']) ? 'selected' : '' ?>><?= e($jr['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label style="grid-column: 1/-1">Bio & Aspirations
          <textarea name="bio" rows="2" placeholder="Tell us about yourself..."><?= e($studentData['bio'] ?? '') ?></textarea>
        </label>
        <label style="grid-column: 1/-1">Areas of Interest
          <input name="interests" value="<?= e($studentData['interests'] ?? '') ?>" placeholder="e.g. Artificial Intelligence, Web Dev">
        </label>
        <label style="grid-column: 1/-1">Projects & Accomplishments
          <textarea name="projects" rows="2" placeholder="e.g. Built E-Commerce App, AWS certified"><?= e($studentData['projects'] ?? '') ?></textarea>
        </label>
      <?php endif; ?>

      <button class="btn primary" style="grid-column: 1/-1; margin-top:10px">Save Profile Details</button>
    </form>
  </div>
</div>

<!-- Change Password Section -->
<div class="card" style="margin-top:20px">
  <h3>Security & Password Settings</h3>
  <p class="sub">Change your account access password.</p>

  <form method="post" class="formgrid" style="max-width:600px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">

    <label style="grid-column: 1/-1">Current Password
      <input type="password" name="current_password" required placeholder="Enter current password">
    </label>

    <label>New Password
      <input type="password" name="new_password" required minlength="6" placeholder="At least 6 characters">
    </label>

    <label>Confirm New Password
      <input type="password" name="confirm_password" required minlength="6" placeholder="Repeat new password">
    </label>

    <button class="btn primary" style="grid-column: 1/-1; margin-top:10px">Update Password</button>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
