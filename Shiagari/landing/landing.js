// SHIAGARI - Projects Manager (Database-Backed)

let projects = [];
let currentUser = null;
let csrfToken = '';

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  fetchCSRFToken();
  fetchCurrentUser();
  loadProjects();
  setupEventListeners();

  // Roadmap placeholder
  document.getElementById('navRoadmap')?.addEventListener('click', (e) => {
    e.preventDefault();
    showToast('Roadmap planner coming soon!', 'info');
  });
});

/**
 * Fetch CSRF token from session
 */
function fetchCSRFToken() {
  fetch('/api/profile.php?action=csrf')
    .then(res => res.json())
    .then(data => {
      if (data.csrf_token) {
        csrfToken = data.csrf_token;
      }
    })
    .catch(err => console.error('Failed to fetch CSRF token:', err));
}

/**
 * Fetch current user information
 */
function fetchCurrentUser() {
  fetch('/api/profile.php')
    .then(res => res.json())
    .then(data => {
      if (data.success && data.user) {
        currentUser = data.user;
      }
    })
    .catch(err => console.error('Failed to fetch user:', err));
}

/**
 * Setup event listeners for modals
 */
function setupEventListeners() {
  document.getElementById('closeModalBtn')?.addEventListener('click', closeModal);
  document.getElementById('cancelModalBtn')?.addEventListener('click', closeModal);
  document.getElementById('saveProjectBtn')?.addEventListener('click', handleCreate);
  document.getElementById('closeDetailsBtn')?.addEventListener('click', closeProjectDetails);
  
  const modal = document.getElementById('projectModal');
  if (modal) {
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  }
  
  const detailsModal = document.getElementById('projectDetailsModal');
  if (detailsModal) {
    detailsModal.addEventListener('click', (e) => { if (e.target === detailsModal) closeProjectDetails(); });
  }
  
  const inviteModal = document.getElementById('inviteModal');
  if (inviteModal) {
    inviteModal.addEventListener('click', (e) => { if (e.target === inviteModal) closeInviteModal(); });
    document.getElementById('inviteSearch')?.addEventListener('input', (e) => searchInvitees(e.target.value));
    document.getElementById('inviteSendBtn')?.addEventListener('click', sendInvitation);
    document.getElementById('closeInviteModalBtn')?.addEventListener('click', closeInviteModal);
  }
  
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const modal = document.getElementById('projectModal');
      if (modal && modal.style.display === 'flex') closeModal();
      
      const detailsModal = document.getElementById('projectDetailsModal');
      if (detailsModal && detailsModal.style.display === 'flex') closeProjectDetails();
      
      const inviteModal = document.getElementById('inviteModal');
      if (inviteModal && inviteModal.style.display === 'flex') closeInviteModal();
    }
  });
}

/**
 * Load projects from database
 */
function loadProjects() {
  fetch('/api/projects.php?action=list')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        projects = data.projects || [];
        renderProjects();
        updateCount();
      } else {
        console.error('Failed to load projects:', data.error);
        showToast('Failed to load projects', 'error');
      }
    })
    .catch(err => {
      console.error('Error loading projects:', err);
      showToast('Error loading projects', 'error');
    });
}

/**
 * Get status information
 */
function getStatusInfo(status) {
  const map = {
    active: { icon: 'fa-play-circle', label: 'Active', class: 'active' },
    planning: { icon: 'fa-draw-polygon', label: 'Planning', class: 'planning' },
    hold: { icon: 'fa-pause-circle', label: 'On Hold', class: 'hold' },
    completed: { icon: 'fa-check-circle', label: 'Completed', class: 'completed' }
  };
  return map[status] || map.active;
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>]/g, function(m) {
    if (m === '&') return '&amp;';
    if (m === '<') return '&lt;';
    if (m === '>') return '&gt;';
    return m;
  });
}

/**
 * Format date for display
 */
function formatDate(dateString) {
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) return 'Unknown';
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

/**
 * Update project count display
 */
function updateCount() {
  const countSpan = document.getElementById('projectCount');
  if (countSpan) countSpan.textContent = projects.length;
}

/**
 * Render all projects
 */
