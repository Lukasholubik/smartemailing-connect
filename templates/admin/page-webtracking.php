<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** @var SMEC_Settings $settings */
$cfg   = $settings->get_webtracking();
$roles = wp_roles()->get_names();
$pages = get_pages( [ 'sort_column' => 'post_title', 'number' => 200 ] );
?>
<div class="wrap smec-wrap">
<h1>Webtracking</h1>

<!-- ── Návod ─────────────────────────────────────────── -->
<div class="smec-card smec-help-card">
  <h2>📡 Kde najdu GUID webtrackingu?</h2>
  <div class="smec-help-steps">
    <div class="smec-help-step">
      <span class="smec-step-num">1</span>
      <div>
        <strong>Přihlaste se do SmartEmailingu</strong><br>
        <a href="https://app.smartemailing.cz" target="_blank" rel="noopener">app.smartemailing.cz</a>
      </div>
    </div>
    <div class="smec-help-step">
      <span class="smec-step-num">2</span>
      <div>
        <strong>Přejděte do Nastavení → Zdroje dat → Webtracking</strong><br>
        V levém menu ikona ozubeného kola → <em>Zdroje dat</em> → záložka <em>Webtracking</em>.
      </div>
    </div>
    <div class="smec-help-step">
      <span class="smec-step-num">3</span>
      <div>
        <strong>Zkopírujte GUID ze skriptu</strong><br>
        Ve vygenerovaném kódu najdete řetězec ve formátu <code>xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</code>. Zkopírujte pouze tuto hodnotu (bez uvozovek), ne celý skript.
      </div>
    </div>
  </div>
  <p class="smec-help-note">
    <strong>Co webtracking dělá:</strong> Sleduje návštěvy identifikovaných kontaktů (ti kteří klikli na odkaz v e-mailu, vyplnili formulář nebo se přihlásili). Anonymní návštěvníky nesleduje. Vyžaduje aktivní cookies v prohlížeči.
  </p>
  <p class="smec-help-links">
    <a href="https://app.smartemailing.cz" target="_blank" rel="noopener" class="button button-secondary">→ Otevřít SmartEmailing</a>
    &nbsp;
    <a href="https://help.smartemailing.cz/article/1484-webtracking" target="_blank" rel="noopener">Dokumentace webtrackingu ↗</a>
    &nbsp;
    <a href="https://help.smartemailing.cz" target="_blank" rel="noopener">Centrum nápovědy ↗</a>
  </p>
</div>

