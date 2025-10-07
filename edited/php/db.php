<?php
/**
 * Central database connection helper.
 *
 * Update the defaults below OR (recommended) set environment variables:
 *   DB_HOST, DB_NAME, DB_USER, DB_PASS
 *
 * Example (PowerShell temporary env vars before starting PHP server):
 *   $env:DB_NAME = 'edge_trading'
 *   $env:DB_USER = 'root'
 *   php -S localhost:8000
 */

// Fallback defaults (edit these to match the DB you imported from SQLQuery1.sql)
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'edge_trading'; // Change this to your actual database name if different
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

/**
 * Returns a mysqli connection or terminates execution on failure.
 */
function get_db() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($mysqli->connect_error) {
        // Provide a clearer diagnostic (never expose credentials themselves)
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        die("DB connection error (" . $mysqli->connect_errno . "): " . $mysqli->connect_error . "\nCheck db.php settings or environment variables.");
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

/** Optional: basic runtime schema check (non-fatal).
 * Call ensure_appointments_table() early if you want automatic creation when missing.
 * Disabled by default to avoid accidental schema drift in production.
 */
function ensure_appointments_table($autoCreate = false) {
    if (!$autoCreate) return; // opt-in only
    $db = get_db();
    $exists = $db->query("SHOW TABLES LIKE 'appointments'");
    if ($exists && $exists->num_rows) return; // already there
    $sql = <<<SQL
CREATE TABLE appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(60) NOT NULL,
  scheduled_date DATE NOT NULL,
  scheduled_time TIME NULL,
  message TEXT NULL,
  queue INT NOT NULL DEFAULT 0,
  status ENUM('scheduled','checked_in') NOT NULL DEFAULT 'scheduled',
  reschedule_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status_date (status, scheduled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    $db->query($sql); // Ignore errors here; if it fails, later queries will still surface the issue.
}

