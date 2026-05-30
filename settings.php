<?php

//  InvenTech — Settings
//  File: settings.php

session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];

$backup_tables = [
    'users'         => 'Users',
    'categories'    => 'Categories',
    'zones'         => 'Zones',
    'shelves'       => 'Shelves',
    'appliances'    => 'Appliances',
    'transactions'  => 'Transactions',
    'activity_logs' => 'Activity Logs',
    'alerts'        => 'Alerts',
];

function get_table_columns(mysqli $conn, string $table): array {
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    return $columns;
}

function log_activity_safe(mysqli $conn, int $uid, string $action, string $description): void {
    try {
        $user_check = $conn->prepare("SELECT COUNT(*) AS cnt FROM users WHERE user_id = ?");
        $user_check->bind_param('i', $uid);
        $user_check->execute();
        $exists = (int)$user_check->get_result()->fetch_assoc()['cnt'];
        $user_check->close();

        if ($exists > 0) {
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
            $log->bind_param('iss', $uid, $action, $description);
            $log->execute();
            $log->close();
        }
    } catch (Throwable $e) {
        // Backup and recovery should continue even if audit logging is unavailable after a restore.
    }
}

// CSV export must run before any HTML is sent to the browser.
if (isset($_GET['action'], $_GET['table']) && $_GET['action'] === 'export_csv' && $role === 'admin') {
    $table = $_GET['table'];
    if (!array_key_exists($table, $backup_tables)) {
        http_response_code(400);
        exit('Invalid export table.');
    }

    $columns = get_table_columns($conn, $table);
    $filename = 'inventech_' . $table . '_' . date('Ymd_His') . '.csv';

    log_activity_safe($conn, $uid, 'edited', "Admin exported CSV backup for table: $table.");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputcsv($out, $columns);

    $result = $conn->query("SELECT * FROM `$table`");
    while ($row = $result->fetch_assoc()) {
        $line = [];
        foreach ($columns as $col) {
            $line[] = $row[$col];
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit();
}

// ── HANDLE POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── CHANGE PASSWORD ──
    if ($action === 'change_password') {
        $current  = trim($_POST['current_password']);
        $new_pass = trim($_POST['new_password']);
        $confirm  = trim($_POST['confirm_password']);

        // Get current password from DB
        $pw_stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $pw_stmt->bind_param('i', $uid);
        $pw_stmt->execute();
        $row = $pw_stmt->get_result()->fetch_assoc();
        $pw_stmt->close();

        // password_verify() checks entered password against the stored hash
        if (!password_verify($current, $row['password'])) {
            $error = "Current password is incorrect.";
        } elseif (empty($new_pass) || strlen($new_pass) < 6) {
            $error = "New password must be at least 6 characters.";
        } elseif ($new_pass !== $confirm) {
            $error = "New passwords do not match.";
        } else {
            // Hash the new password before storing it in the database
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param('si', $hashed, $uid);
            if ($stmt->execute()) {
                $log_desc = "User changed their password.";
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'edited', ?)");
                $log->bind_param('is', $uid, $log_desc);
                $log->execute();
                $success_security = "Password updated successfully.";
            } else {
                $error = "Failed to update password.";
            }
            $stmt->close();
        }
    }

    // ── ADD USER (admin only) ──
    if ($action === 'add_user' && $role === 'admin') {
        $full_name = trim($_POST['full_name']);
        $username  = trim($_POST['username']);
        $password  = trim($_POST['password']);
        $user_role = trim($_POST['user_role']);

        if (empty($full_name) || empty($username) || empty($password)) {
            $error_users = "Please fill in all required fields.";
        } else {
            // Hash the password before storing in the database
            $hashed_new = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $full_name, $username, $hashed_new, $user_role);
            if ($stmt->execute()) {
                $log_desc = "Added new user: $full_name ($username) as $user_role.";
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'added', ?)");
                $log->bind_param('is', $uid, $log_desc);
                $log->execute();
                $success_users = "User '$full_name' added successfully.";
            } else {
                $error_users = "Failed to add user. Username may already exist.";
            }
            $stmt->close();
        }
    }

    // ── EDIT USER (admin only) ──
    if ($action === 'edit_user' && $role === 'admin') {
        $edit_id   = intval($_POST['edit_user_id']);
        $full_name = trim($_POST['full_name']);
        $username  = trim($_POST['username']);
        $user_role = trim($_POST['user_role']);
        $status    = trim($_POST['status']);

        $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, role=?, status=? WHERE user_id=?");
        $stmt->bind_param('ssssi', $full_name, $username, $user_role, $status, $edit_id);
        if ($stmt->execute()) {
            $log_desc = "Updated user account: $full_name ($username).";
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'edited', ?)");
            $log->bind_param('is', $uid, $log_desc);
            $log->execute();
            $success_users = "User '$full_name' updated successfully.";
        } else {
            $error_users = "Failed to update user.";
        }
        $stmt->close();
    }

    // ── RESET USER PASSWORD (admin only) ──
    if ($action === 'reset_password' && $role === 'admin') {
        $reset_id  = intval($_POST['reset_user_id']);
        $new_pass  = trim($_POST['reset_password']);

        if (strlen($new_pass) < 6) {
            $error_users = "Password must be at least 6 characters.";
        } else {
            // Hash the reset password before storing
            $hashed_reset = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
            $stmt->bind_param('si', $hashed_reset, $reset_id);
            if ($stmt->execute()) {
                $tgt_stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
                $tgt_stmt->bind_param('i', $reset_id);
                $tgt_stmt->execute();
                $target = $tgt_stmt->get_result()->fetch_assoc();
                $tgt_stmt->close();
                $log_desc = "Admin reset password for user: {$target['full_name']}.";
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'edited', ?)");
                $log->bind_param('is', $uid, $log_desc);
                $log->execute();
                $success_users = "Password reset successfully.";
            }
            $stmt->close();
        }
    }

    // ── SOFT RESET ──
    if ($action === 'soft_reset' && $role === 'admin') {
        $confirm = trim($_POST['confirm_text'] ?? '');
        if ($confirm !== 'CONFIRM') {
            $error_danger = "Type CONFIRM exactly to proceed.";
        } else {
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            $conn->query("TRUNCATE TABLE transactions");
            $conn->query("TRUNCATE TABLE activity_logs");
            $conn->query("TRUNCATE TABLE alerts");
            $conn->query("UPDATE appliances SET status = 'inactive'");
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");

            // Log the reset
            $desc = "Admin performed a Soft Reset — appliances, transactions, logs and alerts cleared.";
            $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'deleted', ?)")
                 ->bind_param('is', $uid, $desc) || null;
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'deleted', ?)");
            $log->bind_param('is', $uid, $desc);
            $log->execute();

            $success_danger = "Soft Reset complete. Appliances, transactions, logs and alerts have been cleared.";
        }
    }

    // ── CLEAR IMAGE CACHE ──
    // ── FULL RESET ──
    if ($action === 'full_reset' && $role === 'admin') {
        $confirm = trim($_POST['confirm_text'] ?? '');
        if ($confirm !== 'CONFIRM') {
            $error_danger = "Type CONFIRM exactly to proceed.";
        } else {
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            $conn->query("TRUNCATE TABLE transactions");
            $conn->query("TRUNCATE TABLE activity_logs");
            $conn->query("TRUNCATE TABLE alerts");
            $conn->query("TRUNCATE TABLE appliances");
            $conn->query("TRUNCATE TABLE shelves");
            $conn->query("TRUNCATE TABLE zones");
            $conn->query("TRUNCATE TABLE categories");
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");

            $success_danger = "Full Reset complete. Inventory data, locations, categories, transactions, alerts, and activity logs have been cleared.";
        }
    }

    if ($action === 'clear_cache' && $role === 'admin') {
        $upload_dir = __DIR__ . '/uploads/appliances/';

        if (!is_dir($upload_dir)) {
            $error_danger = "Uploads directory not found.";
        } else {
            // Fetch ALL image filenames currently referenced in the appliances table
            // (both active AND inactive/archived — never delete an archived item's photo)
            $img_result = $conn->query("SELECT image FROM appliances WHERE image IS NOT NULL AND image != ''");
            $used_images = [];
            while ($row = $img_result->fetch_assoc()) {
                $used_images[] = $row['image'];
            }

            // Scan the uploads/appliances/ folder
            $all_files   = array_diff(scandir($upload_dir), ['.', '..']);
            $deleted     = 0;
            $skipped     = 0;
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

            foreach ($all_files as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext)) continue; // only touch image files
                if (!in_array($file, $used_images)) {
                    // Orphaned image — not referenced by any appliance record
                    if (unlink($upload_dir . $file)) {
                        $deleted++;
                    }
                } else {
                    $skipped++;
                }
            }

            $log_desc = "Admin cleared image cache — $deleted orphaned image(s) deleted, $skipped in-use image(s) kept.";
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'deleted', ?)");
            $log->bind_param('is', $uid, $log_desc);
            $log->execute();

            if ($deleted === 0) {
                $success_danger = "No orphaned images found — your uploads folder is already clean! ($skipped image(s) currently in use.)";
            } else {
                $success_danger = "Cache cleared! $deleted orphaned image(s) deleted. $skipped image(s) currently in use were kept.";
            }
        }
    }

    // ── IMPORT CSV BACKUP ──
    if ($action === 'import_csv' && $role === 'admin') {
        $table = $_POST['backup_table'] ?? '';
        $mode = $_POST['import_mode'] ?? 'replace';

        if (!array_key_exists($table, $backup_tables)) {
            $error_backup = "Please choose a valid table to restore.";
        } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $error_backup = "Please upload a valid CSV file.";
        } else {
            $expected_columns = get_table_columns($conn, $table);
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $header = $handle ? fgetcsv($handle) : false;

            if (!$handle || !$header) {
                $error_backup = "The CSV file is empty or unreadable.";
            } elseif ($header !== $expected_columns) {
                $error_backup = "CSV columns do not match the selected table. Export from this system first, then import that same file.";
            } else {
                $placeholders = implode(',', array_fill(0, count($expected_columns), '?'));
                $column_sql = '`' . implode('`,`', $expected_columns) . '`';
                $types = str_repeat('s', count($expected_columns));
                $insert_sql = "INSERT INTO `$table` ($column_sql) VALUES ($placeholders)";
                $insert_stmt = $conn->prepare($insert_sql);
                $imported = 0;

                $conn->begin_transaction();
                try {
                    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
                    if ($mode === 'replace') {
                        $conn->query("TRUNCATE TABLE `$table`");
                    }

                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) === 1 && trim($row[0]) === '') {
                            continue;
                        }
                        $row = array_pad($row, count($expected_columns), '');
                        $row = array_slice($row, 0, count($expected_columns));
                        $insert_stmt->bind_param($types, ...$row);
                        $insert_stmt->execute();
                        $imported++;
                    }

                    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                    $conn->commit();
                    fclose($handle);
                    $insert_stmt->close();

                    log_activity_safe($conn, $uid, 'edited', "Admin restored CSV backup into table: $table ($imported row(s), mode: $mode).");

                    $success_backup = "Imported $imported row(s) into " . $backup_tables[$table] . ".";
                } catch (Throwable $e) {
                    $conn->rollback();
                    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                    if ($handle) fclose($handle);
                    if (isset($insert_stmt)) $insert_stmt->close();
                    $error_backup = "Import failed. Check that the CSV belongs to the selected table and does not contain duplicate IDs.";
                }
            }
        }
    }
}

