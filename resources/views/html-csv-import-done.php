<?php
/**
 * Import done step.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>

<div class="pno-progress-form-content posterno-importer">
	<section class="posterno-importer-done">
		<?php
		$results = array();

		if ( 0 < $imported ) {
			$results[] = sprintf(
				/* translators: %d: items count */
				_n( '%s item imported', '%s items imported', $imported, 'posterno' ),
				'<strong>' . number_format_i18n( $imported ) . '</strong>'
			);
		}

		if ( 0 < $updated ) {
			$results[] = sprintf(
				/* translators: %d: items count */
				_n( '%s item updated', '%s items updated', $updated, 'posterno' ),
				'<strong>' . number_format_i18n( $updated ) . '</strong>'
			);
		}

		if ( 0 < $skipped ) {
			$results[] = sprintf(
				/* translators: %d: items count */
				_n( '%s item was skipped', '%s items were skipped', $skipped, 'posterno' ),
				'<strong>' . number_format_i18n( $skipped ) . '</strong>'
			);
		}

		if ( 0 < $failed ) {
			$results [] = sprintf(
				/* translators: %d: items count */
				_n( 'Failed to import %s item', 'Failed to import %s items', $failed, 'posterno' ),
				'<strong>' . number_format_i18n( $failed ) . '</strong>'
			);
		}

		if ( 0 < $failed || 0 < $skipped ) {
			$results[] = '<a href="#" class="posterno-importer-done-view-errors">' . __( 'View import log', 'posterno' ) . '</a>';
		}

		/* translators: %d: import results */
		echo wp_kses_post( __( 'Import complete!', 'posterno' ) . ' ' . implode( '. ', $results ) );
		?>
	</section>
	<section class="pno-importer-error-log" style="display:none">
		<table class="widefat pno-importer-error-log-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Item', 'posterno' ); ?></th>
					<th><?php esc_html_e( 'Reason for failure', 'posterno' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( count( $errors ) ) {
					foreach ( $errors as $error ) {
						if ( ! is_wp_error( $error ) ) {
							continue;
						}
						$error_data = $error->get_error_data();
						?>
						<tr>
							<th><code><?php echo esc_html( $error_data['row'] ); ?></code></th>
							<td><?php echo esc_html( $error->get_error_message() ); ?></td>
						</tr>
						<?php
					}
				}
				?>
			</tbody>
		</table>
	</section>
	<script type="text/javascript">
		jQuery(function() {
			jQuery( '.posterno-importer-done-view-errors' ).on( 'click', function() {
				jQuery( '.pno-importer-error-log' ).slideToggle();
				return false;
			} );
		} );
	</script>
	<div class="pno-actions">
		<a class="button button-primary" href="<?php echo esc_url( $this->page_done_url ); ?>"><?php echo sprintf( esc_html__( 'View %s' ), esc_html( $this->page_item_label ) ); ?></a>
	</div>
</div>
