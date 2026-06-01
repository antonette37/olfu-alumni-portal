<?php
session_start();
require_once __DIR__ . '/db_config.php';
alumni_otp_gate_after_session();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    $isLoggedIn = true;
    require_once 'db_config.php';
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT firstname, lastname, photo FROM itcp WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
    } else {
        $user_data = [];
    }
} else {
    $isLoggedIn = false;
    $user_data = [];
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1" name="viewport" />
  <title>CCS Alumni — Our Lady of Fatima University</title>
  <link rel="icon" href="olfulogo.png" type="image/png">
  <script>document.documentElement.classList.remove('no-js');</script>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

  <style>
    /* ===== DESIGN TOKENS ===== */
    :root {
      --forest:    #0a4a1e;
      --emerald:   #1a7a3a;
      --leaf:      #2ea855;
      --mint:      #d4f0dc;
      --cream:     #faf8f3;
      --warm-gray: #f2ede6;
      --ink:       #111916;
      --muted:     #5a6b61;
      --gold:      #c9a84c;
      --gold-lt:   #f0d98a;
      --white:     #ffffff;
      --radius-lg: 20px;
      --radius-xl: 32px;
      --shadow-sm: 0 2px 12px rgba(10,74,30,.08);
      --shadow-md: 0 8px 32px rgba(10,74,30,.12);
      --shadow-lg: 0 24px 64px rgba(10,74,30,.18);
    }

    /* ===== RESET & BASE ===== */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html {
      scroll-behavior: smooth;
      scroll-snap-type: y mandatory;
      font-size: 16px;
    }
    body {
      font-family: 'DM Sans', sans-serif;
      color: var(--ink);
      background: var(--cream);
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }
    h1, h2, h3, h4 {
      font-family: 'Playfair Display', Georgia, serif;
      line-height: 1.15;
      letter-spacing: -0.01em;
    }
    a { text-decoration: none; color: inherit; }
    img { display: block; max-width: 100%; }

    /* ===== SCROLL SNAP SECTIONS ===== */
    .slide-section {
      height: 100vh;
      width: 100%;
      position: relative;
      scroll-snap-align: start;
      scroll-snap-stop: always;
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* ===== HERO SECTION ===== */
    #hero {
      background: var(--forest);
    }

    .hero-video {
      position: absolute;
      inset: 0;
      width: 100%; height: 100%;
      object-fit: cover;
      opacity: 0.18;
      pointer-events: none;
    }

    /* Grain overlay */
    .hero-grain {
      position: absolute;
      inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.08'/%3E%3C/svg%3E");
      pointer-events: none;
      opacity: 0.4;
    }

    /* Diagonal gold accent */
    .hero-accent {
      position: absolute;
      right: -120px; top: -120px;
      width: 600px; height: 600px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(201,168,76,.18) 0%, transparent 70%);
      pointer-events: none;
    }
    .hero-accent-2 {
      position: absolute;
      left: -80px; bottom: -80px;
      width: 400px; height: 400px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(46,168,85,.12) 0%, transparent 70%);
      pointer-events: none;
    }

    .hero-inner {
      position: relative;
      z-index: 2;
      max-width: 1200px;
      width: 100%;
      margin: 0 auto;
      padding: 0 40px;
      padding-top: 80px; /* header offset */
    }

    .hero-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-family: 'DM Mono', monospace;
      font-size: 11px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--gold-lt);
      margin-bottom: 24px;
      opacity: 0;
      animation: fadeUp 0.7s 0.2s forwards;
    }
    .hero-eyebrow::before {
      content: '';
      display: block;
      width: 32px; height: 1px;
      background: var(--gold);
    }

    .hero-title {
      font-size: clamp(2.4rem, 5.5vw, 5rem);
      font-weight: 900;
      color: var(--white);
      line-height: 1.08;
      max-width: 820px;
      opacity: 0;
      animation: fadeUp 0.8s 0.35s forwards;
    }
    .hero-title em {
      font-style: normal;
      color: var(--gold-lt);
      position: relative;
    }
    .hero-title em::after {
      content: '';
      position: absolute;
      bottom: 6px; left: 0; right: 0;
      height: 3px;
      background: var(--gold);
      border-radius: 2px;
      opacity: 0.6;
    }

    .hero-body {
      font-size: 1.05rem;
      color: rgba(255,255,255,0.72);
      max-width: 540px;
      line-height: 1.75;
      margin-top: 24px;
      font-weight: 300;
      opacity: 0;
      animation: fadeUp 0.8s 0.5s forwards;
    }

    .hero-actions {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      margin-top: 40px;
      opacity: 0;
      animation: fadeUp 0.8s 0.65s forwards;
    }

    .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: var(--gold);
      color: var(--forest);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.875rem;
      font-weight: 600;
      letter-spacing: 0.02em;
      padding: 14px 28px;
      border-radius: 100px;
      border: none;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
      box-shadow: 0 4px 24px rgba(201,168,76,.35);
    }
    .btn-primary:hover {
      background: var(--gold-lt);
      transform: translateY(-2px);
      box-shadow: 0 8px 32px rgba(201,168,76,.45);
    }

    .btn-ghost {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: transparent;
      color: rgba(255,255,255,0.85);
      font-family: 'DM Sans', sans-serif;
      font-size: 0.875rem;
      font-weight: 500;
      padding: 13px 28px;
      border-radius: 100px;
      border: 1.5px solid rgba(255,255,255,0.28);
      cursor: pointer;
      transition: background 0.2s, border-color 0.2s, transform 0.2s;
    }
    .btn-ghost:hover {
      background: rgba(255,255,255,0.08);
      border-color: rgba(255,255,255,0.5);
      transform: translateY(-2px);
    }

    /* Hero carousel nav */
    .carousel-nav {
      position: absolute;
      bottom: 48px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      align-items: center;
      gap: 10px;
      z-index: 10;
      opacity: 0;
      animation: fadeUp 0.8s 0.8s forwards;
    }
    .carousel-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: rgba(255,255,255,0.35);
      border: none;
      cursor: pointer;
      transition: all 0.3s;
      padding: 0;
    }
    .carousel-dot.active {
      background: var(--gold);
      width: 28px;
      border-radius: 4px;
    }

    .carousel-arrows {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 100%;
      display: flex;
      justify-content: space-between;
      padding: 0 24px;
      z-index: 10;
      pointer-events: none;
    }
    .arrow-btn {
      pointer-events: all;
      width: 48px; height: 48px;
      border-radius: 50%;
      background: rgba(255,255,255,0.1);
      border: 1px solid rgba(255,255,255,0.2);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background 0.2s, transform 0.2s;
      backdrop-filter: blur(8px);
    }
    .arrow-btn:hover {
      background: rgba(255,255,255,0.2);
      transform: scale(1.08);
    }

    /* Scroll indicator */
    .scroll-hint {
      position: absolute;
      bottom: 48px;
      right: 48px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      z-index: 10;
      opacity: 0;
      animation: fadeUp 0.8s 1s forwards;
    }
    .scroll-hint span {
      font-family: 'DM Mono', monospace;
      font-size: 10px;
      letter-spacing: 0.15em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.4);
      writing-mode: vertical-lr;
    }
    .scroll-line {
      width: 1px;
      height: 48px;
      background: linear-gradient(to bottom, rgba(255,255,255,0.4), transparent);
      animation: scrollPulse 2s ease-in-out infinite;
    }
    @keyframes scrollPulse {
      0%,100% { opacity: 0.4; transform: scaleY(1); }
      50% { opacity: 1; transform: scaleY(1.2); }
    }

    /* Content fade transition */
    .hero-content-wrap {
      transition: opacity 0.4s ease, transform 0.4s ease;
    }
    .hero-content-wrap.fading {
      opacity: 0;
      transform: translateY(10px);
    }

    /* ===== ABOUT SECTION ===== */
    #about {
      background: var(--cream);
    }
    .about-inner {
      max-width: 1200px;
      width: 100%;
      margin: 0 auto;
      padding: 0 40px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 80px;
      align-items: center;
    }
    .section-tag {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-family: 'DM Mono', monospace;
      font-size: 11px;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--emerald);
      margin-bottom: 16px;
    }
    .section-tag::before {
      content: '';
      display: block;
      width: 24px; height: 2px;
      background: var(--leaf);
      border-radius: 2px;
    }
    .about-title {
      font-size: clamp(2rem, 3.5vw, 3rem);
      font-weight: 700;
      color: var(--forest);
      margin-bottom: 24px;
    }
    .about-body {
      font-size: 1rem;
      line-height: 1.8;
      color: var(--muted);
      margin-bottom: 36px;
    }
    .about-pillars {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .pillar {
      display: flex;
      align-items: flex-start;
      gap: 16px;
    }
    .pillar-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      background: var(--mint);
      color: var(--emerald);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      flex-shrink: 0;
    }
    .pillar-text strong {
      display: block;
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--forest);
      margin-bottom: 2px;
    }
    .pillar-text span {
      font-size: 0.825rem;
      color: var(--muted);
      line-height: 1.5;
    }

    /* About image side */
    .about-visual {
      position: relative;
    }
    .about-img-wrap {
      position: relative;
      border-radius: var(--radius-xl);
      overflow: hidden;
      box-shadow: var(--shadow-lg);
    }
    .about-img-wrap img {
      width: 100%;
      height: 520px;
      object-fit: cover;
      transition: transform 0.5s ease;
    }
    .about-img-wrap:hover img { transform: scale(1.03); }

    .about-badge {
      position: absolute;
      bottom: -24px;
      left: -24px;
      background: var(--white);
      border-radius: 16px;
      padding: 20px 24px;
      box-shadow: var(--shadow-md);
      display: flex;
      align-items: center;
      gap: 16px;
      z-index: 2;
    }
    .badge-num {
      font-family: 'Playfair Display', serif;
      font-size: 2.2rem;
      font-weight: 900;
      color: var(--forest);
      line-height: 1;
    }
    .badge-label {
      font-size: 0.75rem;
      color: var(--muted);
      line-height: 1.4;
      max-width: 80px;
    }
    .about-deco {
      position: absolute;
      top: -20px;
      right: -20px;
      width: 80px; height: 80px;
      background: var(--gold);
      border-radius: 16px;
      opacity: 0.25;
      rotate: 12deg;
    }
    .about-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--emerald);
      margin-top: 32px;
      transition: gap 0.2s;
    }
    .about-link:hover { gap: 14px; }

    /* ===== STATS SECTION ===== */
    #stats {
      background: var(--forest);
      position: relative;
    }
    .stats-bg-ring {
      position: absolute;
      border-radius: 50%;
      border: 1px solid rgba(255,255,255,0.06);
    }
    .stats-inner {
      position: relative;
      z-index: 2;
      max-width: 1200px;
      width: 100%;
      margin: 0 auto;
      padding: 0 40px;
      text-align: center;
    }
    .stats-label {
      display: inline-block;
      font-family: 'DM Mono', monospace;
      font-size: 11px;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--gold-lt);
      margin-bottom: 16px;
    }
    .stats-title {
      font-size: clamp(1.8rem, 3.5vw, 2.8rem);
      color: var(--white);
      font-weight: 700;
      margin-bottom: 12px;
    }
    .stats-sub {
      color: rgba(255,255,255,0.5);
      font-size: 1rem;
      max-width: 480px;
      margin: 0 auto 64px;
      line-height: 1.7;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
    }
    .stat-card {
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: var(--radius-lg);
      padding: 48px 32px;
      transition: background 0.3s, transform 0.3s;
      position: relative;
      overflow: hidden;
    }
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--gold), var(--leaf));
      opacity: 0;
      transition: opacity 0.3s;
    }
    .stat-card:hover {
      background: rgba(255,255,255,0.09);
      transform: translateY(-4px);
    }
    .stat-card:hover::before { opacity: 1; }
    .stat-icon {
      width: 48px; height: 48px;
      border-radius: 12px;
      background: rgba(201,168,76,.15);
      color: var(--gold-lt);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      margin: 0 auto 24px;
    }
    .stat-number {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2.8rem, 5vw, 4rem);
      font-weight: 900;
      color: var(--white);
      line-height: 1;
      margin-bottom: 8px;
    }
    .stat-number sup {
      font-size: 1.5rem;
      vertical-align: super;
    }
    .stat-name {
      font-size: 0.9rem;
      font-weight: 600;
      color: rgba(255,255,255,0.75);
      margin-bottom: 6px;
    }
    .stat-desc {
      font-size: 0.78rem;
      color: rgba(255,255,255,0.38);
      line-height: 1.5;
    }

    /* ===== GALLERY SECTION ===== */
    #gallery {
      background: var(--warm-gray);
    }
    .gallery-inner {
      max-width: 1200px;
      width: 100%;
      margin: 0 auto;
      padding: 0 40px;
    }
    .gallery-header {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      margin-bottom: 40px;
    }
    .gallery-title {
      font-size: clamp(1.8rem, 3vw, 2.6rem);
      color: var(--forest);
    }
    .gallery-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--emerald);
      transition: gap 0.2s;
    }
    .gallery-link:hover { gap: 14px; }

    /* Image mosaic */
    .gallery-mosaic {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      grid-template-rows: repeat(2, 180px);
      gap: 12px;
    }
    .mosaic-item {
      border-radius: 14px;
      overflow: hidden;
      position: relative;
      cursor: pointer;
    }
    .mosaic-item:first-child {
      grid-column: span 2;
      grid-row: span 2;
    }
    .mosaic-item img {
      width: 100%; height: 100%;
      object-fit: cover;
      transition: transform 0.5s ease, filter 0.3s;
    }
    .mosaic-item::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(to top, rgba(10,74,30,.55) 0%, transparent 50%);
      opacity: 0;
      transition: opacity 0.3s;
    }
    .mosaic-item:hover img { transform: scale(1.06); }
    .mosaic-item:hover::after { opacity: 1; }
    .mosaic-empty {
      background: var(--mint);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      color: var(--leaf);
      border-radius: 14px;
    }

    /* Scattered animation items (preserved from original) */
    .gallery-scattered {
      position: relative;
      height: 380px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .gallery-image-item {
      position: absolute;
      transition: all ease-out;
    }
    .gallery-card {
      overflow: hidden;
      border-radius: 12px;
      box-shadow: var(--shadow-md);
      border: 3px solid var(--white);
      width: 180px;
      cursor: pointer;
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .gallery-card:hover {
      transform: scale(1.1) !important;
      box-shadow: var(--shadow-lg);
      z-index: 30 !important;
    }
    .gallery-card img {
      width: 100%;
      height: 130px;
      object-fit: cover;
    }
    .gallery-center-label {
      position: absolute;
      z-index: 20;
      text-align: center;
      pointer-events: none;
    }

    /* ===== ALUMNI CARD SECTION ===== */
    #alumni-card {
      background: var(--cream);
    }
    .card-inner {
      max-width: 1200px;
      width: 100%;
      margin: 0 auto;
      padding: 0 40px;
    }
    .card-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 64px;
      align-items: center;
    }
    .card-content {}
    .card-benefits {
      display: flex;
      flex-direction: column;
      gap: 16px;
      margin: 28px 0 36px;
    }
    .benefit-row {
      display: flex;
      align-items: flex-start;
      gap: 14px;
    }
    .benefit-icon {
      width: 36px; height: 36px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--forest), var(--emerald));
      color: var(--gold-lt);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      flex-shrink: 0;
    }
    .benefit-text strong {
      display: block;
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--forest);
      margin-bottom: 2px;
    }
    .benefit-text span {
      font-size: 0.8rem;
      color: var(--muted);
      line-height: 1.5;
    }

    /* The card mockup */
    .card-visual {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .card-image-wrap {
      position: relative;
      border-radius: var(--radius-xl);
      overflow: hidden;
      box-shadow: var(--shadow-lg);
      transform: perspective(800px) rotateY(-4deg) rotateX(2deg);
      transition: transform 0.4s ease;
    }
    .card-image-wrap:hover {
      transform: perspective(800px) rotateY(0deg) rotateX(0deg);
    }
    .card-image-wrap img {
      width: 100%;
      max-width: 460px;
      display: block;
    }

    /* ===== FEATURES SECTION ===== */
    #features {
      background: var(--white);
    }
    .features-inner {
      max-width: 1200px;
      width: 100%;
      margin: 0 auto;
      padding: 0 40px;
    }
    .features-header {
      text-align: center;
      margin-bottom: 56px;
    }
    .features-title {
      font-size: clamp(1.8rem, 3.5vw, 2.8rem);
      color: var(--forest);
      margin-bottom: 16px;
    }
    .features-sub {
      color: var(--muted);
      font-size: 1rem;
      max-width: 520px;
      margin: 0 auto;
      line-height: 1.7;
    }
    .features-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
    }
    .feature-card {
      border: 1.5px solid #e8e3dc;
      border-radius: var(--radius-lg);
      padding: 32px 28px;
      position: relative;
      cursor: pointer;
      transition: border-color 0.3s, box-shadow 0.3s, transform 0.3s;
      background: var(--white);
      overflow: hidden;
    }
    .feature-card::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, var(--mint), transparent);
      opacity: 0;
      transition: opacity 0.3s;
    }
    .feature-card:hover {
      border-color: var(--leaf);
      box-shadow: var(--shadow-md);
      transform: translateY(-6px);
    }
    .feature-card:hover::before { opacity: 1; }
    .feat-icon-wrap {
      width: 48px; height: 48px;
      border-radius: 12px;
      background: var(--warm-gray);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--emerald);
      font-size: 18px;
      margin-bottom: 20px;
      position: relative;
      transition: background 0.3s, color 0.3s;
    }
    .feature-card:hover .feat-icon-wrap {
      background: var(--emerald);
      color: var(--white);
    }
    .feat-title {
      font-size: 1rem;
      font-weight: 700;
      color: var(--forest);
      margin-bottom: 10px;
      position: relative;
    }
    .feat-desc {
      font-size: 0.825rem;
      color: var(--muted);
      line-height: 1.65;
      position: relative;
    }
    .feat-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--emerald);
      margin-top: 20px;
      position: relative;
      transition: gap 0.2s;
    }
    .feature-card:hover .feat-link { gap: 10px; }

    /* ===== CTA SECTION ===== */
    #cta {
      background: var(--forest);
      position: relative;
    }
    .cta-bg-img {
      position: absolute;
      inset: 0;
      background-image: url('olfubg.jpg');
      background-size: cover;
      background-position: center;
      opacity: 0.12;
    }
    .cta-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, var(--forest) 0%, rgba(10,74,30,.85) 100%);
    }
    .cta-inner {
      position: relative;
      z-index: 2;
      max-width: 760px;
      margin: 0 auto;
      padding: 0 40px;
      text-align: center;
    }
    .cta-eyebrow {
      font-family: 'DM Mono', monospace;
      font-size: 11px;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--gold-lt);
      margin-bottom: 20px;
    }
    .cta-title {
      font-size: clamp(2.2rem, 5vw, 4rem);
      color: var(--white);
      margin-bottom: 20px;
      font-weight: 900;
    }
    .cta-body {
      color: rgba(255,255,255,0.6);
      font-size: 1.05rem;
      line-height: 1.75;
      margin-bottom: 48px;
    }
    .cta-actions {
      display: flex;
      gap: 16px;
      justify-content: center;
      flex-wrap: wrap;
    }

    /* Decorative rings */
    .cta-ring {
      position: absolute;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 50%;
      z-index: 1;
    }

    /* ===== FOOTER SECTION ===== */
    #footer-slide {
      background: #0d1f14;
      display: block;
      align-items: unset;
      justify-content: unset;
      height: 100vh;
      overflow-y: auto;
    }

    /* ===== LOGOUT MODAL ===== */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(10,74,30,.5);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      backdrop-filter: blur(4px);
    }
    .modal-overlay.active { display: flex; }
    .modal-box {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 40px;
      max-width: 400px;
      width: 90%;
      text-align: center;
      box-shadow: var(--shadow-lg);
    }
    .modal-icon {
      width: 56px; height: 56px;
      border-radius: 50%;
      background: #fff7e6;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      color: #d97706;
      font-size: 22px;
    }
    .modal-title {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--forest);
      margin-bottom: 10px;
    }
    .modal-body {
      font-size: 0.9rem;
      color: var(--muted);
      line-height: 1.6;
      margin-bottom: 28px;
    }
    .modal-actions {
      display: flex;
      gap: 12px;
      justify-content: center;
    }
    .modal-cancel {
      padding: 10px 24px;
      border-radius: 100px;
      border: 1.5px solid #ddd;
      background: transparent;
      color: var(--muted);
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.2s;
    }
    .modal-cancel:hover { background: #f5f5f5; }
    .modal-logout {
      padding: 10px 24px;
      border-radius: 100px;
      border: none;
      background: #dc2626;
      color: white;
      font-size: 0.875rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s;
    }
    .modal-logout:hover { background: #b91c1c; }

    <?php if (!$isLoggedIn): ?>
    /* ===== BACK TO TOP (guests only — logged-in users use header #goUpBtn) ===== */
    #backToTop {
      position: fixed;
      bottom: 32px;
      right: 32px;
      z-index: 200;
      width: 44px; height: 44px;
      border-radius: 50%;
      background: var(--forest);
      color: white;
      border: none;
      display: none;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: var(--shadow-md);
      transition: transform 0.2s, background 0.2s;
    }
    #backToTop:hover {
      background: var(--emerald);
      transform: translateY(-3px);
    }
    #backToTop.visible { display: flex; }
    <?php endif; ?>

    /* ===== ANIMATIONS ===== */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .reveal {
      opacity: 0;
      transform: translateY(32px);
      transition: opacity 0.7s ease, transform 0.7s ease;
    }
    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }
    .reveal-delay-1 { transition-delay: 0.1s; }
    .reveal-delay-2 { transition-delay: 0.2s; }
    .reveal-delay-3 { transition-delay: 0.3s; }
    .reveal-delay-4 { transition-delay: 0.4s; }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 900px) {
      .about-inner { grid-template-columns: 1fr; gap: 40px; }
      .about-visual { display: none; }
      .stats-grid { grid-template-columns: 1fr; gap: 16px; }
      .stat-card { padding: 32px 24px; }
      .card-grid { grid-template-columns: 1fr; gap: 40px; }
      .card-visual { display: none; }
      .features-grid { grid-template-columns: repeat(2, 1fr); }
      .hero-inner { padding: 0 24px; padding-top: 80px; }
      .carousel-arrows { padding: 0 8px; }
      .about-inner { padding: 0 24px; }
      .stats-inner, .gallery-inner, .card-inner, .features-inner, .cta-inner { padding: 0 24px; }
    }

    @media (max-width: 580px) {
      .features-grid { grid-template-columns: 1fr; }
      .gallery-mosaic { grid-template-columns: repeat(2,1fr); grid-template-rows: repeat(3, 140px); }
      .mosaic-item:first-child { grid-column: span 2; grid-row: span 1; }
      .hero-actions { flex-direction: column; }
    }
  </style>
