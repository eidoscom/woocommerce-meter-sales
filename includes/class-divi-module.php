<?php
defined( 'ABSPATH' ) || exit;

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Framework\Utility\HTMLUtility;
use ET\Builder\FrontEnd\Module\Style;
use ET\Builder\Packages\GlobalData\GlobalData;
use ET\Builder\Packages\Module\Module;
use ET\Builder\Packages\Module\Options\Element\ElementClassnames;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

class WCMS_Divi_Module implements DependencyInterface {

	private $loaded = false;

	private static $override_stack = [];

	public function load() {
		if ( $this->loaded ) {
			return;
		}
		$this->loaded = true;

		add_action( 'init', [ self::class, 'register_module' ] );
	}

	public static function register_module() {
		$metadata_path = WCMS_PLUGIN_DIR . 'visual-builder/src';

		if ( ! is_dir( $metadata_path ) ) {
			return;
		}

		ModuleRegistration::register_module(
			$metadata_path,
			[
				'render_callback' => [ self::class, 'render_callback' ],
			]
		);
	}

	public static function module_styles( $args ) {
		$elements = $args['elements'];

		Style::add(
			[
				'id'            => $args['id'],
				'name'          => $args['name'],
				'orderIndex'    => $args['orderIndex'],
				'storeInstance' => $args['storeInstance'],
				'styles'        => [
					$elements->style(
						[
							'attrName'   => 'module',
							'styleProps' => [
								'disabledOn' => [
									'disabledModuleVisibility' => $args['settings']['disabledModuleVisibility'] ?? null,
								],
							],
						]
					),
				],
			]
		);
	}

	public static function module_script_data( $args ) {
		$elements = $args['elements'];
		$elements->script_data( [ 'attrName' => 'module' ] );
	}

	public static function module_classnames( $args ) {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		$classnames_instance->add(
			ElementClassnames::classnames(
				[
					'attrs' => $attrs['module']['decoration'] ?? [],
				]
			)
		);
	}

	public static function render_callback( $attrs, $content, $block, $elements ) {
		$product_id = $attrs['module']['advanced']['product_id']['desktop']['value'] ?? $attrs['product_id'] ?? 0;
		if ( empty( $product_id ) || '0' === $product_id ) {
			global $product;
			if ( $product instanceof WC_Product ) {
				$product_id = $product->get_id();
			}
		}

		$product_id = (int) $product_id;
		if ( $product_id > 0 ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return self::placeholder( 'Product not found.' );
			}
		}

		$overrides = self::build_overrides( $attrs );
		self::apply_overrides( $overrides );

		WCMS_Frontend::enqueue_frontend_assets( $product_id );

		$inline_css = self::inline_styles( $overrides );
		if ( ! empty( $inline_css ) ) {
			wp_add_inline_style( 'wcms-frontend', $inline_css );
		}

		$js_overrides = [];
		$js_key_map = [
			'canvas_bg'         => 'canvas_bg',
			'rect_colors'       => 'rect_colors',
			'enable_debug'      => 'enable_debug',
			'enable_pdf_upload' => 'enable_pdf',
		];
		foreach ( $js_key_map as $override_key => $js_key ) {
			if ( isset( $overrides[ $override_key ] ) ) {
				$val = $overrides[ $override_key ];
				if ( $override_key === 'canvas_bg' ) {
					$val = self::resolve_gcid( $val );
				}
				if ( $override_key === 'rect_colors' ) {
					$parts = array_map( 'trim', explode( ',', $val ) );
					$parts = array_map( [ self::class, 'resolve_gcid' ], $parts );
					$val   = implode( ',', $parts );
				}
				$js_overrides[ $js_key ] = $val;
			}
		}
		if ( ! empty( $js_overrides ) ) {
			wp_add_inline_script(
				'wcms-calculator',
				'window.wcms_params = Object.assign(window.wcms_params || {}, ' . wp_json_encode( $js_overrides ) . ');'
			);
		}

		$calculator_html = wc_get_template_html(
			'product-calculator.php',
			[ 'product_id' => $product_id ],
			'',
			WCMS_PLUGIN_DIR . 'templates/'
		);

		self::remove_overrides();

		$module_inner = HTMLUtility::render(
			[
				'tag'               => 'div',
				'attributes'        => [
					'class' => 'et_pb_module_inner',
				],
				'childrenSanitizer' => 'et_core_esc_previously',
				'children'          => $calculator_html,
			]
		);

		$module_elements = $elements->style_components( [ 'attrName' => 'module' ] );

