<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// 如果已安装，直接跳转到首页
if(file_exists("../install.lock")){
    header("Location: ../index.php");
    exit;
}

// 每次直接访问install/index.php时重置安装步骤
if($_SERVER['REQUEST_METHOD'] == 'GET'){
    $_SESSION['install_step'] = 1;
}

// 处理步骤跳转
if(isset($_POST['next_step'])){
    $_SESSION['install_step']++;
}

// 处理返回上一步
if(isset($_POST['prev_step']) && $_SESSION['install_step'] > 1){
    $_SESSION['install_step']--;
}

// 处理安装请求
if(isset($_POST['install'])){
    header('Content-Type: application/json; charset=utf-8');
    
    $response = array(
        'status' => 'error',
        'message' => '',
        'step' => '',
        'sql' => ''
    );
    
    try {
        $host = trim($_POST['host']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $database = trim($_POST['database']);
        $admin_user = trim($_POST['admin_user']);
        $admin_pass = password_hash(trim($_POST['admin_pass']), PASSWORD_DEFAULT);
        
        // 第一步：连接数据库
        $response['step'] = '连接数据库';
        $conn = new PDO("mysql:host=$host", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->exec("set names utf8mb4");
        
        // 第二步：检查数据库是否存在
        $response['step'] = '检查数据库';
        $stmt = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$database'");
        $dbExists = $stmt->fetch();
        
        if($dbExists) {
            $response['step'] = '覆盖安装';
            // 创建数据库备份名称
            $backup_name = $database . '_backup_' . date('YmdHis');
            // 备份原数据库
            $conn->exec("CREATE DATABASE IF NOT EXISTS `$backup_name`");
            $conn->exec("CREATE TABLE `$backup_name`.`install_backup` (backup_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            
            // 删除原数据库
            $conn->exec("DROP DATABASE IF EXISTS `$database`");
        }
        
        // 第三步：创建新数据库
        $response['step'] = '创建数据库';
        $conn->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $conn->exec("USE `$database`");
        
        // 第四步：执行SQL文件
        $response['step'] = '创建数据表';
        $sql_file = file_get_contents('install.sql');
        $conn->exec($sql_file);
        
        // 第五步：创建管理员账号
        $response['step'] = '创建管理员账号';
        try {
            $stmt = $conn->prepare("INSERT INTO `admins` (username, password) VALUES (?, ?)");
            if (!$stmt->execute([$admin_user, $admin_pass])) {
                throw new Exception("创建管理员账号失败");
            }
            
            // 验证管理员账号是否创建成功
            $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
            $stmt->execute([$admin_user]);
            if (!$stmt->fetch()) {
                throw new Exception("管理员账号验证失败");
            }
        } catch (Exception $e) {
            throw new Exception("创建管理员账号失败: " . $e->getMessage());
        }
        
        // 第六步：更新API设置
        $response['step'] = '更新系统设置';
        $api_key = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'api_key'");
        $stmt->execute([$api_key]);
        $stmt = $conn->prepare("UPDATE settings SET value = '0' WHERE name = 'api_enabled'");
        $stmt->execute();
        
        // 创建配置文件和锁定文件
        $response['step'] = '生成配置文件';
        $config_content = "<?php
define('DB_HOST', '$host');
define('DB_USER', '$username');
define('DB_PASS', '$password');
define('DB_NAME', '$database');
";
        file_put_contents("../config.php", $config_content);
        file_put_contents("../install.lock", date('Y-m-d H:i:s'));
        
        $response['status'] = 'success';
        $response['message'] = '安装成功';
        
    } catch(PDOException $e) {
        $response['status'] = 'error';
        $response['message'] = "数据库错误: " . $e->getMessage();
        if(isset($response['step'])) {
            $response['message'] = "{$response['step']}: " . $response['message'];
        }
    } catch(Exception $e) {
        $response['status'] = 'error';
        $response['message'] = "系统错误: " . $e->getMessage();
    }
    
    die(json_encode($response, JSON_UNESCAPED_UNICODE));
}

// 系统检测函数
function checkSystem() {
    $requirements = array();
    
    // 检查PHP版本
    $requirements['php_version'] = array(
        'name' => 'PHP版本',
        'required' => '≥ 7.0',
        'current' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '7.0.0', '>=')
    );
    
    // 检查服务器类型
    $server_software = $_SERVER['SERVER_SOFTWARE'];
    $is_nginx = stripos($server_software, 'nginx') !== false;
    $requirements['server'] = array(
        'name' => '服务器类型',
        'required' => 'Nginx',
        'current' => $server_software,
        'status' => $is_nginx
    );
    
    // 检查PDO扩展
    $requirements['pdo'] = array(
        'name' => 'PDO扩展',
        'required' => '已安装',
        'current' => extension_loaded('pdo') ? '已安装' : '未安装',
        'status' => extension_loaded('pdo')
    );
    
    // 检查PDO MySQL扩展
    $requirements['pdo_mysql'] = array(
        'name' => 'PDO MySQL扩展',
        'required' => '已安装',
        'current' => extension_loaded('pdo_mysql') ? '已安装' : '未安装',
        'status' => extension_loaded('pdo_mysql')
    );
    
    return $requirements;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>系统安装</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .welcome-content {
            height: 400px;
            overflow-y: scroll;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .step-header {
            margin-bottom: 30px;
            text-align: center;
        }
        .step-header .step {
            display: inline-block;
            margin: 0 10px;
            padding: 10px 20px;
            background: #f5f5f5;
            border-radius: 20px;
        }
        .step-header .step.active {
            background: #007bff;
            color: white;
        }
        .step-header .step.completed {
            background: #28a745;
            color: white;
        }
        .requirement-item {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .requirement-item.success {
            border-color: #28a745;
        }
        .requirement-item.error {
            border-color: #dc3545;
        }
        .status-icon {
            float: right;
            font-weight: bold;
        }
        .success .status-icon {
            color: #28a745;
        }
        .error .status-icon {
            color: #dc3545;
        }
        #next-step {
            display: none;
        }
        .install-progress {
            display: none;
            margin-top: 20px;
        }
        
        .progress-bar {
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: #007bff;
            width: 0;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            text-align: center;
            color: #666;
        }
        
        .install-steps {
            margin-top: 15px;
        }
        
        .install-step {
            padding: 5px 0;
            color: #666;
        }
        
        .install-step.active {
            color: #007bff;
            font-weight: bold;
        }
        
        .install-step.completed {
            color: #28a745;
        }
        
        .install-step .step-icon {
            margin-right: 10px;
        }
        
        .button-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        
        .button-group button {
            min-width: 100px;
        }
        
        .prev-btn {
            background: #6c757d;
        }
        
        .prev-btn:hover {
            background: #5a6268;
        }

        .install-container {
            padding-bottom: 60px; /* 为底部版权预留空间 */
        }

        .footer-copyright {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 15px 0;
            background: #f8f9fa;
            color: #6c757d;
            text-align: center;
            border-top: 1px solid #dee2e6;
            left: 0;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="step-header">
            <div class="step <?php echo $_SESSION['install_step'] >= 1 ? 'completed' : ''; ?>">欢迎使用</div>
            <div class="step <?php echo $_SESSION['install_step'] == 2 ? 'active' : ($_SESSION['install_step'] > 2 ? 'completed' : ''); ?>">环境检测</div>
            <div class="step <?php echo $_SESSION['install_step'] == 3 ? 'active' : ''; ?>">系统安装</div>
        </div>

        <?php if($_SESSION['install_step'] == 1): ?>
        <!-- 步骤1：欢迎页面 -->
        <h2>欢迎安装卡密验证系统</h2>
        <div class="welcome-content" id="welcome-content">
            <h3>系统介绍</h3>
            <p>这是一个安全可靠的卡密验证系统，主要功能包括：</p>
            <ul>
                <li>安全的卡密生成和验证</li>
                <li>完善的后台管理功能</li>
                <li>直观的数据统计</li>
                <li>便捷的用户界面</li>
            </ul>
            
            <h3>安装须知</h3>
            <p>安装本系统需要满足以下条件：</p>
            <ul>
                <li>PHP版本 ≥ 7.0</li>
                <li>Nginx服务器</li>
                <li>PDO扩展支持</li>
                <li>PDO MySQL扩展支持</li>
            </ul>
            
            <h3>使用协议</h3>
            <p>1. 本系统仅供学习交流使用</p>
            <p>2. 请勿用于非法用途</p>
            <p>3. 使用本系统造成的任何问题，开发者不承担责任</p>
            
            <h3>安装说明</h3>
            <p>1. 请确保您的服务器环境满足上述要求</p>
            <p>2. 安装过程中请保持网络连接稳定</p>
            <p>3. 请准备好数据库连接信息</p>
            <p>4. 安装完成后请妥善保管管理员账号信息</p>
            
            <h3>注意事项</h3>
            <p>1. 安装前请备份重要数据</p>
            <p>2. 如果已有数据库，系统会先删除再创建</p>
            <p>3. 请确保数据库用户具有创建数据库的权限</p>
            <p>4. 安装完成后会自动创建配置文件</p>
        </div>
        <form method="POST" id="welcome-form">
            <div class="button-group">
                <div></div> <!-- 空div用于占位，保持按钮右对齐 -->
                <button type="submit" name="next_step" id="next-step" disabled>下一步</button>
            </div>
        </form>
        
        <?php elseif($_SESSION['install_step'] == 2): ?>
        <!-- 步骤2：环境检测 -->
        <h2>系统环境检测</h2>
        <?php
        $requirements = checkSystem();
        $all_passed = true;
        foreach($requirements as $req):
            $class = $req['status'] ? 'success' : 'error';
            if(!$req['status']) $all_passed = false;
        ?>
        <div class="requirement-item <?php echo $class; ?>">
            <span class="status-icon"><?php echo $req['status'] ? '✓' : '×'; ?></span>
            <h4><?php echo $req['name']; ?></h4>
            <p>要求：<?php echo $req['required']; ?></p>
            <p>当前：<?php echo $req['current']; ?></p>
        </div>
        <?php endforeach; ?>
        
        <form method="POST">
            <div class="button-group">
                <button type="submit" name="prev_step" class="prev-btn">上一步</button>
                <button type="submit" name="next_step" <?php echo $all_passed ? '' : 'disabled'; ?>>
                    <?php echo $all_passed ? '下一步' : '环境检测未通过'; ?>
                </button>
            </div>
        </form>
        
        <?php else: ?>
        <!-- 步骤3：数据库配置 -->
        <h2>数据库配置</h2>
        <?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>
        <form method="POST" id="install-form">
            <div class="form-group">
                <label>数据库地址：</label>
                <input type="text" name="host" value="localhost" required>
            </div>
            <div class="form-group">
                <label>数据库用户名：</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>数据库密码：</label>
                <input type="password" name="password">
            </div>
            <div class="form-group">
                <label>数据库名：</label>
                <input type="text" name="database" required>
            </div>
            <div class="form-group">
                <label>管理员用户名：</label>
                <input type="text" name="admin_user" required>
            </div>
            <div class="form-group">
                <label>管理员密码：</label>
                <input type="password" name="admin_pass" required>
            </div>
            <div class="button-group">
                <button type="submit" name="prev_step" class="prev-btn">上一步</button>
                <button type="submit" name="install" id="install-btn">开始安装</button>
            </div>
        </form>

        <div class="install-progress" id="install-progress">
            <div class="progress-bar">
                <div class="progress-bar-fill" id="progress-bar-fill"></div>
            </div>
            <div class="progress-text" id="progress-text">正在安装中...</div>
            <div class="install-steps">
                <div class="install-step" data-step="1">
                    <span class="step-icon">○</span>连接数据库
                </div>
                <div class="install-step" data-step="2">
                    <span class="step-icon">○</span>创建数据库
                </div>
                <div class="install-step" data-step="3">
                    <span class="step-icon">○</span>创建数据表
                </div>
                <div class="install-step" data-step="4">
                    <span class="step-icon">○</span>创建管理员账号
                </div>
                <div class="install-step" data-step="5">
                    <span class="step-icon">○</span>生成配置文件
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer class="footer-copyright">
        <div class="container">
            &copy; <?php echo date('Y'); ?> 小小怪卡密系统 - All Rights Reserved
        </div>
    </footer>

    <script>
        // 滚动检测代码
        const welcomeContent = document.getElementById('welcome-content');
        const nextStepBtn = document.getElementById('next-step');
        
        if(welcomeContent && nextStepBtn) {
            welcomeContent.addEventListener('scroll', function() {
                if(welcomeContent.scrollHeight - welcomeContent.scrollTop <= welcomeContent.clientHeight + 1) {
                    nextStepBtn.removeAttribute('disabled');
                    nextStepBtn.style.display = 'block';
                }
            });
        }

        // 修改安装进度显示代码
        const installForm = document.getElementById('install-form');
        const installProgress = document.getElementById('install-progress');
        const progressBarFill = document.getElementById('progress-bar-fill');
        const progressText = document.getElementById('progress-text');
        const installSteps = document.querySelectorAll('.install-step');

        if(installForm) {
            installForm.addEventListener('submit', async function(e) {
                // 如果点击的是返回按钮，直接返回
                if(e.submitter && e.submitter.name === 'prev_step') {
                    return true;
                }
                
                // 如果点击的是安装按钮
                if(e.submitter && e.submitter.name === 'install') {
                    e.preventDefault();
                    
                    // 显示进度条
                    installProgress.style.display = 'block';
                    document.getElementById('install-btn').disabled = true;
                    document.querySelector('.prev-btn').style.display = 'none';
                    
                    try {
                        const formData = new FormData(installForm);
                        formData.append('install', 'true');
                        
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        // 更新进度显示
                        progressText.textContent = result.step || '正在处理...';
                        
                        if(result.status === 'success') {
                            progressText.innerHTML = '<div class="success-message">安装成功！正在跳转...</div>';
                            setTimeout(() => {
                                window.location.href = '../admin.php';
                            }, 1500);
                        } else {
                            throw new Error(result.message);
                        }
                    } catch(error) {
                        progressText.innerHTML = `<div class="error-message">
                            <strong>安装失败：</strong><br>
                            ${error.message}
                        </div>`;
                        document.getElementById('install-btn').disabled = false;
                        document.querySelector('.prev-btn').style.display = 'block';
                    }
                    
                    return false;
                }
            });
        }
    </script>

    <style>
        .success-message {
            color: #28a745;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            margin-top: 10px;
            border-radius: 3px;
        }

        .error-message {
            color: #dc3545;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-top: 10px;
            border-radius: 3px;
        }
    </style>
</body>
</html> 