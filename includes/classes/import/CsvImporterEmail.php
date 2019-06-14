<?php
/**
 * Hooks the component to WordPress.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

namespace PosternoImportExport\Import;

use PosternoImportExport\Import\Controllers\Email;
use WP_Error;
use Exception;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CSV Email importer class.
 */
class CsvImporterEmail extends AbstractImporter {

	/**
	 * Type of importer used for filters.
	 *
	 * @var string
	 */
	public $type = 'email';

	/**
	 * Tracks current row being parsed.
	 *
	 * @var integer
	 */
	protected $parsing_raw_data_index = 0;

	/**
	 * Get formatting callback.
	 *
	 * @return array
	 */
	protected function get_formating_callback() {

		/**
		 * Columns not mentioned here will get parsed with 'pno_clean'.
		 * column_name => callback.
		 */
		$data_formatting = array(
			'id'            => 'intval',
			'content'       => [ $this, 'parse_description_field' ],
			'situations'    => [ $this, 'parse_email_situations_field' ],
			'notify_admin'  => [ $this, 'parse_bool_field' ],
			'admin_content' => [ $this, 'parse_description_field' ],
		);

		/**
		 * Match special column names.
		 */
		$regex_match_data_formatting = array(
			'/meta:*/' => 'wp_kses_post', // Allow some HTML in meta fields.
		);

		$callbacks = array();

		// Figure out the parse function for each column.
		foreach ( $this->get_mapped_keys() as $index => $heading ) {
			$callback = 'pno_clean';

			if ( isset( $data_formatting[ $heading ] ) ) {
				$callback = $data_formatting[ $heading ];
			} else {
				foreach ( $regex_match_data_formatting as $regex => $callback ) {
					if ( preg_match( $regex, $heading ) ) {
						$callback = $callback;
						break;
					}
				}
			}

			$callbacks[] = $callback;
		}

		return apply_filters( 'posterno_email_importer_formatting_callbacks', $callbacks, $this );
	}

	/**
	 * Parse email situations field.
	 *
	 * @param mixed $value the value of the field.
	 * @return array
	 */
	public function parse_email_situations_field( $value ) {
		if ( empty( $value ) ) {
			return array();
		}

		$value = $this->unescape_data( $value );
		$names = $this->explode_values( $value );
		$terms = array();

		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, 'pno-email-type' );

			if ( isset( $term->term_id ) ) {
				$terms[] = $term->term_id;
			}
		}

		return $terms;
	}

	/**
	 * Process importer.
	 *
	 * Do not import emails with IDs that already exist if option
	 * update existing is false, and likewise, if updating emails, do not
	 * process rows which do not exist if an ID is provided.
	 *
	 * @return array
	 */
	public function import() {
		$this->start_time = time();
		$index            = 0;
		$update_existing  = $this->params['update_existing'];
		$data             = array(
			'imported' => array(),
			'failed'   => array(),
			'updated'  => array(),
			'skipped'  => array(),
		);

		foreach ( $this->parsed_data as $parsed_data_key => $parsed_data ) {

			do_action( 'posterno_email_import_before_import', $parsed_data );

			$id        = isset( $parsed_data['id'] ) ? absint( $parsed_data['id'] ) : 0;
			$id_exists = false;

			if ( $id ) {
				$email_status = get_post_status( $id );
				$id_exists    = $email_status && 'publish' === $email_status;
			}

			if ( $id_exists && ! $update_existing ) {
				$data['skipped'][] = new WP_Error(
					'posterno_email_importer_error',
					esc_html__( 'A email with this ID already exists.', 'posterno' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && get_post_type( $id ) !== 'pno_emails' ) {
				$data['skipped'][] = new WP_Error(
					'posterno_email_importer_error',
					esc_html__( 'ID found but post type not matching.', 'posterno' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && ( $id ) && ! $id_exists ) {
				$data['skipped'][] = new WP_Error(
					'posterno_email_importer_error',
					esc_html__( 'No matching email exists to update.', 'posterno' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			// Verify the email type exists.
			$email_type = isset( $parsed_data['situations'] ) ? pno_clean( $parsed_data['situations'] ) : [];

			if ( empty( $email_type ) ) {
				$data['skipped'][] = new WP_Error(
					'posterno_email_importer_error',
					esc_html__( 'No matching email situation exists.', 'posterno' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			$result = $this->process_item( $parsed_data );

			if ( is_wp_error( $result ) ) {
				$result->add_data( array( 'row' => $this->get_row_id( $parsed_data ) ) );
				$data['failed'][] = $result;
			} elseif ( $result['updated'] ) {
				$data['updated'][] = $result['id'];
			} else {
				$data['imported'][] = $result['id'];
			}

			$index ++;

			if ( $this->params['prevent_timeouts'] && ( $this->time_exceeded() || $this->memory_exceeded() ) ) {
				$this->file_position = $this->file_positions[ $index ];
				break;
			}
		}

		return $data;
	}

	/**
	 * Process a single item and save.
	 *
	 * @throws Exception If item cannot be processed.
	 * @param  array $data Raw CSV data.
	 * @return array|WP_Error
	 */
	protected function process_item( $data ) {
		try {

			do_action( 'posterno_email_import_before_process_item', $data );

			$id       = false;
			$updating = false;

			// Grab details.
			$title         = isset( $data['title'] ) ? $data['title'] : 'null';
			$content       = isset( $data['content'] ) ? $data['content'] : false;
			$types         = isset( $data['situations'] ) ? $data['situations'] : [];
			$heading       = isset( $data['heading'] ) ? $data['heading'] : false;
			$notify_admin  = isset( $data['notify_admin'] ) ? $data['notify_admin'] : false;
			$admin_subject = isset( $data['admin_subject'] ) ? $data['admin_subject'] : false;
			$admin_content = isset( $data['admin_content'] ) ? $data['admin_content'] : false;

			if ( $this->params['update_existing'] ) {

				$id       = isset( $data['id'] ) ? $data['id'] : false;
				$updating = true;

			} else {

				$args = [
					'post_type'    => 'pno_emails',
					'post_title'   => $title,
					'post_content' => $content,
					'post_status'  => 'publish',
				];

				$schema = wp_insert_post( $args );

				if ( is_wp_error( $schema ) ) {
					throw new Exception( $schema->get_error_message() );
				} else {
					$id = $schema;
				}
			}

			if ( ! $id ) {
				throw new Exception( esc_html__( 'No ID was found.' ) );
			}

			if ( ! empty( $types ) ) {
				wp_set_post_terms( $id, array_map( 'absint', $types ), 'pno-email-type' );
			}

			carbon_set_post_meta( $id, 'heading', $heading );
			carbon_set_post_meta( $id, 'has_admin_notification', $notify_admin );
			carbon_set_post_meta( $id, 'administrator_notification_subject', $admin_subject );
			carbon_set_post_meta( $id, 'administrator_notification', $admin_content );

			return array(
				'id'      => $id,
				'updated' => $updating,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'posterno_email_importer_error', $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

}
