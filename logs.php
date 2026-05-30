<?php

//  InvenTech — Activity Logs
//  File: logs.php

session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role = $_SESSION['role'];

// ── FILTERS ──────────────────────────────────────────────────
$filter_action = trim($_GET['action']      ?? '');
$filter_user   = intval($_GET['user_id']   ?? 0);
$filter_date   = trim($_GET['date']        ?? '');
$search        = trim($_GET['search']      ?? '');

$where  = ['1=1'];
$params = [];
$types  = '';

if ($filter_action) { $where[] = "l.action = ?";                  $params[] = $filter_action; $types .= 's'; }
if ($filter_user)   { $where[] = "l.user_id = ?";                 $params[] = $filter_user;   $types .= 'i'; }
if ($filter_date)   { $where[] = "DATE(l.logged_at) = ?";         $params[] = $filter_date;   $types .= 's'; }
if ($search)        { $where[] = "l.description LIKE ?";
                      $params[] = "%$search%"; $types .= 's'; }

$where_sql = implode(' AND ', $where);

$sql = "
    SELECT l.*, u.full_name
    FROM activity_logs l
    JOIN users u ON l.user_id = u.user_id
    WHERE $where_sql
    ORDER BY l.logged_at DESC
";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result();
} else {
    $logs = $conn->query($sql);
}

// ── STATS ─────────────────────────────────────────────────────
$total_logs  = $conn->query("SELECT COUNT(*) AS cnt FROM activity_logs")->fetch_assoc()['cnt'];
$today_logs  = $conn->query("SELECT COUNT(*) AS cnt FROM activity_logs WHERE DATE(logged_at) = CURDATE()")->fetch_assoc()['cnt'];
$total_users = $conn->query("SELECT COUNT(DISTINCT user_id) AS cnt FROM activity_logs")->fetch_assoc()['cnt'];

// ── USERS FOR FILTER DROPDOWN ─────────────────────────────────
$users_query = $conn->query("SELECT user_id, full_name FROM users WHERE status='active' ORDER BY full_name");
$user_list   = [];
while ($r = $users_query->fetch_assoc()) $user_list[] = $r;

// ── ACTION CONFIG ─────────────────────────────────────────────
$action_config = [
    'added'   => ['pill' => 'pill-green',  'icon' => '➕', 'label' => 'Added'],
    'edited'  => ['pill' => 'pill-blue',   'icon' => '✏️', 'label' => 'Edited'],
    'deleted' => ['pill' => 'pill-red',    'icon' => '🗑️', 'label' => 'Deleted'],
    'login'   => ['pill' => 'pill-purple', 'icon' => '🔑', 'label' => 'Login'],
    'logout'  => ['pill' => 'pill-gray',   'icon' => '🚪', 'label' => 'Logout'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InvenTech — Activity Logs</title>
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
        <div class="section-title">📝 Activity Logs</div>
        <div class="section-sub">Complete history of all actions performed in the system</div>
      </div>
    </div>

    <!-- Stats -->
    <div class="stat-grid stat-grid-3" style="margin-bottom:20px">
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--blue-500)"></div>
        <div class="stat-icon-wrap" style="background:var(--blue-50)">📋</div>
        <div class="stat-label">Total Log Entries</div>
        <div class="stat-value" style="color:var(--blue-600)"><?= $total_logs ?></div>
        <div class="stat-change neutral">All time records</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--green)"></div>
        <div class="stat-icon-wrap" style="background:var(--green-bg)">☀️</div>
        <div class="stat-label">Today's Activity</div>
        <div class="stat-value" style="color:var(--green)"><?= $today_logs ?></div>
        <div class="stat-change neutral"><?= date('F j, Y') ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--purple)"></div>
        <div class="stat-icon-wrap" style="background:var(--purple-bg)">👥</div>
        <div class="stat-label">Active Users Logged</div>
        <div class="stat-value" style="color:var(--purple)"><?= $total_users ?></div>
        <div class="stat-change neutral">Users with recorded actions</div>
      </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="logs.php">
      <div class="toolbar">
        <div class="search-wrap">
          <input class="search-input" type="text" name="search"
            placeholder="Search log descriptions..."
            value="<?= htmlspecialchars($search) ?>">
        </div>
        <select class="filter-select" name="action">
          <option value="">All Actions</option>
          <?php foreach ($action_config as $key => $cfg): ?>
            <option value="<?= $key ?>" <?= $filter_action === $key ? 'selected' : '' ?>>
              <?= $cfg['icon'] ?> <?= $cfg['label'] ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select class="filter-select" name="user_id">
          <option value="">All Users</option>
          <?php foreach ($user_list as $u): ?>
            <option value="<?= $u['user_id'] ?>" <?= $filter_user == $u['user_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input class="filter-select" type="date" name="date"
          value="<?= htmlspecialchars($filter_date) ?>"
          style="cursor:pointer">
        <button type="submit" class="btn btn-secondary">🔍 Filter</button>
        <a href="logs.php" class="btn btn-secondary">✕ Clear</a>
      </div>
    </form>

    <!-- Logs Table -->
    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Action</th>
              <th>Description</th>
              <th>Performed By</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($logs->num_rows > 0):
              $counter = 1;
              while ($log = $logs->fetch_assoc()):
                $cfg = $action_config[$log['action']] ?? ['pill' => 'pill-gray', 'icon' => '•', 'label' => ucfirst($log['action'])];
            ?>
            <tr>
              <td class="text-muted"><?= $counter++ ?></td>
              <td>
                <span class="pill <?= $cfg['pill'] ?>">
                  <?= $cfg['icon'] ?> <?= $cfg['label'] ?>
                </span>
              </td>
              <td class="td-main" style="max-width:380px;white-space:normal;line-height:1.5">
                <?= htmlspecialchars($log['description']) ?>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--blue-500),var(--blue-300));display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#fff;flex-shrink:0">
                    <?php
                      $parts = explode(',', $log['full_name']);
                      $sn = trim($parts[0] ?? '');
                      $fn = trim($parts[1] ?? '');
                      echo strtoupper(substr($fn,0,1) . substr($sn,0,1));
                    ?>
                  </div>
                  <span style="font-size:13px;font-weight:600"><?= htmlspecialchars($fn . ' ' . $sn) ?></span>
                </div>
              </td>
              <td class="text-muted"><?= date('M j, Y g:ia', strtotime($log['logged_at'])) ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
              <td colspan="5">
                <div class="empty-state">
                  <div class="icon">📝</div>
                  <div class="title">No logs found</div>
                  <div class="desc">Try adjusting your filters or start using the system to generate logs.</div>
                </div>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

</body>
</html>
