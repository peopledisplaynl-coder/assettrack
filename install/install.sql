-- AssetTrack Database Schema v3.0
-- Compatible met MariaDB/MySQL op Strato shared hosting
-- Geen triggers, geen DELIMITER, geen CREATE DATABASE, geen ON UPDATE

CREATE TABLE IF NOT EXISTS organisations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    zip VARCHAR(20),
    phone VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(255),
    logo_path VARCHAR(500),
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    zip VARCHAR(20),
    phone VARCHAR(50),
    email VARCHAR(255),
    logo_path VARCHAR(500),
    theme_color VARCHAR(7) NULL,
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255),
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin','admin','user','visitor') DEFAULT 'user',
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL
);

CREATE TABLE IF NOT EXISTS user_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    location_id INT NOT NULL,
    can_view TINYINT(1) DEFAULT 1,
    can_edit TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_location (user_id, location_id)
);

CREATE TABLE IF NOT EXISTS asset_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    active TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    active TINYINT(1) DEFAULT 1,
    use_count INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    location_desc VARCHAR(255),
    active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_number VARCHAR(50) NOT NULL UNIQUE,
    location_id INT NULL,
    room VARCHAR(100) NULL,
    brand VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    type VARCHAR(100) NULL,
    serial_number VARCHAR(100) NULL,
    status ENUM('In gebruik','Beschikbaar','In reparatie','Buiten gebruik','Afgevoerd') DEFAULT 'Beschikbaar',
    assigned_to VARCHAR(255) NULL,
    installed_date DATE NULL,
    purchase_date DATE NULL,
    warranty_end_date DATE NULL,
    depreciation_years INT NULL,
    replacement_due_date DATE NULL,
    autoupdate_expiry DATE NULL,
    advised_replacement_date DATE NULL,
    mac_address VARCHAR(17) NULL,
    lan_ip_address VARCHAR(45) NULL,
    management_ip VARCHAR(45) NULL,
    most_recent_user VARCHAR(255) NULL,
    notes TEXT NULL,
    touchscreen_monitor_type VARCHAR(100) NULL,
    monitor_count INT NULL,
    monitor_serial VARCHAR(100) NULL,
    in_repair_since DATE NULL,
    out_of_service_since DATE NULL,
    ram VARCHAR(50) NULL,
    cpu VARCHAR(100) NULL,
    operating_system VARCHAR(100) NULL,
    business_critical TINYINT(1) DEFAULT 0,
    phone_number VARCHAR(50) NULL,
    access_point_number VARCHAR(50) NULL,
    manufacturer_url VARCHAR(500) NULL,
    registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS asset_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    sort_order INT DEFAULT 0,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS asset_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(10) NULL,
    asset_type VARCHAR(100) NULL,
    brand VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    depreciation_years INT NULL,
    warranty_months INT NULL,
    manufacturer_url VARCHAR(500) NULL,
    operating_system VARCHAR(100) NULL,
    ram VARCHAR(50) NULL,
    cpu VARCHAR(100) NULL,
    business_critical TINYINT(1) DEFAULT 0,
    touchscreen_monitor_type VARCHAR(100) NULL,
    monitor_count INT NULL,
    maintenance_interval_days INT NULL,
    notes TEXT NULL,
    image_filename VARCHAR(255) NULL,
    active TINYINT(1) DEFAULT 1,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS custom_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_name VARCHAR(100) NOT NULL UNIQUE,
    field_label VARCHAR(255) NOT NULL,
    field_type ENUM('text','number','date','select','boolean','textarea','ip','mac') DEFAULT 'text',
    field_options TEXT NULL,
    required TINYINT(1) DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS custom_field_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    field_id INT NOT NULL,
    value TEXT NULL,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES custom_fields(id) ON DELETE CASCADE,
    UNIQUE KEY unique_asset_field (asset_id, field_id)
);

CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('superadmin','admin','user','visitor') NOT NULL,
    permission_key VARCHAR(50) NOT NULL,
    enabled TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_role_permission (role, permission_key)
);

CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(20) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    old_values TEXT NULL,
    new_values TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS asset_relations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    related_id INT NOT NULL,
    relation_type VARCHAR(50) DEFAULT 'peripheral',
    notes VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (related_id) REFERENCES assets(id) ON DELETE CASCADE,
    UNIQUE KEY unique_relation (asset_id, related_id)
);

CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT NULL,
    zip VARCHAR(10) NULL,
    city VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(100) NULL,
    website VARCHAR(255) NULL,
    app_name VARCHAR(100) DEFAULT 'AssetTrack',
    app_logo VARCHAR(500) NULL,
    theme_primary VARCHAR(7) DEFAULT '#2563eb',
    theme_secondary VARCHAR(7) DEFAULT '#1a2332',
    theme_accent VARCHAR(7) DEFAULT '#3b82f6',
    font_family VARCHAR(100) DEFAULT 'DM Sans',
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS kb_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    icon VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
    description TEXT NULL,
    sort_order INT DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS kb_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    asset_type VARCHAR(100) NULL,
    brand VARCHAR(100) NULL,
    external_url VARCHAR(500) NULL,
    tags VARCHAR(500) NULL,
    active TINYINT(1) DEFAULT 1,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES kb_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS kb_article_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    asset_id INT NULL,
    FOREIGN KEY (article_id) REFERENCES kb_articles(id) ON DELETE CASCADE,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE
);

-- ─── Standaard permissies ────────────────────────────────────────────────────

INSERT IGNORE INTO role_permissions (role, permission_key, enabled) VALUES
('superadmin','view_assets',1),
('superadmin','add_assets',1),
('superadmin','edit_assets',1),
('superadmin','delete_assets',1),
('superadmin','view_reports',1),
('superadmin','export_data',1),
('superadmin','manage_users',1),
('superadmin','manage_settings',1),
('superadmin','print_labels',1),
('superadmin','import_assets',1),
('superadmin','scan_assets',1),
('admin','view_assets',1),
('admin','add_assets',1),
('admin','edit_assets',1),
('admin','delete_assets',1),
('admin','view_reports',1),
('admin','export_data',1),
('admin','manage_users',1),
('admin','manage_settings',0),
('admin','print_labels',1),
('admin','import_assets',1),
('admin','scan_assets',1),
('user','view_assets',1),
('user','add_assets',1),
('user','edit_assets',1),
('user','delete_assets',0),
('user','view_reports',0),
('user','export_data',0),
('user','manage_users',0),
('user','manage_settings',0),
('user','print_labels',1),
('user','import_assets',0),
('user','scan_assets',1),
('visitor','view_assets',1),
('visitor','add_assets',0),
('visitor','edit_assets',0),
('visitor','delete_assets',0),
('visitor','view_reports',0),
('visitor','export_data',0),
('visitor','manage_users',0),
('visitor','manage_settings',0),
('visitor','print_labels',0),
('visitor','import_assets',0),
('visitor','scan_assets',0),
('visitor','view_kb',1),
('visitor','manage_kb',0),
('visitor','manage_appearance',0),
-- superadmin KB + appearance
('superadmin','view_kb',1),
('superadmin','manage_kb',1),
('superadmin','manage_appearance',1),
-- admin KB + appearance
('admin','view_kb',1),
('admin','manage_kb',1),
('admin','manage_appearance',0),
-- user KB
('user','view_kb',1),
('user','manage_kb',0),
('user','manage_appearance',0);

-- ─── Standaard asset types ───────────────────────────────────────────────────

INSERT IGNORE INTO asset_types (name) VALUES
('Chromebook'),
('Desktop'),
('Laptop'),
('Monitor'),
('Printer'),
('Tablet'),
('Server'),
('Telefoon'),
('Netwerkapparatuur'),
('All-in-One'),
('Overig');

-- ─── Standaard merken ────────────────────────────────────────────────────────

INSERT IGNORE INTO brands (name) VALUES
('Acer'),('Apple'),('Asus'),('Brother'),('Canon'),
('Cisco'),('Dell'),('Epson'),('Google'),('HP'),
('Lenovo'),('LG'),('Microsoft'),('Philips'),
('Prowse'),('Samsung'),('TP-Link'),('Ubiquiti'),('Overig');

-- ─── Standaard organisatie ───────────────────────────────────────────────────
-- Naam wordt overschreven door installer via: UPDATE organisations SET name = ? WHERE id = 1

INSERT IGNORE INTO organisations (id, name, active) VALUES (1, 'Mijn Organisatie', 1);

-- ─── Standaard locatie ───────────────────────────────────────────────────────

INSERT IGNORE INTO locations (id, organisation_id, name, active) VALUES (1, 1, 'Hoofdlocatie', 1);

-- ─── Standaard company (voor labels) ────────────────────────────────────────
-- Naam wordt overschreven door installer

INSERT IGNORE INTO companies (id, name, active) VALUES (1, 'Mijn Organisatie', 1);

-- ─── Standaard ruimtes ───────────────────────────────────────────────────────

INSERT IGNORE INTO rooms (location_id, name, location_desc) VALUES
(1, 'Algemeen', NULL),
(1, 'Kantoor', 'Begane grond'),
(1, 'Serverruimte', 'Kelder'),
(1, 'Klaslokaal A', '1e verdieping'),
(1, 'Klaslokaal B', '1e verdieping');

