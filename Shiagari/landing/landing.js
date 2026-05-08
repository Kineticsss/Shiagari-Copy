// SHIAGARI - Projects Manager (Database-Backed)
// Fixed: API paths, invite flow, pending invitations

let projects    = [];
let currentUser = null;
let csrfToken   = '';
let inviteSearchTimeout = null;

/* ─────────────────────────────────────────────
   PATH HELPER
   landing.html lives at /landing/landing.html
   API lives at /api/*.php (relative to project root)
───────────────────────────────────────────── */
const API = '../api';

/* ─────────────────────────────────────────────
   INIT
───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  fetchCSRFAndUser().then(() => {
    loadProjects();
    loadPendingInvitations();
  });
  setupEventListeners();
});

async function fetchCSRFAndUser() {
  try {
    const res  = await fetch(`${API}/profile.php`, { credentials: 'same-origin' });
    const data = await res.json();
    if (data.success && data.user) {
      currentUser = data.user;
      csrfToken   = data.csrf_token || '';
      // Update topbar avatar with initials
      const initials = (data.user.full_name || 'U')
        .split(' ').filter(Boolean).slice(0, 2).map(s => s[0].toUpperCase()).join('');
      const av = document.getElementById('topbarAvatar');
      if (av) av.querySelector('span').textContent = initials;
} else {
    if (!window.location.href.includes('index.php')) {
        window.location.href = '../index.php';
    }
}

/* ─────────────────────────────────────────────
   EVENT LISTENERS
───────────────────────────────────────────── */
function setupEventListeners() {
  // Create project modal
  document.getElementById('closeModalBtn')?.addEventListener('click', closeModal);
  document.getElementById('cancelModalBtn')?.addEventListener('click', closeModal);
  document.getElementById('saveProjectBtn')?.addEventListener('click', handleCreate);

  // Project details modal
  document.getElementById('closeDetailsBtn')?.addEventListener('click', closeProjectDetails);

  // Invite modal
  document.getElementById('closeInviteModalBtn')?.addEventListener('click', closeInviteModal);
  document.getElementById('cancelInviteBtn')?.addEventListener('click', closeInviteModal);
  document.getElementById('inviteSendBtn')?.addEventListener('click', sendInvitation);

  // Invite search with debounce
  document.getElementById('inviteSearch')?.addEventListener('input', e => {
    clearTimeout(inviteSearchTimeout);
    inviteSearchTimeout = setTimeout(() => searchInvitees(e.target.value.trim()), 300);
  });

  // Close dropdowns when clicking outside
  document.addEventListener('click', e => {
    const results = document.getElementById('inviteSearchResults');
    if (results && !e.target.closest('.invite-search-wrap')) {
      results.style.display = 'none';
    }
  });

  // Backdrop clicks
  ['projectModal','projectDetailsModal','inviteModal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', e => { if (e.target === el) el.style.display = 'none'; });
  });

  // Escape key
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      ['projectModal','projectDetailsModal','inviteModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el && el.style.display === 'flex') el.style.display = 'none';
      });
    }
  });
}

/* ─────────────────────────────────────────────
   PROJECTS  (load / render / create / delete)
───────────────────────────────────────────── */
function loadProjects() {
  fetch(`${API}/projects.php?action=list`, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        projects = data.projects || [];
        // Persist for other modules (idea, progress, roadmap)
        localStorage.setItem('shiagari_projects', JSON.stringify(
          projects.map(p => ({ id: String(p.id), name: p.name }))
        ));
        renderProjects();
        updateCount();
      } else {
        showToast(data.error || 'Failed to load projects', 'error');
      }
    })
    .catch(err => { console.error(err); showToast('Error loading projects', 'error'); });
}

function getStatusInfo(status) {
  const map = {
    active:    { icon: 'fa-play-circle',  label: 'Active',    class: 'active'    },
    planning:  { icon: 'fa-draw-polygon', label: 'Planning',  class: 'planning'  },
    hold:      { icon: 'fa-pause-circle', label: 'On Hold',   class: 'hold'      },
    completed: { icon: 'fa-check-circle', label: 'Completed', class: 'completed' }
  };
  return map[status] || map.active;
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>"']/g, m =>
    ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])
  );
}

function formatDate(d) {
  const date = new Date(d);
  return isNaN(date) ? '' : date.toLocaleDateString(undefined, { year:'numeric', month:'short', day:'numeric' });
}

function updateCount() {
  const el = document.getElementById('projectCount');
  if (el) el.textContent = projects.length;
}

