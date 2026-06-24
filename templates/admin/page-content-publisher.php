<?php
/**
 * Admin stránka – Content Publisher (nový článek → SE automation event).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$cfg       = $settings->get_content_publisher();
$pub_types = get_post_types( [ 'public' => true ], 'objects' );
unset( $pub_types['attachment'] );
$categories = get_categories( [ 'hide_empty' => false ] );
$se_lists   = $settings->get_cached_lists() ?? [];

$saved_msg = '';
if ( isset( $_GET['smec-saved'] ) ) {
	$saved_msg = 'Nastavení uloženo.';
}
?>

<div class="wrap">
	<h1>📢 Content Publisher – nový obsah → SmartEmailing</h1>
	<p style="max-width:680px;color:#50575e">
		Při publikování nového článku (nebo jiného post type) automaticky spustí
		<strong>custom automation event</strong> v SmartEmailingu. SE automatizace musí mít
		jako trigger nastaven <em>Custom event</em> se shodným názvem.
	</p>

	<?php if ( $saved_msg ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $saved_msg ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'smec_save_content_publisher', 'smec_nonce' ); ?>
		<input type="hidden" name="action" value="smec_save_content_publisher">

		<!-- Zapnutí -->
		<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px 24px;max-width:720px;margin-bottom:20px">
			<h2 style="margin-top:0">Základní nastavení</h2>
			<table class="form-table" role="presentation">

				<tr>
					<th scope="row">Aktivovat modul</th>
					<td>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( $cfg['enabled'] ); ?>>
							Zapnout Content Publisher
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">Sledované post types</th>
					<td>
						<fieldset>
							<?php foreach ( $pub_types as $pt ) : ?>
							<label style="display:block;margin-bottom:4px">
								<input type="checkbox" name="post_types[]"
									value="<?php echo esc_attr( $pt->name ); ?>"
									<?php checked( in_array( $pt->name, (array) $cfg['post_types'], true ) ); ?>>
								<?php echo esc_html( $pt->labels->name . ' (' . $pt->name . ')' ); ?>
							</label>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row">Filtr kategorií <small style="font-weight:400">(jen pro Příspěvky)</small></th>
					<td>
						<?php if ( $categories ) : ?>
						<fieldset style="max-height:160px;overflow-y:auto;border:1px solid #ddd;padding:8px 12px;border-radius:3px">
							<?php foreach ( $categories as $cat ) : ?>
							<label style="display:block;margin-bottom:3px">
								<input type="checkbox" name="categories[]"
									value="<?php echo esc_attr( $cat->term_id ); ?>"
									<?php checked( in_array( $cat->term_id, (array) $cfg['categories'], true ) ); ?>>
								<?php echo esc_html( $cat->name ); ?>
							</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">Necháš-li vše odškrtnuté, odesílá se pro všechny kategorie.</p>
						<?php else : ?>
						<p class="description">Žádné kategorie nenalezeny.</p>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row">Zpoždění odeslání</th>
					<td>
						<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
							<label style="display:flex;align-items:center;gap:4px">
								<input type="number" name="delay_days" min="0" max="30"
									value="<?php echo esc_attr( $cfg['delay_days'] ?? 0 ); ?>"
									style="width:60px">
								<span>dní</span>
							</label>
							<label style="display:flex;align-items:center;gap:4px">
								<input type="number" name="delay_hours" min="0" max="23"
									value="<?php echo esc_attr( $cfg['delay_hours'] ?? 0 ); ?>"
									style="width:55px">
								<span>hodin</span>
							</label>
							<label style="display:flex;align-items:center;gap:4px">
								<input type="number" name="delay_minutes" min="0" max="59"
									value="<?php echo esc_attr( $cfg['delay_minutes'] ?? 0 ); ?>"
									style="width:55px">
								<span>minut</span>
							</label>
						</div>
						<?php
						$total = $settings->get_content_publisher_delay_minutes();
						if ( $total > 0 ) :
							$d = floor( $total / 1440 );
							$h = floor( ( $total % 1440 ) / 60 );
							$m = $total % 60;
							$parts = [];
							if ( $d ) $parts[] = $d . ' dní';
							if ( $h ) $parts[] = $h . ' hodin';
							if ( $m ) $parts[] = $m . ' minut';
						?>
						<p class="description">Celkem: <strong><?php echo esc_html( implode( ' ', $parts ) ); ?></strong> po publikování (přes WP Cron).</p>
						<?php else : ?>
						<p class="description">0 = odeslat <strong>okamžitě</strong> při publikování.</p>
						<?php endif; ?>

						<?php if ( function_exists( 'as_schedule_single_action' ) ) : ?>
						<p class="description" style="color:#2d7738">✓ <strong>Action Scheduler</strong> detekován (WooCommerce) – přesné načasování bez závislosti na návštěvnících.</p>
						<?php else : ?>
						<div style="margin-top:8px;padding:10px 14px;background:#fff8e5;border-left:3px solid #996800;border-radius:2px;font-size:12px">
							⚠ <strong>WP Cron</strong> – potřebuje HTTP request pro spuštění. Pro přesné načasování nastav system cron na serveru:<br>
							<code style="display:block;margin-top:6px;background:#f6f7f7;padding:4px 8px;border-radius:2px">* * * * * wget -q -O - "<?php echo esc_url( site_url( '/wp-cron.php?doing_wp_cron' ) ); ?>" &gt; /dev/null 2&gt;&amp;1</code>
						</div>
						<?php endif; ?>
					</td>
				</tr>

			</table>
		</div>

		<!-- SE nastavení -->
		<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px 24px;max-width:720px;margin-bottom:20px">
			<h2 style="margin-top:0">SmartEmailing – propojení</h2>
			<table class="form-table" role="presentation">

				<tr>
					<th scope="row">Název custom eventu</th>
					<td>
						<input type="text" name="event_name" class="regular-text"
							value="<?php echo esc_attr( $cfg['event_name'] ); ?>"
							placeholder="new_article_published">
						<p class="description">
							Musí přesně odpovídat názvu triggeru <em>Custom event</em> v SE automatizaci.
							Pouze malá písmena, čísla, podtržítko.
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">Trigger email</th>
					<td>
						<input type="email" name="trigger_email" class="regular-text"
							value="<?php echo esc_attr( $cfg['trigger_email'] ); ?>"
							placeholder="tvuj@email.cz">
						<p class="description">Tvůj email nebo dedikovaný trigger kontakt. Použij pokud chceš event spustit pro <strong>jednoho příjemce</strong> (nebo pokud SE automatizace rozesílá sama všem).</p>
					</td>
				</tr>

				<tr>
					<th scope="row">Seznam kontaktů (segment)</th>
					<td>
						<input type="number" name="contact_list_id" min="0"
							value="<?php echo esc_attr( $cfg['contact_list_id'] ); ?>"
							style="width:120px" placeholder="ID seznamu">
						<p class="description">
							Volitelné. Zadej ID SE seznamu – event se odešle <strong>všem kontaktům v tomto seznamu</strong> (max 500/request, dávkuje se automaticky).<br>
							Lze kombinovat s Trigger emailem (příjemci se sloučí).
						</p>
					</td>
				</tr>

			</table>
		</div>

		<!-- Datový payload -->
		<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:20px 24px;max-width:720px;margin-bottom:24px">
			<h2 style="margin-top:0">Data odesílaná do SE (proměnné v šabloně)</h2>
			<p style="color:#50575e;margin-top:0">
				Zaškrtnuté hodnoty budou dostupné v SE emailové šabloně přes:<br>
				<code>{{ metadata.event.attributes.post_title }}</code>, <code>{{ metadata.event.attributes.post_url }}</code> atd.<br>
				Nebo celé: <code>{{ metadata.event.attributes | json_encode(128) }}</code>
			</p>
			<table class="form-table" role="presentation">

				<tr>
					<th scope="row">Pole pro šablonu</th>
					<td>
						<fieldset>
							<?php
							$fields = [
								'send_title'     => [ 'Titulek článku', 'post_title' ],
								'send_url'       => [ 'URL článku', 'post_url' ],
								'send_excerpt'   => [ 'Perex / výňatek', 'post_excerpt' ],
								'send_thumbnail' => [ 'URL náhledového obrázku', 'post_thumbnail' ],
								'send_author'    => [ 'Autor', 'post_author' ],
								'send_category'  => [ 'Kategorie (jen Příspěvky)', 'post_category' ],
							];
							foreach ( $fields as $key => [ $label, $var ] ) :
							?>
							<label style="display:block;margin-bottom:6px">
								<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1"
									<?php checked( $cfg[ $key ] ?? 0 ); ?>>
								<?php echo esc_html( $label ); ?>
								<code style="font-size:11px;margin-left:4px"><?php echo esc_html( '{' . $var . '}' ); ?></code>
							</label>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>

			</table>
		</div>

		<p class="submit">
			<input type="submit" class="button button-primary button-large" value="Uložit nastavení">
		</p>
	</form>

	<!-- Nápověda pro SE nastavení -->
	<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:16px 20px;max-width:720px;margin-top:8px">
		<h3 style="margin-top:0">Jak nastavit automatizaci v SmartEmailingu</h3>
		<ol style="margin:0;padding-left:20px;line-height:1.8">
			<li>V SE otevři <strong>Automatizace</strong> → <strong>Vytvořit automatizaci</strong></li>
			<li>Jako trigger zvol <strong>Custom event</strong></li>
			<li>Název eventu nastav přesně na: <code><?php echo esc_html( $cfg['event_name'] ?: 'new_article_published' ); ?></code></li>
			<li>V obsahu automatizace přidej akci <strong>Odeslat email</strong></li>
			<li>V emailové šabloně použij proměnné jako <code>{post_title}</code>, <code>{post_url}</code> atd.</li>
			<li>Trigger email v nastavení výše: <strong><?php echo esc_html( $cfg['trigger_email'] ?: '— nevyplněno —' ); ?></strong> – musí existovat jako kontakt v SE</li>
		</ol>
	</div>

	<!-- Test / ruční odeslání -->
	<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:16px 20px;max-width:720px;margin-top:16px">
		<h3 style="margin-top:0">Test – ruční odeslání eventu</h3>
		<p style="color:#50575e">Zadej ID konkrétního postu pro okamžité odeslání eventu (simulace publish).</p>
		<div style="display:flex;gap:10px;align-items:center">
			<input type="number" id="smec-pub-test-id" placeholder="Post ID" min="1" style="width:120px">
			<button type="button" id="smec-pub-test-btn" class="button button-large">▶ Odeslat test</button>
			<span id="smec-pub-test-result" style="font-size:13px"></span>
		</div>
	</div>
</div>

<script>
(function () {
	var btn    = document.getElementById( 'smec-pub-test-btn' );
	var idEl   = document.getElementById( 'smec-pub-test-id' );
	var result = document.getElementById( 'smec-pub-test-result' );

	if ( ! btn ) return;

	btn.addEventListener( 'click', function () {
		var postId = parseInt( idEl.value, 10 );
		if ( ! postId ) { result.textContent = '✗ Zadej platné Post ID.'; return; }

		btn.disabled    = true;
		btn.textContent = '⏳ Odesílám…';
		result.textContent = '';

		var fd = new FormData();
		fd.append( 'action', 'smec_content_publisher_resend' );
		fd.append( 'nonce',   '<?php echo esc_js( wp_create_nonce( 'smec_pub_resend' ) ); ?>' );
		fd.append( 'post_id', postId );

		fetch( '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
			method: 'POST', credentials: 'same-origin', body: fd
		} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( d ) {
			btn.disabled    = false;
			btn.textContent = '▶ Odeslat test';
			if ( d.success ) {
				result.innerHTML = '<span style="color:#2d7738">✓ Event odeslán pro post ID ' + postId + '.</span>';
			} else {
				result.innerHTML = '<span style="color:#d63638">✗ ' + ( d.data && d.data.message ? d.data.message : 'Chyba.' ) + '</span>';
			}
		} )
		.catch( function () {
			btn.disabled    = false;
			btn.textContent = '▶ Odeslat test';
			result.innerHTML = '<span style="color:#d63638">✗ Chyba sítě.</span>';
		} );
	} );
}());
</script>
