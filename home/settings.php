<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: ../admin.php");
    exit;
}

require_once '../config.php';

// 处理密码修改
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])){
    try {
        $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // 验证旧密码
        $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        
        if(!password_verify($old_password, $admin['password'])){
            $error = "旧密码错误";
        } elseif($new_password !== $confirm_password) {
            $error = "两次输入的新密码不一致";
        } elseif(strlen($new_password) < 6) {
            $error = "密码长度不能小于6位";
        } else {
            // 更新密码
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->execute([$new_hash, $_SESSION['admin_id']]);
            $success = "密码修改成功";
        }
    } catch(PDOException $e) {
        $error = "系统错误，请稍后再试";
    }
}

// 处理基础设置和联系方式修改
if($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['change_title']) || isset($_POST['change_contact']))){
    try {
        $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $new_title = trim($_POST['site_title']);
        $new_subtitle = trim($_POST['site_subtitle']);
        $new_copyright = trim($_POST['copyright_text']);
        $new_qq_group = trim($_POST['contact_qq_group']);
        $new_email = trim($_POST['contact_email']);
        
        // 处理微信二维码上传
        $new_wechat_qr = isset($_POST['current_wechat_qr']) ? $_POST['current_wechat_qr'] : '';
        if(isset($_FILES['contact_wechat_qr']) && $_FILES['contact_wechat_qr']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['contact_wechat_qr']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if(in_array($ext, $allowed)) {
                $upload_dir = '../assets/images/';
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = 'wechat-qr-' . time() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                if(move_uploaded_file($_FILES['contact_wechat_qr']['tmp_name'], $upload_path)) {
                    // 删除旧图片
                    if(!empty($new_wechat_qr)) {
                        $old_file = '../' . $new_wechat_qr;
                        if(file_exists($old_file) && is_file($old_file)) {
                            unlink($old_file);
                        }
                    }
                    $new_wechat_qr = 'assets/images/' . $new_filename;
                }
            }
        }
        
        // 根据提交的表单类型验证必填项
        if(isset($_POST['change_title']) && (empty($new_title) || empty($new_subtitle) || empty($new_copyright))) {
            $error = "必填项不能为空";
        } else {
            // 更新所有设置
            $settings_to_update = [
                'site_title' => $new_title,
                'site_subtitle' => $new_subtitle,
                'copyright_text' => $new_copyright,
                'contact_qq_group' => $new_qq_group,
                'contact_wechat_qr' => $new_wechat_qr,
                'contact_email' => $new_email
            ];
            
            foreach($settings_to_update as $name => $value) {
                $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = ?");
                $stmt->execute([$value, $name]);
            }
            
            $success = isset($_POST['change_title']) ? "网站设置修改成功" : "联系方式设置修改成功";
        }
    } catch(PDOException $e) {
        $error = "系统错误，请稍后再试";
    }
}

