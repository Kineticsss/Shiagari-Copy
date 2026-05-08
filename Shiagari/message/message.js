// SHIAGARI - Real-time Chat via Firebase Realtime Database
// Fetches Firebase config from PHP session, then uses Firebase RTDB for messages.

'use strict';

/* ─────────────────────────────────────────────
   STATE
───────────────────────────────────────────── */
let currentUser       = null;   // { id, uid, full_name, username, email }
let allUsers          = [];     // all users fetched from our DB API
let filteredUsers     = [];
let activeChat        = null;   // the user we are currently chatting with
let db                = null;   // Firebase Realtime DB reference
let activeChatListener = null;  // unsubscribe fn for current chat listener
let userSearchTimeout  = null;

const API = '../api';

/* ─────────────────────────────────────────────
   BOOTSTRAP
───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', async () => {
  await loadCurrentUser();
  if (!currentUser) return; // redirected to login

  await initFirebase();
  await loadAllUsers();
  setupEventListeners();
  markUserOnline();
});

/* ─────────────────────────────────────────────
   CURRENT USER  (from our PHP session API)
───────────────────────────────────────────── */
async function loadCurrentUser() {
  try {
    const res  = await fetch(`${API}/profile.php`, { credentials: 'same-origin' });
    const data = await res.json();
    if (data.success && data.user) {
      currentUser = data.user;
      const initials = (data.user.full_name || 'U')
        .split(' ').filter(Boolean).slice(0,2).map(s=>s[0].toUpperCase()).join('');
      const av = document.getElementById('topbarAvatar');
      if (av) av.querySelector('span').textContent = initials;
    } else {
      window.location.href = '../index.php';
    }
  } catch (e) {
    console.error('Failed to load user:', e);
    window.location.href = '../index.php';
  }
}

/* ─────────────────────────────────────────────
   FIREBASE INIT  (config fetched from PHP)
───────────────────────────────────────────── */
async function initFirebase() {
  try {
    // Fetch Firebase config from a small PHP endpoint
    const res  = await fetch(`${API}/firebase-config.php`, { credentials: 'same-origin' });
    const cfg  = await res.json();

    if (!cfg.apiKey) {
      console.warn('Firebase config not available – chat will be limited to local storage fallback.');
      db = null;
      return;
    }

    if (!firebase.apps.length) {
      firebase.initializeApp(cfg);
    }

    db = firebase.database();

    // Sign in anonymously so RTDB rules can be applied
    // (Using the Firebase Auth UID from session is ideal but requires custom tokens.
    //  For simplicity we use the DB with open rules or anonymous auth.)
    await firebase.auth().signInAnonymously();
    console.log('Firebase connected.');
  } catch (e) {
    console.warn('Firebase init failed, falling back to localStorage:', e.message);
    db = null;
  }
}

/* ─────────────────────────────────────────────
   LOAD ALL USERS  (from our DB via search API)
───────────────────────────────────────────── */
async function loadAllUsers() {
  try {
    // Use a broad search to get all users; filter out self
    const res  = await fetch(`${API}/invitations.php?action=search&q=@&limit=50`, { credentials: 'same-origin' });
    const data = await res.json();
    allUsers = (data.users || []).filter(u => String(u.id) !== String(currentUser.id));
  } catch (e) {
    // Fallback: empty list
    allUsers = [];
    console.warn('Could not load users list:', e.message);
  }
  filteredUsers = [...allUsers];
  renderUserList(filteredUsers);
}

/* ─────────────────────────────────────────────
   RENDER USER LIST
───────────────────────────────────────────── */
function renderUserList(users) {
  const list = document.getElementById('chatList');
  if (!list) return;

  if (users.length === 0) {
    list.innerHTML = '<div class="no-users-found">No users found</div>';
    return;
  }

  list.innerHTML = users.map(u => {
    const initials = (u.full_name || u.username || '?')
      .split(' ').filter(Boolean).slice(0,2).map(s=>s[0].toUpperCase()).join('');
    const isActive = activeChat && String(activeChat.id) === String(u.id);
    return `
      <div class="chat-user ${isActive ? 'active' : ''}" data-uid="${u.id}">
        <div class="chat-avatar" style="background:${avatarColor(u.id)}">${escHtml(initials)}</div>
        <div class="chat-user-meta">
          <span class="chat-user-name">${escHtml(u.full_name || u.username)}</span>
          <span class="chat-user-preview" id="preview-${u.id}">@${escHtml(u.username || '')}</span>
        </div>
      </div>`;
  }).join('');

  list.querySelectorAll('.chat-user').forEach(el => {
    el.addEventListener('click', () => {
      const uid  = el.dataset.uid;
      const user = allUsers.find(u => String(u.id) === uid);
      if (user) selectChat(user);
    });
  });

  // Attach realtime unread preview listeners for each user
  if (db) {
    users.forEach(u => attachPreviewListener(u));
  }
}

