-- Starter data: cities + hospitals.  Run after schema.sql:
--   sudo mysql < database/seed.sql
USE LifeLink;

INSERT INTO cities (city_name) VALUES
('Lahore'),('Karachi'),('Islamabad'),('Rawalpindi'),('Faisalabad'),('Multan');

INSERT INTO hospitals (hospital_name, city_id, address, phone, is_verified)
SELECT 'Services Hospital', city_id, 'Jail Road, Lahore', '042-111-000-001', 1 FROM cities WHERE city_name='Lahore';
INSERT INTO hospitals (hospital_name, city_id, address, phone, is_verified)
SELECT 'Jinnah Hospital', city_id, 'Usman Block, Lahore', '042-111-000-002', 1 FROM cities WHERE city_name='Lahore';
INSERT INTO hospitals (hospital_name, city_id, address, phone, is_verified)
SELECT 'Aga Khan University Hospital', city_id, 'Stadium Road, Karachi', '021-111-000-003', 1 FROM cities WHERE city_name='Karachi';
INSERT INTO hospitals (hospital_name, city_id, address, phone, is_verified)
SELECT 'PIMS', city_id, 'G-8/3, Islamabad', '051-111-000-004', 1 FROM cities WHERE city_name='Islamabad';
INSERT INTO hospitals (hospital_name, city_id, address, phone, is_verified)
SELECT 'City Care Clinic', city_id, 'Main Market, Lahore', NULL, 0 FROM cities WHERE city_name='Lahore';
