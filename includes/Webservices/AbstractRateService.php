<?php
namespace MNS\NavasanPlus\Webservices;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Base class for rate services
 * - Manages service settings from options
 * - Secure HTTP requests to API
 * - JSON response normalization
 *
 * Child services must:
 *  - Set $key, $name, $url, $settings
 *  - Implement retrieve() method and return array [ code => ['name'=>..., 'price'=>float], ... ]
 */
abstract class AbstractRateService {

    /** Service key (like tabangohar) */
    protected string $key = '';

    /** Display name of service */
    protected string $name = '';

    /** API root */
    protected string $url  = '';

    /** Is it free? Only for display purposes */
    protected bool $free   = false;

    /** Base currency unit (for display) */
    protected string $currency = '';

    /**
     * Service settings field definitions
     * Example:
     * [
     *   'username' => ['type'=>'text','default'=>''],
     *   'password' => ['type'=>'text','default'=>''],
     * ]
     */
    protected array $settings = [];

    /** Display badges/labels for service (to prevent Dynamic Properties) */
    protected array $badges = [];

    /** Default timeout for requests (seconds) */
    protected int $timeout = 20;

    public function __construct() {}

    /** Must be implemented by child; Output: array|WP_Error */
    abstract public function retrieve();

    /** Read service option from mns_navasan_plus_options */
    protected function get_option( string $field, $default = '' ) {
        $opts = get_option( 'mns_navasan_plus_options', [] );

        // New structure: services[{$this->key}][field]
        if ( isset( $opts['services'][ $this->key ][ $field ] ) && $opts['services'][ $this->key ][ $field ] !== '' ) {
            return $opts['services'][ $this->key ][ $field ];
        }

        // Backward compatibility (for tabangohar_username/password)
        $legacy_key = $this->key . '_' . $field; // like tabangohar_username
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
     * GET request to a URL
     * @param string $url
     * @param array  $args Additional wp_remote_get arguments (e.g. custom headers)
     * @return array|\WP_Error json-decoded array or WP_Error
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

    /** Access to service specifications (optional) */
    public function get_key(): string      { return $this->key; }
    public function get_name(): string     { return $this->name; }
    public function get_url(): string      { return $this->url; }
    public function is_free(): bool        { return $this->free; }
    public function get_currency(): string { return $this->currency; }
    public function get_settings(): array  { return $this->settings; }
    public function get_badges(): array    { return $this->badges; }
}