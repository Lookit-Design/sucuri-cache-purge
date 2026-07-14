<?php
defined( 'ABSPATH' ) || exit;

class Lookit_Sucuri_Purge_Settings {

	const OPTION_KEY = 'lookit_sucuri_purge_settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_disable_autoload' ) );
	}

	public static function add_settings_page() {
		add_options_page(
			__( 'Lookit Cache Purge for Sucuri', 'lookit-cache-purge-for-sucuri' ),
			__( 'Sucuri Cache Purge', 'lookit-cache-purge-for-sucuri' ),
			'manage_options',
			'lookit-sucuri-purge',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'lookit_sucuri_purge_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => array(),
				'autoload'          => false,
			)
		);

		add_settings_section(
			'lookit_sucuri_purge_main',
			__( 'Sucuri API Credentials', 'lookit-cache-purge-for-sucuri' ),
			array( __CLASS__, 'render_section_description' ),
			'lookit-sucuri-purge'
		);

		add_settings_field(
			'api_key',
			__( 'API Key (for plugin)', 'lookit-cache-purge-for-sucuri' ),
			array( __CLASS__, 'render_api_key_field' ),
			'lookit-sucuri-purge',
			'lookit_sucuri_purge_main'
		);
	}

	public static function sanitize_settings( $input ) {

		$sanitized = array();
		$raw       = isset( $input['api_key'] ) ? sanitize_text_field( trim( $input['api_key'] ) ) : '';
		$existing  = self::get_settings();

		// The field renders blank so the secret is never sent back to the
		// browser; a blank submission therefore means "keep the saved key".
		if ( '' === $raw ) {
			$sanitized['api_key'] = $existing['api_key'] ?? '';
			return $sanitized;
		}

		// Validate the combined KEY/SECRET format: 32hex / 32hex.
		if ( ! preg_match( '#^[a-f0-9]{32}/[a-f0-9]{32}$#i', $raw ) ) {
			add_settings_error(
				self::OPTION_KEY,
				'invalid_format',
				__( 'Invalid API Key format. Expected: 32 characters / 32 characters. Paste the "API Key (for plugin)" value from your Sucuri dashboard → API → API Details.', 'lookit-cache-purge-for-sucuri' ),
				'error'
			);
			// Keep whatever was previously saved rather than overwriting with bad data.
			$sanitized['api_key'] = $existing['api_key'] ?? '';
			return $sanitized;
		}

		$sanitized['api_key'] = $raw;
		return $sanitized;
	}

	public static function render_section_description() {
		?>
		<p>
			<?php esc_html_e( 'Enter your Sucuri API Key (for plugin) below.', 'lookit-cache-purge-for-sucuri' ); ?>
			<br>
			<?php esc_html_e( 'Find it in the Sucuri WAF dashboard → API → API Details → "API Key (for plugin)".', 'lookit-cache-purge-for-sucuri' ); ?>
			<?php esc_html_e( 'It will look like:', 'lookit-cache-purge-for-sucuri' ); ?>
			<code>32-characters/32-characters</code>
		</p>
		<?php
	}

	public static function render_api_key_field() {

		$settings = self::get_settings();
		$api_key  = $settings['api_key'] ?? '';

		// Mask middle of the key — show first 8 and last 8 characters
		$masked = '';
		if ( $api_key ) {
			$masked = substr( $api_key, 0, 8 ) . str_repeat( '•', 8 ) . '/' . str_repeat( '•', 8 ) . substr( $api_key, -8 );
		}
		?>
		<input
			type="password"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]"
			id="lookit_sucuri_api_key"
			value=""
			class="regular-text"
			placeholder="<?php echo $api_key ? esc_attr__( 'Leave blank to keep the saved key', 'lookit-cache-purge-for-sucuri' ) : esc_attr__( 'e.g. 31d3f48f9b...a7c2e1f8/a7c2e1f8...b3d4c6e7', 'lookit-cache-purge-for-sucuri' ); ?>"
			autocomplete="off"
			style="font-family: monospace;"
		>
		<?php if ( $api_key ) : ?>
			<p class="description">
				<?php esc_html_e( 'Currently set:', 'lookit-cache-purge-for-sucuri' ); ?>
				<code><?php echo esc_html( $masked ); ?></code>
				&mdash; <?php esc_html_e( 'leave blank to keep it, or paste a new value to replace it.', 'lookit-cache-purge-for-sucuri' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	public static function render_settings_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap lookit-sucuri-purge-settings">
			<h1>
				<span class="dashicons dashicons-shield-alt" style="font-size:28px;vertical-align:middle;margin-right:6px;color:#028673;"></span>
				<?php esc_html_e( 'Lookit Cache Purge for Sucuri — Settings', 'lookit-cache-purge-for-sucuri' ); ?>
			</h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'lookit_sucuri_purge_group' );
				do_settings_sections( 'lookit-sucuri-purge' );
				submit_button( __( 'Save Credentials', 'lookit-cache-purge-for-sucuri' ) );
				?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'How It Works', 'lookit-cache-purge-for-sucuri' ); ?></h2>
			<ul style="list-style:disc;margin-left:1.5em;line-height:1.8;">
				<li><?php esc_html_e( 'When editing any post or page in wp-admin — or when viewing the live site while logged in — a "Sucuri Cache Purge" menu appears in the admin bar.', 'lookit-cache-purge-for-sucuri' ); ?></li>
				<li><?php esc_html_e( 'Clicking "Purge This URL" sends only the current page URL to the Sucuri WAF API — clearing that single URL from the edge cache.', 'lookit-cache-purge-for-sucuri' ); ?></li>
				<li><?php esc_html_e( '"Purge Entire Site" clears the full Sucuri cache (with confirmation).', 'lookit-cache-purge-for-sucuri' ); ?></li>
				<li><?php esc_html_e( 'Sucuri takes up to 2 minutes to fully propagate a cache clear across its edge network — this is normal.', 'lookit-cache-purge-for-sucuri' ); ?></li>
				<li><?php esc_html_e( 'To protect against accidental hammering, the plugin limits you to 6 purges per minute.', 'lookit-cache-purge-for-sucuri' ); ?></li>
			</ul>

			<h2><?php esc_html_e( 'Important Notes', 'lookit-cache-purge-for-sucuri' ); ?></h2>
			<ul style="list-style:disc;margin-left:1.5em;line-height:1.8;">
				<li>
					<strong><?php esc_html_e( 'Static files cache differently.', 'lookit-cache-purge-for-sucuri' ); ?></strong>
					<?php esc_html_e( 'Sucuri caches images, CSS, JS, PDFs, and fonts on its edge for up to 72 hours regardless of per-URL purging. If you change a stylesheet or image, use "Purge Entire Site" or versioning (e.g. ?ver=1.2.3).', 'lookit-cache-purge-for-sucuri' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( '2-minute propagation.', 'lookit-cache-purge-for-sucuri' ); ?></strong>
					<?php esc_html_e( 'After a successful purge, Sucuri takes up to 2 minutes to fully flush the cache across all edge servers. If you do not see your change immediately, wait and reload.', 'lookit-cache-purge-for-sucuri' ); ?>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Keep the credentials out of the autoloaded options so the key is not
	 * pulled into memory on every front-end request. Older installs that saved
	 * the option as autoloaded are migrated in place.
	 */
	public static function maybe_disable_autoload() {
		$alloptions = wp_load_alloptions();

		if ( ! isset( $alloptions[ self::OPTION_KEY ] ) ) {
			return;
		}

		$value = get_option( self::OPTION_KEY );
		delete_option( self::OPTION_KEY );
		add_option( self::OPTION_KEY, $value, '', false );
	}

	/**
	 * Get saved settings array.
	 */
	public static function get_settings() {
		return get_option( self::OPTION_KEY, array() );
	}

	/**
	 * Get the raw combined key.
	 */
	public static function get_api_key() {
		$settings = self::get_settings();
		return $settings['api_key'] ?? '';
	}

	/**
	 * Split the combined key into [key, secret] or [null, null] if not configured.
	 */
	public static function get_api_credentials() {
		$combined = self::get_api_key();
		if ( ! $combined || false === strpos( $combined, '/' ) ) {
			return array( null, null );
		}
		$parts = explode( '/', $combined, 2 );
		return array( $parts[0] ?? null, $parts[1] ?? null );
	}

	public static function is_configured() {
		list( $k, $s ) = self::get_api_credentials();
		return ! empty( $k ) && ! empty( $s );
	}
}
