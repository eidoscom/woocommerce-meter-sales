<?php
defined( 'ABSPATH' ) || exit;

class WCMS_Settings {

    private $option_group = 'wcms_settings';
    private $option_name  = 'wcms_options';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( WCMS_PLUGIN_DIR . 'woocommerce-meter-sales.php' ), array( $this, 'add_action_links' ) );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( $hook !== 'woocommerce_page_wcms-settings' ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_style( 'wcms-admin', WCMS_PLUGIN_URL . 'assets/css/admin.css', array(), WCMS_VERSION );
        wp_enqueue_script( 'wcms-admin', WCMS_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), WCMS_VERSION, true );
    }

    public function add_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=wcms-settings' ) . '">' . __( 'Settings', 'woocommerce-meter-sales' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Meter Sales', 'woocommerce-meter-sales' ),
            __( 'Meter Sales', 'woocommerce-meter-sales' ),
            'manage_woocommerce',
            'wcms-settings',
            array( $this, 'render_page' )
        );
    }

    public static function get( $key, $default = '' ) {
        $options = get_option( 'wcms_options', array() );
        $value   = isset( $options[ $key ] ) ? $options[ $key ] : $default;
        return apply_filters( 'wcms_settings_value', $value, $key, $default );
    }

    public function register_settings() {
        register_setting( $this->option_group, $this->option_name, array( $this, 'sanitize' ) );

        add_settings_section(
            'wcms_general',
            __( 'General', 'woocommerce-meter-sales' ),
            null,
            'wcms-settings'
        );

        add_settings_field(
            'default_film_width',
            __( 'Default film width (cm)', 'woocommerce-meter-sales' ),
            array( $this, 'field_number' ),
            'wcms-settings',
            'wcms_general',
            array( 'key' => 'default_film_width', 'default' => '57', 'step' => '0.1', 'min' => '1' )
        );

        add_settings_field(
            'default_gap',
            __( 'Default gap (cm)', 'woocommerce-meter-sales' ),
            array( $this, 'field_number' ),
            'wcms-settings',
            'wcms_general',
            array( 'key' => 'default_gap', 'default' => '0.5', 'step' => '0.1', 'min' => '0' )
        );

        add_settings_field(
            'default_waste',
            __( 'Default waste (%)', 'woocommerce-meter-sales' ),
            array( $this, 'field_number' ),
            'wcms-settings',
            'wcms_general',
            array( 'key' => 'default_waste', 'default' => '5', 'step' => '0.5', 'min' => '0', 'max' => '50' )
        );

        add_settings_field(
            'dpi',
            __( 'DPI for image conversion', 'woocommerce-meter-sales' ),
            array( $this, 'field_number' ),
            'wcms-settings',
            'wcms_general',
            array( 'key' => 'dpi', 'default' => '300', 'step' => '1', 'min' => '72' )
        );

        add_settings_field(
            'min_charge_meters',
            __( 'Minimum charge (meters)', 'woocommerce-meter-sales' ),
            array( $this, 'field_number' ),
            'wcms-settings',
            'wcms_general',
            array( 'key' => 'min_charge_meters', 'default' => '1', 'step' => '0.01', 'min' => '0', 'description' => __( 'Always charge at least this many meters, even for smaller jobs. Set 0 to disable.', 'woocommerce-meter-sales' ) )
        );

        add_settings_section(
            'wcms_currency',
            __( 'Currency & Formatting', 'woocommerce-meter-sales' ),
            array( $this, 'section_currency_desc' ),
            'wcms-settings'
        );

        add_settings_field(
            'currency_symbol',
            __( 'Currency symbol', 'woocommerce-meter-sales' ),
            array( $this, 'field_text' ),
            'wcms-settings',
            'wcms_currency',
            array( 'key' => 'currency_symbol', 'default' => '$', 'class' => 'small-text' )
        );

        add_settings_field(
            'currency_position',
            __( 'Currency position', 'woocommerce-meter-sales' ),
            array( $this, 'field_select' ),
            'wcms-settings',
            'wcms_currency',
            array(
                'key'     => 'currency_position',
                'default' => 'left',
                'options' => array(
                    'left'        => __( 'Left ($10)', 'woocommerce-meter-sales' ),
                    'right'       => __( 'Right (10$)', 'woocommerce-meter-sales' ),
                    'left_space'  => __( 'Left with space ($ 10)', 'woocommerce-meter-sales' ),
                    'right_space' => __( 'Right with space (10 $)', 'woocommerce-meter-sales' ),
                ),
            )
        );

        add_settings_field(
            'decimal_sep',
            __( 'Decimal separator', 'woocommerce-meter-sales' ),
            array( $this, 'field_text' ),
            'wcms-settings',
            'wcms_currency',
            array( 'key' => 'decimal_sep', 'default' => '.', 'class' => 'small-text' )
        );

        add_settings_field(
            'thousand_sep',
            __( 'Thousand separator', 'woocommerce-meter-sales' ),
            array( $this, 'field_text' ),
            'wcms-settings',
            'wcms_currency',
            array( 'key' => 'thousand_sep', 'default' => ',', 'class' => 'small-text' )
        );

        add_settings_section(
            'wcms_appearance',
            __( 'Appearance', 'woocommerce-meter-sales' ),
            null,
            'wcms-settings'
        );

        add_settings_field(
            'primary_color',
            __( 'Primary color', 'woocommerce-meter-sales' ),
            array( $this, 'field_color' ),
            'wcms-settings',
            'wcms_appearance',
            array( 'key' => 'primary_color', 'default' => '#333333' )
        );

        add_settings_field(
            'accent_color',
            __( 'Accent color', 'woocommerce-meter-sales' ),
            array( $this, 'field_color' ),
            'wcms-settings',
            'wcms_appearance',
            array( 'key' => 'accent_color', 'default' => '#0073aa' )
        );

        add_settings_field(
            'canvas_bg',
            __( 'Canvas background', 'woocommerce-meter-sales' ),
            array( $this, 'field_color' ),
            'wcms-settings',
            'wcms_appearance',
            array( 'key' => 'canvas_bg', 'default' => '#f5f5f5' )
        );

        add_settings_field(
            'rect_colors',
            __( 'Rectangle colors', 'woocommerce-meter-sales' ),
            array( $this, 'field_rect_colors' ),
            'wcms-settings',
            'wcms_appearance',
            array( 'key' => 'rect_colors', 'default' => '#e74c3c,#3498db,#2ecc71,#f39c12,#9b59b6' )
        );

        add_settings_field(
            'custom_css',
            __( 'Custom CSS', 'woocommerce-meter-sales' ),
            array( $this, 'field_textarea' ),
            'wcms-settings',
            'wcms_appearance',
            array( 'key' => 'custom_css', 'default' => '' )
        );

        add_settings_section(
            'wcms_labels',
            __( 'Labels / Translation', 'woocommerce-meter-sales' ),
            array( $this, 'section_labels_desc' ),
            'wcms-settings'
        );

        $labels = $this->get_labels();
        foreach ( $labels as $key => $default ) {
            add_settings_field(
                'label_' . $key,
                $default['label'],
                array( $this, 'field_text' ),
                'wcms-settings',
                'wcms_labels',
                array( 'key' => 'label_' . $key, 'default' => $default['default'], 'class' => 'regular-text' )
            );
        }

        add_settings_section(
            'wcms_advanced',
            __( 'Advanced', 'woocommerce-meter-sales' ),
            null,
            'wcms-settings'
        );

        add_settings_field(
            'enable_pdf_upload',
            __( 'Enable PDF upload', 'woocommerce-meter-sales' ),
            array( $this, 'field_checkbox' ),
            'wcms-settings',
            'wcms_advanced',
            array( 'key' => 'enable_pdf_upload', 'default' => 'yes', 'label' => __( 'Allow PDF file upload in calculator', 'woocommerce-meter-sales' ) )
        );

        add_settings_field(
            'enable_debug',
            __( 'Debug mode', 'woocommerce-meter-sales' ),
            array( $this, 'field_checkbox' ),
            'wcms-settings',
            'wcms_advanced',
            array( 'key' => 'enable_debug', 'default' => 'no', 'label' => __( 'Log AJAX responses to browser console', 'woocommerce-meter-sales' ) )
        );
    }

    private function get_labels() {
        return array(
            'calculator_title'    => array( 'label' => __( 'Calculator title', 'woocommerce-meter-sales' ), 'default' => 'DTF Meter Calculator' ),
            'upload_label'        => array( 'label' => __( 'Upload field label', 'woocommerce-meter-sales' ), 'default' => 'Upload PDF or PNG (or enter dimensions manually)' ),
            'processing_file'     => array( 'label' => __( 'Processing file text', 'woocommerce-meter-sales' ), 'default' => 'Processing file...' ),
            'img_width_label'     => array( 'label' => __( 'Image width label', 'woocommerce-meter-sales' ), 'default' => 'Image width (cm)' ),
            'img_height_label'    => array( 'label' => __( 'Image height label', 'woocommerce-meter-sales' ), 'default' => 'Image height (cm)' ),
            'copies_label'        => array( 'label' => __( 'Copies label', 'woocommerce-meter-sales' ), 'default' => 'Number of copies' ),
            'film_width_label'    => array( 'label' => __( 'Film width label', 'woocommerce-meter-sales' ), 'default' => 'Film width: ' ),
            'view_preview_btn'    => array( 'label' => __( 'Preview button text', 'woocommerce-meter-sales' ), 'default' => 'View full preview' ),
            'total_meters'        => array( 'label' => __( 'Total meters', 'woocommerce-meter-sales' ), 'default' => 'Total meters' ),
            'arrangement'         => array( 'label' => __( 'Arrangement label', 'woocommerce-meter-sales' ), 'default' => 'Arrangement' ),
            'fixed_base'          => array( 'label' => __( 'Fixed base label', 'woocommerce-meter-sales' ), 'default' => 'Fixed base' ),
            'price_per_meter'     => array( 'label' => __( 'Price per meter label', 'woocommerce-meter-sales' ), 'default' => 'Price per meter' ),
            'total_price'         => array( 'label' => __( 'Total price label', 'woocommerce-meter-sales' ), 'default' => 'Total price' ),
            'from_text'           => array( 'label' => __( '"From:" prefix text', 'woocommerce-meter-sales' ), 'default' => 'From: ' ),
            'rotated_msg'         => array( 'label' => __( 'Rotated 90° message', 'woocommerce-meter-sales' ), 'default' => '(rotated 90°)' ),
            'copies_in_result'    => array( 'label' => __( 'Copies text in result', 'woocommerce-meter-sales' ), 'default' => 'copies' ),
        );
    }

    public function sanitize( $input ) {
        $output = array();
        $output['default_film_width'] = isset( $input['default_film_width'] ) ? floatval( $input['default_film_width'] ) : 57;
        $output['default_gap']        = isset( $input['default_gap'] ) ? floatval( $input['default_gap'] ) : 0.5;
        $output['default_waste']      = isset( $input['default_waste'] ) ? floatval( $input['default_waste'] ) : 5;
        $output['dpi']                = isset( $input['dpi'] ) ? intval( $input['dpi'] ) : 300;
        $output['min_charge_meters']  = isset( $input['min_charge_meters'] ) ? floatval( $input['min_charge_meters'] ) : 1;
        $output['currency_symbol']    = isset( $input['currency_symbol'] ) ? sanitize_text_field( $input['currency_symbol'] ) : '$';
        $output['currency_position']  = isset( $input['currency_position'] ) ? sanitize_text_field( $input['currency_position'] ) : 'left';
        $output['decimal_sep']        = isset( $input['decimal_sep'] ) ? sanitize_text_field( $input['decimal_sep'] ) : '.';
        $output['thousand_sep']       = isset( $input['thousand_sep'] ) ? sanitize_text_field( $input['thousand_sep'] ) : ',';
        $output['primary_color']      = isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : '#333333';
        $output['accent_color']       = isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : '#0073aa';
        $output['canvas_bg']          = isset( $input['canvas_bg'] ) ? sanitize_hex_color( $input['canvas_bg'] ) : '#f5f5f5';
        $output['rect_colors']        = isset( $input['rect_colors'] ) ? sanitize_text_field( $input['rect_colors'] ) : '#e74c3c,#3498db,#2ecc71,#f39c12,#9b59b6';
        $output['custom_css']         = isset( $input['custom_css'] ) ? wp_strip_all_tags( $input['custom_css'] ) : '';
        $output['enable_pdf_upload']  = isset( $input['enable_pdf_upload'] ) ? 'yes' : 'no';
        $output['enable_debug']       = isset( $input['enable_debug'] ) ? 'yes' : 'no';

        $labels = $this->get_labels();
        foreach ( $labels as $key => $label ) {
            $field_key = 'label_' . $key;
            $output[ $field_key ] = isset( $input[ $field_key ] ) ? sanitize_text_field( $input[ $field_key ] ) : $label['default'];
        }

        return $output;
    }

    public function section_currency_desc() {
        echo '<p>' . esc_html__( 'These settings override the default WooCommerce currency format for the calculator display only.', 'woocommerce-meter-sales' ) . '</p>';
    }

    public function section_labels_desc() {
        echo '<p>' . esc_html__( 'Override any text shown in the calculator. Leave blank to use the default translation.', 'woocommerce-meter-sales' ) . '</p>';
    }

    public function field_number( $args ) {
        $options = get_option( $this->option_name, array() );
        $value   = isset( $options[ $args['key'] ] ) ? $options[ $args['key'] ] : $args['default'];
        $attrs   = '';
        foreach ( array( 'step', 'min', 'max' ) as $attr ) {
            if ( isset( $args[ $attr ] ) ) {
                $attrs .= ' ' . $attr . '="' . esc_attr( $args[ $attr ] ) . '"';
            }
        }
        echo '<input type="number" name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $args['key'] ) . ']" value="' . esc_attr( $value ) . '"' . $attrs . ' class="small-text" />';
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function field_text( $args ) {
        $options = get_option( $this->option_name, array() );
        $value   = isset( $options[ $args['key'] ] ) ? $options[ $args['key'] ] : $args['default'];
        $class   = isset( $args['class'] ) ? $args['class'] : 'regular-text';
        echo '<input type="text" name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $args['key'] ) . ']" value="' . esc_attr( $value ) . '" class="' . esc_attr( $class ) . '" />';
    }

    public function field_color( $args ) {
        $options = get_option( $this->option_name, array() );
        $value   = isset( $options[ $args['key'] ] ) ? $options[ $args['key'] ] : $args['default'];
        echo '<input type="text" name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $args['key'] ) . ']" value="' . esc_attr( $value ) . '" class="wcms-color-picker" data-default-color="' . esc_attr( $args['default'] ) . '" style="width:100px;" />';
    }

    public function field_select( $args ) {
        $options = get_option( $this->option_name, array() );
        $value   = isset( $options[ $args['key'] ] ) ? $options[ $args['key'] ] : $args['default'];
        echo '<select name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $args['key'] ) . ']">';
        foreach ( $args['options'] as $opt_val => $opt_label ) {
            echo '<option value="' . esc_attr( $opt_val ) . '" ' . selected( $value, $opt_val, false ) . '>' . esc_html( $opt_label ) . '</option>';
        }
        echo '</select>';
    }

    public function field_checkbox( $args ) {
        $options = get_option( $this->option_name, array() );
        $value   = isset( $options[ $args['key'] ] ) ? $options[ $args['key'] ] : $args['default'];
        $label   = isset( $args['label'] ) ? $args['label'] : '';
        echo '<label><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $args['key'] ) . ']" value="yes" ' . checked( $value, 'yes', false ) . ' /> ' . esc_html( $label ) . '</label>';
    }

    public function field_textarea( $args ) {
        $options = get_option( $this->option_name, array() );
        $value   = isset( $options[ $args['key'] ] ) ? $options[ $args['key'] ] : $args['default'];
        echo '<textarea name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $args['key'] ) . ']" rows="6" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
    }

    public function field_rect_colors( $args ) {
        $options = get_option( $this->option_name, array() );
        $value   = isset( $options[ $args['key'] ] ) ? $options[ $args['key'] ] : $args['default'];
        $colors  = array_filter( array_map( 'trim', explode( ',', $value ) ) );
        echo '<div class="wcms-rect-colors-wrapper" data-name="' . esc_attr( $this->option_name . '[' . $args['key'] . ']' ) . '">';
        echo '<div class="wcms-rect-colors-list">';
        foreach ( $colors as $color ) {
            if ( preg_match( '/^#[a-f0-9]{6}$/i', $color ) ) {
                echo '<span class="wcms-rect-color-swatch" data-color="' . esc_attr( $color ) . '" style="background:' . esc_attr( $color ) . ';"><button type="button" class="wcms-rect-color-remove" title="' . esc_attr__( 'Remove', 'woocommerce-meter-sales' ) . '">&times;</button></span>';
            }
        }
        echo '</div>';
        echo '<div class="wcms-rect-color-add">';
        echo '<input type="text" class="wcms-rect-color-picker" value="#3498db" />';
        echo '<button type="button" class="button wcms-rect-color-add-btn">' . esc_html__( 'Add color', 'woocommerce-meter-sales' ) . '</button>';
        echo '</div>';
        echo '<input type="hidden" class="wcms-rect-colors-input" name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $args['key'] ) . ']" value="' . esc_attr( $value ) . '" />';
        echo '</div>';
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        ?>
        <div class="wrap wcms-settings-page">
            <h1><?php esc_html_e( 'Meter Sales Settings', 'woocommerce-meter-sales' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( $this->option_group );
                do_settings_sections( 'wcms-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
            if ($.fn.wpColorPicker) {
                $('.wcms-color-picker').wpColorPicker();
            }
        });
        </script>
        <?php
    }

    public static function format_price( $amount ) {
        $symbol = self::get( 'currency_symbol', '$' );
        $pos    = self::get( 'currency_position', 'left' );
        $dec    = self::get( 'decimal_sep', '.' );
        $thou   = self::get( 'thousand_sep', ',' );
        $num    = number_format( $amount, 2, $dec, $thou );
        switch ( $pos ) {
            case 'right':
                return $num . $symbol;
            case 'left_space':
                return $symbol . ' ' . $num;
            case 'right_space':
                return $num . ' ' . $symbol;
            default:
                return $symbol . $num;
        }
    }

    public static function get_label( $key, $fallback = '' ) {
        $value = self::get( 'label_' . $key, '' );
        if ( empty( $value ) ) {
            $value = $fallback;
        }
        return apply_filters( 'wcms_settings_label', $value, $key, $fallback );
    }
}
