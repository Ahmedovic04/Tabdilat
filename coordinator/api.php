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
    $request_id = (int)($data['request_id'] ?? 0);
    $status = $data['status'] ?? ''; // 'approved' or 'rejected'

    if (!$request_id || !in_array($status, ['approved', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
        exit;
    }

    // Get coordinator's subject
    $stmt = $db->prepare("SELECT subject_id FROM rased_users WHERE id = ?");
    $stmt->execute([$coord_id]);
    $coord_subject = $stmt->fetchColumn();

    if (!$coord_subject) {
        echo json_encode(['success' => false, 'message' => 'لم يتم تحديد مادة لك كمنسق']);
        exit;
    }

    // Get request details to see which side this coordinator belongs to
    $stmt = $db->prepare("
        SELECT r.*, u1.subject_id as req_sub, u2.subject_id as sub_sub 
        FROM rased_requests r
        JOIN rased_users u1 ON r.requester_id = u1.id
        JOIN rased_users u2 ON r.substitute_id = u2.id
        WHERE r.id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'الطلب غير موجود']);
        exit;
    }

    $updated = false;
    // Check if coord is for requester
    if ($request['req_sub'] == $coord_subject) {
        $up = $db->prepare("UPDATE rased_requests SET req_coordinator_status = ? WHERE id = ?");
        $up->execute([$status, $request_id]);
        $updated = true;
    }

    // Check if coord is for substitute
    if ($request['sub_sub'] == $coord_subject) {
        $up = $db->prepare("UPDATE rased_requests SET sub_coordinator_status = ? WHERE id = ?");
        $up->execute([$status, $request_id]);
        $updated = true;
    }

    if ($updated) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'لا تملك صلاحية على هذا الطلب']);
    }
    exit;
}
