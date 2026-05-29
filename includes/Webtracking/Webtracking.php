<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_Webtracking {

	private SMEC_Settings  $settings;
	private SMEC_Logger    $logger;
	private ?SMEC_Notifier $notifier = null;
	private bool $injected = false;

	public function __construct( SMEC_Settings $settings, SMEC_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function set_notifier( SMEC_Notifier $notifier ): void {
		$this->notifier = $notifier;
	}

	public function register(): void {
		$cfg = $this->settings->get_webtracking();
		if ( empty( $cfg['enabled'] ) || empty( $cfg['guid'] ) ) return;

		if ( $cfg['position'] === 'head' ) {
			add_action( 'wp_head', [ $this, 'inject_tracking' ], 99 );
		} else {
			add_action( 'wp_footer', [ $this, 'inject_tracking' ], 99 );
		}
	}

	public function inject_tracking(): void {
		// Prevent double injection
		if ( $this->injected ) return;

		// Never inject in admin
		if ( is_admin() ) return;

		$cfg = $this->settings->get_webtracking();

		if ( empty( $cfg['enabled'] ) || empty( $cfg['guid'] ) ) return;

		// Exclude admins
		if ( ! empty( $cfg['exclude_admins'] ) && current_user_can( 'manage_options' ) ) return;

		// Exclude roles
		if ( ! empty( $cfg['exclude_roles'] ) && is_user_logged_in() ) {
			$user = wp_get_current_user();
			foreach ( $cfg['exclude_roles'] as $role ) {
				if ( in_array( $role, (array) $user->roles, true ) ) return;
			}
		}

		// Exclude specific pages
		if ( ! empty( $cfg['excluded_pages'] ) && is_page() ) {
			global $post;
			if ( $post && in_array( (int) $post->ID, (array) $cfg['excluded_pages'], true ) ) return;
		}

		$guid = esc_js( sanitize_text_field( $cfg['guid'] ) );

		echo "\n<!-- SmartEmailing Connect Webtracking -->\n";
		echo '<script type="text/javascript">' . "\n";
		echo 'var _se = _se || [];' . "\n";
		echo '_se.push(["setGuid", "' . $guid . '"]);' . "\n";
		echo '(function() {' . "\n";
		echo '  var s = document.createElement("script");' . "\n";
		echo '  s.type = "text/javascript"; s.async = true;' . "\n";
		echo '  s.src = "https://app.smartemailing.cz/webtracking/script.js";' . "\n";
		echo '  var f = document.getElementsByTagName("script")[0];' . "\n";
		echo '  f.parentNode.insertBefore(s, f);' . "\n";
		echo '})();' . "\n";
		echo '</script>' . "\n";

		// Optional custom code – pouze admini s unfiltered_html mohou přidat vlastní kód
		if ( ! empty( $cfg['custom_code'] ) ) {
			// Znovu ověřit kapabilitu při výstupu (data mohla být uložena jiným adminem)
			echo "\n<!-- SmartEmailing Connect Custom Tracking Code -->\n";
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $cfg['custom_code'] . "\n"; // uloženo s kontrolou unfiltered_html v Settings::save_webtracking
		}

		echo "<!-- /SmartEmailing Connect Webtracking -->\n\n";

		$this->injected = true;

		// Zaznamenat úspěšnou injekci
		if ( $this->notifier ) {
			$this->notifier->record_webtrack_inject();
		}
	}
}
