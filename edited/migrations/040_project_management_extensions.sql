-- Project Management extensions

-- Extend projects with scheduling, assignments, storage and contact
ALTER TABLE projects
  ADD COLUMN description TEXT NULL AFTER name,
  ADD COLUMN start_date DATE NULL AFTER due_date,
  ADD COLUMN end_date DATE NULL AFTER start_date,
  ADD COLUMN duration_months INT NULL AFTER end_date,
  ADD COLUMN workspace_type ENUM('Furniture','Aluminum & Metal','Interior Design','Other') NOT NULL DEFAULT 'Other' AFTER duration_months,
  ADD COLUMN warehouse VARCHAR(100) NULL AFTER workspace_type,
  ADD COLUMN workspace VARCHAR(100) NULL AFTER warehouse,
  ADD COLUMN customer_email VARCHAR(190) NULL AFTER workspace,
  ADD COLUMN manager_user_id INT NULL AFTER customer_email,
  ADD COLUMN reporter_user_id INT NULL AFTER manager_user_id;

ALTER TABLE projects
  ADD CONSTRAINT fk_projects_manager_user FOREIGN KEY (manager_user_id) REFERENCES store_users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_projects_reporter_user FOREIGN KEY (reporter_user_id) REFERENCES store_users(id) ON DELETE SET NULL;

CREATE INDEX idx_projects_dates ON projects (start_date, end_date);
CREATE INDEX idx_projects_users ON projects (manager_user_id, reporter_user_id);

-- Items needed per project
CREATE TABLE IF NOT EXISTS project_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  material_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
  warehouse VARCHAR(100) NULL,
  workspace VARCHAR(100) NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pi_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT,
  INDEX idx_pi_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reports (analysis / weekly progress)
CREATE TABLE IF NOT EXISTS project_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  report_type ENUM('analysis','weekly') NOT NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NULL,
  report_date DATE NOT NULL,
  sent_to VARCHAR(190) NULL,
  sent_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  sent_at DATETIME NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pr_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_user FOREIGN KEY (created_by) REFERENCES store_users(id) ON DELETE SET NULL,
  INDEX idx_pr_project_date (project_id, report_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
