<?php
/**
 * Hooks the component to WordPress.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

namespace PosternoImportExport\Import;

use PosternoImportExport\Import\BatchImportSchemas;

use PNO\Form\Form;
use PNO\Form\DefaultSanitizer;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Hook to the admin panel.
 */
class Admin {

	use DefaultSanitizer;

	/**
	 * List of registered importers.
	 *
	 * @var array
	 */
	public $importers = [];

	/**
	 * Get things started.
	 */
	public function __construct() {

		if ( ! $this->export_allowed() ) {
			return;
		}

		$this->importers['schemas_importer'] = array(
			'menu'             => 'edit.php?post_type=listings',
			'name'             => esc_html__( 'Import listings schemas', 'posterno' ),
			'capability'       => 'manage_options',
			'callback'         => array( $this, 'importer_page' ),
			'url'              => admin_url( 'edit.php?post_type=listings&page=schemas_importer' ),
			'page_title'       => esc_html__( 'Import listings schemas', 'posterno' ),
			'page_description' => esc_html__( 'This tool allows you to import listings schemas from a CSV file.', 'posterno' ),
		);

		$this->hook();

	}

	/**
	 * Return true if import is allowed for current user, false otherwise.
	 *
	 * @return bool Whether current user can perform import.
	 */
	protected function export_allowed() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Add all importers to the admin menu.
	 *
	 * @return void
	 */
	public function add_to_menus() {
		foreach ( $this->importers as $id => $importer ) {
			add_submenu_page( $importer['menu'], $importer['name'], $importer['name'], $importer['capability'], $id, $importer['callback'] );
		}
	}

	/**
	 * Hook into WP.
	 *
	 * @return void
	 */
	public function hook() {

		add_action( 'admin_menu', array( $this, 'add_to_menus' ) );
		add_action( 'admin_head', array( $this, 'hide_from_menus' ) );
		add_action( 'pno_tools_import', [ $this, 'register_importers_list' ], 20 );

		add_action( 'pno_upload_import_file', [ $this, 'do_ajax_import_file_upload' ] );
		add_action( 'wp_ajax_pno_do_ajax_import', [ $this, 'do_ajax_import' ] );

	}

	/**
	 * Hide all importers from the main menu.
	 *
	 * @return void
	 */
	public function hide_from_menus() {
		global $submenu;
		foreach ( $this->importers as $id => $importer ) {
			if ( isset( $submenu[ $importer['menu'] ] ) ) {
				foreach ( $submenu[ $importer['menu'] ] as $key => $menu ) {
					if ( $id === $menu[2] ) {
						unset( $submenu[ $importer['menu'] ][ $key ] );
					}
				}
			}
		}
	}

	/**
	 * Get the currently activate importer.
	 *
	 * @return string
	 */
	public function get_current_importer() {

		$screen   = get_current_screen();
		$importer = false;

		if ( $screen->id === 'listings_page_schemas_importer' ) {
			$importer = 'schemas_importer';
		}

		return $importer;

	}

	/**
	 * Get title of the currently active importer.
	 *
	 * @return string
	 */
	public function get_importer_title() {
		return $this->importers[ $this->get_current_importer() ]['page_title'];
	}

	/**
	 * Get description of the currently active importer.
	 *
	 * @return string
	 */
	public function get_importer_description() {
		return $this->importers[ $this->get_current_importer() ]['page_description'];
	}

	/**
	 * Get the config form for the importer.
	 *
	 * @return Form
	 */
	public function get_importer_form() {

		$importer = $this->get_current_importer();

		$fields = [
			'pno-import-file' => [
				'type'  => 'file',
				'label' => esc_html__( 'Select CSV file', 'posterno' ),
			],
		];

		$form = Form::createFromConfig( $fields );
		$this->addSanitizer( $form );

		return $form;

	}

	/**
	 * Display content for the importers.
	 *
	 * @return void
	 */
	public function importer_page() {

		$title       = $this->get_importer_title();
		$description = $this->get_importer_description();
		$form        = $this->get_importer_form();

		include PNO_PLUGIN_DIR . 'vendor/posterno/import-export/resources/views/html-import-page.php';

	}

