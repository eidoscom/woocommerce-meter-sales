<?php
$calc_title      = WCMS_Settings::get_label( 'calculator_title', __( 'DTF Configurator', 'woocommerce-meter-sales' ) );
$upload_label    = WCMS_Settings::get_label( 'upload_label', __( 'Upload files (PNG / PDF)', 'woocommerce-meter-sales' ) );
$processing_text = WCMS_Settings::get_label( 'processing_file', __( 'Processing files...', 'woocommerce-meter-sales' ) );
$img_w_label     = WCMS_Settings::get_label( 'img_width_label', __( 'Width (cm)', 'woocommerce-meter-sales' ) );
$img_h_label     = WCMS_Settings::get_label( 'img_height_label', __( 'Height (cm)', 'woocommerce-meter-sales' ) );
$copies_label    = WCMS_Settings::get_label( 'copies_label', __( 'Copies', 'woocommerce-meter-sales' ) );
$film_w_label    = WCMS_Settings::get_label( 'film_width_label', __( 'Film width:', 'woocommerce-meter-sales' ) );
$preview_btn     = WCMS_Settings::get_label( 'view_preview_btn', __( 'View full preview', 'woocommerce-meter-sales' ) );
$total_meters    = WCMS_Settings::get_label( 'total_meters', __( 'Total meters', 'woocommerce-meter-sales' ) );
$arrangement     = WCMS_Settings::get_label( 'arrangement', __( 'Arrangement', 'woocommerce-meter-sales' ) );
$fixed_base      = WCMS_Settings::get_label( 'fixed_base', __( 'Fixed base', 'woocommerce-meter-sales' ) );
$price_meter     = WCMS_Settings::get_label( 'price_per_meter', __( 'Price per meter', 'woocommerce-meter-sales' ) );
$total_price     = WCMS_Settings::get_label( 'total_price', __( 'Total price', 'woocommerce-meter-sales' ) );

$enable_pdf = WCMS_Settings::get( 'enable_pdf_upload', 'yes' );
$accept     = $enable_pdf === 'yes' ? '.pdf,.png,.jpg,.jpeg' : '.png,.jpg,.jpeg';
$current_product_id = isset( $product_id ) ? $product_id : get_the_ID();
$product    = wc_get_product( $current_product_id );
$add_to_cart_text = $product ? $product->single_add_to_cart_text() : __( 'Add to cart', 'woocommerce-meter-sales' );
?>
<div class="wcms-calculator" id="wcms-calculator">
    <h3><?php echo esc_html( $calc_title ); ?></h3>

    <div class="wcms-configurator">
        <!-- LEFT COLUMN: Upload + file list -->
        <div class="wcms-col wcms-col-left">
            <div class="wcms-input-group">
                <label><?php echo esc_html( $upload_label ); ?></label>
                <input type="file" id="wcms-upload" accept="<?php echo esc_attr( $accept ); ?>" multiple />
            </div>

            <div class="wcms-file-list" id="wcms-file-list">
                <p class="wcms-no-files"><?php esc_html_e( 'No files uploaded yet.', 'woocommerce-meter-sales' ); ?></p>
            </div>

            <div class="wcms-loading" id="wcms-loading" style="display:none;">
                <span class="wcms-spinner"></span>
                <span class="wcms-loading-text"><?php echo esc_html( $processing_text ); ?></span>
            </div>
        </div>

        <!-- CENTER COLUMN: Preview -->
        <div class="wcms-col wcms-col-center">
            <div class="wcms-preview-container">
                <div class="wcms-preview-scroll" id="wcms-preview-scroll">
                    <canvas id="wcms-preview-canvas" width="570" height="200"></canvas>
                </div>
                <p class="wcms-preview-label">
                    <?php echo esc_html( $film_w_label ); ?>
                    <span id="wcms-film-width-display">57</span> cm
                </p>
                <button type="button" class="button wcms-preview-btn" id="wcms-preview-btn" style="display:none;">
                    <?php echo esc_html( $preview_btn ); ?>
                </button>
                <button type="button" class="button wcms-optimize-btn" id="wcms-optimize-btn" style="display:none;">
                    <?php esc_html_e( 'Optimize layout', 'woocommerce-meter-sales' ); ?>
                </button>
            </div>
        </div>

        <!-- RIGHT COLUMN: Summary -->
        <div class="wcms-col wcms-col-right">
            <div class="wcms-summary" id="wcms-summary">
                <h4><?php esc_html_e( 'Summary', 'woocommerce-meter-sales' ); ?></h4>
                <p id="wcms-min-notice" class="wcms-min-notice" style="display:none;"></p>
                <div class="wcms-summary-row">
                    <span class="wcms-summary-label"><?php esc_html_e( 'Images', 'woocommerce-meter-sales' ); ?></span>
                    <span class="wcms-summary-value" id="wcms-summary-images">0</span>
                </div>
                <div class="wcms-summary-row">
                    <span class="wcms-summary-label"><?php echo esc_html( $total_meters ); ?></span>
                    <span class="wcms-summary-value" id="wcms-summary-meters">0.00 m</span>
                </div>
                <div class="wcms-summary-row">
                    <span class="wcms-summary-label"><?php echo esc_html( $fixed_base ); ?></span>
                    <span class="wcms-summary-value" id="wcms-summary-fixed"><?php echo WCMS_Settings::format_price( 0 ); ?></span>
                </div>
                <div class="wcms-summary-row">
                    <span class="wcms-summary-label"><?php echo esc_html( $price_meter ); ?></span>
                    <span class="wcms-summary-value" id="wcms-summary-rate"><?php echo WCMS_Settings::format_price( 0 ); ?></span>
                </div>
                <div class="wcms-summary-row wcms-total-row">
                    <span class="wcms-summary-label"><?php echo esc_html( $total_price ); ?></span>
                    <span class="wcms-summary-value" id="wcms-summary-total"><?php echo WCMS_Settings::format_price( 0 ); ?></span>
                </div>
                <div class="wcms-summary-action">
                    <button type="submit" class="single_add_to_cart_button button alt wcms-add-to-cart" id="wcms-add-to-cart">
                        <?php echo esc_html( $add_to_cart_text ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="wcms_images_data" id="wcms_images_data" value="" />
</div>
