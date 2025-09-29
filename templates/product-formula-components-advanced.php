<?php
/**
 * Admin-only preview: list/evaluate formula components with discounts
 * Vars:
 *  - $product : \MNS\NavasanPlus\PublicNS\Product  (wrapper)
 */
if ( ! defined('ABSPATH') ) exit;

use MNS\NavasanPlus\DB;
use MNS\NavasanPlus\Services\FormulaEngine;
use MNS\NavasanPlus\Services\DiscountService;

if ( ! $product || ! method_exists($product, 'get_wc') ) { echo '<p class="description">—</p>'; return; }

$wc  = $product->get_wc();
$db  = DB::instance();
$fid = (int) $wc->get_meta( $db->full_meta_key('formula_id'), true );

$vars_meta = (array) get_post_meta( $fid, $db->full_meta_key('formula_variables'), true );
$comps     = (array) get_post_meta( $fid, $db->full_meta_key('formula_components'), true );
$overAll   = (array) $wc->get_meta( $db->full_meta_key('formula_variables'), true );
$over      = (array) ($overAll[$fid] ?? []);

if ( empty($comps) ) { echo '<p class="description">'.esc_html__('No components defined for this formula.', 'mns-navasan-plus').'</p>'; return; }

// Rate Currency
$rate = function( int $cid ) use ( $db ) : float {
    if ( $cid <= 0 ) return 0.0;
    $r = get_post_meta( $cid, $db->full_meta_key('currency_value'), true );
    return ($r === '' ? 0.0 : (float)$r);
};

// 1) Map Variables for engine (unit×value for currency; for custom use same value)
$vars_for_engine = [];
foreach ( $vars_meta as $code => $row ) {
    $code = sanitize_key($code);
    if ( $code === '' ) continue;

    $cid   = (int)($row['currency_id'] ?? 0);
    $type  = ((string)($row['type'] ?? '') === 'currency' || $cid > 0) ? 'currency' : 'custom';

    $ov = $over[$code]['regular'] ?? null;
    if ( $ov === '' ) $ov = null;
    $value = $ov !== null ? (float)$ov : (float)($row['value'] ?? 0);

    if ( $type === 'currency' ) {
        $vars_for_engine[$code] = $rate($cid) * $value;
    } else {
        $vars_for_engine[$code] = (float)$value;
    }
}

// 2) Evaluate Components
$engine = new FormulaEngine();
$rows = [];
$profit_base = 0.0; $charge_base = 0.0; $other_sum = 0.0;

foreach ( $comps as $c ) {
    $name = (string)($c['name'] ?? '');
    $exp  = (string)($c['text'] ?? $c['expression'] ?? '');
    $sym  = (string)($c['symbol'] ?? '');
    $role = (string)($c['role'] ?? 'none');
    if ( ! in_array($role, ['none','profit','charge'], true) ) $role = 'none';

    $val = 0.0;
    if ( trim($exp) !== '' ) {
        try { $val = (float) $engine->evaluate( $exp, $vars_for_engine ); } catch(\Throwable $e){ $val = 0.0; }
    }

    if ( $role === 'profit' )      $profit_base += $val;
    elseif ( $role === 'charge' )  $charge_base += $val;
    else                            $other_sum   += $val;

    $rows[] = [ 'name'=>$name, 'exp'=>$exp, 'val'=>$val, 'symbol'=>$sym, 'role'=>$role ];
}

// 3) Apply discount on profit/charge amounts
$profit_after = $profit_base;
$charge_after = $charge_base;
if ( class_exists(DiscountService::class) ) {
    [ $profit_after, $charge_after ] = DiscountService::apply( (float)$profit_base, (float)$charge_base, (int)$wc->get_id() );
}

$final = (float)$other_sum + (float)$profit_after + (float)$charge_after;

// Zero decimals برای Toman
$dec_filter = function(){ return 0; };
add_filter( 'wc_get_price_decimals', $dec_filter, 1000 );

