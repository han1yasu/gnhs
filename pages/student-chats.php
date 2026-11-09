<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');

$content = <<<'HTML'
<style>
  /* Hide the global floating widget on this page */
  #floatingChatToggle, #chatWidget { display: none !important; }
  
  .chat-page-container {
    display: flex;
    height: calc(100vh - 120px);
    min-height: 520px;
    background: var(--bg);
    border-radius: 12px;
    border: 1px solid var(--border);
    overflow: hidden;
    position: relative;
  }
  .chat-page-sidebar {
    width: 340px;
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    background: var(--bg2);
    flex-shrink: 0;
  }
  .chat-page-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--bg);
    min-width: 0;
  }
  .chat-inbox-item {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    display: flex;
    gap: 12px;
    align-items: center;
    transition: background 0.15s;
    user-select: none;
  }
  .chat-inbox-item:hover {
    background: var(--border);
  }
  .chat-inbox-item.active {
    background: var(--maroon) !important;
    color: #fff !important;
  }
  .chat-inbox-item.active * {
    color: #fff !important;
  }

  /* Mobile Messenger View Toggle */
  @media (max-width: 768px) {
    .chat-page-container {
      height: calc(100vh - 110px) !important;
      min-height: 460px !important;
    }
    .chat-page-container.mobile-inbox-view .chat-page-sidebar {
      display: flex !important;
      width: 100% !important;
      height: 100% !important;
      border-right: none !important;
    }
    .chat-page-container.mobile-inbox-view .chat-page-main {
      display: none !important;
    }

    .chat-page-container.mobile-chat-view .chat-page-sidebar {
      display: none !important;
    }
    .chat-page-container.mobile-chat-view .chat-page-main {
      display: flex !important;
      width: 100% !important;
      height: 100% !important;
    }
    .mobile-back-btn {
      display: inline-flex !important;
    }
  }
  @media (min-width: 769px) {
    .mobile-back-btn {
      display: none !important;
    }
    .chat-page-sidebar {
      display: flex !important;
    }
    .chat-page-main {
      display: flex !important;
    }
  }
</style>

<div class="header-card" style="margin-bottom: 14px;">
  <h2><i class="fas fa-comments" style="color:var(--maroon);margin-right:8px"></i> Cases Chat</h2>
  <p style="color:var(--text-2);margin-top:4px;">Direct, confidential messaging with Guidance Counselors for your concerns.</p>
</div>

