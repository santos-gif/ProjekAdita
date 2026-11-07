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
    thumbnail_url VARCHAR(255) NULL,  -- '/uploads/<random>.<ext>': the file sits in the shared uploads volume, Apache serves it
    description LONGTEXT NOT NULL,
    requirements TEXT NULL,
    -- draft: the owner's saved-but-not-submitted form (one per owner, never listed); the rest is the review flow.
    status ENUM('draft', 'pending_moderation', 'pending_admin', 'published', 'rejected')
        NOT NULL DEFAULT 'pending_moderation',
    selection_method ENUM('Kurasi Portofolio & CV', 'Wawancara Langsung', 'Tes Teknis', 'First Come First Served')
        NOT NULL DEFAULT 'Kurasi Portofolio & CV',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at TIMESTAMP NULL,  -- set when the admin publishes
    accepting_applications BOOLEAN NOT NULL DEFAULT 1,  -- the owner closes this to stop NEW applications
    KEY idx_projects_status_published (status, published_at),
    KEY idx_projects_owner (owner_id),
    CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tags (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT '',  -- study programme the tag belongs to; the official list is seeded at the end of this file
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

-- A request to join a project; one row per user per project, reused when the user applies again.
-- status: pending; accepted (the applicant became a member); withdrawn (the applicant withdrew, or an owner/manager
-- declined: decided_by tells which; the user MAY apply again); rejected = BANNED from this project (cannot apply again).
CREATE TABLE IF NOT EXISTS project_applications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    desired_title VARCHAR(60) NOT NULL,
    message TEXT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at TIMESTAMP NULL,
    decided_by BIGINT UNSIGNED NULL,  -- the owner/manager who accepted, declined or banned; NULL while pending or when the applicant withdrew
    UNIQUE KEY uq_application_once (project_id, user_id),
    KEY idx_applications_user (user_id),
    CONSTRAINT fk_app_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_app_decider FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL
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

-- A manager asks the OWNER to make a plain member a manager (the owner may also do it directly).
CREATE TABLE IF NOT EXISTS project_promotion_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,       -- the member who would become a manager
    requested_by BIGINT UNSIGNED NOT NULL,    -- the manager who asks
    status ENUM('pending', 'approved', 'denied') NOT NULL DEFAULT 'pending',
    decided_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at TIMESTAMP NULL,
    KEY idx_promotions_project (project_id, status),
    CONSTRAINT fk_promo_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_promo_member FOREIGN KEY (member_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_promo_requester FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_promo_decider FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- A user reports a project. The moderators see it first: they dismiss it or forward it to the admins; an admin
-- dismisses it or takes the project down (every report of that project then becomes 'actioned').
CREATE TABLE IF NOT EXISTS project_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    reporter_id BIGINT UNSIGNED NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('open', 'forwarded', 'dismissed', 'actioned') NOT NULL DEFAULT 'open',
    handled_by BIGINT UNSIGNED NULL,
    note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    handled_at TIMESTAMP NULL,
    KEY idx_reports_status (status, created_at),
    KEY idx_reports_project (project_id, reporter_id),
    CONSTRAINT fk_report_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_report_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_report_handler FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
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

-- The official tag list (Universitas Pradita study programmes); a project may only use these.
-- Generated from the old frontend/features/projects/tags.data.js: unique by name, first category wins.
INSERT IGNORE INTO tags (name, category) VALUES
    ('Digital Business', 'Management'),
    ('Township Business', 'Management'),
    ('Creativepreneurship', 'Management'),
    ('Digital Marketing', 'Management'),
    ('Brand Management', 'Management'),
    ('Business Strategy', 'Management'),
    ('Entrepreneurship', 'Management'),
    ('Business Analytics', 'Management'),
    ('Content Creation', 'Management'),
    ('Investment Analysis', 'Management'),
    ('Real Estate Development', 'Management'),
    ('Financial Management', 'Management'),
    ('Artificial Intelligence', 'Information Technology'),
    ('Cyber Security', 'Information Technology'),
    ('Big Data & IoT Apps Development', 'Information Technology'),
    ('Machine Learning', 'Information Technology'),
    ('Deep Learning', 'Information Technology'),
    ('Data Science', 'Information Technology'),
    ('Data Engineering', 'Information Technology'),
    ('Natural Language Processing', 'Information Technology'),
    ('Computer Vision', 'Information Technology'),
    ('Digital Forensics', 'Information Technology'),
    ('Penetration Testing', 'Information Technology'),
    ('Network Security', 'Information Technology'),
    ('Software Engineering', 'Information Technology'),
    ('Web Development', 'Information Technology'),
    ('Mobile App Development', 'Information Technology'),
    ('UI/UX Design', 'Information Technology'),
    ('Human-Computer Interaction', 'Information Technology'),
    ('Internet of Things (IoT)', 'Information Technology'),
    ('Embedded Systems', 'Information Technology'),
    ('Culinary Arts', 'Culinary Arts'),
    ('Patisserie Arts', 'Culinary Arts'),
    ('Indonesian Cuisine', 'Culinary Arts'),
    ('Asian Cuisine', 'Culinary Arts'),
    ('Western Cuisine', 'Culinary Arts'),
    ('Food Innovation', 'Culinary Arts'),
    ('Food Styling', 'Culinary Arts'),
    ('Food Presentation', 'Culinary Arts'),
    ('Pastry & Bakery', 'Culinary Arts'),
    ('Kitchen Operations', 'Culinary Arts'),
    ('Food Entrepreneurship', 'Culinary Arts'),
    ('Recipe Development', 'Culinary Arts'),
    ('Auditing', 'Accounting'),
    ('Taxation', 'Accounting'),
    ('Digital Accounting', 'Accounting'),
    ('Financial Accounting', 'Accounting'),
    ('Financial Reporting', 'Accounting'),
    ('Financial Analysis', 'Accounting'),
    ('Internal Controls', 'Accounting'),
    ('Risk Management', 'Accounting'),
    ('Accounting Information Systems', 'Accounting'),
    ('Enterprise Resource Planning (ERP)', 'Accounting'),
    ('Business Process Auditing', 'Accounting'),
    ('Tax Compliance', 'Accounting'),
    ('Visual Creative', 'Visual Communication Design'),
    ('Visual Interactive', 'Visual Communication Design'),
    ('Graphic Design', 'Visual Communication Design'),
    ('Visual Storytelling', 'Visual Communication Design'),
    ('Branding & Visual Identity', 'Visual Communication Design'),
    ('Advertising & Campaign Design', 'Visual Communication Design'),
    ('Digital Illustration', 'Visual Communication Design'),
    ('Video Production', 'Visual Communication Design'),
    ('Animation & Motion Graphics', 'Visual Communication Design'),
    ('3D Animation', 'Visual Communication Design'),
    ('Game Design', 'Visual Communication Design'),
    ('Character Design', 'Visual Communication Design'),
    ('Packaging Design', 'Visual Communication Design'),
    ('Digital Media', 'Visual Communication Design'),
    ('Visual Merchandising', 'Visual Communication Design'),
    ('Photography', 'Visual Communication Design'),
    ('Property Development', 'Architecture'),
    ('Technology Innovation', 'Architecture'),
    ('Architectural Design', 'Architecture'),
    ('Computational Design', 'Architecture'),
    ('Sustainable Architecture', 'Architecture'),
    ('Universal Design', 'Architecture'),
    ('Contemporary Architecture', 'Architecture'),
    ('Site Planning', 'Architecture'),
    ('Building Technology', 'Architecture'),
    ('Smart Building Systems', 'Architecture'),
    ('Construction Management', 'Architecture'),
    ('Building Economics', 'Architecture'),
    ('Architectural Visualization', 'Architecture'),
    ('Building Information Modeling (BIM)', 'Architecture'),
    ('Information System Governance', 'Business Information System'),
    ('Enterprise System', 'Business Information System'),
    ('Business Intelligence', 'Business Information System'),
    ('Data Analytics & Visualization', 'Business Information System'),
    ('Systems Analysis', 'Business Information System'),
    ('Information System Development', 'Business Information System'),
    ('IT Audit', 'Business Information System'),
    ('ERP Implementation', 'Business Information System'),
    ('Business Process Management', 'Business Information System'),
    ('Decision Support Systems', 'Business Information System'),
    ('Information Security Management', 'Business Information System'),
    ('Database Management', 'Business Information System'),
    ('Structural Engineering', 'Civil Engineering'),
    ('Quantity Surveyor & Project Cost Control', 'Civil Engineering'),
    ('Construction Engineering Management', 'Civil Engineering'),
    ('Construction Materials', 'Civil Engineering'),
    ('Structural Analysis', 'Civil Engineering'),
    ('Infrastructure Development', 'Civil Engineering'),
    ('Transportation Engineering', 'Civil Engineering'),
    ('Water Resources Engineering', 'Civil Engineering'),
    ('Sanitation & Drainage', 'Civil Engineering'),
    ('Construction Scheduling', 'Civil Engineering'),
    ('Construction Quality Management', 'Civil Engineering'),
    ('Geotechnical Engineering', 'Civil Engineering'),
    ('Construction Cost Estimation', 'Civil Engineering'),
    ('Tourism & Events', 'Hospitality & Tourism'),
    ('Hotel Operations', 'Hospitality & Tourism'),
    ('Culinary Business', 'Hospitality & Tourism'),
    ('Tourism Management', 'Hospitality & Tourism'),
    ('Event Management', 'Hospitality & Tourism'),
    ('Destination Development', 'Hospitality & Tourism'),
    ('Hotel Management', 'Hospitality & Tourism'),
    ('Front Office Operations', 'Hospitality & Tourism'),
    ('Housekeeping', 'Hospitality & Tourism'),
    ('Food & Beverage Service', 'Hospitality & Tourism'),
    ('Food Cost Control', 'Hospitality & Tourism'),
    ('Sustainable Tourism', 'Hospitality & Tourism'),
    ('Travel Experience Design', 'Hospitality & Tourism'),
    ('Hospitality Entrepreneurship', 'Hospitality & Tourism'),
    ('Residential', 'Interior Design'),
    ('Hospitality & Commercial Space', 'Interior Design'),
    ('Interior Space Planning', 'Interior Design'),
    ('Furniture Design', 'Interior Design'),
    ('Lighting Design', 'Interior Design'),
    ('Retail Design', 'Interior Design'),
    ('Office Design', 'Interior Design'),
    ('Hospitality Interior Design', 'Interior Design'),
    ('Exhibition & Gallery Design', 'Interior Design'),
    ('Universal & Inclusive Design', 'Interior Design'),
    ('Smart Living', 'Interior Design'),
    ('Sustainable Interior Design', 'Interior Design'),
    ('Material & Finishing Design', 'Interior Design'),
    ('Interior Visualization', 'Interior Design'),
    ('Urban Design', 'Urban Planning'),
    ('Estate Management', 'Urban Planning'),
    ('Smart City', 'Urban Planning'),
    ('Sustainable Urban Development', 'Urban Planning'),
    ('Land Use Planning', 'Urban Planning'),
    ('Housing & Settlement Planning', 'Urban Planning'),
    ('Urban Mobility', 'Urban Planning'),
    ('Transportation Planning', 'Urban Planning'),
    ('Public Space Design', 'Urban Planning'),
    ('Community Development', 'Urban Planning'),
    ('Property Market Analysis', 'Urban Planning'),
    ('Urban Infrastructure', 'Urban Planning'),
    ('Environmental Planning', 'Urban Planning'),
    ('Regional Development', 'Urban Planning');
