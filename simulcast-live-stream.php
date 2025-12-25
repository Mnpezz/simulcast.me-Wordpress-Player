<?php
/**
 * Plugin Name: Simulcast.me Live Stream
 * Description: Embed your self-hosted Simulcast.me livestream on your WordPress site.
 * Version: 1.0.1
 * Author: mnpezz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register Settings
function simulcast_register_settings() {
	register_setting( 'simulcast_options_group', 'simulcast_api_key' );
}
add_action( 'admin_init', 'simulcast_register_settings' );

// Add Settings Page
function simulcast_register_options_page() {
	add_options_page(
		'Simulcast.me Stream',
		'Simulcast.me Stream',
		'manage_options',
		'simulcast-stream',
		'simulcast_options_page_html'
	);
}
add_action( 'admin_menu', 'simulcast_register_options_page' );

// Settings Page HTML
function simulcast_options_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
			<?php
			settings_fields( 'simulcast_options_group' );
			do_settings_sections( 'simulcast_options_group' );
			?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Simulcast API Key</th>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="password" id="simulcast_api_key" name="simulcast_api_key" value="<?php echo esc_attr( get_option( 'simulcast_api_key' ) ); ?>" class="regular-text" />
                            <button type="button" class="button" id="toggle_api_key">Show</button>
                        </div>
                        <p class="description">Enter your Simulcast.me API Key.</p>
                    </td>
                </tr>
            </table>
			<?php submit_button(); ?>
        </form>
        <script>
            document.getElementById('toggle_api_key').addEventListener('click', function() {
                var input = document.getElementById('simulcast_api_key');
                if (input.type === 'password') {
                    input.type = 'text';
                    this.textContent = 'Hide';
                } else {
                    input.type = 'password';
                    this.textContent = 'Show';
                }
            });
        </script>
    </div>
	<?php
}

// REST API Endpoint for Proxy
add_action( 'rest_api_init', function () {
	register_rest_route( 'simulcast/v1', '/status', array(
		'methods' => 'GET',
		'callback' => 'simulcast_get_stream_status',
		'permission_callback' => '__return_true', // Public endpoint used by frontend
	) );
} );

function simulcast_get_stream_status() {
	$api_key = get_option( 'simulcast_api_key' );
	if ( empty( $api_key ) ) {
		return new WP_Error( 'no_api_key', 'API Key is missing', array( 'status' => 500 ) );
	}

	$response = wp_remote_get( 'https://simulcast.me/api/public/stream/status', array(
		'headers' => array(
			'X-API-Key' => $api_key
		)
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$body = wp_remote_retrieve_body( $response );
	return rest_ensure_response( json_decode( $body ) );
}

// Shortcode
function simulcast_player_shortcode() {
	// Enqueue scripts and styles only when shortcode is used
	wp_enqueue_style( 'videojs-css', 'https://vjs.zencdn.net/8.6.1/video-js.css', array(), '8.6.1' );
	wp_enqueue_script( 'videojs-js', 'https://vjs.zencdn.net/8.6.1/video.min.js', array(), '8.6.1', true );

	wp_enqueue_style( 'simulcast-style', plugin_dir_url( __FILE__ ) . 'assets/css/simulcast-style.css', array(), '1.0.0' );
	wp_enqueue_script( 'simulcast-script', plugin_dir_url( __FILE__ ) . 'assets/js/simulcast-player.js', array( 'jquery', 'videojs-js' ), '1.0.0', true );

	// Pass the local REST API URL instead of the external one. No API key exposed.
	wp_localize_script( 'simulcast-script', 'simulcastData', array(
		'apiUrl' => rest_url( 'simulcast/v1/status' )
	) );

	ob_start();
	?>
    <div id="simulcast-wrapper">
        <div id="simulcast-status" class="simulcast-status">Loading...</div>
        <div class="video-container">
            <video id="simulcast-video" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto" width="640" height="360" style="display:none;"></video>
        </div>
    </div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'simulcast_player', 'simulcast_player_shortcode' );
