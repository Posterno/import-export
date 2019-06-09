<?php
/**
 * Displays the list of content available for export.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>

<div class="postbox" id="export-content">
	<h2 class="hndle ui-sortable-handle">
		<span><?php esc_html_e( 'Export content', 'posterno' ); ?></span>
	</h2>
	<div class="inside">
		<table class="widefat striped health-check-table" role="presentation">
			<?php foreach( $this->exporters as $tool ) : ?>
				<tr>
					<td style="width:200px">
						<strong><?php echo esc_html( $tool['name'] ); ?></strong>
					</td>
					<td>
						<a href="<?php echo esc_url( $tool['url'] ); ?>" class="button2"><?php esc_html_e( 'Export', 'posterno' ); ?> &rarr;</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>
</div>
