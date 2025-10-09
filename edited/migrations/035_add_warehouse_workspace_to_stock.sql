-- Add warehouse and workspace fields to inventory and stock_movements
-- This enables per-warehouse/workspace stock tracking and CRUD

-- INVENTORY: add columns and expand unique key
ALTER TABLE inventory
  ADD COLUMN warehouse VARCHAR(100) NOT NULL DEFAULT '' AFTER material_id,
  ADD COLUMN workspace VARCHAR(100) NOT NULL DEFAULT '' AFTER warehouse;

-- Replace unique key to include warehouse/workspace
ALTER TABLE inventory
  DROP INDEX uq_store_material,
  ADD UNIQUE INDEX uq_store_material_location (store_id, material_id, warehouse, workspace);

-- Optional performance index
CREATE INDEX idx_inventory_wh_ws ON inventory (warehouse, workspace);

-- STOCK MOVEMENTS: add columns
ALTER TABLE stock_movements
  ADD COLUMN warehouse VARCHAR(100) NOT NULL DEFAULT '' AFTER user_name,
  ADD COLUMN workspace VARCHAR(100) NOT NULL DEFAULT '' AFTER warehouse;

-- Optional performance index
CREATE INDEX idx_movements_wh_ws ON stock_movements (warehouse, workspace);
