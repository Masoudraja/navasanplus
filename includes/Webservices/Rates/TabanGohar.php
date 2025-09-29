<?php
namespace MNS\NavasanPlus\Webservices\Rates;

use MNS\NavasanPlus\Webservices\AbstractRateService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Taban Gohar Rate Service
 * Standard output:
 * [
 *   '<api-key>' => ['name' => string, 'price' => float],
 *   ...
 * ]
 */
class TabanGohar extends AbstractRateService {

    /** Prevent Dynamic Properties in PHP 8.2+ */
    protected array $badges = [];

    public function __construct() {
        parent::__construct();

        $this->key      = 'tabangohar';
        $this->name     = 'Taban Gohar';
        // For display/information purposes; the actual endpoint is built in retrieve method
        $this->url      = 'https://webservice.tgnsrv.ir/';
        $this->free     = false;
        $this->currency = 'IRT';

        $this->badges = [
            [
                'title' => __( 'Recommended for gold and jewelry', 'mns-navasan-plus' ),
                'icon'  => 'ring_gold',
                'color' => 'rgb(82,174,71)',
            ],
        ];

        // Better UX: password as password field
        $this->settings = [
            'username' => [
                'type'        => 'text',
                'placeholder' => 'Username',
                'default'     => '',
            ],
            'password' => [
                'type'        => 'password',
                'placeholder' => 'Password',
                'default'     => '',
            ],
        ];
    }

    /**
     * Retrieve rates from TabanGohar web service and map to internal structure
     *
     * @return array|\WP_Error
     */
    public function retrieve() {
        $username = trim( (string) $this->get_option( 'username', '' ) );
        $password = trim( (string) $this->get_option( 'password', '' ) );

        if ( $username === '' || $password === '' ) {
            return new \WP_Error( $this->get_key(), __( 'Missing TabanGohar credentials.', 'mns-navasan-plus' ) );
        }

        // Can be overridden with Filter (test/proxy environments, etc.)
        $endpoint = sprintf(
            'https://webservice.tgnsrv.ir/Pr/Get/%s/%s',
            rawurlencode( $username ),
            rawurlencode( $password )
        );
        $endpoint = apply_filters( 'mnsnp/tabangohar/endpoint', $endpoint, $username, $password );

        // HTTP Request; we expect AbstractRateService::request to return PHP array
        $response = $this->request( $endpoint );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Server-side error
        if ( isset( $response['Error'] ) && $response['Error'] !== '' ) {
            return new \WP_Error( $this->get_key(), sanitize_text_field( (string) $response['Error'] ) );
        }

        if ( ! is_array( $response ) || empty( $response ) ) {
            return new \WP_Error( $this->get_key(), __( 'Webservice returned empty or invalid payload.', 'mns-navasan-plus' ) );
        }

        // Map items (title/ratio)
        $map  = apply_filters( 'mnsnp/tabangohar/items', $this->items() );
        $data = [];

        foreach ( $response as $key => $value ) {
            if ( $key === 'TimeRead' ) { // Ignore time field
                continue;
            }

            $raw_key = (string) $key;
            $num     = is_numeric( $value ) ? (float) $value : 0.0;

            if ( isset( $map[ $raw_key ] ) ) {
                $title = $map[ $raw_key ]['title'] ?: $raw_key;
                $ratio = (float) $map[ $raw_key ]['ratio'];
            } else {
                $title = $raw_key;
                $ratio = 1.0;
            }

            $data[ $raw_key ] = [
                'name'  => sanitize_text_field( (string) $title ),
                'price' => (float) ( $ratio * $num ),
            ];
        }

        return $data;
    }

    /**
     * Map API items → title and calculation ratio
     *
     * @return array<string, array{title:string, ratio:float}>
     */
    protected function items(): array {
        return [
            'SekehRob'             => [ 'title' => 'ربع سکه بهار آزادی',     'ratio' => 1000 ],
            'SekehNim'             => [ 'title' => 'نیم سکه بهار آزادی',     'ratio' => 1000 ],
            'SekehTamam'           => [ 'title' => 'تمام سکه بهار آزادی',    'ratio' => 1000 ],
            'SekehEmam'            => [ 'title' => 'تمام سکه امامی',         'ratio' => 1000 ],
            'SekehGerami'          => [ 'title' => 'سکه گرمی',               'ratio' => 1000 ],
            'YekGram18'            => [ 'title' => 'یک گرم طلای 18 عیار',    'ratio' => 1 ],
            'YekGram20'            => [ 'title' => 'یک گرم طلای 20 عیار',    'ratio' => 1 ],
            'YekGram21'            => [ 'title' => 'یک گرم طلای 21 عیار',    'ratio' => 1 ],
            'YekMesghal18'         => [ 'title' => 'یک مثقال طلای 18 عیار',  'ratio' => 1 ],
            'YekMesghal17'         => [ 'title' => 'یک مثقال طلای 17 عیار',  'ratio' => 1 ],
            'KharidMotefaregheh18' => [ 'title' => 'Purchase متفرقه 18',         'ratio' => 1 ],
            'TavizMotefaregheh18'  => [ 'title' => 'تعویض متفرقه 18',        'ratio' => 1 ],
            'OunceTala'            => [ 'title' => 'انس طلا',                'ratio' => 1 ],
            'OunceNoghreh'         => [ 'title' => 'انس نقره',               'ratio' => 0.001 ],
            'Dollar'               => [ 'title' => 'Dollar',                   'ratio' => 1 ],
            'Euro'                 => [ 'title' => 'Euro',                   'ratio' => 1 ],
            'Derham'               => [ 'title' => 'Dirham',                   'ratio' => 1 ],
            'Pelatin'              => [ 'title' => 'پلاتین',                 'ratio' => 1 ],
        ];
    }
}