// 处理轮播图上传
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_slide'])) {
    try {
        $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $title = trim($_POST['slide_title']);
        $description = trim($_POST['slide_description']);
        $image_url = trim($_POST['image_url']);
        $sort_order = (int)$_POST['sort_order'];
        
        // 如果有文件上传
        if(isset($_FILES['slide_image']) && $_FILES['slide_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['slide_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if(in_array($ext, $allowed)) {
                $upload_dir = '../assets/images/slides/';
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = uniqid() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                if(move_uploaded_file($_FILES['slide_image']['tmp_name'], $upload_path)) {
                    $image_url = 'assets/images/slides/' . $new_filename;
                }
            }
        }
        
        if(empty($title) || (empty($image_url) && !isset($_FILES['slide_image']))) {
            $error = "标题和图片不能为空";
        } else {
            $stmt = $conn->prepare("INSERT INTO slides (title, description, image_url, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $description, $image_url, $sort_order]);
            $success = "轮播图添加成功";
        }
    } catch(PDOException $e) {
        $error = "系统错误，请稍后再试";
    }
}

// 处理轮播图删除
if(isset($_POST['delete_slide'])) {
    try {
        $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $slide_id = (int)$_POST['slide_id'];
        
        // 获取图片路径
        $stmt = $conn->prepare("SELECT image_url FROM slides WHERE id = ?");
        $stmt->execute([$slide_id]);
        $image_url = $stmt->fetchColumn();
        
        // 删除数据库记录
        $stmt = $conn->prepare("DELETE FROM slides WHERE id = ?");
        $stmt->execute([$slide_id]);
        
        // 如果是本地图片则删除文件
        if(strpos($image_url, 'assets/images/slides/') === 0) {
            $file_path = '../' . $image_url;
            if(file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $success = "轮播图删除成功";
    } catch(PDOException $e) {
        $error = "系统错误，请稍后再试";
    }
}

// 获取所有轮播图
try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $stmt = $conn->query("SELECT * FROM slides ORDER BY sort_order ASC");
    $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $slides = [];
}

// 获取系统信息
$system_info = array(
    'php_version' => PHP_VERSION,
    'server_os' => PHP_OS,
    'server_software' => $_SERVER['SERVER_SOFTWARE'],
    'mysql_version' => '',
    'install_time' => file_exists("../install.lock") ? date('Y-m-d H:i:s', filemtime("../install.lock")) : "未知"
);

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $system_info['mysql_version'] = $conn->getAttribute(PDO::ATTR_SERVER_VERSION);
} catch(PDOException $e) {
    $system_info['mysql_version'] = "未知";
}

// 获取当前网站标题和副标题
try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $stmt = $conn->prepare("SELECT name, value FROM settings WHERE name IN (
        'site_title', 'site_subtitle', 'copyright_text',
        'contact_qq_group', 'contact_wechat_qr', 'contact_email'
    )");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $site_title = $settings['site_title'] ?? '卡密验证系统';
    $site_subtitle = $settings['site_subtitle'] ?? '专业的卡密验证解决方案';
    $copyright_text = $settings['copyright_text'] ?? '小小怪卡密系统 - All Rights Reserved';
    $contact_qq_group = $settings['contact_qq_group'] ?? '123456789';
    $contact_wechat_qr = $settings['contact_wechat_qr'] ?? 'assets/images/wechat-qr.jpg';
    $contact_email = $settings['contact_email'] ?? 'support@example.com';
} catch(PDOException $e) {
    // 设置默认值
}