function renderProjects() {
  const grid = document.getElementById('projectsGrid');
  if (!grid) return;

  if (projects.length === 0) {
    grid.innerHTML = `<div class="empty-state"><i class="fas fa-folder-open"></i><p>No projects yet. Click + to create!</p></div><div class="add-btn" id="openModalBtn">+</div>`;
    const btn = document.getElementById('openModalBtn');
    if (btn) btn.addEventListener('click', openModal);
    return;
  }

  let html = '';
  projects.forEach(proj => {
    const status = getStatusInfo(proj.status);
    const memberCount = proj.members ? proj.members.length : 0;
    
    html += `
      <div class="project-card" data-id="${proj.id}" data-status="${proj.status}">
        <div>
          <div class="card-title"><i class="fas fa-cube"></i> ${escapeHtml(proj.name)}</div>
          <div class="card-desc">${escapeHtml(proj.description || 'No description')}</div>
        </div>
        <div class="card-footer">
          <span class="status-badge ${status.class}"><i class="fas ${status.icon}"></i> ${status.label}</span>
          <span class="member-count"><i class="fas fa-users"></i> ${memberCount}</span>
          <button class="delete-card" data-id="${proj.id}"><i class="fas fa-trash-alt"></i></button>
        </div>
      </div>
    `;
  });
  html += `<div class="add-btn" id="openModalBtn">+</div>`;
  grid.innerHTML = html;

  document.querySelectorAll('.delete-card').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.getAttribute('data-id');
      deleteProject(id);
    });
  });

  document.querySelectorAll('.project-card').forEach(card => {
    card.addEventListener('click', () => {
      openProjectDetails(card.dataset.id);
    });
  });

  document.getElementById('openModalBtn')?.addEventListener('click', openModal);
}

/**
 * Open project details modal
 */
function openProjectDetails(projectId) {
  const project = projects.find(p => p.id == projectId);
  if (!project) return;

  const modal = document.getElementById('projectDetailsModal');
  if (!modal) return;

  document.getElementById('detailsTitle').textContent = project.name;
  document.getElementById('detailsStatus').textContent = getStatusInfo(project.status).label;
  document.getElementById('detailsDescription').textContent = project.description || 'No description available for this project.';
  document.getElementById('detailsAuthor').textContent = project.owner_name || 'Unknown';
  document.getElementById('detailsCreated').textContent = formatDate(project.created_at);

  const membersList = document.getElementById('detailsMembers');
  if (project.members && project.members.length > 0) {
    membersList.innerHTML = project.members
      .map(member => `<li>${escapeHtml(member.full_name || member.username || 'Unknown')} <small>(${member.role})</small></li>`)
      .join('');
  } else {
    membersList.innerHTML = '<li>No members assigned yet.</li>';
  }

  // Show invite button only for project owner
  const inviteBtn = document.getElementById('projectInviteBtn');
  if (inviteBtn) {
    if (currentUser && project.owner_id == currentUser.id) {
      inviteBtn.style.display = 'inline-block';
      inviteBtn.onclick = () => openInviteModal(projectId);
    } else {
      inviteBtn.style.display = 'none';
    }
  }

  modal.style.display = 'flex';
  modal.classList.add('project-details');
  document.body.style.overflow = 'hidden';
}

/**
 * Close project details modal
 */
function closeProjectDetails() {
  const modal = document.getElementById('projectDetailsModal');
  if (modal) {
    modal.style.display = 'none';
    modal.classList.remove('project-details');
    document.body.style.overflow = '';
  }
}

/**
 * Open invite collaborators modal
 */
function openInviteModal(projectId) {
  const project = projects.find(p => p.id == projectId);
  if (!project) return;

  const modal = document.getElementById('inviteModal');
  if (!modal) return;

  document.getElementById('inviteProjectId').value = projectId;
  document.getElementById('inviteProjectName').textContent = project.name;
  document.getElementById('inviteSearch').value = '';
  document.getElementById('inviteSearchResults').innerHTML = '';
  document.getElementById('selectedInvitee').innerHTML = '';

  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';

  document.getElementById('inviteSearch').focus();
}

/**
 * Close invite modal
 */
function closeInviteModal() {
  const modal = document.getElementById('inviteModal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }
}

/**
 * Search for users to invite
 */
function searchInvitees(query) {
  if (query.length < 2) {
    document.getElementById('inviteSearchResults').innerHTML = '';
    return;
  }

  fetch(`/api/invitations.php?action=search&q=${encodeURIComponent(query)}`)
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        displaySearchResults(data.users);
      } else {
        console.error('Search failed:', data.error);
      }
    })
    .catch(err => console.error('Search error:', err));
}

/**
 * Display user search results
 */
function displaySearchResults(users) {
  const resultsContainer = document.getElementById('inviteSearchResults');
  
  if (!users || users.length === 0) {
    resultsContainer.innerHTML = '<div class="search-no-results">No users found</div>';
    return;
  }

  let html = '<div class="search-results">';
  users.forEach(user => {
    html += `
      <div class="search-result-item" onclick="selectInvitee(${user.id}, '${escapeHtml(user.username)}', '${escapeHtml(user.email)}')">
        <div class="result-name">${escapeHtml(user.full_name || user.username)}</div>
        <div class="result-email">${escapeHtml(user.email)}</div>
      </div>
    `;
  });
  html += '</div>';
  resultsContainer.innerHTML = html;
}

/**
 * Select invitee from search results
 */
