<?php /* Universal Alumni Footer — Enhanced */ ?>
<style>
/* ===== FOOTER DESIGN TOKENS ===== */
/* Relies on :root vars defined in header; fallbacks provided */
footer#alumniFooter {
  --f-bg:       #0c1f11;
  --f-surface:  #122419;
  --f-border:   rgba(255,255,255,0.07);
  --f-text:     rgba(255,255,255,0.82);
  --f-muted:    rgba(255,255,255,0.42);
  --f-accent:   #c9a84c;
  --f-leaf:     #2ea855;
  --f-white:    #ffffff;
  font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--f-bg);
  color: var(--f-text);
  position: relative;
  overflow: hidden;
}

/* Subtle top grain texture */
footer#alumniFooter::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
  pointer-events: none;
  z-index: 0;
}

/* Glowing orb */
footer#alumniFooter::after {
  content: '';
  position: absolute;
  top: -120px;
  right: -120px;
  width: 500px; height: 500px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(201,168,76,.07) 0%, transparent 65%);
  pointer-events: none;
  z-index: 0;
}

footer#alumniFooter .f-inner {
  position: relative;
  z-index: 1;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 40px;
}

/* ===== TOP GOLD SEPARATOR ===== */
.f-top-accent {
  height: 3px;
  background: linear-gradient(90deg, transparent 0%, var(--f-accent) 30%, #f0d98a 50%, var(--f-accent) 70%, transparent 100%);
  margin-bottom: 0;
}

/* ===== BRAND SECTION ===== */
.f-brand-row {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 60px;
  padding: 56px 0 48px;
  border-bottom: 1px solid var(--f-border);
  align-items: start;
}
.f-brand-logos {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
}
.f-brand-logos img { height: 44px; width: auto; filter: brightness(0) invert(1); opacity: 0.9; }
.f-university-name {
  font-family: 'Playfair Display', Georgia, serif;
  font-size: 1.45rem;
  font-weight: 700;
  color: var(--f-white);
  letter-spacing: -0.01em;
  margin-bottom: 4px;
  line-height: 1.2;
}
.f-campus-name {
  font-family: 'DM Mono', monospace;
  font-size: 0.7rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--f-accent);
  margin-bottom: 20px;
}
.f-brand-tagline {
  font-size: 0.875rem;
  color: var(--f-muted);
  line-height: 1.75;
  max-width: 380px;
  font-style: italic;
}

/* Contact card */
.f-contact-card {
  background: var(--f-surface);
  border: 1px solid var(--f-border);
  border-radius: 16px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.f-contact-group-title {
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--f-accent);
  font-family: 'DM Mono', monospace;
  margin-bottom: 8px;
}
.f-contact-line {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 0.825rem;
  color: var(--f-text);
  line-height: 1.5;
}
.f-contact-line i {
  width: 16px;
  text-align: center;
  color: var(--f-leaf);
  font-size: 12px;
  margin-top: 2px;
  flex-shrink: 0;
}
.f-contact-line a {
  color: var(--f-text);
  transition: color 0.2s;
  word-break: break-all;
}
.f-contact-line a:hover { color: var(--f-accent); }
.f-contact-divider {
  height: 1px;
  background: var(--f-border);
}

/* ===== LINKS GRID ===== */
.f-links-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 40px;
  padding: 48px 0;
  border-bottom: 1px solid var(--f-border);
}
.f-col-title {
  font-family: 'DM Mono', monospace;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--f-accent);
  margin-bottom: 18px;
}
.f-link-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 9px;
}
.f-link-list li a {
  font-size: 0.825rem;
  color: var(--f-muted);
  transition: color 0.2s, padding-left 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0;
}
.f-link-list li a:hover {
  color: var(--f-white);
  padding-left: 6px;
}

/* Social icons */
.f-social-row {
  display: flex;
  gap: 10px;
  margin-top: 4px;
}
.f-social-icon {
  width: 36px; height: 36px;
  border-radius: 9px;
  background: rgba(255,255,255,0.07);
  border: 1px solid var(--f-border);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--f-muted);
  font-size: 13px;
  transition: background 0.2s, color 0.2s, border-color 0.2s, transform 0.2s;
  text-decoration: none;
}
.f-social-icon:hover {
  background: var(--f-accent);
  color: #0a4a1e;
  border-color: var(--f-accent);
  transform: translateY(-3px);
}

/* ===== BOTTOM BAR ===== */
.f-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 0;
  flex-wrap: wrap;
}
.f-copyright {
  font-size: 0.78rem;
  color: var(--f-muted);
  font-family: 'DM Mono', monospace;
}
.f-copyright span { color: var(--f-accent); }
.f-motto {
  font-size: 0.78rem;
  color: var(--f-muted);
  font-style: italic;
  text-align: right;
}
.f-motto::before {
  content: '"';
  color: var(--f-accent);
  font-style: normal;
  font-family: 'Playfair Display', serif;
  font-size: 1.1rem;
  vertical-align: -2px;
  margin-right: 2px;
}
.f-motto::after {
  content: '"';
  color: var(--f-accent);
  font-style: normal;
  font-family: 'Playfair Display', serif;
  font-size: 1.1rem;
  vertical-align: -2px;
  margin-left: 2px;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 900px) {
  footer#alumniFooter .f-inner { padding: 0 24px; }
  .f-brand-row { grid-template-columns: 1fr; gap: 32px; padding: 40px 0 36px; }
  .f-links-grid { grid-template-columns: repeat(2, 1fr); gap: 32px; padding: 36px 0; }
}
@media (max-width: 580px) {
  .f-links-grid { grid-template-columns: 1fr; gap: 28px; }
  .f-bottom { flex-direction: column; text-align: center; gap: 8px; }
  .f-motto { text-align: center; }
}
</style>

