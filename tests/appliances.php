<?php
// ============================================================
//  InvenTech — Appliances
//  File: appliances.php
//  Place in: C:\xampp\htdocs\inventech\
// ============================================================

session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$uid  = $_SESSION['user_id'];
$role = $_SESSION['role'];

$success = '';
$error = '';

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
        $cat_id    = intval($_POST['category_id']) ?: null;
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
        $name      = trim($_POST['name']);
        $brand     = trim($_POST['brand']);
        $model     = trim($_POST['model']);
        $cat_id    = intval($_POST['category_id']) ?: null;
        $cond      = trim($_POST['appliance_condition']);
        $stock     = intval($_POST['stock']);
        $min_stock = intval($_POST['min_stock']);
        $zone_id   = intval($_POST['zone_id']) ?: null;
        $shelf_id  = intval($_POST['shelf_id']) ?: null;
        $desc      = trim($_POST['description']);

        $edit_image_name = trim($_POST['existing_image'] ?? '');

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
            SET name = ?, brand = ?, model = ?, category_id = ?, appliance_condition = ?, stock = ?, min_stock = ?, zone_id = ?, shelf_id = ?, description = ?, image = ? 
            WHERE appliance_id = ?
        ");
        $stmt->bind_param('sssssiiiissi', $name, $brand, $model, $cat_id, $cond, $stock, $min_stock, $zone_id, $shelf_id, $desc, $edit_image_name, $id);
        if ($stmt->execute()) {
            $log_desc = "Updated appliance: $name (ID: $id)";
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'updated', ?)");
            $log->bind_param('is', $uid, $log_desc);
            $log->execute();
            $success = "Appliance updated successfully.";
        } else {
            $error = "Failed to update appliance attributes.";
        }
        $stmt->close();
    }

    // ── DELETE APPLIANCE ──
    if ($action === 'delete') {
        $id = intval($_POST['appliance_id']);
        $stmt = $conn->prepare("DELETE FROM appliances WHERE appliance_id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $success = "Appliance deleted successfully.";
        } else {
            $error = "Failed to delete appliance.";
        }
        $stmt->close();
    }
}

// ── GET FILTERS ──
$filter_search = trim($_GET['search'] ?? '');
$filter_cat    = intval($_GET['category_id'] ?? 0);
$filter_cond   = trim($_GET['appliance_condition'] ?? '');
$filter_zone   = intval($_GET['zone_id'] ?? 0);

$where = ["1=1"];
if ($filter_search !== '') {
    $where[] = "(a.name LIKE '%" . $conn->real_escape_string($filter_search) . "%' OR a.sku LIKE '%" . $conn->real_escape_string($filter_search) . "%')";
}
if ($filter_cat > 0)   $where[] = "a.category_id = $filter_cat";
if ($filter_cond !== '') $where[] = "a.appliance_condition = '" . $conn->real_escape_string($filter_cond) . "'";
if ($filter_zone > 0)  $where[] = "a.zone_id = $filter_zone";

$where_clause = implode(" AND ", $where);

