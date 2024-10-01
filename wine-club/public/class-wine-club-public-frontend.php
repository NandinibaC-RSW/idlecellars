<?php
if ( ! function_exists( 'woocommerce_template_single_add_to_cart' ) ) {
	function woocommerce_template_single_add_to_cart() {

		global $product;

		if(!function_exists('wp_get_current_user')) {
			include(ABSPATH . "wp-includes/pluggable.php"); 
		}

		$user = wp_get_current_user();
		$role = ( array ) $user->roles;

		if($role[0] == 'wc_member' || $role[0] == 'administrator' || !in_array(13, $product->category_ids)) {	

			do_action( 'woocommerce_' . $product->product_type . '_add_to_cart'  );

		}else{

		    //	echo '<div class="login-link"><a href="'.home_url('my-account').'" class="">Login</a></div>';
			do_action( 'woocommerce_' . $product->product_type . '_add_to_cart'  );
		}

	}
}
add_action( 'woocommerce_after_shop_loop_item', 'remove_add_to_cart_buttons', 1 );
function remove_add_to_cart_buttons()
{
	$wineClubSettings = get_option( 'wineClubSettings' );
	if(isset($wineClubSettings['availablePublicMembers']) && $wineClubSettings['availablePublicMembers'] == 1)
	{
		if ( !is_user_logged_in() )
		{ 
			remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart' );
		}		
	}
}
add_action('woocommerce_after_shop_loop_item','replace_add_to_cart');
function replace_add_to_cart()
{
	$wineClubSettings = get_option( 'wineClubSettings' );
	if(isset($wineClubSettings['availablePublicMembers']) && $wineClubSettings['availablePublicMembers'] == 1)
	{
		if ( !is_user_logged_in() )
		{ 
			global $product;
			//$url = get_site_url();
			$url = get_permalink( get_option('woocommerce_myaccount_page_id') );
			echo '<div class="add-to-cart-wrap" data-toggle="tooltip" data-placement="top" title="" data-tooltip-added-text="Login" data-original-title="Login"><a href="'.$url.'" class="button product_type_simple add_to_cart_button ajax_add_to_cart product_type_simple" data-default_icon="sf-icon-account"><i class="sf-icon-account"></i><span>Add to cart</span></a></div>';
		}
	}
}

// Update order status to complete for club connection product only
# Code added on 05-06-2019
add_action( 'woocommerce_payment_complete', 'my_change_status_function' );
function my_change_status_function( $order_id ) {
	$order = wc_get_order( $order_id );
	$products = $order->get_items();
	$items = array();
	foreach($products as $prod){
		if( stripos( $prod['name'], 'Club' ) !== false) {
			$order->update_status( 'completed' );
		}
		$items[$prod['product_id']] = $prod['name'];
	}
}