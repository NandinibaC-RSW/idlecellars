<?php
// Get the plugin version
$plugin_data = get_plugin_data(ABSPATH . 'wp-content/plugins/woocommerce-gateway-authorize-net-cim/woocommerce-gateway-authorize-net-cim.php');
$plugin_version = $plugin_data['Version'];

if($plugin_version >= '3.10.2'){ 
	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/processPayments/class-wine-club-authorizenet-advanced.php';
}else{
	require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/processPayments/class-wine-club-authorizenet.php';
}
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/processPayments/class-wine-club-stripe.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/processPayments/class-wine-club-square.php';

class Wine_Club_Batch_Orders_Payments {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	public function runWineClubMemberPayments() {
		global $pagenow;

		if ( 'post.php' != $pagenow || !isset($_GET['orderId']) || !isset($_GET['action']) || !$_GET['orderId'] || $_GET['action'] != 'processPayment') {
      return;
    }
		$retrieved_nonce = $_REQUEST['_wpnonce'];
		if (!wp_verify_nonce($retrieved_nonce, 'processPayment' ) ) die( 'Failed security check' );

		if (file_exists('wineClubDebug.txt')) {
		  $file = fopen('wineClubDebug.txt', 'a');
		} else {
		  $file = fopen('wineClubDebug.txt', 'w');
		}
		$order = new WC_Order($_GET['orderId']);
    $userId = get_post_meta( $order->get_id(), '_customer_user', true );

    fwrite($file, "\n\n". 'Club connection run on '. date_create('now')->format('Y-m-d H:i:s'));
    fwrite($file, "\n". 'User ID '. $userId.', Order ID '. $_GET['orderId']);

    $settings = get_option('wineClubSettings');

    if($settings['paymentProcessor'] == 'authorizeNet') {
      $response = (new Wine_Club_AuthorizeNet($order, $userId, $file))->run();
    }
    elseif($settings['paymentProcessor'] == 'stripe') {
      $response = (new Wine_Club_Stripe($order, $userId, $file))->run();
    }
    elseif($settings['paymentProcessor'] == 'squareUp') {
      $response = (new Wine_Club_Square($order, $userId, $file))->run();
    }
    else {
      $txt = 'Please select payment processor under club connection settings';
      $url =  admin_url().'post.php?post='.$_GET['orderId'].'&action=edit&response='.urlencode($txt);

      wp_redirect($url);
      exit;
    }

    if ($response->success == true) {
		    $order->update_status("processing");
		    $this->sendEmail(['customer_processing_order', 'new_order'], $order);

		    // Check if $response->message is available and set accordingly
		    if ($response->message) {
		        $message = 'STATUS: ' . $response->message;
		    } else {
		        $message = 'Order created successfully';
		    }

		    $txt = '<h3 style="padding: 20px 0;clear:both;">';
		    $txt .= get_user_meta($userId, 'billing_first_name', true).' '.get_user_meta($userId, 'billing_last_name', true).' '.$message;
		    $txt .= '<hr>';

		    fwrite($file, "\n". $txt);
		} else {
		    $order->update_status("failed");

		    // Check if $response->message is available and set accordingly
		    if ($response->message) {
		        $message = 'STATUS: ' . $response->message;
		    } else {
		        $message = 'Order failed';
		    }

		    $txt = '<h3 style="padding: 20px 0;clear:both;">';
		    $txt .= get_user_meta($userId, 'billing_first_name', true).' '.get_user_meta($userId, 'billing_last_name', true).' '.$message;
		    $txt .= '</h3>';

		    fwrite($file, "\n". $txt);
		}

		$url =  admin_url().'post.php?post='.$_GET['orderId'].'&action=edit&response='.urlencode($txt);
		
    wp_redirect($url);
		exit;
	}

	private function sendEmail(array $emailTypes, $order) {
		$mailer = WC()->mailer();
		$mails = $mailer->get_emails();
		if ( ! empty( $mails ) ) {
		    foreach ( $mails as $mail ) {
		        if ( in_array($mail->id, $emailTypes)) {
		           $mail->trigger( $order->get_id() );
		        }
		     }
		}
	}

	public function addProcessPaymentToOpenOrder( $order ) {
		if(isset($_GET['response'])) echo '<div><p class="form-field form-field-wide">'.urldecode($_GET['response']).'<p></div>';
		if($order->get_status() == 'pending'): ?>
		    <div class="order_data_column">
                <br>
                <a class="button" href="?orderId=<?php echo $order->get_id() ?>&action=processPayment&_wpnonce=<?php echo wp_create_nonce('processPayment') ?>">
                    <?php _e('Process payment'); ?>
                </a>
		    </div>
		<?php endif;
	}
}