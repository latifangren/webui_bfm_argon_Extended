<?php
// Script pingmonitor.php untuk memantau pingloop.sh
// Dibuat untuk lingkungan Android

// Konfigurasi
$logFile = "/data/local/tmp/pingloop.log";  // Lokasi file log
$scriptPath = "/data/adb/service.d/pingloop.sh";  // Lokasi script pingloop.sh yang benar
$refreshInterval = 5;  // Interval refresh dalam detik

// Fungsi untuk memeriksa apakah script pingloop.sh sedang berjalan
function isScriptRunning() {
    $output = [];
    // Coba beberapa pendekatan untuk mendeteksi script yang berjalan
    exec("ps -ef | grep pingloop.sh | grep -v grep", $output);
    if (!empty($output)) return true;
    
    // Pendekatan alternatif jika yang pertama gagal
    $output = [];
    exec("ps | grep pingloop.sh | grep -v grep", $output);
    if (!empty($output)) return true;
    
    // Pendekatan alternatif lain
    $output = [];
    exec("pgrep -f pingloop.sh", $output);
    
    return !empty($output);
}

// Fungsi untuk mendapatkan status mode pesawat
function getAirplaneMode() {
    $output = [];
    exec("settings get global airplane_mode_on", $output);
    return trim($output[0]) == "1" ? true : false;
}

// Fungsi untuk mendapatkan log dari pingloop.sh
function getLogContent($logFile, $lines = 20) {
    if (!file_exists($logFile)) {
        return "File log tidak ditemukan";
    }
    
    $output = [];
    exec("tail -n $lines $logFile", $output);
    return implode("\n", $output);
}

// Fungsi untuk memulai script jika belum berjalan
function startScript($scriptPath) {
    exec("/system/bin/sh $scriptPath > $GLOBALS[logFile] 2>&1 &");
    return "Script pingloop.sh dijalankan";
}

// Fungsi untuk menghentikan script
function stopScript() {
    exec("pkill -f pingloop.sh");
    return "Script pingloop.sh dihentikan";
}

// Fungsi untuk ekstrak data ping untuk grafik
function extractPingData($logContent) {
    $data = [];
    $times = [];
    $lines = explode("\n", $logContent);
    foreach ($lines as $line) {
        if (strpos($line, "Host dapat dijangkau") !== false) {
            // Ekstrak timestamp dan status
            if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
                $timestamp = $matches[1];
                $times[] = $timestamp;
                $data[] = 1; // 1 untuk sukses
            }
        } elseif (strpos($line, "Host tidak dapat dijangkau") !== false) {
            // Ekstrak timestamp dan status
            if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
                $timestamp = $matches[1];
                $times[] = $timestamp;
                $data[] = 0; // 0 untuk gagal
            }
        }
    }
    return ['times' => $times, 'data' => $data];
}

// Fungsi untuk mendapatkan host yang dipantau dari script pingloop.sh
function getMonitoredHost($scriptPath) {
    if (!file_exists($scriptPath)) {
        return "N/A";
    }
    
    $content = file_get_contents($scriptPath);
    if (preg_match('/HOST="([^"]+)"/', $content, $matches)) {
        return $matches[1];
    }
    
    return "N/A";
}

// Fungsi untuk mendapatkan direktori script
function getScriptDirectory($scriptPath) {
    return dirname($scriptPath);
}

