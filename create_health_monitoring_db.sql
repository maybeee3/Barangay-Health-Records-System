-- Create database for Barangay Health Monitoring System
CREATE DATABASE IF NOT EXISTS health_monitoring;
USE health_monitoring;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  reset_token VARCHAR(10),
  reset_token_expires_at DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- Insert default user for login
INSERT INTO users (email, password) VALUES
('brgysanisidrohealth@gmail.com', 'Healthmonitoring');

-- Residents table for Barangay Health Monitoring System


CREATE TABLE IF NOT EXISTS residents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100),
  last_name VARCHAR(100) NOT NULL,
  name_extension VARCHAR(20),
  date_of_birth DATE NOT NULL,
  sex VARCHAR(10) NOT NULL,
  civil_status VARCHAR(20),
  contact_no INT(11) DEFAULT NULL,
  email VARCHAR(100) CHECK (email LIKE '%@gmail.com'),
  address VARCHAR(255),
  province VARCHAR(100) DEFAULT 'Laguna',
  city_municipality VARCHAR(100) DEFAULT 'Pagsanjan',
  barangay VARCHAR(100),
  father_name VARCHAR(100),
  mother_name VARCHAR(100),
  guardian_name VARCHAR(100),
  guardian_relationship VARCHAR(50),
  guardian_contact_no INT(111),
  postal_code INT,
  years_of_residency INT,
  registration_status VARCHAR(20),
  existing_conditions TEXT,
  allergies TEXT,
  maintenance_medicines TEXT,
  blood_type VARCHAR(10),
  occupation VARCHAR(100),
  educational_attainment VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Consultations table for Barangay Health Monitoring System
CREATE TABLE IF NOT EXISTS consultations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resident_id INT NOT NULL,
  email VARCHAR(100),
  date_of_consultation DATE NOT NULL,
  consultation_time TIME,
  blood_pressure VARCHAR(20),
  heart_rate VARCHAR(20),
  respiratory_rate VARCHAR(20),
  temperature VARCHAR(20),
  blood_sugar VARCHAR(20),
  weight VARCHAR(20),
  height VARCHAR(20),
  bmi VARCHAR(20),
  reason_for_consultation TEXT DEFAULT NULL,
  consulting_doctor VARCHAR(255) DEFAULT NULL,
  reminder_sent TINYINT(1) NOT NULL DEFAULT 0
  treatment_prescription TEXT,
  follow_up_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (resident_id) REFERENCES residents(id)
);

CREATE TABLE IF NOT EXISTS health_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resident_id INT NOT NULL,
  record_date DATE DEFAULT NULL,
  record_time TIME DEFAULT NULL,
  contact_no VARCHAR(50) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  reason TEXT DEFAULT NULL,
  treatment TEXT DEFAULT NULL,
  assessment TEXT DEFAULT NULL,
  consulting_doctor VARCHAR(255) DEFAULT NULL,
  v_temp VARCHAR(20) DEFAULT NULL,
  v_wt VARCHAR(20) DEFAULT NULL,
  v_ht VARCHAR(20) DEFAULT NULL,
  v_bp VARCHAR(50) DEFAULT NULL,
  v_rr VARCHAR(20) DEFAULT NULL,
  v_pr VARCHAR(20) DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (resident_id) REFERENCES residents(id)
);";