<?php
session_start();
if(!file_exists("install.lock")){
    header("Location: install/index.php");
    exit;
}

require_once 'config.php';

// 初始化消息变量
$card_msg = null;
$error = null;

// 建立数据库连接
try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取API密钥
    $api_key = '';
    try {
        $stmt = $conn->prepare("SELECT value FROM settings WHERE name = 'api_key'");
        $stmt->execute();
        $api_key = $stmt->fetchColumn();
    } catch(PDOException $e) {
        error_log($e->getMessage());
    }
    
    // 获取统计数据
    $total = $conn->query("SELECT COUNT(*) FROM cards")->fetchColumn();
    $used = $conn->query("SELECT COUNT(*) FROM cards WHERE status = 1")->fetchColumn();
    $unused = $total - $used;
    $usage_rate = $total > 0 ? round(($used / $total) * 100, 1) : 0;

    // 获取网站标题和副标题
    $stmt = $conn->prepare("SELECT name, value FROM settings WHERE name IN (
        'site_title', 'site_subtitle', 'copyright_text',
        'contact_qq_group', 'contact_wechat_qr', 'contact_email',
        'welcome_enabled', 'welcome_message', 'welcome_duration'
    )");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $site_title = $settings['site_title'] ?? '卡密验证系统';
    $site_subtitle = $settings['site_subtitle'] ?? '专业的卡密验证解决方案';
    $copyright_text = $settings['copyright_text'] ?? '小小怪卡密系统 - All Rights Reserved';
    $contact_qq_group = $settings['contact_qq_group'] ?? '123456789';
    $contact_wechat_qr = $settings['contact_wechat_qr'] ?? 'assets/images/wechat-qr.jpg';
    $contact_email = $settings['contact_email'] ?? 'support@example.com';

    // 处理卡密验证
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_card'])) {
        try {
            $card_key = isset($_POST['card_key']) ? trim($_POST['card_key']) : '';
            
            if(empty($card_key)) {
                $card_msg = array('type' => 'error', 'msg' => '请输入卡密');
            } else {
                // 检查卡密是否存在
                $stmt = $conn->prepare("SELECT * FROM cards WHERE card_key = ?");
                $stmt->execute([$card_key]);
                $card = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if($card) {
                    if($card['status'] == 0) {
                        // 计算到期时间（如果有设置duration）
                        $expire_time = null;
                        if($card['duration'] > 0) {
                            $expire_time = date('Y-m-d H:i:s', strtotime("+{$card['duration']} days"));
                        }
                        
                        // 更新卡密状态
                        $stmt = $conn->prepare("UPDATE cards SET status = 1, use_time = NOW(), expire_time = ?, verify_method = 'web' WHERE id = ?");
                        $stmt->execute([$expire_time, $card['id']]);
                        
                        // 根据有效期显示不同的成功消息
                        if($card['duration'] > 0) {
                            $card_msg = array('type' => 'success', 'msg' => "卡密验证成功！有效期{$card['duration']}天，到期时间：{$expire_time}");
                        } else {
                            $card_msg = array('type' => 'success', 'msg' => '卡密验证成功！（永久有效）');
                        }
                    } else {
                        // 已使用的卡密
                        if($card['expire_time']) {
                            $card_msg = array('type' => 'error', 'msg' => "此卡密已被使用，使用时间：{$card['use_time']}，到期时间：{$card['expire_time']}");
                        } else {
                            $card_msg = array('type' => 'error', 'msg' => "此卡密已被使用，使用时间：{$card['use_time']}");
                        }
                    }
                } else {
                    $card_msg = array('type' => 'error', 'msg' => '无效的卡密');
                }
            }
        } catch(PDOException $e) {
            error_log($e->getMessage());
            $card_msg = array('type' => 'error', 'msg' => '系统错误，请稍后再试');
        }
    }

    // 获取轮播图数据
    try {
        $stmt = $conn->query("SELECT * FROM slides WHERE status = 1 ORDER BY sort_order ASC");
        $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $slides = [];
    }

    // 获取系统特点
    try {
        $stmt = $conn->query("SELECT * FROM features WHERE status = 1 ORDER BY sort_order ASC");
        $features = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $features = [];
    }
} catch(PDOException $e) {
    error_log($e->getMessage());
    $error = "数据库连接失败，请稍后再试";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($site_title); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* 基础样式 */
        :root {
            --primary-color: #3498db;
            --bg-color: #ecf0f3;
            --text-color: #2c3e50;
            --shadow-light: 10px 10px 20px #d1d9e6, -10px -10px 20px #ffffff;
            --shadow-inset: inset 5px 5px 10px #d1d9e6, inset -5px -5px 10px #ffffff;
            --glass-bg: rgba(255, 255, 255, 0.25);
        }

        body {
            background: var(--bg-color);
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--text-color);
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(52, 152, 219, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(46, 204, 113, 0.1) 0%, transparent 50%);
        }

        /* 导航栏基础样式 */
        .navbar {
            position: fixed;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            width: 250px;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-light);
            z-index: 1000;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: grab;
            user-select: none;
            transition: all 0.3s ease;
        }

        .navbar:active {
            cursor: grabbing;
        }

        .nav-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 5px;
            text-decoration: none;
            color: var(--text-color);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
        }

        .nav-logo i {
            font-size: 24px;
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(45deg, #3498db, #2ecc71);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-logo span {
            font-size: 18px;
            font-weight: 600;
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-links a,
        .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            color: var(--text-color);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .nav-links a i,
        .dropdown-toggle i {
            font-size: 18px;
            min-width: 25px;
            text-align: center;
        }

        .nav-links a:hover,
        .nav-links a.active,
        .dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        /* 下拉菜单样式修改 */
        .dropdown {
            position: relative;
        }

        .dropdown-toggle .fa-chevron-down {
            margin-left: auto;
            font-size: 12px;
            transition: transform 0.3s ease;
        }

        .dropdown:hover .fa-chevron-down {
            transform: rotate(-180deg);
        }

        .dropdown-menu {
            position: absolute;
            left: calc(100% + 15px);
            top: 0;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 10px;
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateX(20px);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-light);
        }

        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateX(0);
        }

        .dropdown-menu a {
            padding: 10px 15px;
            border-radius: 8px;
        }

        /* 管理员按钮样式 */
        .admin-btn {
            background: linear-gradient(45deg, #3498db, #2ecc71);
            color: white !important;
            margin-top: auto;
        }

        .admin-btn:hover {
            background: linear-gradient(45deg, #2980b9, #27ae60);
        }

        /* 主容器调整 */
        .main-container {
            margin-left: 300px;
            margin-right: 30px;
            padding-top: 30px;
            max-width: none;
        }

        /* 移动端样式优化 */
        @media (max-width: 768px) {
            .navbar {
                cursor: default;
                width: 100%;
                left: 0;
                top: 0;
                transform: none;
                border-radius: 0;
                padding: 15px 20px;
            }

            .nav-container {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }

            .nav-logo {
                border-bottom: none;
                padding: 0;
            }

            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--glass-bg);
                padding: 20px;
                flex-direction: column;
            }

            .nav-links.active {
                display: flex;
            }

            .dropdown-menu {
                position: static;
                background: rgba(255, 255, 255, 0.05);
                transform: none;
                margin-top: 10px;
                margin-left: 40px;
                box-shadow: none;
            }

            .mobile-menu-btn {
                display: flex;
            }

            .main-container {
                margin-left: 20px;
                margin-right: 20px;
                margin-top: 80px;
            }
        }

        /* 动画效果 */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .nav-links.active {
            animation: slideDown 0.3s ease-out;
        }

        /* 主容器样式 */
        .main-container {
            margin-left: 100px;
            margin-right: 20px;
            max-width: none;
        }

        /* 英雄区域拟态效果 */
        .hero-section {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: var(--shadow-light);
        }

        /* 统计卡片拟态效果 */
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-light);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 15px 15px 30px #d1d9e6, -15px -15px 30px #ffffff;
        }

        /* 验证表单拟态效果 */
        .verify-container {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow-light);
            text-align: center;
            max-width: 600px;
            margin: 40px auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .verify-container h2 {
            color: var(--text-color);
            margin-bottom: 30px;
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .verify-container h2 i {
            color: #2ecc71;
        }

        .verify-form {
            margin-top: 30px;
        }

        .input-group {
            position: relative;
            margin-bottom: 25px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #3498db;
            font-size: 20px;
        }

        .verify-form input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid rgba(52, 152, 219, 0.2);
            border-radius: 15px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .verify-form input:focus {
            border-color: #3498db;
            box-shadow: 0 0 15px rgba(52, 152, 219, 0.2);
            outline: none;
        }

        .verify-btn {
            background: linear-gradient(45deg, #3498db, #2ecc71);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .verify-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .verify-btn i {
            font-size: 18px;
        }

        /* 提示框样式 */
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            border: 1px solid rgba(46, 204, 113, 0.3);
            color: #2ecc71;
        }

        .alert i {
            font-size: 20px;
        }

        /* 按钮拟态效果 */
        .btn {
            background: var(--glass-bg);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: var(--shadow-light);
            border-radius: 15px;
            padding: 12px 25px;
            color: var(--text-color);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            box-shadow: var(--shadow-inset);
            transform: scale(0.98);
        }

        /* 文档部分拟态效果 */
        .docs-section {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow-light);
            margin-bottom: 30px;
        }

        /* 代码块样式 */
        pre {
            background: rgba(44, 62, 80, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            color: #fff;
            overflow-x: auto;
        }

        /* 表格样式 */
        table {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 15px;
            overflow: hidden;
            width: 100%;
            margin: 20px 0;
        }

        th {
            background: rgba(44, 62, 80, 0.1);
            padding: 15px;
            color: var(--text-color);
        }

        td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* 页脚样式 */
        .footer-copyright {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px 0;
            text-align: center;
            color: var(--text-color);
        }

        /* 动画效果 */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        .stat-card i {
            animation: float 3s ease-in-out infinite;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .stat-card, .docs-section {
                padding: 20px;
            }

            .hero-section {
                padding: 30px 20px;
            }

            pre {
                padding: 15px;
                font-size: 14px;
            }
        }

        /* 联系我们部分样式 */
        .contact-section {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow-light);
        }

        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .contact-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-light);
        }

        .contact-card i {
            font-size: 48px;
            background: linear-gradient(45deg, #3498db, #2ecc71);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }

        .contact-card h3 {
            margin: 0 0 15px;
            color: var(--text-color);
        }

        .contact-card p {
            color: #666;
            margin-bottom: 20px;
        }

        .contact-btn {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(45deg, #3498db, #2ecc71);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .contact-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .qr-code {
            max-width: 200px;
            border-radius: 10px;
            margin-top: 10px;
            box-shadow: var(--shadow-light);
        }

        /* 返回顶部按钮 */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .back-to-top.visible {
            opacity: 1;
            visibility: visible;
        }

        .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-light);
        }

        /* 页面加载动画 */
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-color);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }

        .loader {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(52, 152, 219, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* 导航栏下拉菜单样式 */
        .dropdown {
            position: relative;
        }

        .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 100%;
            transform: translateX(10px);
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 10px 0;
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-light);
        }

        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateX(0);
        }

        .dropdown-menu a {
            padding: 10px 20px;
            color: var(--text-color) !important;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dropdown-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* 轮播图样式优化 */
        .carousel {
            position: relative;
            height: 400px;
            overflow: hidden;
            margin: 30px 0;
            border-radius: 20px;
            box-shadow: var(--shadow-light);
        }

        .carousel-inner {
            height: 100%;
            display: flex;
            transition: transform 0.5s ease-in-out;
        }

        .carousel-item {
            min-width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .carousel-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .carousel-item:hover img {
            transform: scale(1.05);
        }

        .carousel-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 40px;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
            color: white;
            text-align: center;
            transform: translateY(0);
            transition: transform 0.5s ease;
        }

        .carousel-item:hover .carousel-caption {
            transform: translateY(-10px);
        }

        .carousel-control {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            background: var(--glass-bg);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .carousel:hover .carousel-control {
            opacity: 1;
        }

        .carousel-control:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-50%) scale(1.1);
        }

        .carousel-control.prev {
            left: 20px;
        }

        .carousel-control.next {
            right: 20px;
        }

        .carousel-indicators {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 2;
        }

        .carousel-indicators button {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .carousel-indicators button.active {
            background: white;
            transform: scale(1.2);
        }

        /* 特性卡片动画优化 */
        .features-container {
            padding: 40px 0;
        }
        
        .feature-row {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .feature-card {
            flex: 1;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            display: flex;
            gap: 20px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow-light);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #3498db, #2ecc71);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            flex-shrink: 0;
        }
        
        .feature-content {
            flex: 1;
        }
        
        .feature-content h3 {
            margin: 0 0 10px;
            color: var(--text-color);
            font-size: 20px;
        }
        
        .feature-content p {
            margin: 0 0 15px;
            color: #666;
            font-size: 14px;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .feature-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            color: #666;
            font-size: 14px;
        }
        
        .feature-list li i {
            color: #2ecc71;
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .feature-row {
                flex-direction: column;
            }
            
            .feature-card {
                margin-bottom: 20px;
            }
        }

        /* 验证表单优化 */
        .verify-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            padding: 5px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .tab-btn {
            flex: 1;
            padding: 10px;
            border: none;
            background: transparent;
            color: var(--text-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            background: var(--glass-bg);
            backdrop-filter: blur(5px);
            box-shadow: var(--shadow-light);
        }

        .verify-method {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .verify-method.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Toast 提示框样式 */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 15px 25px;
            margin-bottom: 10px;
            box-shadow: var(--shadow-light);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateX(120%);
            transition: transform 0.3s ease;
            min-width: 300px;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-left: 4px solid #2ecc71;
        }

        .toast.error {
            border-left: 4px solid #e74c3c;
        }

        .toast i {
            font-size: 20px;
        }

        .toast.success i {
            color: #2ecc71;
        }

        .toast.error i {
            color: #e74c3c;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        .toast-message {
            color: #666;
            font-size: 14px;
        }

        .toast-close {
            color: #999;
            cursor: pointer;
            padding: 5px;
            transition: color 0.3s ease;
        }

        .toast-close:hover {
            color: #666;
        }

        .code-tabs {
            background: var(--glass-bg);
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
        }

        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .tab-content {
            display: none;
            position: relative;
        }

        .tab-content.active {
            display: block;
        }

        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 5px;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .copy-btn i {
            font-size: 14px;
        }

        .verify-btn-nav {
            background: linear-gradient(45deg, #3498db, #2ecc71);
            color: white !important;
            border-radius: 25px !important;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            transition: all 0.3s ease;
        }

        .verify-btn-nav:hover {
            transform: translateX(5px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        /* 平滑滚动效果 */
        html {
            scroll-behavior: smooth;
            scroll-padding-top: 20px;
        }

        /* 使用指南样式优化 */
        .guide-container {
            position: relative;
            padding: 40px 0;
            margin: 0 40px;
        }
        
        .guide-wrapper {
            overflow: hidden;
            padding: 20px 0;
        }
        
        .guide-cards {
            display: flex;
            gap: 30px;
            transition: transform 0.3s ease;
        }
        
        .guide-card {
            min-width: calc(33.333% - 20px);
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
        }
        
        .guide-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .guide-question {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .guide-question i {
            font-size: 24px;
            color: #3498db;
        }
        
        .guide-question h3 {
            margin: 0;
            font-size: 18px;
            color: var(--text-color);
        }
        
        .guide-answer {
            color: #666;
        }
        
        .guide-answer p {
            margin: 0 0 15px;
        }
        
        .guide-answer ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .guide-answer li {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .guide-answer li i {
            color: #2ecc71;
            font-size: 12px;
        }
        
        .guide-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            background: var(--glass-bg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1;
        }
        
        .guide-nav:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .guide-nav.prev {
            left: -20px;
        }
        
        .guide-nav.next {
            right: -20px;
        }
        
        @media (max-width: 1024px) {
            .guide-card {
                min-width: calc(50% - 15px);
            }
        }
        
        @media (max-width: 768px) {
            .guide-card {
                min-width: calc(100% - 0px);
            }
        }

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
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">
                <i class="fas fa-key"></i> <?php echo htmlspecialchars($site_title); ?>
            </a>
            <div class="nav-links">
                <a href="index.php" class="active"><i class="fas fa-home"></i> 首页</a>
                <a href="#verify" class="verify-btn-nav"><i class="fas fa-check-circle"></i> 卡密验证</a>
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle">
                        <i class="fas fa-book"></i> 使用文档 <i class="fas fa-chevron-down"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a href="#api-docs"><i class="fas fa-code"></i> API文档</a>
                        <a href="#guide"><i class="fas fa-book-open"></i> 使用教程</a>
                        <a href="#faq"><i class="fas fa-question-circle"></i> 常见问题</a>
                    </div>
                </div>
                <a href="#contact"><i class="fas fa-envelope"></i> 联系我们</a>
                <a href="admin.php" class="admin-btn"><i class="fas fa-user-shield"></i> 管理登录</a>
            </div>
            <div class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="hero-section">
            <h1><?php echo htmlspecialchars($site_title); ?></h1>
            <p><?php echo htmlspecialchars($site_subtitle); ?></p>
        </div>

        <div class="carousel">
            <div class="carousel-inner">
                <?php foreach($slides as $slide): ?>
                <div class="carousel-item">
                    <img src="<?php echo htmlspecialchars($slide['image_url']); ?>" alt="<?php echo htmlspecialchars($slide['title']); ?>">
                    <div class="carousel-caption">
                        <h2><?php echo htmlspecialchars($slide['title']); ?></h2>
                        <p><?php echo htmlspecialchars($slide['description']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control prev"><i class="fas fa-chevron-left"></i></button>
            <button class="carousel-control next"><i class="fas fa-chevron-right"></i></button>
            <div class="carousel-indicators"></div>
        </div>

        <div class="features-section">
            <h2><i class="fas fa-star"></i> 系统特点</h2>
            <div class="features-container">
                <?php 
                $feature_count = 0;
                foreach($features as $feature):
                    if($feature_count % 2 == 0) echo '<div class="feature-row">';
                ?>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="<?php echo htmlspecialchars($feature['icon']); ?>"></i>
                        </div>
                        <div class="feature-content">
                            <h3><?php echo htmlspecialchars($feature['title']); ?></h3>
                            <?php 
                            $description_parts = explode("\n", $feature['description']);
                            echo '<p>' . htmlspecialchars($description_parts[0]) . '</p>';
                            if(count($description_parts) > 1):
                            ?>
                            <ul class="feature-list">
                                <?php for($i = 1; $i < count($description_parts); $i++): ?>
                                    <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($description_parts[$i]); ?></li>
                                <?php endfor; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php
                    if($feature_count % 2 == 1 || $feature_count == count($features) - 1) echo '</div>';
                    $feature_count++;
                endforeach;
                ?>
            </div>
        </div>

        <div class="guide-section">
            <h2><i class="fas fa-book"></i> 使用指南</h2>
            <div class="guide-container">
                <button class="guide-nav prev">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="guide-wrapper">
                    <div class="guide-cards">
                        <div class="guide-card">
                            <div class="guide-question">
                                <i class="fas fa-question-circle"></i>
                                <h3>如何获取卡密？</h3>
                            </div>
                            <div class="guide-answer">
                                <p>您可以通过以下方式获取卡密：</p>
                                <ul>
                                    <li><i class="fas fa-check"></i> 联系管理员购买</li>
                                    <li><i class="fas fa-check"></i> 通过官方渠道获取</li>
                                    <li><i class="fas fa-check"></i> 参与活动赠送</li>
                                </ul>
                            </div>
                        </div>
                        <div class="guide-card">
                            <div class="guide-question">
                                <i class="fas fa-question-circle"></i>
                                <h3>支持哪些验证方式？</h3>
                            </div>
                            <div class="guide-answer">
                                <p>系统支持多种验证方式：</p>
                                <ul>
                                    <li><i class="fas fa-check"></i> 网页在线验证</li>
                                    <li><i class="fas fa-check"></i> API接口验证</li>
                                    <li><i class="fas fa-check"></i> GET/POST请求验证</li>
                                </ul>
                            </div>
                        </div>
                        <div class="guide-card">
                            <div class="guide-question">
                                <i class="fas fa-question-circle"></i>
                                <h3>如何进行验证？</h3>
                            </div>
                            <div class="guide-answer">
                                <p>验证步骤非常简单：</p>
                                <ul>
                                    <li><i class="fas fa-check"></i> 输入您的卡密</li>
                                    <li><i class="fas fa-check"></i> 点击验证按钮</li>
                                    <li><i class="fas fa-check"></i> 等待系统验证结果</li>
                                </ul>
                            </div>
                        </div>
                        <div class="guide-card">
                            <div class="guide-question">
                                <i class="fas fa-question-circle"></i>
                                <h3>验证后如何使用？</h3>
                            </div>
                            <div class="guide-answer">
                                <p>验证成功后：</p>
                                <ul>
                                    <li><i class="fas fa-check"></i> 系统自动激活</li>
                                    <li><i class="fas fa-check"></i> 开始使用功能</li>
                                    <li><i class="fas fa-check"></i> 查看到期时间</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <button class="guide-nav next">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-key"></i>
                <h3>总卡密数</h3>
                <div class="value"><?php echo $total; ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <h3>已使用</h3>
                <div class="value"><?php echo $used; ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <h3>未使用</h3>
                <div class="value"><?php echo $unused; ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-percentage"></i>
                <h3>使用率</h3>
                <div class="value"><?php echo $usage_rate; ?>%</div>
            </div>
        </div>

        <div id="verify" class="verify-container">
            <h2><i class="fas fa-check-circle"></i> 卡密验证</h2>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($card_msg)): ?>
                <div class="alert alert-<?php echo $card_msg['type']; ?>">
                    <i class="fas fa-<?php echo $card_msg['type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $card_msg['msg']; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="verify-form">
                <div class="input-group">
                    <div class="input-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <input type="text" name="card_key" placeholder="请输入您的卡密" required>
                </div>
                <button type="submit" name="verify_card" value="1" class="verify-btn">
                    <i class="fas fa-check"></i> 立即验证
                </button>
            </form>
        </div>

        <div class="history-container">
            <h2><i class="fas fa-history"></i> 最近验证记录</h2>
            <?php
            try {
                $stmt = $conn->query("
                    SELECT c.card_key, c.use_time, c.expire_time, c.duration,
                           c.verify_method,
                           CASE 
                               WHEN c.expire_time IS NOT NULL AND c.expire_time < NOW() THEN '已过期'
                               WHEN c.expire_time IS NOT NULL THEN '使用中'
                               ELSE '永久有效'
                           END as status,
                           CASE c.verify_method
                               WHEN 'web' THEN '网页验证'
                               WHEN 'post' THEN 'POST验证'
                               WHEN 'get' THEN 'GET验证'
                               ELSE '网页验证'
                           END as verify_method_text
                    FROM cards c 
                    WHERE c.status = 1 
                    ORDER BY c.use_time DESC 
                    LIMIT 5
                ");
                $records = $stmt->fetchAll();
                
                if(count($records) > 0){
                    echo '<div class="history-table-container">';
                    echo '<table class="history-table">';
                    echo '<tr>
                            <th>卡密</th>
                            <th>使用时间</th>
                            <th>有效期</th>
                            <th>到期时间</th>
                            <th>状态</th>
                            <th>验证方式</th>
                          </tr>';
                    foreach($records as $r){
                        $masked_key = substr($r['card_key'], 0, 4) . '****' . substr($r['card_key'], -4);
                        echo '<tr>';
                        echo '<td>' . $masked_key . '</td>';
                        echo '<td>' . $r['use_time'] . '</td>';
                        echo '<td>' . ($r['duration'] == 0 ? '永久' : $r['duration'] . '天') . '</td>';
                        echo '<td>' . ($r['expire_time'] ?: '-') . '</td>';
                        echo '<td><span class="status-badge ' . strtolower($r['status']) . '">' . $r['status'] . '</span></td>';
                        echo '<td><span class="method-badge ' . strtolower($r['verify_method']) . '">' . $r['verify_method_text'] . '</span></td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '</div>';
                } else {
                    echo '<p class="no-records">暂无验证记录</p>';
                }
            } catch(PDOException $e) {
                echo '<p class="no-records">暂无验证记录</p>';
            }
            ?>
        </div>

        <!-- API文档部分 -->
        <div id="api-docs" class="docs-section">
            <h2><i class="fas fa-code"></i> API文档</h2>
            <div class="docs-content">
                <div class="api-intro">
                    <h3>接口说明</h3>
                    <p>本系统提供完整的RESTful API接口，支持多种验证方式。所有接口都需要通过API密钥进行认证。</p>
                </div>

                <div class="api-endpoints">
                    <h3>验证接口</h3>
                    <div class="endpoint-card">
                        <div class="endpoint-header">
                            <span class="method post">POST</span>
                            <span class="endpoint-url">/api/verify.php</span>
                        </div>
                        <div class="endpoint-body">
                            <h4>请求头</h4>
                            <pre><code>Content-Type: application/json
X-API-KEY: your_api_key</code></pre>

                            <h4>请求参数</h4>
                            <table class="params-table">
                                <tr>
                                    <th>参数名</th>
                                    <th>类型</th>
                                    <th>必选</th>
                                    <th>说明</th>
                                </tr>
                                <tr>
                                    <td>card_key</td>
                                    <td>string</td>
                                    <td>是</td>
                                    <td>要验证的卡密</td>
                                </tr>
                            </table>

                            <h4>响应示例</h4>
                            <pre><code>{
    "code": 0,
    "message": "验证成功",
    "data": {
        "card_key": "xxx",
        "status": 1,
        "use_time": "2024-xx-xx xx:xx:xx",
        "expire_time": "2024-xx-xx xx:xx:xx",
        "duration": 30
    }
}</code></pre>
                        </div>
                    </div>
                </div>

                <div class="api-examples">
                    <h3>调用示例</h3>
                    <div class="code-tabs">
                        <div class="tab-buttons">
                            <button class="tab-btn active" data-tab="curl">cURL</button>
                            <button class="tab-btn" data-tab="php">PHP</button>
                            <button class="tab-btn" data-tab="python">Python</button>
                        </div>
                        <div class="tab-contents">
                            <div class="tab-content active" id="curl-content">
                                <pre><code>curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: <?php echo htmlspecialchars($api_key); ?>" \
  -d '{"card_key":"your_card_key"}' \
  <?php echo rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]", '/'); ?>/api/verify.php</code></pre>
                                <button class="copy-btn" onclick="copyCode(this)">
                                    <i class="fas fa-copy"></i> 复制代码
                                </button>
                            </div>
                            <div class="tab-content" id="php-content">
                                <pre><code><?php
$code = <<<'EOD'
<?php
// 验证卡密示例
function verifyCard($apiKey, $cardKey) {
    $url = "%s/api/verify.php";
    $data = array("card_key" => $cardKey);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "X-API-KEY: " . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    return false;
}

// 使用示例
$apiKey = "your_api_key";
$cardKey = "your_card_key";
$result = verifyCard($apiKey, $cardKey);

if ($result) {
    if ($result["code"] === 0) {
        echo "验证成功！\n";
        print_r($result["data"]);
    } else {
        echo "验证失败：" . $result["message"] . "\n";
    }
} else {
    echo "请求失败，请检查网络连接\n";
}
EOD;
echo htmlspecialchars(sprintf($code, rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]", '/')));
?></code></pre>
                                <button class="copy-btn" onclick="copyCode(this)">
                                    <i class="fas fa-copy"></i> 复制代码
                                </button>
                            </div>
                            <div class="tab-content" id="python-content">
                                <pre><code>import requests
import json

def verify_card(api_key: str, card_key: str) -> dict:
    """
    验证卡密
    :param api_key: API密钥
    :param card_key: 卡密
    :return: 验证结果
    """
    url = "<?php echo rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]", '/'); ?>/api/verify.php"
    headers = {
        "Content-Type": "application/json",
        "X-API-KEY": api_key
    }
    data = {
        "card_key": card_key
    }
    
    try:
        response = requests.post(url, headers=headers, json=data)
        response.raise_for_status()
        result = response.json()
        
        if result["code"] == 0:
            print("验证成功！")
            print("卡密信息:", result["data"])
        else:
            print("验证失败：", result["message"])
            
        return result
    except requests.exceptions.RequestException as e:
        print("请求失败：", str(e))
        return None

# 使用示例
api_key = "your_api_key"
card_key = "your_card_key"
result = verify_card(api_key, card_key)</code></pre>
                                <button class="copy-btn" onclick="copyCode(this)">
                                    <i class="fas fa-copy"></i> 复制代码
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 使用教程部分 -->
        <div id="guide" class="docs-section">
            <h2><i class="fas fa-book-open"></i> 使用教程</h2>
            <div class="docs-content">
                <div class="tutorial-steps">
                    <div class="tutorial-step">
                        <div class="step-icon">
                            <i class="fas fa-key"></i>
                        </div>
                        <div class="step-content">
                            <h3>1. 获取卡密</h3>
                            <p>从管理员处获取授权卡密，每个卡密都有其特定的使用期限。</p>
                            <div class="step-note">
                                <i class="fas fa-info-circle"></i>
                                <span>卡密一旦使用将无法重复验证，请妥善保管。</span>
                            </div>
                        </div>
                    </div>

                    <div class="tutorial-step">
                        <div class="step-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="step-content">
                            <h3>2. 选择验证方式</h3>
                            <p>系统支持多种验证方式：</p>
                            <ul>
                                <li>网页验证 - 直接在网页输入卡密验证</li>
                                <li>API验证 - 通过接口进行程序验证</li>
                                <li>GET/POST请求 - 支持多种请求方式</li>
                            </ul>
                        </div>
                    </div>

                    <div class="tutorial-step">
                        <div class="step-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="step-content">
                            <h3>3. 集成说明</h3>
                            <p>如果您需要将验证系统集成到自己的程序中：</p>
                            <ol>
                                <li>获取API密钥</li>
                                <li>参考API文档进行接口调用</li>
                                <li>处理验证结果</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 常见问题部分 -->
        <div id="faq" class="docs-section">
            <h2><i class="fas fa-question-circle"></i> 常见问题</h2>
            <div class="docs-content">
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-question">
                            <i class="fas fa-question"></i>
                            <h3>卡密可以重复使用吗？</h3>
                        </div>
                        <div class="faq-answer">
                            <p>不可以，每个卡密都是一次性的，验证后将无法再次使用。</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <i class="fas fa-question"></i>
                            <h3>如何获取API密钥？</h3>
                        </div>
                        <div class="faq-answer">
                            <p>请联系管理员获取API密钥，API密钥用于接口认证。</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <i class="fas fa-question"></i>
                            <h3>验证失败怎么办？</h3>
                        </div>
                        <div class="faq-answer">
                            <p>请检查：</p>
                            <ul>
                                <li>卡密是否正确</li>
                                <li>卡密是否已被使用</li>
                                <li>API密钥是否正确</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 联系我们部分 -->
        <div id="contact" class="contact-section">
            <h2><i class="fas fa-envelope"></i> 联系我们</h2>
            <div class="contact-grid">
                <div class="contact-card">
                    <i class="fab fa-qq"></i>
                    <h3>QQ交流群</h3>
                    <p><?php echo htmlspecialchars($contact_qq_group); ?></p>
                    <a href="https://qm.qq.com/cgi-bin/qm/qr?k=<?php echo htmlspecialchars($contact_qq_group); ?>" class="contact-btn" target="_blank">
                        加入群聊
                    </a>
                </div>
                
                <div class="contact-card">
                    <i class="fab fa-weixin"></i>
                    <h3>微信客服</h3>
                    <p>扫码添加客服</p>
                    <img src="<?php echo htmlspecialchars($contact_wechat_qr); ?>" alt="微信二维码" class="qr-code">
                </div>
                
                <div class="contact-card">
                    <i class="fas fa-envelope"></i>
                    <h3>电子邮件</h3>
                    <p><?php echo htmlspecialchars($contact_email); ?></p>
                    <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" class="contact-btn">
                        发送邮件
                    </a>
                </div>
            </div>
        </div>

        <!-- 返回顶部按钮 -->
        <button id="backToTop" class="back-to-top">
            <i class="fas fa-arrow-up"></i>
        </button>

        <!-- 页面加载动画 -->
        <div class="page-loader">
            <div class="loader"></div>
        </div>
    </div>

    <footer class="footer-copyright">
        <div class="container">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($copyright_text); ?>
        </div>
    </footer>

    <div class="toast-container"></div>

    <?php if($settings['welcome_enabled'] == '1'): ?>
    <script>
    window.addEventListener('DOMContentLoaded', () => {
        // 创建欢迎提示
        const toast = document.createElement('div');
        toast.className = 'toast success show';
        toast.innerHTML = `
            <i class="fas fa-bell"></i>
            <div class="toast-content">
                <div class="toast-message"><?php echo htmlspecialchars($settings['welcome_message']); ?></div>
            </div>
        `;
    
        // 添加到容器
        document.querySelector('.toast-container').appendChild(toast);
    
        // 设置定时关闭
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, <?php echo (int)($settings['welcome_duration'] ?? 3000); ?>);
    });
    </script>
    <?php endif; ?>

    <script>
        // 轮播图功能
        const carousel = document.querySelector('.carousel');
        const carouselInner = carousel.querySelector('.carousel-inner');
        const items = carousel.querySelectorAll('.carousel-item');
        const prevBtn = carousel.querySelector('.prev');
        const nextBtn = carousel.querySelector('.next');
        const indicators = carousel.querySelector('.carousel-indicators');

        let currentIndex = 0;
        const totalItems = items.length;

        // 创建指示器
        items.forEach((_, index) => {
            const button = document.createElement('button');
            button.addEventListener('click', () => goToSlide(index));
            indicators.appendChild(button);
        });

        const indicatorBtns = indicators.querySelectorAll('button');
        updateIndicators();

        // 自动播放
        let autoplayInterval = setInterval(nextSlide, 5000);

        // 鼠标悬停时暂停自动播放
        carousel.addEventListener('mouseenter', () => clearInterval(autoplayInterval));
        carousel.addEventListener('mouseleave', () => autoplayInterval = setInterval(nextSlide, 5000));

        // 上一张/下一张
        prevBtn.addEventListener('click', prevSlide);
        nextBtn.addEventListener('click', nextSlide);

        function nextSlide() {
            currentIndex = (currentIndex + 1) % totalItems;
            updateCarousel();
        }

        function prevSlide() {
            currentIndex = (currentIndex - 1 + totalItems) % totalItems;
            updateCarousel();
        }

        function goToSlide(index) {
            currentIndex = index;
            updateCarousel();
        }

        function updateCarousel() {
            carouselInner.style.transform = `translateX(-${currentIndex * 100}%)`;
            updateIndicators();
        }

        function updateIndicators() {
            indicatorBtns.forEach((btn, index) => {
                btn.classList.toggle('active', index === currentIndex);
            });
        }

        // 返回顶部按钮
        const backToTop = document.getElementById('backToTop');

        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });

        backToTop.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // 页面加载动画
        window.addEventListener('load', () => {
            const loader = document.querySelector('.page-loader');
            loader.style.opacity = '0';
            setTimeout(() => {
                loader.style.display = 'none';
            }, 500);
        });

        // 平滑滚动
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // 修改验证表单处理代码
        document.addEventListener('DOMContentLoaded', function() {
            const verifyForm = document.querySelector('.verify-form');
            
            if (verifyForm) {
                verifyForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // 创建一个新的表单
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.href;
                    
                    // 添加卡密输入
                    const cardKeyInput = document.createElement('input');
                    cardKeyInput.type = 'hidden';
                    cardKeyInput.name = 'card_key';
                    cardKeyInput.value = this.querySelector('input[name="card_key"]').value;
                    form.appendChild(cardKeyInput);
                    
                    // 添加验证按钮
                    const verifyBtn = document.createElement('input');
                    verifyBtn.type = 'hidden';
                    verifyBtn.name = 'verify_card';
                    verifyBtn.value = '1';
                    form.appendChild(verifyBtn);
                    
                    // 显示加载状态
                    const submitBtn = this.querySelector('.verify-btn');
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 验证中...';
                    submitBtn.disabled = true;
                    
                    // 提交表单
                    document.body.appendChild(form);
                    form.submit();
                });
            }
            
            // 如果存在验证结果消息，滚动到验证区域
            const alert = document.querySelector('.alert');
            if (alert) {
                const verifyContainer = document.querySelector('.verify-container');
                if (verifyContainer) {
                    setTimeout(() => {
                        verifyContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        // 显示 Toast 提示
                        const isSuccess = alert.classList.contains('alert-success');
                        const message = alert.textContent.trim();
                        showToast(
                            isSuccess ? 'success' : 'error',
                            isSuccess ? '验证成功' : '验证失败',
                            message
                        );
                    }, 100);
                }
            }
        });

        // Toast 提示函数
        function showToast(type, title, message, duration = 3000) {
            const toastContainer = document.querySelector('.toast-container');
            
            // 创建 toast 元素
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            // 设置 toast 内容
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <div class="toast-close">
                    <i class="fas fa-times"></i>
                </div>
            `;
            
            // 添加到容器
            toastContainer.appendChild(toast);
            
            // 显示动画
            setTimeout(() => toast.classList.add('show'), 10);
            
            // 关闭按钮事件
            toast.querySelector('.toast-close').addEventListener('click', () => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            });
            
            // 自动关闭
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        // 可拖动导航栏功能
        function initDraggableNavbar() {
            const navbar = document.querySelector('.navbar');
            let isDragging = false;
            let currentX;
            let currentY;
            let initialX;
            let initialY;
            let xOffset = parseInt(localStorage.getItem('navbarX')) || 20;
            let yOffset = parseInt(localStorage.getItem('navbarY')) || window.innerHeight / 2;

            // 初始化位置
            updateNavbarPosition(xOffset, yOffset);

            // 鼠标按下事件
            navbar.addEventListener('mousedown', dragStart);
            // 鼠标移动事件
            document.addEventListener('mousemove', drag);
            // 鼠标松开事件
            document.addEventListener('mouseup', dragEnd);

            function dragStart(e) {
                if (e.target.closest('.nav-links') || window.innerWidth <= 768) return;
                
                initialX = e.clientX - xOffset;
                initialY = e.clientY - yOffset;

                if (e.target === navbar || e.target.closest('.nav-logo')) {
                    isDragging = true;
                    navbar.style.transition = 'none';
                    navbar.style.cursor = 'grabbing';
                }
            }

            function drag(e) {
                if (!isDragging) return;

                e.preventDefault();
                currentX = e.clientX - initialX;
                currentY = e.clientY - initialY;

                // 限制在视窗范围内
                currentX = Math.min(Math.max(currentX, 0), window.innerWidth - navbar.offsetWidth);
                currentY = Math.min(Math.max(currentY, 0), window.innerHeight - navbar.offsetHeight);

                xOffset = currentX;
                yOffset = currentY;

                updateNavbarPosition(currentX, currentY);
            }

            function dragEnd() {
                if (!isDragging) return;
                
                isDragging = false;
                navbar.style.cursor = 'grab';
                navbar.style.transition = 'all 0.3s ease';

                // 判断是否吸附到左右两边
                if (xOffset < window.innerWidth / 2) {
                    xOffset = 20; // 左边距
                } else {
                    xOffset = window.innerWidth - navbar.offsetWidth - 20; // 右边距
                }

                // 保存位置到 localStorage
                localStorage.setItem('navbarX', xOffset);
                localStorage.setItem('navbarY', yOffset);

                updateNavbarPosition(xOffset, yOffset);
            }

            function updateNavbarPosition(x, y) {
                navbar.style.left = `${x}px`;
                navbar.style.top = `${y}px`;
                navbar.style.transform = 'none';
            }

            // 窗口大小改变时调整位置
            window.addEventListener('resize', () => {
                if (window.innerWidth <= 768) {
                    navbar.style.left = '0';
                    navbar.style.top = '0';
                    navbar.style.transform = 'none';
                    return;
                }

                // 确保导航栏在视窗范围内
                xOffset = Math.min(xOffset, window.innerWidth - navbar.offsetWidth - 20);
                yOffset = Math.min(yOffset, window.innerHeight - navbar.offsetHeight - 20);
                updateNavbarPosition(xOffset, yOffset);
            });
        }

        // 初始化可拖动导航栏
        initDraggableNavbar();

        // 代码示例切换功能
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', () => {
                // 移除所有活动状态
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                // 添加新的活动状态
                button.classList.add('active');
                document.getElementById(`${button.dataset.tab}-content`).classList.add('active');
            });
        });

        // 复制代码功能
        function copyCode(button) {
            const codeBlock = button.previousElementSibling;
            const code = codeBlock.textContent;
            
            navigator.clipboard.writeText(code).then(() => {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> 已复制';
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                }, 2000);
            }).catch(err => {
                console.error('复制失败:', err);
                button.innerHTML = '<i class="fas fa-times"></i> 复制失败';
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                }, 2000);
            });
        }

        // 使用指南滑动功能
        const guideCards = document.querySelector('.guide-cards');
        const prevGuideBtn = document.querySelector('.guide-nav.prev');
        const nextGuideBtn = document.querySelector('.guide-nav.next');
        let currentGuidePosition = 0;
        const cardWidth = document.querySelector('.guide-card').offsetWidth + 30; // 包含间距
        const totalCards = document.querySelectorAll('.guide-card').length;
        const maxPosition = -(totalCards - Math.floor(guideCards.offsetWidth / cardWidth)) * cardWidth;

        function updateGuidePosition() {
            guideCards.style.transform = `translateX(${currentGuidePosition}px)`;
            
            // 更新按钮状态
            prevGuideBtn.style.opacity = currentGuidePosition === 0 ? '0.5' : '1';
            nextGuideBtn.style.opacity = currentGuidePosition <= maxPosition ? '0.5' : '1';
        }

        prevGuideBtn.addEventListener('click', () => {
            if (currentGuidePosition < 0) {
                currentGuidePosition += cardWidth;
                updateGuidePosition();
            }
        });

        nextGuideBtn.addEventListener('click', () => {
            if (currentGuidePosition > maxPosition) {
                currentGuidePosition -= cardWidth;
                updateGuidePosition();
            }
        });

        // 添加触摸滑动支持
        let touchStartX = 0;
        let touchEndX = 0;

        guideCards.addEventListener('touchstart', e => {
            touchStartX = e.touches[0].clientX;
        });

        guideCards.addEventListener('touchmove', e => {
            touchEndX = e.touches[0].clientX;
        });

        guideCards.addEventListener('touchend', () => {
            const swipeDistance = touchStartX - touchEndX;
            if (Math.abs(swipeDistance) > 50) { // 最小滑动距离
                if (swipeDistance > 0 && currentGuidePosition > maxPosition) {
                    currentGuidePosition -= cardWidth;
                } else if (swipeDistance < 0 && currentGuidePosition < 0) {
                    currentGuidePosition += cardWidth;
                }
                updateGuidePosition();
            }
        });

        // 窗口大小改变时重新计算
        window.addEventListener('resize', () => {
            const newCardWidth = document.querySelector('.guide-card').offsetWidth + 30;
            const newMaxPosition = -(totalCards - Math.floor(guideCards.offsetWidth / newCardWidth)) * newCardWidth;
            
            // 调整当前位置
            if (currentGuidePosition < newMaxPosition) {
                currentGuidePosition = newMaxPosition;
                updateGuidePosition();
            }
        });
    </script>
</body>
</html> 