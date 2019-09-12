<?php
/**
 * Displays content of the export page.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

use PosternoImportExport\Export\CsvProfileFieldsExporter;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$exporter = new CsvProfileFieldsExporter();

?>
<div class="pno-admin-title-area">
	<div class="wrap">
		<h2><?php esc_html_e( 'Export profile custom fields', 'posterno' ); ?></h2>
		<ul class="title-links hidden-sm-and-down">
			<li>
				<a href="https://posterno.com/extensions" rel="nofollow" target="_blank" class="page-title-action"><?php esc_html_e( 'Extensions', 'posterno' ); ?></a>
			</li>
			<li>
				<a href="https://docs.posterno.com/" rel="nofollow" target="_blank" class="page-title-action"><?php esc_html_e( 'Documentation', 'posterno' ); ?></a>
			</li>
		</ul>
	</div>
</div>

<div class="wrap posterno">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Export profile custom fields', 'posterno' ); ?></h1>

	<div class="posterno-exporter-wrapper">
		<form class="posterno-exporter">
			<header>
				<span class="spinner is-active"></span>
				<h2><?php esc_html_e( 'Export profile custom fields to a CSV file', 'posterno' ); ?></h2>
				<p><?php esc_html_e( 'This tool allows you to generate and download a CSV file containing a list of all profile custom fields configured on this website.', 'posterno' ); ?></p>
			</header>
			<section>
				<progress class="posterno-exporter-progress" max="100" value="0"></progress>
			</section>
			<div class="pno-actions">
				<button type="submit" class="posterno-exporter-button button button-primary" value="<?php esc_attr_e( 'Generate CSV', 'posterno' ); ?>"><?php esc_html_e( 'Generate CSV', 'posterno' ); ?></button>
			</div>
		</form>
	</div>
</div>
