<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** @var SMEC_Settings $settings */
$mappings = $settings->get_form_mappings();
$lists    = $settings->get_cached_lists() ?? [];
$fields   = $settings->get_cached_customfields() ?? [];

$form_types = [
	'elementor'    => 'Elementor Pro Forms',
	'cf7'          => 'Contact Form 7',
	'fluent'       => 'Fluent Forms',
	'wpforms'      => 'WPForms',
	'gravity'      => 'Gravity Forms',
	'registration' => 'WP Registrace',
	'custom'       => 'Vlastní',
];

$placeholders = [
	'__FORM_NAME__', '__REFERRER__', '__CURRENT_URL__', '__PAGE_TITLE__',
	'__POST_ID__', '__POST_TITLE__', '__DATE_TIME__', '__DATE__', '__TIME__',
	'__SITE_URL__', '__SITE_NAME__', '__USER_ID__', '__USER_EMAIL__',
	'__UTM_SOURCE__', '__UTM_MEDIUM__', '__UTM_CAMPAIGN__', '__UTM_CONTENT__', '__UTM_TERM__',
];
?>
<div class="wrap smec-wrap">
<h1>Propojení formulářů
  <button type="button" id="smec-add-mapping" class="page-title-action">+ Přidat propojení</button>
</h1>

<!-- ── Návod ─────────────────────────────────────────── -->
<div class="smec-card smec-help-card smec-help-compact">
  <p>
    <strong>Jak napojit formulář:</strong> Klikněte na <em>+ Přidat propojení</em>, vyberte typ formuláře,
    zadejte jeho název nebo ID, vyberte cílový seznam v SmartEmailingu a namapujte pole.
    Poté uložte a otestujte tlačítkem <em>Odeslat testovací kontakt</em> na záložce Test.
  </p>
  <ul class="smec-help-ul">
    <li><strong>Elementor Pro Forms</strong> – jako ID použijte hodnotu pole <em>Form Name</em> v nastavení formuláře v Elementoru.</li>
    <li><strong>Contact Form 7</strong> – jako ID použijte číselné ID formuláře (vidíte ho v seznamu formulářů CF7).</li>
    <li><strong>Fluent Forms / WPForms</strong> – jako ID použijte číselné ID formuláře.</li>
    <li><strong>WP Registrace</strong> – jako ID zadejte <code>wp_registration</code>.</li>
    <li>Použijte <code>*</code> jako ID pro zachycení všech formulářů daného typu.</li>
  </ul>
  <p class="smec-help-links">
    <a href="https://app.smartemailing.cz/docs/api/v3/index.html" target="_blank" rel="noopener">API v3 dokumentace ↗</a>
    &nbsp;&nbsp;
    <a href="https://help.smartemailing.cz" target="_blank" rel="noopener">Centrum nápovědy SmartEmailing ↗</a>
    &nbsp;&nbsp;
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-lists' ) ); ?>">Načíst seznamy a vlastní pole →</a>
  </p>
</div>

<!-- ═══════════════════════════════════════════════
     LIST VIEW