function renderProjects() {
  const grid = document.getElementById('projectsGrid');
  if (!grid) return;

  if (projects.length === 0) {
    grid.innerHTML = `
      <div class="empty-state">
        <i class="fas fa-folder-open"></i>
        <p>No projects yet. Click + to create!</p>
      </div>
      <div class="add-btn" id="openModalBtn">+</div>`;
    document.getElementById('openModalBtn')?.addEventListener('click', openModal);
    return;
  }

  let html = '';
  projects.forEach(proj => {
    const status      = getStatusInfo(proj.status);
    const memberCount = proj.members ? proj.members.length : 0;
    const pendingInv  = proj.pending_invitations || 0;
    html += `
      <div class="project-card" data-id="${proj.id}" data-status="${escapeHtml(proj.status)}">
        <div>
          <div class="card-title"><i class="fas fa-cube"></i> ${escapeHtml(proj.name)}</div>
          <div class="card-desc">${escapeHtml(proj.description || 'No description')}</div>
        </div>
        <div class="card-footer">
          <span class="status-badge ${status.class}"><i class="fas ${status.icon}"></i> ${status.label}</span>
          <span class="member-count"><i class="fas fa-users"></i> ${memberCount}${pendingInv > 0 ? ` <i class="fas fa-envelope" style="color:#f59e0b;margin-left:4px;" title="${pendingInv} pending invite(s)"></i>` : ''}</span>
          <button class="delete-card" data-id="${proj.id}" title="Delete project"><i class="fas fa-trash-alt"></i></button>
        </div>
      </div>`;
  });
  html += `<div class="add-btn" id="openModalBtn">+</div>`;
  grid.innerHTML = html;

  grid.querySelectorAll('.delete-card').forEach(btn => {
    btn.addEventListener('click', e => { e.stopPropagation(); deleteProject(btn.dataset.id); });
  });
  grid.querySelectorAll('.project-card').forEach(card => {
    card.addEventListener('click', () => openProjectDetails(card.dataset.id));
  });
  document.getElementById('openModalBtn')?.addEventListener('click', openModal);
}

/* ─────────────────────────────────────────────
   PROJECT DETAILS MODAL
───────────────────────────────────────────── */
function openProjectDetails(projectId) {
  const project = projects.find(p => String(p.id) === String(projectId));
  if (!project) return;

  document.getElementById('detailsTitle').textContent      = project.name;
  document.getElementById('detailsStatus').textContent     = getStatusInfo(project.status).label;
  document.getElementById('detailsDescription').textContent = project.description || 'No description.';
  document.getElementById('detailsAuthor').textContent     = project.owner_name || 'Unknown';
  document.getElementById('detailsCreated').textContent    = formatDate(project.created_at);

  const membersList = document.getElementById('detailsMembers');
  if (project.members && project.members.length > 0) {
    membersList.innerHTML = project.members
      .map(m => `<li>${escapeHtml(m.full_name || m.username || 'Unknown')} <small style="color:#94a3b8;">(${m.role})</small></li>`)
      .join('');
  } else {
    membersList.innerHTML = '<li style="color:#6b7280;">No members yet.</li>';
  }

  // Show invite button only for project owner
  const inviteBtn = document.getElementById('projectInviteBtn');
  if (inviteBtn) {
    const isOwner = currentUser && String(project.owner_id) === String(currentUser.id);
    inviteBtn.style.display = isOwner ? 'inline-flex' : 'none';
    inviteBtn.onclick = () => {
      closeProjectDetails();
      openInviteModal(projectId);
    };
  }

  document.getElementById('projectDetailsModal').style.display = 'flex';
}

function closeProjectDetails() {
  document.getElementById('projectDetailsModal').style.display = 'none';
}

/* ─────────────────────────────────────────────
   CREATE PROJECT
───────────────────────────────────────────── */
function openModal() {
  document.getElementById('projectName').value  = '';
  document.getElementById('projectDesc').value  = '';
  document.getElementById('projectStatus').value = 'active';
  document.getElementById('projectModal').style.display = 'flex';
  setTimeout(() => document.getElementById('projectName').focus(), 80);
}

function closeModal() {
  document.getElementById('projectModal').style.display = 'none';
}

function handleCreate() {
  const name   = document.getElementById('projectName').value.trim();
  const desc   = document.getElementById('projectDesc').value.trim();
  const status = document.getElementById('projectStatus').value;

  if (!name) { showToast('Enter a project name', 'error'); return; }

  const btn = document.getElementById('saveProjectBtn');
  btn.disabled = true;

  const fd = new FormData();
  fd.append('csrf_token', csrfToken);
  fd.append('name', name);
  fd.append('description', desc);
  fd.append('status', status);

  fetch(`${API}/projects.php?action=create`, { method:'POST', credentials:'same-origin', body:fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(`"${data.project.name}" created`, 'success');
        loadProjects();
        closeModal();
      } else {
        showToast(data.error || 'Failed to create project', 'error');
      }
    })
    .catch(err => { console.error(err); showToast('Error creating project', 'error'); })
    .finally(() => { btn.disabled = false; });
}

