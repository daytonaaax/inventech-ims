<style>
  
/*
   InvenTech — Shared Styles
   File: includes/styles.php
*/

:root {
  --blue-950: #180a28; --blue-900: #2a0e3d; --blue-800: #461a60;
  --blue-700: #611e80; --blue-600: #621db0; --blue-500: #7125eb;
  --blue-400: #bb3bf6; --blue-300: #ca93fd; --blue-200: #e2bffe;
  --blue-100: #f1dbfe; --blue-50:  #f9efff;
  --white: #ffffff;
  --gray-50: #f8fafc; --gray-100: #f1f5f9; --gray-200: #e2e8f0;
  --gray-300: #cbd5e1; --gray-400: #94a3b8; --gray-500: #64748b;
  --gray-600: #475569; --gray-700: #334155; --gray-800: #1e293b;
  --green: #10b981; --green-bg: #d1fae5;
  --yellow: #f59e0b; --yellow-bg: #fef3c7;
  --red: #ef4444;    --red-bg: #fee2e2;
  --orange: #f97316; --orange-bg: #ffedd5;
  --purple: #8b5cf6; --purple-bg: #ede9fe;
  --sidebar-w: 256px; --topbar-h: 64px;
  --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px; --radius-xl: 20px;
  --shadow-sm: 0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.04);
  --shadow-md: 0 4px 16px rgba(0,0,0,.08), 0 2px 6px rgba(0,0,0,.04);
  --shadow-blue: 0 4px 20px rgba(37,99,235,.25);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; }
body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--gray-50); color: var(--gray-800); min-height: 100vh; -webkit-font-smoothing: antialiased; }
input, select, button, a { font-family: inherit; }
a { text-decoration: none; }


