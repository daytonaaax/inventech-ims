<?php

//  InvenTech — Topbar
//  File: includes/topbar.php

// Apply role-based theme to body
$theme_class = ($_SESSION['role'] === 'admin') ? 'theme-admin' : 'theme-staff';
echo '<script>document.body.classList.add("' . $theme_class . '")</script>';
?>
<?php

$pages = [
    'dashboard'    => ['title' => 'Dashboard',       'section' => 'Overview'],
    'appliances'   => ['title' => 'Appliances',       'section' => 'Inventory'],
    'categories'   => ['title' => 'Categories',       'section' => 'Inventory'],
    'zones'        => ['title' => 'Zones & Shelves',  'section' => 'Inventory'],
    'transactions' => ['title' => 'Transactions',     'section' => 'Records'],
    'logs'         => ['title' => 'Activity Logs',    'section' => 'Records'],
    'alerts'       => ['title' => 'Alerts',           'section' => 'Monitoring'],
    'reports'      => ['title' => 'Reports',          'section' => 'Monitoring'],
    'settings'     => ['title' => 'Settings',         'section' => 'System'],
];

$current   = basename($_SERVER['PHP_SELF'], '.php');
$page_info = $pages[$current] ?? ['title' => 'InvenTech', 'section' => ''];

$name_parts = explode(',', $_SESSION['full_name']);
$surname    = trim($name_parts[0] ?? '');
$firstname  = trim($name_parts[1] ?? '');
$initials   = strtoupper(substr($firstname, 0, 1) . substr($surname, 0, 1));
$display    = $firstname . ' ' . $surname;

$unread_alerts = $conn->query("SELECT COUNT(*) AS cnt FROM alerts WHERE is_read = 0")->fetch_assoc()['cnt'];
?>

<div class="topbar">
  <div class="topbar-left">
    <div class="page-breadcrumb">InvenTech / <?= $page_info['section'] ?></div>
    <div class="page-name"><?= $page_info['title'] ?></div>
  </div>
  <div class="topbar-right">
    <a href="alerts.php" class="topbar-btn" title="Alerts">
      🔔
      <?php if ($unread_alerts > 0): ?>
        <span class="notif-dot"></span>
      <?php endif; ?>
    </a>
    <!-- Profile Dropdown -->
    <div class="profile-wrap" id="profile-wrap">
      <div class="topbar-user" onclick="toggleProfile()" style="cursor:pointer">
        <div class="topbar-avatar"><?= $initials ?></div>
        <div class="topbar-uname"><?= htmlspecialchars($display) ?></div>
        <span class="role-badge <?= $_SESSION['role'] ?>"><?= ucfirst($_SESSION['role']) ?></span>
        <span style="font-size:10px;color:var(--gray-400);margin-left:2px">&#9662;</span>
      </div>

      <div class="profile-dropdown" id="profile-dropdown">
        <div class="profile-dropdown-header">
          <div class="profile-dropdown-avatar"><?= $initials ?></div>
          <div>
            <div class="profile-dropdown-name"><?= htmlspecialchars($display) ?></div>
            <div class="profile-dropdown-role">@<?= htmlspecialchars($_SESSION['username']) ?> &middot; <?= ucfirst($_SESSION['role']) ?></div>
          </div>
        </div>
        <div class="profile-dropdown-divider"></div>
        <a href="settings.php?tab=security" class="profile-dropdown-item"><span>&#128274;</span> Change Password</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="settings.php?tab=users" class="profile-dropdown-item"><span>&#128101;</span> Manage Users</a>
        <?php endif; ?>
        <a href="logs.php" class="profile-dropdown-item"><span>&#128221;</span> View Activity Logs</a>
        <div class="profile-dropdown-divider"></div>
        <a href="logout.php" class="profile-dropdown-item profile-dropdown-logout"
          onclick="event.preventDefault(); document.getElementById('profile-dropdown').classList.remove('open'); confirmAction('Sign Out','Are you sure you want to sign out of InvenTech?','🚪','form-signout','Sign Out','btn-danger')">
          <span>&#128682;</span> Sign Out
        </a>
      </div>
    </div>
  </div>
</div>

<style>
  .profile-wrap { position: relative; }
  .profile-dropdown {
    display: none; position: absolute; top: calc(100% + 10px); right: 0;
    width: 240px; background: var(--white); border: 1.5px solid var(--gray-200);
    border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); z-index: 999;
    overflow: hidden; animation: fadeUp .15s ease;
  }
  .profile-dropdown.open { display: block; }
  .profile-dropdown-header {
    display: flex; align-items: center; gap: 12px; padding: 16px;
    background: linear-gradient(120deg, var(--blue-900), var(--blue-600));
  }
  .profile-dropdown-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: rgba(255,255,255,.2); border: 2px solid rgba(255,255,255,.3);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 800; color: var(--white); flex-shrink: 0;
  }
  .profile-dropdown-name { font-size: 13px; font-weight: 700; color: var(--white); }
  .profile-dropdown-role { font-size: 11px; color: rgba(255,255,255,.6); margin-top: 2px; }
  .profile-dropdown-divider { height: 1px; background: var(--gray-100); margin: 4px 0; }
  .profile-dropdown-item {
    display: flex; align-items: center; gap: 10px; padding: 11px 16px;
    font-size: 13px; font-weight: 500; color: var(--gray-700); transition: all .15s;
  }
  .profile-dropdown-item:hover { background: var(--blue-50); color: var(--blue-600); }
  .profile-dropdown-item span { font-size: 15px; width: 20px; text-align: center; }
  .profile-dropdown-logout { color: var(--red) !important; }
  .profile-dropdown-logout:hover { background: var(--red-bg) !important; }
</style>

<script>
  function toggleProfile() {
    document.getElementById('profile-dropdown').classList.toggle('open');
  }
  document.addEventListener('click', function(e) {
    const wrap = document.getElementById('profile-wrap');
    if (wrap && !wrap.contains(e.target)) {
      document.getElementById('profile-dropdown').classList.remove('open');
    }
  });
</script>
