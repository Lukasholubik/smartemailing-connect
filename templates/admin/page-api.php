<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** @var SMEC_Settings $settings */
$cfg        = $settings->get_api();
$masked_key = ! empty( $cfg['api_key'] ) ? str_repeat( '*', 8 ) . substr( $cfg['api_key'], -4 ) : '';
?>
<div class="wrap smec-wrap">
<h1>API připojení</h1>

<!-- ── Návod ─────────────────────────────────────────── -->
<div class="smec-card smec-help-card">
  <h2>🔑 Kde najdu přihlašovací údaje?</h2>
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
        <strong>Přejděte do Nastavení → Přístupy a API</strong><br>
        V levém menu klikněte na ikonu ozubeného kola → <em>Přístupy a API</em> → záložka <em>API</em>.
      </div>
    </div>
    <div class="smec-help-step">
      <span class="smec-step-num">3</span>
      <div>
        <strong>Zkopírujte API token</strong><br>
        Zkopírujte zobrazený API klíč (token). Jako uživatelské jméno použijte svůj přihlašovací e-mail.
      </div>
    </div>
  </div>
  <p class="smec-help-links">
    <a href="https://app.smartemailing.cz" target="_blank" rel="noopener" class="button button-secondary">→ Otevřít SmartEmailing</a>
    &nbsp;
    <a href="https://app.smartemailing.cz/docs/api/v3/index.html" target="_blank" rel="noopener">API v3 dokumentace ↗</a>
    &nbsp;
    <a href="https://help.smartemailing.cz" target="_blank" rel="noopener">Centrum nápovědy SmartEmailing ↗</a>
  </p>
</div>

<!-- ── Formulář ───────────────────────────────────────── -->
<div class="smec-card">
  <h2>Přihlašovací údaje SmartEmailing</h2>

  <table class="form-table smec-form-table">
    <tr>
      <th><label for="smec-api-enabled">Aktivovat API</label></th>
      <td>
        <label class="smec-toggle">
          <input type="checkbox" id="smec-api-enabled" name="enabled" value="1" <?php checked( ! empty( $cfg['enabled'] ) ); ?>>
          <span class="smec-toggle-slider"></span>
        </label>
        <p class="description">Vypnutím dočasně zastavíte odesílání kontaktů do SmartEmailingu (webtracking zůstane nedotčen).</p>
      </td>
    </tr>
    <tr>
      <th><label for="smec-username">Uživatelské jméno (e-mail)</label></th>
      <td>
        <input type="email" id="smec-username" name="username" value="<?php echo esc_attr( $cfg['username'] ); ?>" class="regular-text" placeholder="vas@email.cz">
        <p class="description">Váš přihlašovací e-mail do SmartEmailingu.</p>
      </td>
    </tr>
    <tr>
      <th><label for="smec-api-key">API klíč (token)</label></th>
      <td>
        <div class="smec-password-wrap">
          <input type="password" id="smec-api-key" name="api_key" value="<?php echo esc_attr( $masked_key ); ?>" class="regular-text" placeholder="Zadejte API klíč" autocomplete="new-password">
          <button type="button" class="button smec-toggle-pw" data-target="smec-api-key">Zobrazit</button>
        </div>
        <p class="description">
          Klíč je uložen a zobrazuje se maskovaný. Pro změnu zadejte nový klíč.<br>
          Kde ho najdu: SmartEmailing → <em>Nastavení → Přístupy a API → API</em>.
        </p>
      </td>
    </tr>
    <tr>
      <th><label for="smec-base-url">Base URL API</label></th>
      <td>
        <input type="url" id="smec-base-url" name="base_url" value="<?php echo esc_attr( $cfg['base_url'] ); ?>" class="regular-text">
        <p class="description">
          Výchozí hodnota je správná pro většinu účtů: <code>https://app.smartemailing.cz/api/v3/</code><br>
          Měňte jen pokud vám SmartEmailing přidělil jinou URL (např. enterprise).
        </p>
      </td>
    </tr>
  </table>

  <div class="smec-actions">
    <button type="button" id="smec-save-api" class="button button-primary">Uložit nastavení</button>
    <button type="button" id="smec-test-api" class="button">Otestovat připojení</button>
    <span id="smec-api-result" class="smec-inline-result"></span>
  </div>
</div>

<div class="smec-card" id="smec-api-test-detail" style="display:none;">
  <h3>Výsledek testu</h3>
  <div id="smec-api-test-content"></div>
  <p class="smec-muted" style="margin-top:8px;">
    Pokud test selhává, zkontrolujte: správný e-mail, platný API klíč, aktivní účet SmartEmailing.<br>
    Více informací: <a href="https://app.smartemailing.cz/docs/api/v3/index.html" target="_blank" rel="noopener">API v3 dokumentace ↗</a>
  </p>
</div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.smec-toggle-pw').forEach(function(btn){
    btn.addEventListener('click', function(){
      var inp = document.getElementById(this.dataset.target);
      if (!inp) return;
      inp.type = inp.type === 'password' ? 'text' : 'password';
      this.textContent = inp.type === 'password' ? 'Zobrazit' : 'Skrýt';
    });
  });
});
</script>