</head>
<body>

  <!-- ====== HEADER (preserved from original) ====== -->
  <?php include __DIR__ . '/al_header_universal.php'; ?>

  <!-- ====== VERTICAL SLIDES CONTAINER ====== -->
  <div id="verticalSlidesContainer">

    <!-- ========================================
         SLIDE 1 · HERO
    ========================================= -->
    <section class="slide-section" id="hero" data-slide="0">
      <video class="hero-video" autoplay muted loop playsinline preload="metadata" poster="background_pic.jpg">
        <source src="alumni_vid.mp4" type="video/mp4" />
      </video>
      <div class="hero-grain"></div>
      <div class="hero-accent"></div>
      <div class="hero-accent-2"></div>

      <!-- Carousel arrows (full-width) -->
      <div class="carousel-arrows">
        <button class="arrow-btn" id="prevBtn" aria-label="Previous">
          <i class="fas fa-chevron-left"></i>
        </button>
        <button class="arrow-btn" id="nextBtn" aria-label="Next">
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>

      <div class="hero-inner">
        <div class="hero-content-wrap" id="heroContentWrap">
          <div class="hero-eyebrow" id="heroEyebrow">College of Computer Studies · OLFU Alumni</div>
          <h1 class="hero-title" id="heroTitle">
            Welcome Back, <em>Fatimanian.</em>
          </h1>
          <p class="hero-body" id="heroBody">
            A home for Global Fatimanians nurtured with integrity, excellence, and the Fatimanian spirit — rooted in the values of Veritas et Misericordia.
          </p>
          <div class="hero-actions">
            <button class="btn-primary" id="heroBtn">
              <span id="heroBtnText">Explore the Portal</span>
              <i class="fas fa-arrow-right"></i>
            </button>
            <a href="al_about.php" class="btn-ghost">
              <i class="fas fa-info-circle"></i>
              Learn More
            </a>
          </div>
        </div>
      </div>

      <!-- Dots -->
      <div class="carousel-nav" id="carouselNav"></div>

      <!-- Scroll hint -->
      <div class="scroll-hint">
        <div class="scroll-line"></div>
        <span>scroll</span>
      </div>
    </section>

    <!-- ========================================
         SLIDE 2 · ABOUT
    ========================================= -->
    <section class="slide-section" id="about" data-slide="1">
      <div class="about-inner">
        <!-- Left: text -->
        <div>
          <div class="section-tag reveal">About the System</div>
          <h2 class="about-title reveal reveal-delay-1">Your Gateway to the <em style="font-style:italic;color:var(--emerald);">Fatimanian Network</em></h2>
          <p class="about-body reveal reveal-delay-2">
            The Alumni Management System connects graduates of the College of Computer Studies with the university and with one another. Discover alumni events, explore career opportunities, and maintain a thriving professional network — all in one place.
          </p>
          <div class="about-pillars">
            <div class="pillar reveal reveal-delay-2">
              <div class="pillar-icon"><i class="fas fa-users"></i></div>
              <div class="pillar-text">
                <strong>Stay Connected</strong>
                <span>Find classmates, join groups, and grow your network across industries.</span>
              </div>
            </div>
            <div class="pillar reveal reveal-delay-3">
              <div class="pillar-icon"><i class="fas fa-calendar-alt"></i></div>
              <div class="pillar-text">
                <strong>Discover Events</strong>
                <span>Get updates on reunions, webinars, community outreach, and more.</span>
              </div>
            </div>
            <div class="pillar reveal reveal-delay-4">
              <div class="pillar-icon"><i class="fas fa-briefcase"></i></div>
              <div class="pillar-text">
                <strong>Advance Your Career</strong>
                <span>Browse opportunities and collaborate with industry partners.</span>
              </div>
            </div>
          </div>
          <a href="al_about.php" class="about-link reveal">
            Discover More <i class="fas fa-arrow-right"></i>
          </a>
        </div>

        <!-- Right: image -->
        <div class="about-visual reveal reveal-delay-1">
          <div class="about-deco"></div>
          <div class="about-img-wrap">
            <img src="pic1.jpeg" alt="OLFU Graduate" />
          </div>
          <div class="about-badge">
            <div class="badge-num">CCS</div>
            <div class="badge-label">College of Computer Studies · OLFU</div>
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================
         SLIDE 3 · STATISTICS
    ========================================= -->
    <section class="slide-section" id="stats" data-slide="2">
      <!-- Decorative rings -->
      <div class="stats-bg-ring" style="width:600px;height:600px;top:-200px;right:-200px;"></div>
      <div class="stats-bg-ring" style="width:400px;height:400px;bottom:-100px;left:-100px;"></div>

      <div class="stats-inner">
        <div class="stats-label reveal">Our Community</div>
        <h2 class="stats-title reveal reveal-delay-1">Alumni by the Numbers</h2>
        <p class="stats-sub reveal reveal-delay-2">
          Join thousands of graduates making a difference in their fields and communities.
        </p>

        <div class="stats-grid">
          <?php
          require_once 'db_config.php';
          $conn = getDBConnection();

          $alumni_count = 0;
          $stmt = $conn->prepare("SELECT COUNT(*) as count FROM itcp WHERE status = 'active'");
          if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); $alumni_count = $r['count'] ?? 0; $stmt->close(); }

          $events_count = 0;
          $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE YEAR(event_date) = YEAR(CURDATE())");
          if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); $events_count = $r['count'] ?? 0; $stmt->close(); }

          $jobs_count = 0;
          $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_listings WHERE status = 'active'");
          if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); $jobs_count = $r['count'] ?? 0; $stmt->close(); }

          $conn->close();
          ?>

          <div class="stat-card reveal reveal-delay-1">
            <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="stat-number"><?= number_format($alumni_count) ?><sup>+</sup></div>
            <div class="stat-name">Active Alumni</div>
            <div class="stat-desc">Graduates actively engaged in our community</div>
          </div>

          <div class="stat-card reveal reveal-delay-2">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-number"><?= number_format($events_count) ?><sup>+</sup></div>
            <div class="stat-name">Annual Events</div>
            <div class="stat-desc">Networking events, reunions, and workshops this year</div>
          </div>

          <div class="stat-card reveal reveal-delay-3">
            <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
            <div class="stat-number"><?= number_format($jobs_count) ?><sup>+</sup></div>
            <div class="stat-name">Career Opportunities</div>
            <div class="stat-desc">Job listings and career advancement opportunities</div>
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================
         SLIDE 4 · GALLERY
    ========================================= -->
    <section class="slide-section" id="gallery" data-slide="3">
      <div class="gallery-inner">
        <div class="gallery-header reveal">
          <div>
            <div class="section-tag">Visual Stories</div>
            <h2 class="gallery-title">Alumni Gallery</h2>
          </div>
          <a href="al_gallery.php" class="gallery-link">
            See all photos <i class="fas fa-arrow-right"></i>
          </a>
        </div>

        <?php
          $images = [];
          try {
            require_once 'db_config.php';
            $gallery_conn = getDBConnection();

            if ($gallery_conn && is_object($gallery_conn)) {
              // Detect existing columns so the homepage doesn't break if older schemas exist.
              $imgCols = [];
              $albumsCols = [];

              $colsResult = $gallery_conn->query("SHOW COLUMNS FROM gallery_images");
              if ($colsResult) {
                while ($c = $colsResult->fetch_assoc()) {
                  $imgCols[] = $c['Field'];
                }
              }

              $colsAlbumsResult = $gallery_conn->query("SHOW COLUMNS FROM gallery_albums");
              if ($colsAlbumsResult) {
                while ($c = $colsAlbumsResult->fetch_assoc()) {
                  $albumsCols[] = $c['Field'];
                }
              }

              $fileCol = null;
              if (in_array('file_path', $imgCols, true)) {
                $fileCol = 'file_path';
              } else if (in_array('file_name', $imgCols, true)) {
                // Fallback for older schema (serve_gallery_image.php expects a relative path or filename).
                $fileCol = 'file_name';
              }

              if ($fileCol !== null) {
                $where = [];
                if (in_array('status', $imgCols, true)) {
                  $where[] = "(gi.status IS NULL OR gi.status='active' OR gi.status='')";
                }
                if (in_array('status', $albumsCols, true)) {
                  $where[] = "(ga.status IS NULL OR ga.status='active' OR ga.status='')";
                }

                $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

                // Random selection to match the "from al_gallery" expectation.
                $sql = "SELECT gi.$fileCol AS file_ref
                        FROM gallery_images gi
                        INNER JOIN gallery_albums ga ON gi.album_id = ga.id
                        $whereSql
                        ORDER BY RAND()
                        LIMIT 20";

                $result = $gallery_conn->query($sql);
                if ($result === false) {
                  error_log('al_homepage gallery query failed: ' . $gallery_conn->error);
                }
                if ($result && $result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                    $fileRef = trim($row['file_ref'] ?? '');
                    if ($fileRef !== '') {
                      $images[] = 'serve_gallery_image.php?img=' . urlencode($fileRef);
                    }
                  }
                }
              }
            }
          } catch (Throwable $e) {
            error_log('al_homepage gallery error: ' . $e->getMessage());
            $images = [];
          }

          // Mosaic: need up to 7 images
          $mosaicImages = [];
          if (!empty($images)) {
            for ($i = 0; $i < 7; $i++) $mosaicImages[] = $images[$i % count($images)];
          }

          // Scattered animation: need 8 images
          $galleryImages = [];
          if (!empty($images)) {
            for ($i = 0; $i < 8; $i++) $galleryImages[] = $images[$i % count($images)];
          }
        ?>

        <?php if (!empty($mosaicImages)): ?>
        <!-- Mosaic grid -->
        <div class="gallery-mosaic reveal reveal-delay-1">
          <?php foreach ($mosaicImages as $idx => $imgPath): ?>
            <div class="mosaic-item <?php echo ($idx === 6) ? 'mosaic-empty' : ''; ?>">
              <?php if ($idx < 6): ?>
                <img src="<?= htmlspecialchars($imgPath) ?>" alt="Gallery <?= $idx+1 ?>" loading="lazy" onerror="this.parentElement.classList.add('mosaic-empty');this.remove();" />
              <?php else: ?>
                <a href="al_gallery.php" style="color:var(--leaf);font-size:.85rem;font-weight:600;display:flex;flex-direction:column;align-items:center;gap:8px;">
                  <i class="fas fa-images" style="font-size:1.8rem;"></i>
                  View More
                </a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php for ($i = count($mosaicImages); $i < 7; $i++): ?>
            <div class="mosaic-empty">
              <i class="fas fa-image" style="color:var(--leaf);font-size:1.5rem;"></i>
            </div>
          <?php endfor; ?>
        </div>

        <?php else: ?>
        <!-- Fallback scattered animation (from original) -->
        <div class="gallery-scattered" id="alumniGallerySection">
          <div class="gallery-center-label">
            <p style="font-size:1.1rem;color:var(--muted);font-weight:500;">No gallery images yet</p>
            <a href="al_gallery.php" style="color:var(--emerald);font-size:.875rem;font-weight:600;">Browse Gallery <i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- ========================================
         SLIDE 5 · ALUMNI CARD
    ========================================= -->
    <section class="slide-section" id="alumni-card" data-slide="4">
      <div class="card-inner">
        <div class="card-grid">
          <div class="card-content">
            <div class="section-tag reveal">Official ID</div>
            <h2 class="about-title reveal reveal-delay-1">Get Your Official <em style="font-style:italic;color:var(--emerald);">Alumni Card</em></h2>
            <p class="about-body reveal reveal-delay-2">
              Your official OLFU Alumni Card is more than just identification — it's your key to exclusive benefits, discounts, and access to alumni events. Show your Fatimanian pride wherever you go.
            </p>
            <div class="card-benefits">
              <div class="benefit-row reveal reveal-delay-2">
                <div class="benefit-icon"><i class="fas fa-id-card"></i></div>
                <div class="benefit-text">
                  <strong>Official Identification</strong>
                  <span>Recognized alumni identification for university events and facilities.</span>
                </div>
              </div>
              <div class="benefit-row reveal reveal-delay-3">
                <div class="benefit-icon"><i class="fas fa-percent"></i></div>
                <div class="benefit-text">
                  <strong>Exclusive Discounts</strong>
                  <span>Special rates at partner establishments and university services.</span>
                </div>
              </div>
              <div class="benefit-row reveal reveal-delay-4">
                <div class="benefit-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="benefit-text">
                  <strong>Event Access</strong>
                  <span>Priority access to alumni events, reunions, and networking opportunities.</span>
                </div>
              </div>
            </div>
            <?php if ($isLoggedIn): ?>
              <a href="alumni_card_details.php" class="btn-primary" style="display:inline-flex;background:var(--emerald);color:white;box-shadow:none;">
                Learn More <i class="fas fa-arrow-right"></i>
              </a>
            <?php else: ?>
              <a href="al_login.php" class="btn-primary" style="display:inline-flex;background:var(--emerald);color:white;box-shadow:none;">
                Learn More <i class="fas fa-arrow-right"></i>
              </a>
            <?php endif; ?>
          </div>

          <div class="card-visual reveal reveal-delay-2">
            <div class="card-image-wrap">
              <img src="alumnicard.jpg" alt="OLFU Alumni Card" />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================
         SLIDE 6 · FEATURES
    ========================================= -->
    <section class="slide-section" id="features" data-slide="5">
      <div class="features-inner">
        <div class="features-header">
          <div class="section-tag" style="justify-content:center;">What We Offer</div>
          <h2 class="features-title reveal">Everything You Need to Stay Connected</h2>
          <p class="features-sub reveal reveal-delay-1">Our comprehensive platform offers all the tools and resources to maintain meaningful connections with your alumni network.</p>
        </div>
        <div class="features-grid">
          <div class="feature-card reveal reveal-delay-1" onclick="handleNavClick('al_dashboard.php')">
            <div class="feat-icon-wrap"><i class="fas fa-users"></i></div>
            <div class="feat-title">Alumni Network</div>
            <p class="feat-desc">Connect with fellow graduates, share experiences, and expand your professional network globally.</p>
            <span class="feat-link">Go to Network <i class="fas fa-arrow-right"></i></span>
          </div>
          <div class="feature-card reveal reveal-delay-2" onclick="handleNavClick('al_events.php')">
            <div class="feat-icon-wrap"><i class="fas fa-calendar-alt"></i></div>
            <div class="feat-title">Upcoming Events</div>
            <p class="feat-desc">Stay updated on reunions, workshops, and networking opportunities happening near you.</p>
            <span class="feat-link">View Events <i class="fas fa-arrow-right"></i></span>
          </div>
          <div class="feature-card reveal reveal-delay-3" onclick="handleNavClick('al_career.php')">
            <div class="feat-icon-wrap"><i class="fas fa-book-open"></i></div>
            <div class="feat-title">Student Resources</div>
            <p class="feat-desc">Access resources to support your academic journey and professional career development.</p>
            <span class="feat-link">Explore Resources <i class="fas fa-arrow-right"></i></span>
          </div>
          <div class="feature-card reveal reveal-delay-4" onclick="handleNavClick('al_career.php')">
            <div class="feat-icon-wrap"><i class="fas fa-briefcase"></i></div>
            <div class="feat-title">Career Hub</div>
            <p class="feat-desc">Access exclusive job postings, mentorship programs, and career advancement resources.</p>
            <span class="feat-link">View Opportunities <i class="fas fa-arrow-right"></i></span>
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================
         SLIDE 7 · CTA
    ========================================= -->
    <section class="slide-section" id="cta" data-slide="6">
      <div class="cta-bg-img"></div>
      <div class="cta-overlay"></div>

      <!-- Decorative rings -->
      <div class="cta-ring" style="width:500px;height:500px;top:-150px;right:-150px;"></div>
      <div class="cta-ring" style="width:300px;height:300px;bottom:-80px;left:-80px;"></div>

      <div class="cta-inner">
        <div class="cta-eyebrow reveal">Join the Community</div>
        <h2 class="cta-title reveal reveal-delay-1">Ready to<br>Reconnect?</h2>
        <p class="cta-body reveal reveal-delay-2">
          Join thousands of alumni already part of our thriving community. Create your profile today and start making meaningful connections.
        </p>
        <div class="cta-actions reveal reveal-delay-3">
          <?php if ($isLoggedIn): ?>
            <a href="al_profile.php" class="btn-primary">
              <i class="fas fa-user"></i> View Your Profile
            </a>
          <?php else: ?>
            <a href="al_registration.php" class="btn-primary">
              <i class="fas fa-user-plus"></i> Create Your Profile
            </a>
          <?php endif; ?>
          <a href="al_contact.php" class="btn-ghost">
            <i class="fas fa-envelope"></i> Contact Us
          </a>
        </div>
      </div>
    </section>

    <!-- ========================================
         SLIDE 8 · FOOTER
    ========================================= -->
    <section class="slide-section" id="footer-slide" data-slide="7">
      <?php include 'al_footer_universal.php'; ?>
    </section>

  </div><!-- /verticalSlidesContainer -->

  <?php if (!$isLoggedIn): ?>
  <button id="backToTop" aria-label="Back to top"><i class="fas fa-arrow-up"></i></button>
  <?php endif; ?>

  <!-- Logout Modal -->
  <div class="modal-overlay" id="logoutModal">
    <div class="modal-box">
      <div class="modal-icon"><i class="fas fa-sign-out-alt"></i></div>
      <div class="modal-title">Confirm Logout</div>
      <p class="modal-body">Are you sure you want to log out? You will need to log in again to access your account.</p>
      <div class="modal-actions">
        <button class="modal-cancel" id="cancelLogout">Cancel</button>
        <form action="al_logout.php" method="POST" style="display:inline;">
          <button type="submit" class="modal-logout">Logout</button>
        </form>
      </div>
    </div>
  </div>

  <!-- ====== SCRIPTS ====== -->
  <script>
  (function() {
    'use strict';

    /* ---------- STATE ---------- */
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

    /* ---------- NAV HELPER ---------- */
    window.handleNavClick = function(url) {
      if (!isLoggedIn && url !== 'al_about.php') {
        window.location.href = 'al_login.php?redirect=' + encodeURIComponent(url);
      } else {
        window.location.href = url;
      }
    };

    /* ---------- LOGOUT MODAL ---------- */
    window.showLogoutModal = function() {
      document.getElementById('logoutModal').classList.add('active');
      document.body.style.overflow = 'hidden';
    };
    window.hideLogoutModal = function() {
      document.getElementById('logoutModal').classList.remove('active');
      document.body.style.overflow = '';
    };

    document.addEventListener('DOMContentLoaded', function() {
      const cancelBtn = document.getElementById('cancelLogout');
      const modalOverlay = document.getElementById('logoutModal');
      if (cancelBtn) cancelBtn.addEventListener('click', hideLogoutModal);
      if (modalOverlay) modalOverlay.addEventListener('click', e => { if (e.target === modalOverlay) hideLogoutModal(); });
      document.addEventListener('keydown', e => { if (e.key === 'Escape') hideLogoutModal(); });

      /* ---------- HERO CAROUSEL ---------- */
      const slides = [
        {
          eyebrow: 'College of Computer Studies · OLFU Alumni',
          title: 'Welcome Back, <em>Fatimanian.</em>',
          body: 'A home for Global Fatimanians nurtured with integrity, excellence, and the Fatimanian spirit — rooted in the values of Veritas et Misericordia.',
          btnText: 'Explore the Portal',
          btnUrl: 'gen_faqs.php',
        },
        {
          eyebrow: 'Stay Connected',
          title: 'Reconnect with the <em>CCS Family</em>',
          body: 'Update your contact info, attend reunions and networking events, and mentor the next generation of Fatimanians.',
          btnText: 'Join the Alumni Network',
          btnUrl: isLoggedIn ? 'al_dashboard.php' : 'al_registration.php',
        },
        {
          eyebrow: "Don't Miss Out",
          title: 'Upcoming Alumni <em>Events & Programs</em>',
          body: 'Be part of reunions, webinars, career fairs, and community outreach. Your presence makes every event more meaningful.',
          btnText: 'View All Events',
          btnUrl: 'al_events.php',
        },
      ];

      let currentSlide = 0;
      let autoTimer = null;
      const DURATION = 5000;

      const eyebrow = document.getElementById('heroEyebrow');
      const title   = document.getElementById('heroTitle');
      const body    = document.getElementById('heroBody');
      const btnText = document.getElementById('heroBtnText');
      const heroBtn = document.getElementById('heroBtn');
      const wrap    = document.getElementById('heroContentWrap');
      const nav     = document.getElementById('carouselNav');
      const prevBtn = document.getElementById('prevBtn');
      const nextBtn = document.getElementById('nextBtn');

      function buildDots() {
        nav.innerHTML = '';
        slides.forEach((_, i) => {
          const d = document.createElement('button');
          d.className = 'carousel-dot' + (i === currentSlide ? ' active' : '');
          d.setAttribute('aria-label', 'Slide ' + (i + 1));
          d.addEventListener('click', () => goTo(i));
          nav.appendChild(d);
        });
      }

      function applySlide(i) {
        const s = slides[i];
        eyebrow.textContent = s.eyebrow;
        title.innerHTML = s.title;
        body.textContent = s.body;
        btnText.textContent = s.btnText;
        heroBtn.onclick = () => window.location.href = s.btnUrl;
        buildDots();
      }

      function goTo(i) {
        wrap.classList.add('fading');
        setTimeout(() => {
          currentSlide = i;
          applySlide(i);
          wrap.classList.remove('fading');
        }, 400);
      }

      function next() { goTo((currentSlide + 1) % slides.length); }
      function prev() { goTo((currentSlide - 1 + slides.length) % slides.length); }

      function startAuto() {
        stopAuto();
        autoTimer = setInterval(next, DURATION);
      }
      function stopAuto() { if (autoTimer) { clearInterval(autoTimer); autoTimer = null; } }
      function resetAuto() { stopAuto(); startAuto(); }

      if (prevBtn) prevBtn.addEventListener('click', () => { prev(); resetAuto(); });
      if (nextBtn) nextBtn.addEventListener('click', () => { next(); resetAuto(); });

      applySlide(0);
      startAuto();

      /* ---------- INTERSECTION OBSERVER: REVEAL ---------- */
      const reveals = document.querySelectorAll('.reveal');
      const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
          } else {
            entry.target.classList.remove('visible');
          }
        });
      }, { threshold: 0.15 });
      reveals.forEach(el => revealObserver.observe(el));

      <?php if (!$isLoggedIn): ?>
      /* ---------- BACK TO TOP (guests) ---------- */
      const btt = document.getElementById('backToTop');
      if (btt) {
        window.addEventListener('scroll', () => {
          btt.classList.toggle('visible', window.scrollY > 300);
        }, { passive: true });
        btt.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
      }
      <?php endif; ?>

      /* ---------- SNAP SCROLL ENGINE ---------- */
      const sections = document.querySelectorAll('.slide-section');
      let currentIdx = 0;
      let isScrolling = false;

      function scrollToSlide(idx) {
        if (idx < 0 || idx >= sections.length || isScrolling) return;
        isScrolling = true;
        currentIdx = idx;
        sections[idx].scrollIntoView({ behavior: 'smooth' });
        setTimeout(() => { isScrolling = false; }, 900);
      }

      // Wheel
      let wheelTimer = null;
      window.addEventListener('wheel', e => {
        if (wheelTimer || isScrolling) return;
        wheelTimer = setTimeout(() => { wheelTimer = null; }, 900);
        if (e.deltaY > 40) { e.preventDefault(); scrollToSlide(currentIdx + 1); }
        else if (e.deltaY < -40) { e.preventDefault(); scrollToSlide(currentIdx - 1); }
      }, { passive: false });

      // Keyboard
      document.addEventListener('keydown', e => {
        if (['ArrowDown','PageDown'].includes(e.key)) { e.preventDefault(); scrollToSlide(currentIdx + 1); }
        if (['ArrowUp','PageUp'].includes(e.key))    { e.preventDefault(); scrollToSlide(currentIdx - 1); }
        if (e.key === 'Home') { e.preventDefault(); scrollToSlide(0); }
        if (e.key === 'End')  { e.preventDefault(); scrollToSlide(sections.length - 1); }
      });

      // Touch
      let touchStartY = 0;
      document.addEventListener('touchstart', e => { touchStartY = e.changedTouches[0].screenY; }, { passive: true });
      document.addEventListener('touchend', e => {
        const diff = touchStartY - e.changedTouches[0].screenY;
        if (Math.abs(diff) > 80) {
          if (diff > 0) scrollToSlide(currentIdx + 1);
          else scrollToSlide(currentIdx - 1);
        }
      }, { passive: true });

      // Update index on scroll
      window.addEventListener('scroll', () => {
        const idx = Math.round(window.scrollY / window.innerHeight);
        if (idx !== currentIdx && idx >= 0 && idx < sections.length) currentIdx = idx;
      }, { passive: true });

      /* ---------- UNIVERSAL SEARCH (preserved) ---------- */
      const searchInput   = document.getElementById('globalSearch');
      const searchResults = document.getElementById('globalSearchResults');
      const searchClear   = document.getElementById('globalSearchClear');
      let searchAC = null, searchDebouce = null;

      function clearSearch() {
        if (searchResults) { searchResults.classList.add('hidden'); searchResults.innerHTML = ''; }
      }
      if (searchInput && searchResults) {
        searchInput.addEventListener('input', () => {
          const q = searchInput.value.trim();
          if (searchClear) searchClear.classList.toggle('hidden', !q.length);
          if (searchDebouce) clearTimeout(searchDebouce);
          if (q.length < 2) { clearSearch(); return; }
          searchDebouce = setTimeout(() => {
            if (searchAC) searchAC.abort();
            searchAC = new AbortController();
            fetch('search_universal.php?q=' + encodeURIComponent(q), { signal: searchAC.signal })
              .then(r => r.ok ? r.json() : Promise.reject())
              .then(data => {
                if (!data) return;
                const sections = [
                  ['people','People','user'],['events','Events','calendar'],
                  ['jobs','Jobs','briefcase'],['pages','Pages','file-alt']
                ];
                let html = '';
                sections.forEach(([key, label, icon]) => {
                  if (!data[key] || !data[key].length) return;
                  html += `<div class="px-3 py-1.5 text-xs font-semibold text-gray-500 bg-gray-50 border-b">${label}</div>`;
                  data[key].forEach(item => {
                    html += `<a href="${item.url||'#'}" class="flex items-start gap-3 px-3 py-2 hover:bg-gray-50 transition">
                      <div class="w-6 h-6 flex items-center justify-center text-gray-500"><i class="fas fa-${icon}"></i></div>
                      <div class="min-w-0"><div class="text-sm text-gray-800 truncate">${item.title}</div>${item.subtitle?`<div class="text-xs text-gray-500">${item.subtitle}</div>`:''}</div>
                    </a>`;
                  });
                });
                searchResults.innerHTML = html || '<div class="px-3 py-3 text-sm text-gray-500">No results found</div>';
                searchResults.classList.remove('hidden');
              }).catch(() => {});
          }, 200);
        });
        searchInput.addEventListener('focus', () => { if (searchResults.innerHTML) searchResults.classList.remove('hidden'); });
        document.addEventListener('click', e => {
          if (!searchResults.contains(e.target) && !searchInput.contains(e.target)) clearSearch();
        });
        if (searchClear) searchClear.addEventListener('click', () => {
          searchInput.value = ''; searchClear.classList.add('hidden'); clearSearch(); searchInput.focus();
        });
      }

      /* ---------- MOBILE MENU (preserved) ---------- */
      const mobileMenuBtn   = document.getElementById('mobileMenuBtn');
      const closeMobileMenu = document.getElementById('closeMobileMenu');
      const mobileMenu      = document.getElementById('mobileMenu');

      function openMobileMenu() {
        if (!mobileMenu) return;
        mobileMenu.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        const content = mobileMenu.querySelector('div');
        if (content) content.style.transform = 'translateX(0)';
      }
      function closeMobileMenuFn() {
        if (!mobileMenu) return;
        const content = mobileMenu.querySelector('div');
        if (content) content.style.transform = 'translateX(-100%)';
        setTimeout(() => {
          mobileMenu.classList.add('hidden');
          document.body.style.overflow = '';
        }, 300);
      }

      if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', openMobileMenu);
      if (closeMobileMenu) closeMobileMenu.addEventListener('click', closeMobileMenuFn);
      if (mobileMenu) {
        mobileMenu.addEventListener('click', e => { if (e.target === mobileMenu) closeMobileMenuFn(); });

        // Swipe close
        let mTouchX = 0;
        mobileMenu.addEventListener('touchstart', e => { mTouchX = e.changedTouches[0].screenX; });
        mobileMenu.addEventListener('touchend', e => {
          if (e.changedTouches[0].screenX < mTouchX - 50) closeMobileMenuFn();
        });
      }

    }); // DOMContentLoaded
  })();
  </script>
</body>
</html>