/* ── STAFF THEME (Pastel Purple) ──────────────────────────── */
body.theme-staff {
  --blue-950: #0e102e;
  --blue-900: #1b204e;
  --blue-800: #242b66;
  --blue-700: #2d4080;
  --blue-600: #3f5ea0;
  --blue-500: #5c78f6;
  --blue-400: #8b9cfa;
  --blue-300: #b5c0fd;
  --blue-200: #d9d6fe;
  --blue-100: #ede9fe;
  --blue-50:  #f5f3ff;
  --shadow-blue: 0 4px 20px rgba(92, 128, 246, 0.25);
}
body.theme-staff .sidebar { background: #0e112e; }
body.theme-staff .nav-item.active { background: var(--blue-600); }
body.theme-staff .sidebar-logo-icon { background: var(--blue-500); }
body.theme-staff .btn-primary { background: var(--blue-500); border-color: var(--blue-500); }
body.theme-staff .btn-primary:hover { background: var(--blue-600); }
body.theme-staff .welcome-banner { background: linear-gradient(120deg, #1b234e 0%, #3f4ea0 100%); }
body.theme-staff .topbar-user:hover,
body.theme-staff .topbar-btn:hover { border-color: var(--blue-400); background: var(--blue-50); }
body.theme-staff .nav-item:hover { background: rgba(139,92,246,.12); }
body.theme-staff .stat-card:hover { box-shadow: 0 4px 16px rgba(139,92,246,.12); }
body.theme-staff .search-input:focus,
body.theme-staff .form-ctrl:focus,
body.theme-staff .filter-select:focus { border-color: var(--blue-500); box-shadow: 0 0 0 3px rgba(139,92,246,.1); }
body.theme-staff tbody tr:hover { background: var(--blue-50); }
body.theme-staff .profile-dropdown-header { background: linear-gradient(120deg, #1b1c4e, #453fa0); }

/* Role badge in topbar */
.role-badge {
  font-size: 10px; font-weight: 700; padding: 2px 8px;
  border-radius: 20px; margin-left: 6px;
}
.role-badge.admin { background: var(--blue-100); color: var(--blue-700); }
.role-badge.staff { background: #ede9fe; color: #6b21a8; }

/* ── SIDEBAR ── */
.sidebar { width: var(--sidebar-w); background: var(--blue-950); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; height: 100vh; z-index: 100; overflow-y: auto; border-right: 1px solid rgba(255,255,255,.05); }
.sidebar-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,.06); }
.sidebar-logo { display: flex; align-items: center; gap: 10px; }
.sidebar-logo-icon { width: 36px; height: 36px; border-radius: 10px; background: var(--blue-500); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; box-shadow: var(--shadow-blue); }
.sidebar-logo-text { font-size: 17px; font-weight: 800; color: var(--white); }
.sidebar-logo-sub { font-size: 10px; color: rgba(255,255,255,.35); margin-top: 1px; }
.nav { flex: 1; padding: 16px 12px; }
.nav-section-label { font-size: 10px; font-weight: 700; color: rgba(255,255,255,.25); text-transform: uppercase; letter-spacing: 1.2px; padding: 10px 8px 6px; margin-top: 6px; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: var(--radius-sm); cursor: pointer; color: rgba(255,255,255,.5); font-size: 13.5px; font-weight: 500; transition: all .15s; margin-bottom: 2px; }
.nav-item:hover { color: var(--white); background: rgba(255,255,255,.06); }
.nav-item.active { color: var(--white); background: var(--blue-600); box-shadow: 0 2px 12px rgba(37,99,235,.3); }
.nav-icon { font-size: 16px; width: 22px; text-align: center; flex-shrink: 0; }
.nav-badge { margin-left: auto; background: var(--red); color: var(--white); font-size: 10px; font-weight: 700; border-radius: 20px; padding: 2px 7px; }
.sidebar-footer { padding: 16px 12px; border-top: 1px solid rgba(255,255,255,.06); }
.sidebar-user { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: var(--radius-sm); background: rgba(255,255,255,.05); margin-bottom: 8px; }
.user-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--blue-500), var(--blue-300)); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: var(--white); flex-shrink: 0; }
.user-name { font-size: 13px; font-weight: 700; color: var(--white); }
.user-role { font-size: 11px; color: rgba(255,255,255,.35); }
.logout-btn { display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%; padding: 9px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08); border-radius: var(--radius-sm); color: rgba(255,255,255,.45); font-size: 12px; font-weight: 600; cursor: pointer; transition: all .15s; }
.logout-btn:hover { background: rgba(239,68,68,.15); border-color: rgba(239,68,68,.3); color: #fca5a5; }

/* ── MAIN ── */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { height: var(--topbar-h); background: var(--white); border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; justify-content: space-between; padding: 0 32px; position: sticky; top: 0; z-index: 50; box-shadow: var(--shadow-sm); }
.topbar-left .page-breadcrumb { font-size: 12px; color: var(--gray-400); margin-bottom: 2px; }
.topbar-left .page-name { font-size: 16px; font-weight: 800; color: var(--gray-800); }
.topbar-right { display: flex; align-items: center; gap: 10px; }
.topbar-btn { width: 38px; height: 38px; border-radius: var(--radius-sm); border: 1.5px solid var(--gray-200); background: var(--white); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 16px; position: relative; transition: all .15s; color: var(--gray-600); }
.topbar-btn:hover { border-color: var(--blue-400); background: var(--blue-50); }
.notif-dot { position: absolute; top: 5px; right: 5px; width: 8px; height: 8px; background: var(--red); border-radius: 50%; border: 2px solid var(--white); }
.topbar-user { display: flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: var(--radius-sm); border: 1.5px solid var(--gray-200); cursor: pointer; transition: all .15s; }
.topbar-user:hover { border-color: var(--blue-400); background: var(--blue-50); }
.topbar-avatar { width: 26px; height: 26px; border-radius: 50%; background: linear-gradient(135deg, var(--blue-500), var(--blue-300)); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 800; color: var(--white); }
.topbar-uname { font-size: 13px; font-weight: 700; color: var(--gray-700); }
.content { padding: 28px 32px; flex: 1; }

/* ── PAGE ANIMATION ── */
.content { animation: fadeUp .25s ease; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

/* ── CARDS ── */
.card { background: var(--white); border: 1px solid var(--gray-200); border-radius: var(--radius-lg); padding: 22px; box-shadow: var(--shadow-sm); }
.card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.card-title { font-size: 14px; font-weight: 700; color: var(--gray-700); }
.card-action { font-size: 12px; color: var(--blue-500); font-weight: 600; cursor: pointer; }
.card-action:hover { color: var(--blue-700); }

/* ── STAT CARDS ── */
.stat-grid { display: grid; gap: 16px; margin-bottom: 24px; }
.stat-grid-4 { grid-template-columns: repeat(4,1fr); }
.stat-grid-3 { grid-template-columns: repeat(3,1fr); }
.stat-card { background: var(--white); border: 1px solid var(--gray-200); border-radius: var(--radius-lg); padding: 20px 22px; box-shadow: var(--shadow-sm); position: relative; overflow: hidden; transition: transform .2s, box-shadow .2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.stat-card-accent { position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 16px 0 0 16px; }
.stat-icon-wrap { width: 42px; height: 42px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 14px; }
.stat-label { font-size: 12px; font-weight: 600; color: var(--gray-400); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
.stat-value { font-size: 28px; font-weight: 800; letter-spacing: -1px; line-height: 1; margin-bottom: 8px; }
.stat-change { font-size: 12px; font-weight: 500; }
.stat-change.up { color: var(--green); }
.stat-change.down { color: var(--red); }
.stat-change.neutral { color: var(--gray-400); }

/* ── PILLS ── */
.pill { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.pill-green  { background: var(--green-bg);  color: #065f46; }
.pill-yellow { background: var(--yellow-bg); color: #92400e; }
.pill-red    { background: var(--red-bg);    color: #991b1b; }
.pill-orange { background: var(--orange-bg); color: #9a3412; }
.pill-blue   { background: var(--blue-100);  color: var(--blue-700); }
.pill-purple { background: var(--purple-bg); color: #5b21b6; }
.pill-gray   { background: var(--gray-100);  color: var(--gray-600); }

/* ── BUTTONS ── */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 700; cursor: pointer; transition: all .15s; border: 1.5px solid transparent; white-space: nowrap; }
.btn-primary   { background: var(--blue-500); color: var(--white); border-color: var(--blue-500); box-shadow: var(--shadow-blue); }
.btn-primary:hover { background: var(--blue-600); transform: translateY(-1px); }
.btn-secondary { background: var(--white); color: var(--gray-700); border-color: var(--gray-200); }
.btn-secondary:hover { border-color: var(--blue-400); color: var(--blue-600); background: var(--blue-50); }
.btn-danger { background: var(--red-bg); color: var(--red); border-color: transparent; }
.btn-danger:hover { background: var(--red); color: var(--white); }
.btn-sm { padding: 6px 12px; font-size: 12px; }

/* ── TABLE ── */
.table-container { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th { padding: 11px 16px; text-align: left; font-size: 11px; font-weight: 700; color: var(--gray-400); text-transform: uppercase; letter-spacing: .6px; background: var(--gray-50); border-bottom: 1px solid var(--gray-200); }
tbody td { padding: 13px 16px; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--blue-50); }
.td-main { font-weight: 600; color: var(--gray-800); }

/* ── TOOLBAR ── */
.toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.search-wrap { position: relative; flex: 1; min-width: 200px; }
.search-wrap::before { content: '🔍'; position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: 13px; pointer-events: none; }
.search-input { width: 100%; padding: 9px 14px 9px 36px; border: 1.5px solid var(--gray-200); border-radius: var(--radius-sm); font-size: 13px; color: var(--gray-800); background: var(--white); outline: none; transition: all .2s; font-family: inherit; }
.search-input:focus { border-color: var(--blue-500); box-shadow: 0 0 0 3px rgba(37,99,235,.08); }
.filter-select { padding: 9px 14px; border: 1.5px solid var(--gray-200); border-radius: var(--radius-sm); font-size: 13px; color: var(--gray-700); background: var(--white); outline: none; cursor: pointer; transition: all .2s; font-family: inherit; font-weight: 500; }
.filter-select:focus { border-color: var(--blue-500); }

/* ── FORM CONTROLS ── */
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 11px; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
.form-ctrl { width: 100%; padding: 10px 14px; border: 1.5px solid var(--gray-200); border-radius: var(--radius-sm); font-size: 13px; color: var(--gray-800); background: var(--white); outline: none; transition: all .2s; font-family: inherit; }
.form-ctrl:focus { border-color: var(--blue-500); box-shadow: 0 0 0 3px rgba(37,99,235,.08); }

/* ── SECTION HEADER ── */
.section-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.section-title { font-size: 18px; font-weight: 800; color: var(--gray-800); letter-spacing: -.3px; }
.section-sub { font-size: 13px; color: var(--gray-400); margin-top: 3px; }

/* ── DASHBOARD SPECIFIC ── */
.welcome-banner { background: linear-gradient(120deg, var(--blue-900) 0%, var(--blue-600) 100%); border-radius: var(--radius-xl); padding: 28px 32px; margin-bottom: 24px; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: space-between; }
.welcome-banner::before { content: ''; position: absolute; width: 300px; height: 300px; border-radius: 50%; background: rgba(255,255,255,.05); top: -80px; right: -40px; }
.welcome-text .greeting { font-size: 13px; color: rgba(255,255,255,.6); margin-bottom: 6px; }
.welcome-text .name { font-family: 'Instrument Serif', serif; font-size: 26px; color: var(--white); }
.welcome-text .name span { font-style: italic; color: var(--blue-300); }
.welcome-text .sub { font-size: 13px; color: rgba(255,255,255,.5); margin-top: 6px; }
.welcome-stats { display: flex; gap: 28px; z-index: 1; }
.welcome-stat { text-align: center; }
.welcome-stat .val { font-size: 26px; font-weight: 800; color: var(--white); letter-spacing: -1px; }
.welcome-stat .lbl { font-size: 11px; color: rgba(255,255,255,.5); margin-top: 2px; }
.dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }
.dashboard-grid-3 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.bar-chart-wrap { display: flex; align-items: flex-end; gap: 6px; height: 110px; }
.bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; }
.bar-fill { border-radius: 4px 4px 0 0; transition: opacity .2s; cursor: pointer; }
.bar-fill:hover { opacity: .75; }
.bar-day { font-size: 10px; color: var(--gray-400); font-weight: 600; }
.chart-legend { display: flex; gap: 16px; margin-top: 14px; }
.chart-legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--gray-500); }
.chart-legend-dot { width: 10px; height: 10px; border-radius: 3px; }
.donut-section { display: flex; align-items: center; gap: 20px; }
.donut-chart { position: relative; width: 100px; height: 100px; flex-shrink: 0; }
.donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.donut-val { font-size: 20px; font-weight: 800; color: var(--gray-800); }
.donut-lbl { font-size: 10px; color: var(--gray-400); font-weight: 600; }
.donut-legend { display: flex; flex-direction: column; gap: 10px; flex: 1; }
.donut-legend-item { display: flex; align-items: center; justify-content: space-between; font-size: 12px; }
.donut-legend-left { display: flex; align-items: center; gap: 8px; color: var(--gray-600); }
.donut-legend-dot { width: 8px; height: 8px; border-radius: 50%; }
.donut-legend-count { font-weight: 700; color: var(--gray-800); }
.activity-feed { display: flex; flex-direction: column; }
.activity-item { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--gray-100); }
.activity-item:last-child { border-bottom: none; }
.activity-indicator { width: 8px; height: 8px; border-radius: 50%; margin-top: 5px; flex-shrink: 0; }
.activity-body { flex: 1; }
.activity-text { font-size: 13px; color: var(--gray-700); line-height: 1.5; }
.activity-time { font-size: 11px; color: var(--gray-400); margin-top: 3px; font-weight: 500; }

/* ── ALERTS ── */
.alert-feed { display: flex; flex-direction: column; gap: 12px; }
.alert-card { background: var(--white); border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: 18px 20px; display: flex; align-items: flex-start; gap: 14px; border-left: 4px solid; box-shadow: var(--shadow-sm); }
.alert-card.critical { border-left-color: var(--red); }
.alert-card.warning  { border-left-color: var(--yellow); }
.alert-card.info     { border-left-color: var(--blue-400); }
.alert-card-icon { font-size: 22px; flex-shrink: 0; margin-top: 2px; }
.alert-card-title { font-size: 14px; font-weight: 700; color: var(--gray-800); margin-bottom: 4px; }
.alert-card-desc  { font-size: 13px; color: var(--gray-500); line-height: 1.6; }
.alert-card-time  { font-size: 11px; color: var(--gray-400); margin-top: 6px; }
.alert-card-actions { display: flex; gap: 8px; margin-top: 10px; }

/* ── ZONES ── */
.zone-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-bottom: 24px; }
.zone-card { background: var(--white); border: 1px solid var(--gray-200); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); transition: transform .2s, box-shadow .2s; }
.zone-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.zone-card-header { padding: 16px 18px; border-bottom: 1px solid var(--gray-100); display: flex; align-items: center; justify-content: space-between; }
.zone-card-name  { font-size: 14px; font-weight: 800; color: var(--gray-800); }
.zone-card-count { font-size: 12px; color: var(--gray-400); }
.zone-card-body  { padding: 12px; }
.shelf-row { display: flex; align-items: center; justify-content: space-between; padding: 9px 10px; border-radius: var(--radius-sm); font-size: 12px; margin-bottom: 4px; background: var(--gray-50); }
.shelf-row:last-child { margin-bottom: 0; }
.shelf-name  { color: var(--gray-600); font-weight: 500; }
.shelf-count { font-weight: 800; color: var(--blue-600); }

/* ── SETTINGS ── */
.settings-layout { display: grid; grid-template-columns: 200px 1fr; gap: 24px; }
.settings-nav { display: flex; flex-direction: column; gap: 2px; }
.settings-nav-item { padding: 10px 14px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; color: var(--gray-500); cursor: pointer; transition: all .15s; display: flex; align-items: center; gap: 8px; }
.settings-nav-item:hover { background: var(--gray-100); color: var(--gray-700); }
.settings-nav-item.active { background: var(--blue-50); color: var(--blue-600); }
.settings-section-title { font-size: 14px; font-weight: 800; color: var(--gray-700); margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--gray-200); }
.settings-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--gray-100); }
.toggle-row:last-child { border-bottom: none; }
.t-name { font-size: 13px; font-weight: 600; color: var(--gray-700); }
.t-desc { font-size: 12px; color: var(--gray-400); margin-top: 2px; }
.toggle { width: 42px; height: 24px; border-radius: 20px; background: var(--gray-200); position: relative; cursor: pointer; transition: background .2s; flex-shrink: 0; }
.toggle.on { background: var(--blue-500); }
.toggle::after { content: ''; position: absolute; width: 18px; height: 18px; background: var(--white); border-radius: 50%; top: 3px; left: 3px; transition: left .2s; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
.toggle.on::after { left: 21px; }

/* ── REPORTS ── */
.report-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; margin-bottom: 24px; }
.report-card { background: var(--white); border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: 20px; cursor: pointer; transition: all .2s; box-shadow: var(--shadow-sm); }
.report-card:hover { border-color: var(--blue-400); box-shadow: var(--shadow-md); transform: translateY(-2px); }
.report-card-icon { font-size: 28px; margin-bottom: 12px; }
.report-card-name { font-size: 13px; font-weight: 700; color: var(--gray-800); margin-bottom: 5px; }
.report-card-desc { font-size: 12px; color: var(--gray-400); line-height: 1.5; }

