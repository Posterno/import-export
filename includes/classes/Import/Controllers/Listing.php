<?php
/**
 * Listing importer controller.
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
 * Listing importer controller - handles file upload and forms in admin.
 */
class Listing extends BaseController {

	/**
	 * Import type name used for filters.
	 *
	 * @var string
	 */
	public $type = 'listing';

	/**
	 * Get things started.
	 */
	public function __construct() {
		parent::__construct();

		$this->page_title              = esc_html__( 'Import listings from a CSV File', 'posterno' );
		$this->page_description        = esc_html__( 'This tool allows you to import (or merge) listings to your webiste from a CSV file.', 'posterno' );
		$this->page_update_label       = esc_html__( 'Update existing listings', 'posterno' );
		$this->page_update_description = esc_html__( 'Existing listings that match by ID will be updated. Listings that do not exist will be skipped.', 'posterno' );
		$this->page_done_url           = admin_url( 'edit.php?post_type=listings' );
		$this->page_item_label         = esc_html__( 'Listings', 'posterno' );
		$this->page_url                = admin_url( 'edit.php?post_type=listings&page=listing_importer' );
	}

	/**
	 * Get importer instance.
	 *
	 * @param  string $file File to import.
	 * @param  array  $args Importer arguments.
	 * @return \PosternoImportExport\Import\CsvImporterListing
	 */
	public static function get_importer( $file, $args = array() ) {
		$importer_class = apply_filters( 'posterno_listing_csv_importer_class', '\PosternoImportExport\Import\CsvImporterListing' );
		$args           = apply_filters( 'posterno_listing_csv_importer_args', $args, $importer_class );
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

		if ( ! $this->update_existing ) {
			unset( $headers[0] );
		}

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

		$initial_columns = $this->normalize_columns_names(
			apply_filters(
				'posterno_csv_listing_import_mapping_default_columns',
				array(
					esc_html__( 'ID', 'posterno' )        => 'id',
					esc_html__( 'Listing title', 'posterno' ) => 'title',
					esc_html__( 'Description', 'posterno' ) => 'description',
					esc_html__( 'Short description', 'posterno' ) => 'short_description',
					esc_html__( 'Featured image', 'posterno' ) => 'featured_image',
					esc_html__( 'Publish date', 'posterno' ) => 'published',
					esc_html__( 'Last modified', 'posterno' ) => 'last_modified',
					esc_html__( 'Status', 'posterno' )    => 'status',
					esc_html__( 'Expiry date', 'posterno' ) => 'expires',
					esc_html__( 'Featured', 'posterno' )  => 'featured',
					esc_html__( 'Opening hours', 'posterno' ) => 'opening_hours',
					esc_html__( 'Latitude', 'posterno' )  => 'latitude',
					esc_html__( 'Longitude', 'posterno' ) => 'longitude',
					esc_html__( 'Address', 'posterno' )   => 'address',
					esc_html__( 'Gallery images', 'posterno' ) => 'gallery',
					esc_html__( 'Author ID', 'posterno' ) => 'author_id',
					esc_html__( 'Author username', 'posterno' ) => 'author_username',
					esc_html__( 'Author email', 'posterno' ) => 'author_email',
				)
			)
		);

		$taxonomies = array_flip( pno_get_registered_listings_taxonomies() );
		$fields     = array_flip( pno_get_cb_listings_fields() );

		$default_columns = array_merge( $initial_columns, $taxonomies, $fields );

		$special_columns = $this->get_special_columns(
			$this->normalize_columns_names(
				apply_filters(
					'posterno_csv_listing_import_mapping_special_columns',
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

		return apply_filters( 'posterno_csv_listing_import_mapped_columns', $headers, $raw_headers );
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
		$default_options = array(
			'id'                => esc_html__( 'ID', 'posterno' ),
			'title'             => esc_html__( 'Listing title', 'posterno' ),
			'description'       => esc_html__( 'Description', 'posterno' ),
			'short_description' => esc_html__( 'Short description', 'posterno' ),
			'featured_image'    => esc_html__( 'Featured image', 'posterno' ),
			'published'         => esc_html__( 'Publish date', 'posterno' ),
			'last_modified'     => esc_html__( 'Last modified', 'posterno' ),
			'status'            => esc_html__( 'Status', 'posterno' ),
			'expires'           => esc_html__( 'Expiry date', 'posterno' ),
			'featured'          => esc_html__( 'Featured', 'posterno' ),
			'opening_hours'     => esc_html__( 'Opening hours', 'posterno' ),
			'latitude'          => esc_html__( 'Latitude', 'posterno' ),
			'longitude'         => esc_html__( 'Longitude', 'posterno' ),
			'address'           => esc_html__( 'Address', 'posterno' ),
			'gallery'           => esc_html__( 'Gallery images', 'posterno' ),
			'author_id'         => esc_html__( 'Author ID', 'posterno' ),
			'author_username'   => esc_html__( 'Author username', 'posterno' ),
			'author_email'      => esc_html__( 'Author email', 'posterno' ),
			'meta:' . $meta     => esc_html__( 'Import as meta data', 'posterno' ),
		);

		$options = array_merge( $default_options, pno_get_registered_listings_taxonomies(), pno_get_cb_listings_fields() );

		return apply_filters( 'posterno_csv_listing_import_mapping_options', $options, $item );
	}

}
