<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';
$user = requireLogin('student');

$content = <<<'HTML'
<style>
  /* Hide global floating widget on this full chat page */
  #floatingChatToggle, #chatWidget { display: none !important; }

  /* Messenger Shell Container */
  .msg-app {
    display: flex;
    height: calc(100vh - 125px);
    min-height: 520px;
    background: var(--bg);
    border-radius: 14px;
    border: 1px solid var(--border);
    overflow: hidden;
    position: relative;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
  }

  /* Left Panel: Inbox List */
  .msg-inbox-pane {
    width: 380px;
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    background: var(--bg);
    flex-shrink: 0;
  }
  .msg-inbox-header {
    padding: 16px 18px 12px;
    border-bottom: 1px solid var(--border);
    background: var(--bg);
  }
  .msg-inbox-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
  }
  .msg-inbox-title-row h2 {
    font-size: 22px;
    font-weight: 800;
    margin: 0;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text);
  }
  .msg-inbox-search-box {
    position: relative;
  }
  .msg-inbox-search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-3);
    font-size: 13px;
  }
  .msg-inbox-search-input {
    width: 100%;
    padding: 9px 12px 9px 34px;
    border-radius: 20px;
    border: 1px solid var(--border);
    background: var(--bg2);
    color: var(--text);
    font-size: 13.5px;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.15s, background 0.15s;
  }
  .msg-inbox-search-input:focus {
    border-color: var(--maroon);
    background: var(--bg);
  }

  .msg-inbox-list {
    flex: 1;
    overflow-y: auto;
    padding: 6px 0;
  }

  /* Messenger Inbox Item (Facebook Messenger Style) */
  .msg-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    cursor: pointer;
    transition: background 0.15s ease;
    user-select: none;
    position: relative;
  }
  .msg-item:hover {
    background: var(--bg2);
  }
  .msg-item.active {
    background: rgba(85,0,0,0.08);
  }
  [data-theme="dark"] .msg-item.active {
    background: rgba(255,255,255,0.08);
  }
  .msg-avatar-wrap {
    position: relative;
    width: 50px;
    height: 50px;
    min-width: 50px;
    min-height: 50px;
    border-radius: 50%;
  }
  .msg-avatar-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    background: var(--bg2);
    display: block;
  }
  .msg-avatar-fallback {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--maroon), #8b1d1d);
    color: #fff;
    font-weight: 800;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-transform: uppercase;
  }
  .msg-online-badge {
    position: absolute;
    bottom: 1px;
    right: 1px;
    width: 13px;
    height: 13px;
    border-radius: 50%;
    background: #22c55e;
    border: 2px solid var(--bg);
  }

  .msg-item-body {
    flex: 1;
    min-width: 0;
    overflow: hidden;
  }
  .msg-item-top {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 2px;
  }
  .msg-item-name {
    font-size: 14.5px;
    font-weight: 700;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .msg-item-time {
    font-size: 11.5px;
    color: var(--text-3);
    white-space: nowrap;
    margin-left: 6px;
    flex-shrink: 0;
  }
  .msg-item-preview {
    font-size: 13px;
    color: var(--text-2);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 4px;
    line-height: 1.3;
  }
  .msg-item-preview.unread {
    font-weight: 800;
    color: var(--text);
  }
  .msg-item-badges {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
  }
  .msg-pill {
    font-size: 10.5px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    text-transform: capitalize;
  }
  .msg-pill-prio-high { background: #fee2e2; color: #dc2626; }
  .msg-pill-prio-medium { background: #fef3c7; color: #d97706; }
  .msg-pill-prio-low { background: #e0f2fe; color: #0284c7; }
  .msg-pill-status { background: var(--bg2); color: var(--text-3); border: 1px solid var(--border); }
  .msg-pill-unread-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--blue);
    margin-left: auto;
  }
  .msg-unread-count-badge {
    background: var(--maroon);
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    border-radius: 12px;
    padding: 1px 7px;
    margin-left: auto;
  }

  /* Right Panel: Conversation Thread */
  .msg-chat-pane {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--bg);
    min-width: 0;
  }

  /* Top Bar (Messenger Mobile / Desktop Bar) */
  .msg-header-bar {
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--bg);
    min-height: 56px;
    box-sizing: border-box;
  }
  .msg-back-btn {
    display: none;
    align-items: center;
    gap: 4px;
    background: none;
    border: none;
    color: var(--maroon);
    font-weight: 700;
    font-size: 14.5px;
    cursor: pointer;
    padding: 6px 4px;
  }
  .msg-header-partner {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
  }
  .msg-header-avatar {
    position: relative;
    width: 38px;
    height: 38px;
    min-width: 38px;
    min-height: 38px;
    border-radius: 50%;
  }
  .msg-header-info {
    flex: 1;
    min-width: 0;
  }
  .msg-header-title {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .msg-header-sub {
    margin: 1px 0 0;
    font-size: 11.5px;
    color: var(--text-2);
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .msg-header-sub .dot-online {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #22c55e;
  }
  .msg-header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .msg-icon-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: var(--bg2);
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.15s;
    font-size: 14px;
  }
  .msg-icon-btn:hover {
    background: var(--border);
  }

  /* Chat Messages Thread */
  .msg-messages-thread {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: var(--bg2);
  }
  .msg-date-divider {
    text-align: center;
    margin: 10px 0;
  }
  .msg-date-divider span {
    font-size: 11px;
    font-weight: 700;
    color: var(--text-3);
    background: var(--bg);
    padding: 3px 12px;
    border-radius: 12px;
    border: 1px solid var(--border);
  }

  /* Message Bubbles (Messenger Style) */
  .msg-row {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    max-width: 82%;
  }
  .msg-row.sent {
    align-self: flex-end;
    flex-direction: row-reverse;
  }
  .msg-row.received {
    align-self: flex-start;
  }
  .msg-bubble-avatar {
    width: 28px;
    height: 28px;
    min-width: 28px;
    min-height: 28px;
    border-radius: 50%;
    margin-bottom: 2px;
  }
  .msg-bubble-content {
    display: flex;
    flex-direction: column;
  }
  .msg-row.sent .msg-bubble-content {
    align-items: flex-end;
  }
  .msg-row.received .msg-bubble-content {
    align-items: flex-start;
  }
  .msg-sender-name {
    font-size: 11px;
    color: var(--text-3);
    margin-bottom: 3px;
    margin-left: 8px;
  }
  .msg-bubble {
    padding: 10px 15px;
    font-size: 14px;
    line-height: 1.45;
    word-break: break-word;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
  }
  .msg-row.sent .msg-bubble {
    background: var(--maroon);
    color: #fff;
    border-radius: 18px 18px 4px 18px;
  }
  .msg-row.received .msg-bubble {
    background: var(--bg);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 18px 18px 18px 4px;
  }
  .msg-timestamp {
    font-size: 10.5px;
    color: var(--text-3);
    margin-top: 3px;
    padding: 0 6px;
  }

  /* Input Footer (Messenger Style) */
  .msg-input-bar {
    padding: 12px 16px;
    border-top: 1px solid var(--border);
    background: var(--bg);
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .msg-input-field {
    flex: 1;
    padding: 11px 18px;
    border-radius: 24px;
    border: 1px solid var(--border);
    background: var(--bg2);
    color: var(--text);
    font-size: 14px;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.15s, background 0.15s;
  }
  .msg-input-field:focus {
    border-color: var(--maroon);
    background: var(--bg);
  }
  .msg-send-btn {
    width: 42px;
    height: 42px;
    min-width: 42px;
    min-height: 42px;
    border-radius: 50%;
    border: none;
    background: var(--maroon);
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    transition: opacity 0.15s, transform 0.15s;
    opacity: 0.5;
  }
  .msg-send-btn.active {
    opacity: 1;
    transform: scale(1.04);
  }

  /* Slide-in Concern Details Drawer */
  .msg-drawer {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: 340px;
    max-width: 100%;
    background: var(--bg);
    border-left: 1px solid var(--border);
    box-shadow: -4px 0 20px rgba(0,0,0,0.08);
    display: flex;
    flex-direction: column;
    z-index: 50;
    transform: translateX(100%);
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  }
  .msg-drawer.open {
    transform: translateX(0);
  }
  .msg-drawer-header {
    padding: 16px 18px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .msg-drawer-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
  }
  .msg-drawer-body {
    flex: 1;
    overflow-y: auto;
    padding: 18px;
    font-size: 13.5px;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  .msg-detail-card {
    background: var(--bg2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 14px;
  }
  .msg-detail-label {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--text-3);
    margin-bottom: 4px;
  }
  .msg-detail-val {
    color: var(--text);
    font-weight: 600;
  }

  /* Empty State / Welcome Screen */
  .msg-empty-state {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 30px;
    color: var(--text-3);
  }

  /* Mobile and Tablet / iPad Responsive Behavior (Messenger Experience) */
  @media (max-width: 992px) {
    .msg-app {
      height: calc(100vh - 100px) !important;
      min-height: 480px !important;
      border-radius: 0;
      border-left: none;
      border-right: none;
    }
    .msg-app.mobile-inbox-view .msg-inbox-pane {
      display: flex !important;
      width: 100% !important;
      border-right: none;
    }
    .msg-app.mobile-inbox-view .msg-chat-pane {
      display: none !important;
    }
    .msg-app.mobile-chat-view .msg-inbox-pane {
      display: none !important;
    }
    .msg-app.mobile-chat-view .msg-chat-pane {
      display: flex !important;
      width: 100% !important;
    }
    .msg-back-btn {
      display: inline-flex !important;
    }
    .msg-drawer {
      width: 100% !important;
    }
  }

  @media (min-width: 993px) {
    .msg-inbox-pane {
      display: flex !important;
      width: 330px !important;
    }
    .msg-chat-pane {
      display: flex !important;
    }
    .msg-back-btn {
      display: none !important;
    }
  }
</style>

<div class="msg-app mobile-inbox-view" id="msgApp">
  <!-- Left Inbox Panel (List of Concerns / Chats) -->
  <aside class="msg-inbox-pane" id="msgInboxPane">
    <div class="msg-inbox-header">
      <div class="msg-inbox-title-row">
        <h2><i class="fas fa-comments" style="color:var(--maroon)"></i> Chats</h2>
        <button type="button" class="msg-icon-btn" onclick="pageLoadInbox()" title="Refresh conversations">
          <i class="fas fa-rotate"></i>
        </button>
      </div>
      <div class="msg-inbox-search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="msgSearchInput" class="msg-inbox-search-input" placeholder="Search concerns, cases, messages..." oninput="filterInboxItems()">
      </div>
    </div>

    <div class="msg-inbox-list" id="msgInboxList">
      <div style="padding:40px 20px;text-align:center;color:var(--text-3);">
        <i class="fas fa-spinner fa-spin" style="font-size:24px;margin-bottom:8px;display:block"></i>
        Loading your concerns...
      </div>
    </div>
  </aside>

  <!-- Right Chat Panel (Active Conversation) -->
  <main class="msg-chat-pane" id="msgChatPane">
    <!-- Header App Bar -->
    <header class="msg-header-bar" id="msgHeaderBar">
      <button type="button" class="msg-back-btn" onclick="showMobileInbox()">
        <i class="fas fa-chevron-left"></i> Chats
      </button>

      <div class="msg-header-partner" id="msgHeaderPartner">
        <div class="msg-header-avatar" id="msgHeaderAvatar">
          <div class="msg-avatar-fallback"><i class="fas fa-user-tie"></i></div>
          <span class="msg-online-badge"></span>
        </div>
        <div class="msg-header-info">
          <div class="msg-header-title" id="msgHeaderTitle">Select a conversation</div>
          <div class="msg-header-sub" id="msgHeaderSub">
            <span class="dot-online"></span> Active guidance channel
          </div>
        </div>
      </div>

      <div class="msg-header-actions">
        <button type="button" class="msg-icon-btn" id="msgDetailsBtn" onclick="toggleDetailsDrawer()" title="View Case Details" disabled>
          <i class="fas fa-circle-info"></i>
        </button>
      </div>
    </header>

    <!-- Messages Container -->
    <div class="msg-messages-thread" id="msgMessagesThread">
      <div class="msg-empty-state">
        <i class="fas fa-comment-dots" style="font-size:48px;opacity:0.3;margin-bottom:12px;"></i>
        <h4 style="margin:0 0 6px;color:var(--text);font-size:16px;">Direct Guidance Messaging</h4>
        <p style="margin:0;max-width:280px;font-size:13px;">Select an active concern from your chat list to talk confidentially with a guidance counselor.</p>
      </div>
    </div>

    <!-- Input Bar -->
    <div class="msg-input-bar" id="msgInputBar">
      <input type="text" id="msgInput" class="msg-input-field" placeholder="Type a message..." disabled autocomplete="off" oninput="toggleSendBtnState()" onkeypress="if(event.key==='Enter') pageSendMessage()">
      <button type="button" id="msgSendBtn" class="msg-send-btn" onclick="pageSendMessage()" disabled title="Send message">
        <i class="fas fa-paper-plane"></i>
      </button>
    </div>

    <!-- Concern Details Drawer (Slides in on cellphone or PC) -->
    <aside class="msg-drawer" id="msgDrawer">
      <div class="msg-drawer-header">
        <h3><i class="fas fa-folder-open" style="color:var(--maroon)"></i> Concern Details</h3>
        <button type="button" class="msg-icon-btn" onclick="toggleDetailsDrawer(false)" title="Close details">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="msg-drawer-body" id="msgDrawerBody">
        <div style="color:var(--text-3);text-align:center;padding:20px;">No concern selected.</div>
      </div>
    </aside>
  </main>
</div>

<script>
let allInboxData = [];
let pageCurrentCaseId = null;
let currentCaseInfo = null;
let inboxPollTimer = null;
let messagesPollTimer = null;

function showMobileInbox() {
  const app = document.getElementById('msgApp');
  if (app) {
    app.classList.remove('mobile-chat-view');
    app.classList.add('mobile-inbox-view');
  }
  toggleDetailsDrawer(false);
}

function showMobileChat() {
  const app = document.getElementById('msgApp');
  if (app) {
    app.classList.remove('mobile-inbox-view');
    app.classList.add('mobile-chat-view');
  }
}

function toggleDetailsDrawer(forceState) {
  const drawer = document.getElementById('msgDrawer');
  if (!drawer) return;
  if (typeof forceState === 'boolean') {
    drawer.classList.toggle('open', forceState);
  } else {
    drawer.classList.toggle('open');
  }
}

function toggleSendBtnState() {
  const input = document.getElementById('msgInput');
  const btn = document.getElementById('msgSendBtn');
  if (!input || !btn) return;
  const hasText = input.value.trim().length > 0;
  btn.disabled = !hasText || !pageCurrentCaseId;
  btn.classList.toggle('active', hasText && !!pageCurrentCaseId);
}

function formatMsgTime(isoStr) {
  if (!isoStr) return '';
  const d = new Date(isoStr);
  if (isNaN(d.getTime())) return '';
  const now = new Date();
  const isToday = d.toDateString() === now.toDateString();
  const timeStr = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  if (isToday) return timeStr;
  const yesterday = new Date();
  yesterday.setDate(now.getDate() - 1);
  if (d.toDateString() === yesterday.toDateString()) return 'Yesterday ' + timeStr;
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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

  return { success: false, message: 'Could not connect to server.' };
}

async function pageLoadInbox() {
  try {
    const params = new URLSearchParams(window.location.search);
    const archiveId = params.get('archive_id') || '';
    const caseId = params.get('case_id') || '';
    const query = caseId ? '?case_id=' + caseId : (archiveId ? '?archive_id=' + archiveId : '');

    const data = await apiFetch('get_chat_inbox.php' + query);
    const list = document.getElementById('msgInboxList');
    if (!list) return;

    if (!data.success) {
      list.innerHTML = `
        <div style="padding:32px 18px;text-align:center;color:var(--text-3);">
          <i class="fas fa-exclamation-circle" style="color:var(--danger);font-size:24px;margin-bottom:8px;display:block"></i>
          <span>${data.message || 'Unable to load concerns.'}</span><br>
          <button onclick="pageLoadInbox()" class="btn-sm-primary" style="margin-top:10px;padding:5px 14px;font-size:12px">Retry</button>
        </div>`;
      return;
    }

    allInboxData = data.inbox || [];
    renderInboxItems(allInboxData);

    // Auto open if case_id or archive_id is requested in URL
    if (caseId && !pageCurrentCaseId) {
      const target = allInboxData.find(c => c.id == caseId);
      if (target) openConversation(target.id);
    } else if (archiveId && !pageCurrentCaseId) {
      const target = allInboxData.find(c => c.id == archiveId);
      if (target) openConversation(target.id);
    } else if (!pageCurrentCaseId && window.innerWidth > 992 && allInboxData.length > 0) {
      // On desktop, auto-open first chat
      openConversation(allInboxData[0].id);
    }
  } catch(e) {
    console.error(e);
  }
}

function filterInboxItems() {
  const q = (document.getElementById('msgSearchInput')?.value || '').toLowerCase().trim();
  if (!q) {
    renderInboxItems(allInboxData);
    return;
  }
  const filtered = allInboxData.filter(c => {
    const hay = [
      c.subject || '',
      c.concern_type || '',
      c.case_number || '',
      c.counselor_name || '',
      c.latest_message || ''
    ].join(' ').toLowerCase();
    return hay.includes(q);
  });
  renderInboxItems(filtered);
}

function renderInboxItems(items) {
  const list = document.getElementById('msgInboxList');
  if (!list) return;

  if (items.length === 0) {
    list.innerHTML = `
      <div style="padding:40px 20px;text-align:center;color:var(--text-3);font-size:13.5px;">
        <i class="fas fa-inbox" style="font-size:36px;opacity:0.3;margin-bottom:12px;display:block"></i>
        No active concerns found.<br>
        <a href="student-submit.php" style="color:var(--maroon);font-weight:700;display:inline-block;margin-top:10px;">
          <i class="fas fa-plus"></i> Submit a concern
        </a>
      </div>`;
    return;
  }

  list.innerHTML = items.map(c => {
    const isActive = pageCurrentCaseId == c.id;
    const prio = (c.priority || 'medium').toLowerCase();
    const prioClass = 'msg-pill-prio-' + prio;
    const typeLabel = (c.concern_type || 'General').replace(/_/g, ' ');
    const caseNum = c.case_number ? '#' + c.case_number : '#' + c.id;
    const partnerName = c.subject ? c.subject : (c.counselor_name || 'Guidance Counselor');
    const partnerAvatar = c.counselor_avatar || '';
    const partnerInitials = partnerName.split(' ').map(n=>n[0]).slice(0,2).join('') || 'GC';

    let preview = c.latest_message ? escapeHtml(c.latest_message) : 'No messages yet';
    const hasUnread = parseInt(c.unread_count || 0) > 0;
    const timeLabel = formatMsgTime(c.latest_message_time || c.submitted_at);

    const avatarHtml = partnerAvatar
      ? `<img src="${partnerAvatar}" class="msg-avatar-img" alt="${escapeHtml(partnerName)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
         <div class="msg-avatar-fallback" style="display:none">${partnerInitials}</div>`
      : `<div class="msg-avatar-fallback">${partnerInitials}</div>`;

    return `
      <div class="msg-item ${isActive ? 'active' : ''}" onclick="openConversation(${c.id})" data-case-id="${c.id}">
        <div class="msg-avatar-wrap">
          ${avatarHtml}
          <span class="msg-online-badge"></span>
        </div>
        <div class="msg-item-body">
          <div class="msg-item-top">
            <span class="msg-item-name">${escapeHtml(partnerName)}</span>
            <span class="msg-item-time">${timeLabel}</span>
          </div>
          <div class="msg-item-preview ${hasUnread ? 'unread' : ''}">
            ${hasUnread ? '<strong>' + preview + '</strong>' : preview}
          </div>
          <div class="msg-item-badges">
            <span class="msg-pill ${prioClass}">${prio} priority</span>
            <span class="msg-pill msg-pill-status">${caseNum}</span>
            <span class="msg-pill msg-pill-status">${typeLabel}</span>
            ${hasUnread ? `<span class="msg-unread-count-badge">${c.unread_count}</span>` : ''}
          </div>
        </div>
      </div>
    `;
  }).join('');
}

async function openConversation(caseId) {
  pageCurrentCaseId = caseId;

  // Highlight in inbox
  document.querySelectorAll('.msg-item').forEach(el => {
    el.classList.toggle('active', el.dataset.caseId == caseId);
  });

  // Switch mobile/tablet view to chat
  showMobileChat();

  // Instantly populate header and details drawer from inbox data so it immediately shows the concern details
  const itemData = allInboxData.find(c => c.id == caseId);
  if (itemData) {
    currentCaseInfo = itemData;
    renderChatHeader(itemData);
    renderDrawerDetails(itemData);
    itemData.unread_count = 0;
    renderInboxItems(allInboxData);
  }

  // Enable input & send button & details button
  const input = document.getElementById('msgInput');
  const detailsBtn = document.getElementById('msgDetailsBtn');
  if (input) {
    input.disabled = false;
    input.focus();
  }
  if (detailsBtn) detailsBtn.disabled = false;
  toggleSendBtnState();

  // Load conversation messages
  await pageLoadMessages();

  // Reset polling
  if (messagesPollTimer) clearInterval(messagesPollTimer);
  messagesPollTimer = setInterval(pageLoadMessages, 3500);
}

async function pageLoadMessages() {
  if (!pageCurrentCaseId) return;

  try {
    const data = await apiFetch('get_messages.php?case_id=' + pageCurrentCaseId);
    if (!data.success) return;

    currentCaseInfo = Object.assign({}, currentCaseInfo, data.case_info || {});
    renderChatHeader(currentCaseInfo);
    renderDrawerDetails(currentCaseInfo);
    renderMessagesThread(data.messages || [], data.current_user_id);
  } catch(e) {
    console.error(e);
  }
}

function renderChatHeader(ci) {
  const titleEl = document.getElementById('msgHeaderTitle');
  const subEl = document.getElementById('msgHeaderSub');
  const avatarEl = document.getElementById('msgHeaderAvatar');

  const titleText = ci.subject ? ci.subject : (ci.counselor_name || 'Guidance Counselor');
  const caseNum = ci.case_number ? `#${ci.case_number}` : `#${ci.id}`;
  const prio = (ci.priority || 'medium').toUpperCase();
  const typeLabel = (ci.concern_type || 'General').replace(/_/g, ' ');
  const counselorLabel = ci.counselor_name ? `Counselor: ${ci.counselor_name}` : 'Counselor Assigned';

  if (titleEl) titleEl.innerText = titleText;
  if (subEl) {
    subEl.innerHTML = `<span class="dot-online"></span> ${caseNum} • ${typeLabel} (${prio}) • ${counselorLabel}`;
  }

  if (avatarEl) {
    const avatar = ci.counselor_avatar;
    const initials = titleText.split(' ').map(n=>n[0]).slice(0,2).join('') || 'GC';
    if (avatar) {
      avatarEl.innerHTML = `
        <img src="${avatar}" class="msg-avatar-img" alt="${escapeHtml(titleText)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div class="msg-avatar-fallback" style="display:none">${initials}</div>
        <span class="msg-online-badge"></span>`;
    } else {
      avatarEl.innerHTML = `
        <div class="msg-avatar-fallback">${initials}</div>
        <span class="msg-online-badge"></span>`;
    }
  }
}

function renderDrawerDetails(ci) {
  const body = document.getElementById('msgDrawerBody');
  if (!body) return;

  const caseNum = ci.case_number ? '#' + ci.case_number : '#' + (ci.id || '');
  const statusLabel = (ci.status || 'Pending').replace(/_/g, ' ').toUpperCase();
  const dateStr = ci.submitted_at ? new Date(ci.submitted_at).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';

  body.innerHTML = `
    <div class="msg-detail-card" style="border-left:4px solid var(--maroon);">
      <div class="msg-detail-label">Case Number</div>
      <div class="msg-detail-val" style="font-size:16px;color:var(--maroon);">${caseNum}</div>
    </div>

    <div class="msg-detail-card">
      <div class="msg-detail-label">Subject / Title</div>
      <div class="msg-detail-val">${escapeHtml(ci.subject || 'Guidance Concern')}</div>
    </div>

    <div class="msg-detail-card">
      <div class="msg-detail-label">Concern Type & Priority</div>
      <div class="msg-detail-val" style="display:flex;gap:6px;align-items:center;margin-top:4px;">
        <span class="msg-pill msg-pill-status">${escapeHtml(ci.concern_type || 'General')}</span>
        <span class="msg-pill msg-pill-prio-${(ci.priority||'medium').toLowerCase()}">${escapeHtml(ci.priority || 'Medium')}</span>
      </div>
    </div>

    <div class="msg-detail-card">
      <div class="msg-detail-label">Status & Date Filed</div>
      <div class="msg-detail-val">
        <span class="status-badge status-${ci.status || 'pending'}">${statusLabel}</span>
        <span style="font-size:12px;color:var(--text-3);margin-left:8px;">${dateStr}</span>
      </div>
    </div>

    <div class="msg-detail-card">
      <div class="msg-detail-label">Report Description</div>
      <div class="msg-detail-val" style="font-size:13px;line-height:1.5;white-space:pre-wrap;margin-top:4px;">${escapeHtml(ci.description || 'No description provided.')}</div>
    </div>

    ${ci.witnesses ? `
    <div class="msg-detail-card">
      <div class="msg-detail-label"><i class="fas fa-user-group"></i> Witnesses</div>
      <div class="msg-detail-val" style="font-size:13px;">${escapeHtml(ci.witnesses)}</div>
    </div>` : ''}

    ${ci.resolution ? `
    <div class="msg-detail-card" style="background:#f0fdf4;border-color:#bbf7d0;">
      <div class="msg-detail-label" style="color:#166534;"><i class="fas fa-check-circle"></i> Official Resolution</div>
      <div class="msg-detail-val" style="color:#14532d;font-size:13px;line-height:1.5;white-space:pre-wrap;">${escapeHtml(ci.resolution)}</div>
    </div>` : ''}
  `;
}

function renderMessagesThread(messages, currentUserId) {
  const container = document.getElementById('msgMessagesThread');
  if (!container) return;

  if (messages.length === 0) {
    container.innerHTML = `
      <div class="msg-empty-state">
        <div style="width:58px;height:58px;border-radius:50%;background:rgba(85,0,0,0.1);color:var(--maroon);display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:12px;">
          <i class="fas fa-paper-plane"></i>
        </div>
        <h4 style="margin:0 0 6px;color:var(--text);font-size:15px;">Send a message to your counselor</h4>
        <p style="margin:0;max-width:280px;font-size:12.5px;color:var(--text-3);">Your guidance conversation is private, secure, and encrypted according to school guidance ethics.</p>
      </div>`;
    return;
  }

  let html = '';
  let lastDate = '';

  messages.forEach(m => {
    const isSent = (m.sender_id == currentUserId);
    const dateObj = new Date(m.created_at);
    const dateHeader = dateObj.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
    const timeFormatted = dateObj.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

    if (dateHeader !== lastDate) {
      lastDate = dateHeader;
      html += `<div class="msg-date-divider"><span>${dateHeader}</span></div>`;
    }

    const senderAvatar = m.sender_avatar;
    const senderInitials = (m.sender_name || 'U').split(' ').map(n=>n[0]).slice(0,2).join('');
    const avatarHtml = senderAvatar
      ? `<img src="${senderAvatar}" class="msg-avatar-img msg-bubble-avatar" alt="${escapeHtml(m.sender_name)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
         <div class="msg-avatar-fallback msg-bubble-avatar" style="display:none;font-size:11px;">${senderInitials}</div>`
      : `<div class="msg-avatar-fallback msg-bubble-avatar" style="font-size:11px;">${senderInitials}</div>`;

    html += `
      <div class="msg-row ${isSent ? 'sent' : 'received'}">
        ${!isSent ? avatarHtml : ''}
        <div class="msg-bubble-content">
          ${!isSent ? `<span class="msg-sender-name">${escapeHtml(m.sender_name)}</span>` : ''}
          <div class="msg-bubble">
            ${escapeHtml(m.message)}
          </div>
          <span class="msg-timestamp">${timeFormatted}</span>
        </div>
      </div>
    `;
  });

  // Preserve scroll position if user is reading up, or scroll to bottom if at bottom
  const isNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 120;
  container.innerHTML = html;
  if (isNearBottom) {
    container.scrollTop = container.scrollHeight;
  }
}

async function pageSendMessage() {
  if (!pageCurrentCaseId) return;
  const input = document.getElementById('msgInput');
  const msg = input.value.trim();
  if (!msg) return;

  input.value = '';
  toggleSendBtnState();

  // Optimistic UI insert
  const thread = document.getElementById('msgMessagesThread');
  if (thread && thread.innerHTML.includes('msg-empty-state')) thread.innerHTML = '';
  if (thread) {
    const timeStr = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    thread.innerHTML += `
      <div class="msg-row sent">
        <div class="msg-bubble-content">
          <div class="msg-bubble" style="opacity:0.8;">
            ${escapeHtml(msg)}
          </div>
          <span class="msg-timestamp">${timeStr} <i class="fas fa-check" style="font-size:9px"></i></span>
        </div>
      </div>`;
    thread.scrollTop = thread.scrollHeight;
  }

  try {
    const isNested = window.location.pathname.startsWith('/gnhs-guidance');
    const apiPath = isNested ? '/gnhs-guidance/api/send_message.php' : '/api/send_message.php';
    const res = await apiPost(apiPath, { case_id: pageCurrentCaseId, message: msg });
    if (res.success) {
      await pageLoadMessages();
      await pageLoadInbox();
    } else {
      showToast(res.message || 'Failed to send message', 'error');
    }
  } catch(e) {
    showToast('Failed to send message', 'error');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  pageLoadInbox();
  inboxPollTimer = setInterval(pageLoadInbox, 6000);
});
</script>
HTML;

renderLayout($user, 'Cases Chat', 'chats', $content);
