-- Skema database untuk aplikasi klinik sederhana

CREATE DATABASE IF NOT EXISTS `klinik_sederhana` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `klinik_sederhana`;

CREATE TABLE IF NOT EXISTS `doctors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `specialty` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `doctor_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `time` TIME NOT NULL,
  `slots` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `patients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `doctor_id` INT NOT NULL,
  `schedule_id` INT NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Reservasi',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sampel data awal
INSERT INTO `doctors` (`name`, `specialty`, `phone`) VALUES
('Dr. Siti Aminah', 'Umum', '081234567890'),
('Dr. Budi Santoso', 'Anak', '081298765432');

INSERT INTO `schedules` (`doctor_id`, `date`, `time`, `slots`) VALUES
(1, CURDATE(), '09:00:00', 4),
(1, CURDATE(), '14:00:00', 4),
(2, CURDATE(), '10:00:00', 3),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00', 3);
