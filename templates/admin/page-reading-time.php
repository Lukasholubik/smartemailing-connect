<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** @var SMEC_Settings $settings */
$presets    = $settings->get_rt_presets();
$post_types = get_post_types( [ 'public' => true ], 'objects' );
?>
<div class="wrap smec-wrap">
<h1>Doba čtení
  <button type="button" id="rt-add-preset" class="page-title-action">+ Nový preset</button>
</h1>

<!-- ── Návod ─────────────────────────────────────────── -->
<div class="smec-card smec-help-card smec-help-compact">
  <p>
    <strong>Jak zobrazit dobu čtení:</strong>
    Každý preset má vlastní slug a vizuální nastavení. Použijte shortcode s parametrem <code>preset</code>:
  </p>
  <ul class="smec-help-ul">
    <li><code>[smart_reading_time]</code> – použije preset se slugem <strong>default</strong></li>
    <li><code>[smart_reading_time preset="archiv"]</code> – použije preset se slugem <strong>archiv</strong></li>
    <li><code>[smart_reading_time preset="archiv" label="Čtení:" suffix="min"]</code> – přepíše label/suffix</li>
    <li><strong>Automatické vložení:</strong> zapněte v nastavení presetu – přidá se před/za obsah na vybraných post types</li>
    <li><strong>Gutenberg blok:</strong> SmartEmailing Connect / Reading Time – vyberte preset v postranním panelu bloku</li>
    <li><strong>Vypnout na konkrétním příspěvku:</strong> přidejte vlastní pole <code>_smec_disable_reading_time</code> = <code>1</code></li>
  </ul>
</div>

<!-- ═══════════════════════════════════════════
     SEZNAM PRESETŮ
═══════════════════════════════════════════ -->
<div id="rt-presets-list">
  <?php if ( empty( $presets ) ) : ?>
    <div class="smec-empty-state smec-card">
      <p>Žádné presety. Klikněte na <strong>+ Nový preset</strong>.</p>
    </div>
  <?php else : ?>
    <table class="wp-list-table widefat fixed striped smec-rt-table">
      <thead>
        <tr>
          <th>Název</th>
          <th>Slug (shortcode)</th>
          <th>WPM</th>
          <th>Auto-insert</th>
          <th>Post types</th>
          <th>Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $presets as $p ) : ?>
          <tr data-id="<?php echo esc_attr( $p['id'] ); ?>">
            <td><strong><?php echo esc_html( $p['name'] ?? '—' ); ?></strong></td>
            <td>
              <code>[smart_reading_time<?php echo $p['slug'] !== 'default' ? ' preset="' . esc_attr( $p['slug'] ) . '"' : ''; ?>]</code>
            </td>
            <td><?php echo (int) ( $p['wpm'] ?? 200 ); ?></td>
            <td><?php echo ! empty( $p['auto_insert'] ) ? '<span class="smec-badge smec-badge-active">Ano</span>' : '<span class="smec-badge smec-badge-inactive">Ne</span>'; ?></td>
            <td class="smec-muted"><?php echo esc_html( implode( ', ', (array) ( $p['post_types'] ?? [] ) ) ); ?></td>
            <td class="smec-row-actions">
              <button class="button-link rt-edit-preset" data-id="<?php echo esc_attr( $p['id'] ); ?>">Upravit</button> |
              <button class="button-link rt-duplicate-preset" data-id="<?php echo esc_attr( $p['id'] ); ?>">Duplikovat</button> |
              <button class="button-link rt-delete-preset smec-danger" data-id="<?php echo esc_attr( $p['id'] ); ?>">Smazat</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════
     EDITOR PRESETU (skrytý)
