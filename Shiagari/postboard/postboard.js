// SHIAGARI - Post Board

let posts = [];
let currentProjectId = null;

async function loadPosts() {
  posts = [];

  // Post board is project-scoped
  const projectSelect = document.getElementById('projectSelect');
  if (projectSelect) {
    currentProjectId = projectSelect.value;
  }

  if (!currentProjectId) {
    // If there is no selector, fall back to reading project_id from the URL
    currentProjectId = new URLSearchParams(window.location.search).get('project_id');
  }

  if (!currentProjectId) {
    updatePostCount();
    renderPosts();
    return;
  }

  try {
    const res = await shiagariAPI.getPosts(currentProjectId);
    if (res && res.success) {
      posts = res.posts ?? [];
    }
  } catch (err) {
    console.error('loadPosts:', err);
    posts = [];
  }

  updatePostCount();
  renderPosts();
}


function updatePostCount() {
  const countSpan = document.getElementById('postCount');
  if (countSpan) countSpan.textContent = posts.length;
}

function formatTime(timestamp) {
  const date = new Date(timestamp);
  const now = new Date();
  const diff = now - date;
  
  if (diff < 3600000) {
    const minutes = Math.floor(diff / 60000);
    return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`;
  } else if (diff < 86400000) {
    const hours = Math.floor(diff / 3600000);
    return `${hours} hour${hours !== 1 ? 's' : ''} ago`;
  } else {
    return date.toLocaleDateString();
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

function renderPosts() {
  const container = document.getElementById('postsContainer');
  if (!container) return;
  
  if (posts.length === 0) {
    container.innerHTML = `
      <div class="empty-posts">
        <i class="fas fa-newspaper"></i>
        <p>No posts yet. Be the first to share something!</p>
      </div>
    `;
    return;
  }
  
  const sortedPosts = [...posts].sort((a, b) => b.timestamp - a.timestamp);
  
  container.innerHTML = sortedPosts.map(post => {
    const isLikedByCurrentUser = post.likedBy && post.likedBy.includes('current_user');
    const avatarUrl = `https://ui-avatars.com/api/?background=3b82f6&color=fff&name=${post.avatar}`;
    
    return `
      <div class="post" data-id="${post.id}">
        <div class="post-header">
          <img src="${avatarUrl}" alt="${escapeHtml(post.author)}" class="post-avatar">
          <div class="post-user-info">
            <div class="post-author">
              ${escapeHtml(post.author)}
              <span class="post-role">${escapeHtml(post.role)}</span>
            </div>
            <span class="post-time">${formatTime(post.timestamp)}</span>
          </div>
        </div>
        ${post.isAnnouncement ? `<div class="post-announcement-badge"><i class="fas fa-bullhorn"></i> ${escapeHtml(post.announcementTitle || 'ANNOUNCEMENT')}</div>` : ''}
        ${post.announcementTitle && !post.isAnnouncement ? `<div class="post-title">${escapeHtml(post.announcementTitle)}</div>` : ''}
        <div class="post-content">${escapeHtml(post.content)}</div>
        <div class="post-actions">
          <button class="post-action-btn like-btn ${isLikedByCurrentUser ? 'liked' : ''}" data-id="${post.id}">
            <i class="fas fa-heart"></i> ${post.likes} Likes
          </button>
          <button class="post-action-btn comment-btn" data-id="${post.id}">
            <i class="fas fa-comment"></i> ${post.comments} Comments
          </button>
        </div>
      </div>
    `;
  }).join('');
  
  // Attach event listeners
  document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const postId = btn.getAttribute('data-id');
      handleLike(postId);
    });
  });
  
  document.querySelectorAll('.comment-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const postId = btn.getAttribute('data-id');
      showToast('Comments feature coming soon!', 'info');
    });
  });
}

function handleLike(postId) {
  // Firestore-backed like/comment endpoints are not implemented in this repo yet.
  // Keep UI responsive without persisting local changes.
  showToast('Like feature coming soon!', 'info');
}


function addPost(content, isAnnouncement = false, announcementTitle = null) {
  if (!content || !content.trim()) {
    showToast('Please enter some content', 'error');
    return false;
  }
  
  const newPost = {
    id: Date.now().toString() + '-' + Math.random().toString(36).substr(2, 6),
    author: 'You',
    role: 'Team Member',
    avatar: 'ME',
    content: content.trim(),
    isAnnouncement: isAnnouncement,
    announcementTitle: announcementTitle,
    likes: 0,
    comments: 0,
    timestamp: Date.now(),
    likedBy: []
  };
  
  posts.unshift(newPost);
  savePosts();
  renderPosts();
  showToast('Post published!', 'success');
  return true;
}

function showToast(message, type = 'success') {
  let toast = document.getElementById('postToast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'postToast';
    toast.className = 'toast';
    toast.innerHTML = '<i class="fas"></i><span id="postToastText"></span>';
    document.body.appendChild(toast);
  }
  
  const icon = toast.querySelector('i');
  const toastText = document.getElementById('postToastText');
  
  toastText.textContent = message;
  if (type === 'success') {
    icon.className = 'fas fa-check-circle';
    icon.style.color = '#10b981';
  } else if (type === 'error') {
    icon.className = 'fas fa-exclamation-triangle';
    icon.style.color = '#f97316';
  } else {
    icon.className = 'fas fa-info-circle';
    icon.style.color = '#3b82f6';
  }
  
  toast.classList.add('show');
  setTimeout(() => {
    toast.classList.remove('show');
  }, 2500);
}

// Initialize post creation
function initPostCreation() {
  const postBtn = document.getElementById('postBtn');
  const postInput = document.getElementById('postInput');
  
  if (postBtn) {
    postBtn.addEventListener('click', () => {
      addPost(postInput.value, false, null);
      postInput.value = '';
      postInput.focus();
    });
  }
  
  if (postInput) {
    postInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        addPost(postInput.value, false, null);
        postInput.value = '';
      }
    });
  }
}

// Initialize everything
document.addEventListener('DOMContentLoaded', () => {
  loadPosts();
  initPostCreation();
});