<?php

//  InvenTech — Appliances
//  File: appliances.php

session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];

// ── Auto-open edit modal when coming from zones.php (?edit=ID) ──
$auto_edit_data = null;
$auto_open_add  = isset($_GET['add']) && $_GET['add'] === '1';
if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $ae_id   = intval($_GET['edit']);
    $ae_stmt = $conn->prepare("SELECT * FROM appliances WHERE appliance_id = ? AND status = 'active' LIMIT 1");
    $ae_stmt->bind_param('i', $ae_id);
    $ae_stmt->execute();
    $auto_edit_data = $ae_stmt->get_result()->fetch_assoc();
    $ae_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD APPLIANCE ──
    if ($action === 'add') {
        $last_sku_stmt = $conn->prepare("SELECT sku FROM appliances ORDER BY appliance_id DESC LIMIT 1");
        $last_sku_stmt->execute();
        $last_sku_row = $last_sku_stmt->get_result()->fetch_assoc();
        $last_sku_stmt->close();
        if ($last_sku_row) {
            $last_num = intval(substr($last_sku_row['sku'], 4));
            $sku = 'APL-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $sku = 'APL-001';
        }
        $name      = trim($_POST['name']);
        $brand     = trim($_POST['brand']);
        $model     = trim($_POST['model']);
        $cat_id    = intval($_POST['category_id']);
        $cond      = trim($_POST['appliance_condition']);
        $stock     = intval($_POST['stock']);
        $min_stock = intval($_POST['min_stock']);
        $zone_id   = intval($_POST['zone_id']) ?: null;
        $shelf_id  = intval($_POST['shelf_id']) ?: null;
        $desc      = trim($_POST['description']);

        $image_name = null;
        if (!empty($_FILES['image']['name'])) {
            $upload_dir = __DIR__ . '/uploads/appliances/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $allowed) && $_FILES['image']['size'] <= 2097152) {
                $image_name = 'APL_' . time() . '_' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
            } else {
                $error = "Image must be JPG, PNG, or WEBP and under 2MB.";
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("
                INSERT INTO appliances (sku, name, brand, model, category_id, appliance_condition, stock, min_stock, zone_id, shelf_id, description, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('ssssisiiiiss', $sku, $name, $brand, $model, $cat_id, $cond, $stock, $min_stock, $zone_id, $shelf_id, $desc, $image_name);
            if ($stmt->execute()) {
                $log_desc = "Added new appliance: $name (SKU: $sku)";
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'added', ?)");
                $log->bind_param('is', $uid, $log_desc);
                $log->execute();
                if ($stock <= $min_stock) {
                    $alert_title = "$name — Low Stock ($stock units remaining)";
                    $alert_desc  = "Stock is at or below the minimum threshold of $min_stock units.";
                    $al = $conn->prepare("INSERT INTO alerts (alert_type, title, description) VALUES ('warning', ?, ?)");
                    $al->bind_param('ss', $alert_title, $alert_desc);
                    $al->execute();
                }
                $success = "Appliance added successfully.";
            } else {
                $error = "Failed to add appliance. SKU may already exist.";
            }
            $stmt->close();
        }
    }

    // ── EDIT APPLIANCE ──
    if ($action === 'edit') {
        $id        = intval($_POST['appliance_id']);
        $sku       = trim($_POST['sku']);
        $name      = trim($_POST['name']);
        $brand     = trim($_POST['brand']);
        $model     = trim($_POST['model']);
        $cat_id    = intval($_POST['category_id']);
        $cond      = trim($_POST['appliance_condition']);
        $stock     = intval($_POST['stock']);
        $min_stock = intval($_POST['min_stock']);
        $zone_id   = intval($_POST['zone_id']) ?: null;
        $shelf_id  = intval($_POST['shelf_id']) ?: null;
        $desc      = trim($_POST['description']);

        // Fetch current image from DB as the authoritative source
        $img_stmt = $conn->prepare("SELECT image FROM appliances WHERE appliance_id = ?");
        $img_stmt->bind_param('i', $id);
        $img_stmt->execute();
        $img_row = $img_stmt->get_result()->fetch_assoc();
        $img_stmt->close();
        $edit_image_name = $img_row['image'] ?? '';

        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            $upload_dir = __DIR__ . '/uploads/appliances/';
            if ($edit_image_name && file_exists($upload_dir . $edit_image_name)) {
                unlink($upload_dir . $edit_image_name);
            }
            $edit_image_name = null;
        } elseif (!empty($_FILES['image']['name'])) {
            $upload_dir = __DIR__ . '/uploads/appliances/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $allowed) && $_FILES['image']['size'] <= 2097152) {
                if ($edit_image_name && file_exists($upload_dir . $edit_image_name)) {
                    unlink($upload_dir . $edit_image_name);
                }
                $edit_image_name = 'APL_' . time() . '_' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $edit_image_name);
            }
        }

        $stmt = $conn->prepare("
            UPDATE appliances
            SET sku=?, name=?, brand=?, model=?, category_id=?,
                appliance_condition=?, stock=?, min_stock=?, zone_id=?, shelf_id=?, description=?, image=?
            WHERE appliance_id=?
        ");
        $stmt->bind_param('ssssisiiiissi', $sku, $name, $brand, $model, $cat_id, $cond, $stock, $min_stock, $zone_id, $shelf_id, $desc, $edit_image_name, $id);
        if ($stmt->execute()) {
            $log_desc = "Edited appliance: $name (SKU: $sku)";
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'edited', ?)");
            $log->bind_param('is', $uid, $log_desc);
            $log->execute();
            $success = "Appliance updated successfully.";
        } else {
            $error = "Failed to update appliance.";
        }
        $stmt->close();
    }

    // ── ARCHIVE (soft delete) ──
    if ($action === 'delete') {
        $id = intval($_POST['appliance_id']);
        $stmt_row = $conn->prepare("SELECT name, sku FROM appliances WHERE appliance_id = ?");
        $stmt_row->bind_param('i', $id);
        $stmt_row->execute();
        $row = $stmt_row->get_result()->fetch_assoc();
        $stmt_row->close();

        $stmt = $conn->prepare("UPDATE appliances SET status = 'inactive' WHERE appliance_id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $log_desc = "Archived appliance: {$row['name']} (SKU: {$row['sku']})";
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'deleted', ?)");
            $log->bind_param('is', $uid, $log_desc);
            $log->execute();
            // Redirect after archive to refresh the page cleanly
            header('Location: appliances.php?archived=1');
            exit();
        } else {
            $error = "Failed to archive appliance.";
        }
        $stmt->close();
    }

    // ── RESTORE ──
    if ($action === 'restore' && $role === 'admin') {
        $id = intval($_POST['appliance_id']);
        $stmt_row = $conn->prepare("SELECT name, sku FROM appliances WHERE appliance_id = ?");
        $stmt_row->bind_param('i', $id);
        $stmt_row->execute();
        $row = $stmt_row->get_result()->fetch_assoc();
        $stmt_row->close();

        $stmt = $conn->prepare("UPDATE appliances SET status = 'active' WHERE appliance_id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $log_desc = "Restored appliance: {$row['name']} (SKU: {$row['sku']})";
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'edited', ?)");
            $log->bind_param('is', $uid, $log_desc);
            $log->execute();
            // Redirect after restore to refresh the page cleanly
            header('Location: appliances.php?restored=1');
            exit();
        } else {
            $error = "Failed to restore appliance.";
        }
        $stmt->close();
    }
}