═══════════════════════════════════════════ -->
<div id="rt-preset-editor" style="display:none;">
<div class="smec-card">
  <div class="smec-card-header">
    <h2 id="rt-editor-title">Nový preset</h2>
    <button type="button" id="rt-cancel-edit" class="button">Zpět na seznam</button>
  </div>

  <input type="hidden" id="rt-preset-id" value="">

  <div class="smec-tabs">
    <button type="button" class="smec-tab active" data-tab="rt-basic">Základní</button>
    <button type="button" class="smec-tab" data-tab="rt-visual">Vizuální</button>
    <button type="button" class="smec-tab" data-tab="rt-icon">Ikona</button>
    <button type="button" class="smec-tab" data-tab="rt-auto">Auto-insert</button>
  </div>

  <!-- TAB: Základní -->
  <div class="smec-tab-content active" id="smec-tab-rt-basic">
    <table class="form-table smec-form-table">
      <tr>
        <th><label for="rt-name">Název presetu</label></th>
        <td>
          <input type="text" id="rt-name" class="regular-text" placeholder="Např. Archiv, Single, Výchozí">
          <p class="description">Interní název jen pro přehled v administraci.</p>
        </td>
      </tr>
      <tr>
        <th><label for="rt-slug">Slug (pro shortcode)</label></th>
        <td>
          <input type="text" id="rt-slug" class="regular-text" placeholder="default">
          <p class="description">
            Použijte v shortcode: <code>[smart_reading_time preset="<span class="rt-slug-preview">default</span>"]</code><br>
            Pouze malá písmena, čísla a pomlčky. Preset <strong>default</strong> se použije bez parametru.
          </p>
        </td>
      </tr>
      <tr>
        <th><label for="rt-wpm">Slov za minutu (WPM)</label></th>
        <td>
          <input type="number" id="rt-wpm" class="small-text" min="50" max="1000" value="200">
          <p class="description">Průměrný čtenář: 200 WPM. Vyšší = kratší odhadovaný čas.</p>
        </td>
      </tr>
      <tr>
        <th><label for="rt-label">Popisek</label></th>
        <td><input type="text" id="rt-label" class="regular-text" placeholder="Doba čtení:"></td>
      </tr>
      <tr>
        <th><label for="rt-suffix">Suffix</label></th>
        <td><input type="text" id="rt-suffix" class="small-text" placeholder="min"></td>
      </tr>
      <tr>
        <th><label for="rt-wrapper-tag">Wrapper tag</label></th>
        <td>
          <select id="rt-wrapper-tag">
            <option value="span">&lt;span&gt;</option>
            <option value="div">&lt;div&gt;</option>
            <option value="p">&lt;p&gt;</option>
            <option value="strong">&lt;strong&gt;</option>
          </select>
        </td>
      </tr>
      <tr>
        <th><label for="rt-display">Zobrazení</label></th>
        <td>
          <select id="rt-display">
            <option value="inline">inline</option>
            <option value="block">block</option>
          </select>
        </td>
      </tr>
      <tr>
        <th><label for="rt-css-class">CSS třída</label></th>
        <td>
          <input type="text" id="rt-css-class" class="regular-text" placeholder="moje-trida">
          <p class="description">Přidá se ke kořenovému elementu spolu s <code>smec-reading-time smec-rt-<em>slug</em></code>.</p>
        </td>
      </tr>
    </table>
  </div>

  <!-- TAB: Vizuální -->
  <div class="smec-tab-content" id="smec-tab-rt-visual">
    <p class="smec-muted" style="margin-bottom:16px;">
      CSS se generuje izolovaně pro tento preset pod selektorem <code>.smec-rt-<em>slug</em></code>. Nepřepisuje globální šablonu.
    </p>
    <div class="smec-two-col">
      <table class="form-table smec-form-table">
        <tr>
          <th><label for="rt-v-color">Barva textu</label></th>
          <td class="smec-color-row">
            <input type="color" id="rt-v-color" data-hex="rt-v-color-hex">
            <input type="text" id="rt-v-color-hex" class="small-text smec-hex-input" placeholder="#333333">
          </td>
        </tr>
        <tr>
          <th><label for="rt-v-icon-color">Barva ikony</label></th>
          <td class="smec-color-row">
            <input type="color" id="rt-v-icon-color" data-hex="rt-v-icon-color-hex">
            <input type="text" id="rt-v-icon-color-hex" class="small-text smec-hex-input" placeholder="#333333">
          </td>
        </tr>
        <tr>
          <th><label for="rt-v-bg">Pozadí</label></th>
          <td class="smec-color-row">
            <input type="color" id="rt-v-bg" data-hex="rt-v-bg-hex">
            <input type="text" id="rt-v-bg-hex" class="small-text smec-hex-input" placeholder="#ffffff">
          </td>
        </tr>
        <tr>
          <th><label for="rt-v-font-size">Velikost písma</label></th>
          <td><input type="text" id="rt-v-font-size" class="small-text" placeholder="14px"></td>
        </tr>
        <tr>
          <th><label for="rt-v-font-weight">Font weight</label></th>
          <td><input type="text" id="rt-v-font-weight" class="small-text" placeholder="400"></td>
        </tr>
      </table>
      <table class="form-table smec-form-table">
        <tr>
          <th><label for="rt-v-padding">Padding</label></th>
          <td><input type="text" id="rt-v-padding" class="small-text" placeholder="4px 8px"></td>
        </tr>
        <tr>
          <th><label for="rt-v-border-radius">Border radius</label></th>
          <td><input type="text" id="rt-v-border-radius" class="small-text" placeholder="4px"></td>
        </tr>
        <tr>
          <th><label for="rt-v-text-align">Zarovnání</label></th>
          <td>
            <select id="rt-v-text-align">
              <option value="">— výchozí —</option>
              <option value="left">left</option>
              <option value="center">center</option>
              <option value="right">right</option>
            </select>
          </td>
        </tr>
        <tr>
          <th><label for="rt-v-icon-size">Velikost ikony</label></th>
          <td><input type="text" id="rt-v-icon-size" class="small-text" placeholder="16px"></td>
        </tr>
        <tr>
          <th><label for="rt-v-custom-css">Vlastní CSS</label></th>
          <td>
            <textarea id="rt-v-custom-css" rows="4" class="large-text code" placeholder=".smec-reading-time { ... }"></textarea>
            <p class="description">Platné třídy: <code>.smec-reading-time</code>, <code>.smec-rt-icon</code>, <code>.smec-rt-label</code>, <code>.smec-rt-value</code></p>
          </td>
        </tr>
      </table>
    </div>

    <div class="smec-rt-preview-wrap">
      <h3>Náhled</h3>
      <div class="smec-reading-time-preview">
        <span class="smec-reading-time" id="rt-live-preview">
          <svg class="smec-rt-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span class="smec-rt-label">Doba čtení: </span><span class="smec-rt-value">5</span><span class="smec-rt-suffix"> min</span>
        </span>
      </div>
    </div>
  </div>

  <!-- TAB: Ikona -->
  <div class="smec-tab-content" id="smec-tab-rt-icon">
    <table class="form-table smec-form-table">
      <tr>
        <th><label>Typ ikony</label></th>
        <td>
          <label><input type="radio" name="rt-icon-type" id="rt-icon-builtin" value="clock" checked> Vestavěná (hodiny)</label>
          <br>
          <label><input type="radio" name="rt-icon-type" id="rt-icon-custom" value="custom"> Vlastní SVG</label>
          <label style="margin-left:12px;"><input type="checkbox" id="rt-show-icon" value="1" checked> Zobrazit ikonu</label>
        </td>
      </tr>
      <tr id="rt-builtin-preview-row">
        <th>Náhled vestavěné ikony</th>
        <td>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:24px;height:24px;" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <p class="description">Ikona hodin – odpovídá standardnímu zobrazení doby čtení.</p>
        </td>
      </tr>
      <tr id="rt-custom-svg-row" style="display:none;">
        <th><label for="rt-custom-svg">Vlastní SVG kód</label></th>
        <td>
          <textarea id="rt-custom-svg" rows="8" class="large-text code" placeholder='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">...</svg>'></textarea>
          <p class="description">
            Vložte celý SVG kód. Plugin automaticky přidá třídu <code>smec-rt-icon</code> a <code>aria-hidden="true"</code>.<br>
            <strong>Bezpečnost:</strong> <code>&lt;script&gt;</code> tagy a <code>on*</code> atributy jsou automaticky odstraněny.<br>
            Barvu ikony lze ovládat přes CSS (viz záložka Vizuální) pomocí vlastnosti <code>stroke</code> nebo <code>fill</code>.
          </p>
          <div id="rt-svg-preview" style="margin-top:12px;padding:12px;background:#f6f7f7;border-radius:4px;min-height:40px;display:none;">
            <strong>Náhled:</strong><br>
          </div>
        </td>
      </tr>
    </table>
  </div>

  <!-- TAB: Auto-insert -->
  <div class="smec-tab-content" id="smec-tab-rt-auto">
    <table class="form-table smec-form-table">
      <tr>
        <th><label for="rt-auto-insert">Automatické vložení</label></th>
        <td>
          <label class="smec-toggle">
            <input type="checkbox" id="rt-auto-insert" value="1">
            <span class="smec-toggle-slider"></span>
          </label>
          <p class="description">Doba čtení se automaticky vloží do obsahu příspěvků vybraného post type. Není potřeba shortcode.</p>
        </td>
      </tr>
      <tr id="rt-auto-pos-row">
        <th><label>Pozice</label></th>
        <td>
          <label><input type="radio" name="rt-auto-position" value="before" checked> Před obsahem</label>
          <label style="margin-left:16px;"><input type="radio" name="rt-auto-position" value="after"> Za obsahem</label>
        </td>
      </tr>
      <tr>
        <th><label>Post types</label></th>
        <td>
          <?php foreach ( $post_types as $pt ) : ?>
            <label style="display:inline-block;margin-right:12px;">
              <input type="checkbox" class="rt-pt-checkbox" name="rt-post-types[]" value="<?php echo esc_attr( $pt->name ); ?>">
              <?php echo esc_html( $pt->labels->singular_name ); ?>
              <span class="smec-muted">(<?php echo esc_html( $pt->name ); ?>)</span>
            </label>
          <?php endforeach; ?>
          <p class="description">Na vybraných post types se presetu automaticky přidá do obsahu.</p>
        </td>
      </tr>
    </table>
  </div>

  <div class="smec-actions">
    <button type="button" id="rt-save-preset" class="button button-primary">Uložit preset</button>
    <button type="button" id="rt-cancel-edit2" class="button">Zrušit</button>
    <span id="rt-save-result" class="smec-inline-result"></span>
  </div>
</div>
</div><!-- /rt-preset-editor -->

</div>
