<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$file = dirname(__DIR__, 2) . '/includes/layout.php';
$content = file_get_contents($file);

// Add the 'Cases Chat' to the sidebars
$content = str_replace(
"            ['icon'=>'fa-folder-open',  'label'=>'My Cases',        'href'=>'student-cases.php',     'key'=>'cases'],
            ['icon'=>'fa-archive',      'label'=>'Case Archive',    'href'=>'student-archive.php',   'key'=>'archive'],
            ['icon'=>'fa-comments',     'label'=>'Chat Archive',    'href'=>'student-chat-archive.php','key'=>'chat_archive'],",
"            ['icon'=>'fa-folder-open',  'label'=>'My Cases',        'href'=>'student-cases.php',     'key'=>'cases'],
            ['icon'=>'fa-comments',     'label'=>'Cases Chat',      'href'=>'student-chats.php',     'key'=>'chats'],
            ['icon'=>'fa-archive',      'label'=>'Case Archive',    'href'=>'student-archive.php',   'key'=>'archive'],
            ['icon'=>'fa-history',      'label'=>'Chat Archive',    'href'=>'student-chat-archive.php','key'=>'chat_archive'],", $content);

$content = str_replace(
"            ['icon'=>'fa-folder-open',  'label'=>'All Cases',       'href'=>'admin-cases.php',       'key'=>'cases'],
            ['icon'=>'fa-reply-all',    'label'=>'Follow Ups',      'href'=>'admin-followups.php',   'key'=>'followups'],
            ['icon'=>'fa-archive',      'label'=>'Archive',         'href'=>'admin-archive.php',     'key'=>'archive'],
            ['icon'=>'fa-comments',     'label'=>'Chat Archive',    'href'=>'admin-chat-archive.php','key'=>'chat_archive'],",
"            ['icon'=>'fa-folder-open',  'label'=>'All Cases',       'href'=>'admin-cases.php',       'key'=>'cases'],
            ['icon'=>'fa-comments',     'label'=>'Cases Chat',      'href'=>'admin-chats.php',       'key'=>'chats'],
            ['icon'=>'fa-reply-all',    'label'=>'Follow Ups',      'href'=>'admin-followups.php',   'key'=>'followups'],
            ['icon'=>'fa-archive',      'label'=>'Archive',         'href'=>'admin-archive.php',     'key'=>'archive'],
            ['icon'=>'fa-history',      'label'=>'Chat Archive',    'href'=>'admin-chat-archive.php','key'=>'chat_archive'],", $content);

// Replace the chatWidget
$chatWidgetStart = '  <!-- Floating Chat Widget -->';
$chatWidgetEnd = '    </div>
  </div>

</div>';

$newChatWidget = '  <!-- Floating Chat Bubble Toggle -->
  <button id="floatingChatToggle" onclick="toggleFloatingInbox()" style="position:fixed;bottom:20px;right:40px;width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg, var(--maroon), #800000);color:#fff;border:none;box-shadow:0 4px 15px rgba(0,0,0,0.2);cursor:pointer;z-index:9998;display:flex;align-items:center;justify-content:center;font-size:24px;transition:transform 0.2s;">
    <i class="fas fa-comment-dots"></i>
    <span id="globalUnreadBadge" class="hidden" style="position:absolute;top:0;right:0;background:var(--red);color:#fff;font-size:11px;font-weight:bold;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg);">0</span>
  </button>

  <!-- Floating Chat Widget -->
  <div id="chatWidget" class="hidden" style="position:fixed;bottom:90px;right:40px;width:340px;background:var(--bg);border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.2);display:flex;flex-direction:column;z-index:9999;border:1px solid var(--border);overflow:hidden;height:500px;">
    
    <!-- INBOX PANE -->
    <div id="chatInboxPane" style="display:flex;flex-direction:column;height:100%;">
      <div style="background:linear-gradient(135deg, var(--maroon), #800000);color:#fff;padding:15px;display:flex;justify-content:space-between;align-items:center;">
        <strong style="font-size:16px;">Messages</strong>
        <button onclick="toggleFloatingInbox()" style="background:none;border:none;color:#fff;cursor:pointer;"><i class="fas fa-times"></i></button>
      </div>
      <div id="chatInboxList" style="flex:1;overflow-y:auto;background:var(--bg2);">
        <!-- Inbox items loaded here -->
      </div>
    </div>

    <!-- CONVERSATION PANE -->
    <div id="chatConversationPane" class="hidden" style="display:flex;flex-direction:column;height:100%;">
      <div style="background:linear-gradient(135deg, var(--maroon), #800000);color:#fff;padding:12px 16px;display:flex;align-items:center;gap:10px;">
        <button onclick="backToInbox()" style="background:none;border:none;color:#fff;cursor:pointer;font-size:16px;padding:4px;"><i class="fas fa-arrow-left"></i></button>
        <div style="background:rgba(255,255,255,0.2);width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-user-graduate" style="font-size:14px;"></i>
        </div>
        <div style="display:flex;flex-direction:column;flex:1;">
          <strong id="chatHeaderTitle" style="font-size:14px;font-weight:700;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;">Student Name</strong>
          <span id="chatHeaderSub" style="font-size:11px;opacity:0.9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;">#C-011</span>
        </div>
        <button onclick="toggleFloatingInbox()" style="background:none;border:none;color:#fff;cursor:pointer;padding:4px;"><i class="fas fa-times"></i></button>
      </div>
      <div id="chatMessages" style="flex:1;overflow-y:auto;padding:15px;display:flex;flex-direction:column;gap:12px;background:var(--bg2);">
        <!-- Messages load here -->
      </div>
      <div style="padding:12px;border-top:1px solid var(--border);background:var(--bg);display:flex;gap:8px;align-items:center;">
        <input type="text" id="chatInput" placeholder="Message..." style="flex:1;padding:10px 16px;border-radius:24px;border:1px solid var(--border);background:var(--bg2);color:var(--text);outline:none;font-size:14px;transition:border-color 0.2s;" onfocus="this.style.borderColor=\'var(--maroon)\'" onblur="this.style.borderColor=\'var(--border)\'" onkeypress="if(event.key===\'Enter\') sendChatMessage()">
        <button onclick="sendChatMessage()" style="background:var(--maroon);color:#fff;border:none;width:40px;height:40px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform 0.1s, background 0.2s;"><i class="fas fa-paper-plane" style="margin-right:2px;margin-top:1px;"></i></button>
      </div>
    </div>
  </div>

</div>';

$pos1 = strpos($content, $chatWidgetStart);
$pos2 = strpos($content, $chatWidgetEnd, $pos1);
if ($pos1 !== false && $pos2 !== false) {
    $content = substr_replace($content, $newChatWidget, $pos1, $pos2 - $pos1 + strlen($chatWidgetEnd));
}

file_put_contents($file, $content);
echo "Layout updated.";
