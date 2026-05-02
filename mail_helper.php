<?php
/**
 * Simplified Mail Helper for Rased System
 * This version uses the standard mail() function to avoid 500 errors on restricted servers.
 */

function sendRasedEmail($to, $subject, $message) {
    $from_email = 'abo.hyzar41@gmail.com';
    
    $headers = "From: Rased System <{$from_email}>\r\n" .
               "Reply-To: {$from_email}\r\n" .
               "MIME-Version: 1.0\r\n" .
               "Content-Type: text/plain; charset=utf-8\r\n" .
               "Content-Transfer-Encoding: 8bit\r\n" .
               "X-Mailer: PHP/" . phpversion();

    // Use try-catch or silence error with @ to prevent 500 errors if mail() is disabled
    return @mail($to, $subject, $message, $headers);
}

function sendSubstitutionEmails($db, $request_id) {
    try {
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

        $stmtCoord = $db->prepare("SELECT email FROM rased_users WHERE subject_id = ? AND role = 'coordinator' AND email IS NOT NULL");
        
        $stmtCoord->execute([$req['req_sub_id']]);
        $req_coord_email = $stmtCoord->fetchColumn();
        
        $stmtCoord->execute([$req['sub_sub_id']]);
        $sub_coord_email = $stmtCoord->fetchColumn();

        $to_emails = array_unique(array_filter([$req['req_email'], $req['sub_email'], $req_coord_email, $sub_coord_email]));
        
        if (empty($to_emails)) return;

        $subject = "إشعار اعتماد تبديل حصة - نظام راصد";
        $body = "تحية طيبة،\n\nتم اعتماد طلب التبديل رقم #{$request_id} رسمياً من قبل النائب الأكاديمي.\n\n" .
                "التفاصيل:\n" .
                "--------------------------\n" .
                "- المعلم الغائب: {$req['req_name']}\n" .
                "- المعلم البديل: {$req['sub_name']}\n" .
                "- الصف: {$req['class_name']}\n" .
                "- تاريخ التبديل: {$req['request_date']}\n" .
                "- الحصة: {$req['period_number']}\n" .
                "- موعد التعويض: " . ($req['repayment_date'] ?: 'سيتم التحديد لاحقاً') . "\n" .
                "--------------------------\n\n" .
                "نظام راصد تبديلاتي - مدرسة معيذر الابتدائية";

        foreach ($to_emails as $email) {
            sendRasedEmail($email, $subject, $body);
        }
    } catch (Exception $e) {
        // Log error silently so it doesn't break the main application flow
        error_log("Email sending failed: " . $e->getMessage());
    }
}
