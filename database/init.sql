CREATE DATABASE IF NOT EXISTS opiskelijahelpdesk
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE opiskelijahelpdesk;


-- =========================================
-- USERS
-- =========================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    email VARCHAR(255) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    role ENUM(
        'student',
        'support',
        'admin'
    ) NOT NULL DEFAULT 'student',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_users_role (role)
);


-- =========================================
-- CATEGORIES
-- =========================================

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL UNIQUE
);


-- =========================================
-- TICKETS
-- =========================================

CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    category_id INT NOT NULL,

    assigned_to INT NULL,

    title VARCHAR(255) NOT NULL,

    description TEXT NOT NULL,

    priority ENUM(
        'low',
        'normal',
        'high',
        'urgent'
    ) NOT NULL DEFAULT 'normal',

    status ENUM(
        'new',
        'in_progress',
        'waiting_student',
        'resolved',
        'closed'
    ) NOT NULL DEFAULT 'new',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


    -- Opiskelija joka loi tiketin
    CONSTRAINT fk_tickets_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,


    -- Tiketin kategoria
    CONSTRAINT fk_tickets_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON DELETE RESTRICT,


    -- Tukihenkilö joka käsittelee tikettiä
    CONSTRAINT fk_tickets_assigned_to
        FOREIGN KEY (assigned_to)
        REFERENCES users(id)
        ON DELETE SET NULL,


    -- Indeksit hakua ja dashboardia varten
    INDEX idx_tickets_user_id (user_id),

    INDEX idx_tickets_category_id (category_id),

    INDEX idx_tickets_assigned_to (assigned_to),

    INDEX idx_tickets_status (status),

    INDEX idx_tickets_priority (priority),

    INDEX idx_tickets_created_at (created_at)
);


-- =========================================
-- COMMENTS
-- =========================================

CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,

    ticket_id INT NOT NULL,

    user_id INT NOT NULL,

    comment TEXT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


    -- Kommenttiin liittyvä tiketti
    CONSTRAINT fk_comments_ticket
        FOREIGN KEY (ticket_id)
        REFERENCES tickets(id)
        ON DELETE CASCADE,


    -- Kommentin kirjoittanut käyttäjä
    CONSTRAINT fk_comments_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,


    -- Kommenttien hakeminen tiketin perusteella
    INDEX idx_comments_ticket_id (ticket_id),

    INDEX idx_comments_user_id (user_id),

    INDEX idx_comments_created_at (created_at)
);


-- =========================================
-- DEFAULT CATEGORIES
-- =========================================

INSERT IGNORE INTO categories (name)
VALUES
    ('Laitteisto'),
    ('Ohjelmisto'),
    ('Verkko'),
    ('Käyttäjätunnus'),
    ('Muu');


-- =========================================
-- TEST USERS
-- =========================================

INSERT IGNORE INTO users (name, email, password, role)
VALUES
    ('Testi opiskelija', 'testi@gmail.com', '$2y$12$fq1HP2SfddKIuxsCfqTQeeqxqg8jFHJzHcRDHtN7GuOnczrSP47Eq', 'student'),
    ('Support käyttäjä', 'support@gmail.com', '$2y$12$nGA26bXQtxJIC7hWE47Xa.OAVmSMcHKeBm2XE9xEYaz8v2aTGcXaS', 'support'),
    ('Admin käyttäjä', 'admin@gmail.com', '$2y$12$PiE2ze0Gw/DZsBgqAdc7fe5cidzSHeZjSlmaVNZMsaf/wwyC.pvGe', 'admin');
