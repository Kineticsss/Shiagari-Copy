-- SHIAGARI Database Migrations for Project Collaboration System
-- Includes projects, project_members, and project_invitations tables

-- Create projects table
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL COMMENT 'Unique identifier for project',
    name VARCHAR(255) NOT NULL COMMENT 'Project name',
    description LONGTEXT COMMENT 'Project description',
    status ENUM('active', 'planning', 'hold', 'completed') DEFAULT 'active' COMMENT 'Project status',
    owner_id INT NOT NULL COMMENT 'User ID of project owner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_owner (owner_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- Create project_members table (stores confirmed collaborators)
CREATE TABLE IF NOT EXISTS project_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL COMMENT 'Project ID',
    user_id INT NOT NULL COMMENT 'User ID of member',
    role ENUM('owner', 'editor', 'viewer') DEFAULT 'viewer' COMMENT 'Member role/permissions',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_project_member (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_project (project_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role)
);

-- Create project_invitations table (stores pending invitations)
CREATE TABLE IF NOT EXISTS project_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL COMMENT 'Project ID',
    inviter_id INT NOT NULL COMMENT 'User ID who sent the invitation',
    invitee_id INT NOT NULL COMMENT 'User ID who received invitation (nullable for email-only invites)',
    invitee_email VARCHAR(255) COMMENT 'Email of invitee (if not yet a registered user)',
    invitee_username VARCHAR(255) COMMENT 'Username search query (for display)',
    role ENUM('editor', 'viewer') DEFAULT 'editor' COMMENT 'Role for invited user',
    status ENUM('pending', 'accepted', 'declined', 'expired') DEFAULT 'pending' COMMENT 'Invitation status',
    token VARCHAR(64) UNIQUE COMMENT 'Unique token for invitation link',
    expires_at TIMESTAMP NULL COMMENT 'Invitation expiration time (30 days default)',
    responded_at TIMESTAMP NULL COMMENT 'When user responded to invitation',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invitee_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_project (project_id),
    INDEX idx_invitee (invitee_id),
    INDEX idx_status (status),
    INDEX idx_email (invitee_email),
    INDEX idx_expires (expires_at),
    INDEX idx_token (token)
);

-- Add indexes for performance
ALTER TABLE projects ADD INDEX idx_uuid (uuid);
ALTER TABLE project_members ADD INDEX idx_joined (joined_at);
ALTER TABLE project_invitations ADD INDEX idx_created (created_at);
