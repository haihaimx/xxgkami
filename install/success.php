<?php
session_start();
if(!file_exists("../install.lock") || !isset($_SESSION['install_info'])){
    header("Location: index.php");
    exit;
}

$install_info = $_SESSION['install_info'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>安装成功 - 卡密验证系统</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .success-container {
            max-width: 600px;
            margin: 50px auto;
            text-align: center;
        }
        
        .success-icon {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .info-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .info-item {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 3px;
        }
        
        .info-item label {
            color: #666;
            display: inline-block;
            width: 100px;
        }
        
        .link-btn {
            display: inline-block;
            padding: 8px 20px;
            margin: 10px;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            transition: opacity 0.2s;
        }
        
        .link-btn:hover {
            opacity: 0.9;
        }
        
        .home-btn {
            background: #007bff;
        }
        
        .admin-btn {
            background: #28a745;
        }
        
        .warning {
            color: #dc3545;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✓</div>
        <h2>系统安装成功！</h2>
        <p>请保存以下信息，这些信息只显示一次</p>
        
        <div class="info-box">
            <div class="info-item">
                <label>管理员账号：</label>
                <span><?php echo htmlspecialchars($install_info['admin_user']); ?></span>
            </div>
            <div class="info-item">
                <label>管理员密码：</label>
                <span><?php echo htmlspecialchars($install_info['admin_pass']); ?></span>
            </div>
            <div class="info-item">
                <label>系统目录：</label>
                <span><?php echo htmlspecialchars(dirname(dirname($_SERVER['PHP_SELF']))); ?></span>
            </div>
            <?php if($install_info['db_exists']): ?>
            <div class="info-item">
                <label>安装状态：</label>
                <span>检测到已存在的数据库，已重新安装</span>
            </div>
            <?php endif; ?>
        </div>
        
        <div>
            <a href="../index.php" class="link-btn home-btn">访问首页</a>
            <a href="../admin.php" class="link-btn admin-btn">进入后台</a>
        </div>
        
        <p class="warning">请立即删除 install 目录以确保系统安全！</p>
    </div>

    <footer class="footer-copyright">
        <div class="container">
            &copy; <?php echo date('Y'); ?> 小小怪卡密系统 - All Rights Reserved
        </div>
    </footer>
</body>
</html> 