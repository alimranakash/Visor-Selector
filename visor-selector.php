<?php
/*
Plugin Name:    Visor Selector
Description:    A WooCommerce product selector for helmets, integrated with Elementor.
Version:        2.06
Author:         Heated Visor Dev
*/

function hv_get_visor_products() {
    $cache_key   = 'hv_visor_product_map';
    $product_map = get_transient($cache_key);

    if (false === $product_map) {
        $posts = get_posts([
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => 'simple',
                ],
            ],
        ]);

        $product_map = [];
        foreach ($posts as $post) {
            $product = wc_get_product($post->ID);
            $make    = strtolower(trim($product->get_attribute('pa_helmet_make')));
            $model   = trim($product->get_attribute('pa_helmet_model'));
            $pack    = trim($product->get_attribute('pa_pack_type'));

            if (!$make || !$model || !$pack) {
                continue;
            }

            $product_map[] = [
                'id'    => $product->get_id(),
                'make'  => $make,
                'model' => $model,
                'pack'  => $pack,
                'price' => $product->get_price(),
            ];
        }

        set_transient($cache_key, $product_map, DAY_IN_SECONDS);
    }

    return $product_map;
}

function hv_clear_visor_product_cache($post_id) {
    if (get_post_type($post_id) === 'product') {
        delete_transient('hv_visor_product_map');

        // Clear insert pricing cache more specifically
        $product = wc_get_product($post_id);
        if ($product) {
            $make = $product->get_attribute('pa_helmet_make');
            $model = $product->get_attribute('pa_helmet_model');
            if ($make && $model) {
                $cache_key = 'hv_insert_price_' . sanitize_title($make . '_' . $model);
                wp_cache_delete($cache_key, 'visor_selector');
            }
        }
    }
}

add_action('save_post_product', 'hv_clear_visor_product_cache');
add_action('before_delete_post', 'hv_clear_visor_product_cache');
add_action('trash_post', 'hv_clear_visor_product_cache');

// Plugin activation hook
register_activation_hook(__FILE__, 'hv_plugin_activate');

function hv_plugin_activate() {
    // Set default settings on activation
    $default_settings = [
        'extra_battery_price' => '134.99',
        'extra_insert_price' => '194.99',
        'extra_insert_enabled' => '1',
        'menu_location' => 'woocommerce'
    ];

    // Only set defaults if no settings exist
    if (!get_option('hv_settings')) {
        add_option('hv_settings', $default_settings);
    }
}

// ------------------------------------------------------------
// Admin Settings Page
// ------------------------------------------------------------

// Add admin menu
add_action('admin_menu', 'hv_add_admin_menu');

function hv_add_admin_menu() {
    // Get menu preference from settings (default to 'woocommerce')
    $settings = hv_get_settings();
    $menu_location = isset($settings['menu_location']) ? $settings['menu_location'] : 'woocommerce';

    switch ($menu_location) {
        case 'main_menu':
            // Add as dedicated main menu item
            add_menu_page(
                'Visor Selector',
                'Visor Selector',
                'manage_options',
                'visor-selector',
                'hv_settings_page',
                'dashicons-visibility',
                30
            );

            // Add submenu items for better organization
            add_submenu_page(
                'visor-selector',
                'Settings',
                'Settings',
                'manage_options',
                'visor-selector',
                'hv_settings_page'
            );

            add_submenu_page(
                'visor-selector',
                'Product Cache',
                'Product Cache',
                'manage_options',
                'visor-selector-cache',
                'hv_cache_page'
            );
            break;

        case 'woocommerce':
        default:
            // Check if WooCommerce is active
            if (class_exists('WooCommerce')) {
                // Add to WooCommerce submenu
                add_submenu_page(
                    'woocommerce',
                    'Visor Selector Settings',
                    'Visor Selector',
                    'manage_woocommerce',
                    'visor-selector-settings',
                    'hv_settings_page'
                );
            } else {
                // Fallback: Add as main menu item if WooCommerce is not available
                add_menu_page(
                    'Visor Selector Settings',
                    'Visor Selector',
                    'manage_options',
                    'visor-selector-settings',
                    'hv_settings_page',
                    'dashicons-visibility',
                    30
                );
            }
            break;
    }
}

// Register settings
add_action('admin_init', 'hv_settings_init');

