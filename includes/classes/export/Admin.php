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

use PosternoImportExport\Export\CsvSchemasExporter;

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
		// add_action( 'admin_head', array( $this, 'hide_from_menus' ) );
		add_action( 'admin_init', array( $this, 'download_schemas_export_file' ) );
		add_action( 'wp_ajax_posterno_do_ajax_schemas_export', array( $this, 'do_ajax_schemas_export' ) );

	}

	/**
	 * Displays content of the exporter.
	 *
	 * @return void
	 */
	public function schemas_exporter() {

		require_once PNO_PLUGIN_DIR . '/vendor/posterno/import-export/resources/views/html-schemas-export.php';

	}

	public function do_ajax_schemas_export() {
		check_ajax_referer( 'pno-schemas-export', 'security' );

		if ( ! $this->export_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to export products.', 'posterno' ) ) );
		}

		$step     = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1; // WPCS: input var ok, sanitization ok.
		$exporter = new CsvSchemasExporter();

		if ( ! empty( $_POST['columns'] ) ) { // WPCS: input var ok.
			$exporter->set_column_names( wp_unslash( $_POST['columns'] ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['filename'] ) ) { // WPCS: input var ok.
			$exporter->set_filename( wp_unslash( $_POST['filename'] ) ); // WPCS: input var ok, sanitization ok.
		}

		$exporter->set_page( $step );
		$exporter->generate_file();

		$query_args = apply_filters(
			'posterno_export_get_ajax_query_args',
			array(
				'nonce'    => wp_create_nonce( 'schemas-csv' ),
				'action'   => 'download_schemas_csv',
				'filename' => $exporter->get_filename(),
			)
		);
		if ( 100 === $exporter->get_percent_complete() ) {
			wp_send_json_success(
				array(
					'step'       => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( $query_args, admin_url( 'edit.php?post_type=listings&page=schemas_exporter' ) ),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'step'       => ++$step,
					'percentage' => $exporter->get_percent_complete(),
					'columns'    => $exporter->get_column_names(),
				)
			);
		}
	}

	/**
	 * Download export file.
	 *
	 * @return void
	 */
	public function download_schemas_export_file() {
		if ( isset( $_GET['action'], $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'schemas-csv' ) && 'download_schemas_csv' === wp_unslash( $_GET['action'] ) ) { // WPCS: input var ok, sanitization ok.
			$exporter = new CsvSchemasExporter();
			if ( ! empty( $_GET['filename'] ) ) { // WPCS: input var ok.
				$exporter->set_filename( wp_unslash( $_GET['filename'] ) ); // WPCS: input var ok, sanitization ok.
			}
			$exporter->export();
		}
	}

}
