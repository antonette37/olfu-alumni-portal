<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!isset($show_global_header_search)) {
  /* Hide header search on alumni homepage for guests (shown when logged in and on all other pages). */
  $show_global_header_search = !empty($user_id) || (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'al_homepage.php');
}
if (!isset($user) || !is_array($user)) $user = [];
$notifications = [];
$notification_count = 0;
if ($user_id && empty($user)) {
  $stmt = $conn->prepare("SELECT id, firstname, lastname, photo FROM itcp WHERE id = ?");
  if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc() ?: []; $stmt->close(); }
  $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
  if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
  $notification_count = count(array_filter($notifications, function($n){ return empty($n['is_read']); }));
}

if (!function_exists('getAlumniNotificationUrl')) {
  function getAlumniNotificationUrl(array $notification): string {
    $link = trim($notification['link'] ?? '');
    if ($link !== '') return $link;
    $type = $notification['type'] ?? '';
    $referenceId = isset($notification['reference_id']) ? (int)$notification['reference_id'] : 0;
    switch ($type) {
      case 'job_post':      return 'al_career.php?section=pending' . ($referenceId > 0 ? '&highlight_pending='.$referenceId : '');
      case 'job_approved':  return 'al_career.php?tab=jobs' . ($referenceId > 0 ? '&highlight_job='.$referenceId : '');
      case 'message':       return 'al_messages.php';
      case 'verification':
      case 'update':        return 'al_profileupdate.php';
      case 'rejection':     return 'al_profileupdate.php?status=rejected';
      default:              return 'al_dashboard.php';
    }
  }
}
?>
<style>
/* ===== DESIGN TOKENS (shared with homepage) ===== */
:root {
  --forest:    #0a4a1e;
  --emerald:   #1a7a3a;
  --leaf:      #2ea855;
  --mint:      #d4f0dc;
  --cream:     #faf8f3;
  --ink:       #111916;
  --muted:     #5a6b61;
  --gold:      #c9a84c;
  --gold-lt:   #f0d98a;
}

/* ===== HEADER BASE ===== */
#universalHeader {
  position: sticky;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  height: 68px;
  display: flex;
  align-items: center;
  font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
  /* Glass morphism base */
  background: rgba(255,255,255,0.72);
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
  border-bottom: 1px solid rgba(10,74,30,0.1);
  transition: background 0.35s ease, border-color 0.35s ease, box-shadow 0.35s ease;
}

/* Scrolled state — more opaque */
#universalHeader.scrolled {
  background: rgba(255,255,255,0.95);
  box-shadow: 0 4px 24px rgba(10,74,30,0.1);
  border-bottom-color: rgba(10,74,30,0.12);
}

/* Dark-bg state (over dark hero) */
#universalHeader.dark-bg {
  background: rgba(10,74,30,0.55);
  border-bottom-color: rgba(255,255,255,0.1);
}
#universalHeader.dark-bg.scrolled {
  background: rgba(10,30,15,0.92);
  border-bottom-color: transparent;
  box-shadow: 0 4px 24px rgba(0,0,0,0.25);
}

/* Gold top accent line */
#universalHeader::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--forest), var(--gold), var(--leaf), var(--gold), var(--forest));
  background-size: 300% 100%;
  animation: headerAccent 6s linear infinite;
}
@keyframes headerAccent {
  0%   { background-position: 0% 50%; }
  100% { background-position: 300% 50%; }
}

/* ===== ADAPTIVE TEXT ===== */
.h-text {
  color: var(--ink);
  transition: color 0.3s ease;
}
.h-text-muted {
  color: var(--muted);
  transition: color 0.3s ease;
}
#universalHeader.dark-bg .h-text       { color: rgba(255,255,255,0.92); }
#universalHeader.dark-bg .h-text-muted { color: rgba(255,255,255,0.6); }

/* ===== LOGO WORDMARK ===== */
.header-wordmark-title {
  font-family: 'DM Sans', sans-serif;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.01em;
  line-height: 1.2;
  color: var(--forest);
  transition: color 0.3s;
}
.header-wordmark-sub {
  font-family: 'DM Mono', monospace;
  font-size: 0.62rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--muted);
  transition: color 0.3s;
}
#universalHeader.dark-bg .header-wordmark-title { color: rgba(255,255,255,0.95); }
#universalHeader.dark-bg .header-wordmark-sub   { color: rgba(255,255,255,0.55); }

/* ===== NAV LINKS ===== */
.h-nav-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 500;
  color: var(--ink);
  transition: background 0.2s, color 0.2s;
  cursor: pointer;
  border: none;
  background: none;
  font-family: 'DM Sans', sans-serif;
}
.h-nav-link:hover {
  background: rgba(10,74,30,0.07);
  color: var(--emerald);
}
.h-nav-link .h-chevron {
  font-size: 9px;
  opacity: 0.55;
  transition: transform 0.2s;
}
.h-nav-link:hover .h-chevron { transform: translateY(2px); }

#universalHeader.dark-bg .h-nav-link {
  color: rgba(255,255,255,0.85);
}
#universalHeader.dark-bg .h-nav-link:hover {
  background: rgba(255,255,255,0.1);
  color: white;
}

/* ===== DROPDOWN ===== */
.h-dropdown-wrap {
  position: relative;
}
.h-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  min-width: 200px;
  background: white;
  border: 1px solid rgba(10,74,30,0.1);
  border-radius: 14px;
  padding: 6px;
  box-shadow: 0 16px 48px rgba(10,74,30,0.14), 0 2px 8px rgba(0,0,0,0.06);
  opacity: 0;
  transform: translateY(-6px) scale(0.97);
  pointer-events: none;
  transition: opacity 0.2s ease, transform 0.2s ease;
  z-index: 200;
}
.h-dropdown-wrap:hover .h-dropdown,
.h-dropdown-wrap:focus-within .h-dropdown {
  opacity: 1;
  transform: translateY(0) scale(1);
  pointer-events: auto;
}
.h-dropdown a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 14px;
  border-radius: 8px;
  font-size: 0.82rem;
  color: var(--ink);
  font-weight: 450;
  transition: background 0.15s, color 0.15s;
}
.h-dropdown a:hover {
  background: var(--mint);
  color: var(--forest);
}
.h-dropdown a i {
  width: 14px;
  text-align: center;
  color: var(--muted);
  font-size: 12px;
}
.h-dropdown-divider {
  height: 1px;
  background: rgba(10,74,30,0.08);
  margin: 4px 8px;
}

/* ===== SEARCH ===== */
.h-search-wrap {
  position: relative;
}
.h-search-input {
  width: 220px;
  padding: 7px 36px 7px 34px;
  border-radius: 100px;
  border: 1.5px solid rgba(10,74,30,0.15);
  background: rgba(10,74,30,0.04);
  font-size: 0.8rem;
  font-family: 'DM Sans', sans-serif;
  color: var(--ink);
  transition: border-color 0.2s, background 0.2s, width 0.3s;
  outline: none;
}
.h-search-input:focus {
  width: 260px;
  border-color: var(--leaf);
  background: white;
  box-shadow: 0 0 0 3px rgba(46,168,85,0.12);
}
.h-search-input::placeholder { color: var(--muted); opacity: 0.7; }
.h-search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 12px;
  pointer-events: none;
}
.h-search-clear {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 12px;
  background: none;
  border: none;
  cursor: pointer;
  padding: 2px;
  transition: color 0.2s;
}
.h-search-clear:hover { color: var(--forest); }
#globalSearchResults {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  background: white;
  border: 1px solid rgba(10,74,30,0.1);
  border-radius: 14px;
  box-shadow: 0 16px 48px rgba(10,74,30,0.14);
  overflow: hidden;
  z-index: 300;
  max-height: 380px;
  overflow-y: auto;
}
#universalHeader.dark-bg .h-search-input {
  background: rgba(255,255,255,0.12);
  border-color: rgba(255,255,255,0.2);
  color: white;
}
#universalHeader.dark-bg .h-search-input::placeholder { color: rgba(255,255,255,0.5); }

