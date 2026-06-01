<?php
/**
 * Shared alumni ID card visual (front + back). Used by ad_alumni_id_check.php and ad_viewprofile.php.
 * Deploy fallback: if public_html/ad_alumni_id_cards_snippet.php is missing on Linux, this copy under includes/ is loaded instead.
 *
 * @param array $card Keys: photoSrc, idInitials, fullName, cardFormatted, program, batchYear, validUntil, address, contact, emergency, signatureSrc (optional web URL to PNG/JPG signature)
 */
if (!function_exists('render_alumni_id_cards')) {
    /**
     * Output front/back ID card HTML. Kept untyped for PHP 7.x / mixed hosting; invalid UTF-8
     * from MySQL must not throw (htmlspecialchars + ENT_SUBSTITUTE).
     */
    function render_alumni_id_cards($card)
    {
        $card = is_array($card) ? $card : [];
        $photoSrc = (string) ($card['photoSrc'] ?? '');
        $idInitials = (string) ($card['idInitials'] ?? '?');
        $fullName = (string) ($card['fullName'] ?? '');
        $cardFormatted = (string) ($card['cardFormatted'] ?? '');
        $program = (string) ($card['program'] ?? '—');
        $batchYear = (string) ($card['batchYear'] ?? '—');
        $validUntil = (string) ($card['validUntil'] ?? '—');
        $address = (string) ($card['address'] ?? '—');
        $contact = (string) ($card['contact'] ?? '—');
        $emergency = (string) ($card['emergency'] ?? '—');
        $signatureSrc = (string) ($card['signatureSrc'] ?? '');

        $e = static function ($s) {
            return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        ?>
<style>
    .cards-wrapper { display: flex; flex-direction: column; gap: 20px; }
    .card-label {
      font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em;
      color: #888; margin-bottom: 8px;
    }
    .id-card {
      width: 340px; height: 214px; border-radius: 10px; overflow: hidden; position: relative;
      font-family: Arial, Helvetica, sans-serif; flex-shrink: 0;
    }
    .card-front { background: #0d3d22; }
    .cf-base { position: absolute; inset: 0; background: #0d3d22; }
    .cf-swoosh {
      position: absolute; top: -60px; right: 55px; width: 200px; height: 320px;
      background: linear-gradient(135deg, transparent 0%, rgba(40, 120, 70, 0) 20%, rgba(40, 130, 75, 0.85) 38%, rgba(55, 155, 90, 1) 50%, rgba(40, 130, 75, 0.85) 62%, rgba(40, 120, 70, 0) 80%, transparent 100%);
      transform: rotate(-18deg);
    }
    .cf-swoosh2 {
      position: absolute; top: 0; right: 28px; width: 55px; height: 214px;
      background: linear-gradient(135deg, transparent 0%, rgba(100, 200, 130, 0.18) 50%, transparent 100%);
      transform: rotate(-18deg);
    }
    .cf-gold-stripe {
      position: absolute; bottom: 0; left: 0; right: 0; height: 4px;
      background: linear-gradient(90deg, #a07010, #FFD700, #DAA520, #FFD700, #a07010); z-index: 10;
    }
    .cf-front-cluster {
      position: absolute;
      z-index: 5;
      top: 14px;
      bottom: 34px;
      left: 12px;
      right: 128px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 9px;
    }
    .cf-photo {
      width: 86px; height: 86px; flex-shrink: 0; overflow: hidden;
      background: #1a4a2a; display: flex; align-items: center; justify-content: center;
    }
    .cf-photo img {
      width: 100%; height: 100%;
      object-fit: cover;
      object-position: center center;
    }
    .cf-photo-placeholder {
      font-size: 13px; font-weight: 700; color: rgba(255,255,255,.5); text-align: center;
      line-height: 1.2; padding: 6px; letter-spacing: 0.02em;
    }
    .cf-title { position: absolute; top: 14px; right: 14px; text-align: right; z-index: 5; max-width: 118px; }
    .cf-olfu { font-size: 14px; font-weight: 400; color: #fff; letter-spacing: 2.5px; font-family: Arial, sans-serif; line-height: 1.1; }
    .cf-alumni { font-size: 34px; font-weight: 400; color: #fff; line-height: 0.88; font-family: Georgia, 'Times New Roman', serif; text-align: right; }
    .cf-card-word { font-size: 28px; font-weight: 400; color: #fff; display: block; text-align: right; font-family: Georgia, 'Times New Roman', serif; line-height: 1.05; }
    .cf-info {
      width: 100%;
      text-align: center;
      z-index: 5;
    }
    .cf-name { font-size: 12px; font-weight: 600; color: #fff; letter-spacing: .3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cf-cardno { font-size: 10px; color: rgba(255,255,255,.88); letter-spacing: 1.5px; margin-top: 4px; }
    .cf-program { font-size: 11px; color: #fff; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cf-batch { font-size: 10px; color: #fff; margin-top: 5px; }
    .cf-validity { position: absolute; bottom: 22px; right: 14px; text-align: right; z-index: 5; }
    .cf-valid-label { font-size: 8px; color: rgba(255,255,255,.65); }
    .cf-valid-value { font-size: 9px; color: #fff; font-weight: 600; margin-top: 1px; }
    .card-back {
      background: #ffffff;
      border: 1px solid #c8c8c8;
      display: flex;
      flex-direction: column;
      height: 214px;
      overflow: hidden;
      box-sizing: border-box;
    }
    .cb-header {
      display: flex;
      flex-direction: row;
      flex-wrap: wrap;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 5px 10px 4px;
      flex-shrink: 0;
      box-sizing: border-box;
    }
    .cb-olfu-logo {
      width: 30px;
      height: 30px;
      flex-shrink: 0;
      object-fit: contain;
      object-position: center;
    }
    .cb-univ-name {
      font-size: 11px;
      font-weight: 700;
      color: #4a9d6a;
      letter-spacing: .2px;
      line-height: 1.15;
      font-family: 'Times New Roman', Times, Georgia, serif;
      text-align: center;
      flex: 0 1 auto;
      max-width: calc(100% - 40px);
    }
    .cb-field-row { padding: 3px 10px; flex-shrink: 0; }
    .cb-field-box { border: 1px solid #c8c8c8; padding: 2px 6px; background: #ececec; }
    .cb-field-inline-lbl { font-size: 8px; color: #444; }
    .cb-field-inline-val { font-size: 10px; font-weight: 700; color: #111; margin-left: 4px; }
    .cb-address-row { padding: 2px 10px; }
    .cb-address-row .cb-field-box { padding: 1px 5px; }
    .cb-address-row .cb-field-inline-lbl { font-size: 7px; }
    .cb-address-row .cb-field-inline-val {
      font-size: 7.5px;
      font-weight: 700;
      line-height: 1.2;
    }
    .cb-contact-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      padding: 0 10px;
      gap: 0;
      margin-bottom: 2px;
      flex-shrink: 0;
    }
    .cb-contact-box {
      border: 1px solid #c8c8c8;
      padding: 2px 5px;
      background: #ececec;
      display: flex;
      flex-direction: row;
      align-items: center;
      justify-content: space-between;
      gap: 4px;
      min-height: 16px;
    }
    .cb-contact-box:first-child { border-right: none; }
    .cb-contact-lbl {
      font-size: 7.5px;
      color: #444;
      line-height: 1.1;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .cb-contact-val {
      font-size: 10px;
      font-weight: 700;
      color: #111;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      min-width: 0;
      text-align: right;
    }
    .cb-sig-area { padding: 4px 10px 0; margin-bottom: 4px; flex-shrink: 0; }
    .cb-sig-box {
      border: 1px solid #c8c8c8;
      min-height: 32px;
      height: 32px;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      padding: 4px 6px 3px;
      box-sizing: border-box;
      background: #ececec;
    }
    .cb-sig-box.cb-sig-has-img {
      align-items: center;
      padding: 2px 6px;
    }
    .cb-sig-img {
      max-height: 26px;
      max-width: 100%;
      object-fit: contain;
      display: block;
    }
    .cb-sig-label { font-size: 7px; color: #555; text-align: center; line-height: 1; }
    .cb-back-foot {
      flex: 1 1 auto;
      min-height: 0;
      padding: 3px 9px 5px;
      text-align: center;
      font-family: Arial, Helvetica, sans-serif;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
    }
    .cb-back-foot p {
      margin: 0;
      font-size: 5.35px;
      color: #333;
      line-height: 1.38;
      letter-spacing: 0.01em;
      overflow-wrap: break-word;
    }
    .cb-back-foot p + p { margin-top: 3px; }
    .cb-back-foot strong { font-weight: 700; }
</style>
<div class="alumni-id-cards-preview">
  <div class="cards-wrapper">
    <div>
      <div class="card-label">Front</div>
      <div class="id-card card-front">
        <div class="cf-base"></div>
        <div class="cf-swoosh"></div>
        <div class="cf-swoosh2"></div>
        <div class="cf-gold-stripe"></div>
        <div class="cf-front-cluster">
          <div class="cf-photo">
            <?php if ($photoSrc !== ''): ?>
              <img src="<?= $e($photoSrc) ?>" alt="" />
            <?php else: ?>
              <div class="cf-photo-placeholder"><?= $e($idInitials) ?></div>
            <?php endif; ?>
          </div>
          <div class="cf-info">
            <div class="cf-name"><?= $e($fullName) ?></div>
            <div class="cf-cardno"><?= $e($cardFormatted) ?></div>
            <div class="cf-program"><?= $e($program) ?></div>
            <div class="cf-batch">Batch <span><?= $e($batchYear) ?></span></div>
          </div>
        </div>
        <div class="cf-title">
          <div class="cf-olfu">OLFU</div>
          <div class="cf-alumni">Alumni<br /><span class="cf-card-word">Card</span></div>
        </div>
        <div class="cf-validity">
          <div class="cf-valid-label">Valid until</div>
          <div class="cf-valid-value"><?= $e($validUntil) ?></div>
        </div>
      </div>
    </div>
    <div>
      <div class="card-label">Back</div>
      <div class="id-card card-back">
        <div class="cb-header">
          <img
            src="olfulogo.png"
            alt="Our Lady of Fatima University"
            class="cb-olfu-logo"
            width="30"
            height="30"
            decoding="async"
          />
          <div class="cb-univ-name">OUR LADY OF FATIMA UNIVERSITY</div>
        </div>
        <div class="cb-field-row cb-address-row">
          <div class="cb-field-box">
            <span class="cb-field-inline-lbl">Address:</span>
            <span class="cb-field-inline-val"><?= $e($address) ?></span>
          </div>
        </div>
        <div class="cb-contact-row">
          <div class="cb-contact-box">
            <span class="cb-contact-lbl">Contact No.:</span>
            <span class="cb-contact-val"><?= $e($contact) ?></span>
          </div>
          <div class="cb-contact-box">
            <span class="cb-contact-lbl">Emergency No.:</span>
            <span class="cb-contact-val"><?= $e($emergency) ?></span>
          </div>
        </div>
        <div class="cb-sig-area">
          <div class="cb-sig-box<?= $signatureSrc !== '' ? ' cb-sig-has-img' : '' ?>" id="olfu-alumni-sig-box">
            <?php if ($signatureSrc !== '') : ?>
              <img src="<?= $e($signatureSrc) ?>" alt="" class="cb-sig-img" id="olfu-alumni-sig-img" decoding="async" />
            <?php else : ?>
              <span class="cb-sig-label" id="olfu-alumni-sig-placeholder">Card Holder's Signature</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="cb-back-foot">
          <p>
            By using this card, CARDHOLDER signifies that he/she has read the terms and
            condition of membership and agrees to be bound by them. To enjoy the full benefits
            of membership please present this card when purchasing or availing of privileges at
            partner establishments. It is non-transferable and any tampering will invalidate this card.
          </p>
          <p>
            If found, please return to the <strong>ALUMNI AFFAIRS OFFICE</strong><br />
            Ground flr. Saint John the Baptist Hall, Km 23 Sumulong Highway Sta. Cruz, Antipolo City.
          </p>
          <p>
            FOR ALUMNI ASSISTANCE PLEASE EMAIL AT <strong>alumniaffairs@fatima.edu.ph</strong>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
    }
}
