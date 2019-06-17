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

use Carbon_Fields\Carbon_Fields;
use WP_Error;
use Exception;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CSV TaxonomyTerm importer class.
 */
class CsvImporterTaxonomyTerm extends AbstractImporter {

	/**
	 * Type of importer used for filters.
	 *
	 * @var string
	 */
	public $type = 'taxonomyterm';

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
			'id'          => 'intval',
			'term_name'   => [ $this, 'parse_skip_field' ],
			'description' => [ $this, 'parse_description_field' ],
			'parent'      => 'intval',
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

		return apply_filters( 'posterno_taxonomyterm_importer_formatting_callbacks', $callbacks, $this );
	}

	/**
	 * Process importer.
	 *
	 * Do not import taxonomyterms with IDs that already exist if option
	 * update existing is false, and likewise, if updating taxonomyterms, do not
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

			do_action( 'posterno_taxonomyterm_import_before_import', $parsed_data );

			$id       = isset( $parsed_data['id'] ) ? absint( $parsed_data['id'] ) : 0;
			$taxonomy = isset( $parsed_data['taxonomy'] ) ? sanitize_text_field( $parsed_data['taxonomy'] ) : '';

			if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
				$data['skipped'][] = new WP_Error(
					'posterno_taxonomyterm_importer_error',
					esc_html__( 'Taxonomy not found.', 'posterno' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing ) {

				$term_exists = term_exists( $id, $taxonomy );

				if ( ! $term_exists ) {
					$data['skipped'][] = new WP_Error(
						'posterno_taxonomyterm_importer_error',
						esc_html__( 'Term with this ID not found.', 'posterno' ),
						array(
							'id'  => $id,
							'row' => $this->get_row_id( $parsed_data ),
						)
					);
					continue;
				}
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

			do_action( 'posterno_taxonomyterm_import_before_process_item', $data );

			$id       = false;
			$updating = false;

			if ( $this->params['update_existing'] ) {
				$id       = isset( $data['id'] ) ? $data['id'] : false;
				$updating = true;

				if ( ! $id ) {
					throw new Exception( esc_html__( 'No ID was found.', 'posterno' ) );
				}
			}

			// Grab details.
			$name     = isset( $data['term_name'] ) ? $data['term_name'] : false;
			$slug     = isset( $data['slug'] ) ? $data['slug'] : false;
			$desc     = isset( $data['description'] ) ? $data['description'] : false;
			$parent   = isset( $data['parent'] ) ? $data['parent'] : false;
			$taxonomy = isset( $data['taxonomy'] ) ? $data['taxonomy'] : false;

			if ( ! $taxonomy ) {
				throw new Exception( esc_html__( 'No taxonomy assigned for import.', 'posterno' ) );
			}

			$args = [];

			if ( $name ) {
				$args['name'] = $name;
			}
			if ( $slug ) {
				$args['slug'] = $slug;
			}
			if ( $desc ) {
				$args['description'] = $desc;
			}
			if ( $parent ) {
				$args['parent'] = $parent;
			}

			if ( $updating ) {

				wp_update_term( $id, $taxonomy, $args );

			} else {

				if ( ! $name ) {
					throw new Exception( esc_html__( 'No title assigned for import.', 'posterno' ) );
				}

				$term = wp_insert_term( $name, $taxonomy, $args );

				if ( is_wp_error( $term ) ) {
					throw new Exception( $term->get_error_message() );
				}

				if ( is_array( $term ) && isset( $term['term_id'] ) ) {
					$id = $term['term_id'];
				}
			}

			// Update metadata.
			if ( isset( $data['meta_data'] ) && is_array( $data['meta_data'] ) && ! empty( $data['meta_data'] ) ) {
				foreach ( $data['meta_data'] as $meta ) {
					if ( ! isset( $meta['value'] ) || ( isset( $meta['value'] ) && empty( $meta['value'] ) ) ) {
						continue;
					}

					$type = $this->get_cb_field_type( $meta['key'], $taxonomy );

					if ( $type === 'image' ) {

						$attachment = $this->get_attachment_id_from_url( $meta['value'], $id );

						if ( is_wp_error( $attachment ) ) {
							throw new Exception( $attachment->get_error_message() );
						}

						$attachment_url = wp_get_attachment_url( $attachment );

						if ( $attachment_url ) {
							carbon_set_term_meta( $id, $meta['key'], $attachment_url );
						}
					} else {
						carbon_set_term_meta( $id, $meta['key'], $meta['value'] );
					}
				}
			}

			return array(
				'id'      => $id,
				'updated' => $updating,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'posterno_taxonomyterm_importer_error', $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

	/**
	 * Get the type of a cb field.
	 *
	 * @param string $meta the meta to verify.
	 * @param string $taxonomy the taxonomy to verify
	 * @return bool|string
	 */
	private function get_cb_field_type( $meta, $taxonomy ) {

		$type = false;

		$repo = Carbon_Fields::resolve( 'container_repository' );

		foreach ( $repo->get_containers() as $container ) {
			if ( pno_ends_with( $container->get_id(), "pno_term_settings_{$taxonomy}" ) ) {
				if ( ! empty( $container->get_fields() ) && is_array( $container->get_fields() ) ) {
					foreach ( $container->get_fields() as $field ) {
						if ( $field->get_base_name() === $meta ) {
							return $field->get_type();
						}
					}
				}
			}
		}

		return $type;

	}

}
