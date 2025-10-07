-- Store Management Schema derived from admin/store.html UI
-- Run this after 010_create_core_admin_tables.sql

-- 1) Enhance stores table or create if missing
CREATE TABLE IF NOT EXISTS stores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  address VARCHAR(255) DEFAULT NULL,
  status ENUM('Active','Inactive','Maintenance') NOT NULL DEFAULT 'Active',
  manager_name VARCHAR(150) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_store_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If stores table already exists (from earlier migration), add missing columns if needed
ALTER TABLE stores
  ADD COLUMN IF NOT EXISTS address VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS status ENUM('Active','Inactive','Maintenance') NOT NULL DEFAULT 'Active',
  ADD COLUMN IF NOT EXISTS manager_name VARCHAR(150) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 2) Materials (Raw Materials)
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

-- 3) Suppliers
CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  contact VARCHAR(190) DEFAULT NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Store Users (module users, separate from admin login)
CREATE TABLE IF NOT EXISTS store_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  role VARCHAR(50) NOT NULL, -- e.g., Storekeeper, Manager, Security
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_store_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Reporters
CREATE TABLE IF NOT EXISTS reporters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  role VARCHAR(100) DEFAULT NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) Inventory per Store/Material
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

-- 7) Stock Movements (Receipts/Issues/Adjustments)
CREATE TABLE IF NOT EXISTS stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  movement_date DATE NOT NULL,
  store_id INT NOT NULL,
  material_id INT NOT NULL,
  movement_type ENUM('Receipt','Issue','Adjust') NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  reference VARCHAR(100) DEFAULT NULL,
  user_name VARCHAR(100) DEFAULT NULL, -- simple user string to match UI; could FK to store_users
  notes VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mov_date (movement_date),
  KEY idx_mov_store (store_id),
  KEY idx_mov_material (material_id),
  CONSTRAINT fk_mov_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_mov_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8) Convenience view to compute inventory status (OK/Low) against material thresholds
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
