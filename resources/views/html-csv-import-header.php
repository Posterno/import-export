<?php
/**
 * Importer header.
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
		<h2><?php esc_html_e( 'Import data', 'posterno' ); ?></h2>
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
	<h1 class="screen-header-text"></h1>

	<div class="posterno-progress-form-wrapper">