// ── FETCH DATA ────────────────────────────────────────────────
$users_list = $conn->query("SELECT * FROM users ORDER BY role ASC, full_name ASC");
$users      = [];
while ($r = $users_list->fetch_assoc()) $users[] = $r;

$cu_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$cu_stmt->bind_param('i', $uid);
$cu_stmt->execute();
$current_user = $cu_stmt->get_result()->fetch_assoc();
$cu_stmt->close();

$backup_counts = [];
if ($role === 'admin') {
    foreach ($backup_tables as $table => $label) {
        $count_result = $conn->query("SELECT COUNT(*) AS cnt FROM `$table`");
        $backup_counts[$table] = $count_result ? (int)$count_result->fetch_assoc()['cnt'] : 0;
    }
}

// Active tab
$tab = $_GET['tab'] ?? 'security';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InvenTech — Settings</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
<?php include __DIR__ . '/includes/styles.php'; ?>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
  <?php include __DIR__ . '/includes/topbar.php'; ?>

  <div class="content">

    <!-- Page Header -->
    <div class="section-header">
      <div>
        <div class="section-title">⚙️ Settings</div>
        <div class="section-sub">Manage your account, users, and system preferences</div>
      </div>
    </div>

    <div class="settings-layout">

      <!-- Settings Nav -->
      <div style="position:sticky;top:92px;align-self:start">
        <div class="settings-nav">
          <a href="?tab=security" class="settings-nav-item <?= $tab === 'security' ? 'active' : '' ?>">🔒 Security</a>
          <?php if ($role === 'admin'): ?>
          <a href="?tab=users"    class="settings-nav-item <?= $tab === 'users'    ? 'active' : '' ?>">👥 Users</a>
          <?php endif; ?>
          <a href="?tab=backup"   class="settings-nav-item <?= $tab === 'backup'   ? 'active' : '' ?>">💾 Backup</a>
          <a href="?tab=system"   class="settings-nav-item <?= $tab === 'system'   ? 'active' : '' ?>">🏢 System Info</a>
          <a href="?tab=terms"    class="settings-nav-item <?= $tab === 'terms'    ? 'active' : '' ?>">📋 Terms & Conditions</a>
          <?php if ($role === 'admin'): ?>
          <a href="?tab=danger"   class="settings-nav-item <?= $tab === 'danger'   ? 'active' : '' ?>" style="color:var(--red) !important">⚠️ Danger Zone</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Settings Content -->
      <div>

        <!-- ══ SECURITY TAB ══ -->
        <?php if ($tab === 'security'): ?>
        <div class="card">
          <div class="settings-section-title">🔒 Change Password</div>

          <?php if (!empty($success_security)): ?>
            <div style="background:var(--green-bg);color:#065f46;border:1px solid #6ee7b7;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
              ✅ <?= htmlspecialchars($success_security) ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($error) && $tab === 'security'): ?>
            <div style="background:var(--red-bg);color:#991b1b;border:1px solid #fca5a5;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
              ⚠️ <?= htmlspecialchars($error) ?>
            </div>
          <?php endif; ?>

          <!-- Current user info -->
          <div style="background:var(--blue-50);border:1px solid var(--blue-100);border-radius:10px;padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
            <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--blue-500),var(--blue-300));display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff">
              <?php
                $parts = explode(',', $current_user['full_name']);
                $sn = trim($parts[0] ?? ''); $fn = trim($parts[1] ?? '');
                echo strtoupper(substr($fn,0,1) . substr($sn,0,1));
              ?>
            </div>
            <div>
              <div style="font-size:14px;font-weight:700;color:var(--gray-800)"><?= htmlspecialchars($fn . ' ' . $sn) ?></div>
              <div style="font-size:12px;color:var(--gray-400)">@<?= htmlspecialchars($current_user['username']) ?> · <?= ucfirst($current_user['role']) ?></div>
            </div>
          </div>

          <form method="POST" action="settings.php?tab=security" style="max-width:400px">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
              <label class="form-label">Current Password *</label>
              <input class="form-ctrl" type="password" name="current_password" placeholder="Enter current password" required>
            </div>
            <div class="form-group">
              <label class="form-label">New Password * <span style="color:var(--gray-400);font-size:10px;text-transform:none">(min. 6 characters)</span></label>
              <input class="form-ctrl" type="password" name="new_password" placeholder="Enter new password" required>
            </div>
            <div class="form-group">
              <label class="form-label">Confirm New Password *</label>
              <input class="form-ctrl" type="password" name="confirm_password" placeholder="Re-enter new password" required>
            </div>
            <button type="submit" class="btn btn-primary">Update Password</button>
          </form>
        </div>
        <?php endif; ?>

        <!-- ══ USERS TAB ══ -->
        <?php if ($tab === 'users' && $role === 'admin'): ?>
        <div class="card" style="margin-bottom:20px">
          <div class="card-header">
            <div class="settings-section-title" style="margin-bottom:0">👥 System Users</div>
            <button class="btn btn-primary btn-sm" onclick="openModal('add-user-modal')">＋ Add User</button>
          </div>

          <?php if (!empty($success_users)): ?>
            <div style="background:var(--green-bg);color:#065f46;border:1px solid #6ee7b7;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
              ✅ <?= htmlspecialchars($success_users) ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($error_users)): ?>
            <div style="background:var(--red-bg);color:#991b1b;border:1px solid #fca5a5;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
              ⚠️ <?= htmlspecialchars($error_users) ?>
            </div>
          <?php endif; ?>

          <div class="table-container">
            <table>
              <thead>
                <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($users as $u):
                  $uparts = explode(',', $u['full_name']);
                  $usn = trim($uparts[0] ?? ''); $ufn = trim($uparts[1] ?? '');
                  $initials = strtoupper(substr($ufn,0,1) . substr($usn,0,1));
                ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px">
                      <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--blue-500),var(--blue-300));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff;flex-shrink:0">
                        <?= $initials ?>
                      </div>
                      <div class="td-main"><?= htmlspecialchars($ufn . ' ' . $usn) ?></div>
                    </div>
                  </td>
                  <td class="text-muted">@<?= htmlspecialchars($u['username']) ?></td>
                  <td>
                    <span class="pill <?= $u['role'] === 'admin' ? 'pill-blue' : 'pill-gray' ?>">
                      <?= ucfirst($u['role']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="pill <?= $u['status'] === 'active' ? 'pill-green' : 'pill-red' ?>">
                      <?= ucfirst($u['status']) ?>
                    </span>
                  </td>
                  <td>
                    <div class="flex gap-8">
                      <?php if ($u['user_id'] != $uid): ?>
                        <button class="btn btn-secondary btn-sm"
                          onclick='openEditUser(<?= json_encode($u) ?>)'>✏️ Edit</button>
                        <button class="btn btn-secondary btn-sm"
                          onclick='openResetPassword(<?= $u["user_id"] ?>, <?= json_encode($ufn . " " . $usn) ?>)'>
                          🔑 Reset PW
                        </button>
                      <?php else: ?>
                        <span class="text-muted" style="font-size:12px">You</span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>


        <!-- ══ BACKUP TAB ══ -->
        <?php if ($tab === 'backup'): ?>
        <?php if (!empty($success_backup)): ?>
          <div class="alert-msg" style="background:var(--green-bg);color:#065f46;border:1px solid #6ee7b7;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:600;">
            ✅ <?= htmlspecialchars($success_backup) ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($error_backup)): ?>
          <div class="alert-msg" style="background:var(--red-bg);color:#991b1b;border:1px solid #fca5a5;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:600;">
            ⚠️ <?= htmlspecialchars($error_backup) ?>
          </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom:20px">
          <div class="settings-section-title">💾 CSV Backup & Recovery</div>
          <div style="font-size:13px;color:var(--gray-500);line-height:1.6;margin-bottom:20px">
            Export tables as CSV files for backup, spreadsheet review, or recovery. For restore, import CSV files that were exported from this same system so the columns match exactly.
          </div>

          <div class="table-container">
            <table>
              <thead>
                <tr><th>Data Table</th><th>Rows</th><th>Use</th><th>Export</th></tr>
              </thead>
              <tbody>
                <?php foreach ($backup_tables as $table => $label): ?>
                <tr>
                  <td class="td-main"><?= htmlspecialchars($label) ?></td>
                  <td><span class="pill pill-gray"><?= $backup_counts[$table] ?? 0 ?> rows</span></td>
                  <td class="text-muted">
                    <?php
                      $uses = [
                        'users' => 'System accounts and roles',
                        'categories' => 'Appliance category list',
                        'zones' => 'Warehouse or storage zones',
                        'shelves' => 'Shelf locations per zone',
                        'appliances' => 'Main inventory records',
                        'transactions' => 'Stock movement history',
                        'activity_logs' => 'User activity audit trail',
                        'alerts' => 'System alerts and warnings',
                      ];
                      echo htmlspecialchars($uses[$table] ?? 'System data');
                    ?>
                  </td>
                  <td>
                    <a class="btn btn-secondary btn-sm" href="settings.php?action=export_csv&table=<?= urlencode($table) ?>">⬇ Export CSV</a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if ($role === 'admin'): ?>
        <div class="card" style="border-color:var(--green);border-width:1.5px">

          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;padding-bottom:10px;border-bottom:1px solid var(--gray-200)">
            <div style="font-size:14px;font-weight:800;color:#047857">♻️ Restore From CSV</div>
            <span class="pill pill-green" style="font-size:10px">Admin only</span>
          </div>

          <div style="background:var(--green-bg);border:1px solid #6ee7b7;border-radius:10px;padding:12px 16px;margin-bottom:22px;font-size:12px;color:#065f46;line-height:1.7">
            <span style="font-weight:700">For full recovery, restore tables in this order:</span><br>
            <span style="opacity:.85">Users → Categories → Zones → Shelves → Appliances → Transactions → Activity Logs → Alerts</span>
          </div>

          <form method="POST" action="settings.php?tab=backup" enctype="multipart/form-data" id="restore-csv-form">
            <input type="hidden" name="action" value="import_csv">
            <input type="hidden" name="import_mode" value="replace">

            <div style="margin-bottom:18px">
              <div style="font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Step 1 — Select the table to restore into</div>
              <select class="form-ctrl" name="backup_table" id="restore-backup-table" required
                style="font-size:13px;font-weight:600;color:var(--gray-800)"
                onchange="updateRestoreTableHint(this)">
                <?php foreach ($backup_tables as $table => $label): ?>
                  <option value="<?= htmlspecialchars($table) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
              <div id="restore-table-hint" style="margin-top:8px;font-size:12px;color:var(--gray-500);padding:8px 12px;background:var(--gray-50);border-radius:8px;border:1px solid var(--gray-200);line-height:1.5">
                <?php
                  $hints = ['users'=>'👤 System accounts and login credentials.','categories'=>'🏷️ Appliance category list.','zones'=>'🗺️ Warehouse or storage zones.','shelves'=>'📦 Shelf locations per zone.','appliances'=>'🔌 Main inventory records.','transactions'=>'🔄 Stock movement history.','activity_logs'=>'📋 User activity audit trail.','alerts'=>'🔔 System alerts and warnings.'];
                  echo htmlspecialchars($hints[array_key_first($backup_tables)] ?? '');
                ?>
              </div>
            </div>

            <div style="margin-bottom:22px">
              <div style="font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Step 2 — Upload the CSV backup file</div>

              <input id="restore-csv-input" type="file" name="csv_file" accept=".csv,text/csv" required
                style="display:none" onchange="handleBackupFileSelect(this)">

              <div id="restore-dropzone"
                style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:28px 20px;border:2px dashed #6ee7b7;border-radius:12px;background:var(--green-bg);cursor:pointer;transition:border-color .15s,background .15s;text-align:center"
                onclick="document.getElementById('restore-csv-input').click()">
                <div style="font-size:28px;line-height:1;pointer-events:none">📂</div>
                <div style="font-size:13px;font-weight:700;color:#047857;pointer-events:none">Click to choose a CSV file</div>
                <div style="font-size:11px;color:#6b7280;pointer-events:none">or drag and drop here &middot; .csv files only</div>
              </div>

              <div id="restore-file-selected" style="display:none;align-items:center;gap:10px;padding:10px 14px;background:var(--white);border:1.5px solid #6ee7b7;border-radius:10px;margin-top:10px">
                <span style="font-size:18px">📄</span>
                <div style="flex:1;min-width:0">
                  <div id="restore-csv-file-name" style="font-size:13px;font-weight:700;color:#047857;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>
                  <div id="restore-csv-file-size" style="font-size:11px;color:var(--gray-400);margin-top:1px"></div>
                </div>
                <button type="button" onclick="clearRestoreFile()"
                  style="flex-shrink:0;width:26px;height:26px;border-radius:6px;border:1.5px solid var(--gray-200);background:var(--white);cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;color:var(--gray-400);transition:all .15s"
                  onmouseover="this.style.background='var(--red-bg)';this.style.borderColor='var(--red)';this.style.color='var(--red)'"
                  onmouseout="this.style.background='var(--white)';this.style.borderColor='var(--gray-200)';this.style.color='var(--gray-400)'">✕</button>
              </div>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding-top:18px;border-top:1px solid var(--gray-200)">
              <div style="font-size:12px;color:var(--gray-400);line-height:1.5;flex:1">
                ⚠️ Existing rows in the selected table will be <strong style="color:var(--gray-600)">replaced</strong> after CSV columns are validated.
              </div>
              <button type="submit" class="btn"
                style="background:var(--green);border-color:var(--green);color:#fff;box-shadow:0 4px 16px rgba(16,185,129,.22);flex-shrink:0;padding:10px 22px;white-space:nowrap">
                ♻️ Restore CSV
              </button>
            </div>
          </form>
        </div>

        <script>
          var restoreHints = {
            users:'👤 System accounts and login credentials.',
            categories:'🏷️ Appliance category list.',
            zones:'🗺️ Warehouse or storage zones.',
            shelves:'📦 Shelf locations per zone.',
            appliances:'🔌 Main inventory records.',
            transactions:'🔄 Stock movement history.',
            activity_logs:'📋 User activity audit trail.',
            alerts:'🔔 System alerts and warnings.'
          };

          function updateRestoreTableHint(sel) {
            var hint = document.getElementById('restore-table-hint');
            if (hint) hint.textContent = restoreHints[sel.value] || '';
          }

          function applyRestoreFile(file) {
            if (!file) return;
            document.getElementById('restore-csv-file-name').textContent = file.name;
            document.getElementById('restore-csv-file-size').textContent = file.size < 1024 ? file.size + ' B'
              : file.size < 1048576 ? (file.size/1024).toFixed(1) + ' KB'
              : (file.size/1048576).toFixed(2) + ' MB';
            document.getElementById('restore-dropzone').style.display      = 'none';
            document.getElementById('restore-file-selected').style.display = 'flex';
          }

          function handleBackupFileSelect(input) {
            if (input.files && input.files.length) applyRestoreFile(input.files[0]);
          }

          function clearRestoreFile() {
            document.getElementById('restore-csv-input').value = '';
            document.getElementById('restore-file-selected').style.display = 'none';
            document.getElementById('restore-dropzone').style.display      = 'flex';
          }

          (function() {
            var dz = document.getElementById('restore-dropzone');
            if (!dz) return;
            dz.addEventListener('dragenter', function(e) { e.preventDefault(); e.stopPropagation(); });
            dz.addEventListener('dragover',  function(e) {
              e.preventDefault(); e.stopPropagation();
              dz.style.borderColor = '#059669'; dz.style.background = '#bbf7d0';
            });
            dz.addEventListener('dragleave', function(e) {
              e.preventDefault(); e.stopPropagation();
              dz.style.borderColor = '#6ee7b7'; dz.style.background = 'var(--green-bg)';
            });
            dz.addEventListener('drop', function(e) {
              e.preventDefault(); e.stopPropagation();
              dz.style.borderColor = '#6ee7b7'; dz.style.background = 'var(--green-bg)';
              var file = e.dataTransfer.files[0];
              if (!file) return;
              var input = document.getElementById('restore-csv-input');
              try { var dt = new DataTransfer(); dt.items.add(file); input.files = dt.files; } catch(err) {}
              applyRestoreFile(file);
            });
          })();
        </script>
        <?php endif; ?>
        <?php endif; ?>

        <!-- ══ TERMS & CONDITIONS TAB ══ -->
        <?php if ($tab === 'terms'): ?>
        <div class="card">
          <div class="settings-section-title">📋 Terms and Conditions</div>
          <div style="font-size:12px;color:var(--gray-400);margin-bottom:20px">
            These are the Terms and Conditions that all users must agree to before accessing the system.
          </div>

          <div style="font-size:13px;color:var(--gray-600);line-height:1.9;max-width:100%">

            <div style="margin-bottom:22px">
              <div style="font-size:14px;font-weight:800;color:var(--gray-800);margin-bottom:8px">1. System Overview and Core Architecture</div>
              <p style="margin-bottom:10px">
                The InvenTech Appliance Inventory Management System is a data-driven web application built on a local server environment (Laragon) utilizing a PHP backend and a MySQL relational database.
              </p>
              <div style="font-size:13px;font-weight:700;color:var(--gray-700);margin-bottom:6px">How the System Works (For Developers &amp; Students)</div>
              <p style="margin-bottom:8px">To help student developers understand the structure of this inventory system, its architecture relies on several interconnected database components:</p>
              <ul style="padding-left:20px;display:flex;flex-direction:column;gap:6px">
                <li><strong>Relational Integrity:</strong> The system uses 8 core tables (users, categories, zones, shelves, appliances, transactions, activity_logs, and alerts) mapped using formal relational database architecture to prevent orphaned data.</li>
                <li><strong>Automation via Triggers:</strong> A custom MySQL trigger (<code>trg_low_stock_alert</code>) automatically detects when stock levels drop to or below minimum thresholds, instantly pushing an unread notification to the Alerts page. Restocking items automatically dismisses these alerts.</li>
                <li><strong>Secure Transactions:</strong> Data integrity during stock movements is protected by a MySQL Stored Procedure (<code>sp_log_transaction</code>) utilizing strict BEGIN, COMMIT, and ROLLBACK commands. This ensures that a stock count never updates without a corresponding transaction log entry being successfully created.</li>
                <li><strong>Performance Optimization:</strong> Database Indexes are applied to high-traffic search queries (appliance names, SKUs, conditions, and transaction dates) to prevent slow load times as the inventory scales.</li>
              </ul>
            </div>

            <div style="margin-bottom:22px">
              <div style="font-size:14px;font-weight:800;color:var(--gray-800);margin-bottom:8px">2. Acceptance of Terms</div>
              <p>
                By checking the required agreement box on the Login page, accessing, or using the InvenTech system, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions. If you do not agree to these terms, you are prohibited from using this system.
              </p>
            </div>

            <div style="margin-bottom:22px">
              <div style="font-size:14px;font-weight:800;color:var(--gray-800);margin-bottom:8px">3. Role-Based Access Control and Authorized Use</div>
              <p style="margin-bottom:8px">This system is intended for authorized organizational personnel only. Features, administrative rights, and interface visuals are strictly dictated by user roles:</p>
              <ul style="padding-left:20px;display:flex;flex-direction:column;gap:6px">
                <li><strong>Admin Accounts:</strong> Full administrative control. Admins have access to destructive actions including CSV Restoration, User Management, System Audits, and the Danger Zone (Soft Reset and Full Reset capabilities). Admin portals default to a professional blue theme.</li>
                <li><strong>Staff Accounts:</strong> Limited operational access. Staff are permitted to perform day-to-day operations, view inventory, print reports, and access Settings tabs (System Info, Terms &amp; Conditions, and CSV Export-only Backups). Staff portals feature a distinct pastel purple theme and role badge.</li>
              </ul>
              <p style="margin-top:10px"><strong>User Prohibitions:</strong></p>
              <ul style="padding-left:20px;display:flex;flex-direction:column;gap:6px">
                <li>Users must restrict activities to features explicitly assigned to their roles.</li>
                <li>Sharing account credentials or leaving active sessions unattended on shared workstations is strictly forbidden.</li>
                <li>Any deliberate attempt to bypass security protocols, execute unauthorized SQL queries, manipulate system files, or exploit the local intranet environment will result in immediate permanent deactivation.</li>
              </ul>
            </div>

            <div style="margin-bottom:22px">
              <div style="font-size:14px;font-weight:800;color:var(--gray-800);margin-bottom:8px">4. Operational User Responsibilities</div>
              <p style="margin-bottom:8px">All active system users are bound by the following data integrity responsibilities:</p>
              <ul style="padding-left:20px;display:flex;flex-direction:column;gap:6px">
                <li><strong>Data Accuracy:</strong> Ensure all inventory counts, appliance conditions, categories, and locations (Zones and Shelves) are entered accurately.</li>
                <li><strong>Real-time Tracking:</strong> Record stock-in and stock-out activities immediately to maintain trusted inventory records.</li>
                <li><strong>Account Safety:</strong> Users must explicitly log out after each session to prevent local session hijacking.</li>
                <li><strong>Infrastructure Reporting:</strong> Any system malfunction, suspicious log activity, duplicate alert rendering, or unexpected data behavior must be reported immediately to the system administrator.</li>
              </ul>
            </div>

            <div style="margin-bottom:22px">
              <div style="font-size:14px;font-weight:800;color:var(--gray-800);margin-bottom:8px">5. Data Privacy, Backups, and Security</div>
              <ul style="padding-left:20px;display:flex;flex-direction:column;gap:6px">
                <li><strong>Confidentiality:</strong> All inventory metrics, pricing schemas, supplier specifics, and user activity logs are strictly confidential.</li>
                <li><strong>Security Protocols:</strong> The system enforces standard digital security, including replacing all plain-text passwords with industry-standard cryptographic hashing (<code>password_hash</code> and <code>password_verify</code>) and protecting all forms against SQL Injection utilizing strict MySQLi prepared statements (<code>bind_param</code>).</li>
                <li><strong>Data Management &amp; CSV Operations:</strong> Backups exported via the system must be handled safely. Importing CSV data requires precise table matching and schema validation to avoid database corruption.</li>
              </ul>
            </div>

            <div style="margin-bottom:22px">
              <div style="font-size:14px;font-weight:800;color:var(--gray-800);margin-bottom:8px">6. System Availability and Intranet Limitations</div>
              <p>
                This system operates entirely within a local intranet environment hosting environment (Laragon). The development team does not guarantee uninterrupted system uptime and reserves the right to perform critical patches, updates, database table optimization, or full structural resets during authorized maintenance windows.
              </p>
            </div>

            <div style="margin-bottom:22px">
              <div style="font-size:14px;font-weight:800;color:var(--gray-800);margin-bottom:8px">7. Limitation of Liability</div>
              <p>
                The system administrators, student developers, and academic supervisors shall not be held liable for any loss of data, corrupt InnoDB tables, system exploitation, or operational damages arising from user negligence, improper local server shutdowns, or unapproved record modifications.
              </p>
            </div>

            <div>
              <div style="font-size:14px;font-weight:800;color:var(--gray-800);margin-bottom:8px">8. Amendments and Document Tracking</div>
              <p>
                These Terms and Conditions may be modified over time to align with system feature expansions. Continued usage of InvenTech post-update constitutes absolute agreement to the current revision.
              </p>
              <p style="margin-top:8px">
                A comprehensive documentation of system growth, features, and fixes can be viewed below.
              </p>
            </div>

          </div>

          <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--gray-200);font-size:12px;color:var(--gray-400)">
            Last updated: May 2026 · InvenTech v1.8.4
          </div>
        </div>
        <?php endif; ?>

        <!-- ══ DANGER ZONE TAB ══ -->
        <?php if ($tab === 'danger' && $role === 'admin'): ?>

        <?php if (!empty($success_danger)): ?>
          <div class="alert-msg" style="background:var(--green-bg);color:#065f46;border:1px solid #6ee7b7;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:600;">
            ✅ <?= htmlspecialchars($success_danger) ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($error_danger)): ?>
          <div class="alert-msg" style="background:var(--red-bg);color:#991b1b;border:1px solid #fca5a5;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:600;">
            ⚠️ <?= htmlspecialchars($error_danger) ?>
          </div>
        <?php endif; ?>

        <!-- Maintenance Card -->
        <div class="card" style="margin-bottom:16px">
          <div class="settings-section-title" style="margin-bottom:6px">🛠️ Maintenance</div>
          <div style="font-size:13px;color:var(--gray-500);margin-bottom:20px;line-height:1.6">
            Safe housekeeping tools that clean up unused files without touching any appliance data or records.
          </div>

          <!-- Clear Image Cache -->
          <div style="border:1px solid var(--gray-100);border-radius:var(--radius-md);padding:20px">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap">
              <div>
                <div style="font-size:14px;font-weight:800;color:var(--gray-800);margin-bottom:4px">🗑️ Clear Image Cache</div>
                <div style="font-size:13px;color:var(--gray-500);line-height:1.6;max-width:1000px">
                  Scans the <code style="background:var(--gray-100);padding:2px 5px;border-radius:4px;font-size:11px">uploads/appliances/</code> folder and permanently deletes image files that are <strong>no longer linked to any appliance</strong> — including archived ones.<br>
                  <span style="color:#16a34a;font-weight:600">✓ Safe:</span> Images currently in use (active or archived) are never touched.
                </div>
              </div>
              <form method="POST" action="settings.php?tab=danger" id="form-clear-cache">
                <input type="hidden" name="action" value="clear_cache">
                <button type="button" class="btn btn-secondary"
                  style="flex-shrink:0;border-color:var(--gray-400);color:var(--gray-600)"
                  onclick="confirmAction('Clear Image Cache','This will scan the uploads/appliances/ folder and permanently delete orphaned images. Images currently in use by any appliance (active or archived) will NOT be deleted.','🗑️','form-clear-cache','Clear Cache','btn-secondary')">
                  🗑️ Clear Image Cache
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Danger Zone Card -->
        <div class="card" style="border-color:var(--red)">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
            <div class="settings-section-title" style="margin-bottom:0;color:var(--red);width:100%">⚠️ Danger Zone</div>
          </div>
          <div style="font-size:13px;color:var(--gray-500);margin-bottom:24px;line-height:1.6">
            These actions are <strong>irreversible</strong>. Please make sure you understand what each reset does before proceeding. All resets require you to type <code style="background:var(--gray-100);padding:2px 6px;border-radius:4px;font-size:12px">CONFIRM</code> to execute.
          </div>

          <!-- Soft Reset -->
          <div style="border:1px solid var(--gray-200);border-radius:var(--radius-md);padding:20px;margin-bottom:16px">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap">
              <div>
                <div style="font-size:14px;font-weight:800;color:var(--gray-800);margin-bottom:4px">🧹 Soft Reset</div>
                <div style="font-size:13px;color:var(--gray-500);line-height:1.6;max-width:1000px">
                  Clears all <strong>appliances, transactions, activity logs, and alerts</strong>.<br>
                  Keeps: user accounts, categories, zones, and shelves.
                </div>
              </div>
              <button class="btn btn-secondary" onclick="openDangerModal('soft')"
                style="border-color:var(--yellow);color:var(--yellow);flex-shrink:0">
                🧹 Soft Reset
              </button>
            </div>
          </div>

          <!-- Full Reset -->
          <div style="border:1px solid var(--red-bg);border-radius:var(--radius-md);padding:20px;background:var(--red-bg)">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap">
              <div>
                <div style="font-size:14px;font-weight:800;color:var(--red);margin-bottom:4px">💣 Full Reset</div>
                <div style="font-size:13px;color:var(--gray-600);line-height:1.6;max-width:1000px">
                  Clears <strong>everything</strong> — appliances, transactions, logs, alerts, categories, zones, and shelves.<br>
                  Keeps: user accounts only. <strong style="color:var(--red)">This cannot be undone.</strong>
                </div>
              </div>
              <button class="btn btn-danger" onclick="openDangerModal('full')"
                style="flex-shrink:0;border:1px solid red">
                💣 Full Reset
              </button>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- ══ SYSTEM INFO TAB ══ -->
        <?php if ($tab === 'system'): ?>
        <div class="card">
          <div class="settings-section-title">🏢 System Information</div>
          <div style="display:grid;gap:0;max-width:100%px">

            <?php
            $sys_rows = [
              ['System Name',  'InvenTech'],
              ['Version',      'v1.8.4'],
              ['Description',  'Appliance Inventory Management System'],
              ['Database',     'inventech_db (MySQL)'],
              ['Server',       'localhost (Laragon)'],
              ['Current Date', date('F j, Y')],
              ['Logged in as', '@' . htmlspecialchars($current_user['username']) . ' (' . ucfirst($current_user['role']) . ')'],
            ];
            foreach ($sys_rows as $i => $row):
              $is_last = $i === count($sys_rows) - 1;
              $is_version = $row[0] === 'Version';
              $is_user = $row[0] === 'Logged in as';
            ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:13px 0;<?= $is_last ? '' : 'border-bottom:1px solid var(--gray-100)' ?>">
              <span style="font-size:13px;font-weight:600;color:var(--gray-500)"><?= $row[0] ?></span>
              <span style="font-size:13px;font-weight:700;color:<?= $is_user ? 'var(--blue-600)' : 'var(--gray-800)' ?>;text-align:right;max-width:340px">
                <?php if ($is_version): ?>
                  <span class="pill pill-blue"><?= $row[1] ?></span>
                <?php else: ?>
                  <?= $row[1] ?>
                <?php endif; ?>
              </span>
            </div>
            <?php endforeach; ?>

          </div>

          <div style="margin-top:28px">
            <div style="font-size:13px;font-weight:800;color:var(--gray-700);margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--gray-200)">
              📋 Version History
            </div>
            <?php
            $versions = [
              ['1.8.4', 'patch',  'Replaced the native browser confirm() dialog on the Clear Image Cache button in the Danger Zone tab with the existing custom InvenTech confirmation modal. The button now uses confirmAction() and type="button" with a named form ID, matching the same pattern used by all other destructive actions in the system. Function is unchanged — orphaned images in uploads/appliances/ are still scanned and deleted while images linked to any appliance (active or archived) are kept.'],
              ['1.8.3', 'patch',  'Added Edit Zone and Edit Shelf functionality to the Zones & Shelves page. Zone stat cards now show a ✏️ edit button alongside the 🗑️ delete button, opening a pre-filled Edit Zone modal for updating the zone name and description. Shelf headers inside the zone detail modal now show the same ✏️ and 🗑️ button pair, replacing the previous single Delete button. Edit Shelf modal opens pre-filled with the current shelf name and description. Both modals post to new edit_zone and edit_shelf PHP handlers with activity logging. The Edit Shelf modal is rendered at z-index 400 so it always appears above the zone detail modal. Additionally fixed the shelf header layout where long shelf names or wide pill combinations (e.g. Shelf A2 with 3 types and 75 units) caused the name to wrap to a second line and misalign the description — the name is now nowrap, pills are flex-shrink:0, and the description is moved to the left side with text-overflow ellipsis.'],
              ['1.8.2', 'patch',  'Redesigned the Restore From CSV section in the Backup tab for improved usability. Replaced the cramped single-row layout with a clear two-step vertical flow. Step 1 shows the table selector with a live hint describing each table. Step 2 uses a div-based drag-and-drop upload zone with dragenter, dragover, dragleave, and drop events handled explicitly to avoid the browser file-hijack issue with label-based drop zones. Once a file is selected, the zone collapses into a file card showing the filename and size with a clear button to reselect. The submit row warning text uses flex:1 so it never wraps unexpectedly. The settings navigation sidebar is now sticky at top:92px (below the 64px topbar and 28px content padding), keeping tabs visible while scrolling long pages.'],
              ['1.8.1', 'patch',  'Removed rounded stroke caps from the Condition Breakdown donut chart on the Dashboard for a cleaner, more precise ring appearance. Updated server environment reference from XAMPP to Laragon across Settings System Info tab, reflecting the migration that took place at v1.3.0. Staff users can now access the Backup (export only), System Info, and Terms & Conditions tabs in Settings — Restore CSV, Users, and Danger Zone remain admin-only.'],
              ['1.8.0', 'minor',  'Alerts page overhauled — added soft delete (archive) and unarchive (restore) for individual alerts, bulk delete all archived alerts, and Mark All as Read with a confirmation dialog. All alert actions now record entries in Activity Logs. Fixed a MySQL trigger bug (trg_low_stock_alert) that caused duplicate low stock alerts to stack up — the trigger now updates the existing unread alert instead of inserting a new one, and auto-dismisses alerts when stock is restocked above the minimum threshold.'],
              ['1.7.4', 'patch',  'Fixed CSV backup and restore audit logging so export and restore actions appear reliably in Activity Logs. These actions now use the existing Edited log type with clear export/restore descriptions to match the database action constraint.'],
              ['1.7.3', 'patch',  'Replaced the native browser confirmation on CSV restore with a custom InvenTech modal matching the rest of the system. Updated the restore CSV file input to use the same styled Choose File button pattern used by appliance photo uploads, including a filename display.'],
              ['1.7.2', 'patch',  'Refined the Backup restore interface to make CSV recovery easier and friendlier. The restore form now asks for the CSV file first, then the data table to restore, and uses a lighter green recovery style instead of danger styling. Removed the extra typed RESTORE field while keeping table and column validation in place.'],
              ['1.7.1', 'patch',  'Fixed Full Reset behavior so activity logs remain completely empty after a full system reset. Full Reset now has a dedicated backend handler that clears inventory data, locations, categories, transactions, alerts, and activity logs without creating a new post-reset activity entry.'],
              ['1.7.0', 'minor',  'Added a dedicated Backup tab in Settings for Admins, allowing CSV export of users, categories, zones, shelves, appliances, transactions, activity logs, and alerts. Added CSV restore with table matching, column validation, RESTORE confirmation, and recovery guidance. Improved Reports printing with a modern print-only report header, compact inventory summary metrics, consistent table borders, rounded print styling, and a signature approval section for Prepared By, Checked By, and Approved By.'],
              ['1.6.0', 'minor',  'Zones page overhauled — removed the per-zone shelf list display to clean up the page layout; retained only the Condition Overview table. Added a View button per zone that opens a detailed modal showing zone name, description, total units, and shelf contents. Add and Edit buttons inside the zone modal now redirect the user to the Appliances page and automatically open the corresponding Add or Edit modal. Shelf delete buttons are visible inside the zone modal for Admins only, each with a confirmation dialog before proceeding.'],
              ['1.5.0', 'minor',  'Added sortable column headers on the Appliances table — users can now click the column name beside SKU, Name, Brand, Category, Stock Count, and Condition to sort the table in ascending or descending order. Sort state is indicated by an arrow icon beside the active column header.'],
              ['1.4.2', 'patch',  'Custom confirmation dialogs fully integrated across all destructive actions in the system. Soft delete (archive) and restore on the Appliances page were completely fixed — the confirm modal now correctly submits the form after user approval. The missing action attribute on the restore form was identified as the root cause and resolved.'],
              ['1.4.1', 'patch',  'Fixed multiple issues in the Appliances page modals — the Add Appliance modal footer containing the Save and Cancel buttons was being pushed outside the visible area due to missing max-height and scroll handling on the modal body. Fixed inconsistent image preview sizing in both Add and Edit modals where images would not maintain a 1:1 ratio. Fixed the filename display overflowing beyond the modal boundaries. Fixed the Clear Selection button disappearing after a file was chosen. Fixed soft delete not working due to a JavaScript block that was preventing form submission — root cause was identified as a conflicting onsubmit handler introduced in a prior version.'],
              ['1.4.0', 'minor',  'Added item photo upload support — each appliance can now have a dedicated photo stored in the uploads/appliances/ directory. Photos display as thumbnails in the Appliances table and can be clicked to open a full-screen photo viewer modal. Added Terms and Conditions popup on the Login page with a required checkbox before users can sign in. A dedicated Terms and Conditions tab was added to Settings for logged-in users to reference. Implemented auto-increment SKU generation — the SKU field is now greyed out and read-only, automatically assigned as APL-001, APL-002, and so on based on insertion order. Introduced role-based color themes — Staff accounts display a pastel purple theme across the sidebar, buttons, and accents, while Admin accounts retain the original blue theme. A role badge now appears in the topbar beside the username.'],
              ['1.3.0', 'minor',  'Added item photos, auto SKU generation, Terms and Conditions for login and settings, and role-based color themes as initial implementation before subsequent fixes in v1.4.0 and v1.4.1. Also migrated the development environment from XAMPP to Laragon at this version due to persistent InnoDB table corruption and instability issues encountered with XAMPP during earlier development.'],
              ['1.2.5', 'patch',  'Replaced all native browser confirm() dialogs — which displayed an ugly localhost says popup — with a custom styled confirmation modal that matches the system design. Each destructive action now shows a clean modal with an icon, title, description, and clearly labeled action buttons.'],
              ['1.2.4', 'minor',  'Implemented all remaining advanced database requirements — SQL Trigger (trg_low_stock_alert) that automatically creates an alert when appliance stock is updated to at or below the minimum threshold; Stored Procedure (sp_log_transaction) that handles the full stock transaction flow using BEGIN, COMMIT, and ROLLBACK; SQL View (vw_inventory_summary) joining four tables used by the Reports page; database Indexes on appliance name, SKU, condition, and transaction date for search optimization; and password_hash and password_verify replacing all plain text password handling.'],
              ['1.2.3', 'patch',  'Performed a full audit of all PHP files and eliminated every instance of direct variable interpolation inside SQL query strings. All ten PHP files now use prepared statements with bind_param exclusively, removing the risk of SQL Injection and the associated 20-point academic deduction.'],
              ['1.2.2', 'minor',  'Added a dedicated Categories management page accessible from the sidebar, allowing Admins to add, edit, and delete appliance categories with protection against deleting categories that are currently assigned to active appliances. Added a Danger Zone tab inside Settings with two reset options — Soft Reset which clears appliances, transactions, logs, and alerts while preserving categories, zones, shelves, and users; and Full Reset which wipes all inventory data except user accounts. Both actions require typing CONFIRM in a modal before executing.'],
              ['1.2.1', 'patch',  'Added version history tracking to the System Info tab in Settings, displaying the full changelog with version numbers and release type labels (Major, Minor, Patch).'],
              ['1.2.0', 'minor',  'Major bug fix release addressing five reported issues — corrected inflated unit counts on the Zones page caused by a cartesian product in the JOIN query; fixed the Add and Edit appliance CRUD operations which were throwing fatal errors due to incorrect bind_param type strings; resolved duplicate shelf entries appearing in the appliance form dropdowns; fixed the Reports print feature where the table was being cut off due to horizontal scroll overflow; and resolved a visual bug on the Transactions page where stat card icons were fading and not returning until a page refresh.'],
              ['1.1.4', 'patch',  'Added an Archived Items section at the bottom of the Appliances page visible to Admins only. Archived appliances are displayed in a collapsible table with a Restore button that returns them to active inventory, providing a safety net against accidental deletion.'],
              ['1.1.3', 'patch',  'Added a profile dropdown menu to the topbar username button. Clicking the button now opens a styled dropdown showing the user avatar, full name, username, and role, with quick links to Change Password, Manage Users (Admin only), View Activity Logs, and Sign Out.'],
              ['1.1.2', 'patch',  'Identified and documented an XAMPP improper shutdown issue that caused InnoDB table corruption. Established the correct shutdown procedure — stopping MySQL and Apache in order before closing XAMPP — and performed full database backup and recovery by reimporting the SQL file.'],
              ['1.1.1', 'patch',  'Completed development of all system pages — Dashboard, Appliances, Zones and Shelves, Transactions, Activity Logs, Alerts, Reports, and Settings — each connected to the live MySQL database and sharing the centralized sidebar, topbar, and styles includes.'],
              ['1.1.0', 'minor',  'Started PHP backend development on XAMPP. Built db.php as the centralized database connection file using MySQLi with a try-catch block. Created login.php with session handling, logout.php with session destruction and activity logging, and dashboard.php as the first fully data-driven page pulling live stats from the database.'],
              ['1.0.2', 'patch',  'Designed and finalized the Entity Relationship Diagram covering all eight database tables — users, categories, zones, shelves, appliances, transactions, activity_logs, and alerts — with all primary keys, foreign keys, and relationships mapped using crow\'s foot notation.'],
              ['1.0.1', 'patch',  'Created the core SQL database file defining all eight tables with proper data types, constraints (NOT NULL, UNIQUE, DEFAULT), and foreign key relationships. Included sample data for all tables to support initial testing of the system.'],
              ['1.0.0', 'major',  'Initial prototype of the InvenTech system built as a static HTML mockup. Established the full UI design including the blue and white professional color theme, sidebar navigation, topbar, dashboard layout, and all page templates before any backend or database integration.'],
            ];
            $pill_map = [
              'major' => ['pill-red',    'Major'],
              'minor' => ['pill-blue',   'Minor'],
              'patch' => ['pill-gray',   'Patch'],
            ];
            foreach ($versions as $v):
              $pill_info  = $pill_map[$v[1]] ?? ['pill-gray', ucfirst($v[1])];
              [$pill_class, $pill_label] = $pill_info;
            ?>
            <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid var(--gray-100)">
              <span style="font-size:13px;font-weight:800;color:var(--gray-800);min-width:44px">v<?= $v[0] ?></span>
              <span class="pill <?= $pill_class ?>" style="flex-shrink:0;margin-top:1px"><?= $pill_label ?></span>
              <span style="font-size:13px;color:var(--gray-600);line-height:1.5"><?= $v[2] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<style>
  .file-upload-wrap { display: flex; flex-direction: column; gap: 6px; }
  .file-upload-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: var(--radius-sm);
    border: 1.5px solid var(--blue-300); background: var(--blue-50);
    color: var(--blue-600); font-size: 13px; font-weight: 700;
    cursor: pointer; transition: all .15s; width: fit-content;
    font-family: 'Plus Jakarta Sans', sans-serif;
  }
  .file-upload-btn:hover { background: var(--blue-100); border-color: var(--blue-500); }
  .file-name-display {
    font-size: 12px; color: var(--gray-500);
    max-width: 260px; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis;
  }
