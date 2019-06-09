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

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Export listings schemas.
 */
class CsvSchemasExporter extends CsvBatchExporter {

	/**
	 * Type of export, used in filters.
	 *
	 * @var string
	 */
	protected $export_type = 'schemas';

	/**
	 * Get list of columns for the csv file.
	 *
	 * @return array
	 */
	public function get_default_column_names() {

		/**
		 * Filter: allow developers to customize csv columns for the schemas exporter.
		 */
		return apply_filters(
			"posterno_schema_export_{$this->export_type}_default_columns",
			array(
				'id'            => esc_html__( 'ID', 'posterno' ),
				'title'         => esc_html__( 'Title', 'posterno' ),
				'schema_mode'   => esc_html__( 'Mode', 'posterno' ),
				'schema_code'   => esc_html__( 'Code', 'posterno' ),
				'listing_types' => esc_html__( 'Listing types', 'posterno' ),
			)
		);
	}

	/**
	 * Prepare data for the export.
	 *
	 * @return void
	 */
	public function prepare_data_to_export() {
		$args = array(
			'post_status'    => array( 'private', 'publish', 'draft', 'future', 'pending' ),
			'post_type'      => 'pno_schema',
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

			if ( has_filter( "posterno_schema_export_column_{$column_id}" ) ) {
				// Filter for 3rd parties.
				$value = apply_filters( "posterno_schema_export_column_{$column_id}", '', $id, $column_id );
			} elseif ( is_callable( array( $this, "get_column_value_{$column_id}" ) ) ) {
				// Handle special columns which don't map 1:1 to product data.
				$value = $this->{"get_column_value_{$column_id}"}( $id );
			} else {

				switch ( $column_id ) {
					case 'id':
						$value = absint( $id );
						break;
					case 'title':
						$value = $this->get_post_title( $id );
						break;
					case 'schema_mode':
						$value = trim( get_post_meta( $id, 'schema_mode', true ) );
						break;
					case 'schema_code':
						$value = trim( get_post_meta( $id, 'schema_code', true ) );
						break;
					case 'listing_types':
						$value = $this->format_term_ids( get_post_meta( $id, 'schema_listing_types', true ), 'listings-types' );
						break;
				}
			}

			$row[ $column_id ] = $value;
		}

		/**
		 * Filter: allow developers to customize data retrive for rows of the CSV schemas exporter.
		 *
		 * @param array $row row data.
		 * @param string $id post id.
		 */
		return apply_filters( 'posterno_schema_export_row_data', $row, $id );
	}

}
