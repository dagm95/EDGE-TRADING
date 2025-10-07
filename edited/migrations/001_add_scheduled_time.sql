-- Migration: add scheduled_time column (TIME) to appointments table
-- Run this on your MySQL server for the database used by the site

ALTER TABLE appointments
  ADD COLUMN scheduled_time TIME NULL AFTER scheduled_date;

-- Optional: you may want to set default values for existing rows, or backfill based on created_at.
