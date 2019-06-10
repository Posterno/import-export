<?php
/**
 * Generic list.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Add generic mappings.
 *
 * @param array $mappings Importer columns mappings.
 * @return array
 */
function pno_importer_generic_mappings( $mappings ) {
	$generic_mappings = array(
		__( 'Title', 'posterno' )      => 'name',
		__( 'Menu order', 'posterno' ) => 'menu_order',
	);

	return array_merge( $mappings, $generic_mappings );
}
add_filter( 'posterno_csv_schema_import_mapping_default_columns', 'pno_importer_generic_mappings' );
