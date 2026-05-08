// SHIAGARI - Post Board

let posts = [];

function loadPosts() {
  const stored = localStorage.getItem('shiagari_posts');
  if (stored) {
    posts = JSON.parse(stored);
  } else {
    posts = [
      {
        id: 'p1',
        author: 'Sean Arkin Balmes',
        role: 'Project Manager',
        avatar: 'SB',
        content: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
        isAnnouncement: true,
        announcementTitle: 'ANNOUNCEMENT',
        likes: 12,
        comments: 3,
        timestamp: Date.now() - 86400000,
        likedBy: []
      },
      {
        id: 'p2',
        author: 'Sean Arkin Balmes',
        role: 'Project Manager',
        avatar: 'SB',
        content: 'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
        isAnnouncement: false,
        announcementTitle: null,
        likes: 5,
        comments: 1,
        timestamp: Date.now() - 172800000,
        likedBy: []
      }
    ];
    savePosts();
  }
  updatePostCount();
  renderPosts();
}

function savePosts() {
  localStorage.setItem('shiagari_posts', JSON.stringify(posts));
  updatePostCount();
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
  const post = posts.find(p => p.id === postId);
  if (!post) return;
  
  if (!post.likedBy) post.likedBy = [];
  
  const userLiked = post.likedBy.includes('current_user');
  
  if (userLiked) {
    post.likedBy = post.likedBy.filter(id => id !== 'current_user');
    post.likes = Math.max(0, post.likes - 1);
    showToast('Removed like', 'info');
  } else {
    post.likedBy.push('current_user');
    post.likes++;
    showToast(`Liked "${post.content.substring(0, 30)}..."`, 'success');
  }
  
  savePosts();
  renderPosts();
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