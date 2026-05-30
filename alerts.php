<?php

//  InvenTech — Alerts
//  File: alerts.php

session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];

// ── HANDLE POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Activity log helper ──
    $log_action = function(string $type, string $desc) use ($conn, $uid) {
        $s = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
        $s->bind_param('iss', $uid, $type, $desc);
        $s->execute();
        $s->close();
    };

    // ── MARK SINGLE AS READ ──
    if ($action === 'dismiss') {
        $alert_id = intval($_POST['alert_id']);
        $stmt = $conn->prepare("UPDATE alerts SET is_read = 1 WHERE alert_id = ?");
        $stmt->bind_param('i', $alert_id);
        $stmt->execute();
        $stmt->close();
        $log_action('edited', "Marked alert #$alert_id as read.");
        $success = "Alert marked as read.";
    }

    // ── MARK ALL AS READ ──
    if ($action === 'dismiss_all') {
        $conn->query("UPDATE alerts SET is_read = 1 WHERE is_read = 0");
        $log_action('edited', "Marked all unread alerts as read.");
        $success = "All alerts have been marked as read.";
    }

    // ── SOFT DELETE / ARCHIVE (admin only) ──
    if ($action === 'soft_delete' && $role === 'admin') {
        $alert_id = intval($_POST['alert_id']);
        $stmt = $conn->prepare("UPDATE alerts SET is_read = 2 WHERE alert_id = ?");
        $stmt->bind_param('i', $alert_id);
        $stmt->execute();
        $stmt->close();
        $log_action('deleted', "Archived alert #$alert_id.");
        $success = "Alert archived.";
    }

    // ── UNARCHIVE / RESTORE (admin only) ──
    if ($action === 'unarchive' && $role === 'admin') {
        $alert_id = intval($_POST['alert_id']);
        $stmt = $conn->prepare("UPDATE alerts SET is_read = 1 WHERE alert_id = ?");
        $stmt->bind_param('i', $alert_id);
        $stmt->execute();
        $stmt->close();
        $log_action('edited', "Restored archived alert #$alert_id back to read alerts.");
        $success = "Alert restored to read alerts.";
    }

    // ── HARD DELETE SINGLE (admin only) ──
    if ($action === 'delete' && $role === 'admin') {
        $alert_id  = intval($_POST['alert_id']);
        $title_row = $conn->query("SELECT title FROM alerts WHERE alert_id = $alert_id")->fetch_assoc();
        $alert_title = $title_row['title'] ?? "Alert #$alert_id";
        $stmt = $conn->prepare("DELETE FROM alerts WHERE alert_id = ?");
        $stmt->bind_param('i', $alert_id);
        $stmt->execute();
        $stmt->close();
        $log_action('deleted', "Permanently deleted alert: \"$alert_title\".");
        $success = "Alert permanently deleted.";
    }

    // ── DELETE ALL ARCHIVED (admin only) ──
    if ($action === 'delete_all_archived' && $role === 'admin') {
        $count = $conn->query("SELECT COUNT(*) AS cnt FROM alerts WHERE is_read = 2")->fetch_assoc()['cnt'];
        $conn->query("DELETE FROM alerts WHERE is_read = 2");
        $log_action('deleted', "Permanently deleted all $count archived alert(s).");
        $success = "All archived alerts permanently deleted.";
    }
}

// ── FILTERS ──────────────────────────────────────────────────
$filter_type = trim($_GET['alert_type'] ?? '');
$filter_read = $_GET['is_read'] ?? '';
$search      = trim($_GET['search'] ?? '');

$where  = ['is_read != 2']; // never show archived in main list
$params = [];
$types  = '';

if ($filter_type !== '')  { $where[] = "alert_type = ?"; $params[] = $filter_type; $types .= 's'; }
if ($filter_read !== '')  { $where[] = "is_read = ?";    $params[] = intval($filter_read); $types .= 'i'; }
if ($search)              { $where[] = "(title LIKE ? OR description LIKE ?)";
                            $s = "%$search%"; $params[] = $s; $params[] = $s; $types .= 'ss'; }

$where_sql = implode(' AND ', $where);
$sql = "SELECT * FROM alerts WHERE $where_sql ORDER BY created_at DESC";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $alerts = $stmt->get_result();
} else {
    $alerts = $conn->query($sql);
}

