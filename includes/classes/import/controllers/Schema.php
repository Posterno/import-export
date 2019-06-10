<?php
/**
 * Schema importer controller class.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

namespace PosternoImportExport\Import\Controllers;

use PosternoImportExport\Import\Controllers\MainController;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Schema importer.
 */
class Schema extends MainController {

	/**
	 * Import type.
	 *
	 * @var string
	 */
	public $type = 'schema';

	/**
	 * Get importer instance.
	 *
	 * @param  string $file File to import.
	 * @param  array  $args Importer arguments.
	 * @return PosternoImportExport\Import\SchemaImporter
	 */
	public static function get_importer( $file, $args = array() ) {
		$importer_class = apply_filters( 'posterno_schema_csv_importer_class', '\\PosternoImportExport\\Import\\SchemaImporter' );
		$args           = apply_filters( 'posterno_schema_csv_importer_args', $args, $importer_class );
		return new $importer_class( $file, $args );
	}

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

		$this->steps = apply_filters( 'posterno_schema_csv_importer_steps', $default_steps );

		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification
		$this->step            = isset( $_REQUEST['step'] ) ? sanitize_key( $_REQUEST['step'] ) : current( array_keys( $this->steps ) );
		$this->file            = isset( $_REQUEST['file'] ) ? pno_clean( wp_unslash( $_REQUEST['file'] ) ) : '';
		$this->update_existing = isset( $_REQUEST['update_existing'] ) ? (bool) $_REQUEST['update_existing'] : false;
		$this->delimiter       = ! empty( $_REQUEST['delimiter'] ) ? pno_clean( wp_unslash( $_REQUEST['delimiter'] ) ) : ',';
		$this->map_preferences = isset( $_REQUEST['map_preferences'] ) ? (bool) $_REQUEST['map_preferences'] : false;
		// phpcs:enable

		if ( $this->map_preferences ) {
			add_filter( 'posterno_csv_schema_import_mapped_columns', array( $this, 'auto_map_user_preferences' ), 9999 );
		}
	}

	/**
	 * Output information about the uploading process.
	 */
	protected function upload_form() {
		$bytes      = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size       = size_format( $bytes );
		$upload_dir = wp_upload_dir();

		include PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/html-schema-csv-import-form.php';
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
						'url'   => admin_url( 'edit.php?post_type=product&page=product_importer' ),
						'label' => __( 'Upload a new file', 'posterno' ),
					),
				)
			);

			// Force output the errors in the same page.
			$this->output_errors();
			return;
		}

		include_once PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/html-csv-schema-import-mapping.php';
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

		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification -- Nonce already verified in WC_Admin_Importers::do_ajax_product_import()
		if ( ! empty( $_POST['map_from'] ) && ! empty( $_POST['map_to'] ) ) {
			$mapping_from = pno_clean( wp_unslash( $_POST['map_from'] ) );
			$mapping_to   = pno_clean( wp_unslash( $_POST['map_to'] ) );

			// Save mapping preferences for future imports.
			update_user_option( get_current_user_id(), 'posterno_schema_import_mapping', $mapping_to );
		} else {
			wp_safe_redirect( esc_url_raw( $this->get_next_step_link( 'upload' ) ) );
			exit;
		}
		// phpcs:enable

		wp_localize_script(
			'pno-schema-import',
			'pno_schema_import_params',
			array(
				'import_nonce'    => wp_create_nonce( 'pno-schema-import' ),
				'mapping'         => array(
					'from' => $mapping_from,
					'to'   => $mapping_to,
				),
				'file'            => $this->file,
				'update_existing' => $this->update_existing,
				'delimiter'       => $this->delimiter,
			)
		);
		wp_enqueue_script( 'pno-schema-import' );

		include_once PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/html-csv-import-progress.php';
	}

	/**
	 * Done step.
	 */
	protected function done() {
		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification
		$imported = isset( $_GET['products-imported'] ) ? absint( $_GET['products-imported'] ) : 0;
		$updated  = isset( $_GET['products-updated'] ) ? absint( $_GET['products-updated'] ) : 0;
		$failed   = isset( $_GET['products-failed'] ) ? absint( $_GET['products-failed'] ) : 0;
		$skipped  = isset( $_GET['products-skipped'] ) ? absint( $_GET['products-skipped'] ) : 0;
		$errors   = array_filter( (array) get_user_option( 'product_import_error_log' ) );
		// phpcs:enable

		include_once PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/html-csv-import-done.php';
	}

	/**
	 * Auto map column names.
	 *
	 * @param  array $raw_headers Raw header columns.
	 * @param  bool  $num_indexes If should use numbers or raw header columns as indexes.
	 * @return array
	 */
	protected function auto_map_columns( $raw_headers, $num_indexes = true ) {
		include PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/mappings/mappings.php';

		$default_columns = $this->normalize_columns_names(
			apply_filters(
				'posterno_csv_schema_import_mapping_default_columns',
				array(
					__( 'ID', 'posterno' )                => 'id',
					__( 'Name', 'posterno' )              => 'name',
					__( 'Short description', 'posterno' ) => 'short_description',
					__( 'Description', 'posterno' )       => 'description',
				)
			)
		);

		$special_columns = $this->get_special_columns(
			$this->normalize_columns_names(
				apply_filters(
					'posterno_csv_schema_import_mapping_special_columns',
					array(
						/* translators: %d: Meta number */
						__( 'Meta: %s', 'posterno' ) => 'meta:',
					)
				)
			)
		);

		$headers = array();
		foreach ( $raw_headers as $key => $field ) {
			$field             = strtolower( $field );
			$index             = $num_indexes ? $key : $field;
			$headers[ $index ] = $field;

			if ( isset( $default_columns[ $field ] ) ) {
				$headers[ $index ] = $default_columns[ $field ];
			} else {
				foreach ( $special_columns as $regex => $special_key ) {
					if ( preg_match( $regex, $field, $matches ) ) {
						$headers[ $index ] = $special_key . $matches[1];
						break;
					}
				}
			}
		}

		return apply_filters( 'posterno_csv_schema_import_mapped_columns', $headers, $raw_headers );
	}

	/**
	 * Get mapping options.
	 *
	 * @param  string $item Item name.
	 * @return array
	 */
	protected function get_mapping_options( $item = '' ) {
		// Get index for special column names.
		$index = $item;

		if ( preg_match( '/\d+/', $item, $matches ) ) {
			$index = $matches[0];
		}

		// Properly format for meta field.
		$meta = str_replace( 'meta:', '', $item );

		// Available options.
		$weight_unit    = get_option( 'posterno_weight_unit' );
		$dimension_unit = get_option( 'posterno_dimension_unit' );
		$options        = array(
			'id'                => __( 'ID', 'posterno' ),
			'type'              => __( 'Type', 'posterno' ),
			'sku'               => __( 'SKU', 'posterno' ),
			'name'              => __( 'Name', 'posterno' ),
			'short_description' => __( 'Short description', 'posterno' ),
			'description'       => __( 'Description', 'posterno' ),
			'meta:' . $meta     => __( 'Import as meta', 'posterno' ),
			'menu_order'        => __( 'Position', 'posterno' ),
		);

		return apply_filters( 'posterno_csv_schema_import_mapping_options', $options, $item );
	}

}
