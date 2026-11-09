<?php
$file = 'c:/xampp/htdocs/gnhs-guidance/app.js';
$content = file_get_contents($file);

// Find the start of Chat Widget Logic
$pos = strpos($content, '// ── Chat Widget Logic');
if ($pos !== false) {
    $content = substr($content, 0, $pos); // truncate
}

$newChatLogic = '// ── Chat Widget Logic ──────────────────────────────────────────────────
let chatPollInterval = null;
let inboxPollInterval = null;
let currentChatCaseId = null;
let isFloatingInboxOpen = false;

async function loadInbox() {
  try {
    const res = await fetch(\'/gnhs-guidance/api/get_chat_inbox.php\');
    const data = await res.json();
    if (data.success) {
      const list = document.getElementById(\'chatInboxList\');
      if (!list) return;
      
      let totalUnread = 0;
      if (data.inbox.length === 0) {
        list.innerHTML = \'<div style="padding:30px;text-align:center;color:var(--text-3);font-size:13px;">No active chats found.</div>\';
      } else {
        list.innerHTML = data.inbox.map(c => {
          totalUnread += parseInt(c.unread_count);
          const typeLabel = c.concern_type.replace(/_/g, \' \').replace(/\b\w/g, l => l.toUpperCase());
          const prioLabel = c.priority ? c.priority.charAt(0).toUpperCase() + c.priority.slice(1) + \' Priority\' : \'\';
          
          let lastMsg = c.latest_message ? c.latest_message : \'<em>No messages yet</em>\';
          if(lastMsg.length > 40) lastMsg = lastMsg.substring(0, 40) + \'...\';
          
          let timeLabel = \'\';
          if(c.latest_message_time) {
            const d = new Date(c.latest_message_time);
            timeLabel = d.toLocaleTimeString([], {hour: \'2-digit\', minute:\'2-digit\'});
          }
          
          const badge = c.unread_count > 0 ? `<div style="background:var(--red);color:#fff;font-size:11px;font-weight:bold;border-radius:10px;padding:2px 6px;">${c.unread_count}</div>` : \'\';
          const unreadDot = c.unread_count > 0 ? \'<div style="width:8px;height:8px;border-radius:50%;background:var(--red);"></div>\' : \'\';

          // Data passed to openChat
          const caseNum = c.case_number;
          const studentName = c.student_name;
          const subtext = `#${caseNum} (${typeLabel} - ${prioLabel})`;

          return `
            <div onclick="openChat(${c.id}, \'${studentName.replace(/\'/g, "\\\'")}\', \'${subtext.replace(/\'/g, "\\\'")}\')" style="padding:15px;border-bottom:1px solid var(--border);cursor:pointer;display:flex;gap:12px;align-items:center;transition:background 0.2s;" onmouseover="this.style.background=\'var(--bg)\'" onmouseout="this.style.background=\'transparent\'">
              <div style="width:40px;height:40px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-2);">
                <i class="fas fa-user"></i>
              </div>
              <div style="flex:1;overflow:hidden;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <strong style="font-size:14px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${studentName}</strong>
                  <span style="font-size:11px;color:var(--text-3);">${timeLabel}</span>
                </div>
                <div style="font-size:12px;color:var(--text-2);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  ${subtext}
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <span style="font-size:12px;color:${c.unread_count > 0 ? \'var(--text)\' : \'var(--text-3)\'};font-weight:${c.unread_count > 0 ? \'600\' : \'normal\'};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${lastMsg}</span>
                  ${badge}
                </div>
              </div>
            </div>
          `;
        }).join(\'\');
      }

      // Update global unread badge
      const globalBadge = document.getElementById(\'globalUnreadBadge\');
      if (globalBadge) {
        if (totalUnread > 0) {
          globalBadge.innerText = totalUnread;
          globalBadge.classList.remove(\'hidden\');
        } else {
          globalBadge.classList.add(\'hidden\');
        }
      }
    }
  } catch (e) {}
}

function toggleFloatingInbox() {
  const widget = document.getElementById(\'chatWidget\');
  if (!widget) return;
  
  if (widget.classList.contains(\'hidden\')) {
    // Open inbox
    widget.classList.remove(\'hidden\');
    document.getElementById(\'chatInboxPane\').classList.remove(\'hidden\');
    document.getElementById(\'chatConversationPane\').classList.add(\'hidden\');
    loadInbox();
    if (!inboxPollInterval) inboxPollInterval = setInterval(loadInbox, 5000);
  } else {
    // Close widget completely
    widget.classList.add(\'hidden\');
    if (inboxPollInterval) { clearInterval(inboxPollInterval); inboxPollInterval = null; }
    if (chatPollInterval) { clearInterval(chatPollInterval); chatPollInterval = null; }
    currentChatCaseId = null;
  }
}

function backToInbox() {
  currentChatCaseId = null;
  if (chatPollInterval) { clearInterval(chatPollInterval); chatPollInterval = null; }
  document.getElementById(\'chatConversationPane\').classList.add(\'hidden\');
  document.getElementById(\'chatInboxPane\').classList.remove(\'hidden\');
  loadInbox();
  if (!inboxPollInterval) inboxPollInterval = setInterval(loadInbox, 5000);
}

function openChat(caseId, studentName, subtext) {
  currentChatCaseId = caseId;
  const widget = document.getElementById(\'chatWidget\');
  if (widget) {
    widget.classList.remove(\'hidden\');
    document.getElementById(\'chatInboxPane\').classList.add(\'hidden\');
    document.getElementById(\'chatConversationPane\').classList.remove(\'hidden\');
    
    document.getElementById(\'chatHeaderTitle\').innerText = studentName;
    document.getElementById(\'chatHeaderSub\').innerText = subtext;
    
    loadMessages();
    if (inboxPollInterval) { clearInterval(inboxPollInterval); inboxPollInterval = null; }
    if (chatPollInterval) clearInterval(chatPollInterval);
    chatPollInterval = setInterval(loadMessages, 3000);
  }
}

async function loadMessages() {
  if (!currentChatCaseId) return;
  try {
    const res = await fetch(\'/gnhs-guidance/api/get_messages.php?case_id=\' + currentChatCaseId);
    const data = await res.json();
    if (data.success) {
      const container = document.getElementById(\'chatMessages\');
      if(!container) return;
      const atBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 10;
      
      container.innerHTML = data.messages.length ? data.messages.map(m => {
        const isMe = m.sender_id == data.current_user_id;
        const align = isMe ? \'flex-end\' : \'flex-start\';
        const bg = isMe ? \'var(--maroon)\' : \'var(--bg)\';
        const color = isMe ? \'#fff\' : \'var(--text)\';
        const border = isMe ? \'none\' : \'1px solid var(--border)\';
        const roleBadge = !isMe ? `<div style="font-size:10px;color:var(--text-3);margin-bottom:2px">${m.sender_role === \'admin\' ? \'Counselor\' : \'Student\'}</div>` : \'\';
        return `
          <div style="display:flex;flex-direction:column;align-items:${align};width:100%">
            ${roleBadge}
            <div style="background:${bg};color:${color};border:${border};padding:10px 14px;border-radius:18px;max-width:85%;font-size:14px;line-height:1.4;box-shadow:0 1px 2px rgba(0,0,0,0.05);">
              ${m.message}
            </div>
            <div style="font-size:10px;color:var(--text-3);margin-top:4px;">${new Date(m.created_at).toLocaleTimeString([], {hour: \'2-digit\', minute:\'2-digit\'})}</div>
          </div>`;
      }).join(\'\') : \'<div style="text-align:center;color:var(--text-3);font-size:13px;padding:20px">No messages yet. Send a message to start the conversation!</div>\';
      
      if (atBottom) {
        container.scrollTop = container.scrollHeight;
      }
    }
  } catch (e) {}
}

async function sendChatMessage() {
  if (!currentChatCaseId) return;
  const input = document.getElementById(\'chatInput\');
  const msg = input.value.trim();
  if (!msg) return;
  
  input.value = \'\';
  
  const container = document.getElementById(\'chatMessages\');
  if (container.innerHTML.includes(\'No messages yet\')) container.innerHTML = \'\';
  container.innerHTML += `
    <div style="display:flex;flex-direction:column;align-items:flex-end;width:100%">
      <div style="background:var(--maroon);color:#fff;border:none;padding:10px 14px;border-radius:18px;max-width:85%;font-size:14px;line-height:1.4;opacity:0.7;box-shadow:0 1px 2px rgba(0,0,0,0.05);">
        ${msg}
      </div>
    </div>`;
  container.scrollTop = container.scrollHeight;

  try {
    const res = await apiPost(\'/gnhs-guidance/api/send_message.php\', { case_id: currentChatCaseId, message: msg });
    if (res.success) {
      loadMessages();
    } else {
      showToast(res.message, \'error\');
    }
  } catch (e) {
    showToast(\'Failed to send message\', \'error\');
  }
}

// Start polling for inbox unread counts on load
document.addEventListener(\'DOMContentLoaded\', () => {
    loadInbox();
    setInterval(loadInbox, 10000); // Check inbox counts every 10s globally
});
';

$content .= $newChatLogic;
file_put_contents($file, $content);
echo "App.js updated.";
