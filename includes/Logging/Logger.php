<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SMEC_Logger {

	private SMEC_Settings $settings;

	public function __construct( SMEC_Settings $settings ) {
		$this->settings = $settings;
	}

	// -------------------------------------------------------------------------
	// Log writers
	// -------------------------------------------------------------------------

	public function info( string $message, array $context = [], string $type = 'general' ): void {
		$this->write( 'info', $message, $context, $type );
	}

	public function error( string $message, array $context = [], string $type = 'general' ): void {
		$this->write( 'error', $message, $context, $type );
	}

	public function debug( string $message, array $context = [], string $type = 'general' ): void {
		if ( ! $this->settings->is_debug() ) return;
		$this->write( 'debug', $message, $context, $type );
	}

	public function warning( string $message, array $context = [], string $type = 'general' ): void {
		$this->write( 'warning', $message, $context, $type );
	}

	private function write( string $level, string $message, array $context, string $type ): void {
		global $wpdb;

		// Strip any sensitive keys from context
		$safe_context = $this->sanitize_context( $context );

		$wpdb->insert(
			$wpdb->prefix . 'smec_logs',
			[
				'type'       => sanitize_key( $type ),
				'level'      => sanitize_key( $level ),
				'message'    => mb_substr( $message, 0, 65000 ),
				'context'    => ! empty( $safe_context ) ? wp_json_encode( $safe_context ) : null,
				'created_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	private function sanitize_context( array $context, int $depth = 0 ): array {
		if ( $depth > 5 ) return [ '[max_depth]' => true ]; // ochrana před nekonečnou rekurzí

		$sensitive_keys = [
			'api_key', 'apikey', 'api-key', 'password', 'passwd', 'secret',
			'token', 'authorization', 'auth', 'credential', 'private_key',
			'access_token', 'refresh_token', 'bearer',
		];

		$result = [];
		foreach ( $context as $key => $value ) {
			$lower_key = strtolower( (string) $key );

			// Redaktovat citlivé klíče
			if ( in_array( $lower_key, $sensitive_keys, true ) ) {
				$result[ $key ] = '[REDACTED]';
				continue;
			}

			// Rekurze do vnořených polí
			if ( is_array( $value ) ) {
				$result[ $key ] = $this->sanitize_context( $value, $depth + 1 );
				continue;
			}

			// Zkrátit dlouhé hodnoty (response body může být velký)
			if ( is_string( $value ) && strlen( $value ) > 1000 ) {
				$value = mb_substr( $value, 0, 1000 ) . '...[truncated]';
			}

			// Detekovat a redaktovat API klíče ve stringových hodnotách (pattern: 32+ hex chars)
			if ( is_string( $value ) ) {
				$value = preg_replace( '/\b[a-f0-9]{32,}\b/i', '[KEY_REDACTED]', $value );
			}

			$result[ $key ] = $value;
		}
		return $result;
	}

	// -------------------------------------------------------------------------
	// Log readers
	// -------------------------------------------------------------------------

	public function get_logs( array $args = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'smec_logs';

		$type    = sanitize_key( $args['type']  ?? '' );
		$level   = sanitize_key( $args['level'] ?? '' );
		$limit   = max( 1, min( 500, (int) ( $args['limit']  ?? 50 ) ) );
		$offset  = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$where  = [];
		$values = [];

		if ( $type ) {
			$where[]  = 'type = %s';
			$values[] = $type;
		}
		if ( $level ) {
			$where[]  = 'level = %s';
			$values[] = $level;
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$sql = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		$values[] = $limit;
		$values[] = $offset;

		return $wpdb->get_results(
			$wpdb->prepare( $sql, ...$values ),
			ARRAY_A
		) ?: [];
	}

	public function count_logs( array $args = [] ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'smec_logs';

		$type  = sanitize_key( $args['type']  ?? '' );
		$level = sanitize_key( $args['level'] ?? '' );

		$where  = [];
		$values = [];

		if ( $type ) {
			$where[]  = 'type = %s';
			$values[] = $type;
		}
		if ( $level ) {
			$where[]  = 'level = %s';
			$values[] = $level;
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";

		return (int) ( $values
			? $wpdb->get_var( $wpdb->prepare( $sql, ...$values ) )
			: $wpdb->get_var( $sql )
		);
	}

	public function clear_logs( ?int $older_than_days = null ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'smec_logs';

		if ( $older_than_days !== null ) {
			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE created_at < %s",
					gmdate( 'Y-m-d H:i:s', strtotime( "-{$older_than_days} days" ) )
				)
			);
		} else {
			$result = $wpdb->query( "DELETE FROM {$table}" );
		}

		return (int) $result;
	}

	public function auto_cleanup(): void {
		$days = (int) ( $this->settings->get_general()['log_retention_days'] ?? 30 );
		$this->clear_logs( $days );
	}

	// -------------------------------------------------------------------------
	// Stats for overview page
	// -------------------------------------------------------------------------
	public function get_form_monitor_stats( int $days = 30 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'smec_logs';
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT context FROM {$table}
				 WHERE type = 'import' AND level = 'info' AND created_at >= %s
				 LIMIT 5000",
				$since
			),
			ARRAY_A
		) ?: [];

		$stats = [];
		foreach ( $rows as $row ) {
			$ctx      = json_decode( $row['context'] ?? '{}', true ) ?: [];
			$form_type = $ctx['form_type'] ?? '';
			$form_id   = $ctx['form_id']   ?? '';
			if ( ! $form_type || ! $form_id ) continue;
			$key = $form_type . '|' . $form_id;
			$stats[ $key ] = ( $stats[ $key ] ?? 0 ) + 1;
		}
		return $stats; // ['elementor|New Form' => 42, ...]
	}

	public function get_form_monthly_stats( string $form_type, string $form_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'smec_logs';
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-12 months' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS month, COUNT(*) AS count
				 FROM {$table}
				 WHERE type = 'import' AND level = 'info'
				   AND created_at >= %s
				   AND context LIKE %s
				 GROUP BY month
				 ORDER BY month ASC",
				$since,
				'%' . $wpdb->esc_like( '"form_type":"' . $form_type . '"' ) . '%'
			),
			ARRAY_A
		) ?: [];

		// Vyfiltrovat přesnou shodu form_id a vyplnit prázdné měsíce
		$all_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS month, context
				 FROM {$table}
				 WHERE type = 'import' AND level = 'info'
				   AND created_at >= %s",
				$since
			),
			ARRAY_A
		) ?: [];

		$indexed = [];
		foreach ( $all_rows as $r ) {
			$ctx = json_decode( $r['context'] ?? '{}', true ) ?: [];
			if ( ( $ctx['form_type'] ?? '' ) !== $form_type ) continue;
			if ( ( $ctx['form_id'] ?? '' ) !== $form_id ) continue;
			$m = $r['month'];
			$indexed[ $m ] = ( $indexed[ $m ] ?? 0 ) + 1;
		}

		$result = [];
		for ( $i = 11; $i >= 0; $i-- ) {
			$month = gmdate( 'Y-m', strtotime( "-{$i} months" ) );
			$result[] = [ 'month' => $month, 'count' => $indexed[ $month ] ?? 0 ];
		}
		return $result;
	}

	public function has_api_success_after( string $after_datetime ): bool {
		global $wpdb;
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}smec_logs
				 WHERE type = 'api' AND level IN ('debug','info') AND created_at > %s",
				$after_datetime
			)
		);
		return $count > 0;
	}

	public function get_last_success( string $type = 'import' ): ?array {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}smec_logs WHERE type = %s AND level = 'info' ORDER BY created_at DESC LIMIT 1",
				$type
			),
			ARRAY_A
		);
	}

	public function get_activity_by_day( int $days = 30 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'smec_logs';
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS day,
				        SUM(CASE WHEN type = 'api' AND level != 'error' THEN 1 ELSE 0 END) AS api_calls,
				        SUM(CASE WHEN type = 'import' AND level = 'info'  THEN 1 ELSE 0 END) AS imports,
				        SUM(CASE WHEN level = 'error' THEN 1 ELSE 0 END) AS errors
				 FROM {$table}
				 WHERE created_at >= %s
				 GROUP BY DATE(created_at)
				 ORDER BY day ASC",
				$since
			),
			ARRAY_A
		) ?: [];

		// Vyplnit prázdné dny nulami
		$indexed = [];
		foreach ( $rows as $r ) {
			$indexed[ $r['day'] ] = $r;
		}
		$result = [];
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
			$result[] = [
				'day'      => $day,
				'api_calls'=> (int) ( $indexed[ $day ]['api_calls'] ?? 0 ),
				'imports'  => (int) ( $indexed[ $day ]['imports']   ?? 0 ),
				'errors'   => (int) ( $indexed[ $day ]['errors']    ?? 0 ),
			];
		}
		return $result;
	}

	public function get_activity_by_month( int $months = 12 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'smec_logs';
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$months} months" ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(created_at, '%%Y-%%m') AS month,
				        SUM(CASE WHEN type = 'api' AND level != 'error' THEN 1 ELSE 0 END) AS api_calls,
				        SUM(CASE WHEN type = 'import' AND level = 'info'  THEN 1 ELSE 0 END) AS imports,
				        SUM(CASE WHEN level = 'error' THEN 1 ELSE 0 END) AS errors
				 FROM {$table}
				 WHERE created_at >= %s
				 GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
				 ORDER BY month ASC",
				$since
			),
			ARRAY_A
		) ?: [];

		$indexed = [];
		foreach ( $rows as $r ) {
			$indexed[ $r['month'] ] = $r;
		}
		$result = [];
		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$month = gmdate( 'Y-m', strtotime( "-{$i} months" ) );
			$result[] = [
				'month'    => $month,
				'api_calls'=> (int) ( $indexed[ $month ]['api_calls'] ?? 0 ),
				'imports'  => (int) ( $indexed[ $month ]['imports']   ?? 0 ),
				'errors'   => (int) ( $indexed[ $month ]['errors']    ?? 0 ),
			];
		}
		return $result;
	}

	public function get_imports_by_form( int $days = 30 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'smec_logs';
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT context FROM {$table}
				 WHERE type = 'import' AND level = 'info' AND created_at >= %s
				 LIMIT 2000",
				$since
			),
			ARRAY_A
		) ?: [];

		$by_form = [];
		foreach ( $rows as $row ) {
			$ctx  = json_decode( $row['context'] ?? '{}', true ) ?: [];
			$name = $ctx['mapping_name'] ?? $ctx['mapping'] ?? $ctx['form_type'] ?? 'Jiný';
			$by_form[ $name ] = ( $by_form[ $name ] ?? 0 ) + 1;
		}
		arsort( $by_form );
		$out = [];
		foreach ( $by_form as $name => $count ) {
			$out[] = [ 'name' => $name, 'count' => $count ];
		}
		return $out;
	}

	public function get_last_error( string $type = '' ): ?array {
		global $wpdb;
		if ( $type ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}smec_logs WHERE type = %s AND level = 'error' ORDER BY created_at DESC LIMIT 1",
					$type
				),
				ARRAY_A
			);
		}
		return $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}smec_logs WHERE level = 'error' ORDER BY created_at DESC LIMIT 1",
			ARRAY_A
		);
	}
}
