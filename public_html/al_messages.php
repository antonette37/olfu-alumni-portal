<?php
session_start();
require_once 'config.php';
alumni_otp_gate_after_session();
if (!isset($_SESSION['user_id'])) {
    $redirectTarget = 'al_messages.php';
    if (isset($_GET['to']) && $_GET['to'] !== '') $redirectTarget .= '?to=' . urlencode($_GET['to']);
    header('Location: al_login.php?redirect=' . urlencode($redirectTarget));
    exit();
}
$user_id = $_SESSION['user_id'];
 
$sql = "SELECT * FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
 
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notification_count = count(array_filter($notifications, fn($n) => !$n['is_read']));
 
$sql = "SELECT m.*, s.firstname as sender_firstname, s.lastname as sender_lastname, s.photo as sender_photo, r.firstname as receiver_firstname, r.lastname as receiver_lastname, r.photo as receiver_photo FROM messages m JOIN itcp s ON m.sender_id = s.id JOIN itcp r ON m.receiver_id = r.id WHERE m.sender_id = ? OR m.receiver_id = ? ORDER BY m.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
 
$flags = [];
$fs = $conn->prepare('SELECT other_user_id, archived_at, deleted_at FROM conversation_flags WHERE user_id = ?');
if ($fs) { $fs->bind_param('i', $user_id); $fs->execute(); $fr = $fs->get_result(); while ($row = $fr->fetch_assoc()) $flags[(int)$row['other_user_id']] = $row; $fs->close(); }
 
// Build conversations
$conversations = [];
foreach ($messages as $msg) {
    $key = min($msg['sender_id'],$msg['receiver_id']).'-'.max($msg['sender_id'],$msg['receiver_id']);
    if (!isset($conversations[$key])) {
        $conversations[$key] = ['latest_message'=>$msg,'unread_count'=>0,'archived'=>false,'deleted'=>false];
    }
    if (!$msg['is_read'] && $msg['receiver_id'] == $user_id) $conversations[$key]['unread_count']++;
    $otherId = ($msg['sender_id'] == $user_id) ? $msg['receiver_id'] : $msg['sender_id'];
    if (isset($flags[$otherId])) {
        $conversations[$key]['archived'] = !empty($flags[$otherId]['archived_at']);
        $conversations[$key]['deleted'] = !empty($flags[$otherId]['deleted_at']);
    }
}
uasort($conversations, fn($a,$b) => strtotime($b['latest_message']['created_at']) - strtotime($a['latest_message']['created_at']));
 
$alumni_sql = "SELECT id, firstname, lastname FROM itcp WHERE id != ? ORDER BY lastname, firstname";
$astmt = $conn->prepare($alumni_sql);
$astmt->bind_param("i", $user_id);
$astmt->execute();
$alumni_list = $astmt->get_result()->fetch_all(MYSQLI_ASSOC);
 
