<?php
/**
 * WP mappings.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Add mappings for WordPress tables.
 *
 * @param array $mappings Importer columns mappings.
 * @return array
 */
function pno_importer_wordpress_mappings( $mappings ) {

	$wp_mappings = array(
		'post_id'      => 'id',
		'post_title'   => 'name',
		'post_content' => 'description',
		'post_excerpt' => 'short_description',
		'post_parent'  => 'parent_id',
	);

	return array_merge( $mappings, $wp_mappings );
}
add_filter( 'posterno_csv_product_import_mapping_default_columns', 'pno_importer_wordpress_mappings' );
