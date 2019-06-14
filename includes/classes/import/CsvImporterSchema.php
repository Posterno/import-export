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

use PosternoImportExport\Import\Controllers\Schema;
use WP_Error;
use Exception;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CSV Schema importer class.
 */
class CsvImporterSchema extends AbstractImporter {

	/**
	 * Type of importer used for filters.
	 *
	 * @var string
	 */
	public $type = 'schema';

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
		$data_formatting = array(
			'id'            => 'intval',
			'name'          => [ $this, 'parse_skip_field' ],
			'code'          => [ $this, 'parse_json_field' ],
			'listing_types' => [ $this, 'parse_listing_types_field' ],
		);

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

		return apply_filters( 'posterno_schema_importer_formatting_callbacks', $callbacks, $this );
	}

	/**
	 * Process importer.
	 *
	 * Do not import schemas with IDs that already exist if option
	 * update existing is false, and likewise, if updating schemas, do not
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

			do_action( 'posterno_schema_import_before_import', $parsed_data );

			$id        = isset( $parsed_data['id'] ) ? absint( $parsed_data['id'] ) : 0;
			$id_exists = false;

			if ( $id ) {
				$schema_status = get_post_status( $id );
				$id_exists     = $schema_status && 'publish' === $schema_status;
			}

			if ( $id_exists && ! $update_existing ) {
				$data['skipped'][] = new WP_Error(
					'posterno_schema_importer_error',
					esc_html__( 'A schema with this ID already exists.', 'posterno' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && get_post_type( $id ) !== 'pno_schema' ) {
				$data['skipped'][] = new WP_Error(
					'posterno_schema_importer_error',
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
					'posterno_schema_importer_error',
					esc_html__( 'No matching schema exists to update.', 'posterno' ),
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

			do_action( 'posterno_schema_import_before_process_item', $data );

			$id       = false;
			$updating = false;

			if ( $this->params['update_existing'] ) {

				$id       = isset( $data['id'] ) ? $data['id'] : false;
				$updating = true;

			} else {

				$args = [
					'post_type'   => 'pno_schema',
					'post_title'  => 'Import placeholder for ' . $id,
					'post_status' => 'publish',
				];

				$schema = wp_insert_post( $args );

				if ( is_wp_error( $schema ) ) {
					throw new Exception( $schema->get_error_message() );
				} else {
					$id = $schema;
				}
			}

			if ( ! $id ) {
				throw new Exception( esc_html__( 'No ID was found.', 'posterno' ) );
			}

			$mode          = isset( $data['mode'] ) ? $data['mode'] : false;
			$title         = isset( $data['name'] ) ? $data['name'] : false;
			$listing_types = isset( $data['listing_types'] ) && is_array( $data['listing_types'] ) ? $data['listing_types'] : false;

			$code   = isset( $data['code'] ) ? $data['code'] : false;
			$status = 'publish';

			\PNO\SchemaOrg\Settings\SettingsStorage::save( $id, $mode, $title, $listing_types, $code, $status );

			return array(
				'id'      => $id,
				'updated' => $updating,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'posterno_schema_importer_error', $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

}
