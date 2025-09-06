<?php
namespace MNS\NavasanPlus\Services;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ComponentBreakdown
 *
 * خروجی: ['rows' => [ ['name','symbol','value','percent'], ... ], 'total' => float]
 */
final class ComponentBreakdown
{
    /**
     * @param string|null $mainExpr
     * @param array       $components   اشیای FormulaComponent (->get_name(), ->get_symbol(), ->execute($vars))
     * @param array       $vars         ['code' => number, ...]
     * @param object|null $engine       Services\FormulaEngine|mixed
     * @param float       $epsilon
     * @return array{rows: array<int,array{name:string,symbol:string,value:float,percent:float}>, total: float}
     */
    public static function breakdown( ?string $mainExpr, array $components, array $vars, $engine = null, float $epsilon = 1e-9 ): array
    {
        // 1) محاسبه‌ی اجزاء
        $rows     = [];
        $sumComps = 0.0;

        foreach ( $components as $comp ) {
            if ( ! is_object( $comp ) || ! method_exists( $comp, 'execute' ) ) {
                continue;
            }
            $name   = method_exists( $comp, 'get_name' )   ? (string) $comp->get_name()   : '';
            $symbol = method_exists( $comp, 'get_symbol' ) ? (string) $comp->get_symbol() : '';

            $val = 0.0;
            try {
                $val = (float) $comp->execute( $vars );
                // اگر موتور/عبارت مقدار غیرمتناهی/NaN برگرداند
                if ( function_exists('is_finite') && ! is_finite( $val ) ) {
                    $val = 0.0;
                }
            } catch ( \Throwable $e ) {
                $val = 0.0; // ایمن: ادامه بده
            }

            if ( abs( $val ) < $epsilon ) {
                $val = 0.0;
            }

            $sumComps += $val;
            $rows[] = [
                'name'    => $name,
                'symbol'  => $symbol,
                'value'   => $val,
                'percent' => 0.0, // بعداً پر می‌شود
            ];
        }

        // 2) total از mainExpr یا جمع اجزاء
        $total    = $sumComps;
        $mainExpr = is_string( $mainExpr ) ? trim( $mainExpr ) : '';

        if ( $mainExpr !== '' ) {
            if ( ! $engine && class_exists( '\\MNS\\NavasanPlus\\Services\\FormulaEngine' ) ) {
                $engine = method_exists( '\\MNS\\NavasanPlus\\Services\\FormulaEngine', 'instance' )
                    ? \MNS\NavasanPlus\Services\FormulaEngine::instance()
                    : new \MNS\NavasanPlus\Services\FormulaEngine();
            }
            if ( $engine && method_exists( $engine, 'evaluate' ) ) {
                try {
                    $eval = (float) $engine->evaluate( $mainExpr, $vars );
                    if ( function_exists('is_finite') ? is_finite( $eval ) : true ) {
                        $total = $eval;
                    }
                } catch ( \Throwable $e ) {
                    // اگر شکست خورد، همان sumComps می‌ماند
                }
            }
        }

        // 3) درصدها و Residual
        $den = ( abs( $total ) < $epsilon ) ? 0.0 : $total;

        foreach ( $rows as &$r ) {
            $r['percent'] = ( $den === 0.0 ) ? 0.0 : ( 100.0 * $r['value'] / $den );
        }
        unset( $r );

        $residual = $total - $sumComps;
        if ( abs( $residual ) >= $epsilon ) {
            $rows[] = [
                'name'    => __( 'Residual', 'mns-navasan-plus' ),
                'symbol'  => '',
                'value'   => $residual,
                'percent' => ( $den === 0.0 ) ? 0.0 : ( 100.0 * $residual / $den ),
            ];
        }

        $result = [
            'rows'  => $rows,
            'total' => $total,
        ];

        // فیلتر توسعه‌پذیری
        return apply_filters( 'mnsnp/component_breakdown/result', $result, $mainExpr, $components, $vars );
    }
}