<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** @var SMEC_Settings $settings */
$cfg = $settings->get_general();
?>
<div class="wrap smec-wrap">
<h1>Nastavení</h1>

<div class="smec-two-col">

<div class="smec-card">
  <h2>Moduly</h2>
  <p class="smec-muted">Deaktivujte konkrétní části pluginu pokud je nepotřebujete.</p>
  <table class="form-table smec-form-table">
    <?php
    $module_labels = [
      'webtracking'  => 'Webtracking SmartEmailingu',
      'forms'        => 'Propojení formulářů',
      'woocommerce'  => 'WooCommerce integrace',
      'reading_time' => 'Modul Doba čtení',
    ];
    foreach ( $module_labels as $key => $label ) : ?>
    <tr>
      <th><?php echo esc_html($label); ?></th>
      <td>
        <label class="smec-toggle">
          <input type="checkbox" class="smec-module-toggle" data-module="<?php echo esc_attr($key); ?>" value="1"
            <?php checked( ! empty($cfg['modules'][$key]) ); ?>>
          <span class="smec-toggle-slider"></span>
        </label>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="smec-card">
  <h2>Debug &amp; Logy</h2>
  <table class="form-table smec-form-table">
    <tr>
      <th><label for="gs-debug">Debug režim</label></th>
      <td>
        <label class="smec-toggle">
          <input type="checkbox" id="gs-debug" name="debug" value="1" <?php checked( ! empty($cfg['debug']) ); ?>>
          <span class="smec-toggle-slider"></span>
        </label>
        <p class="description">Zapisuje podrobné informace do logů včetně API odpovědí.</p>
      </td>
    </tr>
    <tr>
      <th><label for="gs-log-retention">Uchovávat logy (dní)</label></th>
      <td>
        <input type="number" id="gs-log-retention" name="log_retention_days" value="<?php echo esc_attr($cfg['log_retention_days']??30); ?>" min="1" max="365" class="small-text">
        <p class="description">Starší záznamy jsou automaticky mazány denním cronem.</p>
      </td>
    </tr>
    <tr>
      <th><label for="gs-delete-on-uninstall">Smazat data při odinstalaci</label></th>
      <td>
        <label class="smec-toggle">
          <input type="checkbox" id="gs-delete-on-uninstall" name="delete_on_uninstall" value="1" <?php checked( ! empty($cfg['delete_on_uninstall']) ); ?>>
          <span class="smec-toggle-slider"></span>
        </label>
        <p class="description">Při odinstalaci pluginu budou smazána všechna data, nastavení a DB tabulky.</p>
      </td>
    </tr>
  </table>

  <div class="smec-actions">
    <button type="button" id="smec-save-general" class="button button-primary">Uložit nastavení</button>
    <span id="smec-general-result" class="smec-inline-result"></span>
  </div>
</div>

</div><!-- /two-col -->

<div class="smec-card">
  <h2>Export &amp; Import nastavení</h2>
  <div class="smec-two-col">
    <div>
      <h3>Export</h3>
      <label><input type="checkbox" id="export-include-api"> Zahrnout API klíč (citlivé!)</label><br><br>
      <button type="button" id="smec-export-settings" class="button button-secondary">Exportovat jako JSON</button>
      <div id="smec-export-result" style="margin-top:12px;"></div>
      <textarea id="smec-export-output" rows="8" class="large-text code" style="display:none;margin-top:8px;" readonly></textarea>
    </div>
    <div>
      <h3>Import</h3>
      <p class="smec-muted">Vložte JSON exportovaný z jiného prostředí.</p>
      <textarea id="smec-import-input" rows="8" class="large-text code" placeholder='{"plugin_version":"1.0.0",...}'></textarea>
      <br>
      <button type="button" id="smec-import-settings" class="button button-secondary" style="margin-top:8px;">Importovat nastavení</button>
      <div id="smec-import-result" style="margin-top:8px;"></div>
    </div>
  </div>
</div>

</div>
