<?php
/**
 * Analytics module for Blog Lead Magnet plugin.
 *
 * Tracks CTA views, clicks, and conversions (gate unlocks).
 *
 * @package ImportantCTA
 */

defined( 'ABSPATH' ) || exit;

class ICTA_Analytics {

	/**
	 * Allowed CTA types.
	 *
	 * @var string[]
	 */
	private const ALLOWED_CTA_TYPES = array( 'cta1', 'cta2', 'cta3', 'gate' );

	/**
	 * Allowed event types.
	 *
	 * @var string[]
	 */
	private const ALLOWED_EVENT_TYPES = array( 'view', 'click', 'unlock' );

	/**
	 * DB table name (without prefix).
	 *
	 * @var string
	 */
	private const TABLE = 'icta_events';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_icta_track_event', array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_icta_track_event', array( $this, 'handle_ajax' ) );

		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Create the events database table.
	 */
	public static function create_table(): void {
		global $wpdb;

		$table   = $wpdb->prefix . self::TABLE;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			cta_type varchar(20) NOT NULL,
			event_type varchar(20) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY post_cta_event (post_id, cta_type, event_type)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Enqueue analytics JS on singular posts.
	 */
	public function enqueue_scripts(): void {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		wp_enqueue_script(
			'icta-analytics',
			ICTA_URL . 'assets/js/analytics.js',
			array(),
			ICTA_VERSION,
			true
		);

		wp_localize_script( 'icta-analytics', 'icta_analytics', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'icta_track_event' ),
			'post_id'  => get_the_ID(),
		) );
	}

	/**
	 * Handle AJAX tracking request.
	 */
	public function handle_ajax(): void {
		check_ajax_referer( 'icta_track_event', 'nonce' );

		$post_id    = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$cta_type   = isset( $_POST['cta_type'] ) ? sanitize_text_field( wp_unslash( $_POST['cta_type'] ) ) : '';
		$event_type = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : '';

		// Validate post exists.
		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID.' ), 400 );
		}

		// Validate cta_type.
		if ( ! in_array( $cta_type, self::ALLOWED_CTA_TYPES, true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid CTA type.' ), 400 );
		}

		// Validate event_type.
		if ( ! in_array( $event_type, self::ALLOWED_EVENT_TYPES, true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid event type.' ), 400 );
		}

		// Rate limiting: max 10 events per IP per minute.
		$ip            = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
		$transient_key = 'icta_rate_' . md5( $ip );
		$count         = (int) get_transient( $transient_key );

		if ( $count >= 10 ) {
			wp_send_json_error( array( 'message' => 'Rate limit exceeded.' ), 429 );
		}

		set_transient( $transient_key, $count + 1, MINUTE_IN_SECONDS );

		// Insert event.
		global $wpdb;
		$table  = $wpdb->prefix . self::TABLE;
		$result = $wpdb->insert(
			$table,
			array(
				'post_id'    => $post_id,
				'cta_type'   => $cta_type,
				'event_type' => $event_type,
			),
			array( '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => 'Failed to record event.' ), 500 );
		}

		wp_send_json_success( array( 'message' => 'Event recorded.' ) );
	}

	/**
	 * Record an event from server-side code.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $cta_type   CTA type (cta1, cta2, cta3, gate).
	 * @param string $event_type Event type (view, click, unlock).
	 * @return bool True on success, false on failure.
	 */
	public static function record( int $post_id, string $cta_type, string $event_type ): bool {
		if ( ! get_post( $post_id ) ) {
			return false;
		}

		if ( ! in_array( $cta_type, self::ALLOWED_CTA_TYPES, true ) ) {
			return false;
		}

		if ( ! in_array( $event_type, self::ALLOWED_EVENT_TYPES, true ) ) {
			return false;
		}

		global $wpdb;
		$table  = $wpdb->prefix . self::TABLE;
		$result = $wpdb->insert(
			$table,
			array(
				'post_id'    => $post_id,
				'cta_type'   => $cta_type,
				'event_type' => $event_type,
			),
			array( '%d', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Register admin submenu page under Settings.
	 */
	public function register_admin_page(): void {
		add_options_page(
			'BLM Analityka',
			'BLM Analityka',
			'manage_options',
			'icta-analytics',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the analytics admin page.
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		// Date filter.
		$allowed_ranges = array( '7', '30', '90', 'all' );
		$range          = isset( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : '30';

		if ( ! in_array( $range, $allowed_ranges, true ) ) {
			$range = '30';
		}

		$date_clause = '';
		if ( 'all' !== $range ) {
			$date_clause = $wpdb->prepare(
				' AND created_at >= %s',
				gmdate( 'Y-m-d H:i:s', strtotime( "-{$range} days" ) )
			);
		}

		// Summary data: per CTA type — views, clicks, conversion rate.
		$summary_sql = "SELECT cta_type,
			SUM( CASE WHEN event_type = 'view' THEN 1 ELSE 0 END ) AS views,
			SUM( CASE WHEN event_type = 'click' THEN 1 ELSE 0 END ) AS clicks,
			SUM( CASE WHEN event_type = 'unlock' THEN 1 ELSE 0 END ) AS unlocks
			FROM {$table}
			WHERE 1=1 {$date_clause}
			GROUP BY cta_type
			ORDER BY cta_type ASC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $date_clause is prepared above.
		$summary = $wpdb->get_results( $summary_sql );

		// Per-post data: top 20 posts by total events.
		$posts_sql = "SELECT post_id,
			SUM( CASE WHEN cta_type = 'cta1' AND event_type = 'click' THEN 1 ELSE 0 END ) AS cta1_clicks,
			SUM( CASE WHEN cta_type = 'cta2' AND event_type = 'click' THEN 1 ELSE 0 END ) AS cta2_clicks,
			SUM( CASE WHEN cta_type = 'cta3' AND event_type = 'click' THEN 1 ELSE 0 END ) AS cta3_clicks,
			SUM( CASE WHEN cta_type = 'gate' AND event_type = 'unlock' THEN 1 ELSE 0 END ) AS gate_unlocks,
			COUNT(*) AS total_events
			FROM {$table}
			WHERE 1=1 {$date_clause}
			GROUP BY post_id
			ORDER BY total_events DESC
			LIMIT 20";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$posts_data = $wpdb->get_results( $posts_sql );

		$page_url = admin_url( 'options-general.php?page=icta-analytics' );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="tablenav top">
				<div class="alignleft actions">
					<?php foreach ( $allowed_ranges as $r ) : ?>
						<?php
						$label = 'all' === $r ? 'Caly okres' : $r . ' dni';
						$url   = add_query_arg( 'range', $r, $page_url );
						$class = ( $range === $r ) ? 'button button-primary' : 'button';
						?>
						<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</div>

			<h2>Podsumowanie CTA</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Typ CTA', 'important-cta' ); ?></th>
						<th><?php esc_html_e( 'Wyswietlenia', 'important-cta' ); ?></th>
						<th><?php esc_html_e( 'Klikniecia', 'important-cta' ); ?></th>
						<th><?php esc_html_e( 'Odblokowania', 'important-cta' ); ?></th>
						<th><?php esc_html_e( 'Konwersja (klik/widok)', 'important-cta' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $summary ) ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'Brak danych.', 'important-cta' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $summary as $row ) : ?>
							<?php
							$views      = (int) $row->views;
							$clicks     = (int) $row->clicks;
							$unlocks    = (int) $row->unlocks;
							$conversion = $views > 0 ? round( ( $clicks / $views ) * 100, 1 ) : 0;
							?>
							<tr>
								<td><?php echo esc_html( $row->cta_type ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $clicks ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $unlocks ) ); ?></td>
								<td><?php echo esc_html( $conversion . '%' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2 style="margin-top: 2em;">Top 20 postow</h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post', 'important-cta' ); ?></th>
						<th><?php esc_html_e( 'CTA1 klik', 'important-cta' ); ?></th>
						<th><?php esc_html_e( 'CTA2 klik', 'important-cta' ); ?></th>
						<th><?php esc_html_e( 'CTA3 klik', 'important-cta' ); ?></th>
						<th><?php esc_html_e( 'Gate odblokowania', 'important-cta' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $posts_data ) ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'Brak danych.', 'important-cta' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $posts_data as $row ) : ?>
							<?php $title = get_the_title( (int) $row->post_id ); ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( (int) $row->post_id ) ); ?>">
										<?php echo esc_html( $title ? $title : '#' . $row->post_id ); ?>
									</a>
								</td>
								<td><?php echo esc_html( number_format_i18n( (int) $row->cta1_clicks ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $row->cta2_clicks ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $row->cta3_clicks ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $row->gate_unlocks ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