═══════════════════════════════════════════════ -->
<div id="smec-mappings-list">
  <?php if ( empty( $mappings ) ) : ?>
    <div class="smec-empty-state smec-card">
      <p>Žádná propojení formulářů. Klikněte na <strong>+ Přidat propojení</strong>.</p>
    </div>
  <?php else : ?>
    <table class="wp-list-table widefat fixed striped smec-mappings-table">
      <thead>
        <tr>
          <th>Název</th><th>Typ formuláře</th><th>ID formuláře</th>
          <th>Seznam</th><th>Stav</th><th>Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $mappings as $m ) :
          $list_name = '—';
          foreach ( $lists as $l ) { if ( (string)($l['id']??'') === (string)($m['list_id']??'') ) { $list_name = $l['name'] ?? $l['publicname'] ?? '—'; break; } }
        ?>
          <tr data-id="<?php echo esc_attr( $m['id'] ); ?>">
            <td><?php echo esc_html( $m['name'] ?? '—' ); ?></td>
            <td><?php echo esc_html( $form_types[ $m['form_type'] ?? '' ] ?? ( $m['form_type'] ?? '—' ) ); ?></td>
            <td><code><?php echo esc_html( $m['form_id'] ?? '—' ); ?></code></td>
            <td><?php echo esc_html( $list_name ); ?></td>
            <td>
              <span class="smec-badge <?php echo ! empty( $m['enabled'] ) ? 'smec-badge-active' : 'smec-badge-inactive'; ?>">
                <?php echo ! empty( $m['enabled'] ) ? 'Aktivní' : 'Neaktivní'; ?>
              </span>
            </td>
            <td class="smec-row-actions">
              <button class="button-link smec-edit-mapping"   data-id="<?php echo esc_attr($m['id']); ?>">Upravit</button> |
              <button class="button-link smec-toggle-mapping" data-id="<?php echo esc_attr($m['id']); ?>"><?php echo ! empty($m['enabled']) ? 'Deaktivovat' : 'Aktivovat'; ?></button> |
              <button class="button-link smec-duplicate-mapping" data-id="<?php echo esc_attr($m['id']); ?>">Duplikovat</button> |
              <button class="button-link smec-delete-mapping  smec-danger" data-id="<?php echo esc_attr($m['id']); ?>">Smazat</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════
     EDITOR (hidden until add/edit)
