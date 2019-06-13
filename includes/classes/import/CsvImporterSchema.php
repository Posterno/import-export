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
	 * Initialize importer.
	 *
	 * @param string $file   File to read.
	 * @param array  $params Arguments for the parser.
	 */
	public function __construct( $file, $params = array() ) {
		$default_args = array(
			'start_pos'        => 0, // File pointer start.
			'end_pos'          => -1, // File pointer end.
			'lines'            => -1, // Max lines to read.
			'mapping'          => array(), // Column mapping. csv_heading => schema_heading.
			'parse'            => false, // Whether to sanitize and format data.
			'update_existing'  => false, // Whether to update existing items.
			'delimiter'        => ',', // CSV delimiter.
			'prevent_timeouts' => true, // Check memory and time usage and abort if reaching limit.
			'enclosure'        => '"', // The character used to wrap text in the CSV.
			'escape'           => "\0", // PHP uses '\' as the default escape character. This is not RFC-4180 compliant. This disables the escape character.
		);

		$this->params = wp_parse_args( $params, $default_args );
		$this->file   = $file;

		if ( isset( $this->params['mapping']['from'], $this->params['mapping']['to'] ) ) {
			$this->params['mapping'] = array_combine( $this->params['mapping']['from'], $this->params['mapping']['to'] );
		}

		$this->read_file();
	}

	/**
	 * Parse relative field and return schema ID.
	 *
	 * Handles `id:xx` and SKUs.
	 *
	 * If mapping to an id: and the schema ID does not exist, this link is not
	 * valid.
	 *
	 * If mapping to a SKU and the schema ID does not exist, a temporary object
	 * will be created so it can be updated later.
	 *
	 * @param string $value Field value.
	 *
	 * @return int|string
	 */
	public function parse_relative_field( $value ) {
		global $wpdb;

		if ( empty( $value ) ) {
			return '';
		}

		// IDs are prefixed with id:.
		if ( preg_match( '/^id:(\d+)$/', $value, $matches ) ) {
			$id = intval( $matches[1] );

			// If original_id is found, use that instead of the given ID since a new placeholder must have been created already.
			$original_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_original_id' AND meta_value = %s;", $id ) ); // WPCS: db call ok, cache ok.

			if ( $original_id ) {
				return absint( $original_id );
			}

			// See if the given ID maps to a valid schema allready.
			$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( 'schema', 'schema_variation' ) AND ID = %d;", $id ) ); // WPCS: db call ok, cache ok.

			if ( $existing_id ) {
				return absint( $existing_id );
			}

			// If we're not updating existing posts, we may need a placeholder schema to map to.
			if ( ! $this->params['update_existing'] ) {
				// $schema = new WC_Product_Simple();
				// $schema->set_name( 'Import placeholder for ' . $id );
				// $schema->set_status( 'importing' );
				// $schema->add_meta_data( '_original_id', $id, true );
				// $id = $schema->save();
			}

			return $id;
		}

		// $id = pno_get_schema_id_by_sku( $value );
		if ( $id ) {
			return $id;
		}

		try {
			// $schema = new WC_Product_Simple();
			// $schema->set_name( 'Import placeholder for ' . $value );
			// $schema->set_status( 'importing' );
			// $schema->set_sku( $value );
			// $id = $schema->save();
			if ( $id && ! is_wp_error( $id ) ) {
				return $id;
			}
		} catch ( Exception $e ) {
			return '';
		}

		return '';
	}

	/**
	 * Parse the ID field.
	 *
	 * If we're not doing an update, create a placeholder schema so mapping works
	 * for rows following this one.
	 *
	 * @param string $value Field value.
	 *
	 * @return int
	 */
	public function parse_id_field( $value ) {
		global $wpdb;

		$id = absint( $value );

		if ( ! $id ) {
			return 0;
		}

		// See if this maps to an ID placeholder already.
		$original_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_original_id' AND meta_value = %s;", $id ) ); // WPCS: db call ok, cache ok.

		if ( $original_id ) {
			return absint( $original_id );
		}

		// Not updating? Make sure we have a new placeholder for this ID.
		if ( ! $this->params['update_existing'] ) {
			/*
			$mapped_keys      = $this->get_mapped_keys();
			$sku_column_index = absint( array_search( 'sku', $mapped_keys, true ) );
			$row_sku          = isset( $this->raw_data[ $this->parsing_raw_data_index ][ $sku_column_index ] ) ? $this->raw_data[ $this->parsing_raw_data_index ][ $sku_column_index ] : '';
			$id_from_sku      = $row_sku ? pno_get_schema_id_by_sku( $row_sku ) : '';

			// If row has a SKU, make sure placeholder was not made already.
			if ( $id_from_sku ) {
				return $id_from_sku;
			}

			$schema = new WC_Product_Simple();
			$schema->set_name( 'Import placeholder for ' . $id );
			$schema->set_status( 'importing' );
			$schema->add_meta_data( '_original_id', $id, true );

			// If row has a SKU, make sure placeholder has it too.
			if ( $row_sku ) {
				$schema->set_sku( $row_sku );
			}
			$id = $schema->save();*/
		}

		return $id && ! is_wp_error( $id ) ? $id : 0;
	}

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
			'id'                => array( $this, 'parse_id_field' ),
			'name'              => array( $this, 'parse_skip_field' ),
			'short_description' => array( $this, 'parse_description_field' ),
			'description'       => array( $this, 'parse_description_field' ),
			'menu_order'        => 'intval',
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
	 * Expand special and internal data into the correct formats for the schema CRUD.
	 *
	 * @param array $data Data to import.
	 *
	 * @return array
	 */
	protected function expand_data( $data ) {
		$data = apply_filters( 'posterno_schema_importer_pre_expand_data', $data );

		// Images field maps to image and gallery id fields.
		if ( isset( $data['images'] ) ) {
			$images               = $data['images'];
			$data['raw_image_id'] = array_shift( $images );

			if ( ! empty( $images ) ) {
				$data['raw_gallery_image_ids'] = $images;
			}
			unset( $data['images'] );
		}

		// Type, virtual and downloadable are all stored in the same column.
		if ( isset( $data['type'] ) ) {
			$data['type']         = array_map( 'strtolower', $data['type'] );
			$data['virtual']      = in_array( 'virtual', $data['type'], true );
			$data['downloadable'] = in_array( 'downloadable', $data['type'], true );

			// Convert type to string.
			$data['type'] = current( array_diff( $data['type'], array( 'virtual', 'downloadable' ) ) );
		}

		// Status is mapped from a special published field.
		if ( isset( $data['published'] ) ) {
			$statuses       = array(
				-1 => 'draft',
				0  => 'private',
				1  => 'publish',
			);
			$data['status'] = isset( $statuses[ $data['published'] ] ) ? $statuses[ $data['published'] ] : -1;

			unset( $data['published'] );
		}

		if ( isset( $data['stock_quantity'] ) ) {
			if ( '' === $data['stock_quantity'] ) {
				$data['manage_stock'] = false;
				$data['stock_status'] = isset( $data['stock_status'] ) ? $data['stock_status'] : true;
			} else {
				$data['manage_stock'] = true;
			}
		}

		// Stock is bool or 'backorder'.
		if ( isset( $data['stock_status'] ) ) {
			if ( 'backorder' === $data['stock_status'] ) {
				$data['stock_status'] = 'onbackorder';
			} else {
				$data['stock_status'] = $data['stock_status'] ? 'instock' : 'outofstock';
			}
		}

		// Prepare grouped schemas.
		if ( isset( $data['grouped_schemas'] ) ) {
			$data['children'] = $data['grouped_schemas'];
			unset( $data['grouped_schemas'] );
		}

		// Handle special column names which span multiple columns.
		$meta_data = array();

		foreach ( $data as $key => $value ) {
			if ( $this->starts_with( $key, 'meta:' ) ) {
				$meta_data[] = array(
					'key'   => str_replace( 'meta:', '', $key ),
					'value' => $value,
				);
				unset( $data[ $key ] );
			}
		}

		if ( ! empty( $meta_data ) ) {
			$data['meta_data'] = $meta_data;
		}

		return $data;
	}

	/**
	 * Process importer.
	 *
	 * Do not import schemas with IDs or SKUs that already exist if option
	 * update existing is false, and likewise, if updating schemas, do not
	 * process rows which do not exist if an ID/SKU is provided.
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

			$id         = isset( $parsed_data['id'] ) ? absint( $parsed_data['id'] ) : 0;
			/*$sku        = isset( $parsed_data['sku'] ) ? $parsed_data['sku'] : '';
			$id_exists  = false;
			$sku_exists = false;

			if ( $id ) {
				$schema    = pno_get_schema( $id );
				$id_exists = $schema && 'importing' !== $schema->get_status();
			}

			if ( $sku ) {
				$id_from_sku = pno_get_schema_id_by_sku( $sku );
				$schema      = $id_from_sku ? pno_get_schema( $id_from_sku ) : false;
				$sku_exists  = $schema && 'importing' !== $schema->get_status();
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

			if ( $sku_exists && ! $update_existing ) {
				$data['skipped'][] = new WP_Error(
					'posterno_schema_importer_error',
					esc_html__( 'A schema with this SKU already exists.', 'posterno' ),
					array(
						'sku' => esc_attr( $sku ),
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && ( $id || $sku ) && ! $id_exists && ! $sku_exists ) {
				$data['skipped'][] = new WP_Error(
					'posterno_schema_importer_error',
					esc_html__( 'No matching schema exists to update.', 'posterno' ),
					array(
						'id'  => $id,
						'sku' => esc_attr( $sku ),
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
			} */

			$index ++;

			if ( $this->params['prevent_timeouts'] && ( $this->time_exceeded() || $this->memory_exceeded() ) ) {
				$this->file_position = $this->file_positions[ $index ];
				break;
			}
		}

		return $data;
	}
}
