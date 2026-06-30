<?php
defined( 'ABSPATH' ) || exit;

class WCMS_Admin {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_fields' ) );
        add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'render_order_imposition_download' ), 10, 1 );
        add_action( 'wp_ajax_wcms_download_imposition', array( $this, 'ajax_download_imposition' ) );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
            global $post;
            if ( ! $post ) {
                return;
            }
            $pt = get_post_type( $post->ID );
            if ( $pt !== 'product' && $pt !== 'shop_order' && $pt !== 'shop_order_placehold' ) {
                return;
            }
        } else if ( ! str_starts_with( $hook, 'woocommerce_page_wc-orders' ) ) {
            return;
        }
        wp_enqueue_style( 'wcms-admin', WCMS_PLUGIN_URL . 'assets/css/admin.css', array(), WCMS_VERSION );
        wp_enqueue_script( 'wcms-admin', WCMS_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), WCMS_VERSION, true );
    }

    public function add_product_data_tab( $tabs ) {
        $tabs['wcms_meter_sales'] = array(
            'label'    => __( 'Meter Sales', 'woocommerce-meter-sales' ),
            'target'   => 'wcms_meter_sales_data',
            'class'    => array( 'show_if_simple' ),
            'priority' => 30,
        );
        return $tabs;
    }

    public function render_product_data_panel() {
        global $post;
        $product_id = $post->ID;
        $enabled    = get_post_meta( $product_id, '_wcms_enabled', true );
        ?>
        <div id="wcms_meter_sales_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                woocommerce_wp_checkbox( array(
                    'id'            => '_wcms_enabled',
                    'label'         => __( 'Sell by meter', 'woocommerce-meter-sales' ),
                    'description'   => __( 'Enable linear meter pricing with DTF nesting calculator for this product.', 'woocommerce-meter-sales' ),
                ) );
                ?>
            </div>
            <div id="wcms_fields" class="options_group" style="<?php echo $enabled === 'yes' ? '' : 'display:none;'; ?>">
                <?php
                woocommerce_wp_text_input( array(
                    'id'            => '_wcms_film_width',
                    'label'         => __( 'Film width (cm)', 'woocommerce-meter-sales' ),
                    'type'          => 'number',
                    'custom_attributes' => array( 'step' => '0.1', 'min' => '1' ),
                    'placeholder'   => '57',
                ) );

                woocommerce_wp_text_input( array(
                    'id'            => '_wcms_fixed_price',
                    'label'         => __( 'Fixed base price', 'woocommerce-meter-sales' ),
                    'description'   => __( 'Added once per order regardless of length.', 'woocommerce-meter-sales' ),
                    'desc_tip'      => true,
                    'type'          => 'number',
                    'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
                ) );

                woocommerce_wp_text_input( array(
                    'id'            => '_wcms_gap',
                    'label'         => __( 'Gap between copies (cm)', 'woocommerce-meter-sales' ),
                    'type'          => 'number',
                    'custom_attributes' => array( 'step' => '0.1', 'min' => '0' ),
                    'placeholder'   => '0.5',
                ) );

                woocommerce_wp_text_input( array(
                    'id'            => '_wcms_waste',
                    'label'         => __( 'Waste percentage (%)', 'woocommerce-meter-sales' ),
                    'description'   => __( 'Extra material added to compensate for borders and waste.', 'woocommerce-meter-sales' ),
                    'desc_tip'      => true,
                    'type'          => 'number',
                    'custom_attributes' => array( 'step' => '0.5', 'min' => '0', 'max' => '50' ),
                    'placeholder'   => '5',
                ) );

                woocommerce_wp_text_input( array(
                    'id'                => '_wcms_min_charge_meters',
                    'label'             => __( 'Minimum charge (meters)', 'woocommerce-meter-sales' ),
                    'description'       => __( 'Override the global minimum for this product. Leave empty for global default.', 'woocommerce-meter-sales' ),
                    'desc_tip'          => true,
                    'type'              => 'number',
                    'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
                    'placeholder'       => __( 'Global default', 'woocommerce-meter-sales' ),
                ) );

                $this->render_price_tiers( $product_id );
                ?>
            </div>
        </div>
        <?php
    }

    private function render_price_tiers( $product_id ) {
        $tiers = get_post_meta( $product_id, '_wcms_price_tiers', true );
        if ( ! is_array( $tiers ) ) {
            $tiers = array(
                array( 'from' => '0', 'to' => '5', 'price' => '' ),
                array( 'from' => '5.01', 'to' => '20', 'price' => '' ),
            );
        }
        ?>
        <div class="options_group">
            <h4 style="padding-left:12px;"><?php esc_html_e( 'Price Tiers (per meter)', 'woocommerce-meter-sales' ); ?></h4>
            <table class="widefat wcms_tiers_table" style="margin:0 12px;width:auto;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'From (m)', 'woocommerce-meter-sales' ); ?></th>
                        <th><?php esc_html_e( 'To (m)', 'woocommerce-meter-sales' ); ?></th>
                        <th><?php esc_html_e( 'Price per meter', 'woocommerce-meter-sales' ); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $tiers as $index => $tier ) : ?>
                        <tr>
                            <td><input type="number" step="0.01" min="0" name="wcms_tiers[<?php echo $index; ?>][from]" value="<?php echo esc_attr( $tier['from'] ); ?>" style="width:80px;" /></td>
                            <td><input type="number" step="0.01" min="0" name="wcms_tiers[<?php echo $index; ?>][to]" value="<?php echo esc_attr( $tier['to'] ); ?>" style="width:80px;" /></td>
                            <td><input type="number" step="0.01" min="0" name="wcms_tiers[<?php echo $index; ?>][price]" value="<?php echo esc_attr( $tier['price'] ); ?>" style="width:100px;" /></td>
                            <td><button type="button" class="button wcms_remove_tier">-</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="button wcms_add_tier" style="margin:6px 12px 12px;">
                <?php esc_html_e( 'Add tier', 'woocommerce-meter-sales' ); ?>
            </button>
        </div>
        <?php
    }

    public function save_fields( $post_id ) {
        $enabled = isset( $_POST['_wcms_enabled'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_wcms_enabled', $enabled );

        if ( $enabled !== 'yes' ) {
            return;
        }

        $fields = array(
            '_wcms_film_width'    => 'floatval',
            '_wcms_fixed_price'   => 'floatval',
            '_wcms_gap'           => 'floatval',
            '_wcms_waste'         => 'floatval',
        );
        foreach ( $fields as $key => $sanitize ) {
            $value = isset( $_POST[ $key ] ) ? $sanitize( $_POST[ $key ] ) : 0;
            update_post_meta( $post_id, $key, $value );
        }

        $min_charge = isset( $_POST['_wcms_min_charge_meters'] ) ? trim( $_POST['_wcms_min_charge_meters'] ) : '';
        if ( $min_charge !== '' ) {
            update_post_meta( $post_id, '_wcms_min_charge_meters', floatval( $min_charge ) );
        } else {
            delete_post_meta( $post_id, '_wcms_min_charge_meters' );
        }

        $tiers = array();
        if ( isset( $_POST['wcms_tiers'] ) && is_array( $_POST['wcms_tiers'] ) ) {
            foreach ( $_POST['wcms_tiers'] as $tier ) {
                $from  = isset( $tier['from'] ) ? floatval( $tier['from'] ) : 0;
                $to    = isset( $tier['to'] ) ? floatval( $tier['to'] ) : 0;
                $price = isset( $tier['price'] ) ? floatval( $tier['price'] ) : 0;
                if ( $price > 0 ) {
                    $tiers[] = array( 'from' => $from, 'to' => $to, 'price' => $price );
                }
            }
        }
        update_post_meta( $post_id, '_wcms_price_tiers', $tiers );

        $reg_price = get_post_meta( $post_id, '_regular_price', true );
        if ( empty( $reg_price ) || floatval( $reg_price ) === 0.0 ) {
            if ( ! empty( $tiers ) ) {
                $lowest = min( array_column( $tiers, 'price' ) );
                $fixed  = floatval( $_POST['_wcms_fixed_price'] ?? 0 );
                $product = wc_get_product( $post_id );
                if ( $product ) {
                    $display_price = $lowest + ( $fixed > 0 ? $fixed : 0 );
                    $product->set_regular_price( $display_price );
                    $product->save();
                }
            }
        }
    }

    public function render_order_imposition_download( $order ) {
        $items = $order->get_items();
        $has_wcms = false;
        foreach ( $items as $item ) {
            $images_data = $item->get_meta( '_wcms_images_data' );
            if ( $images_data ) {
                $has_wcms = true;
                break;
            }
        }
        if ( ! $has_wcms ) {
            return;
        }
        ?>
        <button type="button" class="button wcms-download-imposition" data-order_id="<?php echo esc_attr( $order->get_id() ); ?>">
            <?php esc_html_e( 'Download imposition layout', 'woocommerce-meter-sales' ); ?>
        </button>
        <script>
        jQuery(document).ready(function($) {
            $('.wcms-download-imposition').on('click', function() {
                var orderId = $(this).data('order_id');
                var url = (typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>');
                window.location.href = url + '?action=wcms_download_imposition&order_id=' + orderId + '&nonce=<?php echo esc_js( wp_create_nonce( 'wcms_download_imposition' ) ); ?>';
            });
        });
        </script>
        <?php
    }

    public function ajax_download_imposition() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized.' );
        }
        check_admin_referer( 'wcms_download_imposition', 'nonce' );

        $order_id = intval( $_GET['order_id'] ?? 0 );
        $order    = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_die( 'Order not found.' );
        }

        try {
            WCMS_Imposition_PDF::download( $order_id );
        } catch ( \Throwable $e ) {
            wp_die( 'Error generating PDF: ' . esc_html( $e->getMessage() ) );
        }
    }

    private function render_imposition_image( $film_width_cm, $nesting_result, $images_data ) {
        $images_nesting = $nesting_result['images'];
        $total_length_cm = $nesting_result['total_length_cm'];

        $scale = 5;
        $pad = 40;
        $img_w_px = $film_width_cm * $scale + $pad * 2;
        $img_h_px = max( $total_length_cm * $scale + $pad * 2, 200 );

        $image = imagecreatetruecolor( $img_w_px, $img_h_px );
        imagesavealpha( $image, true );
        $bg = imagecolorallocatealpha( $image, 245, 245, 245, 0 );
        imagefill( $image, 0, 0, $bg );

        $white = imagecolorallocate( $image, 255, 255, 255 );
        $border = imagecolorallocate( $image, 153, 153, 153 );
        $text_color = imagecolorallocate( $image, 51, 51, 51 );

        $colors = array(
            imagecolorallocatealpha( $image, 231, 76, 60, 40 ),
            imagecolorallocatealpha( $image, 52, 152, 219, 40 ),
            imagecolorallocatealpha( $image, 46, 204, 113, 40 ),
            imagecolorallocatealpha( $image, 243, 156, 18, 40 ),
            imagecolorallocatealpha( $image, 155, 89, 182, 40 ),
        );

        $ox = $pad;
        $oy = $pad;
        $total_w = $film_width_cm * $scale;
        $total_h = $total_length_cm * $scale;

        imagefilledrectangle( $image, $ox, $oy, $ox + $total_w, $oy + $total_h, $white );
        imagerectangle( $image, $ox, $oy, $ox + $total_w, $oy + $total_h, $border );

        $current_y = $oy;

        foreach ( $images_nesting as $idx => $n ) {
            $color = $colors[ $idx % count( $colors ) ];
            $stroke = imagecolorallocate( $image, 51, 51, 51 );

            for ( $row = 0; $row < $n['rows']; $row++ ) {
                for ( $col = 0; $col < $n['across']; $col++ ) {
                    $x = $ox + $col * ( $n['img_w'] + $gap ) * $scale;
                    $y = $current_y + $row * ( $n['img_h'] + $gap ) * $scale;
                    $w = $n['img_w'] * $scale;
                    $h = $n['img_h'] * $scale;

                    imagefilledrectangle( $image, $x, $y, $x + $w, $y + $h, $color );
                    imagerectangle( $image, $x, $y, $x + $w, $y + $h, $stroke );
                }
            }

            $label = sprintf( 'Image %d: %dx%d cm x%d', $idx + 1, $n['img_w'], $n['img_h'], $n['copies'] );
            if ( function_exists( 'imagettftext' ) ) {
                $font = WCMS_PLUGIN_DIR . 'assets/fonts/DejaVuSans.ttf';
                if ( file_exists( $font ) ) {
                    imagettftext( $image, 10, 0, $ox, $current_y - 4, $text_color, $font, $label );
                } else {
                    imagestring( $image, 2, $ox, $current_y - 12, $label, $text_color );
                }
            } else {
                imagestring( $image, 2, $ox, $current_y - 12, $label, $text_color );
            }

            $current_y += $n['length_cm'] * $scale;
        }

        $total_m = $total_length_cm / 100;
        $footer = sprintf( 'Film: %d cm | Total: %.2f m | Images: %d', $film_width_cm, $total_m, count( $images_data ) );
        if ( function_exists( 'imagettftext' ) ) {
            $font = WCMS_PLUGIN_DIR . 'assets/fonts/DejaVuSans.ttf';
            if ( file_exists( $font ) ) {
                $box = imagettfbbox( 11, 0, $font, $footer );
                $tw = $box[2] - $box[0];
                $tx = $ox + ( $total_w - $tw ) / 2;
                imagettftext( $image, 11, 0, $tx, $oy + $total_h + 20, $text_color, $font, $footer );
            } else {
                imagestring( $image, 2, $ox, $oy + $total_h + 4, $footer, $text_color );
            }
        } else {
            imagestring( $image, 2, $ox, $oy + $total_h + 4, $footer, $text_color );
        }

        header( 'Content-Type: image/png' );
        header( 'Content-Disposition: attachment; filename="imposition-order-' . intval( $_GET['order_id'] ?? 0 ) . '.png"' );
        imagepng( $image );
        imagedestroy( $image );
    }
}