	/**
	 * Register importers into the tools page.
	 *
	 * @return void
	 */
	public function register_importers_list() {
		include PNO_PLUGIN_DIR . '/vendor/posterno/import-export/resources/views/import-tool.php';
	}

	/**
	 * Delete previously uploaded csv file if it exists.
	 *
	 * @return void
	 */
	private function delete_stored_csv_file() {

		$class_name = sanitize_text_field( $_POST['pno-import-class'] );

		$current_stored_file = get_option( "pno_csv_file_{$class_name}_path" );

		if ( is_array( $current_stored_file ) && isset( $current_stored_file['file'] ) && file_exists( $current_stored_file['file'] ) && pno_starts_with( $current_stored_file['file'], WP_CONTENT_DIR ) ) {
			wp_delete_file( $current_stored_file['file'] );
		}

		delete_option( "pno_csv_file_{$class_name}" );

	}

	/**
	 * Upload CSV File.
	 *
	 * @return void
	 */
	public function do_ajax_import_file_upload() {

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// require_once EDD_PLUGIN_DIR . 'includes/admin/import/class-batch-import.php';
		if ( ! wp_verify_nonce( $_REQUEST['pno_ajax_import'], 'pno_ajax_import' ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'posterno' ) ) );
		}

		if ( empty( $_POST['pno-import-class'] ) ) {
			wp_send_json_error(
				array(
					'error'   => __( 'Missing import parameters. Import class must be specified.', 'posterno' ),
					'request' => $_REQUEST,
				)
			);
		}
		if ( empty( $_FILES['pno-import-file'] ) ) {
			wp_send_json_error(
				array(
					'error'   => __( 'Missing import file. Please provide an import file.', 'posterno' ),
					'request' => $_REQUEST,
				)
			);
		}
		$accepted_mime_types = array(
			'text/csv',
			'text/comma-separated-values',
			'text/plain',
			'text/anytext',
			'text/*',
			'text/plain',
			'text/anytext',
			'text/*',
			'application/csv',
			'application/excel',
			'application/vnd.ms-excel',
			'application/vnd.msexcel',
		);
		if ( empty( $_FILES['pno-import-file']['type'] ) || ! in_array( strtolower( $_FILES['pno-import-file']['type'] ), $accepted_mime_types ) ) {
			$this->delete_stored_csv_file();

			wp_send_json_error(
				array(
					'error'   => __( 'The file you uploaded does not appear to be a CSV file.', 'posterno' ),
					'request' => $_REQUEST,
				)
			);
		}
		if ( ! file_exists( $_FILES['pno-import-file']['tmp_name'] ) ) {
			$this->delete_stored_csv_file();

			wp_send_json_error(
				array(
					'error'   => __( 'Something went wrong during the upload process, please try again.', 'posterno' ),
					'request' => $_REQUEST,
				)
			);
		}

		// Let WordPress import the file. We will remove it after import is complete
		$import_file = wp_handle_upload( $_FILES['pno-import-file'], array( 'test_form' => false ) );

		if ( $import_file && empty( $import_file['error'] ) ) {

			do_action( 'pno_batch_import_class_include', $_POST['pno-import-class'] );

			$class_name = sanitize_text_field( $_POST['pno-import-class'] );

			$import = false;

			if ( $class_name === 'BatchImportSchemas' ) {
				$import = new BatchImportSchemas( $import_file['file'] );
			}

			// Delete any previously uploaded file.
			$this->delete_stored_csv_file();

			// Store file path for later use.
			update_option( "pno_csv_file_{$class_name}", $import_file );

			if ( ! $import->can_import() ) {
				wp_send_json_error( array( 'error' => __( 'You do not have permission to import data', 'posterno' ) ) );
			}

			$mapping_form = $this->get_mapping_form( $import_file, $class_name );

			wp_send_json_success(
				array(
					'form'         => $_POST,
					'class'        => $class_name,
					'upload'       => $import_file,
					'first_row'    => $import->get_first_row(),
					'columns'      => $import->get_columns(),
					'nonce'        => wp_create_nonce( 'pno_ajax_import', 'pno_ajax_import' ),
					'mapping_form' => esc_js( str_replace( "\n", '', $mapping_form ) ),
				)
			);
		} else {

			$this->delete_stored_csv_file();

			/**
			 * Error generated by _wp_handle_upload()
			 *
			 * @see _wp_handle_upload() in wp-admin/includes/file.php
			 */
			wp_send_json_error( array( 'error' => $import_file['error'] ) );
		}
		exit;

	}

	/**
	 * Get output of the mapping form.
	 *
	 * @param array $file CSV file to parse.
	 * @param string $importer importer class.
	 * @return string
	 */
	public function get_mapping_form( $file, $importer ) {

		$fields = [
			'test' => [
				'type'  => 'select',
				'label' => esc_html__( 'Select CSV file', 'posterno' ),
			],
		];

		$form = Form::createFromConfig( $fields );
		$this->addSanitizer( $form );

		$output = false;

		ob_start();

		?>
		<table class="widefat striped" style="margin:30px 0;">
			<thead>
				<tr>
					<th><strong><?php esc_html_e( 'Data field' ); ?></strong></th>
					<th><strong><?php esc_html_e( 'CSV Column' ); ?></strong></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $form->getFields() as $field ) : ?>
				<tr>
					<td>
						<?php if ( ! empty( $field->getLabel() ) ) : ?>
							<label for="<?php echo esc_attr( $field->getName() ); ?>"><?php echo esc_html( $field->getLabel() ); ?></label>
						<?php endif; ?>
					</td>
					<td>
						<?php echo $field->render(); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php

		$output = ob_get_clean();

		return $output;

	}

	/**
	 * Process ajax import.
	 *
	 * @return void
	 */
	public function do_ajax_import() {

		// require_once EDD_PLUGIN_DIR . 'includes/admin/import/class-batch-import.php';
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'pno_ajax_import' ) ) {
			wp_send_json_error(
				array(
					'error'   => __( 'Nonce verification failed', 'posterno' ),
					'request' => $_REQUEST,
				)
			);
		}

		if ( empty( $_REQUEST['class'] ) ) {
			wp_send_json_error(
				array(
					'error'   => __( 'Missing import parameters. Import class must be specified.', 'posterno' ),
					'request' => $_REQUEST,
				)
			);
		}

		if ( ! file_exists( $_REQUEST['upload']['file'] ) ) {
			wp_send_json_error(
				array(
					'error'   => __( 'Something went wrong during the upload process, please try again.', 'posterno' ),
					'request' => $_REQUEST,
				)
			);
		}

		do_action( 'pno_batch_import_class_include', $_REQUEST['class'] );

		$step   = absint( $_REQUEST['step'] );
		$class  = $_REQUEST['class'];
		$import = new $class( $_REQUEST['upload']['file'], $step );

		if ( ! $import->can_import() ) {

			wp_send_json_error( array( 'error' => __( 'You do not have permission to import data', 'posterno' ) ) );

		}

		parse_str( $_REQUEST['mapping'], $map );

		$import->map_fields( $map['pno-import-field'] );

		$ret = $import->process_step( $step );

		$percentage = $import->get_percentage_complete();

		if ( $ret ) {

			$step += 1;
			wp_send_json_success(
				array(
					'step'       => $step,
					'percentage' => $percentage,
					'columns'    => $import->get_columns(),
					'mapping'    => $import->field_mapping,
					'total'      => $import->total,
				)
			);

		} elseif ( true === $import->is_empty ) {

			wp_send_json_error(
				array(
					'error' => __( 'No data found for import parameters', 'posterno' ),
				)
			);

		} else {

			wp_send_json_success(
				array(
					'step'    => 'done',
					'message' => sprintf(
						__( 'Import complete! <a href="%1$s">View imported %2$s</a>.', 'posterno' ),
						$import->get_list_table_url(),
						$import->get_import_type_label()
					),
				)
			);

		}

	}

}
