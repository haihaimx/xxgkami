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
    $stmt = $conn->prepare("SELECT id FROM api_keys WHERE api_key = ? AND status = 1");
    $stmt->execute([$api_key]);
    if(!$stmt->fetch()) {
        http_response_code(401);
        die(json_encode([
            'code' => 4,
            'message' => 'API密钥无效或已禁用',
            'data' => null
        ], JSON_UNESCAPED_UNICODE));
    }
    
    // 更新使用次数和最后使用时间
    $stmt = $conn->prepare("UPDATE api_keys SET use_count = use_count + 1, last_use_time = NOW() WHERE api_key = ?");
    $stmt->execute([$api_key]);
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
$device_id = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 处理POST请求
    $input = json_decode(file_get_contents('php://input'), true);
    if($input) {
        $card_key = isset($input['card_key']) ? trim($input['card_key']) : '';
        $device_id = isset($input['device_id']) ? trim($input['device_id']) : '';
    } else {
        $card_key = isset($_POST['card_key']) ? trim($_POST['card_key']) : '';
        $device_id = isset($_POST['device_id']) ? trim($_POST['device_id']) : '';
    }
} else if($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 处理GET请求
    $card_key = isset($_GET['card_key']) ? trim($_GET['card_key']) : '';
    $device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : '';
}

if(empty($card_key)) {
    http_response_code(400);
    die(json_encode([
        'code' => 1,
        'message' => '请提供卡密',
        'data' => new stdClass()
    ], JSON_UNESCAPED_UNICODE));
}

if(empty($device_id)) {
    http_response_code(400);
    die(json_encode([
        'code' => 1,
        'message' => '请提供设备ID',
        'data' => new stdClass()
    ], JSON_UNESCAPED_UNICODE));
}

// 添加卡密加密函数
function encryptCardKey($key) {
    $salt = 'xiaoxiaoguai_card_system_2024';
    return sha1($key . $salt);
}

// 修改验证卡密部分
try {
    // 对输入的卡密进行加密
    $encrypted_key = encryptCardKey($card_key);
    
    // 首先检查卡密是否存在
    $stmt = $conn->prepare("SELECT * FROM cards WHERE encrypted_key = ?");
    $stmt->execute([$encrypted_key]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$card) {
        http_response_code(400);
        die(json_encode([
            'code' => 1,
            'message' => '无效的卡密',
            'data' => new stdClass()
        ], JSON_UNESCAPED_UNICODE));
    }
    
    // 检查卡密是否被禁用
    if($card['status'] == 2) {
        http_response_code(403);
        die(json_encode([
            'code' => 5,
            'message' => '此卡密已被管理员禁用',
            'data' => new stdClass()
        ], JSON_UNESCAPED_UNICODE));
    }
    
    // 检查卡密状态和设备绑定
    if($card['status'] == 1) {
        // 已使用的卡密
        if($card['device_id'] === $device_id) {
            // 同一设备重复验证
            // 检查是否允许重复验证
            if ($card['allow_reverify']) {
                echo json_encode([
                    'code' => 0,
                    'message' => '验证成功(重复验证)',
                    'data' => [
                        'card_key' => $card['card_key'],
                        'status' => 1,
                        'use_time' => $card['use_time'],
                        'expire_time' => $card['expire_time'],
                        'duration' => $card['duration'],
                        'device_id' => $device_id,
                        'allow_reverify' => $card['allow_reverify']
                    ]
                ], JSON_UNESCAPED_UNICODE);
            } else {
                // 不允许重复验证
                http_response_code(403); // Forbidden
                die(json_encode([
                    'code' => 6, // 新增错误码
                    'message' => '此卡密不允许重复验证',
                    'data' => new stdClass()
                ], JSON_UNESCAPED_UNICODE));
            }
        } else {
            // 其他设备尝试使用 或 卡密已被解绑，允许新设备绑定
            if (empty($card['device_id'])) {
                // 卡密已被解绑，允许当前设备重新绑定
                $expire_time = $card['expire_time']; // 保持原来的到期时间
                // 如果需要，可以更新 use_time 为当前时间，或保持不变
                // $use_time = date('Y-m-d H:i:s');
                $use_time = $card['use_time']; // 保持原来的使用时间
                
                $verify_method = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'post' : 'get';
                $stmt = $conn->prepare("UPDATE cards SET device_id = ?, verify_method = ? WHERE id = ?");
                $stmt->execute([$device_id, $verify_method, $card['id']]);

                echo json_encode([
                    'code' => 0,
                    'message' => '验证成功 (重新绑定设备)',
                    'data' => [
                        'card_key' => $card['card_key'],
                        'status' => 1,
                        'use_time' => $use_time, // 使用原始或当前时间
                        'expire_time' => $expire_time,
                        'duration' => $card['duration'],
                        'device_id' => $device_id, // 新绑定的设备ID
                        'allow_reverify' => $card['allow_reverify']
                    ]
                ], JSON_UNESCAPED_UNICODE);

            } else {
                // 卡密已绑定到其他设备
                http_response_code(400);
                die(json_encode([
                    'code' => 1,
                    'message' => '此卡密已被其他设备使用',
                    'data' => new stdClass()
                ], JSON_UNESCAPED_UNICODE));
            }
        }
    } else if($card['status'] == 0) {
        // 新卡密激活
        $expire_time = null;
        if($card['duration'] > 0) {
            $expire_time = date('Y-m-d H:i:s', strtotime("+{$card['duration']} days"));
        }
        
        $verify_method = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'post' : 'get';
        $stmt = $conn->prepare("UPDATE cards SET status = 1, use_time = NOW(), expire_time = ?, verify_method = ?, device_id = ? WHERE id = ?");
        $stmt->execute([$expire_time, $verify_method, $device_id, $card['id']]);
        
        // 重新获取卡密信息，包含 allow_reverify
        $stmt = $conn->prepare("SELECT allow_reverify FROM cards WHERE id = ?");
        $stmt->execute([$card['id']]);
        $allow_reverify_status = $stmt->fetchColumn();

        echo json_encode([
            'code' => 0,
            'message' => '验证成功',
            'data' => [
                'card_key' => $card['card_key'],
                'status' => 1,
                'use_time' => date('Y-m-d H:i:s'),
                'expire_time' => $expire_time,
                'duration' => $card['duration'],
                'device_id' => $device_id,
                'allow_reverify' => $allow_reverify_status
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'code' => 3,
        'message' => '系统错误',
        'data' => new stdClass()
    ], JSON_UNESCAPED_UNICODE);
} 