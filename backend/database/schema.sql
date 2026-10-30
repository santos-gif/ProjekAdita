-- MySQL 8. Buat database projekadita_db (utf8mb4) lebih dulu, lalu:
--   mysql -u <user> -p projekadita_db < backend/database/schema.sql
-- Catatan: skema.sql lama ternyata berisi kode JS (bukan SQL); departments/projects
-- dipulihkan dari kolom yang dipakai projectController.js lama.

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    jurusan VARCHAR(120) NULL,
    angkatan VARCHAR(10) NULL,
    kontak VARCHAR(120) NULL,
    deskripsi TEXT NULL,
    avatar_url VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS departments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    UNIQUE KEY uq_departments_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS projects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('Teknologi', 'Bisnis', 'Desain', 'Sosial', 'Penelitian', 'Lainnya') NOT NULL,
    department_id BIGINT UNSIGNED NULL,
    max_members INT UNSIGNED NOT NULL DEFAULT 5,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_projects_created (created_at),
    CONSTRAINT fk_projects_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB;
