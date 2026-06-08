<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** @var SMEC_Settings     $settings */
/** @var SMEC_Logger       $logger */
/** @var SMEC_Queue        $queue */
/** @var SMEC_Diagnostics  $diagnostics */
/** @var SMEC_FormScanner  $scanner */

$api_cfg     = $settings->get_api();
$wt_cfg      = $settings->get_webtracking();
$mappings    = $settings->get_form_mappings();
$last_import = $logger->get_last_success( 'import' );
$last_error  = $logger->get_last_error();
$api_enabled = ! empty( $api_cfg['enabled'] ) && ! empty( $api_cfg['username'] ) && ! empty( $api_cfg['api_key'] );
$wt_enabled  = ! empty( $wt_cfg['enabled'] ) && ! empty( $wt_cfg['guid'] );
$active_maps = count( array_filter( $mappings, fn($m) => ! empty( $m['enabled'] ) ) );
$q_pending   = $queue->count_items( 'pending' );
$q_failed    = $queue->count_items( 'failed' );

// Diagnostika
$issues = $diagnostics->run();
$counts = $diagnostics->count_by_level( $issues );
$has_critical = $counts['critical'] > 0;
$has_issues   = ( $counts['critical'] + $counts['warning'] ) > 0;
?>
<div class="wrap smec-wrap">
<h1>SmartEmailing Connect – Přehled</h1>

<!-- ═══════════════════════════════════════════
     DIAGNOSTIKA – hlavní blok
═══════════════════════════════════════════ -->
<?php if ( $has_issues ) : ?>
<div class="smec-diag-banner <?php echo $has_critical ? 'smec-diag-critical' : 'smec-diag-warning'; ?>">
  <div class="smec-diag-banner-header">
    <span class="smec-diag-icon"><?php echo $has_critical ? '🔴' : '⚠️'; ?></span>
    <div>
      <strong>
        <?php
        $parts = [];
        if ( $counts['critical'] ) $parts[] = $counts['critical'] . ' kritický problém' . ( $counts['critical'] > 1 ? 'y' : '' );
        if ( $counts['warning'] )  $parts[] = $counts['warning']  . ' varování';
        if ( $counts['info'] )     $parts[] = $counts['info']     . ' informace';
        echo esc_html( implode( ' &amp; ', $parts ) );
        ?>
      </strong>
      <span class="smec-muted"> – kliknutím na problém zobrazíte doporučené řešení</span>
    </div>
    <button type="button" class="button button-small smec-diag-refresh" id="smec-refresh-diag" title="Znovu zkontrolovat">↻ Obnovit</button>
  </div>
</div>
<?php else : ?>
<div class="smec-diag-banner smec-diag-ok">
  <span class="smec-diag-icon">✅</span>
  <strong>Vše v pořádku.</strong> Žádné problémy nebyly nalezeny.
  <button type="button" class="button button-small smec-diag-refresh" id="smec-refresh-diag" style="margin-left:12px;">↻ Obnovit</button>
</div>
<?php endif; ?>

<!-- Jednotlivé problémy -->
<?php if ( ! empty( $issues ) ) : ?>
<div id="smec-diag-issues">
  <?php foreach ( $issues as $issue ) :
    $level_class = 'smec-diag-item-' . esc_attr( $issue['level'] );
    $level_icon  = match( $issue['level'] ) {
      'critical' => '🔴',
      'warning'  => '⚠️',
      default    => 'ℹ️',
    };
  ?>
  <div class="smec-diag-item <?php echo esc_attr( $level_class ); ?>" data-id="<?php echo esc_attr( $issue['id'] ); ?>">
    <div class="smec-diag-item-header" role="button" tabindex="0">
      <span class="smec-diag-item-icon"><?php echo esc_html( $level_icon ); ?></span>
      <span class="smec-diag-item-title"><?php echo esc_html( $issue['title'] ); ?></span>
      <span class="smec-diag-item-toggle">▼</span>
    </div>
    <div class="smec-diag-item-body">
      <p class="smec-diag-message"><?php echo esc_html( $issue['message'] ); ?></p>

      <div class="smec-diag-solution">
        <span class="smec-diag-solution-label">💡 Doporučené řešení:</span>
        <p><?php echo esc_html( $issue['solution'] ); ?></p>
      </div>

      <div class="smec-diag-actions">
        <?php if ( ! empty( $issue['link'] ) ) : ?>
          <a href="<?php echo esc_url( $issue['link'] ); ?>" class="button button-small">
            <?php echo esc_html( $issue['link_text'] ?? 'Opravit' ); ?> →
          </a>
        <?php endif; ?>

        <?php if ( ! empty( $issue['action'] ) ) :
          $action = $issue['action'];
        ?>
          <?php if ( $action['type'] === 'deactivate_plugin' ) : ?>
            <button type="button"
              class="button button-small smec-action-deactivate-plugin smec-danger-btn"
              data-plugin="<?php echo esc_attr( $action['plugin'] ); ?>"
              data-label="<?php echo esc_attr( $action['label'] ); ?>">
              <?php echo esc_html( $action['label'] ); ?>
            </button>
          <?php elseif ( $action['type'] === 'retry_queue' ) : ?>
            <button type="button" class="button button-small" id="diag-retry-queue">
              <?php echo esc_html( $action['label'] ); ?>
            </button>
          <?php elseif ( $action['type'] === 'process_queue' ) : ?>
            <button type="button" class="button button-small" id="diag-process-queue">
              <?php echo esc_html( $action['label'] ); ?>
            </button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════
     STATUS CARDS
