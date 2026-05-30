<?php

//  InvenTech — Reports
//  File: reports.php

session_start();
date_default_timezone_set('Asia/Manila');
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role = $_SESSION['role'];

// ── REPORT SELECTION ─────────────────────────────────────────
$report_type = trim($_GET['report_type'] ?? '');
$date_from   = trim($_GET['date_from']   ?? date('Y-m-01'));
$date_to     = trim($_GET['date_to']     ?? date('Y-m-d'));
$active_report_type = $report_type === '' ? 'stock_summary' : $report_type;
$date_range_reports = ['transactions', 'user_activity'];
$show_date_range = in_array($active_report_type, $date_range_reports, true);

// ── SUMMARY STATS (always shown) ─────────────────────────────
$total_units     = $conn->query("SELECT COALESCE(SUM(stock),0) AS t FROM appliances WHERE status='active'")->fetch_assoc()['t'];
$total_types     = $conn->query("SELECT COUNT(*) AS t FROM appliances WHERE status='active'")->fetch_assoc()['t'];
$low_stock_count = $conn->query("SELECT COUNT(*) AS t FROM appliances WHERE stock <= min_stock AND status='active'")->fetch_assoc()['t'];
$defective_count = $conn->query("SELECT COUNT(*) AS t FROM appliances WHERE appliance_condition IN ('defective','for_repair') AND status='active'")->fetch_assoc()['t'];
$stmt_in = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS t FROM transactions WHERE transaction_type='stock_in' AND DATE(transaction_date) BETWEEN ? AND ?");
$stmt_in->bind_param('ss', $date_from, $date_to);
$stmt_in->execute();
$tx_in_total = $stmt_in->get_result()->fetch_assoc()['t'];
$stmt_in->close();
$stmt_out = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS t FROM transactions WHERE transaction_type='stock_out' AND DATE(transaction_date) BETWEEN ? AND ?");
$stmt_out->bind_param('ss', $date_from, $date_to);
$stmt_out->execute();
$tx_out_total = $stmt_out->get_result()->fetch_assoc()['t'];
$stmt_out->close();

// ── REPORT DATA ───────────────────────────────────────────────