<footer id="alumniFooter">
  <!-- Gold top accent -->
  <div class="f-top-accent"></div>

  <div class="f-inner">

    <!-- ===== BRAND + CONTACT ===== -->
    <div class="f-brand-row">
      <!-- Left: Branding -->
      <div>
        <div class="f-brand-logos">
          <img src="olfulogo.png" alt="OLFU Logo" />
        </div>
        <div class="f-university-name">Our Lady of Fatima University</div>
        <div class="f-campus-name">Antipolo Campus</div>
        <p class="f-brand-tagline">
          A home for Global Fatimanians nurtured with integrity, excellence, and the Fatimanian spirit — rooted in the values of <em>Veritas et Misericordia</em>.
        </p>
      </div>

      <!-- Right: Contact card -->
      <div class="f-contact-card">
        <div>
          <div class="f-contact-group-title">University Address</div>
          <div class="f-contact-line">
            <i class="fas fa-map-marker-alt"></i>
            <span>Km. 23 Sumulong Highway, Sta. Cruz, Antipolo City, 1870 Rizal</span>
          </div>
        </div>
        <div class="f-contact-divider"></div>
        <div>
          <div class="f-contact-group-title">Alumni Affairs Office</div>
          <div class="f-contact-line">
            <i class="fas fa-phone"></i>
            <span>(02) 661-3032</span>
          </div>
          <div class="f-contact-line" style="margin-top:6px;">
            <i class="fas fa-envelope"></i>
            <a href="mailto:alumniaffairs@fatima.edu.ph">alumniaffairs@fatima.edu.ph</a>
          </div>
        </div>
        <div class="f-contact-divider"></div>
        <div>
          <div class="f-contact-group-title">Technical Support</div>
          <div class="f-contact-line">
            <i class="fas fa-headset"></i>
            <a href="mailto:systems.support@olfulumni.ph">systems.support@olfulumni.ph</a>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== LINKS GRID ===== -->
    <div class="f-links-grid">

      <!-- Quick Links -->
      <div>
        <div class="f-col-title">Quick Links</div>
        <ul class="f-link-list">
          <li><a href="al_profileupdate.php">Update My Contact Info</a></li>
          <li><a href="alumni_card_details.php">Alumni Benefits &amp; Privileges</a></li>
          <li><a href="al_career.php">Job Board / Career Mentorship</a></li>
          <li><a href="al_events.php">University News &amp; Events</a></li>
        </ul>
      </div>

      <!-- Governance -->
      <div>
        <div class="f-col-title">Governance</div>
        <ul class="f-link-list">
          <li><a href="https://www.fatima.edu.ph" target="_blank" rel="noopener">OLFU Main Website</a></li>
          <li><a href="https://aims.fatima.edu.ph" target="_blank" rel="noopener">AIMS Portal</a></li>
          <li><a href="al_privacy_settings.php">Data Privacy Policy</a></li>
          <li><a href="gen_faqs.php">Terms of Use</a></li>
          <li><a href="al_contact.php">Contact Us</a></li>
        </ul>
      </div>

      <!-- Alumni Resources -->
      <div>
        <div class="f-col-title">Alumni Resources</div>
        <ul class="f-link-list">
          <li><a href="al_directory.php">Alumni Directory</a></li>
          <li><a href="al_gallery.php">Photo Gallery</a></li>
          <li><a href="al_dashboard.php">My Dashboard</a></li>
          <li><a href="gen_faqs.php">FAQs</a></li>
        </ul>
      </div>

      <!-- Connect -->
      <div>
        <div class="f-col-title">Connect With Us</div>
        <p style="font-size:.8rem;color:var(--f-muted);line-height:1.7;margin-bottom:18px;">
          Stay connected with your alma mater and fellow alumni through our social channels.
        </p>
        <div class="f-social-row">
          <a href="#" aria-label="Facebook" class="f-social-icon"><i class="fab fa-facebook-f"></i></a>
          <a href="#" aria-label="Twitter / X" class="f-social-icon"><i class="fab fa-x-twitter"></i></a>
          <a href="#" aria-label="LinkedIn" class="f-social-icon"><i class="fab fa-linkedin-in"></i></a>
          <a href="#" aria-label="Instagram" class="f-social-icon"><i class="fab fa-instagram"></i></a>
        </div>
      </div>

    </div>

    <!-- ===== BOTTOM BAR ===== -->
    <div class="f-bottom">
      <div class="f-copyright">
        © <span><?php echo date('Y'); ?></span> Our Lady of Fatima University · All Rights Reserved
      </div>
      <div class="f-motto">
        Producing competent and compassionate professionals.
      </div>
    </div>

  </div><!-- /f-inner -->
</footer>