function hv_settings_init() {
    register_setting('hv_settings', 'hv_settings', [
        'sanitize_callback' => 'hv_sanitize_settings'
    ]);

    add_settings_section(
        'hv_pricing_section',
        __('Extras Pricing Configuration', 'hv'),
        'hv_pricing_section_callback',
        'hv_settings'
    );

    add_settings_field(
        'extra_battery_price',
        __('Extra Battery Price (£)', 'hv'),
        'hv_extra_battery_price_render',
        'hv_settings',
        'hv_pricing_section'
    );

    add_settings_field(
        'extra_insert_price',
        __('Extra Insert Price (£)', 'hv'),
        'hv_extra_insert_price_render',
        'hv_settings',
        'hv_pricing_section'
    );

    add_settings_field(
        'extra_insert_enabled',
        __('Enable Extra Insert Option', 'hv'),
        'hv_extra_insert_enabled_render',
        'hv_settings',
        'hv_pricing_section'
    );

    // Add general settings section
    add_settings_section(
        'hv_general_section',
        __('General Settings', 'hv'),
        'hv_general_section_callback',
        'hv_settings'
    );

    add_settings_field(
        'menu_location',
        __('Admin Menu Location', 'hv'),
        'hv_menu_location_render',
        'hv_settings',
        'hv_general_section'
    );
}

function hv_pricing_section_callback() {
    echo __('Configure pricing for extra items and options.', 'hv');
}

function hv_extra_battery_price_render() {
    $options = get_option('hv_settings');
    $price = isset($options['extra_battery_price']) ? $options['extra_battery_price'] : '134.99';
    ?>
    <input type='number' step='0.01' min='0' name='hv_settings[extra_battery_price]' value='<?php echo esc_attr($price); ?>'>
    <p class="description">Set the price for extra battery in pounds (£)</p>
    <?php
}

function hv_extra_insert_price_render() {
    $options = get_option('hv_settings');
    $price = isset($options['extra_insert_price']) ? $options['extra_insert_price'] : '194.99';
    ?>
    <input type='number' step='0.01' min='0' name='hv_settings[extra_insert_price]' value='<?php echo esc_attr($price); ?>'>
    <p class="description">Set the price for extra insert in pounds (£)</p>
    <?php
}

function hv_extra_insert_enabled_render() {
    $options = get_option('hv_settings');
    $enabled = isset($options['extra_insert_enabled']) ? $options['extra_insert_enabled'] : '1';
    ?>
    <input type='checkbox' name='hv_settings[extra_insert_enabled]' <?php checked($enabled, 1); ?> value='1'>
    <p class="description">Enable the extra insert option for customers</p>
    <?php
}

function hv_general_section_callback() {
    echo __('Configure general plugin settings and preferences.', 'hv');
}

function hv_menu_location_render() {
    $options = get_option('hv_settings');
    $location = isset($options['menu_location']) ? $options['menu_location'] : 'woocommerce';
    ?>
    <select name='hv_settings[menu_location]'>
        <option value='woocommerce' <?php selected($location, 'woocommerce'); ?>>WooCommerce Submenu</option>
        <option value='main_menu' <?php selected($location, 'main_menu'); ?>>Dedicated Main Menu</option>
    </select>
    <p class="description">Choose where the Visor Selector settings appear in the admin menu. <strong>Note:</strong> Changes require page refresh to take effect.</p>
    <?php
}

