// SHIAGARI - Projects Manager

let projects = [];

function loadProjects() {
  const stored = localStorage.getItem('shiagari_projects');
  if (stored) {
    projects = JSON.parse(stored);
  } else {
    projects = [
      { id: 'p1', name: 'Nexus OS', description: 'Next-gen dashboard with real-time analytics', status: 'active', createdAt: new Date().toISOString() },
      { id: 'p2', name: 'Lumina AI', description: 'Intelligent code assistant engine', status: 'active', createdAt: new Date().toISOString() },
      { id: 'p3', name: 'Stellar UI', description: 'Component library for dashboards', status: 'planning', createdAt: new Date().toISOString() },
      { id: 'p4', name: 'Quantum DB', description: 'High-performance data pipeline', status: 'hold', createdAt: new Date().toISOString() }
    ];
    saveProjects();
  }
  updateCount();
}

function saveProjects() {
  localStorage.setItem('shiagari_projects', JSON.stringify(projects));
  updateCount();
}

function updateCount() {
  const countSpan = document.getElementById('projectCount');
  if (countSpan) countSpan.textContent = projects.length;
}

function getStatusInfo(status) {
  const map = {
    active: { icon: 'fa-play-circle', label: 'Active', class: 'active' },
    planning: { icon: 'fa-draw-polygon', label: 'Planning', class: 'planning' },
    hold: { icon: 'fa-pause-circle', label: 'On Hold', class: 'hold' }
  };
  return map[status] || map.active;
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>]/g, function(m) {
    if (m === '&') return '&amp;';
    if (m === '<') return '&lt;';
    if (m === '>') return '&gt;';
    return m;
  });
}

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
    html += `
      <div class="project-card" data-id="${proj.id}" data-status="${proj.status}">
        <div><div class="card-title"><i class="fas fa-cube"></i> ${escapeHtml(proj.name)}</div>
        <div class="card-desc">${escapeHtml(proj.description || 'No description')}</div></div>
        <div class="card-footer">
          <span class="status-badge ${status.class}"><i class="fas ${status.icon}"></i> ${status.label}</span>
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
  document.getElementById('openModalBtn')?.addEventListener('click', openModal);
}

function addProject(name, description, status) {
  if (!name || !name.trim()) {
    showToast('Project name required', 'error');
    return false;
  }
  const newProject = {
    id: Date.now().toString() + '-' + Math.random().toString(36).substr(2, 6),
    name: name.trim(),
    description: description?.trim() || '',
    status: status || 'active',
    createdAt: new Date().toISOString()
  };
  projects.unshift(newProject);
  saveProjects();
  renderProjects();
  showToast(`"${newProject.name}" created`, 'success');
  return true;
}

function deleteProject(id) {
  const project = projects.find(p => p.id === id);
  if (project && confirm(`Delete "${project.name}"?`)) {
    projects = projects.filter(p => p.id !== id);
    saveProjects();
    renderProjects();
    showToast(`"${project.name}" removed`, 'info');
  }
}

let toastTimeout;
function showToast(message, type = 'success') {
  const toast = document.getElementById('toastMsg');
  const toastText = document.getElementById('toastText');
  const icon = toast.querySelector('i');
  if (type === 'error') {
    icon.className = 'fas fa-exclamation-triangle';
    icon.style.color = '#f97316';
  } else {
    icon.className = 'fas fa-check-circle';
    icon.style.color = '#10b981';
  }
  toastText.textContent = message;
  toast.classList.add('show');
  if (toastTimeout) clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => toast.classList.remove('show'), 2500);
}

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
  if (addProject(name, desc, status)) closeModal();
}

document.addEventListener('DOMContentLoaded', () => {
  loadProjects();
  renderProjects();

  document.getElementById('closeModalBtn')?.addEventListener('click', closeModal);
  document.getElementById('cancelModalBtn')?.addEventListener('click', closeModal);
  document.getElementById('saveProjectBtn')?.addEventListener('click', handleCreate);
  if (modal) {
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  }
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isModalOpen) closeModal();
  });

  // Roadmap placeholder
  document.getElementById('navRoadmap')?.addEventListener('click', (e) => {
    e.preventDefault();
    showToast('Roadmap planner coming soon!', 'info');
  });
});