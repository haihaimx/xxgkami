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
    return $key;
}

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
        
        $stmt = $conn->prepare("INSERT INTO cards (card_key, duration) VALUES (?, ?)");
        
        for($i = 0; $i < $count; $i++){
            do {
                $key = generateKey();
                $check = $conn->prepare("SELECT COUNT(*) FROM cards WHERE card_key = ?");
                $check->execute([$key]);
            } while($check->fetchColumn() > 0);
            
            $stmt->execute([$key, $duration]);
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
    $per_page_options = [20, 50, 100, 200];  // 每页显示数量选项
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
    $error = "系统错误，请稍后再试";
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
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            background: #f5f6fa;
            min-height: 100vh;
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
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            <span>全选</span>
                        </label>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th style="width: 40px; text-align: center;">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            </th>
                            <th>ID</th>
                            <th>卡密</th>
                            <th>状态</th>
                            <th>有效期</th>
                            <th>使用时间</th>
                            <th>到期时间</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                        <?php foreach($cards as $card): ?>
                        <tr>
                            <td style="text-align: center;">
                                <?php if(!$card['status']): ?>
                                <input type="checkbox" class="card-checkbox" value="<?php echo htmlspecialchars($card['card_key']); ?>">
                                <?php endif; ?>
                            </td>
                            <td><?php echo $card['id']; ?></td>
                            <td>
                                <div class="card-key-container">
                                    <input type="text" class="card-key" value="<?php echo htmlspecialchars($card['card_key']); ?>" readonly>
                                    <button type="button" class="copy-btn" onclick="copyCardKey(this)" title="复制卡密">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $card['status'] ? 'used' : 'unused'; ?>">
                                    <?php echo $card['status'] ? '已使用' : '未使用'; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if($card['duration'] == 0) {
                                    echo '<span class="duration-badge permanent">永久</span>';
                                } else {
                                    echo '<span class="duration-badge">' . $card['duration'] . '天</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo $card['use_time'] ?: '-'; ?></td>
                            <td><?php echo $card['expire_time'] ?: '-'; ?></td>
                            <td><?php echo $card['create_time']; ?></td>
                            <td>
                                <?php if(!$card['status']): ?>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-danger" onclick="deleteCard(<?php echo $card['id']; ?>)">
                                        <i class="fas fa-trash"></i> 删除
                                    </button>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                        '创建时间': row.cells[7].textContent.trim()
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
                    header: ['ID', '卡密', '状态', '有效期', '使用时间', '到期时间', '创建时间']
                });

                // 设置列宽
                const colWidths = [
                    { wch: 8 },  // ID
                    { wch: 25 }, // 卡密
                    { wch: 10 }, // 状态
                    { wch: 10 }, // 有效期
                    { wch: 20 }, // 使用时间
                    { wch: 20 }, // 到期时间
                    { wch: 20 }  // 创建时间
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
    </script>
</body>
</html> 