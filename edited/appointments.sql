-- SQL schema for appointments
CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  scheduled_date DATE NOT NULL,
  scheduled_time TIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('scheduled','checked_in') NOT NULL DEFAULT 'scheduled',
  queue INT DEFAULT 0,
  reschedule_count INT DEFAULT 0,
  message TEXT,
  KEY idx_status_date (status, scheduled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
