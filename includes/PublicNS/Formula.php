<?php
/**
 * PublicNS\Formula (+ Variable & Component)
 *
 * File: includes/PublicNS/Formula.php
 */

namespace MNS\NavasanPlus\PublicNS;

use MNS\NavasanPlus\DB;
use MNS\NavasanPlus\Services\FormulaEngine; // موتور واحد ارزیابی

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Formula {

    /** @var \WP_Post */
    protected $post;

    // Meta keys (سازگار با نوسان؛ با پیشوند DB ذخیره می‌شوند)
    public const META_EXPR        = 'formula_formul';
    public const META_VARIABLES   = 'formula_variables';
    public const META_COMPONENTS  = 'formula_components';
    public const META_VARS_COUNT  = 'formula_variables_counter';
    public const META_COMPS_COUNT = 'formula_components_counter';

    /** @var FormulaVariable[]|null */
    private ?array $variables_cache = null;

    /** @var FormulaComponent[]|null */
    private ?array $components_cache = null;

    public function __construct( \WP_Post $post ) {
        $this->post = $post;
    }

    public function get_post(): \WP_Post { return $this->post; }
    public function get_id(): int        { return (int) $this->post->ID; }
    public function get_name(): string   { return (string) get_the_title( $this->post ); }

    /** متن/عبارت فرمول */
    public function get_expression(): string {
        return (string) DB::instance()->read_post_meta( $this->get_id(), self::META_EXPR, '' );
    }

    /**
     * @return FormulaVariable[]
     */
    public function get_variables(): array {
        if ( $this->variables_cache !== null ) {
            return $this->variables_cache;
        }

        $raw = DB::instance()->read_post_meta( $this->get_id(), self::META_VARIABLES, [] );
        if ( ! is_array( $raw ) ) {
            return $this->variables_cache = [];
        }

        $out = [];
        $i   = 0;
        foreach ( $raw as $codeKey => $row ) {
            if ( ! is_array( $row ) ) { continue; }

            // code: اولویت با کلید آرایه، سپس 'code' داخل آیتم، سپس اسلاگ name
            $code = is_string( $codeKey ) ? $codeKey : ( $row['code'] ?? '' );
            if ( $code === '' ) {
                $code = sanitize_title( (string) ( $row['name'] ?? ('var_'.$i) ) );
            }

            $out[] = new FormulaVariable(
                $code,
                (string) ( $row['name'] ?? $code ),
                (float)  ( $row['unit'] ?? 1 ),
                (string) ( $row['unit_symbol'] ?? '' ),
                (float)  ( $row['value'] ?? 0 ),
                (string) ( $row['value_symbol'] ?? '' )
            );
            $i++;
        }

        return $this->variables_cache = $out;
    }

    /**
     * @return FormulaComponent[]
     */
    public function get_components(): array {
        if ( $this->components_cache !== null ) {
            return $this->components_cache;
        }

        $raw = DB::instance()->read_post_meta( $this->get_id(), self::META_COMPONENTS, [] );
        if ( ! is_array( $raw ) ) {
            return $this->components_cache = [];
        }

        $out = [];
        foreach ( $raw as $row ) {
            if ( ! is_array( $row ) ) { continue; }
            $out[] = new FormulaComponent(
                (string) ( $row['name'] ?? '' ),
                (string) ( $row['text'] ?? '' ),
                (string) ( $row['symbol'] ?? '' )
            );
        }
        return $this->components_cache = $out;
    }
}

/** شیء «متغیر فرمول» */
class FormulaVariable {
    protected string $code;
    protected string $name;
    protected float  $unit;
    protected string $unit_symbol;
    protected float  $value;
    protected string $value_symbol;

    public function __construct(
        string $code,
        string $name,
        float  $unit,
        string $unit_symbol,
        float  $value,
        string $value_symbol
    ) {
        $this->code         = $code;
        $this->name         = $name;
        $this->unit         = $unit;
        $this->unit_symbol  = $unit_symbol;
        $this->value        = $value;
        $this->value_symbol = $value_symbol;
    }

    public function get_code(): string         { return $this->code; }
    public function get_name(): string         { return $this->name; }
    public function get_unit(): float          { return $this->unit; }
    public function get_unit_symbol(): string  { return $this->unit_symbol; }
    public function get_value(): float         { return $this->value; }
    public function get_value_symbol(): string { return $this->value_symbol; }
}

/** شیء «کامپوننت فرمول» */
class FormulaComponent {
    protected string $name;
    protected string $text;
    protected string $symbol;

    /** Engine واحد اشتراکی برای کاهش سربار ساخت مکرر */
    private static ?FormulaEngine $engine = null;

    public function __construct( string $name, string $text, string $symbol = '' ) {
        $this->name   = $name;
        $this->text   = $text;
        $this->symbol = $symbol;
    }

    public function get_name(): string   { return $this->name; }
    public function get_text(): string   { return $this->text; }
    public function get_symbol(): string { return $this->symbol; }

    /** گرفتن Engine مشترک */
    private static function engine(): FormulaEngine {
        if ( self::$engine === null ) {
            self::$engine = new FormulaEngine();
        }
        return self::$engine;
    }

    /**
     * اجرای متن کامپوننت با متغیرها (موتور واحد: Services\FormulaEngine)
     * - نگاشت [CODE] → CODE
     * - پاک‌سازی ورودی‌ها
     * - ارزیابی امن
     */
    public function execute( array $vars ): float {
        $expr = (string) $this->text;
        if ( $expr === '' ) {
            return 0.0;
        }

        // الگوی [CODE] را به شناسهٔ ساده تبدیل کن (با حفظ حروف/عدد/آندرلاین)
        $expr = preg_replace( '/\[(\w+)\]/', '$1', $expr );

        // فقط کاراکترهای مجاز: اعداد/حروف/آندرلاین و عملگرها و پرانتز و کاما (برای توابعی مثل min/max)
        if ( preg_match( '/[^0-9A-Za-z_\.\+\-\*\/\^\%\(\)\s,]/', $expr ) ) {
            return 0.0;
        }

        // مقادیر متغیرها را امن و عددی کن
        $safeVars = [];
        foreach ( $vars as $k => $v ) {
            if ( is_string( $k ) && preg_match( '/^\w+$/', $k ) ) {
                $safeVars[$k] = is_numeric( $v ) ? (float) $v : 0.0;
            }
        }

        $val = (float) self::engine()->evaluate( $expr, $safeVars );
        return is_finite( $val ) ? $val : 0.0;
    }
}