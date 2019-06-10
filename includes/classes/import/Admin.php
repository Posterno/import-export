<?php
/**
 * Importer Admin initialization.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

namespace PosternoImportExport\Import;

use PosternoImportExport\Import\Controllers\Schema;

/**
 * Hook into the admin panel.
 */
class Admin {

	/**
	 * Array of importer IDs.
	 *
	 * @var string[]
	 */
	protected $importers = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! $this->import_allowed() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'add_to_menus' ) );
		add_action( 'admin_head', array( $this, 'hide_from_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'pno_tools_import', [ $this, 'register_tool' ], 20 );

		add_action( 'wp_ajax_posterno_do_ajax_schema_import', array( $this, 'do_ajax_schema_import' ) );

		// Register Posterno importers.
		$this->importers['schema_importer'] = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => esc_html__( 'Schema Import', 'posterno' ),
			'capability' => 'manage_options',
			'url'        => admin_url( 'edit.php?post_type=listings&page=schema_importer' ),
			'callback'   => array( $this, 'schema_importer' ),
		);
	}

	/**
	 * Return true if Posterno imports are allowed for current user, false otherwise.
	 *
	 * @return bool Whether current user can perform imports.
	 */
	protected function import_allowed() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Add menu items for our custom importers.
	 */
	public function add_to_menus() {
		foreach ( $this->importers as $id => $importer ) {
			add_submenu_page( $importer['menu'], $importer['name'], $importer['name'], $importer['capability'], $id, $importer['callback'] );
		}
	}

	/**
	 * Hide menu items from view so the pages exist, but the menu items do not.
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
	 * Register tool within Posterno tools.
	 *
	 * @return void
	 */
	public function register_tool() {

		include PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/import-tool.php';

	}

	/**
	 * Register importer scripts.
	 */
	public function admin_scripts() {

		wp_register_script( 'pno-schema-import', PNO_PLUGIN_URL . 'vendor/posterno/import-export/dist/js/pno-schema-import.js', array( 'jquery' ), PNO_VERSION, true );

	}

	/**
	 * The schema importer.
	 */
	public function schema_importer() {
		if ( defined( 'WP_LOAD_IMPORTERS' ) ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=listings&page=schema_importer' ) );
			exit;
		}

		$importer = new Schema();
		$importer->dispatch();
	}

	/**
	 * Ajax callback for importing one batch of products from a CSV.
	 */
	public function do_ajax_schema_import() {
		global $wpdb;

		check_ajax_referer( 'pno-schema-import', 'security' );

		if ( ! $this->import_allowed() || ! isset( $_POST['file'] ) ) { // PHPCS: input var ok.
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to import.', 'posterno' ) ) );
		}

		$file   = pno_clean( wp_unslash( $_POST['file'] ) ); // PHPCS: input var ok.
		$params = array(
			'delimiter'       => ! empty( $_POST['delimiter'] ) ? pno_clean( wp_unslash( $_POST['delimiter'] ) ) : ',', // PHPCS: input var ok.
			'start_pos'       => isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0, // PHPCS: input var ok.
			'mapping'         => isset( $_POST['mapping'] ) ? (array) pno_clean( wp_unslash( $_POST['mapping'] ) ) : array(), // PHPCS: input var ok.
			'update_existing' => isset( $_POST['update_existing'] ) ? (bool) $_POST['update_existing'] : false, // PHPCS: input var ok.
			'lines'           => apply_filters( 'posterno_schema_import_batch_size', 30 ),
			'parse'           => true,
		);

		// Log failures.
		if ( 0 !== $params['start_pos'] ) {
			$error_log = array_filter( (array) get_user_option( 'schema_import_error_log' ) );
		} else {
			$error_log = array();
		}

		$importer         = Schema::get_importer( $file, $params );
		$results          = $importer->import();
		$percent_complete = $importer->get_percent_complete();
		$error_log        = array_merge( $error_log, $results['failed'], $results['skipped'] );

		update_user_option( get_current_user_id(), 'schema_import_error_log', $error_log );

		if ( 100 === $percent_complete ) {
			// @codingStandardsIgnoreStart.
			$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_original_id' ) );
			$wpdb->delete( $wpdb->posts, array(
				'post_type'   => 'product',
				'post_status' => 'importing',
			) );
			// @codingStandardsIgnoreEnd.

			$wpdb->query(
				"
				DELETE {$wpdb->postmeta}.* FROM {$wpdb->postmeta}
				LEFT JOIN {$wpdb->posts} wp ON wp.ID = {$wpdb->postmeta}.post_id
				WHERE wp.ID IS NULL
			"
			);

			// Send success.
			wp_send_json_success(
				array(
					'position'   => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( array( 'nonce' => wp_create_nonce( 'schema-csv' ) ), admin_url( 'edit.php?post_type=listings&page=schema_importer&step=done' ) ),
					'imported'   => count( $results['imported'] ),
					'failed'     => count( $results['failed'] ),
					'updated'    => count( $results['updated'] ),
					'skipped'    => count( $results['skipped'] ),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'position'   => $importer->get_file_position(),
					'percentage' => $percent_complete,
					'imported'   => count( $results['imported'] ),
					'failed'     => count( $results['failed'] ),
					'updated'    => count( $results['updated'] ),
					'skipped'    => count( $results['skipped'] ),
				)
			);
		}
	}

}
