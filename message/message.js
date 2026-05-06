// SHIAGARI - Chat Module

// Chat data storage
let conversations = {
  'Vince Villar': {
    avatar: 'VV',
    messages: [
      { text: 'Hello!', sender: 'vince', time: '10:30 AM' },
      { text: 'Hi there! 👋', sender: 'me', time: '10:31 AM' },
      { text: 'How is the project coming along?', sender: 'vince', time: '10:32 AM' },
      { text: 'Going great! Just finished the dashboard.', sender: 'me', time: '10:33 AM' },
      { text: 'Awesome! Can you share a preview?', sender: 'vince', time: '10:34 AM' }
    ]
  },
  'Ayelet De Castro': {
    avatar: 'AD',
    messages: [
      { text: 'Great work on the design!', sender: 'ayelet', time: '9:15 AM' },
      { text: 'Thank you! Glad you like it.', sender: 'me', time: '9:16 AM' },
      { text: 'When can we review the final version?', sender: 'ayelet', time: '9:17 AM' }
    ]
  }
};

let currentUser = 'Vince Villar';
let isDragging = false;
let dragOffsetX, dragOffsetY;

// Load messages from localStorage
function loadMessages() {
  const stored = localStorage.getItem('shiagari_chat_messages');
  if (stored) {
    conversations = JSON.parse(stored);
  } else {
    saveMessages();
  }
}

function saveMessages() {
  localStorage.setItem('shiagari_chat_messages', JSON.stringify(conversations));
}

// Render current chat messages
function renderMessages() {
  const chatBody = document.getElementById('chatBody');
  if (!chatBody) return;
  
  const messages = conversations[currentUser]?.messages || [];
  
  chatBody.innerHTML = messages.map(msg => {
    const isMe = msg.sender === 'me';
    const avatarInitial = isMe ? 'ME' : (conversations[currentUser]?.avatar || currentUser.charAt(0));
    
    return `
      <div class="msg ${isMe ? 'right' : 'left'}">
        ${!isMe ? `<div class="msg-avatar">${avatarInitial}</div>` : ''}
        <div class="msg-content">
          <div class="msg-text">${escapeHtml(msg.text)}</div>
          <div class="msg-time">${msg.time}</div>
        </div>
      </div>
    `;
  }).join('');
  
  // Scroll to bottom
  chatBody.scrollTop = chatBody.scrollHeight;
}

// Add a new message
function addMessage(text) {
  if (!text.trim()) return;
  
  const now = new Date();
  const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  
  if (!conversations[currentUser]) {
    conversations[currentUser] = { avatar: currentUser.charAt(0), messages: [] };
  }
  
  conversations[currentUser].messages.push({
    text: text.trim(),
    sender: 'me',
    time: time
  });
  
  saveMessages();
  renderMessages();
  
  // Simulate reply after 1 second (for demo)
  setTimeout(() => simulateReply(), 1000);
}

// Simulate auto-reply
function simulateReply() {
  const replies = [
    "That's interesting! Tell me more.",
    "Thanks for sharing! 👍",
    "I'll look into that.",
    "Great job! Keep it up!",
    "Let's discuss this in the next meeting.",
    "I appreciate your hard work!"
  ];
  
  const randomReply = replies[Math.floor(Math.random() * replies.length)];
  const now = new Date();
  const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  
  conversations[currentUser].messages.push({
    text: randomReply,
    sender: 'other',
    time: time
  });
  
  saveMessages();
  renderMessages();
  showToast(`New message from ${currentUser}`);
}

// Switch conversation
function switchConversation(userName) {
  currentUser = userName;
  
  // Update active state in chat list
  document.querySelectorAll('.chat-user').forEach(el => {
    el.classList.remove('active');
    if (el.getAttribute('data-user') === userName) {
      el.classList.add('active');
    }
  });
  
  // Update chat header
  const avatarUrl = `https://ui-avatars.com/api/?background=${userName === 'Vince Villar' ? '3b82f6' : '10b981'}&color=fff&name=${userName.split(' ').map(n => n[0]).join('')}`;
  document.getElementById('currentAvatar').src = avatarUrl;
  document.getElementById('currentUserName').textContent = userName;
  
  renderMessages();
}

// Drag functionality
const chatWindow = document.getElementById('chatWindow');
const dragHandle = document.getElementById('dragHandle');