// ── STATS ─────────────────────────────────────────────────────
$total_alerts    = $conn->query("SELECT COUNT(*) AS cnt FROM alerts WHERE is_read != 2")->fetch_assoc()['cnt'];
$unread_critical = $conn->query("SELECT COUNT(*) AS cnt FROM alerts WHERE alert_type='critical' AND is_read=0")->fetch_assoc()['cnt'];
$unread_warning  = $conn->query("SELECT COUNT(*) AS cnt FROM alerts WHERE alert_type='warning'  AND is_read=0")->fetch_assoc()['cnt'];
$unread_info     = $conn->query("SELECT COUNT(*) AS cnt FROM alerts WHERE alert_type='info'     AND is_read=0")->fetch_assoc()['cnt'];
$archived_count  = $conn->query("SELECT COUNT(*) AS cnt FROM alerts WHERE is_read=2")->fetch_assoc()['cnt'];

// ── ARCHIVED ALERTS ───────────────────────────────────────────
$archived_alerts = $conn->query("SELECT * FROM alerts WHERE is_read = 2 ORDER BY created_at DESC");

// ── ALERT CONFIG ──────────────────────────────────────────────
$alert_config = [
    'critical' => ['icon' => '🚨', 'pill' => 'pill-red',    'label' => 'Critical', 'border' => 'var(--red)',      'bg' => '#fff5f5'],
    'warning'  => ['icon' => '⚠️', 'pill' => 'pill-yellow', 'label' => 'Warning',  'border' => 'var(--yellow)',   'bg' => '#fffbeb'],
    'info'     => ['icon' => 'ℹ️', 'pill' => 'pill-blue',   'label' => 'Info',     'border' => 'var(--blue-400)', 'bg' => 'var(--blue-50)'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InvenTech — Alerts</title>
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
        <div class="section-title">🔔 Alerts</div>
        <div class="section-sub">System notifications that need your attention</div>
      </div>
      <?php $has_unread = ($unread_critical + $unread_warning + $unread_info) > 0; ?>
      <?php if ($has_unread): ?>
      <form method="POST" action="alerts.php" id="form-dismiss-all">
        <input type="hidden" name="action" value="dismiss_all">
        <button type="button" class="btn btn-secondary"
          onclick="confirmAction('Mark All as Read','All unread alerts will be marked as read.','✓','form-dismiss-all','Mark All Read','btn-primary')">
          ✓ Mark All as Read
        </button>
      </form>
      <?php endif; ?>
    </div>

    <!-- Success -->
    <?php if (!empty($success)): ?>
      <div style="background:var(--green-bg);color:#065f46;border:1px solid #6ee7b7;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✅ <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stat-grid stat-grid-4" style="margin-bottom:20px">
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--blue-500)"></div>
        <div class="stat-icon-wrap" style="background:var(--blue-50)">🔔</div>
        <div class="stat-label">Total Alerts</div>
        <div class="stat-value" style="color:var(--blue-600)"><?= $total_alerts ?></div>
        <div class="stat-change neutral">All time</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--red)"></div>
        <div class="stat-icon-wrap" style="background:var(--red-bg)">🚨</div>
        <div class="stat-label">Unread Critical</div>
        <div class="stat-value" style="color:var(--red)"><?= $unread_critical ?></div>
        <div class="stat-change <?= $unread_critical > 0 ? 'down' : 'up' ?>"><?= $unread_critical > 0 ? 'Needs immediate action' : 'All clear' ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--yellow)"></div>
        <div class="stat-icon-wrap" style="background:var(--yellow-bg)">⚠️</div>
        <div class="stat-label">Unread Warnings</div>
        <div class="stat-value" style="color:var(--yellow)"><?= $unread_warning ?></div>
        <div class="stat-change <?= $unread_warning > 0 ? 'down' : 'up' ?>"><?= $unread_warning > 0 ? 'Monitor closely' : 'All clear' ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--blue-400)"></div>
        <div class="stat-icon-wrap" style="background:var(--blue-50)">ℹ️</div>
        <div class="stat-label">Unread Info</div>
        <div class="stat-value" style="color:var(--blue-500)"><?= $unread_info ?></div>
        <div class="stat-change neutral">For your awareness</div>
      </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="alerts.php">
      <div class="toolbar">
        <div class="search-wrap">
          <input class="search-input" type="text" name="search"
            placeholder="Search alerts..."
            value="<?= htmlspecialchars($search) ?>">
        </div>
        <select class="filter-select" name="alert_type">
          <option value="">All Types</option>
          <option value="critical" <?= $filter_type === 'critical' ? 'selected' : '' ?>>🚨 Critical</option>
          <option value="warning"  <?= $filter_type === 'warning'  ? 'selected' : '' ?>>⚠️ Warning</option>
          <option value="info"     <?= $filter_type === 'info'     ? 'selected' : '' ?>>ℹ️ Info</option>
        </select>
        <select class="filter-select" name="is_read">
          <option value="">All Status</option>
          <option value="0" <?= $filter_read === '0' ? 'selected' : '' ?>>🔴 Unread</option>
          <option value="1" <?= $filter_read === '1' ? 'selected' : '' ?>>✅ Read</option>
        </select>
        <button type="submit" class="btn btn-secondary">🔍 Filter</button>
        <a href="alerts.php" class="btn btn-secondary">✕ Clear</a>
      </div>
    </form>

    <!-- Alerts List -->
    <?php if ($alerts->num_rows > 0): ?>
    <div class="alert-feed">
      <?php while ($al = $alerts->fetch_assoc()):
        $cfg     = $alert_config[$al['alert_type']] ?? $alert_config['info'];
        $is_read = $al['is_read'] == 1;
      ?>
      <div class="alert-card <?= $al['alert_type'] ?>"
        style="opacity:<?= $is_read ? '.6' : '1' ?>;background:<?= $is_read ? 'var(--gray-50)' : $cfg['bg'] ?>">
        <div class="alert-card-icon"><?= $cfg['icon'] ?></div>
        <div class="alert-card-content" style="flex:1">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
            <div class="alert-card-title"><?= htmlspecialchars($al['title']) ?></div>
            <span class="pill <?= $cfg['pill'] ?>"><?= $cfg['label'] ?></span>
            <?php if ($is_read): ?>
              <span class="pill pill-gray">✓ Read</span>
            <?php else: ?>
              <span class="pill pill-green" style="font-size:10px">● Unread</span>
            <?php endif; ?>
          </div>
          <div class="alert-card-desc"><?= htmlspecialchars($al['description']) ?></div>
          <div class="alert-card-time">
            🕐 <?= date('F j, Y \a\t g:ia', strtotime($al['created_at'])) ?>
          </div>
          <div class="alert-card-actions">
            <?php if (!$is_read): ?>
            <form method="POST" action="alerts.php" style="display:inline">
              <input type="hidden" name="action"   value="dismiss">
              <input type="hidden" name="alert_id" value="<?= $al['alert_id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm">✓ Mark as Read</button>
            </form>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
            <form method="POST" action="alerts.php" style="display:inline"
              id="form-softdel-<?= $al['alert_id'] ?>" onsubmit="return false">
              <input type="hidden" name="action"   value="soft_delete">
              <input type="hidden" name="alert_id" value="<?= $al['alert_id'] ?>">
              <button type="button" class="btn btn-secondary btn-sm"
                onclick="confirmAction('Archive Alert','This alert will be moved to the archived section.','🗂️','form-softdel-<?= $al['alert_id'] ?>','Archive','btn-secondary')">
                🗂️ Archive
              </button>
            </form>
            <form method="POST" action="alerts.php" style="display:inline"
              id="form-delalert-<?= $al['alert_id'] ?>" onsubmit="return false">
              <input type="hidden" name="action"   value="delete">
              <input type="hidden" name="alert_id" value="<?= $al['alert_id'] ?>">
              <button type="button" class="btn btn-danger btn-sm"
                onclick="confirmAction('Delete Alert','This alert will be permanently removed and cannot be recovered.','🗑️','form-delalert-<?= $al['alert_id'] ?>','Delete','btn-danger')">
                🗑️ Delete
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>

    <?php else: ?>
    <div class="card">
      <div class="empty-state">
        <div class="icon">✅</div>
        <div class="title">No alerts found</div>
        <div class="desc">
          <?= $filter_type || $filter_read !== '' || $search
            ? 'Try adjusting your filters.'
            : 'The system is all clear — no alerts to show.' ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- ══ ARCHIVED ALERTS ══ -->
  <?php if ($role === 'admin' && $archived_count > 0): ?>
  <div style="margin-top:28px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;cursor:pointer"
      onclick="toggleArchived()">
      <div style="font-size:16px;font-weight:800;color:var(--gray-600)">🗄️ Archived Alerts</div>
      <span class="pill pill-gray"><?= $archived_count ?> items</span>
      <span id="archive-arrow" style="color:var(--gray-400);font-size:12px">▼ Show</span>
    </div>
    <div id="archived-section" style="display:none">
      <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
          <div style="font-size:12px;color:var(--gray-400)">
            These alerts have been archived. Only admins can see and permanently delete them.
          </div>
          <form method="POST" action="alerts.php" id="form-del-all-archived" onsubmit="return false">
            <input type="hidden" name="action" value="delete_all_archived">
            <button type="button" class="btn btn-danger btn-sm"
              onclick="confirmAction('Delete All Archived','All <?= $archived_count ?> archived alerts will be permanently deleted and cannot be recovered.','🗑️','form-del-all-archived','Delete All','btn-danger')">
              🗑️ Delete All Archived
            </button>
          </form>
        </div>
        <div class="alert-feed">
          <?php while ($ar = $archived_alerts->fetch_assoc()):
            $cfg = $alert_config[$ar['alert_type']] ?? $alert_config['info'];
          ?>
          <div class="alert-card" style="opacity:.55;background:var(--gray-50);border-color:var(--gray-200)">
            <div class="alert-card-icon"><?= $cfg['icon'] ?></div>
            <div class="alert-card-content" style="flex:1">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                <div class="alert-card-title"><?= htmlspecialchars($ar['title']) ?></div>
                <span class="pill <?= $cfg['pill'] ?>"><?= $cfg['label'] ?></span>
                <span class="pill pill-gray">🗄️ Archived</span>
              </div>
              <div class="alert-card-desc"><?= htmlspecialchars($ar['description']) ?></div>
              <div class="alert-card-time">
                🕐 <?= date('F j, Y \a\t g:ia', strtotime($ar['created_at'])) ?>
              </div>
              <div class="alert-card-actions">
                <form method="POST" action="alerts.php" style="display:inline"
                  id="form-permdel-<?= $ar['alert_id'] ?>" onsubmit="return false">
                  <input type="hidden" name="action"   value="delete">
                  <input type="hidden" name="alert_id" value="<?= $ar['alert_id'] ?>">
                  <button type="button" class="btn btn-danger btn-sm"
                    onclick="confirmAction('Delete Alert','This archived alert will be permanently removed.','🗑️','form-permdel-<?= $ar['alert_id'] ?>','Delete','btn-danger')">
                    🗑️ Delete Permanently
                  </button>
                </form>
              </div>
            </div>
          </div>
          <?php endwhile; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main -->

