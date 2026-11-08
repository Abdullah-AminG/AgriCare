-- ======================================================================
-- AgriCare - Crop Health and Advisory System
-- FILE: schema.sql  (database STRUCTURE only - no data)
--
-- SAFE TO RUN MORE THAN ONCE. This file never drops anything:
--   * CREATE DATABASE IF NOT EXISTS   -> keeps an existing database
--   * CREATE TABLE IF NOT EXISTS      -> keeps existing tables and their rows
--   * CREATE OR REPLACE VIEW          -> views hold no data, so replacing is safe
--
-- Import with:   sudo mysql < database/schema.sql
-- Then (once, optional, for demo/test data):   sudo mysql < database/seed.sql
-- Wipe everything during development only:     see database/dev_reset.sql
--
-- Note: "IF NOT EXISTS" does not ALTER a table that already exists. If the
-- table structure changes in a future version, a separate migration is needed.
-- ======================================================================

CREATE DATABASE IF NOT EXISTS agricare
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE agricare;

-- Table 1: users - farmer and reviewer (admin/adviser) accounts.
CREATE TABLE IF NOT EXISTS users (
    user_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(100) NOT NULL,
    phone         VARCHAR(20)  NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('farmer', 'admin') NOT NULL DEFAULT 'farmer',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- Table 2: farms (users 1 -> N farms)
CREATE TABLE IF NOT EXISTS farms (
    farm_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id   INT UNSIGNED NOT NULL,
    farm_name VARCHAR(100) NOT NULL,
    location  VARCHAR(150) NOT NULL,
    area      DECIMAL(10,2) NOT NULL COMMENT 'Area in acres',
    PRIMARY KEY (farm_id),
    KEY idx_farms_user (user_id),
    CONSTRAINT fk_farms_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Table 3: crops (master list of supported crops)
CREATE TABLE IF NOT EXISTS crops (
    crop_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    crop_name   VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    PRIMARY KEY (crop_id),
    UNIQUE KEY uq_crops_name (crop_name)
) ENGINE=InnoDB;

-- Table 4: farm_crops (farms 1 -> N farm_crops, crops 1 -> N farm_crops)
CREATE TABLE IF NOT EXISTS farm_crops (
    farm_crop_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    farm_id       INT UNSIGNED NOT NULL,
    crop_id       INT UNSIGNED NOT NULL,
    planting_date DATE NOT NULL,
    PRIMARY KEY (farm_crop_id),
    KEY idx_farm_crops_farm (farm_id),
    KEY idx_farm_crops_crop (crop_id),
    CONSTRAINT fk_farm_crops_farm
        FOREIGN KEY (farm_id) REFERENCES farms (farm_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_farm_crops_crop
        FOREIGN KEY (crop_id) REFERENCES crops (crop_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Table 5: diseases (crops 1 -> N diseases)
CREATE TABLE IF NOT EXISTS diseases (
    disease_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    crop_id      INT UNSIGNED NOT NULL,
    disease_name VARCHAR(150) NOT NULL,
    symptoms     TEXT NOT NULL,
    prevention   TEXT NOT NULL,
    PRIMARY KEY (disease_id),
    KEY idx_diseases_crop (crop_id),
    CONSTRAINT fk_diseases_crop
        FOREIGN KEY (crop_id) REFERENCES crops (crop_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Table 6: reports (users 1 -> N reports, farm_crops 1 -> N reports)
-- The application never deletes reports, so history is always kept.
CREATE TABLE IF NOT EXISTS reports (
    report_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    farm_crop_id INT UNSIGNED NOT NULL,
    image_path   VARCHAR(255) NULL COMMENT 'Relative path, e.g. uploads/reports/report_ab12.jpg',
    symptoms     TEXT NOT NULL,
    report_date  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status       ENUM('Pending', 'Under Review', 'Resolved') NOT NULL DEFAULT 'Pending',
    PRIMARY KEY (report_id),
    KEY idx_reports_user (user_id),
    KEY idx_reports_farm_crop (farm_crop_id),
    KEY idx_reports_status (status),
    KEY idx_reports_date (report_date),
    CONSTRAINT fk_reports_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_reports_farm_crop
        FOREIGN KEY (farm_crop_id) REFERENCES farm_crops (farm_crop_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Table 7: advice (reports 1 -> N advice, users 1 -> N advice)
-- disease_id is optional (NULL when the reviewer does not name a disease).
CREATE TABLE IF NOT EXISTS advice (
    advice_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id     INT UNSIGNED NOT NULL,
    reviewer_id   INT UNSIGNED NOT NULL,
    disease_id    INT UNSIGNED NULL,
    advice_text   TEXT NOT NULL,
    response_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (advice_id),
    KEY idx_advice_report (report_id),
    KEY idx_advice_reviewer (reviewer_id),
    KEY idx_advice_disease (disease_id),
    CONSTRAINT fk_advice_report
        FOREIGN KEY (report_id) REFERENCES reports (report_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_advice_reviewer
        FOREIGN KEY (reviewer_id) REFERENCES users (user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_advice_disease
        FOREIGN KEY (disease_id) REFERENCES diseases (disease_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Additional table: report_updates (reports 1 -> N report_updates)
-- Farmer progress notes. Old notes are never overwritten.
CREATE TABLE IF NOT EXISTS report_updates (
    update_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_id   INT UNSIGNED NOT NULL,
    update_note TEXT NOT NULL,
    update_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (update_id),
    KEY idx_updates_report (report_id),
    CONSTRAINT fk_updates_report
        FOREIGN KEY (report_id) REFERENCES reports (report_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Table: login_attempts - FAILED logins only, used to limit password guessing.
-- (5 failures for the same email + IP address within 15 minutes = temporarily locked.)
-- Rows older than one day are deleted automatically by the application.
-- Not linked to users with a foreign key on purpose: attempts for unknown emails are stored too.
CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email        VARCHAR(150) NOT NULL,
    ip_address   VARCHAR(45)  NOT NULL,
    attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attempt_id),
    KEY idx_login_attempts_lookup (email, ip_address, attempted_at)
) ENGINE=InnoDB;

-- ===================== VIEWS (read-only helpers) =====================

CREATE OR REPLACE VIEW farmer_report_summary AS
SELECT
    r.report_id,
    r.user_id,
    u.full_name   AS farmer_name,
    f.farm_id,
    f.farm_name,
    c.crop_id,
    c.crop_name,
    r.report_date,
    r.status
FROM reports r
JOIN users u       ON u.user_id = r.user_id
JOIN farm_crops fc ON fc.farm_crop_id = r.farm_crop_id
JOIN farms f       ON f.farm_id = fc.farm_id
JOIN crops c       ON c.crop_id = fc.crop_id;

CREATE OR REPLACE VIEW reviewer_pending_reports AS
SELECT
    r.report_id,
    r.symptoms,
    c.crop_name,
    f.farm_name,
    u.full_name AS farmer_name,
    r.report_date
FROM reports r
JOIN users u       ON u.user_id = r.user_id
JOIN farm_crops fc ON fc.farm_crop_id = r.farm_crop_id
JOIN farms f       ON f.farm_id = fc.farm_id
JOIN crops c       ON c.crop_id = fc.crop_id
WHERE r.status = 'Pending';
