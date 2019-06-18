<?php
/**
 * Import header.
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
		<h2><?php echo esc_html( $this->page_title ); ?></h2>
		<ul class="title-links hidden-sm-and-down">
			<li>
				<a href="https://posterno.com/addons" rel="nofollow" target="_blank" class="page-title-action"><?php esc_html_e( 'Addons', 'posterno' ); ?></a>
			</li>
			<li>
				<a href="https://docs.posterno.com/" rel="nofollow" target="_blank" class="page-title-action"><?php esc_html_e( 'Documentation', 'posterno' ); ?></a>
			</li>
		</ul>
	</div>
</div>

<div class="wrap posterno">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Import data', 'posterno' ); ?></h1>

	<div class="posterno-progress-form-wrapper">