// Show success message if redirected from restore or archive
if (isset($_GET['restored'])) {
    $success = "Appliance restored successfully.";
}
if (isset($_GET['archived'])) {
    $success = "Appliance archived. You can restore it from the Archived Items section below.";
}

// ── FETCH ────────────────────────────────────────────────────
$filter_cat  = intval($_GET['category_id'] ?? 0);
$filter_cond = trim($_GET['appliance_condition'] ?? '');
$filter_zone = intval($_GET['zone_id'] ?? 0);
$search      = trim($_GET['search'] ?? '');

$where  = ["a.status = 'active'"];
$params = [];
$types  = '';

if ($filter_cat)  { $where[] = "a.category_id = ?";        $params[] = $filter_cat;  $types .= 'i'; }
if ($filter_cond) { $where[] = "a.appliance_condition = ?"; $params[] = $filter_cond; $types .= 's'; }
if ($filter_zone) { $where[] = "a.zone_id = ?";             $params[] = $filter_zone; $types .= 'i'; }
if ($search)      {
    $where[] = "(a.name LIKE ? OR a.brand LIKE ? OR a.sku LIKE ?)";
    $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; $types .= 'sss';
}

$where_sql = implode(' AND ', $where);
$sql = "
    SELECT a.*, c.category_name, z.zone_name, s.shelf_name
    FROM appliances a
    LEFT JOIN categories c ON a.category_id = c.category_id
    LEFT JOIN zones      z ON a.zone_id      = z.zone_id
    LEFT JOIN shelves    s ON a.shelf_id     = s.shelf_id
    WHERE $where_sql
    ORDER BY a.created_at DESC
";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $appliances = $stmt->get_result();
} else {
    $appliances = $conn->query($sql);
}

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
$zones      = $conn->query("SELECT * FROM zones ORDER BY zone_name");
$shelves    = $conn->query("SELECT * FROM shelves ORDER BY zone_id, shelf_name");

$cat_list  = [];
while ($r = $categories->fetch_assoc()) $cat_list[] = $r;
$zone_list = [];
while ($r = $zones->fetch_assoc()) $zone_list[] = $r;
$shelf_list = [];
$shelf_seen = [];
while ($r = $shelves->fetch_assoc()) {
    if (!in_array($r['shelf_id'], $shelf_seen)) {
        $shelf_list[] = $r;
        $shelf_seen[] = $r['shelf_id'];
    }
}