═══════════════════════════════════════════ -->
<div class="smec-status-grid">

  <div class="smec-card smec-status-card <?php echo $api_enabled ? 'smec-ok' : 'smec-warn'; ?>">
    <h3>API připojení</h3>
    <p class="smec-status-badge"><?php echo $api_enabled ? '✓ Aktivní' : '✗ Neaktivní'; ?></p>
    <?php if ( $api_enabled ) : ?><p><?php echo esc_html( $api_cfg['username'] ); ?></p><?php endif; ?>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-api' ) ); ?>" class="smec-link">Nastavit</a>
  </div>

  <div class="smec-card smec-status-card <?php echo $wt_enabled ? 'smec-ok' : 'smec-warn'; ?>">
    <h3>Webtracking</h3>
    <p class="smec-status-badge"><?php echo $wt_enabled ? '✓ Aktivní' : '✗ Neaktivní'; ?></p>
    <?php if ( $wt_enabled ) : ?><p>GUID: <code><?php echo esc_html( substr( $wt_cfg['guid'], 0, 8 ) ); ?>…</code></p><?php endif; ?>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-webtracking' ) ); ?>" class="smec-link">Nastavit</a>
  </div>

  <div class="smec-card smec-status-card smec-neutral">
    <h3>Aktivní propojení formulářů</h3>
    <p class="smec-status-number"><?php echo (int) $active_maps; ?></p>
    <p class="smec-muted">z <?php echo count( $mappings ); ?> celkem</p>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-forms' ) ); ?>" class="smec-link">Spravovat</a>
  </div>

  <div class="smec-card smec-status-card <?php echo $q_failed > 0 ? 'smec-warn' : 'smec-neutral'; ?>">
    <h3>Fronta</h3>
    <p>Čekající: <strong><?php echo (int) $q_pending; ?></strong></p>
    <p>Chybné: <strong class="<?php echo $q_failed > 0 ? 'smec-error-text' : ''; ?>"><?php echo (int) $q_failed; ?></strong></p>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-logs' ) ); ?>" class="smec-link">Zobrazit</a>
  </div>

</div>

<!-- ═══════════════════════════════════════════
     GRAFY AKTIVITY
═══════════════════════════════════════════ -->
<div class="smec-card" id="smec-charts-card">
  <div class="smec-card-header">
    <h2>Aktivita</h2>
    <div class="smec-chart-period-btns">
      <button type="button" class="button smec-period-btn smec-period-active" data-period="30d">30 dní</button>
      <button type="button" class="button smec-period-btn" data-period="12m">12 měsíců</button>
    </div>
  </div>

  <div id="smec-charts-loading" class="smec-chart-loading">Načítám data…</div>
  <div id="smec-charts-body" style="display:none;">
    <div class="smec-charts-grid">
      <div class="smec-chart-wrap">
        <h3 class="smec-chart-title">API volání &amp; Importy</h3>
        <canvas id="smec-chart-activity" height="90"></canvas>
      </div>
      <div class="smec-chart-wrap">
        <h3 class="smec-chart-title">Kontakty dle formuláře</h3>
        <canvas id="smec-chart-byform" height="90"></canvas>
        <p id="smec-chart-byform-empty" class="smec-muted" style="display:none;text-align:center;padding:20px 0;">Žádné importy v tomto období.</p>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════
     POSLEDNÍ AKTIVITA
═══════════════════════════════════════════ -->
<div class="smec-two-col">
  <div class="smec-card">
    <h3>Poslední úspěšný import</h3>
    <?php if ( $last_import ) : ?>
      <p><?php echo esc_html( $last_import['message'] ); ?></p>
      <p class="smec-muted"><?php echo esc_html( $last_import['created_at'] ); ?></p>
    <?php else : ?>
      <p class="smec-muted">Žádný import zatím.</p>
    <?php endif; ?>
  </div>
  <div class="smec-card">
    <h3>Poslední chyba</h3>
    <?php if ( $last_error ) : ?>
      <p class="smec-error-text"><?php echo esc_html( $last_error['message'] ); ?></p>
      <p class="smec-muted"><?php echo esc_html( $last_error['type'] ); ?> | <?php echo esc_html( $last_error['created_at'] ); ?></p>
    <?php else : ?>
      <p class="smec-muted" style="color:#007017;">✓ Žádné chyby.</p>
    <?php endif; ?>
  </div>
