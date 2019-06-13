<?php
/**
 * Importers controllers base class.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

namespace PosternoImportExport\Import\Controllers;

use WP_Error;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Base controller class holds common functionalities.
 */
class BaseController {

	/**
	 * The path to the current file.
	 *
	 * @var string
	 */
	protected $file = '';

	/**
	 * The current import step.
	 *
	 * @var string
	 */
	protected $step = '';

	/**
	 * Progress steps.
	 *
	 * @var array
	 */
	protected $steps = array();

	/**
	 * Errors.
	 *
	 * @var array
	 */
	protected $errors = array();

	/**
	 * The current delimiter for the file being read.
	 *
	 * @var string
	 */
	protected $delimiter = ',';

	/**
	 * Whether to use previous mapping selections.
	 *
	 * @var bool
	 */
	protected $map_preferences = false;

	/**
	 * Whether to skip existing schemas.
	 *
	 * @var bool
	 */
	protected $update_existing = false;

	/**
	 * Importer type name, used for hooks.
	 *
	 * @var string
	 */
	public $type = '';

	/**
	 * Holds the title of the page.
	 *
	 * @var string
	 */
	public $page_title = '';

	/**
	 * Holds the description displayed within the upload form.
	 *
	 * @var string
	 */
	public $page_description = '';

	/**
	 * Holds the update label option.
	 *
	 * @var string
	 */
	public $page_update_label = '';

	/**
	 * Holds the update label checkbox description.
	 *
	 * @var string
	 */
	public $page_update_description = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$default_steps = array(
			'upload'  => array(
				'name'    => __( 'Upload CSV file', 'posterno' ),
				'view'    => array( $this, 'upload_form' ),
				'handler' => array( $this, 'upload_form_handler' ),
			),
			'mapping' => array(
				'name'    => __( 'Column mapping', 'posterno' ),
				'view'    => array( $this, 'mapping_form' ),
				'handler' => '',
			),
			'import'  => array(
				'name'    => __( 'Import', 'posterno' ),
				'view'    => array( $this, 'import' ),
				'handler' => '',
			),
			'done'    => array(
				'name'    => __( 'Done!', 'posterno' ),
				'view'    => array( $this, 'done' ),
				'handler' => '',
			),
		);

		$this->steps = apply_filters( "posterno_{$this->type}_csv_importer_steps", $default_steps );

		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification
		$this->step            = isset( $_REQUEST['step'] ) ? sanitize_key( $_REQUEST['step'] ) : current( array_keys( $this->steps ) );
		$this->file            = isset( $_REQUEST['file'] ) ? pno_clean( wp_unslash( $_REQUEST['file'] ) ) : '';
		$this->update_existing = isset( $_REQUEST['update_existing'] ) ? (bool) $_REQUEST['update_existing'] : false;
		$this->delimiter       = ! empty( $_REQUEST['delimiter'] ) ? pno_clean( wp_unslash( $_REQUEST['delimiter'] ) ) : ',';
		$this->map_preferences = isset( $_REQUEST['map_preferences'] ) ? (bool) $_REQUEST['map_preferences'] : false;
		// phpcs:enable

