-- Core admin tables for Stores, Workers, Projects, Notifications
-- Run these against your MySQL database configured in php/db.php

-- STORES
CREATE TABLE IF NOT EXISTS stores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  location VARCHAR(190) DEFAULT NULL,
  manager VARCHAR(150) DEFAULT NULL,
  status ENUM('Open','Closed','Maintenance') NOT NULL DEFAULT 'Open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL,
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WORKERS
CREATE TABLE IF NOT EXISTS workers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  role VARCHAR(120) DEFAULT NULL,
  store_id INT NULL,
  contact VARCHAR(190) DEFAULT NULL,
  status ENUM('Active','Inactive','On Leave','Terminated') NOT NULL DEFAULT 'Active',
  start_date DATE NULL,
  probation_end DATE NULL,
  avatar_url VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_workers_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PROJECTS
CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  store_id INT NULL,
  owner VARCHAR(150) DEFAULT NULL,
  due_date DATE NULL,
  status ENUM('Planning','In Progress','Blocked','Done','Cancelled') NOT NULL DEFAULT 'Planning',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_projects_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
  KEY idx_status (status),
  KEY idx_due (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTIFICATIONS
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  body TEXT NULL,
  type ENUM('info','success','warning','error') NOT NULL DEFAULT 'info',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_is_read (is_read),
  KEY idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional sample data (uncomment to seed)
-- INSERT INTO stores (name, location, manager) VALUES ('Main Warehouse','Downtown','Ava Cole');
-- INSERT INTO workers (name, role, store_id, contact) VALUES ('John Doe','Storekeeper',1,'+123456');
-- INSERT INTO projects (name, store_id, owner, due_date, status) VALUES ('Inventory Revamp',1,'Ops','2025-12-10','In Progress');
-- INSERT INTO notifications (title, body, type) VALUES ('Welcome','Admin dashboard initialized','success');


-- =============================
-- Store Management Additions
-- (moved here from 030_store_management_schema.sql so a single migration can set up everything)
-- =============================

-- MATERIALS (Raw Materials)
CREATE TABLE IF NOT EXISTS materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(190) NOT NULL,
  unit VARCHAR(30) NOT NULL, -- e.g., kg, m, pcs
  min_stock DECIMAL(12,2) NOT NULL DEFAULT 0,
  max_stock DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('OK','Low','Inactive') DEFAULT 'OK',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_material_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SUPPLIERS
CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  contact VARCHAR(190) DEFAULT NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STORE USERS (module-level users)
CREATE TABLE IF NOT EXISTS store_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  role VARCHAR(50) NOT NULL, -- e.g., Storekeeper, Manager, Security
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_store_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- REPORTERS
CREATE TABLE IF NOT EXISTS reporters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  role VARCHAR(100) DEFAULT NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- INVENTORY (per Store/Material)
CREATE TABLE IF NOT EXISTS inventory (
  id INT AUTO_INCREMENT PRIMARY KEY,
  store_id INT NOT NULL,
  material_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
  last_update DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_store_material (store_id, material_id),
  KEY idx_material (material_id),
  CONSTRAINT fk_inv_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_inv_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STOCK MOVEMENTS (Receipts/Issues/Adjustments)
CREATE TABLE IF NOT EXISTS stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  movement_date DATE NOT NULL,
  store_id INT NOT NULL,
  material_id INT NOT NULL,
  movement_type ENUM('Receipt','Issue','Adjust') NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  reference VARCHAR(100) DEFAULT NULL,
  user_name VARCHAR(100) DEFAULT NULL, -- simple user string; could FK to store_users
  notes VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mov_date (movement_date),
  KEY idx_mov_store (store_id),
  KEY idx_mov_material (material_id),
  CONSTRAINT fk_mov_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_mov_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- VIEW: Inventory with computed status vs thresholds
DROP VIEW IF EXISTS v_inventory_status;
CREATE VIEW v_inventory_status AS
SELECT i.id,
       i.store_id,
       s.name AS store_name,
       i.material_id,
       m.code AS material_code,
       m.name AS material_name,
       m.unit,
       m.min_stock,
       m.max_stock,
       i.quantity,
       i.last_update,
       CASE WHEN i.quantity < m.min_stock THEN 'Low'
            WHEN i.quantity > m.max_stock THEN 'High'
            ELSE 'OK' END AS status
FROM inventory i
JOIN stores s ON s.id = i.store_id
JOIN materials m ON m.id = i.material_id;