<!-- ── Formulář ───────────────────────────────────────── -->
<div class="smec-card">
  <h2>Nastavení webtrackingu SmartEmailing</h2>

  <table class="form-table smec-form-table">
    <tr>
      <th><label for="wt-enabled">Aktivovat webtracking</label></th>
      <td>
        <label class="smec-toggle">
          <input type="checkbox" id="wt-enabled" name="enabled" value="1" <?php checked( ! empty( $cfg['enabled'] ) ); ?>>
          <span class="smec-toggle-slider"></span>
        </label>
        <p class="description">Tracking skript se vloží na všechny frontendové stránky webu (dle níže nastavených výjimek).</p>
      </td>
    </tr>
    <tr>
      <th><label for="wt-guid">GUID / Webtracking identifikátor</label></th>
      <td>
        <input type="text" id="wt-guid" name="guid" value="<?php echo esc_attr( $cfg['guid'] ); ?>" class="regular-text" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
        <p class="description">
          Jedinečný identifikátor vašeho účtu pro webtracking.<br>
          Kde ho najdu: SmartEmailing → <em>Nastavení → Zdroje dat → Webtracking</em>.
        </p>
      </td>
    </tr>
    <tr>
      <th><label>Umístění skriptu</label></th>
      <td>
        <label><input type="radio" name="position" value="footer" <?php checked( ( $cfg['position'] ?? 'footer' ), 'footer' ); ?>> Patička (doporučeno)</label><br>
        <label><input type="radio" name="position" value="head"   <?php checked( ( $cfg['position'] ?? 'footer' ), 'head'   ); ?>> Hlavička</label>
        <p class="description">Vložení do patičky je výchozí a doporučené – neblokuje načítání stránky.</p>
      </td>
    </tr>
    <tr>
      <th><label for="wt-exclude-admins">Vyloučit administrátory</label></th>
      <td>
        <label class="smec-toggle">
          <input type="checkbox" id="wt-exclude-admins" name="exclude_admins" value="1" <?php checked( ! empty( $cfg['exclude_admins'] ) ); ?>>
          <span class="smec-toggle-slider"></span>
        </label>
        <p class="description">Tracking se nebude vkládat přihlášeným administrátorům. Doporučujeme zapnout – zamezí zkreslení dat.</p>
      </td>
    </tr>
    <tr>
      <th><label>Vyloučit role</label></th>
      <td>
        <?php foreach ( $roles as $slug => $label ) : ?>
          <label style="display:inline-block;margin-right:12px;">
            <input type="checkbox" name="exclude_roles[]" value="<?php echo esc_attr( $slug ); ?>"
              <?php checked( in_array( $slug, (array)($cfg['exclude_roles']??[]), true ) ); ?>>
            <?php echo esc_html( translate_user_role( $label ) ); ?>
          </label>
        <?php endforeach; ?>
        <p class="description">Tracking se nevloží přihlášeným uživatelům s vybranými rolemi.</p>
      </td>
    </tr>
    <tr>
      <th><label>Vyloučit stránky</label></th>
      <td>
        <select name="excluded_pages[]" multiple class="smec-multiselect" size="5">
          <?php foreach ( $pages as $page ) : ?>
            <option value="<?php echo (int) $page->ID; ?>" <?php selected( in_array( $page->ID, (array)($cfg['excluded_pages']??[]), true ) ); ?>>
              <?php echo esc_html( $page->post_title ); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="description">Ctrl+klik pro výběr více stránek. Tracking se na vybraných stránkách nevloží.</p>
      </td>
    </tr>
    <tr>
      <th><label for="wt-custom-code">Vlastní tracking kód (volitelné)</label></th>
      <td>
        <textarea id="wt-custom-code" name="custom_code" rows="5" class="large-text code"><?php echo esc_textarea( $cfg['custom_code'] ?? '' ); ?></textarea>
        <p class="description">
          Bude vložen ihned za standardní SmartEmailing tracking skript. Slouží např. pro rozšíření sledování událostí.<br>
          Více informací: <a href="https://help.smartemailing.cz/article/1484-webtracking" target="_blank" rel="noopener">dokumentace webtrackingu ↗</a>
        </p>
      </td>
    </tr>
  </table>

  <div class="smec-actions">
    <button type="button" id="smec-save-webtracking" class="button button-primary">Uložit nastavení</button>
    <button type="button" id="smec-test-webtracking" class="button button-secondary">Otestovat webtracking</button>
    <span id="smec-wt-result" class="smec-inline-result"></span>
  </div>
</div>

<!-- ── Test výsledek ──────────────────────────────────────── -->
<div id="smec-wt-test-result" class="smec-card" style="display:none;">
  <h3 id="smec-wt-test-title"></h3>
  <ul id="smec-wt-test-checks" class="smec-check-list"></ul>
  <ul id="smec-wt-test-hints" class="smec-hint-list" style="display:none;"></ul>
</div>

<!-- ── Ověření ────────────────────────────────────────── -->
<div class="smec-card">
  <h3>Jak ověřit, že webtracking funguje?</h3>
  <ol class="smec-help-ol">
    <li>Odhlaste se z WordPressu (nebo použijte anonymní okno prohlížeče).</li>
    <li>Navštivte libovolnou stránku webu.</li>
    <li>Otevřete vývojářské nástroje prohlížeče (F12) → záložka <em>Console</em>.</li>
    <li>Správně fungující tracking zobrazí zprávu:<br>
      <code style="background:#f6f7f7;padding:2px 6px;border-radius:3px;">SE tracking initialized with guid</code> <span style="color:#007017;">zeleně</span>
    </li>
    <li>Po návštěvě stránky kontaktu se tracking zaznamená do SmartEmailingu do cca 5 minut.</li>
  </ol>
  <p class="smec-muted">Webtracking funguje pouze u <strong>identifikovaných kontaktů</strong> – tj. těch, kteří klikli na odkaz v e-mailu, vyplnili formulář nebo se přihlásili. Anonymní návštěvy se nezaznamenávají.</p>
</div>

</div>
