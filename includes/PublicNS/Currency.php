<?php
/**
 * PublicNS\Currency
 *
 * رَپِر یک «پُست» (CPT) Currency برای دسترسی یکنواخت به Rate جاری، نماد، تاریخ به‌روزرسانی
 * و محاسبات ساده‌ی Statisticsی (مثل میانگین Previous برای درصد تغییر).
 *
 * File: includes/PublicNS/Currency.php
 */

namespace MNS\NavasanPlus\PublicNS;

use MNS\NavasanPlus\DB;
use MNS\NavasanPlus\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Currency {

    /** @var \WP_Post */
    protected $post;

    // --- light caches to reduce meta calls ---
    private ?string $code_cache        = null;
    private ?string $symbol_cache      = null;
    private ?float  $rate_cache        = null;
    private ?array  $history_cache     = null;  // [timestamp => rate]
    private ?int    $update_time_cache = null;
    private array   $prev_mean_cache   = [];    // keyed by span

    /**
     * @param \WP_Post $post پستِ مربوط به Currency (post_type: mnswmc)
     */
    public function __construct( \WP_Post $post ) {
        $this->post = $post;
    }

    /** خودِ پست (برای سازگاری با قالب‌هایی که get_post() را صدا می‌زنند) */
    public function get_post(): \WP_Post {
        return $this->post;
    }

    /** ID پست */
    public function get_id(): int {
        return (int) $this->post->ID;
    }

    /** Name Currency (عنوان پست) */
    public function get_name(): string {
        return (string) get_the_title( $this->post );
    }

    /** کُد Currency (اختیاری) از متا */
    public function get_code(): string {
        if ( $this->code_cache !== null ) return $this->code_cache;
        return $this->code_cache = (string) DB::instance()->read_post_meta( $this->get_id(), 'currency_code', '' );
    }

    /** نماد/Unit Display (مثلاً "Toman" یا "$") از متا */
    public function get_symbol(): string {
        if ( $this->symbol_cache !== null ) return $this->symbol_cache;
        return $this->symbol_cache = (string) DB::instance()->read_post_meta( $this->get_id(), 'currency_symbol', '' );
    }

    /** Rate جاری از متا */
    public function get_rate(): float {
        if ( $this->rate_cache !== null ) return $this->rate_cache;
        return $this->rate_cache = (float) DB::instance()->read_post_meta( $this->get_id(), 'currency_value', 0 );
    }

    /**
     * رشته‌ی قابل Display Rate (با فرمت و نماد)
     *
     * @param float|null $rate     اگر null باشد از Rate جاری استفاده می‌شود
     * @param int|null   $decimals تعداد اعشار برای فرمت
     */
    public function display_rate( ?float $rate = null, ?int $decimals = 0 ): string {
        $value     = ($rate !== null) ? (float) $rate : $this->get_rate();
        $formatted = Helpers::format_decimal( $value, $decimals ?? 0 );
        $symbol    = $this->get_symbol();
        return $symbol !== '' ? ($formatted . ' ' . $symbol) : $formatted;
    }

    /**
     * زمان Lastین به‌روزرسانی Rate
     *
     * ترتیب Firstویت:
     *  1) متای `currency_update_time`
     *  2) بیشترین کلید زمانی در آرایه‌ی `currency_history`
     *  3) زمان Lastین Edit پست
     *
     * @return int Unix timestamp
     */
    public function get_update_time(): int {
        if ( $this->update_time_cache !== null ) {
            return $this->update_time_cache;
        }

        $pid       = $this->get_id();
        $meta_time = (int) DB::instance()->read_post_meta( $pid, 'currency_update_time', 0 );
        if ( $meta_time > 0 ) {
            return $this->update_time_cache = $meta_time;
        }

        $history = $this->get_history();
        if ( ! empty( $history ) ) {
            $last = max( array_keys( $history ) );
            if ( $last ) {
                return $this->update_time_cache = (int) $last;
            }
        }

        // fallback: زمان Lastین تغییر پست
        $modified = get_post_modified_time( 'U', true, $pid );
        return $this->update_time_cache = ( $modified ? (int) $modified : time() );
    }

    /**
     * میانگین «Previous» برای Calculation‌ی درصد تغییر
     * به‌صورت میانگین نقاط تاریخی بدون درنظر گرفتن Lastین مقدار.
     *
     * @param int $span حداکثر تعداد نقاطی که لحاظ می‌کنیم (بدون Lastین نقطه)
     */
    public function get_prev_mean( int $span = 10 ): float {
        $span    = max( 1, (int) $span );
        $cache_k = (string) $span;
        if ( isset( $this->prev_mean_cache[ $cache_k ] ) ) {
            return $this->prev_mean_cache[ $cache_k ];
        }

        $history = $this->get_history();
        if ( count( $history ) <= 1 ) {
            return $this->prev_mean_cache[ $cache_k ] = (float) $this->get_rate();
        }

        // Sort بر اساس زمان
        ksort( $history, SORT_NUMERIC );

        // Delete Lastین نقطه (جدیدترین)
        $keys = array_keys( $history );
        array_pop( $keys );

        // در نظر گرفتن فقط span نقطه‌ی Lastِ باقی‌مانده
        $keys = array_slice( $keys, -$span );

        $sum = 0.0; $cnt = 0;
        foreach ( $keys as $k ) {
            $sum += (float) $history[ $k ];
            $cnt++;
        }
        return $this->prev_mean_cache[ $cache_k ] = ( $cnt > 0 ? ($sum / $cnt) : (float) $this->get_rate() );
    }

    /**
     * درصد تغییر نسبت به میانگین Previous (مثبت/منفی)
     * @param int $span
     */
    public function get_change_percent( int $span = 10 ): float {
        $prev = $this->get_prev_mean( $span );
        $curr = $this->get_rate();
        if ( $prev <= 0 ) {
            return 0.0;
        }
        return ( ( $curr - $prev ) / $prev ) * 100.0;
    }

    /**
     * تاریخچه‌ی Rate‌ها (timestamp ⇒ rate)
     * @return array<int,float>
     */
    public function get_history(): array {
        if ( $this->history_cache !== null ) {
            return $this->history_cache;
        }
        $raw = DB::instance()->read_post_meta( $this->get_id(), 'currency_history', [] );
        return $this->history_cache = ( is_array( $raw ) ? $raw : [] );
    }

    /** (اختیاری) رشتهٔ زمان به‌روزرسانی با فرمت دلخواه */
    public function format_update_time( string $format = 'Y-m-d H:i' ): string {
        return date_i18n( $format, $this->get_update_time() );
    }

    /** Output ساده برای JSON/UI */
    public function to_array(): array {
        return [
            'id'           => $this->get_id(),
            'name'         => $this->get_name(),
            'code'         => $this->get_code(),
            'symbol'       => $this->get_symbol(),
            'rate'         => $this->get_rate(),
            'display_rate' => $this->display_rate(),
            'updated_at'   => $this->get_update_time(),
            'change_pct'   => $this->get_change_percent(),
        ];
    }
}