</div>

<div class="smec-card smec-quick-links">
  <h3>Rychlé odkazy</h3>
  <ul>
    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-api' ) ); ?>">→ API připojení</a></li>
    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-webtracking' ) ); ?>">→ Webtracking</a></li>
    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-lists' ) ); ?>">→ Seznamy &amp; Vlastní pole</a></li>
    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-forms' ) ); ?>">→ Propojení formulářů</a></li>
    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-reading-time' ) ); ?>">→ Doba čtení</a></li>
    <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-logs' ) ); ?>">→ Logy &amp; diagnostika</a></li>
  </ul>
</div>

<!-- ═══════════════════════════════════════════
     MONITOR FORMULÁŘŮ
═══════════════════════════════════════════ -->
<?php
$all_forms  = $scanner->scan();
$form_stats = $scanner->stats( $all_forms );
$detectable = array_filter( $all_forms, fn( $f ) => $f['detectable'] && $f['connected'] !== null );
?>
<div class="smec-card" id="smec-form-monitor">
  <div class="smec-card-header">
    <h2>Monitor formulářů
      <?php if ( $form_stats['missing'] > 0 ) : ?>
        <span class="smec-badge smec-badge-inactive" style="margin-left:8px;vertical-align:middle;">
          <?php echo (int) $form_stats['missing']; ?> bez napojení
        </span>
      <?php elseif ( $form_stats['total'] > 0 ) : ?>
        <span class="smec-badge smec-badge-active" style="margin-left:8px;vertical-align:middle;">✓ vše napojeno</span>
      <?php endif; ?>
      <?php if ( $form_stats['ignored'] > 0 ) : ?>
        <span class="smec-badge" style="margin-left:4px;vertical-align:middle;background:#f0f0f0;color:#72777c;">
          <?php echo (int) $form_stats['ignored']; ?> ignorováno
        </span>
      <?php endif; ?>
    </h2>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <div class="smec-chart-period-btns">
        <button type="button" class="button button-small smec-monitor-period-btn smec-period-active" data-period="30d">30 dní</button>
        <button type="button" class="button button-small smec-monitor-period-btn" data-period="12m">12 měsíců</button>
      </div>
      <label style="font-size:0.8rem;color:#72777c;">
        <input type="checkbox" id="smec-show-ignored" style="margin-right:3px;">
        Zobrazit ignorované
      </label>
      <button type="button" id="smec-rescan-forms" class="button button-small">↻ Znovu skenovat</button>
    </div>
  </div>

  <p class="smec-muted">
    Přehled formulářů nalezených na webu. Elementor Pro formuláře jsou automaticky detekovány ze stránek.
    <strong>Cíl:</strong> žádný formulář sbírající kontakty by neměl být bez napojení.
    Formuláře kde odesílání není potřeba označte jako <em>Ignorovat</em>.
  </p>

  <?php if ( empty( $detectable ) ) : ?>
    <div class="smec-empty-state">
      <p>Žádné formuláře nebyly detekovány.</p>
      <p class="smec-muted">Ujistěte se, že je aktivní Elementor Pro, Contact Form 7, WPForms nebo jiný podporovaný plugin.</p>
    </div>
  <?php else : ?>
    <table class="wp-list-table widefat striped smec-form-monitor-table" id="smec-form-monitor-table">
      <thead>
        <tr>
          <th style="width:20%">Formulář &amp; ID</th>
          <th style="width:11%">Plugin</th>
          <th style="width:23%">Stránky</th>
          <th style="width:16%">Napojení</th>
          <th style="width:12%" class="smec-col-sent" title="Počet úspěšně importovaných kontaktů">Odesláno <span class="smec-period-label">(30d)</span></th>
          <th style="width:18%">Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $detectable as $form ) :
          $status      = $form['connected'];   // true | false | 'ignored'
          $is_ignored  = $status === 'ignored';
          $is_ok       = $status === true;
          $row_class   = $is_ignored ? 'smec-row-ignored' : ( $is_ok ? '' : 'smec-row-warn' );
          $pages        = (array) ( $form['pages'] ?? [] );
        ?>
          <tr class="<?php echo esc_attr( $row_class ); ?> <?php echo $is_ignored ? 'smec-ignored-row' : ''; ?>"
              data-form-type="<?php echo esc_attr( $form['type'] ); ?>"
              data-form-id="<?php echo esc_attr( $form['id'] ); ?>"
              style="<?php echo $is_ignored ? 'display:none;' : ''; ?>">

            <td>
              <strong><?php echo esc_html( $form['name'] ); ?></strong><br>
              <code style="font-size:0.75rem;color:#72777c;"><?php echo esc_html( $form['id'] ); ?></code>
            </td>

            <td>
              <span class="smec-plugin-badge"><?php echo esc_html( $form['plugin'] ); ?></span>
            </td>

            <td class="smec-form-pages-cell">
              <?php if ( empty( $pages ) ) : ?>
                <span class="smec-muted">—</span>
              <?php else : ?>
                <?php foreach ( array_slice( $pages, 0, 3 ) as $pg ) : ?>
                  <?php if ( ! empty( $pg['url'] ) ) : ?>
                    <a href="<?php echo esc_url( $pg['url'] ); ?>" target="_blank" class="smec-page-link"
                       title="<?php echo esc_attr( $pg['title'] ); ?>">
                      <?php echo esc_html( mb_substr( $pg['title'], 0, 30 ) ); ?>
                    </a><br>
                  <?php else : ?>
                    <span class="smec-muted"><?php echo esc_html( $pg['title'] ); ?></span><br>
                  <?php endif; ?>
                <?php endforeach; ?>
                <?php if ( count( $pages ) > 3 ) : ?>
                  <span class="smec-muted">+<?php echo count( $pages ) - 3; ?> dalšich</span>
                <?php endif; ?>
                <?php if ( ! empty( $form['edit_url'] ) ) : ?>
                  <a href="<?php echo esc_url( $form['edit_url'] ); ?>" target="_blank"
                     class="smec-muted" style="font-size:0.75rem;">Upravit formulář ↗</a>
                <?php endif; ?>
              <?php endif; ?>
            </td>

            <td>
              <?php if ( $is_ok ) : ?>
                <span class="smec-form-ok">✓ <?php echo esc_html( $form['mapping_name'] ?? 'Napojeno' ); ?></span>
              <?php elseif ( $is_ignored ) : ?>
                <span style="color:#72777c;">⊘ Ignorováno</span>
                <?php if ( ! empty( $form['ignore_reason'] ) ) : ?>
                  <br><span class="smec-muted" style="font-size:0.75rem;"><?php echo esc_html( $form['ignore_reason'] ); ?></span>
                <?php endif; ?>
              <?php else : ?>
                <span class="smec-form-warn">✗ Bez napojení</span>
              <?php endif; ?>
            </td>

            <td class="smec-col-sent">
              <span class="smec-sent-count smec-sent-loading" data-form-type="<?php echo esc_attr( $form['type'] ); ?>" data-form-id="<?php echo esc_attr( $form['id'] ); ?>">…</span>
            </td>

            <td class="smec-row-actions">
              <?php if ( $is_ignored ) : ?>
                <button type="button"
                  class="button-link smec-unignore-form"
                  data-type="<?php echo esc_attr( $form['type'] ); ?>"
                  data-id="<?php echo esc_attr( $form['id'] ); ?>">
                  Odignorovat
                </button>
              <?php else : ?>
                <?php if ( ! $is_ok ) : ?>
                  <a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-forms' ) ); ?>"
                     class="button button-small button-primary">+ Přidat napojení</a>
                  &nbsp;
                <?php else : ?>
                  <a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-forms' ) ); ?>"
                     class="button-link">Upravit →</a>
                  &nbsp;|&nbsp;
                <?php endif; ?>
                <button type="button"
                  class="button-link smec-ignore-form"
                  data-type="<?php echo esc_attr( $form['type'] ); ?>"
                  data-id="<?php echo esc_attr( $form['id'] ); ?>"
                  title="Označit formulář jako ignorovaný – nebude se zobrazovat jako chybějící napojení">
                  Ignorovat
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <p class="smec-muted" style="margin-top:12px;font-size:0.8rem;">
    Data jsou cachována na 5 minut. Klikněte na ↻ Znovu skenovat pro aktualizaci.
  </p>
</div>

<!-- Modal: měsíční přehled formuláře -->
<div id="smec-monthly-modal" class="smec-modal-overlay" style="display:none;">
  <div class="smec-modal-box">
    <div class="smec-modal-header">
      <h3 id="smec-monthly-modal-title">Měsíční přehled</h3>
      <button type="button" id="smec-monthly-modal-close" class="smec-modal-close">✕</button>
    </div>
    <div class="smec-modal-body">
      <canvas id="smec-chart-monthly" height="120"></canvas>
    </div>
  </div>
</div>

<!-- Předáme data pro JS -->
<script>
window.smecDiagData = {
  issues: <?php echo wp_json_encode( $issues ); ?>,
  counts: <?php echo wp_json_encode( $counts ); ?>
};
</script>
</div>
