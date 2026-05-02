<?php
require_once '../config.php';
require_once '../mail_helper.php';
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
    $request_id = (int)($data['request_id'] ?? 0);
    $status = $data['status'] ?? '';

    if (!$request_id || !in_array($status, ['approved', 'approved_with_mod', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
        exit;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE rased_requests SET deputy_status = ? WHERE id = ?");
        $stmt->execute([$status, $request_id]);
        
        // If approved, send notification emails using the new helper
        if (in_array($status, ['approved', 'approved_with_mod'])) {
            sendSubstitutionEmails($db, $request_id);
        }
        
        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'خطأ في التحديث: ' . $e->getMessage()]);
    }
    exit;
}
