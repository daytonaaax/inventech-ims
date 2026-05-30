<?php

//  InvenTech — Dashboard
//  File: dashboard.php

session_start();
require_once 'db_config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ── FETCH STATS ─────────────────────────────────────────────

// Total appliances (active only)
$total = $conn->query("SELECT COUNT(*) AS cnt FROM appliances WHERE status = 'active'")->fetch_assoc()['cnt'];

// Good condition count (brand_new + good)
$good = $conn->query("SELECT COUNT(*) AS cnt FROM appliances WHERE appliance_condition IN ('brand_new','good') AND status = 'active'")->fetch_assoc()['cnt'];

// Low stock count (stock <= min_stock)
$low_stock = $conn->query("SELECT COUNT(*) AS cnt FROM appliances WHERE stock <= min_stock AND status = 'active'")->fetch_assoc()['cnt'];

// Defective / for repair count
$defective = $conn->query("SELECT COUNT(*) AS cnt FROM appliances WHERE appliance_condition IN ('for_repair','defective') AND status = 'active'")->fetch_assoc()['cnt'];

// Unread alerts count
$alert_count = $conn->query("SELECT COUNT(*) AS cnt FROM alerts WHERE is_read = 0")->fetch_assoc()['cnt'];

// ── RECENT TRANSACTIONS (last 5) ────────────────────────────
$recent_tx = $conn->query("
    SELECT t.reference_no, a.name AS appliance_name,
           t.transaction_type, t.quantity, t.transaction_date,
           u.full_name AS handled_by
    FROM transactions t
    JOIN appliances a ON t.appliance_id = a.appliance_id
    JOIN users u      ON t.handled_by   = u.user_id
    ORDER BY t.transaction_date DESC
    LIMIT 5
");

// ── ACTIVE ALERTS (last 4 unread) ───────────────────────────
$active_alerts = $conn->query("
    SELECT * FROM alerts
    WHERE is_read = 0
    ORDER BY created_at DESC
    LIMIT 4
");

// ── CONDITION BREAKDOWN for donut chart ─────────────────────
$cond_new    = $conn->query("SELECT COUNT(*) AS cnt FROM appliances WHERE appliance_condition = 'brand_new'  AND status='active'")->fetch_assoc()['cnt'];
$cond_good   = $conn->query("SELECT COUNT(*) AS cnt FROM appliances WHERE appliance_condition = 'good'       AND status='active'")->fetch_assoc()['cnt'];
$cond_fair   = $conn->query("SELECT COUNT(*) AS cnt FROM appliances WHERE appliance_condition = 'fair'       AND status='active'")->fetch_assoc()['cnt'];
$cond_repair = $conn->query("SELECT COUNT(*) AS cnt FROM appliances WHERE appliance_condition = 'for_repair' AND status='active'")->fetch_assoc()['cnt'];
$cond_defect = $conn->query("SELECT COUNT(*) AS cnt FROM appliances WHERE appliance_condition = 'defective'  AND status='active'")->fetch_assoc()['cnt'];
$good_total  = $cond_new + $cond_good;
$warn_total  = $cond_fair + $cond_repair;

// ── STOCK MOVEMENT THIS WEEK (last 7 days) ──────────────────
$week_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date  = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime("-$i days")); // Mon, Tue...

    $stmt_in = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS total FROM transactions WHERE transaction_type='stock_in' AND DATE(transaction_date)=?");
    $stmt_in->bind_param('s', $date);
    $stmt_in->execute();
    $in = $stmt_in->get_result()->fetch_assoc()['total'];
    $stmt_in->close();

    $stmt_out = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS total FROM transactions WHERE transaction_type='stock_out' AND DATE(transaction_date)=?");
    $stmt_out->bind_param('s', $date);
    $stmt_out->execute();
    $out = $stmt_out->get_result()->fetch_assoc()['total'];
    $stmt_out->close();

    $week_data[] = ['label' => $label, 'in' => (int)$in, 'out' => (int)$out];
}

