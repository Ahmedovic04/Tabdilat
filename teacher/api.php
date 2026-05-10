<?php
require_once '../config.php';
require_once '../mail_helper.php';

startSecureSession();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['rased_user_id']) || !in_array($_SESSION['rased_role'], ['teacher', 'coordinator', 'deputy'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$db = getDB();
$teacher_id = $_SESSION['rased_user_id'];
$action = $_GET['action'] ?? '';

if ($action === 'get_classes') {
    $date = $_GET['date'] ?? '';
    if (!$date) { echo json_encode(['success' => false]); exit; }
    
    $timestamp = strtotime($date);
    $day_of_week = date('w', $timestamp);
    
    if ($day_of_week > 4) {
        echo json_encode(['success' => true, 'classes' => []]);
        exit;
    }
    
    $stmt = $db->prepare("
        SELECT tc.period_number, tc.class_id, c.name as class_name 
        FROM rased_teacher_classes tc 
        JOIN rased_classes c ON tc.class_id = c.id 
        WHERE tc.teacher_id = ? AND tc.day_of_week = ?
        ORDER BY tc.period_number
    ");
    $stmt->execute([$teacher_id, $day_of_week]);
    $classes = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'classes' => $classes, 'day' => $day_of_week]);
    exit;
}

if ($action === 'get_substitutes') {
    $class_id = (int)($_GET['class_id'] ?? 0);
    $day_of_week = (int)($_GET['day'] ?? -1);
    $period = (int)($_GET['period'] ?? 0);
    
    if (!$class_id || $day_of_week < 0 || !$period) {
        echo json_encode(['success' => false, 'message' => 'بيانات ناقصة']);
        exit;
    }
    
    $stmt = $db->prepare("
        SELECT u.id, u.name, 
               (SELECT COUNT(*) FROM rased_teacher_classes WHERE teacher_id = u.id AND day_of_week = ?) as daily_classes_count
        FROM rased_users u
        JOIN rased_teacher_classes tc ON tc.teacher_id = u.id
        WHERE tc.class_id = ? AND u.id != ?
        AND u.id NOT IN (
            SELECT teacher_id FROM rased_teacher_classes WHERE day_of_week = ? AND period_number = ?
        )
        GROUP BY u.id, u.name
    ");
    $stmt->execute([$day_of_week, $class_id, $teacher_id, $day_of_week, $period]);
    $substitutes = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'substitutes' => $substitutes]);
    exit;
}