// ── QUERIES FOR DATA VIEW ──
$appliances_res = $conn->query("
    SELECT a.*, c.category_name, z.zone_name, s.shelf_name 
    FROM appliances a
    LEFT JOIN categories c ON a.category_id = c.category_id
    LEFT JOIN zones z ON a.zone_id = z.zone_id
    LEFT JOIN shelves s ON a.shelf_id = s.shelf_id
    WHERE $where_clause
    ORDER BY a.sku DESC
");
$appliances = [];
while ($row = $appliances_res->fetch_assoc()) {
    $appliances[] = $row;
}

// ── CRITICAL FIX: FORCE LOAD TARGET APPLIANCE IF REDIRECTING TO EDIT ──
$targeted_appliance_json = 'null';
if (isset($_GET['edit']) && isset($_GET['id'])) {
    $target_id = intval($_GET['id']);
    $target_stmt = $conn->prepare("
        SELECT a.*, c.category_name, z.zone_name, s.shelf_name 
        FROM appliances a
        LEFT JOIN categories c ON a.category_id = c.category_id
        LEFT JOIN zones z ON a.zone_id = z.zone_id
        LEFT JOIN shelves s ON a.shelf_id = s.shelf_id
        WHERE a.appliance_id = ? LIMIT 1
    ");
    $target_stmt->bind_param('i', $target_id);
    $target_stmt->execute();
    $target_row = $target_stmt->get_result()->fetch_assoc();
    if ($target_row) {
        $targeted_appliance_json = json_encode($target_row);
    }
    $target_stmt->close();
}

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
$zones      = $conn->query("SELECT * FROM zones ORDER BY zone_name");
$shelves    = $conn->query("SELECT * FROM shelves ORDER BY shelf_name");

$shelves_by_zone = [];
while ($sh = $shelves->fetch_assoc()) {
    $shelves_by_zone[$sh['zone_id']][] = $sh;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InvenTech — Appliances Inventory</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<?php include __DIR__ . '/includes/styles.php'; ?>
<style>
  .modal-overlay {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(15, 23, 42, 0.6) !important;
    backdrop-filter: blur(4px) !important;
    display: none;
    align-items: center !important;
    justify-content: center !important;
    z-index: 99999 !important;
  }
  .modal-overlay.open {
    display: flex !important;
  }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
  <?php include __DIR__ . '/includes/topbar.php'; ?>

  <div class="content">
    <div class="section-header">
      <div>
        <div class="section-title">📦 Appliances Inventory</div>
        <div class="section-sub">Manage corporate hardware components, physical stock placements, and conditions</div>
      </div>
      <button class="btn btn-primary" onclick="openAddModal()">＋ Add Appliance</button>
    </div>

    <?php if ($success !== ''): ?>
      <div class="alert alert-success alert-msg" style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert-danger alert-msg" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:16px; padding:16px;">
      <form method="GET" action="appliances.php" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
        <input type="text" name="search" class="form-ctrl" style="width:240px;" placeholder="Search SKU or Name..." value="<?= htmlspecialchars($filter_search) ?>">
        
        <select name="category_id" class="form-ctrl" style="width:180px;">
          <option value="">All Categories</option>
          <?php $categories->data_seek(0); while($c = $categories->fetch_assoc()): ?>
            <option value="<?= $c['category_id'] ?>" <?= $filter_cat == $c['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['category_name']) ?></option>
          <?php endwhile; ?>
        </select>

        <select name="appliance_condition" class="form-ctrl" style="width:160px;">
          <option value="">All Conditions</option>
          <option value="brand_new" <?= $filter_cond === 'brand_new' ? 'selected' : '' ?>>Brand New</option>
          <option value="good" <?= $filter_cond === 'good' ? 'selected' : '' ?>>Good</option>
          <option value="fair" <?= $filter_cond === 'fair' ? 'selected' : '' ?>>Fair</option>
          <option value="for_repair" <?= $filter_cond === 'for_repair' ? 'selected' : '' ?>>For Repair</option>
          <option value="defective" <?= $filter_cond === 'defective' ? 'selected' : '' ?>>Defective</option>
        </select>

        <button type="submit" class="btn btn-primary">🔍 Filter</button>
        <a href="appliances.php" class="btn btn-secondary">Clear</a>
      </form>
    </div>

    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Image</th>
              <th>SKU</th>
              <th>Appliance Name</th>
              <th>Category</th>
              <th>Location</th>
              <th>Stock</th>
              <th>Condition</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($appliances)): ?>
              <tr><td colspan="8"><div class="empty-state"><div class="icon">📦</div><div class="title">No appliances active</div></div></td></tr>
            <?php else: ?>
              <?php foreach ($appliances as $ap): ?>
              <tr>
                <td>
                  <?php if (!empty($ap['image']) && file_exists('uploads/appliances/' . $ap['image'])): ?>
                    <img src="uploads/appliances/<?= htmlspecialchars($ap['image']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;cursor:pointer" onclick="openPhotoViewer('uploads/appliances/<?= htmlspecialchars($ap['image']) ?>', '<?= htmlspecialchars($ap['name']) ?>', '<?= htmlspecialchars($ap['sku']) ?>')">
                  <?php else: ?>
                    <div style="width:40px;height:40px;background:#f1f5f9;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:16px">📺</div>
                  <?php endif; ?>
                </td>
                <td style="font-family:monospace;font-weight:700;color:#64748b"><?= htmlspecialchars($ap['sku']) ?></td>
                <td>
                  <div style="font-weight:600;color:#1e293b"><?= htmlspecialchars($ap['name']) ?></div>
                  <div style="font-size:11px;color:#94a3b8"><?= htmlspecialchars($ap['brand'] ?? '') ?> <?= htmlspecialchars($ap['model'] ?? '') ?></div>
                </td>
                <td><span class="pill pill-blue"><?= htmlspecialchars($ap['category_name'] ?? 'Unassigned') ?></span></td>
                <td>
                  <div style="font-size:12px;font-weight:600;color:#334155">📍 <?= htmlspecialchars($ap['zone_name'] ?? 'No Zone') ?></div>
                  <div style="font-size:11px;color:#94a3b8">📌 <?= htmlspecialchars($ap['shelf_name'] ?? 'No Shelf') ?></div>
                </td>
                <td>
                  <strong style="color:<?= $ap['stock'] <= $ap['min_stock'] ? '#ef4444' : '#1e293b' ?>"><?= $ap['stock'] ?></strong>
                  <?php if ($ap['stock'] <= $ap['min_stock']): ?>
                    <span class="pill pill-red" style="font-size:10px;padding:2px 6px;margin-left:4px">Low</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                    $cond = $ap['appliance_condition'];
                    $pill_class = 'pill-gray';
                    if ($cond === 'brand_new' || $cond === 'good') $pill_class = 'pill-green';
                    if ($cond === 'fair') $pill_class = 'pill-yellow';
                    if ($cond === 'for_repair') $pill_class = 'pill-orange';
                    if ($cond === 'defective') $pill_class = 'pill-red';
                  ?>
                  <span class="pill <?= $pill_class ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $cond))) ?></span>
                </td>
                <td>
                  <div style="display:flex; gap:8px;">
                    <button class="btn btn-secondary btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($ap)) ?>)">✏️ Edit</button>
                    <?php if ($role === 'admin'): ?>
                    <form method="POST" action="appliances.php" id="del-form-<?= $ap['appliance_id'] ?>" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this appliance row?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="appliance_id" value="<?= $ap['appliance_id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="appliance-modal">
  <div class="modal" style="width:540px; background:#fff; padding:24px; border-radius:12px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1)">
    <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <div class="modal-title" id="modal-title" style="font-size:18px; font-weight:700;">📦 Add New Appliance</div>
      <button class="modal-close" onclick="closeApplianceModal()" style="background:none; border:none; font-size:18px; cursor:pointer;">✕</button>
    </div>
    <form method="POST" action="appliances.php" enctype="multipart/form-data">
      <input type="hidden" name="action" id="form-action" value="add">
      <input type="hidden" name="appliance_id" id="form-appliance-id" value="">
      <input type="hidden" name="existing_image" id="form-existing-image" value="">

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
        <div class="form-group">
          <label class="form-label">Appliance Name *</label>
          <input class="form-ctrl" type="text" name="name" id="form-name" required>
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select class="form-ctrl" name="category_id" id="form-category">
            <option value="">Unassigned</option>
            <?php $categories->data_seek(0); while($c = $categories->fetch_assoc()) { ?>
              <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
            <?php } ?>
          </select>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
        <div class="form-group">
          <label class="form-label">Brand</label>
          <input class="form-ctrl" type="text" name="brand" id="form-brand">
        </div>
        <div class="form-group">
          <label class="form-label">Model</label>
          <input class="form-ctrl" type="text" name="model" id="form-model">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
        <div class="form-group">
          <label class="form-label">Zone Location</label>
          <select class="form-ctrl" name="zone_id" id="add-zone-select" onchange="filterShelves('add-zone-select', 'add-shelf-select')">
            <option value="">No Zone Assigned</option>
            <?php $zones->data_seek(0); while($z = $zones->fetch_assoc()) { ?>
              <option value="<?= $z['zone_id'] ?>"><?= htmlspecialchars($z['zone_name']) ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Shelf Location</label>
          <select class="form-ctrl" name="shelf_id" id="add-shelf-select">
            <option value="">No Shelf Assigned</option>
          </select>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:12px;">
        <div class="form-group">
          <label class="form-label">Initial Stock</label>
          <input class="form-ctrl" type="number" name="stock" id="form-stock" value="0" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Min Stock Threshold</label>
          <input class="form-ctrl" type="number" name="min_stock" id="form-min-stock" value="5" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Condition</label>
          <select class="form-ctrl" name="appliance_condition" id="form-condition">
            <option value="brand_new">Brand New</option>
            <option value="good">Good</option>
            <option value="fair">Fair</option>
            <option value="for_repair">For Repair</option>
            <option value="defective">Defective</option>
          </select>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label">Description / Tracking Notes</label>
        <textarea class="form-ctrl" name="description" id="form-description" style="height:60px; resize:none"></textarea>
      </div>

      <div class="form-group" style="margin-bottom:16px;">
        <label class="form-label">Appliance Display Image</label>
        <input type="file" name="image" class="form-ctrl" accept="image/*">
      </div>

      <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" class="btn btn-secondary" onclick="closeApplianceModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="form-submit-btn">Save Appliance</button>
      </div>
    </form>
  </div>
</div>

<div id="photo-viewer" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,.9); z-index:99999; justify-content:center; align-items:center; flex-direction:column; opacity:0" onclick="closePhotoViewer()">
  <div style="position:relative; max-width:85vw; max-height:75vh" onclick="event.stopPropagation()">
    <button style="position:absolute; top:-40px; right:0; background:none; border:none; color:#fff; font-size:24px; cursor:pointer" onclick="closePhotoViewer()">✕</button>
    <img id="photo-viewer-img" src="" style="max-width:100%; max-height:75vh; border-radius:14px; box-shadow:0 24px 80px rgba(0,0,0,.6); display:block;">
  </div>
  <div style="margin-top:16px; text-align:center; pointer-events:none" onclick="event.stopPropagation()">
    <div id="photo-viewer-name" style="font-size:16px; font-weight:700; color:#fff; margin-bottom:4px"></div>
    <div id="photo-viewer-sku" style="font-size:12px; color:rgba(255,255,255,.5); font-family:monospace"></div>
  </div>
</div>

<script>
const SHELVES_DATA = <?= json_encode($shelves_by_zone) ?>;

function filterShelves(zoneSelectId, shelfSelectId, selectedShelfId = null) {
  const zoneSelect = document.getElementById(zoneSelectId);
  const shelfSelect = document.getElementById(shelfSelectId);
  if (!zoneSelect || !shelfSelect) return;
  
  const zoneId = zoneSelect.value;
  shelfSelect.innerHTML = '<option value="">No Shelf Assigned</option>';
  
  if (zoneId && SHELVES_DATA[zoneId]) {
    SHELVES_DATA[zoneId].forEach(s => {
      const opt = document.createElement('option');
      opt.value = s.shelf_id;
      opt.textContent = s.shelf_name;
      if (selectedShelfId && s.shelf_id == selectedShelfId) {
        opt.selected = true;
      }
      shelfSelect.appendChild(opt);
    });
  }
}

function openAddModal() {
  document.getElementById('modal-title').textContent = '📦 Add New Appliance';
  document.getElementById('form-action').value = 'add';
  document.getElementById('form-appliance-id').value = '';
  document.getElementById('form-existing-image').value = '';
  
  document.getElementById('form-name').value = '';
  document.getElementById('form-brand').value = '';
  document.getElementById('form-model').value = '';
  document.getElementById('form-category').value = '';
  document.getElementById('add-zone-select').value = '';
  document.getElementById('form-condition').value = 'brand_new';
  document.getElementById('form-stock').value = '0';
  document.getElementById('form-stock').disabled = false;
  document.getElementById('form-min-stock').value = '5';
  document.getElementById('form-description').value = '';

  filterShelves('add-zone-select', 'add-shelf-select');
  
  const modalEl = document.getElementById('appliance-modal');
  if (modalEl) {
    modalEl.style.display = 'flex';
    modalEl.classList.add('open');
  }
}

function openEditModal(ap) {
  if (!ap) return;
  document.getElementById('modal-title').textContent = '✏️ Edit Appliance: ' + ap.sku;
  document.getElementById('form-action').value = 'edit';
  document.getElementById('form-appliance-id').value = ap.appliance_id;
  document.getElementById('form-existing-image').value = ap.image || '';

  document.getElementById('form-name').value = ap.name || '';
  document.getElementById('form-brand').value = ap.brand || '';
  document.getElementById('form-model').value = ap.model || '';
  document.getElementById('form-category').value = ap.category_id || '';
  document.getElementById('add-zone-select').value = ap.zone_id || '';
  document.getElementById('form-condition').value = ap.appliance_condition || 'brand_new';
  document.getElementById('form-stock').value = ap.stock || '0';
  document.getElementById('form-stock').disabled = false;
  document.getElementById('form-min-stock').value = ap.min_stock || '5';
  document.getElementById('form-description').value = ap.description || '';

  filterShelves('add-zone-select', 'add-shelf-select', ap.shelf_id);
  
  const modalEl = document.getElementById('appliance-modal');
  if (modalEl) {
    modalEl.style.display = 'flex';
    modalEl.classList.add('open');
  }
}

function closeApplianceModal() {
  const modalEl = document.getElementById('appliance-modal');
  if (modalEl) {
    modalEl.style.display = 'none';
    modalEl.classList.remove('open');
  }
}

function openPhotoViewer(src, name, sku) {
  document.getElementById('photo-viewer-img').src  = src;
  document.getElementById('photo-viewer-name').textContent = name;
  document.getElementById('photo-viewer-sku').textContent  = sku;
  const viewer = document.getElementById('photo-viewer');
  viewer.style.display = 'flex';
  document.body.style.overflow = 'hidden';
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

// ── FIXED CROSS-PAGE INTERCEPTOR LISTENERS ──
document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  
  // 1. Check for Add Parameter
  if (urlParams.has('add')) {
    openAddModal();
    if (urlParams.has('zone_id')) {
      const targetZone = urlParams.get('zone_id');
      const zoneSelect = document.getElementById('add-zone-select');
      if (zoneSelect) {
        zoneSelect.value = targetZone;
        filterShelves('add-zone-select', 'add-shelf-select');
      }
    }
  }

  // 2. Check for Edit Parameter (Guaranteed fallback lookup)
  if (urlParams.has('edit') && urlParams.has('id')) {
    const targetId = urlParams.get('id');
    
    // First lookup: Try the current filtered viewing table array
    const appliancesList = <?= json_encode($appliances) ?>;
    let itemToEdit = appliancesList.find(item => item.appliance_id == targetId);
    
    // Backup lookup: Use the forced server data row if the target is out of current page filter limits
    if (!itemToEdit) {
        itemToEdit = <?= $targeted_appliance_json ?>;
    }
    
    if (itemToEdit) {
      openEditModal(itemToEdit);
    }
  }

  // Auto dismiss alert messages
  setTimeout(() => {
    document.querySelectorAll('.alert-msg').forEach(el => {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    });
  }, 4000);
});
</script>
</body>
</html>