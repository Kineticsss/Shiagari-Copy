// SHIAGARI - Post Board

let posts = [];
let currentProjectId = null;

// ── Project selector ──────────────────────────────────────────────────────────

async function initProjectSelector() {
  const select = document.getElementById('projectSelect');
  if (!select) return;

  try {
    const result = await shiagariAPI.getProjects();
    const projects = result.success ? (result.projects ?? []) : [];

    if (projects.length === 0) {
      select.innerHTML = '<option value="">No projects found</option>';
      return;
    }

    // Restore previously selected project
    const savedId = localStorage.getItem('shiagari_selected_project');

    select.innerHTML = projects
      .map(p => `<option value="${escapeAttr(p.id)}">${escapeHtml(p.name)}</option>`)
      .join('');

    if (savedId && select.querySelector(`option[value="${savedId}"]`)) {
      select.value = savedId;
    }

    currentProjectId = select.value;
    await loadPosts();

    select.addEventListener('change', async (e) => {
      currentProjectId = e.target.value;
      localStorage.setItem('shiagari_selected_project', currentProjectId);
      await loadPosts();
    });
  } catch (err) {
    console.error('initProjectSelector:', err);
    select.innerHTML = '<option value="">Could not load projects</option>';
  }
}

// ── Load posts ────────────────────────────────────────────────────────────────

async function loadPosts() {
  posts = [];
  updatePostCount();

  if (!currentProjectId) {
    renderPosts();
    return;
  }

  try {
    const res = await shiagariAPI.getPosts(currentProjectId);
    if (res && res.success) {
      posts = res.posts ?? [];
    } else {
      showToast(res.error || 'Could not load posts', 'error');
    }
  } catch (err) {
    console.error('loadPosts:', err);
    showToast('Error loading posts', 'error');
  }

  updatePostCount();
  renderPosts();
}

// ── Render ────────────────────────────────────────────────────────────────────

function updatePostCount() {
  const span = document.getElementById('postCount');
  if (span) span.textContent = posts.length;
}

function formatTime(timestamp) {
  const date = new Date(timestamp);
  const now  = new Date();
  const diff = now - date;

  if (diff < 3_600_000) {
    const m = Math.floor(diff / 60_000);
    return `${m} minute${m !== 1 ? 's' : ''} ago`;
  }
  if (diff < 86_400_000) {
    const h = Math.floor(diff / 3_600_000);
    return `${h} hour${h !== 1 ? 's' : ''} ago`;
  }
  return date.toLocaleDateString();
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>]/g, m =>
    m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;'
  );
}

function escapeAttr(str) {
  if (!str) return '';
  return String(str).replace(/["']/g, c => c === '"' ? '&quot;' : '&#39;');
}

function renderPosts() {
  const container = document.getElementById('postsContainer');
  if (!container) return;

  if (posts.length === 0) {
    container.innerHTML = `
      <div class="empty-posts">
        <i class="fas fa-newspaper"></i>
        <p>No posts yet. Be the first to share something!</p>
      </div>`;
    return;
  }

  const sorted = [...posts].sort((a, b) =>
    new Date(b.createdAt ?? b.timestamp ?? 0) - new Date(a.createdAt ?? a.timestamp ?? 0)
  );

  container.innerHTML = sorted.map(post => {
    const author    = escapeHtml(post.author ?? post.uid ?? 'Unknown');
    const initials  = (post.author ?? 'U').trim().split(/\s+/).map(w => w[0]).join('').toUpperCase().slice(0, 2);
    const avatarUrl = `https://ui-avatars.com/api/?background=3b82f6&color=fff&name=${encodeURIComponent(initials)}`;
    const time      = formatTime(post.createdAt ?? post.timestamp ?? Date.now());

    return `
      <div class="post" data-id="${escapeAttr(post.id)}">
        <div class="post-header">
          <img src="${avatarUrl}" alt="${author}" class="post-avatar">
          <div class="post-user-info">
            <div class="post-author">${author}</div>
            <span class="post-time">${time}</span>
          </div>
        </div>
        ${post.isAnnouncement
          ? `<div class="post-announcement-badge"><i class="fas fa-bullhorn"></i> ${escapeHtml(post.announcementTitle || 'ANNOUNCEMENT')}</div>`
          : ''}
        <div class="post-content">${escapeHtml(post.content)}</div>
        <div class="post-actions">
          <button class="post-action-btn like-btn" data-id="${escapeAttr(post.id)}">
            <i class="fas fa-heart"></i> ${post.likes ?? 0} Likes
          </button>
          <button class="post-action-btn comment-btn" data-id="${escapeAttr(post.id)}">
            <i class="fas fa-comment"></i> ${post.comments ?? 0} Comments
          </button>
        </div>
      </div>`;
  }).join('');

  container.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', () => showToast('Like feature coming soon!', 'info'));
  });
  container.querySelectorAll('.comment-btn').forEach(btn => {
    btn.addEventListener('click', () => showToast('Comments coming soon!', 'info'));
  });
}

// ── Create post ───────────────────────────────────────────────────────────────

async function addPost(content) {
  if (!content || !content.trim()) {
    showToast('Please enter some content', 'error');
    return false;
  }

  if (!currentProjectId) {
    showToast('Please select a project first', 'error');
    return false;
  }

  try {
    const result = await shiagariAPI.createPost(currentProjectId, content.trim());
    if (result.success) {
      posts.unshift(result.post);
      updatePostCount();
      renderPosts();
      showToast('Post published!', 'success');
      return true;
    } else {
      showToast(result.error || 'Failed to post', 'error');
      return false;
    }
  } catch (err) {
    console.error('addPost:', err);
    showToast('Error publishing post', 'error');
    return false;
  }
}

// ── Toast ─────────────────────────────────────────────────────────────────────

let toastTimeout = null;

function showToast(message, type = 'success') {
  const toast     = document.getElementById('toastMsg');
  const toastText = document.getElementById('toastText');
  const icon      = toast.querySelector('i');

  toastText.textContent = message;
  icon.className =
    type === 'error' ? 'fas fa-exclamation-triangle' :
    type === 'info'  ? 'fas fa-info-circle' :
                       'fas fa-check-circle';
  icon.style.color =
    type === 'error' ? '#f97316' :
    type === 'info'  ? '#3b82f6' :
                       '#10b981';

  toast.classList.add('show');
  if (toastTimeout) clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => toast.classList.remove('show'), 2500);
}

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', async () => {
  // Init API client with CSRF token injected by PHP
  if (typeof csrfToken !== 'undefined') {
    shiagariAPI.init(csrfToken);
  }

  // Show logged-in user's initial on the profile button
  if (typeof currentUserName !== 'undefined') {
    const initial = document.getElementById('profileInitial');
    if (initial) {
      initial.textContent = (currentUserName || 'U')
        .trim().split(/\s+/).map(w => w[0]).join('').toUpperCase().slice(0, 2) || 'U';
    }
  }

  await initProjectSelector();

  const postBtn   = document.getElementById('postBtn');
  const postInput = document.getElementById('postInput');

  postBtn?.addEventListener('click', async () => {
    const content = postInput.value;
    const ok = await addPost(content);
    if (ok) postInput.value = '';
  });

  postInput?.addEventListener('keypress', async (e) => {
    if (e.key === 'Enter') {
      const content = postInput.value;
      const ok = await addPost(content);
      if (ok) postInput.value = '';
    }
  });
});