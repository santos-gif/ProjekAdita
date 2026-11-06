-- MariaDB 11.4 (also valid on MySQL 8). Compose mounts this file into the db container;
-- it is imported only on the very first start, while the data volume is empty.
-- To apply a changed schema: `down -v` (deletes the database), then `up --build`.
--
-- Project workflow (projects.status):
--   pending_moderation --moderator approves--> pending_admin --admin publishes--> published
--   rejected is final and can be reached from either gate (project_reviews keeps who and why).

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'moderator', 'admin') NOT NULL DEFAULT 'user',
    -- Filled in later by the onboarding form, so all NULL at registration.
    identity_number VARCHAR(30) NULL,  -- student ID (NIM) or national lecturer ID (NIDN)
    study_program VARCHAR(120) NULL,   -- major; the form offers a "Dosen/Staff" option
    cohort_year YEAR NULL,
    phone VARCHAR(20) NULL,
    bio TEXT NULL,
    avatar_url VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_identity_number (identity_number)  -- several NULLs are allowed
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS projects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    owner_id BIGINT UNSIGNED NOT NULL,
    project_name VARCHAR(120) NOT NULL,
    thumbnail_url VARCHAR(255) NULL,
    description LONGTEXT NOT NULL,
    requirements TEXT NULL,
    status ENUM('pending_moderation', 'pending_admin', 'published', 'rejected')
        NOT NULL DEFAULT 'pending_moderation',
    selection_method ENUM('Kurasi Portofolio & CV', 'Wawancara Langsung', 'Tes Teknis', 'First Come First Served') 
        NOT NULL DEFAULT 'Kurasi Portofolio & CV'
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at TIMESTAMP NULL,  -- set when the admin publishes
    KEY idx_projects_status_published (status, published_at),
    KEY idx_projects_owner (owner_id),
    CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tags (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    UNIQUE KEY uq_tags_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_tags (
    project_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (project_id, tag_id),
    KEY idx_project_tags_tag (tag_id),
    CONSTRAINT fk_ptags_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_ptags_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- A request to join a project; one per user per project.
CREATE TABLE IF NOT EXISTS project_applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    desired_title VARCHAR(60) NOT NULL,
    message TEXT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at TIMESTAMP NULL,
    UNIQUE KEY uq_application_once (project_id, user_id),
    KEY idx_applications_user (user_id),
    CONSTRAINT fk_app_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Everyone on a project, the owner included (the owner's row is created together with the project).
CREATE TABLE IF NOT EXISTS project_members (
    project_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('member', 'manager', 'owner') NOT NULL DEFAULT 'member',
    title VARCHAR(60) NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, user_id),
    KEY idx_members_user (user_id),
    CONSTRAINT fk_member_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- One row per moderator or admin decision; a rejection must carry a reason.
CREATE TABLE IF NOT EXISTS project_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    reviewer_id BIGINT UNSIGNED NOT NULL,
    stage ENUM('moderation', 'admin') NOT NULL,
    is_approved BOOLEAN NOT NULL,
    reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reviews_project (project_id),
    CONSTRAINT chk_reject_needs_reason CHECK (is_approved OR reason IS NOT NULL),
    CONSTRAINT fk_review_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_review_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- In-app notifications (no email).
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(40) NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    message VARCHAR(255) NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user (user_id, read_at),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Attempt counters per visitor, for any kind of action: scope names the action ('login', 'reset', ...).
-- One row per (scope, ip, email). exp is a unix timestamp: when the current window (or lockout) ends; the limits
-- themselves live in code. Expired rows are deleted by the code. A successful login deletes its row.
CREATE TABLE IF NOT EXISTS rate_limits (
    scope ENUM("login", "reset") NOT NULL,
    ip_address VARBINARY(16) NOT NULL,     -- INET6_ATON(ip): 4 bytes for IPv4, 16 for IPv6; read back with INET6_NTOA
    email VARCHAR(190) NOT NULL DEFAULT '',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    exp INT UNSIGNED NOT NULL,
    PRIMARY KEY (scope, ip_address, email),
    KEY idx_rate_limits_exp (exp)
) ENGINE=InnoDB;

-- PHP sessions (Core\DbSessionHandler). The cookie holds only the id. exp is a unix timestamp;
-- expired rows are removed by PHP's own cleanup. user_id is NULL until someone logs in.
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    exp INT UNSIGNED NOT NULL,
    csrf CHAR(64) NULL,
    KEY idx_sessions_user (user_id),
    KEY idx_sessions_exp (exp),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- The emailed password-reset code: one row per user, the 6-digit code stored hashed.
-- Valid for 10 minutes and 5 wrong tries (both enforced in PasswordResetRepository), then useless.
CREATE TABLE IF NOT EXISTS password_resets (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    code_hash VARCHAR(255) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;
