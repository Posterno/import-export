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
use Carbon_Fields\Carbon_Fields;

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
		$this->form = Form::createFromConfig( $this->get_fields() );
		$this->addSanitizer( $this->form );
		parent::__construct();
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

		$cols = [
			'id'          => esc_html__( 'ID', 'posterno' ),
			'name'        => esc_html__( 'Term name' ),
			'slug'        => esc_html__( 'Term slug' ),
			'description' => esc_html__( 'Description' ),
			'parent'      => esc_html__( 'Parent' ),
		];

		/**
		 * Filter: allow developers to customize csv columns for the registration fields exporter.
		 */
		return apply_filters( "posterno_export_{$this->export_type}_default_columns", $cols );
	}

	/**
	 * Get fields associated to the carbon fields.
	 *
	 * @return array
	 */
	public function get_cb_fields() {

		$cols = [];

		$repo = Carbon_Fields::resolve( 'container_repository' );

		foreach ( $repo->get_containers() as $container ) {
			if ( pno_ends_with( $container->get_id(), "pno_term_settings_{$this->taxonomy}" ) ) {
				if ( ! empty( $container->get_fields() ) && is_array( $container->get_fields() ) ) {
					foreach ( $container->get_fields() as $field ) {
						$cols[ $field->get_base_name() ] = $field->get_base_name();
					}
				}

			}
		}

		return $cols;

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
			'orderby'    => 'id',
			'order'      => 'ASC',
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

			if ( has_filter( "posterno_{$this->export_type}_{$this->taxonomy}_export_column_{$column_id}" ) ) {
				// Filter for 3rd parties.
				$value = apply_filters( "posterno_{$this->export_type}_{$this->taxonomy}_export_column_{$column_id}", '', $term, $column_id );
			} elseif ( is_callable( array( $this, "get_column_value_{$column_id}" ) ) ) {
				// Handle special columns which don't map 1:1 to product data.
				$value = $this->{"get_column_value_{$column_id}"}( $term );
			} else {

				switch ( $column_id ) {
					case 'id':
						$value = absint( $term->term_id );
						break;
					case 'name':
						$value = $term->name;
						break;
					case 'slug':
						$value = $term->slug;
						break;
					case 'description':
						$value = $this->filter_description_field( $term->description );
						break;
					case 'parent':
						$value = $term->parent;
						break;
					default:
						$value = $this->get_carbon_term_setting( $this->taxonomy, $term->term_id, $column_id );
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
		return apply_filters( "posterno_{$this->export_type}_{$this->taxonomy}_export_row_data", $row, $term );
	}

}
