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
    
    // 获取API调用统计数据
    // 今日调用次数
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    $stmt = $conn->prepare("SELECT SUM(use_count) FROM api_keys WHERE last_use_time BETWEEN ? AND ?");
    $stmt->execute([$today_start, $today_end]);
    $today_calls = (int)$stmt->fetchColumn() ?: 0;

    // 总调用次数
    $stmt = $conn->prepare("SELECT SUM(use_count) FROM api_keys");
    $stmt->execute();
    $total_calls = (int)$stmt->fetchColumn() ?: 0;

    // 获取示例API密钥（用于文档展示）
    $stmt = $conn->prepare("SELECT api_key FROM api_keys WHERE status = 1 ORDER BY use_count DESC LIMIT 1");
    $stmt->execute();
    $api_key = $stmt->fetchColumn();
    
    // 如果没有启用的API密钥，使用占位符
    if(!$api_key) {
        $api_key = 'your-api-key-here';
    }
    
    // 处理API状态更新
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_api'])) {
        $api_enabled = isset($_POST['api_enabled']) ? '1' : '0';
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'api_enabled'");
        $stmt->execute([$api_enabled]);
        $success = "API设置更新成功";
    }
    
    // 处理添加新API密钥
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_api_key'])) {
        $key_name = trim($_POST['key_name']);
        $description = trim($_POST['description']);
        $api_key = bin2hex(random_bytes(16)); // 生成32位随机密钥
        
        $stmt = $conn->prepare("INSERT INTO api_keys (key_name, api_key, description) VALUES (?, ?, ?)");
        $stmt->execute([$key_name, $api_key, $description]);
        $success = "API密钥添加成功";
    }
    
    // 处理API密钥状态更新
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_key_status'])) {
        $key_id = $_POST['key_id'];
        $new_status = $_POST['new_status'];
        
        $stmt = $conn->prepare("UPDATE api_keys SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $key_id]);
        $success = "API密钥状态已更新";
    }
    
    // 处理删除API密钥
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_key'])) {
        $key_id = $_POST['key_id'];
        
        $stmt = $conn->prepare("DELETE FROM api_keys WHERE id = ?");
        $stmt->execute([$key_id]);
        $success = "API密钥已删除";
    }
    
    // 获取API状态
    $stmt = $conn->prepare("SELECT value FROM settings WHERE name = 'api_enabled'");
    $stmt->execute();
    $api_enabled = $stmt->fetchColumn();
    
    // 获取所有API密钥
    $stmt = $conn->prepare("SELECT * FROM api_keys ORDER BY create_time DESC");
    $stmt->execute();
    $api_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "系统错误，请稍后再试";
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>API设置 - 卡密验证系统</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- 主要CDN：BootCDN -->
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- 备用CDN：七牛云 -->
    <!-- <link rel="stylesheet" href="https://cdn.staticfile.org/font-awesome/6.0.0/css/all.min.css"> -->
    
    <!-- 备用CDN：字节跳动 -->
    <!-- <link rel="stylesheet" href="https://lf26-cdn-tos.bytecdntp.com/cdn/expire-1-M/font-awesome/6.0.0/css/all.min.css"> -->
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

        .api-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .api-table th,
        .api-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .api-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .api-notes {
            margin: 15px 0;
            padding-left: 20px;
        }

        .api-notes li {
            margin: 8px 0;
            color: #666;
        }

        .info-item pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }

        .info-item code {
            font-family: monospace;
        }

        .api-key-form {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .status-btn {
            padding: 5px 15px;
            border: none;
            border-radius: 15px;
            cursor: pointer;
        }

        .status-btn.enabled {
            background: #2ecc71;
            color: white;
        }

        .status-btn.disabled {
            background: #e74c3c;
            color: white;
        }

        /* 添加错误码表格样式 */
        .api-table th,
        .api-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .api-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .api-table td {
            vertical-align: top;
        }

        .api-table tr:hover {
            background-color: #f8f9fa;
        }

        .api-table td:nth-child(1) {
            width: 80px;
            text-align: center;
            font-weight: bold;
        }

        .api-table td:nth-child(2) {
            width: 150px;
        }

        .api-table td:nth-child(3) {
            width: 100px;
            text-align: center;
        }

        .api-table td:nth-child(4) {
            min-width: 250px;
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
                        <div class="value"><?php echo number_format($today_calls); ?></div>
                        <div class="label">今日调用次数</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-history"></i>
                        <div class="value"><?php echo number_format($total_calls); ?></div>
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
                        <h4>必要参数说明</h4>
                        <table class="api-table">
                            <tr>
                                <th>参数名</th>
                                <th>参数说明</th>
                                <th>是否必须</th>
                                <th>传递方式</th>
                            </tr>
                            <tr>
                                <td>api_key</td>
                                <td>API密钥，用于接口认证</td>
                                <td>是</td>
                                <td>GET参数或请求头(X-API-KEY)</td>
                            </tr>
                            <tr>
                                <td>card_key</td>
                                <td>要验证的卡密</td>
                                <td>是</td>
                                <td>GET参数或POST数据</td>
                            </tr>
                            <tr>
                                <td>device_id</td>
                                <td>设备唯一标识</td>
                                <td>是</td>
                                <td>GET参数或POST数据</td>
                            </tr>
                        </table>

                        <h4>接口说明</h4>
                        <p>本系统提供卡密验证功能，支持 POST 和 GET 两种请求方式。每个卡密只能绑定一个设备使用。</p>
                        
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
    "card_key": "您的卡密",
    "device_id": "设备唯一标识"
}</code></pre>
                        </div>

                        <h4>GET 方式调用</h4>
                        <div class="info-item">
                            <pre><code>GET /api/verify.php?api_key=<?php echo htmlspecialchars($api_key); ?>&card_key=您的卡密&device_id=设备唯一标识</code></pre>
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
        "duration": 30,
        "device_id": "xxx"
    }
}

