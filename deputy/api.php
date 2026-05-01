<?php
require_once '../config.php';
startSecureSession();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'deputy') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$db = getDB();
$action = $_GET['action'] ?? '';

if ($action === 'update_status') {
    $data = json_decode(file_get_contents('php://input'), true);
    $req_id = (int)($data['request_id'] ?? 0);
    $status = $data['status'] ?? '';
    
    if (!$req_id || !in_array($status, ['approved', 'rejected', 'approved_with_mod'])) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
        exit;
    }
    
    $stmt = $db->prepare("UPDATE rased_requests SET deputy_status = ? WHERE id = ?");
    if ($stmt->execute([$status, $req_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء التحديث']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