// Cache management page
function hv_cache_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Handle cache clearing
    if (isset($_POST['clear_cache']) && wp_verify_nonce($_POST['_wpnonce'], 'hv_clear_cache')) {
        delete_transient('hv_visor_product_map');
        add_settings_error('hv_cache_messages', 'cache_cleared', __('Product cache cleared successfully!'), 'updated');
    }

    settings_errors('hv_cache_messages');

    // Get cache info
    $cache_data = get_transient('hv_visor_product_map');
    $cache_exists = $cache_data !== false;
    $product_count = $cache_exists ? count($cache_data) : 0;
    ?>
    <div class="wrap">
        <h1>Visor Selector - Product Cache</h1>

        <div class="hv-cache-info" style="margin: 20px 0; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa;">
            <h3>Cache Status</h3>
            <p><strong>Status:</strong> <?php echo $cache_exists ? '<span style="color: green;">Active</span>' : '<span style="color: orange;">Empty</span>'; ?></p>
            <p><strong>Products Cached:</strong> <?php echo $product_count; ?></p>
            <p><strong>Cache Duration:</strong> 24 hours</p>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field('hv_clear_cache'); ?>
            <p>Clear the product cache to force a refresh of all visor product data. This is useful after adding, editing, or deleting products.</p>
            <input type="submit" name="clear_cache" class="button button-secondary" value="Clear Product Cache"
                   onclick="return confirm('Are you sure you want to clear the product cache?');">
        </form>

        <?php if ($cache_exists && $product_count > 0): ?>
        <div style="margin-top: 30px;">
            <h3>Cached Products Preview</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Make</th>
                        <th>Model</th>
                        <th>Pack Type</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $preview_count = 0;
                    foreach ($cache_data as $product):
                        if ($preview_count >= 10) break; // Show only first 10
                    ?>
                    <tr>
                        <td><?php echo esc_html($product['id']); ?></td>
                        <td><?php echo esc_html($product['make']); ?></td>
                        <td><?php echo esc_html($product['model']); ?></td>
                        <td><?php echo esc_html($product['pack']); ?></td>
                        <td>£<?php echo esc_html($product['price']); ?></td>
                    </tr>
                    <?php
                        $preview_count++;
                    endforeach;
                    ?>
                </tbody>
            </table>
            <?php if ($product_count > 10): ?>
            <p><em>Showing first 10 of <?php echo $product_count; ?> cached products.</em></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

// Settings page HTML
function hv_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Handle settings update messages
    if (isset($_GET['settings-updated'])) {
        add_settings_error('hv_messages', 'hv_message', __('Settings Saved'), 'updated');
    }

    settings_errors('hv_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action='options.php' method='post'>
            <?php
            settings_fields('hv_settings');
            do_settings_sections('hv_settings');
            submit_button(__('Save Settings', 'hv'));
            ?>
        </form>

        <div class="hv-settings-info" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa;">
            <h3>Plugin Information</h3>
            <p><strong>Version:</strong> 1.3</p>
            <p><strong>Description:</strong> Configure pricing and options for the Visor Selector plugin.</p>
            <p><strong>Note:</strong> Changes will take effect immediately on the frontend.</p>
        </div>
    </div>
    <?php
}

// Helper function to get plugin settings with defaults
function hv_get_settings() {
    $defaults = [
        'extra_battery_price' => '134.99',
        'extra_insert_price' => '194.99',
        'extra_insert_enabled' => '1',
        'menu_location' => 'woocommerce'
    ];

    $options = get_option('hv_settings', []);
    return wp_parse_args($options, $defaults);
}

// Sanitize settings input
function hv_sanitize_settings($input) {
    $sanitized = [];

    // Sanitize extra battery price
    if (isset($input['extra_battery_price'])) {
        $price = floatval($input['extra_battery_price']);
        // Ensure price is between 0 and 9999.99
        if ($price < 0) {
            $price = 0;
            add_settings_error('hv_messages', 'price_negative', __('Price cannot be negative. Set to 0.', 'hv'));
        } elseif ($price > 9999.99) {
            $price = 9999.99;
            add_settings_error('hv_messages', 'price_too_high', __('Price too high. Maximum is £9999.99.', 'hv'));
        }
        $sanitized['extra_battery_price'] = number_format($price, 2, '.', '');
    }

    // Sanitize extra insert price
    if (isset($input['extra_insert_price'])) {
        $price = floatval($input['extra_insert_price']);
        // Ensure price is between 0 and 9999.99
        if ($price < 0) {
            $price = 0;
            add_settings_error('hv_messages', 'insert_price_negative', __('Insert price cannot be negative. Set to 0.', 'hv'));
        } elseif ($price > 9999.99) {
            $price = 9999.99;
            add_settings_error('hv_messages', 'insert_price_too_high', __('Insert price too high. Maximum is £9999.99.', 'hv'));
        }
        $sanitized['extra_insert_price'] = number_format($price, 2, '.', '');
    }

    // Sanitize extra insert enabled checkbox
    $sanitized['extra_insert_enabled'] = isset($input['extra_insert_enabled']) ? '1' : '0';

    // Sanitize menu location
    if (isset($input['menu_location'])) {
        $allowed_locations = ['woocommerce', 'main_menu'];
        $sanitized['menu_location'] = in_array($input['menu_location'], $allowed_locations)
            ? $input['menu_location']
            : 'woocommerce';
    }

    return $sanitized;
}

