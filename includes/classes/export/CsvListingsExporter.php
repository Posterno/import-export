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
use PNO\Form\Form;
use PNO\Form\DefaultSanitizer;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Export Posterno listings.
 */
class CsvListingsExporter extends CsvBatchExporter {

	use DefaultSanitizer;

	/**
	 * Type of export, used in filters.
	 *
	 * @var string
	 */
	protected $export_type = 'listings';

	/**
	 * Statuses to export.
	 *
	 * @var string|array
	 */
	public $statuses = null;

	/**
	 * Categories to export.
	 *
	 * @var string|array
	 */
	public $categories = null;

	/**
	 * Get things started.
	 */
	public function __construct() {
		$this->form = Form::createFromConfig( $this->get_fields() );
		$this->addSanitizer( $this->form );
		parent::__construct();
	}

	/**
	 * Set stati to export.
	 *
	 * @param string|array $value stati to export.
	 * @return void
	 */
	public function set_statuses( $value ) {
		$this->statuses = array_map( 'pno_clean', $value );
	}

	/**
	 * Set categories to export.
	 *
	 * @param string|array $cats categories to export.
	 * @return void
	 */
	public function set_categories( $cats ) {
		$this->categories = array_map( 'sanitize_title_with_dashes', $cats );
	}

	/**
	 * Get fields for the forms.
	 *
	 * @return array
	 */
	public function get_fields() {

		$fields = [
			'columns_to_export' => [
				'type'       => 'multiselect',
				'label'      => esc_html__( 'Which columns should be exported?', 'posterno' ),
				'required'   => true,
				'values'     => $this->get_default_column_names(),
				'attributes' => [
					'class'            => 'form-control',
					'data-placeholder' => esc_html__( 'Leave empty to export all content' ),
				],
			],
			'status'            => [
				'type'       => 'multiselect',
				'label'      => esc_html__( 'Which statuses should be exported?', 'posterno' ),
				'required'   => true,
				'values'     => [
					'publish' => esc_html__( 'Published' ),
					'private' => esc_html__( 'Private' ),
					'draft'   => esc_html__( 'Draft' ),
					'future'  => esc_html__( 'Future' ),
					'pending' => esc_html__( 'Pending' ),
					'expired' => esc_html__( 'Expired' ),
				],
				'attributes' => [
					'class'            => 'form-control',
					'data-placeholder' => esc_html__( 'Leave empty to export all listings' ),
				],
			],
			'categories'        => [
				'type'       => 'multiselect',
				'label'      => esc_html__( 'Which listing category should be exported?', 'posterno' ),
				'required'   => true,
				'values'     => pno_get_listings_categories_for_association(),
				'attributes' => [
					'class'            => 'form-control',
					'data-placeholder' => esc_html__( 'Leave empty to export all listings' ),
				],
			],
		];

		return $fields;

	}

	/**
	 * Get list of columns for the csv file.
	 *
	 * @return array
	 */
	public function get_default_column_names() {

		$cols = [
			'id'                => esc_html__( 'ID', 'posterno' ),
			'post_title'        => esc_html__( 'Title', 'posterno' ),
			'description'       => esc_html__( 'Description' ),
			'short_description' => esc_html__( 'Short description' ),
			'featured_image'    => esc_html__( 'Featured image' ),
			'published'         => esc_html__( 'Published' ),
			'status'            => esc_html__( 'Status' ),
			'expires'           => esc_html__( 'Expires' ),
			'opening_hours'     => esc_html__( 'Opening hours' ),
		];

		$cols = array_merge( $cols, $this->get_cb_fields(), pno_get_registered_listings_taxonomies() );

		/**
		 * Filter: allow developers to customize csv columns for the listings exporter.
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

		$fields_to_skip = [
			'listing_type',
			'monday',
			'monday_time_slots',
			'monday_opening',
			'monday_closing',
			'monday_additional_times',
			'tuesday',
			'tuesday_time_slots',
			'tuesday_opening',
			'tuesday_closing',
			'tuesday_additional_times',
			'wednesday',
			'wednesday_time_slots',
			'wednesday_opening',
			'wednesday_closing',
			'wednesday_additional_times',
			'thursday',
			'thursday_time_slots',
			'thursday_opening',
			'thursday_closing',
			'thursday_additional_times',
			'friday',
			'friday_time_slots',
			'friday_opening',
			'friday_closing',
			'friday_additional_times',
			'saturday',
			'saturday_time_slots',
			'saturday_opening',
			'saturday_closing',
			'saturday_additional_times',
			'sunday',
			'sunday_time_slots',
			'sunday_opening',
			'sunday_closing',
			'sunday_additional_times',
		];

		foreach ( $repo->get_containers() as $container ) {
			if ( $container->get_id() === 'carbon_fields_container_pno_listings_settings' ) {
				if ( ! empty( $container->get_fields() ) && is_array( $container->get_fields() ) ) {
					foreach ( $container->get_fields() as $field ) {
						if ( in_array( $field->get_base_name(), $fields_to_skip ) ) {
							continue;
						}
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
			'post_status'    => array( 'private', 'publish', 'draft', 'future', 'pending', 'expired' ),
			'post_type'      => 'listings',
			'posts_per_page' => $this->get_limit(),
			'paged'          => $this->get_page(),
			'fields'         => 'ids',
			'orderby'        => array(
				'ID' => 'ASC',
			),
		);

		$schemas = new \WP_Query( apply_filters( 'posterno_listings_export_query', $args ) );

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