/* ─────────────────────────────────────────────
   DELETE PROJECT
───────────────────────────────────────────── */
function deleteProject(id) {
  const project = projects.find(p => String(p.id) === String(id));
  if (!project || !confirm(`Delete "${project.name}"?`)) return;

  const url = new URL(`${API}/projects.php`, window.location.origin);
  url.searchParams.set('action', 'delete');
  url.searchParams.set('id', id);
  url.searchParams.set('csrf_token', csrfToken);

  fetch(url, { method:'DELETE', credentials:'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(`"${project.name}" deleted`, 'info');
        loadProjects();
      } else {
        showToast(data.error || 'Failed to delete', 'error');
      }
    })
    .catch(err => { console.error(err); showToast('Error deleting project', 'error'); });
}

/* ─────────────────────────────────────────────
   INVITE COLLABORATOR FLOW
───────────────────────────────────────────── */
function openInviteModal(projectId) {
  const project = projects.find(p => String(p.id) === String(projectId));
  if (!project) return;

  document.getElementById('inviteProjectId').value    = projectId;
  document.getElementById('inviteProjectName').textContent = project.name;
  document.getElementById('inviteSearch').value       = '';
  document.getElementById('inviteSearchResults').style.display = 'none';
  document.getElementById('inviteSearchResults').innerHTML    = '';
  document.getElementById('selectedInvitee').innerHTML        = '';
  document.getElementById('inviteUserId').value       = '';
  document.getElementById('inviteUserEmail').value    = '';
  document.getElementById('inviteRole').value         = 'editor';

  document.getElementById('inviteModal').style.display = 'flex';
  setTimeout(() => document.getElementById('inviteSearch').focus(), 80);
}

function closeInviteModal() {
  document.getElementById('inviteModal').style.display = 'none';
}

function searchInvitees(query) {
  const resultsEl = document.getElementById('inviteSearchResults');

  if (query.length < 2) {
    resultsEl.style.display = 'none';
    resultsEl.innerHTML = '';
    return;
  }

  fetch(`${API}/invitations.php?action=search&q=${encodeURIComponent(query)}`, { credentials:'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (!data.success || !data.users || data.users.length === 0) {
        resultsEl.innerHTML = '<div class="search-result-item" style="color:#94a3b8;">No users found</div>';
        resultsEl.style.display = 'block';
        return;
      }

      // Filter out already-selected and current user
      const filtered = data.users.filter(u => String(u.id) !== String(currentUser?.id));

      if (filtered.length === 0) {
        resultsEl.innerHTML = '<div class="search-result-item" style="color:#94a3b8;">No other users found</div>';
        resultsEl.style.display = 'block';
        return;
      }

      resultsEl.innerHTML = filtered.map(u => `
        <div class="search-result-item" data-id="${u.id}" data-username="${escapeHtml(u.username)}" data-email="${escapeHtml(u.email)}" data-name="${escapeHtml(u.full_name || u.username)}">
          <div class="result-name">${escapeHtml(u.full_name || u.username)} ${u.username ? `<span style="color:#60a5fa;font-size:12px;">@${escapeHtml(u.username)}</span>` : ''}</div>
          <div class="result-email">${escapeHtml(u.email)}</div>
        </div>
      `).join('');
      resultsEl.style.display = 'block';

      resultsEl.querySelectorAll('.search-result-item[data-id]').forEach(item => {
        item.addEventListener('click', () => selectInvitee(item.dataset.id, item.dataset.username, item.dataset.email, item.dataset.name));
      });
    })
    .catch(err => console.error('Search error:', err));
}

function selectInvitee(userId, username, email, fullName) {
  document.getElementById('inviteUserId').value    = userId;
  document.getElementById('inviteUserEmail').value = email;
  document.getElementById('inviteSearch').value    = '';
  document.getElementById('inviteSearchResults').style.display = 'none';

  document.getElementById('selectedInvitee').innerHTML = `
    <div class="selected-user">
      <span><i class="fas fa-user-check" style="color:#10b981;margin-right:6px;"></i>${escapeHtml(fullName)} <span style="color:#60a5fa;">@${escapeHtml(username)}</span> &nbsp;·&nbsp; ${escapeHtml(email)}</span>
      <button onclick="clearInvitee()" title="Remove">&times;</button>
    </div>`;
}

