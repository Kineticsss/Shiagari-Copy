// SHIAGARI - Chat Module (real API, no fake data)

let activeConversationUid = null; // UID of the user we're chatting with
let activeConversationName = '';
let pollingInterval = null;
let lastMessageCount = 0;
let isDragging = false;
let dragOffsetX, dragOffsetY;

// ─── Init ────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
  shiagariAPI.init(csrfToken);
  loadConversations();
  initDrag();
  initMinimize();
  initSendMessage();
  initUserSearch();
});

function initUserSearch() {
  const input = document.getElementById('userSearchInput');
  const results = document.getElementById('userSearchResults');
  if (!input || !results) return;

  let debounceTimer = null;

  const renderEmpty = (msg) => {
    results.innerHTML = msg
      ? `<div class="search-empty">${escapeHtml(msg)}</div>`
      : '';
  };

  renderEmpty('Type to search users');

  input.addEventListener('input', () => {
    const q = input.value;

    if (debounceTimer) clearTimeout(debounceTimer);

    debounceTimer = setTimeout(async () => {
      const query = (q ?? '').trim();

      if (!query) {
        renderEmpty('Type to search users');
        return;
      }

      renderEmpty('Searching...');

      try {
        const res = await shiagariAPI.searchUsers(query);
        if (!res.success) {
          renderEmpty(res.error ?? 'Search failed');
          return;
        }

        const users = res.users ?? [];
        if (!users.length) {
          renderEmpty('No users found');
          return;
        }

        results.innerHTML = users
          .map(u => {
            const name = u.displayName ?? u.email ?? u.uid;
            return `
              <button type="button" class="search-result" data-uid="${escapeAttr(u.uid)}" data-name="${escapeAttr(name)}">
                <div class="search-result-name">${escapeHtml(name)}</div>
                <div class="search-result-meta">${escapeHtml(u.email ?? '')}</div>
              </button>
            `;
          })
          .join('');

        results.querySelectorAll('.search-result').forEach(btn => {
          btn.addEventListener('click', async () => {
            const otherUid = btn.dataset.uid;
            const otherName = btn.dataset.name;
            if (!otherUid) return;
            results.innerHTML = '';
            input.value = '';
            await openConversation(otherUid, otherName);
          });
        });
      } catch (err) {
        console.error('User search:', err);
        renderEmpty('Error searching users');
      }
    }, 350);
  });
}


// ─── Conversations list ───────────────────────────────────────────────────────

async function loadConversations() {
  const chatList = document.getElementById('chatList');
  chatList.innerHTML = '<div class="loading-chats"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

  try {
    const result = await shiagariAPI.getConversations();

    if (!result.success) {
      chatList.innerHTML = '<div class="empty-list">Could not load conversations.</div>';
      return;
    }

    const conversations = result.conversations ?? [];

    if (conversations.length === 0) {
      chatList.innerHTML = '<div class="empty-list">No conversations yet.<br>Search for a user to start chatting.</div>';
      return;
    }

    renderConversationList(conversations);
  } catch (err) {
    console.error('loadConversations:', err);
    chatList.innerHTML = '<div class="empty-list">Error loading conversations.</div>';
  }
}

function renderConversationList(conversations) {
  const chatList = document.getElementById('chatList');

  chatList.innerHTML = conversations.map(conv => {
    const otherUid  = conv.otherUid  ?? '';
    const otherName = conv.otherName ?? 'Unknown';
    const initials  = getInitials(otherName);
    const lastMsg   = conv.lastMessage ?? '';
    const unread    = (conv.unreadCount > 0)
      ? `<span class="unread-badge">${conv.unreadCount}</span>`
      : '';

    return `
      <div class="chat-user ${activeConversationUid === otherUid ? 'active' : ''}"
           data-uid="${escapeAttr(otherUid)}"
           data-name="${escapeAttr(otherName)}"
           onclick="openConversation('${escapeAttr(otherUid)}', '${escapeAttr(otherName)}')">
        <div class="chat-user-avatar">${initials}</div>
        <div class="chat-user-details">
          <span class="chat-user-name">${escapeHtml(otherName)}</span>
          <span class="chat-user-preview">${escapeHtml(lastMsg)}</span>
        </div>
        ${unread}
      </div>`;
  }).join('');
}

// ─── Open a conversation ──────────────────────────────────────────────────────

async function openConversation(otherUid, otherName) {
  document.querySelectorAll('.chat-user').forEach(el => {
    el.classList.toggle('active', el.dataset.uid === otherUid);
  });

  activeConversationUid  = otherUid;
  activeConversationName = otherName;

  document.getElementById('currentAvatar').textContent   = getInitials(otherName);
  document.getElementById('currentUserName').textContent  = otherName;

  document.getElementById('messageInput').disabled = false;
  document.getElementById('sendBtn').disabled      = false;
  document.getElementById('messageInput').focus();

  await fetchAndRenderMessages();

  stopPolling();
  pollingInterval = setInterval(pollMessages, 4000);
}

// ─── Fetch & render messages ──────────────────────────────────────────────────

async function fetchAndRenderMessages() {
  if (!activeConversationUid) return;
  try {
    const result = await shiagariAPI.getConversation(activeConversationUid);
    if (!result.success) return;

    const messages = result.conversation?.messages ?? [];
    renderMessages(messages);
    lastMessageCount = messages.length;

    const convId = result.conversation?.id ?? '';
    if (convId) shiagariAPI.markMessagesRead(convId).catch(() => {});
  } catch (err) {
    console.error('fetchAndRenderMessages:', err);
  }
}