/* ===== ICON BUTTONS ===== */
.h-icon-btn {
  position: relative;
  width: 36px; height: 36px;
  border-radius: 10px;
  border: none;
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ink);
  cursor: pointer;
  transition: background 0.2s, color 0.2s;
  font-size: 15px;
}
.h-icon-btn:hover {
  background: rgba(10,74,30,0.08);
  color: var(--emerald);
}
#universalHeader.dark-bg .h-icon-btn { color: rgba(255,255,255,0.85); }
#universalHeader.dark-bg .h-icon-btn:hover { background: rgba(255,255,255,0.12); color: white; }

/* Badge */
.h-badge {
  position: absolute;
  top: 1px; right: 1px;
  min-width: 16px; height: 16px;
  padding: 0 4px;
  background: #ef4444;
  color: white;
  font-size: 10px;
  font-weight: 700;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
  border: 1.5px solid white;
}

/* ===== NOTIFICATION / MESSAGE DROPDOWNS ===== */
/* Closed state: do not rely on Tailwind `.hidden` (many alumni pages omit Tailwind). */
#notificationDropdown.is-collapsed,
#messagesDropdown.is-collapsed {
  display: none !important;
  pointer-events: none;
  visibility: hidden;
}
.h-panel {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 320px;
  background: white;
  border: 1px solid rgba(10,74,30,0.1);
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(10,74,30,0.15), 0 2px 8px rgba(0,0,0,0.06);
  z-index: 300;
  overflow: hidden;
}
.h-panel-header {
  padding: 14px 18px 12px;
  border-bottom: 1px solid rgba(10,74,30,0.07);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.h-panel-title {
  font-family: 'DM Sans', sans-serif;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--forest);
  letter-spacing: -0.01em;
}
.h-panel-action {
  font-size: 0.75rem;
  color: var(--emerald);
  font-weight: 600;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  transition: color 0.2s;
}
.h-panel-action:hover { color: var(--forest); }
.h-panel-body { max-height: 280px; overflow-y: auto; }
.h-panel-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 18px;
  border-bottom: 1px solid rgba(10,74,30,0.05);
  transition: background 0.15s;
  text-decoration: none;
}
.h-panel-item:last-child { border-bottom: none; }
.h-panel-item:hover { background: rgba(10,74,30,0.04); }
.h-panel-item.unread { background: rgba(46,168,85,0.05); }
.h-notif-icon {
  width: 32px; height: 32px;
  border-radius: 8px;
  background: var(--mint);
  color: var(--emerald);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  flex-shrink: 0;
  margin-top: 1px;
}
.h-panel-item.unread .h-notif-icon { background: rgba(46,168,85,0.2); }
.h-notif-title {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--forest);
  line-height: 1.35;
  margin-bottom: 2px;
}
.h-notif-body {
  font-size: 0.75rem;
  color: var(--muted);
  line-height: 1.4;
}
.h-notif-time {
  font-size: 0.7rem;
  color: rgba(90,107,97,0.6);
  margin-top: 3px;
  font-family: 'DM Mono', monospace;
}
.h-panel-footer {
  padding: 10px 18px;
  border-top: 1px solid rgba(10,74,30,0.07);
  text-align: center;
}
.h-panel-footer a {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--emerald);
  transition: color 0.2s;
}
.h-panel-footer a:hover { color: var(--forest); }
.h-panel-empty {
  padding: 28px 18px;
  text-align: center;
  color: var(--muted);
  font-size: 0.82rem;
}
.h-panel-empty i { font-size: 1.6rem; display: block; margin-bottom: 8px; opacity: 0.4; }

/* ===== JOIN BUTTON ===== */
.h-join-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 18px;
  border-radius: 100px;
  background: var(--forest);
  color: white;
  font-size: 0.8rem;
  font-weight: 600;
  font-family: 'DM Sans', sans-serif;
  border: none;
  cursor: pointer;
  transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
  text-decoration: none;
  box-shadow: 0 4px 16px rgba(10,74,30,0.3);
  position: relative;
  overflow: hidden;
}
.h-join-btn::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, transparent 30%, rgba(255,255,255,0.15) 50%, transparent 70%);
  transform: translateX(-100%);
  transition: transform 0.5s ease;
}
.h-join-btn:hover {
  background: var(--emerald);
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(10,74,30,0.35);
}
.h-join-btn:hover::before { transform: translateX(100%); }

#universalHeader.dark-bg .h-join-btn {
  background: var(--gold);
  color: var(--forest);
  box-shadow: 0 4px 16px rgba(201,168,76,0.4);
}
#universalHeader.dark-bg .h-join-btn:hover {
  background: var(--gold-lt);
}

/* ===== FLOATING WIDGETS ===== */
@keyframes pulseGlow {
  0%,100% { box-shadow: 0 8px 24px rgba(10,74,30,0.35); }
  50%      { box-shadow: 0 12px 36px rgba(10,74,30,0.55); }
}
@keyframes pulseGlowBlue {
  0%,100% { box-shadow: 0 8px 24px rgba(59,130,246,0.35); }
  50%      { box-shadow: 0 12px 36px rgba(59,130,246,0.55); }
}
@keyframes shimmerBtn {
  0%   { background-position: -200% center; }
  100% { background-position:  200% center; }
}
@keyframes shineBtn {
  0%   { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
  100% { transform: translateX(200%)  translateY(200%)  rotate(45deg); }
}

.floating-fab {
  width: 52px; height: 52px;
  border-radius: 50%;
  border: none;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: transform 0.2s;
  color: white;
}
.floating-fab:hover { transform: scale(1.08); animation: none !important; }
.floating-fab::before {
  content: '';
  position: absolute;
  top: -50%; left: -50%;
  width: 200%; height: 200%;
  background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.25) 50%, transparent 70%);
  animation: shineBtn 3.5s infinite;
  pointer-events: none;
}

#floatingProfileBtn {
  background: linear-gradient(135deg, #22c55e 0%, #15803d 50%, #22c55e 100%);
  background-size: 200% 200%;
  animation: pulseGlow 2.5s ease-in-out infinite, shimmerBtn 4s linear infinite;
}
#chatbotBtn {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 50%, #3b82f6 100%);
  background-size: 200% 200%;
  animation: pulseGlowBlue 2.5s ease-in-out infinite, shimmerBtn 4s linear infinite;
}

.floating-hidden {
  opacity: 0;
  pointer-events: none !important;
  transform: translateX(130%);
  transition: transform 0.3s ease, opacity 0.3s ease;
}
#chatbotContainer, #floatingProfileMenu {
  transition: transform 0.3s ease, opacity 0.3s ease;
}

/* Go-up btn */
#goUpBtn {
  background: var(--forest);
  box-shadow: 0 6px 20px rgba(10,74,30,0.35);
  transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
}
#goUpBtn:hover {
  background: var(--emerald);
  transform: translateY(-2px);
  box-shadow: 0 10px 28px rgba(10,74,30,0.4);
}

/* Toggle btn */
#floatingWidgetsToggle {
  position: fixed;
  bottom: 22px;
  right: 154px;
  width: 36px; height: 36px;
  border-radius: 50%;
  background: rgba(10,74,30,0.85);
  color: rgba(255,255,255,0.85);
  border: 1px solid rgba(255,255,255,0.15);
  display: flex; align-items: center; justify-content: center;
  z-index: 60;
  cursor: pointer;
  font-size: 11px;
  transition: right 0.3s, background 0.2s, transform 0.2s;
}
#floatingWidgetsToggle.collapsed { right: 22px; }
#floatingWidgetsToggle:hover {
  background: var(--forest);
  transform: scale(1.08);
}

