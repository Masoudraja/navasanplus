<?php
/**
 * Quick Edit template for MNS Navasan Plus
 *
 * Renders inline‐edit fields for discount on profit and charge.
 *
 * @var WP_Post $post
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<fieldset class="inline-edit-col-right inline-edit-navasan-plus">
    <div class="inline-edit-col inline-edit-col-50">

        <label class="inline-edit-group">
            <span class="title"><?php esc_html_e( 'Discount on Profit (%)', 'mns-navasan-plus' ); ?></span>
            <span class="input-text-wrap">
                <input
                    type="number"
                    name="mns_discount_profit_percentage"
                    class="inline-edit-mns-discount-profit-percentage"
                    step="0.01"
                    value=""
                />
            </span>
        </label>

        <label class="inline-edit-group">
            <span class="title"><?php esc_html_e( 'Discount on Profit (Fixed)', 'mns-navasan-plus' ); ?></span>
            <span class="input-text-wrap">
                <input
                    type="number"
                    name="mns_discount_profit_fixed"
                    class="inline-edit-mns-discount-profit-fixed"
                    step="0.01"
                    value=""
                />
            </span>
        </label>

    </div>
    <div class="inline-edit-col inline-edit-col-50">

        <label class="inline-edit-group">
            <span class="title"><?php esc_html_e( 'Discount on Charge (%)', 'mns-navasan-plus' ); ?></span>
            <span class="input-text-wrap">
                <input
                    type="number"
                    name="mns_discount_charge_percentage"
                    class="inline-edit-mns-discount-charge-percentage"
                    step="0.01"
                    value=""
                />
            </span>
        </label>

        <label class="inline-edit-group">
            <span class="title"><?php esc_html_e( 'Discount on Charge (Fixed)', 'mns-navasan-plus' ); ?></span>
            <span class="input-text-wrap">
                <input
                    type="number"
                    name="mns_discount_charge_fixed"
                    class="inline-edit-mns-discount-charge-fixed"
                    step="0.01"
                    value=""
                />
            </span>
        </label>

    </div>
</fieldset>