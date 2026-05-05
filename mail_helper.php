<?php
/**
 * PHPMailer-based Mail Helper for Rased System
 * Using Port 587 (TLS) for maximum compatibility with restricted servers.
 */

function _rased_smtp_comm($socket, $cmd = "") {
    if ($cmd !== "") fwrite($socket, $cmd . "\r\n");
    $res = "";
    while ($str = fgets($socket, 515)) {
        $res .= $str;
        if (substr($str, 3, 1) == " ") break;
    }
    return $res;
}

function sendRasedEmail($to, $subject, $message) {
    // --- IMPORTANT: Gmail SMTP Configuration ---
    $smtp_user = 'abo.hyzar41@gmail.com';
    $smtp_pass = 'ruowqwdaikcsprhf'; // 16-char App Password
    // -------------------------------------------

    $host = "smtp.gmail.com";
    $port = 587;
    $timeout = 15;

    $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        error_log("Socket Connection Failed: $errno - $errstr");
        return false;
    }

    try {
        _rased_smtp_comm($socket); // Greeting
        _rased_smtp_comm($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        
        // STARTTLS for Port 587
        _rased_smtp_comm($socket, "STARTTLS");
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log("Failed to enable crypto/TLS");
            fclose($socket);
            return false;
        }
        
        _rased_smtp_comm($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        _rased_smtp_comm($socket, "AUTH LOGIN");
        _rased_smtp_comm($socket, base64_encode($smtp_user));
        _rased_smtp_comm($socket, base64_encode($smtp_pass));
        
        _rased_smtp_comm($socket, "MAIL FROM: <$smtp_user>");
        _rased_smtp_comm($socket, "RCPT TO: <$to>");
        _rased_smtp_comm($socket, "DATA");
        
        $header = "To: <$to>\r\n" .
                  "From: Rased System <$smtp_user>\r\n" .
                  "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n" .
                  "Content-Type: text/plain; charset=utf-8\r\n\r\n";
        
        _rased_smtp_comm($socket, $header . $message . "\r\n.");
        _rased_smtp_comm($socket, "QUIT");
        fclose($socket);
        return true;
    } catch (Exception $e) {
        if ($socket) fclose($socket);
        return false;
    }
}

function sendSubstitutionEmails($db, $request_id) {
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
    
    if (!$req) return false;

    $stmtCoord = $db->prepare("SELECT email FROM rased_users WHERE subject_id = ? AND role = 'coordinator' AND email IS NOT NULL");
    
    $stmtCoord->execute([$req['req_sub_id']]);
    $req_coord_email = $stmtCoord->fetchColumn();
    
    $stmtCoord->execute([$req['sub_sub_id']]);
    $sub_coord_email = $stmtCoord->fetchColumn();

    $to_emails = array_unique(array_filter([$req['req_email'], $req['sub_email'], $req_coord_email, $sub_coord_email]));
    
    if (empty($to_emails)) {
        throw new Exception("الموظفون المعنيون بالطلب لم يسجلوا إيميلاتهم بعد.");
    }

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

    $all_sent = true;
    foreach ($to_emails as $email) {
        if (!sendRasedEmail($email, $subject, $body)) {
            $all_sent = false;
        }
    }
    
    return $all_sent;
}

function sendRejectionEmails($db, $request_id) {
    $stmt = $db->prepare("
        SELECT r.*, 
               u1.name as req_name, u1.email as req_email,
               u2.name as sub_name, u2.email as sub_email
        FROM rased_requests r
        JOIN rased_users u1 ON r.requester_id = u1.id
        JOIN rased_users u2 ON r.substitute_id = u2.id
        WHERE r.id = ?
    ");
    $stmt->execute([$request_id]);
    $req = $stmt->fetch();
    
    if (!$req) return false;

    $subject = "إشعار رفض طلب تبديل - نظام راصد";
    $body = "تحية طيبة،\n\nنود إبلاغكم بأن طلب التبديل رقم #{$request_id} قد تم رفضه من قبل النائب الأكاديمي.\n\n" .
            "التفاصيل:\n" .
            "--------------------------\n" .
            "- المعلم الغائب: {$req['req_name']}\n" .
            "- المعلم البديل: {$req['sub_name']}\n" .
            "- التاريخ: {$req['request_date']}\n" .
            "- الحصة: {$req['period_number']}\n" .
            "--------------------------\n\n" .
            "نظام راصد تبديلاتي - مدرسة معيذر الابتدائية";

    $to_emails = array_unique(array_filter([$req['req_email'], $req['sub_email']]));
    foreach ($to_emails as $email) {
        sendRasedEmail($email, $subject, $body);
    }
    return true;
}

