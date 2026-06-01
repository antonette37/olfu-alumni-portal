<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db_config.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: al_login.php'); exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$unread_inquiries = 0;
$recent_messages = [];

/*
 * Skip inquiry DB work when $hide_admin_inquiries is truthy (ad_alumni_id_check, ad_announcements).
 * Do not use empty() alone — it treats 0 / "0" oddly; explicit check is reliable on all PHP versions.
 */
$skip_admin_inquiry_db = isset($hide_admin_inquiries) && $hide_admin_inquiries;
if (!$skip_admin_inquiry_db) {
    $conn = getDBConnection();
    $uq = $conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE (COALESCE(is_read,0)=0 OR LOWER(TRIM(COALESCE(status,'')))='new')");
    if ($uq) {
        $r = $uq->fetch_assoc();
        $unread_inquiries = (int) ($r['c'] ?? 0);
    }
    if ($unread_inquiries == 0) {
        $uq2 = $conn->query("SELECT COUNT(*) AS c FROM contactmessages WHERE LOWER(TRIM(COALESCE(status,''))) = 'new'");
        if ($uq2) {
            $r2 = $uq2->fetch_assoc();
            $unread_inquiries = (int) ($r2['c'] ?? 0);
        }
    }

    $rq = $conn->query("SELECT id, name, email, message, status, submitted_at, CASE WHEN (COALESCE(is_read,0)=0 OR LOWER(TRIM(COALESCE(status,'')))='new') THEN 1 ELSE 0 END AS is_unread FROM contact_messages ORDER BY submitted_at DESC LIMIT 8");
    if ($rq) {
        while ($row = $rq->fetch_assoc()) {
            $recent_messages[] = $row;
        }
    }
    if (empty($recent_messages)) {
        $rq2 = $conn->query("SELECT id, sender_name as name, sender_email as email, message, status, sent_at as submitted_at, CASE WHEN LOWER(TRIM(COALESCE(status,'')))='new' THEN 1 ELSE 0 END AS is_unread FROM contactmessages ORDER BY sent_at DESC LIMIT 8");
        if ($rq2) {
            while ($row = $rq2->fetch_assoc()) {
                $recent_messages[] = $row;
            }
        }
    }
}
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

<style>
/* ══ ADMIN DESIGN TOKENS ══ */
:root {
  --cr:       #8b0000;   /* crimson */
  --cr-dk:    #600000;   /* dark crimson */
  --cr-lt:    #b91c1c;   /* lighter crimson */
  --cr-pale:  #fef2f2;   /* pale crimson bg */
  --gold:     #c9a84c;   /* warm gold */
  --gold-lt:  #f0d98a;   /* light gold */
  --gold-pale:#fdf5e0;   /* pale gold */
  --ink:      #1a0a0a;   /* near-black with warm tint */
  --slate:    #4a4040;
  --muted:    #7a6a6a;
  --border:   #e8dada;
  --surface:  #ffffff;
  --bg:       #faf7f7;   /* warm off-white */
  --shadow-sm:0 1px 4px rgba(139,0,0,.07),0 2px 12px rgba(139,0,0,.05);
  --shadow-md:0 4px 16px rgba(139,0,0,.1),0 8px 32px rgba(139,0,0,.08);
  --shadow-lg:0 12px 40px rgba(139,0,0,.15),0 4px 16px rgba(0,0,0,.06);
  --r:        12px;
  --r-lg:     18px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
  font-family: 'Sora', -apple-system, sans-serif;
  background: var(--bg);
  color: var(--ink);
  -webkit-font-smoothing: antialiased;
}
a { text-decoration: none; color: inherit; }