// 添加处理系统特点的代码
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_feature'])) {
    try {
        $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $feature_id = isset($_POST['feature_id']) ? (int)$_POST['feature_id'] : 0;
        $icon = trim($_POST['icon']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $sort_order = (int)$_POST['sort_order'];
        
        if(empty($title) || empty($description)) {
            $error = "标题和描述不能为空";
        } else {
            if($feature_id > 0) {
                // 更新现有特点
                $stmt = $conn->prepare("UPDATE features SET icon = ?, title = ?, description = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$icon, $title, $description, $sort_order, $feature_id]);
            } else {
                // 添加新特点
                $stmt = $conn->prepare("INSERT INTO features (icon, title, description, sort_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$icon, $title, $description, $sort_order]);
            }
            $success = "系统特点保存成功";
        }
    } catch(PDOException $e) {
        $error = "系统错误，请稍后再试";
    }
}

// 处理删除系统特点
if(isset($_POST['delete_feature'])) {
    try {
        $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $feature_id = (int)$_POST['feature_id'];
        $stmt = $conn->prepare("DELETE FROM features WHERE id = ?");
        $stmt->execute([$feature_id]);
        $success = "系统特点删除成功";
    } catch(PDOException $e) {
        $error = "系统错误，请稍后再试";
    }
}

// 获取所有系统特点
try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $stmt = $conn->query("SELECT * FROM features ORDER BY sort_order ASC");
    $features = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $features = [];
}

// 处理欢迎提示设置
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_welcome'])){
    try {
        $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $welcome_enabled = isset($_POST['welcome_enabled']) ? '1' : '0';
        $welcome_message = trim($_POST['welcome_message']);
        $welcome_duration = (int)$_POST['welcome_duration'];
        
        if(empty($welcome_message) || $welcome_duration < 1000) {
            $error = "请填写有效的欢迎消息和显示时长";
        } else {
            $settings_to_update = [
                'welcome_enabled' => $welcome_enabled,
                'welcome_message' => $welcome_message,
                'welcome_duration' => $welcome_duration
            ];
            
            foreach($settings_to_update as $name => $value) {
                $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = ?");
                $stmt->execute([$value, $name]);
            }
            
            $success = "欢迎提示设置已更新";
        }
    } catch(PDOException $e) {
        $error = "系统错误，请稍后再试";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>系统设置 - 卡密验证系统</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            position: relative;
            padding-bottom: 60px; /* 为页脚预留空间 */
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            background: #f5f6fa;
            min-height: calc(100vh - 60px); /* 减去页脚高度 */
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
            position: fixed; /* 改为固定定位 */
            bottom: 0;
            left: 250px; /* 与侧边栏宽度相同 */
            right: 0;
            padding: 15px 0;
            background: #f8f9fa;
            color: #6c757d;
            text-align: center;
            border-top: 1px solid #dee2e6;
            z-index: 1000; /* 确保在其他内容之上 */
        }

        /* 移动端适配 */
        @media (max-width: 768px) {
            .footer-copyright {
                left: 0;
            }
        }

        /* 设置页面特定样式 */
        .info-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-item i {
            width: 30px;
            color: #3498db;
        }

        .info-item label {
            width: 120px;
            color: #666;
            margin-right: 10px;
        }

        .info-item span {
            color: #2c3e50;
        }

        .security-list {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }

        .security-list li {
            padding: 10px 0;
            color: #666;
        }

        .security-list li i {
            color: #2ecc71;
            margin-right: 10px;
        }

        .warning {
            color: #e74c3c;
            margin: 0;
        }

        .warning i {
            margin-right: 10px;
        }

        /* 设置导航样式 */
        .settings-nav .submenu {
            display: none;
            list-style: none;
            padding-left: 20px;
            margin: 0;
            background: rgba(0, 0, 0, 0.1);
            opacity: 0;
            transition: opacity 0.3s ease;
            max-height: 0;
            overflow: hidden;
        }

        .settings-nav.active .submenu {
            display: block;
            opacity: 1;
            max-height: 500px; /* 足够大的高度以容纳所有子菜单项 */
            transition: all 0.3s ease;
        }

        .settings-nav .submenu li a {
            padding: 10px 20px;
            font-size: 14px;
        }

        .settings-toggle {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
        }

        .settings-toggle .fa-chevron-down {
            transition: transform 0.3s ease;
        }

        .settings-nav.active .settings-toggle .fa-chevron-down {
            transform: rotate(180deg);
        }

        /* 设置卡片样式 */
        .settings-section {
            scroll-margin-top: 20px;
        }

        /* 当前活动的导航项 */
        .submenu li a.active {
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid var(--primary-color);
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
                <li class="active settings-nav">
                    <a href="#" class="settings-toggle">
                        <i class="fas fa-cog"></i>系统设置
                        <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="submenu">
                        <li><a href="#basic-settings"><i class="fas fa-sliders-h"></i>基础设置</a></li>
                        <li><a href="#welcome-settings"><i class="fas fa-bell"></i>欢迎提示</a></li>
                        <li><a href="#security-settings"><i class="fas fa-shield-alt"></i>安全设置</a></li>
                        <li><a href="#contact-settings"><i class="fas fa-address-book"></i>联系方式</a></li>
                        <li><a href="#feature-settings"><i class="fas fa-star"></i>特点管理</a></li>
                        <li><a href="#slide-settings"><i class="fas fa-images"></i>轮播图</a></li>
                        <li><a href="#system-info"><i class="fas fa-info-circle"></i>系统信息</a></li>
                    </ul>
                </li>
                <li><a href="api_settings.php"><i class="fas fa-code"></i>API接口</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i>退出登录</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h2><i class="fas fa-cog"></i> 系统设置</h2>
                <div class="user-info">
                    <img src="../assets/images/avatar.png" alt="avatar">
                    <span>欢迎，<?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                </div>
            </div>
            
            <?php 
            if(isset($success)) echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> $success</div>";
            if(isset($error)) echo "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> $error</div>";
            ?>
            
            <div id="basic-settings" class="card settings-section">
                <div class="card-header">
                    <h3><i class="fas fa-heading"></i> 基础设置</h3>
                </div>
                <form method="POST" enctype="multipart/form-data" class="form-group" style="padding: 20px;">
                    <div class="form-group">
                        <label><i class="fas fa-font"></i> 网站标题：</label>
                        <input type="text" name="site_title" value="<?php echo htmlspecialchars($site_title); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-font"></i> 网站副标题：</label>
                        <input type="text" name="site_subtitle" value="<?php echo htmlspecialchars($site_subtitle); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-copyright"></i> 版权信息：</label>
                        <input type="text" name="copyright_text" value="<?php echo htmlspecialchars($copyright_text); ?>" required>
                    </div>
                    <button type="submit" name="change_title" class="btn btn-primary">
                        <i class="fas fa-save"></i> 保存设置
                    </button>
                </form>
            </div>

            <div id="security-settings" class="card settings-section">
                <div class="card-header">
                    <h3><i class="fas fa-lock"></i> 安全设置</h3>
                </div>
                <form method="POST" class="form-group" style="padding: 20px;">
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> 旧密码：</label>
                        <input type="password" name="old_password" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> 新密码：</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> 确认新密码：</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-save"></i> 修改密码
                    </button>
                </form>
            </div>

            <div id="contact-settings" class="card settings-section">
                <div class="card-header">
                    <h3><i class="fas fa-address-book"></i> 联系方式设置</h3>
                </div>
                <form method="POST" enctype="multipart/form-data" class="form-group" style="padding: 20px;">
                    <div class="form-group">
                        <label><i class="fab fa-qq"></i> QQ群号：</label>
                        <input type="text" name="contact_qq_group" value="<?php echo htmlspecialchars($contact_qq_group); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fab fa-weixin"></i> 微信二维码：</label>
                        <input type="hidden" name="current_wechat_qr" value="<?php echo htmlspecialchars($contact_wechat_qr); ?>">
                        <input type="file" name="contact_wechat_qr" accept="image/*">
                        <?php if($contact_wechat_qr): ?>
                            <div class="current-image">
                                <p>当前二维码：</p>
                                <img src="../<?php echo htmlspecialchars($contact_wechat_qr); ?>" alt="当前微信二维码" style="max-width: 200px; margin-top: 10px;">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> 联系邮箱：</label>
                        <input type="email" name="contact_email" value="<?php echo htmlspecialchars($contact_email); ?>" required>
                    </div>
                    <button type="submit" name="change_contact" class="btn btn-primary">
                        <i class="fas fa-save"></i> 保存设置
                    </button>
                </form>
            </div>

            <div id="feature-settings" class="card settings-section">
                <div class="card-header">
                    <h3><i class="fas fa-star"></i> 系统特点管理</h3>
                </div>
                <div style="padding: 20px;">
                    <form method="POST" class="form-group" data-form="feature">
                        <input type="hidden" name="feature_id" value="">
                        <div class="form-group">
                            <label><i class="fas fa-icons"></i> 图标类名：</label>
                            <input type="text" name="icon" placeholder="例如: fas fa-shield-alt" required>
                            <small class="form-text">可以在 <a href="https://fontawesome.com/icons" target="_blank">Font Awesome</a> 查找图标</small>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> 标题：</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> 描述：</label>
                            <textarea name="description" rows="5" required placeholder="第一行为主要描述&#10;后续每行为一个功能点"></textarea>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-sort"></i> 排序：</label>
                            <input type="number" name="sort_order" value="0" min="0">
                        </div>
                        <button type="submit" name="save_feature" class="btn btn-primary">
                            <i class="fas fa-save"></i> 保存特点
                        </button>
                    </form>
                   
                   <div class="features-list" style="margin-top: 30px;">
                       <h4>当前系统特点</h4>
                       <?php foreach($features as $feature): ?>
                       <div class="feature-item" style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;">
                           <div class="feature-header" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                               <i class="<?php echo htmlspecialchars($feature['icon']); ?>" style="font-size: 24px; color: #3498db;"></i>
                               <h5 style="margin: 0;"><?php echo htmlspecialchars($feature['title']); ?></h5>
                           </div>
                           <div class="feature-description" style="margin-bottom: 15px;">
                               <?php echo nl2br(htmlspecialchars($feature['description'])); ?>
                           </div>
                           <div class="feature-actions">
                               <button class="btn btn-sm btn-primary edit-feature" 
                                       data-id="<?php echo $feature['id']; ?>"
                                       data-icon="<?php echo htmlspecialchars($feature['icon']); ?>"
                                       data-title="<?php echo htmlspecialchars($feature['title']); ?>"
                                       data-description="<?php echo htmlspecialchars($feature['description']); ?>"
                                       data-sort="<?php echo $feature['sort_order']; ?>">
                                   <i class="fas fa-edit"></i> 编辑
                               </button>
                               <form method="POST" style="display: inline;">
                                   <input type="hidden" name="feature_id" value="<?php echo $feature['id']; ?>">
                                   <button type="submit" name="delete_feature" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('确定要删除这个系统特点吗？')">
                                       <i class="fas fa-trash"></i> 删除
                                   </button>
                               </form>
                           </div>
                       </div>
                       <?php endforeach; ?>
                   </div>
                </div>
            </div>

            <div id="slide-settings" class="card settings-section">
                <div class="card-header">
                    <h3><i class="fas fa-images"></i> 轮播图管理</h3>
                </div>
                <div style="padding: 20px;">
                    <form method="POST" enctype="multipart/form-data" class="form-group">
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> 标题：</label>
                            <input type="text" name="slide_title" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-align-left"></i> 描述：</label>
                            <input type="text" name="slide_description">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-link"></i> 图片链接：</label>
                            <input type="text" name="image_url" placeholder="输入图片URL或上传图片">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-upload"></i> 上传图片：</label>
                            <input type="file" name="slide_image" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-sort"></i> 排序：</label>
                            <input type="number" name="sort_order" value="0" min="0">
                        </div>
                        <button type="submit" name="upload_slide" class="btn btn-primary">
                            <i class="fas fa-plus"></i> 添加轮播图
                        </button>
                    </form>

                    <div class="slides-list" style="margin-top: 20px;">
                        <h4>当前轮播图</h4>
                        <div class="slides-grid">
                            <?php foreach($slides as $slide): ?>
                            <div class="slide-item" style="border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 5px;">
                                <img src="../<?php echo htmlspecialchars($slide['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($slide['title']); ?>"
                                     style="max-width: 200px; height: auto;">
                                <div class="slide-info">
                                    <h5><?php echo htmlspecialchars($slide['title']); ?></h5>
                                    <p><?php echo htmlspecialchars($slide['description']); ?></p>
                                    <p>排序：<?php echo $slide['sort_order']; ?></p>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="slide_id" value="<?php echo $slide['id']; ?>">
                                        <button type="submit" name="delete_slide" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('确定要删除这个轮播图吗？')">
                                            <i class="fas fa-trash"></i> 删除
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="system-info" class="card settings-section">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> 系统信息</h3>
                </div>
                <div class="system-info" style="padding: 20px;">
                    <div class="info-item">
                        <i class="fab fa-php"></i>
                        <label>PHP版本</label>
                        <span><?php echo $system_info['php_version']; ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-server"></i>
                        <label>服务器系统</label>
                        <span><?php echo $system_info['server_os']; ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-cogs"></i>
                        <label>服务器软件</label>
                        <span><?php echo $system_info['server_software']; ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-database"></i>
                        <label>MySQL版本</label>
                        <span><?php echo $system_info['mysql_version']; ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-calendar-alt"></i>
                        <label>安装时间</label>
                        <span><?php echo $system_info['install_time']; ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-memory"></i>
                        <label>内存使用</label>
                        <span><?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <label>服务器时间</label>
                        <span><?php echo date('Y-m-d H:i:s'); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-network-wired"></i>
                        <label>服务器IP</label>
                        <span><?php echo $_SERVER['SERVER_ADDR'] ?? '未知'; ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-hdd"></i>
                        <label>磁盘空间</label>
                        <span>
                            <?php 
                            $total = disk_total_space('/');
                            $free = disk_free_space('/');
                            if($total && $free) {
                                echo '总共: ' . round($total / 1024 / 1024 / 1024, 2) . ' GB, ';
                                echo '剩余: ' . round($free / 1024 / 1024 / 1024, 2) . ' GB';
                            } else {
                                echo '未知';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-upload"></i>
                        <label>上传限制</label>
                        <span><?php echo ini_get('upload_max_filesize'); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <label>执行时限</label>
                        <span><?php echo ini_get('max_execution_time'); ?> 秒</span>
                    </div>
                </div>
                <div style="padding: 20px; border-top: 1px solid #eee;">
                    <p class="warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        为了系统安全，请确保：
                    </p>
                    <ul class="security-list">
                        <li><i class="fas fa-check"></i> 已删除安装目录</li>
                        <li><i class="fas fa-check"></i> 定期修改管理员密码</li>
                        <li><i class="fas fa-check"></i> 使用强密码保护账号</li>
                        <li><i class="fas fa-check"></i> 及时备份重要数据</li>
                        <li><i class="fas fa-check"></i> 定期更新系统</li>
                    </ul>
                </div>
            </div>

            <div id="welcome-settings" class="card settings-section">
                <div class="card-header">
                    <h3><i class="fas fa-bell"></i> 欢迎提示设置</h3>
                </div>
                <form method="POST" class="form-group" style="padding: 20px;">
                    <div class="form-group">
                        <label><i class="fas fa-toggle-on"></i> 启用欢迎提示：</label>
                        <label class="switch">
                            <input type="checkbox" name="welcome_enabled" <?php echo $settings['welcome_enabled'] == '1' ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-comment"></i> 欢迎消息：</label>
                        <input type="text" name="welcome_message" value="<?php echo htmlspecialchars($settings['welcome_message'] ?? '欢迎使用卡密验证系统'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-clock"></i> 显示时长（毫秒）：</label>
                        <input type="number" name="welcome_duration" value="<?php echo htmlspecialchars($settings['welcome_duration'] ?? '3000'); ?>" min="1000" step="100" required>
                    </div>
                    <button type="submit" name="change_welcome" class="btn btn-primary">
                        <i class="fas fa-save"></i> 保存设置
                    </button>
                </form>
            </div>
        </div>
    </div>

    <footer class="footer-copyright">
        <div class="container">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($copyright_text); ?>
        </div>
    </footer>

    <script>
    document.querySelectorAll('.edit-feature').forEach(button => {
        button.addEventListener('click', function() {
            const form = document.querySelector('form[data-form="feature"]');
            const idInput = form.querySelector('input[name="feature_id"]');
            const iconInput = form.querySelector('input[name="icon"]');
            const titleInput = form.querySelector('input[name="title"]');
            const descriptionInput = form.querySelector('textarea[name="description"]');
            const sortInput = form.querySelector('input[name="sort_order"]');
            
            idInput.value = this.dataset.id;
            iconInput.value = this.dataset.icon;
            titleInput.value = this.dataset.title;
            descriptionInput.value = this.dataset.description;
            sortInput.value = this.dataset.sort;
            
            form.scrollIntoView({ behavior: 'smooth' });
        });
    });

    // 页面加载时默认关闭子菜单
    window.addEventListener('DOMContentLoaded', () => {
        document.querySelector('.settings-nav').classList.remove('active');
    });

    // 设置导航展开/收起
    document.querySelector('.settings-toggle').addEventListener('click', function(e) {
        e.preventDefault();
        const settingsNav = document.querySelector('.settings-nav');
        const isActive = settingsNav.classList.contains('active');
        
        if (isActive) {
            settingsNav.classList.remove('active');
            // 等待动画完成后再隐藏子菜单
            setTimeout(() => {
                settingsNav.querySelector('.submenu').style.display = 'none';
            }, 300);
        } else {
            settingsNav.querySelector('.submenu').style.display = 'block';
            // 使用 requestAnimationFrame 确保 display 更改已应用
            requestAnimationFrame(() => {
                settingsNav.classList.add('active');
            });
        }
    });

    // 处理子导航项点击
    document.querySelectorAll('.submenu a').forEach(link => {
        link.addEventListener('click', function() {
            // 移除所有活动状态
            document.querySelectorAll('.submenu a').forEach(a => a.classList.remove('active'));
            // 添加当前活动状态
            this.classList.add('active');
        });
    });

    // 根据滚动位置更新活动的导航项
    window.addEventListener('scroll', () => {
        const sections = document.querySelectorAll('.settings-section');
        let currentSection = '';
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            if (window.pageYOffset >= sectionTop - 100) {
                currentSection = section.id;
            }
        });
        
        document.querySelectorAll('.submenu a').forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${currentSection}`) {
                link.classList.add('active');
            }
        });
    });
    </script>
</body>
</html> 