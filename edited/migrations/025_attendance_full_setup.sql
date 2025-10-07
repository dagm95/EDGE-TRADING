-- Robust attendance table to store worker attendance day-by-day
-- Safe to run multiple times; CREATE is IF NOT EXISTS. ALTERs may error if already applied.

CREATE TABLE IF NOT EXISTS attendance (
  `date` date NOT NULL,
  `worker_id` int NOT NULL,
  `status` enum('Present','Late','Absent','Leave') DEFAULT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`date`, `worker_id`),
  KEY `idx_worker_date` (`worker_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Try to add FK (ignore error if already exists or if workers table missing)
ALTER TABLE attendance
  ADD CONSTRAINT `fk_att_worker` FOREIGN KEY (`worker_id`) REFERENCES `workers`(`id`) ON DELETE CASCADE;