</style>

<!-- ══ ADD USER MODAL ══ -->
<div class="modal-overlay" id="add-user-modal">
  <div class="modal" style="width:460px">
    <div class="modal-header">
      <div class="modal-title">➕ Add New User</div>
      <button class="modal-close" onclick="closeModal('add-user-modal')">✕</button>
    </div>
    <form method="POST" action="settings.php?tab=users">
      <input type="hidden" name="action" value="add_user">
      <div class="form-group">
        <label class="form-label">Full Name * <span style="color:var(--gray-400);font-size:10px;text-transform:none">(format: Surname, Firstname)</span></label>
        <input class="form-ctrl" type="text" name="full_name" placeholder="e.g. Santos, Maria" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group">
          <label class="form-label">Username *</label>
          <input class="form-ctrl" type="text" name="username" placeholder="e.g. maria.s" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password *</label>
          <input class="form-ctrl" type="password" name="password" placeholder="Min. 6 characters" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Role *</label>
        <select class="form-ctrl" name="user_role" required>
          <option value="staff">Staff</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-user-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add User</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT USER MODAL ══ -->
<div class="modal-overlay" id="edit-user-modal">
  <div class="modal" style="width:460px">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit User</div>
      <button class="modal-close" onclick="closeModal('edit-user-modal')">✕</button>
    </div>
    <form method="POST" action="settings.php?tab=users">
      <input type="hidden" name="action"       value="edit_user">
      <input type="hidden" name="edit_user_id" id="edit-uid">
      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input class="form-ctrl" type="text" name="full_name" id="edit-fullname" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group">
          <label class="form-label">Username *</label>
          <input class="form-ctrl" type="text" name="username" id="edit-username" required>
        </div>
        <div class="form-group">
          <label class="form-label">Role *</label>
          <select class="form-ctrl" name="user_role" id="edit-role">
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Status *</label>
        <select class="form-ctrl" name="status" id="edit-status">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-user-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ RESET PASSWORD MODAL ══ -->
