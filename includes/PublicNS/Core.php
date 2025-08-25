<?php
namespace MNS\NavasanPlus\PublicNS;

use MNS\NavasanPlus\DB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Core {

    /** @var Core|null */
    private static ?self $_instance = null;

    /**
     * Singleton
     */
    public static function instance(): self {
        if ( null === self::$_instance ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * ثبت هوک‌ها
     */
    private function __construct() {
        // این هوک روی کوئری محصولات ووکامرس صدا می‌خورد
        add_action( 'woocommerce_product_query', [ $this, 'products_query' ], 9999 );
    }

    /** (اختیاری) Loader::boot_public() این را صدا می‌زند */
    public function run(): void {
        // هوک‌ها در __construct ثبت شده‌اند
    }

    /**
     * فیلتر کردن کوئری محصولات بر اساس ارز/فرمول از طریق پارامترهای GET
     *
     * @param \WP_Query $q
     */
    public function products_query( $q ): void {
        // در ادمین کاری نکن
        if ( is_admin() ) {
            return;
        }

        // اگر شیء get/set ندارد، خارج شو (محافظه‌کارانه)
        if ( ! is_object( $q ) || ! method_exists( $q, 'get' ) || ! method_exists( $q, 'set' ) ) {
            return;
        }

        // کلیدهای متا با پیشوند صحیح
        // توجه: اگر اسم متاهای واقعی‌تان فرق می‌کند، همین‌جا تغییر دهید.
        $currency_key = DB::instance()->full_meta_key( 'currency_id' ); // -> _mns_navasan_plus_currency_id
        $formula_key  = DB::instance()->full_meta_key( 'formula_id' );  // -> _mns_navasan_plus_formula_id

        // meta_query فعلی کوئری محصولات
        $meta_query = (array) $q->get( 'meta_query' );

        $added = false;

        // فیلتر بر اساس شناسه ارز مرتبط با محصول
        if ( isset( $_GET['base_currency'] ) ) {
            $cid = absint( wp_unslash( $_GET['base_currency'] ) );
            if ( $cid > 0 ) {
                $meta_query[] = [
                    'key'     => $currency_key,
                    'value'   => $cid,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ];
                $added = true;
            }
        }

        // فیلتر بر اساس شناسه فرمول مرتبط با محصول
        if ( isset( $_GET['base_formula'] ) ) {
            $fid = absint( wp_unslash( $_GET['base_formula'] ) );
            if ( $fid > 0 ) {
                $meta_query[] = [
                    'key'     => $formula_key,
                    'value'   => $fid,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ];
                $added = true;
            }
        }

        if ( $added ) {
            // اطمینان از relation منطقی
            if ( empty( $meta_query['relation'] ) ) {
                $meta_query['relation'] = 'AND';
            }
            $q->set( 'meta_query', $meta_query );
        }
    }
}