// 4) Table layout with clear sections
echo '<table class="mnsnp-sections-table" style="width: background: #f9f9f9; 100%; max-width: 750px; border-collapse: collapse; border: 1px solid #ddd; font-size: 13px;">';

// Header
echo '<tr style="background: #f9f9f9; border-bottom: 2px solid #ddd;">';
echo '<th style="padding: 8px; font-weight: bold; text-align: left; border-right: 1px solid #ddd;">Section</th>';
echo '<th style="padding: 8px; font-weight: bold; text-align: left;">Details</th>';
echo '</tr>';

// Summary Section
echo '<tr style="border-bottom: 1px solid #ddd;">';
echo '<td style="padding: 8px; vertical-align: top; border-right: 1px solid #ddd; font-weight: bold; width: 120px;">' . esc_html__('Summary', 'mns-navasan-plus') . '</td>';
echo '<td style="padding: 8px; font-size: 13px; line-height: 1.4;">';
echo esc_html__('Profit: ', 'mns-navasan-plus') . wp_kses_post( wc_price($profit_base) ) . ' → ' . wp_kses_post( wc_price($profit_after) ) . '<br>';
echo esc_html__('Charge: ', 'mns-navasan-plus') . wp_kses_post( wc_price($charge_base) ) . ' → ' . wp_kses_post( wc_price($charge_after) ) . '<br>';
echo esc_html__('Other: ', 'mns-navasan-plus') . wp_kses_post( wc_price($other_sum) ) . '<br>';
echo '<strong>' . esc_html__('Final: ', 'mns-navasan-plus') . wp_kses_post( wc_price($final) ) . '</strong>';
echo '</td></tr>';

// Components Header
echo '<tr style="border-bottom: 1px solid #ddd;">';
echo '<td style="padding: 8px; vertical-align: top; border-right: 1px solid #ddd; font-weight: bold;">' . esc_html__('Components', 'mns-navasan-plus') . '</td>';
echo '<td style="padding: 4px;">';
echo '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
echo '<tr style="background: #f5f5f5;">';
echo '<th style="padding: 4px; border: 1px solid #ddd; text-align: left;">' . esc_html__('Name', 'mns-navasan-plus') . '</th>';
echo '<th style="padding: 4px; border: 1px solid #ddd; text-align: left;">' . esc_html__('Expression', 'mns-navasan-plus') . '</th>';
echo '<th style="padding: 4px; border: 1px solid #ddd; text-align: left;">' . esc_html__('Role', 'mns-navasan-plus') . '</th>';
echo '<th style="padding: 4px; border: 1px solid #ddd; text-align: right;">' . esc_html__('Value', 'mns-navasan-plus') . '</th>';
echo '</tr>';

foreach ( $rows as $r ) {
    $badge = $r['role']==='profit' ? 'profit' : ( $r['role']==='charge' ? 'charge' : '—' );
    echo '<tr>';
    echo '<td style="padding: 4px; border: 1px solid #ddd;">'.esc_html( $r['name'] ?: '—' ).'</td>';
    echo '<td style="padding: 4px; border: 1px solid #ddd; font-family: monospace; font-size: 12px;">'.esc_html($r['exp'] ?: '—').'</td>';
    echo '<td style="padding: 4px; border: 1px solid #ddd; font-size: 12px;">'.esc_html($badge).'</td>';
    echo '<td style="padding: 4px; border: 1px solid #ddd; text-align: right;">'.wp_kses_post( wc_price( $r['val'] ) ).'</td>';
    echo '</tr>';
}
echo '<tr style="background: #f0f0f0; font-weight: bold;">';
echo '<td colspan="3" style="padding: 4px; border: 1px solid #ddd; text-align: right;">'.esc_html__('Final Total', 'mns-navasan-plus').'</td>';
echo '<td style="padding: 4px; border: 1px solid #ddd; text-align: right;">'.wp_kses_post( wc_price( $final ) ).'</td>';
echo '</tr>';
echo '</table></td></tr></table>';

remove_filter( 'wc_get_price_decimals', $dec_filter, 1000 );