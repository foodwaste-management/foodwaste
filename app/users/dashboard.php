<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "foodwaste_db");

$user_id = (int)$_SESSION['user_id'];
$email   = $_SESSION['email'] ?? 'user@example.com';

// Handle logout
if (isset($_POST['logout'])) {
    $check = $conn->query("SELECT user_id FROM users WHERE user_id = $user_id LIMIT 1");
    if ($check && $check->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, email, activity, activity_type, ip_address) VALUES (?, ?, 'User logged out', 'login', ?)");
        $stmt->bind_param("iss", $user_id, $email, $_SERVER['REMOTE_ADDR']);
        $stmt->execute();
        $stmt->close();
    }
    session_destroy();
    header("Location: index.php");
    exit;
}

// ── Fetch latest methane reading from sensor ──────────────────────
$methane_level  = 0; // default if no sensor data yet
$methane_status = 'SAFE';
$r = $conn->query("SELECT methane_ppm, status FROM methane_monitoring WHERE user_id = $user_id ORDER BY recorded_at DESC LIMIT 1");
if ($r && $row = $r->fetch_assoc()) {
    $methane_level  = $row['methane_ppm'];
    $methane_status = $row['status'];
}

// ── Fetch latest gas level from sensor ───────────────────────────
$gas_left = 0; // default if no sensor data yet
$r2 = $conn->query("SELECT gas_percentage FROM gas_level WHERE user_id = $user_id ORDER BY recorded_at DESC LIMIT 1");
if ($r2 && $row2 = $r2->fetch_assoc()) {
    $gas_left = $row2['gas_percentage'];
}

// ── Fetch last 10 gas usage entries for chart ─────────────────────
$chart_labels = [];
$chart_data   = [];
$r3 = $conn->query("SELECT flow_rate, recorded_at FROM gas_usage WHERE user_id = $user_id ORDER BY recorded_at DESC LIMIT 10");
if ($r3) {
    $rows = [];
    while ($row3 = $r3->fetch_assoc()) $rows[] = $row3;
    $rows = array_reverse($rows);
    foreach ($rows as $row3) {
        $chart_labels[] = date('H:i:s', strtotime($row3['recorded_at']));
        $chart_data[]   = $row3['flow_rate'];
    }
}

// ── DEMO FALLBACK DATA (commented out — uncomment for testing) ────
/*
if (empty($chart_labels)) {
    $now = time();
    for ($i = 9; $i >= 0; $i--) {
        $chart_labels[] = date('H:i:s', $now - $i * 60);
        $chart_data[]   = rand(4, 12);
    }
}
*/

$chart_labels_json = json_encode($chart_labels);
$chart_data_json   = json_encode($chart_data);

// ── Methane display values ────────────────────────────────────────
$methane_display = min($methane_level, 100);
$methane_color   = $methane_level > 50 ? '#dc2626' : ($methane_level > 20 ? '#d97706' : '#16a34a');
$methane_label   = $methane_level > 50 ? '⚠ Leak Detected' : ($methane_level > 20 ? '⚡ Caution' : '✔ Normal');
$methane_class   = $methane_level > 50 ? 'status-danger' : ($methane_level > 20 ? 'status-warn' : 'status-safe');

// ── Gas display values ────────────────────────────────────────────
$gas_color = $gas_left < 20 ? '#dc2626' : ($gas_left < 40 ? '#d97706' : '#16a34a');
$gas_label = $gas_left < 20 ? '⚠ Critical Low' : ($gas_left < 40 ? '⚡ Running Low' : '✔ Sufficient');
$gas_class = $gas_left < 20 ? 'status-danger' : ($gas_left < 40 ? 'status-warn' : 'status-safe');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Biogas Monitoring Dashboard</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body>

<!-- Header -->
<header class="header">
    <div class="header-left">
        <div class="live-dot"></div>
        <h1>Biogas Monitoring Dashboard</h1>
    </div>
    <div class="header-right">
        <span class="user-chip"><?php echo htmlspecialchars($email); ?></span>
        <form method="POST" style="margin:0">
            <a href="/foodwaste/logout.php" class="btn-logout">Logout</a>
        </form>
    </div>
</header>

