<?php
/**
 * Importer steps.
 *
 * @package     posterno-import-export
 * @copyright   Copyright (c) 2019, Sematico, LTD
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.1.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<ol class="pno-progress-steps">
	<?php foreach ( $this->steps as $step_key => $step ) : ?>
		<?php
		$step_class = '';
		if ( $step_key === $this->step ) {
			$step_class = 'active';
		} elseif ( array_search( $this->step, array_keys( $this->steps ), true ) > array_search( $step_key, array_keys( $this->steps ), true ) ) {
			$step_class = 'done';
		}
		?>
		<li class="<?php echo esc_attr( $step_class ); ?>">
			<?php echo esc_html( $step['name'] ); ?>
		</li>
	<?php endforeach; ?>
</ol>
