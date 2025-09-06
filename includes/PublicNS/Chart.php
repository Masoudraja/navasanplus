<?php
namespace MNS\NavasanPlus\PublicNS;

if ( ! defined( 'ABSPATH' ) ) exit;

class Chart {

    public static function get_currency_history( int $currency_id ): array {
        $post = get_post( $currency_id );
        if ( ! $post || 'mnsnp_currency' !== $post->post_type ) {
            return [];
        }
        $currency = new Currency( $post );
        $history  = $currency->get_history();
        if ( ! is_array( $history ) ) {
            return [];
        }
        ksort( $history, SORT_NUMERIC );
        return $history;
    }

    /**
     * Downsample with “always include first & last”.
     *
     * @param array<int,float> $history
     * @param int              $max_points
     * @return array<int,float>
     */
    public static function downsample( array $history, int $max_points = 60 ): array {
        $count = count( $history );
        if ( $count <= $max_points || $max_points <= 0 ) {
            return $history;
        }

        $step     = (int) ceil( $count / $max_points );
        $i        = 0;
        $out      = [];
        $last_ts  = array_key_last( $history );

        foreach ( $history as $ts => $val ) {
            if ( $i === 0 || ($i % $step) === 0 ) {
                $out[ $ts ] = $val;
            }
            $i++;
        }

        // تضمین وجود آخرین نقطه
        if ( $last_ts !== null && ! array_key_exists( $last_ts, $out ) ) {
            $out[ $last_ts ] = $history[ $last_ts ];
        }

        return $out;
    }

    public static function sma( array $values, int $window = 5 ): array {
        $n = max( 1, (int) $window );
        $out = [];
        $sum = 0.0; $q = [];
        foreach ( $values as $v ) {
            $q[] = (float) $v;
            $sum += (float) $v;
            if ( count( $q ) > $n ) {
                $sum -= (float) array_shift( $q );
            }
            $out[] = ( count( $q ) === $n ) ? ( $sum / $n ) : null;
        }
        return $out;
    }

    /**
     * @param int|\MNS\NavasanPlus\PublicNS\Currency $currency
     */
    public static function prepare_currency_dataset( $currency, array $args = [] ): array {
        $defaults = [
            'max_points'  => 60,
            'sma'         => 5,
            'label'       => null,
            'color'       => '#0073aa',
            'date_format' => 'Y-m-d H:i',
        ];
        $args = wp_parse_args( $args, $defaults );

        if ( is_numeric( $currency ) ) {
            $post = get_post( (int) $currency );
            if ( ! $post ) {
                return [ 'labels' => [], 'datasets' => [], 'meta' => [] ];
            }
            $currency = new Currency( $post );
        } elseif ( ! ( $currency instanceof Currency ) ) {
            return [ 'labels' => [], 'datasets' => [], 'meta' => [] ];
        }

        $history = $currency->get_history();
        if ( empty( $history ) ) {
            return [
                'labels'   => [],
                'datasets' => [],
                'meta'     => [
                    'currency_id' => $currency->get_id(),
                    'name'        => $currency->get_name(),
                    'points'      => 0,
                ],
            ];
        }

        ksort( $history, SORT_NUMERIC );
        $history = self::downsample( $history, (int) $args['max_points'] );

        $labels = [];
        $values = [];
        foreach ( $history as $ts => $val ) {
            $labels[] = wp_date( $args['date_format'], (int) $ts );
            $values[] = (float) $val;
        }

        $hex  = (string) $args['color'];
        $rgba = self::hex_to_rgba( $hex, 0.15 );

        $dataset_main = [
            'label'           => $args['label'] ?: sprintf( __( '%s Rate', 'mns-navasan-plus' ), $currency->get_name() ),
            'data'            => $values,
            'borderColor'     => $hex,
            'backgroundColor' => $rgba,
            'pointRadius'     => 0,
            'tension'         => 0.2,
            'fill'            => true,
            'borderWidth'     => 2,
        ];

        $datasets = [ $dataset_main ];

        $sma_n = (int) $args['sma'];
        if ( $sma_n > 0 ) {
            $sma_vals = self::sma( $values, $sma_n );
            $datasets[] = [
                'label'           => sprintf( __( 'SMA(%d)', 'mns-navasan-plus' ), $sma_n ),
                'data'            => $sma_vals,
                'borderColor'     => self::tint_hex( $hex, 0.35 ),
                'backgroundColor' => 'transparent',
                'pointRadius'     => 0,
                'tension'         => 0.15,
                'fill'            => false,
                'borderWidth'     => 1,
                'borderDash'      => [6, 4],
            ];
        }

        $prepared = [
            'labels'   => $labels,
            'datasets' => $datasets,
            'meta'     => [
                'currency_id' => $currency->get_id(),
                'name'        => $currency->get_name(),
                'points'      => count( $values ),
            ],
        ];

        // فیلتر برای شخصی‌سازی
        return apply_filters( 'mnsnp/chart/prepared', $prepared, $currency, $args );
    }

