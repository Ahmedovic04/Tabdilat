<?php
require_once 'config.php';

echo "Starting Rased Tabdeelaty Setup...\n";

try {
    $db = getDB();

    // 1. Create Tables
    $db->exec("
        CREATE TABLE IF NOT EXISTS rased_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            role ENUM('teacher', 'coordinator', 'deputy') DEFAULT 'teacher',
            subject_id INT NULL,
            is_new BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS rased_subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            coordinator_id INT NULL,
            FOREIGN KEY (coordinator_id) REFERENCES rased_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS rased_classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) UNIQUE NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS rased_teacher_classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            class_id INT NOT NULL,
            day_of_week INT NOT NULL, -- 0=Sunday, 1=Monday...
            period_number INT NOT NULL,
            FOREIGN KEY (teacher_id) REFERENCES rased_users(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES rased_classes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS rased_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            requester_id INT NOT NULL,
            substitute_id INT NOT NULL,
            class_id INT NOT NULL,
            request_date DATE NOT NULL,
            period_number INT NOT NULL,
            repayment_date DATE NULL,
            req_coordinator_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            sub_coordinator_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            deputy_status ENUM('pending', 'approved', 'approved_with_mod', 'rejected') DEFAULT 'pending',
            repayment_status ENUM('pending', 'completed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (requester_id) REFERENCES rased_users(id),
            FOREIGN KEY (substitute_id) REFERENCES rased_users(id),
            FOREIGN KEY (class_id) REFERENCES rased_classes(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Add repayment_date if it doesn't exist (Migration)
    try {
        $db->exec("ALTER TABLE rased_requests ADD COLUMN repayment_date DATE NULL AFTER period_number");
        echo "Added repayment_date column.\n";
    } catch (Exception $e) {
        // Column probably already exists
    }

    echo "Tables created successfully.<br>\n";

    // 2. Parse Excel/HTML File
    $file_path = 'Teachers_Summary (2).xls';
    if (!file_exists($file_path)) {
        die("File not found at: $file_path<br>\n");
    }

    $content = file_get_contents($file_path);
    
    // We use DOMDocument to parse HTML
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content);
    $xpath = new DOMXPath($dom);
    
    $rows = $xpath->query('//table//tr');
    
    $db->exec("TRUNCATE TABLE rased_teacher_classes");
    // Don't truncate users or classes if we want to keep them, but let's just insert ignore.
    
    $default_password = password_hash('123456', PASSWORD_DEFAULT);
    
    $stmtUser = $db->prepare("INSERT IGNORE INTO rased_users (username, password, name, role) VALUES (?, ?, ?, 'teacher')");
    $stmtClass = $db->prepare("INSERT IGNORE INTO rased_classes (name) VALUES (?)");
    
    $teachers_added = 0;
    $classes_added = 0;
    $schedule_added = 0;

    foreach ($rows as $index => $row) {
        if ($index < 2) continue; // Skip header rows
        
        $cells = $xpath->query('td', $row);
        if ($cells->length < 36) continue; // 1 (Teacher) + 5*7 (Periods)
        
        $teacher_name = trim($cells->item(0)->nodeValue);
        if (empty($teacher_name)) continue;
        
        // Ensure teacher username exists (generate one from name or just use name for now)
        $username = 't_' . crc32($teacher_name); // simple unique username
        $stmtUser->execute([$username, $default_password, $teacher_name]);
        
        // Get teacher id
        $t_res = $db->query("SELECT id FROM rased_users WHERE name = " . $db->quote($teacher_name))->fetch();
        if (!$t_res) continue;
        $teacher_id = $t_res['id'];
        $teachers_added++;

        // Process schedule
        for ($i = 1; $i <= 35; $i++) {
            $class_name = trim($cells->item($i)->nodeValue);
            if (empty($class_name)) continue;
            
            $stmtClass->execute([$class_name]);
            
            $c_res = $db->query("SELECT id FROM rased_classes WHERE name = " . $db->quote($class_name))->fetch();
            if (!$c_res) continue;
            $class_id = $c_res['id'];
            
            $day_of_week = floor(($i - 1) / 7);
            $period_number = (($i - 1) % 7) + 1;
            
            $db->prepare("INSERT INTO rased_teacher_classes (teacher_id, class_id, day_of_week, period_number) VALUES (?, ?, ?, ?)")
               ->execute([$teacher_id, $class_id, $day_of_week, $period_number]);
            
            $schedule_added++;
        }
    }
    
    echo "Import completed!<br>\n";
    echo "Teachers processed: $teachers_added<br>\n";
    echo "Schedule entries added: $schedule_added<br>\n";
    
    // Add Academic Deputy user for testing
    $db->prepare("INSERT IGNORE INTO rased_users (username, password, name, role) VALUES ('deputy', ?, 'النائب الأكاديمي', 'deputy')")->execute([$default_password]);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>\n";
}
