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
        
        // If approved, send notification emails
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

/**
 * Function to send emails to all parties involved in a substitution
 */
function sendSubstitutionEmails($db, $request_id) {
    // Fetch request details with emails and subject info
    $stmt = $db->prepare("
        SELECT r.*, 
               u1.name as req_name, u1.email as req_email, u1.subject_id as req_sub_id,
               u2.name as sub_name, u2.email as sub_email, u2.subject_id as sub_sub_id,
               c.name as class_name
        FROM rased_requests r
        JOIN rased_users u1 ON r.requester_id = u1.id
        JOIN rased_users u2 ON r.substitute_id = u2.id
        JOIN rased_classes c ON r.class_id = c.id
        WHERE r.id = ?
    ");
    $stmt->execute([$request_id]);
    $req = $stmt->fetch();
    
    if (!$req) return;

    // Get coordinators for both subjects
    $stmtCoord = $db->prepare("SELECT email FROM rased_users WHERE subject_id = ? AND role = 'coordinator'");
    
    $stmtCoord->execute([$req['req_sub_id']]);
    $req_coord_email = $stmtCoord->fetchColumn();
    
    $stmtCoord->execute([$req['sub_sub_id']]);
    $sub_coord_email = $stmtCoord->fetchColumn();

    $to_emails = array_filter([$req['req_email'], $req['sub_email'], $req_coord_email, $sub_coord_email]);
    
    if (empty($to_emails)) return;

    $subject = "إشعار اعتماد تبديل حصة - نظام راصد";
    $message = "تم اعتماد طلب التبديل رقم #{$request_id} من قبل النائب الأكاديمي.\n\n" .
               "التفاصيل:\n" .
               "- المعلم الغائب: {$req['req_name']}\n" .
               "- المعلم البديل: {$req['sub_name']}\n" .
               "- الصف: {$req['class_name']}\n" .
               "- التاريخ: {$req['request_date']}\n" .
               "- الحصة: {$req['period_number']}\n" .
               "- موعد التعويض: " . ($req['repayment_date'] ?: 'سيحدد لاحقاً') . "\n\n" .
               "يرجى العلم والالتزام.\nتحياتنا، نظام راصد تبديلاتي.";

    $headers = "From: rased-no-reply@nakama.qa\r\n" .
               "Reply-To: rased-no-reply@nakama.qa\r\n" .
               "Content-Type: text/plain; charset=utf-8\r\n" .
               "X-Mailer: PHP/" . phpversion();

    foreach ($to_emails as $email) {
        @mail($email, $subject, $message, $headers);
    }
}
