<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/functions.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header('Location: /foodwaste/index.php');
    exit;
}

$managerEmail = $_SESSION['email'] ?? '';
$managerId    = (int)($_SESSION['user_id'] ?? 0);




// ─── HANDLE VERIFY / UNVERIFY ────────────────────────────────────────────────
$actionMsg   = '';
$actionError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['target_user_id'])) {
    $targetId = (int)$_POST['target_user_id'];
    $action   = $_POST['action'];




    // Safety: only allow toggling role='user' accounts
    $check = $pdo->prepare("SELECT user_id, email, verified, role FROM users WHERE user_id = ? AND role = 'user'");
    $check->execute([$targetId]);
    $target = $check->fetch();

    if (!$target) {
        $actionMsg   = 'Invalid user or insufficient permission.';
        $actionError = true;
    } else {
        if ($action === 'verify') {
            $pdo->prepare("UPDATE users SET verified = 1 WHERE user_id = ?")->execute([$targetId]);
            $activity = "Manager verified account: {$target['email']}";
            $actionMsg = "Account <strong>" . htmlspecialchars($target['email']) . "</strong> has been verified.";
        } elseif ($action === 'unverify') {
            $pdo->prepare("UPDATE users SET verified = 0 WHERE user_id = ?")->execute([$targetId]);
            $activity = "Manager unverified account: {$target['email']}";
            $actionMsg = "Account <strong>" . htmlspecialchars($target['email']) . "</strong> has been set to unverified.";
        } else {
            $actionMsg   = 'Unknown action.';
            $actionError = true;
        }

        if (!$actionError) {
            // Log the action
            $log = $pdo->prepare("INSERT INTO activity_logs (user_id, email, activity, activity_type, ip_address) VALUES (?, ?, ?, 'admin', ?)");
            $log->execute([$managerId, $managerEmail, $activity, $_SERVER['REMOTE_ADDR']]);
        }
    }
}




// ─── QUERIES: scoped to role='user' accounts only ────────────────────────────

$totalUsers      = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$verifiedCount   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND verified = 1")->fetchColumn();
$unverifiedCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND verified = 0")->fetchColumn();
$users           = $pdo->query("SELECT user_id, email, verified, created_at FROM users WHERE role = 'user' ORDER BY created_at DESC")->fetchAll();




