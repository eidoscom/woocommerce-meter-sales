<?php
defined( 'ABSPATH' ) || exit;

class WCMS_Cart {

    public function __construct() {
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'before_calculate_totals' ), 20, 1 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
    }

    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        $enabled = get_post_meta( $product_id, '_wcms_enabled', true );
        if ( $enabled !== 'yes' ) {
            return $cart_item_data;
        }

        if ( isset( $_POST['wcms_images_data'] ) ) {
            $raw_data = json_decode( wp_unslash( $_POST['wcms_images_data'] ), true );
            if ( ! is_array( $raw_data ) || empty( $raw_data ) ) {
                return $cart_item_data;
            }

            // Check if optimized format
            $is_optimized = ! empty( $raw_data['optimized'] );

            if ( $is_optimized ) {
                $images = array();
                if ( isset( $raw_data['images'] ) && is_array( $raw_data['images'] ) ) {
                    foreach ( $raw_data['images'] as $img ) {
                        $images[] = array(
                            'img_w'  => floatval( $img['img_w'] ?? 0 ),
                            'img_h'  => floatval( $img['img_h'] ?? 0 ),
                            'copies' => intval( $img['copies'] ?? 1 ),
                            'title'  => isset( $img['title'] ) ? sanitize_text_field( $img['title'] ) : ( 'Image ' . ( count( $images ) + 1 ) ),
                        );
                    }
                }

                $length_m  = floatval( $raw_data['total_length_m'] ?? 0 );
                $length_cm = floatval( $raw_data['length_cm'] ?? ( $length_m * 100 ) );

                $total_copies = 0;
                foreach ( $images as $img ) {
                    $total_copies += $img['copies'];
                }

                $cart_item_data['_wcms_data'] = array(
                    'images'       => $images,
                    'nesting'      => array(),
                    'length_cm'    => $length_cm,
                    'length_m'     => $length_m,
                    'total_copies' => $total_copies,
                    'total_images' => count( $images ),
                    'is_optimized' => true,
                );
            } else {
                $film_width = get_post_meta( $product_id, '_wcms_film_width', true ) ?: 57;
                $gap        = get_post_meta( $product_id, '_wcms_gap', true ) ?: 0.5;
                $waste      = get_post_meta( $product_id, '_wcms_waste', true ) ?: 5;

                $images = array();
                foreach ( $raw_data as $img ) {
                    $images[] = array(
                        'img_w'  => floatval( $img['print_w'] ?? $img['img_w'] ?? 0 ),
                        'img_h'  => floatval( $img['print_h'] ?? $img['img_h'] ?? 0 ),
                        'copies' => intval( $img['copies'] ?? 1 ),
                        'title'  => isset( $img['title'] ) ? sanitize_text_field( $img['title'] ) : ( 'Image ' . ( count( $images ) + 1 ) ),
                    );
                }

                $nesting = new WCMS_Nesting();
                $result  = $nesting->calculate_multi( $film_width, $images, $gap, $waste );

                $total_copies = 0;
                foreach ( $images as $img ) {
                    $total_copies += $img['copies'];
                }

                $cart_item_data['_wcms_data'] = array(
                    'images'       => $images,
                    'nesting'      => $result['images'],
                    'length_cm'    => $result['total_length_cm'],
                    'length_m'     => $result['total_length_m'],
                    'total_copies' => $total_copies,
                    'total_images' => count( $images ),
                    'is_optimized' => false,
                );
            }

            $cart_item_data['unique_key'] = md5( microtime() . rand() );
        }

        return $cart_item_data;
    }

    public function before_calculate_totals( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( ! isset( $cart_item['_wcms_data'] ) ) {
                continue;
            }

            $data       = $cart_item['_wcms_data'];
            $product_id = $cart_item['product_id'];

            $pricing = new WCMS_Pricing();
            $price   = $pricing->calculate_price( $product_id, $data['length_m'] );

            $cart_item['data']->set_price( $price );
        }
    }

    public function display_cart_item_data( $item_data, $cart_item ) {
        if ( ! isset( $cart_item['_wcms_data'] ) ) {
            return $item_data;
        }

        $d = $cart_item['_wcms_data'];

        $images_detail = array();
        foreach ( $d['images'] as $i => $img ) {
            $title = ! empty( $img['title'] ) ? $img['title'] : ( 'Image ' . ( $i + 1 ) );
            $images_detail[] = sprintf(
                '%s: %s x %s cm x %d',
                $title,
                number_format( $img['img_w'], 1 ),
                number_format( $img['img_h'], 1 ),
                $img['copies']
            );
        }

        $item_data[] = array(
            'name'  => __( 'Images', 'woocommerce-meter-sales' ),
            'value' => implode( ' | ', $images_detail ),
        );
        $item_data[] = array(
            'name'  => __( 'Total copies', 'woocommerce-meter-sales' ),
            'value' => $d['total_copies'],
        );
        $item_data[] = array(
            'name'  => __( 'Film consumption', 'woocommerce-meter-sales' ),
            'value' => number_format( $d['length_m'], 2 ) . ' m',
        );

        return $item_data;
    }

    public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! isset( $values['_wcms_data'] ) ) {
            return;
        }

        $d = $values['_wcms_data'];
        $item->add_meta_data( '_wcms_images_data', wp_json_encode( $d['images'] ) );
        $item->add_meta_data( '_wcms_nesting_data', wp_json_encode( $d['nesting'] ) );
        $item->add_meta_data( '_wcms_total_length', $d['length_m'] );
        $item->add_meta_data( '_wcms_total_copies', $d['total_copies'] );
    }
}
