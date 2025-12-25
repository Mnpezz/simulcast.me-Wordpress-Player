<?php
/**
 * Plugin Name: Simulcast.me Live Stream
 * Description: Embed your self-hosted Simulcast.me livestream on your WordPress site.
 * Version: 1.1.0
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
    // Also pass checkout URL and Tip Product ID
    $tip_product_id = get_option('simulcast_tip_product_id');
    
	wp_localize_script( 'simulcast-script', 'simulcastData', array(
		'apiUrl' => rest_url( 'simulcast/v1/status' ),
        'checkoutUrl' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '',
        'tipProductId' => $tip_product_id
	) );


	ob_start();
	?>
    <div id="simulcast-wrapper">
        <div id="simulcast-status" class="simulcast-status">Loading...</div>
        <div class="video-container">
            <video id="simulcast-video" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto" width="640" height="360" style="display:none;"></video>
        </div>
        
        <!-- Tip Button Section -->
        <div id="simulcast-tip-section" style="margin-top: 15px; text-align: center;">
            <button id="open-tip-modal" class="simulcast-btn-primary">💖 Support the Stream</button>
        </div>

        <!-- Tip Modal (Hidden) -->
        <div id="simulcast-tip-modal" class="simulcast-modal" style="display:none;">
            <div class="simulcast-modal-content">
                <span id="close-tip-modal" class="simulcast-close">&times;</span>
                <h3>Send a Tip</h3>
                <p>Enjoying the stream? Support with a tip!</p>
                
                <div class="simulcast-tip-options">
                    <button class="simulcast-tip-preset" data-amount="5">$5</button>
                    <button class="simulcast-tip-preset" data-amount="10">$10</button>
                    <button class="simulcast-tip-preset" data-amount="20">$20</button>
                </div>
                
                <div class="simulcast-tip-custom">
                    <label>Or enter custom amount:</label>
                    <div class="simulcast-input-group">
                        <span class="currency-symbol">$</span>
                        <input type="number" id="simulcast-custom-tip" placeholder="0.00" step="0.01" min="1">
                    </div>
                </div>

                <button id="simulcast-submit-tip" class="simulcast-btn-success">Proceed to Checkout</button>
                <div id="simulcast-tip-error" style="color: red; margin-top: 10px; display: none;"></div>
            </div>
        </div>
    </div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'simulcast_player', 'simulcast_player_shortcode' );

/*
 * ==================================================
 * Simlcast Tip / Donation Logic
 * ==================================================
 */

// 1. Ensure WooCommerce is active before doing anything
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    // 2. Check/Create the Tip Product on Global Init (so it works on frontend too)
    add_action( 'init', 'simulcast_check_tip_product' );
    function simulcast_check_tip_product() {
        if ( ! get_option( 'simulcast_tip_product_created' ) ) {
            $product_id = wc_get_product_id_by_sku( 'simulcast_tip' );
            
            if ( ! $product_id ) {
                $product = new WC_Product_Simple();
                $product->set_name( 'Stream Tip' );
                $product->set_slug( 'stream-tip' );
                $product->set_regular_price( 1.00 ); // Default base price
                $product->set_sku( 'simulcast_tip' );
                $product->set_virtual( true );
                $product->set_sold_individually( false );
                $product->set_status( 'publish' );
                $product->set_catalog_visibility( 'hidden' ); // Hide from shop
                $product->save();
                $product_id = $product->get_id();
            }

            update_option( 'simulcast_tip_product_id', $product_id );
            update_option( 'simulcast_tip_product_created', true );
        }
    }

    // 3. Capture Tip Amount when adding to cart
    add_filter( 'woocommerce_add_cart_item_data', 'simulcast_add_tip_data', 10, 2 );
    function simulcast_add_tip_data( $cart_item_data, $product_id ) {
        $tip_product_id = get_option( 'simulcast_tip_product_id' );
        if ( $product_id == $tip_product_id && isset( $_POST['simulcast_tip_amount'] ) ) {
            $amount = floatval( $_POST['simulcast_tip_amount'] );
            if ( $amount > 0 ) {
                $cart_item_data['simulcast_tip_amount'] = $amount;
                // Make unique so they can add multiple distinct tips if they want
                $cart_item_data['unique_key'] = md5( microtime() . rand() );
            }
        }
        return $cart_item_data;
    }

    // 4. Override Price in Cart
    add_action( 'woocommerce_before_calculate_totals', 'simulcast_set_tip_price' );
    function simulcast_set_tip_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['simulcast_tip_amount'] ) ) {
                $cart_item['data']->set_price( $cart_item['simulcast_tip_amount'] );
            }
        }
    }

    // 5. Display Custom Data in Cart/Checkout (Optional, improves UX)
    add_filter( 'woocommerce_get_item_data', 'simulcast_display_tip_data', 10, 2 );
    function simulcast_display_tip_data( $item_data, $cart_item ) {
        if ( isset( $cart_item['simulcast_tip_amount'] ) ) {
            // Usually not needed to show "Tip Amount: $X" because Price is already $X, 
            // but helpful if we want to confirm it's a custom amount.
             $item_data[] = array(
                 'key'   => 'Tip Amount',
                 'value' => wc_price( $cart_item['simulcast_tip_amount'] ),
             );
        }
        return $item_data;
    }
}
