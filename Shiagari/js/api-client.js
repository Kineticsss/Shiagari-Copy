// js/api-client.js
// Frontend API client for Firestore data operations

class ShiagariAPI {
    constructor() {
        this.baseUrl = '';
        this.csrfToken = '';
    }

    /**
     * Initialize API client with CSRF token
     */
    init(csrfToken) {
        this.csrfToken = csrfToken;
    }

    /**
     * Make API request
     */
    async request(endpoint, method = 'GET', data = null) {
        // Ensure endpoint is absolute path (starts with /)
        const absoluteEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
        
        const options = {
            method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
        };

        if (data) {
            options.body = JSON.stringify({
                ...data,
                csrf_token: this.csrfToken,
            });
        }

        try {
            const response = await fetch(absoluteEndpoint, options);
            const result = await response.json();

            if (!response.ok && result.error) {
                throw new Error(result.error);
            }

            return result;
        } catch (error) {
            console.error(`API Error (${absoluteEndpoint}):`, error);
            throw error;
        }
    }

    // ==================== PROJECTS ====================

    /**
     * Get all projects for current user
     */
    async getProjects() {
        return this.request('api/projects.php', 'GET');
    }

    /**
     * Create a new project
     */
    async createProject(name, description = '', status = 'active', members = []) {
        return this.request('api/projects.php', 'POST', {
            action: 'create',
            name,
            description,
            status,
            members,
        });
    }

    /**
     * Update a project
     */
    async updateProject(projectId, updates) {
        return this.request('api/projects.php', 'POST', {
            action: 'update',
            project_id: projectId,
            ...updates,
        });
    }

    /**
     * Delete a project
     */
    async deleteProject(projectId) {
        return this.request('api/projects.php', 'POST', {
            action: 'delete',
            project_id: projectId,
        });
    }

    // ==================== IDEAS ====================

    /**
     * Get ideas for a project
     */
    async getIdeas(projectId) {
        return this.request(`api/ideas.php?project_id=${projectId}`, 'GET');
    }

    /**
     * Create an idea
     */
    async createIdea(projectId, title, description = '', category = 'general') {
        return this.request('api/ideas.php', 'POST', {
            action: 'create',
            project_id: projectId,
            title,
            description,
            category,
        });
    }

    /**
     * Update an idea
     */
    async updateIdea(ideaId, updates) {
        return this.request('api/ideas.php', 'POST', {
            action: 'update',
            idea_id: ideaId,
            ...updates,
        });
    }

    /**
     * Delete an idea
     */
    async deleteIdea(ideaId) {
        return this.request('api/ideas.php', 'POST', {
            action: 'delete',
            idea_id: ideaId,
        });
    }

    // ==================== POSTS ====================

    /**
     * Get posts for a project
     */
    async getPosts(projectId) {
        return this.request(`api/posts.php?project_id=${projectId}`, 'GET');
    }

    /**
     * Create a post
     */
    async createPost(projectId, content, isAnnouncement = false, announcementTitle = '') {
        return this.request('api/posts.php', 'POST', {
            action: 'create',
            project_id: projectId,
            content,
            is_announcement: isAnnouncement,
            announcement_title: announcementTitle,
        });
    }

    /**
     * Update a post
     */
    async updatePost(postId, updates) {
        return this.request('api/posts.php', 'POST', {
            action: 'update',
            post_id: postId,
            ...updates,
        });
    }

    /**
     * Delete a post
     */
    async deletePost(postId) {
        return this.request('api/posts.php', 'POST', {
            action: 'delete',
            post_id: postId,
        });
    }

    // ==================== PROGRESS/TASKS ====================

    /**
     * Get tasks for a project
     */
    async getTasks(projectId) {
        return this.request(`api/progress.php?project_id=${projectId}`, 'GET');
    }

    /**
     * Create a task
     */
    async createTask(projectId, name, category = 'uiux', status = 'notstarted', progress = 0) {
        return this.request('api/progress.php', 'POST', {
            action: 'create',
            project_id: projectId,
            name,
            category,
            status,
            progress,
        });
    }

    /**
     * Update a task
     */
    async updateTask(taskId, updates) {
        return this.request('api/progress.php', 'POST', {
            action: 'update',
            task_id: taskId,
            ...updates,
        });
    }

    /**
     * Delete a task
     */
    async deleteTask(taskId) {
        return this.request('api/progress.php', 'POST', {
            action: 'delete',
            task_id: taskId,
        });
    }

    // ==================== ROADMAP/EPICS ====================

    /**
     * Get epics for a project
     */
    async getEpics(projectId) {
        return this.request(`api/roadmap.php?project_id=${projectId}`, 'GET');
    }

    /**
     * Create an epic
     */
    async createEpic(projectId, name, color = 'blue', startQuarter = 0, duration = 1, description = '') {
        return this.request('api/roadmap.php', 'POST', {
            action: 'create',
            project_id: projectId,
            name,
            color,
            start_quarter: startQuarter,
            duration,
            description,
        });
    }

    /**
     * Update an epic
     */
    async updateEpic(epicId, updates) {
        return this.request('api/roadmap.php', 'POST', {
            action: 'update',
            epic_id: epicId,
            ...updates,
        });
    }

    /**
     * Delete an epic
     */
    async deleteEpic(epicId) {
        return this.request('api/roadmap.php', 'POST', {
            action: 'delete',
            epic_id: epicId,
        });
    }

    // ==================== MESSAGES ====================

    /**
     * Get all conversations for current user
     */
    async getConversations() {
        return this.request('api/messages.php', 'GET');
    }

    /**
     * Get conversation with a specific user
     */
    async getConversation(userId) {
        return this.request(`api/messages.php?user_id=${userId}`, 'GET');
    }

    // ==================== USER SEARCH ====================

    /**
     * Search for existing users to chat/invite
     */
    async searchUsers(query) {
        const q = encodeURIComponent(query ?? '');
        return this.request(`api/user-search.php?q=${q}`, 'GET');
    }


    /**
     * Send a message
     */
    async sendMessage(toUid, content) {
        return this.request('api/messages.php', 'POST', {
            action: 'send',
            to_uid: toUid,
            content,
        });
    }

    /**
     * Mark messages as read
     */
    async markMessagesRead(conversationId) {
        return this.request('api/messages.php', 'POST', {
            action: 'mark_read',
            conversation_id: conversationId,
        });
    }

    // ==================== USER PROFILE ====================

    /**
     * Get user profile
     */
    async getUserProfile(uid = null) {
        if (uid) {
            return this.request(`api/user-profile.php?uid=${uid}`, 'GET');
        }
        return this.request('api/user-profile.php', 'GET');
    }

    /**
     * Update user profile
     */
    async updateProfile(updates) {
        return this.request('api/user-profile.php', 'POST', {
            action: 'update',
            ...updates,
        });
    }
}

// Global instance
const shiagariAPI = new ShiagariAPI();

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = shiagariAPI;
}
