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
 * Export Posterno emails.
 */
class CsvEmailsExporter extends CsvBatchExporter {

	/**
	 * Type of export, used in filters.
	 *
	 * @var string
	 */
	protected $export_type = 'emails';

	/**
	 * Get list of columns for the csv file.
	 *
	 * @return array
	 */
	public function get_default_column_names() {

		/**
		 * Filter: allow developers to customize csv columns for the emails exporter.
		 */
		return apply_filters(
			"posterno_export_{$this->export_type}_default_columns",
			array(
				'id'                                 => esc_html__( 'ID', 'posterno' ),
				'post_title'                         => esc_html__( 'Title', 'posterno' ),
				'post_content'                       => esc_html__( 'Content', 'posterno' ),
				'situations'                         => esc_html__( 'Situations', 'posterno' ),
				'heading'                            => esc_html__( 'Heading', 'posterno' ),
				'has_admin_notification'             => esc_html__( 'Notify admin', 'posterno' ),
				'administrator_notification_subject' => esc_html__( 'Admin notification subject', 'posterno' ),
				'administrator_notification'         => esc_html__( 'Admin notification content', 'posterno' ),
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
			'post_type'      => 'pno_emails',
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
					case 'situations':
						$value = $this->format_term_ids( $this->get_post_terms( $id, 'pno-email-type' ), 'pno-email-type' );
						break;
					case 'post_content':
						$value = $this->filter_description_field( $this->get_post_content( $id ) );
						break;
					case 'heading':
						$value = carbon_get_post_meta( $id, 'heading' );
						break;
					case 'has_admin_notification':
						$value = carbon_get_post_meta( $id, 'has_admin_notification' );
						break;
					case 'administrator_notification_subject':
						$value = carbon_get_post_meta( $id, 'administrator_notification_subject' );
						break;
					case 'administrator_notification':
						$value = carbon_get_post_meta( $id, 'administrator_notification' );
						break;
				}
			}

			$row[ $column_id ] = $value;
		}

		/**
		 * Filter: allow developers to customize data retrive for rows of the CSV emails exporter.
		 *
		 * @param array $row row data.
		 * @param string $id post id.
		 */
		return apply_filters( "posterno_{$this->export_type}_export_row_data", $row, $id );
	}

}
