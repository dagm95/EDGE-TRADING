Appointments system notes

- PHP files are inside `php/` to keep server endpoints together.
- Database expectations (table `appointments`):
  - id INT AUTO_INCREMENT PRIMARY KEY
  - name, email, phone
  - scheduled_date DATE
  - scheduled_time TIME (new column added by migration)
  - created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  - status ENUM('scheduled','checked_in') DEFAULT 'scheduled'
  - queue INT DEFAULT 0
  - reschedule_count INT DEFAULT 0
  - message TEXT

- To add the `scheduled_time` column, run the SQL in `migrations/001_add_scheduled_time.sql`.
- Admin and action handlers are located under `php/`.
- For production: move DB credentials and admin password to environment variables and use hashed admin passwords.