function initDrag() {
  if (!chatWindow || !dragHandle) return;
  
  dragHandle.addEventListener('mousedown', (e) => {
    if (e.target.closest('.chat-header-actions')) return;
    isDragging = true;
    const rect = chatWindow.getBoundingClientRect();
    dragOffsetX = e.clientX - rect.left;
    dragOffsetY = e.clientY - rect.top;
    chatWindow.style.position = 'fixed';
    chatWindow.style.cursor = 'grabbing';
  });
  
  document.addEventListener('mousemove', (e) => {
    if (!isDragging) return;
    let newLeft = e.clientX - dragOffsetX;
    let newTop = e.clientY - dragOffsetY;
    
    // Keep within viewport bounds
    newLeft = Math.max(0, Math.min(newLeft, window.innerWidth - chatWindow.offsetWidth));
    newTop = Math.max(0, Math.min(newTop, window.innerHeight - chatWindow.offsetHeight));
    
    chatWindow.style.left = newLeft + 'px';
    chatWindow.style.top = newTop + 'px';
    chatWindow.style.right = 'auto';
    chatWindow.style.bottom = 'auto';
  });
  
  document.addEventListener('mouseup', () => {
    isDragging = false;
    if (chatWindow) chatWindow.style.cursor = '';
  });
}

// Minimize functionality
function initMinimize() {
  const minBtn = document.getElementById('minBtn');
  if (minBtn) {
    minBtn.addEventListener('click', () => {
      chatWindow.classList.toggle('minimized');
      const icon = minBtn.querySelector('i');
      if (chatWindow.classList.contains('minimized')) {
        icon.className = 'fas fa-window-maximize';
      } else {
        icon.className = 'fas fa-minus';
      }
    });
  }
}

// Send message
function initSendMessage() {
  const sendBtn = document.getElementById('sendBtn');
  const messageInput = document.getElementById('messageInput');
  
  if (sendBtn) {
    sendBtn.addEventListener('click', () => {
      addMessage(messageInput.value);
      messageInput.value = '';
      messageInput.focus();
    });
  }
  
  if (messageInput) {
    messageInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        addMessage(messageInput.value);
        messageInput.value = '';
      }
    });
  }
}

// Toast notification
let toastTimeout = null;

function showToast(message) {
  // Create toast if doesn't exist
  let toast = document.getElementById('chatToast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'chatToast';
    toast.className = 'toast';
    toast.innerHTML = '<i class="fas fa-comment"></i><span id="chatToastText"></span>';
    document.body.appendChild(toast);
    
    // Add styles for this specific toast
    toast.style.position = 'fixed';
    toast.style.bottom = '30px';
    toast.style.right = '30px';
    toast.style.background = '#1e293b';
    toast.style.padding = '12px 20px';
    toast.style.borderRadius = '40px';
    toast.style.display = 'flex';
    toast.style.alignItems = 'center';
    toast.style.gap = '10px';
    toast.style.fontSize = '14px';
    toast.style.fontWeight = '500';
    toast.style.borderLeft = '4px solid #3b82f6';
    toast.style.boxShadow = '0 10px 20px rgba(0,0,0,0.3)';
    toast.style.transform = 'translateX(400px)';
    toast.style.transition = 'transform 0.3s ease';
    toast.style.zIndex = '1100';
  }
  
  const toastText = document.getElementById('chatToastText');
  if (toastText) toastText.textContent = message;
  
  toast.classList.add('show');
  toast.style.transform = 'translateX(0)';
  
  if (toastTimeout) clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => {
    toast.style.transform = 'translateX(400px)';
  }, 2500);
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

// Initialize chat list click handlers
function initChatList() {
  document.querySelectorAll('.chat-user').forEach(user => {
    user.addEventListener('click', () => {
      const userName = user.getAttribute('data-user');
      if (userName) switchConversation(userName);
    });
  });
}

// Set initial position for chat window
function setInitialPosition() {
  if (chatWindow) {
    chatWindow.style.position = 'fixed';
    chatWindow.style.bottom = '20px';
    chatWindow.style.right = '20px';
    chatWindow.style.left = 'auto';
    chatWindow.style.top = 'auto';
  }
}

// Initialize everything
document.addEventListener('DOMContentLoaded', () => {
  loadMessages();
  renderMessages();
  initDrag();
  initMinimize();
  initSendMessage();
  initChatList();
  setInitialPosition();
});