		return Module::render(
			[
				'orderIndex'          => $block->parsed_block['orderIndex'],
				'storeInstance'       => $block->parsed_block['storeInstance'],
				'attrs'               => $attrs,
				'elements'            => $elements,
				'id'                  => $block->parsed_block['id'],
				'moduleClassName'     => 'wcms_product_form',
				'name'                => $block->block_type->name,
				'classnamesFunction'  => [ self::class, 'module_classnames' ],
				'moduleCategory'      => $block->block_type->category,
				'stylesComponent'     => [ self::class, 'module_styles' ],
				'scriptDataComponent' => [ self::class, 'module_script_data' ],
				'children'            => $module_elements . $module_inner,
			]
		);
	}

	private static function build_overrides( $attrs ) {
		$overrides = [];
		$advanced  = $attrs['module']['advanced'] ?? [];
		$rect_colors = [];
		foreach ( $advanced as $key => $setting ) {
			if ( strpos( $key, 'rect_color_' ) === 0 ) {
				if ( is_array( $setting ) && isset( $setting['desktop']['value'] ) ) {
					$val = trim( $setting['desktop']['value'] );
					if ( $val !== '' && $val !== null ) {
						$rect_colors[] = $val;
					}
				}
				continue;
			}
			if ( is_array( $setting ) && isset( $setting['desktop']['value'] ) ) {
				$val = $setting['desktop']['value'];
				if ( $val !== '' && $val !== null ) {
					$overrides[ $key ] = $val;
				}
			}
		}
		if ( ! empty( $rect_colors ) ) {
			$overrides['rect_colors'] = implode( ',', $rect_colors );
		}
		if ( isset( $overrides['enable_pdf_upload'] ) ) {
			$overrides['enable_pdf_upload'] = $overrides['enable_pdf_upload'] ? 'yes' : 'no';
		}
		if ( isset( $overrides['enable_debug'] ) ) {
			$overrides['enable_debug'] = $overrides['enable_debug'] ? 'yes' : 'no';
		}
		return $overrides;
	}

	private static function apply_overrides( $overrides ) {
		if ( empty( $overrides ) ) {
			return;
		}
		self::$override_stack[] = $overrides;
		if ( count( self::$override_stack ) === 1 ) {
			add_filter( 'wcms_settings_value', [ self::class, 'filter_settings_value' ], 10, 2 );
			add_filter( 'wcms_settings_label', [ self::class, 'filter_settings_label' ], 10, 2 );
		}
	}

	private static function remove_overrides() {
		array_pop( self::$override_stack );
		if ( empty( self::$override_stack ) ) {
			remove_filter( 'wcms_settings_value', [ self::class, 'filter_settings_value' ], 10 );
			remove_filter( 'wcms_settings_label', [ self::class, 'filter_settings_label' ], 10 );
		}
	}

	public static function filter_settings_value( $value, $key ) {
		if ( ! empty( self::$override_stack ) ) {
			$current = end( self::$override_stack );
			if ( isset( $current[ $key ] ) ) {
				return $current[ $key ];
			}
		}
		return $value;
	}

	public static function filter_settings_label( $value, $key ) {
		if ( ! empty( self::$override_stack ) ) {
			$current = end( self::$override_stack );
			$prefixed = 'label_' . $key;
			if ( isset( $current[ $prefixed ] ) ) {
				return $current[ $prefixed ];
			}
		}
		return $value;
	}

	private static function resolve_gcid( $color ) {
		if ( empty( $color ) || strpos( $color, 'gcid-' ) !== 0 ) {
			return $color;
		}
		$global_colors = GlobalData::get_global_colors();
		return $global_colors[ $color ]['color'] ?? $color;
	}

	private static function css_color( $color ) {
		if ( empty( $color ) ) {
			return '';
		}
		if ( strpos( $color, 'gcid-' ) === 0 ) {
			return 'var(--' . $color . ')';
		}
		return $color;
	}

	private static function inline_styles( $overrides ) {
		$css = '';
		if ( ! empty( $overrides['primary_color'] ) ) {
			$color = esc_attr( self::css_color( $overrides['primary_color'] ) );
			$css  .= ".wcms-calculator h3{color:{$color};}";
			$css  .= ".wcms-summary-label{color:{$color};}";
		}
		if ( ! empty( $overrides['accent_color'] ) ) {
			$color = esc_attr( self::css_color( $overrides['accent_color'] ) );
			$css  .= ".wcms-total-row .wcms-summary-value{color:{$color};}";
			$css  .= ".wcms-preview-btn{background:{$color};border-color:{$color};}";
			$css  .= ".wcms-spinner{border-top-color:{$color};}";
			$css  .= ".wcms-summary-action .wcms-add-to-cart{background:{$color};border-color:{$color};}";
		}
		if ( ! empty( $overrides['canvas_bg'] ) ) {
			$color = esc_attr( self::css_color( $overrides['canvas_bg'] ) );
			$css  .= ".wcms-calculator{background:{$color};}";
		}
		if ( ! empty( $overrides['custom_css'] ) ) {
			$css .= "\n" . wp_strip_all_tags( $overrides['custom_css'] );
		}
		return $css;
	}

	private static function placeholder( $message = '' ) {
		$message = $message ?: 'WCMS Product Calculator';
		return '<div class="wcms-divi-placeholder"><p>' . esc_html( $message ) . '</p></div>';
	}
}
