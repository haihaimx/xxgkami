<?php
session_start();
if(!file_exists("install.lock")){
    header("Location: install/index.php");
    exit;
}

if(isset($_SESSION['admin_id'])){
    header("Location: home/index.php");
    exit;
}

require_once 'config.php';

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("SELECT id, username, password FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];
            header("Location: home/index.php");
            exit;
        } else {
            $error = "用户名或密码错误";
        }
    } catch(PDOException $e) {
        $error = "系统错误，请稍后再试";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>管理员登录 - 卡密验证系统</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --bg-color: #ecf0f3;
            --text-color: #2c3e50;
            --shadow-light: 10px 10px 20px #d1d9e6, -10px -10px 20px #ffffff;
            --shadow-inset: inset 5px 5px 10px #d1d9e6, inset -5px -5px 10px #ffffff;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg-color);
            display: flex;
            flex-direction: column;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(52, 152, 219, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(46, 204, 113, 0.1) 0%, transparent 50%);
        }

        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: var(--shadow-light);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            margin: 0;
            color: var(--text-color);
            font-size: 28px;
            font-weight: 600;
        }

        .login-header p {
            margin: 10px 0 0;
            color: #666;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: var(--text-color);
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: none;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            box-shadow: var(--shadow-inset);
            font-size: 16px;
            color: var(--text-color);
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 45px;
            color: #666;
            font-size: 20px;
        }

        .error {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #3498db, #2ecc71);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .home-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-color);
            text-decoration: none;
            font-weight: 500;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .home-link:hover {
            color: var(--primary-color);
            transform: translateX(-5px);
        }

        .footer-copyright {
            text-align: center;
            padding: 20px;
            color: #666;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="login-container">
            <div class="login-header">
                <h1>管理员登录</h1>
                <p>欢迎回来，请登录您的账号</p>
            </div>

            <?php if(isset($error)): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>用户名</label>
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="请输入用户名" required>
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="请输入密码" required>
                </div>
                <button type="submit">
                    登录 <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <a href="./index.php" class="home-link">
                <i class="fas fa-arrow-left"></i> 返回首页
            </a>
        </div>
    </div>

    <footer class="footer-copyright">
        &copy; <?php echo date('Y'); ?> 小小怪卡密系统 - All Rights Reserved
    </footer>
</body>
</html> 