═══════════════════════════════════════════════ -->
<div id="smec-mapping-editor" style="display:none;">
<div class="smec-card">
  <div class="smec-card-header">
    <h2 id="smec-editor-title">Nové propojení formuláře</h2>
    <button type="button" id="smec-cancel-edit" class="button">Zpět na seznam</button>
  </div>

  <input type="hidden" id="smec-mapping-id" value="">

  <div class="smec-tabs">
    <button type="button" class="smec-tab active" data-tab="basic">Základní</button>
    <button type="button" class="smec-tab" data-tab="fields">Systémová pole</button>
    <button type="button" class="smec-tab" data-tab="custom-fields">Vlastní pole</button>
    <button type="button" class="smec-tab" data-tab="tags">Štítky &amp; Podmínky</button>
    <button type="button" class="smec-tab" data-tab="test">Test</button>
  </div>

  <!-- TAB: Basic -->
  <div class="smec-tab-content active" id="smec-tab-basic">
    <table class="form-table smec-form-table">
      <tr><th><label for="m-name">Název propojení</label></th>
        <td><input type="text" id="m-name" class="regular-text" placeholder="Např. Newsletter signup"></td></tr>
      <tr><th><label for="m-form-type">Typ formuláře</label></th>
        <td>
          <select id="m-form-type">
            <?php foreach ( $form_types as $val => $label ) : ?>
              <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
          </select>
        </td></tr>
      <tr><th><label for="m-form-id">ID / Název formuláře</label></th>
        <td><input type="text" id="m-form-id" class="regular-text" placeholder="Např. newsletter-form nebo 12">
          <p class="description">Pro Elementor: název formuláře (Form Name). Pro CF7: ID formuláře (číslo). Použijte <code>*</code> pro všechny formuláře daného typu.</p></td></tr>
      <tr><th><label for="m-list-id">Cílový seznam</label></th>
        <td>
          <select id="m-list-id">
            <option value="">— Vyberte seznam —</option>
            <?php foreach ( $lists as $l ) : ?>
              <option value="<?php echo esc_attr($l['id']); ?>"><?php echo esc_html($l['name'] ?? $l['publicname'] ?? '—'); ?> (ID: <?php echo esc_html($l['id']); ?>)</option>
            <?php endforeach; ?>
          </select>
          <?php if ( empty($lists) ) : ?><p class="description">Načtěte <a href="<?php echo esc_url(admin_url('admin.php?page=smec-lists')); ?>">seznamy ze SmartEmailingu</a> nejdříve.</p><?php endif; ?>
        </td></tr>
      <tr><th><label for="m-contact-status">Stav kontaktu</label></th>
        <td>
          <select id="m-contact-status">
            <option value="confirmed">Confirmed (potvrzený)</option>
            <option value="unconfirmed">Unconfirmed (nepotvrzený)</option>
          </select>
        </td></tr>
      <tr><th><label for="m-consent-field">Pole souhlasu (volitelné)</label></th>
        <td><input type="text" id="m-consent-field" class="regular-text" placeholder="Např. consent nebo gdpr">
          <p class="description">Pokud je zadáno, kontakt se importuje pouze pokud je toto pole neprázdné nebo true.</p></td></tr>
      <tr><th><label for="m-enabled">Aktivovat propojení</label></th>
        <td><label class="smec-toggle"><input type="checkbox" id="m-enabled" value="1"><span class="smec-toggle-slider"></span></label></td></tr>
    </table>
  </div>

  <!-- TAB: System Fields -->
  <div class="smec-tab-content" id="smec-tab-fields">
    <p class="smec-muted">Namapujte pole formuláře na systémová pole SmartEmailingu. Povinné: <strong>E-mail</strong>.</p>
    <table class="wp-list-table widefat smec-field-map-table">
      <thead><tr><th>SE Pole</th><th>Zdroj</th><th>Hodnota / Klíč pole / Placeholder</th></tr></thead>
      <tbody id="smec-system-fields-body">
        <?php
        $sys_fields = [
          'emailaddress' => 'E-mail *',
          'name'         => 'Jméno',
          'surname'      => 'Příjmení',
          'cellphone'    => 'Telefon',
          'birthday'     => 'Datum nar. (YYYY-MM-DD)',
          'salution'     => 'Oslovení',
        ];
        foreach ( $sys_fields as $key => $label ) : ?>
          <tr data-field="<?php echo esc_attr($key); ?>">
            <td><?php echo esc_html($label); ?></td>
            <td>
              <select class="smec-source-select" name="source[<?php echo esc_attr($key); ?>]">
                <option value="form_field">Pole formuláře</option>
                <option value="static">Statická hodnota</option>
                <option value="placeholder">Placeholder</option>
              </select>
            </td>
            <td>
              <input type="text" class="smec-field-value regular-text" name="value[<?php echo esc_attr($key); ?>]" placeholder="<?php echo esc_attr( $key === 'emailaddress' ? 'email' : $key ); ?>">
              <select class="smec-placeholder-select" style="display:none;">
                <?php foreach ( $placeholders as $ph ) : ?><option value="<?php echo esc_attr($ph); ?>"><?php echo esc_html($ph); ?></option><?php endforeach; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- TAB: Custom Fields -->
  <div class="smec-tab-content" id="smec-tab-custom-fields">
    <p class="smec-muted">Přidejte mapování vlastních polí SmartEmailingu.</p>
    <button type="button" id="smec-add-cf-row" class="button">+ Přidat pole</button>
    <table class="wp-list-table widefat smec-field-map-table" style="margin-top:12px;">
      <thead><tr><th>SE Vlastní pole</th><th>Zdroj</th><th>Hodnota / Klíč pole</th><th></th></tr></thead>
      <tbody id="smec-cf-body">
        <tr class="smec-cf-template" style="display:none;">
          <td>
            <select class="smec-cf-field-id">
              <option value="">— Vyberte pole —</option>
              <?php foreach ( $fields as $f ) : ?>
                <option value="<?php echo esc_attr($f['id']); ?>"><?php echo esc_html($f['name'] ?? '—'); ?> (ID: <?php echo esc_html($f['id']); ?>)</option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <select class="smec-source-select">
              <option value="form_field">Pole formuláře</option>
              <option value="static">Statická hodnota</option>
              <option value="placeholder">Placeholder</option>
            </select>
          </td>
          <td>
            <input type="text" class="smec-field-value regular-text" placeholder="Hodnota nebo klíč pole">
            <select class="smec-placeholder-select" style="display:none;">
              <?php foreach ( $placeholders as $ph ) : ?><option value="<?php echo esc_attr($ph); ?>"><?php echo esc_html($ph); ?></option><?php endforeach; ?>
            </select>
          </td>
          <td><button type="button" class="button-link smec-remove-row smec-danger">✕</button></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- TAB: Tags & Conditions -->
  <div class="smec-tab-content" id="smec-tab-tags">
    <h3>Štítky / Tagy</h3>
    <button type="button" id="smec-add-tag-row" class="button">+ Přidat štítek</button>
    <table class="wp-list-table widefat smec-field-map-table" style="margin-top:12px;">
      <thead><tr><th>Zdroj</th><th>Hodnota / Klíč pole</th><th></th></tr></thead>
      <tbody id="smec-tags-body">
        <tr class="smec-tag-template" style="display:none;">
          <td>
            <select class="smec-source-select">
              <option value="static">Statická hodnota</option>
              <option value="form_field">Pole formuláře</option>
              <option value="placeholder">Placeholder</option>
            </select>
          </td>
          <td>
            <input type="text" class="smec-field-value regular-text" placeholder="Hodnota nebo klíč pole">
            <select class="smec-placeholder-select" style="display:none;">
              <?php foreach ( $placeholders as $ph ) : ?><option value="<?php echo esc_attr($ph); ?>"><?php echo esc_html($ph); ?></option><?php endforeach; ?>
            </select>
          </td>
          <td><button type="button" class="button-link smec-remove-row smec-danger">✕</button></td>
        </tr>
      </tbody>
    </table>

    <h3>Podmínky (AND logika)</h3>
    <p class="smec-muted">Kontakt se importuje pouze pokud jsou splněny všechny podmínky.</p>
    <button type="button" id="smec-add-condition" class="button">+ Přidat podmínku</button>
    <table class="wp-list-table widefat smec-field-map-table" style="margin-top:12px;">
      <thead><tr><th>Pole formuláře</th><th>Operátor</th><th>Hodnota</th><th></th></tr></thead>
      <tbody id="smec-conditions-body">
        <tr class="smec-condition-template" style="display:none;">
          <td><input type="text" class="smec-condition-field regular-text" placeholder="Klíč pole"></td>
          <td>
            <select class="smec-condition-op">
              <option value="==">==</option><option value="!=">!=</option>
              <option value="contains">obsahuje</option>
              <option value="not_empty">není prázdné</option>
              <option value="empty">je prázdné</option>
            </select>
          </td>
          <td><input type="text" class="smec-condition-val regular-text" placeholder="Hodnota"></td>
          <td><button type="button" class="button-link smec-remove-row smec-danger">✕</button></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- TAB: Test -->
  <div class="smec-tab-content" id="smec-tab-test">
    <p>Zašle testovací kontakt na vaši e-mailovou adresu.</p>
    <div class="smec-inline-form">
      <input type="email" id="smec-test-email" placeholder="test@example.com" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
      <button type="button" id="smec-send-test" class="button button-secondary">Odeslat testovací kontakt</button>
    </div>
    <div id="smec-test-result" class="smec-notice" style="display:none;margin-top:12px;"></div>
  </div>

  <div class="smec-actions">
    <button type="button" id="smec-save-mapping" class="button button-primary">Uložit propojení</button>
    <button type="button" id="smec-cancel-edit2" class="button">Zrušit</button>
    <span id="smec-mapping-result" class="smec-inline-result"></span>
  </div>
</div><!-- /smec-card -->
</div><!-- /smec-mapping-editor -->

</div>
