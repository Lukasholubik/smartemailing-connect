<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_ReadingTime {

	private SMEC_Settings $settings;

	public function __construct( SMEC_Settings $settings ) {
		$this->settings = $settings;
	}

	public function register(): void {
		add_shortcode( 'smart_reading_time', [ $this, 'shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'wp_head', [ $this, 'inject_dynamic_css' ], 50 );
		add_filter( 'the_content', [ $this, 'auto_insert' ], 10, 1 );
		add_action( 'init', [ $this, 'register_block' ] );
	}

	// -------------------------------------------------------------------------
	// Shortcode: [smart_reading_time preset="slug" post_id="" label="" ...]
	// -------------------------------------------------------------------------
	public function shortcode( array $atts ): string {
		$atts = shortcode_atts( [
			'preset'  => 'default',
			'post_id' => get_the_ID(),
			'label'   => null,
			'suffix'  => null,
			'icon'    => null,
			'class'   => null,
		], $atts, 'smart_reading_time' );

		$preset = $this->settings->get_rt_preset_by_slug( $atts['preset'] );
		if ( ! $preset ) return '';

		// Shortcode atributy přepisují preset (pouze pokud byly explicitně zadány)
		if ( $atts['label']  !== null ) $preset['label']     = $atts['label'];
		if ( $atts['suffix'] !== null ) $preset['suffix']    = $atts['suffix'];
		if ( $atts['icon']   !== null ) $preset['show_icon'] = (bool) $atts['icon'];
		if ( $atts['class']  !== null ) $preset['css_class'] = sanitize_html_class( $atts['class'] );

		$post_id = (int) $atts['post_id'];
		if ( ! $post_id ) return '';

		$minutes = $this->calculate( $post_id, (int) ( $preset['wpm'] ?? 200 ) );
		if ( $minutes === null ) return '';

		return $this->render( $minutes, $preset );
	}

	// -------------------------------------------------------------------------
	// Auto-insert into content (všechny presety s auto_insert = 1)
	// -------------------------------------------------------------------------
	public function auto_insert( string $content ): string {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) return $content;
		if ( (int) get_post_meta( get_the_ID(), '_smec_disable_reading_time', true ) ) return $content;

		foreach ( $this->settings->get_rt_presets() as $preset ) {
			if ( empty( $preset['auto_insert'] ) ) continue;
			$types = (array) ( $preset['post_types'] ?? [ 'post' ] );
			if ( ! in_array( get_post_type(), $types, true ) ) continue;

			$minutes = $this->calculate( get_the_ID(), (int) ( $preset['wpm'] ?? 200 ) );
			if ( $minutes === null ) continue;

			$html = $this->render( $minutes, $preset );
			if ( ( $preset['auto_position'] ?? 'before' ) === 'after' ) {
				$content .= $html;
			} else {
				$content = $html . $content;
			}
		}

		return $content;
	}

	// -------------------------------------------------------------------------
	// Calculation
	// -------------------------------------------------------------------------
	public function calculate( int $post_id, int $wpm = 200 ): ?int {
		$post = get_post( $post_id );
		if ( ! $post ) return null;

		$content = $post->post_content;
		$content = do_shortcode( $content );
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
		$content = preg_replace( '/\s+/u', ' ', $content );
		$content = trim( $content );

		if ( $content === '' ) return 1;

		$words = preg_split( '/\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY );
		$count = $words ? count( $words ) : 0;
		$wpm   = max( 1, $wpm );

		return max( 1, (int) ceil( $count / $wpm ) );
	}

	// -------------------------------------------------------------------------
	// Render HTML
	// -------------------------------------------------------------------------
	private function render( int $minutes, array $preset ): string {
		$tag        = in_array( $preset['wrapper_tag'] ?? 'span', [ 'span', 'div', 'p', 'strong' ], true ) ? $preset['wrapper_tag'] : 'span';
		$slug       = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $preset['slug'] ?? 'default' ) );
		$base_class = 'smec-reading-time smec-rt-' . $slug;
		if ( ! empty( $preset['css_class'] ) ) {
			$base_class .= ' ' . sanitize_html_class( $preset['css_class'] );
		}

		$label  = esc_html( $preset['label']  ?? '' );
		$suffix = esc_html( $preset['suffix'] ?? 'min' );
		$icon_html = '';
		if ( ! empty( $preset['show_icon'] ) ) {
			$icon_html = $this->get_icon_svg( $preset );
		}

		ob_start();
		echo '<' . esc_attr( $tag ) . ' class="' . esc_attr( $base_class ) . '">';
		if ( $icon_html ) {
			// SVG je sanitizovaný při uložení (admin), nebo je to hardcoded builtin
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $icon_html;
		}
		if ( $label ) echo '<span class="smec-rt-label">' . $label . ' </span>';
		echo '<span class="smec-rt-value">' . esc_html( $minutes ) . '</span>';
		if ( $suffix ) echo '<span class="smec-rt-suffix"> ' . $suffix . '</span>';
		echo '</' . esc_attr( $tag ) . '>';
		return ob_get_clean();
	}

	private function get_icon_svg( array $preset ): string {
		if ( ( $preset['icon'] ?? 'clock' ) === 'custom' && ! empty( $preset['custom_svg'] ) ) {
			// Přidat class ke kořenovému <svg> elementu
			return $this->add_class_to_svg( $preset['custom_svg'], 'smec-rt-icon' );
		}

		// Vestavěná ikona – hodiny
		return '<svg class="smec-rt-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
	}

	private function add_class_to_svg( string $svg, string $class ): string {
		// Přidat smec-rt-icon class ke kořenovému <svg> tagu
		if ( preg_match( '/<svg([^>]*class=["\'])([^"\']*)/i', $svg ) ) {
			// Přidat class k existujícímu atributu
			$svg = preg_replace_callback(
				'/(<svg[^>]*class=["\'])([^"\']*)/i',
				fn( $m ) => $m[1] . $class . ' ' . $m[2],
				$svg,
				1
			);
		} else {
			// Vložit nový class atribut
			$svg = preg_replace( '/<svg/i', '<svg class="' . esc_attr( $class ) . '"', $svg, 1 );
		}
		// Přidat aria-hidden pokud není přítomné
		if ( ! str_contains( $svg, 'aria-hidden' ) ) {
			$svg = preg_replace( '/<svg/i', '<svg aria-hidden="true"', $svg, 1 );
		}
		return $svg;
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------
	public function enqueue_styles(): void {
		if ( ! is_singular() && ! is_archive() ) return;
		wp_enqueue_style(
			'smec-reading-time',
			SMEC_PLUGIN_URL . 'assets/frontend/css/reading-time.css',
			[],
			SMEC_VERSION
		);
	}

	public function inject_dynamic_css(): void {
		$css = '';
		foreach ( $this->settings->get_rt_presets() as $preset ) {
			$css .= $this->build_preset_css( $preset );
		}
		if ( $css ) {
			echo '<style id="smec-reading-time-dynamic">' . $css . "</style>\n";
		}
	}

	private function build_preset_css( array $preset ): string {
		$slug     = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $preset['slug'] ?? 'default' ) );
		$selector = '.smec-rt-' . $slug;
		$rules    = [];

		if ( ! empty( $preset['color'] ) )         $rules[] = 'color:' . esc_attr( $preset['color'] ) . ';';
		if ( ! empty( $preset['font_size'] ) )      $rules[] = 'font-size:' . esc_attr( $preset['font_size'] ) . ';';
		if ( ! empty( $preset['font_weight'] ) )    $rules[] = 'font-weight:' . esc_attr( $preset['font_weight'] ) . ';';
		if ( ! empty( $preset['padding'] ) )        $rules[] = 'padding:' . esc_attr( $preset['padding'] ) . ';';
		if ( ! empty( $preset['border_radius'] ) )  $rules[] = 'border-radius:' . esc_attr( $preset['border_radius'] ) . ';';
		if ( ! empty( $preset['background'] ) )     $rules[] = 'background:' . esc_attr( $preset['background'] ) . ';';
		if ( ! empty( $preset['text_align'] ) )     $rules[] = 'text-align:' . esc_attr( $preset['text_align'] ) . ';';
		if ( ( $preset['display'] ?? '' ) === 'block' ) $rules[] = 'display:block;';

		$icon_rules = [];
		if ( ! empty( $preset['icon_color'] ) )  $icon_rules[] = 'color:' . esc_attr( $preset['icon_color'] ) . ';stroke:' . esc_attr( $preset['icon_color'] ) . ';';
		if ( ! empty( $preset['icon_size'] ) )   $icon_rules[] = 'width:' . esc_attr( $preset['icon_size'] ) . ';height:' . esc_attr( $preset['icon_size'] ) . ';';

		$out = '';
		if ( $rules ) {
			$out .= $selector . '{' . implode( '', $rules ) . '}';
		}
		if ( $icon_rules ) {
			$out .= $selector . ' .smec-rt-icon{' . implode( '', $icon_rules ) . '}';
		}
		if ( ! empty( $preset['custom_css'] ) ) {
			$out .= ' ' . str_replace( '.smec-reading-time', $selector, $preset['custom_css'] );
		}

		return $out;
	}

	// -------------------------------------------------------------------------
	// Gutenberg block (server-side render)
	// -------------------------------------------------------------------------
	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) return;

		register_block_type( 'smartemailing-connect/reading-time', [
			'render_callback' => [ $this, 'render_block' ],
			'attributes'      => [
				'preset'   => [ 'type' => 'string',  'default' => 'default' ],
				'postId'   => [ 'type' => 'number',  'default' => 0 ],
				'label'    => [ 'type' => 'string',  'default' => '' ],
				'suffix'   => [ 'type' => 'string',  'default' => '' ],
				'showIcon' => [ 'type' => 'boolean', 'default' => true ],
			],
		] );
	}

	public function render_block( array $attributes ): string {
		$preset = $this->settings->get_rt_preset_by_slug( $attributes['preset'] ?? 'default' );
		if ( ! $preset ) return '';

		$post_id = ! empty( $attributes['postId'] ) ? (int) $attributes['postId'] : get_the_ID();
		if ( ! $post_id ) return '';

		if ( ! empty( $attributes['label'] ) )  $preset['label']     = $attributes['label'];
		if ( ! empty( $attributes['suffix'] ) ) $preset['suffix']    = $attributes['suffix'];
		if ( isset( $attributes['showIcon'] ) ) $preset['show_icon'] = $attributes['showIcon'] ? 1 : 0;

		$minutes = $this->calculate( $post_id, (int) ( $preset['wpm'] ?? 200 ) );
		if ( $minutes === null ) return '';

		return $this->render( $minutes, $preset );
	}
}