<div class="chat-page-container mobile-inbox-view" id="chatContainer">
  <!-- Left Sidebar (Inbox) -->
  <div class="chat-page-sidebar" id="chatSidebar">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);background:var(--bg);display:flex;align-items:center;justify-content:space-between">
      <h3 style="font-size:15px;font-weight:800;margin:0;display:flex;align-items:center;gap:8px">
        <i class="fas fa-inbox" style="color:var(--maroon)"></i> Active Chats
      </h3>
      <button onclick="pageLoadInbox()" title="Refresh Inbox" style="background:none;border:none;color:var(--text-3);cursor:pointer;font-size:13px;padding:4px"><i class="fas fa-sync-alt"></i></button>
    </div>
    <div id="pageChatInbox" style="flex:1;overflow-y:auto;">
      <div style="padding:40px 20px;text-align:center;color:var(--text-3);"><i class="fas fa-spinner fa-spin"></i> Loading conversations...</div>
    </div>
  </div>
  
  <!-- Right Main Area (Conversation) -->
  <div class="chat-page-main" id="chatMain">
    <!-- Header -->
    <div id="pageChatHeader" style="padding:12px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;background:var(--bg);">
      <button type="button" class="btn-sm-outline mobile-back-btn" onclick="showMobileInbox()" style="display:none;align-items:center;gap:6px;padding:5px 9px;font-size:11.5px;font-weight:700">
        <i class="fas fa-arrow-left"></i> Chats
      </button>
      <div style="width:36px;height:36px;min-width:36px;border-radius:50%;background:var(--maroon);color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;">
        <i class="fas fa-user-tie"></i>
      </div>
      <div style="flex:1;overflow:hidden">
        <h3 id="pageChatTitle" style="margin:0;font-size:15px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Select a chat</h3>
        <p id="pageChatSub" style="margin:2px 0 0 0;font-size:11.5px;color:var(--text-2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Choose a conversation from the left to start messaging.</p>
      </div>
    </div>
    
    <!-- Messages -->
    <div id="pageChatMessages" style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:12px;background:var(--bg2);">
      <div style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-3);flex-direction:column;gap:10px;text-align:center;padding:20px;">
        <i class="fas fa-comments" style="font-size:42px;opacity:0.4;"></i>
        <p style="font-size:13px;margin:0">Choose an active concern to review or send messages.</p>
      </div>
    </div>
    
    <!-- Input area -->
    <div style="padding:12px 16px;border-top:1px solid var(--border);background:var(--bg);display:flex;gap:10px;align-items:center;">
      <input type="text" id="pageChatInput" placeholder="Type your message..." disabled style="flex:1;padding:10px 16px;border-radius:20px;border:1px solid var(--border);background:var(--bg2);color:var(--text);font-size:13.5px;outline:none;" onkeypress="if(event.key==='Enter') pageSendMessage()">
      <button id="pageChatSendBtn" onclick="pageSendMessage()" disabled style="background:var(--maroon);color:#fff;border:none;width:38px;height:38px;min-width:38px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;opacity:0.5;"><i class="fas fa-paper-plane"></i></button>
    </div>
  </div>
</div>

<script>
let pageCurrentCaseId = null;
let pageInboxPoll = null;
let pageChatPoll = null;

function showMobileInbox() {
  const container = document.getElementById('chatContainer');
  if (container) {
    container.classList.remove('mobile-chat-view');
    container.classList.add('mobile-inbox-view');
  }
}

function showMobileChat() {
  const container = document.getElementById('chatContainer');
  if (container) {
    container.classList.remove('mobile-inbox-view');
    container.classList.add('mobile-chat-view');
  }
}

async function apiFetch(endpoint, options = {}) {
  options.credentials = 'include';
  const isNested = window.location.pathname.startsWith('/gnhs-guidance');
  const base1 = isNested ? '/gnhs-guidance/api/' : '/api/';
  const base2 = isNested ? '/api/' : '/gnhs-guidance/api/';

  try {
    const r1 = await fetch(base1 + endpoint, options);
    if (r1.ok) {
      const data = await r1.json();
      if (data && data.success !== false) return data;
    }
  } catch(e) {}

  try {
    const r2 = await fetch(base2 + endpoint, options);
    if (r2.ok) return await r2.json();
  } catch(e) {}

  return { success: false, message: 'Could not connect to chat server.' };
}

