<?php
class User {

	public static function checkIfUserProcessManullyDateExpired($userID) {
		if(get_the_author_meta( 'wineClubProcesManullyTillDate', $userID ) < date('Y-m-d') && get_the_author_meta( 'wineClubProcesManullyTillDate', $userID )) {
			update_user_meta($userID, 'wineClubProcesManullyTillDate', null);
			update_user_meta($userID, 'wineClubProcesManully', null);
		}
	}


}