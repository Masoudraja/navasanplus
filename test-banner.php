<?php
/**
 * Quick test file for Currency Banner functionality
 * 
 * To test: Add ?test_banner=1 to any WordPress page URL when logged in as admin
 */

// Hook into WordPress initialization to avoid early execution issues
add_action('init', function() {
    // Only run if test parameter is present and user is admin
    if ( isset($_GET['test_banner']) && is_admin() === false && current_user_can('manage_options') ) {
        add_action('wp_footer', function() {
            echo '<div style="margin: 20px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">';
            echo '<h3>Currency Banner Test</h3>';
            
            // Test shortcode rendering
            echo '<h4>Basic Banner:</h4>';
            echo do_shortcode('[mns_currency_banner]');
            
            echo '<h4>Minimal Style:</h4>';
            echo do_shortcode('[mns_currency_banner style="minimal" height="compact" columns="3"]');
            
            echo '<h4>Available Currencies:</h4>';
            $currencies = get_posts([
                'post_type'      => 'mnsnp_currency',
                'posts_per_page' => 5,
                'post_status'    => 'publish'
            ]);
            
            if (empty($currencies)) {
                echo '<p>No currencies found. Please create some currencies first.</p>';
            } else {
                echo '<ul>';
                foreach ($currencies as $post) {
                    $currency = new \MNS\NavasanPlus\PublicNS\Currency($post);
                    echo '<li>' . esc_html($currency->get_name()) . ' - ' . esc_html($currency->display_rate()) . '</li>';
                }
                echo '</ul>';
                
                // Test with specific currencies
                $ids = wp_list_pluck($currencies, 'ID');
                $ids_str = implode(',', array_slice($ids, 0, 3));
                echo '<h4>Specific Currencies (' . $ids_str . '):</h4>';
                echo do_shortcode('[mns_currency_banner currencies="' . $ids_str . '" style="classic"]');
            }
            
            echo '</div>';
        }, 999);
    }
});
?>