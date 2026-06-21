<?php
require_once __DIR__ . '/config.php';

function db_connect() {
    static $pdo;
    if ($pdo) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
            ]
        );

        initialize_database($pdo);
        $pdo->exec('USE `' . DB_NAME . '`');
        return $pdo;
    } catch (PDOException $e) {
        die('Database connection gagal: ' . $e->getMessage());
    }
}

function initialize_database($pdo) {
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS doctors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            specialty VARCHAR(100) NOT NULL,
            phone VARCHAR(25) NOT NULL,
            description TEXT,
            education TEXT,
            symptoms TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doctor_id INT NOT NULL,
            date DATE NOT NULL,
            time TIME NOT NULL,
            slots INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS patients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            phone VARCHAR(25) NOT NULL,
            doctor_id INT NOT NULL,
            schedule_id INT NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT "Reservasi",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
            FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS site_content (
            content_key VARCHAR(100) PRIMARY KEY,
            content_value TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admin_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL,
            doctor_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $defaultDoctors = $pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn();
    if ($defaultDoctors == 0) {
        $insertDoctor = $pdo->prepare('INSERT INTO doctors (name, specialty, phone) VALUES (?, ?, ?)');
        $insertDoctor->execute(['Dr. Siti Aminah', 'Umum', '081234567890']);
        $insertDoctor->execute(['Dr. Budi Santoso', 'Anak', '081298765432']);
    }

    $defaultSchedules = $pdo->query('SELECT COUNT(*) FROM schedules')->fetchColumn();
    if ($defaultSchedules == 0) {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $insertSchedule = $pdo->prepare('INSERT INTO schedules (doctor_id, date, time, slots) VALUES (?, ?, ?, ?)');
        $insertSchedule->execute([1, $today, '09:00:00', 4]);
        $insertSchedule->execute([1, $today, '14:00:00', 4]);
        $insertSchedule->execute([2, $today, '10:00:00', 3]);
        $insertSchedule->execute([2, $tomorrow, '11:00:00', 3]);
    }

    $defaultContent = $pdo->query('SELECT COUNT(*) FROM site_content')->fetchColumn();
    if ($defaultContent == 0) {
        $insertContent = $pdo->prepare('INSERT INTO site_content (content_key, content_value) VALUES (?, ?)');
        $insertContent->execute(['site_title', 'Sistem Klinik Sederhana']);
        $insertContent->execute(['hero_eyebrow', 'Selamat Datang di Klinik Sehat']);
        $insertContent->execute(['hero_title', 'Reservasi Janji Temu Dokter dengan Mudah']);
        $insertContent->execute(['hero_text', 'Lihat jadwal dokter untuk hari ini, cek ketersediaan slot, dan langsung kirim permintaan reservasi ke admin lewat WhatsApp.']);
        $insertContent->execute(['hero_cta_text', 'Lihat Jadwal']);
        $insertContent->execute(['footer_text', 'Untuk reservasi, klik tombol WhatsApp dan kirim pesan ke admin klinik.']);
    }

    $defaultSettings = $pdo->query('SELECT COUNT(*) FROM admin_settings')->fetchColumn();
    if ($defaultSettings == 0) {
        $insertSettings = $pdo->prepare('INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?)');
        $insertSettings->execute(['admin_phone', ADMIN_PHONE]);
    }

    $adminExists = $pdo->prepare('SELECT COUNT(*) AS count FROM users WHERE username = ? LIMIT 1');
    $adminExists->execute(['admin']);
    $adminExistsRow = $adminExists->fetch(PDO::FETCH_ASSOC);
    if (!$adminExistsRow || $adminExistsRow['count'] == 0) {
        $insertUser = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
        $insertUser->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
    }

    $doctorRows = $pdo->query('SELECT id FROM doctors')->fetchAll(PDO::FETCH_COLUMN);
    $checkDoctorUser = $pdo->prepare('SELECT COUNT(*) AS count FROM users WHERE doctor_id = ? LIMIT 1');
    $createDoctorUser = $pdo->prepare('INSERT INTO users (username, password, role, doctor_id) VALUES (?, ?, ?, ?)');
    foreach ($doctorRows as $doctorId) {
        $checkDoctorUser->execute([$doctorId]);
        $doctorExistsRow = $checkDoctorUser->fetch(PDO::FETCH_ASSOC);
        if (!$doctorExistsRow || $doctorExistsRow['count'] == 0) {
            $createDoctorUser->execute([(string)$doctorId, password_hash('doctor123', PASSWORD_DEFAULT), 'doctor', (int)$doctorId]);
        }
    }
}

function db_query($sql, $params = [], $one = false) {
    $stmt = db_connect()->prepare($sql);
    $stmt->execute($params);
    return $one ? $stmt->fetch() : $stmt->fetchAll();
}

function load_data($name) {
    switch ($name) {
        case 'doctors':
            return db_query('SELECT * FROM doctors ORDER BY name');
        case 'schedules':
            return db_query('SELECT * FROM schedules ORDER BY date, time');
        case 'patients':
            return db_query('SELECT * FROM patients ORDER BY created_at DESC');
    }
    return [];
}

function load_site_contents() {
    $rows = db_query('SELECT content_key, content_value FROM site_content');
    $contents = [];
    foreach ($rows as $row) {
        $contents[$row['content_key']] = $row['content_value'];
    }
    return $contents;
}

function upsert_site_content($key, $value) {
    db_query('INSERT INTO site_content (content_key, content_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE content_value = VALUES(content_value)', [$key, $value]);
}

function get_site_content($key, $default = '') {
    $row = db_query('SELECT content_value FROM site_content WHERE content_key = ? LIMIT 1', [$key], true);
    return $row ? $row['content_value'] : $default;
}

function find_by_id($items, $id, $key = 'id') {
    foreach ($items as $item) {
        if ((string)$item[$key] === (string)$id) {
            return $item;
        }
    }
    return null;
}

function get_schedule_by_id($scheduleId) {
    return db_query('SELECT * FROM schedules WHERE id = ? LIMIT 1', [$scheduleId], true);
}

function get_doctor_by_id($doctorId) {
    return db_query('SELECT * FROM doctors WHERE id = ? LIMIT 1', [$doctorId], true);
}

function insert_doctor($name, $specialty, $phone, $description = '', $education = '', $symptoms = '') {
    $stmt = db_connect()->prepare('INSERT INTO doctors (name, specialty, phone, description, education, symptoms) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $specialty, $phone, $description, $education, $symptoms]);
    $doctorId = db_connect()->lastInsertId();
    $username = (string)$doctorId;
    $passwordHash = password_hash('doctor123', PASSWORD_DEFAULT);
    $createUser = db_connect()->prepare('INSERT INTO users (username, password, role, doctor_id) VALUES (?, ?, ?, ?)');
    $createUser->execute([$username, $passwordHash, 'doctor', (int)$doctorId]);
}

function update_doctor($doctorId, $name, $specialty, $phone, $description = '', $education = '', $symptoms = '') {
    db_query('UPDATE doctors SET name = ?, specialty = ?, phone = ?, description = ?, education = ?, symptoms = ? WHERE id = ?', 
        [$name, $specialty, $phone, $description, $education, $symptoms, $doctorId]);
}

function get_admin_setting($key, $default = '') {
    $row = db_query('SELECT setting_value FROM admin_settings WHERE setting_key = ? LIMIT 1', [$key], true);
    return $row ? $row['setting_value'] : $default;
}

function upsert_admin_setting($key, $value) {
    db_query('INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
}

function insert_schedule($doctorId, $date, $time, $slots) {
    db_query('INSERT INTO schedules (doctor_id, date, time, slots) VALUES (?, ?, ?, ?)', [$doctorId, $date, $time, $slots]);
}

function insert_patient($name, $phone, $doctorId, $scheduleId) {
    db_query('INSERT INTO patients (name, phone, doctor_id, schedule_id) VALUES (?, ?, ?, ?)', [$name, $phone, $doctorId, $scheduleId]);
}

function count_bookings($patients, $scheduleId) {
    $count = 0;
    foreach ($patients as $patient) {
        if ($patient['schedule_id'] === $scheduleId) {
            $count++;
        }
    }
    return $count;
}

function get_schedule_bookings($schedules, $patients) {
    $bookings = [];
    foreach ($schedules as $schedule) {
        $bookings[$schedule['id']] = count_bookings($patients, $schedule['id']);
    }
    return $bookings;
}

function flash_message($text, $type = 'success') {
    echo '<div class="flash flash-' . htmlspecialchars($type) . '">' . htmlspecialchars($text) . '</div>';
}

function sanitize($value) {
    return htmlspecialchars(trim($value));
}

function login_user($username, $password) {
    $row = db_query('SELECT id, username, password, role, doctor_id FROM users WHERE username = ? LIMIT 1', [$username], true);
    if (!$row && ctype_digit($username)) {
        $row = db_query('SELECT id, username, password, role, doctor_id FROM users WHERE doctor_id = ? LIMIT 1', [$username], true);
    }

    if ($row) {
        if (password_verify($password, $row['password'])) {
            return $row;
        }
        return null;
    }

    if (ctype_digit($username)) {
        $doctor = get_doctor_by_id((int)$username);
        if ($doctor && $password === 'doctor123') {
            $passwordHash = password_hash('doctor123', PASSWORD_DEFAULT);
            $createUser = db_connect()->prepare('INSERT INTO users (username, password, role, doctor_id) VALUES (?, ?, ?, ?)');
            $createUser->execute([$username, $passwordHash, 'doctor', (int)$username]);
            return db_query('SELECT id, username, password, role, doctor_id FROM users WHERE username = ? LIMIT 1', [$username], true);
        }
    }

    return null;
}

function check_session() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    $user = db_query('SELECT id, username, role, doctor_id FROM users WHERE id = ? LIMIT 1', [$_SESSION['user_id']], true);
    return $user ? $user : false;
}

function is_admin() {
    $user = check_session();
    return $user && $user['role'] === 'admin';
}

function is_doctor() {
    $user = check_session();
    return $user && $user['role'] === 'doctor';
}
