<?php
/**
 * Generic mappings
 *
 * @package WooCommerce\Admin\Importers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add generic mappings.
 *
 * @since 3.1.0
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
