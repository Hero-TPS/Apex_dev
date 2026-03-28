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
    `module`      VARCHAR(64)            DEFAULT NULL,
    `operation`   VARCHAR(16)            DEFAULT NULL COMMENT 'view | create | edit | delete',
    `description` VARCHAR(255)           DEFAULT NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pages_path` (`path`),
    KEY `idx_pages_module_op` (`module`, `operation`)
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
-- Seed: Application pages
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `pages` (`name`, `path`, `module`, `operation`, `description`) VALUES
-- Bookings
('Log Booking',            '/modules/Bookings/add.php',               'bookings',       'create', 'Add a new booking'),
('View Bookings',          '/modules/Bookings/',                      'bookings',       'view',   'View booking history'),
('Edit Booking',           '/modules/Bookings/edit.php',              'bookings',       'edit',   'Edit an existing booking'),
-- Fuel
('Log Fuel',               '/modules/Fuel/add.php',                   'fuel',           'create', 'Add a fuel fill-up'),
('Fuel Reports',           '/modules/Fuel/',                          'fuel',           'view',   'View fuel logs'),
('Edit Fuel Log',          '/modules/Fuel/edit.php',                  'fuel',           'edit',   'Edit a fuel entry'),
-- Uber
('Log Uber Income',        '/modules/Uber/add.php',                   'uber',           'create', 'Add Uber income entry'),
('Uber Reports',           '/modules/Uber/',                          'uber',           'view',   'View Uber income history'),
('Edit Uber Entry',        '/modules/Uber/edit.php',                  'uber',           'edit',   'Edit an Uber entry'),
-- Financials
('Financial Summary',      '/modules/Financials/',                    'financials',     'view',   'Monthly financial overview'),
('Balance Sheet',          '/modules/Financials/balance_sheet.php',   'financials',     'view',   'Monthly balance sheet report'),
-- Clients
('View Clients',           '/modules/Clients/',                       'clients',        'view',   'View and search clients'),
('Add Client',             '/modules/Clients/add.php',                'clients',        'create', 'Add a new client'),
('Edit Client',            '/modules/Clients/edit.php',               'clients',        'edit',   'Edit an existing client'),
-- Maintenance
('Maintenance',            '/maintenance/',                           'maintenance',    'view',   'System settings and variables'),
-- Access Control
('Access Control',         '/modules/AccessControl/',                 'access_control', 'view',   'Manage users and roles'),
('Access Control - Users', '/modules/AccessControl/users/',           'access_control', 'view',   'User management'),
('Access Control - Roles', '/modules/AccessControl/roles/',           'access_control', 'view',   'Role management');

-- Fix legacy path entries if they exist from a previous migration run
UPDATE `pages` SET `path` = '/modules/Clients/', `name` = 'View Clients', `description` = 'View and search clients',
                   `module` = 'clients', `operation` = 'view'
WHERE `path` = '/modules/Contacts/';

UPDATE `pages` SET `path` = '/maintenance/', `module` = 'maintenance', `operation` = 'view'
WHERE `path` = '/modules/Maintenance/';

-- Remove the old public home-page entry if present (not a permission-controlled page)
DELETE FROM `pages` WHERE `path` = '/index.php';

-- -----------------------------------------------------------------------------
-- Seed: Driver role — can log & view their own bookings, fuel, and Uber income
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `roles` (`name`, `description`)
VALUES ('Driver', 'Field driver — log bookings, fuel and Uber income only');

INSERT IGNORE INTO `role_permissions` (`role_id`, `page_id`)
SELECT r.id, p.id
FROM `roles` r
JOIN `pages`  p ON p.path IN (
    '/modules/Bookings/add.php',
    '/modules/Bookings/',
    '/modules/Fuel/add.php',
    '/modules/Fuel/',
    '/modules/Uber/add.php',
    '/modules/Uber/'
)
WHERE r.name = 'Driver';