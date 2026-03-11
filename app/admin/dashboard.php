<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /foodwaste/index.php');
    exit;
}




// ─── QUERIES: system-wide ────────────────────────────────────────────────────

// Users
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$userRoles  = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role")->fetchAll();
$users      = $pdo->query("SELECT user_id, email, role, verified, created_at FROM users ORDER BY created_at DESC")->fetchAll();




// Methane — all users, last 20
$methane = $pdo->query("
    SELECT m.methane_ppm, m.status, m.recorded_at, u.email
    FROM methane_monitoring m
    LEFT JOIN users u ON m.user_id = u.user_id
    ORDER BY m.recorded_at DESC LIMIT 20
")->fetchAll();
$methane       = array_reverse($methane);
$latestMethane = !empty($methane) ? end($methane)['methane_ppm'] : 0;
$methaneStatus = !empty($methane) ? end($methane)['status']      : 'SAFE';
$statusCounts  = ['SAFE' => 0, 'WARNING' => 0, 'LEAK' => 0];
foreach ($methane as $m) { if (isset($statusCounts[$m['status']])) $statusCounts[$m['status']]++; }




// Gas level — all users, last 20
$gasLevel = $pdo->query("
    SELECT g.pressure_kpa, g.gas_percentage, g.recorded_at, u.email
    FROM gas_level g
    LEFT JOIN users u ON g.user_id = u.user_id
    ORDER BY g.recorded_at DESC LIMIT 20
")->fetchAll();
$gasLevel       = array_reverse($gasLevel);
$latestPressure = !empty($gasLevel) ? end($gasLevel)['pressure_kpa']   : 0;
$latestGasPct   = !empty($gasLevel) ? end($gasLevel)['gas_percentage'] : 0;




// Gas usage — all users, last 20
$gasUsage = $pdo->query("
    SELECT gu.flow_rate, gu.gas_used, gu.recorded_at, u.email
    FROM gas_usage gu
    LEFT JOIN users u ON gu.user_id = u.user_id
    ORDER BY gu.recorded_at DESC LIMIT 20
")->fetchAll();
$gasUsage     = array_reverse($gasUsage);
$totalGasUsed = $pdo->query("SELECT COALESCE(SUM(gas_used),0) FROM gas_usage")->fetchColumn();




// Activity logs — all users, last 20
$logs          = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 20")->fetchAll();
$failedLogins  = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE activity LIKE '%Failed%'")->fetchColumn();
$successLogins = count(array_filter($logs, fn($l) => $l['activity_type'] === 'login'));



// ─── Chart JSON ──────────────────────────────────────────────────────────────
$methaneLabels = json_encode(array_map(fn($r) => date('H:i', strtotime($r['recorded_at'])), $methane));
$methanePpm    = json_encode(array_column($methane,  'methane_ppm'));
$gasLvlLabels  = json_encode(array_map(fn($r) => date('H:i', strtotime($r['recorded_at'])), $gasLevel));
$gasPressure   = json_encode(array_column($gasLevel, 'pressure_kpa'));
$gasPct        = json_encode(array_column($gasLevel, 'gas_percentage'));
$gasUseLabels  = json_encode(array_map(fn($r) => date('H:i', strtotime($r['recorded_at'])), $gasUsage));
$gasUsedArr    = json_encode(array_column($gasUsage, 'gas_used'));
$flowRateArr   = json_encode(array_column($gasUsage, 'flow_rate'));
$statusJson    = json_encode(array_values($statusCounts));
$roleJson      = json_encode(array_column($userRoles, 'count'));
$roleLblJson   = json_encode(array_column($userRoles, 'role'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Dashboard — FoodWaste BioGas</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>





<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --g-deep:#243d10;--g-mid:#3e6b22;--g-bright:#6aab35;--g-pale:#c8e8a0;--g-ghost:#eaf4d8;
    --b-mid:#ddd0b8;--b-light:#f0e8d8;--b-white:#faf6ee;
    --txt-dark:#1b2c0a;--txt-soft:#637248;--txt-muted:#91a470;
    --danger:#b83225;--warning:#c96a08;--safe:#277a44;
    --shadow:0 2px 18px rgba(36,61,16,.09);--r:13px;--sb-w:248px;
}
html,body{height:100%;background:var(--b-light);color:var(--txt-dark);font-family:'DM Sans',sans-serif;font-size:15px}
.shell{display:flex;min-height:100vh}
.sb{width:var(--sb-w);background:var(--g-deep);position:fixed;inset:0 auto 0 0;display:flex;flex-direction:column;z-index:200;box-shadow:3px 0 28px rgba(0,0,0,.22)}
.sb-logo{padding:26px 22px 18px;border-bottom:1px solid rgba(255,255,255,.08)}
.sb-logo .emblem{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,var(--g-bright),var(--g-mid));display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:11px;box-shadow:0 4px 12px rgba(106,171,53,.35)}
.sb-logo h2{font-family:'DM Serif Display',serif;color:#fff;font-size:1.08rem;line-height:1.25}
.sb-logo small{color:var(--g-pale);font-size:.68rem;font-weight:300;opacity:.8;display:block;margin-top:3px}
.sb-nav{flex:1;padding:14px 0 10px;overflow-y:auto}
.nav-grp{padding:8px 20px 3px;font-size:.63rem;font-weight:600;letter-spacing:.13em;color:rgba(255,255,255,.28);text-transform:uppercase;margin-top:8px}
.nav-a{display:flex;align-items:center;gap:11px;padding:9px 22px;margin:1px 8px;color:rgba(255,255,255,.60);font-size:.86rem;text-decoration:none;border-radius:8px;border-left:3px solid transparent;transition:all .18s;cursor:pointer}
.nav-a .ico{font-size:1rem;width:20px;text-align:center;flex-shrink:0}
.nav-a:hover{color:#fff;background:rgba(255,255,255,.07)}
.nav-a.active{color:#fff;background:rgba(106,171,53,.18);border-left-color:var(--g-bright)}
.sb-foot{padding:14px 18px 18px;border-top:1px solid rgba(255,255,255,.08)}
.sb-user{display:flex;align-items:center;gap:10px;margin-bottom:11px}
.avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--g-bright),var(--g-mid));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.86rem;flex-shrink:0}
.uinfo .uname{color:#fff;font-size:.82rem;font-weight:500}
.uinfo .urole{color:var(--g-pale);font-size:.65rem;opacity:.7}
.signout{display:flex;align-items:center;gap:8px;width:100%;padding:8px 14px;border-radius:8px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:rgba(255,255,255,.55);font-size:.82rem;font-family:inherit;text-decoration:none;transition:all .18s;cursor:pointer}
.signout:hover{background:rgba(184,50,37,.22);border-color:rgba(184,50,37,.4);color:#ffaaaa}
.main{margin-left:var(--sb-w);flex:1;display:flex;flex-direction:column}
.topbar{background:var(--b-white);border-bottom:1px solid var(--b-mid);padding:13px 30px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;box-shadow:0 1px 8px rgba(36,61,16,.06)}
.topbar-title{font-family:'DM Serif Display',serif;font-size:1.3rem;color:var(--g-deep)}
.topbar-r{display:flex;align-items:center;gap:12px}
.clock{font-size:.76rem;color:var(--txt-soft)}
.pill{padding:3px 11px;border-radius:20px;font-size:.70rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase}
.pill.safe{background:#d4f0de;color:#145a2c}
.pill.warning{background:#fde5c2;color:#8a4200}
.pill.leak{background:#fcd8d8;color:#8a1010}
.content{padding:26px 30px;flex:1}
.sec{display:none}.sec.on{display:block}
.kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(195px,1fr));gap:16px;margin-bottom:22px}
.kpi{background:var(--b-white);border-radius:var(--r);padding:20px 22px;box-shadow:var(--shadow);border:1px solid var(--b-mid);position:relative;overflow:hidden;transition:transform .18s,box-shadow .18s}
.kpi::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--g-mid),var(--g-bright));border-radius:3px 3px 0 0}
.kpi.red::after{background:linear-gradient(90deg,#8a1010,var(--danger))}
.kpi.amber::after{background:linear-gradient(90deg,#8a4200,var(--warning))}
.kpi:hover{transform:translateY(-3px);box-shadow:0 8px 28px rgba(36,61,16,.14)}
.kpi-ico{font-size:1.6rem;margin-bottom:12px;line-height:1}
.kpi-val{font-family:'DM Serif Display',serif;font-size:2.1rem;color:var(--g-deep);line-height:1}
.kpi-lbl{font-size:.76rem;color:var(--txt-soft);font-weight:500;margin-top:4px}
.kpi-sub{font-size:.70rem;color:var(--txt-muted);margin-top:5px}
.alert{display:flex;align-items:center;gap:12px;padding:11px 18px;border-radius:10px;font-size:.84rem;font-weight:500;margin-bottom:20px}
.alert.danger{background:#fcd8d8;border:1px solid #f5a8a8;color:#6e0e0e}
.alert.warning{background:#fde5c2;border:1px solid #f4c38a;color:#6e3200}
.chart-grid{display:grid;gap:18px;margin-bottom:22px}
.chart-grid.g2{grid-template-columns:1fr 1fr}
.chart-grid.g21{grid-template-columns:2fr 1fr}
.card{background:var(--b-white);border-radius:var(--r);padding:20px 22px;box-shadow:var(--shadow);border:1px solid var(--b-mid)}
.card-title{font-family:'DM Serif Display',serif;font-size:.98rem;color:var(--g-deep);margin-bottom:2px}
.card-sub{font-size:.71rem;color:var(--txt-muted);margin-bottom:14px}
.ch{position:relative;height:210px}
.ch.tall{height:250px}
.tbl-card{background:var(--b-white);border-radius:var(--r);box-shadow:var(--shadow);border:1px solid var(--b-mid);overflow:hidden;margin-bottom:22px}
.tbl-head{padding:16px 22px;border-bottom:1px solid var(--b-mid);display:flex;align-items:center;justify-content:space-between}
.tbl-head h3{font-family:'DM Serif Display',serif;font-size:.98rem;color:var(--g-deep)}
.tbl-head span{font-size:.71rem;color:var(--txt-muted)}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:.82rem}
thead tr{background:var(--b-light)}
thead th{padding:9px 16px;text-align:left;font-size:.69rem;font-weight:600;letter-spacing:.06em;color:var(--txt-soft);text-transform:uppercase;white-space:nowrap}
tbody tr{border-bottom:1px solid var(--b-mid);transition:background .12s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:var(--b-light)}
tbody td{padding:10px 16px;color:var(--txt-dark);vertical-align:middle}
.bdg{display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:.67rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase}
.b-safe{background:#d4f0de;color:#145a2c}
.b-warning{background:#fde5c2;color:#8a4200}
.b-leak{background:#fcd8d8;color:#8a1010}
.b-login{background:#ddeeff;color:#124a80}
.b-logout{background:#f0e8ff;color:#4a1280}
.b-failed{background:#fcd8d8;color:#8a1010}
.b-admin{background:var(--g-ghost);color:var(--g-deep)}
.b-manager{background:#fde5c2;color:#8a4200}
.b-user{background:var(--b-mid);color:#3a4f22}
.b-verified{background:#d4f0de;color:#145a2c}
.b-unverified{background:#fcd8d8;color:#8a1010}
.empty{text-align:center;padding:50px;color:var(--txt-muted)}
.empty .ei{font-size:2.4rem;margin-bottom:10px}
.empty p{font-size:.83rem}
@media(max-width:960px){
    :root{--sb-w:64px}
    .sb-logo h2,.sb-logo small,.nav-grp,.nav-a span,.uinfo,.signout span{display:none}
    .nav-a{justify-content:center;padding:11px;margin:1px 4px}
    .sb-foot{padding:10px 6px}
    .sb-user{justify-content:center}
    .chart-grid.g2,.chart-grid.g21{grid-template-columns:1fr}
}
@media(max-width:600px){
    .kpi-row{grid-template-columns:1fr 1fr}
    .content{padding:16px 12px}
    .topbar{padding:11px 14px}
}
</style>



</head>
<body>
<div class="shell">

<aside class="sb">
    <div class="sb-logo">
        <div class="emblem">🌿</div>
        <h2>BioGas Monitor</h2>
        <small>Admin Panel</small>
    </div>



    <nav class="sb-nav">
        <div class="nav-grp">Overview</div>
        <a class="nav-a active" onclick="go('overview',this)"><span class="ico"></span><span>Dashboard</span></a>

        <div class="nav-grp">Sensor Overview</div>
        <a class="nav-a" onclick="go('methane',this)"><span class="ico"></span><span>Methane</span></a>
        <a class="nav-a" onclick="go('gaslevel',this)"><span class="ico"></span><span>Gas Level</span></a>
        <a class="nav-a" onclick="go('gasusage',this)"><span class="ico"></span><span>Gas Usage</span></a>

        <div class="nav-grp">Administration</div>
        <a class="nav-a" onclick="go('users',this)"><span class="ico"></span><span>Users</span></a>
        <a class="nav-a" onclick="go('logs',this)"><span class="ico"></span><span>Activity Logs</span></a>
    </nav>



    <div class="sb-foot">
        <div class="sb-user">
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['email'],0,1)); ?></div>
            <div class="uinfo">
                <div class="uname"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                <div class="urole">Administrator</div>
            </div>
        </div>
        <a href="/foodwaste/auth/signout.php" class="signout"><span></span><span>Sign Out</span></a>
    </div>

</aside>




<div class="main">
    <div class="topbar">
        <div class="topbar-title" id="pg-title">Dashboard</div>
        <div class="topbar-r">
            <span class="clock" id="clk"></span>
            <span class="pill <?php echo strtolower($methaneStatus); ?>">CH₄ <?php echo $methaneStatus; ?></span>
        </div>
    </div>
    <div class="content">




    <!-- ══ OVERVIEW ══ -->
    <div class="sec on" id="s-overview">
        <?php if($methaneStatus==='LEAK'): ?>
        <div class="alert danger"><strong>Gas Leak Detected</strong> — A user's methane reading is at a critical level.</div>
        <?php elseif($methaneStatus==='WARNING'): ?>
        <div class="alert warning"><strong>Warning:</strong> Elevated methane detected from a user's sensor.</div>
        <?php endif; ?>

        <div class="kpi-row">
            <div class="kpi <?php echo $methaneStatus==='LEAK'?'red':($methaneStatus==='WARNING'?'amber':''); ?>">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo number_format($latestMethane,1); ?></div>
                <div class="kpi-lbl">Latest Methane (ppm)</div>
                <div class="kpi-sub">Status: <strong><?php echo $methaneStatus; ?></strong></div>
            </div>
            <div class="kpi">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo number_format($latestGasPct,1); ?>%</div>
                <div class="kpi-lbl">Latest Gas Level</div>
                <div class="kpi-sub"><?php echo number_format($latestPressure,1); ?> kPa pressure</div>
            </div>
            <div class="kpi">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo number_format($totalGasUsed,2); ?></div>
                <div class="kpi-lbl">Total Gas Used (m³)</div>
                <div class="kpi-sub">Cumulative — all users</div>
            </div>
            <div class="kpi">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo $totalUsers; ?></div>
                <div class="kpi-lbl">Registered Users</div>
                <div class="kpi-sub"><?php echo $failedLogins; ?> failed login<?php echo $failedLogins!=1?'s':''; ?></div>
            </div>
        </div>

        <div class="chart-grid g21">
            <div class="card">
                <div class="card-title">Methane Trend — All Users</div>
                <div class="card-sub">Recent CH₄ readings across all user sensors</div>
                <div class="ch"><canvas id="ch-meth-ov"></canvas></div>
            </div>
            <div class="card">
                <div class="card-title">Methane Status Split</div>
                <div class="card-sub">Safe / Warning / Leak distribution</div>
                <div class="ch"><canvas id="ch-meth-donut-ov"></canvas></div>
            </div>
        </div>

        <div class="chart-grid g2">
            <div class="card">
                <div class="card-title">Gas Level &amp; Pressure — All Users</div>
                <div class="card-sub">% fill and kPa readings</div>
                <div class="ch"><canvas id="ch-gaslvl-ov"></canvas></div>
            </div>
            <div class="card">
                <div class="card-title">Gas Consumption — All Users</div>
                <div class="card-sub">m³ consumed over time</div>
                <div class="ch"><canvas id="ch-gasuse-ov"></canvas></div>
            </div>
        </div>

        <div class="tbl-card">
            <div class="tbl-head"><h3>Recent Activity</h3><span>Latest 5 events</span></div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>#</th><th>Email</th><th>Activity</th><th>IP</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach(array_slice($logs,0,5) as $lg):
                    $cls=str_contains($lg['activity'],'Failed')?'failed':(str_contains($lg['activity'],'logged in')?'login':'logout'); ?>
                <tr>
                    <td><?php echo $lg['id']; ?></td>
                    <td><?php echo htmlspecialchars($lg['email']); ?></td>
                    <td><span class="bdg b-<?php echo $cls; ?>"><?php echo htmlspecialchars($lg['activity']); ?></span></td>
                    <td><?php echo htmlspecialchars($lg['ip_address']); ?></td>
                    <td><?php echo $lg['created_at']; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>




    <!-- ══ METHANE ══ -->
    <div class="sec" id="s-methane">
        <div class="kpi-row">
            <div class="kpi <?php echo $methaneStatus==='LEAK'?'red':($methaneStatus==='WARNING'?'amber':''); ?>">
                <div class="kpi-ico"></div><div class="kpi-val"><?php echo number_format($latestMethane,1); ?></div>
                <div class="kpi-lbl">Latest Reading (ppm)</div>
            </div>
            <div class="kpi"><div class="kpi-ico"></div><div class="kpi-val"><?php echo $statusCounts['SAFE']; ?></div><div class="kpi-lbl">Safe Readings</div></div>
            <div class="kpi amber"><div class="kpi-ico"></div><div class="kpi-val"><?php echo $statusCounts['WARNING']; ?></div><div class="kpi-lbl">Warnings</div></div>
            <div class="kpi red"><div class="kpi-ico"></div><div class="kpi-val"><?php echo $statusCounts['LEAK']; ?></div><div class="kpi-lbl">Leak Events</div></div>
        </div>
        <div class="chart-grid g2">
            <div class="card"><div class="card-title">Methane PPM Over Time</div><div class="card-sub">All user sensors — recent readings</div><div class="ch tall"><canvas id="ch-meth-det"></canvas></div></div>
            <div class="card"><div class="card-title">Alert Distribution</div><div class="card-sub">Status breakdown</div><div class="ch tall"><canvas id="ch-meth-donut2"></canvas></div></div>
        </div>
        <div class="tbl-card">
            <div class="tbl-head"><h3>Methane Records</h3><span>All users — <?php echo count($methane); ?> readings</span></div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>#</th><th>User</th><th>PPM</th><th>Status</th><th>Recorded At</th></tr></thead>
                <tbody>
                <?php foreach(array_reverse($methane) as $i=>$m): ?>
                <tr>
                    <td><?php echo $i+1; ?></td>
                    <td><?php echo htmlspecialchars($m['email']??'—'); ?></td>
                    <td><?php echo number_format($m['methane_ppm'],2); ?></td>
                    <td><span class="bdg b-<?php echo strtolower($m['status']); ?>"><?php echo $m['status']; ?></span></td>
                    <td><?php echo $m['recorded_at']; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($methane)): ?><tr><td colspan="5"><div class="empty"><div class="ei"></div><p>No methane data yet.</p></div></td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>




    <!-- ══ GAS LEVEL ══ -->
    <div class="sec" id="s-gaslevel">
        <div class="kpi-row">
            <div class="kpi"><div class="kpi-ico"></div><div class="kpi-val"><?php echo number_format($latestGasPct,1); ?>%</div><div class="kpi-lbl">Latest Gas %</div></div>
            <div class="kpi"><div class="kpi-ico"></div><div class="kpi-val"><?php echo number_format($latestPressure,1); ?></div><div class="kpi-lbl">Latest Pressure (kPa)</div></div>
            <div class="kpi"><div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo count($gasLevel)?number_format(array_sum(array_column($gasLevel,'gas_percentage'))/count($gasLevel),1):0; ?>%</div>
                <div class="kpi-lbl">Avg Gas % (all users)</div>
            </div>
            <div class="kpi"><div class="kpi-ico"></div><div class="kpi-val"><?php echo count($gasLevel); ?></div><div class="kpi-lbl">Total Readings</div></div>
        </div>
        <div class="chart-grid g2">
            <div class="card"><div class="card-title">Gas Fill % Over Time</div><div class="card-sub">All user sensors</div><div class="ch tall"><canvas id="ch-gaspct"></canvas></div></div>
            <div class="card"><div class="card-title">Pressure (kPa) Over Time</div><div class="card-sub">All user sensors</div><div class="ch tall"><canvas id="ch-pressure"></canvas></div></div>
        </div>
        <div class="tbl-card">
            <div class="tbl-head"><h3>Gas Level Records</h3><span>All users — <?php echo count($gasLevel); ?> readings</span></div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>#</th><th>User</th><th>Pressure (kPa)</th><th>Gas %</th><th>Recorded At</th></tr></thead>
                <tbody>
                <?php foreach(array_reverse($gasLevel) as $i=>$g): ?>
                <tr>
                    <td><?php echo $i+1; ?></td>
                    <td><?php echo htmlspecialchars($g['email']??'—'); ?></td>
                    <td><?php echo number_format($g['pressure_kpa'],2); ?></td>
                    <td><?php echo number_format($g['gas_percentage'],1); ?>%</td>
                    <td><?php echo $g['recorded_at']; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($gasLevel)): ?><tr><td colspan="5"><div class="empty"><div class="ei"></div><p>No gas level data yet.</p></div></td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>




    <!-- ══ GAS USAGE ══ -->
    <div class="sec" id="s-gasusage">
        <div class="kpi-row">
            <div class="kpi"><div class="kpi-ico"></div><div class="kpi-val"><?php echo number_format($totalGasUsed,2); ?></div><div class="kpi-lbl">Total Used (m³)</div></div>
            <div class="kpi"><div class="kpi-ico"></div><div class="kpi-val"><?php echo count($gasUsage)?number_format(end($gasUsage)['flow_rate'],2):0; ?></div><div class="kpi-lbl">Latest Flow Rate</div></div>
            <div class="kpi"><div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo count($gasUsage)?number_format(array_sum(array_column($gasUsage,'flow_rate'))/count($gasUsage),2):0; ?></div>
                <div class="kpi-lbl">Avg Flow Rate (all users)</div>
            </div>
            <div class="kpi"><div class="kpi-ico"></div><div class="kpi-val"><?php echo count($gasUsage); ?></div><div class="kpi-lbl">Total Readings</div></div>
        </div>
        <div class="chart-grid g2">
            <div class="card"><div class="card-title">Gas Consumed Over Time</div><div class="card-sub">All user sensors — m³</div><div class="ch tall"><canvas id="ch-gasused"></canvas></div></div>
            <div class="card"><div class="card-title">Flow Rate Over Time</div><div class="card-sub">All user sensors — m³/hr</div><div class="ch tall"><canvas id="ch-flowrate"></canvas></div></div>
        </div>
        <div class="tbl-card">
            <div class="tbl-head"><h3>Gas Usage Records</h3><span>All users — <?php echo count($gasUsage); ?> readings</span></div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>#</th><th>User</th><th>Flow Rate</th><th>Gas Used (m³)</th><th>Recorded At</th></tr></thead>
                <tbody>
                <?php foreach(array_reverse($gasUsage) as $i=>$g): ?>
                <tr>
                    <td><?php echo $i+1; ?></td>
                    <td><?php echo htmlspecialchars($g['email']??'—'); ?></td>
                    <td><?php echo number_format($g['flow_rate'],3); ?></td>
                    <td><?php echo number_format($g['gas_used'],3); ?></td>
                    <td><?php echo $g['recorded_at']; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($gasUsage)): ?><tr><td colspan="5"><div class="empty"><div class="ei"></div><p>No usage data yet.</p></div></td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>




    <!-- ══ USERS ══ -->
    <div class="sec" id="s-users">
        <div class="kpi-row">
            <div class="kpi"><div class="kpi-ico"></div><div class="kpi-val"><?php echo $totalUsers; ?></div><div class="kpi-lbl">Total Users</div></div>
            <?php foreach($userRoles as $r): ?>
            <div class="kpi"><div class="kpi-val"><?php echo $r['count']; ?></div><div class="kpi-lbl"><?php echo ucfirst($r['role']); ?>s</div></div>
            <?php endforeach; ?>
        </div>
        <div class="chart-grid g2">
            <div class="card"><div class="card-title">Role Distribution</div><div class="card-sub">Users by role</div><div class="ch"><canvas id="ch-roles"></canvas></div></div>
            <div class="card"><div class="card-title">Login Activity</div><div class="card-sub">Successful vs failed logins</div><div class="ch"><canvas id="ch-activity"></canvas></div></div>
        </div>
        <div class="tbl-card">
            <div class="tbl-head"><h3>All Users</h3><span><?php echo $totalUsers; ?> registered</span></div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>ID</th><th>Email</th><th>Role</th><th>Verified</th><th>Joined</th></tr></thead>
                <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td><?php echo $u['user_id']; ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><span class="bdg b-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                    <td><span class="bdg <?php echo $u['verified']?'b-verified':'b-unverified'; ?>"><?php echo $u['verified']?'Verified':'Unverified'; ?></span></td>
                    <td><?php echo $u['created_at']; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>




    <!-- ══ ACTIVITY LOGS ══ -->
    <div class="sec" id="s-logs">
        <div class="kpi-row">
            <div class="kpi"><div class="kpi-ico"></div><div class="kpi-val"><?php echo count($logs); ?></div><div class="kpi-lbl">Recent Entries</div></div>
            <div class="kpi red"><div class="kpi-ico"></div><div class="kpi-val"><?php echo $failedLogins; ?></div><div class="kpi-lbl">Failed Logins</div></div>
            <div class="kpi"><div class="kpi-ico"></div><div class="kpi-val"><?php echo $successLogins; ?></div><div class="kpi-lbl">Successful Logins</div></div>
        </div>
        <div class="tbl-card">
            <div class="tbl-head"><h3>Activity Logs</h3><span>Last 20 entries — all users</span></div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>ID</th><th>User ID</th><th>Email</th><th>Activity</th><th>IP Address</th><th>Timestamp</th></tr></thead>
                <tbody>
                <?php foreach($logs as $lg):
                    $cls=str_contains($lg['activity'],'Failed')?'failed':(str_contains($lg['activity'],'logged in')?'login':'logout'); ?>
                <tr>
                    <td><?php echo $lg['id']; ?></td>
                    <td><?php echo $lg['user_id']??'<em style="color:var(--txt-muted)">—</em>'; ?></td>
                    <td><?php echo htmlspecialchars($lg['email']); ?></td>
                    <td><span class="bdg b-<?php echo $cls; ?>"><?php echo htmlspecialchars($lg['activity']); ?></span></td>
                    <td><?php echo htmlspecialchars($lg['ip_address']); ?></td>
                    <td><?php echo $lg['created_at']; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>

    </div><!-- /content -->
</div><!-- /main -->
</div><!-- /shell -->

<script>
const TITLES={overview:'Dashboard',methane:'Methane — All Users',gaslevel:'Gas Level — All Users',gasusage:'Gas Usage — All Users',users:'User Management',logs:'Activity Logs'};
function go(id,el){
    document.querySelectorAll('.sec').forEach(s=>s.classList.remove('on'));
    document.querySelectorAll('.nav-a').forEach(n=>n.classList.remove('active'));
    document.getElementById('s-'+id).classList.add('on');
    el.classList.add('active');
    document.getElementById('pg-title').textContent=TITLES[id];
}
function tick(){const n=new Date();document.getElementById('clk').textContent=n.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'})+' '+n.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});}
tick();setInterval(tick,1000);
Chart.defaults.font.family="'DM Sans',sans-serif";Chart.defaults.font.size=12;Chart.defaults.color='#637248';
const C={green:'#6aab35',mid:'#3e6b22',beige:'#b5a48a',safe:'#277a44',warn:'#c96a08',danger:'#b83225'};
const mLbls=<?php echo $methaneLabels;?>,mPpm=<?php echo $methanePpm;?>;
const gLbls=<?php echo $gasLvlLabels;?>,gPress=<?php echo $gasPressure;?>,gPct=<?php echo $gasPct;?>;
const uLbls=<?php echo $gasUseLabels;?>,uUsed=<?php echo $gasUsedArr;?>,uFlow=<?php echo $flowRateArr;?>;
const sStat=<?php echo $statusJson;?>,rData=<?php echo $roleJson;?>,rLbls=<?php echo $roleLblJson;?>;
const failCnt=<?php echo (int)$failedLogins;?>,okCnt=<?php echo (int)$successLogins;?>;
function line(id,labels,datasets){const el=document.getElementById(id);if(!el)return;new Chart(el,{type:'line',data:{labels,datasets},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:datasets.length>1,position:'bottom',labels:{boxWidth:10,padding:14,usePointStyle:true}}},scales:{x:{grid:{color:'rgba(180,200,150,.15)'},ticks:{maxTicksLimit:8,maxRotation:0}},y:{grid:{color:'rgba(180,200,150,.2)'},beginAtZero:false}}}});}
function donut(id,data,labels,colors){const el=document.getElementById(id);if(!el)return;new Chart(el,{type:'doughnut',data:{labels,datasets:[{data,backgroundColor:colors,borderWidth:2,borderColor:'#faf6ee',hoverOffset:6}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,padding:14,usePointStyle:true}}},cutout:'62%'}});}
function bar(id,labels,datasets){const el=document.getElementById(id);if(!el)return;new Chart(el,{type:'bar',data:{labels,datasets},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{grid:{color:'rgba(180,200,150,.2)'},beginAtZero:true}}}});}
const DS=(color,label,data)=>({label,data,borderColor:color,backgroundColor:color+'28',fill:true,tension:0.42,pointBackgroundColor:color,pointRadius:3,borderWidth:2});
line('ch-meth-ov',mLbls,[DS(C.green,'CH₄ ppm',mPpm)]);
donut('ch-meth-donut-ov',sStat,['Safe','Warning','Leak'],[C.safe,C.warn,C.danger]);
line('ch-gaslvl-ov',gLbls,[DS(C.green,'Gas %',gPct),DS(C.beige,'Pressure kPa',gPress)]);
line('ch-gasuse-ov',uLbls,[DS(C.mid,'Gas Used m³',uUsed)]);
line('ch-meth-det',mLbls,[DS(C.green,'CH₄ ppm',mPpm)]);
donut('ch-meth-donut2',sStat,['Safe','Warning','Leak'],[C.safe,C.warn,C.danger]);
line('ch-gaspct',gLbls,[DS(C.green,'Gas %',gPct)]);
line('ch-pressure',gLbls,[DS(C.beige,'Pressure kPa',gPress)]);
line('ch-gasused',uLbls,[DS(C.green,'Gas Used m³',uUsed)]);
line('ch-flowrate',uLbls,[DS(C.mid,'Flow Rate',uFlow)]);
donut('ch-roles',rData,rLbls.map(r=>r.charAt(0).toUpperCase()+r.slice(1)),[C.safe,C.warn,C.green]);
bar('ch-activity',['Successful Logins','Failed Logins'],[{data:[okCnt,failCnt],backgroundColor:[C.safe+'cc',C.danger+'cc'],borderColor:[C.safe,C.danger],borderWidth:2,borderRadius:6}]);
</script>
</body>
</html>