/* ===== PROFILE MODAL ===== */
.fp-modal {
  font-family: 'DM Sans', sans-serif;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 24px 64px rgba(10,74,30,0.2), 0 2px 8px rgba(0,0,0,0.08);
  border: 1px solid rgba(10,74,30,0.1);
  transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), opacity 0.2s ease;
}
.fp-header {
  background: linear-gradient(135deg, var(--forest) 0%, var(--emerald) 100%);
  padding: 28px 24px 24px;
  position: relative;
}
.fp-avatar {
  width: 68px; height: 68px;
  border-radius: 50%;
  background: rgba(255,255,255,0.15);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  border: 3px solid rgba(255,255,255,0.3);
  overflow: hidden;
}
.fp-avatar img { width: 100%; height: 100%; object-fit: cover; }
.fp-avatar-initials {
  font-family: 'Playfair Display', serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: white;
}
.fp-name {
  font-family: 'Playfair Display', serif;
  font-size: 1.2rem;
  font-weight: 700;
  color: white;
  letter-spacing: -0.01em;
  margin-bottom: 2px;
}
.fp-meta {
  font-size: 0.75rem;
  color: rgba(255,255,255,0.7);
  font-family: 'DM Mono', monospace;
  letter-spacing: 0.05em;
}
.fp-close {
  position: absolute;
  top: 14px; right: 14px;
  width: 28px; height: 28px;
  border-radius: 50%;
  background: rgba(255,255,255,0.12);
  border: none;
  color: rgba(255,255,255,0.8);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  font-size: 12px;
  transition: background 0.2s;
}
.fp-close:hover { background: rgba(255,255,255,0.22); color: white; }

/* Tabs */
.fp-tabs {
  display: flex;
  border-bottom: 1px solid rgba(10,74,30,0.08);
  background: white;
  padding: 0 6px;
}
.fp-tab {
  flex: 1;
  padding: 12px 8px;
  font-size: 0.78rem;
  font-weight: 500;
  color: var(--muted);
  border: none;
  background: transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: color 0.2s, border-color 0.2s;
}
.fp-tab:hover { color: var(--forest); }
.fp-tab.active {
  color: var(--forest);
  border-bottom-color: var(--leaf);
  font-weight: 600;
}

/* Tab content */
.fp-body {
  padding: 20px;
  background: white;
  flex: 1;
  overflow-y: auto;
}
.fp-field-label {
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--muted);
  font-family: 'DM Mono', monospace;
  margin-bottom: 4px;
}
.fp-field-value {
  font-size: 0.875rem;
  color: var(--ink);
  font-weight: 500;
}
.fp-action-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  background: rgba(10,74,30,0.03);
  border: 1px solid rgba(10,74,30,0.07);
  text-decoration: none;
  transition: background 0.15s, border-color 0.15s;
  cursor: pointer;
  border-color: transparent;
}
.fp-action-row:hover { background: var(--mint); border-color: rgba(10,74,30,0.1); }
.fp-action-icon {
  width: 36px; height: 36px;
  border-radius: 9px;
  background: var(--mint);
  color: var(--emerald);
  display: flex; align-items: center; justify-content: center;
  font-size: 13px;
  flex-shrink: 0;
}
.fp-action-title {
  font-size: 0.83rem;
  font-weight: 600;
  color: var(--forest);
}
.fp-action-sub {
  font-size: 0.73rem;
  color: var(--muted);
}
.fp-edit-btn {
  width: 100%;
  padding: 10px;
  border-radius: 10px;
  border: 1.5px solid rgba(10,74,30,0.2);
  background: white;
  color: var(--forest);
  font-size: 0.83rem;
  font-weight: 600;
  font-family: 'DM Sans', sans-serif;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}
