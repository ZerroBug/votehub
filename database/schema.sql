CREATE DATABASE IF NOT EXISTS votehub_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE votehub_db;

CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('Super_Admin','Event_Manager','Finance_Officer','Results_Officer','Auditor') NOT NULL DEFAULT 'Event_Manager',
 status ENUM('Active','Inactive','Suspended') NOT NULL DEFAULT 'Active',
 last_login_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(200) NOT NULL,
 event_code VARCHAR(50) NOT NULL UNIQUE,
 description TEXT NULL,
 logo VARCHAR(255) NULL,
 start_date DATETIME NOT NULL,
 end_date DATETIME NOT NULL,
 status ENUM('Draft','Scheduled','Active','Paused','Closed','Archived') NOT NULL DEFAULT 'Draft',
 default_vote_price DECIMAL(12,2) NOT NULL DEFAULT 1.00,
 ussd_code VARCHAR(30) NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_events_user FOREIGN KEY(created_by) REFERENCES users(id),
 INDEX(status)
) ENGINE=InnoDB;

CREATE TABLE categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(200) NOT NULL,
 category_code VARCHAR(50) NOT NULL,
 description TEXT NULL,
 vote_price DECIMAL(12,2) NOT NULL DEFAULT 1.00,
 max_votes_per_transaction INT UNSIGNED NOT NULL DEFAULT 20,
 max_votes_per_phone INT UNSIGNED NULL,
 display_order INT UNSIGNED NOT NULL DEFAULT 0,
 status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
 UNIQUE KEY uq_category_event(event_id,category_code)
) ENGINE=InnoDB;

CREATE TABLE contestants (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_id BIGINT UNSIGNED NOT NULL,
 category_id BIGINT UNSIGNED NOT NULL,
 contestant_code VARCHAR(50) NOT NULL,
 full_name VARCHAR(200) NOT NULL,
 photo VARCHAR(255) NULL,
 gender ENUM('Male','Female','Other') NULL,
 biography TEXT NULL,
 status ENUM('Active','Inactive','Disqualified') NOT NULL DEFAULT 'Active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
 FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE,
 UNIQUE KEY uq_contestant_event(event_id,contestant_code)
) ENGINE=InnoDB;

CREATE TABLE transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 transaction_reference VARCHAR(100) NOT NULL UNIQUE,
 event_id BIGINT UNSIGNED NOT NULL,
 category_id BIGINT UNSIGNED NOT NULL,
 contestant_id BIGINT UNSIGNED NOT NULL,
 phone_number VARCHAR(30) NOT NULL,
 vote_count INT UNSIGNED NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 payment_provider VARCHAR(80) NULL,
 payment_reference VARCHAR(150) NULL,
 status ENUM('Pending','Successful','Failed','Cancelled','Refunded') NOT NULL DEFAULT 'Pending',
 metadata JSON NULL,
 paid_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(event_id) REFERENCES events(id),
 FOREIGN KEY(category_id) REFERENCES categories(id),
 FOREIGN KEY(contestant_id) REFERENCES contestants(id),
 INDEX(status),INDEX(event_id),INDEX(phone_number)
) ENGINE=InnoDB;

CREATE TABLE votes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 transaction_id BIGINT UNSIGNED NOT NULL,
 event_id BIGINT UNSIGNED NOT NULL,
 category_id BIGINT UNSIGNED NOT NULL,
 contestant_id BIGINT UNSIGNED NOT NULL,
 phone_number VARCHAR(30) NOT NULL,
 vote_count INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(transaction_id) REFERENCES transactions(id),
 FOREIGN KEY(event_id) REFERENCES events(id),
 FOREIGN KEY(category_id) REFERENCES categories(id),
 FOREIGN KEY(contestant_id) REFERENCES contestants(id),
 INDEX(event_id,category_id),INDEX(contestant_id),INDEX(created_at),UNIQUE KEY uq_votes_transaction(transaction_id)
) ENGINE=InnoDB;

CREATE TABLE ussd_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 session_id VARCHAR(150) NOT NULL UNIQUE,
 event_id BIGINT UNSIGNED NULL,
 phone_number VARCHAR(30) NULL,
 network VARCHAR(40) NULL,
 current_step VARCHAR(80) NULL,
 selected_category_id BIGINT UNSIGNED NULL,
 selected_contestant_id BIGINT UNSIGNED NULL,
 selected_vote_count INT UNSIGNED NULL,
 state_data JSON NULL,
 status ENUM('Active','Completed','Expired','Cancelled') NOT NULL DEFAULT 'Active',
 started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 ended_at DATETIME NULL,
 last_activity_at DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 action VARCHAR(100) NOT NULL,
 entity_type VARCHAR(80) NULL,
 entity_id BIGINT UNSIGNED NULL,
 description TEXT NULL,
 ip_address VARCHAR(45) NULL,
 user_agent VARCHAR(500) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS webhook_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(190) NOT NULL UNIQUE,
 event_name VARCHAR(100) NOT NULL,
 payload JSON NULL,
 processed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(event_name), INDEX(processed_at)
) ENGINE=InnoDB;
