<?php
defined( 'ABSPATH' ) || exit;

class WCMS_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_calculator' ) );
        add_filter( 'woocommerce_get_price_html', array( $this, 'modify_price_html' ), 10, 2 );
        add_action( 'wc_ajax_wcms_calculate', array( $this, 'ajax_calculate' ) );
        add_action( 'wc_ajax_nopriv_wcms_calculate', array( $this, 'ajax_calculate' ) );
        add_action( 'wc_ajax_wcms_calculate_multi', array( $this, 'ajax_calculate_multi' ) );
        add_action( 'wc_ajax_nopriv_wcms_calculate_multi', array( $this, 'ajax_calculate_multi' ) );
        add_action( 'wc_ajax_wcms_calculate_price', array( $this, 'ajax_calculate_price' ) );
        add_action( 'wc_ajax_nopriv_wcms_calculate_price', array( $this, 'ajax_calculate_price' ) );
    }

    public static function enqueue_frontend_assets( $product_id = 0 ) {
		if ( ! wp_style_is( 'wcms-frontend', 'enqueued' ) ) {
			wp_enqueue_style( 'wcms-frontend', WCMS_PLUGIN_URL . 'assets/css/style.css', array(), WCMS_VERSION );
		}
		if ( ! wp_script_is( 'wcms-calculator', 'enqueued' ) ) {
			wp_enqueue_script( 'wcms-calculator', WCMS_PLUGIN_URL . 'assets/js/product-calculator.js', array( 'jquery' ), WCMS_VERSION, true );

			$custom_css = WCMS_Settings::get( 'custom_css', '' );
			$inline_css = 'form.cart .quantity { display:none !important; }';
			if ( ! empty( $custom_css ) ) {
				$inline_css .= "\n" . $custom_css;
			}
			wp_add_inline_style( 'wcms-frontend', $inline_css );

			$film_width = $product_id ? get_post_meta( $product_id, '_wcms_film_width', true ) : WCMS_Settings::get( 'default_film_width', 57 );
			$gap        = $product_id ? get_post_meta( $product_id, '_wcms_gap', true ) : WCMS_Settings::get( 'default_gap', 0.5 );
			$waste      = $product_id ? get_post_meta( $product_id, '_wcms_waste', true ) : WCMS_Settings::get( 'default_waste', 5 );
			$fixed      = $product_id ? get_post_meta( $product_id, '_wcms_fixed_price', true ) : 0;
			$tiers      = $product_id ? ( get_post_meta( $product_id, '_wcms_price_tiers', true ) ?: array() ) : array();
			$dpi        = intval( WCMS_Settings::get( 'dpi', 300 ) );

			wp_localize_script( 'wcms-calculator', 'wcms_params', array(
				'ajax_url'       => WC_AJAX::get_endpoint( 'wcms_calculate_multi' ),
				'ajax_price_url' => WC_AJAX::get_endpoint( 'wcms_calculate_price' ),
				'product_id'     => $product_id,
				'nonce'          => wp_create_nonce( 'wcms_calculate' ),
				'film_width'     => $film_width,
				'gap'            => $gap,
				'waste'          => $waste,
				'fixed_price'    => $fixed,
				'tiers'          => $tiers,
				'dpi'            => $dpi,
				'primary_color'  => WCMS_Settings::get( 'primary_color', '#333333' ),
				'accent_color'   => WCMS_Settings::get( 'accent_color', '#0073aa' ),
				'canvas_bg'      => WCMS_Settings::get( 'canvas_bg', '#f5f5f5' ),
				'rect_colors'    => WCMS_Settings::get( 'rect_colors', '#e74c3c,#3498db,#2ecc71,#f39c12,#9b59b6' ),
				'enable_debug'   => WCMS_Settings::get( 'enable_debug', 'no' ),
				'enable_pdf'     => WCMS_Settings::get( 'enable_pdf_upload', 'yes' ),
				'currency_symbol' => WCMS_Settings::get( 'currency_symbol', '$' ),
				'currency_pos'   => WCMS_Settings::get( 'currency_position', 'left' ),
				'decimal_sep'    => WCMS_Settings::get( 'decimal_sep', '.' ),
				'thousand_sep'   => WCMS_Settings::get( 'thousand_sep', ',' ),
				'i18n'           => array(
					'price_label'     => WCMS_Settings::get_label( 'total_price', __( 'Total price', 'woocommerce-meter-sales' ) ),
					'meters_label'    => WCMS_Settings::get_label( 'total_meters', __( 'Total meters', 'woocommerce-meter-sales' ) ),
					'rate_label'      => WCMS_Settings::get_label( 'price_per_meter', __( 'Price per meter', 'woocommerce-meter-sales' ) ),
					'fixed_label'     => WCMS_Settings::get_label( 'fixed_base', __( 'Fixed base', 'woocommerce-meter-sales' ) ),
					'copies_label'    => WCMS_Settings::get_label( 'copies_in_result', __( 'copies', 'woocommerce-meter-sales' ) ),
					'rotated_msg'     => WCMS_Settings::get_label( 'rotated_msg', __( '(rotated 90°)', 'woocommerce-meter-sales' ) ),
					'dpi_warning'     => __( 'Warning: Resolution below 150 DPI may cause poor print quality.', 'woocommerce-meter-sales' ),
					'dpi_label'       => __( 'DPI', 'woocommerce-meter-sales' ),
					'copies'          => __( 'Copies', 'woocommerce-meter-sales' ),
					'remove'          => __( 'Remove', 'woocommerce-meter-sales' ),
					'images_label'    => __( 'Images', 'woocommerce-meter-sales' ),
					'total_meters'    => __( 'Total meters', 'woocommerce-meter-sales' ),
					'price_per_meter' => __( 'Price per meter', 'woocommerce-meter-sales' ),
					'fixed_base'      => __( 'Fixed base', 'woocommerce-meter-sales' ),
					'total_price'     => __( 'Total price', 'woocommerce-meter-sales' ),
					'add_to_cart'     => __( 'Add to cart', 'woocommerce-meter-sales' ),
					'no_files'        => __( 'No files uploaded yet.', 'woocommerce-meter-sales' ),
					'film_warn'       => __( 'Dimension adjusted to fit film width of ', 'woocommerce-meter-sales' ),
					'optimize_btn'    => __( 'Optimize layout', 'woocommerce-meter-sales' ),
					'optimized_label' => __( 'Optimized', 'woocommerce-meter-sales' ),
					'calculating'     => __( 'Calculating optimized layout...', 'woocommerce-meter-sales' ),
					'title_placeholder' => __( 'Image title', 'woocommerce-meter-sales' ),
				),
			) );
		}
	}

    public function enqueue_scripts() {
        global $post;

        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }

        $has_divi4 = has_shortcode( $post->post_content, 'wcms_dtf_calculator' );
        if ( ! is_product() && ! $has_divi4 ) {
            return;
        }

        $product_id = 0;
        if ( is_product() ) {
            $enabled = get_post_meta( $post->ID, '_wcms_enabled', true );
            if ( $enabled !== 'yes' ) {
                return;
            }
            $product_id = $post->ID;
        }

        self::enqueue_frontend_assets( $product_id );
    }

    public function render_calculator() {
        global $post;
        $enabled = get_post_meta( $post->ID, '_wcms_enabled', true );
        if ( $enabled !== 'yes' ) {
            return;
        }

        wc_get_template(
            'product-calculator.php',
            array( 'product_id' => $post->ID ),
            '',
            WCMS_PLUGIN_DIR . 'templates/'
        );
    }

    public function modify_price_html( $price_html, $product ) {
        $enabled = get_post_meta( $product->get_id(), '_wcms_enabled', true );
        if ( $enabled === 'yes' ) {
            $prefix = WCMS_Settings::get_label( 'from_text', __( 'From: ', 'woocommerce-meter-sales' ) );
            return '<span class="wcms-from-price">' . esc_html( $prefix ) . $price_html . '</span>';
        }
        return $price_html;
    }

    public function ajax_calculate() {
        check_ajax_referer( 'wcms_calculate', 'nonce' );

        $product_id = intval( $_POST['product_id'] ?? 0 );
        $img_w      = floatval( $_POST['img_w'] ?? 0 );
        $img_h      = floatval( $_POST['img_h'] ?? 0 );
        $copies     = intval( $_POST['copies'] ?? 1 );

        if ( ! $product_id || ! $img_w || ! $img_h || ! $copies ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
        }

        $film_width = get_post_meta( $product_id, '_wcms_film_width', true ) ?: 57;
        $gap        = get_post_meta( $product_id, '_wcms_gap', true ) ?: 0.5;
        $waste      = get_post_meta( $product_id, '_wcms_waste', true ) ?: 5;

        $nesting = new WCMS_Nesting();
        $result  = $nesting->calculate( $film_width, $img_w, $img_h, $copies, $gap, $waste );

        $pricing   = new WCMS_Pricing();
        $price     = $pricing->calculate_price( $product_id, $result['length_m'] );
        $breakdown = $pricing->get_price_breakdown( $product_id, $result['length_m'] );

        wp_send_json_success( array(
            'nesting'    => $result,
            'breakdown'  => $breakdown,
            'price'      => $price,
            'min_notice' => $breakdown['min_applied'] ? sprintf(
                esc_html__( 'Minimum charge of %s m applied.', 'woocommerce-meter-sales' ),
                number_format( $breakdown['min_charge'], 2 )
            ) : '',
            'formatted'  => array(
                'total'    => WCMS_Settings::format_price( $price ),
                'fixed'    => WCMS_Settings::format_price( $breakdown['fixed'] ),
                'rate'     => WCMS_Settings::format_price( $breakdown['rate'] ),
                'meters'   => number_format( $result['length_m'], 2 ),
                'variable' => WCMS_Settings::format_price( $breakdown['variable'] ),
            ),
        ) );
    }

    public function ajax_calculate_price() {
        check_ajax_referer( 'wcms_calculate', 'nonce' );

        $product_id = intval( $_POST['product_id'] ?? 0 );
        $meters     = floatval( $_POST['meters'] ?? 0 );

        if ( ! $product_id || $meters <= 0 ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
        }

        $pricing   = new WCMS_Pricing();
        $price     = $pricing->calculate_price( $product_id, $meters );
        $breakdown = $pricing->get_price_breakdown( $product_id, $meters );

        wp_send_json_success( array(
            'breakdown'  => $breakdown,
            'price'      => $price,
            'min_notice' => $breakdown['min_applied'] ? sprintf(
                esc_html__( 'Minimum charge of %s m applied.', 'woocommerce-meter-sales' ),
                number_format( $breakdown['min_charge'], 2 )
            ) : '',
            'formatted'  => array(
                'total'    => WCMS_Settings::format_price( $price ),
                'fixed'    => WCMS_Settings::format_price( $breakdown['fixed'] ),
                'rate'     => WCMS_Settings::format_price( $breakdown['rate'] ),
                'meters'   => number_format( $meters, 2 ),
                'variable' => WCMS_Settings::format_price( $breakdown['variable'] ),
            ),
        ) );
    }

    public function ajax_calculate_multi() {
        check_ajax_referer( 'wcms_calculate', 'nonce' );

        $product_id = intval( $_POST['product_id'] ?? 0 );
        $images     = isset( $_POST['images'] ) ? json_decode( wp_unslash( $_POST['images'] ), true ) : array();

        if ( ! $product_id || empty( $images ) ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
        }

        $film_width = get_post_meta( $product_id, '_wcms_film_width', true ) ?: 57;
        $gap        = get_post_meta( $product_id, '_wcms_gap', true ) ?: 0.5;
        $waste      = get_post_meta( $product_id, '_wcms_waste', true ) ?: 5;

        $nesting = new WCMS_Nesting();
        $result  = $nesting->calculate_multi( $film_width, $images, $gap, $waste );

        $pricing   = new WCMS_Pricing();
        $price     = $pricing->calculate_price( $product_id, $result['total_length_m'] );
        $breakdown = $pricing->get_price_breakdown( $product_id, $result['total_length_m'] );

        $total_copies = 0;
        foreach ( $images as $img ) {
            $total_copies += intval( $img['copies'] );
        }

        wp_send_json_success( array(
            'nesting'      => $result,
            'breakdown'    => $breakdown,
            'price'        => $price,
            'total_copies' => $total_copies,
            'total_images' => count( $images ),
            'min_notice'   => $breakdown['min_applied'] ? sprintf(
                esc_html__( 'Minimum charge of %s m applied.', 'woocommerce-meter-sales' ),
                number_format( $breakdown['min_charge'], 2 )
            ) : '',
            'formatted'    => array(
                'total'    => WCMS_Settings::format_price( $price ),
                'fixed'    => WCMS_Settings::format_price( $breakdown['fixed'] ),
                'rate'     => WCMS_Settings::format_price( $breakdown['rate'] ),
                'meters'   => number_format( $result['total_length_m'], 2 ),
                'variable' => WCMS_Settings::format_price( $breakdown['variable'] ),
            ),
        ) );
    }
}
