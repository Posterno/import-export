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

use PNO\Form\Form;
use PNO\Form\DefaultSanitizer;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Export Posterno terms.
 */
class CsvTaxonomyExporter extends CsvBatchExporter {

	use DefaultSanitizer;

	/**
	 * Type of export, used in filters.
	 *
	 * @var string
	 */
	protected $export_type = 'taxonomy';

	/**
	 * Holds settings form for this exporter.
	 *
	 * @var Form
	 */
	public $form;

	/**
	 * Taxonomy to export.
	 *
	 * @var string
	 */
	public $taxonomy;

	/**
	 * Get things started.
	 */
	public function __construct() {
		parent::__construct();
		$this->form = Form::createFromConfig( $this->get_fields() );
		$this->addSanitizer( $this->form );
	}

	/**
	 * Get fields for the forms.
	 *
	 * @return array
	 */
	public function get_fields() {

		$fields = [
			'taxonomy_to_export' => [
				'type'       => 'select',
				'label'      => esc_html__( 'Taxonomy to export:', 'posterno' ),
				'required'   => true,
				'values'     => pno_get_registered_listings_taxonomies(),
				'attributes' => [
					'class' => 'form-control',
				],
			],
		];

		return $fields;

	}

	/**
	 * Set the taxonomy to export.
	 *
	 * @param string $tax the taxonomy name.
	 * @return void
	 */
	public function set_taxonomy_to_export( $tax ) {
		$this->taxonomy = $tax;
	}

	/**
	 * Get list of columns for the csv file.
	 *
	 * @return array
	 */
	public function get_default_column_names() {

		/**
		 * Filter: allow developers to customize csv columns for the taxonomy exporter.
		 */
		return apply_filters(
			"posterno_export_{$this->export_type}_default_columns",
			array(
				'id' => esc_html__( 'ID', 'posterno' ),
			)
		);
	}

	/**
	 * Prepare data for the export.
	 *
	 * @return void
	 */
	public function prepare_data_to_export() {

		$offset = ( $this->get_page() - 1 ) * $this->get_limit();

		$args = [
			'taxonomy'   => $this->taxonomy,
			'hide_empty' => false,
			'number'     => $this->get_limit(),
			'offset'     => $offset,
		];

		$terms = get_terms( $args );

		$this->total_rows = count( $terms );
		$this->row_data   = array();

		foreach ( $terms as $term ) {
			$this->row_data[] = $this->generate_row_data( $term );
		}

	}

	/**
	 * Generate data for each row.
	 *
	 * @param object $term term found.
	 * @return array
	 */
	protected function generate_row_data( $term ) {
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
				$value = apply_filters( "posterno_{$this->export_type}_export_column_{$column_id}", '', $term, $column_id );
			} elseif ( is_callable( array( $this, "get_column_value_{$column_id}" ) ) ) {
				// Handle special columns which don't map 1:1 to product data.
				$value = $this->{"get_column_value_{$column_id}"}( $term );
			} else {

				switch ( $column_id ) {
					case 'id':
						$value = absint( $term->term_id );
						break;
				}
			}

			$row[ $column_id ] = $value;
		}

		/**
		 * Filter: allow developers to customize data retrive for rows of the CSV taxonomy exporter.
		 *
		 * @param array $row row data.
		 * @param string $id post id.
		 */
		return apply_filters( "posterno_{$this->export_type}_export_row_data", $row, $term );
	}

}
