<?php
namespace MNS\NavasanPlus\Templates\Classes;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Field renderer for admin metaboxes / WC product panels.
 *
 * امضاها (با سازگاری کامل با Codeهای فعلی):
 *  - text( $id, $name, $value, $label, [ $args ] )
 *  - number( $id, $name, $value, $label, [ $args ] )
 *  - checkbox( $id, $name, $checked_bool, $label, $desc = '', [ $args ] )
 *  - select( $id, $name, array $options, $selected, $label, [ $args ], $desc = '' )
 *  - textarea( $id, $name, $value, $label, [ $args ], $desc = '' )
 *
 * $args مشترک:
 *  - wrapper_class: string   (مثلاً form-row، form-row-first/last/full)
 *  - class:         string   کلاس اضافه برای input/select/textarea
 *  - desc_tip:      bool     Display tip به‌جای description
 *  - placeholder:   string
 *  - step/min/max:  برای number
 *  - rows/cols:     برای textarea (rows پیش‌فرض 4)
 *  - data:          array    داده‌ها برای data-*
 *  - required/readonly/disabled: bool
 */
final class Fields {

    /* -------------------- Text -------------------- */
    public static function text( string $id, string $name, $value = '', string $label = '', $args = [] ): void {
        $a = self::normalize_args( $args );
        [ $open, $close ] = self::wrap_tags( $a['wrapper_class'] );

        echo $open;
        printf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( $label ) );
        printf(
            '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="short %4$s" %5$s %6$s %7$s %8$s %9$s />',
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( (string) $value ),
            esc_attr( $a['class'] ),
            self::ph( $a['placeholder'] ),
            self::data_attrs( $a['data'] ),
            $a['required'] ? 'required' : '',
            $a['readonly'] ? 'readonly' : '',
            $a['disabled'] ? 'disabled' : ''
        );
        self::maybe_tip_or_desc( $a['desc'], $a['desc_tip'] );
        echo $close;
    }

    /* ------------------- Number ------------------- */
    public static function number( string $id, string $name, $value = 0, string $label = '', $args = [] ): void {
        $a = self::normalize_args( $args );
        [ $open, $close ] = self::wrap_tags( $a['wrapper_class'] );

        $step = $a['step'] ?? '0.01';
        echo $open;
        printf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( $label ) );
        printf(
            '<input type="number" id="%1$s" name="%2$s" value="%3$s" class="short %4$s" step="%5$s" %6$s %7$s %8$s %9$s %10$s %11$s />',
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( (string) $value ),
            esc_attr( $a['class'] ),
            esc_attr( $step ),
            isset( $a['min'] ) ? 'min="' . esc_attr( (string) $a['min'] ) . '"' : '',
            isset( $a['max'] ) ? 'max="' . esc_attr( (string) $a['max'] ) . '"' : '',
            self::ph( $a['placeholder'] ),
            self::data_attrs( $a['data'] ),
            $a['required'] ? 'required' : '',
            $a['disabled'] ? 'disabled' : ''
        );
        self::maybe_tip_or_desc( $a['desc'], $a['desc_tip'] );
        echo $close;
    }

    /* ------------------ Checkbox ------------------ */
    public static function checkbox( string $id, string $name, $checked, string $label, string $desc = '', $args = [] ): void {
        $a = self::normalize_args( $args );
        [ $open, $close ] = self::wrap_tags( $a['wrapper_class'] );

        echo $open;
        echo '<label class="mnsnp-checkbox">';
        printf(
            '<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s %4$s %5$s %6$s />',
            esc_attr( $id ),
            esc_attr( $name ),
            checked( (bool) $checked, true, false ),
            self::data_attrs( $a['data'] ),
            $a['readonly'] ? 'readonly' : '',
            $a['disabled'] ? 'disabled' : ''
        );
        echo ' ' . esc_html( $label ) . '</label>';

        self::maybe_tip_or_desc( $desc !== '' ? $desc : $a['desc'], $a['desc_tip'] );
        echo $close;
    }

    /* -------------------- Select ------------------ */
    public static function select(
        string $id,
        string $name,
        array $options,
        $selected,
        string $label,
        $args = [],
        string $desc = ''
    ): void {
        $a = self::normalize_args( $args );
        [ $open, $close ] = self::wrap_tags( $a['wrapper_class'] );

        echo $open;
        printf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( $label ) );
        printf(
            '<select id="%1$s" name="%2$s" class="select short %3$s" %4$s %5$s %6$s>',
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( $a['class'] ),
            self::data_attrs( $a['data'] ),
            $a['required'] ? 'required' : '',
            $a['disabled'] ? 'disabled' : ''
        );
        foreach ( $options as $val => $text ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( (string) $val ),
                selected( (string) $selected, (string) $val, false ),
                esc_html( (string) $text )
            );
        }
        echo '</select>';
        self::maybe_tip_or_desc( $desc !== '' ? $desc : $a['desc'], $a['desc_tip'] );
        echo $close;
    }

    /* ------------------- Textarea ----------------- */
    public static function textarea(
        string $id,
        string $name,
        $value = '',
        string $label = '',
        $args = [],
        string $desc = ''
    ): void {
        $a = self::normalize_args( $args );
        [ $open, $close ] = self::wrap_tags( $a['wrapper_class'] );

        $rows = max( 2, (int) ( $a['rows'] ?? 4 ) );
        $cols_attr = isset( $a['cols'] ) ? ' cols="' . esc_attr( (string) $a['cols'] ) . '"' : '';

        echo $open;
        if ( $label !== '' ) {
            printf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( $label ) );
        }

        printf(
            '<textarea id="%1$s" name="%2$s" rows="%3$d"%4$s class="short %5$s" %6$s %7$s %8$s %9$s>',
            esc_attr( $id ),
            esc_attr( $name ),
            (int) $rows,
            $cols_attr,
            esc_attr( $a['class'] ),
            self::ph( $a['placeholder'] ),
            self::data_attrs( $a['data'] ),
            $a['required'] ? 'required' : '',
            $a['disabled'] ? 'disabled' : ''
        );
        echo esc_textarea( (string) $value );
        echo '</textarea>';

        self::maybe_tip_or_desc( $desc !== '' ? $desc : $a['desc'], $a['desc_tip'] );
        echo $close;
    }

    /* ------------------ Internals ----------------- */

    private static function normalize_args( $args ): array {
        if ( is_string( $args ) ) { // اجازهٔ پاس‌دادن desc به‌صورت رشته
            $args = [ 'desc' => $args ];
        }
        $defaults = [
            'wrapper_class' => '',
            'class'         => '',
            'placeholder'   => '',
            'desc'          => '',
            'desc_tip'      => false,
            'data'          => [],
            'required'      => false,
            'readonly'      => false,
            'disabled'      => false,
            'step'          => null,
            'min'           => null,
            'max'           => null,
            'rows'          => null,
            'cols'          => null,
        ];
        $args = wp_parse_args( (array) $args, $defaults );
        if ( ! is_array( $args['data'] ) ) $args['data'] = [];
        return $args;
    }

    private static function wrap_tags( string $wrapper_class ): array {
        $wrapper_class = trim( $wrapper_class );
        if ( $wrapper_class === '' ) {
            return [ '<p class="form-field">', '</p>' ];
        }
        return [ '<div class="' . esc_attr( $wrapper_class ) . '">', '</div>' ];
    }

    private static function ph( string $ph ): string {
        return $ph !== '' ? 'placeholder="' . esc_attr( $ph ) . '"' : '';
    }

    private static function data_attrs( array $data ): string {
        if ( empty( $data ) ) return '';
        $out = [];
        foreach ( $data as $k => $v ) {
            $out[] = 'data-' . esc_attr( sanitize_key( $k ) ) . '="' . esc_attr( (string) $v ) . '"';
        }
        return implode( ' ', $out );
    }

    private static function maybe_tip_or_desc( string $desc, bool $tip ): void {
        if ( $desc === '' ) return;
        if ( $tip ) {
            printf(
                ' <span class="woocommerce-help-tip" data-tip="%s"></span>',
                esc_attr( $desc )
            );
        } else {
            printf( '<span class="description">%s</span>', esc_html( $desc ) );
        }
    }
}