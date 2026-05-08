// SHIAGARI - Progress Tracker (KISS, DRY, YAGNI compliant)

// ==================== DATA MODEL ====================
let projectsData = {
  project1: {
    name: 'Dashboard Redesign',
    tasks: [
      { id: 't1', name: 'Wireframing', category: 'uiux', status: 'finished', progress: 100 },
      { id: 't2', name: 'High-fidelity Mockups', category: 'uiux', status: 'finished', progress: 100 },
      { id: 't3', name: 'Component Library', category: 'frontend', status: 'inprogress', progress: 65 },
      { id: 't4', name: 'API Integration', category: 'backend', status: 'inprogress', progress: 40 },
      { id: 't5', name: 'User Testing', category: 'uiux', status: 'notstarted', progress: 0 },
      { id: 't6', name: 'Deployment Setup', category: 'backend', status: 'notstarted', progress: 0 }
    ]
  },
  project2: {
    name: 'Mobile App Launch',
    tasks: [
      { id: 't7', name: 'App Icon Design', category: 'uiux', status: 'finished', progress: 100 },
      { id: 't8', name: 'Splash Screen', category: 'frontend', status: 'finished', progress: 100 },
      { id: 't9', name: 'Push Notifications', category: 'backend', status: 'inprogress', progress: 75 },
      { id: 't10', name: 'App Store Assets', category: 'uiux', status: 'notstarted', progress: 0 }
    ]
  },
  project3: {
    name: 'API Integration',
    tasks: [
      { id: 't11', name: 'REST API Design', category: 'backend', status: 'finished', progress: 100 },
      { id: 't12', name: 'Authentication', category: 'backend', status: 'finished', progress: 100 },
      { id: 't13', name: 'Rate Limiting', category: 'backend', status: 'inprogress', progress: 50 },
      { id: 't14', name: 'Documentation', category: 'frontend', status: 'notstarted', progress: 0 }
    ]
  }
};

const STATUS_ORDER = ['notstarted', 'inprogress', 'finished'];
const STATUS_LABELS = {
  notstarted: '📋 NOT STARTED',
  inprogress: '🔄 IN PROGRESS',
  finished: '✅ FINISHED'
};

const CATEGORY_CONFIG = {
  uiux: { name: 'UI/UX', color: '#ff2d75', class: 'uiux' },
  frontend: { name: 'Frontend', color: '#3b82f6', class: 'frontend' },
  backend: { name: 'Backend', color: '#ff3b30', class: 'backend' }
};

let PROJECTS = {};

const STORAGE_SELECTED_PROJECT_KEY = 'shiagari_selected_project';

function loadProjectsFromStorage() {
  const stored = localStorage.getItem('shiagari_projects');
  if (stored) {
    const projects = JSON.parse(stored);
    PROJECTS = {};
    projects.forEach(proj => {
      PROJECTS[proj.id] = proj.name;
    });
    return projects;
  }
  return [];
}

function getSelectedProjectIdFromStorage() {
  return localStorage.getItem(STORAGE_SELECTED_PROJECT_KEY) || Object.keys(PROJECTS)[0] || 'p1';
}

// ==================== UTILITIES ====================
function generateId() {
  return Date.now().toString() + '-' + Math.random().toString(36).substr(2, 6);
}

function getStorageKey(projectId) {
  return `shiagari_progress_${projectId}`;
}

function saveToLocalStorage(projectId) {
  const projectTasks = projectsData[projectId] ? projectsData[projectId].tasks : [];
  localStorage.setItem(getStorageKey(projectId), JSON.stringify(projectTasks));
}