$urlTo = isset($_GET['to']) ? (int)$_GET['to'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages — CCS Alumni</title>
<link rel="icon" href="olfulogo.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{
  --forest:#0d2e18;--pine:#133d23;--leaf:#1b5e35;--moss:#2d7a4f;--fern:#3d9966;
  --sage:#a8c9b0;--mist:#e8f2ec;--snow:#f5f9f6;--cream:#faf8f3;--white:#ffffff;
  --gold:#b8922a;--gold-lt:#e0b84a;--ink:#0c1a10;--charcoal:#2a3d30;
  --slate:#4a6355;--silver:#8aab96;--fog:#c8ddd2;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Outfit',system-ui,sans-serif;background:var(--cream);color:var(--ink)}
 
/* ── MAIN LAYOUT ── */
.msg-shell{display:flex;height:calc(100vh - 4rem);margin-top:4rem}
@media(max-width:768px){.msg-shell{flex-direction:column;height:auto;margin-top:0}}
 
/* ── SIDEBAR ── */
.msg-sidebar{
  width:340px;flex-shrink:0;
  background:var(--white);
  border-right:1px solid var(--fog);
  display:flex;flex-direction:column;
  overflow:hidden;
}
@media(max-width:768px){.msg-sidebar{width:100%;border-right:none;border-bottom:1px solid var(--fog);max-height:45vh}}
 
.sidebar-head{
  padding:1.25rem 1.25rem .875rem;
  border-bottom:1px solid var(--mist);
  background:var(--white);
}
.sidebar-title{
  display:flex;align-items:center;justify-content:space-between;
  font-family:'DM Serif Display',serif;font-size:1.2rem;color:var(--forest);
  margin-bottom:.875rem;
}
.compose-fab{
  width:32px;height:32px;border-radius:10px;background:var(--forest);
  border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;
  font-size:.8rem;transition:background .2s;
}
.compose-fab:hover{background:var(--pine)}
 
.search-wrap{position:relative}
.search-wrap i{position:absolute;left:.875rem;top:50%;transform:translateY(-50%);color:var(--silver);font-size:.8rem;pointer-events:none}
.search-input{
  width:100%;height:38px;background:var(--snow);border:1.5px solid var(--fog);
  border-radius:10px;padding:0 .875rem 0 2.25rem;
  font-family:'Outfit',sans-serif;font-size:.84rem;color:var(--ink);outline:none;
  transition:border-color .2s;
}
.search-input:focus{border-color:var(--leaf)}
 
.conv-list{flex:1;overflow-y:auto}
.conv-list::-webkit-scrollbar{width:4px}
.conv-list::-webkit-scrollbar-thumb{background:var(--fog);border-radius:2px}
 
.conv-item{
  display:flex;align-items:center;gap:.875rem;
  padding:.875rem 1.25rem;border-bottom:1px solid rgba(200,221,210,.3);
  cursor:pointer;transition:background .15s;position:relative;
}
.conv-item:hover{background:var(--snow)}
.conv-item.active{background:var(--mist);border-left:3px solid var(--leaf)}
 
.conv-avatar{
  width:44px;height:44px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,var(--leaf),var(--moss));
  display:flex;align-items:center;justify-content:center;
  font-family:'DM Serif Display',serif;font-size:1rem;color:#fff;
  overflow:hidden;
}
.conv-avatar img{width:100%;height:100%;object-fit:cover}
 