<!-- Main -->
<main class="container">

    <!-- Real-Time Gas Usage -->
    <div class="card">
        <div class="card-title">
            Real-Time Gas Usage
            <span class="live-badge"><span class="live-badge-dot"></span>Live</span>
        </div>
        <div class="chart-meta">
            <div class="meta-item">
                <span class="meta-label">Latest Flow</span>
                <span class="meta-val" id="latest-val">—<span> m³/h</span></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Last Updated</span>
                <span id="live-timestamp">—</span>
            </div>
        </div>
        <div class="chart-canvas-wrap">
            <?php if (!empty($chart_labels)): ?>
                <canvas id="usageChart"></canvas>
            <?php else: ?>
                <div class="no-data">No gas usage data from sensors yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Gauge Row -->
    <div class="grid-2">

        <!-- Methane Gauge -->
        <div class="card">
            <div class="card-title">Methane Leak Monitoring</div>
            <div class="gauge-wrap">
                <svg class="gauge-svg" width="200" height="130" viewBox="0 0 200 120">
                    <path class="gauge-track" d="M 20,110 A 80,80 0 0,1 180,110"/>
                    <path class="gauge-fill" id="methane-fill"
                        d="M 20,110 A 80,80 0 0,1 180,110"
                        style="stroke:<?php echo $methane_color?>"/>
                    <text class="gauge-center-text" x="100" y="96"><?php echo $methane_level?></text>
                    <text class="gauge-unit" x="100" y="114">ppm</text>
                </svg>
                <span class="gauge-status <?php echo $methane_class?>"><?php echo $methane_label?></span>
            </div>
        </div>

        <!-- Gas Level Gauge -->
        <div class="card">
            <div class="card-title">Gas Remaining in Barrel</div>
            <div class="gauge-wrap">
                <svg class="gauge-svg" width="200" height="130" viewBox="0 0 200 120">
                    <path class="gauge-track" d="M 20,110 A 80,80 0 0,1 180,110"/>
                    <path class="gauge-fill" id="gas-fill"
                        d="M 20,110 A 80,80 0 0,1 180,110"
                        style="stroke:<?php echo $gas_color?>"/>
                    <text class="gauge-center-text" x="100" y="96"><?php echo $gas_left?></text>
                    <text class="gauge-unit" x="100" y="114">%</text>
                </svg>
                <span class="gauge-status <?php echo $gas_class?>"><?php echo $gas_label?></span>
            </div>
        </div>

    </div>
</main>

<script>
// ── Gauge arcs ────────────────────────────────────────────────────
(function () {
    const arcLen = Math.PI * 80;

    function setGauge(id, pct) {
        const p = document.getElementById(id);
        if (!p) return;
        p.style.strokeDasharray  = arcLen;
        p.style.strokeDashoffset = arcLen - pct * arcLen;
    }

    setGauge('methane-fill', Math.min(<?php echo $methane_display?>, 100) / 100);
    setGauge('gas-fill',     Math.min(Math.max(<?php echo $gas_left?>, 0), 100) / 100);
})();

// ── Chart (only renders if real sensor data exists) ───────────────
<?php if (!empty($chart_labels)): ?>

const labels     = <?php echo $chart_labels_json?>;
const dataPoints = <?php echo $chart_data_json?>;

const ctx = document.getElementById('usageChart').getContext('2d');

const gradient = ctx.createLinearGradient(0, 0, 0, 210);
gradient.addColorStop(0, 'rgba(22,163,74,0.18)');
gradient.addColorStop(1, 'rgba(22,163,74,0)');

const chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Flow Rate (m³/h)',
            data: dataPoints,
            borderColor: '#16a34a',
            backgroundColor: gradient,
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: '#16a34a',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            fill: true,
            tension: 0.4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 350 },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#ffffff',
                borderColor: '#e2d9c8',
                borderWidth: 1,
                titleColor: '#a0aec0',
                bodyColor: '#1a202c',
                titleFont: { family: 'Inter', size: 11 },
                bodyFont:  { family: 'Inter', size: 13, weight: '600' },
                callbacks: { label: c => ` ${c.parsed.y} m³/h` }
            }
        },
        scales: {
            x: {
                grid:  { color: '#f0ebe0' },
                ticks: { color: '#a0aec0', font: { family: 'Inter', size: 10 }, maxRotation: 0 }
            },
            y: {
                grid:  { color: '#f0ebe0' },
                ticks: { color: '#a0aec0', font: { family: 'Inter', size: 10 } },
                beginAtZero: true
            }
        }
    }
});

// Seed latest value from DB data
const lastVal = dataPoints[dataPoints.length - 1];
document.getElementById('latest-val').innerHTML = lastVal + '<span> m³/h</span>';

<?php endif; ?>

// ── Live timestamp (always runs) ──────────────────────────────────
function nowStr() {
    return new Date().toLocaleTimeString('en-GB', { hour12: false });
}
document.getElementById('live-timestamp').textContent = nowStr();

// ── DEMO: simulated live push (commented out — uncomment for testing) ──
/*
function pushLivePoint() {
    const newVal   = +(Math.random() * 6 + 4).toFixed(1);
    const nowLabel = nowStr();

    chart.data.labels.push(nowLabel);
    chart.data.datasets[0].data.push(newVal);

    if (chart.data.labels.length > 20) {
        chart.data.labels.shift();
        chart.data.datasets[0].data.shift();
    }

    chart.update();
    document.getElementById('latest-val').innerHTML = newVal + '<span> m³/h</span>';
    document.getElementById('live-timestamp').textContent = nowStr();
}
setInterval(pushLivePoint, 5000);
*/
</script>
</body>
</html>