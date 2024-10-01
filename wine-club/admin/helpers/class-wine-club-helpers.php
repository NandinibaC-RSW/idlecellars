<?php

class Wine_Club_Helpers {

	public static function ifProductIsInDiscountCategory($membershipLevel, $product) {
		$categories = get_the_terms( $product->get_id(), 'product_cat' );

		if(!$membershipLevel) {
			return false;
		}

		if($membershipLevel->discountCategories) {
			$checkedCategories = unserialize($membershipLevel->discountCategories);			
		} else {
			$checkedCategories = [];
		}

		if($categories) {
			foreach ($categories as $category) {
				// If product is in club connection discount categories
				if(in_array($category->term_id, $checkedCategories)) {
						return true;
				}
			}
		}
		

		return false;
	}

}