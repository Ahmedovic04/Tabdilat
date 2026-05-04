<?php
require_once '../config.php';
startSecureSession();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['rased_user_id']) || !in_array($_SESSION['rased_role'], ['teacher', 'coordinator'])) {
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
    
    $suggestions = [];
    $current_date = strtotime($absence_date . ' + 1 day');
    $days_checked = 0;
    
    while ($days_checked < 14 && count($suggestions) < 10) {
        $dow = date('w', $current_date);
        if ($dow <= 4) {
            foreach ($sub_schedule as $ss) {
                if ($ss['day_of_week'] == $dow) {
                    if (!isset($req_busy[$dow . '-' . $ss['period_number']])) {
                        $c_stmt = $db->prepare("SELECT name FROM rased_classes WHERE id = ?");
                        $c_stmt->execute([$ss['class_id']]);
                        $c_name = $c_stmt->fetchColumn();
                        
                        $suggestions[] = [
                            'date' => date('Y-m-d', $current_date),
                            'formatted_date' => date('d/m/Y', $current_date),
                            'period' => $ss['period_number'],
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
            
            $role = $_SESSION['rased_role'];
            $initial_status = ($role === 'coordinator') ? 'approved' : 'pending';
            
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
        }
        $db->commit();

        if (!empty($duplicates)) {
            $periods = implode('، ', $duplicates);
            echo json_encode([
                'success' => false,
                'message' => "⚠️ لا يمكن إرسال الطلب: يوجد طلب تبديل مسبق للحصة رقم ({$periods}) في نفس اليوم. لا يمكن تكرار الطلب."
            ]);
        } else {
            echo json_encode(['success' => true]);
        }

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
    }
    exit;
}