// Methane — user accounts only, last 20
$methane = $pdo->query("
    SELECT m.methane_ppm, m.status, m.recorded_at, u.email
    FROM methane_monitoring m
    LEFT JOIN users u ON m.user_id = u.user_id
    WHERE u.role = 'user'
    ORDER BY m.recorded_at DESC LIMIT 20
")->fetchAll();
$methane       = array_reverse($methane);
$latestMethane = !empty($methane) ? end($methane)['methane_ppm'] : 0;
$methaneStatus = !empty($methane) ? end($methane)['status']      : 'SAFE';
$statusCounts  = ['SAFE' => 0, 'WARNING' => 0, 'LEAK' => 0];
foreach ($methane as $m) { if (isset($statusCounts[$m['status']])) $statusCounts[$m['status']]++; }




// Gas level — user accounts only, last 20
$gasLevel = $pdo->query("
    SELECT g.pressure_kpa, g.gas_percentage, g.recorded_at, u.email
    FROM gas_level g
    LEFT JOIN users u ON g.user_id = u.user_id
    WHERE u.role = 'user'
    ORDER BY g.recorded_at DESC LIMIT 20
")->fetchAll();
$gasLevel       = array_reverse($gasLevel);
$latestPressure = !empty($gasLevel) ? end($gasLevel)['pressure_kpa']   : 0;
$latestGasPct   = !empty($gasLevel) ? end($gasLevel)['gas_percentage'] : 0;




// Gas usage — user accounts only, last 20
$gasUsage = $pdo->query("
    SELECT gu.flow_rate, gu.gas_used, gu.recorded_at, u.email
    FROM gas_usage gu
    LEFT JOIN users u ON gu.user_id = u.user_id
    WHERE u.role = 'user'
    ORDER BY gu.recorded_at DESC LIMIT 20
")->fetchAll();
$gasUsage     = array_reverse($gasUsage);
$totalGasUsed = $pdo->query("
    SELECT COALESCE(SUM(gu.gas_used), 0)
    FROM gas_usage gu
    LEFT JOIN users u ON gu.user_id = u.user_id
    WHERE u.role = 'user'
")->fetchColumn();




// Activity logs — user accounts only, last 20
$logs = $pdo->query("
    SELECT al.*
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    WHERE u.role = 'user'
    ORDER BY al.created_at DESC LIMIT 20
")->fetchAll();
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
?>



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Manager Dashboard — FoodWaste BioGas</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


</head>
<body>
<div class="shell">




<!-- ══ SIDEBAR ══ -->
<aside class="sb">
    <div class="sb-logo">
        <div class="emblem">🌿</div>
        <h2>BioGas Monitor</h2>
        <small>Manager View</small>
    </div>



    <nav class="sb-nav">
        <div class="nav-grp">Overview</div>
        <a class="nav-a active" onclick="go('overview',this)"><span class="ico"></span><span>Dashboard</span></a>

        <div class="nav-grp">Sensor Overview</div>
        <a class="nav-a" onclick="go('methane',this)"><span class="ico"></span><span>Methane</span></a>
        <a class="nav-a" onclick="go('gaslevel',this)"><span class="ico"></span><span>Gas Level</span></a>
        <a class="nav-a" onclick="go('gasusage',this)"><span class="ico"></span><span>Gas Usage</span></a>

        <div class="nav-grp">User Management</div>
        <a class="nav-a" onclick="go('users',this)"><span class="ico"></span><span>Users &amp; Verification</span></a>
        <a class="nav-a" onclick="go('logs',this)"><span class="ico"></span><span>Activity Logs</span></a>
    </nav>



    <div class="sb-foot">
        <div class="sb-user">
            <div class="avatar"><?php echo strtoupper(substr($managerEmail,0,1)); ?></div>
            <div class="uinfo">
                <div class="uname"><?php echo htmlspecialchars($managerEmail); ?></div>
                <div class="urole">Manager</div>
            </div>
        </div>
        <a href="/foodwaste/auth/signout.php" class="signout"><span></span><span>Sign Out</span></a>
    </div>
</aside>




<!-- ══ MAIN ══ -->
<div class="main">
    <div class="topbar">
        <div class="topbar-title" id="pg-title">Dashboard</div>
        <div class="topbar-r">
            <span class="clock" id="clk"></span>
            <span class="pill <?php echo strtolower($methaneStatus); ?>">CH₄ <?php echo $methaneStatus; ?></span>
        </div>
    </div>


    
    <div class="content">

    <?php if($actionMsg): ?>
    <div class="alert <?php echo $actionError?'error':'success'; ?>" id="action-msg">
        <?php echo $actionError ? '❌' : '✅'; ?> <?php echo $actionMsg; ?>
    </div>
    <?php endif; ?>




    <!-- ══ OVERVIEW ══ -->
    <div class="sec on" id="s-overview">
        <?php if($methaneStatus==='LEAK'): ?>
        <div class="alert danger"><strong>Gas Leak Detected</strong> — A user's methane sensor is reporting a critical level.</div>
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
            <div class="kpi grn">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo $verifiedCount; ?></div>
                <div class="kpi-lbl">Verified Users</div>
                <div class="kpi-sub"><?php echo $unverifiedCount; ?> awaiting verification</div>
            </div>
            <div class="kpi <?php echo $unverifiedCount>0?'amber':''; ?>">
                <div class="kpi-ico"></div>
                <div class="kpi-val"><?php echo $totalUsers; ?></div>
                <div class="kpi-lbl">Total Users</div>
                <div class="kpi-sub">Registered accounts</div>
            </div>
        </div>

        <?php if($unverifiedCount > 0): ?>
        <div class="alert warning">
            <strong><?php echo $unverifiedCount; ?> account<?php echo $unverifiedCount!=1?'s are':' is'; ?> awaiting verification.</strong>
            <a href="javascript:void(0)" onclick="go('users', document.querySelectorAll('.nav-a')[5])" style="color:inherit;font-weight:700;margin-left:6px;text-decoration:underline">Review now →</a>
        </div>
        <?php endif; ?>

        <div class="chart-grid g21">
            <div class="card">
                <div class="card-title">Methane Trend — All Users</div>
                <div class="card-sub">Recent CH₄ readings across all user sensors</div>
                <div class="ch"><canvas id="ch-meth-ov"></canvas></div>
            </div>
            <div class="card">
                <div class="card-title">Methane Status Split</div>
                <div class="card-sub">Safe / Warning / Leak</div>
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
            <div class="tbl-head"><h3>Recent User Activity</h3><span>Latest 5 events</span></div>
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
                <?php if(empty($logs)): ?>
                <tr><td colspan="5"><div class="empty"><div class="ei"></div><p>No activity yet.</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>




    <!-- ══ METHANE ══ -->
    <div class="sec" id="s-methane">
        <div class="readonly-notice">Sensor data is submitted by users from their own devices. This is a read-only view.</div>
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
            <div class="card"><div class="card-title">Methane PPM Over Time</div><div class="card-sub">All user sensors</div><div class="ch tall"><canvas id="ch-meth-det"></canvas></div></div>
            <div class="card"><div class="card-title">Alert Distribution</div><div class="card-sub">Status breakdown</div><div class="ch tall"><canvas id="ch-meth-donut2"></canvas></div></div>
        </div>
        <div class="tbl-card">
            <div class="tbl-head"><h3>Methane Records</h3><span><?php echo count($methane); ?> readings — all users</span></div>
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
        <div class="readonly-notice">Sensor data is submitted by users from their own devices. This is a read-only view.</div>
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
            <div class="tbl-head"><h3>Gas Level Records</h3><span><?php echo count($gasLevel); ?> readings — all users</span></div>
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
        <div class="readonly-notice">Sensor data is submitted by users from their own devices. This is a read-only view.</div>
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
            <div class="card"><div class="card-title">Gas Consumed Over Time</div><div class="card-sub">All user sensors</div><div class="ch tall"><canvas id="ch-gasused"></canvas></div></div>
            <div class="card"><div class="card-title">Flow Rate Over Time</div><div class="card-sub">All user sensors</div><div class="ch tall"><canvas id="ch-flowrate"></canvas></div></div>
        </div>
        <div class="tbl-card">
            <div class="tbl-head"><h3>Gas Usage Records</h3><span><?php echo count($gasUsage); ?> readings — all users</span></div>
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




    <!-- ══ USERS & VERIFICATION ══ -->
    <div class="sec" id="s-users">
        <div class="kpi-row">
            <div class="kpi"><div class="kpi-ico"></div><div class="kpi-val"><?php echo $totalUsers; ?></div><div class="kpi-lbl">Total Users</div></div>
            <div class="kpi grn"><div class="kpi-ico"></div><div class="kpi-val"><?php echo $verifiedCount; ?></div><div class="kpi-lbl">Verified</div><div class="kpi-sub">Cleared to use the system</div></div>
            <div class="kpi <?php echo $unverifiedCount>0?'amber':''; ?>">
                <div class="kpi-ico"></div><div class="kpi-val"><?php echo $unverifiedCount; ?></div>
                <div class="kpi-lbl">Awaiting Verification</div>
                <div class="kpi-sub"><?php echo $unverifiedCount>0?'Action required':'All clear'; ?></div>
            </div>
        </div>

        <div class="tbl-card">
            <div class="tbl-head">
                <h3>User Accounts</h3>
                <span>Click Verify or Unverify to update account status — action is logged</span>
            </div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>ID</th><th>Email</th><th>Status</th><th>Joined</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td><?php echo $u['user_id']; ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td>
                        <span class="bdg <?php echo $u['verified']?'b-verified':'b-unverified'; ?>">
                            <?php echo $u['verified']?'✓ Verified':'⏳ Unverified'; ?>
                        </span>
                    </td>
                    <td><?php echo $u['created_at']; ?></td>
                    <td>
                        <?php if(!$u['verified']): ?>
                        <button class="btn-verify"
                            onclick="openModal(<?php echo $u['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($u['email'])); ?>', 'verify')">
                            ✓ Verify
                        </button>
                        <?php else: ?>
                        <button class="btn-unverify"
                            onclick="openModal(<?php echo $u['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($u['email'])); ?>', 'unverify')">
                            ✕ Unverify
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($users)): ?>
                <tr><td colspan="5"><div class="empty"><div class="ei"></div><p>No users found.</p></div></td></tr>
                <?php endif; ?>
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
            <div class="tbl-head"><h3>User Activity Logs</h3><span>Last 20 entries</span></div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>ID</th><th>Email</th><th>Activity</th><th>IP Address</th><th>Timestamp</th></tr></thead>
                <tbody>
                <?php foreach($logs as $lg):
                    $cls=str_contains($lg['activity'],'Failed')?'failed':(str_contains($lg['activity'],'logged in')?'login':'logout'); ?>
                <tr>
                    <td><?php echo $lg['id']; ?></td>
                    <td><?php echo htmlspecialchars($lg['email']); ?></td>
                    <td><span class="bdg b-<?php echo $cls; ?>"><?php echo htmlspecialchars($lg['activity']); ?></span></td>
                    <td><?php echo htmlspecialchars($lg['ip_address']); ?></td>
                    <td><?php echo $lg['created_at']; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($logs)): ?><tr><td colspan="5"><div class="empty"><div class="ei"></div><p>No activity yet.</p></div></td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>

    </div><!-- /content -->
</div><!-- /main -->
</div><!-- /shell -->




<!-- ══ CONFIRM MODAL ══ -->
<div class="modal-bg" id="modal">
    <div class="modal">
        <h3 id="modal-title">Confirm Action</h3>
        <p id="modal-body">Are you sure?</p>
        <form method="POST" id="modal-form">
            <input type="hidden" name="action"         id="modal-action">
            <input type="hidden" name="target_user_id" id="modal-uid">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" id="modal-confirm">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>



// ── Navigation ──────────────────────────────────────────────────────────────
const TITLES={overview:'Dashboard',methane:'Methane — All Users',gaslevel:'Gas Level — All Users',gasusage:'Gas Usage — All Users',users:'Users & Verification',logs:'Activity Logs'};
function go(id,el){
    document.querySelectorAll('.sec').forEach(s=>s.classList.remove('on'));
    document.querySelectorAll('.nav-a').forEach(n=>n.classList.remove('active'));
    document.getElementById('s-'+id).classList.add('on');
    if(el) el.classList.add('active');
    document.getElementById('pg-title').textContent=TITLES[id];
}




// ── Clock ───────────────────────────────────────────────────────────────────
function tick(){const n=new Date();document.getElementById('clk').textContent=n.toLocaleDateString('en-PH',{weekday:'short',month:'short',day:'numeric'})+' '+n.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});}
tick();setInterval(tick,1000);




// ── Auto-dismiss action message ──────────────────────────────────────────────
const msg=document.getElementById('action-msg');
if(msg) setTimeout(()=>{msg.style.opacity='0';msg.style.transition='opacity .4s';setTimeout(()=>msg.remove(),400);},4000);




// ── Confirm Modal ────────────────────────────────────────────────────────────
function openModal(uid, email, action){
    document.getElementById('modal-uid').value    = uid;
    document.getElementById('modal-action').value = action;
    const isVerify = action === 'verify';
    document.getElementById('modal-title').textContent = isVerify ? 'Verify Account' : 'Unverify Account';
    document.getElementById('modal-body').innerHTML    = isVerify
        ? `This will mark <strong>${email}</strong> as verified, granting them full access to their dashboard.`
        : `This will revoke verified status from <strong>${email}</strong>. They may lose access to parts of the system.`;
    const btn = document.getElementById('modal-confirm');
    btn.textContent  = isVerify ? 'Yes, Verify' : 'Yes, Unverify';
    btn.className    = isVerify ? 'btn-confirm-verify' : 'btn-confirm-unverify';
    document.getElementById('modal').classList.add('open');
}
function closeModal(){document.getElementById('modal').classList.remove('open');}
document.getElementById('modal').addEventListener('click', e=>{ if(e.target===e.currentTarget) closeModal(); });




// ── Charts ───────────────────────────────────────────────────────────────────
Chart.defaults.font.family="'DM Sans',sans-serif";Chart.defaults.font.size=12;Chart.defaults.color='#3a5570';
const C={blue:'#4a90b8',mid:'#2a5070',beige:'#b5a48a',safe:'#277a44',warn:'#c96a08',danger:'#b83225'};
const mLbls=<?php echo $methaneLabels;?>,mPpm=<?php echo $methanePpm;?>;
const gLbls=<?php echo $gasLvlLabels;?>,gPress=<?php echo $gasPressure;?>,gPct=<?php echo $gasPct;?>;
const uLbls=<?php echo $gasUseLabels;?>,uUsed=<?php echo $gasUsedArr;?>,uFlow=<?php echo $flowRateArr;?>;
const sStat=<?php echo $statusJson;?>;
function line(id,labels,datasets){const el=document.getElementById(id);if(!el)return;new Chart(el,{type:'line',data:{labels,datasets},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:datasets.length>1,position:'bottom',labels:{boxWidth:10,padding:14,usePointStyle:true}}},scales:{x:{grid:{color:'rgba(74,144,184,.12)'},ticks:{maxTicksLimit:8,maxRotation:0}},y:{grid:{color:'rgba(74,144,184,.15)'},beginAtZero:false}}}});}
function donut(id,data,labels,colors){const el=document.getElementById(id);if(!el)return;new Chart(el,{type:'doughnut',data:{labels,datasets:[{data,backgroundColor:colors,borderWidth:2,borderColor:'#faf6ee',hoverOffset:6}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{boxWidth:10,padding:14,usePointStyle:true}}},cutout:'62%'}});}
const DS=(color,label,data)=>({label,data,borderColor:color,backgroundColor:color+'28',fill:true,tension:0.42,pointBackgroundColor:color,pointRadius:3,borderWidth:2});
line('ch-meth-ov',   mLbls,[DS(C.blue,'CH₄ ppm',mPpm)]);
donut('ch-meth-donut-ov',sStat,['Safe','Warning','Leak'],[C.safe,C.warn,C.danger]);
line('ch-gaslvl-ov', gLbls,[DS(C.blue,'Gas %',gPct),DS(C.beige,'Pressure kPa',gPress)]);
line('ch-gasuse-ov', uLbls,[DS(C.mid,'Gas Used m³',uUsed)]);
line('ch-meth-det',  mLbls,[DS(C.blue,'CH₄ ppm',mPpm)]);
donut('ch-meth-donut2',sStat,['Safe','Warning','Leak'],[C.safe,C.warn,C.danger]);
line('ch-gaspct',    gLbls,[DS(C.blue,'Gas %',gPct)]);
line('ch-pressure',  gLbls,[DS(C.beige,'Pressure kPa',gPress)]);
line('ch-gasused',   uLbls,[DS(C.blue,'Gas Used m³',uUsed)]);
line('ch-flowrate',  uLbls,[DS(C.mid,'Flow Rate',uFlow)]);
</script>
</body>
</html>