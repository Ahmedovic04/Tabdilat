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
            name VARCHAR(100) NOT NULL
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
            day_of_week INT NOT NULL,
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
            repayment_period INT NULL,
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

    // Seed Subjects
    $subjects = ['لغة عربية', 'لغة إنجليزية', 'رياضيات', 'اجتماعيات', 'تربية رياضية', 'فنون', 'حوسبة'];
    $stmtSub = $db->prepare("INSERT IGNORE INTO rased_subjects (name) VALUES (?)");
    foreach ($subjects as $sub) {
        $stmtSub->execute([$sub]);
    }

    // Migration for missing columns
    try { $db->exec("ALTER TABLE rased_requests ADD COLUMN repayment_date DATE NULL AFTER period_number"); } catch(Exception $e){}
    try { $db->exec("ALTER TABLE rased_requests ADD COLUMN repayment_period INT NULL AFTER repayment_date"); } catch(Exception $e){}
    try { $db->exec("ALTER TABLE rased_users ADD COLUMN subject_id INT NULL AFTER role"); } catch(Exception $e){}

    echo "Setup and Seeding completed successfully.<br>\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>\n";
}