    public static function chartjs_config( array $prepared, array $options = [] ): array {
        $base_options = [
            'responsive'          => true,
            'maintainAspectRatio' => false,
            'interaction'         => [
                'mode'      => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend'  => [ 'display' => true, 'position' => 'bottom' ],
                'tooltip' => [ 'enabled' => true ],
            ],
            'scales' => [
                'x' => [
                    'display' => true,
                    'ticks'   => [ 'maxRotation' => 0, 'autoSkip' => true, 'maxTicksLimit' => 8 ],
                ],
                'y' => [
                    'display' => true,
                    'ticks'   => [ 'beginAtZero' => false ],
                ],
            ],
            // برای دیتاست‌های پرنقطه کمی نرم‌تر دیده شود
            'elements' => [
                'line'   => [ 'borderJoinStyle' => 'round' ],
                'point'  => [ 'radius' => 0 ],
            ],
        ];

        $final_options = self::array_deep_merge( $base_options, $options );

        $config = [
            'type'    => 'line',
            'data'    => [
                'labels'   => $prepared['labels']   ?? [],
                'datasets' => $prepared['datasets'] ?? [],
            ],
            'options' => $final_options,
        ];

        // فیلتر برای شخصی‌سازی نهایی
        return apply_filters( 'mnsnp/chart/config', $config, $prepared, $options );
    }

    protected static function hex_to_rgba( string $hex, float $alpha = 1.0 ): string {
        $hex = ltrim( trim( $hex ), '#' );
        // فال‌بک ایمن
        if ( ! preg_match( '/^([0-9a-f]{3}|[0-9a-f]{6})$/i', $hex ) ) {
            $hex = '0073aa';
        }
        if ( strlen( $hex ) === 3 ) {
            $r = hexdec( str_repeat( $hex[0], 2 ) );
            $g = hexdec( str_repeat( $hex[1], 2 ) );
            $b = hexdec( str_repeat( $hex[2], 2 ) );
        } else {
            $r = hexdec( substr( $hex, 0, 2 ) );
            $g = hexdec( substr( $hex, 2, 2 ) );
            $b = hexdec( substr( $hex, 4, 2 ) );
        }
        $a = max( 0, min( 1, $alpha ) );
        return sprintf( 'rgba(%d,%d,%d,%.3f)', $r, $g, $b, $a );
    }

    protected static function tint_hex( string $hex, float $factor = 0.25 ): string {
        $hex = ltrim( trim( $hex ), '#' );
        if ( ! preg_match( '/^([0-9a-f]{3}|[0-9a-f]{6})$/i', $hex ) ) {
            $hex = '0073aa';
        }
        if ( strlen( $hex ) === 3 ) {
            $r = hexdec( str_repeat( $hex[0], 2 ) );
            $g = hexdec( str_repeat( $hex[1], 2 ) );
            $b = hexdec( str_repeat( $hex[2], 2 ) );
        } else {
            $r = hexdec( substr( $hex, 0, 2 ) );
            $g = hexdec( substr( $hex, 2, 2 ) );
            $b = hexdec( substr( $hex, 4, 2 ) );
        }
        $f = max( 0, min( 1, $factor ) );
        $r = (int) round( $r + (255 - $r) * $f );
        $g = (int) round( $g + (255 - $g) * $f );
        $b = (int) round( $b + (255 - $b) * $f );
        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }

    protected static function array_deep_merge( array $a, array $b ): array {
        foreach ( $b as $k => $v ) {
            if ( is_array( $v ) && isset( $a[ $k ] ) && is_array( $a[ $k ] ) ) {
                $a[ $k ] = self::array_deep_merge( $a[ $k ], $v );
            } else {
                $a[ $k ] = $v;
            }
        }
        return $a;
    }
}