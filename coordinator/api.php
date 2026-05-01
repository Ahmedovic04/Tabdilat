<?php
require_once '../config.php';
startSecureSession();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['rased_user_id']) || $_SESSION['rased_role'] !== 'coordinator') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$db = getDB();
$coord_id = $_SESSION['rased_user_id'];
$action = $_GET['action'] ?? '';

if ($action === 'update_status') {
    $data = json_decode(file_get_contents('php://input'), true);
    $req_id = (int)($data['request_id'] ?? 0);
    $status = $data['status'] ?? '';
    
    if (!$req_id || !in_array($status, ['approved', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
        exit;
    }
    
    // We need to figure out if this coordinator is the requester's coordinator or substitute's coordinator
    $stmt = $db->prepare("
        SELECT r.id, u1.subject_id as req_sub_id, u2.subject_id as sub_sub_id
        FROM rased_requests r
        JOIN rased_users u1 ON r.requester_id = u1.id
        JOIN rased_users u2 ON r.substitute_id = u2.id
        WHERE r.id = ?
    ");
    $stmt->execute([$req_id]);
    $req_info = $stmt->fetch();
    
    if (!$req_info) {
        echo json_encode(['success' => false, 'message' => 'الطلب غير موجود']);
        exit;
    }
    
    // Get coordinator IDs for these subjects
    $stmt2 = $db->prepare("SELECT id, coordinator_id FROM rased_subjects WHERE id IN (?, ?)");
    $stmt2->execute([$req_info['req_sub_id'], $req_info['sub_sub_id']]);
    $subjects = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $req_coord = $subjects[$req_info['req_sub_id']] ?? null;
    $sub_coord = $subjects[$req_info['sub_sub_id']] ?? null;
    
    $db->beginTransaction();
    try {
        if ($req_coord == $coord_id) {
            $db->prepare("UPDATE rased_requests SET req_coordinator_status = ? WHERE id = ?")->execute([$status, $req_id]);
        }
        if ($sub_coord == $coord_id) {
            $db->prepare("UPDATE rased_requests SET sub_coordinator_status = ? WHERE id = ?")->execute([$status, $req_id]);
        }
        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء التحديث']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
