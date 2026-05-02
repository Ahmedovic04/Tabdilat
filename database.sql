-- 
-- هيكل قاعدة البيانات لنظام راصد تبديلاتي
-- يمكن تنفيذ هذا الملف لإنشاء الجداول يدوياً، 
-- أو يمكنك ببساطة استخدام ملف rased_setup.php الذي يقوم بإنشائها برمجياً.
--

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

CREATE TABLE IF NOT EXISTS rased_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    coordinator_id INT NULL,
    FOREIGN KEY (coordinator_id) REFERENCES rased_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rased_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rased_teacher_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    day_of_week INT NOT NULL, -- 0=الأحد, 1=الإثنين...
    period_number INT NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES rased_users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES rased_classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- إضافة حساب النائب الأكاديمي الافتراضي
-- كلمة المرور الافتراضية: 123456
INSERT IGNORE INTO rased_users (username, password, name, role, is_new) 
VALUES ('deputy', '$2y$10$wN1R.K4bK.X5P0eUuA6.z.s4X2Z0Gq3d.fSg3D7z0g3d.fSg3D7zO', 'النائب الأكاديمي', 'deputy', FALSE);
