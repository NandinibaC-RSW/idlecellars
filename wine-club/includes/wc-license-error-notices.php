<?php
/**
 * This is a means of catching errors from the activation method above and displaying it to the customer
 */
function edd_sample_admin_notices() {
	if ( isset( $_GET['sl_activation'] ) && ! empty( $_GET['message'] ) ) {

		switch( $_GET['sl_activation'] ) {

			case 'false':
				$message = urldecode( $_GET['message'] );
				?>
				<div class="error">
					<p><?php echo $message; ?></p>
				</div>
				<?php
				break;
			case 'true':							$message = urldecode( $_GET['message'] );								?>				<div class="updated">					<p><?php echo $message; ?></p>				</div>				<?php 								break;
			default:
				// Developers can put a custom success message here for when activation is successful if they way.
				break;
		}
	}
}
add_action( 'admin_notices', 'edd_sample_admin_notices' );