-- =====================================================================
-- AgriCare - Crop Health and Advisory System
-- FILE: dev_reset.sql
--
-- !!! DANGER - DESTRUCTIVE - DEVELOPMENT USE ONLY !!!
-- This script DELETES ALL TABLES AND ALL DATA in the "agricare" database:
-- every user, farm, crop record, report, advice and follow-up note.
-- It does NOT delete uploaded photographs in public/uploads/reports/.
-- NEVER run it on a database that holds real records.
--
-- It exists so a developer can return to a clean sample state:
--   sudo mysql < database/dev_reset.sql     (1) wipe
--   sudo mysql < database/schema.sql        (2) rebuild structure
--   sudo mysql < database/seed.sql          (3) load sample data
--
-- The database itself and the MySQL user/privileges are kept.
-- Tables are dropped children-first so foreign keys never block the drop.
-- =====================================================================

USE agricare;

DROP VIEW  IF EXISTS reviewer_pending_reports;
DROP VIEW  IF EXISTS farmer_report_summary;

DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS report_updates;
DROP TABLE IF EXISTS advice;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS diseases;
DROP TABLE IF EXISTS farm_crops;
DROP TABLE IF EXISTS crops;
DROP TABLE IF EXISTS farms;
DROP TABLE IF EXISTS users;
