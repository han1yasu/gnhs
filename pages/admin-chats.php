<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('admin');

$content = <<<'HTML'
<style>
  /* Hide the global floating widget on this page */
  #floatingChatToggle, #chatWidget { display: none !important; }
  
  .chat-page-container {
    display: flex;
    height: calc(100vh - 120px);
    background: var(--bg);
    border-radius: 12px;
    border: 1px solid var(--border);
    overflow: hidden;
  }
  .chat-page-sidebar {
    width: 350px;
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    background: var(--bg2);
  }
  .chat-page-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--bg);
  }
</style>

<div class="header-card" style="margin-bottom: 20px;">
  <h2><i class="fas fa-comments" style="color:var(--maroon);margin-right:10px"></i> Cases Chat</h2>
  <p style="color:var(--text-2);margin-top:6px;">Manage your active student conversations.</p>
</div>

<div class="chat-page-container">
  <!-- Left Sidebar (Inbox) -->
  <div class="chat-page-sidebar">
    <div style="padding:20px;border-bottom:1px solid var(--border);background:var(--bg);">
      <h3 style="font-size:18px;margin:0;">Active Chats</h3>
    </div>
    <div id="pageChatInbox" style="flex:1;overflow-y:auto;">
      <div style="padding:40px;text-align:center;color:var(--text-3);"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
    </div>
  </div>
  
  <!-- Right Main Area (Conversation) -->
  <div class="chat-page-main">
    <!-- Header -->
    <div id="pageChatHeader" style="padding:20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:15px;background:var(--bg);">
      <div style="width:40px;height:40px;border-radius:50%;background:var(--maroon);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;">
        <i class="fas fa-user-graduate"></i>
      </div>
      <div>
        <h3 id="pageChatTitle" style="margin:0;font-size:18px;color:var(--text);">Select a chat</h3>
        <p id="pageChatSub" style="margin:4px 0 0 0;font-size:13px;color:var(--text-2);">Choose a conversation from the left to start messaging.</p>
      </div>
    </div>
    
    <!-- Messages -->
    <div id="pageChatMessages" style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:15px;background:var(--bg2);">
      <!-- Messages load here -->
      <div style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-3);flex-direction:column;gap:10px;">
        <i class="fas fa-comments" style="font-size:48px;opacity:0.5;"></i>
        <p>Your messages will appear here.</p>
      </div>
    </div>
    
    <!-- Input area -->
    <div style="padding:20px;border-top:1px solid var(--border);background:var(--bg);display:flex;gap:10px;">
      <input type="text" id="pageChatInput" placeholder="Type your message..." disabled style="flex:1;padding:12px 20px;border-radius:24px;border:1px solid var(--border);background:var(--bg2);color:var(--text);font-size:15px;outline:none;transition:border-color 0.2s;" onfocus="this.style.borderColor='var(--maroon)'" onblur="this.style.borderColor='var(--border)'" onkeypress="if(event.key==='Enter') pageSendMessage()">
      <button id="pageChatSendBtn" onclick="pageSendMessage()" disabled style="background:var(--maroon);color:#fff;border:none;width:45px;height:45px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;opacity:0.5;"><i class="fas fa-paper-plane" style="margin-right:2px;"></i></button>
    </div>
  </div>
</div>

<script>
let pageCurrentCaseId = null;
let pageInboxPoll = null;
let pageChatPoll = null;

async function pageLoadInbox() {
  try {
    const params = new URLSearchParams(window.location.search);
    const archiveId = params.get('archive_id') || '';
    const res = await fetch('/api/get_chat_inbox.php' + (archiveId ? '?archive_id=' + archiveId : ''));
    const data = await res.json();
    if (data.success) {
      const list = document.getElementById('pageChatInbox');
      if (data.inbox.length === 0) {
        list.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-3);">No active chats found.</div>';
      } else {
        list.innerHTML = data.inbox.map(c => {
          const typeLabel = c.concern_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
          const prioLabel = c.priority ? c.priority.charAt(0).toUpperCase() + c.priority.slice(1) + ' Priority' : '';
          
          let lastMsg = c.latest_message ? c.latest_message : '<em>No messages yet</em>';
          if(lastMsg.length > 50) lastMsg = lastMsg.substring(0, 50) + '...';
          
          let timeLabel = '';
          if(c.latest_message_time) {
            const d = new Date(c.latest_message_time);
            timeLabel = d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
          }
          
          const badge = c.unread_count > 0 ? `<div style="background:var(--red);color:#fff;font-size:11px;font-weight:bold;border-radius:10px;padding:2px 6px;">${c.unread_count}</div>` : '';
          
          const isActive = pageCurrentCaseId == c.id;
          const bgClass = isActive ? 'background:var(--maroon);color:#fff;' : 'background:transparent;color:var(--text);';
          const subColor = isActive ? 'color:rgba(255,255,255,0.8);' : 'color:var(--text-2);';
          
          // Data passed to openChat
          const caseNum = c.case_number;
          const studentName = c.student_name;
          const subtext = `#${caseNum} (${typeLabel} - ${prioLabel})`;

          return `
            <div onclick="pageOpenChat(${c.id}, '${studentName.replace(/'/g, "\\'")}', '${subtext.replace(/'/g, "\\'")}', '${c.status}')" style="padding:20px;border-bottom:1px solid var(--border);cursor:pointer;display:flex;gap:15px;align-items:center;transition:background 0.2s;${bgClass}" onmouseover="if(${pageCurrentCaseId}!=${c.id}) this.style.background='var(--border)'" onmouseout="if(${pageCurrentCaseId}!=${c.id}) this.style.background='transparent'">
              <div style="flex:1;overflow:hidden;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                  <strong style="font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${studentName}</strong>
                  <span style="font-size:11px;${subColor}">${timeLabel}</span>
                </div>
                <div style="font-size:12px;${subColor}margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  ${subtext}
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <span style="font-size:13px;${subColor}font-weight:${c.unread_count > 0 && !isActive ? '600' : 'normal'};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${lastMsg}</span>
                  ${badge}
                </div>
              </div>
            </div>
          `;
        }).join('');
      }
      if (data.inbox.length > 0 && archiveId && !pageCurrentCaseId) {
        const c = data.inbox[0];
        const typeLabel = c.concern_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        const prioLabel = c.priority ? c.priority.charAt(0).toUpperCase() + c.priority.slice(1) + ' Priority' : '';
        pageOpenChat(c.id, c.student_name, `#${c.case_number} (${typeLabel} - ${prioLabel})`, c.status);
      }
    }
  } catch (e) {
    console.error(e);
  }
}

