<?php

//  InvenTech — Categories
//  File: categories.php

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

    // ── ADD CATEGORY ──
    if ($action === 'add' && $role === 'admin') {
        $name = trim($_POST['category_name']);
        $desc = trim($_POST['description']);

        if (empty($name)) {
            $error = "Category name is required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $name, $desc);
            if ($stmt->execute()) {
                $log_desc = "Added new category: $name";
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'added', ?)");
                $log->bind_param('is', $uid, $log_desc);
                $log->execute();
                $success = "Category '$name' added successfully.";
            } else {
                $error = "Failed to add category. Name may already exist.";
            }
            $stmt->close();
        }
    }

    // ── EDIT CATEGORY ──
    if ($action === 'edit' && $role === 'admin') {
        $id   = intval($_POST['category_id']);
        $name = trim($_POST['category_name']);
        $desc = trim($_POST['description']);

        $stmt = $conn->prepare("UPDATE categories SET category_name = ?, description = ? WHERE category_id = ?");
        $stmt->bind_param('ssi', $name, $desc, $id);
        if ($stmt->execute()) {
            $log_desc = "Edited category: $name";
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'edited', ?)");
            $log->bind_param('is', $uid, $log_desc);
            $log->execute();
            $success = "Category updated successfully.";
        } else {
            $error = "Failed to update category.";
        }
        $stmt->close();
    }

    // ── DELETE CATEGORY ──
    if ($action === 'delete' && $role === 'admin') {
        $id  = intval($_POST['category_id']);
        $cr_stmt = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
        $cr_stmt->bind_param('i', $id);
        $cr_stmt->execute();
        $row = $cr_stmt->get_result()->fetch_assoc();
        $cr_stmt->close();

        // Check if any appliances use this category
        $iu_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM appliances WHERE category_id = ? AND status = 'active'");
        $iu_stmt->bind_param('i', $id);
        $iu_stmt->execute();
        $in_use = $iu_stmt->get_result()->fetch_assoc()['cnt'];
        $iu_stmt->close();

        if ($in_use > 0) {
            $error = "Cannot delete '{$row['category_name']}' — it is currently assigned to $in_use appliance(s). Reassign them first.";
        } else {
            $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $log_desc = "Deleted category: {$row['category_name']}";
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'deleted', ?)");
                $log->bind_param('is', $uid, $log_desc);
                $log->execute();
                $success = "Category deleted successfully.";
            } else {
                $error = "Failed to delete category.";
            }
            $stmt->close();
        }
    }
}

