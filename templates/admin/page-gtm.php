<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** @var SMEC_Settings $settings */
$cfg   = $settings->get_gtm();
$roles = wp_roles()->get_names();
?>
<div class="wrap smec-wrap">
<h1>Google Tag Manager</h1>

<!-- ── Návod ─────────────────────────────────────────── -->
<div class="smec-card smec-help-card smec-help-compact">
  <p>
    <strong>Google Tag Manager</strong> umožňuje spravovat marketingové tagy (Google Analytics, Facebook Pixel, …)
    na jednom místě bez zásahu do kódu webu. Stačí vložit Container ID nebo celý GTM snippet z vašeho GTM účtu.
  </p>
  <div class="smec-help-steps">
    <div class="smec-help-step">
      <span class="smec-step-num">1</span>
      <div>
        <strong>Přihlaste se do Google Tag Manager</strong><br>
        <a href="https://tagmanager.google.com" target="_blank" rel="noopener">tagmanager.google.com</a>
      </div>
    </div>
    <div class="smec-help-step">
      <span class="smec-step-num">2</span>
      <div>
        <strong>Vyberte kontejner a zkopírujte instalační kód</strong><br>
        Administrace → váš kontejner → vpravo nahoře ikona <em>&lt;&gt;</em> → zkopírujte celý kód nebo jen ID (formát <code>GTM-XXXXXXX</code>).
      </div>
    </div>
    <div class="smec-help-step">
      <span class="smec-step-num">3</span>
      <div>
        <strong>Vložte kód nebo Container ID níže a uložte</strong><br>
        Plugin automaticky extrahuje Container ID z celého snippetu a vloží GTM správně do <code>&lt;head&gt;</code> i <code>&lt;body&gt;</code>.
      </div>
    </div>
  </div>
</div>

<!-- ── Formulář ───────────────────────────────────────── -->
<div class="smec-card">
  <h2>Nastavení GTM</h2>

  <table class="form-table smec-form-table">
    <tr>
      <th><label for="gtm-enabled">Aktivovat GTM</label></th>
      <td>
        <label class="smec-toggle">
          <input type="checkbox" id="gtm-enabled" name="enabled" value="1" <?php checked( ! empty( $cfg['enabled'] ) ); ?>>
          <span class="smec-toggle-slider"></span>
        </label>
      </td>
    </tr>
    <tr>
      <th><label for="gtm-container">Container ID nebo instalační kód</label></th>
      <td>
        <textarea id="gtm-container" name="container_id" rows="5" class="large-text code"
          placeholder="Vložte Container ID (GTM-XXXXXXX) nebo celý instalační snippet ze GTM účtu..."><?php
            echo esc_textarea( $cfg['container_id'] );
          ?></textarea>
        <p class="description">
          Aktuálně uložené Container ID:
          <strong id="gtm-current-id"><?php echo $cfg['container_id'] ? esc_html( $cfg['container_id'] ) : '—'; ?></strong>
          <br>
          Plugin automaticky extrahuje ID z celého kódu – stačí vložit cokoliv co jste dostali z GTM.
        </p>
      </td>
    </tr>
    <tr>
      <th><label for="gtm-exclude-admins">Vyloučit administrátory</label></th>
      <td>
        <label class="smec-toggle">
          <input type="checkbox" id="gtm-exclude-admins" name="exclude_admins" value="1" <?php checked( ! empty( $cfg['exclude_admins'] ) ); ?>>
          <span class="smec-toggle-slider"></span>
        </label>
        <p class="description">GTM se nebude načítat přihlášeným administrátorům – zamezí zkreslení dat.</p>
      </td>
    </tr>
    <tr>
      <th><label>Vyloučit role</label></th>
      <td>
        <?php foreach ( $roles as $slug => $label ) : ?>
          <label style="display:inline-block;margin-right:12px;">
            <input type="checkbox" name="exclude_roles[]" value="<?php echo esc_attr( $slug ); ?>"
              <?php checked( in_array( $slug, (array) ( $cfg['exclude_roles'] ?? [] ), true ) ); ?>>
            <?php echo esc_html( translate_user_role( $label ) ); ?>
          </label>
        <?php endforeach; ?>
        <p class="description">GTM se nevloží přihlášeným uživatelům s vybranými rolemi.</p>
      </td>
    </tr>
  </table>

  <div class="smec-actions">
    <button type="button" id="smec-save-gtm" class="button button-primary">Uložit nastavení</button>
    <button type="button" id="smec-test-gtm" class="button button-secondary">Otestovat GTM</button>
    <span id="smec-gtm-result" class="smec-inline-result"></span>
  </div>
</div>

<!-- ── Test výsledek ──────────────────────────────────────── -->
<div id="smec-gtm-test-result" class="smec-card" style="display:none;">
  <h3 id="smec-gtm-test-title"></h3>
  <ul id="smec-gtm-test-checks" class="smec-check-list"></ul>
  <ul id="smec-gtm-test-hints" class="smec-hint-list" style="display:none;"></ul>
</div>

<!-- ── Informace o vložení ────────────────────────────────── -->
<div class="smec-card">
  <h3>Jak je GTM vložen do webu?</h3>
  <table class="smec-guide-table">
    <tr>
      <td><strong>&lt;head&gt;</strong></td>
      <td>GTM JavaScript snippet – vložen jako první skript v hlavičce (<code>wp_head</code> priority 1).</td>
    </tr>
    <tr>
      <td><strong>&lt;body&gt;</strong></td>
      <td>GTM noscript iframe – vložen ihned za otevírací tag body (<code>wp_body_open</code>). Pokud téma tuto funkci nepodporuje, je vložen do patičky (<code>wp_footer</code>).</td>
    </tr>
  </table>
  <p class="smec-muted" style="margin-top:12px;">
    Po aktivaci ověřte funkčnost tlačítkem <em>Otestovat GTM</em> nebo v Google Tag Manager → Náhled.
  </p>
</div>

</div>
