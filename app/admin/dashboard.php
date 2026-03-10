<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

// Only allow admin
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /foodwaste/index.php');
    exit;
}

// ─── DATABASE QUERIES ────────────────────────────────────────────────────────

// Users
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$userRoles  = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role")->fetchAll();

// All users table
$users = $pdo->query("SELECT user_id, email, role, verified, created_at FROM users ORDER BY created_at DESC")->fetchAll();

// Activity logs (last 20)
$logs = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 20")->fetchAll();

// Failed logins
$failedLogins  = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE activity LIKE '%Failed%'")->fetchColumn();
$successLogins = count(array_filter($logs, fn($l) => $l['activity_type'] === 'login'));

// ─── JSON for charts ─────────────────────────────────────────────────────────
$roleJson    = json_encode(array_column($userRoles, 'count'));
$roleLblJson = json_encode(array_column($userRoles, 'role'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — FoodWaste BioGas</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ─── RESET & TOKENS ─────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --g-deep:    #243d10;
    --g-mid:     #3e6b22;
    --g-bright:  #6aab35;
    --g-pale:    #c8e8a0;
    --g-ghost:   #eaf4d8;
    --b-dark:    #b5a48a;
    --b-mid:     #ddd0b8;
    --b-light:   #f0e8d8;
    --b-white:   #faf6ee;
    --txt-dark:  #1b2c0a;
    --txt-mid:   #3a4f22;
    --txt-soft:  #637248;
    --txt-muted: #91a470;
    --danger:    #b83225;
    --warning:   #c96a08;
    --safe:      #277a44;
    --shadow:    0 2px 18px rgba(36,61,16,.09);
    --r:         13px;
    --sb-w:      248px;
}

html, body { height: 100%; background: var(--b-light); color: var(--txt-dark);
             font-family: 'DM Sans', sans-serif; font-size: 15px; }

/* ─── LAYOUT ─────────────────────────────────────────────────────────── */
.shell { display: flex; min-height: 100vh; }

/* ─── SIDEBAR ─────────────────────────────────────────────────────────── */
.sb {
    width: var(--sb-w); background: var(--g-deep);
    position: fixed; inset: 0 auto 0 0;
    display: flex; flex-direction: column;
    z-index: 200;
    box-shadow: 3px 0 28px rgba(0,0,0,.22);
}
.sb-logo {
    padding: 26px 22px 18px;
    border-bottom: 1px solid rgba(255,255,255,.08);
}
.sb-logo .emblem {
    width: 46px; height: 46px; border-radius: 12px;
    background: linear-gradient(135deg, var(--g-bright), var(--g-mid));
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; margin-bottom: 11px;
    box-shadow: 0 4px 12px rgba(106,171,53,.35);
}
.sb-logo h2 { font-family:'DM Serif Display',serif; color:#fff; font-size:1.08rem; line-height:1.25; }
.sb-logo small { color: var(--g-pale); font-size:.68rem; font-weight:300; opacity:.8; display:block; margin-top:3px; }

.sb-nav { flex:1; padding: 14px 0 10px; overflow-y: auto; }
.nav-grp {
    padding: 8px 20px 3px;
    font-size: .63rem; font-weight: 600; letter-spacing: .13em;
    color: rgba(255,255,255,.28); text-transform: uppercase; margin-top: 8px;
}
.nav-a {
    display: flex; align-items: center; gap: 11px;
    padding: 9px 22px; margin: 1px 8px;
    color: rgba(255,255,255,.60); font-size: .86rem; font-weight:400;
    text-decoration: none; border-radius: 8px;
    border-left: 3px solid transparent;
    transition: all .18s; cursor: pointer;
}
.nav-a .ico { font-size: 1rem; width: 20px; text-align: center; flex-shrink:0; }
.nav-a:hover  { color:#fff; background:rgba(255,255,255,.07); }
.nav-a.active { color:#fff; background:rgba(106,171,53,.18); border-left-color: var(--g-bright); }

.sb-foot {
    padding: 14px 18px 18px;
    border-top: 1px solid rgba(255,255,255,.08);
}
.sb-user { display:flex; align-items:center; gap:10px; margin-bottom:11px; }
.avatar {
    width:36px; height:36px; border-radius:50%;
    background: linear-gradient(135deg, var(--g-bright), var(--g-mid));
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-weight:700; font-size:.86rem; flex-shrink:0;
    box-shadow: 0 2px 8px rgba(106,171,53,.3);
}
.uinfo .uname { color:#fff; font-size:.82rem; font-weight:500; }
.uinfo .urole { color:var(--g-pale); font-size:.65rem; opacity:.7; }
.signout {
    display:flex; align-items:center; gap:8px; width:100%;
    padding: 8px 14px; border-radius:8px;
    background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.10);
    color:rgba(255,255,255,.55); font-size:.82rem; font-family:inherit;
    text-decoration:none; transition: all .18s; cursor:pointer;
}
.signout:hover { background:rgba(184,50,37,.22); border-color:rgba(184,50,37,.4); color:#ffaaaa; }

/* ─── MAIN ─────────────────────────────────────────────────────────────── */
.main { margin-left:var(--sb-w); flex:1; display:flex; flex-direction:column; }

.topbar {
    background: var(--b-white); border-bottom:1px solid var(--b-mid);
    padding: 13px 30px;
    display:flex; align-items:center; justify-content:space-between;
    position: sticky; top:0; z-index:50;
    box-shadow: 0 1px 8px rgba(36,61,16,.06);
}
.topbar-title { font-family:'DM Serif Display',serif; font-size:1.3rem; color:var(--g-deep); }
.topbar-r { display:flex; align-items:center; gap:12px; }
.clock { font-size:.76rem; color:var(--txt-soft); }

.content { padding: 26px 30px; flex:1; }

/* ─── SECTION TOGGLE ──────────────────────────────────────────────────── */
.sec { display:none; }
.sec.on { display:block; }

/* ─── KPI GRID ────────────────────────────────────────────────────────── */
.kpi-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(195px,1fr)); gap:16px; margin-bottom:22px; }

.kpi {
    background:var(--b-white); border-radius:var(--r);
    padding: 20px 22px; box-shadow:var(--shadow);
    border:1px solid var(--b-mid);
    position:relative; overflow:hidden;
    transition: transform .18s, box-shadow .18s;
}
.kpi::after {
    content:''; position:absolute; top:0;left:0;right:0; height:3px;
    background: linear-gradient(90deg, var(--g-mid), var(--g-bright));
    border-radius: 3px 3px 0 0;
}
.kpi.red::after  { background:linear-gradient(90deg,#8a1010,var(--danger)); }
.kpi.amber::after{ background:linear-gradient(90deg,#8a4200,var(--warning)); }
.kpi:hover { transform:translateY(-3px); box-shadow:0 8px 28px rgba(36,61,16,.14); }

.kpi-ico { font-size:1.6rem; margin-bottom:12px; line-height:1; }
.kpi-val { font-family:'DM Serif Display',serif; font-size:2.1rem; color:var(--g-deep); line-height:1; }
.kpi-lbl { font-size:.76rem; color:var(--txt-soft); font-weight:500; margin-top:4px; }
.kpi-sub { font-size:.70rem; color:var(--txt-muted); margin-top:5px; }

/* ─── CHARTS ──────────────────────────────────────────────────────────── */
.chart-grid { display:grid; gap:18px; margin-bottom:22px; }
.chart-grid.g2 { grid-template-columns:1fr 1fr; }

.card {
    background:var(--b-white); border-radius:var(--r);
    padding:20px 22px; box-shadow:var(--shadow);
    border:1px solid var(--b-mid);
}
.card-title { font-family:'DM Serif Display',serif; font-size:.98rem; color:var(--g-deep); margin-bottom:2px; }
.card-sub   { font-size:.71rem; color:var(--txt-muted); margin-bottom:14px; }
.ch { position:relative; height:210px; }

/* ─── TABLE ──────────────────────────────────────────────────────────────── */
.tbl-card {
    background:var(--b-white); border-radius:var(--r);
    box-shadow:var(--shadow); border:1px solid var(--b-mid);
    overflow:hidden; margin-bottom:22px;
}
.tbl-head {
    padding:16px 22px; border-bottom:1px solid var(--b-mid);
    display:flex; align-items:center; justify-content:space-between;
}
.tbl-head h3 { font-family:'DM Serif Display',serif; font-size:.98rem; color:var(--g-deep); }
.tbl-head span { font-size:.71rem; color:var(--txt-muted); }
.tbl-wrap { overflow-x:auto; }

table { width:100%; border-collapse:collapse; font-size:.82rem; }
thead tr { background:var(--b-light); }
thead th {
    padding:9px 16px; text-align:left;
    font-size:.69rem; font-weight:600; letter-spacing:.06em;
    color:var(--txt-soft); text-transform:uppercase; white-space:nowrap;
}
tbody tr { border-bottom:1px solid var(--b-mid); transition:background .12s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:var(--b-light); }
tbody td { padding:10px 16px; color:var(--txt-dark); vertical-align:middle; }

/* ─── BADGES ──────────────────────────────────────────────────────────── */
.bdg {
    display:inline-flex; align-items:center;
    padding: 2px 9px; border-radius:20px;
    font-size:.67rem; font-weight:600; letter-spacing:.04em; text-transform:uppercase;
}
.b-login     { background:#ddeeff; color:#124a80; }
.b-logout    { background:#f0e8ff; color:#4a1280; }
.b-failed    { background:#fcd8d8; color:#8a1010; }
.b-admin     { background:var(--g-ghost); color:var(--g-deep); }
.b-manager   { background:#fde5c2; color:#8a4200; }
.b-user      { background:var(--b-mid); color:var(--txt-mid); }
.b-verified  { background:#d4f0de; color:#145a2c; }
.b-unverified{ background:#fcd8d8; color:#8a1010; }

/* ─── RESPONSIVE ──────────────────────────────────────────────────────── */
@media(max-width:960px){
    :root{ --sb-w:64px; }
    .sb-logo h2,.sb-logo small,.nav-grp,.nav-a span,.uinfo,.signout span { display:none; }
    .nav-a { justify-content:center; padding:11px; margin:1px 4px; }
    .sb-foot { padding:10px 6px; }
    .sb-user { justify-content:center; }
    .chart-grid.g2 { grid-template-columns:1fr; }
}
@media(max-width:600px){
    .kpi-row { grid-template-columns:1fr 1fr; }
    .content { padding:16px 12px; }
    .topbar  { padding:11px 14px; }
}
</style>
</head>
<body>
<div class="shell">

<!-- ═══ SIDEBAR ══════════════════════════════════════════════════════════ -->
<aside class="sb">
    <div class="sb-logo">
        <div class="emblem">🌿</div>
        <h2>BioGas Monitor</h2>
        <small>Food Waste Management</small>
    </div>

    <nav class="sb-nav">
        <div class="nav-grp">Overview</div>
        <a class="nav-a active" onclick="go('overview',this)">
            <span class="ico"></span><span>Dashboard</span>
        </a>

        <div class="nav-grp">Administration</div>
        <a class="nav-a" onclick="go('users',this)">
            <span class="ico"></span><span>Users</span>
        </a>
        <a class="nav-a" onclick="go('logs',this)">
            <span class="ico"></span><span>Activity Logs</span>
        </a>
    </nav>

    <div class="sb-foot">
        <div class="sb-user">
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['email'],0,1)); ?></div>
            <div class="uinfo">
                <div class="uname"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                <div class="urole">Administrator</div>
            </div>
        </div>
        <a href="/foodwaste/auth/signout.php" class="signout">
            <span></span><span>Sign Out</span>
        </a>
    </div>
</aside>

<!-- ═══ MAIN ═══════════════════════════════════════════════════════════════ -->
<div class="main">

    <div class="topbar">
        <div class="topbar-title" id="pg-title">Dashboard Overview</div>
        <div class="topbar-r">
            <span class="clock" id="clk"></span>
        </div>
    </div>

    <div class="content">

    <!-- ══════════════════ OVERVIEW ══════════════════════════════════════ -->
    <div class="sec on" id="s-overview">

        <div class="kpi-row">
            <div class="kpi">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo $totalUsers; ?></div>
                <div class="kpi-lbl">Registered Users</div>
                <div class="kpi-sub"><?php echo $failedLogins; ?> failed login<?php echo $failedLogins != 1 ? 's' : ''; ?></div>
            </div>
            <div class="kpi red">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo $failedLogins; ?></div>
                <div class="kpi-lbl">Failed Logins</div>
                <div class="kpi-sub">Recent attempts</div>
            </div>
            <div class="kpi">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo $successLogins; ?></div>
                <div class="kpi-lbl">Successful Logins</div>
                <div class="kpi-sub">Recent sessions</div>
            </div>
            <div class="kpi">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo count($logs); ?></div>
                <div class="kpi-lbl">Log Entries</div>
                <div class="kpi-sub">Last 20 recorded</div>
            </div>
        </div>

        <div class="chart-grid g2">
            <div class="card">
                <div class="card-title">Role Distribution</div>
                <div class="card-sub">Users by role</div>
                <div class="ch"><canvas id="ch-roles-ov"></canvas></div>
            </div>
            <div class="card">
                <div class="card-title">Account Activity</div>
                <div class="card-sub">Successful logins vs failed attempts</div>
                <div class="ch"><canvas id="ch-activity-ov"></canvas></div>
            </div>
        </div>

        <div class="tbl-card">
            <div class="tbl-head"><h3>Recent Activity</h3><span>Latest 5 events</span></div>
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>#</th><th>Email</th><th>Activity</th><th>IP</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($logs, 0, 5) as $lg):
                        $cls = str_contains($lg['activity'], 'Failed') ? 'failed' : (str_contains($lg['activity'], 'logged in') ? 'login' : 'logout');
                    ?>
                    <tr>
                        <td><?php echo $lg['id']; ?></td>
                        <td><?php echo htmlspecialchars($lg['email']); ?></td>
                        <td><span class="bdg b-<?php echo $cls; ?>"><?php echo htmlspecialchars($lg['activity']); ?></span></td>
                        <td><?php echo htmlspecialchars($lg['ip_address']); ?></td>
                        <td><?php echo $lg['created_at']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /s-overview -->

    <!-- ══════════════════ USERS ══════════════════════════════════════════ -->
    <div class="sec" id="s-users">
        <div class="kpi-row">
            <div class="kpi">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo $totalUsers; ?></div>
                <div class="kpi-lbl">Total Users</div>
            </div>
            <?php foreach ($userRoles as $r): ?>
            <div class="kpi">
                <div class="kpi-val"><?php echo $r['count']; ?></div>
                <div class="kpi-lbl"><?php echo ucfirst($r['role']); ?>s</div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="chart-grid g2">
            <div class="card">
                <div class="card-title">Role Distribution</div>
                <div class="card-sub">Users by role</div>
                <div class="ch"><canvas id="ch-roles"></canvas></div>
            </div>
            <div class="card">
                <div class="card-title">Account Activity</div>
                <div class="card-sub">Login vs failed attempts</div>
                <div class="ch"><canvas id="ch-activity"></canvas></div>
            </div>
        </div>

        <div class="tbl-card">
            <div class="tbl-head"><h3>All Users</h3><span><?php echo $totalUsers; ?> registered</span></div>
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Email</th><th>Role</th><th>Verified</th><th>Joined</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo $u['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><span class="bdg b-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                        <td><span class="bdg <?php echo $u['verified'] ? 'b-verified' : 'b-unverified'; ?>"><?php echo $u['verified'] ? 'Verified' : 'Unverified'; ?></span></td>
                        <td><?php echo $u['created_at']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div><!-- /s-users -->

    <!-- ══════════════════ ACTIVITY LOGS ══════════════════════════════════ -->
    <div class="sec" id="s-logs">
        <div class="kpi-row">
            <div class="kpi">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo count($logs); ?></div>
                <div class="kpi-lbl">Recent Entries</div>
            </div>
            <div class="kpi red">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo $failedLogins; ?></div>
                <div class="kpi-lbl">Failed Logins</div>
            </div>
            <div class="kpi">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo $successLogins; ?></div>
                <div class="kpi-lbl">Successful Logins</div>
            </div>
        </div>
        <div class="tbl-card">
            <div class="tbl-head"><h3>Activity Logs</h3><span>Last 20 entries</span></div>
            <div class="tbl-wrap">
                <table>
                    <thead><tr><th>ID</th><th>User ID</th><th>Email</th><th>Activity</th><th>IP Address</th><th>Timestamp</th></tr></thead>
                    <tbody>
                    <?php foreach ($logs as $lg):
                        $cls = str_contains($lg['activity'], 'Failed') ? 'failed' : (str_contains($lg['activity'], 'logged in') ? 'login' : 'logout');
                    ?>
                    <tr>
                        <td><?php echo $lg['id']; ?></td>
                        <td><?php echo $lg['user_id'] ?? '<em style="color:var(--txt-muted)">—</em>'; ?></td>
                        <td><?php echo htmlspecialchars($lg['email']); ?></td>
                        <td><span class="bdg b-<?php echo $cls; ?>"><?php echo htmlspecialchars($lg['activity']); ?></span></td>
                        <td><?php echo htmlspecialchars($lg['ip_address']); ?></td>
                        <td><?php echo $lg['created_at']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div><!-- /s-logs -->

    </div><!-- /content -->
</div><!-- /main -->
</div><!-- /shell -->

<script>
// ─── NAVIGATION ──────────────────────────────────────────────────────────
const TITLES = {
    overview: 'Dashboard Overview',
    users:    'Users',
    logs:     'Activity Logs'
};
function go(id, el) {
    document.querySelectorAll('.sec').forEach(s => s.classList.remove('on'));
    document.querySelectorAll('.nav-a').forEach(n => n.classList.remove('active'));
    document.getElementById('s-' + id).classList.add('on');
    el.classList.add('active');
    document.getElementById('pg-title').textContent = TITLES[id];
}

// ─── CLOCK ───────────────────────────────────────────────────────────────
function tick() {
    const n = new Date();
    document.getElementById('clk').textContent =
        n.toLocaleDateString('en-PH', { weekday:'short', month:'short', day:'numeric' }) + ' ' +
        n.toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
}
tick(); setInterval(tick, 1000);

// ─── CHART.JS DEFAULTS ───────────────────────────────────────────────────
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#637248';

const C = {
    green:  '#6aab35',
    mid:    '#3e6b22',
    safe:   '#277a44',
    warn:   '#c96a08',
    danger: '#b83225',
    beige:  '#b5a48a',
};

// ─── DATA FROM PHP ───────────────────────────────────────────────────────
const rData    = <?php echo $roleJson; ?>;
const rLbls    = <?php echo $roleLblJson; ?>;
const failCnt  = <?php echo (int)$failedLogins; ?>;
const okCnt    = <?php echo (int)$successLogins; ?>;

// ─── HELPERS ─────────────────────────────────────────────────────────────
function donut(id, data, labels, colors) {
    const el = document.getElementById(id); if (!el) return;
    new Chart(el, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#faf6ee', hoverOffset: 6 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 14, usePointStyle: true } } },
            cutout: '62%'
        }
    });
}

function bar(id, labels, datasets) {
    const el = document.getElementById(id); if (!el) return;
    new Chart(el, {
        type: 'bar',
        data: { labels, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: 'rgba(180,200,150,.2)' }, beginAtZero: true }
            }
        }
    });
}

// ─── INIT CHARTS ─────────────────────────────────────────────────────────
const roleColors = [C.safe, C.warn, C.green];
const actDataset = [{
    data: [okCnt, failCnt],
    backgroundColor: [C.safe + 'cc', C.danger + 'cc'],
    borderColor: [C.safe, C.danger],
    borderWidth: 2,
    borderRadius: 6
}];

// Overview charts
donut('ch-roles-ov',    rData, rLbls.map(r => r.charAt(0).toUpperCase() + r.slice(1)), roleColors);
bar('ch-activity-ov',   ['Successful Logins', 'Failed Logins'], actDataset);

// Users page charts
donut('ch-roles',       rData, rLbls.map(r => r.charAt(0).toUpperCase() + r.slice(1)), roleColors);
bar('ch-activity',      ['Successful Logins', 'Failed Logins'], actDataset);
</script>

</body>
</html>