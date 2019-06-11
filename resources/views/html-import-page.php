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
		<form class="posterno-exporter">
			<header>
				<span class="spinner is-active"></span>
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $description ); ?></p>
			</header>
			<section class="has-form">

			</section>
			<div class="pno-actions">
				<button type="submit" class="posterno-exporter-button button button-primary" value="<?php esc_attr_e( 'Upload CSV', 'posterno' ); ?>"><?php esc_html_e( 'Upload CSV', 'posterno' ); ?></button>
			</div>
		</form>
	</div>
</div>