.conv-info{flex:1;min-width:0}
.conv-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.2rem}
.conv-name{font-weight:600;font-size:.88rem;color:var(--forest);truncate:clip}
.conv-time{font-size:.72rem;color:var(--silver);white-space:nowrap;margin-left:.5rem}
.conv-subject{font-size:.8rem;font-weight:500;color:var(--charcoal);margin-bottom:.1rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.conv-preview{font-size:.76rem;color:var(--silver);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
 
.unread-dot{
  width:8px;height:8px;border-radius:50%;background:var(--leaf);
  flex-shrink:0;box-shadow:0 0 0 2px var(--white);
}
.new-badge{
  font-size:.65rem;font-weight:700;padding:.15rem .5rem;
  background:var(--leaf);color:#fff;border-radius:999px;white-space:nowrap;flex-shrink:0;
}
.archived-tag{font-size:.68rem;color:var(--silver);font-style:italic}
 
/* Hover actions */
.conv-actions{
  position:absolute;right:.75rem;bottom:.5rem;
  display:none;gap:.35rem;
}
.conv-item:hover .conv-actions{display:flex}
.ca-btn{
  font-size:.68rem;padding:.2rem .5rem;border:1px solid var(--fog);
  border-radius:6px;background:var(--white);cursor:pointer;
  color:var(--slate);transition:all .15s;white-space:nowrap;
}
.ca-btn:hover{background:var(--mist);border-color:var(--sage)}
.ca-btn.del:hover{background:#fee2e2;border-color:#fca5a5;color:#dc2626}
 
/* ── MAIN PANEL ── */
.msg-main{flex:1;display:flex;flex-direction:column;min-width:0;overflow:hidden}
 
/* Chat header */
.chat-head{
  display:none;align-items:center;justify-content:space-between;
  padding:1rem 1.5rem;background:var(--white);border-bottom:1px solid var(--mist);
  box-shadow:0 1px 4px rgba(13,46,24,.05);
}
.chat-head.visible{display:flex}
.chat-peer{display:flex;align-items:center;gap:.875rem}
.chat-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--leaf),var(--moss));color:#fff;display:flex;align-items:center;justify-content:center;font-family:'DM Serif Display',serif;font-size:.95rem;overflow:hidden;flex-shrink:0}
.chat-avatar img{width:100%;height:100%;object-fit:cover}
.chat-name{font-family:'DM Serif Display',serif;font-size:1.1rem;color:var(--forest)}
.chat-status{font-size:.75rem;color:var(--silver)}
 
/* Thread */
.chat-thread{flex:1;overflow-y:auto;padding:1.5rem;display:flex;flex-direction:column;gap:.875rem}
.chat-thread::-webkit-scrollbar{width:4px}
.chat-thread::-webkit-scrollbar-thumb{background:var(--fog);border-radius:2px}
 
.msg-row{display:flex;align-items:flex-end;gap:.625rem}
.msg-row.mine{flex-direction:row-reverse}
.msg-tiny-avatar{width:28px;height:28px;border-radius:50%;flex-shrink:0;overflow:hidden;background:linear-gradient(135deg,var(--leaf),var(--moss));display:flex;align-items:center;justify-content:center;font-size:.65rem;color:#fff;font-weight:700}
.msg-tiny-avatar img{width:100%;height:100%;object-fit:cover}
.msg-bubble{
  max-width:68%;padding:.75rem 1rem;border-radius:16px;
  font-size:.86rem;line-height:1.6;word-break:break-word;white-space:pre-wrap;
}
.msg-bubble.mine{background:var(--forest);color:#fff;border-radius:16px 16px 4px 16px}
.msg-bubble.theirs{background:var(--white);color:var(--ink);border:1px solid var(--fog);border-radius:16px 16px 16px 4px;box-shadow:0 1px 3px rgba(13,46,24,.06)}
.msg-time{font-size:.68rem;color:var(--silver);margin-top:.25rem;text-align:right}
.msg-time.theirs{text-align:left}
 
.date-divider{display:flex;align-items:center;gap:.75rem;margin:.5rem 0;color:var(--silver);font-size:.72rem}
.date-divider::before,.date-divider::after{content:'';flex:1;height:1px;background:var(--fog)}
 
/* Input area */
.chat-input-wrap{
  padding:1rem 1.5rem;background:var(--white);border-top:1px solid var(--mist);
}
.chat-form{display:flex;align-items:flex-end;gap:.75rem}
.chat-textarea{
  flex:1;min-height:44px;max-height:120px;resize:none;overflow-y:auto;
  border:1.5px solid var(--fog);border-radius:12px;padding:.625rem .875rem;
  font-family:'Outfit',sans-serif;font-size:.88rem;color:var(--ink);background:var(--snow);
  outline:none;transition:border-color .2s;line-height:1.5;
}
.chat-textarea:focus{border-color:var(--leaf);background:var(--white)}
.send-btn{
  width:44px;height:44px;border-radius:12px;background:var(--forest);border:none;
  color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;
  font-size:.9rem;flex-shrink:0;transition:all .2s;box-shadow:0 2px 6px rgba(13,46,24,.2);
}
.send-btn:hover{background:var(--pine);transform:translateY(-1px)}
 
/* Compose panel */
.compose-head{
  display:none;align-items:center;justify-content:space-between;
  padding:1rem 1.5rem;background:var(--white);border-bottom:1px solid var(--mist);
}
.compose-head.visible{display:flex}
.compose-head-title{font-family:'DM Serif Display',serif;font-size:1.1rem;color:var(--forest);display:flex;align-items:center;gap:.5rem}
.compose-head-title i{color:var(--moss);font-size:.9rem}
.compose-close{background:none;border:none;cursor:pointer;color:var(--silver);font-size:1rem;padding:.25rem}
.compose-close:hover{color:var(--forest)}
 
.compose-body{
  display:none;flex:1;padding:2rem;overflow-y:auto;
}
.compose-body.visible{display:block}
.compose-form{max-width:600px}
.cf-group{margin-bottom:1.25rem}
.cf-label{display:block;font-size:.7rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--charcoal);margin-bottom:.4rem}
.cf-control{
  width:100%;height:44px;border:1.5px solid var(--fog);border-radius:10px;
  padding:0 .875rem;font-family:'Outfit',sans-serif;font-size:.88rem;
  color:var(--ink);background:var(--snow);outline:none;transition:border-color .2s;
}
.cf-control:focus{border-color:var(--leaf);background:var(--white)}
textarea.cf-control{height:auto;padding:.75rem .875rem;resize:vertical;min-height:120px}
.cf-actions{display:flex;justify-content:flex-end;gap:.75rem;margin-top:1.25rem}
.btn-cancel{background:var(--snow);border:1px solid var(--fog);color:var(--charcoal);border-radius:10px;padding:.6rem 1.25rem;font-family:'Outfit',sans-serif;font-size:.86rem;cursor:pointer;transition:all .2s}
.btn-cancel:hover{background:var(--fog)}
.btn-send{background:var(--forest);color:#fff;border:none;border-radius:10px;padding:.6rem 1.5rem;font-family:'Outfit',sans-serif;font-size:.86rem;font-weight:600;cursor:pointer;transition:background .2s}
.btn-send:hover{background:var(--pine)}
 
/* Default empty state */
.msg-empty{
  display:flex;flex:1;align-items:center;justify-content:center;
  flex-direction:column;gap:1rem;text-align:center;padding:2rem;
}
.msg-empty-icon{width:80px;height:80px;border-radius:50%;background:var(--mist);display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--sage)}
.msg-empty-title{font-family:'DM Serif Display',serif;font-size:1.3rem;color:var(--forest)}
.msg-empty-sub{font-size:.85rem;color:var(--silver);max-width:280px}
.btn-compose{display:inline-flex;align-items:center;gap:.5rem;background:var(--forest);color:#fff;border:none;border-radius:12px;padding:.7rem 1.5rem;font-family:'Outfit',sans-serif;font-size:.88rem;font-weight:600;cursor:pointer;transition:all .2s;box-shadow:0 2px 8px rgba(13,46,24,.2)}
.btn-compose:hover{background:var(--pine);transform:translateY(-1px)}
 
/* Archive/show toggle */
.arch-toggle{display:flex;align-items:center;gap:.5rem;font-size:.75rem;color:var(--slate);padding:.5rem 1.25rem;border-top:1px solid var(--mist)}
.arch-toggle label{display:flex;align-items:center;gap:.4rem;cursor:pointer}
.arch-toggle input{accent-color:var(--leaf)}
 
/* Empty conv list */
.conv-empty{padding:3rem 1.25rem;text-align:center;color:var(--silver)}
.conv-empty i{font-size:2rem;margin-bottom:.75rem;display:block;color:var(--fog)}
.conv-empty p{font-size:.85rem}
 
/* FAB */
#fab{
  position:fixed;bottom:1.5rem;right:1.5rem;z-index:50;
  width:52px;height:52px;border-radius:50%;background:var(--forest);
  color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;box-shadow:0 4px 16px rgba(13,46,24,.3);transition:all .2s;
}
#fab:hover{background:var(--pine);transform:scale(1.08)}
 
/* Lightbox */
#lightbox{position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:600;display:none;align-items:center;justify-content:center}
#lightbox.open{display:flex}
#lb-img{max-width:90vw;max-height:90vh;border-radius:8px}
#lb-close{position:absolute;top:1rem;right:1.25rem;color:#fff;font-size:1.5rem;cursor:pointer;background:none;border:none}
 
/* Scroll anchor */
.chat-thread-wrap{display:flex;flex-direction:column;flex:1;overflow:hidden}
</style>
</head>
<body>
<?php include __DIR__ . '/al_header_universal.php'; ?>
 
<div class="msg-shell">
  <!-- ── SIDEBAR ── -->
  <div class="msg-sidebar">
    <div class="sidebar-head">
      <div class="sidebar-title">
        Messages
        <button class="compose-fab" onclick="openCompose()" title="New message"><i class="fas fa-plus"></i></button>
      </div>
      <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input class="search-input" id="convSearch" placeholder="Search conversations…" oninput="filterConversations(this.value)">
      </div>
    </div>
 
    <div class="conv-list" id="convList">
      <?php
      $showArchived = isset($_GET['show_archived']) && $_GET['show_archived'] === '1';
      $convRendered = 0;
      foreach ($conversations as $conv):
        if ($conv['deleted']) continue;
        if ($conv['archived'] && !$showArchived) continue;
        $convRendered++;
        $msg = $conv['latest_message'];
        $otherId = ($msg['sender_id'] == $user_id) ? $msg['receiver_id'] : $msg['sender_id'];
        $otherFn = ($msg['sender_id'] == $user_id) ? $msg['receiver_firstname'] : $msg['sender_firstname'];
        $otherLn = ($msg['sender_id'] == $user_id) ? $msg['receiver_lastname'] : $msg['sender_lastname'];
        $otherName = trim($otherFn . ' ' . $otherLn);
        $otherPhoto = ($msg['sender_id'] == $user_id) ? $msg['receiver_photo'] : $msg['sender_photo'];
        $initials = strtoupper(substr($otherFn,0,1).substr($otherLn,0,1));
        $photoUrl = '';
        if ($otherPhoto) {
          if (strpos($otherPhoto,'http') === 0) $photoUrl = $otherPhoto;
          elseif (file_exists(__DIR__.'/uploads/'.$otherPhoto)) $photoUrl = 'serve_profile_image.php?img='.urlencode($otherPhoto);
        }
        $time = strtotime($msg['created_at']); $now = time(); $diff = $now - $time;
        $timeStr = $diff < 3600 ? floor($diff/60).'m' : ($diff < 86400 ? floor($diff/3600).'h' : ($diff < 604800 ? floor($diff/86400).'d' : date('M d',$time)));
        $isActive = ($urlTo == $otherId);
        $convJson = htmlspecialchars(json_encode($conv, JSON_HEX_APOS|JSON_HEX_QUOT));
      ?>
      <div class="conv-item <?php echo $isActive ? 'active' : ''; ?>"
           onclick="loadConversation(<?php echo htmlspecialchars(json_encode($conv, JSON_HEX_APOS|JSON_HEX_QUOT)); ?>)"
           data-other-id="<?php echo $otherId; ?>"
           data-name="<?php echo htmlspecialchars(strtolower($otherName)); ?>">
        <div class="conv-avatar">
          <?php if ($photoUrl): ?><img src="<?php echo htmlspecialchars($photoUrl); ?>" alt=""><?php else: echo $initials; endif; ?>
        </div>
        <div class="conv-info">
          <div class="conv-top">
            <span class="conv-name"><?php echo htmlspecialchars($otherName); ?></span>
            <div style="display:flex;align-items:center;gap:.35rem;flex-shrink:0">
              <?php if ($conv['unread_count'] > 0): ?><span class="new-badge">New</span><?php endif; ?>
              <span class="conv-time"><?php echo $timeStr; ?></span>
            </div>
          </div>
          <div class="conv-subject"><?php echo htmlspecialchars($msg['subject'] ?? ''); ?></div>
          <div class="conv-preview"><?php echo htmlspecialchars(substr($msg['message'] ?? '', 0, 55)); ?><?php if (strlen($msg['message'] ?? '') > 55) echo '…'; ?></div>
          <?php if ($conv['archived']): ?><span class="archived-tag"><i class="fas fa-archive" style="margin-right:.3rem"></i>Archived</span><?php endif; ?>
        </div>
        <div class="conv-actions">
          <button class="ca-btn" onclick="event.stopPropagation();archiveConv(<?php echo $otherId; ?>, <?php echo $conv['archived'] ? 'false' : 'true'; ?>)"><?php echo $conv['archived'] ? 'Unarchive' : 'Archive'; ?></button>
          <button class="ca-btn del" onclick="event.stopPropagation();deleteConvForMe(<?php echo $otherId; ?>)">Delete</button>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if ($convRendered === 0): ?>
        <div class="conv-empty"><i class="fas fa-inbox"></i><p>No conversations yet.<br>Start one by clicking +</p></div>
      <?php endif; ?>
    </div>
 
    <div class="arch-toggle">
      <label><input type="checkbox" id="archToggle" <?php echo $showArchived ? 'checked' : ''; ?> onchange="toggleArchived(this.checked)"> Show archived</label>
    </div>
  </div>
 
  <!-- ── MAIN PANEL ── -->
  <div class="msg-main" id="msgMain">
    <!-- Compose header -->
    <div class="compose-head" id="composeHead">
      <div class="compose-head-title"><i class="fas fa-paper-plane"></i> New Message</div>
      <button class="compose-close" onclick="closeCompose()"><i class="fas fa-times"></i></button>
    </div>
 
    <!-- Chat header -->
    <div class="chat-head" id="chatHead">
      <div class="chat-peer">
        <div class="chat-avatar" id="chatAvatar"></div>
        <div>
          <div class="chat-name" id="chatName">—</div>
          <div class="chat-status">Alumni Network</div>
        </div>
      </div>
    </div>
 
    <!-- Compose body -->
    <div class="compose-body" id="composeBody">
      <div class="compose-form">
        <div class="cf-group">
          <label class="cf-label">To</label>
          <select class="cf-control" id="composeRecipient" required>
            <option value="">Select recipient…</option>
            <?php foreach ($alumni_list as $a): ?>
              <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['firstname'].' '.$a['lastname']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="cf-group">
          <label class="cf-label">Subject</label>
          <input class="cf-control" id="composeSubject" placeholder="What's it about?" required>
        </div>
        <div class="cf-group">
          <label class="cf-label">Message</label>
          <textarea class="cf-control" id="composeMsg" rows="8" placeholder="Write your message…" required></textarea>
        </div>
        <div class="cf-actions">
          <button class="btn-cancel" onclick="closeCompose()">Cancel</button>
          <button class="btn-send" id="composeSendBtn" onclick="sendComposedMessage()"><i class="fas fa-paper-plane" style="margin-right:.4rem"></i>Send</button>
        </div>
      </div>
    </div>
 
    <!-- Thread wrap -->
    <div class="chat-thread-wrap" id="threadWrap" style="display:none">
      <div class="chat-thread" id="chatThread"></div>
      <div id="typingHint" style="padding:0 1.5rem .5rem;font-size:.72rem;color:var(--silver);display:none"><i class="fas fa-circle" style="color:var(--fern);margin-right:.35rem"></i>Typing…</div>
      <div class="chat-input-wrap">
        <div class="chat-form">
          <textarea class="chat-textarea" id="replyInput" rows="1" placeholder="Type a message…"></textarea>
          <button class="send-btn" onclick="sendReply()"><i class="fas fa-paper-plane"></i></button>
        </div>
      </div>
    </div>
 
    <!-- Default empty -->
    <div class="msg-empty" id="msgEmpty">
      <div class="msg-empty-icon"><i class="fas fa-comments"></i></div>
      <div class="msg-empty-title">Your messages</div>
      <p class="msg-empty-sub">Select a conversation to read messages, or start a new one.</p>
      <button class="btn-compose" onclick="openCompose()"><i class="fas fa-plus"></i> New Message</button>
    </div>
  </div>
</div>
 
<!-- FAB -->
<button id="fab" onclick="openCompose()" title="New message"><i class="fas fa-pen"></i></button>
 
<!-- Lightbox -->
<div id="lightbox"><button id="lb-close" onclick="closeLightbox()"><i class="fas fa-times"></i></button><img id="lb-img" src="" alt=""></div>
 
<script>
const ME = <?php echo $user_id; ?>;
const MY_PHOTO = <?php echo json_encode(($user['photo'] ?? '') && file_exists(__DIR__.'/uploads/'.($user['photo']??'')) ? 'serve_profile_image.php?img='.urlencode($user['photo']) : ''); ?>;
const MY_INITIALS = <?php echo json_encode(strtoupper(substr($user['firstname']??'M',0,1).substr($user['lastname']??'',0,1))); ?>;
 
let currentOtherId = null;
let pollInterval = null;
let currentSubject = '';
 
// ── Init ──
(function init() {
  // Preselect from ?to= param
  const urlTo = <?php echo $urlTo ?: 0; ?>;
  if (urlTo) {
    const el = document.querySelector('.conv-item[data-other-id="' + urlTo + '"]');
    if (el) { el.click(); return; }
    // Not found in list – open compose with preselected
    openCompose();
    const sel = document.getElementById('composeRecipient');
    if (sel) { sel.value = urlTo; }
  }
})();
 
// ── Compose ──
function openCompose() {
  document.getElementById('chatHead').classList.remove('visible');
  document.getElementById('composeHead').classList.add('visible');
  document.getElementById('composeBody').classList.add('visible');
  document.getElementById('threadWrap').style.display = 'none';
  document.getElementById('msgEmpty').style.display = 'none';
  document.querySelectorAll('.conv-item').forEach(i => i.classList.remove('active'));
  currentOtherId = null; stopPoll();
}
function closeCompose() {
  document.getElementById('composeHead').classList.remove('visible');
  document.getElementById('composeBody').classList.remove('visible');
  if (!currentOtherId) { document.getElementById('msgEmpty').style.display = 'flex'; }
  else { document.getElementById('threadWrap').style.display = 'flex'; }
}
async function sendComposedMessage() {
  const rcpt = document.getElementById('composeRecipient').value;
  const subj = document.getElementById('composeSubject').value.trim();
  const body = document.getElementById('composeMsg').value.trim();
  if (!rcpt || !subj || !body) { alert('Please fill in all fields.'); return; }
  const btn = document.getElementById('composeSendBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  try {
    const r = await fetch('send_message.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ receiver_id: rcpt, subject: subj, message: body }) });
    const d = await r.json();
    if (d.success) {
      document.getElementById('composeSubject').value = '';
      document.getElementById('composeMsg').value = '';
      closeCompose();
      window.location.reload();
    } else { alert(d.message || 'Send failed.'); }
  } catch(e) { alert('Network error. Please try again.'); }
  btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane" style="margin-right:.4rem"></i>Send';
}
 
// ── Load conversation ──
function loadConversation(conv) {
  const msg = conv.latest_message;
  const otherId = msg.sender_id == ME ? msg.receiver_id : msg.sender_id;
  const otherFn = msg.sender_id == ME ? msg.receiver_firstname : msg.sender_firstname;
  const otherLn = msg.sender_id == ME ? msg.receiver_lastname : msg.sender_lastname;
  const otherName = (otherFn + ' ' + otherLn).trim();
  const otherPhoto = msg.sender_id == ME ? msg.receiver_photo : msg.sender_photo;
 
  currentOtherId = otherId;
  currentSubject = (msg.subject || '').replace(/^Re:\s*/i,'').trim();
 
  // Update header
  document.getElementById('chatHead').classList.add('visible');
  document.getElementById('composeHead').classList.remove('visible');
  document.getElementById('composeBody').classList.remove('visible');
  document.getElementById('threadWrap').style.display = 'flex';
  document.getElementById('msgEmpty').style.display = 'none';
  document.getElementById('chatName').textContent = otherName;
 
  const ava = document.getElementById('chatAvatar');
  if (otherPhoto) {
    const src = otherPhoto.startsWith('http') ? otherPhoto : 'serve_profile_image.php?img=' + encodeURIComponent(otherPhoto);
    ava.innerHTML = '<img src="' + src + '" alt="">';
  } else {
    ava.innerHTML = (otherFn[0]||'').toUpperCase() + (otherLn[0]||'').toUpperCase();
  }
 
  // Mark active
  document.querySelectorAll('.conv-item').forEach(i => i.classList.remove('active'));
  const item = document.querySelector('.conv-item[data-other-id="' + otherId + '"]');
  if (item) item.classList.add('active');
 
  fetchThread(otherId);
  stopPoll(); startPoll(otherId);
 
  if (conv.unread_count > 0) {
    fetch('mark_conversation_read.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ sender_id: otherId, receiver_id: ME }) }).catch(()=>{});
  }
}
 
// ── Fetch thread ──
async function fetchThread(otherId) {
  try {
    const r = await fetch('get_conversation.php?other_id=' + encodeURIComponent(otherId));
    const d = await r.json();
    if (!d.success) return;
    const thread = document.getElementById('chatThread');
    thread.innerHTML = '';
    let lastDate = '';
 
    if (d.messages && d.messages.length > 0) {
      const wSubj = d.messages.find(m => m.subject && m.subject.trim()) || d.messages[0];
      currentSubject = (wSubj.subject || '').replace(/^Re:\s*/i,'').trim();
    }
 
    (d.messages || []).forEach(msg => {
      const dateStr = new Date(msg.created_at.replace(' ','T')).toDateString();
      if (dateStr !== lastDate) {
        lastDate = dateStr;
        const div = document.createElement('div');
        div.className = 'date-divider';
        div.textContent = dateStr;
        thread.appendChild(div);
      }
      const isMine = msg.sender_id == ME;
      const row = document.createElement('div');
      row.className = 'msg-row' + (isMine ? ' mine' : '');
 
      // Avatar
      const avaEl = document.createElement('div');
      avaEl.className = 'msg-tiny-avatar';
      if (isMine) {
        if (MY_PHOTO) { avaEl.innerHTML = '<img src="' + MY_PHOTO + '" alt="">'; }
        else { avaEl.textContent = MY_INITIALS; avaEl.style.background = 'linear-gradient(135deg,#2d7a4f,#1b5e35)'; }
      } else {
        const photo = msg.sender_photo;
        if (photo) {
          const src = photo.startsWith('http') ? photo : 'serve_profile_image.php?img=' + encodeURIComponent(photo);
          avaEl.innerHTML = '<img src="' + src + '" alt="">';
        } else { avaEl.textContent = ((msg.sender_firstname||'')[0]||'').toUpperCase() + ((msg.sender_lastname||'')[0]||'').toUpperCase(); }
      }
 
      const bubble = document.createElement('div');
      bubble.className = 'msg-bubble ' + (isMine ? 'mine' : 'theirs');
      bubble.innerHTML = linkify(escHtml(msg.message || ''));
 
      const timeEl = document.createElement('div');
      timeEl.className = 'msg-time' + (isMine ? '' : ' theirs');
      timeEl.textContent = new Date(msg.created_at.replace(' ','T')).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true});
 
      const bWrap = document.createElement('div');
      bWrap.appendChild(bubble);
      bWrap.appendChild(timeEl);
      row.appendChild(avaEl);
      row.appendChild(bWrap);
      thread.appendChild(row);
    });
    thread.scrollTo({ top: thread.scrollHeight, behavior: 'smooth' });
  } catch(e) { console.error(e); }
}
 
// ── Send reply ──
async function sendReply() {
  if (!currentOtherId) return;
  const ta = document.getElementById('replyInput');
  const body = ta.value.trim();
  if (!body) return;
  const subject = currentSubject ? 'Re: ' + currentSubject : 'Message';
  ta.value = ''; ta.style.height = 'auto';
  try {
    const r = await fetch('send_message.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ receiver_id: currentOtherId, subject, message: body }) });
    const d = await r.json();
    if (d.success) fetchThread(currentOtherId);
    else alert(d.message || 'Send failed.');
  } catch(e) { alert('Network error.'); }
}
 
// ── Poll ──
function startPoll(id) { pollInterval = setInterval(() => fetchThread(id), 3000); }
function stopPoll() { if (pollInterval) { clearInterval(pollInterval); pollInterval = null; } }
 
// ── Textarea auto-resize ──
document.getElementById('replyInput').addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
document.getElementById('replyInput').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendReply(); }
});
 