/* ══ HEADER ══ */
#adminHeader {
  position: fixed;
  top: 0; left: 0; right: 0;
  height: 64px;
  background: rgba(255,255,255,0.96);
  backdrop-filter: blur(20px) saturate(180%);
  border-bottom: 1px solid var(--border);
  z-index: 100;
  display: flex;
  align-items: center;
  transition: box-shadow 0.3s;
}
#adminHeader.scrolled {
  box-shadow: var(--shadow-sm);
}
/* Gold top bar */
#adminHeader::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--cr) 0%, var(--gold) 40%, var(--cr-lt) 70%, var(--gold) 100%);
  background-size: 300% 100%;
  animation: headerShimmer 5s linear infinite;
}
@keyframes headerShimmer {
  0%   { background-position: 0% 50%; }
  100% { background-position: 300% 50%; }
}
.header-inner {
  max-width: 100%;
  width: 100%;
  padding: 0 24px;
  display: flex;
  align-items: center;
  gap: 16px;
}

/* Logo */
.header-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}
.header-logo img { height: 36px; width: auto; }
.header-logo-text { line-height: 1.2; }
.header-logo-title {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--cr-dk);
  letter-spacing: -0.01em;
}
.header-logo-sub {
  font-family: 'DM Mono', monospace;
  font-size: 0.58rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--muted);
}

/* Search */
.header-search {
  flex: 1;
  max-width: 440px;
  position: relative;
}
.header-search i {
  position: absolute;
  left: 14px; top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 13px;
  pointer-events: none;
}
.header-search input {
  width: 100%;
  padding: 9px 36px 9px 38px;
  border: 1.5px solid var(--border);
  border-radius: 100px;
  background: var(--bg);
  font-family: 'Sora', sans-serif;
  font-size: 0.82rem;
  color: var(--ink);
  outline: none;
  transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
}
.header-search input:focus {
  border-color: var(--cr);
  background: var(--surface);
  box-shadow: 0 0 0 3px rgba(139,0,0,0.08);
}
.header-search input::placeholder { color: var(--muted); opacity: 0.7; }
.header-search .clear-btn {
  position: absolute;
  right: 12px; top: 50%;
  transform: translateY(-50%);
  background: none; border: none;
  color: var(--muted); font-size: 12px;
  cursor: pointer; padding: 2px;
  transition: color 0.2s;
}
.header-search .clear-btn:hover { color: var(--cr); }
#adminGlobalSearchResults {
  position: absolute;
  top: calc(100% + 8px);
  left: 0; right: 0;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r);
  box-shadow: var(--shadow-lg);
  z-index: 300;
  overflow: hidden;
  max-height: 360px;
  overflow-y: auto;
}
.sr-section-label {
  padding: 8px 14px 4px;
  font-family: 'DM Mono', monospace;
  font-size: 0.65rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--muted);
  background: var(--bg);
  border-bottom: 1px solid var(--border);
}
.sr-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  font-size: 0.82rem;
  color: var(--ink);
  transition: background 0.15s;
  cursor: pointer;
  border-bottom: 1px solid rgba(232,218,218,0.5);
}
.sr-item:hover { background: var(--cr-pale); }
.sr-item:last-child { border-bottom: none; }
.sr-icon {
  width: 28px; height: 28px;
  border-radius: 8px;
  background: var(--cr-pale);
  color: var(--cr);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; flex-shrink: 0;
}

/* Right actions */
.header-actions {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-left: auto;
  flex-shrink: 0;
}
.h-icon-btn {
  position: relative;
  width: 40px; height: 40px;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: var(--slate);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: background 0.2s, color 0.2s;
  font-size: 16px;
}
.h-icon-btn:hover { background: var(--cr-pale); color: var(--cr); }
.h-badge {
  position: absolute;
  top: 4px; right: 4px;
  min-width: 16px; height: 16px;
  padding: 0 4px;
  background: var(--cr);
  color: white;
  font-size: 9px;
  font-weight: 700;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid var(--surface);
}

