<?php
/**
 * Hooks the component to WordPress.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

namespace PosternoImportExport\Export;

use Carbon_Fields\Carbon_Fields;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Export Posterno listings fields.
 */
class CsvListingsFieldsExporter extends CsvBatchExporter {

	/**
	 * Type of export, used in filters.
	 *
	 * @var string
	 */
	protected $export_type = 'listings-fields';

	/**
	 * Get list of columns for the csv file.
	 *
	 * @return array
	 */
	public function get_default_column_names() {

		$cols = [
			'id'         => esc_html__( 'ID', 'posterno' ),
			'post_title' => esc_html__( 'Title', 'posterno' ),
		];

		$cols = array_merge( $cols, $this->get_cb_fields() );

		/**
		 * Filter: allow developers to customize csv columns for the listings fields exporter.
		 */
		return apply_filters( "posterno_export_{$this->export_type}_default_columns", $cols );
	}

	/**
	 * Get all settings registered for fields.
	 *
	 * @return array
	 */
	private function get_cb_fields() {

		$repo = Carbon_Fields::resolve( 'container_repository' );

		$fields = [];

		foreach ( $repo->get_containers() as $container ) {
			if ( $container->get_id() === 'carbon_fields_container_pno_listings_fields_settings' ) {
				if ( ! empty( $container->get_fields() ) && is_array( $container->get_fields() ) ) {
					foreach ( $container->get_fields() as $field ) {
						$fields[ $field->get_base_name() ] = $field->get_base_name();
					}
				}
			}
		}

		return $fields;

	}

	/**
	 * Prepare data for the export.
	 *
	 * @return void
	 */
	public function prepare_data_to_export() {
		$args = array(
			'post_status'    => array( 'private', 'publish', 'draft', 'future', 'pending' ),
			'post_type'      => 'pno_listings_fields',
			'posts_per_page' => $this->get_limit(),
			'paged'          => $this->get_page(),
			'fields'         => 'ids',
			'orderby'        => array(
				'ID' => 'ASC',
			),
		);

		$schemas = new \WP_Query( $args );

		$this->total_rows = $schemas->found_posts;
		$this->row_data   = array();

		foreach ( $schemas->get_posts() as $schema ) {
			$this->row_data[] = $this->generate_row_data( $schema );
		}

	}

	/**
	 * Generate data for each row.
	 *
	 * @param string $id id of the post found.
	 * @return array
	 */
	protected function generate_row_data( $id ) {
		$columns = $this->get_column_names();
		$row     = array();
		foreach ( $columns as $column_id => $column_name ) {
			$column_id = strstr( $column_id, ':' ) ? current( explode( ':', $column_id ) ) : $column_id;
			$value     = '';

			// Skip some columns if dynamically handled later or if we're being selective.
			if ( ! $this->is_column_exporting( $column_id ) ) {
				continue;
			}

			if ( has_filter( "posterno_{$this->export_type}_export_column_{$column_id}" ) ) {
				// Filter for 3rd parties.
				$value = apply_filters( "posterno_{$this->export_type}_export_column_{$column_id}", '', $id, $column_id );
			} elseif ( is_callable( array( $this, "get_column_value_{$column_id}" ) ) ) {
				// Handle special columns which don't map 1:1 to product data.
				$value = $this->{"get_column_value_{$column_id}"}( $id );
			} else {

				switch ( $column_id ) {
					case 'id':
						$value = absint( $id );
						break;
					case 'post_title':
						$value = $this->get_post_title( $id );
						break;
					default:
						$value = $this->get_carbon_setting( $id, $column_id );
						break;
				}
			}

			$row[ $column_id ] = $value;
		}

		/**
		 * Filter: allow developers to customize data retrive for rows of the CSV listings fields exporter.
		 *
		 * @param array $row row data.
		 * @param string $id post id.
		 */
		return apply_filters( "posterno_{$this->export_type}_export_row_data", $row, $id );
	}

}