function loadFromLocalStorage(projectId) {
  const stored = localStorage.getItem(getStorageKey(projectId));
  if (!projectsData[projectId]) {
    projectsData[projectId] = {
      name: PROJECTS[projectId] || 'Untitled Project',
      tasks: []
    };
  }
  if (stored) {
    projectsData[projectId].tasks = JSON.parse(stored);
  }
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

// ==================== CORE BUSINESS LOGIC ====================
function getTasksByStatus(projectId, status) {
  const project = projectsData[projectId];
  if (!project) return [];
  return project.tasks.filter(task => task.status === status);
}

function findTaskById(taskId) {
  for (let projectId in projectsData) {
    const task = projectsData[projectId].tasks.find(t => t.id === taskId);
    if (task) return task;
  }
  return null;
}

function updateTaskStatus(taskId, newStatus) {
  for (let projectId in projectsData) {
    const task = projectsData[projectId].tasks.find(t => t.id === taskId);
    if (task) {
      task.status = newStatus;
      task.progress = newStatus === 'finished' ? 100 : task.progress;
      saveToLocalStorage(projectId);
      renderCurrentProject();
      showToast(`Task moved to ${STATUS_LABELS[newStatus]}`, 'success');
      return true;
    }
  }
  return false;
}

function updateTaskProgress(taskId, newProgress) {
  for (let projectId in projectsData) {
    const task = projectsData[projectId].tasks.find(t => t.id === taskId);
    if (task) {
      task.progress = Math.min(100, Math.max(0, newProgress));
      if (task.progress === 100 && task.status !== 'finished') {
        task.status = 'finished';
      } else if (task.progress > 0 && task.progress < 100 && task.status === 'notstarted') {
        task.status = 'inprogress';
      }
      saveToLocalStorage(projectId);
      renderCurrentProject();
      showToast(`Progress updated to ${task.progress}%`, 'info');
      return true;
    }
  }
  return false;
}

function addTask(projectId, taskName, category, status) {
  if (!taskName || taskName.trim() === '') {
    showToast('Task name is required', 'error');
    return false;
  }
  
  const newTask = {
    id: generateId(),
    name: taskName.trim(),
    category: category,
    status: status,
    progress: status === 'finished' ? 100 : 0
  };
  
  projectsData[projectId].tasks.push(newTask);
  saveToLocalStorage(projectId);
  renderCurrentProject();
  showToast(`Task "${newTask.name}" added`, 'success');
  return true;
}

function deleteTask(taskId) {
  for (let projectId in projectsData) {
    const index = projectsData[projectId].tasks.findIndex(t => t.id === taskId);
    if (index !== -1) {
      const taskName = projectsData[projectId].tasks[index].name;
      if (confirm(`Delete "${taskName}"?`)) {
        projectsData[projectId].tasks.splice(index, 1);
        saveToLocalStorage(projectId);
        renderCurrentProject();
        showToast(`Task deleted`, 'info');
      }
      return true;
    }
  }
  return false;
}

// ==================== STATISTICS & CHARTS ====================
function calculateOverallProgress(projectId) {
  const tasks = projectsData[projectId].tasks;
  if (tasks.length === 0) return 0;
  const totalProgress = tasks.reduce((sum, task) => sum + task.progress, 0);
  return Math.round(totalProgress / tasks.length);
}

function calculateCategoryProgress(projectId) {
  const categories = { uiux: { total: 0, done: 0 }, frontend: { total: 0, done: 0 }, backend: { total: 0, done: 0 } };
  
  projectsData[projectId].tasks.forEach(task => {
    if (categories[task.category]) {
      categories[task.category].total += 100;
      categories[task.category].done += task.progress;
    }
  });
  
  const result = {};
  for (let cat in categories) {
    const total = categories[cat].total;
    result[cat] = total > 0 ? Math.round((categories[cat].done / total) * 100) : 0;
  }
  return result;
}

function updateChart(projectId) {
  const categoryProgress = calculateCategoryProgress(projectId);
  const uiux = categoryProgress.uiux || 0;
  const frontend = categoryProgress.frontend || 0;
  const backend = categoryProgress.backend || 0;
  
  const total = uiux + frontend + backend;
  if (total === 0) {
    const chartCircle = document.getElementById('chartCircle');
    if (chartCircle) chartCircle.style.background = '#2d3f5f';
    return;
  }
  
  let uiuxEnd = (uiux / total) * 100;
  let frontendEnd = uiuxEnd + (frontend / total) * 100;
  
  const gradient = `conic-gradient(
    #ff2d75 0% ${uiuxEnd}%,
    #3b82f6 ${uiuxEnd}% ${frontendEnd}%,
    #ff3b30 ${frontendEnd}% 100%
  )`;
  
  const chartCircle = document.getElementById('chartCircle');
  if (chartCircle) chartCircle.style.background = gradient;
  
  const legendUIUX = document.getElementById('legendUIUX');
  const legendFrontend = document.getElementById('legendFrontend');
  const legendBackend = document.getElementById('legendBackend');
  
  if (legendUIUX) legendUIUX.textContent = `${uiux}%`;
  if (legendFrontend) legendFrontend.textContent = `${frontend}%`;
  if (legendBackend) legendBackend.textContent = `${backend}%`;
}

// ==================== DRAG & DROP ====================
let draggedTaskId = null;

function attachDragAndDrop() {
  const tasks = document.querySelectorAll('.task[draggable="true"]');
  const columns = document.querySelectorAll('.column');
  
  tasks.forEach(task => {
    task.setAttribute('draggable', 'true');
    task.addEventListener('dragstart', handleDragStart);
    task.addEventListener('dragend', handleDragEnd);
  });
  
  columns.forEach(column => {
    column.addEventListener('dragover', handleDragOver);
    column.addEventListener('dragleave', handleDragLeave);
    column.addEventListener('drop', handleDrop);
  });
}

function handleDragStart(e) {
  draggedTaskId = this.getAttribute('data-task-id');
  this.style.opacity = '0.5';
  e.dataTransfer.effectAllowed = 'move';
}

function handleDragEnd(e) {
  this.style.opacity = '';
  draggedTaskId = null;
  document.querySelectorAll('.column').forEach(col => {
    col.style.borderColor = '';
  });
}

function handleDragOver(e) {
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
  this.style.borderColor = '#3b82f6';
}

function handleDragLeave(e) {
  this.style.borderColor = '';
}

function handleDrop(e) {
  e.preventDefault();
  this.style.borderColor = '';
  const targetColumn = this.closest('.column');
  if (!targetColumn || !draggedTaskId) return;
  
  const newStatus = targetColumn.getAttribute('data-status');
  if (newStatus) {
    updateTaskStatus(draggedTaskId, newStatus);
  }
}

// ==================== RENDER UI ====================
// Load real projects and initialize tracker
function loadRealProjectsList() {
  const stored = localStorage.getItem('shiagari_projects');
  if (stored) {
    try {
      const projects = JSON.parse(stored);
      projects.forEach(proj => {
        PROJECTS[proj.id] = proj.name;
      });
    } catch (e) {
      console.error('Error parsing projects:', e);
    }
  }
}

loadRealProjectsList();

let currentProjectId = localStorage.getItem(STORAGE_SELECTED_PROJECT_KEY) || 'project1';

function renderCurrentProject() {
  renderColumns(currentProjectId);
  updateStats(currentProjectId);
  updateChart(currentProjectId);
  updateSelectedProjectName(currentProjectId);
}

function updateSelectedProjectName(projectId) {
  const label = document.getElementById('currentProjectName');
  if (label) {
    // Try real projects first, then fall back to projectsData
    let projectName = PROJECTS[projectId] || (projectsData[projectId]?.name) || 'Unknown Project';
    label.textContent = projectName;
  }
}

function saveSelectedProject(projectId) {
  localStorage.setItem(STORAGE_SELECTED_PROJECT_KEY, projectId);
}

function renderColumns(projectId) {
  const container = document.getElementById('trackerContainer');
  if (!container) return;
  
  let columnsHtml = '';
  
  STATUS_ORDER.forEach(status => {
    const tasks = getTasksByStatus(projectId, status);
    const statusLabel = STATUS_LABELS[status];
    const icon = status === 'notstarted' ? 'fa-clock' : (status === 'inprogress' ? 'fa-spinner fa-pulse' : 'fa-check-circle');
    
    columnsHtml += `
      <div class="column" data-status="${status}">
        <div class="column-header">
          <h3><i class="fas ${icon}"></i> ${statusLabel}</h3>
          <span class="task-count">${tasks.length}</span>
        </div>
        <div class="tasks-container" data-status="${status}">
    `;
    
    if (tasks.length === 0) {
      columnsHtml += `
        <div class="empty-column">
          <i class="fas fa-inbox"></i>
          <p>No tasks</p>
        </div>
      `;
    } else {
      tasks.forEach(task => {
        const category = CATEGORY_CONFIG[task.category] || CATEGORY_CONFIG.uiux;
        columnsHtml += `
          <div class="task" data-task-id="${task.id}" draggable="true">
            <div class="task-header">
              <span class="task-name">${escapeHtml(task.name)}</span>
              <span class="task-category category-${category.class}">${category.name}</span>
            </div>
            <div class="progress-bar-container">
              <div class="progress-label">
                <span>Progress</span>
                <span>${task.progress}%</span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill ${task.progress === 100 ? 'full' : ''}" style="width: ${task.progress}%"></div>
              </div>
            </div>
            <div class="task-actions">
              <button class="task-action-btn increment-progress" data-id="${task.id}">
                <i class="fas fa-plus-circle"></i> +10%
              </button>
              <button class="task-action-btn decrement-progress" data-id="${task.id}">
                <i class="fas fa-minus-circle"></i> -10%
              </button>
              <button class="task-action-btn delete-task" data-id="${task.id}">
                <i class="fas fa-trash-alt"></i> Delete
              </button>
            </div>
          </div>
        `;
      });
    }
    
    columnsHtml += `
        </div>
        <button class="add-task-btn" data-status="${status}">
          <i class="fas fa-plus"></i> Add Task
        </button>
      </div>
    `;
  });
  
  columnsHtml += `
    <div class="chart-section">
      <div class="chart-title">
        <i class="fas fa-chart-pie"></i> Category Distribution
      </div>
      <div class="chart-circle" id="chartCircle"></div>
      <div class="legend">
        <div class="legend-item">
          <div class="legend-left">
            <div class="legend-color uiux"></div>
            <span>UI/UX</span>
          </div>
          <span class="legend-value" id="legendUIUX">0%</span>
        </div>
        <div class="legend-item">
          <div class="legend-left">
            <div class="legend-color frontend"></div>
            <span>Frontend</span>
          </div>
          <span class="legend-value" id="legendFrontend">0%</span>
        </div>
        <div class="legend-item">
          <div class="legend-left">
            <div class="legend-color backend"></div>
            <span>Backend</span>
          </div>
          <span class="legend-value" id="legendBackend">0%</span>
        </div>
      </div>
    </div>
  `;
  
  container.innerHTML = columnsHtml;
  attachTaskEventListeners();
  attachDragAndDrop();
}

function updateStats(projectId) {
  const overallProgress = calculateOverallProgress(projectId);
  const statsSpan = document.getElementById('overallProgress');
  if (statsSpan) statsSpan.textContent = overallProgress;
}

function attachTaskEventListeners() {
  document.querySelectorAll('.increment-progress').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const taskId = btn.getAttribute('data-id');
      const task = findTaskById(taskId);
      if (task) updateTaskProgress(taskId, Math.min(100, task.progress + 10));
    });
  });
  
  document.querySelectorAll('.decrement-progress').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const taskId = btn.getAttribute('data-id');
      const task = findTaskById(taskId);
      if (task) updateTaskProgress(taskId, Math.max(0, task.progress - 10));
    });
  });
  
  document.querySelectorAll('.delete-task').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const taskId = btn.getAttribute('data-id');
      deleteTask(taskId);
    });
  });
  
  document.querySelectorAll('.add-task-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const status = btn.getAttribute('data-status');
      openAddTaskModal(status);
    });
  });
}