// Get insert product price by make and model
function hv_get_insert_product_price($make, $model) {
    // Cache key for performance
    $cache_key = 'hv_insert_price_' . sanitize_title($make . '_' . $model);
    $cached_price = wp_cache_get($cache_key, 'visor_selector');

    if ($cached_price !== false) {
        return floatval($cached_price);
    }

    // Query for insert product with specific attributes
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => '_stock_status',
                'value' => 'instock',
                'compare' => '='
            )
        ),
        'tax_query' => array(
            'relation' => 'AND',
            array(
                'taxonomy' => 'pa_helmet_make',
                'field' => 'slug',
                'terms' => sanitize_title($make),
                'operator' => 'IN'
            ),
            array(
                'taxonomy' => 'pa_helmet_model',
                'field' => 'slug',
                'terms' => sanitize_title($model),
                'operator' => 'IN'
            ),
            array(
                'taxonomy' => 'pa_pack_type',
                'field' => 'slug',
                'terms' => 'insert-only',
                'operator' => 'IN'
            )
        )
    );

    $query = new WP_Query($args);
    $price = 0;

    if ($query->have_posts()) {
        $product_id = $query->posts[0]->ID;
        $product = wc_get_product($product_id);

        if ($product) {
            $price = floatval($product->get_regular_price());
        }
    }

    wp_reset_postdata();

    // Fallback to default if no product found
    if ($price <= 0) {
        $settings = hv_get_settings();
        $price = !empty($settings['extra_insert_price']) ? floatval($settings['extra_insert_price']) : 194.99;
    }

    // Cache the result for 1 hour
    wp_cache_set($cache_key, $price, 'visor_selector', 3600);

    return $price;
}

// Get extras pricing dynamically
function hv_get_extras_pricing($make = '', $model = '') {
    $settings = hv_get_settings();
    $pricing = [];

    // Add extra battery price (fixed pricing from settings)
    $battery_price = !empty($settings['extra_battery_price']) ? $settings['extra_battery_price'] : '134.99';
    $pricing['extra-battery'] = floatval($battery_price);

    // Add extra insert price (dynamic pricing based on product lookup)
    if (!empty($make) && !empty($model)) {
        $pricing['extra-insert'] = hv_get_insert_product_price($make, $model);
    } else {
        // Fallback to settings if make/model not provided
        $insert_price = !empty($settings['extra_insert_price']) ? $settings['extra_insert_price'] : '194.99';
        $pricing['extra-insert'] = floatval($insert_price);
    }

    return $pricing;
}








