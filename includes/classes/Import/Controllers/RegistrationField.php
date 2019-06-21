<?php
/**
 * Registration Field importer controller.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

namespace PosternoImportExport\Import\Controllers;

use Carbon_Fields\Carbon_Fields;
use WP_Error;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Registration Field importer controller - handles file upload and forms in admin.
 */
class RegistrationField extends BaseController {

	/**
	 * Import type name used for filters.
	 *
	 * @var string
	 */
	public $type = 'registrationfield';

	/**
	 * Get things started.
	 */
	public function __construct() {
		parent::__construct();

		$this->page_title              = esc_html__( 'Import registration fields from a CSV File', 'posterno' );
		$this->page_description        = esc_html__( 'This tool allows you to import (or merge) registration fields to your webiste from a CSV file.', 'posterno' );
		$this->page_update_label       = esc_html__( 'Update existing registration fields', 'posterno' );
		$this->page_update_description = esc_html__( 'Existing registration fields that match by ID will be updated. Registration Fields that do not exist will be skipped.', 'posterno' );
		$this->page_done_url           = admin_url( 'users.php?page=posterno-custom-registration-form' );
		$this->page_item_label         = esc_html__( 'Registration Field', 'posterno' );
		$this->page_url                = admin_url( 'edit.php?post_type=listings&page=registrationfield_importer' );
	}

	/**
	 * Get importer instance.
	 *
	 * @param  string $file File to import.
	 * @param  array  $args Importer arguments.
	 * @return \PosternoImportExport\Import\CsvImporterRegistrationField
	 */
	public static function get_importer( $file, $args = array() ) {
		$importer_class = apply_filters( 'posterno_registrationfield_csv_importer_class', '\PosternoImportExport\Import\CsvImporterRegistrationField' );
		$args           = apply_filters( 'posterno_registrationfield_csv_importer_args', $args, $importer_class );
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
				'posterno_csv_registrationfield_import_mapping_default_columns',
				array(
					__( 'ID', 'posterno' )    => 'id',
					__( 'Title', 'posterno' ) => 'title',
				)
			)
		);

		$repo = Carbon_Fields::resolve( 'container_repository' );

		$fields = [];

		foreach ( $repo->get_containers() as $container ) {
			if ( $container->get_id() === 'carbon_fields_container_pno_registration_fields_settings' || $container->get_id() === 'carbon_fields_container_pno_registration_fields_advanced_settings' ) {
				if ( ! empty( $container->get_fields() ) && is_array( $container->get_fields() ) ) {
					foreach ( $container->get_fields() as $field ) {
						$fields[ ! empty( $field->get_label() ) ? $field->get_label() : $field->get_base_name() ] = $field->get_base_name();
					}
				}
			}
		}

		$default_columns = array_merge( $initial_columns, $fields );

		$special_columns = $this->get_special_columns(
			$this->normalize_columns_names(
				apply_filters(
					'posterno_csv_registrationfield_import_mapping_special_columns',
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

		return apply_filters( 'posterno_csv_registrationfield_import_mapped_columns', $headers, $raw_headers );
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

		// Available options.
		$default = array(
			'id'    => esc_html__( 'ID', 'posterno' ),
			'title' => esc_html__( 'Field title', 'posterno' ),
		);

		$repo = Carbon_Fields::resolve( 'container_repository' );

		$fields = [];

		foreach ( $repo->get_containers() as $container ) {
			if ( $container->get_id() === 'carbon_fields_container_pno_registration_fields_settings' || $container->get_id() === 'carbon_fields_container_pno_registration_fields_advanced_settings' ) {
				if ( ! empty( $container->get_fields() ) && is_array( $container->get_fields() ) ) {
					foreach ( $container->get_fields() as $field ) {
						$fields[ $field->get_base_name() ] = ! empty( $field->get_label() ) ? $field->get_label() : $field->get_base_name();
					}
				}
			}
		}

		$options = array_merge( $default, $fields );

		return apply_filters( 'posterno_csv_registrationfield_import_mapping_options', $options, $item );
	}
}