$conditions = [
    'brand_new'  => 'Brand New',
    'good'       => 'Good',
    'fair'       => 'Fair',
    'for_repair' => 'For Repair',
    'defective'  => 'Defective',
];
$cond_pills = [
    'brand_new'  => 'pill-green',
    'good'       => 'pill-green',
    'fair'       => 'pill-yellow',
    'for_repair' => 'pill-orange',
    'defective'  => 'pill-red',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InvenTech — Appliances</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
<?php include __DIR__ . '/includes/styles.php'; ?>
<style>
/* ── MODAL SCROLL FIX ── */
.modal {
  display: flex;
  flex-direction: column;
  max-height: 90vh;
}
.modal form {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  flex: 1;
  min-height: 0;
}
.modal-body {
  overflow-y: auto;
  flex: 1;
  padding: 0 2px;
}
.modal-footer {
  flex-shrink: 0;
  border-top: 1px solid var(--gray-200);
  padding-top: 16px;
  margin-top: 16px;
  background: var(--white);
}

/* ── IMAGE PREVIEW BOX ── */
.img-preview-box {
  width: 90px;
  height: 90px;
  border-radius: 10px;
  background: var(--gray-100);
  border: 2px dashed var(--gray-300);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  flex-shrink: 0;
  overflow: hidden;
  position: relative;
}
.img-preview-box img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 8px;
  background: #fff;
  display: block;
}

/* ── CUSTOM FILE UPLOAD ── */
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
  max-width: 220px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.file-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

