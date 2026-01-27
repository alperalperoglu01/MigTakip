CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','chef','user') NOT NULL DEFAULT 'user',
  courier_class VARCHAR(50) NULL,
  seniority_start_date DATE NULL,
  accounting_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  motor_default_type ENUM('own','rental') NOT NULL DEFAULT 'own',
  motor_monthly_rent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  first_login_done TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS company_settings (
  id INT PRIMARY KEY,
  hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 177.00,
  default_daily_hours DECIMAL(10,2) NOT NULL DEFAULT 12.00,
  overtime_hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO company_settings (id, hourly_rate, default_daily_hours, overtime_hourly_rate)
VALUES (1, 177.00, 12.00, 0.00)
ON DUPLICATE KEY UPDATE
  hourly_rate=VALUES(hourly_rate),
  default_daily_hours=VALUES(default_daily_hours),
  overtime_hourly_rate=VALUES(overtime_hourly_rate);

CREATE TABLE IF NOT EXISTS prime_tiers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  min_value INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  label VARCHAR(60) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uniq_prime_min (min_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bonus_tiers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  min_value INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  label VARCHAR(60) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uniq_bonus_min (min_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS months (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  ym CHAR(7) NOT NULL,
  daily_hours DECIMAL(10,2) NOT NULL,
  hourly_rate DECIMAL(10,2) NOT NULL,
  locked_daily_hours DECIMAL(10,2) NOT NULL,
  locked_hourly_rate DECIMAL(10,2) NOT NULL,
  locked_overtime_hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  locked_help_fund DECIMAL(10,2) NOT NULL DEFAULT 250.00,
  locked_franchise_fee DECIMAL(10,2) NOT NULL DEFAULT 1000.00,
  locked_tevkifat_rate DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  is_closed TINYINT(1) NOT NULL DEFAULT 0,
  target_packages INT NULL,
  remaining_days INT NULL,
  fuel_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  penalty_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  other_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  advance_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_month (user_id, ym),
  CONSTRAINT fk_month_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS day_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  month_id INT NOT NULL,
  day TINYINT NOT NULL,
  status ENUM('WORK','LEAVE','SICK','ANNUAL','OFF') NOT NULL DEFAULT 'OFF',
  packages INT NOT NULL DEFAULT 0,
  overtime_hours DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  note VARCHAR(255) NULL,
  motor_type ENUM('own','rental') NOT NULL DEFAULT 'own',
  motor_rent_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_month_day (month_id, day),
  CONSTRAINT fk_day_month FOREIGN KEY (month_id) REFERENCES months(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO prime_tiers (min_value, amount, label, active) VALUES
(0,   0,    '0-17', 1),
(18,  250,  '18-23', 1),
(24,  430,  '24-27', 1),
(28,  645,  '28-32', 1),
(33,  1010, '33-37', 1),
(38,  1595, '38-42', 1),
(43,  2040, '43-48', 1),
(49,  2500, '49+',   1)
ON DUPLICATE KEY UPDATE amount=VALUES(amount), label=VALUES(label), active=VALUES(active);

INSERT INTO bonus_tiers (min_value, amount, label, active) VALUES
(0,    0,     '0+', 1),
(700,  12800,     '700-799 (düzenle)', 1),
(800,  20224,     '800-999 (düzenle)', 1),
(1000, 33408, '1000-1199', 1),
(1200, 40300, '1200-1399', 1),
(1400, 47970, '1400+',     1)
ON DUPLICATE KEY UPDATE amount=VALUES(amount), label=VALUES(label), active=VALUES(active);

INSERT INTO users (name, email, password_hash, role)
VALUES ('Admin', 'admin@example.com', '$2b$12$RhKqIvtaTd1782EaUgDYXuif6tq69t6V9P52SA05L4vQjlGMU/zY.', 'admin')
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), role=VALUES(role);


-- Migration for existing databases (run once if needed):
ALTER TABLE months ADD COLUMN target_packages INT NULL;
ALTER TABLE months ADD COLUMN remaining_days INT NULL;

ALTER TABLE users MODIFY role ENUM('admin','chef','user') NOT NULL DEFAULT 'user';
ALTER TABLE users ADD COLUMN seniority_start_date DATE NULL;

ALTER TABLE company_settings ADD COLUMN overtime_hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00;

ALTER TABLE day_entries ADD COLUMN overtime_hours DECIMAL(10,2) NOT NULL DEFAULT 0.00;


-- MIGRATIONS (v3.2)
ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER email;
ALTER TABLE users ADD COLUMN courier_class VARCHAR(50) NULL AFTER role;
ALTER TABLE months ADD COLUMN fuel_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER remaining_days;
ALTER TABLE months ADD COLUMN penalty_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER fuel_cost;
ALTER TABLE months ADD COLUMN other_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER penalty_cost;
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category VARCHAR(20) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- MIGRATIONS (v3.4) - İki yönlü mesajlaşma
CREATE TABLE IF NOT EXISTS contact_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL,
    sender_type VARCHAR(10) NOT NULL, -- 'user' veya 'admin'
    sender_user_id INT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- MIGRATIONS (v3.5) - Ay kilitleme, onboarding, motor kira, avans, yeni durumlar
ALTER TABLE users ADD COLUMN accounting_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE users ADD COLUMN motor_default_type ENUM('own','rental') NOT NULL DEFAULT 'own';
ALTER TABLE users ADD COLUMN motor_monthly_rent DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE users ADD COLUMN first_login_done TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE months ADD COLUMN locked_daily_hours DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE months ADD COLUMN locked_hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE months ADD COLUMN locked_overtime_hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE months ADD COLUMN locked_help_fund DECIMAL(10,2) NOT NULL DEFAULT 250.00;
ALTER TABLE months ADD COLUMN locked_franchise_fee DECIMAL(10,2) NOT NULL DEFAULT 1000.00;
ALTER TABLE months ADD COLUMN locked_tevkifat_rate DECIMAL(5,2) NOT NULL DEFAULT 5.00;
ALTER TABLE months ADD COLUMN is_closed TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE months ADD COLUMN advance_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00;

ALTER TABLE day_entries MODIFY status ENUM('WORK','LEAVE','SICK','ANNUAL','OFF') NOT NULL DEFAULT 'OFF';
ALTER TABLE day_entries ADD COLUMN motor_type ENUM('own','rental') NOT NULL DEFAULT 'own';
ALTER TABLE day_entries ADD COLUMN motor_rent_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- contact_messages status düzeltme (eski kurulumlarda)
ALTER TABLE contact_messages MODIFY status VARCHAR(20) NOT NULL DEFAULT 'open';
