// SHIAGARI - Idea Factory

let ideas = [];
let editId = null;

const CATEGORIES = {
  sketch: { icon: 'fa-paintbrush', label: 'Sketch' },
  flowchart: { icon: 'fa-diagram-project', label: 'Flowchart' },
  color: { icon: 'fa-palette', label: 'Color Palette' },
  design: { icon: 'fa-pen-ruler', label: 'Design' },
  tech: { icon: 'fa-microchip', label: 'Tech' },
  business: { icon: 'fa-chart-line', label: 'Business' }
};

function saveToStorage() {
  localStorage.setItem('shiagari_ideas', JSON.stringify(ideas));
  document.getElementById('ideaCount').textContent = ideas.length;
}

function loadFromStorage() {
  const stored = localStorage.getItem('shiagari_ideas');
  if (stored) {
    ideas = JSON.parse(stored);
  } else {
    ideas = [
      { id: 'i1', title: 'Neumorphic Design System', description: 'Complete UI kit with soft shadows.', category: 'design', author: 'Alex Chen', likes: 12, createdAt: Date.now() },
      { id: 'i2', title: 'AI Storyboard Generator', description: 'Generate storyboards from text prompts.', category: 'sketch', author: 'Jamie L.', likes: 8, createdAt: Date.now() },
      { id: 'i3', title: 'Data Flow Visualizer', description: 'Interactive flowchart tool.', category: 'flowchart', author: 'Taylor W.', likes: 15, createdAt: Date.now() },
      { id: 'i4', title: 'Sunset Gradient Palette', description: 'Warm gradients for UI.', category: 'color', author: 'Morgan K.', likes: 23, createdAt: Date.now() }
    ];
    saveToStorage();
  }
  document.getElementById('ideaCount').textContent = ideas.length;
}

function escape(str) {
  if (!str) return '';
  return str.replace(/[&<>]/g, m => m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;');
}

function showToast(message, isError = false) {
  const toast = document.getElementById('toastMsg');
  const icon = toast.querySelector('i');
  icon.className = isError ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle';
  icon.style.color = isError ? '#f97316' : '#10b981';
  document.getElementById('toastText').textContent = message;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 2500);
}

function render() {
  const grid = document.getElementById('ideaGrid');
  if (!ideas.length) {
    grid.innerHTML = `<div class="empty-state"><i class="fas fa-lightbulb"></i><p>No ideas yet. Click + to create one!</p></div>`;
    return;
  }

  grid.innerHTML = ideas.map(idea => {
    const cat = CATEGORIES[idea.category];
    return `
      <div class="idea-card" data-id="${idea.id}" data-category="${idea.category}">
        <div class="idea-image"><i class="fas ${cat.icon}"></i></div>
        <div class="idea-content">
          <div class="idea-title">${escape(idea.title)}</div>
          <div class="idea-desc">${escape(idea.description || 'No description')}</div>
          <span class="tag ${idea.category}"><i class="fas ${cat.icon}"></i> ${cat.label}</span>
          <div class="idea-footer">
            <div class="author-info"><i class="fas fa-user-circle"></i> ${escape(idea.author)}</div>
            <div class="idea-actions">
              <span class="like-idea" data-id="${idea.id}"><i class="fas fa-heart"></i> ${idea.likes}</span>
              <span class="delete-idea" data-id="${idea.id}"><i class="fas fa-trash-alt"></i></span>
            </div>
          </div>
        </div>
      </div>
    `;
  }).join('');

  document.querySelectorAll('.idea-card').forEach(card => {
    card.addEventListener('click', (e) => {
      if (e.target.closest('.like-idea') || e.target.closest('.delete-idea')) return;
      showDetail(card.dataset.id);
    });
  });

  document.querySelectorAll('.like-idea').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.dataset.id;
      const idea = ideas.find(i => i.id === id);
      if (idea) { idea.likes++; saveToStorage(); render(); showToast(`❤️ Liked "${idea.title}"`); }
    });
  });

  document.querySelectorAll('.delete-idea').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const id = btn.dataset.id;
      const idea = ideas.find(i => i.id === id);
      if (idea && confirm(`Delete "${idea.title}"?`)) {
        ideas = ideas.filter(i => i.id !== id);
        saveToStorage();
        render();
        showToast(`🗑️ "${idea.title}" deleted`);
      }
    });
  });
}