// ==================== MODAL ====================
let currentModalStatus = null;

function openAddTaskModal(status) {
  currentModalStatus = status;
  const modal = document.getElementById('taskModal');
  const modalTitle = modal.querySelector('h3');
  modalTitle.innerHTML = `<i class="fas fa-plus-circle"></i> Add Task to ${STATUS_LABELS[status]}`;
  document.getElementById('taskName').value = '';
  document.getElementById('taskCategory').value = 'uiux';
  document.getElementById('taskColumn').value = status;
  modal.style.display = 'flex';
  setTimeout(() => document.getElementById('taskName').focus(), 100);
}

function closeModal() {
  document.getElementById('taskModal').style.display = 'none';
  currentModalStatus = null;
}

function handleSaveTask() {
  const taskName = document.getElementById('taskName').value.trim();
  const category = document.getElementById('taskCategory').value;
  const status = document.getElementById('taskColumn').value;
  
  if (!taskName) {
    showToast('Please enter a task name', 'error');
    return;
  }
  
  addTask(currentProjectId, taskName, category, status);
  closeModal();
}

// ==================== TOAST ====================
let toastTimeout = null;

function showToast(message, type = 'success') {
  const toast = document.getElementById('toastMsg');
  const toastText = document.getElementById('toastText');
  const iconElem = toast.querySelector('i');
  
  toastText.textContent = message;
  if (type === 'success') {
    iconElem.className = 'fas fa-check-circle';
    iconElem.style.color = '#10b981';
  } else if (type === 'error') {
    iconElem.className = 'fas fa-exclamation-triangle';
    iconElem.style.color = '#f97316';
  } else {
    iconElem.className = 'fas fa-info-circle';
    iconElem.style.color = '#3b82f6';
  }
  
  toast.classList.add('show');
  if (toastTimeout) clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => {
    toast.classList.remove('show');
  }, 2500);
}