add_action('init', function () {
    add_shortcode('visor_selector', function () {
        ob_start();

        // Get products from cache
        $product_map = hv_get_visor_products();

        $upload_base = wp_get_upload_dir()['baseurl'];
        $logos = [
            'shoei'     => $upload_base . '/2025/08/logo-shoei.png',
            'arai'      => $upload_base . '/2025/08/logo-arai.png',
			'airoh'		=> $upload_base . '/2025/12/logow-airoh.png',
            'klim'      => $upload_base . '/2025/08/logo-klim.png',
            'schuberth' => $upload_base . '/2025/08/logo-schuberth.png',
            'universal' => $upload_base . '/2025/08/logo-universal.png',
			'visin' => $upload_base . '/2025/09/visin-bestfit.png',
        ];

        // Pass data to JS
        wp_localize_script('visor-selector', 'visorData', [
            'products' => $product_map,
            'logos'    => $logos,
            'checkout_url' => wc_get_checkout_url(),
            'cart_url' => wc_get_cart_url(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hv_add_to_cart'),
            'extras_pricing' => hv_get_extras_pricing(), // Only battery pricing, insert is dynamic
            'settings' => hv_get_settings()
        ]);

        // Output selector container
        echo '<div id="visor-selector">';
		echo '<h2 class="selector-title">1. BRAND</h2>';
        echo '<div id="make-select" class="make-grid"></div>';
		echo '<a href="/no-brand-found" style="color: #fff; font-size: 14px;"><em>My brand is not listed?</em></a>';
		echo '<h2 class="selector-title">2. MODEL</h2>';
		echo '<div class="model-select-row">
    		    <select id="model-select"><option>Select Model</option></select>
        		<button id="model-unknown" type="button"><a href="/no-brand-found/"><span style="color: #fff;">OTHER / UNKNOWN</span></a></button>
			  </div>';
		echo '<h2 class="selector-title">3. PACK TYPE</h2>';
		echo '<div class="pack-wrap"><input type="radio" name="pack" id="pack-full" value="Full Pack"><label for="pack-full">Full Pack</label><input type="radio" name="pack" id="pack-insert" value="Insert Only"><label for="pack-insert">Insert Only</label></div>';
        echo '<div id="battery-colour-wrap" style="display:none; margin-top: 20px;"><h2 class="selector-title">BATTERY PACK COLOUR</h2><div class="battery-wrap"><input type="radio" name="battery_colour" id="battery-black" value="Black"><label for="battery-black">Black</label><input type="radio" name="battery_colour" id="battery-grey" value="Grey"><label for="battery-grey">Grey</label></div></div>';

        echo '<div id="extras-wrap" style="display:none; margin-top: 20px;"><h2 class="selector-title">OPTIONAL EXTRAS</h2><div class="extras-options"><input type="checkbox" name="extras" id="extras-battery" value="extra-battery"><label for="extras-battery">Extra Battery</label><input type="checkbox" name="extras" id="extras-insert" value="extra-insert"><label for="extras-insert">Extra Insert</label><button type="button" id="clear-extras" class="clear-button">clear</button></div></div>';

        echo '<div id="visor-price">YOUR PRICE: £</div>';
        echo '<div class="action-buttons">';
        echo '<button id="add-to-cart" disabled>Add to Cart</button>';
        echo '<button type="button" id="reset-selection" class="reset-button">Reset Selection</button>';
        echo '</div>';
        echo '<div id="visor-message" class="visor-message" style="display: none;"></div>';
        echo '</div>';

        $output = ob_get_clean();

        // Remove any <br> tags that might have been added
        $output = str_replace(['<br>', '<br/>', '<br />'], '', $output);

        return $output;
    });
});


// AJAX handler for adding to cart
add_action('wp_ajax_hv_add_to_cart', 'hv_handle_add_to_cart');
add_action('wp_ajax_nopriv_hv_add_to_cart', 'hv_handle_add_to_cart');

function hv_handle_add_to_cart() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'hv_add_to_cart')) {
        wp_send_json_error('Security check failed');
        return;
    }

    // Get product ID
    $product_id = intval($_POST['product_id']);
    if (!$product_id) {
        wp_send_json_error('Invalid product ID');
    }

    // Get quantity
    $quantity = intval($_POST['quantity']) ?: 1;

    // Get product details for configuration storage
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error('Product not found');
    }

    // Prepare cart item data
    $cart_item_data = [];

    // Store product configuration details from frontend selection
    $cart_item_data['hv_configuration'] = [
        'make' => !empty($_POST['make']) ? sanitize_text_field($_POST['make']) : $product->get_attribute('pa_helmet_make'),
        'model' => !empty($_POST['model']) ? sanitize_text_field($_POST['model']) : $product->get_attribute('pa_helmet_model'),
        'pack_type' => !empty($_POST['pack_type']) ? sanitize_text_field($_POST['pack_type']) : $product->get_attribute('pa_pack_type')
    ];

    // Handle extras

    if (!empty($_POST['extras']) && is_array($_POST['extras'])) {
        $extras = [];
        foreach ($_POST['extras'] as $extra) {
            $extras[] = sanitize_text_field($extra);
        }
        if ($extras) {
            $cart_item_data['hv_extras'] = $extras;
        }
    }

    // Handle battery color addon
    if (!empty($_POST['battery_color'])) {
        $cart_item_data['hv_battery_color'] = sanitize_text_field($_POST['battery_color']);
    }

    // Add main product to cart
    $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, [], $cart_item_data);

    if ($cart_item_key) {
        wp_send_json_success([
            'message' => 'Product added to cart successfully!',
            'cart_url' => wc_get_cart_url(),
            'cart_count' => WC()->cart->get_cart_contents_count()
        ]);
    } else {
        wp_send_json_error('Failed to add product to cart');
    }
}

// Enqueue JS on frontend
add_action('wp_enqueue_scripts', function () {
    if (!is_singular()) return; // Only enqueue on single product/page/post

    wp_register_script(
        'visor-selector',
        plugins_url('assets/visor.js', __FILE__),
        [],
        time(),
        true
    );

    wp_enqueue_script('visor-selector');
	// ✅ Register and enqueue CSS
    wp_register_style(
        'visor-selector-style',
        plugins_url('assets/style.css', __FILE__),
        [],
        time()
    );
    wp_enqueue_style('visor-selector-style');

});