function showDetail(id) {
  const idea = ideas.find(i => i.id === id);
  if (!idea) return;
  const cat = CATEGORIES[idea.category];
  document.getElementById('detailTitle').innerHTML = `<i class="fas ${cat.icon}"></i> ${escape(idea.title)}`;
  document.getElementById('detailBody').innerHTML = `
    <div class="detail-field"><div class="detail-label">Category</div><div class="detail-value"><span class="tag ${idea.category}"><i class="fas ${cat.icon}"></i> ${cat.label}</span></div></div>
    <div class="detail-field"><div class="detail-label">Description</div><div class="detail-value">${escape(idea.description) || 'No description.'}</div></div>
    <div class="detail-field"><div class="detail-label">Author</div><div class="detail-value"><i class="fas fa-user"></i> ${escape(idea.author)}</div></div>
    <div class="detail-field"><div class="detail-label">Stats</div><div class="detail-value"><i class="fas fa-heart" style="color:#ef4444"></i> ${idea.likes} likes</div></div>
    <div class="detail-field"><div class="detail-label">Created</div><div class="detail-value">${new Date(idea.createdAt).toLocaleDateString()}</div></div>
  `;
  document.getElementById('detailModal').style.display = 'flex';
  document.getElementById('editFromDetailBtn').onclick = () => {
    document.getElementById('detailModal').style.display = 'none';
    openModal(id);
  };
}

function closeDetailModal() { document.getElementById('detailModal').style.display = 'none'; }

function openModal(id = null) {
  editId = id;
  const idea = id ? ideas.find(i => i.id === id) : null;
  document.getElementById('modalTitle').innerHTML = id ? '<i class="fas fa-edit"></i> Edit Idea' : '<i class="fas fa-lightbulb"></i> New Idea';
  document.getElementById('saveIdeaBtn').textContent = id ? 'Update Idea' : 'Save Idea';
  document.getElementById('ideaTitle').value = idea?.title || '';
  document.getElementById('ideaDesc').value = idea?.description || '';
  document.getElementById('ideaCategory').value = idea?.category || 'design';
  document.getElementById('ideaAuthor').value = idea?.author || 'Creative User';
  document.getElementById('ideaModal').style.display = 'flex';
}

function closeModal() { document.getElementById('ideaModal').style.display = 'none'; editId = null; }

function saveIdea() {
  const title = document.getElementById('ideaTitle').value.trim();
  if (!title) { showToast('Title required', true); return; }
  const ideaData = {
    title,
    description: document.getElementById('ideaDesc').value.trim(),
    category: document.getElementById('ideaCategory').value,
    author: document.getElementById('ideaAuthor').value.trim() || 'Anonymous'
  };
  if (editId) {
    const index = ideas.findIndex(i => i.id === editId);
    if (index !== -1) { ideas[index] = { ...ideas[index], ...ideaData }; showToast(`📝 "${title}" updated`); }
  } else {
    ideas.unshift({ id: Date.now().toString(), ...ideaData, likes: 0, createdAt: Date.now() });
    showToast(`✨ "${title}" added!`);
  }
  saveToStorage();
  render();
  closeModal();
}

function init() {
  loadFromStorage();
  render();
  document.getElementById('floatingAddBtn').onclick = () => openModal();
  document.getElementById('saveIdeaBtn').onclick = saveIdea;
  document.getElementById('closeModalBtn')?.addEventListener('click', closeModal);
  document.getElementById('cancelModalBtn')?.addEventListener('click', closeModal);
  document.getElementById('closeDetailBtn')?.addEventListener('click', closeDetailModal);
  document.getElementById('closeDetailModalBtn')?.addEventListener('click', closeDetailModal);
  document.getElementById('ideaModal')?.addEventListener('click', (e) => { if (e.target === document.getElementById('ideaModal')) closeModal(); });
  document.getElementById('detailModal')?.addEventListener('click', (e) => { if (e.target === document.getElementById('detailModal')) closeDetailModal(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (document.getElementById('ideaModal').style.display === 'flex') closeModal();
      if (document.getElementById('detailModal').style.display === 'flex') closeDetailModal();
    }
  });
  document.getElementById('navProgress')?.addEventListener('click', (e) => { e.preventDefault(); showToast('Progress Tracker coming soon!'); });
  document.getElementById('navRoadmap')?.addEventListener('click', (e) => { e.preventDefault(); showToast('Roadmap planner coming soon!'); });
}

init();