<?php
/**
 * Admin View: Importer - CSV import progress
 *
 * @package WooCommerce\Admin\Importers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="pno-progress-form-content posterno-importer posterno-importer__importing">
	<header>
		<span class="spinner is-active"></span>
		<h2><?php esc_html_e( 'Importing', 'posterno' ); ?></h2>
		<p><?php esc_html_e( 'Your products are now being imported...', 'posterno' ); ?></p>
	</header>
	<section>
		<progress class="posterno-importer-progress" max="100" value="0"></progress>
	</section>
</div>
