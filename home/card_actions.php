<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    die(json_encode(['success' => false, 'message' => '未登录']));
}

require_once '../config.php';

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);
if(!isset($input['action'])) {
    die(json_encode(['success' => false, 'message' => '无效的请求']));
}

try {
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch($input['action']) {
        case 'disable':
            // 停用卡密
            $stmt = $conn->prepare("UPDATE cards SET status = 2 WHERE id = ? AND status = 1");
            $success = $stmt->execute([$input['card_id']]);
            echo json_encode([
                'success' => $success,
                'message' => $success ? '卡密已停用' : '停用失败'
            ]);
            break;
            
        case 'enable':
            // 启用卡密
            $stmt = $conn->prepare("UPDATE cards SET status = 1 WHERE id = ? AND status = 2");
            $success = $stmt->execute([$input['card_id']]);
            echo json_encode([
                'success' => $success,
                'message' => $success ? '卡密已启用' : '启用失败'
            ]);
            break;
            
        case 'update_expire_time':
            // 修改到期时间
            $stmt = $conn->prepare("UPDATE cards SET expire_time = ? WHERE id = ? AND status IN (1, 2)");
            $success = $stmt->execute([
                date('Y-m-d H:i:s', strtotime($input['expire_time'])),
                $input['card_id']
            ]);
            echo json_encode([
                'success' => $success,
                'message' => $success ? '到期时间已更新' : '更新失败'
            ]);
            break;
            
        case 'delete_used':
            // 删除已使用的卡密
            $stmt = $conn->prepare("DELETE FROM cards WHERE id = ? AND status IN (1, 2)");
            $success = $stmt->execute([$input['card_id']]);
            echo json_encode([
                'success' => $success,
                'message' => $success ? '卡密已删除' : '删除失败'
            ]);
            break;
            
        case 'toggle_reverify':
            // 切换允许重复验证状态
            $card_id = intval($input['card_id'] ?? 0);
            $allow_reverify = intval($input['allow_reverify'] ?? 0);
            // 确保状态值是 0 或 1
            $allow_reverify = ($allow_reverify === 1) ? 1 : 0; 

            if ($card_id > 0) {
                $stmt = $conn->prepare("UPDATE cards SET allow_reverify = ? WHERE id = ?");
                $success = $stmt->execute([$allow_reverify, $card_id]);
                echo json_encode([
                    'success' => $success,
                    'message' => $success ? ('设置成功，现在' . ($allow_reverify ? '允许' : '禁止') . '重复验证') : '设置失败'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => '无效的卡密ID'
                ]);
            }
            break;
            
        case 'unbind_device':
            // 解绑设备
            $card_id = intval($input['card_id'] ?? 0);
            if ($card_id > 0) {
                // 只解绑已使用或已停用的卡密
                $stmt = $conn->prepare("UPDATE cards SET device_id = NULL WHERE id = ? AND status IN (1, 2)");
                $success = $stmt->execute([$card_id]);
                echo json_encode([
                    'success' => $success,
                    'message' => $success ? '设备已成功解绑' : '解绑失败或卡密状态不允许解绑'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => '无效的卡密ID'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => '未知的操作类型'
            ]);
    }
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => '系统错误：' . $e->getMessage()
    ]);
} 