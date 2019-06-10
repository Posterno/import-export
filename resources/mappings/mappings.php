<?php
/**
 * Load automatic mappings.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

require dirname( __FILE__ ) . '/default.php';
require dirname( __FILE__ ) . '/generic.php';
require dirname( __FILE__ ) . '/wordpress.php';
