<?php
/**
 * Batch Import Class.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

namespace PosternoImportExport\Import;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Batch import schemas.
 */
class BatchImportSchemas extends BatchImport {

	/**
	 * Set up our import config.
	 *
	 * @return void
	 */
	public function init() {

		// Set up default field map values.
		$this->field_mapping = array(
			'post_title'     => '',
			'post_name'      => '',
			'post_status'    => 'draft',
			'post_author'    => '',
			'post_date'      => '',
			'post_content'   => '',
			'post_excerpt'   => '',
			'featured_image' => '',
		);
	}

	/**
	 * Process a step
	 *
	 * @return bool
	 */
	public function process_step() {

		$more = false;

		if ( ! $this->can_import() ) {
			wp_die( __( 'You do not have permission to import data.', 'posterno' ), __( 'Error', 'posterno' ), array( 'response' => 403 ) );
		}

		$i      = 1;
		$offset = $this->step > 1 ? ( $this->per_step * ( $this->step - 1 ) ) : 0;

		if ( $offset > $this->total ) {
			$this->done = true;
		}

		if ( ! $this->done && $this->csv->data ) {

			$more = true;

			foreach ( $this->csv->data as $key => $row ) {

				// Skip all rows until we pass our offset
				if ( $key + 1 <= $offset ) {
					continue;
				}

				// Done with this batch
				if ( $i > $this->per_step ) {
					break;
				}

				// Import Download
				$args = array(
					'post_type'    => 'download',
					'post_title'   => '',
					'post_name'    => '',
					'post_status'  => '',
					'post_author'  => '',
					'post_date'    => '',
					'post_content' => '',
					'post_excerpt' => '',
				);

				foreach ( $args as $key => $field ) {
					if ( ! empty( $this->field_mapping[ $key ] ) && ! empty( $row[ $this->field_mapping[ $key ] ] ) ) {
						$args[ $key ] = $row[ $this->field_mapping[ $key ] ];
					}
				}

				if ( empty( $args['post_author'] ) ) {
					$user                = wp_get_current_user();
					$args['post_author'] = $user->ID;
				} else {

					// Check all forms of possible user inputs, email, ID, login.
					if ( is_email( $args['post_author'] ) ) {
						$user = get_user_by( 'email', $args['post_author'] );
					} elseif ( is_numeric( $args['post_author'] ) ) {
						$user = get_user_by( 'ID', $args['post_author'] );
					} else {
						$user = get_user_by( 'login', $args['post_author'] );
					}

					// If we don't find one, resort to the logged in user.
					if ( false === $user ) {
						$user = wp_get_current_user();
					}

					$args['post_author'] = $user->ID;
				}

				// Format the date properly
				if ( ! empty( $args['post_date'] ) ) {

					$timestamp = strtotime( $args['post_date'], current_time( 'timestamp' ) );
					$date      = date( 'Y-m-d H:i:s', $timestamp );

					// If the date provided results in a date string, use it, or just default to today so it imports
					if ( ! empty( $date ) ) {
						$args['post_date'] = $date;
					} else {
						$date = '';
					}
				}

				// Detect any status that could map to `publish`
				if ( ! empty( $args['post_status'] ) ) {

					$published_statuses = array(
						'live',
						'published',
					);

					$current_status = strtolower( $args['post_status'] );

					if ( in_array( $current_status, $published_statuses ) ) {
						$args['post_status'] = 'publish';
					}
				}

				$download_id = wp_insert_post( $args );

				// Custom fields
				$i++;
			}
		}

		return $more;
	}

	/**
	 * Return the calculated completion percentage
	 *
	 * @return int
	 */
	public function get_percentage_complete() {

		if ( $this->total > 0 ) {
			$percentage = ( $this->step * $this->per_step / $this->total ) * 100;
		}

		if ( $percentage > 100 ) {
			$percentage = 100;
		}

		return $percentage;
	}

	/**
	 * Retrieve URL to list table.
	 *
	 * @return string
	 */
	public function get_list_table_url() {
		return admin_url( 'edit.php?post_type=listings&page=posterno-listings-schema' );
	}

	/**
	 * Retrieve label.
	 *
	 * @return string
	 */
	public function get_import_type_label() {
		return esc_html__( 'Schemas', 'posterno' );
	}

}