/* Dropdowns */
.h-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  min-width: 320px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  box-shadow: var(--shadow-lg);
  z-index: 300;
  overflow: hidden;
  display: none;
}
.h-dropdown.open { display: block; animation: dropIn 0.18s ease; }
@keyframes dropIn {
  from { opacity: 0; transform: translateY(-8px) scale(0.97); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}
.h-dropdown-header {
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.h-dropdown-title {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--cr-dk);
}
.h-dropdown-action {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--cr);
  background: none;
  border: none;
  cursor: pointer;
  transition: color 0.2s;
}
.h-dropdown-action:hover { color: var(--cr-dk); }
.h-dropdown-body { max-height: 300px; overflow-y: auto; }
.h-dropdown-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 18px;
  border-bottom: 1px solid rgba(232,218,218,0.5);
  transition: background 0.15s;
  cursor: pointer;
}
.h-dropdown-item:hover { background: var(--cr-pale); }
.h-dropdown-item:last-child { border-bottom: none; }
.h-dropdown-item.unread { background: rgba(139,0,0,0.03); }
.h-notif-icon {
  width: 34px; height: 34px;
  border-radius: 9px;
  background: var(--cr-pale);
  color: var(--cr);
  display: flex; align-items: center; justify-content: center;
  font-size: 13px;
  flex-shrink: 0;
}
.h-notif-thumb {
  width: 34px; height: 34px;
  border-radius: 9px;
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid var(--border);
  background: var(--bg);
  display: block;
}
.h-notif-name { font-size: 0.8rem; font-weight: 600; color: var(--ink); margin-bottom: 2px; }
.h-notif-msg  { font-size: 0.75rem; color: var(--muted); line-height: 1.45; }
.h-notif-time { font-size: 0.68rem; color: rgba(122,106,106,0.7); margin-top: 3px; font-family: 'DM Mono', monospace; }
.h-dropdown-footer {
  padding: 10px 18px;
  border-top: 1px solid var(--border);
  text-align: center;
}
.h-dropdown-footer a { font-size: 0.78rem; font-weight: 600; color: var(--cr); transition: color 0.2s; }
.h-dropdown-footer a:hover { color: var(--cr-dk); }
.h-empty { padding: 24px; text-align: center; color: var(--muted); font-size: 0.82rem; }
.h-empty i { display: block; font-size: 1.5rem; margin-bottom: 8px; opacity: 0.4; }

/* Relative wrapper for dropdowns */
.h-dropdown-wrap { position: relative; }
</style>

