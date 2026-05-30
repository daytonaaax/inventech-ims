<?php

//  InvenTech — Transactions
//  File: transactions.php

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
    $action       = $_POST['action'] ?? '';
    $appliance_id = intval($_POST['appliance_id']);
    $type         = trim($_POST['transaction_type']);
    $qty          = intval($_POST['quantity']);
    $notes        = trim($_POST['notes']);

    if ($action === 'log' && $appliance_id && $qty > 0) {

        // Generate reference number using a prepared statement
        $last_stmt = $conn->prepare("SELECT reference_no FROM transactions ORDER BY transaction_id DESC LIMIT 1");
        $last_stmt->execute();
        $last_result = $last_stmt->get_result()->fetch_assoc();
        $last_num = $last_result ? intval(substr($last_result['reference_no'], 2)) : 0;
        $ref_no   = 'T-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
        $last_stmt->close();

        // ── CALL STORED PROCEDURE ──────────────────────────────
        // sp_log_transaction handles everything inside a database
        // transaction with BEGIN/COMMIT/ROLLBACK automatically.
        // If any step fails, all changes are rolled back safely.
        $stmt = $conn->prepare("CALL sp_log_transaction(?, ?, ?, ?, ?, ?, @p_success, @p_message)");
        $stmt->bind_param('sisiis', $ref_no, $appliance_id, $type, $qty, $uid, $notes);
        $stmt->execute();
        $stmt->close();

        // Get the output parameters from the stored procedure
        $result = $conn->query("SELECT @p_success AS success, @p_message AS message")->fetch_assoc();

        if ($result['success'] == 1) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// ── FETCH STATS ──────────────────────────────────────────────
$stock_in_month  = $conn->query("SELECT COALESCE(SUM(quantity),0) AS total FROM transactions WHERE transaction_type='stock_in'  AND MONTH(transaction_date)=MONTH(NOW()) AND YEAR(transaction_date)=YEAR(NOW())")->fetch_assoc()['total'];
$stock_out_month = $conn->query("SELECT COALESCE(SUM(quantity),0) AS total FROM transactions WHERE transaction_type='stock_out' AND MONTH(transaction_date)=MONTH(NOW()) AND YEAR(transaction_date)=YEAR(NOW())")->fetch_assoc()['total'];
$net_change      = $stock_in_month - $stock_out_month;
$total_tx        = $conn->query("SELECT COUNT(*) AS cnt FROM transactions")->fetch_assoc()['cnt'];

// ── FETCH FILTERS ────────────────────────────────────────────
$filter_type = trim($_GET['transaction_type'] ?? '');
$filter_user = intval($_GET['handled_by'] ?? 0);
$search      = trim($_GET['search'] ?? '');

$where  = ['1=1'];
$params = [];
$types  = '';

if ($filter_type) { $where[] = "t.transaction_type = ?"; $params[] = $filter_type; $types .= 's'; }
if ($filter_user) { $where[] = "t.handled_by = ?";       $params[] = $filter_user; $types .= 'i'; }
if ($search)      { $where[] = "(a.name LIKE ? OR t.reference_no LIKE ?)";
                    $s = "%$search%"; $params[] = $s; $params[] = $s; $types .= 'ss'; }

$where_sql = implode(' AND ', $where);

$sql = "
    SELECT t.*, a.name AS appliance_name, a.sku,
           u.full_name AS handled_by_name
    FROM transactions t
    JOIN appliances a ON t.appliance_id = a.appliance_id
    JOIN users      u ON t.handled_by   = u.user_id
    WHERE $where_sql
    ORDER BY t.transaction_date DESC
";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $transactions = $stmt->get_result();
} else {
    $transactions = $conn->query($sql);
}

// ── DROPDOWNS ────────────────────────────────────────────────
$appliances_list = $conn->query("SELECT appliance_id, name, sku, stock, appliance_condition FROM appliances WHERE status='active' ORDER BY name");
$appl_list = [];
while ($r = $appliances_list->fetch_assoc()) $appl_list[] = $r;

