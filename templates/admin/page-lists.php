<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** @var SMEC_Settings $settings */
$lists  = $settings->get_cached_lists();
$fields = $settings->get_cached_customfields();
?>
<div class="wrap smec-wrap">
<h1>Seznamy &amp; Vlastní pole</h1>

<!-- ── Návod ─────────────────────────────────────────── -->
<div class="smec-card smec-help-card smec-help-compact">
  <p>
    <strong>Seznamy kontaktů</strong> a <strong>vlastní pole</strong> se načítají přímo z vašeho SmartEmailing účtu přes API.
    Nejdříve musíte mít nakonfigurované <a href="<?php echo esc_url( admin_url( 'admin.php?page=smec-api' ) ); ?>">API připojení</a>.
  </p>
  <p>
    Jak vytvořit seznam nebo vlastní pole v SmartEmailingu:
    SmartEmailing → <em>Kontakty → Seznamy kontaktů</em> nebo <em>Nastavení → Vlastní pole</em>.
    Alternativně je vytvořte přímo zde pomocí tlačítek níže.
  </p>
  <p class="smec-help-links">
    <a href="https://app.smartemailing.cz" target="_blank" rel="noopener">→ Otevřít SmartEmailing ↗</a>
    &nbsp;&nbsp;
    <a href="https://app.smartemailing.cz/docs/api/v3/index.html" target="_blank" rel="noopener">API v3 dokumentace ↗</a>
  </p>
</div>

<div class="smec-two-col">

  <!-- ── Contact Lists ── -->
  <div class="smec-card">
    <div class="smec-card-header">
      <h2>Seznamy kontaktů</h2>
      <div>
        <button type="button" id="smec-fetch-lists" class="button">↻ Načíst ze SmartEmailingu</button>
      </div>
    </div>

    <div class="smec-card-body">
      <div id="smec-lists-static">
        <?php if ( $lists === null ) : ?>
          <p class="smec-muted smec-empty-state">Klikněte na "Načíst ze SmartEmailingu" pro zobrazení vašich seznamů.</p>
        <?php elseif ( empty( $lists ) ) : ?>
          <p class="smec-muted smec-empty-state">Žádné seznamy nenalezeny.</p>
        <?php else : ?>
          <table class="wp-list-table widefat striped smec-data-table">
            <thead><tr><th>ID</th><th>Název</th></tr></thead>
            <tbody>
              <?php foreach ( $lists as $list ) : ?>
                <tr>
                  <td><code><?php echo esc_html( $list['id'] ?? '—' ); ?></code></td>
                  <td><?php echo esc_html( $list['name'] ?? $list['publicname'] ?? '—' ); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
      <div id="smec-lists-container"></div>
    </div>

    <div class="smec-card-footer">
      <h3>Vytvořit nový seznam</h3>
      <div class="smec-inline-form">
        <input type="text" id="smec-new-list-name" placeholder="Název nového seznamu" class="regular-text">
        <button type="button" id="smec-create-list" class="button button-secondary">Vytvořit</button>
        <span id="smec-create-list-result" class="smec-inline-result"></span>
      </div>
    </div>
  </div>

  <!-- ── Custom Fields ── -->
  <div class="smec-card">
    <div class="smec-card-header">
      <h2>Vlastní pole</h2>
      <div>
        <button type="button" id="smec-fetch-fields" class="button">↻ Načíst ze SmartEmailingu</button>
      </div>
    </div>

    <div class="smec-card-body">
      <div id="smec-fields-static">
        <?php if ( $fields === null ) : ?>
          <p class="smec-muted smec-empty-state">Klikněte na "Načíst ze SmartEmailingu" pro zobrazení vlastních polí.</p>
        <?php elseif ( empty( $fields ) ) : ?>
          <p class="smec-muted smec-empty-state">Žádná vlastní pole nenalezena.</p>
        <?php else : ?>
          <table class="wp-list-table widefat striped smec-data-table">
            <thead><tr><th>ID</th><th>Název</th><th>Typ</th></tr></thead>
            <tbody>
              <?php foreach ( $fields as $field ) : ?>
                <tr>
                  <td><code><?php echo esc_html( $field['id'] ?? '—' ); ?></code></td>
                  <td><?php echo esc_html( $field['name'] ?? '—' ); ?></td>
                  <td><?php echo esc_html( $field['type'] ?? '—' ); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
      <div id="smec-fields-container"></div>
    </div>

    <div class="smec-card-footer">
      <h3>Vytvořit vlastní pole</h3>
      <div class="smec-inline-form smec-create-cf-form">
        <input type="text" id="smec-cf-name"  placeholder="Název pole" class="regular-text">
        <select id="smec-cf-type">
          <option value="text">Text</option>
          <option value="date">Datum</option>
          <option value="number">Číslo</option>
          <option value="checkbox">Checkbox</option>
          <option value="radio">Radio</option>
        </select>
        <button type="button" id="smec-create-field" class="button button-secondary">Vytvořit</button>
        <span id="smec-create-field-result" class="smec-inline-result"></span>
      </div>
    </div>
  </div>

</div>

<div class="smec-card">
  <p class="smec-muted">Data se ukládají do cache na 10 minut.
    <button type="button" id="smec-refresh-cache" class="button-link">Vymazat cache</button>
  </p>
</div>

</div>
