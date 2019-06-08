<?php
/**
 * Displays content of the export page.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

use PosternoImportExport\Export\CsvTaxonomyExporter;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$exporter = new CsvTaxonomyExporter();

?>
<div class="pno-admin-title-area">
	<div class="wrap">
		<h2><?php esc_html_e( 'Export taxonomy terms' ); ?></h2>
		<ul class="title-links hidden-sm-and-down">
			<li>
				<a href="https://posterno.com/addons" rel="nofollow" target="_blank" class="page-title-action"><?php esc_html_e( 'View Addons' ); ?></a>
			</li>
			<li>
				<a href="https://docs.posterno.com/" rel="nofollow" target="_blank" class="page-title-action"><?php esc_html_e( 'Documentation' ); ?></a>
			</li>
		</ul>
	</div>
</div>

<div class="wrap posterno">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Export taxonomy terms' ); ?></h1>

	<div class="posterno-exporter-wrapper">
		<form class="posterno-exporter">
			<header>
				<span class="spinner is-active"></span>
				<h2><?php esc_html_e( 'Export taxonomy terms to a CSV file', 'posterno' ); ?></h2>
				<p><?php esc_html_e( 'This tool allows you to generate and download a CSV file containing a list of all taxonomy terms created on this website.', 'posterno' ); ?></p>
			</header>
			<section class="has-form">

				<table class="form-table">
					<tbody>
						<?php foreach ( $exporter->form->getFields() as $field ) : ?>
						<tr>
							<th scope="row">
								<?php if ( ! empty( $field->getLabel() ) ) : ?>
									<label for="<?php echo esc_attr( $field->getName() ); ?>"><?php echo esc_html( $field->getLabel() ); ?></label>
								<?php endif; ?>
							</th>
							<td>
								<?php echo $field->render(); ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<progress class="posterno-exporter-progress" max="100" value="0"></progress>
			</section>
			<div class="pno-actions">
				<button type="submit" class="posterno-exporter-button button button-primary" value="<?php esc_attr_e( 'Generate CSV', 'posterno' ); ?>"><?php esc_html_e( 'Generate CSV', 'posterno' ); ?></button>
			</div>
		</form>
	</div>
</div>
