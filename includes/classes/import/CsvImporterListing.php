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

use PosternoImportExport\Import\Controllers\Listing;
use WP_Error;
use Exception;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CSV Listing importer class.
 */
class CsvImporterListing extends AbstractImporter {

	/**
	 * Type of importer used for filters.
	 *
	 * @var string
	 */
	public $type = 'listing';

	/**
	 * Tracks current row being parsed.
	 *
	 * @var integer
	 */
	protected $parsing_raw_data_index = 0;

	/**
	 * Get formatting callback.
	 *
	 * @return array
	 */
	protected function get_formating_callback() {

		/**
		 * Columns not mentioned here will get parsed with 'pno_clean'.
		 * column_name => callback.
		 */
		$default_formatting = array(
			'id'                => 'intval',
			'description'       => [ $this, 'parse_description_field' ],
			'short_description' => [ $this, 'parse_description_field' ],
			'featured_image'    => [ $this, 'parse_images_field' ],
			'published'         => [ $this, 'parse_date_field' ],
			'expires'           => [ $this, 'parse_date_field' ],
			'featured'          => [ $this, 'parse_bool_field' ],
			'opening_hours'     => [ $this, 'parse_json_field' ],
			'latitude'          => [ $this, 'parse_float_field' ],
			'longitude'         => [ $this, 'parse_float_field' ],
			'gallery_images'    => [ $this, 'parse_images_field' ],
		);

		$additional_mappings = $this->get_carbon_fields_mappings();

		$data_formatting = array_merge( $default_formatting, $additional_mappings );

		/**
		 * Match special column names.
		 */
		$regex_match_data_formatting = array(
			'/meta:*/' => 'wp_kses_post', // Allow some HTML in meta fields.
		);

		$callbacks = array();

		// Figure out the parse function for each column.
		foreach ( $this->get_mapped_keys() as $index => $heading ) {
			$callback = 'pno_clean';

			if ( isset( $data_formatting[ $heading ] ) ) {
				$callback = $data_formatting[ $heading ];
			} else {
				foreach ( $regex_match_data_formatting as $regex => $callback ) {
					if ( preg_match( $regex, $heading ) ) {
						$callback = $callback;
						break;
					}
				}
			}

			$callbacks[] = $callback;
		}

		return apply_filters( 'posterno_listing_importer_formatting_callbacks', $callbacks, $this );
	}

	/**
	 * Get mappings for custom fields.
	 *
	 * @return array
	 */
	private function get_carbon_fields_mappings() {

		$mappings = [];

		$repo = \Carbon_Fields\Carbon_Fields::resolve( 'container_repository' );

		$fields_to_skip = pno_get_carbon_listings_fields_to_skip();

		foreach ( $repo->get_containers() as $container ) {
			if ( $container->get_id() === 'carbon_fields_container_pno_listings_settings' ) {
				if ( ! empty( $container->get_fields() ) && is_array( $container->get_fields() ) ) {
					foreach ( $container->get_fields() as $field ) {
						if ( in_array( $field->get_base_name(), $fields_to_skip ) ) {
							continue;
						}
						switch ( $field->get_type() ) {
							case 'textarea':
							case 'rich_text':
								$mappings[ $field->get_base_name() ] = [ $this, 'parse_description_field' ];
								break;
							case 'multiselect':
							case 'set':
								$mappings[ $field->get_base_name() ] = [ $this, 'parse_comma_field' ];
								break;
							case 'checkbox':
								$mappings[ $field->get_base_name() ] = [ $this, 'parse_bool_field' ];
								break;
						}
					}
				}
			}
		}

		return $mappings;

	}

	/**
	 * Process importer.
	 *
	 * Do not import listings with IDs that already exist if option
	 * update existing is false, and likewise, if updating listings, do not
	 * process rows which do not exist if an ID is provided.
	 *
	 * @return array
	 */
	public function import() {
		$this->start_time = time();
		$index            = 0;
		$update_existing  = $this->params['update_existing'];
		$data             = array(
			'imported' => array(),
			'failed'   => array(),
			'updated'  => array(),
			'skipped'  => array(),
		);

		foreach ( $this->parsed_data as $parsed_data_key => $parsed_data ) {

			do_action( 'posterno_listing_import_before_import', $parsed_data );

			$id        = isset( $parsed_data['id'] ) ? absint( $parsed_data['id'] ) : 0;
			$id_exists = false;

			if ( $id ) {
				$listing_status = get_post_status( $id );
				$id_exists      = $listing_status && 'publish' === $listing_status;
			}

			if ( $id_exists && ! $update_existing ) {
				$data['skipped'][] = new WP_Error(
					'posterno_listing_importer_error',
					esc_html__( 'A listing with this ID already exists.', 'posterno' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && get_post_type( $id ) !== 'listings' ) {
				$data['skipped'][] = new WP_Error(
					'posterno_listing_importer_error',
					esc_html__( 'ID found but post type not matching.', 'posterno' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && ( $id ) && ! $id_exists ) {
				$data['skipped'][] = new WP_Error(
					'posterno_listing_importer_error',
					esc_html__( 'No matching listing exists to update.', 'posterno' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			$result = $this->process_item( $parsed_data );

			if ( is_wp_error( $result ) ) {
				$result->add_data( array( 'row' => $this->get_row_id( $parsed_data ) ) );
				$data['failed'][] = $result;
			} elseif ( $result['updated'] ) {
				$data['updated'][] = $result['id'];
			} else {
				$data['imported'][] = $result['id'];
			}

			$index ++;

			if ( $this->params['prevent_timeouts'] && ( $this->time_exceeded() || $this->memory_exceeded() ) ) {
				$this->file_position = $this->file_positions[ $index ];
				break;
			}
		}

		return $data;
	}

	/**
	 * Process a single item and save.
	 *
	 * @throws Exception If item cannot be processed.
	 * @param  array $data Raw CSV data.
	 * @return array|WP_Error
	 */
	protected function process_item( $data ) {
		try {

			do_action( 'posterno_listing_import_before_process_item', $data );

			$id       = false;
			$updating = false;

			// Grab details.
			$title = isset( $data['title'] ) ? $data['title'] : 'null';

			error_log( print_r( $data, true ) );

			return array(
				'id'      => $id,
				'updated' => $updating,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'posterno_listing_importer_error', $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

}
