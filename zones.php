<?php

//  InvenTech — Zones & Shelves
//  File: zones.php

session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Initialize message variables to prevent undefined tracking warnings
$success = '';
$error = '';

// ── HANDLE POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD ZONE ──
    if ($action === 'add_zone' && $role === 'admin') {
        $zone_name = trim($_POST['zone_name'] ?? '');
        $zone_desc = trim($_POST['zone_description'] ?? '');

        if ($zone_name === '') {
            $error = "Zone name cannot be empty.";
        } else {
            $stmt = $conn->prepare("INSERT INTO zones (zone_name, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $zone_name, $zone_desc);
            if ($stmt->execute()) {
                $log_desc = "Added new zone: $zone_name";
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'added', ?)");
                $log->bind_param('is', $uid, $log_desc);
                $log->execute();
                $log->close();
                $success = "Zone '$zone_name' added successfully.";
            } else {
                $error = "Failed to add zone. Name may already exist.";
            }
            $stmt->close();
        }
    }

    // ── ADD SHELF ──
    if ($action === 'add_shelf' && $role === 'admin') {
        $zone_id   = intval($_POST['zone_id'] ?? 0);
        $shelf_name = trim($_POST['shelf_name'] ?? '');
        $shelf_desc = trim($_POST['shelf_description'] ?? '');

        if ($zone_id <= 0 || $shelf_name === '') {
            $error = "Invalid zone selection or shelf name.";
        } else {
            $stmt = $conn->prepare("INSERT INTO shelves (zone_id, shelf_name, description) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $zone_id, $shelf_name, $shelf_desc);
            if ($stmt->execute()) {
                $zn_stmt = $conn->prepare("SELECT zone_name FROM zones WHERE zone_id = ?");
                $zn_stmt->bind_param('i', $zone_id);
                $zn_stmt->execute();
                $result = $zn_stmt->get_result()->fetch_assoc();
                $zone_name = $result ? $result['zone_name'] : 'Unknown Zone';
                $zn_stmt->close();

                $log_desc  = "Added new shelf: $shelf_name in $zone_name";
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'added', ?)");
                $log->bind_param('is', $uid, $log_desc);
                $log->execute();
                $log->close();
                $success = "Shelf '$shelf_name' added successfully.";
            } else {
                $error = "Failed to add shelf.";
            }
            $stmt->close();
        }
    }

    // ── DELETE ZONE ──
    if ($action === 'delete_zone' && $role === 'admin') {
        $zone_id = intval($_POST['zone_id'] ?? 0);
        
        $zd_stmt = $conn->prepare("SELECT zone_name FROM zones WHERE zone_id = ?");
        $zd_stmt->bind_param('i', $zone_id);
        $zd_stmt->execute();
        $zone = $zd_stmt->get_result()->fetch_assoc();
        $zd_stmt->close();

        if ($zone) {
            // Check for assigned active appliances to prevent orphaned structural bugs
            $check_appliances = $conn->prepare("SELECT COUNT(*) as count FROM appliances WHERE zone_id = ? AND status = 'active'");
            $check_appliances->bind_param('i', $zone_id);
            $check_appliances->execute();
            $has_appliances = $check_appliances->get_result()->fetch_assoc()['count'] > 0;
            $check_appliances->close();

            if ($has_appliances) {
                $error = "Failed to delete zone. Make sure no appliances are assigned to it first.";
            } else {
                // Execute using explicit database ACID transactions for stability
                $conn->begin_transaction();
                try {
                    // Wipe associated sub-shelves safely
                    $del_shelves = $conn->prepare("DELETE FROM shelves WHERE zone_id = ?");
                    $del_shelves->bind_param('i', $zone_id);
                    $del_shelves->execute();
                    $del_shelves->close();

                    $stmt = $conn->prepare("DELETE FROM zones WHERE zone_id = ?");
                    $stmt->bind_param('i', $zone_id);
                    $stmt->execute();
                    $stmt->close();

                    $log_desc = "Deleted zone: {$zone['zone_name']}";
                    $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'deleted', ?)");
                    $log->bind_param('is', $uid, $log_desc);
                    $log->execute();
                    $log->close();

                    $conn->commit();
                    $success = "Zone and its empty shelves deleted successfully.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Failed to delete zone due to an internal execution error.";
                }
            }
        } else {
            $error = "Zone not found.";
        }
    }

    // ── DELETE SHELF ──
    if ($action === 'delete_shelf' && $role === 'admin') {
        $shelf_id = intval($_POST['shelf_id'] ?? 0);
        
        $sd_stmt = $conn->prepare("SELECT shelf_name FROM shelves WHERE shelf_id = ?");
        $sd_stmt->bind_param('i', $shelf_id);
        $sd_stmt->execute();
        $shelf = $sd_stmt->get_result()->fetch_assoc();
        $sd_stmt->close();

        if ($shelf) {
            // Safeguard constraint validation matching systemic dependencies
            $check_shelf_appliances = $conn->prepare("SELECT COUNT(*) as count FROM appliances WHERE shelf_id = ? AND status = 'active'");
            $check_shelf_appliances->bind_param('i', $shelf_id);
            $check_shelf_appliances->execute();
            $has_shelf_appliances = $check_shelf_appliances->get_result()->fetch_assoc()['count'] > 0;
            $check_shelf_appliances->close();

            if ($has_shelf_appliances) {
                $error = "Cannot delete shelf while active appliances are physically assigned to it.";
            } else {
                $stmt = $conn->prepare("DELETE FROM shelves WHERE shelf_id = ?");
                $stmt->bind_param('i', $shelf_id);
                if ($stmt->execute()) {
                    $log_desc = "Deleted shelf: {$shelf['shelf_name']}";
                    $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'deleted', ?)");
                    $log->bind_param('is', $uid, $log_desc);
                    $log->execute();
                    $log->close();
                    $success = "Shelf deleted successfully.";
                } else {
                    $error = "Failed to delete shelf.";
                }
                $stmt->close();
            }
        } else {
            $error = "Shelf tracking target not found.";
        }
    }

    // ── EDIT ZONE ──
    if ($action === 'edit_zone' && $role === 'admin') {
        $zone_id   = intval($_POST['zone_id'] ?? 0);
        $zone_name = trim($_POST['zone_name'] ?? '');
        $zone_desc = trim($_POST['zone_description'] ?? '');
        if ($zone_id <= 0 || $zone_name === '') {
            $error = "Zone name cannot be empty.";
        } else {
            $stmt = $conn->prepare("UPDATE zones SET zone_name = ?, description = ? WHERE zone_id = ?");
            $stmt->bind_param('ssi', $zone_name, $zone_desc, $zone_id);
            if ($stmt->execute()) {
                $log_desc = "Edited zone: $zone_name";
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'edited', ?)");
                $log->bind_param('is', $uid, $log_desc);
                $log->execute();
                $log->close();
                $success = "Zone '$zone_name' updated successfully.";
            } else {
                $error = "Failed to update zone.";
            }
            $stmt->close();
        }
    }

    // ── EDIT SHELF ──
    if ($action === 'edit_shelf' && $role === 'admin') {
        $shelf_id   = intval($_POST['shelf_id'] ?? 0);
        $shelf_name = trim($_POST['shelf_name'] ?? '');
        $shelf_desc = trim($_POST['shelf_description'] ?? '');
        if ($shelf_id <= 0 || $shelf_name === '') {
            $error = "Shelf name cannot be empty.";
        } else {
            $stmt = $conn->prepare("UPDATE shelves SET shelf_name = ?, description = ? WHERE shelf_id = ?");
            $stmt->bind_param('ssi', $shelf_name, $shelf_desc, $shelf_id);
            if ($stmt->execute()) {
                $log_desc = "Edited shelf: $shelf_name";
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'edited', ?)");
                $log->bind_param('is', $uid, $log_desc);
                $log->execute();
                $log->close();
                $success = "Shelf '$shelf_name' updated successfully.";
            } else {
                $error = "Failed to update shelf.";
            }
            $stmt->close();
        }
    }
}

