<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
header('X-Powered-By: 小小怪卡密系统');

require_once '../config.php';

// 检查API是否启用
try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("SELECT value FROM settings WHERE name = 'api_enabled'");
    $stmt->execute();
    $api_enabled = $stmt->fetchColumn();
    
    error_log("API Status: " . $api_enabled);
    
    if($api_enabled !== '1' && $api_enabled !== 1) {
        http_response_code(403);
        die(json_encode([
            'code' => 2,
            'message' => 'API接口未启用',
            'data' => null
        ], JSON_UNESCAPED_UNICODE));
    }
} catch(PDOException $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'code' => 3,
        'message' => '系统错误',
        'data' => null
    ], JSON_UNESCAPED_UNICODE));
}

// 验证API密钥（支持Header和GET参数）
$headers = getallheaders();
$api_key = '';

// 优先从Header获取API密钥
if(isset($headers['X-API-KEY'])) {
    $api_key = $headers['X-API-KEY'];
} 
// 如果是GET请求且Header中没有API密钥，则从URL参数获取
else if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['api_key'])) {
    $api_key = $_GET['api_key'];
}

try {
    $stmt = $conn->prepare("SELECT value FROM settings WHERE name = 'api_key'");
    $stmt->execute();
    $stored_key = $stmt->fetchColumn();
    
    if($api_key !== $stored_key) {
        http_response_code(401);
        die(json_encode([
            'code' => 4,
            'message' => 'API密钥无效',
            'data' => null
        ], JSON_UNESCAPED_UNICODE));
    }
} catch(PDOException $e) {
    http_response_code(500);
    die(json_encode([
        'code' => 3,
        'message' => '系统错误',
        'data' => null
    ], JSON_UNESCAPED_UNICODE));
}

// 获取请求数据（支持GET和POST）
$card_key = '';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 处理POST请求
    $input = json_decode(file_get_contents('php://input'), true);
    if($input && isset($input['card_key'])) {
        $card_key = trim($input['card_key']);
    } else {
        // 处理普通POST表单
        $card_key = isset($_POST['card_key']) ? trim($_POST['card_key']) : '';
    }
} else if($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 处理GET请求
    $card_key = isset($_GET['card_key']) ? trim($_GET['card_key']) : '';
}

if(empty($card_key)) {
    http_response_code(400);
    die(json_encode([
        'code' => 1,
        'message' => '请提供卡密',
        'data' => null
    ], JSON_UNESCAPED_UNICODE));
}

// 验证卡密
try {
    $stmt = $conn->prepare("SELECT * FROM cards WHERE card_key = ? AND status = 0");
    $stmt->execute([$card_key]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($card) {
        // 计算到期时间（如果有设置duration）
        $expire_time = null;
        if($card['duration'] > 0) {
            $expire_time = date('Y-m-d H:i:s', strtotime("+{$card['duration']} days"));
        }
        
        // 在更新卡密状态时添加验证方式
        $verify_method = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'post' : 'get';
        $stmt = $conn->prepare("UPDATE cards SET status = 1, use_time = NOW(), expire_time = ?, verify_method = ? WHERE id = ?");
        $stmt->execute([$expire_time, $verify_method, $card['id']]);
        
        // 获取更新后的卡密信息
        $stmt = $conn->prepare("SELECT card_key, status, use_time, expire_time, duration FROM cards WHERE id = ?");
        $stmt->execute([$card['id']]);
        $updated_card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'code' => 0,
            'message' => '验证成功',
            'data' => $updated_card
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // 检查是否是已使用的卡密
        $stmt = $conn->prepare("SELECT card_key, status, use_time, expire_time FROM cards WHERE card_key = ?");
        $stmt->execute([$card_key]);
        $used_card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($used_card) {
            http_response_code(400);
            echo json_encode([
                'code' => 1,
                'message' => '此卡密已被使用',
                'data' => [
                    'use_time' => $used_card['use_time'],
                    'expire_time' => $used_card['expire_time']
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(400);
            echo json_encode([
                'code' => 1,
                'message' => '无效的卡密',
                'data' => null
            ], JSON_UNESCAPED_UNICODE);
        }
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'code' => 3,
        'message' => '系统错误',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
} 