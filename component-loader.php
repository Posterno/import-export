<?php
/**
 * Hooks the component to WordPress.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * "Register" the export tools.
 */
add_action(
	'pno_tools_export',
	function() {

		require_once dirname( __FILE__ ) . '/resources/views/export-tool.php';

	},
	20
);
