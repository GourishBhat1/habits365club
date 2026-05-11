-- Sales Module Database Schema
-- Run this on the habits365 database

CREATE TABLE IF NOT EXISTS leads (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(255) NOT NULL,
    phone           VARCHAR(20) NOT NULL,
    email           VARCHAR(255) DEFAULT '',
    child_name      VARCHAR(255) DEFAULT '',
    child_age       VARCHAR(50) DEFAULT '',
    standard        VARCHAR(50) DEFAULT '',
    school_name     VARCHAR(255) DEFAULT '',
    location        VARCHAR(100) DEFAULT '',
    course_interest VARCHAR(255) DEFAULT '',
    lead_source     VARCHAR(50) NOT NULL DEFAULT 'manual',
    lead_source_id  VARCHAR(100) DEFAULT '',
    assigned_to     INT DEFAULT NULL,
    status          VARCHAR(50) DEFAULT 'new',
    notes           TEXT DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lead_followups (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    lead_id      INT NOT NULL,
    sales_id     INT NOT NULL,
    type         VARCHAR(50) DEFAULT 'call',
    notes        TEXT DEFAULT NULL,
    status       VARCHAR(50) DEFAULT 'pending',
    due_date     DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (sales_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
