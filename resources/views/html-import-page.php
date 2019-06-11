<?php
/**
 * Displays content of the import page.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<div class="pno-admin-title-area">
	<div class="wrap">
		<h2><?php echo esc_html( $title ); ?></h2>
		<ul class="title-links hidden-sm-and-down">
			<li>
				<a href="https://posterno.com/addons" rel="nofollow" target="_blank" class="page-title-action"><?php esc_html_e( 'View Addons', 'posterno' ); ?></a>
			</li>
			<li>
				<a href="https://docs.posterno.com/" rel="nofollow" target="_blank" class="page-title-action"><?php esc_html_e( 'Documentation', 'posterno' ); ?></a>
			</li>
		</ul>
	</div>
</div>

<div class="wrap posterno">
	<h1 class="screen-reader-text"><?php echo esc_html( $title ); ?></h1>

	<div class="posterno-exporter-wrapper">
		<form class="posterno-exporter pno-import-form" action="<?php echo esc_url( add_query_arg( 'pno_action', 'upload_import_file', admin_url() ) ); ?>" method="post" enctype="multipart/form-data">
			<header>
				<span class="spinner is-active"></span>
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $description ); ?></p>
			</header>
			<section class="has-form">

				<div class="notice-wrap"></div>

				<div class="fields-container">
					<table class="form-table">
						<tbody>
							<?php foreach ( $form->getFields() as $field ) : ?>
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
				</div>

				<progress class="posterno-exporter-progress" max="100" value="0"></progress>

				<?php wp_nonce_field( 'pno_ajax_import', 'pno_ajax_import' ); ?>
				<input type="hidden" name="pno-import-class" value="BatchImportSchemas"/>

			</section>
			<div class="pno-actions">
				<button type="submit" class="posterno-exporter-button button button-primary" value="<?php esc_attr_e( 'Upload CSV', 'posterno' ); ?>"><?php esc_html_e( 'Upload CSV', 'posterno' ); ?></button>
			</div>
		</form>
	</div>
</div>
