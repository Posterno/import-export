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
				'id'                 => __( 'ID', 'posterno' ),
				'type'               => __( 'Type', 'posterno' ),
				'sku'                => __( 'SKU', 'posterno' ),
				'name'               => __( 'Name', 'posterno' ),
				'published'          => __( 'Published', 'posterno' ),
				'featured'           => __( 'Is featured?', 'posterno' ),
				'catalog_visibility' => __( 'Visibility in catalog', 'posterno' ),
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
			'status'   => array( 'private', 'publish', 'draft', 'future', 'pending' ),
			'type'     => $this->product_types_to_export,
			'limit'    => $this->get_limit(),
			'page'     => $this->get_page(),
			'orderby'  => array(
				'ID' => 'ASC',
			),
			'return'   => 'objects',
			'paginate' => true,
		);

		if ( ! empty( $this->product_category_to_export ) ) {
			$args['category'] = $this->product_category_to_export;
		}
		$products = pno_get_products( apply_filters( "posterno_product_export_{$this->export_type}_query_args", $args ) );

		$this->total_rows  = $products->total;
		$this->row_data    = array();
		$variable_products = array();

		foreach ( $products->products as $product ) {
			// Check if the category is set, this means we need to fetch variations seperately as they are not tied to a category.
			if ( ! empty( $args['category'] ) && $product->is_type( 'variable' ) ) {
				$variable_products[] = $product->get_id();
			}

			$this->row_data[] = $this->generate_row_data( $product );
		}

		// If a category was selected we loop through the variations as they are not tied to a category so will be excluded by default.
		if ( ! empty( $variable_products ) ) {
			foreach ( $variable_products as $parent_id ) {
				$products = pno_get_products(
					array(
						'parent' => $parent_id,
						'type'   => array( 'variation' ),
						'return' => 'objects',
						'limit'  => -1,
					)
				);

				if ( ! $products ) {
					continue;
				}

				foreach ( $products as $product ) {
					$this->row_data[] = $this->generate_row_data( $product );
				}
			}
		}
	}

	/**
	 * Take a product and generate row data from it for export.
	 *
	 * @param WC_Product $product WC_Product object.
	 *
	 * @return array
	 */
	protected function generate_row_data( $product ) {
		$columns = $this->get_column_names();
		$row     = array();
		foreach ( $columns as $column_id => $column_name ) {
			$column_id = strstr( $column_id, ':' ) ? current( explode( ':', $column_id ) ) : $column_id;
			$value     = '';

			// Skip some columns if dynamically handled later or if we're being selective.
			if ( in_array( $column_id, array( 'downloads', 'attributes', 'meta' ), true ) || ! $this->is_column_exporting( $column_id ) ) {
				continue;
			}

			if ( has_filter( "posterno_product_export_{$this->export_type}_column_{$column_id}" ) ) {
				// Filter for 3rd parties.
				$value = apply_filters( "posterno_product_export_{$this->export_type}_column_{$column_id}", '', $product, $column_id );

			} elseif ( is_callable( array( $this, "get_column_value_{$column_id}" ) ) ) {
				// Handle special columns which don't map 1:1 to product data.
				$value = $this->{"get_column_value_{$column_id}"}( $product );

			} elseif ( is_callable( array( $product, "get_{$column_id}" ) ) ) {
				// Default and custom handling.
				$value = $product->{"get_{$column_id}"}( 'edit' );
			}

			if ( 'description' === $column_id || 'short_description' === $column_id ) {
				$value = $this->filter_description_field( $value );
			}

			$row[ $column_id ] = $value;
		}

		$this->prepare_downloads_for_export( $product, $row );
		$this->prepare_attributes_for_export( $product, $row );
		$this->prepare_meta_for_export( $product, $row );
		return apply_filters( 'posterno_product_export_row_data', $row, $product );
	}

	/**
	 * Get type value.
	 *
	 * @param WC_Product $product Product being exported.
	 *
	 * @since  3.1.0
	 * @return string
	 */
	protected function get_column_value_type( $product ) {
		$types   = array();
		$types[] = $product->get_type();

		if ( $product->is_downloadable() ) {
			$types[] = 'downloadable';
		}

		if ( $product->is_virtual() ) {
			$types[] = 'virtual';
		}

		return $this->implode_values( $types );
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