if ($action === 'get_repayment_suggestions') {
    $sub_id = (int)($_GET['sub_id'] ?? 0);
    $absence_date = $_GET['date'] ?? '';
    $class_id = (int)($_GET['class_id'] ?? 0);
    
    if (!$sub_id || !$absence_date || !$class_id) {
        echo json_encode(['success' => false]);
        exit;
    }
    
    $stmt = $db->prepare("SELECT class_id, day_of_week, period_number FROM rased_teacher_classes WHERE teacher_id = ? AND class_id = ?");
    $stmt->execute([$sub_id, $class_id]);
    $sub_schedule = $stmt->fetchAll();
    
    $stmt2 = $db->prepare("SELECT day_of_week, period_number FROM rased_teacher_classes WHERE teacher_id = ?");
    $stmt2->execute([$teacher_id]);
    $req_schedule_raw = $stmt2->fetchAll();
    $req_busy = [];
    foreach ($req_schedule_raw as $rs) {
        $req_busy[$rs['day_of_week'] . '-' . $rs['period_number']] = true;
    }
    
    // ── CHECK ALREADY RESERVED COMPENSATION SESSIONS ──
    // Get all compensation sessions already reserved by other approved requests
    $reserved_stmt = $db->prepare("
        SELECT repayment_date, repayment_period 
        FROM rased_requests 
        WHERE repayment_date IS NOT NULL 
        AND repayment_period IS NOT NULL 
        AND sub_coordinator_status = 'approved'
    ");
    $reserved_stmt->execute();
    $reserved_sessions = [];
    while ($row = $reserved_stmt->fetch()) {
        $key = $row['repayment_date'] . '_' . $row['repayment_period'];
        $reserved_sessions[$key] = true;
    }
    // --------------------------------------------------
    
    $suggestions = [];
    $current_date = strtotime($absence_date . ' + 1 day');
    $days_checked = 0;
    
    while ($days_checked < 14 && count($suggestions) < 10) {
        $dow = date('w', $current_date);
        if ($dow <= 4) {
            foreach ($sub_schedule as $ss) {
                if ($ss['day_of_week'] == $dow) {
                    if (!isset($req_busy[$dow . '-' . $ss['period_number']])) {
                        $comp_date = date('Y-m-d', $current_date);
                        $comp_period = $ss['period_number'];
                        $session_key = $comp_date . '_' . $comp_period;
                        
                        // Skip if this session is already reserved
                        if (isset($reserved_sessions[$session_key])) {
                            continue;
                        }
                        
                        $c_stmt = $db->prepare("SELECT name FROM rased_classes WHERE id = ?");
                        $c_stmt->execute([$ss['class_id']]);
                        $c_name = $c_stmt->fetchColumn();
                        
                        $suggestions[] = [
                            'date' => $comp_date,
                            'formatted_date' => date('d/m/Y', $current_date),
                            'period' => $comp_period,
                            'class_name' => $c_name
                        ];
                    }
                }
            }
        }
        $current_date = strtotime('+1 day', $current_date);
        $days_checked++;
    }
    
    echo json_encode(['success' => true, 'suggestions' => $suggestions]);
    exit;
}

if ($action === 'submit_request') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['requests'])) {
        echo json_encode(['success' => false, 'message' => 'لا توجد بيانات']);
        exit;
    }
    
    $date = $data['date'];
    
    $db->beginTransaction();
    try {
        // ── CHECK FOR DUPLICATES BEFORE INSERTING ──
        $dupCheck = $db->prepare("
            SELECT COUNT(*) FROM rased_requests
            WHERE requester_id = ?
              AND request_date = ?
              AND period_number = ?
              AND deputy_status != 'rejected'
        ");

        $stmt = $db->prepare("
            INSERT INTO rased_requests 
            (requester_id, substitute_id, class_id, request_date, period_number, repayment_date, repayment_period, req_coordinator_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $duplicates = [];

        foreach ($data['requests'] as $req) {
            // Check duplicate: same requester + same date + same period
            $dupCheck->execute([$teacher_id, $date, $req['period_number']]);
            $count = $dupCheck->fetchColumn();

            if ($count > 0) {
                $duplicates[] = $req['period_number'];
                continue; // skip this one
            }

            $rep_date = null;
            $rep_period = null;
            if (!empty($req['repayment_val']) && $req['repayment_val'] !== 'manual') {
                $parts = explode('_', $req['repayment_val']);
                if (count($parts) == 2) {
                    $rep_date = $parts[0];
                    $rep_period = (int)$parts[1];
                }
            }
            
            // ── CHECK IF COMPENSATION SESSION IS ALREADY RESERVED ──
            if ($rep_date && $rep_period) {
                $checkReserved = $db->prepare("
                    SELECT COUNT(*) FROM rased_requests 
                    WHERE repayment_date = ? 
                    AND repayment_period = ? 
                    AND sub_coordinator_status = 'approved'
                ");
                $checkReserved->execute([$rep_date, $rep_period]);
                if ($checkReserved->fetchColumn() > 0) {
                    $db->rollBack();
                    echo json_encode([
                        'success' => false, 
                        'message' => "⚠️ عذراً، الحصة المختارة للتعويض (التاريخ: {$rep_date}، الحصة: {$rep_period}) قد تم حجزها بالفعل من قبل معلم آخر. يرجى اختيار حصة تعويض أخرى."
                    ]);
                    exit;
                }
            }
            // ------------------------------------------------------
            
            $role = $_SESSION['rased_role'];
                    $initial_status = 'pending';
        $stmt = $db->prepare(
            "INSERT INTO rased_requests 
            (requester_id, substitute_id, class_id, request_date, period_number, repayment_date, repayment_period, sub_coordinator_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
            
                        $stmt->execute([
                $teacher_id, 
                $req['substitute_id'], 
                $req['class_id'], 
                $date, 
                $req['period_number'],
                $rep_date,
                $rep_period,
                $initial_status
            ]);

            // --- SEND NOTIFICATION TO SUBSTITUTE ---
            try {
                $stmtSubInfo = $db->prepare("SELECT name, email FROM rased_users WHERE id = ?");
                $stmtSubInfo->execute([$req['substitute_id']]);
                $subInfo = $stmtSubInfo->fetch();

                if ($subInfo && !empty($subInfo['email'])) {
                    $req_name = $_SESSION['rased_full_name'] ?? 'زميلك';
                    $subject = "قام المعلم ($req_name) بطلب تبديل حصة معك";
                    $body = "تحية طيبة،\n\nقام المعلم ($req_name) بتقديم طلب لتبديل حصة معك (الحصة {$req['period_number']} بتاريخ $date).\n\n" .
                            "يرجى الدخول إلى حسابك في نظام راصد (قسم متابعة طلباتي) لإبداء رأيك بالقبول أو الرفض.\n\n" .
                            "رابط النظام: " . SITE_URL;
                    
                    sendRasedEmail($subInfo['email'], $subject, $body);
                }
            } catch (Exception $e) {
                // Log error but don't stop the process
                error_log("Failed to send request email: " . $e->getMessage());
            }
            // ----------------------------------------
        }
        $db->commit();

        if (!empty($duplicates)) {
            $periods = implode('، ', $duplicates);
            echo json_encode([
                'success' => false,
                'message' => "⚠️ لا يمكن إرسال الطلب: يوجد طلب تبديل مسبق للحصة رقم ({$periods}) في نفس اليوم. لا يمكن تكرار الطلب."
            ]);
        } else {
            // Get the first substitute name for the success message
            $first_sub_name = '';
            if (!empty($data['requests'][0]['substitute_id'])) {
                $stmtSubName = $db->prepare("SELECT name FROM rased_users WHERE id = ?");
                $stmtSubName->execute([$data['requests'][0]['substitute_id']]);
                $first_sub_name = $stmtSubName->fetchColumn();
            }
            echo json_encode(['success' => true, 'updated_sub' => $first_sub_name]);
        }

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_profile') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = trim($data['email'] ?? '');
    $new_password = $data['new_password'] ?? '';
    $confirm_password = $data['confirm_password'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'يرجى إدخال بريد إلكتروني صحيح']);
        exit;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE rased_users SET email = ? WHERE id = ?");
        $stmt->execute([$email, $teacher_id]);

        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
            }
            if ($new_password !== $confirm_password) {
                throw new Exception('كلمتا المرور غير متطابقتين');
            }
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE rased_users SET password = ?, is_new = 0 WHERE id = ?");
            $stmt->execute([$hashed, $teacher_id]);
        }

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'sub_approve') {
    $data = json_decode(file_get_contents('php://input'), true);
    $request_id = (int)($data['request_id'] ?? 0);
    $status = $data['status'] ?? ''; // 'approved' or 'rejected'

    if (!$request_id || !in_array($status, ['approved', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
        exit;
    }

    // Verify current user is the substitute for this request
    $stmt = $db->prepare("
        SELECT r.*, u2.name as substitute_name, u2.email as substitute_email, 
               u1.name as requester_name, u1.email as requester_email
        FROM rased_requests r
        JOIN rased_users u1 ON r.requester_id = u1.id
        JOIN rased_users u2 ON r.substitute_id = u2.id
        WHERE r.id = ? AND r.substitute_id = ?
    ");
    $stmt->execute([$request_id, $teacher_id]);
    $request = $stmt->fetch();

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'ليس لك صلاحية الموافقة على هذا الطلب']);
        exit;
    }

    // Update substitute status
    // Automatically approve the request fully when substitute approves
    // Deputy approval becomes optional (already approved)
    if ($status === 'approved') {
        $upd = $db->prepare("UPDATE rased_requests SET sub_coordinator_status = 'approved', req_coordinator_status = 'approved', deputy_status = 'approved' WHERE id = ?");
        $upd->execute([$request_id]);
    } else {
        // If rejected, only update substitute status
        $upd = $db->prepare("UPDATE rased_requests SET sub_coordinator_status = ?, req_coordinator_status = 'approved' WHERE id = ?");
        $upd->execute([$status, $request_id]);
    }

    if ($status === 'approved') {
        $sub_name = $request['substitute_name'];
        $req_name = $request['requester_name'];
        
        // Fetch original class name
        $stmtCls = $db->prepare("SELECT name FROM rased_classes WHERE id = ?");
        $stmtCls->execute([$request['class_id']]);
        $orig_class_name = $stmtCls->fetchColumn();

        // Fetch repayment class name (if applicable)
        $rep_class_name = '-';
        if ($request['repayment_date'] && $request['repayment_period']) {
            $dow = date('w', strtotime($request['repayment_date'])) - 1; // Adjusting to match our 0=Sun logic if needed, but let's check current date logic
            // Our day_of_week logic in rased_setup.php/teacher_classes is 0=Sun, 1=Mon...
            $dow = date('w', strtotime($request['repayment_date'])); 
            
            $stmtRepCls = $db->prepare("
                SELECT c.name 
                FROM rased_teacher_classes tc
                JOIN rased_classes c ON tc.class_id = c.id
                WHERE tc.teacher_id = ? AND tc.day_of_week = ? AND tc.period_number = ?
            ");
            $stmtRepCls->execute([$request['substitute_id'], $dow, $request['repayment_period']]);
            $rep_class_name = $stmtRepCls->fetchColumn() ?: 'حصة إضافية';
        }

        $subject = "قام المعلم ($sub_name) بقبول طلب تبديل حصة";
        $message = "تحية طيبة،\n\nقام المعلم ($sub_name) بقبول طلب تبديل الحصة ({$request['period_number']}) بتاريخ ({$request['request_date']}) في صف ($orig_class_name)،\n" .
                   "على أن يكون التعويض يوم (" . ($request['repayment_date'] ?: 'لاحقاً') . ") في حصة (" . ($request['repayment_period'] ?: '-') . ") صف ($rep_class_name).\n\n" .
                   "يرجى العلم بأن هذا الإشعار يحل محل الاعتماد الورقي، وجاري متابعة الطلب في النظام.";
        
        // Send to requester
        if (!empty($request['requester_email'])) {
            sendRasedEmail($request['requester_email'], $subject, $message);
        }
        // Send to substitute
        if (!empty($request['substitute_email'])) {
            sendRasedEmail($request['substitute_email'], $subject, $message);
        }
    } elseif ($status === 'rejected') {
        $sub_name = $request['substitute_name'];
        $req_name = $request['requester_name'];
        $subject = "نعتذر، تم رفض طلب تبديل الحصة";
        $message = "تحية طيبة،\n\nنود إبلاغكم بأن المعلم ($sub_name) قد اعتذر عن قبول طلب التبديل المقدم من الزميل ($req_name) للحصة ({$request['period_number']}) بتاريخ ({$request['request_date']}).\n\n" .
                   "يمكنكم محاولة التنسيق مع زميل آخر وإرسال طلب جديد.\n\n" .
                   "نظام راصد تبديلاتي";

        // Send to requester
        if (!empty($request['requester_email'])) {
            sendRasedEmail($request['requester_email'], $subject, $message);
        }
        // Send to substitute
        if (!empty($request['substitute_email'])) {
            sendRasedEmail($request['substitute_email'], $subject, $message);
        }
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'update_request') {
    // SECURITY: Only Deputy can update/edit requests now
    if ($_SESSION['rased_role'] !== 'deputy') {
        echo json_encode(['success' => false, 'message' => 'عذراً، التعديل متاح فقط للنائب الأكاديمي.']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $request_id = (int)($_GET['request_id'] ?? 0);
    $requests = $data['requests'] ?? [];

    if (!$request_id || empty($requests)) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير مكتملة']);
        exit;
    }

    $req = $requests[0]; // We only edit one request at a time
    $sub_id = $req['substitute_id'];
    $repayment_val = $req['repayment_val'];
    $request_date = $data['date'] ?? null; // Capture the date from frontend
    
    $rep_date = null;
    $rep_period = null;
    if ($repayment_val && $repayment_val !== 'manual') {
        list($rep_date, $rep_period) = explode('_', $repayment_val);
    }

    // Fetch request details BEFORE update to know emails
    $stmtData = $db->prepare("
        SELECT r.*, u1.name as req_name, u1.email as req_email, u2.name as sub_name, u2.email as sub_email
        FROM rased_requests r
        JOIN rased_users u1 ON r.requester_id = u1.id
        JOIN rased_users u2 ON r.substitute_id = u2.id
        WHERE r.id = ?
    ");
    $stmtData->execute([$request_id]);
    $old_req = $stmtData->fetch();

    if (!$old_req) {
        echo json_encode(['success' => false, 'message' => 'الطلب غير موجود']);
        exit;
    }

    // Reset statuses to pending since the request changed
    // Using named parameters for better reliability
    $sql = "UPDATE rased_requests 
            SET substitute_id = :sub_id, 
                repayment_date = :rep_date, 
                repayment_period = :rep_period, 
                request_date = :req_date, 
                sub_coordinator_status = 'pending', 
                deputy_status = 'pending' 
            WHERE id = :id";
    
    $params = [
        ':sub_id' => $sub_id,
        ':rep_date' => $rep_date,
        ':rep_period' => $rep_period,
        ':req_date' => $request_date,
        ':id' => $request_id
    ];

    if ($_SESSION['rased_role'] !== 'deputy') {
        $sql .= " AND requester_id = :user_id";
        $params[':user_id'] = $teacher_id;
    }

    $stmt = $db->prepare($sql);
    $success = $stmt->execute($params);
    $rows_affected = $stmt->rowCount();

    // Fetch details after update to confirm and notify
    $stmtNew = $db->prepare("
        SELECT r.*, u1.name as req_name, u1.email as req_email, u2.name as sub_name, u2.email as sub_email
        FROM rased_requests r
        JOIN rased_users u1 ON r.requester_id = u1.id
        JOIN rased_users u2 ON r.substitute_id = u2.id
        WHERE r.id = ?
    ");
    $stmtNew->execute([$request_id]);
    $new_req = $stmtNew->fetch();

    if ($success) {
        if ($rows_affected === 0) {
            // Check if it's because values were identical or because of a real error
            if ($new_req && $new_req['substitute_id'] == $sub_id && $new_req['request_date'] == $request_date) {
                echo json_encode(['success' => true, 'updated_sub' => $new_req['sub_name'], 'message' => 'لم يتم تغيير أي بيانات (البيانات مطابقة للحالية)']);
            } else {
                echo json_encode(['success' => false, 'message' => 'عذراً، لم نتمكن من تحديث الطلب. يرجى التأكد من أنك تملك صلاحية التعديل.']);
            }
            exit;
        }

        require_once '../mail_helper.php';
        
        $subject = "تحديث هام: تم تعديل طلب التبديل #" . $request_id;
        $body = "تحية طيبة،\n\nنود إبلاغكم بأنه تم تعديل تفاصيل طلب التبديل رقم #{$request_id}.\n\n" .
                "التفاصيل المحدثة والنهائية:\n" .
                "  - المعلم الغائب: {$new_req['req_name']}\n" .
                "  - المعلم البديل: {$new_req['sub_name']}\n" .
                "  - تاريخ التبديل: {$new_req['request_date']}\n" .
                "  - موعد التعويض: " . ($new_req['repayment_date'] ?: 'غير محدد') . " (الحصة " . ($new_req['repayment_period'] ?: '-') . ")\n\n" .
                "يرجى من المعلم البديل ({$new_req['sub_name']}) الدخول للنظام للمراجعة والموافقة.\n\n" .
                "نظام راصد تبديلاتي";

        // Notify All Parties
        if (!empty($new_req['req_email'])) sendRasedEmail($new_req['req_email'], $subject, $body);
        if (!empty($new_req['sub_email'])) sendRasedEmail($new_req['sub_email'], $subject, $body);

        // If substitute changed, notify the old one
        if ($old_req['substitute_id'] != $new_req['substitute_id'] && !empty($old_req['sub_email'])) {
            $old_sub_subject = "إخطار: إلغاء تكليف بتبديل حصة #{$request_id}";
            $old_sub_body = "تحية طيبة،\n\nنود إبلاغكم بأنه تم تعديل طلب التبديل رقم #{$request_id} وتم اختيار معلم بديل آخر.\n\n" .
                            "لم تعد مكلفاً بتغطية هذه الحصة. نشكركم على تعاونكم.\n\n" .
                            "نظام راصد تبديلاتي";
            sendRasedEmail($old_req['sub_email'], $old_sub_subject, $old_sub_body);
        }

        echo json_encode(['success' => true, 'updated_sub' => $new_req['sub_name']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل تنفيذ أمر التحديث في القاعدة']);
    }
    exit;
}