// ------------------------------------------------------------
// Extras handling
// ------------------------------------------------------------

// Store selected extras on the cart item
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id) {
    $extras = [];
    if (!empty($_POST['extras']) && is_array($_POST['extras'])) {
        foreach ($_POST['extras'] as $extra) {
            $extras[] = sanitize_text_field($extra);
        }
    }
    if ($extras) {
        $cart_item_data['hv_extras'] = $extras;
    }
    return $cart_item_data;
}, 10, 2);

// Display configuration and extras in the cart line item
add_filter('woocommerce_get_item_data', 'hv_display_cart_item_data', 20, 2);



function hv_display_cart_item_data($item_data, $cart_item) {

    // Display configuration details
    if (!empty($cart_item['hv_configuration'])) {
        $config = $cart_item['hv_configuration'];

        if (!empty($config['make'])) {
            $item_data[] = [
                'key'   => __('Helmet Make', 'hv'),
                'value' => esc_html($config['make']),
            ];
        }

        if (!empty($config['model'])) {
            $item_data[] = [
                'key'   => __('Helmet Model', 'hv'),
                'value' => esc_html($config['model']),
            ];
        }

        if (!empty($config['pack_type'])) {
            $item_data[] = [
                'key'   => __('Pack Type', 'hv'),
                'value' => esc_html($config['pack_type']),
            ];
        }
    }

    // Display battery color
    if (!empty($cart_item['hv_battery_color'])) {
        $item_data[] = [
            'key'   => __('Battery Pack Colour', 'hv'),
            'value' => esc_html($cart_item['hv_battery_color']),
        ];
    }

    // Display extras
    if (!empty($cart_item['hv_extras']) && is_array($cart_item['hv_extras'])) {
        foreach ($cart_item['hv_extras'] as $extra) {
            if ($extra === 'extra-battery') {
                $item_data[] = [
                    'key'   => __('Extra Battery', 'hv'),
                    'value' => __('Yes', 'hv'),
                ];
            } elseif ($extra === 'extra-insert') {
                $item_data[] = [
                    'key'   => __('Extra Insert', 'hv'),
                    'value' => __('Yes', 'hv'),
                ];
            }
        }
    }

    return $item_data;
}



// Display configuration details in order items (checkout, emails, admin)
add_filter('woocommerce_order_item_display_meta_key', function($display_key, $meta, $item) {
    // Customize the display of our custom meta keys
    switch($display_key) {
        case 'hv_configuration':
            return __('Configuration', 'hv');
        case 'hv_battery_color':
            return __('Battery Pack Colour', 'hv');
        case 'hv_extras':
            return __('Extras', 'hv');
        default:
            return $display_key;
    }
}, 10, 3);

// Save configuration details to order items
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    // Save configuration details
    if (!empty($values['hv_configuration'])) {
        $config = $values['hv_configuration'];

        if (!empty($config['make'])) {
            $item->add_meta_data(__('Helmet Make', 'hv'), $config['make']);
        }

        if (!empty($config['model'])) {
            $item->add_meta_data(__('Helmet Model', 'hv'), $config['model']);
        }

        if (!empty($config['pack_type'])) {
            $item->add_meta_data(__('Pack Type', 'hv'), $config['pack_type']);
        }
    }

    // Save battery color
    if (!empty($values['hv_battery_color'])) {
        $item->add_meta_data(__('Battery Pack Colour', 'hv'), $values['hv_battery_color']);
    }

    // Save extras
    if (!empty($values['hv_extras']) && is_array($values['hv_extras'])) {
        foreach ($values['hv_extras'] as $extra) {
            if ($extra === 'extra-battery') {
                $item->add_meta_data(__('Extra Battery', 'hv'), __('Yes', 'hv'));
            } elseif ($extra === 'extra-insert') {
                $item->add_meta_data(__('Extra Insert', 'hv'), __('Yes', 'hv'));
            }
        }
    }
}, 10, 4);

