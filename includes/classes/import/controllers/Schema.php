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
