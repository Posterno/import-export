<?php
/**
 * Hooks the component to WordPress.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

namespace PosternoImportExport\Export;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Hook the exporters to the admin panel.
 */
class Admin {

	/**
	 * Array of exporter IDs.
	 *
	 * @var string[]
	 */
	protected $exporters = array();

	/**
	 * Get things started.
	 */
	public function __construct() {

		if ( ! $this->export_allowed() ) {
			return;
		}

		$this->hook();

		// Register exporters.
		$this->exporters['schemas_exporter'] = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => esc_html__( 'Schemas Export' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'schemas_exporter' ),
		);

	}

	/**
	 * Return true if export is allowed for current user, false otherwise.
	 *
	 * @return bool Whether current user can perform export.
	 */
	protected function export_allowed() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Add all exporters to the admin menu.
	 *
	 * @return void
	 */
	public function add_to_menus() {
		foreach ( $this->exporters as $id => $exporter ) {
			add_submenu_page( $exporter['menu'], $exporter['name'], $exporter['name'], $exporter['capability'], $id, $exporter['callback'] );
		}
	}

	/**
	 * Hook into WP.
	 *
	 * @return void
	 */
	public function hook() {

		add_action( 'admin_menu', array( $this, 'add_to_menus' ) );
		//add_action( 'admin_head', array( $this, 'hide_from_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		//add_action( 'admin_init', array( $this, 'download_export_file' ) );
		//add_action( 'wp_ajax_woocommerce_do_ajax_product_export', array( $this, 'do_ajax_product_export' ) );

	}

	/**
	 * Load assets.
	 *
	 * @return void
	 */
	public function admin_scripts() {

		wp_register_style( 'pno-export', PNO_PLUGIN_URL . '/vendor/posterno/import-export/dist/css/screen.css', false, PNO_VERSION );
		wp_register_script( 'pno-schemas-export', PNO_PLUGIN_URL . '/vendor/posterno/import-export/dist/js/pno-schemas-export.js', array( 'jquery' ), PNO_VERSION );

		wp_localize_script(
			'pno-schemas-export',
			'pno_schemas_export_params',
			array(
				'export_nonce' => wp_create_nonce( 'pno-schemas-export' ),
			)
		);
	}

	/**
	 * Displays content of the exporter.
	 *
	 * @return void
	 */
	public function schemas_exporter() {

		require_once PNO_PLUGIN_DIR . '/vendor/posterno/import-export/resources/views/html-schemas-export.php';

	}

	/**
	 * Download export file.
	 *
	 * @return void
	 */
	public function download_export_file() {

	}

}