<header id="adminHeader">
  <div class="header-inner">

    <!-- Logo -->
    <a href="ad_dashboard.php" class="header-logo">
      <img src="olfulogo.png" alt="OLFU" />
      <img src="ccs_logo.png" alt="CCS" class="hidden sm:block" />
      <div class="header-logo-text hidden sm:block">
        <div class="header-logo-title">Admin Portal</div>
        <div class="header-logo-sub">Our Lady of Fatima University</div>
      </div>
    </a>

    <!-- Search -->
    <div class="header-search hidden md:block">
      <i class="fas fa-search"></i>
      <input id="adminGlobalSearch" type="text" placeholder="Search alumni, events, inquiries…" autocomplete="off" />
      <button id="adminGlobalSearchClear" class="clear-btn hidden"><i class="fas fa-times"></i></button>
      <div id="adminGlobalSearchResults" class="hidden"></div>
    </div>

    <!-- Actions -->
    <div class="header-actions">

      <!-- Inbox -->
      <?php if (empty($hide_admin_inquiries)): ?>
      <div class="h-dropdown-wrap">
        <button class="h-icon-btn" id="inboxBtn" title="Inquiries">
          <i class="fas fa-inbox"></i>
          <span class="h-badge<?= $unread_inquiries > 0 ? '' : ' hidden' ?>" id="inboxBadge"><?= $unread_inquiries > 99 ? '99+' : $unread_inquiries ?></span>
        </button>
        <div class="h-dropdown" id="inboxDropdown">
          <div class="h-dropdown-header">
            <span class="h-dropdown-title">Recent Inquiries</span>
            <a href="ad_contactmessages.php" class="h-dropdown-action">View all</a>
          </div>
          <div class="h-dropdown-body" id="inboxList">
            <?php if (empty($recent_messages)): ?>
              <div class="h-empty"><i class="fas fa-inbox"></i>No recent inquiries</div>
            <?php else: foreach ($recent_messages as $m): ?>
              <a href="ad_contactmessages.php" class="h-dropdown-item <?= !empty($m['is_unread']) ? 'unread' : '' ?>">
                <div class="h-notif-icon"><i class="fas fa-envelope"></i></div>
                <div style="flex:1;min-width:0;">
                  <div class="h-notif-name"><?= htmlspecialchars((string) ($m['name'] ?? '')) ?></div>
                  <?php $msgPrev = (string) ($m['message'] ?? ''); ?>
                  <div class="h-notif-msg"><?= htmlspecialchars(strlen($msgPrev) > 70 ? substr($msgPrev, 0, 70) : $msgPrev) ?><?= strlen($msgPrev) > 70 ? '…' : '' ?></div>
                  <div class="h-notif-time"><?= date('M d · g:i A', strtotime((string) ($m['submitted_at'] ?? 'now'))) ?></div>
                </div>
                <?php if (!empty($m['is_unread'])): ?>
                  <div style="width:7px;height:7px;border-radius:50%;background:var(--cr);flex-shrink:0;margin-top:4px;"></div>
                <?php endif; ?>
              </a>
            <?php endforeach; endif; ?>
          </div>
          <div class="h-dropdown-footer"><a href="ad_contactmessages.php">All inquiries <i class="fas fa-arrow-right" style="font-size:10px;"></i></a></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Content requests -->
      <div class="h-dropdown-wrap">
        <button class="h-icon-btn" id="contentRequestBtn" title="Content Requests">
          <i class="fas fa-briefcase"></i>
          <span class="h-badge hidden" id="contentRequestBadge">0</span>
        </button>
        <div class="h-dropdown" id="contentRequestDropdown">
          <div class="h-dropdown-header">
            <span class="h-dropdown-title">Content Requests</span>
            <a href="ad_content_management.php" class="h-dropdown-action">View all</a>
          </div>
          <div class="h-dropdown-body" id="contentRequestList">
            <div class="h-empty"><i class="fas fa-check-circle"></i>No pending requests</div>
          </div>
        </div>
      </div>

      <!-- User requests -->
      <div class="h-dropdown-wrap">
        <button class="h-icon-btn" id="userRequestBtn" title="New User Registrations">
          <i class="fas fa-user-plus"></i>
          <span class="h-badge hidden" id="userRequestBadge">0</span>
        </button>
        <div class="h-dropdown" id="userRequestDropdown">
          <div class="h-dropdown-header">
            <span class="h-dropdown-title">New Registrations</span>
            <a href="ad_user_management.php?status=pending" class="h-dropdown-action">View all</a>
          </div>
          <div class="h-dropdown-body" id="userRequestList">
            <div class="h-empty"><i class="fas fa-users"></i>No pending registrations</div>
          </div>
        </div>
      </div>

    </div>
  </div>
</header>

