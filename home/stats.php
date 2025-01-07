<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: ../admin.php");
    exit;
}

require_once '../config.php';

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取总体统计
    $total = $conn->query("SELECT COUNT(*) FROM cards")->fetchColumn();
    $used = $conn->query("SELECT COUNT(*) FROM cards WHERE status = 1")->fetchColumn();
    $unused = $total - $used;
    $usage_rate = $total > 0 ? round(($used / $total) * 100, 1) : 0;
    
    // 获取今日数据
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    $today_used = $conn->query("SELECT COUNT(*) FROM cards WHERE status = 1 AND use_time BETWEEN '$today_start' AND '$today_end'")->fetchColumn();
    $today_created = $conn->query("SELECT COUNT(*) FROM cards WHERE create_time BETWEEN '$today_start' AND '$today_end'")->fetchColumn();
    
    // 获取最近7天的使用趋势
    $daily_stats = array();
    for($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $start = $date . ' 00:00:00';
        $end = $date . ' 23:59:59';
        $count = $conn->query("SELECT COUNT(*) FROM cards WHERE status = 1 AND use_time BETWEEN '$start' AND '$end'")->fetchColumn();
        $daily_stats[] = array(
            'date' => date('m-d', strtotime($date)),
            'count' => $count
        );
    }
    
} catch(PDOException $e) {
    $error = "系统错误，请稍后再试";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>数据统计 - 卡密验证系统</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* 统一的侧边栏样式 */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #2c3e50;
            position: fixed;
            left: 0;
            top: 0;
            color: #fff;
            z-index: 1000;
        }

        .sidebar .logo {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .logo h2 {
            margin: 0;
            font-size: 24px;
        }

        .sidebar .menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar .menu li {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar .menu li a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar .menu li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar .menu li a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar .menu li.active a {
            background: #3498db;
        }

        /* 主内容区域样式 */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            background: #f5f6fa;
            min-height: 100vh;
            position: relative;
        }

        /* 侧边栏版权信息样式 */
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 15px;
            background: rgba(0, 0, 0, 0.2);
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* 页面底部版权信息样式 */
        .footer-copyright {
            position: absolute;
            bottom: 0;
            right: 0;
            width: calc(100% - 250px);
            padding: 15px 0;
            background: #f8f9fa;
            color: #6c757d;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }

        /* 统计页面特定样式 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-card i {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .stat-card h3 {
            margin: 0;
            color: #666;
            font-size: 16px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table-responsive table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-responsive th,
        .table-responsive td {
            padding: 12px;
            border: 1px solid #eee;
            text-align: left;
        }

        .table-responsive th {
            background: #f8f9fa;
            font-weight: 600;
        }

        /* 图表容器样式 */
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            height: 400px;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <div class="sidebar">
            <div class="logo">
                <h2>管理系统</h2>
            </div>
            <ul class="menu">
                <li><a href="index.php"><i class="fas fa-key"></i>卡密管理</a></li>
                <li class="active"><a href="stats.php"><i class="fas fa-chart-line"></i>数据统计</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i>系统设置</a></li>
                <li><a href="api_settings.php"><i class="fas fa-code"></i>API接口</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i>退出登录</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h2><i class="fas fa-chart-line"></i> 数据统计</h2>
                <div class="user-info">
                    <img src="../assets/images/avatar.png" alt="avatar">
                    <span>欢迎，<?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-key fa-2x" style="color: #3498db; margin-bottom: 10px;"></i>
                    <h3>总卡密数</h3>
                    <div class="value"><?php echo $total; ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle fa-2x" style="color: #2ecc71; margin-bottom: 10px;"></i>
                    <h3>已使用</h3>
                    <div class="value"><?php echo $used; ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock fa-2x" style="color: #f1c40f; margin-bottom: 10px;"></i>
                    <h3>未使用</h3>
                    <div class="value"><?php echo $unused; ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-percentage fa-2x" style="color: #e74c3c; margin-bottom: 10px;"></i>
                    <h3>使用率</h3>
                    <div class="value"><?php echo $usage_rate; ?>%</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-day"></i> 今日数据</h3>
                </div>
                <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr);">
                    <div class="stat-card">
                        <i class="fas fa-check fa-2x" style="color: #27ae60; margin-bottom: 10px;"></i>
                        <h3>今日使用</h3>
                        <div class="value"><?php echo $today_used; ?></div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-plus fa-2x" style="color: #2980b9; margin-bottom: 10px;"></i>
                        <h3>今日生成</h3>
                        <div class="value"><?php echo $today_created; ?></div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-area"></i> 最近7天使用趋势</h3>
                </div>
                <div style="padding: 20px; height: 400px;">
                    <canvas id="usageChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> 最近使用记录</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th>卡密</th>
                            <th>使用时间</th>
                        </tr>
                        <?php
                        $stmt = $conn->query("SELECT card_key, use_time FROM cards 
                            WHERE status = 1 
                            ORDER BY use_time DESC 
                            LIMIT 10");
                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            $masked_key = substr($row['card_key'], 0, 4) . '****' . substr($row['card_key'], -4);
                        ?>
                        <tr>
                            <td><?php echo $masked_key; ?></td>
                            <td><?php echo $row['use_time']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 绘制使用趋势图
        const ctx = document.getElementById('usageChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($daily_stats, 'date')); ?>,
                datasets: [{
                    label: '每日使用量',
                    data: <?php echo json_encode(array_column($daily_stats, 'count')); ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    </script>
</body>
</html> 