$users_list = $conn->query("SELECT user_id, full_name FROM users WHERE status='active' ORDER BY full_name");
$user_list  = [];
while ($r = $users_list->fetch_assoc()) $user_list[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InvenTech — Transactions</title>
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
        <div class="section-title">🔄 Transactions</div>
        <div class="section-sub">Log stock in and stock out movements, view full transaction history</div>
      </div>
      <button class="btn btn-primary" onclick="openModal('tx-modal')">＋ Log Transaction</button>
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
    <div class="stat-grid stat-grid-4" style="margin-bottom:20px">
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--blue-500)"></div>
        <div class="stat-icon-wrap" style="background:var(--blue-50)">📋</div>
        <div class="stat-label">Total Transactions</div>
        <div class="stat-value" style="color:var(--blue-600)"><?= $total_tx ?></div>
        <div class="stat-change neutral">All time records</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--green)"></div>
        <div class="stat-icon-wrap" style="background:var(--green-bg)">📥</div>
        <div class="stat-label">Stock In — This Month</div>
        <div class="stat-value" style="color:var(--green)">+<?= $stock_in_month ?></div>
        <div class="stat-change up">Units received</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--red)"></div>
        <div class="stat-icon-wrap" style="background:var(--red-bg)">📤</div>
        <div class="stat-label">Stock Out — This Month</div>
        <div class="stat-value" style="color:var(--red)">-<?= $stock_out_month ?></div>
        <div class="stat-change down">Units released</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:<?= $net_change >= 0 ? 'var(--green)' : 'var(--red)' ?>"></div>
        <div class="stat-icon-wrap" style="background:<?= $net_change >= 0 ? 'var(--green-bg)' : 'var(--red-bg)' ?>">📊</div>
        <div class="stat-label">Net Change — This Month</div>
        <div class="stat-value" style="color:<?= $net_change >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= $net_change >= 0 ? '+' : '' ?><?= $net_change ?></div>
        <div class="stat-change <?= $net_change >= 0 ? 'up' : 'down' ?>"><?= $net_change >= 0 ? 'Positive this month' : 'More out than in' ?></div>
      </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="transactions.php">
      <div class="toolbar">
        <div class="search-wrap">
          <input class="search-input" type="text" name="search" placeholder="Search by appliance name or reference no..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <select class="filter-select" name="transaction_type">
          <option value="">All Types</option>
          <option value="stock_in"  <?= $filter_type === 'stock_in'  ? 'selected' : '' ?>>Stock In</option>
          <option value="stock_out" <?= $filter_type === 'stock_out' ? 'selected' : '' ?>>Stock Out</option>
        </select>
        <select class="filter-select" name="handled_by">
          <option value="">All Staff</option>
          <?php foreach ($user_list as $u): ?>
            <option value="<?= $u['user_id'] ?>" <?= $filter_user == $u['user_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary">🔍 Filter</button>
        <a href="transactions.php" class="btn btn-secondary">✕ Clear</a>
      </div>
    </form>

    <!-- Transactions Table -->
    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Ref #</th>
              <th>Appliance</th>
              <th>Type</th>
              <th>Qty</th>
              <th>Handled By</th>
              <th>Notes</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($transactions->num_rows > 0):
              while ($tx = $transactions->fetch_assoc()):
            ?>
            <tr>
              <td class="text-muted font-bold"><?= htmlspecialchars($tx['reference_no']) ?></td>
              <td>
                <div class="td-main"><?= htmlspecialchars($tx['appliance_name']) ?></div>
                <div style="font-size:11px;color:var(--gray-400)"><?= htmlspecialchars($tx['sku']) ?></div>
              </td>
              <td>
                <?php if ($tx['transaction_type'] === 'stock_in'): ?>
                  <span class="pill pill-green">📥 Stock In</span>
                <?php else: ?>
                  <span class="pill pill-red">📤 Stock Out</span>
                <?php endif; ?>
              </td>
              <td>
                <strong style="color:<?= $tx['transaction_type'] === 'stock_in' ? 'var(--green)' : 'var(--red)' ?>">
                  <?= $tx['transaction_type'] === 'stock_in' ? '+' : '-' ?><?= $tx['quantity'] ?>
                </strong>
              </td>
              <td style="font-size:13px"><?= htmlspecialchars($tx['handled_by_name']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($tx['notes'] ?: '—') ?></td>
              <td class="text-muted"><?= date('M j, Y g:ia', strtotime($tx['transaction_date'])) ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
              <td colspan="7">
                <div class="empty-state">
                  <div class="icon">🔄</div>
                  <div class="title">No transactions yet</div>
                  <div class="desc">Log your first stock movement using the button above.</div>
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

<!-- ══════════════════════════════════════
     LOG TRANSACTION MODAL
══════════════════════════════════════ -->
<div class="modal-overlay" id="tx-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">🔄 Log Transaction</div>
      <button class="modal-close" onclick="closeModal('tx-modal')">✕</button>
    </div>
    <form method="POST" action="transactions.php">
      <input type="hidden" name="action" value="log">

      <div class="form-group">
        <label class="form-label">Appliance *</label>
        <select class="form-ctrl" name="appliance_id" id="tx-appliance" onchange="updateStock(this)" required>
          <option value="">Select appliance</option>
          <?php
            $cond_labels = [
              'brand_new'  => 'Brand New',
              'good'       => 'Good',
              'fair'       => 'Fair',
              'for_repair' => 'For Repair',
              'defective'  => 'Defective',
            ];
            foreach ($appl_list as $a):
              $cond_label = $cond_labels[$a['appliance_condition']] ?? $a['appliance_condition'];
          ?>
            <option value="<?= $a['appliance_id'] ?>" data-stock="<?= $a['stock'] ?>">
              [<?= htmlspecialchars($a['sku']) ?>] <?= htmlspecialchars($a['name']) ?> — <?= $a['stock'] ?> in stock | <?= $cond_label ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Current stock indicator -->
      <div id="stock-indicator" style="display:none;background:var(--blue-50);border:1px solid var(--blue-200);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;">
        📦 Current stock: <strong id="current-stock">0</strong> units
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group">
          <label class="form-label">Transaction Type *</label>
          <select class="form-ctrl" name="transaction_type" required>
            <option value="stock_in">📥 Stock In</option>
            <option value="stock_out">📤 Stock Out</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Quantity *</label>
          <input class="form-ctrl" type="number" name="quantity" min="1" value="1" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea class="form-ctrl" name="notes" rows="2" placeholder="e.g. Delivery from supplier, Released to customer..." style="resize:vertical"></textarea>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('tx-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Log Transaction</button>
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

  // Show current stock when appliance is selected
  function updateStock(sel) {
    const opt       = sel.options[sel.selectedIndex];
    const stock     = opt.dataset.stock;
    const indicator = document.getElementById('stock-indicator');
    const display   = document.getElementById('current-stock');
    if (sel.value) {
      indicator.style.display = 'block';
      display.textContent     = stock;
      display.style.color     = stock <= 5 ? 'var(--red)' : 'var(--green)';
    } else {
      indicator.style.display = 'none';
    }
  }

  // Auto-dismiss only the alert messages (not stat cards)
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
