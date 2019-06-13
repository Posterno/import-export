<?php
/**
 * Default mappings
 *
 * @package WooCommerce\Admin\Importers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Importer current locale.
 *
 * @since 3.1.0
 * @return string
 */
function pno_importer_current_locale() {
	$locale = get_locale();
	if ( function_exists( 'get_user_locale' ) ) {
		$locale = get_user_locale();
	}

	return $locale;
}

/**
 * Add English mapping placeholders when not using English as current language.
 *
 * @since 3.1.0
 * @param array $mappings Importer columns mappings.
 * @return array
 */
function pno_importer_default_english_mappings( $mappings ) {
	if ( 'en_US' === pno_importer_current_locale() ) {
		return $mappings;
	}

	$new_mappings = array(
		'ID'                => 'id',
		'Name'              => 'name',
		'Short description' => 'short_description',
		'Description'       => 'description',
		'Position'          => 'menu_order',
	);

	return array_merge( $mappings, $new_mappings );
}
add_filter( 'posterno_csv_schema_import_mapping_default_columns', 'pno_importer_default_english_mappings', 100 );

/**
 * Add English special mapping placeholders when not using English as current language.
 *
 * @since 3.1.0
 * @param array $mappings Importer columns mappings.
 * @return array
 */
function pno_importer_default_special_english_mappings( $mappings ) {
	if ( 'en_US' === pno_importer_current_locale() ) {
		return $mappings;
	}

	$new_mappings = array(
		'Meta: %s' => 'meta:',
	);

	return array_merge( $mappings, $new_mappings );
}
add_filter( 'posterno_csv_schema_import_mapping_special_columns', 'pno_importer_default_special_english_mappings', 100 );