<!-- ══ CONFIRM DIALOG ══ -->
<div class="confirm-overlay" id="confirm-overlay">
  <div class="confirm-box">
    <div class="confirm-icon" id="confirm-icon">⚠️</div>
    <div class="confirm-title" id="confirm-title">Confirm Action</div>
    <div class="confirm-desc"  id="confirm-desc">Are you sure?</div>
    <div class="confirm-btns">
      <button class="btn btn-secondary" onclick="closeConfirm()">Cancel</button>
      <button class="btn btn-danger"    id="confirm-ok-btn">Confirm</button>
    </div>
  </div>
</div>

<script>
  function confirmAction(title, message, icon, formId, confirmLabel, confirmClass) {
    document.getElementById('confirm-icon').textContent  = icon  || '⚠️';
    document.getElementById('confirm-title').textContent = title || 'Confirm';
    document.getElementById('confirm-desc').textContent  = message || 'Are you sure?';
    const btn = document.getElementById('confirm-ok-btn');
    btn.textContent = confirmLabel || 'Confirm';
    btn.className   = 'btn ' + (confirmClass || 'btn-danger');
    btn.onclick = function () {
      closeConfirm();
      document.getElementById(formId).submit();
    };
    document.getElementById('confirm-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeConfirm() {
    document.getElementById('confirm-overlay').classList.remove('open');
    document.body.style.overflow = '';
  }
  document.getElementById('confirm-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeConfirm(); });

  function toggleArchived() {
    const sec   = document.getElementById('archived-section');
    const arrow = document.getElementById('archive-arrow');
    if (sec.style.display === 'none') {
      sec.style.display = 'block';
      arrow.textContent = '▲ Hide';
    } else {
      sec.style.display = 'none';
      arrow.textContent = '▼ Show';
    }
  }

  setTimeout(() => {
    document.querySelectorAll('[style*="green-bg"]').forEach(el => {
      el.style.transition = 'opacity .5s';
      el.style.opacity    = '0';
      setTimeout(() => el.remove(), 500);
    });
  }, 4000);
</script>

</body>
</html>

