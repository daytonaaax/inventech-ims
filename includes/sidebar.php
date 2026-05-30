<?php

//  InvenTech — Sidebar
//  File: includes/sidebar.php
//  Included in every page automatically

// Figure out which page is active for nav highlighting
$current = basename($_SERVER['PHP_SELF'], '.php');

// Count unread alerts for the badge
$alert_badge = $conn->query("SELECT COUNT(*) AS cnt FROM alerts WHERE is_read = 0")->fetch_assoc()['cnt'];

$nav = [
    ['section' => 'Overview'],
    ['id' => 'dashboard',    'icon' => '🏠', 'label' => 'Dashboard'],
    ['section' => 'Inventory'],
    ['id' => 'appliances',   'icon' => '📋', 'label' => 'Appliances'],
    ['id' => 'categories',   'icon' => '🏷️', 'label' => 'Categories'],
    ['id' => 'zones',        'icon' => '🗂️', 'label' => 'Zones & Shelves'],
    ['section' => 'Records'],
    ['id' => 'transactions', 'icon' => '🔄', 'label' => 'Transactions'],
    ['id' => 'logs',         'icon' => '📝', 'label' => 'Activity Logs'],
    ['section' => 'Monitoring'],
    ['id' => 'alerts',       'icon' => '🔔', 'label' => 'Alerts', 'badge' => true],
    ['id' => 'reports',      'icon' => '📊', 'label' => 'Reports'],
    ['section' => 'System'],
    ['id' => 'settings',     'icon' => '⚙️', 'label' => 'Settings'],
];

// Get initials from full name
$name_parts = explode(',', $_SESSION['full_name']);
$surname    = trim($name_parts[0] ?? '');
$firstname  = trim($name_parts[1] ?? '');
$initials   = strtoupper(substr($firstname, 0, 1) . substr($surname, 0, 1));
?>

<aside class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">
      <div class="sidebar-logo-icon">📦</div>
      <div>
        <div class="sidebar-logo-text">InvenTech</div>
        <div class="sidebar-logo-sub">Appliance IMS v1.8.4</div>
      </div>
    </div>
  </div>

  <nav class="nav">
    <?php foreach ($nav as $item): ?>
      <?php if (isset($item['section'])): ?>
        <div class="nav-section-label"><?= $item['section'] ?></div>
      <?php else: ?>
        <a href="<?= $item['id'] ?>.php" class="nav-item <?= $current === $item['id'] ? 'active' : '' ?>">
          <span class="nav-icon"><?= $item['icon'] ?></span>
          <?= $item['label'] ?>
          <?php if (!empty($item['badge']) && $alert_badge > 0): ?>
            <span class="nav-badge"><?= $alert_badge ?></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= $initials ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($firstname . ' ' . $surname) ?></div>
        <div class="user-role"><?= ucfirst($_SESSION['role']) ?></div>
      </div>
    </div>
    <a href="logout.php" class="logout-btn" id="logout-btn"
      onclick="event.preventDefault(); confirmAction('Sign Out','Are you sure you want to sign out of InvenTech?','🚪','form-signout','Sign Out','btn-danger')">
      🚪 Sign Out
    </a>
    <form id="form-signout" action="logout.php" method="GET" style="display:none"></form>
  </div>

<!-- ══ GLOBAL CONFIRM MODAL ══════════════════════════════════
     Used across all pages to replace ugly browser confirm()
     Usage: confirmAction('Title','Description','icon',formId)
════════════════════════════════════════════════════════════ -->
<div class="confirm-overlay" id="global-confirm">
  <div class="confirm-box">
    <div class="confirm-icon" id="confirm-icon">⚠️</div>
    <div class="confirm-title" id="confirm-title">Are you sure?</div>
    <div class="confirm-desc"  id="confirm-desc">This action cannot be undone.</div>
    <div class="confirm-btns">
      <button class="btn btn-secondary" onclick="closeConfirm()">Cancel</button>
      <button class="btn btn-danger"    id="confirm-proceed-btn" onclick="proceedConfirm()">Yes, proceed</button>
    </div>
  </div>
</div>

<script>
  let _confirmForm = null;

  // Call this instead of onsubmit="return confirm(...)"
  // formId = the id of the form to submit on confirm
  function confirmAction(title, desc, icon, formId, btnLabel, btnClass) {
    _confirmForm = document.getElementById(formId);
    // Debug: warn if form not found
    if (!_confirmForm) {
      console.warn('InvenTech confirmAction: form not found with id:', formId);
    }
    document.getElementById('confirm-title').textContent   = title;
    document.getElementById('confirm-desc').textContent    = desc;
    document.getElementById('confirm-icon').textContent    = icon || '⚠️';
    const btn = document.getElementById('confirm-proceed-btn');
    btn.textContent  = btnLabel  || 'Yes, proceed';
    btn.className    = 'btn ' + (btnClass || 'btn-danger');
    document.getElementById('global-confirm').classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeConfirm() {
    document.getElementById('global-confirm').classList.remove('open');
    document.body.style.overflow = '';
    _confirmForm = null;
  }

  function proceedConfirm() {
    if (_confirmForm) {
      // Store reference before closing (closeConfirm clears overflow only)
      const form = _confirmForm;
      _confirmForm = null;
      closeConfirm();
      // Small delay to let modal close animation finish before submitting
      setTimeout(() => {
        // requestSubmit() respects form validation; fallback to submit()
        if (form.requestSubmit) {
          form.requestSubmit();
        } else {
          form.submit();
        }
      }, 100);
    }
  }

  // Close on overlay click
  document.getElementById('global-confirm').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
  });
</script>

</aside>