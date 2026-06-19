<?php
defined( 'ABSPATH' ) || exit;

class WCMS_Divi_Integration {

	public function __construct() {
		add_action( 'divi_module_library_modules_dependency_tree', [ $this, 'register_dependency' ] );
		add_action( 'divi_visual_builder_assets_before_enqueue_scripts', [ $this, 'enqueue_vb_assets' ] );
	}

	public function register_dependency( $dependency_tree ) {
		require_once WCMS_PLUGIN_DIR . 'includes/class-divi-module.php';
		$dependency_tree->add_dependency( new WCMS_Divi_Module() );
	}

	public function enqueue_vb_assets() {
		if ( ! function_exists( 'et_core_is_fb_enabled' ) || ! et_core_is_fb_enabled() ) {
			return;
		}

		if ( ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() ) {
			return;
		}

		$js_path = WCMS_PLUGIN_DIR . 'visual-builder/build/wcms-divi.js';
		if ( ! file_exists( $js_path ) ) {
			return;
		}

		\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
			[
				'name'    => 'wcms-divi-visual-builder',
				'version' => WCMS_VERSION,
				'script'  => [
					'src'                => WCMS_PLUGIN_URL . 'visual-builder/build/wcms-divi.js',
					'deps'               => [
						'react',
						'jquery',
						'divi-module-library',
						'wp-hooks',
						'divi-rest',
					],
					'enqueue_top_window' => false,
					'enqueue_app_window' => true,
				],
			]
		);

		wp_enqueue_style(
			'wcms-divi-style',
			WCMS_PLUGIN_URL . 'assets/css/divi.css',
			[],
			WCMS_VERSION
		);
	}
}
