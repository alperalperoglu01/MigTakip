<?php
// Basit şema kontrolü / otomatik migration
// Not: Yetki kısıtlı ortamlarda ALTER TABLE başarısız olabilir; bu durumda uygulama çalışmaya devam eder.

function column_exists(PDO $pdo, string $table, string $column): bool {
  $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?");
  $stmt->execute([$dbName, $table, $column]);
  return (int)$stmt->fetchColumn() > 0;
}

function table_exists(PDO $pdo, string $table): bool {
  $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
  $stmt->execute([$dbName, $table]);
  return (int)$stmt->fetchColumn() > 0;
}

function safe_exec(PDO $pdo, string $sql): void {
  try { $pdo->exec($sql); } catch (Throwable $e) { /* sessiz */ }
}

function ensure_schema(PDO $pdo): void {
  static $done = false;
  if ($done) return;
  $done = true;

  // USERS
  if (table_exists($pdo,'users')) {
    if (!column_exists($pdo,'users','phone')) {
      safe_exec($pdo,"ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER email");
    }
    if (!column_exists($pdo,'users','courier_class')) {
      // ENUM yerine VARCHAR: eski sürümlerde enum default sorun çıkarmasın
      safe_exec($pdo,"ALTER TABLE users ADD COLUMN courier_class VARCHAR(30) NULL AFTER phone");
      safe_exec($pdo,"UPDATE users SET courier_class='Hemen Kuryesi' WHERE courier_class IS NULL OR courier_class=''");
    }
    if (!column_exists($pdo,'users','start_date')) {
      safe_exec($pdo,"ALTER TABLE users ADD COLUMN start_date DATE NULL AFTER courier_class");
    }
    if (!column_exists($pdo,'users','accounting_fee')) {
      safe_exec($pdo,"ALTER TABLE users ADD COLUMN accounting_fee DECIMAL(10,2) DEFAULT 0 AFTER start_date");
    }
    if (!column_exists($pdo,'users','motor_default_type')) {
      safe_exec($pdo,"ALTER TABLE users ADD COLUMN motor_default_type VARCHAR(10) DEFAULT 'own' AFTER accounting_fee");
    }
    if (!column_exists($pdo,'users','motor_monthly_rent')) {
      safe_exec($pdo,"ALTER TABLE users ADD COLUMN motor_monthly_rent DECIMAL(10,2) DEFAULT 0 AFTER motor_default_type");
    }
    if (!column_exists($pdo,'users','motor_plate')) {
      safe_exec($pdo,"ALTER TABLE users ADD COLUMN motor_plate VARCHAR(20) NULL AFTER motor_monthly_rent");
    }
    if (!column_exists($pdo,'users','default_motor_type')) {
      safe_exec($pdo,"ALTER TABLE users ADD COLUMN default_motor_type VARCHAR(10) DEFAULT 'own' AFTER accounting_fee");
    }
    if (!column_exists($pdo,'users','default_motor_rent_monthly')) {
      safe_exec($pdo,"ALTER TABLE users ADD COLUMN default_motor_rent_monthly DECIMAL(10,2) DEFAULT 0 AFTER default_motor_type");
    }
    if (!column_exists($pdo,'users','first_login_done')) {
      safe_exec($pdo,"ALTER TABLE users ADD COLUMN first_login_done TINYINT(1) NOT NULL DEFAULT 1 AFTER default_motor_rent_monthly");
    }
  }

  // MONTHS
  if (table_exists($pdo,'months')) {
    if (!column_exists($pdo,'months','is_closed')) {
      safe_exec($pdo,"ALTER TABLE months ADD COLUMN is_closed TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!column_exists($pdo,'months','fuel_cost')) {
      safe_exec($pdo,"ALTER TABLE months ADD COLUMN fuel_cost DECIMAL(10,2) DEFAULT 0");
    }
    if (!column_exists($pdo,'months','penalty_cost')) {
      safe_exec($pdo,"ALTER TABLE months ADD COLUMN penalty_cost DECIMAL(10,2) DEFAULT 0");
    }
    if (!column_exists($pdo,'months','other_cost')) {
      safe_exec($pdo,"ALTER TABLE months ADD COLUMN other_cost DECIMAL(10,2) DEFAULT 0");
    }
    if (!column_exists($pdo,'months','tevkifat_rate')) {
      safe_exec($pdo,"ALTER TABLE months ADD COLUMN tevkifat_rate DECIMAL(5,2) DEFAULT 5.00");
    }
    if (!column_exists($pdo,'months','advance_amount')) {
      safe_exec($pdo,"ALTER TABLE months ADD COLUMN advance_amount DECIMAL(10,2) DEFAULT 0");
    }
    if (!column_exists($pdo,'months','motor_full_month')) {
      safe_exec($pdo,"ALTER TABLE months ADD COLUMN motor_full_month TINYINT(1) NOT NULL DEFAULT 1");
    }
    if (!column_exists($pdo,'months','motor_rental_days')) {
      safe_exec($pdo,"ALTER TABLE months ADD COLUMN motor_rental_days INT NOT NULL DEFAULT 0");
    }
    if (!column_exists($pdo,'months','locked_accounting_fee')) {
      safe_exec($pdo,"ALTER TABLE months ADD COLUMN locked_accounting_fee DECIMAL(10,2) NULL");
    }
    if (!column_exists($pdo,'months','locked_motor_default_type')) {
      safe_exec($pdo,"ALTER TABLE months ADD COLUMN locked_motor_default_type VARCHAR(10) NULL");
    }
    if (!column_exists($pdo,'months','locked_motor_monthly_rent')) {
      safe_exec($pdo,"ALTER TABLE months ADD COLUMN locked_motor_monthly_rent DECIMAL(10,2) NULL");
    }
  }

  // DAY ENTRIES (günlük motor kira / durum)
  if (table_exists($pdo,'day_entries')) {
    if (!column_exists($pdo,'day_entries','motor_type')) {
      safe_exec($pdo,"ALTER TABLE day_entries ADD COLUMN motor_type VARCHAR(10) NULL");
    }
    if (!column_exists($pdo,'day_entries','motor_rent_daily')) {
      safe_exec($pdo,"ALTER TABLE day_entries ADD COLUMN motor_rent_daily DECIMAL(10,2) DEFAULT 0");
    }
    if (!column_exists($pdo,'day_entries','status')) {
      safe_exec($pdo,"ALTER TABLE day_entries ADD COLUMN status VARCHAR(20) NULL");
    }
  }

  // COMPANY SETTINGS
  if (table_exists($pdo,'company_settings')) {
    if (!column_exists($pdo,'company_settings','overtime_rate')) {
      safe_exec($pdo,"ALTER TABLE company_settings ADD COLUMN overtime_rate DECIMAL(10,2) DEFAULT 0");
    }
  }

  // CONTACT MESSAGES
  if (!table_exists($pdo,'contact_messages')) {
    safe_exec($pdo, "CREATE TABLE contact_messages (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      category VARCHAR(20) NOT NULL,
      subject VARCHAR(150) NOT NULL,
      message TEXT NOT NULL,
      status TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } else {
    if (!column_exists($pdo,'contact_messages','subject')) {
      safe_exec($pdo,"ALTER TABLE contact_messages ADD COLUMN subject VARCHAR(150) NOT NULL DEFAULT '' AFTER category");
    }
    if (!column_exists($pdo,'contact_messages','status')) {
      safe_exec($pdo,"ALTER TABLE contact_messages ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1");
    }
  }

  // CONTACT REPLIES
  if (!table_exists($pdo,'contact_replies')) {
    safe_exec($pdo, "CREATE TABLE contact_replies (
      id INT AUTO_INCREMENT PRIMARY KEY,
      contact_id INT NOT NULL,
      sender_type VARCHAR(10) NOT NULL,
      sender_user_id INT NULL,
      message TEXT NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_contact (contact_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
}
