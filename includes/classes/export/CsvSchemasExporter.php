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

class CsvSchemasExporter extends CsvBatchExporter {

	/**
	 * Type of export used in filter names.
	 *
	 * @var string
	 */
	protected $export_type = 'schemas';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Return an array of columns to export.
	 *
	 * @since  3.1.0
	 * @return array
	 */
	public function get_default_column_names() {
		return apply_filters(
			"posterno_product_export_{$this->export_type}_default_columns",
			array(
				'id'        => __( 'ID', 'posterno' ),
				'sku'       => __( 'SKU', 'posterno' ),
				'name'      => __( 'Name', 'posterno' ),
				'published' => __( 'Published', 'posterno' ),
			)
		);
	}

	/**
	 * Prepare data for export.
	 *
	 * @since 3.1.0
	 */
	public function prepare_data_to_export() {
		$args = array(
			'post_status'         => array( 'private', 'publish', 'draft', 'future', 'pending' ),
			'post_type'      => 'pno_schema',
			'posts_per_page' => $this->get_limit(),
			'paged'          => $this->get_page(),
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

	protected function generate_row_data( $schema ) {
		$columns = $this->get_column_names();
		$row     = array();
		foreach ( $columns as $column_id => $column_name ) {
			$column_id = strstr( $column_id, ':' ) ? current( explode( ':', $column_id ) ) : $column_id;
			$value     = '';

			// Skip some columns if dynamically handled later or if we're being selective.
			if ( ! $this->is_column_exporting( $column_id ) ) {
				continue;
			}

			if ( has_filter( "posterno_product_export_{$this->export_type}_column_{$column_id}" ) ) {
				// Filter for 3rd parties.
				$value = apply_filters( "posterno_product_export_{$this->export_type}_column_{$column_id}", '', $schema, $column_id );

			} elseif ( is_callable( array( $this, "get_column_value_{$column_id}" ) ) ) {
				// Handle special columns which don't map 1:1 to product data.
				$value = $this->{"get_column_value_{$column_id}"}( $schema );

			} elseif ( is_callable( array( $schema, "get_{$column_id}" ) ) ) {
				// Default and custom handling.
				$value = $schema->{"get_{$column_id}"}( 'edit' );
			}

			if ( 'description' === $column_id || 'short_description' === $column_id ) {
				$value = $this->filter_description_field( $value );
			}

			$row[ $column_id ] = $value;
		}

		return apply_filters( 'posterno_product_export_row_data', $row, $schema );
	}

	/**
	 * Filter description field for export.
	 * Convert newlines to '\n'.
	 *
	 * @param string $description Product description text to filter.
	 *
	 * @since  3.5.4
	 * @return string
	 */
	protected function filter_description_field( $description ) {
		$description = str_replace( '\n', "\\\\n", $description );
		$description = str_replace( "\n", '\n', $description );
		return $description;
	}

}
