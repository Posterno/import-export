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

use PosternoImportExport\Import\Controllers\Listing;
use WP_Error;
use Exception;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CSV Listing importer class.
 */
class CsvImporterListing extends AbstractImporter {

	/**
	 * Type of importer used for filters.
	 *
	 * @var string
	 */
	public $type = 'listing';

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
		$default_formatting = array(
			'id'                      => 'intval',
			'description'             => [ $this, 'parse_description_field' ],
			'short_description'       => [ $this, 'parse_description_field' ],
			'featured_image'          => [ $this, 'parse_images_field' ],
			'featured'                => [ $this, 'parse_bool_field' ],
			'opening_hours'           => [ $this, 'parse_json_field' ],
			'latitude'                => [ $this, 'parse_float_field' ],
			'longitude'               => [ $this, 'parse_float_field' ],
			'gallery'                 => [ $this, 'parse_images_field' ],
			'listing_social_profiles' => [ $this, 'parse_json_field' ],
		);

		$additional_mappings = $this->get_carbon_fields_mappings();
		$taxonomy_mappings   = $this->get_taxonomies_mappings();

		$data_formatting = array_merge( $default_formatting, $additional_mappings, $taxonomy_mappings );

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

		return apply_filters( 'posterno_listing_importer_formatting_callbacks', $callbacks, $this );
	}

	/**
	 * Get listing field.
	 *
	 * @param string $setting_id meta key.
	 * @return string|boolean
	 */
	protected function get_listing_field( $setting_id ) {

		$field = new \PNO\Database\Queries\Listing_Fields();

		$found_field = $field->get_item_by( 'listing_meta_key', $setting_id );

		return $found_field instanceof \PNO\Entities\Field\Listing && $found_field->getPostID() > 0 ? $found_field : false;

	}

	/**
	 * Get mappings for custom fields.
	 *
	 * @return array
	 */
	private function get_carbon_fields_mappings() {

		$mappings = [];

		$repo = \Carbon_Fields\Carbon_Fields::resolve( 'container_repository' );

		$fields_to_skip = pno_get_carbon_listings_fields_to_skip();

		foreach ( $repo->get_containers() as $container ) {
			if ( $container->get_id() === 'carbon_fields_container_pno_listings_settings' ) {
				if ( ! empty( $container->get_fields() ) && is_array( $container->get_fields() ) ) {
					foreach ( $container->get_fields() as $field ) {
						if ( in_array( $field->get_base_name(), $fields_to_skip ) ) {
							continue;
						}

						$found_field = $this->get_listing_field( $field->get_base_name() );

						if ( ! $found_field ) {
							continue;
						}

						switch ( $found_field->getType() ) {
							case 'textarea':
							case 'editor':
								$mappings[ $field->get_base_name() ] = [ $this, 'parse_description_field' ];
								break;
							case 'multiselect':
							case 'multicheckbox':
								$mappings[ $field->get_base_name() ] = [ $this, 'parse_comma_field' ];
								break;
							case 'checkbox':
								$mappings[ $field->get_base_name() ] = [ $this, 'parse_bool_field' ];
								break;
							case 'file':
								$mappings[ $field->get_base_name() ] = [ $this, 'parse_images_field' ];
								break;
						}
					}
				}
			}
		}

		return $mappings;

	}

	/**
	 * Get mappings for taxonomies.
	 *
	 * @return array
	 */
	private function get_taxonomies_mappings() {

		$mappings = [];

		// Listing types.
		$mappings['listings-types'] = [ $this, 'parse_listing_types_field' ];

		// Listing categories.
		$mappings['listings-categories'] = [ $this, 'parse_listings_categories_taxonomy_field' ];

		// Listing categories.
		$mappings['listings-locations'] = [ $this, 'parse_listings_locations_taxonomy_field' ];

		// Listing tags.
		$mappings['listings-tags'] = [ $this, 'parse_listing_tags_field' ];

		return $mappings;

	}

	/**
	 * Process importer.
	 *
	 * Do not import listings with IDs that already exist if option
	 * update existing is false, and likewise, if updating listings, do not
	 * process rows which do not exist if an ID is provided.
	 *
	 * @return array
	 */
	public function import() {

		check_ajax_referer( 'pno-listing-import', 'security' );

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

			do_action( 'posterno_listing_import_before_import', $parsed_data );

			$id        = isset( $parsed_data['id'] ) ? absint( $parsed_data['id'] ) : 0;
			$id_exists = false;

			if ( $id ) {
				$listing_status = get_post_status( $id );
				$id_exists      = $listing_status && 'publish' === $listing_status;
			}

			if ( $id_exists && ! $update_existing ) {
				$data['skipped'][] = new WP_Error(
					'posterno_listing_importer_error',
					esc_html__( 'A listing with this ID already exists.', 'posterno' ),
					array(
						'id'  => $id,
						'row' => $this->get_row_id( $parsed_data ),
					)
				);
				continue;
			}

			if ( $update_existing && get_post_type( $id ) !== 'listings' ) {
				$data['skipped'][] = new WP_Error(
					'posterno_listing_importer_error',
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
					'posterno_listing_importer_error',
					esc_html__( 'No matching listing exists to update.', 'posterno' ),
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

			do_action( 'posterno_listing_import_before_process_item', $data );

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
			$title           = isset( $data['title'] ) ? $data['title'] : false;
			$description     = isset( $data['description'] ) ? $data['description'] : false;
			$excerpt         = isset( $data['short_description'] ) ? $data['short_description'] : false;
			$featured_image  = isset( $data['featured_image'][0] ) ? $data['featured_image'][0] : false;
			$publish_date    = isset( $data['published'] ) ? $data['published'] : false;
			$last_modified   = isset( $data['last_modified'] ) ? $data['last_modified'] : false;
			$status          = isset( $data['status'] ) ? $data['status'] : false;
			$expires_date    = isset( $data['expires'] ) ? $data['expires'] : false;
			$featured        = isset( $data['featured'] ) && $data['featured'] ? 'yes' : false;
			$opening_hours   = isset( $data['opening_hours'] ) && ! empty( $data['opening_hours'] ) ? $data['opening_hours'] : false;
			$lat             = isset( $data['latitude'] ) ? $data['latitude'] : false;
			$lng             = isset( $data['longitude'] ) ? $data['longitude'] : false;
			$address         = isset( $data['address'] ) ? $data['address'] : false;
			$gallery         = isset( $data['gallery'] ) && ! empty( $data['gallery'] ) ? $data['gallery'] : false;
			$social_profiles = isset( $data['listing_social_profiles'] ) && ! empty( $data['listing_social_profiles'] ) ? $data['listing_social_profiles'] : false;

			$args = [
				'post_type' => 'listings',
			];

			if ( $title ) {
				$args['post_title'] = $title;
			}
			if ( $description ) {
				$args['post_content'] = $description;
			}
			if ( $excerpt ) {
				$args['post_excerpt'] = $excerpt;
			}
			if ( $publish_date ) {
				$publish_date          = date( 'Y-m-d H:i:s', strtotime( $publish_date ) );
				$args['post_date']     = $publish_date;
				$args['post_date_gmt'] = get_gmt_from_date( $publish_date );
			}
			if ( $last_modified ) {
				$last_modified             = date( 'Y-m-d H:i:s', strtotime( $last_modified ) );
				$args['post_modified']     = $last_modified;
				$args['post_modified_gmt'] = get_gmt_from_date( $last_modified );
			}
			if ( $status ) {
				$args['post_status'] = $status;
			}

			// Grab the author's ID.
			$author_id       = isset( $data['author_id'] ) ? $data['author_id'] : false;
			$author_username = isset( $data['author_username'] ) ? $data['author_username'] : false;
			$author_email    = isset( $data['author_email'] ) ? $data['author_email'] : false;

			if ( $author_username ) {
				$user = get_user_by( 'login', $author_username );
				if ( $user ) {
					$author_id = $user->ID;
				}
			} elseif ( $author_email ) {
				$user = get_user_by( 'email', $author_email );
				if ( $user ) {
					$author_id = $user->ID;
				}
			} else {
				$user = get_user_by( 'id', $author_id );
				if ( $user ) {
					$author_id = $user->ID;
				}
			}

			if ( $author_id ) {
				$args['post_author'] = $author_id;
			}

			if ( $updating ) {
				$args['ID'] = $id;
				$update     = wp_update_post( $args );

				if ( is_wp_error( $update ) ) {
					throw new Exception( $update->get_error_message() );
				}
			} else {
				if ( ! $title ) {
					throw new Exception( esc_html__( 'No title assigned for import.', 'posterno' ) );
				}

				$new_listing_id = wp_insert_post( $args );

				if ( is_wp_error( $new_listing_id ) ) {
					throw new Exception( $new_listing_id->get_error_message() );
				}

				$id = $new_listing_id;

			}

			// Now update all other data.
			if ( $expires_date ) {
				update_post_meta( $id, '_listing_expires', date( 'Y-m-d', strtotime( $expires_date ) ) );
			}
			if ( $featured ) {
				update_post_meta( $id, '_listing_is_featured', $featured );
			} elseif ( isset( $data['featured'] ) && ( empty( $featured ) || ! $featured ) ) {
				delete_post_meta( $id, '_listing_is_featured' );
			}
			if ( $opening_hours && is_array( $opening_hours ) && ! empty( $opening_hours ) ) {
				foreach ( $opening_hours as $day_name => $day_details ) {
					$day_opening       = isset( $day_details['opening'] ) ? $day_details['opening'] : false;
					$day_closing       = isset( $day_details['closing'] ) ? $day_details['closing'] : false;
					$operation         = isset( $day_details['operation'] ) ? $day_details['operation'] : false;
					$additiona_timings = isset( $day_details['additional_times'] ) && is_array( $day_details['additional_times'] ) && ! empty( $day_details['additional_times'] ) ? $day_details['additional_times'] : false;

					if ( $day_opening ) {
						pno_update_listing_opening_hours_by_day( $id, $day_name, 'opening', $day_opening );
					}
					if ( $day_closing ) {
						pno_update_listing_opening_hours_by_day( $id, $day_name, 'closing', $day_closing );
					}
					if ( $operation ) {
						pno_update_listing_hours_of_operation( $id, $day_name, $operation );
					}
					if ( $additiona_timings ) {
						pno_update_listing_additional_opening_hours_by_day( $id, $day_name, $additiona_timings );
					}
				}
			}

			if ( $lat && $lng ) {
				pno_update_listing_coordinates( $lat, $lng, $id );
			}

			if ( $address ) {
				pno_update_listing_address_only( $address, $id );
			} elseif ( ! $address && pno_geocoder_is_enabled() ) {
				$response = \PNO\Geocoder\Helper\Query::geocode_coordinates( $lat, $lng );
				if ( isset( $response['street'] ) && ! empty( $response['street'] ) ) {
					update_post_meta( $id, 'geocoded_data', $response );
					pno_update_listing_address_only( $response['street'], $id );
				}
			}

			if ( $featured_image ) {
				$featured_img_id = $this->get_attachment_id_from_url( $featured_image, $id );
				if ( $featured_img_id ) {
					set_post_thumbnail( $id, $featured_img_id );
				}
			}

			if ( $gallery && is_array( $gallery ) && ! empty( $gallery ) ) {
				foreach ( $gallery as $gallery_item ) {
					$att_id = $this->get_attachment_id_from_url( $gallery_item, $id );
					if ( \is_numeric( $att_id ) ) {
						$att_url = wp_get_attachment_url( $att_id );
						if ( $att_url ) {
							$images[] = [
								'url'  => $att_url,
								'path' => get_attached_file( $att_id ),
							];
						}
					}
				}
				if ( ! empty( $images ) ) {
					carbon_set_post_meta( $id, 'listing_gallery_images', $images );
				}
			}

			if ( $social_profiles && is_array( $social_profiles ) ) {
				$profiles = [];
				foreach ( $social_profiles as $profile ) {
					$profiles[] = [
						'social_id'  => isset( $profile['social_id'] ) ? $profile['social_id'] : false,
						'social_url' => isset( $profile['social_url'] ) ? $profile['social_url'] : false,
					];
				}
				if ( ! empty( $profiles ) ) {
					carbon_set_post_meta( $id, 'listing_social_profiles', $profiles );
				}
			}

			// Now update all other fields.
			$skipping = [
				'title',
				'description',
				'short_description',
				'featured_image',
				'published',
				'status',
				'expires',
				'featured',
				'opening_hours',
				'latitude',
				'longitude',
				'address',
				'gallery',
				'listing_social_profiles',
			];

			foreach ( $data as $field_key => $field_value ) {
				if ( in_array( $field_key, $skipping, true ) ) {
					continue;
				}
				if ( ! $field_value || empty( $field_value ) && ! taxonomy_exists( $field_key ) ) {
					delete_post_meta( $id, '_' . $field_key );
					continue;
				}

				if ( taxonomy_exists( $field_key ) && is_array( $field_value ) && ! empty( $field_value ) ) {
					$terms = wp_set_post_terms( $id, array_map( 'absint', $field_value ), $field_key );

					if ( is_wp_error( $terms ) ) {
						throw new Exception( $terms->get_error_message() );
					}
				} else {
					$found_field = $this->get_listing_field( $field_key );

					if ( ! $found_field ) {
						continue;
					}

					if ( $found_field->getType() === 'file' ) {
						if ( $found_field->isMultiple() ) {
							if ( is_array( $field_value ) ) {
								$files = [];
								foreach ( $field_value as $file_url_to_upload ) {
									$new_att_id = $this->get_attachment_id_from_url( $file_url_to_upload, $id );
									if ( \is_numeric( $new_att_id ) ) {
										$new_att_url = wp_get_attachment_url( $new_att_id );
										if ( $new_att_url ) {
											$files[] = [
												'url'  => $att_url,
												'path' => get_attached_file( $new_att_id ),
											];
										}
									}
								}
								if ( ! empty( $files ) ) {
									carbon_set_post_meta( $id, $field_key, $files );
								}
							}
						} else {
							if ( isset( $field_value[0] ) ) {
								$new_att_id = $this->get_attachment_id_from_url( $field_value[0], $id );
								if ( is_numeric( $new_att_id ) ) {
									carbon_set_post_meta( $id, $field_key, $new_att_id );
								}
							}
						}
					} else {
						carbon_set_post_meta( $id, $field_key, $field_value );
					}
				}
			}

			// Now update metadata.
			if ( isset( $data['meta_data'] ) && ! empty( $data['meta_data'] ) && is_array( $data['meta_data'] ) ) {
				foreach ( $data['meta_data'] as $meta_data ) {
					if ( ! isset( $meta_data['value'] ) || ! isset( $meta_data['key'] ) ) {
						continue;
					}
					update_post_meta( $id, esc_attr( $meta_data['key'] ), pno_clean( maybe_unserialize( $meta_data['value'] ) ) );
				}
			}

			do_action( 'posterno_listing_import_after_process_item', $data );

			return array(
				'id'      => $id,
				'updated' => $updating,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'posterno_listing_importer_error', $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

}