// ── FETCH ZONES WITH SHELF + APPLIANCE COUNTS ────────────────
$zones_query = $conn->query("
    SELECT z.*,
        COUNT(DISTINCT s.shelf_id) AS shelf_count,
        COUNT(DISTINCT a.appliance_id) AS appliance_count,
        COALESCE((
            SELECT SUM(stock) FROM appliances
            WHERE zone_id = z.zone_id AND status = 'active'
        ), 0) AS total_stock
    FROM zones z
    LEFT JOIN shelves    s ON z.zone_id = s.zone_id
    LEFT JOIN appliances a ON z.zone_id = a.zone_id AND a.status = 'active'
    GROUP BY z.zone_id
    ORDER BY z.zone_name
");

$zones_list = [];
if ($zones_query) {
    while ($z = $zones_query->fetch_assoc()) $zones_list[] = $z;
}

// ── FETCH SHELVES PER ZONE ───────────────────────────────────
$shelves_query = $conn->query("
    SELECT s.*,
        z.zone_name,
        COUNT(a.appliance_id) AS item_count,
        COALESCE((
            SELECT SUM(stock) FROM appliances
            WHERE shelf_id = s.shelf_id AND status = 'active'
        ), 0) AS total_stock
    FROM shelves s
    LEFT JOIN zones      z ON s.zone_id = z.zone_id
    LEFT JOIN appliances a ON s.shelf_id = a.shelf_id AND a.status = 'active'
    GROUP BY s.shelf_id
    ORDER BY z.zone_name, s.shelf_name
");

$shelves_by_zone = [];
if ($shelves_query) {
    while ($s = $shelves_query->fetch_assoc()) {
        $shelves_by_zone[$s['zone_id']][] = $s;
    }
}

// ── CONDITION OVERVIEW PER ZONE ──────────────────────────────
$cond_overview = $conn->query("
    SELECT z.zone_id, z.zone_name,
        SUM(CASE WHEN a.appliance_condition IN ('brand_new','good') THEN 1 ELSE 0 END) AS good_count,
        SUM(CASE WHEN a.appliance_condition IN ('fair','for_repair') THEN 1 ELSE 0 END) AS warn_count,
        SUM(CASE WHEN a.appliance_condition = 'defective' THEN 1 ELSE 0 END) AS defect_count,
        COUNT(a.appliance_id) AS total
    FROM zones z
    LEFT JOIN appliances a ON z.zone_id = a.zone_id AND a.status = 'active'
    GROUP BY z.zone_id
    ORDER BY z.zone_name
");

$cond_by_zone = [];
if ($cond_overview) {
    while ($c = $cond_overview->fetch_assoc()) $cond_by_zone[$c['zone_id']] = $c;
}

// ── APPLIANCES PER SHELF (for zone detail modal) ─────────────
$apl_query = $conn->query("
    SELECT a.appliance_id, a.sku, a.name, a.brand, a.appliance_condition,
           a.stock, a.min_stock, a.shelf_id, a.zone_id,
           c.category_name
    FROM appliances a
    LEFT JOIN categories c ON a.category_id = c.category_id
    WHERE a.status = 'active'
    ORDER BY a.shelf_id, a.name
");
$appliances_by_shelf = [];
if ($apl_query) {
    while ($ap = $apl_query->fetch_assoc()) {
        $sid = $ap['shelf_id'] ?? 'none';
        $appliances_by_shelf[$sid][] = $ap;
    }
}

// Condition labels
$cond_labels = [
    'brand_new'  => ['label' => 'Brand New',  'pill' => 'pill-green'],
    'good'       => ['label' => 'Good',        'pill' => 'pill-green'],
    'fair'       => ['label' => 'Fair',        'pill' => 'pill-yellow'],
    'for_repair' => ['label' => 'For Repair',  'pill' => 'pill-orange'],
    'defective'  => ['label' => 'Defective',   'pill' => 'pill-red'],
];

// ── ZONE LIST FOR DROPDOWNS ──────────────────────────────────
$zone_dropdown = $conn->query("SELECT zone_id, zone_name FROM zones ORDER BY zone_name");
$zone_dd = [];
if ($zone_dropdown) {
    while ($r = $zone_dropdown->fetch_assoc()) $zone_dd[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InvenTech — Zones & Shelves</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
<?php include __DIR__ . '/includes/styles.php'; ?>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
  <?php include __DIR__ . '/includes/topbar.php'; ?>

  <div class="content">

    <div class="section-header">
      <div>
        <div class="section-title">🗂️ Zones & Shelves</div>
        <div class="section-sub">Manage physical storage locations and track where each appliance is placed</div>
      </div>
      <?php if ($role === 'admin'): ?>
      <div class="flex gap-8">
        <button class="btn btn-secondary" onclick="openModal('add-shelf-modal')">＋ Add Shelf</button>
        <button class="btn btn-primary"   onclick="openModal('add-zone-modal')">＋ Add Zone</button>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($success !== ''): ?>
      <div style="background:var(--green-bg);color:#065f46;border:1px solid #6ee7b7;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✅ <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div style="background:var(--red-bg);color:#991b1b;border:1px solid #fca5a5;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠️ <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if (empty($zones_list)): ?>
      <div class="card">
        <div class="empty-state">
          <div class="icon">🗂️</div>
          <div class="title">No zones yet</div>
          <div class="desc">Add your first zone using the button above.</div>
        </div>
      </div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(<?= min(count($zones_list), 3) ?>,1fr);gap:16px;margin-bottom:24px">
      <?php foreach ($zones_list as $z): ?>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--blue-500)"></div>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
          <div class="stat-icon-wrap" style="background:var(--blue-50);margin-bottom:0">🗂️</div>
          <?php if ($role === 'admin'): ?>
          <div style="display:flex;gap:5px;align-items:center">
            <button type="button" class="btn btn-secondary btn-sm" style="padding:8px 8px"
              onclick="openEditZone(<?= $z['zone_id'] ?>, <?= htmlspecialchars(json_encode($z['zone_name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($z['description'] ?? ''), ENT_QUOTES) ?>)">✏️</button>
            <form method="POST" action="zones.php" id="form-delzone-<?= $z['zone_id'] ?>" style="margin:0">
              <input type="hidden" name="action"  value="delete_zone">
              <input type="hidden" name="zone_id" value="<?= $z['zone_id'] ?>">
              <button type="button" class="btn btn-danger btn-sm" style="padding:8px 8px"
                onclick="confirmAction('Delete Zone','Deleting this zone will also remove all its shelves. Active appliances assigned here cannot be processed without safe unlinking.','🗑️','form-delzone-<?= $z['zone_id'] ?>','Delete Zone','btn-danger')">🗑️</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
        <div class="stat-label"><?= htmlspecialchars($z['zone_name']) ?></div>
        <div class="stat-value" style="color:var(--blue-600)"><?= $z['total_stock'] ?></div>
        <div class="stat-change neutral">
          <?= $z['appliance_count'] ?> appliance types · <?= $z['shelf_count'] ?> shelves
        </div>
        <?php if (!empty($z['description'])): ?>
          <div style="font-size:11px;color:var(--gray-400);margin-top:6px"><?= htmlspecialchars($z['description']) ?></div>
        <?php endif; ?>
        <button class="btn btn-secondary btn-sm" style="margin-top:12px;width:100%;justify-content:center"
          onclick="openZoneModal(<?= $z['zone_id'] ?>)">
          📂 View Shelves
        </button>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card" style="margin-top:8px">
      <div class="card-header"><div class="card-title">📊 Condition Overview by Zone</div></div>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Zone</th>
              <th>Good / Brand New</th>
              <th>Fair / For Repair</th>
              <th>Defective</th>
              <th>Total Units</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cond_by_zone as $c): ?>
            <tr>
              <td class="td-main"><?= htmlspecialchars($c['zone_name']) ?></td>
              <td><span class="pill pill-green"><?= $c['good_count'] ?></span></td>
              <td><span class="pill pill-yellow"><?= $c['warn_count'] ?></span></td>
              <td><span class="pill pill-red"><?= $c['defect_count'] ?></span></td>
              <td><strong><?= $c['total'] ?></strong></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($cond_by_zone)): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="icon">📊</div><div class="title">No data yet</div></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div></div><?php foreach ($shelves_by_zone as $zone_shelves): foreach ($zone_shelves as $s): ?>
<form method="POST" action="zones.php" id="form-delshelf-<?= $s['shelf_id'] ?>" style="display:none">
  <input type="hidden" name="action"   value="delete_shelf">
  <input type="hidden" name="shelf_id" value="<?= $s['shelf_id'] ?>">
</form>
<?php endforeach; endforeach; ?>

<div class="modal-overlay" id="add-zone-modal">
  <div class="modal" style="width:420px">
    <div class="modal-header">
      <div class="modal-title">🗂️ Add New Zone</div>
      <button class="modal-close" onclick="closeModal('add-zone-modal')">✕</button>
    </div>
    <form method="POST" action="zones.php">
      <input type="hidden" name="action" value="add_zone">
      <div class="form-group">
        <label class="form-label">Zone Name *</label>
        <input class="form-ctrl" type="text" name="zone_name" placeholder="e.g. Zone D" required>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <input class="form-ctrl" type="text" name="zone_description" placeholder="e.g. Small appliances storage">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-zone-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Zone</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="add-shelf-modal">
  <div class="modal" style="width:420px">
    <div class="modal-header">
      <div class="modal-title">📌 Add New Shelf</div>
      <button class="modal-close" onclick="closeModal('add-shelf-modal')">✕</button>
    </div>
    <form method="POST" action="zones.php">
      <input type="hidden" name="action" value="add_shelf">
      <div class="form-group">
        <label class="form-label">Zone *</label>
        <select class="form-ctrl" name="zone_id" required>
          <option value="">Select zone</option>
          <?php foreach ($zone_dd as $z) { ?>
            <option value="<?= $z['zone_id'] ?>"><?= htmlspecialchars($z['zone_name']) ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Shelf Name *</label>
        <input class="form-ctrl" type="text" name="shelf_name" placeholder="e.g. Shelf 5" required>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <input class="form-ctrl" type="text" name="shelf_description" placeholder="e.g. Top row, large items">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-shelf-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Shelf</button>
      </div>
    </form>
  </div>
</div>

<!-- z-index 400 so these always appear above the zone-detail-modal (z-index 200) -->
<div class="modal-overlay" id="edit-zone-modal" style="z-index:400">
  <div class="modal" style="width:420px">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Zone</div>
      <button class="modal-close" onclick="closeModal('edit-zone-modal')">✕</button>
    </div>
    <form method="POST" action="zones.php">
      <input type="hidden" name="action"  value="edit_zone">
      <input type="hidden" name="zone_id" id="edit-zone-id">
      <div class="form-group">
        <label class="form-label">Zone Name *</label>
        <input class="form-ctrl" type="text" name="zone_name" id="edit-zone-name" required>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <input class="form-ctrl" type="text" name="zone_description" id="edit-zone-desc" placeholder="e.g. Small appliances storage">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-zone-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="edit-shelf-modal" style="z-index:400">
  <div class="modal" style="width:420px">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Shelf</div>
      <button class="modal-close" onclick="closeModal('edit-shelf-modal')">✕</button>
    </div>
    <form method="POST" action="zones.php">
      <input type="hidden" name="action"   value="edit_shelf">
      <input type="hidden" name="shelf_id" id="edit-shelf-id">
      <div class="form-group">
        <label class="form-label">Shelf Name *</label>
        <input class="form-ctrl" type="text" name="shelf_name" id="edit-shelf-name" required>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <input class="form-ctrl" type="text" name="shelf_description" id="edit-shelf-desc" placeholder="e.g. Top row, large items">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-shelf-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="zone-detail-modal" style="align-items:center;justify-content:center;padding:20px">
  <div id="zone-detail-box" style="
    background:var(--white);border-radius:var(--radius-xl);
    width:780px;max-width:95vw;max-height:88vh;
    display:flex;flex-direction:column;
    box-shadow:0 24px 80px rgba(0,0,0,.18);
    animation:fadeUp .2s ease;overflow:hidden;
  ">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:22px 28px 18px;border-bottom:1px solid var(--gray-100);flex-shrink:0">
      <div>
        <div id="zone-modal-title" style="font-size:18px;font-weight:800;color:var(--gray-800)">Zone Shelves</div>
        <div id="zone-modal-sub"   style="font-size:12px;color:var(--gray-400);margin-top:2px"></div>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <a id="zone-modal-add-btn" href="appliances.php" class="btn btn-primary btn-sm">＋ Add Appliance</a>
        <button class="modal-close" onclick="closeModal('zone-detail-modal')">✕</button>
      </div>
    </div>
    <div id="zone-modal-body" style="overflow-y:auto;flex:1;padding:22px 28px;display:flex;flex-direction:column;gap:16px">
      </div>
  </div>
</div>

<?php
// Embed all zone/shelf/appliance data as JSON for JS to consume securely
$zone_data = [];
foreach ($zones_list as $z) {
    $zid = $z['zone_id'];
    $shelves = $shelves_by_zone[$zid] ?? [];
    $shelf_data = [];
    foreach ($shelves as $s) {
        $sid  = $s['shelf_id'];
        $apls = $appliances_by_shelf[$sid] ?? [];
        $apl_rows = [];
        foreach ($apls as $ap) {
            $cond_info  = $cond_labels[$ap['appliance_condition']] ?? ['label'=>$ap['appliance_condition'],'pill'=>'pill-gray'];
            $apl_rows[] = [
                'appliance_id' => $ap['appliance_id'],
                'sku'          => $ap['sku'],
                'name'         => $ap['name'],
                'brand'        => $ap['brand'] ?? '',
                'category'     => $ap['category_name'] ?? '—',
                'condition'    => $cond_info['label'],
                'cond_pill'    => $cond_info['pill'],
                'stock'        => $ap['stock'],
                'min_stock'    => $ap['min_stock'],
            ];
        }
        $shelf_data[] = [
            'shelf_id'    => $sid,
            'shelf_name'  => $s['shelf_name'],
            'description' => $s['description'] ?? '',
            'item_count'  => $s['item_count'],
            'total_stock' => $s['total_stock'],
            'appliances'  => $apl_rows,
        ];
    }
    $zone_data[$zid] = [
        'zone_id'    => $zid,
        'zone_name'  => $z['zone_name'],
        'description'=> $z['description'] ?? '',
        'total_stock'=> $z['total_stock'],
        'shelves'    => $shelf_data,
    ];
}
?>
<script>
const ZONE_DATA   = <?= json_encode($zone_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const USER_ROLE   = <?= json_encode($role) ?>;

// ── Open zone detail modal ──
function openZoneModal(zoneId) {
  const z = ZONE_DATA[zoneId];
  if (!z) return;

  document.getElementById('zone-modal-title').textContent = '🗂️ ' + z.zone_name + ' — Shelves';
  document.getElementById('zone-modal-sub').textContent   =
    z.description ? z.description + ' · ' + z.total_stock + ' total units'
                  : z.total_stock + ' total units across ' + z.shelves.length + ' shelf/shelves';

  const body = document.getElementById('zone-modal-body');
  body.innerHTML = '';

  if (!z.shelves.length) {
    body.innerHTML = `
      <div class="empty-state">
        <div class="icon">📭</div>
        <div class="title">No shelves in this zone yet</div>
        <div class="desc">Add a shelf using the ＋ Add Shelf button on the main page.</div>
      </div>`;
  } else {
    z.shelves.forEach(s => {
      body.appendChild(buildShelfCard(s, z.zone_name));
    });
  }

  openModal('zone-detail-modal');
}

// ── Build one shelf card ──
function buildShelfCard(shelf, zoneName) {
  const card = document.createElement('div');
  card.style.cssText = `
    border:1px solid var(--gray-200);border-radius:var(--radius-lg);
    overflow:hidden;background:var(--white);
  `;

  // ── Shelf header ──
  const header = document.createElement('div');
  header.style.cssText = `
    display:flex;align-items:center;justify-content:space-between;
    padding:14px 18px;background:var(--gray-50);
    border-bottom:1px solid var(--gray-200);cursor:pointer;
    user-select:none;
  `;
  header.innerHTML = `
    <div style="display:flex;align-items:center;gap:12px;min-width:0;flex:1">
      <div style="font-size:15px;font-weight:800;color:var(--gray-800);white-space:nowrap">📌 ${esc(shelf.shelf_name)}</div>
      <span class="pill pill-blue" style="flex-shrink:0">${shelf.item_count} type${shelf.item_count !== 1 ? 's' : ''}</span>
      <span class="pill pill-gray" style="flex-shrink:0">${shelf.total_stock} units</span>
      ${shelf.description ? `<span style="font-size:12px;color:var(--gray-400);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0">${esc(shelf.description)}</span>` : ''}
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;margin-left:12px">
      ${USER_ROLE === 'admin' ? `
        <button type="button" class="btn btn-secondary btn-sm" style="padding:8px 8px;font-size:11px"
          onclick="event.stopPropagation();openEditShelf(${shelf.shelf_id}, \`${esc(shelf.shelf_name)}\`, \`${esc(shelf.description)}\`)">
          ✏️
        </button>
        <button type="button" class="btn btn-danger btn-sm" style="padding:8px 8px;font-size:11px"
          onclick="event.stopPropagation();confirmAction('Delete Shelf','This shelf will be permanently deleted. Appliances assigned to it will be unlinked.','🗑️','form-delshelf-${shelf.shelf_id}','Delete Shelf','btn-danger')">
          🗑️
        </button>` : ''}
      <span class="shelf-toggle-arrow" style="font-size:11px;color:var(--gray-400);transition:transform .2s">▼</span>
    </div>
  `;

  // ── Shelf body (collapsible) ──
  const bodyWrap = document.createElement('div');
  bodyWrap.style.cssText = 'overflow:hidden;transition:max-height .3s ease;max-height:0';

  const bodyInner = document.createElement('div');
  bodyInner.style.cssText = 'padding:16px 18px';

  if (!shelf.appliances.length) {
    bodyInner.innerHTML = `
      <div style="text-align:center;padding:20px;color:var(--gray-400)">
        <div style="font-size:24px;margin-bottom:6px">📦</div>
        <div style="font-size:13px;font-weight:600;color:var(--gray-500)">No appliances on this shelf yet</div>
        <div style="font-size:12px;margin-top:4px">Use the ＋ Add Appliance button above to assign one.</div>
      </div>`;
  } else {
    const table = document.createElement('div');
    table.style.cssText = 'overflow-x:auto';
    table.innerHTML = `
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="background:var(--gray-50)">
            <th style="padding:9px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid var(--gray-200)">SKU</th>
            <th style="padding:9px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid var(--gray-200)">Name</th>
            <th style="padding:9px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid var(--gray-200)">Category</th>
            <th style="padding:9px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid var(--gray-200)">Stock</th>
            <th style="padding:9px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid var(--gray-200)">Condition</th>
            <th style="padding:9px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid var(--gray-200)">Actions</th>
          </tr>
        </thead>
        <tbody>
          ${shelf.appliances.map(ap => `
            <tr style="border-bottom:1px solid var(--gray-100)">
              <td style="padding:11px 12px;color:var(--gray-400);font-size:12px;font-weight:700">${esc(ap.sku)}</td>
              <td style="padding:11px 12px">
                <div style="font-weight:600;color:var(--gray-800)">${esc(ap.name)}</div>
                ${ap.brand ? `<div style="font-size:11px;color:var(--gray-400)">${esc(ap.brand)}</div>` : ''}
              </td>
              <td style="padding:11px 12px"><span class="pill pill-blue">${esc(ap.category)}</span></td>
              <td style="padding:11px 12px">
                <span style="font-weight:800;color:${ap.stock <= ap.min_stock ? 'var(--red)' : 'var(--gray-800)'}">${ap.stock}</span>
                ${ap.stock <= ap.min_stock ? '<span class="pill pill-red" style="margin-left:4px;font-size:10px">Low</span>' : ''}
              </td>
              <td style="padding:11px 12px"><span class="pill ${ap.cond_pill}">${esc(ap.condition)}</span></td>
              <td style="padding:11px 12px">
                <a href="appliances.php?edit=${ap.appliance_id}" class="btn btn-secondary btn-sm" style="font-size:11px;padding:5px 10px">✏️ Edit</a>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>`;
    bodyInner.appendChild(table);
  }

  bodyWrap.appendChild(bodyInner);
  card.appendChild(header);
  card.appendChild(bodyWrap);

  // ── Toggle collapse on header click ──
  let open = false;
  header.addEventListener('click', () => {
    open = !open;
    bodyWrap.style.maxHeight = open ? bodyWrap.scrollHeight + 500 + 'px' : '0';
    const arrow = header.querySelector('.shelf-toggle-arrow');
    arrow.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
    header.style.background = open ? 'var(--blue-50)' : 'var(--gray-50)';
  });

  // Auto-open if only one shelf or it has items
  if (shelf.appliances.length > 0) {
    setTimeout(() => header.click(), 50);
  }

  return card;
}

// ── HTML escape helper ──
function esc(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<script>
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

  function openEditZone(zoneId, zoneName, zoneDesc) {
    document.getElementById('edit-zone-id').value   = zoneId;
    document.getElementById('edit-zone-name').value = zoneName;
    document.getElementById('edit-zone-desc').value = zoneDesc;
    openModal('edit-zone-modal');
  }

  function openEditShelf(shelfId, shelfName, shelfDesc) {
    document.getElementById('edit-shelf-id').value   = shelfId;
    document.getElementById('edit-shelf-name').value = shelfName;
    document.getElementById('edit-shelf-desc').value = shelfDesc;
    openModal('edit-shelf-modal');
  }
  
  // Safe transition alert dismissing block
  setTimeout(() => {
    document.querySelectorAll('[style*="green-bg"], [style*="red-bg"]').forEach(el => {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    });
  }, 4000);

  // ── Confirm dialog ──
  function confirmAction(title, message, icon, formId, confirmLabel, confirmClass) {
    document.getElementById('confirm-icon').textContent  = icon  || '⚠️';
    document.getElementById('confirm-title').textContent = title || 'Confirm';
    document.getElementById('confirm-desc').textContent  = message || 'Are you sure?';
    const btn = document.getElementById('confirm-ok-btn');
    btn.textContent = confirmLabel || 'Confirm';
    btn.className   = 'btn ' + (confirmClass || 'btn-danger');
    btn.onclick = function () {
      closeConfirm();
      // Directly selecting and executing form submission structures explicitly
      const targetForm = document.getElementById(formId);
      if (targetForm) {
          HTMLFormElement.prototype.submit.call(targetForm);
      }
    };
    document.getElementById('confirm-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeConfirm() {
    document.getElementById('confirm-overlay').classList.remove('open');
    document.body.style.overflow = '';
  }
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeConfirm(); });
</script>

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

</body>
</html>