// Handler untuk permintaan AJAX
if (isset($_GET['action']) && $_GET['action'] == 'get_status') {
    $scriptRunning = isScriptRunning();
    $airplaneMode = getAirplaneMode();
    $logContent = getLogContent($logFile);
    $pingData = extractPingData($logContent);
    $monitoredHost = getMonitoredHost($scriptPath);
    $scriptDirectory = getScriptDirectory($scriptPath);
    
    header('Content-Type: application/json');
    echo json_encode([
        'scriptRunning' => $scriptRunning,
        'airplaneMode' => $airplaneMode,
        'logContent' => $logContent,
        'pingData' => $pingData,
        'monitoredHost' => $monitoredHost,
        'scriptDirectory' => $scriptDirectory,
        'scriptPath' => $scriptPath,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Menangani permintaan aksi
$message = "";
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'start':
            $message = startScript($scriptPath);
            break;
        case 'stop':
            $message = stopScript();
            break;
        case 'restart':
            stopScript();
            sleep(1);
            $message = startScript($scriptPath);
            break;
    }
}

// Dapatkan status terkini
$scriptRunning = isScriptRunning();
$airplaneMode = getAirplaneMode();
$logContent = getLogContent($logFile);
$pingData = extractPingData($logContent);
$monitoredHost = getMonitoredHost($scriptPath);
$scriptDirectory = getScriptDirectory($scriptPath);

// HTML dan tampilan interface
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemantau Ping</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-color: #f8f9fa;
            --card-bg: white;
            --text-color: #333;
            --header-color: #3a76f8;
            --shadow-color: rgba(0,0,0,0.08);
            --border-color: #ddd;
            --subtitle-color: #777;
            --running-bg: #e0f7ea;
            --running-color: #00a854;
            --stopped-bg: #ffebef;
            --stopped-color: #f5222d;
            --airplane-on-bg: #fff7e6;
            --airplane-on-color: #fa8c16;
            --airplane-off-bg: #e6f7ff;
            --airplane-off-color: #1890ff;
            --log-bg: #f8f9fa;
            --success-color: #52c41a;
            --error-color: #f5222d;
            --chart-grid: rgba(0, 0, 0, 0.05);
        }

        .dark-mode {
            --bg-color: #1f1f1f;
            --card-bg: #2d2d2d;
            --text-color: #f0f0f0;
            --header-color: #4a83f8;
            --shadow-color: rgba(0,0,0,0.2);
            --border-color: #444;
            --subtitle-color: #aaa;
            --running-bg: #163228;
            --running-color: #52c41a;
            --stopped-bg: #3b1a1f;
            --stopped-color: #ff4d4f;
            --airplane-on-bg: #3b2e15;
            --airplane-on-color: #ffa940;
            --airplane-off-bg: #15233b;
            --airplane-off-color: #40a9ff;
            --log-bg: #2b2b2b;
            --success-color: #73d13d;
            --error-color: #ff7875;
            --chart-grid: rgba(255, 255, 255, 0.08);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
        }
        
        /* Container lebih compact */
        .container {
            max-width: 900px;
            margin: 10px auto;
            background: var(--card-bg);
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--shadow-color);
            transition: background-color 0.3s, box-shadow 0.3s;
        }
        
        /* Header lebih compact */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 15px;
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 2px 8px var(--shadow-color);
            margin-bottom: 10px;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo-icon {
            font-size: 20px;
            margin-right: 5px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 18px;
            background: linear-gradient(135deg, #4285f4, #34a853);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: 600;
        }
        
        .dark-mode .header h1 {
            background: linear-gradient(135deg, #4a83f8, #52c41a);
            -webkit-background-clip: text;
            background-clip: text;
        }
        
        .credit {
            display: flex;
            align-items: center;
            font-size: 0.75rem;
            margin-left: 8px;
            gap: 3px;
        }
        
        .credit-text {
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .credit-link {
            text-decoration: none;
            color: #4285f4;
            position: relative;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .credit-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background: linear-gradient(135deg, #4285f4, #34a853);
            transition: width 0.3s ease;
        }
        
        .credit-link:hover {
            color: #34a853;
        }
        
        .credit-link:hover::after {
            width: 100%;
        }
        
        .dark-mode-toggle {
            font-size: 18px;
            cursor: pointer;
            background: none;
            border: none;
            color: var(--text-color);
            transition: transform 0.3s, color 0.3s;
        }
        
        .dark-mode-toggle:hover {
            transform: rotate(30deg);
        }
        
        /* Redesain status panel untuk lebih compact */
        .status-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .status-card {
            padding: 8px;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 1px 5px var(--shadow-color);
            background-color: var(--card-bg);
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 70px;
        }
        
        .card-icon {
            font-size: 16px;
            margin-bottom: 3px;
        }
        
        .status-title {
            font-size: 12px;
            margin-bottom: 4px;
            color: var(--subtitle-color);
        }
        
        .status-value {
            font-size: 13px;
            font-weight: bold;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        
        /* Controls lebih compact */
        .controls {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .controls button {
            padding: 6px 8px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .button-icon {
            margin-right: 3px;
            font-size: 14px;
        }
        
        /* Chart section lebih compact */
        .chart-section {
            margin: 10px 0;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--shadow-color);
            overflow: hidden;
        }
        
        .chart-header {
            background: linear-gradient(135deg, #4285f4, #34a853);
            padding: 8px 10px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .chart-header h2 {
            margin: 0;
            font-size: 16px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        
        .last-ping-status {
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        
        .chart-wrapper {
            padding: 10px;
            height: 200px; /* Reduced height */
            position: relative;
        }
        
        /* Log section lebih compact */
        .log-section {
            margin: 10px 0;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--shadow-color);
            overflow: hidden;
        }
        
        .log-section h2 {
            background: linear-gradient(135deg, #fbbc05, #ea4335);
            margin: 0;
            padding: 8px 10px;
            color: white;
            font-size: 16px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        
        .log-container {
            height: 200px; /* Reduced height */
            overflow-y: auto;
            padding: 8px;
            background-color: var(--log-bg);
            font-family: 'Courier New', monospace;
            font-size: 11px;
            border: none;
            transition: background-color 0.3s;
        }
        
        /* Path yang lebih compact */
        .path-text {
            font-size: 11px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Timestamp lebih compact */
        .timestamp {
            text-align: center;
            font-size: 11px;
            color: var(--subtitle-color);
            margin-top: 5px;
        }
        
        /* Miscellaneous settings */
        pre {
            margin: 0;
        }
        
        .message {
            margin: 10px 0;
            padding: 8px;
            border-radius: 6px;
            background-color: var(--running-bg);
            color: var(--running-color);
            text-align: center;
            opacity: 1;
            transition: opacity 1s ease-out;
        }
        
        /* Status colors */
        .running {
            background-color: var(--running-bg);
            color: var(--running-color);
        }
        .stopped {
            background-color: var(--stopped-bg);
            color: var(--stopped-color);
        }
        .airplane-on {
            background-color: var(--airplane-on-bg);
            color: var(--airplane-on-color);
        }
        .airplane-off {
            background-color: var(--airplane-off-bg);
            color: var(--airplane-off-color);
        }
        .success-ping {
            background-color: #34a853;
            color: white;
            box-shadow: 0 2px 5px rgba(52, 168, 83, 0.4);
        }
        .failed-ping {
            background-color: #ea4335;
            color: white;
            box-shadow: 0 2px 5px rgba(234, 67, 53, 0.4);
        }
        
        /* Animation */
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 170, 100, 0.4); }
            70% { box-shadow: 0 0 0 8px rgba(0, 170, 100, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 170, 100, 0); }
        }
        
        /* Log highlight */
        .log-line-success {
            color: var(--success-color);
        }
        .log-line-error {
            color: var(--error-color);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .status-panel {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
            .controls {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                margin: 5px;
                padding: 8px;
            }
            .status-panel {
                grid-template-columns: repeat(2, 1fr);
            }
            .path-text {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <div class="logo-icon">📡</div>
                <h1>Ping Monitor</h1>
                <div class="credit">
                    <span class="credit-text">mod by</span>
                    <a href="https://t.me/latifan_id" class="credit-link" target="_blank">
                        latifan_id
                    </a>
                </div>
            </div>
            <button class="dark-mode-toggle" id="darkModeToggle">🌓</button>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message" id="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Status cards dalam grid layout -->
        <div class="status-panel">
            <div class="status-card <?php echo $scriptRunning ? 'running pulse' : 'stopped'; ?>" id="script-status">
                <div class="card-icon"><?php echo $scriptRunning ? '⚙️' : '⛔'; ?></div>
                <div class="status-title">Status Script</div>
                <div class="status-value" id="script-status-value">
                    <?php echo $scriptRunning ? 'Berjalan' : 'Berhenti'; ?>
                </div>
            </div>
            
            <div class="status-card <?php echo $airplaneMode ? 'airplane-on pulse' : 'airplane-off'; ?>" id="airplane-status">
                <div class="card-icon"><?php echo $airplaneMode ? '✈️' : '📱'; ?></div>
                <div class="status-title">Mode Pesawat</div>
                <div class="status-value" id="airplane-status-value">
                    <?php echo $airplaneMode ? 'Aktif' : 'Tidak Aktif'; ?>
                </div>
            </div>
            
            <div class="status-card" id="host-status">
                <div class="card-icon">🌐</div>
                <div class="status-title">Host</div>
                <div class="status-value" id="host-status-value">
                    <?php echo $monitoredHost; ?>
                </div>
            </div>
            
            <div class="status-card" id="script-directory">
                <div class="card-icon">📁</div>
                <div class="status-title">Direktori</div>
                <div class="status-value path-text" id="script-directory-value">
                    <?php echo $scriptDirectory; ?>
                </div>
            </div>
            
            <div class="status-card" id="script-path">
                <div class="card-icon">📄</div>
                <div class="status-title">Path Script</div>
                <div class="status-value path-text" id="script-path-value">
                    <?php echo $scriptPath; ?>
                </div>
            </div>
        </div>
        
        <!-- Tombol kontrol dalam grid layout -->
        <div class="controls">
            <form method="post" id="start-form">
                <input type="hidden" name="action" value="start">
                <button type="submit" class="start-btn" <?php echo $scriptRunning ? 'disabled' : ''; ?>>
                    <span class="button-icon">▶️</span> Mulai
                </button>
            </form>
            
            <form method="post" id="stop-form">
                <input type="hidden" name="action" value="stop">
                <button type="submit" class="stop-btn" <?php echo !$scriptRunning ? 'disabled' : ''; ?>>
                    <span class="button-icon">⏹️</span> Berhenti
                </button>
            </form>
            
            <form method="post" id="restart-form">
                <input type="hidden" name="action" value="restart">
                <button type="submit" class="restart-btn">
                    <span class="button-icon">🔄</span> Mulai Ulang
                </button>
            </form>
        </div>

        <!-- Chart section -->
        <div class="chart-section">
            <div class="chart-header">
                <h2>Statistik Ping</h2>
                <div class="last-ping-status" id="last-ping-status">
                    Ping Terakhir: Menunggu data...
                </div>
            </div>
            <div class="chart-wrapper">
                <canvas id="pingChart"></canvas>
            </div>
        </div>

        <!-- Log section -->
        <div class="log-section">
            <h2>Log Aktivitas</h2>
            <div class="log-container" id="log-container">
                <pre id="log-content"><?php echo $logContent; ?></pre>
            </div>
        </div>
        
        <div class="timestamp" id="timestamp">
            Terakhir diperbarui: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>

    <script>
        // Konfigurasi chart yang benar-benar baru
        const pingData = <?php echo json_encode($pingData); ?>;
        
        // Buat data untuk chart
        const labels = pingData.times.map(time => time.substr(11, 5));
        const data = pingData.data;
        
        // Siapkan warna dan border dari data ping
        const backgroundColors = data.map(status => 
            status === 1 ? 'rgba(52, 168, 83, 0.5)' : 'rgba(234, 67, 53, 0.5)'
        );
        const borderColors = data.map(status => 
            status === 1 ? 'rgb(52, 168, 83)' : 'rgb(234, 67, 53)'
        );
        
        // Buat chart baru dengan animasi dan styling yang lebih menarik
        const ctx = document.getElementById('pingChart').getContext('2d');
        const pingChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Status Ping',
                    data: data,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 3,
                    pointStyle: 'rectRounded',
                    pointRadius: 8,
                    pointHoverRadius: 12,
                    tension: 0.2,
                    fill: false,
                    stepped: 'before'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            size: 16
                        },
                        bodyFont: {
                            size: 14
                        },
                        padding: 15,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                return value === 1 ? '✅ Ping Berhasil' : '❌ Ping Gagal';
                            },
                            title: function(tooltipItems) {
                                return 'Waktu: ' + pingData.times[tooltipItems[0].dataIndex];
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: -0.1,
                        max: 1.1,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                if (value === 0) return '❌ Gagal';
                                if (value === 1) return '✅ Sukses';
                                return '';
                            },
                            font: {
                                size: 14
                            },
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                },
                elements: {
                    line: {
                        borderJoinStyle: 'round'
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
        
        // Fungsi untuk memperbarui grafik dengan style yang lebih menarik
        function updateChart(newData) {
            // Terbatas pada 20 data terakhir untuk tampilan yang lebih baik
            const maxDataPoints = 20;
            
            // Potong data jika lebih dari maxDataPoints
            let times = [...newData.times];
            let data = [...newData.data];
            if (times.length > maxDataPoints) {
                times = times.slice(-maxDataPoints);
                data = data.slice(-maxDataPoints);
            }
            
            // Persiapkan label dan warna
            const labels = times.map(time => time.substr(11, 5));
            const backgroundColors = data.map(status => 
                status === 1 ? 'rgba(52, 168, 83, 0.5)' : 'rgba(234, 67, 53, 0.5)'
            );
            const borderColors = data.map(status => 
                status === 1 ? 'rgb(52, 168, 83)' : 'rgb(234, 67, 53)'
            );
            
            // Update chart data
            pingChart.data.labels = labels;
            pingChart.data.datasets[0].data = data;
            pingChart.data.datasets[0].backgroundColor = backgroundColors;
            pingChart.data.datasets[0].borderColor = borderColors;
            pingChart.update();
            
            // Update status ping terakhir dengan animasi
            if (data.length > 0) {
                const lastStatus = data[data.length - 1];
                const lastPingStatus = document.getElementById('last-ping-status');
                
                // Tambahkan animasi sederhana
                lastPingStatus.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    lastPingStatus.style.transform = 'scale(1)';
                }, 300);
                
                lastPingStatus.className = lastStatus === 1 ? 'last-ping-status success-ping' : 'last-ping-status failed-ping';
                lastPingStatus.textContent = lastStatus === 1 ? '✅ Ping Terakhir: Berhasil' : '❌ Ping Terakhir: Gagal';
            }
        }
        
        // Fungsi untuk memperbarui status
        function updateStatus() {
            fetch('?action=get_status')
                .then(response => response.json())
                .then(data => {
                    // Update status script
                    const scriptRunning = data.scriptRunning;
                    document.getElementById('script-status-value').textContent = scriptRunning ? 'Berjalan' : 'Berhenti';
                    const scriptStatus = document.getElementById('script-status');
                    scriptStatus.className = `status-card ${scriptRunning ? 'running pulse' : 'stopped'}`;
                    document.querySelector('.start-btn').disabled = scriptRunning;
                    document.querySelector('.stop-btn').disabled = !scriptRunning;
                    
                    // Update status mode pesawat
                    const airplaneMode = data.airplaneMode;
                    document.getElementById('airplane-status-value').textContent = airplaneMode ? 'Aktif' : 'Tidak Aktif';
                    const airplaneStatus = document.getElementById('airplane-status');
                    airplaneStatus.className = `status-card ${airplaneMode ? 'airplane-on pulse' : 'airplane-off'}`;
                    
                    // Update log content dengan syntax highlighting
                    const logContainer = document.getElementById('log-content');
                    let formattedLog = '';
                    const lines = data.logContent.split('\n');
                    for (const line of lines) {
                        if (line.includes("Host dapat dijangkau")) {
                            formattedLog += `<span class="log-line-success">${escapeHtml(line)}</span>\n`;
                        } else if (line.includes("Host tidak dapat dijangkau")) {
                            formattedLog += `<span class="log-line-error">${escapeHtml(line)}</span>\n`;
                        } else {
                            formattedLog += escapeHtml(line) + '\n';
                        }
                    }
                    logContainer.innerHTML = formattedLog;
                    
                    // Auto-scroll log ke bawah
                    const logContainerDiv = document.getElementById('log-container');
                    logContainerDiv.scrollTop = logContainerDiv.scrollHeight;
                    
                    // Update timestamp
                    document.getElementById('timestamp').textContent = 'Terakhir diperbarui: ' + data.timestamp;
                    
                    // Update chart data jika ada data baru
                    if (data.pingData.times.length > 0) {
                        // Update grafik dengan data baru
                        updateChart(data.pingData);
                        
                        // Update direktori script
                        document.getElementById('script-directory-value').textContent = data.scriptDirectory;
                        document.getElementById('script-path-value').textContent = data.scriptPath;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
        
        // Fungsi untuk escape HTML entities
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        // Update status setiap 5 detik
        setInterval(updateStatus, <?php echo $refreshInterval * 1000; ?>);
        
        // Jalankan saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            // Efek fade-out untuk pesan
            const message = document.getElementById('message');
            if (message) {
                setTimeout(() => {
                    message.style.opacity = 0;
                }, 5000);
            }
            
            // Tambahkan event listener untuk form submission
            document.getElementById('start-form').addEventListener('submit', function(e) {
                e.preventDefault();
                fetch('', {
                    method: 'POST',
                    body: new FormData(this)
                }).then(() => {
                    updateStatus();
                });
            });
            
            document.getElementById('stop-form').addEventListener('submit', function(e) {
                e.preventDefault();
                fetch('', {
                    method: 'POST',
                    body: new FormData(this)
                }).then(() => {
                    updateStatus();
                });
            });
            
            document.getElementById('restart-form').addEventListener('submit', function(e) {
                e.preventDefault();
                fetch('', {
                    method: 'POST',
                    body: new FormData(this)
                }).then(() => {
                    updateStatus();
                });
            });
            
            // Auto-scroll log ke bawah
            const logContainer = document.getElementById('log-container');
            logContainer.scrollTop = logContainer.scrollHeight;
        });
        
        // Dark Mode
        const darkModeToggle = document.getElementById('darkModeToggle');
        const body = document.body;
        
        // Cek apakah pengguna sudah pernah mengaktifkan dark mode
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
            darkModeToggle.textContent = '☀️';
        } else {
            darkModeToggle.textContent = '🌙';
        }
        
        // Toggle dark mode
        darkModeToggle.addEventListener('click', function() {
            if (body.classList.contains('dark-mode')) {
                body.classList.remove('dark-mode');
                localStorage.setItem('darkMode', 'disabled');
                darkModeToggle.textContent = '🌙';
            } else {
                body.classList.add('dark-mode');
                localStorage.setItem('darkMode', 'enabled');
                darkModeToggle.textContent = '☀️';
            }
            
            // Perbarui grafik untuk menyesuaikan dengan tema
            pingChart.options.scales.y.grid.color = getComputedStyle(document.documentElement)
                .getPropertyValue('--chart-grid');
            pingChart.options.scales.x.grid.color = getComputedStyle(document.documentElement)
                .getPropertyValue('--chart-grid');
            pingChart.update();
        });
    </script>
</body>
</html> 