/* ── SORTABLE COLUMNS ── */
th.sortable {
  cursor: pointer;
  user-select: none;
  white-space: nowrap;
}
th.sortable:hover {
  background: var(--gray-100);
}
th.sortable .sort-icon {
  display: inline-block;
  margin-left: 4px;
  font-size: 11px;
  color: var(--gray-400);
  transition: color .15s;
}
th.sortable:hover .sort-icon {
  color: var(--gray-600);
}
th.sort-asc .sort-icon,
th.sort-desc .sort-icon {
  color: var(--blue-600);
}
th.sort-asc .sort-icon::after   { content: ' ▲'; }
th.sort-desc .sort-icon::after  { content: ' ▼'; }
th.sort-asc .sort-icon,
th.sort-desc .sort-icon { font-size: 0; }
th.sort-asc .sort-icon::after,
th.sort-desc .sort-icon::after { font-size: 11px; }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
  <?php include __DIR__ . '/includes/topbar.php'; ?>
  <div class="content">

    <div class="section-header">
      <div>
        <div class="section-title">📋 Appliances</div>
        <div class="section-sub">Manage all appliance records — add, edit, view, and archive units</div>
      </div>
      <button class="btn btn-primary" onclick="openModal('add-modal')">＋ Add Appliance</button>
    </div>

    <?php if (!empty($success)): ?>
      <div class="alert-msg" style="background:var(--green-bg);color:#065f46;border:1px solid #6ee7b7;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✅ <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="alert-msg" style="background:var(--red-bg);color:#991b1b;border:1px solid #fca5a5;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠️ <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="GET" action="appliances.php">
      <div class="toolbar">
        <div class="search-wrap">
          <input class="search-input" type="text" name="search" placeholder="Search by name, brand, or SKU..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <select class="filter-select" name="category_id">
          <option value="">All Categories</option>
          <?php foreach ($cat_list as $c): ?>
            <option value="<?= $c['category_id'] ?>" <?= $filter_cat == $c['category_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['category_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <select class="filter-select" name="appliance_condition">
          <option value="">All Conditions</option>
          <?php foreach ($conditions as $val => $label): ?>
            <option value="<?= $val ?>" <?= $filter_cond === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <select class="filter-select" name="zone_id">
          <option value="">All Zones</option>
          <?php foreach ($zone_list as $z): ?>
            <option value="<?= $z['zone_id'] ?>" <?= $filter_zone == $z['zone_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($z['zone_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary">🔍 Filter</button>
        <a href="appliances.php" class="btn btn-secondary">✕ Clear</a>
      </div>
    </form>

    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th style="width:72px">Photo</th>
              <th class="sortable" data-col="1" onclick="sortTable(this)">SKU <span class="sort-icon">⇅</span></th>
              <th class="sortable" data-col="2" onclick="sortTable(this)">Name <span class="sort-icon">⇅</span></th>
              <th class="sortable" data-col="3" onclick="sortTable(this)">Brand / Model <span class="sort-icon">⇅</span></th>
              <th class="sortable" data-col="4" onclick="sortTable(this)">Category <span class="sort-icon">⇅</span></th>
              <th class="sortable" data-col="5" data-type="number" onclick="sortTable(this)">Stock <span class="sort-icon">⇅</span></th>
              <th class="sortable" data-col="6" onclick="sortTable(this)">Condition <span class="sort-icon">⇅</span></th>
              <th class="sortable" data-col="7" onclick="sortTable(this)">Location <span class="sort-icon">⇅</span></th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($appliances->num_rows > 0):
              while ($a = $appliances->fetch_assoc()):
                $pill     = $cond_pills[$a['appliance_condition']] ?? 'pill-gray';
                $cond_lbl = $conditions[$a['appliance_condition']] ?? $a['appliance_condition'];
                $low      = $a['stock'] <= $a['min_stock'];
            ?>
            <tr>
              <td>
                <?php if (!empty($a['image'])): ?>
                  <img src="uploads/appliances/<?= htmlspecialchars($a['image']) ?>"
                    style="width:72px;height:72px;object-fit:cover;border-radius:10px;border:1px solid var(--gray-200);display:block;cursor:zoom-in;transition:transform .2s,box-shadow .2s"
                    onmouseover="this.style.transform='scale(1.06)';this.style.boxShadow='0 4px 16px rgba(0,0,0,.15)'"
                    onmouseout="this.style.transform='scale(1)';this.style.boxShadow='none'"
                    onclick="openPhotoViewer('uploads/appliances/<?= htmlspecialchars($a['image']) ?>','<?= htmlspecialchars(addslashes($a['name'])) ?>','<?= htmlspecialchars($a['sku']) ?>')"
                    alt="<?= htmlspecialchars($a['name']) ?>">
                <?php else: ?>
                  <div style="width:72px;height:72px;border-radius:10px;background:var(--gray-100);border:1px solid var(--gray-200);display:flex;align-items:center;justify-content:center;font-size:28px">📦</div>
                <?php endif; ?>
              </td>
              <td class="text-muted font-bold"><?= htmlspecialchars($a['sku']) ?></td>
              <td class="td-main"><?= htmlspecialchars($a['name']) ?></td>
              <td>
                <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($a['brand'] ?? '—') ?></div>
                <div style="font-size:11px;color:var(--gray-400)"><?= htmlspecialchars($a['model'] ?? '') ?></div>
              </td>
              <td><span class="pill pill-blue"><?= htmlspecialchars($a['category_name'] ?? '—') ?></span></td>
              <td>
                <span style="font-weight:800;color:<?= $low ? 'var(--red)' : 'var(--gray-800)' ?>">
                  <?= $a['stock'] ?>
                </span>
                <?php if ($low): ?>
                  <span class="pill pill-red" style="margin-left:4px;font-size:10px">Low</span>
                <?php endif; ?>
              </td>
              <td><span class="pill <?= $pill ?>"><?= $cond_lbl ?></span></td>
              <td style="font-size:12px;color:var(--gray-500)">
                <?= htmlspecialchars($a['zone_name'] ?? '—') ?>
                <?= $a['shelf_name'] ? ' / ' . htmlspecialchars($a['shelf_name']) : '' ?>
              </td>
              <td>
                <div class="flex gap-8">
                  <button class="btn btn-secondary btn-sm" onclick='openEdit(<?= json_encode($a) ?>)'>✏️ Edit</button>
                  <?php if ($role === 'admin'):
                    $arch_id = 'arch-' . $a['appliance_id'];
                  ?>
                  <form method="POST" id="<?= $arch_id ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="appliance_id" value="<?= $a['appliance_id'] ?>">
                    <button type="button" class="btn btn-danger btn-sm"
                      onclick="confirmAction('Archive Appliance','This appliance will be archived. You can restore it from Archived Items below.','🗂️','<?= $arch_id ?>','Archive','btn-danger')">
                      🗑️
                    </button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
              <td colspan="9">
                <div class="empty-state">
                  <div class="icon">📦</div>
                  <div class="title">No appliances found</div>
                  <div class="desc">Try adjusting your filters or add a new appliance.</div>
                </div>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($role === 'admin'):
      $archived = $conn->query("
          SELECT a.*, c.category_name FROM appliances a
          LEFT JOIN categories c ON a.category_id = c.category_id
          WHERE a.status = 'inactive'
          ORDER BY a.updated_at DESC
      ");
      if ($archived->num_rows > 0):
    ?>
    <div style="margin-top:28px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;cursor:pointer" onclick="toggleArchived()">
        <div style="font-size:16px;font-weight:800;color:var(--gray-600)">🗄️ Archived Items</div>
        <span class="pill pill-gray"><?= $archived->num_rows ?> items</span>
        <span id="archive-arrow" style="color:var(--gray-400);font-size:12px">▼ Show</span>
      </div>
      <div id="archived-table" style="display:none">
        <div class="card">
          <div style="font-size:12px;color:var(--gray-400);margin-bottom:14px">These appliances have been archived. Only admins can see and restore them.</div>
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th style="width:72px">Photo</th>
                  <th>SKU</th>
                  <th>Name</th>
                  <th>Category</th>
                  <th>Last Updated</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($ar = $archived->fetch_assoc()):
                  $rest_id = 'rest-' . $ar['appliance_id'];
                ?>
                <tr>
                  <td>
                    <?php if (!empty($ar['image'])): ?>
                      <img src="uploads/appliances/<?= htmlspecialchars($ar['image']) ?>"
                        style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid var(--gray-200);display:block;cursor:zoom-in;opacity:.75;transition:opacity .2s"
                        onmouseover="this.style.opacity='1'"
                        onmouseout="this.style.opacity='.75'"
                        onclick="openPhotoViewer('uploads/appliances/<?= htmlspecialchars($ar['image']) ?>','<?= htmlspecialchars(addslashes($ar['name'])) ?>','<?= htmlspecialchars($ar['sku']) ?>')"
                        alt="<?= htmlspecialchars($ar['name']) ?>">
                    <?php else: ?>
                      <div style="width:56px;height:56px;border-radius:8px;background:var(--gray-100);border:1px solid var(--gray-200);display:flex;align-items:center;justify-content:center;font-size:22px;opacity:.6">📦</div>
                    <?php endif; ?>
                  </td>
                  <td class="text-muted font-bold"><?= htmlspecialchars($ar['sku']) ?></td>
                  <td class="td-main"><?= htmlspecialchars($ar['name']) ?></td>
                  <td><span class="pill pill-gray"><?= htmlspecialchars($ar['category_name'] ?? '—') ?></span></td>
                  <td class="text-muted"><?= date('M j, Y', strtotime($ar['updated_at'])) ?></td>
                  <td>
                    <!-- FIX: action="appliances.php" added so form submits correctly -->
                    <form method="POST" action="appliances.php" id="<?= $rest_id ?>">
                      <input type="hidden" name="action" value="restore">
                      <input type="hidden" name="appliance_id" value="<?= $ar['appliance_id'] ?>">
                      <button type="button" class="btn btn-secondary btn-sm"
                        onclick="confirmAction('Restore Appliance','This appliance will be moved back to active inventory.','♻️','<?= $rest_id ?>','Restore','btn-primary')">
                        ♻️ Restore
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php endif; endif; ?>

  </div>
</div>

<!-- ══ ADD MODAL ══ -->
<div class="modal-overlay" id="add-modal">
  <div class="modal" style="width:560px">
    <div class="modal-header">
      <div class="modal-title">➕ Add New Appliance</div>
      <button class="modal-close" onclick="closeModal('add-modal')">✕</button>
    </div>
    <form method="POST" action="appliances.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group">
            <label class="form-label">SKU <span style="color:var(--gray-400);font-size:10px;text-transform:none;font-weight:400">(auto-generated)</span></label>
            <input class="form-ctrl" type="text" id="preview-sku" placeholder="Auto-assigned on save"
              style="background:var(--gray-50);color:var(--gray-400);cursor:not-allowed" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Appliance Name *</label>
            <input class="form-ctrl" type="text" name="name" placeholder="e.g. 2-Door Refrigerator" required>
          </div>
          <div class="form-group">
            <label class="form-label">Brand</label>
            <input class="form-ctrl" type="text" name="brand" placeholder="e.g. LG">
          </div>
          <div class="form-group">
            <label class="form-label">Model</label>
            <input class="form-ctrl" type="text" name="model" placeholder="e.g. GN-B392PLGK">
          </div>
          <div class="form-group">
            <label class="form-label">Category *</label>
            <select class="form-ctrl" name="category_id" required>
              <option value="">Select category</option>
              <?php foreach ($cat_list as $c): ?>
                <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Condition *</label>
            <select class="form-ctrl" name="appliance_condition" required>
              <?php foreach ($conditions as $val => $label): ?>
                <option value="<?= $val ?>"><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Stock Quantity *</label>
            <input class="form-ctrl" type="number" name="stock" min="0" value="0" required>
          </div>
          <div class="form-group">
            <label class="form-label">Min Stock Threshold *</label>
            <input class="form-ctrl" type="number" name="min_stock" min="1" value="5" required>
          </div>
          <div class="form-group">
            <label class="form-label">Zone</label>
            <select class="form-ctrl" name="zone_id" id="add-zone-select" onchange="filterShelves('add-zone-select','add-shelf-select')">
              <option value="">Select zone</option>
              <?php foreach ($zone_list as $z): ?>
                <option value="<?= $z['zone_id'] ?>"><?= htmlspecialchars($z['zone_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Shelf</label>
            <select class="form-ctrl" name="shelf_id" id="add-shelf-select">
              <option value="">Select zone first</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-ctrl" name="description" rows="2" placeholder="Optional notes..." style="resize:vertical"></textarea>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Item Photo <span style="color:var(--gray-400);font-size:10px;text-transform:none;font-weight:400">(JPG/PNG/WEBP · max 2MB · 1:1 recommended)</span></label>
          <div style="display:flex;align-items:flex-start;gap:14px">
            <div class="img-preview-box" id="add-img-preview">📦</div>
            <div style="flex:1;min-width:0">
              <div class="file-upload-wrap">
                <label class="file-upload-btn" for="add-img-input">📁 Choose Photo</label>
                <input id="add-img-input" type="file" name="image" accept="image/jpeg,image/png,image/webp"
                  onchange="handleFileSelect(this,'add-img-preview','add-file-name','add-clear-btn')" style="display:none">
                <div class="file-actions">
                  <span class="file-name-display" id="add-file-name">No file selected</span>
                  <button type="button" id="add-clear-btn" class="btn btn-danger btn-sm" style="display:none;padding:4px 8px;font-size:11px"
                    onclick="clearFile('add-img-input','add-img-preview','add-file-name','add-clear-btn')">✕ Clear</button>
                </div>
              </div>
              <div style="font-size:11px;color:var(--gray-400);margin-top:6px">Leave empty to use the default placeholder.</div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Appliance</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT MODAL ══ -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal" style="width:560px">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Appliance</div>
      <button class="modal-close" onclick="closeModal('edit-modal')">✕</button>
    </div>
    <form method="POST" action="appliances.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="appliance_id" id="edit-id">
      <input type="hidden" name="sku" id="edit-sku-hidden">
      <input type="hidden" name="existing_image" id="edit-existing-image">
      <input type="hidden" name="remove_image" id="edit-remove-image" value="0">
      <div class="modal-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group">
            <label class="form-label">SKU <span style="color:var(--gray-400);font-size:10px;text-transform:none;font-weight:400">(cannot be changed)</span></label>
            <input class="form-ctrl" type="text" id="edit-sku" style="background:var(--gray-50);color:var(--gray-400);cursor:not-allowed" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Appliance Name *</label>
            <input class="form-ctrl" type="text" name="name" id="edit-name" required>
          </div>
          <div class="form-group">
            <label class="form-label">Brand</label>
            <input class="form-ctrl" type="text" name="brand" id="edit-brand">
          </div>
          <div class="form-group">
            <label class="form-label">Model</label>
            <input class="form-ctrl" type="text" name="model" id="edit-model">
          </div>
          <div class="form-group">
            <label class="form-label">Category *</label>
            <select class="form-ctrl" name="category_id" id="edit-category">
              <option value="">Select category</option>
              <?php foreach ($cat_list as $c): ?>
                <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Condition *</label>
            <select class="form-ctrl" name="appliance_condition" id="edit-condition">
              <?php foreach ($conditions as $val => $label): ?>
                <option value="<?= $val ?>"><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Stock Quantity *</label>
            <input class="form-ctrl" type="number" name="stock" id="edit-stock" min="0" required>
          </div>
          <div class="form-group">
            <label class="form-label">Min Stock Threshold *</label>
            <input class="form-ctrl" type="number" name="min_stock" id="edit-min-stock" min="1" required>
          </div>
          <div class="form-group">
            <label class="form-label">Zone</label>
            <select class="form-ctrl" name="zone_id" id="edit-zone" onchange="filterShelves('edit-zone','edit-shelf')">
              <option value="">Select zone</option>
              <?php foreach ($zone_list as $z): ?>
                <option value="<?= $z['zone_id'] ?>"><?= htmlspecialchars($z['zone_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Shelf</label>
            <select class="form-ctrl" name="shelf_id" id="edit-shelf">
              <option value="">Select zone first</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-ctrl" name="description" id="edit-description" rows="2" style="resize:vertical"></textarea>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Item Photo</label>
          <div style="display:flex;align-items:flex-start;gap:14px">
            <div class="img-preview-box" id="edit-img-preview">📦</div>
            <div style="flex:1;min-width:0">
              <div class="file-upload-wrap">
                <label class="file-upload-btn" for="edit-img-input">📁 Change Photo</label>
                <input id="edit-img-input" type="file" name="image" accept="image/jpeg,image/png,image/webp"
                  onchange="handleFileSelect(this,'edit-img-preview','edit-file-name','edit-clear-btn')" style="display:none">
                <div class="file-actions">
                  <span class="file-name-display" id="edit-file-name">No file selected</span>
                  <button type="button" id="edit-clear-btn" class="btn btn-danger btn-sm" style="display:none;padding:4px 8px;font-size:11px"
                    onclick="clearFile('edit-img-input','edit-img-preview','edit-file-name','edit-clear-btn')">✕ Clear</button>
                </div>
              </div>
              <button type="button" id="edit-remove-db-btn" class="btn btn-danger btn-sm" style="display:none;margin-top:6px"
                onclick="removeExistingImage()">🗑️ Remove Current Photo</button>
              <div style="font-size:11px;color:var(--gray-400);margin-top:6px">Upload new photo to replace. Click Remove to delete current photo.</div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Appliance</button>
      </div>
    </form>
  </div>
</div>

<script>
const allShelves = <?php echo json_encode($shelf_list); ?>;

// ── Shelf filter ──
function filterShelves(zoneId, shelfId) {
  const zoneVal  = document.getElementById(zoneId).value;
  const shelfSel = document.getElementById(shelfId);
  shelfSel.innerHTML = '';
  if (!zoneVal) { shelfSel.innerHTML = '<option value="">Select zone first</option>'; return; }
  const filtered = allShelves.filter(s => s.zone_id == zoneVal);
  if (!filtered.length) { shelfSel.innerHTML = '<option value="">No shelves in this zone</option>'; return; }
  shelfSel.innerHTML = '<option value="">Select shelf</option>';
  filtered.forEach(s => {
    const o = document.createElement('option');
    o.value = s.shelf_id; o.textContent = s.shelf_name;
    shelfSel.appendChild(o);
  });
}

// ── Modal ──
function openModal(id) {
  document.getElementById(id).classList.add('open');
  document.body.style.overflow = 'hidden';
  if (id === 'add-modal') {
    fetch('get_next_sku.php').then(r => r.text()).then(sku => {
      const f = document.getElementById('preview-sku');
      if (f) f.placeholder = sku + ' (auto-assigned)';
    });
  }
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); });
});

// ── File handling ──
function handleFileSelect(input, previewId, nameId, clearBtnId) {
  const file = input.files[0];
  if (!file) return;
  // Update filename display (truncated)
  document.getElementById(nameId).textContent = file.name;
  document.getElementById(clearBtnId).style.display = 'inline-flex';
  // Preview image
  const reader = new FileReader();
  reader.onload = e => {
    const preview = document.getElementById(previewId);
    preview.innerHTML = '<img src="' + e.target.result + '">';
  };
  reader.readAsDataURL(file);
}

function clearFile(inputId, previewId, nameId, clearBtnId) {
  document.getElementById(inputId).value = '';
  document.getElementById(nameId).textContent = 'No file selected';
  document.getElementById(clearBtnId).style.display = 'none';
  // Restore preview — if edit modal, restore existing image
  if (inputId === 'edit-img-input') {
    const existing = document.getElementById('edit-existing-image').value;
    loadEditImage(existing, false);
  } else {
    document.getElementById(previewId).innerHTML = '📦';
  }
}

// ── Edit modal image management ──
function loadEditImage(imageName, resetRemoveFlag) {
  const preview    = document.getElementById('edit-img-preview');
  const removeDbBtn = document.getElementById('edit-remove-db-btn');
  if (resetRemoveFlag !== false) {
    document.getElementById('edit-remove-image').value = '0';
  }
  if (imageName) {
    preview.innerHTML = '<img src="uploads/appliances/' + imageName + '" onerror="this.parentElement.innerHTML=\'📦\'">';
    removeDbBtn.style.display = 'inline-flex';
  } else {
    preview.innerHTML = '📦';
    removeDbBtn.style.display = 'none';
  }
}

function removeExistingImage() {
  document.getElementById('edit-remove-image').value = '1';
  document.getElementById('edit-existing-image').value = '';
  document.getElementById('edit-img-preview').innerHTML = '📦';
  document.getElementById('edit-remove-db-btn').style.display = 'none';
  // Also clear any newly selected file
  document.getElementById('edit-img-input').value = '';
  document.getElementById('edit-file-name').textContent = 'No file selected';
  document.getElementById('edit-clear-btn').style.display = 'none';
}

// ── Populate edit modal ──
function openEdit(a) {
  document.getElementById('edit-id').value         = a.appliance_id;
  document.getElementById('edit-sku').value         = a.sku;
  document.getElementById('edit-sku-hidden').value  = a.sku;
  document.getElementById('edit-name').value        = a.name;
  document.getElementById('edit-brand').value       = a.brand    ?? '';
  document.getElementById('edit-model').value       = a.model    ?? '';
  document.getElementById('edit-stock').value       = a.stock;
  document.getElementById('edit-min-stock').value   = a.min_stock;
  document.getElementById('edit-description').value = a.description ?? '';
  // Reset file input
  document.getElementById('edit-img-input').value = '';
  document.getElementById('edit-file-name').textContent = 'No file selected';
  document.getElementById('edit-clear-btn').style.display = 'none';
  setSelect('edit-category',  a.category_id);
  setSelect('edit-condition', a.appliance_condition);
  setSelect('edit-zone',      a.zone_id);
  filterShelves('edit-zone', 'edit-shelf');
  setSelect('edit-shelf',     a.shelf_id);
  document.getElementById('edit-existing-image').value = a.image || '';
  loadEditImage(a.image || '');
  openModal('edit-modal');
}

function setSelect(id, val) {
  const s = document.getElementById(id);
  for (let o of s.options) o.selected = (o.value == val);
}

function toggleArchived() {
  const t = document.getElementById('archived-table');
  const a = document.getElementById('archive-arrow');
  const show = t.style.display === 'none';
  t.style.display = show ? 'block' : 'none';
  a.textContent   = show ? '▲ Hide' : '▼ Show';
}

setTimeout(() => {
  document.querySelectorAll('.alert-msg').forEach(el => {
    el.style.transition = 'opacity .5s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 500);
  });
}, 4000);

// ── Column Sort ──
function sortTable(th) {
  const table   = th.closest('table');
  const tbody   = table.querySelector('tbody');
  const rows    = Array.from(tbody.querySelectorAll('tr'));
  const col     = parseInt(th.dataset.col);
  const isNum   = th.dataset.type === 'number';
  const wasAsc  = th.classList.contains('sort-asc');
  const asc     = !wasAsc;

  // Reset all headers
  table.querySelectorAll('th.sortable').forEach(h => {
    h.classList.remove('sort-asc', 'sort-desc');
    h.querySelector('.sort-icon').textContent = '⇅';
  });

  th.classList.add(asc ? 'sort-asc' : 'sort-desc');

  rows.sort((a, b) => {
    const aEmpty = a.cells.length <= 1; // empty-state row
    const bEmpty = b.cells.length <= 1;
    if (aEmpty || bEmpty) return 0;

    let aVal = a.cells[col]?.innerText.trim() ?? '';
    let bVal = b.cells[col]?.innerText.trim() ?? '';

    if (isNum) {
      aVal = parseFloat(aVal) || 0;
      bVal = parseFloat(bVal) || 0;
      return asc ? aVal - bVal : bVal - aVal;
    }
    return asc
      ? aVal.localeCompare(bVal, undefined, { sensitivity: 'base' })
      : bVal.localeCompare(aVal, undefined, { sensitivity: 'base' });
  });

  rows.forEach(r => tbody.appendChild(r));
}
</script>

<!-- ══ PHOTO VIEWER MODAL ══ -->
<div id="photo-viewer" style="
  display:none; position:fixed; inset:0; z-index:2000;
  background:rgba(0,0,0,.85); backdrop-filter:blur(6px);
  align-items:center; justify-content:center; flex-direction:column;
  cursor:zoom-out;
" onclick="closePhotoViewer()">

  <!-- Close button -->
  <button onclick="closePhotoViewer()" style="
    position:absolute; top:20px; right:24px;
    width:40px; height:40px; border-radius:50%;
    background:rgba(255,255,255,.15); border:1.5px solid rgba(255,255,255,.3);
    color:#fff; font-size:18px; cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    transition:background .2s; z-index:1;
  " onmouseover="this.style.background='rgba(255,255,255,.25)'"
     onmouseout="this.style.background='rgba(255,255,255,.15)'">✕</button>

  <!-- Image container -->
  <div style="position:relative; max-width:85vw; max-height:80vh;" onclick="event.stopPropagation()">
    <img id="photo-viewer-img" src="" alt=""
      style="
        max-width:85vw; max-height:75vh;
        object-fit:contain; border-radius:14px;
        box-shadow:0 24px 80px rgba(0,0,0,.6);
        display:block;
      ">
  </div>

  <!-- Caption -->
  <div style="margin-top:16px; text-align:center; pointer-events:none" onclick="event.stopPropagation()">
    <div id="photo-viewer-name" style="font-size:16px; font-weight:700; color:#fff; margin-bottom:4px"></div>
    <div id="photo-viewer-sku"  style="font-size:12px; color:rgba(255,255,255,.5); font-family:monospace"></div>
    <div style="font-size:11px; color:rgba(255,255,255,.35); margin-top:8px">Click anywhere outside to close</div>
  </div>
</div>

<script>
function openPhotoViewer(src, name, sku) {
  document.getElementById('photo-viewer-img').src  = src;
  document.getElementById('photo-viewer-name').textContent = name;
  document.getElementById('photo-viewer-sku').textContent  = sku;
  const viewer = document.getElementById('photo-viewer');
  viewer.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  // Animate in
  viewer.style.opacity = '0';
  requestAnimationFrame(() => {
    viewer.style.transition = 'opacity .2s';
    viewer.style.opacity = '1';
  });
}
function closePhotoViewer() {
  const viewer = document.getElementById('photo-viewer');
  viewer.style.opacity = '0';
  setTimeout(() => {
    viewer.style.display = 'none';
    document.body.style.overflow = '';
  }, 200);
}
// Close with Escape key
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closePhotoViewer();
});
</script>

<?php if ($auto_edit_data): ?>
<script>
// Auto-open edit modal — triggered by ?edit=ID from zones.php
window.addEventListener('DOMContentLoaded', function() {
  openEdit(<?= json_encode($auto_edit_data) ?>);
});
</script>
<?php elseif ($auto_open_add): ?>
<script>
// Auto-open add modal — triggered by ?add=1 from zones.php
window.addEventListener('DOMContentLoaded', function() {
  openModal('add-modal');
});
</script>
<?php endif; ?>

</body>
</html>
