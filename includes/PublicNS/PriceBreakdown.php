<?php
namespace MNS\NavasanPlus\PublicNS;

if ( ! defined( 'ABSPATH' ) ) exit;

use MNS\NavasanPlus\DB;

final class PriceBreakdown {

    public function run(): void {
        // Primary hook - after product summary (most universally supported)
        \add_action( 'woocommerce_after_single_product_summary', [ $this, 'display_breakdown_box' ], 5 );
        // JavaScript fallback for theme compatibility
        \add_action( 'wp_footer', [ $this, 'inject_breakdown_js' ] );
    }

    /**
     * A helper function to get meta with a fallback from a variation to its parent product.
     */
    private function get_meta_with_fallback( \WC_Product $product, string $key, $default = '' ) {
        $db = DB::instance();
        $full_key = $db->full_meta_key($key);
        $value = $product->get_meta($full_key, true);

        if ( $value === '' && $product->is_type('variation') ) {
            $parent_product = \wc_get_product( $product->get_parent_id() );
            if ( $parent_product ) {
                $value = $parent_product->get_meta($full_key, true);
            }
        }

        return $value === '' ? $default : $value;
    }

    public function display_breakdown_box(): void {
        global $product;
        
        if ( ! $product || ! \is_product() ) {
            return;
        }

        $is_active = \wc_string_to_bool( $this->get_meta_with_fallback( $product, 'active' ) );
        $dep_type  = $this->get_meta_with_fallback( $product, 'dependence_type', 'simple' );

        if ( ! $is_active || ! in_array($dep_type, ['advanced', 'formula']) ) {
            return;
        }
        
        $fid = (int) $this->get_meta_with_fallback( $product, 'formula_id', 0 );
        if ( $fid <= 0 ) {
            return;
        }
        
        $db = DB::instance();
        $vars_meta = \get_post_meta( $fid, $db->full_meta_key('formula_variables'), true );
        $overAll   = $product->get_meta( $db->full_meta_key('formula_variables'), true );
        
        $vars_meta = is_array($vars_meta) ? $vars_meta : [];
        $overAll   = is_array($overAll) ? $overAll : [];
        $overrides = $overAll[$fid] ?? [];

        if ( empty($vars_meta) ) return;

        // Find the raw input values for variables with specific roles
        $display_data = [];
        foreach ( $vars_meta as $code => $row ) {
            $role = $row['role'] ?? 'none';
            if ( in_array($role, ['weight', 'profit', 'charge']) ) {
                // Try to get override value first, then fallback to formula default
                $value = null;
                if ( isset($overrides[$code]['regular']) && $overrides[$code]['regular'] !== '' ) {
                    $value = $overrides[$code]['regular'];
                } elseif ( isset($overrides[$code]) && !is_array($overrides[$code]) && $overrides[$code] !== '' ) {
                    // Handle legacy format where values were stored directly
                    $value = $overrides[$code];
                } elseif ( isset($row['value']) && $row['value'] !== '' ) {
                    // Fallback to formula default value
                    $value = $row['value'];
                }
                
                if ( $value !== null && $value !== '' ) {
                    $display_data[$role] = [
                        'label' => $row['name'] ?? $role,
                        'value' => $value,
                        'value_symbol' => $row['value_symbol'] ?? ''
                    ];
                }
            }
        }

        if ( empty($display_data) ) return;

        ?>
        <div class="mnsnp-price-breakdown-box" style="margin: 20px 0; padding: 20px; border: 2px solid #e0e0e0; border-radius: 8px; background: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); color: #333333; text-align: center;">
            <!-- <h4 style="margin: 0 0 15px 0; color: #2c3e50; font-size: 18px; font-weight: 600; border-bottom: 2px solid #3498db; padding-bottom: 8px; text-align: right;"><?php \esc_html_e('Product Details', 'mns-navasan-plus'); ?></h4> -->
            <table class="mnsnp-breakdown-table" style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tbody>
                    <?php if ( isset($display_data['weight']) ): ?>
                    <tr style="border-bottom: 1px solid #f0f0f0;">
                        <th style="padding: 12px 15px; text-align: right; font-weight: 600; color: #34495e; background: #f8f9fa;"><?php echo \esc_html( $display_data['weight']['label'] ); ?>:</th>
                        <td style="padding: 12px 15px; color: #2c3e50; font-weight: 500; text-align: left;"><?php echo \esc_html( \number_format_i18n( (float)$display_data['weight']['value'], 3 ) ); ?> <?php echo \esc_html( $display_data['weight']['value_symbol'] ?: \__('grams', 'mns-navasan-plus') ); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ( isset($display_data['profit']) ): ?>
                    <tr style="border-bottom: 1px solid #f0f0f0;">
                        <th style="padding: 12px 15px; text-align: right; font-weight: 600; color: #34495e; background: #f8f9fa;"><?php echo \esc_html( $display_data['profit']['label'] ); ?>:</th>
                        <td style="padding: 12px 15px; color: #27ae60; font-weight: 600; text-align: left;"><?php echo \esc_html( \number_format_i18n( (float)$display_data['profit']['value'], 2 ) ); ?> <?php echo \esc_html( $display_data['profit']['value_symbol'] ); ?></td>
                    </tr>
                    <?php endif; ?>

                    <?php if ( isset($display_data['charge']) ): ?>
                    <tr style="border-bottom: 1px solid #f0f0f0;">
                        <th style="padding: 12px 15px; text-align: right; font-weight: 600; color: #34495e; background: #f8f9fa;"><?php echo \esc_html( $display_data['charge']['label'] ); ?>:</th>
                        <td style="padding: 12px 15px; color: #27ae60; font-weight: 600; text-align: left;"><?php echo \esc_html( \number_format_i18n( (float)$display_data['charge']['value'], 2 ) ); ?> <?php echo \esc_html( $display_data['charge']['value_symbol'] ); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function inject_breakdown_js(): void {
        if ( ! \is_product() ) return;
        
        global $product;
        if ( ! $product ) return;
        
        $breakdown_html = $this->get_breakdown_html( $product );
        if ( empty( $breakdown_html ) ) return;
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // WoodMart specific selectors based on HTML structure
            var selectors = [
                '.woocommerce-product-gallery.images',
                '.wd-single-gallery .elementor-widget-container',
                '.elementor-element-f716c4d .elementor-widget-container',
                '.wd-carousel-container.wd-gallery-images',
                '.woocommerce-product-gallery',
                '.product-images',
                '.single-product-gallery'
            ];
            
            var $target = null;
            for (var i = 0; i < selectors.length; i++) {
                $target = $(selectors[i]);
                if ($target.length > 0) break;
            }
            
            if ($target && $target.length > 0) {
                $target.after(<?php echo \wp_json_encode( $breakdown_html ); ?>);
            }
        });
        </script>
        <?php
    }
    
    private function get_breakdown_html( $product ): string {
        $is_active = \wc_string_to_bool( $this->get_meta_with_fallback( $product, 'active' ) );
        $dep_type  = $this->get_meta_with_fallback( $product, 'dependence_type', 'simple' );

        if ( ! $is_active || ! in_array($dep_type, ['advanced', 'formula']) ) {
            return '';
        }
        
        $fid = (int) $this->get_meta_with_fallback( $product, 'formula_id', 0 );
        if ( $fid <= 0 ) {
            return '';
        }
        
        $db = DB::instance();
        $vars_meta = \get_post_meta( $fid, $db->full_meta_key('formula_variables'), true );
        $overAll   = $product->get_meta( $db->full_meta_key('formula_variables'), true );
        
        $vars_meta = is_array($vars_meta) ? $vars_meta : [];
        $overAll   = is_array($overAll) ? $overAll : [];
        $overrides = $overAll[$fid] ?? [];

        if ( empty($vars_meta) ) return '';

        // Find the raw input values for variables with specific roles
        $display_data = [];
        foreach ( $vars_meta as $code => $row ) {
            $role = $row['role'] ?? 'none';
            if ( in_array($role, ['weight', 'profit', 'charge']) ) {
                // Try to get override value first, then fallback to formula default
                $value = null;
                if ( isset($overrides[$code]['regular']) && $overrides[$code]['regular'] !== '' ) {
                    $value = $overrides[$code]['regular'];
                } elseif ( isset($overrides[$code]) && !is_array($overrides[$code]) && $overrides[$code] !== '' ) {
                    // Handle legacy format where values were stored directly
                    $value = $overrides[$code];
                } elseif ( isset($row['value']) && $row['value'] !== '' ) {
                    // Fallback to formula default value
                    $value = $row['value'];
                }
                
                if ( $value !== null && $value !== '' ) {
                    $display_data[$role] = [
                        'label' => $row['name'] ?? $role,
                        'value' => $value,
                        'value_symbol' => $row['value_symbol'] ?? ''
                    ];
                }
            }
        }

        if ( empty($display_data) ) return '';

        \ob_start();
        ?>
        <div class="mnsnp-price-breakdown-box" style="margin: 20px 0; padding: 20px; border: 2px solid #e0e0e0; border-radius: 8px; background: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); color: #333333; text-align: center;">
            <!-- <h4 style="margin: 0 0 15px 0; color: #2c3e50; font-size: 18px; font-weight: 600; border-bottom: 2px solid #3498db; padding-bottom: 8px; text-align: right;"><?php \esc_html_e('Product Details', 'mns-navasan-plus'); ?></h4> -->
            <table class="mnsnp-breakdown-table" style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tbody>
                    <?php if ( isset($display_data['weight']) ): ?>
                    <tr style="border-bottom: 1px solid #f0f0f0;">
                        <th style="padding: 12px 15px; text-align: right; font-weight: 600; color: #34495e; background: #f8f9fa;"><?php echo \esc_html( $display_data['weight']['label'] ); ?>:</th>
                        <td style="padding: 12px 15px; color: #2c3e50; font-weight: 500; text-align: left;"><?php echo \esc_html( \number_format_i18n( (float)$display_data['weight']['value'], 3 ) ); ?> <?php echo \esc_html( $display_data['weight']['value_symbol'] ?: \__('grams', 'mns-navasan-plus') ); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ( isset($display_data['profit']) ): ?>
                    <tr style="border-bottom: 1px solid #f0f0f0;">
                        <th style="padding: 12px 15px; text-align: right; font-weight: 600; color: #34495e; background: #f8f9fa;"><?php echo \esc_html( $display_data['profit']['label'] ); ?>:</th>
                        <td style="padding: 12px 15px; color: #27ae60; font-weight: 600; text-align: left;"><?php echo \esc_html( \number_format_i18n( (float)$display_data['profit']['value'], 2 ) ); ?> <?php echo \esc_html( $display_data['profit']['value_symbol'] ); ?></td>
                    </tr>
                    <?php endif; ?>

                    <?php if ( isset($display_data['charge']) ): ?>
                    <tr style="border-bottom: 1px solid #f0f0f0;">
                        <th style="padding: 12px 15px; text-align: right; font-weight: 600; color: #34495e; background: #f8f9fa;"><?php echo \esc_html( $display_data['charge']['label'] ); ?>:</th>
                        <td style="padding: 12px 15px; color: #27ae60; font-weight: 600; text-align: left;"><?php echo \esc_html( \number_format_i18n( (float)$display_data['charge']['value'], 2 ) ); ?> <?php echo \esc_html( $display_data['charge']['value_symbol'] ); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        return \ob_get_clean();
    }
}