async function pageLoadInbox() {
  try {
    const params = new URLSearchParams(window.location.search);
    const archiveId = params.get('archive_id') || '';
    const caseId = params.get('case_id') || '';
    const query = caseId ? '?case_id=' + caseId : (archiveId ? '?archive_id=' + archiveId : '');
    
    const data = await apiFetch('get_chat_inbox.php' + query);
    const list = document.getElementById('pageChatInbox');
    if (!list) return;

    if (!data.success) {
      list.innerHTML = `
        <div style="padding:30px 16px;text-align:center;color:var(--text-3);">
          <i class="fas fa-exclamation-circle" style="color:var(--danger);font-size:24px;margin-bottom:8px;display:block"></i>
          <span style="font-size:12.5px">${data.message || 'Unable to load chats.'}</span><br/>
          <button onclick="pageLoadInbox()" class="btn-sm-primary" style="margin-top:10px;padding:4px 12px;font-size:11px">Retry</button>
        </div>`;
      return;
    }

    if (!data.inbox || data.inbox.length === 0) {
      list.innerHTML = `
        <div style="padding:40px 18px;text-align:center;color:var(--text-3);font-size:13px;">
          <i class="fas fa-comments" style="font-size:32px;opacity:0.4;margin-bottom:10px;display:block"></i>
          No active case chats found.<br/>
          <a href="student-submit.php" style="color:var(--maroon);font-weight:700;display:inline-block;margin-top:8px">Submit a concern &rarr;</a>
        </div>`;
      return;
    }

    list.innerHTML = '';
    data.inbox.forEach(c => {
      const typeRaw = c.concern_type || 'General';
      const typeLabel = typeRaw.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
      const prioLabel = c.priority ? (c.priority.charAt(0).toUpperCase() + c.priority.slice(1) + ' Priority') : 'Normal';
      const studentName = c.student_name || 'Guidance Counselor';
      const caseNum = c.case_number || c.id;
      const subtext = `#${caseNum} (${typeLabel} - ${prioLabel})`;

      let lastMsg = c.latest_message ? c.latest_message : 'No messages yet';
      if (lastMsg.length > 45) lastMsg = lastMsg.substring(0, 45) + '...';

      let timeLabel = '';
      if (c.latest_message_time) {
        const d = new Date(c.latest_message_time);
        timeLabel = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      }

      const isActive = pageCurrentCaseId == c.id;
      const unreadCount = parseInt(c.unread_count || 0);
      const badgeHtml = unreadCount > 0 ? `<span style="background:var(--danger);color:#fff;font-size:10px;font-weight:800;border-radius:10px;padding:1px 6px;">${unreadCount}</span>` : '';

      const item = document.createElement('div');
      item.className = 'chat-inbox-item' + (isActive ? ' active' : '');
      item.innerHTML = `
        <div style="flex:1;overflow:hidden;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">
            <strong style="font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${studentName}</strong>
            <span style="font-size:10.5px;opacity:0.8">${timeLabel}</span>
          </div>
          <div style="font-size:11.5px;opacity:0.85;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            ${subtext}
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:12px;opacity:0.9;font-weight:${unreadCount > 0 && !isActive ? '700' : 'normal'};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${lastMsg}</span>
            ${badgeHtml}
          </div>
        </div>
      `;
      item.onclick = function() {
        pageOpenChat(c.id, studentName, subtext, c.status);
      };
      list.appendChild(item);
    });

    const autoId = caseId || archiveId;
    if (data.inbox.length > 0 && !pageCurrentCaseId) {
      const autoItem = autoId ? (data.inbox.find(i => i.id == autoId) || data.inbox[0]) : data.inbox[0];
      if (autoItem) {
        const tRaw = autoItem.concern_type || 'General';
        const tLabel = tRaw.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        const pLabel = autoItem.priority ? (autoItem.priority.charAt(0).toUpperCase() + autoItem.priority.slice(1) + ' Priority') : 'Normal';
        const sName = autoItem.student_name || 'Guidance Counselor';
        const sSub = `#${autoItem.case_number || autoItem.id} (${tLabel} - ${pLabel})`;
        
        // On phone with no explicit case requested, stay in inbox view until tapped;
        // on desktop or when case is explicitly requested, auto-open immediately!
        if (window.innerWidth <= 768 && !autoId) {
          // Stay on mobile inbox list
        } else {
          pageOpenChat(autoItem.id, sName, sSub, autoItem.status);
        }
      }
    }
  } catch(e) {
    console.error('Chat inbox error:', e);
  }
}

function pageOpenChat(id, title, sub, status) {
  pageCurrentCaseId = id;
  const titleEl = document.getElementById('pageChatTitle');
  const subEl = document.getElementById('pageChatSub');
  if (titleEl) titleEl.innerText = title;
  if (subEl) subEl.innerText = sub;
  
  const input = document.getElementById('pageChatInput');
  const btn = document.getElementById('pageChatSendBtn');
  
  if (status === 'resolved' || status === 'closed') {
    if (input) { input.disabled = true; input.placeholder = 'This case is concluded. Chat is archived.'; }
    if (btn) { btn.disabled = true; btn.style.opacity = '0.4'; }
  } else {
    if (input) { input.disabled = false; input.placeholder = 'Type your message...'; }
    if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
  }

  // Switch to chat view on mobile
  showMobileChat();
  
  pageLoadInbox();
  pageLoadMessages();
  
  if (pageChatPoll) clearInterval(pageChatPoll);
  pageChatPoll = setInterval(pageLoadMessages, 3000);
}

