<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_GTM {

	private SMEC_Settings $settings;

	public function __construct( SMEC_Settings $settings ) {
		$this->settings = $settings;
	}

	public function register(): void {
		$cfg = $this->settings->get_gtm();
		if ( empty( $cfg['enabled'] ) || empty( $cfg['container_id'] ) ) return;

		add_action( 'wp_head',       [ $this, 'inject_head' ],   1 );
		add_action( 'wp_body_open',  [ $this, 'inject_noscript' ], 1 );
		// Fallback pro témata bez wp_body_open podpory
		add_action( 'wp_footer',     [ $this, 'inject_noscript_fallback' ], 1 );
	}

	public function inject_head(): void {
		if ( ! $this->should_inject() ) return;

		$id = esc_js( $this->settings->get_gtm()['container_id'] );
		echo "\n<!-- Google Tag Manager – lazy load (SmartEmailing Connect) -->\n";
		echo '<script>' . "\n";
		// dataLayer inicializujeme okamžitě – události pushnuté před načtením GTM se neztrácejí
		echo 'window.dataLayer=window.dataLayer||[];' . "\n";
		echo '(function(){' . "\n";
		echo 'var loaded=false;' . "\n";
		echo 'function loadGTM(){' . "\n";
		echo '  if(loaded)return;loaded=true;' . "\n";
		echo '  (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':new Date().getTime(),event:\'gtm.js\'});' . "\n";
		echo '  var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!=\'dataLayer\'?\'&l=\'+l:\'\';' . "\n";
		echo '  j.async=true;j.src=\'https://www.googletagmanager.com/gtm.js?id=\'+i+dl;' . "\n";
		echo '  f.parentNode.insertBefore(j,f);' . "\n";
		echo '  })(window,document,\'script\',\'dataLayer\',\'' . $id . '\');' . "\n";
		echo '}' . "\n";
		// Načti po 3 sekundách nebo při první interakci uživatele
		echo 'var t=setTimeout(loadGTM,3000);' . "\n";
		echo 'function onInt(){clearTimeout(t);loadGTM();}' . "\n";
		echo 'document.addEventListener("scroll",onInt,{once:true,passive:true});' . "\n";
		echo 'document.addEventListener("click",onInt,{once:true});' . "\n";
		echo 'document.addEventListener("touchstart",onInt,{once:true,passive:true});' . "\n";
		echo 'document.addEventListener("keydown",onInt,{once:true});' . "\n";
		echo '})();' . "\n";
		echo '</script>' . "\n";
		echo "<!-- End Google Tag Manager -->\n\n";
	}

	public function inject_noscript(): void {
		if ( ! $this->should_inject() ) return;
		$this->noscript_injected = true;

		$id = esc_attr( $this->settings->get_gtm()['container_id'] );
		echo "\n<!-- Google Tag Manager (noscript) -->\n";
		echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $id . '"' . "\n";
		echo 'height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
		echo "<!-- End Google Tag Manager (noscript) -->\n\n";
	}

	// Fallback: vloží noscript do patičky pokud téma nepodporuje wp_body_open
	private bool $noscript_injected = false;

	public function inject_noscript_fallback(): void {
		if ( $this->noscript_injected ) return;
		$this->inject_noscript();
	}

	private function should_inject(): bool {
		if ( is_admin() ) return false;

		$cfg = $this->settings->get_gtm();

		if ( ! empty( $cfg['exclude_admins'] ) && current_user_can( 'manage_options' ) ) return false;

		if ( ! empty( $cfg['exclude_roles'] ) && is_user_logged_in() ) {
			$user = wp_get_current_user();
			foreach ( (array) $cfg['exclude_roles'] as $role ) {
				if ( in_array( $role, (array) $user->roles, true ) ) return false;
			}
		}

		return true;
	}

	// Extrahuje container ID z vloženého GTM kódu nebo přímého ID
	public static function extract_container_id( string $input ): string {
		$input = trim( $input );

		// Přímý formát: GTM-XXXXXXX nebo GTM-XXXXXXXX
		if ( preg_match( '/^GTM-[A-Z0-9]+$/i', $input ) ) {
			return strtoupper( $input );
		}

		// Extrakce z GTM kódu (hledá GTM-XXXXX v kódu)
		if ( preg_match( "/['\"]GTM-([A-Z0-9]+)['\"]/i", $input, $matches ) ) {
			return 'GTM-' . strtoupper( $matches[1] );
		}

		return '';
	}
}
