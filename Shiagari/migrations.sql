-- SHIAGARI Database Migrations (full)
-- Run this file once against your MySQL database.
-- Safe to run multiple times (uses IF NOT EXISTS / MODIFY).

-- ── users ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    firebase_uid VARCHAR(128) UNIQUE NOT NULL,
    email        VARCHAR(255) UNIQUE NOT NULL,
    full_name    VARCHAR(80)  NOT NULL DEFAULT '',
    username     VARCHAR(30)  NOT NULL DEFAULT '',
    role         ENUM('admin','user') DEFAULT 'user',
    last_login   TIMESTAMP NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_firebase_uid (firebase_uid),
    INDEX idx_email (email),
    INDEX idx_username (username)
);

-- ── projects ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS projects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    uuid        VARCHAR(36) UNIQUE NOT NULL,
    name        VARCHAR(255) NOT NULL,
    description LONGTEXT,
    status      ENUM('active','planning','hold','completed') DEFAULT 'active',
    owner_id    INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_owner   (owner_id),
    INDEX idx_status  (status),
    INDEX idx_created (created_at)
);

-- ── project_members ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS project_members (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id    INT NOT NULL,
    role       ENUM('owner','editor','viewer') DEFAULT 'viewer',
    joined_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_project_member (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_project (project_id),
    INDEX idx_user    (user_id)
);

-- ── project_invitations ───────────────────────────────────────────────────────
-- NOTE: invitee_id is NULLABLE to support email-only invitations.
CREATE TABLE IF NOT EXISTS project_invitations (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    project_id       INT NOT NULL,
    inviter_id       INT NOT NULL,
    invitee_id       INT NULL,                      -- nullable: not yet registered users
    invitee_email    VARCHAR(255) NOT NULL DEFAULT '',
    role             ENUM('editor','viewer') DEFAULT 'editor',
    status           ENUM('pending','accepted','declined','expired') DEFAULT 'pending',
    token            VARCHAR(64) UNIQUE,
    expires_at       TIMESTAMP NULL,
    responded_at     TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id)  REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (inviter_id)  REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (invitee_id)  REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_project    (project_id),
    INDEX idx_invitee    (invitee_id),
    INDEX idx_status     (status),
    INDEX idx_email      (invitee_email),
    INDEX idx_expires    (expires_at),
    INDEX idx_token      (token)
);

-- Fix existing table if invitee_id was created as NOT NULL
ALTER TABLE project_invitations MODIFY COLUMN invitee_id INT NULL;

-- ── posts ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS posts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    project_id INT NULL,
    content    TEXT NOT NULL,
    is_announcement TINYINT(1) DEFAULT 0,
    title      VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user    (user_id),
    INDEX idx_project (project_id),
    INDEX idx_created (created_at)
);

-- ── post_comments ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS post_comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    post_id    INT NOT NULL,
    user_id    INT NOT NULL,
    content    TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_post (post_id)
);