// Backup method: Also handle via WooCommerce filter for form submissions
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id) {
    // Only process if not already handled by AJAX
    if (empty($cart_item_data['hv_extras']) && !empty($_POST['extras']) && is_array($_POST['extras'])) {
        $extras = [];
        foreach ($_POST['extras'] as $extra) {
            $extras[] = sanitize_text_field($extra);
        }
        if ($extras) {
            $cart_item_data['hv_extras'] = $extras;
        }
    }

    // Handle configuration if not already set
    if (empty($cart_item_data['hv_configuration'])) {
        $product = wc_get_product($product_id);
        $cart_item_data['hv_configuration'] = [
            'make' => !empty($_POST['make']) ? sanitize_text_field($_POST['make']) : $product->get_attribute('pa_helmet_make'),
            'model' => !empty($_POST['model']) ? sanitize_text_field($_POST['model']) : $product->get_attribute('pa_helmet_model'),
            'pack_type' => !empty($_POST['pack_type']) ? sanitize_text_field($_POST['pack_type']) : $product->get_attribute('pa_pack_type')
        ];
    }

    // Handle battery color if not already set
    if (empty($cart_item_data['hv_battery_color']) && !empty($_POST['battery_color'])) {
        $cart_item_data['hv_battery_color'] = sanitize_text_field($_POST['battery_color']);
    }

    return $cart_item_data;
}, 10, 2);

// Save cart item data to order items
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    // Save configuration
    if (!empty($values['hv_configuration'])) {
        foreach ($values['hv_configuration'] as $key => $value) {
            $item->add_meta_data('hv_' . $key, $value);
        }
    }

    // Save battery color
    if (!empty($values['hv_battery_color'])) {
        $item->add_meta_data('hv_battery_color', $values['hv_battery_color']);
    }

    // Save extras
    if (!empty($values['hv_extras']) && is_array($values['hv_extras'])) {
        foreach ($values['hv_extras'] as $extra) {
            if ($extra === 'extra-battery') {
                $item->add_meta_data('Extra Battery', 'Yes');
            } elseif ($extra === 'extra-insert') {
                $item->add_meta_data('Extra Insert', 'Yes');
            }
        }
    }
}, 10, 4);

// Adjust price when extras are chosen
add_action('woocommerce_before_calculate_totals', function ($cart) {
    // Skip if in admin but not during AJAX
    if (is_admin() && !wp_doing_ajax()) {
        return;
    }

    // Prevent infinite loops
    if (did_action('woocommerce_before_calculate_totals') >= 2) {
        return;
    }

    // Skip if cart is empty
    if ($cart->is_empty()) {
        return;
    }

    // Get cart contents
    $cart_contents = $cart->get_cart();

    foreach ($cart_contents as $cart_item_key => $cart_item) {
        // Skip if no extras
        if (empty($cart_item['hv_extras']) || !is_array($cart_item['hv_extras'])) {
            continue;
        }

        // Get make and model from cart item configuration for dynamic pricing
        $make = '';
        $model = '';
        if (!empty($cart_item['hv_configuration'])) {
            $make = $cart_item['hv_configuration']['make'] ?? '';
            $model = $cart_item['hv_configuration']['model'] ?? '';
        }

        // Get extras pricing with dynamic insert pricing
        $extras_pricing = hv_get_extras_pricing($make, $model);

        // Calculate total extra cost
        $extra_total = 0;
        foreach ($cart_item['hv_extras'] as $extra) {
            if (isset($extras_pricing[$extra])) {
                $extra_total += (float) $extras_pricing[$extra];
            }
        }

        // Apply extra cost to product price
        if ($extra_total > 0) {
            $product = $cart_item['data'];
            $original_price = (float) $product->get_regular_price();
            $new_price = $original_price + $extra_total;

            // Set the new price
            $product->set_price($new_price);
        }
    }
}, 20, 1);

// Capture custom helmet text in cart item data
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id) {
    // Handle custom helmet text for Best Fit
    if (!empty($_POST['custom_helmet_text'])) {
        $cart_item_data['hv_custom_helmet'] = sanitize_text_field($_POST['custom_helmet_text']);
    }
    return $cart_item_data;
}, 5, 2);

// Display custom helmet text in cart
add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (!empty($cart_item['hv_custom_helmet'])) {
        $item_data[] = [
            'key'   => __('Helmet Details', 'hv'),
            'value' => esc_html($cart_item['hv_custom_helmet']),
        ];
    }
    return $item_data;
}, 25, 2);

// Save custom helmet text to order
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!empty($values['hv_custom_helmet'])) {
        $item->add_meta_data(__('Helmet Details', 'hv'), $values['hv_custom_helmet']);
    }
}, 15, 4);