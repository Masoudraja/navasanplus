<?php
namespace MNS\NavasanPlus\Webservices;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * کلاس پایهٔ سرویس‌های نرخ
 * - مدیریت تنظیمات سرویس از options
 * - درخواست HTTP امن به API
 * - نرمال‌سازی پاسخ JSON
 *
 * سرویس‌های فرزند باید:
 *  - $key, $name, $url, $settings را تنظیم کنند
 *  - متد retrieve() را پیاده‌سازی کنند و آرایهٔ [ code => ['name'=>..., 'price'=>float], ... ] برگردانند
 */
abstract class AbstractRateService {

    /** کلید سرویس (مثل tabangohar) */
    protected string $key = '';

    /** نام نمایشی سرویس */
    protected string $name = '';

    /** ریشهٔ API */
    protected string $url  = '';

    /** آیا رایگان است؟ صرفاً جهت نمایش */
    protected bool $free   = false;

    /** واحد پول پایه (نمایشی) */
    protected string $currency = '';

    /**
     * تعریف فیلدهای تنظیمات سرویس
     * نمونه:
     * [
     *   'username' => ['type'=>'text','default'=>''],
     *   'password' => ['type'=>'text','default'=>''],
     * ]
     */
    protected array $settings = [];

    /** نشان‌ها/برچسب‌های نمایشی سرویس (برای جلوگیری از Dynamic Properties) */
    protected array $badges = [];

    /** timeout پیش‌فرض درخواست‌ها (ثانیه) */
    protected int $timeout = 20;

    public function __construct() {}

    /** باید توسط فرزند پیاده‌سازی شود؛ خروجی: array|WP_Error */
    abstract public function retrieve();

    /** خواندن گزینهٔ سرویس از mns_navasan_plus_options */
    protected function get_option( string $field, $default = '' ) {
        $opts = get_option( 'mns_navasan_plus_options', [] );

        // ساختار جدید: services[{$this->key}][field]
        if ( isset( $opts['services'][ $this->key ][ $field ] ) && $opts['services'][ $this->key ][ $field ] !== '' ) {
            return $opts['services'][ $this->key ][ $field ];
        }

        // سازگاری عقب‌رو (برای tabangohar_username/password)
        $legacy_key = $this->key . '_' . $field; // مثل tabangohar_username
        if ( isset( $opts[ $legacy_key ] ) && $opts[ $legacy_key ] !== '' ) {
            return $opts[ $legacy_key ];
        }

        // default
        if ( isset( $this->settings[ $field ]['default'] ) ) {
            return $this->settings[ $field ]['default'];
        }
        return $default;
    }

    /**
     * درخواست GET به یک URL
     * @param string $url
     * @param array  $args آرگومان‌های اضافه wp_remote_get (مثلاً headers سفارشی)
     * @return array|\WP_Error json-decoded array یا WP_Error
     */
    protected function request( string $url, array $args = [] ) {
        $url  = esc_url_raw( $url );
        $args = wp_parse_args( $args, [
            'timeout' => $this->timeout,
            'headers' => [
                'Accept'     => 'application/json',
                'User-Agent' => 'MNS-Navasan-Plus/1.0; ' . home_url(),
            ],
        ] );

        $res = wp_remote_get( $url, $args );

        if ( is_wp_error( $res ) ) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code( $res );
        $body = wp_remote_retrieve_body( $res );

        if ( $code < 200 || $code >= 300 ) {
            return new \WP_Error( $this->key, sprintf( 'HTTP %d', (int) $code ) );
        }

        if ( $body === '' || $body === null ) {
            return new \WP_Error( $this->key, 'Empty response body' );
        }

        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( $this->key, 'Invalid JSON: ' . json_last_error_msg() );
        }

        return $data;
    }

    /** دسترسی به مشخصات سرویس (اختیاری) */
    public function get_key(): string      { return $this->key; }
    public function get_name(): string     { return $this->name; }
    public function get_url(): string      { return $this->url; }
    public function is_free(): bool        { return $this->free; }
    public function get_currency(): string { return $this->currency; }
    public function get_settings(): array  { return $this->settings; }
    public function get_badges(): array    { return $this->badges; }
}