function clearInvitee() {
  document.getElementById('inviteUserId').value    = '';
  document.getElementById('inviteUserEmail').value = '';
  document.getElementById('selectedInvitee').innerHTML = '';
  document.getElementById('inviteSearch').focus();
}

function sendInvitation() {
  const projectId  = document.getElementById('inviteProjectId').value;
  const userId     = document.getElementById('inviteUserId').value;
  const userEmail  = document.getElementById('inviteUserEmail').value;
  const role       = document.getElementById('inviteRole').value;

  if (!projectId)               { showToast('Project not selected', 'error'); return; }
  if (!userId && !userEmail)    { showToast('Please search and select a user first', 'error'); return; }

  const btn = document.getElementById('inviteSendBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

  const fd = new FormData();
  fd.append('csrf_token', csrfToken);
  fd.append('project_id', projectId);
  if (userId)    fd.append('invitee_id',    userId);
  if (userEmail) fd.append('invitee_email', userEmail);
  fd.append('role', role);

  fetch(`${API}/invitations.php?action=send`, { method:'POST', credentials:'same-origin', body:fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('Invitation sent!', 'success');
        closeInviteModal();
        loadProjects();
      } else {
        showToast(data.error || 'Failed to send invitation', 'error');
      }
    })
    .catch(err => { console.error(err); showToast('Error sending invitation', 'error'); })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Invitation';
    });
}

/* ─────────────────────────────────────────────
   PENDING INVITATIONS BANNER
───────────────────────────────────────────── */
function loadPendingInvitations() {
  fetch(`${API}/invitations.php?action=pending`, { credentials:'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (data.success && data.invitations && data.invitations.length > 0) {
        renderInvitationsBanner(data.invitations);
      }
    })
    .catch(err => console.error('Failed to load invitations:', err));
}

function renderInvitationsBanner(invitations) {
  const banner = document.getElementById('invitationsBanner');
  const list   = document.getElementById('invitationsList');
  if (!banner || !list) return;

  list.innerHTML = invitations.map(inv => `
    <div class="invitation-item" id="inv-${inv.id}">
      <div class="inv-info">
        <div class="inv-title"><i class="fas fa-folder" style="color:#3b82f6;margin-right:6px;"></i>${escapeHtml(inv.project_name)}</div>
        <div class="inv-sub">Invited by ${escapeHtml(inv.inviter_name || inv.inviter_username)} · as <strong>${escapeHtml(inv.role)}</strong></div>
      </div>
      <div class="invitation-actions">
        <button class="btn-accept"  onclick="respondInvitation(${inv.id}, 'accept')"><i class="fas fa-check"></i> Accept</button>
        <button class="btn-decline" onclick="respondInvitation(${inv.id}, 'decline')"><i class="fas fa-times"></i> Decline</button>
      </div>
    </div>`).join('');

  banner.style.display = 'block';
}

function respondInvitation(invitationId, action) {
  const fd = new FormData();
  fd.append('csrf_token', csrfToken);
  fd.append('invitation_id', invitationId);

  fetch(`${API}/invitations.php?action=${action}`, { method:'POST', credentials:'same-origin', body:fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(action === 'accept' ? 'Invitation accepted! You are now a collaborator.' : 'Invitation declined.', 'success');
        // Remove item from banner
        document.getElementById(`inv-${invitationId}`)?.remove();
        // If no more invitations, hide banner
        if (!document.querySelector('.invitation-item')) {
          document.getElementById('invitationsBanner').style.display = 'none';
        }
        if (action === 'accept') loadProjects();
      } else {
        showToast(data.error || 'Failed to respond', 'error');
      }
    })
    .catch(err => { console.error(err); showToast('Error responding to invitation', 'error'); });
}

/* ─────────────────────────────────────────────
   TOAST
───────────────────────────────────────────── */
let _toastTimer;
function showToast(message, type = 'success') {
  const toast     = document.getElementById('toastMsg');
  const toastText = document.getElementById('toastText');
  const icon      = toast.querySelector('i');

  const styles = {
    success: { cls: 'fa-check-circle',         color: '#10b981' },
    error:   { cls: 'fa-exclamation-triangle',  color: '#f97316' },
    info:    { cls: 'fa-info-circle',           color: '#3b82f6' }
  };
  const s = styles[type] || styles.success;
  icon.className    = `fas ${s.cls}`;
  icon.style.color  = s.color;
  toastText.textContent = message;
  toast.classList.add('show');

  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
}