		if ( $this->map_preferences ) {
			add_filter( "posterno_csv_{$this->type}_import_mapped_columns", array( $this, 'auto_map_user_preferences' ), 9999 );
		}
	}

	/**
	 * Check whether a file is a valid CSV file.
	 *
	 * @param string $file File path.
	 * @param bool   $check_path Whether to also check the file is located in a valid location (Default: true).
	 * @return bool
	 */
	public static function is_file_valid_csv( $file, $check_path = true ) {
		if ( $check_path && apply_filters( 'posterno_csv_importer_check_import_file_path', true ) && false !== stripos( $file, '://' ) ) {
			return false;
		}

		$valid_filetypes = self::get_valid_csv_filetypes();
		$filetype        = wp_check_filetype( $file, $valid_filetypes );
		if ( in_array( $filetype['type'], $valid_filetypes, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get all the valid filetypes for a CSV file.
	 *
	 * @return array
	 */
	protected static function get_valid_csv_filetypes() {
		return apply_filters(
			'posterno_csv_import_valid_filetypes',
			array(
				'csv' => 'text/csv',
				'txt' => 'text/plain',
			)
		);
	}

	/**
	 * Get the URL for the next step's screen.
	 *
	 * @param string $step  slug (default: current step).
	 * @return string       URL for next step if a next step exists.
	 *                      Admin URL if it's the last step.
	 *                      Empty string on failure.
	 */
	public function get_next_step_link( $step = '' ) {
		if ( ! $step ) {
			$step = $this->step;
		}

		$keys = array_keys( $this->steps );

		if ( end( $keys ) === $step ) {
			return admin_url();
		}

		$step_index = array_search( $step, $keys, true );

		if ( false === $step_index ) {
			return '';
		}

		$params = array(
			'step'            => $keys[ $step_index + 1 ],
			'file'            => str_replace( DIRECTORY_SEPARATOR, '/', $this->file ),
			'delimiter'       => $this->delimiter,
			'update_existing' => $this->update_existing,
			'map_preferences' => $this->map_preferences,
			'_wpnonce'        => wp_create_nonce( 'posterno-csv-importer' ), // wp_nonce_url() escapes & to &amp; breaking redirects.
		);

		return add_query_arg( $params );
	}

	/**
	 * Output header view.
	 */
	protected function output_header() {
		include PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/html-csv-import-header.php';
	}

	/**
	 * Output steps view.
	 */
	protected function output_steps() {
		include PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/html-csv-import-steps.php';
	}

	/**
	 * Output footer view.
	 */
	protected function output_footer() {
		include PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/html-csv-import-footer.php';
	}

	/**
	 * Add error message.
	 *
	 * @param string $message Error message.
	 * @param array  $actions List of actions with 'url' and 'label'.
	 */
	protected function add_error( $message, $actions = array() ) {
		$this->errors[] = array(
			'message' => $message,
			'actions' => $actions,
		);
	}

	/**
	 * Add error message.
	 */
	protected function output_errors() {
		if ( ! $this->errors ) {
			return;
		}

		foreach ( $this->errors as $error ) {
			echo '<div class="error inline">';
			echo '<p>' . esc_html( $error['message'] ) . '</p>';

			if ( ! empty( $error['actions'] ) ) {
				echo '<p>';
				foreach ( $error['actions'] as $action ) {
					echo '<a class="button button-primary" href="' . esc_url( $action['url'] ) . '">' . esc_html( $action['label'] ) . '</a> ';
				}
				echo '</p>';
			}
			echo '</div>';
		}
	}

	/**
	 * Trigger the handler on page load.
	 *
	 * @return void
	 */
	public function trigger_step_handler() {
		// phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
		if ( ! empty( $_POST['save_step'] ) && ! empty( $this->steps[ $this->step ]['handler'] ) ) {
			call_user_func( $this->steps[ $this->step ]['handler'], $this );
		}
	}

	/**
	 * Dispatch current step and show correct view.
	 *
	 * @return void
	 */
	public function dispatch() {
		$this->trigger_step_handler();
		$this->output_header();
		$this->output_steps();
		$this->output_errors();
		call_user_func( $this->steps[ $this->step ]['view'], $this );
		$this->output_footer();
	}

	/**
	 * Handle the upload form and store options.
	 */
	public function upload_form_handler() {
		check_admin_referer( 'posterno-csv-importer' );

		$file = $this->handle_upload();

		if ( is_wp_error( $file ) ) {
			$this->add_error( $file->get_error_message() );
			return;
		} else {
			$this->file = $file;
		}

		wp_safe_redirect( esc_url_raw( $this->get_next_step_link() ) );
		exit;
	}

	/**
	 * Output information about the uploading process.
	 */
	protected function upload_form() {
		$bytes      = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size       = size_format( $bytes );
		$upload_dir = wp_upload_dir();

		include PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/html-csv-import-form.php';
	}

	/**
	 * Handles the CSV upload and initial parsing of the file to prepare for
	 * displaying author import options.
	 *
	 * @return string|WP_Error
	 */
	public function handle_upload() {
		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification -- Nonce already verified in WC_Product_CSV_Importer_Controller::upload_form_handler()
		$file_url = isset( $_POST['file_url'] ) ? pno_clean( wp_unslash( $_POST['file_url'] ) ) : '';

		if ( empty( $file_url ) ) {
			if ( ! isset( $_FILES['import'] ) ) {
				return new WP_Error( 'posterno_csv_importer_upload_file_empty', __( 'File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your php.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.', 'posterno' ) );
			}

			if ( ! self::is_file_valid_csv( pno_clean( wp_unslash( $_FILES['import']['name'] ) ), false ) ) {
				return new WP_Error( 'posterno_csv_importer_upload_file_invalid', __( 'Invalid file type. The importer supports CSV and TXT file formats.', 'posterno' ) );
			}

			$overrides = array(
				'test_form' => false,
				'mimes'     => self::get_valid_csv_filetypes(),
			);
			$import    = $_FILES['import']; // WPCS: sanitization ok, input var ok.
			$upload    = wp_handle_upload( $import, $overrides );

			if ( isset( $upload['error'] ) ) {
				return new WP_Error( 'posterno_csv_importer_upload_error', $upload['error'] );
			}

			// Construct the object array.
			$object = array(
				'post_title'     => basename( $upload['file'] ),
				'post_content'   => $upload['url'],
				'post_mime_type' => $upload['type'],
				'guid'           => $upload['url'],
				'context'        => 'import',
				'post_status'    => 'private',
			);

			// Save the data.
			$id = wp_insert_attachment( $object, $upload['file'] );

			/*
			 * Schedule a cleanup for one day from now in case of failed
			 * import or missing wp_import_cleanup() call.
			 */
			wp_schedule_single_event( time() + DAY_IN_SECONDS, 'importer_scheduled_cleanup', array( $id ) );

			return $upload['file'];
		} elseif ( file_exists( ABSPATH . $file_url ) ) {
			if ( ! self::is_file_valid_csv( ABSPATH . $file_url ) ) {
				return new WP_Error( 'posterno_csv_importer_upload_file_invalid', __( 'Invalid file type. The importer supports CSV and TXT file formats.', 'posterno' ) );
			}

			return ABSPATH . $file_url;
		}
		// phpcs:enable

		return new WP_Error( 'posterno_csv_importer_upload_invalid_file', __( 'Please upload or provide the link to a valid CSV file.', 'posterno' ) );
	}

	/**
	 * Mapping step.
	 */
	protected function mapping_form() {
		$args = array(
			'lines'     => 1,
			'delimiter' => $this->delimiter,
		);

		$importer     = self::get_importer( $this->file, $args );
		$headers      = $importer->get_raw_keys();
		$mapped_items = $this->auto_map_columns( $headers );
		$sample       = current( $importer->get_raw_data() );

		if ( empty( $sample ) ) {
			$this->add_error(
				__( 'The file is empty or using a different encoding than UTF-8, please try again with a new file.', 'posterno' ),
				array(
					array(
						'url'   => admin_url( "edit.php?post_type=listings&page={$this->type}_importer" ),
						'label' => __( 'Upload a new file', 'posterno' ),
					),
				)
			);

			// Force output the errors in the same page.
			$this->output_errors();
			return;
		}

		include_once PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/html-csv-import-mapping.php';
	}

	/**
	 * Import the file if it exists and is valid.
	 */
	public function import() {
		if ( ! self::is_file_valid_csv( $this->file ) ) {
			$this->add_error( __( 'Invalid file type. The importer supports CSV and TXT file formats.', 'posterno' ) );
			$this->output_errors();
			return;
		}

		if ( ! is_file( $this->file ) ) {
			$this->add_error( __( 'The file does not exist, please try again.', 'posterno' ) );
			$this->output_errors();
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification -- Nonce already verified in WC_Admin_Importers::do_ajax_schema_import()
		if ( ! empty( $_POST['map_from'] ) && ! empty( $_POST['map_to'] ) ) {
			$mapping_from = pno_clean( wp_unslash( $_POST['map_from'] ) );
			$mapping_to   = pno_clean( wp_unslash( $_POST['map_to'] ) );

			// Save mapping preferences for future imports.
			update_user_option( get_current_user_id(), "posterno_{$this->type}_import_mapping", $mapping_to );
		} else {
			wp_redirect( esc_url_raw( $this->get_next_step_link( 'upload' ) ) );
			exit;
		}
		// phpcs:enable

		wp_localize_script(
			'pno-data-import',
			'pno_data_import_params',
			array(
				'import_nonce'    => wp_create_nonce( "pno-{$this->type}-import" ),
				'mapping'         => array(
					'from' => $mapping_from,
					'to'   => $mapping_to,
				),
				'file'            => $this->file,
				'update_existing' => $this->update_existing,
				'delimiter'       => $this->delimiter,
				'type'            => $this->type,
			)
		);
		wp_enqueue_script( 'pno-data-import' );

		include_once PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/html-csv-import-progress.php';
	}

	/**
	 * Done step.
	 */
	protected function done() {
		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification
		$imported = isset( $_GET['items-imported'] ) ? absint( $_GET['items-imported'] ) : 0;
		$updated  = isset( $_GET['items-updated'] ) ? absint( $_GET['items-updated'] ) : 0;
		$failed   = isset( $_GET['items-failed'] ) ? absint( $_GET['items-failed'] ) : 0;
		$skipped  = isset( $_GET['items-skipped'] ) ? absint( $_GET['items-skipped'] ) : 0;
		$errors   = array_filter( (array) get_user_option( 'schema_import_error_log' ) );
		// phpcs:enable

		include_once PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/html-csv-import-done.php';
	}

	/**
	 * Columns to normalize.
	 *
	 * @param  array $columns List of columns names and keys.
	 * @return array
	 */
	protected function normalize_columns_names( $columns ) {
		$normalized = array();

		foreach ( $columns as $key => $value ) {
			$normalized[ strtolower( $key ) ] = $value;
		}

		return $normalized;
	}

	/**
	 * Map columns using the user's lastest import mappings.
	 *
	 * @param  array $headers Header columns.
	 * @return array
	 */
	public function auto_map_user_preferences( $headers ) {
		$mapping_preferences = get_user_option( "posterno_{$this->type}_import_mapping" );

		if ( ! empty( $mapping_preferences ) && is_array( $mapping_preferences ) ) {
			return $mapping_preferences;
		}

		return $headers;
	}

	/**
	 * Sanitize special column name regex.
	 *
	 * @param  string $value Raw special column name.
	 * @return string
	 */
	protected function sanitize_special_column_name_regex( $value ) {
		return '/' . str_replace( array( '%d', '%s' ), '(.*)', trim( quotemeta( $value ) ) ) . '/';
	}

	/**
	 * Get special columns.
	 *
	 * @param  array $columns Raw special columns.
	 * @return array
	 */
	protected function get_special_columns( $columns ) {
		$formatted = array();

		foreach ( $columns as $key => $value ) {
			$regex = $this->sanitize_special_column_name_regex( $key );

			$formatted[ $regex ] = $value;
		}

		return $formatted;
	}

}
