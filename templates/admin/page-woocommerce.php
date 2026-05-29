<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** @var SMEC_Settings $settings */
$cfg    = $settings->get_woocommerce();
$lists  = $settings->get_cached_lists() ?? [];
$fields = $settings->get_cached_customfields() ?? [];
$wc_active = class_exists('WooCommerce');
?>
<div class="wrap smec-wrap">
<h1>WooCommerce integrace</h1>

<?php if ( ! $wc_active ) : ?>
  <div class="notice notice-warning"><p><strong>WooCommerce není aktivní.</strong> Tato sekce bude dostupná po aktivaci WooCommerce.</p></div>
<?php endif; ?>

<div class="smec-card <?php echo $wc_active ? '' : 'smec-disabled'; ?>">
  <h2>Nastavení WooCommerce integrace</h2>

  <table class="form-table smec-form-table">
    <tr>
      <th><label for="woo-enabled">Aktivovat WooCommerce integraci</label></th>
      <td>
        <label class="smec-toggle">
          <input type="checkbox" id="woo-enabled" name="enabled" value="1" <?php checked( ! empty($cfg['enabled']) ); ?> <?php echo $wc_active ? '' : 'disabled'; ?>>
          <span class="smec-toggle-slider"></span>
        </label>
      </td>
    </tr>
    <tr>
      <th><label for="woo-list-id">Cílový seznam kontaktů</label></th>
      <td>
        <select id="woo-list-id" name="list_id">
          <option value="">— Vyberte seznam —</option>
          <?php foreach ( $lists as $l ) : ?>
            <option value="<?php echo esc_attr($l['id']); ?>" <?php selected( (string)($cfg['list_id']??''), (string)$l['id'] ); ?>>
              <?php echo esc_html($l['name'] ?? $l['publicname'] ?? '—'); ?> (ID: <?php echo esc_html($l['id']); ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </td>
    </tr>
    <tr>
      <th><label for="woo-require-optin">Vyžadovat souhlas na checkoutu</label></th>
      <td>
        <label class="smec-toggle">
          <input type="checkbox" id="woo-require-optin" name="require_optin" value="1" <?php checked( ! empty($cfg['require_optin']) ); ?>>
          <span class="smec-toggle-slider"></span>
        </label>
      </td>
    </tr>
    <tr id="woo-optin-label-row">
      <th><label for="woo-optin-label">Text checkboxu opt-in</label></th>
      <td><input type="text" id="woo-optin-label" name="optin_label" value="<?php echo esc_attr($cfg['optin_label'] ?? 'Přihlásit se k odběru newsletteru'); ?>" class="large-text"></td>
    </tr>
    <tr>
      <th><label>Importovat při statusu objednávky</label></th>
      <td>
        <?php
        $wc_statuses = $wc_active ? wc_get_order_statuses() : [ 'wc-processing' => 'Processing', 'wc-completed' => 'Completed' ];
        foreach ( $wc_statuses as $status => $label ) :
          $slug = str_replace( 'wc-', '', $status );
        ?>
          <label style="display:inline-block;margin-right:12px;">
            <input type="checkbox" name="import_on_status[]" value="<?php echo esc_attr($slug); ?>"
              <?php checked( in_array( $slug, (array)($cfg['import_on_status']??['completed']), true ) ); ?>>
            <?php echo esc_html($label); ?>
          </label>
        <?php endforeach; ?>
      </td>
    </tr>
    <tr>
      <th><label for="woo-status">Stav importovaného kontaktu</label></th>
      <td>
        <select id="woo-status" name="status">
          <option value="confirmed"   <?php selected($cfg['status']??'', 'confirmed'); ?>>Confirmed</option>
          <option value="unconfirmed" <?php selected($cfg['status']??'', 'unconfirmed'); ?>>Unconfirmed</option>
        </select>
      </td>
    </tr>
    <tr>
      <th>Tagy</th>
      <td>
        <input type="text" id="woo-tags-input" class="regular-text" placeholder="tag1, tag2, tag3">
        <p class="description">Čárkou oddělené tagy, které budou přiřazeny zákazníkovi.</p>
        <div id="woo-tags-preview"></div>
      </td>
    </tr>
  </table>

  <h3>Mapování vlastních polí</h3>
  <p class="smec-muted">Mapujte fakturační pole WooCommerce na vlastní pole SmartEmailingu.</p>
  <button type="button" id="woo-add-cf-row" class="button">+ Přidat mapování</button>
  <table class="wp-list-table widefat smec-field-map-table" style="margin-top:12px;">
    <thead><tr><th>SE Vlastní pole</th><th>Zdroj</th><th>WC Pole / Hodnota</th><th></th></tr></thead>
    <tbody id="woo-cf-body">
      <?php foreach ( (array)($cfg['custom_field_mapping']??[]) as $row ) : ?>
        <tr>
          <td>
            <select class="smec-cf-field-id">
              <option value="">— —</option>
              <?php foreach ($fields as $f) : ?>
                <option value="<?php echo esc_attr($f['id']); ?>" <?php selected((string)($row['field_id']??''), (string)$f['id']); ?>><?php echo esc_html($f['name']??'—'); ?> (<?php echo esc_html($f['id']); ?>)</option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <select class="smec-source-select">
              <option value="form_field" <?php selected($row['source']??'', 'form_field'); ?>>WC Fakturace</option>
              <option value="order_field" <?php selected($row['source']??'', 'order_field'); ?>>Pole objednávky</option>
              <option value="static" <?php selected($row['source']??'', 'static'); ?>>Statická hodnota</option>
            </select>
          </td>
          <td><input type="text" class="smec-field-value regular-text" value="<?php echo esc_attr($row['value']??''); ?>"></td>
          <td><button type="button" class="button-link smec-remove-row smec-danger">✕</button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="smec-actions">
    <button type="button" id="smec-save-woocommerce" class="button button-primary">Uložit nastavení</button>
    <span id="smec-woo-result" class="smec-inline-result"></span>
  </div>
</div>

</div>