// ==================== PROJECT SELECTOR ====================
function initProjectSelector() {
  const select = document.getElementById('projectSelect');
  if (!select) return;
  
  // Clear existing options
  select.innerHTML = '';
  
  // Prefer real projects from PROJECTS object, fallback to projectsData
  const projectsList = Object.keys(PROJECTS).length > 0 
    ? Object.entries(PROJECTS) 
    : Object.entries(projectsData).map(([id, data]) => [id, data.name]);
  
  // Populate dropdown
  projectsList.forEach(([projectId, projectName]) => {
    const option = document.createElement('option');
    option.value = projectId;
    option.textContent = projectName;
    select.appendChild(option);
  });
  
  // Set current value
  const savedProjectId = localStorage.getItem(STORAGE_SELECTED_PROJECT_KEY);
  if (savedProjectId && select.querySelector(`option[value="${savedProjectId}"]`)) {
    select.value = savedProjectId;
    currentProjectId = savedProjectId;
  } else {
    // Fallback to first available project
    if (select.options.length > 0) {
      select.value = select.options[0].value;
      currentProjectId = select.options[0].value;
    }
  }
  
  updateSelectedProjectName(currentProjectId);
  
  select.addEventListener('change', (e) => {
    currentProjectId = e.target.value;
    saveSelectedProject(currentProjectId);
    // Ensure project exists in projectsData, if not create placeholder
    if (!projectsData[currentProjectId]) {
      projectsData[currentProjectId] = {
        name: PROJECTS[currentProjectId] || 'Untitled Project',
        tasks: []
      };
    }
    loadFromLocalStorage(currentProjectId);
    renderCurrentProject();
  });
}

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', () => {
  loadProjectsFromStorage();
  initProjectSelector();
  loadFromLocalStorage(currentProjectId);
  renderCurrentProject();
  
  const closeBtn = document.getElementById('closeModalBtn');
  const cancelBtn = document.getElementById('cancelModalBtn');
  const saveBtn = document.getElementById('saveTaskBtn');
  const modal = document.getElementById('taskModal');
  
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
  if (saveBtn) saveBtn.addEventListener('click', handleSaveTask);
  
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
  }
  
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
      closeModal();
    }
  });
  
  // Roadmap placeholder
  const navRoadmap = document.getElementById('navRoadmap');
  if (navRoadmap) {
    navRoadmap.addEventListener('click', (e) => {
      e.preventDefault();
      showToast('Roadmap planner coming soon!', 'info');
    });
  }
});