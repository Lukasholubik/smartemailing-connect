<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** @var SMEC_Logger $logger */
/** @var SMEC_Queue  $queue */
$pending = $queue->count_items( 'pending' );
$failed  = $queue->count_items( 'failed' );
$done    = $queue->count_items( 'done' );
?>
<div class="wrap smec-wrap">
<h1>Logy &amp; diagnostika</h1>

<div class="smec-two-col">

<div class="smec-card">
  <div class="smec-card-header">
    <h2>API logy</h2>
  </div>
  <div class="smec-log-filters">
    <select id="log-type">
      <option value="">Všechny typy</option>
      <option value="import">Import</option>
      <option value="api">API</option>
      <option value="forms">Formuláře</option>
      <option value="webtracking">Webtracking</option>
      <option value="woocommerce">WooCommerce</option>
      <option value="queue">Fronta</option>
      <option value="general">Obecné</option>
    </select>
    <select id="log-level">
      <option value="">Všechny úrovně</option>
      <option value="info">Info</option>
      <option value="error">Chyba</option>
      <option value="warning">Varování</option>
      <option value="debug">Debug</option>
    </select>
    <button type="button" id="smec-load-logs" class="button">Načíst</button>
    <button type="button" id="smec-clear-logs-all" class="button smec-danger-btn">Smazat vše</button>
    <button type="button" id="smec-clear-logs-old" class="button">Smazat starší 30 dní</button>
  </div>
  <div id="smec-logs-table-wrap">
    <p class="smec-muted">Klikněte na "Načíst" pro zobrazení logů.</p>
  </div>
  <div id="smec-logs-pagination" class="smec-pagination"></div>
</div>

<div class="smec-card">
  <h2>Fronta opakování</h2>
  <div class="smec-stats-row">
    <span class="smec-stat"><strong><?php echo (int)$pending; ?></strong> čekající</span>
    <span class="smec-stat smec-warn-text"><strong><?php echo (int)$failed; ?></strong> chybné</span>
    <span class="smec-stat"><strong><?php echo (int)$done; ?></strong> dokončené</span>
  </div>
  <div class="smec-actions" style="margin-top:16px;">
    <button type="button" id="smec-process-queue" class="button">▶ Zpracovat frontu nyní</button>
    <button type="button" id="smec-retry-failed"  class="button">↺ Opakovat chybné</button>
    <button type="button" id="smec-clear-done-queue" class="button">Smazat dokončené</button>
  </div>
  <div id="smec-queue-result" class="smec-notice" style="display:none;margin-top:12px;"></div>

  <h3>Diagnostika</h3>
  <table class="widefat">
    <tr><td>Plugin verze</td><td><?php echo esc_html( SMEC_VERSION ); ?></td></tr>
    <tr><td>WordPress verze</td><td><?php global $wp_version; echo esc_html($wp_version); ?></td></tr>
    <tr><td>PHP verze</td><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
    <tr><td>WooCommerce</td><td><?php echo class_exists('WooCommerce') ? esc_html(WC_VERSION) : '—'; ?></td></tr>
    <tr><td>Elementor Pro</td><td><?php echo defined('ELEMENTOR_PRO_VERSION') ? esc_html(ELEMENTOR_PRO_VERSION) : '—'; ?></td></tr>
    <tr><td>Contact Form 7</td><td><?php echo defined('WPCF7_VERSION') ? esc_html(WPCF7_VERSION) : '—'; ?></td></tr>
    <tr><td>Fluent Forms</td><td><?php echo defined('FLUENTFORM_VERSION') ? esc_html(FLUENTFORM_VERSION) : '—'; ?></td></tr>
    <tr><td>WPForms</td><td><?php echo defined('WPFORMS_VERSION') ? esc_html(WPFORMS_VERSION) : '—'; ?></td></tr>
    <tr><td>Ofic. SmartEmailing plugin</td><td><?php echo is_plugin_active('smartemailing/smartemailing.php') ? '<span style="color:#c9372c">Aktivní – možná kolize!</span>' : 'Neaktivní'; ?></td></tr>
    <tr><td>DB verze</td><td><?php echo esc_html(get_option('smec_db_version','—')); ?></td></tr>
    <tr><td>Příští cron (queue)</td><td><?php $next = wp_next_scheduled('smec_process_queue'); echo $next ? esc_html(date_i18n('Y-m-d H:i:s',$next)) : '—'; ?></td></tr>
  </table>
</div>

</div>
</div>
