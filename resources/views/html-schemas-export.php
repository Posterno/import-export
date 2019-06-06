<?php
/**
 * Displays content of the export page.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

use PosternoImportExport\Export\CsvSchemasExporter;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$exporter = new CsvSchemasExporter();
?>
<div class="wrap woocommerce">
	<h1><?php esc_html_e( 'Export listings schemas' ); ?></h1>

	<div class="woocommerce-exporter-wrapper">
		<form class="woocommerce-exporter">
			<header>
				<span class="spinner is-active"></span>
				<h2><?php esc_html_e( 'Export schemas to a CSV file', 'woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'This tool allows you to generate and download a CSV file containing a list of all listings schemas.', 'woocommerce' ); ?></p>
			</header>
			<section>
				<progress class="woocommerce-exporter-progress" max="100" value="0"></progress>
			</section>
			<div class="wc-actions">
				<button type="submit" class="woocommerce-exporter-button button button-primary" value="<?php esc_attr_e( 'Generate CSV', 'woocommerce' ); ?>"><?php esc_html_e( 'Generate CSV', 'woocommerce' ); ?></button>
			</div>
		</form>
	</div>
</div>