// ── FETCH CATEGORIES WITH APPLIANCE COUNT ────────────────────
$categories = $conn->query("
    SELECT c.*,
        COUNT(a.appliance_id) AS appliance_count
    FROM categories c
    LEFT JOIN appliances a ON c.category_id = a.category_id AND a.status = 'active'
    GROUP BY c.category_id
    ORDER BY c.category_name ASC
");

$cat_list = [];
while ($r = $categories->fetch_assoc()) $cat_list[] = $r;

// ── STATS ─────────────────────────────────────────────────────
$total_categories = count($cat_list);
$total_with_items = count(array_filter($cat_list, fn($c) => $c['appliance_count'] > 0));
$total_empty      = $total_categories - $total_with_items;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InvenTech — Categories</title>
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
        <div class="section-title">🏷️ Categories</div>
        <div class="section-sub">Manage appliance categories used across the inventory</div>
      </div>
      <?php if ($role === 'admin'): ?>
      <button class="btn btn-primary" onclick="openModal('add-modal')">＋ Add Category</button>
      <?php endif; ?>
    </div>

    <!-- Success / Error -->
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

    <!-- Stats -->
    <div class="stat-grid stat-grid-3" style="margin-bottom:24px">
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--blue-500)"></div>
        <div class="stat-icon-wrap" style="background:var(--blue-50)">🏷️</div>
        <div class="stat-label">Total Categories</div>
        <div class="stat-value" style="color:var(--blue-600)"><?= $total_categories ?></div>
        <div class="stat-change neutral">Across all appliances</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--green)"></div>
        <div class="stat-icon-wrap" style="background:var(--green-bg)">✅</div>
        <div class="stat-label">Active Categories</div>
        <div class="stat-value" style="color:var(--green)"><?= $total_with_items ?></div>
        <div class="stat-change neutral">Have appliances assigned</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--gray-400)"></div>
        <div class="stat-icon-wrap" style="background:var(--gray-100)">📭</div>
        <div class="stat-label">Empty Categories</div>
        <div class="stat-value" style="color:var(--gray-500)"><?= $total_empty ?></div>
        <div class="stat-change neutral">No appliances assigned</div>
      </div>
    </div>

    <!-- Categories Table -->
    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Category Name</th>
              <th>Description</th>
              <th>Appliances</th>
              <th>Date Added</th>
              <?php if ($role === 'admin'): ?>
              <th>Actions</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($cat_list)):
              $counter = 1;
              foreach ($cat_list as $c):
            ?>
            <tr>
              <td class="text-muted"><?= $counter++ ?></td>
              <td class="td-main"><?= htmlspecialchars($c['category_name']) ?></td>
              <td class="text-muted" style="max-width:300px;white-space:normal;line-height:1.5">
                <?= htmlspecialchars($c['description'] ?: '—') ?>
              </td>
              <td>
                <?php if ($c['appliance_count'] > 0): ?>
                  <span class="pill pill-blue"><?= $c['appliance_count'] ?> appliance<?= $c['appliance_count'] > 1 ? 's' : '' ?></span>
                <?php else: ?>
                  <span class="pill pill-gray">None</span>
                <?php endif; ?>
              </td>
              <td class="text-muted"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
              <?php if ($role === 'admin'): ?>
              <td>
                <div class="flex gap-8">
                  <button class="btn btn-secondary btn-sm"
                    onclick='openEdit(<?= json_encode($c) ?>)'>
                    ✏️ Edit
                  </button>
                  <form method="POST" id="form-delcat-<?= $c['category_id'] ?>">
                    <input type="hidden" name="action"      value="delete">
                    <input type="hidden" name="category_id" value="<?= $c['category_id'] ?>">
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmAction('Delete Category','This category will be permanently deleted. Make sure no appliances are using it first.','🏷️','form-delcat-<?= $c['category_id'] ?>','Delete','btn-danger')">🗑️</button>
                  </form>
                </div>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; else: ?>
            <tr>
              <td colspan="6">
                <div class="empty-state">
                  <div class="icon">🏷️</div>
                  <div class="title">No categories yet</div>
                  <div class="desc">Add your first category using the button above.</div>
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

<!-- ══ ADD MODAL ══ -->
<div class="modal-overlay" id="add-modal">
  <div class="modal" style="width:440px">
    <div class="modal-header">
      <div class="modal-title">🏷️ Add New Category</div>
      <button class="modal-close" onclick="closeModal('add-modal')">✕</button>
    </div>
    <form method="POST" action="categories.php">
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label class="form-label">Category Name *</label>
        <input class="form-ctrl" type="text" name="category_name"
          placeholder="e.g. Air Conditioner" required>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-ctrl" name="description" rows="3"
          placeholder="Brief description of this category..."
          style="resize:vertical"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Category</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT MODAL ══ -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal" style="width:440px">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Category</div>
      <button class="modal-close" onclick="closeModal('edit-modal')">✕</button>
    </div>
    <form method="POST" action="categories.php">
      <input type="hidden" name="action"      value="edit">
      <input type="hidden" name="category_id" id="edit-id">
      <div class="form-group">
        <label class="form-label">Category Name *</label>
        <input class="form-ctrl" type="text" name="category_name"
          id="edit-name" required>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-ctrl" name="description" id="edit-desc"
          rows="3" style="resize:vertical"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

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

  function openEdit(c) {
    document.getElementById('edit-id').value   = c.category_id;
    document.getElementById('edit-name').value = c.category_name;
    document.getElementById('edit-desc').value = c.description ?? '';
    openModal('edit-modal');
  }

  // Auto-dismiss messages
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