.fp-edit-btn:hover { background: var(--mint); border-color: var(--leaf); }
.fp-logout-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: #dc2626;
  font-size: 0.83rem;
  font-weight: 600;
  font-family: 'DM Sans', sans-serif;
  cursor: pointer;
  transition: background 0.15s;
  text-align: left;
}
.fp-logout-btn:hover { background: #fef2f2; }

/* ===== CHATBOT MODAL ===== */
.cb-modal {
  font-family: 'DM Sans', sans-serif;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 24px 64px rgba(59,130,246,0.18), 0 2px 8px rgba(0,0,0,0.08);
  border: 1px solid rgba(59,130,246,0.12);
  transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), opacity 0.2s ease;
}
.cb-header {
  background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
  padding: 18px 20px;
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
}
.cb-avatar {
  width: 38px; height: 38px;
  border-radius: 50%;
  background: rgba(255,255,255,0.18);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.cb-title { font-size: 0.95rem; font-weight: 700; color: white; }
.cb-sub { font-size: 0.72rem; color: rgba(255,255,255,0.75); font-family: 'DM Mono', monospace; }
.cb-close {
  position: absolute; top: 12px; right: 12px;
  width: 26px; height: 26px; border-radius: 50%;
  background: rgba(255,255,255,0.15); border: none;
  color: rgba(255,255,255,0.85); font-size: 11px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background 0.2s;
}
.cb-close:hover { background: rgba(255,255,255,0.25); }
.cb-messages { flex: 1; overflow-y: auto; padding: 16px; background: #f8fafc; display: flex; flex-direction: column; gap: 14px; }
.cb-bubble-wrap { display: flex; align-items: flex-start; gap: 8px; }
.cb-bubble-wrap.user { flex-direction: row-reverse; }
.cb-bubble-icon {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-size: 11px; color: white;
}
.cb-bubble-icon.bot { background: #3b82f6; }
.cb-bubble-icon.user { background: var(--leaf); }
.cb-bubble {
  max-width: 78%;
  padding: 10px 14px;
  border-radius: 14px;
  font-size: 0.82rem;
  line-height: 1.55;
}
.cb-bubble.bot { background: white; color: var(--ink); border: 1px solid rgba(59,130,246,0.1); border-radius: 4px 14px 14px 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.cb-bubble.user { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border-radius: 14px 4px 14px 14px; }
.cb-suggestions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.cb-suggestion-btn {
  padding: 4px 10px;
  border-radius: 100px;
  background: rgba(59,130,246,0.1);
  color: #2563eb;
  font-size: 0.72rem;
  font-weight: 600;
  border: 1px solid rgba(59,130,246,0.2);
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
  font-family: 'DM Sans', sans-serif;
}
.cb-suggestion-btn:hover { background: rgba(59,130,246,0.18); border-color: #3b82f6; }
.cb-input-area {
  border-top: 1px solid rgba(59,130,246,0.1);
  padding: 12px 14px;
  background: white;
  display: flex; gap: 8px; align-items: center;
}
.cb-input {
  flex: 1;
  padding: 8px 14px;
  border-radius: 100px;
  border: 1.5px solid rgba(59,130,246,0.2);
  font-size: 0.82rem;
  font-family: 'DM Sans', sans-serif;
  outline: none;
  transition: border-color 0.2s;
  color: var(--ink);
}
.cb-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.cb-send-btn {
  width: 34px; height: 34px;
  border-radius: 50%;
  background: #3b82f6;
  border: none; color: white;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-size: 12px;
  transition: background 0.2s, transform 0.15s;
}
.cb-send-btn:hover { background: #1d4ed8; transform: scale(1.06); }

/* Ensure fp tab-content isolation */
#floatingProfileModal .tab-content { position: static !important; opacity: 1 !important; transform: none !important; pointer-events: auto !important; }
#floatingProfileModal .tab-content.hidden { display: none !important; }
#floatingProfileModal .tab-content:not(.hidden) { display: block !important; }
</style>

<!-- Load fonts if not already loaded by parent page -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

<!-- ====== HEADER ====== -->
<header id="universalHeader">
  <div style="max-width:1400px;margin:0 auto;padding:0 24px;width:100%;display:flex;align-items:center;gap:16px;">

    <!-- Logo -->
    <a href="al_homepage.php" style="display:flex;align-items:center;gap:10px;flex-shrink:0;text-decoration:none;">
      <img src="olfulogo.png" alt="OLFU Logo" style="height:36px;width:auto;" />
      <img src="ccs_logo.png" alt="CCS Logo" style="height:36px;width:auto;" />
      <div class="hidden sm:block" style="padding-left:4px;border-left:1px solid rgba(10,74,30,0.15);margin-left:4px;">
        <div class="header-wordmark-title">Alumni Management System</div>
        <div class="header-wordmark-sub">Our Lady of Fatima University</div>
      </div>
    </a>

    <!-- Nav (desktop) -->
    <nav style="flex:1;display:flex;align-items:center;gap:4px;" class="hidden md:flex">
      <?php if ($user_id): ?>
        <a href="javascript:void(0)" onclick="handleNavClick('al_dashboard.php')" class="h-nav-link">
          <i class="fas fa-th-large" style="font-size:11px;"></i> Dashboard
        </a>
        <a href="javascript:void(0)" onclick="handleNavClick('al_career.php')" class="h-nav-link">
          <i class="fas fa-briefcase" style="font-size:11px;"></i> Career
        </a>
        <a href="javascript:void(0)" onclick="handleNavClick('al_events.php')" class="h-nav-link">
          <i class="fas fa-calendar-alt" style="font-size:11px;"></i> Events
        </a>
        <div class="h-dropdown-wrap">
          <button class="h-nav-link">
            <i class="fas fa-users" style="font-size:11px;"></i> Find Alumni
            <i class="fas fa-chevron-down h-chevron"></i>
          </button>
          <div class="h-dropdown">
            <a href="javascript:void(0)" onclick="handleNavClick('al_directory.php')"><i class="fas fa-address-book"></i> Alumni Directory</a>
            <a href="al_gallery.php"><i class="fas fa-images"></i> Alumni Gallery</a>
            <a href="alumni_card_details.php"><i class="fas fa-id-card"></i> Alumni Card</a>
          </div>
        </div>
        <div class="h-dropdown-wrap">
          <button class="h-nav-link">
            <i class="fas fa-info-circle" style="font-size:11px;"></i> About
            <i class="fas fa-chevron-down h-chevron"></i>
          </button>
          <div class="h-dropdown">
            <a href="javascript:void(0)" onclick="handleNavClick('al_about.php')"><i class="fas fa-university"></i> About OLFU Alumni</a>
            <a href="gen_faqs.php"><i class="fas fa-question-circle"></i> FAQs</a>
            <a href="al_contact.php"><i class="fas fa-envelope"></i> Contact Us</a>
          </div>
        </div>
      <?php else: ?>
        <a href="javascript:void(0)" onclick="handleNavClick('al_events.php')" class="h-nav-link">
          <i class="fas fa-calendar-alt" style="font-size:11px;"></i> Events
        </a>
        <div class="h-dropdown-wrap">
          <button class="h-nav-link">
            <i class="fas fa-users" style="font-size:11px;"></i> Find Alumni
            <i class="fas fa-chevron-down h-chevron"></i>
          </button>
          <div class="h-dropdown">
            <a href="javascript:void(0)" onclick="handleNavClick('al_directory.php')"><i class="fas fa-address-book"></i> Alumni Directory</a>
            <a href="al_gallery.php"><i class="fas fa-images"></i> Alumni Gallery</a>
            <a href="alumni_card_details.php"><i class="fas fa-id-card"></i> Alumni Card</a>
          </div>
        </div>
        <div class="h-dropdown-wrap">
          <button class="h-nav-link">
            <i class="fas fa-info-circle" style="font-size:11px;"></i> About
            <i class="fas fa-chevron-down h-chevron"></i>
          </button>
          <div class="h-dropdown">
            <a href="javascript:void(0)" onclick="handleNavClick('al_about.php')"><i class="fas fa-university"></i> About OLFU Alumni</a>
            <a href="gen_faqs.php"><i class="fas fa-question-circle"></i> FAQs</a>
            <a href="al_contact.php"><i class="fas fa-envelope"></i> Contact Us</a>
          </div>
        </div>
      <?php endif; ?>
    </nav>

    <?php if (!empty($show_global_header_search)): ?>
    <!-- Search -->
    <div class="h-search-wrap hidden md:block">
      <i class="fas fa-search h-search-icon"></i>
      <input id="globalSearch" type="text" class="h-search-input" placeholder="Search…" autocomplete="off" />
      <button id="globalSearchClear" class="h-search-clear hidden"><i class="fas fa-times"></i></button>
      <div id="globalSearchResults" class="hidden"></div>
    </div>
    <?php endif; ?>

    <!-- Right actions -->
    <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
      <?php if ($user_id): ?>

        <!-- Notifications -->
        <div style="position:relative;" id="notifWrap">
          <button class="h-icon-btn" id="notificationBell" aria-label="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($notification_count > 0): ?>
              <span class="h-badge"><?= $notification_count ?></span>
            <?php endif; ?>
          </button>
          <div class="h-panel is-collapsed" id="notificationDropdown" style="width:340px;">
            <div class="h-panel-header">
              <span class="h-panel-title">Notifications</span>
              <?php if (!empty($notifications)): ?>
                <button class="h-panel-action" id="markAllRead">Mark all read</button>
              <?php endif; ?>
            </div>
            <div class="h-panel-body">
              <?php if (empty($notifications)): ?>
                <div class="h-panel-empty"><i class="fas fa-bell-slash"></i>No notifications yet</div>
              <?php else: foreach ($notifications as $n):
                $nUrl   = htmlspecialchars(getAlumniNotificationUrl($n));
                $isRead = !empty($n['is_read']);
                $iconMap = ['job_post'=>'fa-briefcase','job_approved'=>'fa-check-circle','message'=>'fa-envelope','verification'=>'fa-user-check','rejection'=>'fa-user-times'];
                $icon   = $iconMap[$n['type']??''] ?? 'fa-bell';
              ?>
                <a href="<?= $nUrl ?>" class="h-panel-item <?= !$isRead ? 'unread' : '' ?>">
                  <div class="h-notif-icon"><i class="fas <?= $icon ?>"></i></div>
                  <div style="flex:1;min-width:0;">
                    <?php if (!empty($n['title'])): ?>
                      <div class="h-notif-title"><?= htmlspecialchars($n['title']) ?></div>
                    <?php endif; ?>
                    <div class="h-notif-body"><?= htmlspecialchars($n['message'] ?? '') ?></div>
                    <div class="h-notif-time"><?= !empty($n['created_at']) ? date('M d · g:i A', strtotime($n['created_at'])) : '' ?></div>
                  </div>
                  <?php if (!$isRead): ?><div style="width:7px;height:7px;border-radius:50%;background:var(--leaf);flex-shrink:0;margin-top:4px;"></div><?php endif; ?>
                </a>
              <?php endforeach; endif; ?>
            </div>
            <?php if (!empty($notifications)): ?>
              <div class="h-panel-footer"><a href="al_dashboard.php">View all activity</a></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Messages -->
        <div style="position:relative;" id="msgWrap">
          <?php
            $stmt = $conn->prepare("SELECT COUNT(*) as uc FROM messages WHERE receiver_id = ? AND is_read = 0");
            $unread_count = 0;
            if ($stmt) { $stmt->bind_param('i',$user_id); $stmt->execute(); $r=$stmt->get_result()->fetch_assoc(); $unread_count=(int)($r['uc']??0); $stmt->close(); }
            $recent_messages = [];
            $stmt = $conn->prepare("SELECT m.*,s.firstname as sf,s.lastname as sl,r.firstname as rf,r.lastname as rl FROM messages m JOIN itcp s ON m.sender_id=s.id JOIN itcp r ON m.receiver_id=r.id WHERE m.sender_id=? OR m.receiver_id=? ORDER BY m.created_at DESC LIMIT 5");
            if ($stmt) { $stmt->bind_param('ii',$user_id,$user_id); $stmt->execute(); $recent_messages=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
          ?>
          <button class="h-icon-btn" id="messagesButton" aria-label="Messages">
            <i class="fas fa-envelope"></i>
            <?php if ($unread_count > 0): ?>
              <span class="h-badge"><?= $unread_count ?></span>
            <?php endif; ?>
          </button>
          <div class="h-panel is-collapsed" id="messagesDropdown">
            <div class="h-panel-header">
              <span class="h-panel-title">Messages</span>
              <a href="al_messages.php" class="h-panel-action">Compose</a>
            </div>
            <div class="h-panel-body">
              <?php if (empty($recent_messages)): ?>
                <div class="h-panel-empty"><i class="fas fa-inbox"></i>No messages yet</div>
              <?php else: foreach ($recent_messages as $msg):
                $toMe    = $msg['receiver_id'] == $user_id;
                $partner = $toMe ? $msg['sf'].' '.$msg['sl'] : $msg['rf'].' '.$msg['rl'];
                $unreadDot = (!$msg['is_read'] && $toMe);
              ?>
                <a href="al_messages.php" class="h-panel-item <?= $unreadDot ? 'unread' : '' ?>">
                  <div class="h-notif-icon" style="background:<?= $toMe ? 'var(--mint)' : '#eff6ff' ?>;color:<?= $toMe ? 'var(--emerald)' : '#3b82f6' ?>;">
                    <i class="fas fa-<?= $toMe ? 'inbox' : 'paper-plane' ?>"></i>
                  </div>
                  <div style="flex:1;min-width:0;">
                    <div class="h-notif-title"><?= $toMe ? 'From: ' : 'To: ' ?><?= htmlspecialchars($partner) ?></div>
                    <div class="h-notif-body" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($msg['subject'] ?? '') ?></div>
                    <div class="h-notif-time"><?= date('M d', strtotime($msg['created_at'])) ?></div>
                  </div>
                  <?php if ($unreadDot): ?><div style="width:7px;height:7px;border-radius:50%;background:#3b82f6;flex-shrink:0;margin-top:4px;"></div><?php endif; ?>
                </a>
              <?php endforeach; endif; ?>
            </div>
            <div class="h-panel-footer"><a href="al_messages.php">See all messages</a></div>
          </div>
        </div>

      <?php else: ?>
        <a href="al_login.php" class="h-join-btn">
          <i class="fas fa-user-plus" style="font-size:11px;"></i>
          <span class="hidden sm:inline">Join Us</span>
        </a>
      <?php endif; ?>

    </div>

  </div>
</header>

<?php if ($user_id):
  $stmt = $conn->prepare("SELECT email, personal_contact, student_number, program, year_graduated FROM itcp WHERE id = ?");
  $profile_data = ['email'=>'','phone'=>'','student_id'=>'','program'=>'','year_graduated'=>''];
  if ($stmt) {
    $stmt->bind_param('i', $user_id); $stmt->execute(); $r = $stmt->get_result()->fetch_assoc();
    if ($r) $profile_data = ['email'=>$r['email']??'','phone'=>$r['personal_contact']??'','student_id'=>$r['student_number']??'','program'=>$r['program']??'','year_graduated'=>$r['year_graduated']??''];
    $stmt->close();
  }
  $firstname  = $user['firstname'] ?? '';
  $lastname   = $user['lastname']  ?? '';
  $initials   = strtoupper(substr($firstname,0,1).substr($lastname,0,1));
  $full_name  = trim($firstname.' '.$lastname);
  $class_year = !empty($profile_data['year_graduated']) ? 'Class of '.$profile_data['year_graduated'] : '';
?>

<!-- ====== CHATBOT ====== -->
<div id="chatbotContainer" style="position:fixed;bottom:22px;right:88px;z-index:150;">
  <button id="chatbotBtn" class="floating-fab" title="FAQ Assistant" aria-label="Open FAQ chatbot">
    <i class="fas fa-comments" style="font-size:18px;position:relative;z-index:1;"></i>
  </button>
  <div id="chatbotModal" class="cb-modal hidden" style="position:fixed;bottom:90px;right:88px;width:360px;height:520px;display:flex;flex-direction:column;z-index:160;transform:scale(0.92);opacity:0;">
    <div class="cb-header">
      <div class="cb-avatar"><i class="fas fa-robot" style="color:white;font-size:16px;"></i></div>
      <div>
        <div class="cb-title">FAQ Assistant</div>
        <div class="cb-sub">Ask me anything</div>
      </div>
      <button id="closeChatbotModal" class="cb-close"><i class="fas fa-times"></i></button>
    </div>
    <div id="chatbotMessages" class="cb-messages">
      <div class="cb-bubble-wrap">
        <div class="cb-bubble-icon bot"><i class="fas fa-robot"></i></div>
        <div class="cb-bubble bot">Hello! I'm here to help with frequently asked questions. How can I assist you today?
          <div class="cb-suggestions">
            <button class="cb-suggestion-btn" onclick="cbSuggest('How do I register?')">How do I register?</button>
            <button class="cb-suggestion-btn" onclick="cbSuggest('How do I reset my password?')">Reset password</button>
            <button class="cb-suggestion-btn" onclick="cbSuggest('What features are available?')">Features</button>
          </div>
        </div>
      </div>
    </div>
    <div class="cb-input-area">
      <input type="text" id="chatbotInput" class="cb-input" placeholder="Type your question…" autocomplete="off" />
      <button id="chatbotSendBtn" class="cb-send-btn"><i class="fas fa-paper-plane"></i></button>
    </div>
  </div>
</div>

<!-- ====== FLOATING PROFILE ====== -->
<div id="floatingProfileMenu" style="position:fixed;bottom:22px;right:22px;z-index:150;">
  <button id="floatingProfileBtn" class="floating-fab" aria-label="Profile menu">
    <?php if (!empty($user['photo']) && file_exists(__DIR__.'/uploads/'.$user['photo'])): ?>
      <img src="serve_profile_image.php?img=<?= urlencode($user['photo']) ?>" alt="Profile" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.4);position:relative;z-index:1;">
    <?php else: ?>
      <span class="fp-avatar-initials" style="position:relative;z-index:1;"><?= htmlspecialchars($initials) ?></span>
    <?php endif; ?>
  </button>
  <div id="floatingProfileModal" class="fp-modal hidden" style="position:fixed;bottom:90px;right:22px;width:320px;height:520px;background:white;display:flex;flex-direction:column;z-index:160;transform:scale(0.92);opacity:0;">
    <div class="fp-header">
      <button id="closeProfileModal" class="fp-close"><i class="fas fa-times"></i></button>
      <div style="display:flex;align-items:center;gap:14px;">
        <div class="fp-avatar">
          <?php if (!empty($user['photo']) && file_exists(__DIR__.'/uploads/'.$user['photo'])): ?>
            <img src="serve_profile_image.php?img=<?= urlencode($user['photo']) ?>" alt="Profile" />
          <?php else: ?>
            <span class="fp-avatar-initials"><?= htmlspecialchars($initials) ?></span>
          <?php endif; ?>
        </div>
        <div>
          <div class="fp-name"><?= htmlspecialchars($full_name) ?></div>
          <?php if ($class_year): ?><div class="fp-meta"><?= htmlspecialchars($class_year) ?></div><?php endif; ?>
          <?php if (!empty($profile_data['program'])): ?><div class="fp-meta" style="opacity:.75;"><?= htmlspecialchars($profile_data['program']) ?></div><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="fp-tabs">
      <button class="fp-tab active" data-tab="profile"><i class="fas fa-user" style="font-size:11px;"></i> Profile</button>
      <button class="fp-tab" data-tab="career"><i class="fas fa-briefcase" style="font-size:11px;"></i> Career</button>
      <button class="fp-tab" data-tab="settings"><i class="fas fa-cog" style="font-size:11px;"></i> Settings</button>
    </div>
    <div class="fp-body">
      <!-- Profile Tab -->
      <div id="profileTabContent" class="tab-content">
        <div style="display:flex;flex-direction:column;gap:16px;margin-bottom:20px;">
          <div>
            <div class="fp-field-label">Email</div>
            <div class="fp-field-value"><?= htmlspecialchars($profile_data['email'] ?: 'Not provided') ?></div>
          </div>
          <div>
            <div class="fp-field-label">Phone</div>
            <div class="fp-field-value"><?= htmlspecialchars($profile_data['phone'] ?: 'Not provided') ?></div>
          </div>
          <div>
            <div class="fp-field-label">Student ID</div>
            <div class="fp-field-value"><?= htmlspecialchars($profile_data['student_id'] ?: 'Not provided') ?></div>
          </div>
        </div>
        <button onclick="handleNavClick('al_profileupdate.php')" class="fp-edit-btn">Edit Profile</button>
      </div>
      <!-- Career Tab -->
      <div id="careerTabContent" class="tab-content hidden">
        <div style="display:flex;flex-direction:column;gap:10px;">
          <a href="javascript:void(0)" onclick="handleNavClick('al_mycareer.php')" class="fp-action-row">
            <div class="fp-action-icon"><i class="fas fa-briefcase"></i></div>
            <div><div class="fp-action-title">My Career</div><div class="fp-action-sub">View and manage your career profile</div></div>
          </a>
          <a href="javascript:void(0)" onclick="handleNavClick('al_career.php')" class="fp-action-row">
            <div class="fp-action-icon" style="background:#eff6ff;color:#3b82f6;"><i class="fas fa-search"></i></div>
            <div><div class="fp-action-title">Job Opportunities</div><div class="fp-action-sub">Browse available job postings</div></div>
          </a>
        </div>
      </div>
      <!-- Settings Tab -->
      <div id="settingsTabContent" class="tab-content hidden">
        <div style="display:flex;flex-direction:column;gap:6px;">
          <a href="javascript:void(0)" onclick="handleNavClick('al_general_settings.php')" class="fp-action-row">
            <div class="fp-action-icon"><i class="fas fa-cog"></i></div>
            <div><div class="fp-action-title">General Settings</div></div>
          </a>
          <a href="javascript:void(0)" onclick="handleNavClick('al_privacy_settings.php')" class="fp-action-row">
            <div class="fp-action-icon"><i class="fas fa-shield-alt"></i></div>
            <div><div class="fp-action-title">Privacy & Security</div></div>
          </a>
          <a href="javascript:void(0)" onclick="handleNavClick('al_my_tickets.php')" class="fp-action-row">
            <div class="fp-action-icon"><i class="fas fa-ticket-alt"></i></div>
            <div><div class="fp-action-title">My Tickets</div></div>
          </a>
          <div style="height:1px;background:rgba(10,74,30,0.08);margin:6px 0;"></div>
          <button onclick="showLogoutModal()" class="fp-logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Go Up -->
<button id="goUpBtn" class="hidden" style="position:fixed;right:22px;bottom:90px;width:40px;height:40px;border-radius:50%;border:none;color:white;display:none;align-items:center;justify-content:center;cursor:pointer;z-index:140;font-size:14px;" aria-label="Back to top">
  <i class="fas fa-arrow-up"></i>
</button>

<!-- Widgets Toggle -->
<button id="floatingWidgetsToggle" aria-label="Toggle widgets"><i class="fas fa-chevron-right"></i></button>

<?php endif; ?>

<!-- ====== LOGOUT MODAL ====== -->
<div id="logoutModal" class="hidden" style="position:fixed;inset:0;background:rgba(10,74,30,0.5);backdrop-filter:blur(4px);z-index:1000;display:none;align-items:center;justify-content:center;">
  <div style="background:white;border-radius:20px;padding:36px;max-width:380px;width:90%;text-align:center;box-shadow:0 24px 64px rgba(10,74,30,0.2);font-family:'DM Sans',sans-serif;">
    <div style="width:52px;height:52px;border-radius:50%;background:#fff7e6;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;color:#d97706;font-size:20px;"><i class="fas fa-sign-out-alt"></i></div>
    <div style="font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;color:var(--forest);margin-bottom:10px;">Confirm Logout</div>
    <p style="font-size:0.875rem;color:var(--muted);line-height:1.6;margin-bottom:26px;">Are you sure you want to log out? You'll need to sign in again to access your account.</p>
    <div style="display:flex;gap:10px;justify-content:center;">
      <button id="cancelLogout" style="padding:10px 24px;border-radius:100px;border:1.5px solid #ddd;background:white;color:var(--muted);font-size:0.875rem;font-weight:500;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .2s;">Cancel</button>
      <form action="al_logout.php" method="POST" style="display:inline;">
        <button type="submit" style="padding:10px 24px;border-radius:100px;border:none;background:#dc2626;color:white;font-size:0.875rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .2s;">Logout</button>
      </form>
    </div>
  </div>
</div>

<!-- ====== SCRIPTS ====== -->
<script>
(function(){
  'use strict';

  /* ---- ADAPTIVE HEADER ---- */
  const header = document.getElementById('universalHeader');
  function getColorBrightness(color) {
    const m = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    if (!m) return 255;
    return (parseInt(m[1])*299 + parseInt(m[2])*587 + parseInt(m[3])*114) / 1000;
  }
  function getBg(el) {
    if (!el || el === document.body || el === document.documentElement) return null;
    const bg = window.getComputedStyle(el).backgroundColor;
    if (bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') return getBg(el.parentElement);
    return bg;
  }
  function checkHeader() {
    try {
      // Scrolled state
      header.classList.toggle('scrolled', window.scrollY > 10);
      // Dark-bg detection
      const hh = header.getBoundingClientRect().height;
      const el = document.elementFromPoint(window.innerWidth/2, hh+2);
      if (!el || el === header) { header.classList.remove('dark-bg'); return; }
      let bg = getBg(el) || window.getComputedStyle(document.body).backgroundColor;
      if (!bg || bg === 'rgba(0, 0, 0, 0)') { header.classList.remove('dark-bg'); return; }
      header.classList.toggle('dark-bg', getColorBrightness(bg) < 128);
    } catch(e) { header.classList.remove('dark-bg'); }
  }
  window.addEventListener('scroll', checkHeader, { passive:true });
  window.addEventListener('resize', () => setTimeout(checkHeader, 60), { passive:true });
  setTimeout(checkHeader, 80);
  setInterval(checkHeader, 1200);

  /* ---- SEARCH ---- */
  const si = document.getElementById('globalSearch');
  const sr = document.getElementById('globalSearchResults');
  const sc = document.getElementById('globalSearchClear');
  let sAC = null, sDeb = null;
  function clearSearch() { if(sr){ sr.classList.add('hidden'); sr.innerHTML=''; } }
  function renderSearchSection(title,items,type) {
    if(!items||!items.length) return '';
    const iconMap = {people:'user',events:'calendar',jobs:'briefcase',pages:'file-alt'};
    const icon = iconMap[type] || 'file-alt';
    let h = `<div style="padding:6px 12px 4px;font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);font-family:'DM Mono',monospace;background:#fafaf8;">${title}</div>`;
    items.forEach(item => {
      h += `<a href="${item.url||'#'}" style="display:flex;align-items:flex-start;gap:10px;padding:9px 12px;color:var(--ink);text-decoration:none;transition:background .15s;font-family:'DM Sans',sans-serif;"
              onmouseover="this.style.background='var(--mint)'" onmouseout="this.style.background=''">`
         + `<div style="width:18px;height:18px;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:11px;margin-top:1px;"><i class="fas fa-${icon}"></i></div>`
         + `<div style="min-width:0;"><div style="font-size:.82rem;font-weight:500;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.title}</div>${item.subtitle?`<div style="font-size:.72rem;color:var(--muted);">${item.subtitle}</div>`:''}</div>`
         + `</a>`;
    });
    return h;
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
        fetch('search_universal.php?q=' + encodeURIComponent(q), { signal: sAC.signal })
          .then(r => r.ok ? r.json() : Promise.reject())
          .then(data => {
            if (!data) return;
            const html = ['people','events','jobs','pages'].map(k => renderSearchSection(k.charAt(0).toUpperCase()+k.slice(1), data[k], k)).join('');
            sr.innerHTML = html || `<div style="padding:16px;text-align:center;font-size:.82rem;color:var(--muted);">No results found</div>`;
            sr.classList.remove('hidden');
          }).catch(()=>{});
      }, 200);
    });
    si.addEventListener('focus', () => { if(sr.innerHTML) sr.classList.remove('hidden'); });
    document.addEventListener('click', e => { if(!sr.contains(e.target)&&!si.contains(e.target)) clearSearch(); });
    if (sc) sc.addEventListener('click', () => { si.value=''; sc.classList.add('hidden'); clearSearch(); si.focus(); });
  }

  /* ---- NOTIFICATION + MESSAGE DROPDOWNS ---- */
  if (!window._hDropdownInit) {
    window._hDropdownInit = true;
    document.addEventListener('click', function(e) {
      const bell = document.getElementById('notificationBell');
      const nd   = document.getElementById('notificationDropdown');
      const msgB = document.getElementById('messagesButton');
      const md   = document.getElementById('messagesDropdown');

      if (bell && nd && (bell===e.target||bell.contains(e.target))) {
        e.stopPropagation();
        const collapsed = nd.classList.contains('is-collapsed');
        nd.classList.toggle('is-collapsed', !collapsed);
        if (md) md.classList.add('is-collapsed');
        if (!window._notifRead) {
          window._notifRead = true;
          fetch('al_mark_notifications_read.php',{method:'POST'}).catch(()=>{});
        }
        return;
      }
      if (msgB && md && (msgB===e.target||msgB.contains(e.target))) {
        e.stopPropagation();
        const collapsed = md.classList.contains('is-collapsed');
        md.classList.toggle('is-collapsed', !collapsed);
        if (nd) nd.classList.add('is-collapsed');
        return;
      }
      if (nd && !nd.contains(e.target)) nd.classList.add('is-collapsed');
      if (md && !md.contains(e.target)) md.classList.add('is-collapsed');
    }, true);
  }

  /* ---- MARK ALL READ ---- */
  (function(){
    function attach() {
      const btn = document.getElementById('markAllRead');
      if (!btn) return;
      btn.addEventListener('click', function() {
        window._notifRead = true;
        fetch('al_mark_notifications_read.php',{method:'POST'}).catch(()=>{});
        const badge = document.querySelector('#notificationBell .h-badge');
        if (badge) badge.remove();
        document.querySelectorAll('#notificationDropdown .h-panel-item.unread').forEach(el => el.classList.remove('unread'));
      });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', attach);
    else attach();
  })();

  /* ---- LOGOUT MODAL ---- */
  window.showLogoutModal = function() {
    const m = document.getElementById('logoutModal');
    if (m) { m.classList.remove('hidden'); m.style.display='flex'; document.body.style.overflow='hidden'; }
  };
  window.hideLogoutModal = function() {
    const m = document.getElementById('logoutModal');
    if (m) { m.classList.add('hidden'); m.style.display='none'; document.body.style.overflow=''; }
  };
  document.addEventListener('DOMContentLoaded', function() {
    const m = document.getElementById('logoutModal');
    const c = document.getElementById('cancelLogout');
    if (c) c.addEventListener('click', hideLogoutModal);
    if (m) m.addEventListener('click', e=>{ if(e.target===m) hideLogoutModal(); });
    document.addEventListener('keydown', e=>{ if(e.key==='Escape') hideLogoutModal(); });
  });

  /* ---- HANDLENAVCLIK (global) ---- */
  window.handleNavClick = window.handleNavClick || function(url) {
    if (url === 'al_about.php') { window.location.href = url; return; }
    const loggedIn = <?= $user_id ? 'true' : 'false' ?>;
    window.location.href = loggedIn ? url : 'al_login.php?redirect=' + encodeURIComponent(url);
  };

<?php if ($user_id): ?>
  /* ---- FLOATING PROFILE MODAL ---- */
  (function(){
    const btn   = document.getElementById('floatingProfileBtn');
    const modal = document.getElementById('floatingProfileModal');
    const close = document.getElementById('closeProfileModal');
    const tabs  = document.querySelectorAll('.fp-tab');

    function openModal() {
      // Close chatbot if open
      const cm = document.getElementById('chatbotModal');
      if (cm && !cm.classList.contains('hidden')) closeElement(cm);
      modal.classList.remove('hidden'); modal.style.display='flex';
      setTimeout(()=>{ modal.style.transform='scale(1)'; modal.style.opacity='1'; },10);
      // Reset to profile tab
      activateTab('profile');
    }
    function closeElement(el) {
      el.style.transform='scale(0.92)'; el.style.opacity='0';
      setTimeout(()=>{ el.classList.add('hidden'); el.style.display='none'; },220);
    }
    function closeModal() { closeElement(modal); }

    if (btn)   btn.addEventListener('click', e=>{ e.stopPropagation(); modal.classList.contains('hidden') ? openModal() : closeModal(); });
    if (close) close.addEventListener('click', e=>{ e.stopPropagation(); closeModal(); });
    document.addEventListener('keydown', e=>{ if(e.key==='Escape'&&!modal.classList.contains('hidden')) closeModal(); });

    function activateTab(name) {
      tabs.forEach(t => {
        const active = t.getAttribute('data-tab') === name;
        t.classList.toggle('active', active);
      });
      ['profileTabContent','careerTabContent','settingsTabContent'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('hidden', id !== name+'TabContent');
      });
    }
    tabs.forEach(t => t.addEventListener('click', ()=> activateTab(t.getAttribute('data-tab'))));
  })();

  /* ---- CHATBOT ---- */
  (function(){
    const btn   = document.getElementById('chatbotBtn');
    const modal = document.getElementById('chatbotModal');
    const close = document.getElementById('closeChatbotModal');
    const input = document.getElementById('chatbotInput');
    const send  = document.getElementById('chatbotSendBtn');
    const msgs  = document.getElementById('chatbotMessages');

    const faqData = [
      {question:"What is the Alumni Management System?",answer:"The Alumni Management System is a platform that allows former students to register, connect, and engage with the institution and fellow alumni.",keywords:["alumni","management","system","about","platform"],followUps:["How do I register?","What features are available?"],more:"It's your digital campus hub for updates, events, profiles, and networking."},
      {question:"How do I create an alumni account?",answer:"Click Register, fill in your details, and submit. An admin will verify and approve your registration.",keywords:["register","create","account","sign","up"],followUps:["How long does approval take?","What details are required?"],more:"Approval typically takes 1–3 business days. You'll receive a notification email once approved."},
      {question:"I forgot my password — how do I reset it?",answer:"Click Forgot Password and follow the steps to reset via email.",keywords:["forgot","password","reset","login"],followUps:["What if I don't have access to my email?"],more:"Check your spam folder too. For old email issues, contact alumni.ccs@olfu.edu.ph with proof of identity."},
      {question:"What features are available for registered alumni?",answer:"Update your profile, search alumni, view jobs, join events, share projects, and more.",keywords:["features","available","after","login","benefits"],followUps:["How do I search for other alumni?","How do I post a job?"],more:"You can also access career resources, success stories, and raise support tickets."},
      {question:"How do I search for other alumni?",answer:"Use the Directory to search by name, year, program, or company.",keywords:["search","alumni","directory","find","people"],followUps:["Can I message someone I found?"],more:"You can send internal messages if their privacy settings allow it."},
      {question:"What is the job portal?",answer:"The job portal helps alumni find and share job opportunities.",keywords:["job","portal","career","opportunities","employment"],followUps:["How do I post a job?","How do I apply?"],more:"You can browse curated listings or submit your own openings for admin review."},
      {question:"Is my personal information secure?",answer:"Yes, your data is encrypted and securely stored.",keywords:["secure","privacy","data","information","safety"],followUps:["Can I control what others see?"],more:"Review your privacy options under Settings > Privacy and Security."},
      {question:"Who do I contact for support?",answer:"Use the Contact page or email alumni.ccs@olfu.edu.ph.",keywords:["contact","support","help","assistance"],followUps:["What are the support hours?"],more:"Support is available weekdays 9 AM–5 PM (GMT+8). Email is fastest."},
      {question:"How do I update my profile?",answer:"Click your profile icon at the bottom right, then 'Edit Profile', or go to the Profile Update page.",keywords:["update","profile","edit","information"],followUps:["Can I update my photo?"],more:"Remember to save changes. Upload a new profile photo under Personal Information."},
      {question:"How do I change my password?",answer:"Go to Settings > Privacy and Security to change your password.",keywords:["change","password","update","credentials"],followUps:["What are the password requirements?"],more:"Use at least 8 characters including numbers and symbols for a strong password."}
    ];

    const stopWords = new Set(['the','a','an','is','are','do','does','i','my','how','what','who','where','when','why','can','will','you','me','to','for','on','in','of','it','about','please','tell']);
    const followUpTriggers = new Set(['more','details','explain','elaborate','tell','steps','info','guide','help']);
    let lastTopic = null;

    function tokenize(text) {
      return text.toLowerCase().replace(/[^a-z0-9\s]/g,' ').split(/\s+/).filter(t=>t&&!stopWords.has(t));
    }
    function score(tokens, faq) {
      let s=0;
      const qt = new Set(tokenize(faq.question));
      tokens.forEach(t => {
        if (faq.keywords.includes(t)) s+=4;
        else if (qt.has(t)) s+=2.5;
        else faq.keywords.forEach(k=>{ if(k.startsWith(t)||t.startsWith(k)) s+=0.7; });
      });
      if (s>0) s += Math.min(tokens.length,3)*0.4;
      return s;
    }
    function getResponse(raw) {
      const tokens = tokenize(raw);
      // Follow-up?
      if (lastTopic && tokens.length<=3 && tokens.some(t=>followUpTriggers.has(t)))
        return { answer: lastTopic.more||lastTopic.answer, suggestions: lastTopic.followUps||[] };
      const scored = faqData.map(f=>({f,s:score(tokens,f)})).sort((a,b)=>b.s-a.s);
      const best = scored[0];
      if (best && best.s>=3.5) { lastTopic=best.f; return { answer:best.f.answer, suggestions:best.f.followUps||[] }; }
      if (best && best.s>=1.5) { lastTopic=best.f; return { answer:best.f.answer+' Feel free to ask for more detail!', suggestions:best.f.followUps||[] }; }
      return { answer:"I didn't find an exact match. Try asking about registration, password reset, or career features.", suggestions:["How do I register?","Who do I contact for support?","What is the job portal?"] };
    }
    function esc(t) { const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }
    function addMsg(text, isBot=false, suggestions=[]) {
      const wrap = document.createElement('div');
      wrap.className = 'cb-bubble-wrap' + (isBot?'':' user');
      const iconEl = document.createElement('div');
      iconEl.className = 'cb-bubble-icon ' + (isBot?'bot':'user');
      iconEl.innerHTML = `<i class="fas fa-${isBot?'robot':'user'}"></i>`;
      const bubble = document.createElement('div');
      bubble.className = 'cb-bubble ' + (isBot?'bot':'user');
      bubble.innerHTML = esc(text);
      if (isBot && suggestions.length) {
        const sd = document.createElement('div');
        sd.className = 'cb-suggestions';
        suggestions.forEach(s => {
          const b = document.createElement('button');
          b.className='cb-suggestion-btn'; b.textContent=s;
          b.addEventListener('click',()=>cbProcess(s));
          sd.appendChild(b);
        });
        bubble.appendChild(sd);
      }
      wrap.appendChild(iconEl); wrap.appendChild(bubble);
      msgs.appendChild(wrap);
      msgs.scrollTop = msgs.scrollHeight;
    }
    function cbProcess(raw) {
      const txt = raw.trim(); if (!txt) return;
      addMsg(txt, false);
      if (input) input.value='';
      setTimeout(()=>{ const r=getResponse(txt); addMsg(r.answer,true,r.suggestions); },350);
    }
    window.cbSuggest = cbProcess;
    window.findFAQAnswer = function(q){ return getResponse(String(q||'')).answer; };

    function openCB() {
      const pm = document.getElementById('floatingProfileModal');
      if (pm&&!pm.classList.contains('hidden')){ pm.style.transform='scale(0.92)';pm.style.opacity='0';setTimeout(()=>{pm.classList.add('hidden');pm.style.display='none';},220); }
      modal.classList.remove('hidden'); modal.style.display='flex';
      setTimeout(()=>{ modal.style.transform='scale(1)'; modal.style.opacity='1'; },10);
    }
    function closeCB() { modal.style.transform='scale(0.92)';modal.style.opacity='0'; setTimeout(()=>{modal.classList.add('hidden');modal.style.display='none';},220); }

    if (btn) btn.addEventListener('click', e=>{e.stopPropagation(); modal.classList.contains('hidden')?openCB():closeCB();});
    if (close) close.addEventListener('click',e=>{e.stopPropagation();closeCB();});
    if (send) send.addEventListener('click',()=>cbProcess(input?input.value:''));
    if (input) input.addEventListener('keypress',e=>{if(e.key==='Enter')cbProcess(input.value);});
    document.addEventListener('click',e=>{
      if(modal&&!modal.classList.contains('hidden')&&!btn.contains(e.target)&&!modal.contains(e.target)) closeCB();
    });
  })();

  /* ---- GO UP BUTTON ---- */
  (function(){
    const btn = document.getElementById('goUpBtn');
    if (!btn) return;
    function toggle() {
      if (window.scrollY > 220) { btn.classList.remove('hidden'); btn.style.display='flex'; }
      else { btn.classList.add('hidden'); btn.style.display='none'; }
    }
    window.addEventListener('scroll', toggle, {passive:true});
    toggle();
    btn.addEventListener('click', ()=>window.scrollTo({top:0,behavior:'smooth'}));
  })();

  /* ---- WIDGETS TOGGLE ---- */
  (function(){
    const tgl  = document.getElementById('floatingWidgetsToggle');
    const pm   = document.getElementById('floatingProfileMenu');
    const cb   = document.getElementById('chatbotContainer');
    let hidden = false;
    if (!tgl) return;
    tgl.addEventListener('click', function() {
      hidden = !hidden;
      [pm,cb].forEach(el=>{ if(!el) return; el.classList.toggle('floating-hidden',hidden); });
      tgl.classList.toggle('collapsed',hidden);
      const icon = tgl.querySelector('i');
      if (icon) { icon.classList.toggle('fa-chevron-right',!hidden); icon.classList.toggle('fa-chevron-left',hidden); }
      if (hidden) {
        const fpm = document.getElementById('floatingProfileModal');
        const cbm = document.getElementById('chatbotModal');
        if (fpm&&!fpm.classList.contains('hidden')){ fpm.style.transform='scale(0.92)';fpm.style.opacity='0';setTimeout(()=>{fpm.classList.add('hidden');fpm.style.display='none';},220); }
        if (cbm&&!cbm.classList.contains('hidden')){ cbm.style.transform='scale(0.92)';cbm.style.opacity='0';setTimeout(()=>{cbm.classList.add('hidden');cbm.style.display='none';},220); }
      }
    });
  })();
<?php endif; ?>

})();
</script>
