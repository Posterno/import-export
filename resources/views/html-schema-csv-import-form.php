<?php
/**
 * Importer schema.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<form class="pno-progress-form-content posterno-importer" enctype="multipart/form-data" method="post">
	<header>
		<h2><?php esc_html_e( 'Import schema from a CSV file', 'posterno' ); ?></h2>
		<p><?php esc_html_e( 'This tool allows you to import (or merge) schema data to your site from a CSV file.', 'posterno' ); ?></p>
	</header>
	<section>
		<table class="form-table posterno-importer-options">
			<tbody>
				<tr>
					<th scope="row">
						<label for="upload">
							<?php esc_html_e( 'Choose a CSV file from your computer:', 'posterno' ); ?>
						</label>
					</th>
					<td>
						<?php
						if ( ! empty( $upload_dir['error'] ) ) {
							?>
							<div class="inline error">
								<p><?php esc_html_e( 'Before you can upload your import file, you will need to fix the following error:', 'posterno' ); ?></p>
								<p><strong><?php echo esc_html( $upload_dir['error'] ); ?></strong></p>
							</div>
							<?php
						} else {
							?>
							<input type="file" id="upload" name="import" size="25" />
							<input type="hidden" name="action" value="save" />
							<input type="hidden" name="max_file_size" value="<?php echo esc_attr( $bytes ); ?>" />
							<br>
							<small>
								<?php
								printf(
									/* translators: %s: maximum upload size */
									esc_html__( 'Maximum size: %s', 'posterno' ),
									esc_html( $size )
								);
								?>
							</small>
							<?php
						}
					?>
					</td>
				</tr>
				<tr>
					<th><label for="posterno-importer-update-existing"><?php esc_html_e( 'Update existing schemas', 'posterno' ); ?></label><br/></th>
					<td>
						<input type="hidden" name="update_existing" value="0" />
						<input type="checkbox" id="posterno-importer-update-existing" name="update_existing" value="1" />
						<label for="posterno-importer-update-existing"><?php esc_html_e( 'Existing schemas that match by ID will be updated. Schema that do not exist will be skipped.', 'posterno' ); ?></label>
					</td>
				</tr>
				<tr class="posterno-importer-advanced hidden">
					<th>
						<label for="posterno-importer-file-url"><?php esc_html_e( 'Alternatively, enter the path to a CSV file on your server:', 'posterno' ); ?></label>
					</th>
					<td>
						<label for="posterno-importer-file-url" class="posterno-importer-file-url-field-wrapper">
							<code><?php echo esc_html( ABSPATH ) . ' '; ?></code><input type="text" id="posterno-importer-file-url" name="file_url" />
						</label>
					</td>
				</tr>
				<tr class="posterno-importer-advanced hidden">
					<th><label><?php esc_html_e( 'CSV Delimiter', 'posterno' ); ?></label><br/></th>
					<td><input type="text" name="delimiter" placeholder="," size="2" /></td>
				</tr>
				<tr class="posterno-importer-advanced hidden">
					<th><label><?php esc_html_e( 'Use previous column mapping preferences?', 'posterno' ); ?></label><br/></th>
					<td><input type="checkbox" id="posterno-importer-map-preferences" name="map_preferences" value="1" /></td>
				</tr>
			</tbody>
		</table>
	</section>
	<script type="text/javascript">
		jQuery(function() {
			jQuery( '.posterno-importer-toggle-advanced-options' ).on( 'click', function() {
				var elements = jQuery( '.posterno-importer-advanced' );
				if ( elements.is( '.hidden' ) ) {
					elements.removeClass( 'hidden' );
					jQuery( this ).text( jQuery( this ).data( 'hidetext' ) );
				} else {
					elements.addClass( 'hidden' );
					jQuery( this ).text( jQuery( this ).data( 'showtext' ) );
				}
				return false;
			} );
		});
	</script>
	<div class="pno-actions">
		<a href="#" class="posterno-importer-toggle-advanced-options" data-hidetext="<?php esc_html_e( 'Hide advanced options', 'posterno' ); ?>" data-showtext="<?php esc_html_e( 'Hide advanced options', 'posterno' ); ?>"><?php esc_html_e( 'Show advanced options', 'posterno' ); ?></a>
		<button type="submit" class="button button-primary button-next" value="<?php esc_attr_e( 'Continue', 'posterno' ); ?>" name="save_step"><?php esc_html_e( 'Continue', 'posterno' ); ?></button>
		<?php wp_nonce_field( 'posterno-csv-importer' ); ?>
	</div>
</form>
