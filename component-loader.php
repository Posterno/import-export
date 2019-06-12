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
			'listings_page_emails_exporter',
			'listings_page_listings_fields_exporter',
			'listings_page_profile_fields_exporter',
			'listings_page_registration_fields_exporter',
			'listings_page_taxonomy_exporter',
			'listings_page_listings_exporter',
			'listings_page_schemas_importer',
		];

		wp_register_style( 'pno-admin-export-import', PNO_PLUGIN_URL . '/vendor/posterno/import-export/dist/css/screen.css', false, PNO_VERSION );

		wp_register_script( 'pno-schemas-export', PNO_PLUGIN_URL . '/vendor/posterno/import-export/dist/js/pno-schemas-export.js', array( 'jquery' ), PNO_VERSION, true );
		wp_register_script( 'pno-emails-export', PNO_PLUGIN_URL . '/vendor/posterno/import-export/dist/js/pno-emails-export.js', array( 'jquery' ), PNO_VERSION, true );
		wp_register_script( 'pno-listings-fields-export', PNO_PLUGIN_URL . '/vendor/posterno/import-export/dist/js/pno-listings-fields-export.js', array( 'jquery' ), PNO_VERSION, true );
		wp_register_script( 'pno-profile-fields-export', PNO_PLUGIN_URL . '/vendor/posterno/import-export/dist/js/pno-profile-fields-export.js', array( 'jquery' ), PNO_VERSION, true );
		wp_register_script( 'pno-registration-fields-export', PNO_PLUGIN_URL . '/vendor/posterno/import-export/dist/js/pno-registration-fields-export.js', array( 'jquery' ), PNO_VERSION, true );
		wp_register_script( 'pno-taxonomy-export', PNO_PLUGIN_URL . '/vendor/posterno/import-export/dist/js/pno-taxonomy-export.js', array( 'jquery' ), PNO_VERSION, true );
		wp_register_script( 'pno-listings-export', PNO_PLUGIN_URL . '/vendor/posterno/import-export/dist/js/pno-listings-export.js', array( 'jquery' ), PNO_VERSION, true );

		wp_register_script( 'pno-ajax-import-script', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.form/4.2.2/jquery.form.min.js', false, PNO_VERSION, true );
		wp_register_script( 'pno-importers', PNO_PLUGIN_URL . '/vendor/posterno/import-export/dist/js/pno-import.js', array( 'jquery' ), PNO_VERSION, true );

		if ( in_array( $screen->id, $ids, true ) ) {
			wp_enqueue_style( 'pno-admin-export-import' );
			wp_enqueue_style( 'pno-options-panel', PNO_PLUGIN_URL . '/assets/css/admin/admin-settings-panel.min.css', false, PNO_VERSION );
		}

		// Schemas.
		if ( $screen->id === 'listings_page_schemas_exporter' ) {
			wp_enqueue_script( 'pno-schemas-export' );
			wp_localize_script(
				'pno-schemas-export',
				'pno_schemas_export_params',
				array(
					'export_nonce' => wp_create_nonce( 'pno-schemas-export' ),
				)
			);
		}

		// Emails.
		if ( $screen->id === 'listings_page_emails_exporter' ) {
			wp_enqueue_script( 'pno-emails-export' );
			wp_localize_script(
				'pno-emails-export',
				'pno_emails_export_params',
				array(
					'export_nonce' => wp_create_nonce( 'pno-emails-export' ),
				)
			);
		}

		// Listings fields.
		if ( $screen->id === 'listings_page_listings_fields_exporter' ) {
			wp_enqueue_script( 'pno-listings-fields-export' );
			wp_localize_script(
				'pno-listings-fields-export',
				'pno_listings_fields_export_params',
				array(
					'export_nonce' => wp_create_nonce( 'pno-listings-fields-export' ),
				)
			);
		}

		// Profile fields.
		if ( $screen->id === 'listings_page_profile_fields_exporter' ) {
			wp_enqueue_script( 'pno-profile-fields-export' );
			wp_localize_script(
				'pno-profile-fields-export',
				'pno_profile_fields_export_params',
				array(
					'export_nonce' => wp_create_nonce( 'pno-profile-fields-export' ),
				)
			);
		}

		// Registration fields.
		if ( $screen->id === 'listings_page_registration_fields_exporter' ) {
			wp_enqueue_script( 'pno-registration-fields-export' );
			wp_localize_script(
				'pno-registration-fields-export',
				'pno_registration_fields_export_params',
				array(
					'export_nonce' => wp_create_nonce( 'pno-registration-fields-export' ),
				)
			);
		}

		// Taxonomy.
		if ( $screen->id === 'listings_page_taxonomy_exporter' ) {
			wp_enqueue_script( 'pno-taxonomy-export' );
			wp_localize_script(
				'pno-taxonomy-export',
				'pno_taxonomy_export_params',
				array(
					'export_nonce' => wp_create_nonce( 'pno-taxonomy-export' ),
				)
			);
		}

		// Listings.
		if ( $screen->id === 'listings_page_listings_exporter' ) {
			wp_enqueue_style( 'pno-select2-style', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css', false, PNO_VERSION );
			wp_enqueue_script( 'pno-select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js', array( 'jquery' ), PNO_VERSION, true );
			wp_enqueue_script( 'pno-listings-export' );
			wp_localize_script(
				'pno-listings-export',
				'pno_listings_export_params',
				array(
					'export_nonce' => wp_create_nonce( 'pno-listings-export' ),
				)
			);
		}

		$importers = [
			'listings_page_schemas_importer',
		];

		if ( in_array( $screen->id, $importers, true ) ) {
			wp_enqueue_script( 'pno-ajax-import-script' );
			wp_enqueue_script( 'pno-importers' );
		}

	},
	20
);
