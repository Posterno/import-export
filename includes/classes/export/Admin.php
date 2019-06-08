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
use PosternoImportExport\Export\CsvEmailsExporter;
use PosternoImportExport\Export\CsvListingsFieldsExporter;
use PosternoImportExport\Export\CsvProfileFieldsExporter;
use PosternoImportExport\Export\CsvRegistrationFieldsExporter;
use PosternoImportExport\Export\CsvTaxonomyExporter;

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
		$this->exporters['schemas_exporter']             = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => esc_html__( 'Listings schemas' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'schemas_exporter' ),
			'url'        => admin_url( 'edit.php?post_type=listings&page=schemas_exporter' ),
		);
		$this->exporters['emails_exporter']              = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => esc_html__( 'Emails' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'emails_exporter' ),
			'url'        => admin_url( 'edit.php?post_type=listings&page=emails_exporter' ),
		);
		$this->exporters['listings_fields_exporter']     = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => esc_html__( 'Listings custom fields' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'listings_fields_exporter' ),
			'url'        => admin_url( 'edit.php?post_type=listings&page=listings_fields_exporter' ),
		);
		$this->exporters['profile_fields_exporter']      = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => esc_html__( 'Profile custom fields' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'profile_fields_exporter' ),
			'url'        => admin_url( 'edit.php?post_type=listings&page=profile_fields_exporter' ),
		);
		$this->exporters['registration_fields_exporter'] = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => esc_html__( 'Registration custom fields' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'registration_fields_exporter' ),
			'url'        => admin_url( 'edit.php?post_type=listings&page=registration_fields_exporter' ),
		);
		$this->exporters['taxonomy_exporter']            = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => esc_html__( 'Taxonomy terms' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'taxonomy_exporter' ),
			'url'        => admin_url( 'edit.php?post_type=listings&page=taxonomy_exporter' ),
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
		add_action( 'admin_head', array( $this, 'hide_from_menus' ) );
		add_action( 'pno_tools_export', [ $this, 'register_exporters_list' ], 20 );

		add_action( 'admin_init', array( $this, 'download_schemas_export_file' ) );
		add_action( 'wp_ajax_posterno_do_ajax_schemas_export', array( $this, 'do_ajax_schemas_export' ) );

		add_action( 'admin_init', array( $this, 'download_emails_export_file' ) );
		add_action( 'wp_ajax_posterno_do_ajax_emails_export', array( $this, 'do_ajax_emails_export' ) );

		add_action( 'admin_init', array( $this, 'download_listings_fields_export_file' ) );
		add_action( 'wp_ajax_posterno_do_ajax_listings_fields_export', array( $this, 'do_ajax_listings_fields_export' ) );

		add_action( 'admin_init', array( $this, 'download_profile_fields_export_file' ) );
		add_action( 'wp_ajax_posterno_do_ajax_profile_fields_export', array( $this, 'do_ajax_profile_fields_export' ) );

		add_action( 'admin_init', array( $this, 'download_registration_fields_export_file' ) );
		add_action( 'wp_ajax_posterno_do_ajax_registration_fields_export', array( $this, 'do_ajax_registration_fields_export' ) );

		add_action( 'admin_init', array( $this, 'download_taxonomy_export_file' ) );
		add_action( 'wp_ajax_posterno_do_ajax_taxonomy_export', array( $this, 'do_ajax_taxonomy_export' ) );

	}

	/**
	 * Hide all exporters from the main menu.
	 *
	 * @return void
	 */
	public function hide_from_menus() {
		global $submenu;
		foreach ( $this->exporters as $id => $exporter ) {
			if ( isset( $submenu[ $exporter['menu'] ] ) ) {
				foreach ( $submenu[ $exporter['menu'] ] as $key => $menu ) {
					if ( $id === $menu[2] ) {
						unset( $submenu[ $exporter['menu'] ][ $key ] );
					}
				}
			}
		}
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
	 * Export schemas.
	 *
	 * @return void
	 */
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

	/*---------------------EMAILS EXPORTER-------------------------*/

	/**
	 * Displays content of the exporter.
	 *
	 * @return void
	 */
	public function emails_exporter() {
		require_once PNO_PLUGIN_DIR . '/vendor/posterno/import-export/resources/views/html-emails-export.php';
	}

	/**
	 * Export emails.
	 *
	 * @return void
	 */
	public function do_ajax_emails_export() {
		check_ajax_referer( 'pno-emails-export', 'security' );

		if ( ! $this->export_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to export emails.', 'posterno' ) ) );
		}

		$step     = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1; // WPCS: input var ok, sanitization ok.
		$exporter = new CsvEmailsExporter();

		if ( ! empty( $_POST['columns'] ) ) { // WPCS: input var ok.
			$exporter->set_column_names( wp_unslash( $_POST['columns'] ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['filename'] ) ) { // WPCS: input var ok.
			$exporter->set_filename( wp_unslash( $_POST['filename'] ) ); // WPCS: input var ok, sanitization ok.
		}

		$exporter->set_page( $step );
		$exporter->generate_file();

		$query_args = apply_filters(
			'posterno_emails_export_get_ajax_query_args',
			array(
				'nonce'    => wp_create_nonce( 'emails-csv' ),
				'action'   => 'download_emails_csv',
				'filename' => $exporter->get_filename(),
			)
		);
		if ( 100 === $exporter->get_percent_complete() ) {
			wp_send_json_success(
				array(
					'step'       => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( $query_args, admin_url( 'edit.php?post_type=listings&page=emails_exporter' ) ),
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
	public function download_emails_export_file() {
		if ( isset( $_GET['action'], $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'emails-csv' ) && 'download_emails_csv' === wp_unslash( $_GET['action'] ) ) { // WPCS: input var ok, sanitization ok.
			$exporter = new CsvEmailsExporter();
			if ( ! empty( $_GET['filename'] ) ) { // WPCS: input var ok.
				$exporter->set_filename( wp_unslash( $_GET['filename'] ) ); // WPCS: input var ok, sanitization ok.
			}
			$exporter->export();
		}
	}

	/*---------------------LISTINGS CUSTOM FIELDS EXPORTER-------------------------*/

	/**
	 * Displays content of the exporter.
	 *
	 * @return void
	 */
	public function listings_fields_exporter() {
		require_once PNO_PLUGIN_DIR . '/vendor/posterno/import-export/resources/views/html-listings-fields-export.php';
	}

	/**
	 * Export listings custom fields.
	 *
	 * @return void
	 */
	public function do_ajax_listings_fields_export() {
		check_ajax_referer( 'pno-listings-fields-export', 'security' );

		if ( ! $this->export_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to export listings fields.', 'posterno' ) ) );
		}

		$step     = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1; // WPCS: input var ok, sanitization ok.
		$exporter = new CsvListingsFieldsExporter();

		if ( ! empty( $_POST['columns'] ) ) { // WPCS: input var ok.
			$exporter->set_column_names( wp_unslash( $_POST['columns'] ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['filename'] ) ) { // WPCS: input var ok.
			$exporter->set_filename( wp_unslash( $_POST['filename'] ) ); // WPCS: input var ok, sanitization ok.
		}

		$exporter->set_page( $step );
		$exporter->generate_file();

		$query_args = apply_filters(
			'posterno_listings_fields_export_get_ajax_query_args',
			array(
				'nonce'    => wp_create_nonce( 'listings-fields-csv' ),
				'action'   => 'download_listings_fields_csv',
				'filename' => $exporter->get_filename(),
			)
		);
		if ( 100 === $exporter->get_percent_complete() ) {
			wp_send_json_success(
				array(
					'step'       => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( $query_args, admin_url( 'edit.php?post_type=listings&page=listings_fields_exporter' ) ),
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
	public function download_listings_fields_export_file() {
		if ( isset( $_GET['action'], $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'listings-fields-csv' ) && 'download_listings_fields_csv' === wp_unslash( $_GET['action'] ) ) { // WPCS: input var ok, sanitization ok.
			$exporter = new CsvListingsFieldsExporter();
			if ( ! empty( $_GET['filename'] ) ) { // WPCS: input var ok.
				$exporter->set_filename( wp_unslash( $_GET['filename'] ) ); // WPCS: input var ok, sanitization ok.
			}
			$exporter->export();
		}
	}

	/*---------------------PROFILE CUSTOM FIELDS EXPORTER-------------------------*/

	/**
	 * Displays content of the exporter.
	 *
	 * @return void
	 */
	public function profile_fields_exporter() {
		require_once PNO_PLUGIN_DIR . '/vendor/posterno/import-export/resources/views/html-profile-fields-export.php';
	}

	/**
	 * Export profile custom fields.
	 *
	 * @return void
	 */
	public function do_ajax_profile_fields_export() {
		check_ajax_referer( 'pno-profile-fields-export', 'security' );

		if ( ! $this->export_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to export profile fields.', 'posterno' ) ) );
		}

		$step     = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1; // WPCS: input var ok, sanitization ok.
		$exporter = new CsvProfileFieldsExporter();

		if ( ! empty( $_POST['columns'] ) ) { // WPCS: input var ok.
			$exporter->set_column_names( wp_unslash( $_POST['columns'] ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['filename'] ) ) { // WPCS: input var ok.
			$exporter->set_filename( wp_unslash( $_POST['filename'] ) ); // WPCS: input var ok, sanitization ok.
		}

		$exporter->set_page( $step );
		$exporter->generate_file();

		$query_args = apply_filters(
			'posterno_profile_fields_export_get_ajax_query_args',
			array(
				'nonce'    => wp_create_nonce( 'profile-fields-csv' ),
				'action'   => 'download_profile_fields_csv',
				'filename' => $exporter->get_filename(),
			)
		);
		if ( 100 === $exporter->get_percent_complete() ) {
			wp_send_json_success(
				array(
					'step'       => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( $query_args, admin_url( 'edit.php?post_type=listings&page=profile_fields_exporter' ) ),
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
	public function download_profile_fields_export_file() {
		if ( isset( $_GET['action'], $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'profile-fields-csv' ) && 'download_profile_fields_csv' === wp_unslash( $_GET['action'] ) ) { // WPCS: input var ok, sanitization ok.
			$exporter = new CsvProfileFieldsExporter();
			if ( ! empty( $_GET['filename'] ) ) { // WPCS: input var ok.
				$exporter->set_filename( wp_unslash( $_GET['filename'] ) ); // WPCS: input var ok, sanitization ok.
			}
			$exporter->export();
		}
	}

	/*---------------------REGISTRATION CUSTOM FIELDS EXPORTER-------------------------*/

	/**
	 * Displays content of the exporter.
	 *
	 * @return void
	 */
	public function registration_fields_exporter() {
		require_once PNO_PLUGIN_DIR . '/vendor/posterno/import-export/resources/views/html-registration-fields-export.php';
	}

	/**
	 * Export registration custom fields.
	 *
	 * @return void
	 */
	public function do_ajax_registration_fields_export() {
		check_ajax_referer( 'pno-registration-fields-export', 'security' );

		if ( ! $this->export_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to export registration fields.', 'posterno' ) ) );
		}

		$step     = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1; // WPCS: input var ok, sanitization ok.
		$exporter = new CsvRegistrationFieldsExporter();

		if ( ! empty( $_POST['columns'] ) ) { // WPCS: input var ok.
			$exporter->set_column_names( wp_unslash( $_POST['columns'] ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['filename'] ) ) { // WPCS: input var ok.
			$exporter->set_filename( wp_unslash( $_POST['filename'] ) ); // WPCS: input var ok, sanitization ok.
		}

		$exporter->set_page( $step );
		$exporter->generate_file();

		$query_args = apply_filters(
			'posterno_registration_fields_export_get_ajax_query_args',
			array(
				'nonce'    => wp_create_nonce( 'registration-fields-csv' ),
				'action'   => 'download_registration_fields_csv',
				'filename' => $exporter->get_filename(),
			)
		);
		if ( 100 === $exporter->get_percent_complete() ) {
			wp_send_json_success(
				array(
					'step'       => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( $query_args, admin_url( 'edit.php?post_type=listings&page=registration_fields_exporter' ) ),
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
	public function download_registration_fields_export_file() {
		if ( isset( $_GET['action'], $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'registration-fields-csv' ) && 'download_registration_fields_csv' === wp_unslash( $_GET['action'] ) ) { // WPCS: input var ok, sanitization ok.
			$exporter = new CsvRegistrationFieldsExporter();
			if ( ! empty( $_GET['filename'] ) ) { // WPCS: input var ok.
				$exporter->set_filename( wp_unslash( $_GET['filename'] ) ); // WPCS: input var ok, sanitization ok.
			}
			$exporter->export();
		}
	}

	/*---------------------TAXONOMY EXPORTER-------------------------*/

	/**
	 * Displays content of the exporter.
	 *
	 * @return void
	 */
	public function taxonomy_exporter() {
		require_once PNO_PLUGIN_DIR . '/vendor/posterno/import-export/resources/views/html-taxonomy-export.php';
	}

	/**
	 * Export registration custom fields.
	 *
	 * @return void
	 */
	public function do_ajax_taxonomy_export() {
		check_ajax_referer( 'pno-taxonomy-export', 'security' );

		if ( ! $this->export_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to export taxonomy.', 'posterno' ) ) );
		}

		$step     = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1; // WPCS: input var ok, sanitization ok.
		$exporter = new CsvTaxonomyExporter();

		if ( ! empty( $_POST['columns'] ) ) { // WPCS: input var ok.
			$exporter->set_column_names( wp_unslash( $_POST['columns'] ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['filename'] ) ) { // WPCS: input var ok.
			$exporter->set_filename( wp_unslash( $_POST['filename'] ) ); // WPCS: input var ok, sanitization ok.
		}

		if ( ! empty( $_POST['taxonomy_to_export'] ) ) { // WPCS: input var ok.
			$exporter->set_taxonomy_to_export( wp_unslash( $_POST['taxonomy_to_export'] ) ); // WPCS: input var ok, sanitization ok.
		}

		$exporter->set_page( $step );
		$exporter->generate_file();

		$query_args = apply_filters(
			'posterno_taxonomy_export_get_ajax_query_args',
			array(
				'nonce'    => wp_create_nonce( 'taxonomy-csv' ),
				'action'   => 'download_taxonomy_csv',
				'filename' => $exporter->get_filename(),
			)
		);
		if ( 100 === $exporter->get_percent_complete() ) {
			wp_send_json_success(
				array(
					'step'       => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( $query_args, admin_url( 'edit.php?post_type=listings&page=registration_fields_exporter' ) ),
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
	public function download_taxonomy_export_file() {
		if ( isset( $_GET['action'], $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'taxonomy-csv' ) && 'download_taxonomy_csv' === wp_unslash( $_GET['action'] ) ) { // WPCS: input var ok, sanitization ok.
			$exporter = new CsvTaxonomyExporter();
			if ( ! empty( $_GET['filename'] ) ) { // WPCS: input var ok.
				$exporter->set_filename( wp_unslash( $_GET['filename'] ) ); // WPCS: input var ok, sanitization ok.
			}
			$exporter->export();
		}
	}

	/**
	 * Display a list of exporters registered within the tools page.
	 *
	 * @return void
	 */
	public function register_exporters_list() {

		include PNO_PLUGIN_DIR . '/vendor/posterno/import-export/resources/views/export-tool.php';

	}

}
