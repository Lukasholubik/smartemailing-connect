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

<!-- ── Průvodce ────────────────────────────────────────── -->
<div class="smec-card smec-help-card">
  <div class="smec-guide-header" id="smec-guide-toggle" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;">
    <h2 style="margin:0;">📖 Průvodce propojením formulářů</h2>
    <span class="smec-guide-arrow" style="font-size:1.2rem;">▼</span>
  </div>

  <div id="smec-guide-body" style="margin-top:20px;">

    <!-- KROK 1 -->
    <div class="smec-guide-section">
      <h3 class="smec-guide-step">① Než začnete</h3>
      <p>Ujistěte se, že máte:</p>
      <ul class="smec-help-ul">
        <li>Nakonfigurované <a href="<?php echo esc_url( admin_url('admin.php?page=smec-api') ); ?>">API připojení</a> (zelený stav).</li>
        <li>Načtené <a href="<?php echo esc_url( admin_url('admin.php?page=smec-lists') ); ?>">Seznamy a vlastní pole</a> ze SmartEmailingu (nutné pro výběr v editorů).</li>
        <li>Aktivní formulářový plugin (Elementor Pro, CF7, Fluent Forms nebo WPForms).</li>
      </ul>
    </div>

    <!-- KROK 2 -->
    <div class="smec-guide-section">
      <h3 class="smec-guide-step">② Záložka Základní – co kde najdu?</h3>
      <table class="smec-guide-table">
        <tr>
          <td><strong>Název propojení</strong></td>
          <td>Libovolný název pro vaši orientaci. Např. <code>Newsletter – homepage</code>.</td>
        </tr>
        <tr>
          <td><strong>Typ formuláře</strong></td>
          <td>Vyberte plugin, kterým je formulář vytvořen.</td>
        </tr>
        <tr>
          <td><strong>ID / Název formuláře</strong></td>
          <td>
            Identifikátor konkrétního formuláře. Jak ho zjistit:
            <ul class="smec-help-ul smec-guide-sublist">
              <li><strong>Elementor Pro Forms</strong> → otevřete formulář v Elementoru → widget nastavení → pole <em>Form Name</em>. Zadejte přesně tuto hodnotu (rozlišuje malá/velká písmena).</li>
              <li><strong>Contact Form 7</strong> → WP admin → Kontaktní formuláře → v seznamu u formuláře vidíte <em>ID: 42</em>. Zadejte číslo.</li>
              <li><strong>Fluent Forms / WPForms</strong> → v seznamu formulářů vidíte ID v sloupci nebo v URL při editaci (<code>?form_id=5</code>). Zadejte číslo.</li>
              <li><strong>WP Registrace</strong> → zadejte pevně <code>wp_registration</code>.</li>
              <li><strong>Hvězdička <code>*</code></strong> → zachytí všechny formuláře daného typu (vhodné pokud máte jen jeden).</li>
            </ul>
          </td>
        </tr>
        <tr>
          <td><strong>Cílový seznam</strong></td>
          <td>Vyberte seznam SmartEmailingu, do kterého se kontakty přidají. Pokud je seznam prázdný, nejdřív ho <a href="<?php echo esc_url( admin_url('admin.php?page=smec-lists') ); ?>">načtěte ze SmartEmailingu</a>.</td>
        </tr>
        <tr>
          <td><strong>Stav kontaktu</strong></td>
          <td>
            <code>Confirmed</code> = kontakt je rovnou potvrzený (neprojde double opt-in e-mailem).<br>
            <code>Unconfirmed</code> = SmartEmailing pošle potvrzovací e-mail. Vhodné pokud nesebíráte výslovný souhlas přímo na formuláři.
          </td>
        </tr>
        <tr>
          <td><strong>Pole souhlasu</strong></td>
          <td>
            Nepovinné. Zadejte název pole z formuláře, které funguje jako checkbox souhlasu (GDPR).<br>
            Kontakt se importuje pouze pokud je toto pole zaškrtnuté (neprázdné).<br>
            Příklad: formulář má checkbox s názvem <code>souhlas</code> → zadejte <code>souhlas</code>.
          </td>
        </tr>
      </table>
    </div>

    <!-- KROK 3 -->
    <div class="smec-guide-section">
      <h3 class="smec-guide-step">③ Záložka Systémová pole – mapování polí formuláře</h3>
      <p>Tady říkáte pluginu: <em>„Toto pole z mého formuláře se uloží jako tento údaj v SmartEmailingu."</em></p>

      <h4>Tři typy zdrojů hodnoty:</h4>
      <table class="smec-guide-table">
        <tr>
          <td><strong>Pole formuláře</strong></td>
          <td>
            Hodnotu vezme z odeslaného formuláře. Do pole <em>Hodnota / Klíč pole</em> zadejte přesný název (klíč) pole z vašeho formuláře.<br>
            <span class="smec-guide-example">Příklad: formulář má pole <code>your-email</code> → zadejte <code>your-email</code></span>
          </td>
        </tr>
        <tr>
          <td><strong>Statická hodnota</strong></td>
          <td>
            Vždy vloží stejný pevný text, bez ohledu na to co uživatel vyplnil.<br>
            <span class="smec-guide-example">Příklad: chcete aby oslovení bylo vždy <code>Vážený</code> → Zdroj: Statická hodnota, Hodnota: <code>Vážený</code></span>
          </td>
        </tr>
        <tr>
          <td><strong>Placeholder</strong></td>
          <td>
            Automaticky vyplní systémovou proměnnou. Vyberte ze seznamu.<br>
            <span class="smec-guide-example">Příklad: <code>__UTM_SOURCE__</code> = přenese UTM parametr z URL stránky do SmartEmailingu</span>
          </td>
        </tr>
      </table>

      <h4 style="margin-top:16px;">Systémová pole SmartEmailingu a jak zjistit klíče polí formuláře:</h4>
      <table class="smec-guide-table">
        <thead><tr><th>SE Pole</th><th>Co zadávat jako klíč (příklady)</th><th>Poznámka</th></tr></thead>
        <tbody>
          <tr><td><strong>E-mail</strong> *</td><td><code>your-email</code>, <code>email</code>, <code>Email</code></td><td>Povinné – bez e-mailu import selže.</td></tr>
          <tr><td><strong>Jméno</strong></td><td><code>your-name</code>, <code>first-name</code>, <code>firstname</code></td><td></td></tr>
          <tr><td><strong>Příjmení</strong></td><td><code>last-name</code>, <code>surname</code></td><td></td></tr>
          <tr><td><strong>Telefon</strong></td><td><code>phone</code>, <code>tel</code>, <code>your-phone</code></td><td>Formát: <code>+420777123456</code></td></tr>
          <tr><td><strong>Datum nar.</strong></td><td><code>birthday</code>, <code>dob</code></td><td>Formát: <code>YYYY-MM-DD</code></td></tr>
          <tr><td><strong>Oslovení</strong></td><td>Nejčastěji Statická hodnota: <code>Vážený</code> / <code>Vážená</code></td><td>Nebo z pole formuláře.</td></tr>
        </tbody>
      </table>

      <div class="smec-guide-howto">
        <strong>Jak zjistím klíče polí svého formuláře?</strong>
        <ul class="smec-help-ul">
          <li><strong>CF7</strong> → otevřete formulář v editoru → v HTML kódu pole hledejte atribut <code>name</code>, např. <code>[text* your-name]</code> → klíč je <code>your-name</code>.</li>
          <li><strong>Elementor Pro</strong> → widget → obsah → každé pole má volbu <em>ID</em> – to je klíč.</li>
          <li><strong>Fluent Forms / WPForms</strong> → editor pole → Advanced → Field Name/ID.</li>
          <li>Obecně: zobrazte si zdrojový kód stránky a hledejte <code>name="..."</code> u inputů.</li>
        </ul>
      </div>
    </div>

    <!-- KROK 4 -->
    <div class="smec-guide-section">
      <h3 class="smec-guide-step">④ Záložka Vlastní pole</h3>
      <p>
        Slouží pro mapování na <strong>vámi vytvořená pole</strong> v SmartEmailingu (např. Firma, Segment, Zdroj leadu).<br>
        Nejdřív musíte <a href="<?php echo esc_url( admin_url('admin.php?page=smec-lists') ); ?>">načíst vlastní pole</a> ze SmartEmailingu – pak se zobrazí v rozbalovacím seznamu.
      </p>
      <p>Funguje stejně jako systémová pole: vyberete SE pole, zdroj (formulář / staticky / placeholder) a hodnotu.</p>
      <span class="smec-guide-example">Příklad: máte v SE vlastní pole „Zdroj" (ID 15) → Zdroj: Statická hodnota, Hodnota: <code>web_form</code></span>
    </div>

    <!-- KROK 5 -->
    <div class="smec-guide-section">
      <h3 class="smec-guide-step">⑤ Záložka Štítky &amp; Podmínky</h3>

      <h4>Štítky (Tagy)</h4>
      <p>Kontaktu v SmartEmailingu přidáte štítek. Tagy slouží k segmentaci.</p>
      <table class="smec-guide-table">
        <tr>
          <td><strong>Statická hodnota</strong></td>
          <td>Všichni přes tento formulář dostanou tag. Př.: <code>newsletter-web</code></td>
        </tr>
        <tr>
          <td><strong>Pole formuláře</strong></td>
          <td>Tag se vezme z vybraného pole formuláře (kontakt označí sám sebe). Př.: pole <code>oblast_zajmu</code></td>
        </tr>
        <tr>
          <td><strong>Placeholder</strong></td>
          <td>Př.: <code>__UTM_CAMPAIGN__</code> – tag podle kampaně, ze které přišel</td>
        </tr>
      </table>

      <h4 style="margin-top:16px;">Podmínky (AND logika)</h4>
      <p>Import proběhne <strong>pouze pokud jsou splněny VŠECHNY podmínky</strong>. Vhodné pro formuláře s více účely.</p>
      <table class="smec-guide-table">
        <thead><tr><th>Operátor</th><th>Kdy použít</th><th>Příklad</th></tr></thead>
        <tbody>
          <tr><td><code>==</code></td><td>Pole má přesnou hodnotu</td><td>Pole <code>newsletter</code> == <code>yes</code></td></tr>
          <tr><td><code>!=</code></td><td>Pole NEMÁ tuto hodnotu</td><td>Pole <code>typ</code> != <code>firma</code></td></tr>
          <tr><td><code>obsahuje</code></td><td>Hodnota obsahuje řetězec</td><td>Pole <code>email</code> obsahuje <code>@gmail</code></td></tr>
          <tr><td><code>není prázdné</code></td><td>Pole bylo vyplněno</td><td>Pole <code>souhlas</code> není prázdné</td></tr>
          <tr><td><code>je prázdné</code></td><td>Pole nebylo vyplněno</td><td></td></tr>
        </tbody>
      </table>
    </div>

    <!-- KROK 6 -->
    <div class="smec-guide-section">
      <h3 class="smec-guide-step">⑥ Záložka Test</h3>
      <p>
        Uložte propojení a přejděte na záložku Test. Zadejte svůj e-mail a klikněte na <em>Odeslat testovací kontakt</em>.<br>
        Plugin pošle testovací data do SmartEmailingu a oznámí vám výsledek. Pak zkontrolujte v SmartEmailingu, zda se kontakt správně importoval se všemi poli a tagy.
      </p>
    </div>

    <!-- Placeholdery -->
    <div class="smec-guide-section">
      <h3 class="smec-guide-step">📋 Přehled všech placeholderů</h3>
      <table class="smec-guide-table smec-guide-placeholders">
        <thead><tr><th>Placeholder</th><th>Co vloží</th></tr></thead>
        <tbody>
          <tr><td><code>__DATE__</code></td><td>Aktuální datum (YYYY-MM-DD)</td></tr>
          <tr><td><code>__DATE_TIME__</code></td><td>Datum a čas (YYYY-MM-DD HH:MM:SS)</td></tr>
          <tr><td><code>__PAGE_TITLE__</code></td><td>Název stránky, kde byl formulář odeslán</td></tr>
          <tr><td><code>__CURRENT_URL__</code></td><td>URL stránky s formulářem</td></tr>
          <tr><td><code>__REFERRER__</code></td><td>Odkud návštěvník přišel (HTTP referrer)</td></tr>
          <tr><td><code>__UTM_SOURCE__</code></td><td>utm_source z URL (např. <code>google</code>)</td></tr>
          <tr><td><code>__UTM_MEDIUM__</code></td><td>utm_medium z URL (např. <code>cpc</code>)</td></tr>
          <tr><td><code>__UTM_CAMPAIGN__</code></td><td>utm_campaign z URL</td></tr>
          <tr><td><code>__UTM_CONTENT__</code></td><td>utm_content z URL</td></tr>
          <tr><td><code>__UTM_TERM__</code></td><td>utm_term z URL</td></tr>
          <tr><td><code>__SITE_NAME__</code></td><td>Název webu</td></tr>
          <tr><td><code>__SITE_URL__</code></td><td>URL webu</td></tr>
          <tr><td><code>__USER_ID__</code></td><td>ID přihlášeného uživatele</td></tr>
          <tr><td><code>__USER_EMAIL__</code></td><td>E-mail přihlášeného uživatele</td></tr>
          <tr><td><code>__FORM_NAME__</code></td><td>Název tohoto propojení</td></tr>
          <tr><td><code>__POST_ID__</code></td><td>ID aktuálního postu/stránky</td></tr>
          <tr><td><code>__POST_TITLE__</code></td><td>Titulek aktuálního postu/stránky</td></tr>
        </tbody>
      </table>
    </div>

    <p class="smec-help-links">
      <a href="https://app.smartemailing.cz/docs/api/v3/index.html" target="_blank" rel="noopener">API v3 dokumentace ↗</a>
      &nbsp;&nbsp;
      <a href="https://help.smartemailing.cz" target="_blank" rel="noopener">Centrum nápovědy SmartEmailing ↗</a>
      &nbsp;&nbsp;
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-lists' ) ); ?>">Načíst seznamy a vlastní pole →</a>
    </p>
  </div><!-- /smec-guide-body -->
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
