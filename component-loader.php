<?php
/**
 * Hooks the component to WordPress.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( is_admin() ) {
	new PosternoImportExport\Export\Admin();
}

/**
 * Load assets when appropriate.
 */
add_action(
	'admin_enqueue_scripts',
	function() {

		$screen = get_current_screen();

		$ids = [
			'listings_page_schemas_exporter',
		];

		wp_register_style( 'pno-admin-export-import', PNO_PLUGIN_URL . '/vendor/posterno/import-export/dist/css/screen.css', false, PNO_VERSION );
		wp_register_script( 'pno-schemas-export', PNO_PLUGIN_URL . '/vendor/posterno/import-export/dist/js/pno-schemas-export.js', array( 'jquery' ), PNO_VERSION, true );

		if ( in_array( $screen->id, $ids ) ) {
			wp_enqueue_style( 'pno-admin-export-import' );
			wp_enqueue_script( 'pno-schemas-export' );
		}

		wp_localize_script(
			'pno-schemas-export',
			'pno_schemas_export_params',
			array(
				'export_nonce' => wp_create_nonce( 'pno-schemas-export' ),
			)
		);

	},
	20
);

/**
 * "Register" the export tools.
 */
add_action(
	'pno_tools_export',
	function() {

		require_once dirname( __FILE__ ) . '/resources/views/export-tool.php';

	},
	20
);
