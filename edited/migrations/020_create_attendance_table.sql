-- Creates attendance table to track daily attendance per worker
CREATE TABLE IF NOT EXISTS attendance (
  date date NOT NULL,
  worker_id int NOT NULL,
  status enum('Present','Late','Absent','Leave') DEFAULT NULL,
  time_in time DEFAULT NULL,
  time_out time DEFAULT NULL,
  notes varchar(255) DEFAULT NULL,
  PRIMARY KEY (date, worker_id),
  KEY idx_worker_date (worker_id, date),
  CONSTRAINT fk_att_worker FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