function avatarColor(id) {
  const colors = ['#3b82f6','#8b5cf6','#10b981','#f59e0b','#ef4444','#06b6d4','#ec4899'];
  return colors[Number(id) % colors.length];
}

/* ─────────────────────────────────────────────
   SELECT CHAT
───────────────────────────────────────────── */
function selectChat(user) {
  activeChat = user;

  // Highlight in list
  document.querySelectorAll('.chat-user').forEach(el => {
    el.classList.toggle('active', String(el.dataset.uid) === String(user.id));
  });

  // Update chat header
  const initials = (user.full_name || user.username || '?')
    .split(' ').filter(Boolean).slice(0,2).map(s=>s[0].toUpperCase()).join('');
  document.getElementById('currentChatAvatar').textContent = initials;
  document.getElementById('currentChatAvatar').style.background = avatarColor(user.id);
  document.getElementById('currentUserName').textContent   = user.full_name || user.username;
  document.getElementById('currentUserStatus').textContent = `@${user.username || ''}`;
  document.getElementById('chatHeader').style.display      = 'flex';

  // Enable input
  const input = document.getElementById('messageInput');
  const btn   = document.getElementById('sendBtn');
  input.placeholder = `Message ${user.full_name || user.username}…`;
  input.disabled    = false;
  btn.disabled      = false;
  input.focus();

  // Load messages
  loadMessages();
}

/* ─────────────────────────────────────────────
   CONVERSATION KEY  (canonical, sorted by numeric id)
───────────────────────────────────────────── */
function chatKey(idA, idB) {
  // Use a stable key regardless of who initiates
  return [String(idA), String(idB)].sort().join('_');
}

/* ─────────────────────────────────────────────
   LOAD / LISTEN TO MESSAGES
───────────────────────────────────────────── */
function loadMessages() {
  const msgEl = document.getElementById('chatMessages');
  msgEl.innerHTML = '<div class="chat-loading"><i class="fas fa-spinner fa-spin"></i> Loading…</div>';

  // Detach previous listener
  if (activeChatListener) {
    activeChatListener();
    activeChatListener = null;
  }

  if (db) {
    loadMessagesFirebase();
  } else {
    loadMessagesLocalStorage();
  }
}

/* Firebase path: /chats/{chatKey}/messages/{pushId} */
function loadMessagesFirebase() {
  const key      = chatKey(currentUser.id, activeChat.id);
  const ref      = db.ref(`chats/${key}/messages`).orderByChild('ts').limitToLast(200);

  const handler = ref.on('value', snapshot => {
    const messages = [];
    snapshot.forEach(child => {
      messages.push({ id: child.key, ...child.val() });
    });
    renderMessages(messages);
  });

  // Store detach function
  activeChatListener = () => ref.off('value', handler);
}

function loadMessagesLocalStorage() {
  const key      = chatKey(currentUser.id, activeChat.id);
  const stored   = localStorage.getItem(`shiagari_chat_${key}`);
  const messages = stored ? JSON.parse(stored) : [];
  renderMessages(messages);
  activeChatListener = null;
}

/* ─────────────────────────────────────────────
   RENDER MESSAGES
───────────────────────────────────────────── */
function renderMessages(messages) {
  const container = document.getElementById('chatMessages');
  if (!container) return;

  if (messages.length === 0) {
    container.innerHTML = `
      <div class="chat-empty-state">
        <i class="fas fa-comment-dots"></i>
        <p>No messages yet. Say hello!</p>
      </div>`;
    return;
  }

  let html       = '';
  let lastDate   = '';

  messages.forEach(msg => {
    const isMine = String(msg.senderId) === String(currentUser.id);
    const ts     = msg.ts ? new Date(msg.ts) : new Date();
    const dateStr = ts.toLocaleDateString(undefined, { weekday:'short', month:'short', day:'numeric' });
    const timeStr = ts.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });

    if (dateStr !== lastDate) {
      html += `<div class="message-date-divider">${escHtml(dateStr)}</div>`;
      lastDate = dateStr;
    }

    html += `
      <div class="message ${isMine ? 'sent' : 'received'}">
        <div class="message-bubble">
          <div class="message-text">${escHtml(msg.text)}</div>
          <div class="message-time">${timeStr}</div>
        </div>
      </div>`;
  });

  container.innerHTML = html;
  container.scrollTop = container.scrollHeight;
}