// ── Search ──
function filterConversations(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.conv-item').forEach(item => {
    const name = item.dataset.name || '';
    item.style.display = (!q || name.includes(q)) ? '' : 'none';
  });
}
 
// ── Archive / delete ──
function archiveConv(otherId, shouldArchive) {
  fetch('set_conversation_flag.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ other_id: otherId, action: shouldArchive ? 'archive' : 'unarchive' }) })
    .then(() => window.location.reload());
}
function deleteConvForMe(otherId) {
  if (!confirm('Remove this conversation from your inbox? This does not delete it for the other person.')) return;
  fetch('set_conversation_flag.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ other_id: otherId, action: 'delete_me' }) })
    .then(() => window.location.reload());
}
 
// ── Show archived toggle ──
function toggleArchived(v) {
  const url = new URL(window.location.href);
  url.searchParams.set('show_archived', v ? '1' : '0');
  window.location.href = url.toString();
}
 
// ── Helpers ──
function escHtml(s) {
  return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
}
function linkify(text) {
  return text.replace(/(https?:\/\/[^\s]+|www\.[^\s]+)/gi, m => {
    const href = /^https?:\/\//i.test(m) ? m : 'http://' + m;
    return '<a href="' + href + '" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;opacity:.85">' + m + '</a>';
  });
}
 
// ── Lightbox ──
function closeLightbox() { document.getElementById('lightbox').classList.remove('open'); }
document.addEventListener('click', e => {
  if (e.target.tagName === 'IMG' && e.target.closest('.msg-bubble')) {
    document.getElementById('lb-img').src = e.target.src;
    document.getElementById('lightbox').classList.add('open');
  }
});
document.getElementById('lightbox').addEventListener('click', e => { if (e.target === document.getElementById('lightbox')) closeLightbox(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
 
// ── Visibility poll pause ──
document.addEventListener('visibilitychange', () => {
  if (document.hidden) stopPoll();
  else if (currentOtherId) startPoll(currentOtherId);
});
</script>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) include 'al_footer_universal.php'; ?>
</body>
</html>