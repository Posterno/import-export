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

use PosternoImportExport\Import\Controllers\BaseController;
use PosternoImportExport\Import\Controllers\Schema;
use PosternoImportExport\Import\Controllers\Email;
use PosternoImportExport\Import\Controllers\ListingsField;
use PosternoImportExport\Import\Controllers\ProfilesField;
use PosternoImportExport\Import\Controllers\RegistrationField;
use PosternoImportExport\Import\Controllers\Taxonomy;
use PosternoImportExport\Import\Controllers\Listing;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Creates import sections within the admin panel.
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

		add_action( 'admin_init', [ $this, 'dispatcher' ] );
		add_action( 'admin_menu', array( $this, 'add_to_menus' ) );
		add_action( 'admin_head', array( $this, 'hide_from_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'pno_tools_import', [ $this, 'register_importers_list' ], 20 );

		add_action( 'wp_ajax_posterno_do_ajax_schema_import', array( $this, 'do_ajax_schema_import' ) );
		add_action( 'wp_ajax_posterno_do_ajax_email_import', array( $this, 'do_ajax_email_import' ) );
		add_action( 'wp_ajax_posterno_do_ajax_listingsfield_import', array( $this, 'do_ajax_listingsfield_import' ) );
		add_action( 'wp_ajax_posterno_do_ajax_profilesfield_import', array( $this, 'do_ajax_profilesfield_import' ) );
		add_action( 'wp_ajax_posterno_do_ajax_registrationfield_import', array( $this, 'do_ajax_registrationfield_import' ) );
		add_action( 'wp_ajax_posterno_do_ajax_taxonomyterm_import', array( $this, 'do_ajax_taxonomyterm_import' ) );
		add_action( 'wp_ajax_posterno_do_ajax_listing_import', array( $this, 'do_ajax_listing_import' ) );

		// Register importers.
		$this->importers['schema_importer']            = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => __( 'Schema Import', 'posterno' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'schema_importer' ),
			'url'        => admin_url( 'edit.php?post_type=listings&page=schema_importer' ),
		);
		$this->importers['email_importer']             = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => __( 'Email Import', 'posterno' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'email_importer' ),
			'url'        => admin_url( 'edit.php?post_type=listings&page=email_importer' ),
		);
		$this->importers['listingsfield_importer']     = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => __( 'Listings fields Import', 'posterno' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'listingsfield_importer' ),
			'url'        => admin_url( 'edit.php?post_type=listings&page=listingsfield_importer' ),
		);
		$this->importers['profilesfield_importer']     = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => __( 'Profiles fields Import', 'posterno' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'profilesfield_importer' ),
			'url'        => admin_url( 'edit.php?post_type=listings&page=profilesfield_importer' ),
		);
		$this->importers['registrationfield_importer'] = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => __( 'Registration fields Import', 'posterno' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'registrationfield_importer' ),
			'url'        => admin_url( 'edit.php?post_type=listings&page=registrationfield_importer' ),
		);
		$this->importers['taxonomyterm_importer']      = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => __( 'Taxonomy Terms Import', 'posterno' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'taxonomyterm_importer' ),
			'url'        => admin_url( 'edit.php?post_type=listings&page=taxonomyterm_importer' ),
		);
		$this->importers['listing_importer']           = array(
			'menu'       => 'edit.php?post_type=listings',
			'name'       => __( 'Listings Import', 'posterno' ),
			'capability' => 'manage_options',
			'callback'   => array( $this, 'listing_importer' ),
			'url'        => admin_url( 'edit.php?post_type=listings&page=listing_importer' ),
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
	 * Creates the list of importers within the admin tools page.
	 *
	 * @return void
	 */
	public function register_importers_list() {
		include PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/import-tool.php';
	}

	/**
	 * Register importer scripts.
	 */
	public function admin_scripts() {
		wp_register_script( 'pno-data-import', PNO_PLUGIN_URL . 'vendor/posterno/import-export/dist/js/pno-import.js', array( 'jquery' ), PNO_VERSION, true );
	}

	/**
	 * Detect step handler on forms submission.
	 *
	 * @return void
	 */
	public function dispatcher() {
		$importer = new BaseController();
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-setup' ) {
			return;
		}
		$importer->trigger_step_handler();
	}

	/**
	 * The schema importer page.
	 */
	public function schema_importer() {
		$importer = new Schema();
		$importer->dispatch();
	}

	/**
	 * The email importer page.
	 */
	public function email_importer() {
		$importer = new Email();
		$importer->dispatch();
	}

	/**
	 * The listings fields importer page.
	 */
	public function listingsfield_importer() {
		$importer = new ListingsField();
		$importer->dispatch();
	}

	/**
	 * The profiles fields importer page.
	 */
	public function profilesfield_importer() {
		$importer = new ProfilesField();
		$importer->dispatch();
	}

	/**
	 * The registration fields importer page.
	 */
	public function registrationfield_importer() {
		$importer = new RegistrationField();
		$importer->dispatch();
	}

	/**
	 * The taxonomy importer page.
	 */
	public function taxonomyterm_importer() {
		$importer = new Taxonomy();
		$importer->dispatch();
	}

	/**
	 * The listings importer page.
	 */
	public function listing_importer() {
		$importer = new Listing();
		$importer->dispatch();
	}

	/**
	 * Ajax callback for importing one batch of schemas from a CSV.
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
			$this->delete_csv_file( $file );
			// @codingStandardsIgnoreStart.
			$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_original_id' ) );
			$wpdb->delete( $wpdb->posts, array(
				'post_type'   => 'schema',
				'post_status' => 'importing',
			) );

			$wpdb->query(
				"
				DELETE {$wpdb->postmeta}.* FROM {$wpdb->postmeta}
				LEFT JOIN {$wpdb->posts} wp ON wp.ID = {$wpdb->postmeta}.post_id
				WHERE wp.ID IS NULL
			"
			);
			// @codingStandardsIgnoreStart.
			$wpdb->query( "
				DELETE tr.* FROM {$wpdb->term_relationships} tr
				LEFT JOIN {$wpdb->posts} wp ON wp.ID = tr.object_id
				LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE wp.ID IS NULL
				AND tt.taxonomy IN ( '" . implode( "','", array_map( 'esc_sql', get_object_taxonomies( 'schema' ) ) ) . "' )
			" );
			// @codingStandardsIgnoreEnd.

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

	/**
	 * Ajax callback for importing one batch of schemas from a CSV.
	 */
	public function do_ajax_email_import() {
		global $wpdb;

		check_ajax_referer( 'pno-email-import', 'security' );

		if ( ! $this->import_allowed() || ! isset( $_POST['file'] ) ) { // PHPCS: input var ok.
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to import.', 'posterno' ) ) );
		}

		$file   = pno_clean( wp_unslash( $_POST['file'] ) ); // PHPCS: input var ok.
		$params = array(
			'delimiter'       => ! empty( $_POST['delimiter'] ) ? pno_clean( wp_unslash( $_POST['delimiter'] ) ) : ',', // PHPCS: input var ok.
			'start_pos'       => isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0, // PHPCS: input var ok.
			'mapping'         => isset( $_POST['mapping'] ) ? (array) pno_clean( wp_unslash( $_POST['mapping'] ) ) : array(), // PHPCS: input var ok.
			'update_existing' => isset( $_POST['update_existing'] ) ? (bool) $_POST['update_existing'] : false, // PHPCS: input var ok.
			'lines'           => apply_filters( 'posterno_email_import_batch_size', 30 ),
			'parse'           => true,
		);

		// Log failures.
		if ( 0 !== $params['start_pos'] ) {
			$error_log = array_filter( (array) get_user_option( 'email_import_error_log' ) );
		} else {
			$error_log = array();
		}

		$importer         = Email::get_importer( $file, $params );
		$results          = $importer->import();
		$percent_complete = $importer->get_percent_complete();
		$error_log        = array_merge( $error_log, $results['failed'], $results['skipped'] );

		update_user_option( get_current_user_id(), 'email_import_error_log', $error_log );

		if ( 100 === $percent_complete ) {
			$this->delete_csv_file( $file );
			// Send success.
			wp_send_json_success(
				array(
					'position'   => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( array( 'nonce' => wp_create_nonce( 'email-csv' ) ), admin_url( 'edit.php?post_type=listings&page=email_importer&step=done' ) ),
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

	/**
	 * Ajax callback for importing one batch of schemas from a CSV.
	 */
	public function do_ajax_listingsfield_import() {
		global $wpdb;

		check_ajax_referer( 'pno-listingsfield-import', 'security' );

		if ( ! $this->import_allowed() || ! isset( $_POST['file'] ) ) { // PHPCS: input var ok.
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to import.', 'posterno' ) ) );
		}

		$file   = pno_clean( wp_unslash( $_POST['file'] ) ); // PHPCS: input var ok.
		$params = array(
			'delimiter'       => ! empty( $_POST['delimiter'] ) ? pno_clean( wp_unslash( $_POST['delimiter'] ) ) : ',', // PHPCS: input var ok.
			'start_pos'       => isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0, // PHPCS: input var ok.
			'mapping'         => isset( $_POST['mapping'] ) ? (array) pno_clean( wp_unslash( $_POST['mapping'] ) ) : array(), // PHPCS: input var ok.
			'update_existing' => isset( $_POST['update_existing'] ) ? (bool) $_POST['update_existing'] : false, // PHPCS: input var ok.
			'lines'           => apply_filters( 'posterno_listingsfield_import_batch_size', 30 ),
			'parse'           => true,
		);

		// Log failures.
		if ( 0 !== $params['start_pos'] ) {
			$error_log = array_filter( (array) get_user_option( 'listingsfield_import_error_log' ) );
		} else {
			$error_log = array();
		}

		$importer         = ListingsField::get_importer( $file, $params );
		$results          = $importer->import();
		$percent_complete = $importer->get_percent_complete();
		$error_log        = array_merge( $error_log, $results['failed'], $results['skipped'] );

		update_user_option( get_current_user_id(), 'listingsfield_import_error_log', $error_log );

		if ( 100 === $percent_complete ) {

			\PNO\Cache\Helper::flush_all_fields_cache();

			$this->delete_csv_file( $file );

			// Send success.
			wp_send_json_success(
				array(
					'position'   => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( array( 'nonce' => wp_create_nonce( 'listingsfield-csv' ) ), admin_url( 'edit.php?post_type=listings&page=listingsfield_importer&step=done' ) ),
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

	/**
	 * Ajax callback for importing one batch of schemas from a CSV.
	 */
	public function do_ajax_profilesfield_import() {
		global $wpdb;

		check_ajax_referer( 'pno-profilesfield-import', 'security' );

		if ( ! $this->import_allowed() || ! isset( $_POST['file'] ) ) { // PHPCS: input var ok.
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to import.', 'posterno' ) ) );
		}

		$file   = pno_clean( wp_unslash( $_POST['file'] ) ); // PHPCS: input var ok.
		$params = array(
			'delimiter'       => ! empty( $_POST['delimiter'] ) ? pno_clean( wp_unslash( $_POST['delimiter'] ) ) : ',', // PHPCS: input var ok.
			'start_pos'       => isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0, // PHPCS: input var ok.
			'mapping'         => isset( $_POST['mapping'] ) ? (array) pno_clean( wp_unslash( $_POST['mapping'] ) ) : array(), // PHPCS: input var ok.
			'update_existing' => isset( $_POST['update_existing'] ) ? (bool) $_POST['update_existing'] : false, // PHPCS: input var ok.
			'lines'           => apply_filters( 'posterno_profilesfield_import_batch_size', 30 ),
			'parse'           => true,
		);

		// Log failures.
		if ( 0 !== $params['start_pos'] ) {
			$error_log = array_filter( (array) get_user_option( 'profilesfield_import_error_log' ) );
		} else {
			$error_log = array();
		}

		$importer         = ProfilesField::get_importer( $file, $params );
		$results          = $importer->import();
		$percent_complete = $importer->get_percent_complete();
		$error_log        = array_merge( $error_log, $results['failed'], $results['skipped'] );

		update_user_option( get_current_user_id(), 'profilesfield_import_error_log', $error_log );

		if ( 100 === $percent_complete ) {

			\PNO\Cache\Helper::flush_all_fields_cache();

			$this->delete_csv_file( $file );

			// Send success.
			wp_send_json_success(
				array(
					'position'   => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( array( 'nonce' => wp_create_nonce( 'profilesfield-csv' ) ), admin_url( 'edit.php?post_type=listings&page=profilesfield_importer&step=done' ) ),
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

	/**
	 * Ajax callback for importing one batch of schemas from a CSV.
	 */
	public function do_ajax_registrationfield_import() {
		global $wpdb;

		check_ajax_referer( 'pno-registrationfield-import', 'security' );

		if ( ! $this->import_allowed() || ! isset( $_POST['file'] ) ) { // PHPCS: input var ok.
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to import.', 'posterno' ) ) );
		}

		$file   = pno_clean( wp_unslash( $_POST['file'] ) ); // PHPCS: input var ok.
		$params = array(
			'delimiter'       => ! empty( $_POST['delimiter'] ) ? pno_clean( wp_unslash( $_POST['delimiter'] ) ) : ',', // PHPCS: input var ok.
			'start_pos'       => isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0, // PHPCS: input var ok.
			'mapping'         => isset( $_POST['mapping'] ) ? (array) pno_clean( wp_unslash( $_POST['mapping'] ) ) : array(), // PHPCS: input var ok.
			'update_existing' => isset( $_POST['update_existing'] ) ? (bool) $_POST['update_existing'] : false, // PHPCS: input var ok.
			'lines'           => apply_filters( 'posterno_registrationfield_import_batch_size', 30 ),
			'parse'           => true,
		);

		// Log failures.
		if ( 0 !== $params['start_pos'] ) {
			$error_log = array_filter( (array) get_user_option( 'registrationfield_import_error_log' ) );
		} else {
			$error_log = array();
		}

		$importer         = RegistrationField::get_importer( $file, $params );
		$results          = $importer->import();
		$percent_complete = $importer->get_percent_complete();
		$error_log        = array_merge( $error_log, $results['failed'], $results['skipped'] );

		update_user_option( get_current_user_id(), 'registrationfield_import_error_log', $error_log );

		if ( 100 === $percent_complete ) {

			\PNO\Cache\Helper::flush_all_fields_cache();

			$this->delete_csv_file( $file );

			// Send success.
			wp_send_json_success(
				array(
					'position'   => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( array( 'nonce' => wp_create_nonce( 'registrationfield-csv' ) ), admin_url( 'edit.php?post_type=listings&page=registrationfield_importer&step=done' ) ),
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

	/**
	 * Ajax callback for importing one batch of schemas from a CSV.
	 */
	public function do_ajax_taxonomyterm_import() {
		global $wpdb;

		check_ajax_referer( 'pno-taxonomyterm-import', 'security' );

		if ( ! $this->import_allowed() || ! isset( $_POST['file'] ) ) { // PHPCS: input var ok.
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to import.', 'posterno' ) ) );
		}

		$file   = pno_clean( wp_unslash( $_POST['file'] ) ); // PHPCS: input var ok.
		$params = array(
			'delimiter'       => ! empty( $_POST['delimiter'] ) ? pno_clean( wp_unslash( $_POST['delimiter'] ) ) : ',', // PHPCS: input var ok.
			'start_pos'       => isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0, // PHPCS: input var ok.
			'mapping'         => isset( $_POST['mapping'] ) ? (array) pno_clean( wp_unslash( $_POST['mapping'] ) ) : array(), // PHPCS: input var ok.
			'update_existing' => isset( $_POST['update_existing'] ) ? (bool) $_POST['update_existing'] : false, // PHPCS: input var ok.
			'lines'           => apply_filters( 'posterno_taxonomyterm_import_batch_size', 30 ),
			'parse'           => true,
		);

		// Log failures.
		if ( 0 !== $params['start_pos'] ) {
			$error_log = array_filter( (array) get_user_option( 'taxonomyterm_import_error_log' ) );
		} else {
			$error_log = array();
		}

		$importer         = Taxonomy::get_importer( $file, $params );
		$results          = $importer->import();
		$percent_complete = $importer->get_percent_complete();
		$error_log        = array_merge( $error_log, $results['failed'], $results['skipped'] );

		update_user_option( get_current_user_id(), 'taxonomyterm_import_error_log', $error_log );

		if ( 100 === $percent_complete ) {
			$this->delete_csv_file( $file );
			// Send success.
			wp_send_json_success(
				array(
					'position'   => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( array( 'nonce' => wp_create_nonce( 'taxonomyterm-csv' ) ), admin_url( 'edit.php?post_type=listings&page=taxonomyterm_importer&step=done' ) ),
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

	/**
	 * Ajax callback for importing one batch of schemas from a CSV.
	 */
	public function do_ajax_listing_import() {
		global $wpdb;

		check_ajax_referer( 'pno-listing-import', 'security' );

		if ( ! $this->import_allowed() || ! isset( $_POST['file'] ) ) { // PHPCS: input var ok.
			wp_send_json_error( array( 'message' => __( 'Insufficient privileges to import.', 'posterno' ) ) );
		}

		$file   = pno_clean( wp_unslash( $_POST['file'] ) ); // PHPCS: input var ok.
		$params = array(
			'delimiter'       => ! empty( $_POST['delimiter'] ) ? pno_clean( wp_unslash( $_POST['delimiter'] ) ) : ',', // PHPCS: input var ok.
			'start_pos'       => isset( $_POST['position'] ) ? absint( $_POST['position'] ) : 0, // PHPCS: input var ok.
			'mapping'         => isset( $_POST['mapping'] ) ? (array) pno_clean( wp_unslash( $_POST['mapping'] ) ) : array(), // PHPCS: input var ok.
			'update_existing' => isset( $_POST['update_existing'] ) ? (bool) $_POST['update_existing'] : false, // PHPCS: input var ok.
			'lines'           => apply_filters( 'posterno_listing_import_batch_size', 30 ),
			'parse'           => true,
		);

		// Log failures.
		if ( 0 !== $params['start_pos'] ) {
			$error_log = array_filter( (array) get_user_option( 'listing_import_error_log' ) );
		} else {
			$error_log = array();
		}

		$importer         = Listing::get_importer( $file, $params );
		$results          = $importer->import();
		$percent_complete = $importer->get_percent_complete();
		$error_log        = array_merge( $error_log, $results['failed'], $results['skipped'] );

		update_user_option( get_current_user_id(), 'listing_import_error_log', $error_log );

		if ( 100 === $percent_complete ) {
			$this->delete_csv_file( $file );
			// Send success.
			wp_send_json_success(
				array(
					'position'   => 'done',
					'percentage' => 100,
					'url'        => add_query_arg( array( 'nonce' => wp_create_nonce( 'listing-csv' ) ), admin_url( 'edit.php?post_type=listings&page=listing_importer&step=done' ) ),
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

	/**
	 * Delete csv file.
	 *
	 * @param string $file file path.
	 * @return void
	 */
	private function delete_csv_file( $file ) {

		if ( file_exists( $file ) && pno_starts_with( $file, WP_CONTENT_DIR ) ) {
			wp_delete_file( $file );
		}

	}

}
