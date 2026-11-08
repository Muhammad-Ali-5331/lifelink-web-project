-- =====================================================================
-- LifeLink schema  (MySQL 8.0.16+ so that CHECK constraints are enforced)
-- Run as MySQL root:  sudo mysql < database/schema.sql
-- =====================================================================
DROP DATABASE IF EXISTS LifeLink;
CREATE DATABASE LifeLink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE LifeLink;

-- 1. cities ------------------------------------------------------------
CREATE TABLE cities (
    city_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    city_name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 2. users -------------------------------------------------------------
CREATE TABLE users (
    user_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(100) NOT NULL UNIQUE,
    phone         VARCHAR(20)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','donor','requester') NOT NULL DEFAULT 'donor',
    city_id       INT UNSIGNED NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_city FOREIGN KEY (city_id)
        REFERENCES cities(city_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 3. donors (one row per donor user) -------------------------------------
CREATE TABLE donors (
    user_id            INT UNSIGNED PRIMARY KEY,
    blood_group        ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    date_of_birth      DATE NOT NULL,
    weight_kg          DECIMAL(4,1) NOT NULL,
    last_donation_date DATE NULL,
    is_available       TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_donors_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT chk_weight CHECK (weight_kg >= 50)
) ENGINE=InnoDB;

-- 4. hospitals ---------------------------------------------------------
CREATE TABLE hospitals (
    hospital_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_name VARCHAR(100) NOT NULL,
    city_id       INT UNSIGNED NOT NULL,
    address       VARCHAR(200) NOT NULL,
    phone         VARCHAR(20) NULL,
    is_verified   TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_hospital_city (hospital_name, city_id),
    CONSTRAINT fk_hospitals_city FOREIGN KEY (city_id)
        REFERENCES cities(city_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 5. blood_requests ----------------------------------------------------
CREATE TABLE blood_requests (
    request_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_id    INT UNSIGNED NOT NULL,
    hospital_id     INT UNSIGNED NOT NULL,
    patient_name    VARCHAR(100) NOT NULL,
    blood_group     ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    units_needed    TINYINT UNSIGNED NOT NULL,
    units_fulfilled TINYINT UNSIGNED NOT NULL DEFAULT 0,
    urgency         ENUM('normal','urgent','critical') NOT NULL DEFAULT 'normal',
    needed_by       DATE NOT NULL,
    status          ENUM('open','fulfilled','cancelled','expired') NOT NULL DEFAULT 'open',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_req_requester FOREIGN KEY (requester_id)
        REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_req_hospital FOREIGN KEY (hospital_id)
        REFERENCES hospitals(hospital_id) ON DELETE RESTRICT,
    CONSTRAINT chk_units_needed CHECK (units_needed BETWEEN 1 AND 10),
    CONSTRAINT chk_units_fulfilled CHECK (units_fulfilled <= units_needed)
) ENGINE=InnoDB;

-- 6. donor_responses ---------------------------------------------------
CREATE TABLE donor_responses (
    response_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id   INT UNSIGNED NOT NULL,
    donor_id     INT UNSIGNED NOT NULL,
    status       ENUM('offered','accepted','completed','declined') NOT NULL DEFAULT 'offered',
    responded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_request_donor (request_id, donor_id),
    CONSTRAINT fk_resp_request FOREIGN KEY (request_id)
        REFERENCES blood_requests(request_id) ON DELETE CASCADE,
    CONSTRAINT fk_resp_donor FOREIGN KEY (donor_id)
        REFERENCES donors(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. donations (permanent history) ---------------------------------------
CREATE TABLE donations (
    donation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    response_id INT UNSIGNED NOT NULL UNIQUE,
    donated_on  DATE NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_don_response FOREIGN KEY (response_id)
        REFERENCES donor_responses(response_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 8. blood_compatibility (27 rows) ---------------------------------------
CREATE TABLE blood_compatibility (
    donor_group     ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    recipient_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    PRIMARY KEY (donor_group, recipient_group)
) ENGINE=InnoDB;

INSERT INTO blood_compatibility (donor_group, recipient_group) VALUES
('O-','O-'),('O-','O+'),('O-','A-'),('O-','A+'),('O-','B-'),('O-','B+'),('O-','AB-'),('O-','AB+'),
('O+','O+'),('O+','A+'),('O+','B+'),('O+','AB+'),
('A-','A-'),('A-','A+'),('A-','AB-'),('A-','AB+'),
('A+','A+'),('A+','AB+'),
('B-','B-'),('B-','B+'),('B-','AB-'),('B-','AB+'),
('B+','B+'),('B+','AB+'),
('AB-','AB-'),('AB-','AB+'),
('AB+','AB+');

-- =====================================================================
-- VIEWS
-- v_eligible_donors deliberately has NO phone column, so a careless
-- query on the view can never leak a donor's number.
-- =====================================================================
CREATE VIEW v_eligible_donors AS
SELECT u.user_id, u.full_name, u.city_id, c.city_name,
       d.blood_group, d.last_donation_date
FROM donors d
JOIN users  u ON u.user_id = d.user_id
JOIN cities c ON c.city_id = u.city_id
WHERE u.is_active = 1
  AND d.is_available = 1
  AND TIMESTAMPDIFF(YEAR, d.date_of_birth, CURDATE()) >= 18
  AND (d.last_donation_date IS NULL
       OR DATEDIFF(CURDATE(), d.last_donation_date) >= 90);

CREATE VIEW v_open_requests AS
SELECT r.request_id, r.requester_id, r.patient_name, r.blood_group, r.urgency,
       r.needed_by, r.units_needed, r.units_fulfilled,
       (r.units_needed - r.units_fulfilled) AS units_remaining,
       h.hospital_id, h.hospital_name, h.address AS hospital_address,
       h.city_id, c.city_name,
       (SELECT COUNT(*)
          FROM v_eligible_donors e
          JOIN blood_compatibility bc
            ON bc.donor_group = e.blood_group
           AND bc.recipient_group = r.blood_group
         WHERE e.city_id = h.city_id) AS eligible_donors
FROM blood_requests r
JOIN hospitals h ON h.hospital_id = r.hospital_id
JOIN cities    c ON c.city_id = h.city_id
WHERE r.status = 'open' AND r.needed_by >= CURDATE();

-- =====================================================================
-- Application database user (least privilege: only the LifeLink DB).
-- Change the password, and keep it in sync with config/database.php
-- =====================================================================
CREATE USER IF NOT EXISTS 'lifelinkuser'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD_HERE';
GRANT SELECT, INSERT, UPDATE, DELETE ON LifeLink.* TO 'lifelinkuser'@'localhost';
