<?php
/**
 * Hooks the component to WordPress.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

namespace PosternoImportExport\Import;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Hook to the admin panel.
 */
class Admin {

	/**
	 * List of registered importers.
	 *
	 * @var array
	 */
	public $importers = [];

	/**
	 * Get things started.
	 */
	public function __construct() {

		if ( ! $this->export_allowed() ) {
			return;
		}

		$this->importers['schemas_importer'] = array(
			'menu'             => 'edit.php?post_type=listings',
			'name'             => esc_html__( 'Import listings schemas', 'posterno' ),
			'capability'       => 'manage_options',
			'callback'         => array( $this, 'importer_page' ),
			'url'              => admin_url( 'edit.php?post_type=listings&page=schemas_importer' ),
			'page_title'       => esc_html__( 'Import listings schemas' ),
			'page_description' => esc_html__( 'This tool allows you to import listings schemas from a CSV file.' ),
		);

		$this->hook();

	}

	/**
	 * Return true if import is allowed for current user, false otherwise.
	 *
	 * @return bool Whether current user can perform import.
	 */
	protected function export_allowed() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Add all importers to the admin menu.
	 *
	 * @return void
	 */
	public function add_to_menus() {
		foreach ( $this->importers as $id => $importer ) {
			add_submenu_page( $importer['menu'], $importer['name'], $importer['name'], $importer['capability'], $id, $importer['callback'] );
		}
	}

	/**
	 * Hook into WP.
	 *
	 * @return void
	 */
	public function hook() {

		add_action( 'admin_menu', array( $this, 'add_to_menus' ) );
		add_action( 'admin_head', array( $this, 'hide_from_menus' ) );
		add_action( 'pno_tools_import', [ $this, 'register_importers_list' ], 20 );

	}

	/**
	 * Hide all importers from the main menu.
	 *
	 * @return void
	 */
	public function hide_from_menus() {
		global $submenu;
		foreach ( $this->importers as $id => $importer ) {
			if ( isset( $submenu[ $importer['menu'] ] ) ) {
				foreach ( $submenu[ $importer['menu'] ] as $key => $menu ) {
					if ( $id === $menu[2] ) {
						unset( $submenu[ $importer['menu'] ][ $key ] );
					}
				}
			}
		}
	}

	/**
	 * Get the currently activate importer.
	 *
	 * @return strign
	 */
	public function get_current_importer() {

		$screen   = get_current_screen();
		$importer = false;

		if ( $screen->id === 'listings_page_schemas_importer' ) {
			$importer = 'schemas_importer';
		}

		return $importer;

	}

	/**
	 * Get title of the currently active importer.
	 *
	 * @return string
	 */
	public function get_importer_title() {
		return $this->importers[ $this->get_current_importer() ]['page_title'];
	}

	/**
	 * Get description of the currently active importer.
	 *
	 * @return string
	 */
	public function get_importer_description() {
		return $this->importers[ $this->get_current_importer() ]['page_description'];
	}

	/**
	 * Display content for the importers.
	 *
	 * @return void
	 */
	public function importer_page() {

		$title       = $this->get_importer_title();
		$description = $this->get_importer_description();

		include PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/html-import-page.php';

	}

	/**
	 * Register importers into the tools page.
	 *
	 * @return void
	 */
	public function register_importers_list() {
		include PNO_PLUGIN_DIR . '/vendor/posterno/import-export/resources/views/import-tool.php';
	}

}
