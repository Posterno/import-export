<?php
/**
 * Importer interface.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

namespace PosternoImportExport\Import;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Interface class.
 */
interface ImporterInterface {

	/**
	 * Process importation.
	 * Returns an array with the imported and failed items.
	 * 'imported' contains a list of IDs.
	 * 'failed' contains a list of WP_Error objects.
	 *
	 * Example:
	 * ['imported' => [], 'failed' => []]
	 *
	 * @return array
	 */
	public function import();

	/**
	 * Get file raw keys.
	 *
	 * CSV - Headers.
	 * XML - Element names.
	 * JSON - Keys
	 *
	 * @return array
	 */
	public function get_raw_keys();

	/**
	 * Get file mapped headers.
	 *
	 * @return array
	 */
	public function get_mapped_keys();

	/**
	 * Get raw data.
	 *
	 * @return array
	 */
	public function get_raw_data();

	/**
	 * Get parsed data.
	 *
	 * @return array
	 */
	public function get_parsed_data();

	/**
	 * Get file pointer position from the last read.
	 *
	 * @return int
	 */
	public function get_file_position();

	/**
	 * Get file pointer position as a percentage of file size.
	 *
	 * @return int
	 */
	public function get_percent_complete();
}
