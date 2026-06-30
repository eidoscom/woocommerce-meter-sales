<?php
defined( 'ABSPATH' ) || exit;

class WCMS_Cart {

    public function __construct() {
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'before_calculate_totals' ), 20, 1 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'move_uploaded_files_to_order' ) );
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

                $film_width = get_post_meta( $product_id, '_wcms_film_width', true ) ?: 57;
                $gap        = get_post_meta( $product_id, '_wcms_gap', true ) ?: 0.5;
                $waste      = get_post_meta( $product_id, '_wcms_waste', true ) ?: 5;

                $nesting = new WCMS_Nesting();
                $result  = $nesting->calculate_multi( $film_width, $images, $gap, $waste );

                $cart_item_data['_wcms_data'] = array(
                    'images'       => $images,
                    'nesting'      => $result['images'],
                    'length_cm'    => $length_cm,
                    'length_m'     => $length_m,
                    'total_copies' => $total_copies,
                    'total_images' => count( $images ),
                    'is_optimized' => true,
                    'raw_data'     => $raw_data,
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
                    'raw_data'     => $raw_data,
                );
            }

            $cart_item_data['unique_key'] = md5( microtime() . rand() );

            if ( ! empty( $_FILES['wcms_files'] ) && is_array( $_FILES['wcms_files']['name'] ) ) {
                $upload_dir = wp_upload_dir();
                $base_dir   = $upload_dir['basedir'] . '/wcms-impositions/cart-' . $cart_item_data['unique_key'];
                wp_mkdir_p( $base_dir );

                $file_paths = array();
                foreach ( $_FILES['wcms_files']['name'] as $fi => $fname ) {
                    if ( empty( $_FILES['wcms_files']['tmp_name'][ $fi ] ) ) {
                        continue;
                    }
                    $tmp_path = $_FILES['wcms_files']['tmp_name'][ $fi ];
                    $ext      = strtolower( pathinfo( $fname, PATHINFO_EXTENSION ) );
                    if ( ! in_array( $ext, array( 'png', 'jpg', 'jpeg', 'pdf' ), true ) ) {
                        continue;
                    }
                    $dest = $base_dir . '/' . sanitize_file_name( $fname );
                    if ( move_uploaded_file( $tmp_path, $dest ) ) {
                        $file_paths[] = $dest;
                    }
                }
                if ( ! empty( $file_paths ) ) {
                    $cart_item_data['_wcms_data']['file_paths'] = $file_paths;
                }
            }
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
        if ( ! empty( $d['file_paths'] ) && is_array( $d['file_paths'] ) ) {
            $item->add_meta_data( '_wcms_upload_paths', $d['file_paths'] );
        }
        if ( ! empty( $d['raw_data'] ) ) {
            $item->add_meta_data( '_wcms_raw_data', wp_json_encode( $d['raw_data'] ) );
        }
    }

    public function move_uploaded_files_to_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $all_cart_paths = array();
        foreach ( $order->get_items() as $item ) {
            $cart_paths = $item->get_meta( '_wcms_upload_paths' );
            if ( $cart_paths && is_array( $cart_paths ) ) {
                $all_cart_paths = array_merge( $all_cart_paths, $cart_paths );
            }
        }

        if ( empty( $all_cart_paths ) ) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $order_dir  = $upload_dir['basedir'] . '/wcms-impositions/order-' . $order_id;
        wp_mkdir_p( $order_dir );

        $new_paths = array();
        foreach ( $all_cart_paths as $src ) {
            if ( ! file_exists( $src ) ) {
                continue;
            }
            $filename = basename( $src );
            $dest     = $order_dir . '/' . $filename;
            if ( rename( $src, $dest ) ) {
                $new_paths[] = $dest;
            }
        }

        if ( ! empty( $new_paths ) ) {
            foreach ( $order->get_items() as $item ) {
                $item->update_meta_data( '_wcms_upload_paths', $new_paths );
                $item->save_meta_data();
            }
        }
    }
}