// 验证失败
{
    "code": 1,
    "message": "卡密无效或已被其他设备使用",
    "data": null
}</code></pre>
                        </div>

                        <h4>错误码说明</h4>
                        <table class="api-table">
                            <tr>
                                <th>错误码</th>
                                <th>说明</th>
                                <th>HTTP状态码</th>
                                <th>处理建议</th>
                            </tr>
                            <tr>
                                <td>0</td>
                                <td>成功</td>
                                <td>200</td>
                                <td>请求成功，可以正常处理返回的数据</td>
                            </tr>
                            <tr>
                                <td>1</td>
                                <td>卡密相关错误</td>
                                <td>400</td>
                                <td>
                                    可能的错误原因：<br>
                                    - 卡密不存在<br>
                                    - 卡密已被其他设备使用<br>
                                    - 未提供卡密或设备ID
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>API接口未启用</td>
                                <td>403</td>
                                <td>请联系管理员启用API接口</td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>系统错误</td>
                                <td>500</td>
                                <td>服务器内部错误，请稍后重试或联系管理员</td>
                            </tr>
                            <tr>
                                <td>4</td>
                                <td>API密钥无效</td>
                                <td>401</td>
                                <td>请检查API密钥是否正确或是否已被禁用</td>
                            </tr>
                            <tr>
                                <td>5</td>
                                <td>卡密已被禁用</td>
                                <td>403</td>
                                <td>此卡密已被管理员手动禁用，请联系管理员处理</td>
                            </tr>
                        </table>

                        <h4>注意事项</h4>
                        <ul class="api-notes">
                            <li>每个卡密只能绑定一个设备使用</li>
                            <li>同一设备可以重复验证已绑定的卡密</li>
                            <li>device_id 应该是设备的唯一标识，建议使用硬件信息生成</li>
                            <li>API密钥可以通过Header或GET参数传递，推荐使用Header方式</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-key"></i> API密钥管理</h3>
                </div>
                <div class="card-body">
                    <!-- 添加新API密钥表单 -->
                    <form method="POST" class="api-key-form">
                        <div class="form-group">
                            <label>密钥名称：</label>
                            <input type="text" name="key_name" required placeholder="为API密钥取个名字">
                        </div>
                        <div class="form-group">
                            <label>备注说明：</label>
                            <input type="text" name="description" placeholder="可选的说明信息">
                        </div>
                        <button type="submit" name="add_api_key" class="btn btn-primary">
                            <i class="fas fa-plus"></i> 添加新密钥
                        </button>
                    </form>

                    <!-- API密钥列表 -->
                    <div class="api-keys-list">
                        <h4>API密钥列表</h4>
                        <table class="api-table">
                            <thead>
                                <tr>
                                    <th>名称</th>
                                    <th>API密钥</th>
                                    <th>状态</th>
                                    <th>使用次数</th>
                                    <th>最后使用</th>
                                    <th>创建时间</th>
                                    <th>备注</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($api_keys as $key): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($key['key_name']); ?></td>
                                    <td>
                                        <div class="api-key-container">
                                            <span class="api-key"><?php echo htmlspecialchars($key['api_key']); ?></span>
                                            <button type="button" class="copy-btn" onclick="copyApiKey(this)">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="POST" class="status-toggle">
                                            <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo $key['status'] ? '0' : '1'; ?>">
                                            <button type="submit" name="toggle_key_status" class="status-btn <?php echo $key['status'] ? 'enabled' : 'disabled'; ?>">
                                                <?php echo $key['status'] ? '启用' : '禁用'; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td><?php echo $key['use_count']; ?></td>
                                    <td><?php echo $key['last_use_time'] ?: '-'; ?></td>
                                    <td><?php echo $key['create_time']; ?></td>
                                    <td><?php echo htmlspecialchars($key['description']); ?></td>
                                    <td>
                                        <form method="POST" class="delete-key" onsubmit="return confirm('确定要删除这个API密钥吗？');">
                                            <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                            <button type="submit" name="delete_key" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
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
        function copyApiKey(button) {
            const apiKey = button.previousElementSibling.textContent;
            navigator.clipboard.writeText(apiKey).then(() => {
                const originalHtml = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(() => {
                    button.innerHTML = originalHtml;
                }, 2000);
            });
        }
    </script>
</body>
</html> 