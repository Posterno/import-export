<?php
/**
 * Schema importer controller.
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
 * Schema importer controller - handles file upload and forms in admin.
 */
class Schema extends BaseController {

	/**
	 * Import type name used for filter.s
	 *
	 * @var string
	 */
	public $type = 'schema';

	/**
	 * Get things started.
	 */
	public function __construct() {
		parent::__construct();

		$this->page_title              = esc_html__( 'Import schemas from a CSV File' );
		$this->page_description        = esc_html__( 'This tool allows you to import (or merge) schema data to your webiste from a CSV file.' );
		$this->page_update_label       = esc_html__( 'Update existing schemas' );
		$this->page_update_description = esc_html__( 'Existing schemas that match by ID will be updated. Schemas that do not exist will be skipped.' );
	}

	/**
	 * Get importer instance.
	 *
	 * @param  string $file File to import.
	 * @param  array  $args Importer arguments.
	 * @return \PosternoImportExport\Import\CsvImporterSchema
	 */
	public static function get_importer( $file, $args = array() ) {
		$importer_class = apply_filters( 'posterno_schema_csv_importer_class', '\PosternoImportExport\Import\CsvImporterSchema' );
		$args           = apply_filters( 'posterno_schema_csv_importer_args', $args, $importer_class );
		return new $importer_class( $file, $args );
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
	 * Auto map column names.
	 *
	 * @param  array $raw_headers Raw header columns.
	 * @param  bool  $num_indexes If should use numbers or raw header columns as indexes.
	 * @return array
	 */
	protected function auto_map_columns( $raw_headers, $num_indexes = true ) {
		include PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/mappings/mappings.php';

		/*
		 * @hooked pno_importer_generic_mappings - 10
		 * @hooked pno_importer_wordpress_mappings - 10
		 * @hooked pno_importer_default_english_mappings - 100
		 */
		$default_columns = $this->normalize_columns_names(
			apply_filters(
				'posterno_csv_schema_import_mapping_default_columns',
				array(
					__( 'ID', 'posterno' )                => 'id',
					__( 'Name', 'posterno' )              => 'name',
					__( 'Short description', 'posterno' ) => 'short_description',
					__( 'Description', 'posterno' )       => 'description',
					__( 'Position', 'posterno' )          => 'menu_order',
				)
			)
		);

		$special_columns = $this->get_special_columns(
			$this->normalize_columns_names(
				apply_filters(
					'posterno_csv_schema_import_mapping_special_columns',
					array(
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
		$options = array(
			'id'                => __( 'ID', 'posterno' ),
			'type'              => __( 'Type', 'posterno' ),
			'name'              => __( 'Name', 'posterno' ),
			'short_description' => __( 'Short description', 'posterno' ),
			'description'       => __( 'Description', 'posterno' ),
			'meta:' . $meta     => __( 'Import as meta', 'posterno' ),
			'menu_order'        => __( 'Position', 'posterno' ),
		);

		return apply_filters( 'posterno_csv_schema_import_mapping_options', $options, $item );
	}
}
