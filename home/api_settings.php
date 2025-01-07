<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: ../admin.php");
    exit;
}

require_once '../config.php';

// 处理API设置更新
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_api'])){
    try {
        $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 更新API状态
        $api_enabled = isset($_POST['api_enabled']) ? '1' : '0';
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'api_enabled'");
        $stmt->execute([$api_enabled]);
        
        // 如果需要重新生成API密钥
        if(isset($_POST['regenerate_key'])) {
            $new_api_key = bin2hex(random_bytes(16));
            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'api_key'");
            $stmt->execute([$new_api_key]);
        }
        
        $success = "API设置更新成功";
    } catch(PDOException $e) {
        $error = "系统错误，请稍后再试";
    }
}

// 获取当前API设置
try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $stmt = $conn->prepare("SELECT name, value FROM settings WHERE name IN ('api_enabled', 'api_key')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $api_enabled = $settings['api_enabled'] ?? '0';
    $api_key = $settings['api_key'] ?? '';
} catch(PDOException $e) {
    $api_enabled = '0';
    $api_key = '';
}

// 获取API调用统计
try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $total_calls = $conn->query("SELECT COUNT(*) FROM cards WHERE verify_method IN ('post', 'get')")->fetchColumn();
    $today_calls = $conn->query("SELECT COUNT(*) FROM cards WHERE verify_method IN ('post', 'get') AND DATE(use_time) = CURDATE()")->fetchColumn();
} catch(PDOException $e) {
    $total_calls = 0;
    $today_calls = 0;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>API设置 - 卡密验证系统</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* 侧边栏样式 */
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
            border-bottom: 1px solid rgba(255,255,255,0.1);
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
            background: rgba(255,255,255,0.1);
        }

        .sidebar .menu li.active a {
            background: #3498db;
        }

        /* 主内容区域样式 */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
            position: relative;
            padding-bottom: 60px;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            background: #f5f6fa;
            min-height: calc(100vh - 60px);
        }

        /* 页脚样式 */
        .footer-copyright {
            position: fixed;
            bottom: 0;
            left: 250px;
            right: 0;
            padding: 15px 0;
            background: #f8f9fa;
            color: #6c757d;
            text-align: center;
            border-top: 1px solid #dee2e6;
            z-index: 1000;
        }

        /* API特定样式 */
        .api-key-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        .api-key {
            flex: 1;
            padding: 10px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-family: monospace;
        }

        .copy-btn {
            padding: 10px 15px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .copy-btn:hover {
            background: #2980b9;
        }

        .api-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card i {
            font-size: 24px;
            color: #3498db;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }

        .stat-card .label {
            color: #7f8c8d;
            margin-top: 5px;
        }

        /* 卡片样式 */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }

        .card-header h3 {
            margin: 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* 表单样式 */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        /* 提示框样式 */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* 添加开关按钮样式 */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
            margin-right: 10px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #2ecc71;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .api-status {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .status-text {
            font-size: 16px;
            color: #666;
        }

        .status-text.enabled {
            color: #2ecc71;
        }

        .status-text.disabled {
            color: #e74c3c;
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
                <li><a href="stats.php"><i class="fas fa-chart-line"></i>数据统计</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i>系统设置</a></li>
                <li class="active"><a href="api_settings.php"><i class="fas fa-code"></i>API接口</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i>退出登录</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h2><i class="fas fa-code"></i> API接口设置</h2>
            </div>
            
            <?php 
            if(isset($success)) echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> $success</div>";
            if(isset($error)) echo "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> $error</div>";
            ?>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-cog"></i> API配置</h3>
                </div>
                <form method="POST" class="form-group" style="padding: 20px;">
                    <div class="form-group">
                        <div class="api-status">
                            <label class="switch">
                                <input type="checkbox" name="api_enabled" <?php echo $api_enabled == '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <span class="status-text <?php echo $api_enabled == '1' ? 'enabled' : 'disabled'; ?>">
                                API接口当前已<?php echo $api_enabled == '1' ? '启用' : '禁用'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>API密钥：</label>
                        <div class="api-key-container">
                            <div class="api-key"><?php echo htmlspecialchars($api_key); ?></div>
                            <button type="button" class="copy-btn" onclick="copyApiKey()">
                                <i class="fas fa-copy"></i> 复制
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="regenerate_key">
                            重新生成API密钥
                        </label>
                    </div>
                    
                    <button type="submit" name="update_api" class="btn btn-primary">
                        <i class="fas fa-save"></i> 保存设置
                    </button>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> API调用统计</h3>
                </div>
                <div class="api-stats" style="padding: 20px;">
                    <div class="stat-card">
                        <i class="fas fa-clock"></i>
                        <div class="value"><?php echo $today_calls; ?></div>
                        <div class="label">今日调用次数</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-history"></i>
                        <div class="value"><?php echo $total_calls; ?></div>
                        <div class="label">总调用次数</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-book"></i> API 调用文档</h3>
                </div>
                <div class="api-docs" style="padding: 20px;">
                    <div class="doc-section">
                        <h4>接口说明</h4>
                        <p>本系统提供卡密验证功能，支持 POST 和 GET 两种请求方式。</p>
                        
                        <h4>接口地址</h4>
                        <div class="info-item">
                            <i class="fas fa-link"></i>
                            <label>API接口地址：</label>
                            <span><?php echo rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]", '/'); ?>/api/verify.php</span>
                        </div>

                        <h4>POST 方式调用</h4>
                        <div class="info-item">
                            <pre><code>POST /api/verify.php
Content-Type: application/json
X-API-KEY: <?php echo htmlspecialchars($api_key); ?>

{
    "card_key": "您的卡密"
}</code></pre>
                        </div>

                        <h4>GET 方式调用</h4>
                        <div class="info-item">
                            <pre><code>GET /api/verify.php?card_key=您的卡密&api_key=<?php echo htmlspecialchars($api_key); ?></code></pre>
                        </div>

                        <h4>返回示例</h4>
                        <div class="info-item">
                            <pre><code>// 验证成功
{
    "code": 0,
    "message": "验证成功",
    "data": {
        "card_key": "xxx",
        "status": 1,
        "use_time": "2024-xx-xx xx:xx:xx",
        "expire_time": "2024-xx-xx xx:xx:xx",
        "duration": 30
    }
}

// 验证失败
{
    "code": 1,
    "message": "卡密无效或已被使用",
    "data": null
}</code></pre>
                        </div>

                        <h4>错误码说明</h4>
                        <table class="api-table">
                            <tr>
                                <th>错误码</th>
                                <th>说明</th>
                            </tr>
                            <tr>
                                <td>0</td>
                                <td>成功</td>
                            </tr>
                            <tr>
                                <td>1</td>
                                <td>卡密无效或已使用</td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>API接口未启用</td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>API密钥无效</td>
                            </tr>
                            <tr>
                                <td>4</td>
                                <td>请求参数错误</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer-copyright">
        <div class="container">
            &copy; <?php echo date('Y'); ?> 小小怪卡密系统 - All Rights Reserved
        </div>
    </footer>

    <script>
        function copyApiKey() {
            const apiKey = document.querySelector('.api-key').textContent;
            navigator.clipboard.writeText(apiKey).then(() => {
                const btn = document.querySelector('.copy-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> 已复制';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                }, 2000);
            });
        }
    </script>
</body>
</html> 