// Get max value for bar chart scaling
$max_val = 1;
foreach ($week_data as $d) {
    $max_val = max($max_val, $d['in'], $d['out']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InvenTech — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
<?php include __DIR__ . '/includes/styles.php'; ?>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
  <?php include __DIR__ . '/includes/topbar.php'; ?>

  <div class="content">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
      <div class="welcome-text">
        <div class="greeting">☀️ Good day, <?= htmlspecialchars($_SESSION['role'] === 'admin' ? 'Administrator' : 'Staff') ?></div>
        <div class="name">Here's your <span>inventory overview</span> for today.</div>
        <div class="sub"><?= date('l, F j, Y') ?> — All systems operational</div>
      </div>
      <div class="welcome-stats">
        <div class="welcome-stat"><div class="val"><?= $total ?></div><div class="lbl">Total Units</div></div>
        <div class="welcome-stat"><div class="val"><?= $low_stock ?></div><div class="lbl">Low Stock</div></div>
        <div class="welcome-stat"><div class="val"><?= $alert_count ?></div><div class="lbl">Alerts</div></div>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid stat-grid-4">
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--blue-500)"></div>
        <div class="stat-icon-wrap" style="background:var(--blue-50)">📦</div>
        <div class="stat-label">Total Appliances</div>
        <div class="stat-value" style="color:var(--blue-600)"><?= $total ?></div>
        <div class="stat-change neutral">Active inventory records</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--green)"></div>
        <div class="stat-icon-wrap" style="background:var(--green-bg)">✅</div>
        <div class="stat-label">Good Condition</div>
        <div class="stat-value" style="color:var(--green)"><?= $good ?></div>
        <div class="stat-change neutral"><?= $total > 0 ? round(($good/$total)*100) : 0 ?>% of total inventory</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--yellow)"></div>
        <div class="stat-icon-wrap" style="background:var(--yellow-bg)">⚠️</div>
        <div class="stat-label">Low Stock Items</div>
        <div class="stat-value" style="color:var(--yellow)"><?= $low_stock ?></div>
        <div class="stat-change <?= $low_stock > 0 ? 'down' : 'up' ?>"><?= $low_stock > 0 ? 'Needs restocking' : 'All stocks sufficient' ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:var(--red)"></div>
        <div class="stat-icon-wrap" style="background:var(--red-bg)">🔧</div>
        <div class="stat-label">For Repair / Defective</div>
        <div class="stat-value" style="color:var(--red)"><?= $defective ?></div>
        <div class="stat-change <?= $defective > 0 ? 'down' : 'up' ?>"><?= $defective > 0 ? 'Needs attention' : 'No defective units' ?></div>
      </div>
    </div>

    <!-- Charts Row -->
    <div class="dashboard-grid">

      <!-- Bar Chart -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">📈 Stock Movement — Last 7 Days</div>
          <a href="transactions.php" class="card-action">View All →</a>
        </div>
        <div class="bar-chart-wrap">
          <?php foreach ($week_data as $d):
            $h_in  = $max_val > 0 ? round(($d['in']  / $max_val) * 100) : 0;
            $h_out = $max_val > 0 ? round(($d['out'] / $max_val) * 100) : 0;
          ?>
          <div class="bar-col">
            <div style="display:flex;gap:3px;align-items:flex-end;height:100px;width:100%">
              <div class="bar-fill" style="flex:1;height:<?= max($h_in,4) ?>px;background:var(--blue-500)" title="In: <?= $d['in'] ?>"></div>
              <div class="bar-fill" style="flex:1;height:<?= max($h_out,4) ?>px;background:var(--blue-200)" title="Out: <?= $d['out'] ?>"></div>
            </div>
            <div class="bar-day"><?= $d['label'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="chart-legend">
          <div class="chart-legend-item"><div class="chart-legend-dot" style="background:var(--blue-500)"></div>Stock In</div>
          <div class="chart-legend-item"><div class="chart-legend-dot" style="background:var(--blue-200)"></div>Stock Out</div>
        </div>
      </div>

      <!-- Donut Chart -->
      <div class="card">
        <div class="card-header"><div class="card-title">🥧 Condition Breakdown</div></div>
        <?php
          $circumference = 2 * M_PI * 38; // r=38
          $pct_good   = $total > 0 ? ($good_total  / $total) : 0;
          $pct_warn   = $total > 0 ? ($warn_total  / $total) : 0;
          $pct_defect = $total > 0 ? ($cond_defect / $total) : 0;
          $dash_good   = round($pct_good   * $circumference, 1);
          $dash_warn   = round($pct_warn   * $circumference, 1);
          $dash_defect = round($pct_defect * $circumference, 1);
          $gap = $circumference;
          $offset_warn   = -$dash_good;
          $offset_defect = -($dash_good + $dash_warn);
        ?>
        <div class="donut-section">
          <div class="donut-chart">
            <svg width="100" height="100" viewBox="0 0 100 100" style="transform:rotate(-90deg)">
              <circle cx="50" cy="50" r="38" fill="none" stroke="#f1f5f9" stroke-width="14"/>
              <circle cx="50" cy="50" r="38" fill="none" stroke="var(--green)"  stroke-width="14" stroke-dasharray="<?= $dash_good ?> <?= $gap ?>"/>
              <circle cx="50" cy="50" r="38" fill="none" stroke="var(--yellow)" stroke-width="14" stroke-dasharray="<?= $dash_warn ?> <?= $gap ?>" stroke-dashoffset="<?= $offset_warn ?>"/>
              <circle cx="50" cy="50" r="38" fill="none" stroke="var(--red)"    stroke-width="14" stroke-dasharray="<?= $dash_defect ?> <?= $gap ?>" stroke-dashoffset="<?= $offset_defect ?>"/>
            </svg>
            <div class="donut-center"><div class="donut-val"><?= $total ?></div><div class="donut-lbl">Total</div></div>
          </div>
          <div class="donut-legend">
            <div class="donut-legend-item">
              <div class="donut-legend-left"><div class="donut-legend-dot" style="background:var(--green)"></div>Good / New</div>
              <div class="donut-legend-count"><?= $good_total ?></div>
            </div>
            <div class="donut-legend-item">
              <div class="donut-legend-left"><div class="donut-legend-dot" style="background:var(--yellow)"></div>Fair / Repair</div>
              <div class="donut-legend-count"><?= $warn_total ?></div>
            </div>
            <div class="donut-legend-item">
              <div class="donut-legend-left"><div class="donut-legend-dot" style="background:var(--red)"></div>Defective</div>
              <div class="donut-legend-count"><?= $cond_defect ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Transactions + Active Alerts -->
    <div class="dashboard-grid-3">
      <div class="card">
        <div class="card-header">
          <div class="card-title">🔄 Recent Transactions</div>
          <a href="transactions.php" class="card-action">See All →</a>
        </div>
        <?php if ($recent_tx->num_rows > 0): ?>
        <div class="table-container">
          <table>
            <thead><tr><th>Appliance</th><th>Type</th><th>Qty</th><th>Date</th></tr></thead>
            <tbody>
              <?php while ($tx = $recent_tx->fetch_assoc()): ?>
              <tr>
                <td class="td-main"><?= htmlspecialchars($tx['appliance_name']) ?></td>
                <td>
                  <?php if ($tx['transaction_type'] === 'stock_in'): ?>
                    <span class="pill pill-green">Stock In</span>
                  <?php else: ?>
                    <span class="pill pill-red">Stock Out</span>
                  <?php endif; ?>
                </td>
                <td><strong><?= $tx['transaction_type'] === 'stock_in' ? '+' : '-' ?><?= $tx['quantity'] ?></strong></td>
                <td class="text-muted"><?= date('M j, g:ia', strtotime($tx['transaction_date'])) ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div class="empty-state"><div class="icon">🔄</div><div class="title">No transactions yet</div></div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-title">🔔 Active Alerts</div>
          <a href="alerts.php" class="card-action">View All →</a>
        </div>
        <div class="activity-feed">
          <?php if ($active_alerts->num_rows > 0):
            while ($al = $active_alerts->fetch_assoc()):
              $dot_color = $al['alert_type'] === 'critical' ? 'var(--red)' : ($al['alert_type'] === 'warning' ? 'var(--yellow)' : 'var(--blue-400)');
          ?>
          <div class="activity-item">
            <div class="activity-indicator" style="background:<?= $dot_color ?>"></div>
            <div class="activity-body">
              <div class="activity-text"><?= htmlspecialchars($al['title']) ?></div>
              <div class="activity-time"><?= date('M j, g:ia', strtotime($al['created_at'])) ?> · <?= ucfirst($al['alert_type']) ?></div>
            </div>
          </div>
          <?php endwhile; else: ?>
          <div class="empty-state"><div class="icon">✅</div><div class="title">No active alerts</div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

</body>
</html>