async function pollMessages() {
  if (!activeConversationUid) return;
  try {
    const result = await shiagariAPI.getConversation(activeConversationUid);
    if (!result.success) return;

    const messages = result.conversation?.messages ?? [];
    if (messages.length !== lastMessageCount) {
      const grew = messages.length > lastMessageCount;
      renderMessages(messages);
      lastMessageCount = messages.length;
      if (grew) {
        const last = messages[messages.length - 1];
        if (last?.from === activeConversationUid) {
          showToast(`New message from ${activeConversationName}`);
        }
      }
    }
  } catch (_) { /* ignore poll errors */ }
}

function stopPolling() {
  if (pollingInterval) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
}

function renderMessages(messages) {
  const chatMessages = document.getElementById('chatMessages');
  if (!chatMessages) return;

  if (messages.length === 0) {
    chatMessages.innerHTML = `
      <div class="empty-chat">
        <i class="fas fa-comments"></i>
        <p>No messages yet. Say hi!</p>
      </div>`;
    return;
  }

  const wasAtBottom =
    chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 60;

  chatMessages.innerHTML = messages.map(msg => {
    const isMe     = msg.from !== activeConversationUid;
    const name     = isMe ? (typeof currentUserName !== 'undefined' ? currentUserName : 'Me') : activeConversationName;
    const initials = getInitials(name);
    const time     = formatTime(msg.timestamp);

    return `
      <div class="msg ${isMe ? 'right' : 'left'}">
        ${!isMe ? `<div class="msg-avatar">${initials}</div>` : ''}
        <div class="msg-content">
          <div class="msg-text">${escapeHtml(msg.content)}</div>
          <div class="msg-time">${time}</div>
        </div>
      </div>`;
  }).join('');

  if (wasAtBottom) chatMessages.scrollTop = chatMessages.scrollHeight;
}

// ─── Send message ─────────────────────────────────────────────────────────────

function initSendMessage() {
  const sendBtn      = document.getElementById('sendBtn');
  const messageInput = document.getElementById('messageInput');

  sendBtn?.addEventListener('click', () => doSend());

  messageInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      doSend();
    }
  });
}

async function doSend() {
  const messageInput = document.getElementById('messageInput');
  const content = messageInput?.value?.trim();
  if (!content || !activeConversationUid) return;

  messageInput.value    = '';
  messageInput.disabled = true;
  document.getElementById('sendBtn').disabled = true;

  try {
    const result = await shiagariAPI.sendMessage(activeConversationUid, content);
    if (result.success) {
      await fetchAndRenderMessages();
    } else {
      showToast('Failed to send: ' + (result.error ?? 'Unknown error'), 'error');
      messageInput.value = content;
    }
  } catch (err) {
    showToast('Error sending message.', 'error');
    messageInput.value = content;
  } finally {
    messageInput.disabled = false;
    document.getElementById('sendBtn').disabled = false;
    messageInput.focus();
  }
}

// ─── Drag ─────────────────────────────────────────────────────────────────────

function initDrag() {
  const chatWindow = document.getElementById('chatWindow');
  const dragHandle = document.getElementById('dragHandle');
  if (!chatWindow || !dragHandle) return;

  dragHandle.addEventListener('mousedown', (e) => {
    if (e.target.closest('.chat-header-actions')) return;
    isDragging = true;
    const rect = chatWindow.getBoundingClientRect();
    dragOffsetX = e.clientX - rect.left;
    dragOffsetY = e.clientY - rect.top;
    chatWindow.style.cursor = 'grabbing';
  });

  document.addEventListener('mousemove', (e) => {
    if (!isDragging) return;
    const newLeft = Math.max(0, Math.min(e.clientX - dragOffsetX, window.innerWidth  - chatWindow.offsetWidth));
    const newTop  = Math.max(0, Math.min(e.clientY - dragOffsetY, window.innerHeight - chatWindow.offsetHeight));
    chatWindow.style.left   = newLeft + 'px';
    chatWindow.style.top    = newTop  + 'px';
    chatWindow.style.right  = 'auto';
    chatWindow.style.bottom = 'auto';
  });

  document.addEventListener('mouseup', () => {
    isDragging = false;
    if (chatWindow) chatWindow.style.cursor = '';
  });
}

// ─── Minimize ─────────────────────────────────────────────────────────────────

function initMinimize() {
  const minimizeBtn = document.getElementById('minimizeBtn');
  const chatWindow  = document.getElementById('chatWindow');
  if (!minimizeBtn || !chatWindow) return;

  minimizeBtn.addEventListener('click', () => {
    chatWindow.classList.toggle('minimized');
    const icon = minimizeBtn.querySelector('i');
    icon.className = chatWindow.classList.contains('minimized')
      ? 'fas fa-window-maximize'
      : 'fas fa-minus';
  });
}

// ─── Toast ───────────────────────────────────────────────────────────────────

let toastTimeout = null;

function showToast(message, type = 'info') {
  const toast     = document.getElementById('toastMsg');
  const toastText = document.getElementById('toastText');
  if (!toast || !toastText) return;

  toastText.textContent = message;
  toast.className = `toast show ${type}`;

  if (toastTimeout) clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => toast.classList.remove('show'), 2800);
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getInitials(name) {
  if (!name) return '?';
  return name.trim().split(/\s+/).map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

function formatTime(isoString) {
  if (!isoString) return '';
  try {
    return new Date(isoString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  } catch (_) { return ''; }
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, c =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])
  );
}

function escapeAttr(str) {
  if (!str) return '';
  return String(str).replace(/["']/g, c => c === '"' ? '&quot;' : '&#39;');
}
