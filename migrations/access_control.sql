-- =============================================================================
-- migrations/access_control.sql
-- Access Control: users, roles, user_roles, pages, role_permissions
-- Run once against your HPTS database.
-- WARNING: Default admin password is 'admin123' — change on first deploy.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Table: users
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(64)      NOT NULL,
    `email`         VARCHAR(255)     NOT NULL DEFAULT '',
    `password_hash` VARCHAR(255)     NOT NULL,
    `is_active`     TINYINT(1)       NOT NULL DEFAULT 1,
    `is_admin`      TINYINT(1)       NOT NULL DEFAULT 0,
    `last_login`    DATETIME                  DEFAULT NULL,
    `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: roles
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(64)   NOT NULL,
    `description` VARCHAR(255)           DEFAULT NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: user_roles
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_roles` (
    `user_id` INT UNSIGNED NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`user_id`, `role_id`),
    CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: pages
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pages` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(128)  NOT NULL,
    `path`        VARCHAR(255)  NOT NULL,
    `description` VARCHAR(255)           DEFAULT NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pages_path` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: role_permissions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `page_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `page_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rp_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Seed: Admin role (superuser — not deletable/renameable)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `roles` (`name`, `description`)
VALUES ('Admin', 'Superuser — full access to all pages');

-- -----------------------------------------------------------------------------
-- Seed: Default admin user  (password: admin123 — CHANGE ON FIRST DEPLOY)
-- password_hash = password_hash('admin123', PASSWORD_BCRYPT)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `users` (`username`, `email`, `password_hash`, `is_active`, `is_admin`)
VALUES (
    'admin',
    '',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    1,
    1
);

-- Assign Admin role to default admin user
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`)
SELECT u.id, r.id
FROM `users` u, `roles` r
WHERE u.username = 'admin' AND r.name = 'Admin';

-- -----------------------------------------------------------------------------
-- Seed: Application pages (14 pages matching the Access Control module)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `pages` (`name`, `path`, `description`) VALUES
('Dashboard',               '/index.php',                                    'Main dashboard'),
('Log Booking',             '/modules/Bookings/add.php',                     'Add a new booking'),
('Booking Reports',         '/modules/Bookings/',                            'View booking history'),
('Log Fuel',                '/modules/Fuel/add.php',                         'Add a fuel fill-up'),
('Fuel Reports',            '/modules/Fuel/',                                'View fuel logs'),
('Log Uber Income',         '/modules/Uber/add.php',                         'Add Uber income entry'),
('Uber Reports',            '/modules/Uber/',                                'View Uber income history'),
('Financial Summary',       '/modules/Financials/',                          'Monthly financial overview'),
('Balance Sheet',           '/modules/Financials/balance_sheet.php',         'Monthly balance sheet report'),
('Contacts',                '/modules/Contacts/',                            'Manage contacts'),
('Maintenance',             '/modules/Maintenance/',                         'System settings and variables'),
('Access Control',          '/modules/AccessControl/',                       'Manage users and roles'),
('Access Control – Users',  '/modules/AccessControl/users/',                 'User management'),
('Access Control – Roles',  '/modules/AccessControl/roles/',                 'Role management');