function selectInvitee(userId, username, email) {
  document.getElementById('selectedInvitee').innerHTML = `
    <div class="selected-user">
      <span>${escapeHtml(username)} (${escapeHtml(email)})</span>
      <button type="button" onclick="clearInvitee()">×</button>
    </div>
  `;
  document.getElementById('inviteUserId').value = userId;
  document.getElementById('inviteUserEmail').value = email;
  document.getElementById('inviteSearch').value = '';
  document.getElementById('inviteSearchResults').innerHTML = '';
}

/**
 * Clear selected invitee
 */
function clearInvitee() {
  document.getElementById('selectedInvitee').innerHTML = '';
  document.getElementById('inviteUserId').value = '';
  document.getElementById('inviteUserEmail').value = '';
  document.getElementById('inviteSearch').value = '';
  document.getElementById('inviteSearch').focus();
}

/**
 * Send invitation
 */
function sendInvitation() {
  const projectId = document.getElementById('inviteProjectId').value;
  const userId = document.getElementById('inviteUserId').value;
  const userEmail = document.getElementById('inviteUserEmail').value;
  const role = document.getElementById('inviteRole').value || 'editor';

  if (!projectId || (!userId && !userEmail)) {
    showToast('Please select a user to invite', 'error');
    return;
  }

  const formData = new FormData();
  formData.append('csrf_token', csrfToken);
  formData.append('project_id', projectId);
  if (userId) formData.append('invitee_id', userId);
  if (userEmail) formData.append('invitee_email', userEmail);
  formData.append('role', role);

  fetch('/api/invitations.php?action=send', {
    method: 'POST',
    body: formData
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast('Invitation sent successfully!', 'success');
        closeInviteModal();
        loadProjects();
      } else {
        showToast(data.error || 'Failed to send invitation', 'error');
      }
    })
    .catch(err => {
      console.error('Error:', err);
      showToast('Error sending invitation', 'error');
    });
}

/**
 * Add new project
 */
function addProject(name, description, status) {
  if (!name || !name.trim()) {
    showToast('Project name required', 'error');
    return false;
  }

  const formData = new FormData();
  formData.append('csrf_token', csrfToken);
  formData.append('name', name.trim());
  formData.append('description', description?.trim() || '');
  formData.append('status', status || 'active');

  fetch('/api/projects.php?action=create', {
    method: 'POST',
    body: formData
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast(`"${data.project.name}" created`, 'success');
        loadProjects();
        return true;
      } else {
        showToast(data.error || 'Failed to create project', 'error');
        return false;
      }
    })
    .catch(err => {
      console.error('Error:', err);
      showToast('Error creating project', 'error');
      return false;
    });
}

/**
 * Delete project
 */
function deleteProject(id) {
  const project = projects.find(p => p.id == id);
  if (!project) return;

  if (!confirm(`Delete "${project.name}"?`)) return;

  const url = new URL('/api/projects.php', window.location.origin);
  url.searchParams.append('action', 'delete');
  url.searchParams.append('id', id);
  url.searchParams.append('csrf_token', csrfToken);

  fetch(url, { method: 'DELETE' })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast(`"${project.name}" deleted`, 'info');
        loadProjects();
      } else {
        showToast(data.error || 'Failed to delete project', 'error');
      }
    })
    .catch(err => {
      console.error('Error:', err);
      showToast('Error deleting project', 'error');
    });
}

/**
 * Show toast notification
 */
let toastTimeout;
function showToast(message, type = 'success') {
  const toast = document.getElementById('toastMsg');
  const toastText = document.getElementById('toastText');
  const icon = toast.querySelector('i');
  
  if (type === 'error') {
    icon.className = 'fas fa-exclamation-triangle';
    icon.style.color = '#f97316';
  } else if (type === 'info') {
    icon.className = 'fas fa-info-circle';
    icon.style.color = '#3b82f6';
  } else {
    icon.className = 'fas fa-check-circle';
    icon.style.color = '#10b981';
  }
  
  toastText.textContent = message;
  toast.classList.add('show');
  
  if (toastTimeout) clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => toast.classList.remove('show'), 2500);
}

/**
 * Modal management
 */
const modal = document.getElementById('projectModal');
let isModalOpen = false;

function openModal() {
  if (!modal) return;
  document.getElementById('projectName').value = '';
  document.getElementById('projectDesc').value = '';
  document.getElementById('projectStatus').value = 'active';
  modal.style.display = 'flex';
  isModalOpen = true;
  setTimeout(() => document.getElementById('projectName').focus(), 100);
}

function closeModal() {
  if (modal) {
    modal.style.display = 'none';
    isModalOpen = false;
  }
}

function handleCreate() {
  const name = document.getElementById('projectName').value.trim();
  const desc = document.getElementById('projectDesc').value;
  const status = document.getElementById('projectStatus').value;
  
  if (!name) {
    showToast('Enter project name', 'error');
    return;
  }
  
  const btn = document.getElementById('saveProjectBtn');
  btn.disabled = true;
  
  addProject(name, desc, status);
  
  setTimeout(() => { btn.disabled = false; }, 500);
  closeModal();
}