<script>
(function(){
  function hEsc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }
  function badgeText(n) {
    const count = Number.parseInt(n, 10);
    if (!Number.isFinite(count) || count <= 0) return '0';
    return count > 99 ? '99+' : String(count);
  }

  // Scroll state
  window.addEventListener('scroll', () => {
    document.getElementById('adminHeader').classList.toggle('scrolled', window.scrollY > 10);
  }, { passive: true });

  // ── Dropdown toggle helper ──
  const dropdowns = [
    { btn: 'inboxBtn',         dd: 'inboxDropdown' },
    { btn: 'contentRequestBtn', dd: 'contentRequestDropdown' },
    { btn: 'userRequestBtn',   dd: 'userRequestDropdown' },
  ];
  dropdowns.forEach(({ btn, dd }) => {
    const b = document.getElementById(btn);
    const d = document.getElementById(dd);
    if (!b || !d) return;
    b.addEventListener('click', (e) => {
      e.stopPropagation();
      const open = d.classList.contains('open');
      // Close all
      dropdowns.forEach(({ dd: id }) => document.getElementById(id)?.classList.remove('open'));
      if (!open) d.classList.add('open');
    });
  });
  document.addEventListener('click', () => {
    dropdowns.forEach(({ dd }) => document.getElementById(dd)?.classList.remove('open'));
  });

  // ── Search ──
  const si = document.getElementById('adminGlobalSearch');
  const sr = document.getElementById('adminGlobalSearchResults');
  const sc = document.getElementById('adminGlobalSearchClear');
  let sAC = null, sDeb = null;

  function renderSection(title, items, icon) {
    if (!items?.length) return '';
    let h = `<div class="sr-section-label">${title}</div>`;
    items.forEach(item => {
      h += `<a href="${item.url || '#'}" class="sr-item">
        <div class="sr-icon"><i class="fas fa-${icon}"></i></div>
        <div><div style="font-weight:600;">${item.title}</div>${item.subtitle ? `<div style="font-size:.72rem;color:var(--muted);">${item.subtitle}</div>` : ''}</div>
      </a>`;
    });
    return h;
  }

  function clearSearch() {
    if (sr) { sr.classList.add('hidden'); sr.innerHTML = ''; }
  }

  if (si && sr) {
    si.addEventListener('input', () => {
      const q = si.value.trim();
      if (sc) sc.classList.toggle('hidden', !q.length);
      if (sDeb) clearTimeout(sDeb);
      if (q.length < 2) { clearSearch(); return; }
      sDeb = setTimeout(() => {
        if (sAC) sAC.abort();
        sAC = new AbortController();
        fetch(`search_admin_universal.php?q=${encodeURIComponent(q)}`, { signal: sAC.signal })
          .then(r => r.ok ? r.json() : Promise.reject())
          .then(data => {
            if (!data) return;
            const html = [
              renderSection('Alumni', data.alumni, 'user'),
              renderSection('Inquiries', data.inquiries, 'inbox'),
              renderSection('Events', data.events, 'calendar'),
              renderSection('System', data.system, 'cog'),
            ].filter(Boolean).join('');
            sr.innerHTML = html || '<div class="h-empty">No results found</div>';
            sr.classList.remove('hidden');
          }).catch(() => {});
      }, 220);
    });
    si.addEventListener('focus', () => { if (sr.innerHTML) sr.classList.remove('hidden'); });
    document.addEventListener('click', e => {
      if (!sr.contains(e.target) && !si.contains(e.target)) clearSearch();
    });
    if (sc) sc.addEventListener('click', () => { si.value = ''; sc.classList.add('hidden'); clearSearch(); si.focus(); });
  }

  // ── Inbox loader ──
  function loadInboxRequests() {
    fetch('get_admin_notifications.php')
      .then(r => r.ok ? r.text() : Promise.reject())
      .then(text => { try { return JSON.parse(text.trim()); } catch (e) { return {}; } })
      .then(data => {
        const count = Number.parseInt(data?.unread_count ?? 0, 10) || 0;
        const items = Array.isArray(data?.notifications) ? data.notifications : [];
        const badge = document.getElementById('inboxBadge');
        if (badge) {
          badge.textContent = badgeText(count);
          badge.classList.toggle('hidden', count <= 0);
        }
        const list = document.getElementById('inboxList');
        if (!list) return;
        if (!items.length) {
          list.innerHTML = '<div class="h-empty"><i class="fas fa-inbox"></i>No recent inquiries</div>';
          return;
        }
        list.innerHTML = items.map(n => `
          <a href="${hEsc(n.link || 'ad_contactmessages.php')}" class="h-dropdown-item ${n.read ? '' : 'unread'}">
            <div class="h-notif-icon"><i class="fas fa-envelope"></i></div>
            <div style="flex:1;min-width:0;">
              <div class="h-notif-name">${hEsc(n.message || 'From alumni')}</div>
              <div class="h-notif-msg">${hEsc(n.title || '')}</div>
              <div class="h-notif-time">${hEsc(n.time || '')}</div>
            </div>
            ${n.read ? '' : '<div style="width:7px;height:7px;border-radius:50%;background:var(--cr);flex-shrink:0;margin-top:4px;"></div>'}
          </a>
        `).join('');
      })
      .catch(() => {});
  }

  // ── User requests loader ──
  function loadUserRequests() {
    fetch('get_user_requests.php')
      .then(r => r.ok ? r.text() : Promise.reject())
      .then(text => { try { return JSON.parse(text.trim()); } catch(e) { return {}; } })
      .then(data => {
        const count = Number.parseInt(data?.pending_count ?? 0, 10) || 0;
        const reqs  = data?.requests ?? [];
        const badge = document.getElementById('userRequestBadge');
        if (badge) { badge.textContent = badgeText(count); badge.classList.toggle('hidden', count === 0); }
        const list  = document.getElementById('userRequestList');
        if (!list) return;
        if (!reqs.length) { list.innerHTML = '<div class="h-empty"><i class="fas fa-users"></i>No pending registrations</div>'; return; }
        list.innerHTML = reqs.map(r => `
          <a href="ad_user_management.php?status=pending" class="h-dropdown-item">
            <div class="h-notif-icon"><i class="fas fa-user-plus"></i></div>
            <div><div class="h-notif-name">${r.name}</div><div class="h-notif-msg">${r.email}</div><div class="h-notif-time">${r.time}</div></div>
          </a>`).join('');
      }).catch(() => {});
  }

  // ── Content requests loader ──
  function loadContentRequests() {
    fetch('get_content_requests.php')
      .then(r => r.ok ? r.text() : Promise.reject())
      .then(text => { try { return JSON.parse(text.trim()); } catch(e) { return {}; } })
      .then(data => {
        const count = Number.parseInt(data?.total_pending ?? 0, 10) || 0;
        const badge = document.getElementById('contentRequestBadge');
        if (badge) { badge.textContent = badgeText(count); badge.classList.toggle('hidden', count === 0); }
        const list  = document.getElementById('contentRequestList');
        if (!list) return;
        const all = [...(data?.job_requests ?? []), ...(data?.story_requests ?? [])];
        if (!all.length) { list.innerHTML = '<div class="h-empty"><i class="fas fa-check-circle"></i>No pending requests</div>'; return; }
        list.innerHTML = all.map(r => {
          const icon = r.kind === 'story' ? 'star' : 'briefcase';
          const pu = (r.photo_url && String(r.photo_url).trim()) ? String(r.photo_url).trim() : '';
          const left = pu
            ? `<img class="h-notif-thumb" src="${hEsc(pu)}" alt="" width="34" height="34" loading="lazy" onerror="this.outerHTML='<div class=\\'h-notif-icon\\'><i class=\\'fas fa-${icon}\\'></i></div>'">`
            : `<div class="h-notif-icon"><i class="fas fa-${icon}"></i></div>`;
          return `
          <a href="ad_content_management.php" class="h-dropdown-item">
            ${left}
            <div><div class="h-notif-name">${r.title}</div><div class="h-notif-msg">by ${r.author}</div><div class="h-notif-time">${r.time}</div></div>
          </a>`;
        }).join('');
      }).catch(() => {});
  }

  loadInboxRequests();
  loadUserRequests();
  loadContentRequests();
  setInterval(loadInboxRequests, 30000);
  setInterval(loadUserRequests, 30000);
  setInterval(loadContentRequests, 30000);

})();
</script>