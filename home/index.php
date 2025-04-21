<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: ../admin.php");
    exit;
}

require_once '../config.php';

function generateKey($length = 20) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $key = '';
    for ($i = 0; $i < $length; $i++) {
        $key .= $characters[random_int(0, strlen($characters) - 1)];
    }
    // 返回原始卡密和加密后的卡密
    return [
        'original' => $key,
        'encrypted' => encryptCardKey($key)
    ];
}

function encryptCardKey($key) {
    // 使用固定的盐值进行加密，确保同样的卡密加密结果一致
    $salt = 'xiaoxiaoguai_card_system_2024';
    return sha1($key . $salt);
}

function decryptCardKey($encrypted_key) {
    // 由于SHA1是单向加密，这里返回null
    // 验证时需要将输入的卡密加密后与数据库中的加密值比较
    return null;
}

// Initialize variables outside the try block
$cards = [];
$per_page_options = [20, 50, 100, 200]; // Define per_page_options here
$error = null;
$success = null;
$total = 0;
$used = 0;
$unused = 0;
$usage_rate = 0;
$total_pages = 0;
$limit = 20; // Default limit
$page = 1; // Default page

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 获取统计数据
    $total = $conn->query("SELECT COUNT(*) FROM cards")->fetchColumn();
    $used = $conn->query("SELECT COUNT(*) FROM cards WHERE status = 1")->fetchColumn();
    $unused = $total - $used;
    $usage_rate = $total > 0 ? round(($used / $total) * 100, 1) : 0;

    // 添加卡密 - 只在点击生成按钮时执行
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_card']) && isset($_POST['action']) && $_POST['action'] == 'add'){
        $count = intval($_POST['count'] ?? 1);
        $count = min(max($count, 1), 100); // 限制一次最多生成100个
        
        // 处理时长
        $duration = $_POST['duration'];
        if($duration === 'custom') {
            $duration = intval($_POST['custom_duration']);
        } else {
            $duration = intval($duration);
        }
        
        // 获取是否允许重复验证的设置
        $allow_reverify = isset($_POST['allow_reverify']) && $_POST['allow_reverify'] == '1' ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO cards (card_key, encrypted_key, duration, allow_reverify) VALUES (?, ?, ?, ?)");
        
        for($i = 0; $i < $count; $i++){
            do {
                $key = generateKey();
                $check = $conn->prepare("SELECT COUNT(*) FROM cards WHERE encrypted_key = ?");
                $check->execute([$key['encrypted']]);
            } while($check->fetchColumn() > 0);
            
            $stmt->execute([$key['original'], $key['encrypted'], $duration, $allow_reverify]);
        }
        
        $success = "成功生成 {$count} 个卡密";

        // 更新统计数据
        $total = $conn->query("SELECT COUNT(*) FROM cards")->fetchColumn();
        $used = $conn->query("SELECT COUNT(*) FROM cards WHERE status = 1")->fetchColumn();
        $unused = $total - $used;
        $usage_rate = $total > 0 ? round(($used / $total) * 100, 1) : 0;
    }

    // 删除卡密
    if(isset($_GET['delete'])){
        $stmt = $conn->prepare("DELETE FROM cards WHERE id = ? AND status = 0");
        $stmt->execute([$_GET['delete']]);
        if($stmt->rowCount() > 0){
            $success = "删除成功";
        } else {
            $error = "删除失败，卡密不存在或已被使用";
        }
    }

    // 获取卡密列表
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;  // 默认20条
    if (!in_array($limit, $per_page_options)) {
        $limit = 20;
    }

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;
    
    $stmt = $conn->query("SELECT COUNT(*) FROM cards");
    $total = $stmt->fetchColumn();
    $total_pages = ceil($total / $limit);
    
    $stmt = $conn->prepare("SELECT * FROM cards ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 在try块中添加批量删除处理
    if(isset($_POST['delete_cards']) && isset($_POST['card_ids'])) {
        $ids = array_map('intval', $_POST['card_ids']);
        if(!empty($ids)) {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $conn->prepare("DELETE FROM cards WHERE id IN ($placeholders) AND status = 0");
            $stmt->execute($ids);
            $deleted = $stmt->rowCount();
            if($deleted > 0) {
                $success = "成功删除 {$deleted} 个卡密";
            } else {
                $error = "没有卡密被删除，可能卡密不存在或已被使用";
            }
        }
    }
} catch(PDOException $e) {
    // $error = "系统错误，请稍后再试"; // Temporarily comment out the generic message
    // $error = "数据库错误: " . $e->getMessage(); // Comment out the previous change too
    die("捕获到数据库错误，请检查日志或联系管理员。详细信息： " . $e->getMessage()); // Directly output the error and stop
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>卡密管理 - 卡密验证系统</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
    <style>
        /* 后台通用样式 */
        body {
            margin: 0;
            padding: 0;
            background: #f5f6fa;
        }

        /* 侧边栏样式 */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            padding: 20px 0;
        }

        .sidebar .logo {
            text-align: center;
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .logo h2 {
            margin: 0;
            font-size: 24px;
            color: #fff;
        }

        .sidebar .menu {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .sidebar .menu li {
            padding: 0;
            margin: 0;
        }

        .sidebar .menu li a {
            display: block;
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

        .sidebar .menu li:hover a,
        .sidebar .menu li.active a {
            background: rgba(255, 255, 255, 0.1);
        }

        /* 主内容区域样式 */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
            position: relative;
            padding-bottom: 60px; /* 为底部版权留出空间 */
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            background: #f5f6fa;
            min-height: calc(100vh - 60px); /* 减去底部版权的高度 */
            position: relative;
        }

        /* 头部样式 */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .header h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        /* 卡片样式 */
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .card-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
        }

        /* 版权信息样式 */
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

        .footer-copyright {
            position: fixed;
            bottom: 0;
            left: 250px; /* 与侧边栏宽度相同 */
            right: 0;
            background: #fff;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #eee;
            height: 60px;
            box-sizing: border-box;
            z-index: 1000;
        }

        /* 卡密管理特定样式 */
        .generate-form {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .card-key-container {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .card-key {
            background: transparent;
            border: none;
            padding: 5px;
            font-family: monospace;
            color: #2c3e50;
            width: 180px;
            cursor: pointer;
        }

        .copy-btn {
            padding: 5px 8px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
        }

        .status-badge.used {
            background: #e74c3c;
            color: white;
        }

        .status-badge.unused {
            background: #2ecc71;
            color: white;
        }

        .duration-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            background: #3498db;
            color: white;
        }

        .duration-badge.permanent {
            background: #2ecc71;
        }

        /* 卡密管理页面补充样式 */
        .export-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .export-controls input[type="text"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: white;
        }

        .btn-primary {
            background: #3498db;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .select-all-container {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .table-responsive {
            overflow-x: auto;
            margin-top: 15px;
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

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 5px;
        }

        .per-page-select {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .per-page-option {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #666;
            text-decoration: none;
        }

        .per-page-option.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .pagination a {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #666;
            text-decoration: none;
        }

        .pagination a.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .pagination-ellipsis {
            color: #666;
            padding: 0 5px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
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

        /* 自定义时长输入框样式 */
        .custom-duration {
            margin-top: 15px;
        }

        /* 表格内的复选框样式 */
        .card-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* 修改统计卡片的样式 */
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
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stat-card i {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .stat-card h3 {
            margin: 0;
            color: #666;
            font-size: 16px;
            font-weight: 500;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }

        /* 确保内容不会被底部版权遮挡 */
        .card:last-child {
            margin-bottom: 0;
        }
        
        /* 在小屏幕上调整版权样式 */
        @media (max-width: 768px) {
            .footer-copyright {
                left: 0;
            }
        }

        /* 设备ID样式 */
        .device-id {
            display: inline-block;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            background: #f8f9fa;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            color: #666;
            cursor: help;
        }

        /* 当鼠标悬停时显示完整设备ID */
        .device-id:hover {
            position: relative;
        }

        .device-id:hover::after {
            content: attr(title);
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 100%;
            background: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 5px;
            min-width: 300px;
        }

        .button-group {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .status-badge.disabled {
            background: #e74c3c;
            color: white;
        }

        .btn-success {
            background: #2ecc71;
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
        }

        /* 切换按钮样式 */
        .toggle-btn {
            cursor: pointer;
            font-size: 22px;
            transition: color 0.3s;
        }
        .toggle-btn.on {
            color: #2ecc71; /* 开启状态颜色 */
        }
        .toggle-btn.off {
            color: #ccc; /* 关闭状态颜色 */
        }
        .toggle-btn:hover {
            opacity: 0.8;
        }

        /* 添加卡密表单样式 */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        #custom-duration-group {
            display: none; /* 默认隐藏 */
        }
        .form-group .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .form-group input[type="checkbox"] {
            margin-right: 8px;
            width: 18px;
            height: 18px;
            cursor: pointer;
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
                <li class="active"><a href="index.php"><i class="fas fa-key"></i>卡密管理</a></li>
                <li><a href="stats.php"><i class="fas fa-chart-line"></i>数据统计</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i>系统设置</a></li>
                <li><a href="api_settings.php"><i class="fas fa-code"></i>API接口</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i>退出登录</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h2><i class="fas fa-key"></i> 卡密管理</h2>
                <div class="user-info">
                    <img src="../assets/images/avatar.png" alt="avatar">
                    <span>欢迎，<?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                </div>
            </div>
            
            <?php 
            if(isset($success)) echo "<div class='alert alert-success'>$success</div>";
            if(isset($error)) echo "<div class='alert alert-error'>$error</div>";
            ?>
            
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
                    <h3><i class="fas fa-plus-circle"></i> 生成卡密</h3>
                </div>
                <form method="POST" class="form-group" style="padding: 20px;">
                    <input type="hidden" name="action" value="add">
                    <div class="generate-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-list-ol"></i> 生成数量：</label>
                                <input type="number" name="count" min="1" max="100" value="1" class="form-control">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> 使用时长：</label>
                                <select name="duration" class="form-control">
                                    <option value="0">永久</option>
                                    <option value="1">1天</option>
                                    <option value="7">7天</option>
                                    <option value="30">30天</option>
                                    <option value="90">90天</option>
                                    <option value="180">180天</option>
                                    <option value="365">365天</option>
                                    <option value="custom">自定义</option>
                                </select>
                            </div>
                            <div class="form-group custom-duration" style="display: none;">
                                <label><i class="fas fa-edit"></i> 自定义天数：</label>
                                <input type="number" name="custom_duration" min="1" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="allow_reverify" class="checkbox-label">
                                    <input type="checkbox" id="allow_reverify" name="allow_reverify" value="1" checked>
                                    允许同一设备重复验证
                                </label>
                            </div>
                        </div>
                        <button type="submit" name="generate_card" class="btn btn-primary">
                            <i class="fas fa-plus"></i> 生成卡密
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>卡密列表</h3>
                    <div class="export-controls">
                        <input type="text" id="exportFileName" placeholder="文件名称" value="卡密列表">
                        <button type="button" class="btn btn-primary" onclick="exportSelected()">
                            <i class="fas fa-file-excel"></i> 导出Excel
                        </button>
                        <button type="button" class="btn btn-danger" onclick="deleteSelected()">
                            <i class="fas fa-trash"></i> 批量删除
                        </button>
                        <label class="select-all-container">
                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                            <span>全选</span>
                        </label>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th width="20">
                                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                                </th>
                                <th>ID</th>
                                <th>卡密</th>
                                <th>状态</th>
                                <th>有效期</th>
                                <th>使用时间</th>
                                <th>到期时间</th>
                                <th>创建时间</th>
                                <th>设备ID</th>
                                <th>允许重复验证</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($cards as $card): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="card-checkbox" value="<?php echo $card['card_key']; ?>">
                                </td>
                                <td><?php echo $card['id']; ?></td>
                                <td>
                                    <input type="text" value="<?php echo $card['card_key']; ?>" readonly style="width: 180px;">
                                    <button type="button" class="copy-btn" onclick="copyCardKey(this)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <?php if(isset($_SESSION['show_encrypted']) && $_SESSION['show_encrypted']): ?>
                                    <div class="encrypted-key">
                                        加密值: <?php echo $card['encrypted_key']; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php 
                                        echo $card['status'] == 0 ? 'unused' : 
                                            ($card['status'] == 1 ? 'used' : 'disabled'); 
                                    ?>">
                                        <?php 
                                        echo $card['status'] == 0 ? '未使用' : 
                                            ($card['status'] == 1 ? '已使用' : '已停用'); 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="duration-badge">
                                        <?php echo $card['duration'] > 0 ? "{$card['duration']}天" : '永久'; ?>
                                    </span>
                                </td>
                                <td><?php echo $card['use_time'] ?: '-'; ?></td>
                                <td><?php echo $card['expire_time'] ?: '-'; ?></td>
                                <td><?php echo $card['create_time']; ?></td>
                                <td>
                                    <?php if($card['status'] && $card['device_id']): ?>
                                        <span class="device-id" title="<?php echo htmlspecialchars($card['device_id']); ?>">
                                            <?php 
                                            // 显示设备ID的前6位和后4位，中间用...代替
                                            $device_id = $card['device_id'];
                                            echo strlen($device_id) > 10 ? 
                                                substr($device_id, 0, 6) . '...' . substr($device_id, -4) : 
                                                $device_id;
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="fas <?php echo $card['allow_reverify'] ? 'fa-toggle-on toggle-btn on' : 'fa-toggle-off toggle-btn off'; ?>"
                                       onclick="toggleReverify(<?php echo $card['id']; ?>, <?php echo $card['allow_reverify'] ? 0 : 1; ?>, this)"></i>
                                </td>
                                <td>
                                    <?php if($card['status'] == 0): // 未使用 ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteUnusedCard(<?php echo $card['id']; ?>)" title="删除未使用卡密">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: // 已使用或已停用 ?>
                                        <?php if($card['status'] == 1): // 正常状态显示停用按钮 ?>
                                            <button type="button" class="btn btn-warning btn-sm" onclick="toggleCardStatus(<?php echo $card['id']; ?>, 'disable')" title="停用卡密">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php elseif($card['status'] == 2): // 停用状态显示启用按钮 ?>
                                            <button type="button" class="btn btn-success btn-sm" onclick="toggleCardStatus(<?php echo $card['id']; ?>, 'enable')" title="启用卡密">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-info btn-sm" onclick="promptExpireTime(<?php echo $card['id']; ?>, '<?php echo $card['expire_time']; ?>')" title="修改时间">
                                            <i class="fas fa-clock"></i>
                                        </button>
                                        <?php if(!empty($card['device_id'])): // 如果已绑定设备，显示解绑按钮 ?>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="unbindDevice(<?php echo $card['id']; ?>)" title="解绑设备">
                                            <i class="fas fa-unlink"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteUsedCard(<?php echo $card['id']; ?>)" title="删除卡密">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if($total_pages > 1 || count($per_page_options) > 1): ?>
                <div class="pagination-container">
                    <!-- 每页显示数量选择 -->
                    <div class="per-page-select">
                        <span>每页显示：</span>
                        <?php foreach($per_page_options as $option): ?>
                        <a href="?limit=<?php echo $option; ?>" 
                           class="per-page-option <?php echo $limit == $option ? 'active' : ''; ?>">
                            <?php echo $option; ?>条
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- 分页链接 -->
                    <?php if($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if($page > 1): ?>
                            <a href="?page=1&limit=<?php echo $limit; ?>" title="首页">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?php echo ($page-1); ?>&limit=<?php echo $limit; ?>" title="上一页">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if($start > 1) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }
                        
                        for($i = $start; $i <= $end; $i++): 
                        ?>
                            <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>" 
                               <?php if($i == $page) echo 'class="active"'; ?>>
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; 
                        
                        if($end < $total_pages) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }
                        ?>

                        <?php if($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page+1); ?>&limit=<?php echo $limit; ?>" title="下一页">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?php echo $total_pages; ?>&limit=<?php echo $limit; ?>" title="末页">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer-copyright">
        <div class="container">
            &copy; <?php echo date('Y'); ?> 小小怪卡密系统 - All Rights Reserved
        </div>
    </footer>

    <!-- 添加模态框 -->
    <div id="editExpireTimeModal" class="modal">
        <div class="modal-content">
            <h3>修改到期时间</h3>
            <form id="editExpireTimeForm">
                <input type="hidden" name="card_id" id="editCardId">
                <div class="form-group">
                    <label>到期时间：</label>
                    <input type="datetime-local" name="expire_time" id="editExpireTime" required>
                </div>
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelector('select[name="duration"]').addEventListener('change', function() {
            const customDuration = document.querySelector('.custom-duration');
            if(this.value === 'custom') {
                customDuration.style.display = 'block';
            } else {
                customDuration.style.display = 'none';
            }
        });

        function copyCardKey(btn) {
            const input = btn.previousElementSibling;
            input.select();
            document.execCommand('copy');
            
            // 更新按钮状态
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> 已复制';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
            }, 2000);
        }

        function deleteCard(id) {
            if(confirm('确定要删除这个卡密吗？')) {
                window.location.href = '?delete=' + id;
            }
        }

        // 全选功能
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.card-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        // 监听单个复选框的变化
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('card-checkbox')) {
                const selectAll = document.getElementById('selectAll');
                const checkboxes = document.querySelectorAll('.card-checkbox');
                const checkedBoxes = document.querySelectorAll('.card-checkbox:checked');
                selectAll.checked = checkboxes.length === checkedBoxes.length;
            }
        });

        // 导出为Excel函数
        function exportSelected() {
            const checkboxes = document.querySelectorAll('.card-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('请至少选择一个卡密');
                return;
            }

            try {
                // 收集选中的卡密信息
                const selectedCards = Array.from(checkboxes).map(checkbox => {
                    const row = checkbox.closest('tr');
                    return {
                        'ID': row.cells[1].textContent,
                        '卡密': checkbox.value,
                        '状态': row.querySelector('.status-badge').textContent.trim(),
                        '有效期': row.querySelector('.duration-badge').textContent.trim(),
                        '使用时间': row.cells[5].textContent.trim(),
                        '到期时间': row.cells[6].textContent.trim(),
                        '创建时间': row.cells[7].textContent.trim(),
                        '设备ID': row.cells[8].textContent.trim()  // 添加设备ID列
                    };
                });
                
                // 获取文件名
                let fileName = document.getElementById('exportFileName').value.trim() || '卡密列表';
                if (!fileName.toLowerCase().endsWith('.xlsx')) {
                    fileName += '.xlsx';
                }

                // 创建工作簿
                const wb = XLSX.utils.book_new();
                
                // 添加标题行
                const ws = XLSX.utils.json_to_sheet(selectedCards, {
                    header: ['ID', '卡密', '状态', '有效期', '使用时间', '到期时间', '创建时间', '设备ID']
                });

                // 设置列宽
                const colWidths = [
                    { wch: 8 },   // ID
                    { wch: 25 },  // 卡密
                    { wch: 10 },  // 状态
                    { wch: 10 },  // 有效期
                    { wch: 20 },  // 使用时间
                    { wch: 20 },  // 到期时间
                    { wch: 20 },  // 创建时间
                    { wch: 30 }   // 设备ID
                ];
                ws['!cols'] = colWidths;

                // 添加工作表到工作簿
                XLSX.utils.book_append_sheet(wb, ws, '卡密列表');

                // 导出Excel文件
                XLSX.writeFile(wb, fileName);
            } catch (error) {
                console.error('导出失败:', error);
                alert('导出失败，请稍后重试');
            }
        }

        // 批量删除函数
        function deleteSelected() {
            const checkboxes = document.querySelectorAll('.card-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('请至少选择一个卡密');
                return;
            }

            if(!confirm(`确定要删除选中的 ${checkboxes.length} 个卡密吗？此操作不可恢复！`)) {
                return;
            }

            // 收集选中的卡密ID
            const cardIds = Array.from(checkboxes).map(checkbox => {
                const row = checkbox.closest('tr');
                return row.cells[1].textContent; // ID列
            });

            // 创建表单并提交
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            // 添加操作标识
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'delete_cards';
            actionInput.value = '1';
            form.appendChild(actionInput);

            // 添加卡密ID
            cardIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'card_ids[]';
                input.value = id;
                form.appendChild(input);
            });

            // 提交表单
            document.body.appendChild(form);
            form.submit();
        }

        // 停用卡密
        function disableCard(cardId) {
            if(confirm('确定要停用这个卡密吗？')) {
                fetch('card_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'disable',
                        card_id: cardId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message || '操作失败');
                    }
                });
            }
        }

        // 修改到期时间
        function editExpireTime(cardId, currentExpireTime) {
            document.getElementById('editCardId').value = cardId;
            document.getElementById('editExpireTime').value = currentExpireTime.replace(' ', 'T');
            document.getElementById('editExpireTimeModal').style.display = 'block';
        }

        // 关闭模态框
        function closeModal() {
            document.getElementById('editExpireTimeModal').style.display = 'none';
        }

        // 处理修改到期时间表单提交
        document.getElementById('editExpireTimeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('card_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_expire_time',
                    card_id: formData.get('card_id'),
                    expire_time: formData.get('expire_time')
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert(data.message || '操作失败');
                }
            });
        });

        // 删除已使用的卡密
        function deleteUsedCard(cardId) {
            if(confirm('确定要删除这个已使用的卡密吗？此操作不可恢复！')) {
                fetch('card_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete_used',
                        card_id: cardId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message || '操作失败');
                    }
                });
            }
        }

        // 启用卡密
        function enableCard(cardId) {
            if(confirm('确定要启用这个卡密吗？')) {
                fetch('card_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'enable',
                        card_id: cardId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message || '操作失败');
                    }
                });
            }
        }

        // 切换允许重复验证状态
        async function toggleReverify(cardId, newState, iconElement) {
            const actionText = newState === 1 ? '允许' : '禁止';
            // 不需要确认提示，直接切换
            // if (!confirm(`确定要${actionText}此卡密被同一设备重复验证吗？`)) return;

            // 乐观更新 UI (可选，可以增加用户体验)
            iconElement.classList.toggle('on', newState === 1);
            iconElement.classList.toggle('off', newState === 0);
            iconElement.classList.toggle('fa-toggle-on', newState === 1);
            iconElement.classList.toggle('fa-toggle-off', newState === 0);
            // 更新 onclick 事件的参数
             iconElement.setAttribute('onclick', `toggleReverify(${cardId}, ${newState === 1 ? 0 : 1}, this)`);


            const result = await sendActionRequest('toggle_reverify', { card_id: cardId, allow_reverify: newState });
            
            if (!result.success) {
                 alert(result.message);
                 // 如果失败，则恢复 UI
                 const oldState = newState === 1 ? 0 : 1;
                 iconElement.classList.toggle('on', oldState === 1);
                 iconElement.classList.toggle('off', oldState === 0);
                 iconElement.classList.toggle('fa-toggle-on', oldState === 1);
                 iconElement.classList.toggle('fa-toggle-off', oldState === 0);
                 iconElement.setAttribute('onclick', `toggleReverify(${cardId}, ${oldState === 1 ? 0 : 1}, this)`);
            }
            // 不需要刷新页面，除非后端返回需要刷新
            // if (result.success) { window.location.reload(); }
        }

        // 通用AJAX请求函数
        async function sendActionRequest(action, data) {
            try {
                const response = await fetch('card_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ action, ...data })
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                console.error('请求失败:', error);
                alert('操作失败，请检查网络或联系管理员');
                return { success: false, message: '请求失败' };
            }
        }

        // --- 新增解绑设备功能 ---
        async function unbindDevice(cardId) {
            if (!confirm('确定要解绑此卡密的设备吗？解绑后，任何设备都可以使用此卡密重新验证并绑定。')) return;

            const result = await sendActionRequest('unbind_device', { card_id: cardId });
            alert(result.message);
            if (result.success) {
                window.location.reload(); // 刷新页面更新状态
            }
        }

        // --- 重新添加缺失的函数 ---

        // 切换卡密状态（启用/停用）
        async function toggleCardStatus(cardId, statusAction) {
            const actionText = statusAction === 'disable' ? '停用' : '启用';
            if (!confirm(`确定要${actionText}这个卡密吗？`)) return;

            const result = await sendActionRequest(statusAction, { card_id: cardId });
            alert(result.message);
            if (result.success) {
                window.location.reload(); // 刷新页面更新状态
            }
        }

        // 弹出提示框修改到期时间
        async function promptExpireTime(cardId, currentExpireTime) {
            const newExpireTimeStr = prompt("请输入新的到期时间（格式 YYYY-MM-DD HH:MM:SS），留空则清除到期时间", currentExpireTime || '');
            
            // 用户取消
            if (newExpireTimeStr === null) return; 

            let newExpireTime = newExpireTimeStr.trim();

            if (newExpireTime === '') {
                // 清除到期时间 (需要后端支持，例如传递 null 或特定值)
                 if (!confirm(`确定要清除卡密 ${cardId} 的到期时间吗？`)) return;
                 // 假设后端能处理 expire_time 为 null
                 newExpireTime = null; 
            } else {
                // 简单验证日期格式 (更严格的验证应该在后端进行)
                // 注意：直接用 Date.parse 或 new Date() 在不同浏览器可能有兼容性问题
                // 最好是后端进行严格验证
                const dateRegex = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/;
                if (!dateRegex.test(newExpireTime)) {
                     alert("日期格式无效，请输入 YYYY-MM-DD HH:MM:SS");
                     return;
                }
            }

            const result = await sendActionRequest('update_expire_time', { card_id: cardId, expire_time: newExpireTime });
            alert(result.message);
            if (result.success) {
                window.location.reload(); // 刷新页面更新状态
            }
        }

        // 删除未使用卡密 (之前可能叫 deleteCard)
        function deleteUnusedCard(id) {
            if(confirm('确定要删除这个未使用的卡密吗？此操作不可恢复！')) {
                window.location.href = '?delete=' + id;
            }
        }

        // 删除已使用/已停用的卡密 (这个函数是存在的)
        // async function deleteUsedCard(cardId) { ... }

        // --- 结束 重新添加缺失的函数 ---

        // 切换允许重复验证状态 (这个函数是存在的)
        // async function toggleReverify(cardId, newState, iconElement) { ... }

        // 通用AJAX请求函数 (这个函数是存在的)
        // async function sendActionRequest(action, data) { ... }
    </script>
</body>
</html> 