<div class="modal-overlay" id="reset-pw-modal">
  <div class="modal" style="width:400px">
    <div class="modal-header">
      <div class="modal-title">🔑 Reset Password</div>
      <button class="modal-close" onclick="closeModal('reset-pw-modal')">✕</button>
    </div>
    <form method="POST" action="settings.php?tab=users">
      <input type="hidden" name="action"        value="reset_password">
      <input type="hidden" name="reset_user_id" id="reset-uid">
      <div style="background:var(--yellow-bg);border:1px solid #fde68a;border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#92400e;">
        ⚠️ Resetting password for: <strong id="reset-username">—</strong>
      </div>
      <div class="form-group">
        <label class="form-label">New Password * <span style="color:var(--gray-400);font-size:10px;text-transform:none">(min. 6 characters)</span></label>
        <input class="form-ctrl" type="password" name="reset_password" placeholder="Enter new password" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('reset-pw-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Reset Password</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ DANGER ZONE CONFIRMATION MODAL ══ -->
<div class="modal-overlay" id="danger-modal">
  <div class="modal" style="width:440px;border-top:4px solid var(--red)">
    <div class="modal-header">
      <div class="modal-title" style="color:var(--red)" id="danger-modal-title">⚠️ Confirm Reset</div>
      <button class="modal-close" onclick="closeModal('danger-modal')">✕</button>
    </div>
    <div style="margin-bottom:20px">
      <div style="font-size:13px;color:var(--gray-600);line-height:1.7" id="danger-modal-desc"></div>
      <div style="margin-top:16px;background:var(--red-bg);border:1px solid #fca5a5;border-radius:10px;padding:14px 16px;font-size:13px;color:#991b1b">
        ⚠️ This action <strong>cannot be undone.</strong> Type <strong>CONFIRM</strong> below to proceed.
      </div>
    </div>
    <form method="POST" action="settings.php?tab=danger" id="danger-form">
      <input type="hidden" name="action" id="danger-action">
      <div class="form-group">
        <label class="form-label">Type CONFIRM to proceed</label>
        <input class="form-ctrl" type="text" name="confirm_text" id="danger-confirm-input"
          placeholder="Type CONFIRM here" autocomplete="off"
          style="border-color:var(--red)">
        <div id="danger-confirm-help" style="display:none;margin-top:7px;font-size:12px;font-weight:600;color:var(--red)">
          Type CONFIRM in uppercase exactly.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('danger-modal')">Cancel</button>
        <button type="submit" class="btn btn-danger" id="danger-submit-btn">I understand, proceed</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ RESTORE CSV CONFIRMATION MODAL ══ -->
<div class="modal-overlay" id="restore-csv-modal">
  <div class="modal" style="width:440px;border-top:4px solid var(--green)">
    <div class="modal-header">
      <div class="modal-title" style="color:#047857">♻️ Confirm CSV Restore</div>
      <button class="modal-close" onclick="closeModal('restore-csv-modal')">✕</button>
    </div>
    <div style="font-size:13px;color:var(--gray-600);line-height:1.7;margin-bottom:18px">
      You are about to restore <strong id="restore-modal-table">selected data</strong> using:
      <div style="margin-top:10px;background:var(--green-bg);border:1px solid #6ee7b7;border-radius:10px;padding:12px 14px;color:#065f46;font-weight:700" id="restore-modal-file">
        No file selected
      </div>
      <div style="margin-top:12px;color:var(--gray-500)">
        Existing rows in the selected table will be replaced after the CSV columns are validated.
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('restore-csv-modal')">Cancel</button>
      <button type="button" class="btn" style="background:var(--green);border-color:var(--green);color:#fff" onclick="submitRestoreCsv()">Restore Now</button>
    </div>
  </div>
</div>

<script>
  let restoreCsvConfirmed = false;

  function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
  }
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) closeModal(overlay.id);
    });
  });

  function openEditUser(u) {
    document.getElementById('edit-uid').value      = u.user_id;
    document.getElementById('edit-fullname').value = u.full_name;
    document.getElementById('edit-username').value = u.username;
    setSelect('edit-role',   u.role);
    setSelect('edit-status', u.status);
    openModal('edit-user-modal');
  }

  function openResetPassword(uid, name) {
    document.getElementById('reset-uid').value      = uid;
    document.getElementById('reset-username').textContent = name;
    openModal('reset-pw-modal');
  }

  function setSelect(id, val) {
    const sel = document.getElementById(id);
    for (let o of sel.options) o.selected = (o.value === val);
  }

  function handleBackupFileSelect(input) {
    const display = document.getElementById('restore-csv-file-name');
    display.textContent = input.files && input.files.length ? input.files[0].name : 'No file selected';
  }

  const restoreCsvForm = document.getElementById('restore-csv-form');
  if (restoreCsvForm) {
    restoreCsvForm.addEventListener('submit', e => {
      if (restoreCsvConfirmed) return;
      e.preventDefault();
      openRestoreCsvModal();
    });
  }

  function openRestoreCsvModal() {
    const fileInput = document.getElementById('restore-csv-input');
    const tableSelect = document.getElementById('restore-backup-table');

    if (!fileInput.files || !fileInput.files.length) {
      fileInput.reportValidity();
      return;
    }

    document.getElementById('restore-modal-file').textContent = fileInput.files[0].name;
    document.getElementById('restore-modal-table').textContent = tableSelect.options[tableSelect.selectedIndex].text;
    openModal('restore-csv-modal');
  }

  function submitRestoreCsv() {
    restoreCsvConfirmed = true;
    closeModal('restore-csv-modal');
    document.getElementById('restore-csv-form').requestSubmit();
  }

  function openDangerModal(type) {
    const title  = document.getElementById('danger-modal-title');
    const desc   = document.getElementById('danger-modal-desc');
    const action = document.getElementById('danger-action');
    const btn    = document.getElementById('danger-submit-btn');
    const input  = document.getElementById('danger-confirm-input');
    const help   = document.getElementById('danger-confirm-help');

    input.value = '';
    help.style.display = 'none';
    input.style.borderColor = 'var(--red)';
    btn.disabled = true;
    btn.style.opacity = '.55';
    btn.style.cursor = 'not-allowed';

    if (type === 'soft') {
      title.textContent  = '🧹 Confirm Soft Reset';
      desc.innerHTML     = 'This will permanently delete all <strong>appliances, transactions, activity logs, and alerts</strong>.<br><br>Categories, zones, shelves, and user accounts will be kept.';
      action.value       = 'soft_reset';
      btn.textContent    = 'Confirm Soft Reset';
      btn.style.background = 'var(--yellow)';
      btn.style.color      = '#fff';
    } else {
      title.textContent  = '💣 Confirm Full Reset';
      desc.innerHTML     = 'This will permanently delete <strong>ALL inventory data</strong> including appliances, transactions, logs, alerts, categories, zones, and shelves.<br><br>Only user accounts will remain.';
      action.value       = 'full_reset';
      btn.textContent    = 'Confirm Full Reset';
      btn.style.background = 'var(--red)';
      btn.style.color      = '#fff';
    }
    openModal('danger-modal');
  }

  const dangerInput = document.getElementById('danger-confirm-input');
  const dangerForm = document.getElementById('danger-form');
  if (dangerInput && dangerForm) {
    dangerInput.addEventListener('input', validateDangerConfirm);
    dangerForm.addEventListener('submit', e => {
      if (!validateDangerConfirm(true)) {
        e.preventDefault();
      }
    });
  }

  function validateDangerConfirm(showMessage = false) {
    const input = document.getElementById('danger-confirm-input');
    const help = document.getElementById('danger-confirm-help');
    const btn = document.getElementById('danger-submit-btn');
    const value = input.value;
    const isValid = value === 'CONFIRM';
    const hasText = value.length > 0;

    btn.disabled = !isValid;
    btn.style.opacity = isValid ? '1' : '.55';
    btn.style.cursor = isValid ? 'pointer' : 'not-allowed';
    input.style.borderColor = isValid ? 'var(--green)' : 'var(--red)';

    if (!hasText) {
      help.style.display = 'none';
    } else if (!isValid && (showMessage || hasText)) {
      help.textContent = value.toUpperCase() === 'CONFIRM'
        ? 'Use uppercase CONFIRM exactly. Lowercase confirm will not proceed.'
        : 'That does not match. Type CONFIRM exactly to continue.';
      help.style.display = 'block';
    } else {
      help.style.display = 'none';
    }

    return isValid;
  }

  setTimeout(() => {
    document.querySelectorAll('.alert-msg').forEach(el => {
      el.style.transition = 'opacity .5s';
      el.style.opacity    = '0';
      setTimeout(() => el.remove(), 500);
    });
  }, 4000);
</script>

</body>
</html>
