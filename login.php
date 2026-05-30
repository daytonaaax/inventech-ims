<?php

//  InvenTech — Login Page
//  File: login.php

session_start();
require_once 'db_config.php';

// If already logged in, go straight to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both your username and password.';
    } else {
        // Fetch user from database
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        // Check if user exists and password matches
        // NOTE: Using plain text comparison for now (since sample data uses plain text)
        // When you're ready for production, switch to password_hash() and password_verify()
        // password_verify() checks the plain input against the hashed password in the database
        if ($user && password_verify($password, $user['password'])) {

            // Store user info in session
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'];

            // Log the login action
            $uid  = $user['user_id'];
            $desc = "User " . $user['full_name'] . " signed into the system.";
            $log  = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'login', ?)");
            $log->bind_param('is', $uid, $desc);
            $log->execute();
            $log->close();

            // Redirect to dashboard
            header('Location: dashboard.php');
            exit();

        } else {
            $error = 'Incorrect username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InvenTech — Sign In</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: #fff;
    min-height: 100vh;
    display: flex;
    -webkit-font-smoothing: antialiased;
  }

  /* ── LEFT PANEL ── */
  .login-left {
    flex: 1;
    background: linear-gradient(145deg, #200e3d 0%, #6c1e80 60%, #5d1586 100%);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding-left: 150px;
    position: relative;
    overflow: hidden;
  }

  .login-left::before {
    content: '';
    position: absolute;
    width: 500px; height: 500px;
    border-radius: 50%;
    border: 1px solid #ffffff31;
    background: rgba(255,255,255,.04);
    top: -120px; right: -100px;
  }
  .login-left::after {
    content: '';
    position: absolute;
    width: 280px; height: 280px;
    border: 1px solid #ffffff31;
    border-radius: 50%;
    background: rgba(255,255,255,.04);
    bottom: -60px; left: -40px;
  }

  .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; position: relative; z-index: 1; }
  .brand-icon {
    width: 80px; height: 80px; border-radius: 16px;
    background: rgba(255, 255, 255, 0.25);
    border: 1px solid rgba(255,255,255,.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 50px; backdrop-filter: blur(8px);
  }
  .brand-name { font-size: 32px; font-weight: 800; color: #fff; }
  .brand-tagline { font-size: 20px; color: rgba(255, 255, 255, 0.53); margin-top: 1px; }
  .headline {
    font-family: 'Instrument Serif', serif;
    font-size: 50px; line-height: 1.15;
    color: #fff; margin-bottom: 18px;
    position: relative; z-index: 1;
  }
  .headline em { font-style: italic; color: #c08aff; }
  .desc {
    font-size: 18px; color: rgba(255, 255, 255, 0.62);
    max-width: 500px; line-height: 1.8;
    position: relative; z-index: 1;
  }
  .features { margin-top: 25px; display: flex; flex-direction: column; gap: 13px; position: relative; z-index: 1; }
  .feat { display: flex; align-items: center; gap: 10px; font-size: 14px; color: rgba(255, 255, 255, 0.62); }
  .feat-dot { width: 6px; height: 6px; border-radius: 50%; background: #ca93fd; flex-shrink: 0; }

  /* ── RIGHT PANEL ── */
  .login-right {
    width: 740px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    padding: 48px 56px;
    background: #fff;
  }
  .form-wrap { width: 100%; max-width: 400px; }
  .form-title { font-size: 30px; font-weight: 800; color: #1e293b; letter-spacing: -.4px; margin-bottom: 6px; }
  .form-sub { font-size: 16px; color: #94a3b8; margin-bottom: 32px; }

  .error-box {
    background: #fee2e2; color: #991b1b;
    border: 1px solid #fca5a5; border-radius: 10px;
    padding: 11px 14px; font-size: 13px;
    margin-bottom: 18px; display: flex; align-items: center; gap: 8px;
  }

  .form-group { margin-bottom: 18px; }
  .form-label {
    display: block; font-size: 11px; font-weight: 700;
    color: #64748b; text-transform: uppercase;
    letter-spacing: .6px; margin-bottom: 7px;
  }
  .form-input {
    width: 100%; padding: 12px 16px;
    border: 1.5px solid #e2e8f0; border-radius: 10px;
    font-size: 14px; font-family: inherit;
    color: #351e3b; background: #f8fafc;
    outline: none; transition: all .2s;
  }
  .form-input:focus {
    border-color: #a225eb;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
  }

  .submit-btn {
    width: 100%; padding: 14px;
    background: #8125eb; color: #fff;
    border: none; border-radius: 10px;
    font-size: 15px; font-weight: 700;
    font-family: inherit; cursor: pointer;
    transition: all .2s; margin-top: 6px;
    box-shadow: 0 4px 20px rgba(37,99,235,.3);
  }
  .submit-btn:hover { background: #7e1dd8; transform: translateY(-1px); box-shadow: 0 6px 24px rgba(37,99,235,.4); }
  .submit-btn:active { transform: translateY(0); }

  .form-note { margin-top: 20px; font-size: 12px; color: #94a3b8; text-align: center; }
  .form-note span { color: #6a25eb; font-weight: 600; }

  /* ── DEMO CREDENTIALS BOX ── */
  .demo-box {
    margin-top: 24px;
    background: #eff6ff; border: 1px solid #bfdbfe;
    border-radius: 10px; padding: 14px 16px;
  }
  .demo-box-title { font-size: 11px; font-weight: 700; color: #1d4ed8; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; }
  .demo-cred { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px; }
  .demo-cred:last-child { margin-bottom: 0; }
  .demo-label { color: #64748b; font-weight: 600; }
  .demo-val { color: #1e293b; font-weight: 700; font-family: monospace; }
</style>
</head>
<body>

<div class="login-left">
  <div class="brand">
    <div class="brand-icon">📦</div>
    <div>
      <div class="brand-name">InvenTech</div>
      <div class="brand-tagline">Appliance Inventory Management System</div>
    </div>
  </div>
  <div class="headline">Smarter inventory,<br><em>better control.</em></div>
  <div class="desc">
    An appliance inventory management system built for every appliance store needs, all running locally on your device.
  </div>
  <div class="features">
    <div class="feat"><div class="feat-dot"></div>Track and monitor stocks</div>
    <div class="feat"><div class="feat-dot"></div>Complete transaction and activity history</div>
    <div class="feat"><div class="feat-dot"></div>Zone and shelf location management</div>
    <div class="feat"><div class="feat-dot"></div>Automated low stock and defect item alerts</div>
    <div class="feat"><div class="feat-dot"></div>Role-based access for Admin and Staff</div>
  </div>
</div>

<div class="login-right">
  <div class="form-wrap">
    <div class="form-title">Welcome back</div>
    <div class="form-sub">Sign in to access your inventory dashboard</div>

    <?php if ($error): ?>
      <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <input
          class="form-input"
          type="text"
          id="username"
          name="username"
          placeholder="Enter your username"
          value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
          autocomplete="username"
          required
        >
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input
          class="form-input"
          type="password"
          id="password"
          name="password"
          placeholder="Enter your password"
          autocomplete="current-password"
          required
        >
      </div>
      <!-- T&C Checkbox -->
      <div style="display:flex;align-items:flex;gap:10px;margin-bottom:16px;margin-top:14px;justify-content:center">
        <input type="checkbox" id="tc-agree" name="tc_agree"
          style="width:16px;height:16px;margin-top:2px;cursor:pointer;accent-color:#8125eb;flex-shrink:0"
          onchange="toggleSubmit(this)">
        <label for="tc-agree" style="font-size:13px;color:#64748b;line-height:1.5;">
          I have read and agree to the
        </label>
        <span onclick="openTC()" style="font-size:13px;color:#8125eb;font-weight:600;cursor:pointer;text-decoration:underline;line-height:1.5;">
          Terms and Conditions
        </span>
      </div>
      <button type="submit" class="submit-btn" id="login-submit-btn"
        disabled style="opacity:.55;cursor:not-allowed">
        Sign In to InvenTech
      </button>
    </form>

    <p class="form-note">No account? Contact your <span>System Administrator</span>.</p>
  </div>
</div>

<!-- ══ TERMS AND CONDITIONS MODAL ══ -->
<div id="tc-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;backdrop-filter:blur(4px)">
  <div style="background:#fff;border-radius:20px;width:600px;max-width:95vw;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden">

    <!-- Header -->
    <div style="padding:24px 28px 18px;border-bottom:1px solid #e2e8f0;flex-shrink:0">
      <div style="display:flex;align-items:center;justify-content:space-between">
        <div>
          <div style="font-size:18px;font-weight:800;color:#1e293b">📋 Terms and Conditions</div>
          <div style="font-size:12px;color:#94a3b8;margin-top:3px">InvenTech — Appliance Inventory Management System</div>
        </div>
        <button onclick="closeTC()" style="width:32px;height:32px;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center">✕</button>
      </div>
    </div>

    <!-- Body — scrollable -->
    <div style="padding:24px 28px;overflow-y:auto;flex:1;font-size:13px;color:#475569;line-height:1.8">

      <div style="margin-bottom:24px">
        <div style="font-size:15px;font-weight:800;color:#1e293b;margin-bottom:8px">1. System Overview and Core Architecture</div>
        <p style="margin-bottom:10px">The InvenTech Appliance Inventory Management System is a data-driven web application built on a local server environment (Laragon) utilizing a PHP backend and a MySQL relational database.</p>
        <p style="margin-bottom:8px;font-weight:700;color:#1e293b">How the System Works (For Developers &amp; Students)</p>
        <ul style="padding-left:20px;display:flex;flex-direction:column;gap:6px">
          <li><strong>Relational Integrity:</strong> The system uses 8 core tables (users, categories, zones, shelves, appliances, transactions, activity_logs, and alerts) mapped using formal relational database architecture to prevent orphaned data.</li>
          <li><strong>Automation via Triggers:</strong> A custom MySQL trigger (<code>trg_low_stock_alert</code>) automatically detects when stock levels drop to or below minimum thresholds, instantly pushing an unread notification to the Alerts page. Restocking items automatically dismisses these alerts.</li>
          <li><strong>Secure Transactions:</strong> Data integrity during stock movements is protected by a MySQL Stored Procedure (<code>sp_log_transaction</code>) utilizing strict BEGIN, COMMIT, and ROLLBACK commands. This ensures that a stock count never updates without a corresponding transaction log entry being successfully created.</li>
          <li><strong>Performance Optimization:</strong> Database Indexes are applied to high-traffic search queries (appliance names, SKUs, conditions, and transaction dates) to prevent slow load times as the inventory scales.</li>
        </ul>
      </div>

      <div style="margin-bottom:24px">
        <div style="font-size:15px;font-weight:800;color:#1e293b;margin-bottom:8px">2. Acceptance of Terms</div>
        <p>By checking the required agreement box on the Login page, accessing, or using the InvenTech system, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions. If you do not agree to these terms, you are prohibited from using this system.</p>
      </div>

      <div style="margin-bottom:24px">
        <div style="font-size:15px;font-weight:800;color:#1e293b;margin-bottom:8px">3. Role-Based Access Control and Authorized Use</div>
        <p style="margin-bottom:10px">This system is intended for authorized organizational personnel only. Features, administrative rights, and interface visuals are strictly dictated by user roles:</p>
        <ul style="padding-left:20px;display:flex;flex-direction:column;gap:6px">
          <li><strong>Admin Accounts:</strong> Full administrative control. Admins have access to destructive actions including CSV Restoration, User Management, System Audits, and the Danger Zone (Soft Reset and Full Reset capabilities). Admin portals default to a professional blue theme.</li>
          <li><strong>Staff Accounts:</strong> Limited operational access. Staff are permitted to perform day-to-day operations, view inventory, print reports, and access Settings tabs (System Info, Terms &amp; Conditions, and CSV Export-only Backups). Staff portals feature a distinct pastel purple theme and role badge.</li>
        </ul>
        <p style="margin-top:10px;margin-bottom:6px"><strong>User Prohibitions:</strong></p>
        <ul style="padding-left:20px;display:flex;flex-direction:column;gap:6px">
          <li>Users must restrict activities to features explicitly assigned to their roles.</li>
          <li>Sharing account credentials or leaving active sessions unattended on shared workstations is strictly forbidden.</li>
          <li>Any deliberate attempt to bypass security protocols, execute unauthorized SQL queries, manipulate system files, or exploit the local intranet environment will result in immediate permanent deactivation.</li>
        </ul>
      </div>

      <div style="margin-bottom:24px">
        <div style="font-size:15px;font-weight:800;color:#1e293b;margin-bottom:8px">4. Operational User Responsibilities</div>
        <p style="margin-bottom:10px">All active system users are bound by the following data integrity responsibilities:</p>
        <ul style="padding-left:20px;display:flex;flex-direction:column;gap:6px">
          <li><strong>Data Accuracy:</strong> Ensure all inventory counts, appliance conditions, categories, and locations (Zones and Shelves) are entered accurately.</li>
          <li><strong>Real-time Tracking:</strong> Record stock-in and stock-out activities immediately to maintain trusted inventory records.</li>
          <li><strong>Account Safety:</strong> Users must explicitly log out after each session to prevent local session hijacking.</li>
          <li><strong>Infrastructure Reporting:</strong> Any system malfunction, suspicious log activity, duplicate alert rendering, or unexpected data behavior must be reported immediately to the system administrator.</li>
        </ul>
      </div>

      <div style="margin-bottom:24px">
        <div style="font-size:15px;font-weight:800;color:#1e293b;margin-bottom:8px">5. Data Privacy, Backups, and Security</div>
        <ul style="padding-left:20px;display:flex;flex-direction:column;gap:6px">
          <li><strong>Confidentiality:</strong> All inventory metrics, pricing schemas, supplier specifics, and user activity logs are strictly confidential.</li>
          <li><strong>Security Protocols:</strong> The system enforces standard digital security, including replacing all plain-text passwords with industry-standard cryptographic hashing (<code>password_hash</code> and <code>password_verify</code>) and protecting all forms against SQL Injection utilizing strict MySQLi prepared statements (<code>bind_param</code>).</li>
          <li><strong>Data Management &amp; CSV Operations:</strong> Backups exported via the system must be handled safely. Importing CSV data requires precise table matching and schema validation to avoid database corruption.</li>
        </ul>
      </div>

      <div style="margin-bottom:24px">
        <div style="font-size:15px;font-weight:800;color:#1e293b;margin-bottom:8px">6. System Availability and Intranet Limitations</div>
        <p>This system operates entirely within a local intranet environment (Laragon). The development team does not guarantee uninterrupted system uptime and reserves the right to perform critical patches, updates, database table optimization, or full structural resets during authorized maintenance windows.</p>
      </div>

      <div style="margin-bottom:24px">
        <div style="font-size:15px;font-weight:800;color:#1e293b;margin-bottom:8px">7. Limitation of Liability</div>
        <p>The system administrators, student developers, and academic supervisors shall not be held liable for any loss of data, corrupt InnoDB tables, system exploitation, or operational damages arising from user negligence, improper local server shutdowns, or unapproved record modifications.</p>
      </div>

      <div style="margin-bottom:8px">
        <div style="font-size:15px;font-weight:800;color:#1e293b;margin-bottom:8px">8. Amendments and Document Tracking</div>
        <p>These Terms and Conditions may be modified over time to align with system feature expansions. Continued usage of InvenTech post-update constitutes absolute agreement to the current revision.</p>
        <p style="margin-top:8px">A comprehensive documentation of system growth, features, and fixes can be viewed in the Settings page under Version History.</p>
      </div>

    </div>

    <!-- Footer -->
    <div style="padding:18px 28px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;background:#f8fafc">
      <div style="font-size:12px;color:#94a3b8">Last updated: May 2026</div>
      <button onclick="agreeTC()" style="background:#2563eb;color:#fff;border:none;border-radius:10px;padding:10px 24px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">
        I Agree
      </button>
    </div>
  </div>
</div>

<script>
  function toggleSubmit(checkbox) {
    const btn = document.getElementById('login-submit-btn');
    btn.disabled      = !checkbox.checked;
    btn.style.opacity = checkbox.checked ? '1' : '.55';
    btn.style.cursor  = checkbox.checked ? 'pointer' : 'not-allowed';
  }

  function openTC() {
    document.getElementById('tc-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
  function closeTC() {
    document.getElementById('tc-modal').style.display = 'none';
    document.body.style.overflow = '';
  }
  function agreeTC() {
    const cb = document.getElementById('tc-agree');
    cb.checked = true;
    toggleSubmit(cb);
    closeTC();
  }
</script>
</body>
</html>