// 1. Stock Summary — uses the vw_inventory_summary VIEW
// The VIEW (created in inventech_advanced.sql) joins 4 tables
// and pre-calculates stock status, transaction counts, etc.
$stock_summary = null;
if ($report_type === 'stock_summary' || $report_type === '') {
    $stock_summary = $conn->query("
        SELECT *
        FROM vw_inventory_summary
        ORDER BY stock ASC, appliance_name ASC
    ");
}

// 2. Transaction History
$tx_history = null;
if ($report_type === 'transactions') {
    $tx_stmt = $conn->prepare("
      SELECT t.reference_no, a.name AS appliance_name, a.sku,
           t.transaction_type, t.quantity, u.full_name AS handled_by,
           t.notes, t.transaction_date
      FROM transactions t
      JOIN appliances a ON t.appliance_id = a.appliance_id
      JOIN users      u ON t.handled_by   = u.user_id
      WHERE DATE(t.transaction_date) BETWEEN ? AND ?
      ORDER BY t.transaction_date DESC
    ");
    $tx_stmt->bind_param('ss', $date_from, $date_to);
    $tx_stmt->execute();
    $tx_history = $tx_stmt->get_result();
    $tx_stmt->close();
}

// 3. Defective Units
$defective_list = null;
if ($report_type === 'defective') {
    $defective_list = $conn->query("
        SELECT a.sku, a.name, a.brand, a.appliance_condition,
               a.stock, c.category_name, z.zone_name, s.shelf_name
        FROM appliances a
        LEFT JOIN categories c ON a.category_id = c.category_id
        LEFT JOIN zones      z ON a.zone_id      = z.zone_id
        LEFT JOIN shelves    s ON a.shelf_id      = s.shelf_id
        WHERE a.appliance_condition IN ('defective','for_repair')
          AND a.status = 'active'
        ORDER BY a.appliance_condition, a.name
    ");
}

// 4. Low Stock
$low_stock_list = null;
if ($report_type === 'low_stock') {
    $low_stock_list = $conn->query("
        SELECT a.sku, a.name, a.brand, a.stock, a.min_stock,
               c.category_name, z.zone_name,
               (a.min_stock - a.stock) AS units_needed
        FROM appliances a
        LEFT JOIN categories c ON a.category_id = c.category_id
        LEFT JOIN zones      z ON a.zone_id      = z.zone_id
        WHERE a.stock <= a.min_stock AND a.status = 'active'
        ORDER BY a.stock ASC
    ");
}

// 5. Zone & Shelf Report
$zone_report = null;
if ($report_type === 'zone_shelf') {
    $zone_report = $conn->query("
        SELECT z.zone_name, s.shelf_name, s.description AS shelf_desc,
               COUNT(a.appliance_id) AS item_types,
               COALESCE(SUM(a.stock),0) AS total_stock
        FROM zones z
        LEFT JOIN shelves    s ON z.zone_id  = s.zone_id
        LEFT JOIN appliances a ON s.shelf_id = a.shelf_id AND a.status='active'
        GROUP BY z.zone_id, s.shelf_id
        ORDER BY z.zone_name, s.shelf_name
    ");
}

// 6. User Activity
$user_activity = null;
if ($report_type === 'user_activity' && $role === 'admin') {
    $ua_stmt = $conn->prepare("
        SELECT u.full_name,
               COUNT(l.log_id) AS total_actions,
               SUM(CASE WHEN l.action='added'   THEN 1 ELSE 0 END) AS added,
               SUM(CASE WHEN l.action='edited'  THEN 1 ELSE 0 END) AS edited,
               SUM(CASE WHEN l.action='deleted' THEN 1 ELSE 0 END) AS deleted,
               SUM(CASE WHEN l.action='login'   THEN 1 ELSE 0 END) AS logins,
               MAX(l.logged_at) AS last_active
        FROM users u
        LEFT JOIN activity_logs l ON u.user_id = l.user_id
          AND DATE(l.logged_at) BETWEEN ? AND ?
        WHERE u.status = 'active'
        GROUP BY u.user_id
        ORDER BY total_actions DESC
    ");
    $ua_stmt->bind_param('ss', $date_from, $date_to);
    $ua_stmt->execute();
    $user_activity = $ua_stmt->get_result();
    $ua_stmt->close();
}

$cond_labels = [
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

$report_meta = [
    'stock_summary' => [
        'title' => 'Stock Summary Report',
        'desc'  => 'Current inventory snapshot showing appliance stock levels, condition, category, and storage location.',
    ],
    'transactions' => [
        'title' => 'Transaction History Report',
        'desc'  => 'Stock movement ledger for all stock-in and stock-out activity within the selected period.',
    ],
    'defective' => [
        'title' => 'Defective Units Report',
        'desc'  => 'Inventory exceptions requiring inspection, repair, replacement, or removal from active circulation.',
    ],
    'low_stock' => [
        'title' => 'Low Stock Report',
        'desc'  => 'Reorder watchlist for appliances at or below their configured minimum stock threshold.',
    ],
    'zone_shelf' => [
        'title' => 'Zone and Shelf Report',
        'desc'  => 'Storage location summary grouped by zone and shelf for warehouse visibility.',
    ],
    'user_activity' => [
        'title' => 'User Activity Report',
        'desc'  => 'Staff activity summary for inventory actions and logins within the selected period.',
    ],
];

$active_report_meta = $report_meta[$active_report_type] ?? $report_meta['stock_summary'];
$generated_at = date('F j, Y g:ia');
$date_from_label = date('M j, Y', strtotime($date_from));
$date_to_label = date('M j, Y', strtotime($date_to));
$period_label = $show_date_range ? $date_from_label . ' to ' . $date_to_label : 'Current inventory snapshot';
$prepared_by = $_SESSION['full_name'] ?? 'InvenTech User';

$print_record_count = 0;
if ($active_report_type === 'stock_summary' && $stock_summary) {
    $print_record_count = $stock_summary->num_rows;
} elseif ($active_report_type === 'transactions' && $tx_history) {
    $print_record_count = $tx_history->num_rows;
} elseif ($active_report_type === 'defective' && $defective_list) {
    $print_record_count = $defective_list->num_rows;
} elseif ($active_report_type === 'low_stock' && $low_stock_list) {
    $print_record_count = $low_stock_list->num_rows;
} elseif ($active_report_type === 'zone_shelf' && $zone_report) {
    $print_record_count = $zone_report->num_rows;
} elseif ($active_report_type === 'user_activity' && $user_activity) {
    $print_record_count = $user_activity->num_rows;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InvenTech — Reports</title>
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
        <div class="section-title">📊 Reports</div>
        <div class="section-sub">Generate and view summaries of your inventory data</div>
      </div>
      <button class="btn btn-secondary" onclick="window.print()">🖨️ Print Report</button>
    </div>

    <!-- Summary Stats -->
    <div class="stat-grid stat-grid-4" style="margin-bottom:24px">
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--blue-500)"></div>
        <div class="stat-icon-wrap" style="background:var(--blue-50)">📦</div>
        <div class="stat-label">Total Units in Stock</div>
        <div class="stat-value" style="color:var(--blue-600)"><?= $total_units ?></div>
        <div class="stat-change neutral"><?= $total_types ?> appliance types</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--yellow)"></div>
        <div class="stat-icon-wrap" style="background:var(--yellow-bg)">⚠️</div>
        <div class="stat-label">Low Stock Items</div>
        <div class="stat-value" style="color:var(--yellow)"><?= $low_stock_count ?></div>
        <div class="stat-change <?= $low_stock_count > 0 ? 'down' : 'up' ?>"><?= $low_stock_count > 0 ? 'Need restocking' : 'All sufficient' ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--green)"></div>
        <div class="stat-icon-wrap" style="background:var(--green-bg)">📥</div>
        <div class="stat-label">Stock In (Period)</div>
        <div class="stat-value" style="color:var(--green)">+<?= $tx_in_total ?></div>
        <div class="stat-change neutral"><?= $date_from ?> to <?= $date_to ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--red)"></div>
        <div class="stat-icon-wrap" style="background:var(--red-bg)">📤</div>
        <div class="stat-label">Stock Out (Period)</div>
        <div class="stat-value" style="color:var(--red)">-<?= $tx_out_total ?></div>
        <div class="stat-change neutral"><?= $date_from ?> to <?= $date_to ?></div>
      </div>
    </div>

    <!-- Report Type Cards -->
    <div class="report-grid" style="margin-bottom:24px">
      <a href="?report_type=stock_summary" class="report-card <?= $report_type === 'stock_summary' || $report_type === '' ? 'active-report' : '' ?>">
        <div class="report-card-icon">📦</div>
        <div class="report-card-name">Stock Summary</div>
        <div class="report-card-desc">All appliances with current stock levels and conditions</div>
      </a>
      <a href="?report_type=transactions&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="report-card <?= $report_type === 'transactions' ? 'active-report' : '' ?>">
        <div class="report-card-icon">🔄</div>
        <div class="report-card-name">Transaction History</div>
        <div class="report-card-desc">All stock-in and stock-out records for the selected period</div>
      </a>
      <a href="?report_type=defective" class="report-card <?= $report_type === 'defective' ? 'active-report' : '' ?>">
        <div class="report-card-icon">🔧</div>
        <div class="report-card-name">Defective Units</div>
        <div class="report-card-desc">All units marked as For Repair or Defective</div>
      </a>
      <a href="?report_type=low_stock" class="report-card <?= $report_type === 'low_stock' ? 'active-report' : '' ?>">
        <div class="report-card-icon">⚠️</div>
        <div class="report-card-name">Low Stock</div>
        <div class="report-card-desc">Items at or below minimum stock threshold</div>
      </a>
      <a href="?report_type=zone_shelf" class="report-card <?= $report_type === 'zone_shelf' ? 'active-report' : '' ?>">
        <div class="report-card-icon">🗂️</div>
        <div class="report-card-name">Zone & Shelf</div>
        <div class="report-card-desc">Inventory summary per zone and shelf location</div>
      </a>
      <?php if ($role === 'admin'): ?>
      <a href="?report_type=user_activity&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" class="report-card <?= $report_type === 'user_activity' ? 'active-report' : '' ?>">
        <div class="report-card-icon">👤</div>
        <div class="report-card-name">User Activity</div>
        <div class="report-card-desc">Summary of actions performed by each staff member</div>
      </a>
      <?php endif; ?>
    </div>

    <?php if ($show_date_range): ?>
    <!-- Date Range Filter -->
    <form method="GET" action="reports.php">
      <input type="hidden" name="report_type" value="<?= htmlspecialchars($report_type) ?>">
      <div class="card" style="margin-bottom:20px">
        <div class="card-header" style="margin-bottom:14px">
          <div>
            <div class="card-title">Date Range Filter</div>
            <div style="font-size:12px;color:var(--gray-400);margin-top:4px">
              Applies to <?= $active_report_type === 'transactions' ? 'Transaction History' : 'User Activity' ?>
            </div>
          </div>
        </div>
        <div style="display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap">
          <div>
            <label class="form-label">Date From</label>
            <input type="date" class="form-ctrl" name="date_from" value="<?= $date_from ?>" style="width:160px">
          </div>
          <div>
            <label class="form-label">Date To</label>
            <input type="date" class="form-ctrl" name="date_to" value="<?= $date_to ?>" style="width:160px">
          </div>
          <button type="submit" class="btn btn-primary">Apply Date Range</button>
        </div>
      </div>
    </form>
    <?php endif; ?>

    <!-- ══ REPORT OUTPUT ══ -->

    <div class="print-report-cover">
      <div class="print-report-topline">
        <div>
          <div class="print-brand">InvenTech Inventory Management</div>
          <div class="print-title"><?= htmlspecialchars($active_report_meta['title']) ?></div>
          <div class="print-desc"><?= htmlspecialchars($active_report_meta['desc']) ?></div>
        </div>
        <div class="print-badge">Official Inventory Report</div>
      </div>
      <div class="print-report-summary">
        <div>
          <span>Report Period</span>
          <strong><?= htmlspecialchars($period_label) ?></strong>
        </div>
        <div>
          <span>Generated</span>
          <strong><?= $generated_at ?></strong>
        </div>
        <div>
          <span>Prepared By</span>
          <strong><?= htmlspecialchars($prepared_by) ?></strong>
        </div>
        <div>
          <span>Records</span>
          <strong><?= $print_record_count ?></strong>
        </div>
      </div>
      <div class="print-kpi-row">
        <div>
          <span>Total Units</span>
          <strong><?= $total_units ?></strong>
        </div>
        <div>
          <span>Appliance Types</span>
          <strong><?= $total_types ?></strong>
        </div>
        <div>
          <span>Low Stock</span>
          <strong><?= $low_stock_count ?></strong>
        </div>
        <div>
          <span>Defective / Repair</span>
          <strong><?= $defective_count ?></strong>
        </div>
      </div>
    </div>

    <?php if ($stock_summary && ($report_type === 'stock_summary' || $report_type === '')): ?>
    <div class="card" id="report-output">
      <div class="card-header">
        <div class="card-title">📦 Stock Summary Report</div>
        <span style="font-size:12px;color:var(--gray-400)">Generated: <?= date('F j, Y g:ia') ?></span>
      </div>
      <div class="table-container">
        <table>
          <thead>
            <tr><th>SKU</th><th>Name</th><th>Brand</th><th>Category</th><th>Condition</th><th>Stock</th><th>Min Stock</th><th>Status</th><th>Location</th></tr>
          </thead>
          <tbody>
            <?php if ($stock_summary->num_rows > 0):
              while ($r = $stock_summary->fetch_assoc()):
                $pill = $cond_pills[$r['appliance_condition']] ?? 'pill-gray';
                $lbl  = $cond_labels[$r['appliance_condition']] ?? $r['appliance_condition'];
            ?>
            <tr>
              <td class="text-muted font-bold"><?= htmlspecialchars($r['sku']) ?></td>
              <td class="td-main"><?= htmlspecialchars($r['appliance_name']) ?></td>
              <td><?= htmlspecialchars($r['brand'] ?? '—') ?></td>
              <td><span class="pill pill-blue"><?= htmlspecialchars($r['category_name'] ?? '—') ?></span></td>
              <td><span class="pill <?= $pill ?>"><?= $lbl ?></span></td>
              <td><strong style="color:<?= $r['stock'] <= $r['min_stock'] ? 'var(--red)' : 'var(--gray-800)' ?>"><?= $r['stock'] ?></strong></td>
              <td class="text-muted"><?= $r['min_stock'] ?></td>
              <td><span class="pill <?= $r['stock_status'] === 'Low' ? 'pill-red' : 'pill-green' ?>"><?= $r['stock_status'] ?></span></td>
              <td class="text-muted"><?= htmlspecialchars($r['zone_name'] ?? '—') ?><?= $r['shelf_name'] ? ' / '.$r['shelf_name'] : '' ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="9"><div class="empty-state"><div class="icon">📦</div><div class="title">No appliances found</div></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($tx_history): ?>
    <div class="card" id="report-output">
      <div class="card-header">
        <div class="card-title">🔄 Transaction History — <?= $date_from ?> to <?= $date_to ?></div>
        <span style="font-size:12px;color:var(--gray-400)">Generated: <?= date('F j, Y g:ia') ?></span>
      </div>
      <div class="table-container">
        <table>
          <thead><tr><th>Ref #</th><th>Appliance</th><th>Type</th><th>Qty</th><th>Handled By</th><th>Notes</th><th>Date</th></tr></thead>
          <tbody>
            <?php if ($tx_history->num_rows > 0):
              while ($r = $tx_history->fetch_assoc()):
            ?>
            <tr>
              <td class="text-muted font-bold"><?= htmlspecialchars($r['reference_no']) ?></td>
              <td class="td-main"><?= htmlspecialchars($r['appliance_name']) ?></td>
              <td><span class="pill <?= $r['transaction_type'] === 'stock_in' ? 'pill-green' : 'pill-red' ?>"><?= $r['transaction_type'] === 'stock_in' ? '📥 Stock In' : '📤 Stock Out' ?></span></td>
              <td><strong style="color:<?= $r['transaction_type'] === 'stock_in' ? 'var(--green)' : 'var(--red)' ?>"><?= $r['transaction_type'] === 'stock_in' ? '+' : '-' ?><?= $r['quantity'] ?></strong></td>
              <td><?= htmlspecialchars($r['handled_by']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($r['notes'] ?: '—') ?></td>
              <td class="text-muted"><?= date('M j, Y g:ia', strtotime($r['transaction_date'])) ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="7"><div class="empty-state"><div class="icon">🔄</div><div class="title">No transactions in this period</div></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($defective_list): ?>
    <div class="card" id="report-output">
      <div class="card-header">
        <div class="card-title">🔧 Defective Units Report</div>
        <span style="font-size:12px;color:var(--gray-400)">Generated: <?= date('F j, Y g:ia') ?></span>
      </div>
      <div class="table-container">
        <table>
          <thead><tr><th>SKU</th><th>Name</th><th>Brand</th><th>Category</th><th>Condition</th><th>Stock</th><th>Location</th></tr></thead>
          <tbody>
            <?php if ($defective_list->num_rows > 0):
              while ($r = $defective_list->fetch_assoc()):
                $pill = $cond_pills[$r['appliance_condition']] ?? 'pill-gray';
                $lbl  = $cond_labels[$r['appliance_condition']] ?? $r['appliance_condition'];
            ?>
            <tr>
              <td class="text-muted font-bold"><?= htmlspecialchars($r['sku']) ?></td>
              <td class="td-main"><?= htmlspecialchars($r['name']) ?></td>
              <td><?= htmlspecialchars($r['brand'] ?? '—') ?></td>
              <td><span class="pill pill-blue"><?= htmlspecialchars($r['category_name'] ?? '—') ?></span></td>
              <td><span class="pill <?= $pill ?>"><?= $lbl ?></span></td>
              <td><strong><?= $r['stock'] ?></strong></td>
              <td class="text-muted"><?= htmlspecialchars($r['zone_name'] ?? '—') ?><?= $r['shelf_name'] ? ' / '.$r['shelf_name'] : '' ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="7"><div class="empty-state"><div class="icon">✅</div><div class="title">No defective units</div></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($low_stock_list): ?>
    <div class="card" id="report-output">
      <div class="card-header">
        <div class="card-title">⚠️ Low Stock Report</div>
        <span style="font-size:12px;color:var(--gray-400)">Generated: <?= date('F j, Y g:ia') ?></span>
      </div>
      <div class="table-container">
        <table>
          <thead><tr><th>SKU</th><th>Name</th><th>Brand</th><th>Category</th><th>Current Stock</th><th>Min Stock</th><th>Units Needed</th><th>Zone</th></tr></thead>
          <tbody>
            <?php if ($low_stock_list->num_rows > 0):
              while ($r = $low_stock_list->fetch_assoc()):
            ?>
            <tr>
              <td class="text-muted font-bold"><?= htmlspecialchars($r['sku']) ?></td>
              <td class="td-main"><?= htmlspecialchars($r['name']) ?></td>
              <td><?= htmlspecialchars($r['brand'] ?? '—') ?></td>
              <td><span class="pill pill-blue"><?= htmlspecialchars($r['category_name'] ?? '—') ?></span></td>
              <td><strong style="color:var(--red)"><?= $r['stock'] ?></strong></td>
              <td class="text-muted"><?= $r['min_stock'] ?></td>
              <td><span class="pill pill-red"><?= $r['units_needed'] ?> needed</span></td>
              <td class="text-muted"><?= htmlspecialchars($r['zone_name'] ?? '—') ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="8"><div class="empty-state"><div class="icon">✅</div><div class="title">No low stock items</div></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($zone_report): ?>
    <div class="card" id="report-output">
      <div class="card-header">
        <div class="card-title">🗂️ Zone & Shelf Report</div>
        <span style="font-size:12px;color:var(--gray-400)">Generated: <?= date('F j, Y g:ia') ?></span>
      </div>
      <div class="table-container">
        <table>
          <thead><tr><th>Zone</th><th>Shelf</th><th>Description</th><th>Item Types</th><th>Total Units</th></tr></thead>
          <tbody>
            <?php if ($zone_report->num_rows > 0):
              while ($r = $zone_report->fetch_assoc()):
            ?>
            <tr>
              <td class="td-main"><?= htmlspecialchars($r['zone_name']) ?></td>
              <td><?= htmlspecialchars($r['shelf_name'] ?? '—') ?></td>
              <td class="text-muted"><?= htmlspecialchars($r['shelf_desc'] ?? '—') ?></td>
              <td><?= $r['item_types'] ?></td>
              <td><strong style="color:var(--blue-600)"><?= $r['total_stock'] ?></strong></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="5"><div class="empty-state"><div class="icon">🗂️</div><div class="title">No zones found</div></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($user_activity): ?>
    <div class="card" id="report-output">
      <div class="card-header">
        <div class="card-title">👤 User Activity Report — <?= $date_from ?> to <?= $date_to ?></div>
        <span style="font-size:12px;color:var(--gray-400)">Generated: <?= date('F j, Y g:ia') ?></span>
      </div>
      <div class="table-container">
        <table>
          <thead><tr><th>Staff Member</th><th>Total Actions</th><th>Added</th><th>Edited</th><th>Deleted</th><th>Logins</th><th>Last Active</th></tr></thead>
          <tbody>
            <?php while ($r = $user_activity->fetch_assoc()): ?>
            <tr>
              <td class="td-main"><?= htmlspecialchars($r['full_name']) ?></td>
              <td><strong style="color:var(--blue-600)"><?= $r['total_actions'] ?></strong></td>
              <td><span class="pill pill-green"><?= $r['added'] ?></span></td>
              <td><span class="pill pill-blue"><?= $r['edited'] ?></span></td>
              <td><span class="pill pill-red"><?= $r['deleted'] ?></span></td>
              <td><span class="pill pill-purple"><?= $r['logins'] ?></span></td>
              <td class="text-muted"><?= $r['last_active'] ? date('M j, Y g:ia', strtotime($r['last_active'])) : 'No activity' ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <div class="print-signoff">
      <div class="print-signoff-note">
        This report is prepared for inventory review and confirmation. Please verify the listed records before filing or releasing.
      </div>
      <div class="print-signoff-grid">
        <div class="print-signoff-box">
          <div class="print-signature-line"></div>
          <strong>Prepared By</strong>
          <span><?= htmlspecialchars($prepared_by) ?></span>
          <small>Date: ____________________</small>
        </div>
        <div class="print-signoff-box">
          <div class="print-signature-line"></div>
          <strong>Checked By</strong>
          <span>Inventory Custodian</span>
          <small>Date: ____________________</small>
        </div>
        <div class="print-signoff-box">
          <div class="print-signature-line"></div>
          <strong>Approved By</strong>
          <span>Authorized Officer</span>
          <small>Date: ____________________</small>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<style>
  .active-report { border-color: var(--blue-400) !important; background: var(--blue-50) !important; }
  .active-report .report-card-name { color: var(--blue-600); }
  .print-report-cover { display: none; }
  .print-signoff { display: none; }
  @media print {
    :root {
      --print-ink: #111827;
      --print-muted: #64748b;
      --print-line: #94a3b8;
      --print-soft: #f6f8fb;
      --print-accent: #621db0;
      --print-border: 1.5px solid var(--print-line);
    }
    .sidebar, .topbar, .report-grid, form, .stat-grid, .section-header { display: none !important; }
    .main { margin-left: 0 !important; padding: 0 !important; display: block !important; }
    .content { padding: 0 !important; animation: none !important; }
    body { background: white !important; color: var(--print-ink) !important; }
    .print-report-cover {
      display: block !important;
      padding-bottom: 12px;
      margin-bottom: 12px;
      border-bottom: var(--print-border);
      break-inside: avoid;
    }
    .print-report-topline {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 18px;
      margin-bottom: 10px;
    }
    .print-brand {
      color: var(--print-accent);
      font-size: 11px;
      font-weight: 800;
      letter-spacing: .9px;
      margin-bottom: 4px;
      text-transform: uppercase;
    }
    .print-title {
      color: var(--print-ink);
      font-size: 21px;
      font-weight: 800;
      line-height: 1.15;
      margin-bottom: 4px;
    }
    .print-desc {
      color: var(--print-muted);
      font-size: 10.5px;
      line-height: 1.35;
      max-width: 600px;
    }
    .print-badge {
      border: var(--print-border);
      border-radius: 10px;
      color: var(--print-accent);
      font-size: 9px;
      font-weight: 800;
      letter-spacing: .5px;
      padding: 6px 9px;
      text-transform: uppercase;
      white-space: nowrap;
    }
    .print-report-summary,
    .print-kpi-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 7px;
      margin-top: 7px;
    }
    .print-report-summary div,
    .print-kpi-row div {
      border: var(--print-border);
      border-radius: 10px;
      background: var(--print-soft);
      padding: 7px 10px;
      min-height: 42px;
    }
    .print-report-summary span,
    .print-kpi-row span {
      display: block;
      color: var(--print-muted);
      font-size: 8.5px;
      font-weight: 800;
      letter-spacing: .5px;
      margin-bottom: 3px;
      text-transform: uppercase;
    }
    .print-report-summary strong,
    .print-kpi-row strong {
      color: var(--print-ink);
      display: block;
      font-size: 11.5px;
      font-weight: 800;
      line-height: 1.25;
    }
    .card {
      box-shadow: none !important;
      border: 0 !important;
      border-radius: 0 !important;
      padding: 0 !important;
    }
    .card-header { display: none !important; }
    .table-container {
      overflow: visible !important;
      overflow-x: visible !important;
      border: var(--print-border);
      border-radius: 10px;
      padding: 0 !important;
    }
    table {
      width: 100% !important;
      table-layout: fixed !important;
      border-collapse: separate !important;
      border-spacing: 0 !important;
      font-size: 10.5px !important;
    }
    thead { display: table-header-group; }
    tr { break-inside: avoid; }
    thead th {
      background: #eef2f7 !important;
      border-bottom: var(--print-border) !important;
      color: #334155 !important;
      font-size: 9px !important;
      letter-spacing: .45px !important;
    }
    thead th:first-child { border-top-left-radius: 9px; }
    thead th:last-child { border-top-right-radius: 9px; }
    tbody tr:last-child td:first-child { border-bottom-left-radius: 9px; }
    tbody tr:last-child td:last-child { border-bottom-right-radius: 9px; }
    thead th, tbody td { 
      padding: 6px 8px !important; 
      border-right: var(--print-border) !important;
      word-wrap: break-word !important;
      overflow-wrap: break-word !important;
      white-space: normal !important;
    }
    thead th:last-child, tbody td:last-child { border-right: 0 !important; }
    tbody td {
      border-bottom: var(--print-border) !important;
      color: #1f2937 !important;
      font-size: 10px !important;
    }
    tbody tr:last-child td { border-bottom: 0 !important; }
    tbody tr:nth-child(even) td { background: #fbfcfe !important; }
    tbody tr:hover { background: transparent !important; }
    .text-muted { color: #64748b !important; font-size: 9.5px !important; }
    .td-main { color: #111827 !important; font-weight: 800 !important; }
    .pill {
      background: #eef2f7 !important;
      border: var(--print-border) !important;
      color: #1f2937 !important;
      font-size: 9px !important;
      padding: 2px 6px !important;
      border-radius: 999px !important;
    }
    .print-signoff {
      display: block !important;
      margin-top: 28px;
      padding-top: 14px;
      border-top: var(--print-border);
      break-inside: avoid;
    }
    .print-signoff-note {
      color: var(--print-muted);
      font-size: 10px;
      line-height: 1.4;
      margin-bottom: 22px;
    }
    .print-signoff-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
    }
    .print-signoff-box {
      min-height: 92px;
      padding: 28px 0 10px;
      text-align: left;
    }
    .print-signature-line {
      border-top: var(--print-border);
      margin-bottom: 8px;
    }
    .print-signoff-box strong {
      color: var(--print-ink);
      display: block;
      font-size: 10px;
      letter-spacing: .45px;
      margin-bottom: 3px;
      text-transform: uppercase;
    }
    .print-signoff-box span,
    .print-signoff-box small {
      color: var(--print-muted);
      display: block;
      font-size: 9.5px;
      line-height: 1.4;
    }
    @page { margin: 1.2cm; size: A4 landscape; }
  }
</style>

</body>
</html>