async function pageLoadMessages() {
  if (!pageCurrentCaseId) return;
  try {
    const data = await apiFetch('get_messages.php?case_id=' + pageCurrentCaseId);
    if (data.success) {
      const container = document.getElementById('pageChatMessages');
      if (!container) return;
      const atBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 40;
      
      container.innerHTML = data.messages && data.messages.length ? data.messages.map(m => {
        const isMe = m.sender_id == data.current_user_id;
        const align = isMe ? 'flex-end' : 'flex-start';
        const bg = isMe ? 'var(--maroon)' : 'var(--bg)';
        const color = isMe ? '#fff' : 'var(--text)';
        const border = isMe ? 'none' : '1px solid var(--border)';
        const roleBadge = !isMe ? `<div style="font-size:10.5px;color:var(--text-3);margin-bottom:3px;font-weight:700">${m.sender_role === 'admin' ? 'Guidance Counselor' : 'Student'}</div>` : '';
        return `
          <div style="display:flex;flex-direction:column;align-items:${align};width:100%">
            ${roleBadge}
            <div style="background:${bg};color:${color};border:${border};padding:10px 15px;border-radius:18px;max-width:82%;font-size:13.5px;line-height:1.45;box-shadow:0 1px 2px rgba(0,0,0,0.06);word-break:break-word;">
              ${m.message}
            </div>
            <div style="font-size:10px;color:var(--text-3);margin-top:4px;">${new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
          </div>`;
      }).join('') : '<div style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-3);flex-direction:column;gap:8px;text-align:center;padding:20px;"><i class="fas fa-comments" style="font-size:36px;opacity:0.4;"></i><p style="font-size:12.5px;margin:0">No messages yet. Send a message to the counselor!</p></div>';
      
      if (atBottom) {
        container.scrollTop = container.scrollHeight;
      }
    }
  } catch(e) {}
}

async function pageSendMessage() {
  if (!pageCurrentCaseId) return;
  const input = document.getElementById('pageChatInput');
  const msg = input ? input.value.trim() : '';
  if (!msg) return;
  
  input.value = '';
  
  const container = document.getElementById('pageChatMessages');
  if (container && container.innerHTML.includes('No messages yet')) container.innerHTML = '';
  if (container) {
    container.innerHTML += `
      <div style="display:flex;flex-direction:column;align-items:flex-end;width:100%">
        <div style="background:var(--maroon);color:#fff;border:none;padding:10px 15px;border-radius:18px;max-width:82%;font-size:13.5px;line-height:1.45;opacity:0.75;box-shadow:0 1px 2px rgba(0,0,0,0.06);word-break:break-word;">
          ${msg}
        </div>
      </div>`;
    container.scrollTop = container.scrollHeight;
  }

  try {
    const isNested = window.location.pathname.startsWith('/gnhs-guidance');
    const apiPath = isNested ? '/gnhs-guidance/api/send_message.php' : '/api/send_message.php';
    const res = await apiPost(apiPath, { case_id: pageCurrentCaseId, message: msg });
    if (res.success) {
      pageLoadMessages();
      pageLoadInbox();
    } else {
      showToast(res.message || 'Failed to send message', 'error');
    }
  } catch(e) {
    showToast('Failed to send message', 'error');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  pageLoadInbox();
  pageInboxPoll = setInterval(pageLoadInbox, 5000);
});
</script>
HTML;

renderLayout($user, 'Cases Chat', 'chats', $content);