function pageOpenChat(id, title, sub, status) {
  pageCurrentCaseId = id;
  document.getElementById('pageChatTitle').innerText = title;
  document.getElementById('pageChatSub').innerText = sub;
  
  const input = document.getElementById('pageChatInput');
  const btn = document.getElementById('pageChatSendBtn');
  
  if (status === 'resolved') {
    input.disabled = true;
    btn.disabled = true;
    input.placeholder = 'This case is resolved. Chat is read-only.';
    btn.style.opacity = '0.5';
  } else {
    input.disabled = false;
    btn.disabled = false;
    input.placeholder = 'Type your message...';
    btn.style.opacity = '1';
    input.focus();
  }
  
  pageLoadInbox(); // Refresh inbox to show active selection
  pageLoadMessages();
  
  if (pageChatPoll) clearInterval(pageChatPoll);
  pageChatPoll = setInterval(pageLoadMessages, 3000);
}

async function pageLoadMessages() {
  if (!pageCurrentCaseId) return;
  try {
    const res = await fetch('/api/get_messages.php?case_id=' + pageCurrentCaseId);
    const data = await res.json();
    if (data.success) {
      const container = document.getElementById('pageChatMessages');
      if(!container) return;
      const atBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 10;
      
      container.innerHTML = data.messages.length ? data.messages.map(m => {
        const isMe = m.sender_id == data.current_user_id;
        const align = isMe ? 'flex-end' : 'flex-start';
        const bg = isMe ? 'var(--maroon)' : 'var(--bg)';
        const color = isMe ? '#fff' : 'var(--text)';
        const border = isMe ? 'none' : '1px solid var(--border)';
        const roleBadge = !isMe ? `<div style="font-size:11px;color:var(--text-3);margin-bottom:4px">${m.sender_role === 'admin' ? 'Counselor' : 'Student'}</div>` : '';
        return `
          <div style="display:flex;flex-direction:column;align-items:${align};width:100%">
            ${roleBadge}
            <div style="background:${bg};color:${color};border:${border};padding:12px 18px;border-radius:20px;max-width:75%;font-size:15px;line-height:1.5;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
              ${escapeHtml(m.message)}
            </div>
            <div style="font-size:11px;color:var(--text-3);margin-top:6px;">${new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
          </div>`;
      }).join('') : '<div style="height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-3);flex-direction:column;gap:10px;"><i class="fas fa-comments" style="font-size:48px;opacity:0.5;"></i><p>No messages yet. Say hello!</p></div>';
      
      if (atBottom) {
        container.scrollTop = container.scrollHeight;
      }
    }
  } catch (e) {}
}

async function pageSendMessage() {
  if (!pageCurrentCaseId) return;
  const input = document.getElementById('pageChatInput');
  const msg = input.value.trim();
  if (!msg) return;
  
  input.value = '';
  
  const container = document.getElementById('pageChatMessages');
  if (container.innerHTML.includes('No messages yet')) container.innerHTML = '';
  container.innerHTML += `
    <div style="display:flex;flex-direction:column;align-items:flex-end;width:100%">
      <div style="background:var(--maroon);color:#fff;border:none;padding:12px 18px;border-radius:20px;max-width:75%;font-size:15px;line-height:1.5;opacity:0.7;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
        ${escapeHtml(msg)}
      </div>
    </div>`;
  container.scrollTop = container.scrollHeight;

  try {
    const res = await apiPost('/api/send_message.php', { case_id: pageCurrentCaseId, message: msg });
    if (res.success) {
      pageLoadMessages();
      pageLoadInbox();
    } else {
      showToast(res.message, 'error');
    }
  } catch (e) {
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
