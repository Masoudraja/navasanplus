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

// نرخ ارز
$rate = function( int $cid ) use ( $db ) : float {
    if ( $cid <= 0 ) return 0.0;
    $r = get_post_meta( $cid, $db->full_meta_key('currency_value'), true );
    return ($r === '' ? 0.0 : (float)$r);
};

// 1) Map متغیرها برای موتور (unit×value برای currency؛ برای custom همان value)
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

// 2) ارزیابی کامپوننت‌ها
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

// 3) اعمال تخفیف روی مبالغ سود/اجرت
$profit_after = $profit_base;
$charge_after = $charge_base;
if ( class_exists(DiscountService::class) ) {
    [ $profit_after, $charge_after ] = DiscountService::apply( (float)$profit_base, (float)$charge_base, (int)$wc->get_id() );
}

$final = (float)$other_sum + (float)$profit_after + (float)$charge_after;

// صفر اعشار برای تومان
$dec_filter = function(){ return 0; };
add_filter( 'wc_get_price_decimals', $dec_filter, 1000 );

// 4) خلاصه بالا
echo '<div class="mnsnp-breakdown-summary" style="margin:8px 0 10px;padding:8px;border:1px dashed #ddd;border-radius:6px;background:#fbfbfb">';
echo '<strong>'.esc_html__('Summary','mns-navasan-plus').'</strong><br>';
echo esc_html__('Profit (before → after): ', 'mns-navasan-plus') . wp_kses_post( wc_price($profit_base) ) . ' &rarr; <strong>' . wp_kses_post( wc_price($profit_after) ) . '</strong><br>';
echo esc_html__('Charge (before → after): ', 'mns-navasan-plus') . wp_kses_post( wc_price($charge_base) ) . ' &rarr; <strong>' . wp_kses_post( wc_price($charge_after) ) . '</strong><br>';
echo esc_html__('Other components: ', 'mns-navasan-plus') . wp_kses_post( wc_price($other_sum) ) . '<br>';
echo esc_html__('Final (after discounts): ', 'mns-navasan-plus') . '<strong> ' . wp_kses_post( wc_price($final) ) . '</strong>';
echo '</div>';

// 5) جدول جزئیات
echo '<table class="widefat striped"><thead><tr>';
echo '<th>'.esc_html__('Component', 'mns-navasan-plus').'</th>';
echo '<th>'.esc_html__('Expression', 'mns-navasan-plus').'</th>';
echo '<th>'.esc_html__('Role', 'mns-navasan-plus').'</th>';
echo '<th style="width:160px;text-align:end">'.esc_html__('Value', 'mns-navasan-plus').'</th>';
echo '</tr></thead><tbody>';

foreach ( $rows as $r ) {
    $badge = $r['role']==='profit' ? '🟢 profit' : ( $r['role']==='charge' ? '🟣 charge' : '—' );
    echo '<tr>';
    echo '<td>'.esc_html( $r['name'] ?: '—' ).'</td>';
    echo '<td><code style="white-space:pre-wrap">'.esc_html($r['exp'] ?: '—').'</code></td>';
    echo '<td>'.esc_html($badge).'</td>';
    echo '<td style="text-align:end">'.wp_kses_post( wc_price( $r['val'] ) ).'</td>';
    echo '</tr>';
}
echo '<tr><td colspan="3" style="text-align:end"><strong>'.esc_html__('Final (after discounts)', 'mns-navasan-plus').'</strong></td><td style="text-align:end"><strong>'.wp_kses_post( wc_price( $final ) ).'</strong></td></tr>';
echo '</tbody></table>';

remove_filter( 'wc_get_price_decimals', $dec_filter, 1000 );