/* ─────────────────────────────────────────────
   SEND MESSAGE
───────────────────────────────────────────── */
function sendMessage() {
  if (!activeChat) return;

  const input = document.getElementById('messageInput');
  const text  = input.value.trim();
  if (!text) return;

  input.value = '';

  const msg = {
    senderId:     String(currentUser.id),
    senderName:   currentUser.full_name || currentUser.username,
    recipientId:  String(activeChat.id),
    text,
    ts: Date.now()
  };

  if (db) {
    sendMessageFirebase(msg);
  } else {
    sendMessageLocalStorage(msg);
  }
}

function sendMessageFirebase(msg) {
  const key = chatKey(currentUser.id, activeChat.id);
  db.ref(`chats/${key}/messages`).push(msg)
    .then(() => {
      // Update last message metadata for both users (for previews)
      const meta = { lastMsg: msg.text, lastTs: msg.ts, senderId: msg.senderId };
      db.ref(`chats/${key}/meta`).set(meta);
    })
    .catch(err => { console.error('Send failed:', err); showToast('Failed to send message', 'error'); });
}

function sendMessageLocalStorage(msg) {
  const key      = chatKey(currentUser.id, activeChat.id);
  const stored   = localStorage.getItem(`shiagari_chat_${key}`);
  const messages = stored ? JSON.parse(stored) : [];
  messages.push({ ...msg, id: Date.now().toString() });
  localStorage.setItem(`shiagari_chat_${key}`, JSON.stringify(messages));
  renderMessages(messages);
}

/* ─────────────────────────────────────────────
   PREVIEW LISTENER  (last message snippet in user list)
───────────────────────────────────────────── */
function attachPreviewListener(user) {
  if (!db) return;
  const key     = chatKey(currentUser.id, user.id);
  const metaRef = db.ref(`chats/${key}/meta`);

  metaRef.on('value', snap => {
    const meta    = snap.val();
    const preview = document.getElementById(`preview-${user.id}`);
    if (preview && meta && meta.lastMsg) {
      const isMine = String(meta.senderId) === String(currentUser.id);
      const prefix = isMine ? 'You: ' : '';
      preview.textContent = prefix + meta.lastMsg.substring(0, 40);
    }
  });
}

/* ─────────────────────────────────────────────
   ONLINE PRESENCE  (Firebase RTDB)
───────────────────────────────────────────── */
function markUserOnline() {
  if (!db || !currentUser) return;
  const presenceRef = db.ref(`presence/${currentUser.id}`);
  presenceRef.set({ online: true, lastSeen: Date.now() });
  presenceRef.onDisconnect().set({ online: false, lastSeen: Date.now() });
}

/* ─────────────────────────────────────────────
   USER SEARCH FILTER
───────────────────────────────────────────── */
function filterUsers(query) {
  const q = query.toLowerCase().trim();
  if (!q) {
    filteredUsers = [...allUsers];
  } else {
    filteredUsers = allUsers.filter(u =>
      (u.full_name  || '').toLowerCase().includes(q) ||
      (u.username   || '').toLowerCase().includes(q) ||
      (u.email      || '').toLowerCase().includes(q)
    );
  }
  renderUserList(filteredUsers);
}

/* ─────────────────────────────────────────────
   EVENT LISTENERS
───────────────────────────────────────────── */
function setupEventListeners() {
  const sendBtn = document.getElementById('sendBtn');
  const input   = document.getElementById('messageInput');
  const search  = document.getElementById('userSearchInput');

  sendBtn?.addEventListener('click', sendMessage);
  input?.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } });

  search?.addEventListener('input', e => {
    clearTimeout(userSearchTimeout);
    userSearchTimeout = setTimeout(() => filterUsers(e.target.value), 250);
  });
}

/* ─────────────────────────────────────────────
   TOAST
───────────────────────────────────────────── */
let _toastTimer;
function showToast(message, type = 'success') {
  const toast = document.getElementById('toastMsg');
  const text  = document.getElementById('toastText');
  const icon  = toast.querySelector('i');
  const map   = { success:['fa-check-circle','#10b981'], error:['fa-exclamation-triangle','#f97316'], info:['fa-info-circle','#3b82f6'] };
  const [cls, color] = map[type] || map.success;
  icon.className   = `fas ${cls}`;
  icon.style.color = color;
  text.textContent = message;
  toast.classList.add('show');
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
}

/* ─────────────────────────────────────────────
   UTILITIES
───────────────────────────────────────────── */
function escHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}