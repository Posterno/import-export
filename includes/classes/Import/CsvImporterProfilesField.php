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

use Carbon_Fields\Carbon_Fields;
use WP_Error;
use Exception;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CSV ProfilesField importer class.
 */
class CsvImporterProfilesField extends AbstractImporter {

	/**
	 * Type of importer used for filters.
	 *
	 * @var string
	 */
	public $type = 'profilesfield';

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
			'id'    => 'intval',
			'title' => [ $this, 'parse_skip_field' ],
		);

		// Get formatting for custom fields.
		$repo = Carbon_Fields::resolve( 'container_repository' );

		foreach ( $repo->get_containers() as $container ) {
			if ( $container->get_id() === 'carbon_fields_container_pno_profile_fields_settings' || $container->get_id() === 'carbon_fields_container_pno_profile_fields_advanced_settings' ) {
				if ( ! empty( $container->get_fields() ) && is_array( $container->get_fields() ) ) {
					foreach ( $container->get_fields() as $field ) {
						$field_type = $field->get_type();
						switch ( $field_type ) {
							case 'select':
							case 'multiselect':
							case 'complex':
								$data_formatting[ $field->get_base_name() ] = [ $this, 'parse_serialized_field' ];
								break;
							case 'checkbox':
								$data_formatting[ $field->get_base_name() ] = [ $this, 'parse_bool_field' ];
								break;
						}
					}
				}
			}
		}

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

		return apply_filters( 'posterno_profilesfield_importer_formatting_callbacks', $callbacks, $this );
	}

	/**
	 * Process importer.
	 *
	 * Do not import profilesfields with IDs that already exist if option
	 * update existing is false, and likewise, if updating profilesfields, do not
	 * process rows which do not exist if an ID is provided.
	 *
	 * @return array
	 */
	public function import() {

		check_ajax_referer( 'pno-profilesfield-import', 'security' );

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

			do_action( 'posterno_profilesfield_import_before_import', $parsed_data );

			$id        = isset( $parsed_data['id'] ) ? absint( $parsed_data['id'] ) : 0;
			$id_exists = false;

			if ( $id ) {
				$profilesfield_status = get_post_status( $id );
				$id_exists            = $profilesfield_status && 'publish' === $profilesfield_status;
			}

			if ( $id_exists && ! $update_existing ) {
				$data['skipped'][] = new WP_Error(
					'posterno_profilesfield_importer_error',
					esc_html__( 'A profiles field with this ID already exists.', 'posterno' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && ( $id ) && ! $id_exists ) {
				$data['skipped'][] = new WP_Error(
					'posterno_profilesfield_importer_error',
					esc_html__( 'No matching profiles field exists to update.', 'posterno' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && get_post_type( $id ) !== 'pno_users_fields' ) {
				$data['skipped'][] = new WP_Error(
					'posterno_profilesfield_importer_error',
					esc_html__( 'ID found but post type not matching.', 'posterno' ),
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

			do_action( 'posterno_profilesfield_import_before_process_item', $data );

			$id       = false;
			$updating = false;

			if ( $this->params['update_existing'] ) {
				$id       = isset( $data['id'] ) ? $data['id'] : false;
				$updating = true;

				if ( ! $id ) {
					throw new Exception( esc_html__( 'No ID was found.', 'posterno' ) );
				}
			}

			// Verify if the field's metakey being processed already exists or is a default one.
			$meta_key    = isset( $data['profile_field_meta_key'] ) && ! empty( $data['profile_field_meta_key'] ) ? $data['profile_field_meta_key'] : false;
			$field_in_db = $this->field_exists( $meta_key );

			if ( pno_is_default_field( $meta_key ) ) {
				$updating = true;
			} elseif ( $field_in_db ) {
				$updating = true;
				$id       = $field_in_db;
			}

			// Now update or create a new field.
			$title    = isset( $data['title'] ) ? $data['title'] : false;
			$type     = isset( $data['profile_field_type'] ) ? $data['profile_field_type'] : false;
			$priority = isset( $data['profile_field_priority'] ) ? $data['profile_field_priority'] : 100;

			if ( $updating ) {
				$args = [
					'ID' => $id,
				];
				if ( $title ) {
					$args['post_title'] = $title;
				}
				wp_update_post( $args );
			} else {
				if ( ! $title ) {
					throw new Exception( esc_html__( 'No title assigned for import.', 'posterno' ) );
				}
				if ( ! $type ) {
					throw new Exception( esc_html__( 'No type assigned for import.', 'posterno' ) );
				}
				if ( ! $meta_key ) {
					throw new Exception( esc_html__( 'No meta key assigned for import.', 'posterno' ) );
				}
				$new_field = \PNO\Entities\Field\Profile::create(
					[
						'name'     => $title,
						'priority' => $priority,
						'type'     => $type,
					]
				);

				$id = $new_field->getPostID();

			}

			// Now update all other settings found.
			$keys_to_skip = [
				'ID',
				'title',
			];

			foreach ( $data as $key => $value ) {
				if ( empty( $value ) || in_array( $key, $keys_to_skip, true ) ) {
					continue;
				}
				carbon_set_post_meta( $id, $key, $value );
			}

			return array(
				'id'      => $id,
				'updated' => $updating,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'posterno_profilesfield_importer_error', $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

	/**
	 * Verify the field exists in the db by the meta key.
	 *
	 * @param string $key the meta key to verify.
	 * @return string|bool
	 */
	private function field_exists( $key ) {

		$field_id = false;

		$field       = new \PNO\Database\Queries\Profile_Fields();
		$found_field = $field->get_item_by( 'user_meta_key', $key );

		if ( $found_field instanceof \PNO\Entities\Field\Profile && $found_field->getPostID() > 0 ) {
			$field_id = $found_field->getPostID();
		}

		return $field_id;

	}

}