/* ── MODAL ── */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 200; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
.modal-overlay.open { display: flex; }
.modal { background: var(--white); border-radius: var(--radius-xl); padding: 28px; width: 500px; max-width: 95vw; box-shadow: var(--shadow-lg); animation: fadeUp .2s ease; box-sizing: border-box;}
.modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
.modal-title { font-size: 16px; font-weight: 800; color: var(--gray-800); }
.modal-close { width: 32px; height: 32px; border-radius: 8px; border: 1.5px solid var(--gray-200); background: var(--white); cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; transition: all .15s; }
.modal-close:hover { background: var(--red-bg); border-color: var(--red); color: var(--red); }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--gray-200); }


/* ── CONFIRM MODAL ── */
.confirm-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.45); z-index: 1000;
  align-items: center; justify-content: center;
  backdrop-filter: blur(3px);
}
.confirm-overlay.open { display: flex; }
.confirm-box {
  background: var(--white); border-radius: var(--radius-xl);
  padding: 28px; width: 380px; max-width: 95vw;
  box-shadow: var(--shadow-lg); animation: fadeUp .2s ease;
  border-top: 4px solid var(--red);
}
.confirm-icon { font-size: 36px; margin-bottom: 12px; text-align: center; }
.confirm-title { font-size: 16px; font-weight: 800; color: var(--gray-800); margin-bottom: 8px; text-align: center; }
.confirm-desc  { font-size: 13px; color: var(--gray-500); text-align: center; line-height: 1.6; margin-bottom: 22px; }
.confirm-btns  { display: flex; gap: 10px; justify-content: center; }
.confirm-btns .btn { min-width: 110px; justify-content: center; }

/* ── MISC ── */
.empty-state { text-align: center; padding: 40px 24px; color: var(--gray-400); }
.empty-state .icon  { font-size: 36px; margin-bottom: 10px; }
.empty-state .title { font-size: 14px; font-weight: 700; color: var(--gray-500); margin-bottom: 4px; }
.empty-state .desc  { font-size: 12px; }
.text-muted { color: var(--gray-400); font-size: 12px; }
.font-bold  { font-weight: 700; }
.mt-16 { margin-top: 16px; }
.mt-20 { margin-top: 20px; }
.flex { display: flex; }
.items-center { align-items: center; }
.gap-8  { gap: 8px; }
.gap-12 { gap: 12px; }
.justify-between { justify-content: space-between; }
</style>