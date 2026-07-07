<?php
defined( 'ABSPATH' ) || exit;

class Lookit_Sucuri_Purge_Settings {

	const OPTION_KEY = 'lookit_sucuri_purge_settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function add_settings_page() {
		add_options_page(
			__( 'Lookit Sucuri Cache Purge', 'lookit-sucuri-cache-purge' ),
			__( 'Sucuri Cache Purge', 'lookit-sucuri-cache-purge' ),
			'manage_options',
			'lookit-sucuri-purge',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'lookit_sucuri_purge_group',
			self::OPTION_KEY,
			array( __CLASS__, 'sanitize_settings' )
		);

		add_settings_section(
			'lookit_sucuri_purge_main',
			__( 'Sucuri API Credentials', 'lookit-sucuri-cache-purge' ),
			array( __CLASS__, 'render_section_description' ),
			'lookit-sucuri-purge'
		);

		add_settings_field(
			'api_key',
			__( 'API Key (for plugin)', 'lookit-sucuri-cache-purge' ),
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
				__( 'Invalid API Key format. Expected: 32 characters / 32 characters. Paste the "API Key (for plugin)" value from your Sucuri dashboard → API → API Details.', 'lookit-sucuri-cache-purge' ),
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
			<?php esc_html_e( 'Enter your Sucuri API Key (for plugin) below.', 'lookit-sucuri-cache-purge' ); ?>
			<br>
			<?php esc_html_e( 'Find it in the Sucuri WAF dashboard → API → API Details → "API Key (for plugin)".', 'lookit-sucuri-cache-purge' ); ?>
			<?php esc_html_e( 'It will look like:', 'lookit-sucuri-cache-purge' ); ?>
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
			placeholder="<?php echo $api_key ? esc_attr__( 'Leave blank to keep the saved key', 'lookit-sucuri-cache-purge' ) : esc_attr__( 'e.g. 31d3f48f9b...a7c2e1f8/a7c2e1f8...b3d4c6e7', 'lookit-sucuri-cache-purge' ); ?>"
			autocomplete="off"
			style="font-family: monospace;"
		>
		<?php if ( $api_key ) : ?>
			<p class="description">
				<?php esc_html_e( 'Currently set:', 'lookit-sucuri-cache-purge' ); ?>
				<code><?php echo esc_html( $masked ); ?></code>
				&mdash; <?php esc_html_e( 'leave blank to keep it, or paste a new value to replace it.', 'lookit-sucuri-cache-purge' ); ?>
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
				<?php esc_html_e( 'Lookit Sucuri Cache Purge — Settings', 'lookit-sucuri-cache-purge' ); ?>
			</h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'lookit_sucuri_purge_group' );
				do_settings_sections( 'lookit-sucuri-purge' );
				submit_button( __( 'Save Credentials', 'lookit-sucuri-cache-purge' ) );
				?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'How It Works', 'lookit-sucuri-cache-purge' ); ?></h2>
			<ul style="list-style:disc;margin-left:1.5em;line-height:1.8;">
				<li><?php esc_html_e( 'When editing any post or page in wp-admin — or when viewing the live site while logged in — a "Sucuri Cache Purge" menu appears in the admin bar.', 'lookit-sucuri-cache-purge' ); ?></li>
				<li><?php esc_html_e( 'Clicking "Purge This URL" sends only the current page URL to the Sucuri WAF API — clearing that single URL from the edge cache.', 'lookit-sucuri-cache-purge' ); ?></li>
				<li><?php esc_html_e( '"Purge Entire Site" clears the full Sucuri cache (with confirmation).', 'lookit-sucuri-cache-purge' ); ?></li>
				<li><?php esc_html_e( 'Sucuri takes up to 2 minutes to fully propagate a cache clear across its edge network — this is normal.', 'lookit-sucuri-cache-purge' ); ?></li>
				<li><?php esc_html_e( 'To protect against accidental hammering, the plugin limits you to 6 purges per minute.', 'lookit-sucuri-cache-purge' ); ?></li>
			</ul>

			<h2><?php esc_html_e( 'Important Notes', 'lookit-sucuri-cache-purge' ); ?></h2>
			<ul style="list-style:disc;margin-left:1.5em;line-height:1.8;">
				<li>
					<strong><?php esc_html_e( 'Static files cache differently.', 'lookit-sucuri-cache-purge' ); ?></strong>
					<?php esc_html_e( 'Sucuri caches images, CSS, JS, PDFs, and fonts on its edge for up to 72 hours regardless of per-URL purging. If you change a stylesheet or image, use "Purge Entire Site" or versioning (e.g. ?ver=1.2.3).', 'lookit-sucuri-cache-purge' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( '2-minute propagation.', 'lookit-sucuri-cache-purge' ); ?></strong>
					<?php esc_html_e( 'After a successful purge, Sucuri takes up to 2 minutes to fully flush the cache across all edge servers. If you do not see your change immediately, wait and reload.', 'lookit-sucuri-cache-purge' ); ?>
				</li>
			</ul>
		</div>
		<?php
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
